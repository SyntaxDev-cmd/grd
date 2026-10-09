<?php
// Aviso de pagamento do Mercado Pago. Não confia no que chega: busca o pagamento na API com o token do admin e só então libera.
require __DIR__ . '/../inc/boot.php';
$body = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = (string)($_GET['data_id'] ?? $_GET['id'] ?? $body['data']['id'] ?? '');
$type = (string)($_GET['type'] ?? $_GET['topic'] ?? $body['type'] ?? '');
if ($id !== '' && preg_match('/^\d{1,20}$/', $id) && in_array($type, ['payment', ''], true)) {
    $st = db()->prepare("SELECT * FROM orders WHERE mp_id = ? AND status = 'pending'");
    $st->execute([$id]);
    if ($o = $st->fetch()) order_sync($o);
}
http_response_code(200);
echo 'ok';
