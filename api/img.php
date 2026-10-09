<?php
// Proxy de imagens (necessário para desenhar imagens externas no canvas sem bloqueio de CORS)
require __DIR__ . '/../inc/boot.php';
guard(true, true);
session_write_close();

function img_fail(int $code): never { http_response_code($code); exit; }

function img_send(string $file, string $mime): never
{
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=604800');
    readfile($file);
    exit;
}

$u = (string)base64_decode(strtr((string)($_GET['i'] ?? ''), '-_', '+/'), true);
$p = parse_url($u);
if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) img_fail(400);

$file = DATA . '/cache/img/' . sha1($u);
if (is_file($file) && is_file($file . '.m') && filemtime($file) > time() - 7 * 86400) {
    img_send($file, (string)file_get_contents($file . '.m'));
}

// Bloqueia destinos internos/privados e fixa o IP resolvido (evita SSRF e DNS rebinding)
$host = $p['host'];
$port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
if (!in_array($port, [80, 443], true)) img_fail(400);
$ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
$ip = null;
foreach ($ips as $cand) {
    if (!filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) img_fail(403);
    $ip ??= $cand;
}
if (!$ip) img_fail(404);

$max = 10 * 1024 * 1024;
$body = '';
$ch = curl_init($u);
curl_setopt_array($ch, [
    CURLOPT_RESOLVE => ["$host:$port:$ip"],
    CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 GeradorGold/1.0',
    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, $max) {
        $body .= $chunk;
        return strlen($body) > $max ? 0 : strlen($chunk);
    },
]);
curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 200 || $body === '') img_fail(404);

$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif'], true)) img_fail(415);

file_put_contents($file, $body, LOCK_EX);
file_put_contents($file . '.m', $mime);
img_send($file, $mime);
