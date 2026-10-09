<?php
// Servidor de licenças do Gerador Gold. Recebe {key, domain}, valida e devolve uma resposta ASSINADA.
// Só este servidor tem a chave privada; o script vendido apenas confere a assinatura com a chave pública.
require __DIR__ . '/../inc/boot.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$key = strtoupper(trim((string)($_REQUEST['key'] ?? '')));
$domain = lic_domain((string)($_REQUEST['domain'] ?? ''));
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$build = (string)($_REQUEST['build'] ?? '');  // marca do comprador gravada no pacote (identifica a origem do vazamento)

function lic_reply(string $status, array $extra = []): never
{
    // TTL: por quanto tempo o script pode operar com esta resposta antes de reconsultar / tolerar o servidor fora do ar
    echo json_encode(lic_sign(array_merge(['status' => $status, 'ttl' => 259200, 'grace' => 1209600], $extra)), JSON_UNESCAPED_SLASHES);
    exit;
}

if (!preg_match('/^GG(-[A-Z0-9]{4}){4}$/', $key) || $domain === '') lic_reply('invalid', ['key' => $key, 'domain' => $domain]);

$pdo = db();
$st = $pdo->prepare('SELECT * FROM licenses WHERE lkey = ?');
$st->execute([$key]);
$lic = $st->fetch();
if (!$lic) lic_reply('invalid', ['key' => $key, 'domain' => $domain]);

// Trava por domínio: a licença gruda no primeiro domínio que ativar; depois só vale nele
if ($lic['domain'] === '' && $lic['status'] === 'active') {
    $pdo->prepare('UPDATE licenses SET domain = ? WHERE id = ?')->execute([$domain, $lic['id']]);
    $lic['domain'] = $domain;
}
$pdo->prepare('UPDATE licenses SET last_check = ?, last_ip = ?, checks = checks + 1 WHERE id = ?')->execute([now(), $ip, $lic['id']]);
if ($build !== '' && $lic['buyer_tag'] === '') { $pdo->prepare('UPDATE licenses SET buyer_tag = ? WHERE id = ?')->execute([mb_substr($build, 0, 60), $lic['id']]); }
lic_record_hit($lic, $domain, $ip, $build);  // registra todo domínio/IP que usa esta chave

$status = 'active';
if ($lic['status'] === 'blocked') $status = 'blocked';
elseif ($lic['expires_at'] !== null && $lic['expires_at'] < now()) $status = 'expired';
elseif (lic_domain($lic['domain']) !== $domain) $status = 'domain';

lic_reply($status, ['key' => $key, 'domain' => $domain, 'licensed_to' => $lic['domain'], 'expires' => $lic['expires_at'], 'buyer' => $lic['buyer']]);
