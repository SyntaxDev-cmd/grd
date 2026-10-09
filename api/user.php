<?php
require __DIR__ . '/../inc/boot.php';
$me = guard(true);
if (!is_post()) json_out(['error' => 'Método inválido.'], 405);
csrf_check(true);

$a = $_POST['a'] ?? '';
$kind = in_array($_POST['kind'] ?? '', ['games', 'promo'], true) ? $_POST['kind'] : 'movie';

function presets(int $uid, string $kind): array
{
    $st = db()->prepare('SELECT id, name, data FROM presets WHERE user_id = ? AND kind = ? ORDER BY name COLLATE NOCASE');
    $st->execute([$uid, $kind]);
    return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'data' => json_decode($r['data'], true)], $st->fetchAll());
}

if ($a === 'preset_save') {
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 40);
    $data = json_decode((string)($_POST['data'] ?? ''), true);
    if ($name === '' || !is_array($data) || strlen((string)$_POST['data']) > 8000) json_out(['error' => 'Dados inválidos.'], 400);
    $pdo = db();
    $st = $pdo->prepare('SELECT COUNT(*) FROM presets WHERE user_id = ?');
    $st->execute([$me['id']]);
    if ((int)$st->fetchColumn() >= 60) json_out(['error' => 'Limite de 60 estilos salvos atingido.'], 400);
    $pdo->prepare('DELETE FROM presets WHERE user_id = ? AND kind = ? AND name = ?')->execute([$me['id'], $kind, $name]);
    $pdo->prepare('INSERT INTO presets (user_id, kind, name, data, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$me['id'], $kind, $name, json_encode($data, JSON_UNESCAPED_UNICODE), now()]);
    json_out(['presets' => presets((int)$me['id'], $kind)]);
}

if ($a === 'preset_delete') {
    db()->prepare('DELETE FROM presets WHERE id = ? AND user_id = ?')->execute([(int)($_POST['id'] ?? 0), $me['id']]);
    json_out(['presets' => presets((int)$me['id'], $kind)]);
}

// Imagem de fundo enviada direto do editor de jogos (substitui a anterior na marca do usuário)
if ($a === 'bg_upload') {
    if (is_expired($me)) json_out(['error' => 'Seu plano expirou.'], 402);
    try {
        $name = save_upload($_FILES['bg'] ?? [], 1920, false);
    } catch (RuntimeException $ex) {
        json_out(['error' => $ex->getMessage()], 400);
    }
    $b = json_decode($me['brand'] ?: '{}', true) ?: [];
    delete_upload((string)($b['bgImage'] ?? ''));
    $b['bgImage'] = $name;
    db()->prepare('UPDATE users SET brand = ? WHERE id = ?')->execute([json_encode($b, JSON_UNESCAPED_UNICODE), $me['id']]);
    json_out(['url' => url('/uploads/' . $name)]);
}

if ($a === 'log') {
    $n = max(1, min(60, (int)($_POST['n'] ?? 1)));
    // Conta em teste: só gera enquanto houver banners do teste
    $ac = access_state($me);
    if ($ac['trial'] && $n > $ac['left']) {
        json_out(['error' => $ac['left'] > 0 ? "Seu teste grátis só tem mais {$ac['left']} banner" . ($ac['left'] === 1 ? '' : 's') . ". Reduza a quantidade ou assine um plano." : 'Você usou todos os banners do teste grátis. Assine um plano para continuar.', 'locked' => true], 402);
    }
    $layout = preg_replace('/[^a-z]/', '', (string)($_POST['layout'] ?? ''));
    $format = preg_replace('/[^a-z]/', '', (string)($_POST['format'] ?? ''));
    $pdo = db();
    $pdo->prepare('INSERT INTO logs (user_id, kind, layout, format, title, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$me['id'], ($_POST['kind'] ?? '') === 'video' ? 'video' : $kind, $layout, $format, mb_substr((string)($_POST['title'] ?? ''), 0, 80), now()]);
    $pdo->prepare('UPDATE users SET banners = banners + ? WHERE id = ?')->execute([$n, $me['id']]);
    json_out(['ok' => true]);
}

json_out(['error' => 'Ação inválida.'], 400);
