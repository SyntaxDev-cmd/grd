<?php
/*
 * Cliente de licença — embarcado no script vendido. Não existe no Gerador Gold (sistema base).
 * Só roda quando inc/license_config.php está presente (criado pelo empacotador).
 * Confere a resposta ASSINADA do servidor com a chave PÚBLICA embutida: mesmo com todo o código em mãos,
 * o comprador não forja uma licença válida porque não tem a chave privada (que fica só no Gerador Gold).
 */
if (defined('GG_LICENSE_LOADED')) return;
define('GG_LICENSE_LOADED', 1);

function lic_cfg(string $k)
{
    static $c = null;
    if ($c === null) { $c = is_file(__DIR__ . '/license_config.php') ? (require __DIR__ . '/license_config.php') : []; }
    return $c[$k] ?? null;
}
function lic_is_build(): bool { return is_file(__DIR__ . '/license_config.php'); }

function lic_host(): string
{
    $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $h = explode(':', $h)[0];
    return (string)preg_replace('#^www\.#', '', $h);
}

/** Confere a assinatura e devolve o payload, ou null se a assinatura não bater com a chave pública. */
function lic_verify(string $payload, string $sig): ?array
{
    $pub = (string)lic_cfg('pubkey');
    if ($pub === '' || $payload === '') return null;
    if (openssl_verify($payload, base64_decode($sig), $pub, OPENSSL_ALGO_SHA256) !== 1) return null;
    $j = json_decode($payload, true);
    return is_array($j) ? $j : null;
}

function lic_cache_path(): string { return (defined('DATA') ? DATA : __DIR__ . '/../data') . '/license.lic'; }

function lic_load_cache(): ?array
{
    $raw = @file_get_contents(lic_cache_path());
    if ($raw === false) return null;
    $j = json_decode($raw, true);
    if (!is_array($j) || empty($j['payload'])) return null;
    $p = lic_verify((string)$j['payload'], (string)($j['sig'] ?? ''));
    return $p ? ['p' => $p, 'key' => $j['key'] ?? ($p['key'] ?? '')] : null; // assinatura inválida => ignora o cache
}

function lic_save_cache(string $payload, string $sig, string $key): void
{
    @file_put_contents(lic_cache_path(), json_encode(['payload' => $payload, 'sig' => $sig, 'key' => $key]), LOCK_EX);
}

/** Consulta o servidor do Gerador Gold. Devolve o par assinado {payload, sig} ou null se não respondeu. */
function lic_ask(string $key, string $domain): ?array
{
    $ch = curl_init((string)lic_cfg('server'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_POSTFIELDS => http_build_query(['key' => $key, 'domain' => $domain, 'build' => (string)lic_cfg('build')]), CURLOPT_USERAGENT => 'GG-License/1',
    ]);
    $body = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    if (!$ok || !$body) return null;
    $j = json_decode((string)$body, true);
    if (!is_array($j) || empty($j['payload']) || !lic_verify((string)$j['payload'], (string)($j['sig'] ?? ''))) return null;
    return $j;
}

/** Decisão de licença para o domínio atual. Devolve ['ok'=>bool, 'reason'=>..., 'key'=>...]. */
function lic_state(): array
{
    $domain = lic_host();
    $cache = lic_load_cache();
    $key = $cache['key'] ?? '';
    $now = time();

    // Com cache válido e ainda "fresco" (dentro do TTL), confia sem bater no servidor a cada acesso
    if ($cache) {
        $p = $cache['p'];
        $fresh = ($p['issued'] ?? 0) + (int)($p['ttl'] ?? 0) > $now;
        if ($fresh) return lic_eval($p, $domain, $key);
    }

    // Precisa reconsultar o servidor (ou nunca ativou)
    if ($key !== '') {
        $resp = lic_ask($key, $domain);
        if ($resp) {
            lic_save_cache((string)$resp['payload'], (string)$resp['sig'], $key);
            return lic_eval(json_decode((string)$resp['payload'], true), $domain, $key);
        }
        // Servidor fora do ar: tolera o cache dentro do prazo de carência
        if ($cache) {
            $p = $cache['p'];
            if (($p['issued'] ?? 0) + (int)($p['grace'] ?? 0) > $now) { $r = lic_eval($p, $domain, $key); $r['grace'] = true; return $r; }
            return ['ok' => false, 'reason' => 'offline', 'key' => $key];
        }
    }
    return ['ok' => false, 'reason' => 'need_key', 'key' => $key];
}

function lic_eval(array $p, string $domain, string $key): array
{
    $st = $p['status'] ?? 'invalid';
    if ($st !== 'active') return ['ok' => false, 'reason' => $st, 'key' => $key];
    if (lic_host_eq((string)($p['domain'] ?? ''), $domain)) return ['ok' => true, 'reason' => 'active', 'key' => $key, 'p' => $p];
    return ['ok' => false, 'reason' => 'domain', 'key' => $key, 'p' => $p];
}
function lic_host_eq(string $a, string $b): bool { return preg_replace('#^www\.#', '', strtolower($a)) === $b; }

/** Portão: chamado no início do boot do script vendido. Libera, ou mostra a tela de ativação/bloqueio e encerra. */
function license_guard(): void
{
    if (!lic_is_build() || (defined('IS_CLI') && IS_CLI)) return;
    // A própria chamada ao servidor de licença e assets não podem exigir licença
    $self = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($self, '/assets/') || str_contains($self, 'license')) return;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['gg_license_key'])) {
        $key = strtoupper(trim((string)$_POST['gg_license_key']));
        $resp = lic_ask($key, lic_host());
        if ($resp) { lic_save_cache((string)$resp['payload'], (string)$resp['sig'], $key); }
        $loc = ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $loc);
        exit;
    }

    $s = lic_state();
    if ($s['ok']) return;
    lic_screen($s['reason'], lic_host());
}

function lic_screen(string $reason, string $domain): never
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $msgs = [
        'need_key' => ['Ativação necessária', 'Informe a chave de licença recebida na compra para liberar este domínio.<br><br>Depois de ativar, entre com <b>usuário: admin</b> e <b>senha: admin</b> — e troque a senha em seguida.'],
        'domain'   => ['Domínio não autorizado', 'Esta licença está vinculada a outro domínio. Fale com o fornecedor para liberar <b>' . htmlspecialchars($domain) . '</b>.'],
        'blocked'  => ['Licença bloqueada', 'Esta licença foi bloqueada. Entre em contato com o fornecedor.'],
        'expired'  => ['Licença expirada', 'O período desta licença terminou. Renove com o fornecedor para continuar.'],
        'invalid'  => ['Licença inválida', 'A chave informada não foi reconhecida. Verifique e tente de novo.'],
        'offline'  => ['Não foi possível validar', 'O servidor de licenças não respondeu e o prazo de carência terminou. Tente novamente em instantes.'],
    ];
    [$title, $text] = $msgs[$reason] ?? $msgs['invalid'];
    $showForm = in_array($reason, ['need_key', 'invalid', 'domain'], true);
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $title ?></title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#08080c;color:#f2f2f5;font:15px/1.5 system-ui,sans-serif;padding:20px}
    .b{max-width:420px;width:100%;background:#111118;border:1px solid #25252f;border-radius:18px;padding:34px 30px;text-align:center;box-shadow:0 30px 80px -30px #000}
    .ic{width:54px;height:54px;border-radius:14px;margin:0 auto 16px;display:grid;place-items:center;background:rgba(245,197,24,.12);color:#f5c518}
    h1{font-size:20px;margin:0 0 10px}p{color:#9a9aa8;margin:0 0 20px}
    input{width:100%;box-sizing:border-box;background:#0c0c12;border:1px solid #32323f;color:#fff;padding:13px;border-radius:10px;font:600 15px system-ui;text-align:center;letter-spacing:.08em;text-transform:uppercase}
    button{width:100%;margin-top:12px;padding:13px;border:0;border-radius:10px;background:linear-gradient(135deg,#f5c518,#ff9d00);color:#15120a;font:800 15px system-ui;cursor:pointer}
    small{display:block;margin-top:16px;color:#55555f}</style></head><body>
    <form class="b" method="post">
      <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></div>
      <h1><?= $title ?></h1><p><?= $text ?></p>
      <?php if ($showForm): ?><input name="gg_license_key" placeholder="GG-XXXX-XXXX-XXXX-XXXX" autocomplete="off" autofocus><button>Ativar licença</button><?php endif; ?>
      <small>Domínio: <?= htmlspecialchars($domain) ?></small>
    </form></body></html><?php
    exit;
}
