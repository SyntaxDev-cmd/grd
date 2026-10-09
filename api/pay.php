<?php
// Assinatura por PIX (Mercado Pago): cria a cobrança e consulta o pagamento
require __DIR__ . '/../inc/boot.php';
$me = guard(true, true);
if (!is_post()) json_out(['error' => 'Método inválido.'], 405);
csrf_check(true);
$pdo = db();
$a = $_POST['a'] ?? '';

function order_out(array $o): array
{
    return ['id' => (int)$o['id'], 'status' => $o['status'], 'plan' => $o['plan_name'], 'amount' => brl((int)$o['amount']),
        'code' => $o['qr'], 'img' => $o['qr_img'] !== '' ? 'data:image/png;base64,' . $o['qr_img'] : ''];
}

if ($a === 'create') {
    if ($me['role'] === 'admin') json_out(['error' => 'O administrador não precisa assinar.'], 400);
    // O plano precisa ser da plataforma que cobra esta conta (um cliente nunca paga um plano de outra plataforma)
    $bt = billing_tid($me);
    $st = $pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1 AND ' . tenant_where($bt) . ' AND role ' . ($me['role'] === 'ultra' ? "= 'ultra'" : "<> 'ultra'"));
    $st->execute([(int)($_POST['plan'] ?? 0)]);
    $plan = $st->fetch();
    if (!$plan) json_out(['error' => 'Plano indisponível.'], 404);
    $pp = plan_price($plan, $me);

    // Reaproveita a cobrança ainda válida do mesmo plano (evita gerar vários PIX ao clicar de novo)
    $st = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND plan_id = ? AND status = 'pending' AND amount = ? AND qr <> '' AND created_at > ? ORDER BY id DESC LIMIT 1");
    $st->execute([$me['id'], $plan['id'], $pp['amount'], date('Y-m-d H:i:s', time() - 25 * 60)]);
    if ($old = $st->fetch()) json_out(order_out($old));

    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND created_at > ?");
    $st->execute([$me['id'], date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$st->fetchColumn() >= 12) json_out(['error' => 'Muitas cobranças geradas. Aguarde alguns minutos.'], 429);

    $pdo->prepare('INSERT INTO orders (user_id, plan_id, plan_name, price, discount, amount, coupon, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$me['id'], $plan['id'], $plan['name'], $pp['price'], $pp['discount'], $pp['amount'], $pp['coupon'], now()]);
    $oid = (int)$pdo->lastInsertId();

    $email = filter_var($me['email'], FILTER_VALIDATE_EMAIL) ?: 'cliente' . $me['id'] . '@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'example.com');
    [$code, $j] = mp_api('POST', '/v1/payments', [
        'transaction_amount' => round($pp['amount'] / 100, 2),
        'description' => mb_substr(tenant_setting($bt, 'site_name', 'Assinatura') . ' - Plano ' . $plan['name'], 0, 120),
        'payment_method_id' => 'pix',
        'payer' => ['email' => $email, 'first_name' => mb_substr($me['name'] ?: $me['username'], 0, 60)],
        'external_reference' => 'GG-' . $oid,
        'date_of_expiration' => date('Y-m-d\TH:i:s.000P', time() + 30 * 60),
        'notification_url' => site_link() . 'api/mp_webhook.php',
    ], 'gg-order-' . $oid, $bt);
    $td = $j['point_of_interaction']['transaction_data'] ?? [];
    if (!in_array($code, [200, 201], true) || empty($td['qr_code'])) {
        $pdo->prepare("UPDATE orders SET status = 'cancelled', note = ? WHERE id = ?")->execute([mb_substr('Falha ao criar: ' . ($j['message'] ?? 'erro ' . $code), 0, 200), $oid]);
        json_out(['error' => $code === 0 ? $j['message'] : 'Não foi possível gerar o PIX agora. Tente de novo em instantes.'], 502);
    }
    $pdo->prepare('UPDATE orders SET mp_id = ?, qr = ?, qr_img = ? WHERE id = ?')->execute([(string)$j['id'], (string)$td['qr_code'], (string)($td['qr_code_base64'] ?? ''), $oid]);
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$oid]);
    json_out(order_out($st->fetch()));
}

if ($a === 'check') {
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $st->execute([(int)($_POST['id'] ?? 0), $me['id']]);
    $o = $st->fetch();
    if (!$o) json_out(['error' => 'Pedido não encontrado.'], 404);
    json_out(['status' => order_sync($o)]);
}

json_out(['error' => 'Ação inválida.'], 400);
