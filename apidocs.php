<?php
/* Documentação pública da API de Revenda. Sem login e sem segredos — só referência. */
require __DIR__ . '/inc/boot.php';
$site = (string)setting('site_name', 'Gerador');
$base = site_link() . 'api/v1';
$rl = API_RATE_PER_MIN;

/* Fonte única de verdade dos endpoints (também guia os exemplos). */
$endpoints = [
    ['GET', '/me', 'Dados da sua conta e cota disponível.', null,
        '{ "ok": true, "account": { "id": 12, "username": "revenda1", "role": "revenda", "expires_at": "2026-12-31 23:59:59", "can_create_roles": ["user"] }, "quota": { "users_used": 8, "users_max": 50, "users_free": 42 }, "rate_limit_per_min": ' . $rl . ' }'],
    ['GET', '/plans', 'Planos ativos da sua plataforma (para montar suas ofertas).', null,
        '{ "ok": true, "plans": [ { "id": 1, "name": "Mensal", "months": 1, "price_cents": 2990, "price": "R$ 29,90" } ] }'],
    ['GET', '/users', 'Lista as contas que estão abaixo da sua. Aceita ?limit= (até 100), ?offset= e ?q= (busca).', null,
        '{ "ok": true, "total": 8, "limit": 50, "offset": 0, "users": [ { "id": 31, "username": "cliente01", "status": "active", "expires_at": "2026-11-10 23:59:59" } ] }'],
    ['POST', '/users', 'Cria uma conta de cliente. Tudo é opcional: sem usuário/senha, o sistema gera; "trial": true cria um teste (usa os dias de teste da plataforma); senão "months" define o plano.',
        '{ "name": "Cliente Exemplo", "email": "cliente@exemplo.com", "months": 1 }',
        '{ "ok": true, "user": { "id": 32, "username": "clienteexemplo", "status": "active", "expires_at": "2026-11-14 23:59:59", "password": "aB3xk9Qp", "login_url": "' . site_link() . 'login.php" }, "password_generated": true }'],
    ['GET', '/users/{id}', 'Mostra uma conta específica (precisa estar abaixo da sua).', null,
        '{ "ok": true, "user": { "id": 32, "username": "clienteexemplo", "status": "active", "paid": true } }'],
    ['POST', '/users/{id}/renew', 'Renova/estende o acesso. "months" = quantos meses somar ao vencimento atual.',
        '{ "months": 3 }',
        '{ "ok": true, "user": { "id": 32, "expires_at": "2027-02-14 23:59:59", "paid": true } }'],
    ['POST', '/users/{id}/suspend', 'Bloqueia o acesso da conta (ela continua existindo).', null,
        '{ "ok": true, "user": { "id": 32, "status": "blocked" } }'],
    ['POST', '/users/{id}/activate', 'Reativa uma conta bloqueada.', null,
        '{ "ok": true, "user": { "id": 32, "status": "active" } }'],
    ['POST', '/users/{id}/password', 'Redefine a senha. Sem "password", uma senha nova é gerada e devolvida.',
        '{ "password": "novaSenha123" }',
        '{ "ok": true, "user": { "id": 32 }, "password": "novaSenha123", "password_generated": false }'],
    ['DELETE', '/users/{id}', 'Exclui a conta de um cliente. Ação irreversível.', null,
        '{ "ok": true, "deleted": 32 }'],
];

$errs = [
    ['400', 'Requisição malformada.'],
    ['401', 'Chave ausente ou inválida.'],
    ['402', 'Plano da sua conta vencido.'],
    ['403', 'Sem permissão para essa ação/conta.'],
    ['404', 'Recurso ou conta não encontrada.'],
    ['409', 'Conflito: usuário já existe ou cota esgotada.'],
    ['422', 'Dados inválidos (ex.: senha curta, e-mail inválido).'],
    ['429', 'Limite de ' . $rl . ' chamadas/min excedido.'],
];
$mcolor = ['GET' => '#22c55e', 'POST' => '#f5c518', 'DELETE' => '#ff5f6d'];
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>API · <?= $h($site) ?></title>
<style>
:root{--bg:#07070c;--panel:#111119;--line:#23232f;--txt:#f2f2f6;--mut:#9a9aa8;--gold:#f5c518;--gold2:#ff9d00;--r:14px}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--txt);font:15px/1.6 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
a{color:var(--gold);text-decoration:none}a:hover{text-decoration:underline}
code,pre{font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace}
.wrap{display:grid;grid-template-columns:260px 1fr;max-width:1180px;margin:0 auto;min-height:100vh}
aside{position:sticky;top:0;align-self:start;height:100vh;overflow:auto;border-right:1px solid var(--line);padding:26px 20px}
aside .logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;margin-bottom:6px}
aside .logo span.m{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#111;display:grid;place-items:center;font-weight:900}
aside .tag{color:var(--mut);font-size:12px;margin-bottom:20px}
aside nav a{display:block;color:var(--mut);padding:7px 10px;border-radius:9px;font-size:14px}
aside nav a:hover{background:var(--panel);color:var(--txt);text-decoration:none}
aside nav .grp{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#55555f;margin:16px 0 6px;padding:0 10px}
main{padding:40px 44px;max-width:860px}
h1{font-size:30px;margin:0 0 8px}h2{font-size:21px;margin:40px 0 12px;padding-top:10px}
h3{font-size:16px;margin:0}
p{color:#d4d4de}.mut{color:var(--mut)}
.hero{background:linear-gradient(160deg,rgba(245,197,24,.12),transparent 60%);border:1px solid var(--line);border-radius:18px;padding:28px}
.pill{display:inline-block;background:rgba(245,197,24,.14);color:var(--gold);border-radius:999px;padding:3px 12px;font-size:12px;font-weight:700;margin-bottom:12px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:18px 20px;margin:14px 0}
.ep{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.m{font-weight:800;font-size:12px;padding:3px 9px;border-radius:7px;color:#0b0b12}
.path{font-size:15px;font-weight:600}
.copybtn{margin-left:auto;background:#1b1b26;border:1px solid var(--line);color:var(--mut);border-radius:8px;padding:5px 10px;font-size:12px;cursor:pointer}
.copybtn:hover{color:var(--txt)}
pre{background:#0b0b12;border:1px solid var(--line);border-radius:12px;padding:14px 16px;overflow-x:auto;margin:12px 0 0}
pre code{color:#e6e6ef;font-size:13px;line-height:1.6;white-space:pre}
.tabs{display:flex;gap:6px;margin:14px 0 0}
.tabs button{background:#1b1b26;border:1px solid var(--line);color:var(--mut);border-radius:8px 8px 0 0;padding:6px 12px;font-size:13px;cursor:pointer}
.tabs button.on{background:#0b0b12;color:var(--gold);border-bottom-color:#0b0b12}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);font-size:14px}
th{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--mut)}
.k{color:var(--gold)}
.top-actions{margin-top:18px}
.btn{display:inline-block;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#15120a;font-weight:800;border-radius:10px;padding:10px 16px}
@media(max-width:860px){.wrap{grid-template-columns:1fr}aside{position:static;height:auto;border-right:0;border-bottom:1px solid var(--line)}main{padding:28px 20px}}
</style>
</head>
<body>
<div class="wrap">
  <aside>
    <div class="logo"><span class="m"><?= $h(mb_strtoupper(mb_substr($site, 0, 1))) ?></span><?= $h($site) ?></div>
    <div class="tag">API de Revenda · v1</div>
    <nav>
      <div class="grp">Começar</div>
      <a href="#intro">Visão geral</a>
      <a href="#auth">Autenticação</a>
      <a href="#limites">Limites e erros</a>
      <div class="grp">Endpoints</div>
      <?php foreach ($endpoints as $i => $ep): ?><a href="#ep<?= $i ?>"><?= $ep[0] ?> <?= $h($ep[1]) ?></a><?php endforeach; ?>
    </nav>
  </aside>
  <main>
    <section id="intro">
      <div class="hero">
        <span class="pill">API REST · JSON</span>
        <h1>API de Revenda</h1>
        <p>Automatize a criação e a gestão das contas dos seus clientes direto do seu sistema — site de vendas, bot de WhatsApp, planilha ou qualquer linguagem. Tudo respeita a sua cota e hierarquia, igual ao painel.</p>
        <p class="mut" style="margin-bottom:0">Base: <code class="k"><?= $h($base) ?></code></p>
        <div class="top-actions"><a class="btn" href="<?= $h(url('/api.php')) ?>">Pegar minha chave →</a></div>
      </div>
    </section>

    <h2 id="auth">Autenticação</h2>
    <p>Gere sua chave em <a href="<?= $h(url('/api.php')) ?>">API &amp; Integração</a> no seu painel. Envie-a no cabeçalho <code>Authorization</code> em toda chamada. <b>Guarde a chave</b>: ela aparece só uma vez.</p>
    <div class="card">
      <div class="ep"><h3>Cabeçalho</h3><button class="copybtn" data-c="auth-h">Copiar</button></div>
      <pre><code id="auth-h">Authorization: Bearer gg_live_sua_chave_aqui
Content-Type: application/json</code></pre>
    </div>

    <h2 id="limites">Limites e erros</h2>
    <p>Até <b><?= $rl ?> chamadas por minuto</b> por chave. As respostas usam códigos HTTP padrão; erros vêm como <code>{ "ok": false, "error": { "type": "...", "message": "..." } }</code>.</p>
    <table>
      <thead><tr><th>HTTP</th><th>Quando acontece</th></tr></thead>
      <tbody><?php foreach ($errs as $er): ?><tr><td><code class="k"><?= $er[0] ?></code></td><td><?= $h($er[1]) ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2>Endpoints</h2>
    <?php foreach ($endpoints as $i => $ep):
        [$method, $path, $desc, $body, $resp] = $ep;
        $full = $base . $path;
        // Exemplos por linguagem
        $curl = "curl -X $method \"$full\" \\\n  -H \"Authorization: Bearer SUA_CHAVE\"";
        if ($body !== null) $curl .= " \\\n  -H \"Content-Type: application/json\" \\\n  -d '" . $body . "'";
        $php = "<?php\n\$ch = curl_init(\"$full\");\ncurl_setopt_array(\$ch, [\n  CURLOPT_RETURNTRANSFER => true,\n  CURLOPT_CUSTOMREQUEST => \"$method\",\n  CURLOPT_HTTPHEADER => [\"Authorization: Bearer SUA_CHAVE\", \"Content-Type: application/json\"],"
            . ($body !== null ? "\n  CURLOPT_POSTFIELDS => '" . $body . "'," : '') . "\n]);\n\$res = json_decode(curl_exec(\$ch), true);\nprint_r(\$res);";
        $node = "const res = await fetch(\"$full\", {\n  method: \"$method\",\n  headers: { \"Authorization\": \"Bearer SUA_CHAVE\", \"Content-Type\": \"application/json\" },"
            . ($body !== null ? "\n  body: JSON.stringify(" . $body . ")," : '') . "\n});\nconsole.log(await res.json());";
    ?>
    <div class="card" id="ep<?= $i ?>">
      <div class="ep">
        <span class="m" style="background:<?= $mcolor[$method] ?>"><?= $method ?></span>
        <span class="path"><?= $h($path) ?></span>
      </div>
      <p style="margin:10px 0 0"><?= $h($desc) ?></p>
      <div class="tabs" data-tabs="t<?= $i ?>">
        <button class="on" data-t="t<?= $i ?>-curl">cURL</button>
        <button data-t="t<?= $i ?>-php">PHP</button>
        <button data-t="t<?= $i ?>-node">Node.js</button>
      </div>
      <pre data-pane="t<?= $i ?>-curl"><code><?= $h($curl) ?></code></pre>
      <pre data-pane="t<?= $i ?>-php" hidden><code><?= $h($php) ?></code></pre>
      <pre data-pane="t<?= $i ?>-node" hidden><code><?= $h($node) ?></code></pre>
      <p class="mut" style="margin:14px 0 4px;font-size:13px">Resposta</p>
      <pre><code><?= $h($resp) ?></code></pre>
    </div>
    <?php endforeach; ?>

    <h2>Dicas</h2>
    <div class="card">
      <p style="margin:0 0 8px">• Ao criar sem <code>username</code>/<code>password</code>, o sistema gera e devolve — perfeito para liberar acesso automático após o pagamento.</p>
      <p style="margin:0 0 8px">• <code>"trial": true</code> cria uma conta de teste usando os dias de teste da plataforma; sem isso, use <code>"months"</code> para um plano pago.</p>
      <p style="margin:0">• A senha só aparece na resposta da criação e do reset. Se perder, chame <code>/users/{id}/password</code> de novo.</p>
    </div>
    <p class="mut" style="margin-top:30px"><a href="<?= $h(url('/painel.php')) ?>">← Voltar ao painel</a></p>
  </main>
</div>
<script>
document.querySelectorAll('.copybtn').forEach(b => b.addEventListener('click', async () => {
  const el = document.getElementById(b.dataset.c);
  try { await navigator.clipboard.writeText(el.textContent); } catch (e) {}
  const t = b.textContent; b.textContent = 'Copiado!'; setTimeout(() => b.textContent = t, 1400);
}));
document.querySelectorAll('.tabs').forEach(tabs => {
  tabs.querySelectorAll('button').forEach(btn => btn.addEventListener('click', () => {
    tabs.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === btn));
    const group = tabs.dataset.tabs;
    document.querySelectorAll('[data-pane^="' + group + '-"]').forEach(p => p.hidden = (p.dataset.pane !== btn.dataset.t));
  }));
});
</script>
</body>
</html>
