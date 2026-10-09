<?php
// Admin: planos (valores), pagamento por PIX, teste grátis, indicação e pedidos
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard_owner();
$pdo = db();
$tid = brand_tid($me);            // plataforma administrada (null = sistema base)
$tw = tenant_where($tid);         // filtro dos planos desta plataforma
$own = fn(int $planId): bool => (bool)$pdo->query("SELECT 1 FROM plans WHERE id = " . $planId . " AND $tw")->fetchColumn();
$money = fn(string $v): int => (int)round((float)str_replace(',', '.', preg_replace('/[^\d,.]/', '', str_replace('.', '', strpos($v, ',') !== false ? $v : str_replace('.', ',', $v)))) * 100);

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'plan') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 40);
        $price = $money((string)($_POST['price'] ?? '0'));
        // Só o sistema base vende o plano de Ultra Master
        $role = in_array($_POST['role'] ?? '', $tid === null ? ['user', 'revenda', 'master', 'ultra'] : ['user', 'revenda', 'master'], true) ? $_POST['role'] : 'user';
        if ($id && !$own($id)) redirect('/admin/planos.php');
        if ($name === '' || $price < 100) { flash('Informe o nome e um valor de pelo menos R$ 1,00.', 'err'); redirect('/admin/planos.php' . ($id ? '?edit=' . $id : '')); }
        $vals = [$name, mb_substr(trim((string)($_POST['descr'] ?? '')), 0, 120), $role, max(1, min(60, (int)($_POST['months'] ?? 1))), $price,
            $role === 'user' ? 0 : max(0, (int)($_POST['limit_users'] ?? 0)), in_array($role, ['master', 'ultra'], true) ? max(0, (int)($_POST['limit_resellers'] ?? 0)) : 0,
            empty($_POST['active']) ? 0 : 1, (int)($_POST['sort'] ?? 0)];
        if ($id) $pdo->prepare('UPDATE plans SET name = ?, descr = ?, role = ?, months = ?, price = ?, limit_users = ?, limit_resellers = ?, active = ?, sort = ? WHERE id = ?')->execute([...$vals, $id]);
        else $pdo->prepare('INSERT INTO plans (name, descr, role, months, price, limit_users, limit_resellers, active, sort, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$vals, $tid]);
        flash('Plano salvo.');
    } elseif ($a === 'plan_del' && $own($id)) {
        // Plano com pedidos não é apagado (o histórico depende dele): só sai de venda
        $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE plan_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn()) { $pdo->prepare('UPDATE plans SET active = 0 WHERE id = ?')->execute([$id]); flash('O plano já tem pedidos, então foi apenas desativado.'); }
        else { $pdo->prepare('DELETE FROM plans WHERE id = ?')->execute([$id]); flash('Plano excluído.'); }
    } elseif ($a === 'settings') {
        $tok = trim((string)($_POST['mp_token'] ?? ''));
        if ($tok !== '') set_setting('mp_token', $tok);
        if (!empty($_POST['mp_clear'])) set_setting('mp_token', '');
        set_setting('allow_register', empty($_POST['allow_register']) ? '0' : '1');
        set_setting('trial_days', (string)max(1, min(30, (int)($_POST['trial_days'] ?? 1))));
        set_setting('trial_limit', (string)max(1, min(1000, (int)($_POST['trial_limit'] ?? 15))));
        set_setting('ref_new_pct', (string)max(0, min(90, (int)($_POST['ref_new_pct'] ?? 10))));
        set_setting('ref_owner_pct', (string)max(0, min(90, (int)($_POST['ref_owner_pct'] ?? 10))));
        flash('Configurações de pagamento salvas.');
    } elseif ($a === 'mp_test') {
        [$code, $j] = mp_api('GET', '/v1/payment_methods', null, '', $tid);
        if ($code === 200) flash('Mercado Pago respondeu: o token é válido' . (in_array('pix', array_column($j, 'id'), true) ? ' e o PIX está disponível na conta.' : ', mas o PIX não aparece entre os meios de pagamento da conta. Cadastre uma chave PIX no Mercado Pago.'));
        else flash('O Mercado Pago recusou o token (' . ($code ?: 'sem conexão') . '): ' . mb_substr((string)($j['message'] ?? ''), 0, 120), 'err');
    } elseif ($a === 'approve' || $a === 'sync' || $a === 'cancel') {
        // Só pedidos de planos desta plataforma
        $st = $pdo->prepare("SELECT o.* FROM orders o JOIN plans p ON p.id = o.plan_id WHERE o.id = ? AND " . tenant_where($tid, 'p.tenant_id'));
        $st->execute([$id]);
        if ($o = $st->fetch()) {
            if ($a === 'approve') flash(order_activate($id, 'Liberado manualmente por ' . $me['username']) ? 'Pedido marcado como pago e plano liberado.' : 'Este pedido não está mais pendente.', 'ok');
            elseif ($a === 'cancel') { $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$id]); flash('Pedido cancelado.'); }
            else flash(['approved' => 'Pagamento confirmado no Mercado Pago: plano liberado.', 'cancelled' => 'O Mercado Pago informa que a cobrança foi cancelada.'][order_sync($o)] ?? 'O Mercado Pago ainda não registra o pagamento deste pedido.');
        }
    }
    redirect('/admin/planos.php');
}

$edit = null;
if (isset($_GET['edit'])) { $st = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND $tw"); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
$plans = $pdo->query("SELECT * FROM plans WHERE $tw ORDER BY sort, price")->fetchAll();
$ow = tenant_where($tid, 'p.tenant_id');
$orders = $pdo->query("SELECT o.*, u.username FROM orders o JOIN plans p ON p.id = o.plan_id LEFT JOIN users u ON u.id = o.user_id WHERE $ow ORDER BY o.id DESC LIMIT 40")->fetchAll();
$sum = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(o.amount), 0) t FROM orders o JOIN plans p ON p.id = o.plan_id WHERE o.status = 'approved' AND $ow")->fetch();
$month = $pdo->prepare("SELECT COALESCE(SUM(o.amount), 0) FROM orders o JOIN plans p ON p.id = o.plan_id WHERE o.status = 'approved' AND $ow AND o.paid_at >= ?");
$month->execute([date('Y-m-01 00:00:00')]);
$hasTok = trim((string)setting('mp_token', '')) !== '';
$stLabel = ['pending' => ['Aguardando', 'warn'], 'approved' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'mut']];
page_start('Planos e Pagamentos', 'a-plans', $me);
?>
<?php if (!$hasTok): ?><div class="alert warn">O PIX ainda não está ligado: falta o token do Mercado Pago (abaixo). Enquanto isso, as assinaturas só podem ser liberadas manualmente.</div><?php endif; ?>
<div class="stats">
  <div class="stat hl"><b><?= brl((int)$month->fetchColumn()) ?></b><span>Recebido neste mês</span></div>
  <div class="stat"><b><?= brl((int)$sum['t']) ?></b><span>Recebido no total</span></div>
  <div class="stat"><b><?= (int)$sum['n'] ?></b><span>Assinaturas pagas</span></div>
</div>

<div class="two" style="align-items:start;gap:18px">
  <div>
    <div class="card">
      <h2>Planos <small>os valores podem ser alterados a qualquer momento</small></h2>
      <div class="tbl-wrap"><table>
        <tr><th>Plano</th><th>Tipo</th><th>Duração</th><th>Valor</th><th></th></tr>
        <?php foreach ($plans as $p): ?>
          <tr>
            <td><b><?= e($p['name']) ?></b><?= $p['active'] ? '' : ' <span class="tag mut">fora de venda</span>' ?><br><small><?= e($p['descr']) ?></small></td>
            <td><?= e(ROLES[$p['role']]) ?><?= $p['role'] !== 'user' ? '<br><small>' . (int)$p['limit_users'] . ' usuários' . (in_array($p['role'], ['master', 'ultra'], true) ? ' · ' . (int)$p['limit_resellers'] . ' revendas' : '') . '</small>' : '' ?></td>
            <td><?= (int)$p['months'] ?> <?= (int)$p['months'] === 1 ? 'mês' : 'meses' ?></td>
            <td><b><?= brl((int)$p['price']) ?></b></td>
            <td style="white-space:nowrap;text-align:right"><a class="btn sm" href="<?= url('/admin/planos.php?edit=' . $p['id']) ?>">Editar</a>
              <form method="post" onsubmit="return confirm('Remover o plano <?= e($p['name']) ?>?')"><?= csrf_field() ?><input type="hidden" name="a" value="plan_del"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn sm bad">Excluir</button></form></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    </div>
    <form class="card" method="post">
      <?= csrf_field() ?><input type="hidden" name="a" value="plan"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <h2><?= $edit ? 'Editar plano ' . e($edit['name']) : 'Novo plano' ?></h2>
      <div class="two">
        <label class="fld"><span>Nome</span><input type="text" name="name" required maxlength="40" value="<?= e($edit['name'] ?? '') ?>"></label>
        <label class="fld"><span>Valor (R$)</span><input type="text" name="price" required inputmode="decimal" value="<?= $edit ? number_format($edit['price'] / 100, 2, ',', '.') : '' ?>" placeholder="29,90"></label>
        <label class="fld"><span>Duração (meses)</span><input type="number" name="months" min="1" max="60" value="<?= (int)($edit['months'] ?? 1) ?>"></label>
        <label class="fld"><span>Tipo de conta que o plano libera</span>
          <select name="role" id="prole"><?php foreach (['user' => 'Usuário', 'revenda' => 'Revenda', 'master' => 'Master'] + ($tid === null ? ['ultra' => 'Ultra Master (plataforma própria)'] : []) as $k => $lb): ?><option value="<?= $k ?>" <?= ($edit['role'] ?? 'user') === $k ? 'selected' : '' ?>><?= $lb ?></option><?php endforeach; ?></select></label>
        <label class="fld" data-r="revenda master ultra"><span>Limite de usuários</span><input type="number" name="limit_users" min="0" value="<?= (int)($edit['limit_users'] ?? 20) ?>"></label>
        <label class="fld" data-r="master ultra"><span>Limite de revendas</span><input type="number" name="limit_resellers" min="0" value="<?= (int)($edit['limit_resellers'] ?? 5) ?>"></label>
      </div>
      <label class="fld"><span>Descrição curta</span><input type="text" name="descr" maxlength="120" value="<?= e($edit['descr'] ?? '') ?>"></label>
      <div class="row wrap">
        <label class="fld" style="max-width:120px;margin:0"><span>Ordem</span><input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>"></label>
        <label class="c-check"><input type="checkbox" name="active" value="1" <?= !$edit || $edit['active'] ? 'checked' : '' ?>><i></i><span>À venda</span></label>
        <span class="sp"></span>
        <?php if ($edit): ?><a class="btn ghost" href="<?= url('/admin/planos.php') ?>">Cancelar</a><?php endif; ?>
        <button class="btn gold">Salvar plano</button>
      </div>
    </form>
  </div>
  <div>
    <form class="card" method="post" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="a" value="settings">
      <h2>Mercado Pago (PIX)</h2>
      <label class="fld"><span>Access Token de produção <?= $hasTok ? '— já configurado' : '' ?></span><input type="password" name="mp_token" autocomplete="new-password" placeholder="<?= $hasTok ? 'Deixe em branco para manter o atual' : 'APP_USR-…' ?>">
        <em>Em mercadopago.com.br → Seu negócio → Configurações → Credenciais → Credenciais de produção. A conta precisa ter uma chave PIX cadastrada. O token fica guardado só no servidor e não é exibido de novo.</em></label>
      <?php if ($hasTok): ?><label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="mp_clear" value="1"><i></i><span>Remover o token (desliga o PIX)</span></label><?php endif; ?>
      <h2 style="margin-top:18px">Cadastro e teste grátis</h2>
      <label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="allow_register" value="1" <?= setting('allow_register') === '1' ? 'checked' : '' ?>><i></i><span>Permitir que qualquer pessoa crie conta</span></label>
      <div class="two">
        <label class="fld"><span>Dias de teste</span><input type="number" name="trial_days" min="1" max="30" value="<?= e(setting('trial_days', '1')) ?>"></label>
        <label class="fld"><span>Banners no teste</span><input type="number" name="trial_limit" min="1" max="1000" value="<?= e(setting('trial_limit', '15')) ?>"></label>
      </div>
      <h2 style="margin-top:8px">Indicação</h2>
      <div class="two">
        <label class="fld"><span>Desconto de quem foi indicado (%)</span><input type="number" name="ref_new_pct" min="0" max="90" value="<?= e(setting('ref_new_pct', '10')) ?>"><em>Na primeira assinatura.</em></label>
        <label class="fld"><span>Desconto de quem indicou (%)</span><input type="number" name="ref_owner_pct" min="0" max="90" value="<?= e(setting('ref_owner_pct', '10')) ?>"><em>Um cupom por indicado que assinar.</em></label>
      </div>
      <button class="btn gold">Salvar</button>
    </form>
    <?php if ($hasTok): ?><form method="post" style="margin:-6px 0 18px"><?= csrf_field() ?><input type="hidden" name="a" value="mp_test"><button class="btn">Testar o token do Mercado Pago</button></form><?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Pedidos recentes</h2>
  <?php if (!$orders): ?><div class="empty">Nenhum pedido ainda.</div><?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>#</th><th>Data</th><th>Usuário</th><th>Plano</th><th>Valor</th><th>Situação</th><th></th></tr>
    <?php foreach ($orders as $o): [$lb, $cl] = $stLabel[$o['status']] ?? [$o['status'], 'mut']; ?>
      <tr>
        <td><?= (int)$o['id'] ?></td><td><small><?= fmt_date($o['created_at'], true) ?></small></td><td><b><?= e($o['username'] ?? '—') ?></b></td>
        <td><?= e($o['plan_name']) ?><?= $o['discount'] ? '<br><small>desconto ' . brl((int)$o['discount']) . ' (' . e($o['coupon']) . ')</small>' : '' ?></td>
        <td><?= brl((int)$o['amount']) ?></td>
        <td><span class="tag <?= $cl ?>"><?= $lb ?></span><?= $o['note'] ? '<br><small>' . e($o['note']) . '</small>' : '' ?></td>
        <td style="white-space:nowrap;text-align:right">
          <?php if ($o['status'] === 'pending'): ?>
            <?php if ($o['mp_id'] !== ''): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="sync"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn sm">Conferir no MP</button></form><?php endif; ?>
            <form method="post" onsubmit="return confirm('Liberar o plano deste pedido sem confirmação do Mercado Pago?')"><?= csrf_field() ?><input type="hidden" name="a" value="approve"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn sm">Marcar como pago</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="cancel"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn sm bad">Cancelar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<script>
(function () {
  const r = document.getElementById('prole');
  const sync = () => document.querySelectorAll('[data-r]').forEach(el => el.hidden = !el.dataset.r.split(' ').includes(r.value));
  r.addEventListener('change', sync); sync();
})();
</script>
<?php page_end();
