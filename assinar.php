<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard(false, true);
$ac = access_state($u);
$pdo = db();
// O Ultra Master assina o plano dele no sistema base; os demais, na plataforma a que pertencem
$bt = billing_tid($u);
$plans = $pdo->query('SELECT * FROM plans WHERE active = 1 AND ' . tenant_where($bt) . ' AND role ' . ($u['role'] === 'ultra' ? "= 'ultra'" : "<> 'ultra'") . ' ORDER BY sort, price')->fetchAll();
// Conta criada por um master ou revenda renova com quem a criou, não com o PIX do sistema
$parent = null;
if ($u['parent_id']) { $st = $pdo->prepare('SELECT username, name, role FROM users WHERE id = ?'); $st->execute([$u['parent_id']]); $parent = $st->fetch() ?: null; }
$viaParent = $parent && !in_array($parent['role'], ['admin', 'ultra'], true); // criado pelo dono da plataforma paga direto pelo PIX dela
$st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 6');
$st->execute([$u['id']]);
$orders = $st->fetchAll();
$stLabel = ['pending' => ['Aguardando pagamento', 'warn'], 'approved' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'mut'], 'expired' => ['Expirado', 'mut']];
$support = trim((string)setting('support_link'));
page_start('Assinar plano', 'assinar', $u);
?>
<?php if ($ac['locked']): ?>
  <div class="alert warn"><b><?= $ac['reason'] === 'expired' ? 'Seu plano venceu.' : ($ac['reason'] === 'trial_limit' ? 'Você usou todos os banners do teste grátis.' : 'Seu teste grátis terminou.') ?></b> Você continua entrando e vendo todos os modelos; para voltar a gerar e baixar, assine um plano.</div>
<?php elseif ($ac['trial']): ?>
  <div class="alert ok">Você está no teste grátis: restam <b><?= $ac['left'] ?></b> banner<?= $ac['left'] === 1 ? '' : 's' ?>, até <?= fmt_date($u['expires_at'], true) ?>. Assine agora e não perca o acesso.</div>
<?php endif; ?>

<?php if (is_admin($u)): ?>
  <div class="card"><div class="empty">O administrador não precisa assinar. Os planos são configurados em <a href="<?= url('/admin/planos.php') ?>">Planos e Pagamentos</a>.</div></div>
<?php elseif ($viaParent): ?>
  <div class="card"><h2>Renovação</h2><p>Sua conta foi criada por <b><?= e($parent['name'] ?: $parent['username']) ?></b>. A renovação do seu plano é feita diretamente com essa pessoa.</p>
  <?php if ($support): ?><a class="btn gold" href="<?= e($support) ?>" target="_blank" rel="noopener">Falar com o suporte</a><?php endif; ?></div>
<?php elseif (!$plans): ?>
  <div class="card"><div class="empty">Nenhum plano disponível no momento.</div></div>
<?php else: ?>
<div class="plans">
  <?php foreach ($plans as $p): $pp = plan_price($p, $u); $mgr = $p['role'] !== 'user'; ?>
    <div class="plan <?= $mgr ? 'plan-mgr' : '' ?>">
      <?php if ($mgr): ?><span class="tag gold"><?= e(ROLES[$p['role']]) ?></span><?php endif; ?>
      <h3><?= e($p['name']) ?></h3>
      <p><?= e($p['descr']) ?></p>
      <?php if ($pp['discount']): ?><s><?= brl($pp['price']) ?></s><?php endif; ?>
      <div class="plan-price"><?= brl($pp['amount']) ?></div>
      <small><?= (int)$p['months'] ?> <?= (int)$p['months'] === 1 ? 'mês' : 'meses' ?> de acesso<?= $pp['discount'] ? ' · ' . $pp['pct'] . '% de desconto por indicação' : '' ?></small>
      <?php if ($mgr): ?><ul><li>Até <?= (int)$p['limit_users'] ?> usuários</li><?php if ($p['role'] !== 'revenda'): ?><li>Até <?= (int)$p['limit_resellers'] ?> revendas</li><?php endif; ?><li><?= $p['role'] === 'ultra' ? 'Gerador com a sua marca, seus planos e seu Mercado Pago' : 'Painel próprio de clientes' ?></li></ul><?php endif; ?>
      <button type="button" class="btn gold wide" data-plan="<?= (int)$p['id'] ?>">Assinar com PIX</button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($orders): ?>
<div class="card" style="margin-top:18px">
  <h2>Meus pedidos</h2>
  <div class="tbl-wrap"><table>
    <tr><th>Data</th><th>Plano</th><th>Valor</th><th>Situação</th></tr>
    <?php foreach ($orders as $o): [$lb, $cl] = $stLabel[$o['status']] ?? [$o['status'], 'mut']; ?>
      <tr><td><?= fmt_date($o['created_at'], true) ?></td><td><?= e($o['plan_name']) ?></td><td><?= brl((int)$o['amount']) ?></td><td><span class="tag <?= $cl ?>"><?= $lb ?></span></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="modal" id="pix" hidden>
  <div class="modal-box" style="text-align:center">
    <h2 id="pxTitle">Pague com PIX</h2>
    <p id="pxSub" style="margin-bottom:14px"></p>
    <img id="pxImg" alt="QR Code do PIX" style="width:240px;max-width:100%;border-radius:12px;background:#fff;padding:8px">
    <label class="fld" style="margin-top:14px;text-align:left"><span>PIX copia e cola</span><textarea id="pxCode" rows="3" readonly style="font-size:12px"></textarea></label>
    <div class="row wrap" style="justify-content:center">
      <button type="button" class="btn gold" id="pxCopy">Copiar código</button>
      <button type="button" class="btn ghost" id="pxClose">Fechar</button>
    </div>
    <p id="pxWait" style="margin:16px 0 0;color:var(--mut);font-size:13px">Aguardando o pagamento… a liberação é automática, não precisa enviar comprovante.</p>
  </div>
</div>
<script>
(function () {
  const $ = s => document.querySelector(s), base = <?= json_encode(BASE) ?>, csrf = <?= json_encode(csrf()) ?>;
  let timer = 0, order = 0;
  const post = async p => {
    const r = await fetch(base + '/api/pay.php', { method: 'POST', headers: { 'X-CSRF': csrf }, body: window.GG_body(p) });
    const j = await r.json().catch(() => ({ error: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }));
    if (!r.ok || j.error) throw new Error(j.error || 'Erro ' + r.status);
    return j;
  };
  const stop = () => { clearInterval(timer); timer = 0; };
  async function check() {
    try {
      const j = await post({ a: 'check', id: order });
      if (j.status === 'approved') { stop(); $('#pxTitle').textContent = 'Pagamento confirmado!'; $('#pxWait').textContent = 'Seu plano foi liberado. Atualizando…'; setTimeout(() => { location.href = base + '/painel.php'; }, 1800); }
      else if (j.status === 'cancelled') { stop(); $('#pxWait').textContent = 'Esta cobrança foi cancelada. Feche e gere uma nova.'; }
    } catch (e) { /* tenta de novo no próximo ciclo */ }
  }
  document.querySelectorAll('[data-plan]').forEach(b => b.addEventListener('click', async () => {
    b.disabled = true;
    try {
      const j = await post({ a: 'create', plan: b.dataset.plan });
      order = j.id;
      $('#pxTitle').textContent = 'Pague com PIX';
      $('#pxSub').textContent = 'Plano ' + j.plan + ' · ' + j.amount;
      $('#pxImg').src = j.img; $('#pxImg').hidden = !j.img;
      $('#pxCode').value = j.code;
      $('#pxWait').textContent = 'Aguardando o pagamento… a liberação é automática, não precisa enviar comprovante.';
      $('#pix').hidden = false;
      stop(); timer = setInterval(check, 5000);
    } catch (e) { alert(e.message); }
    b.disabled = false;
  }));
  $('#pxCopy').addEventListener('click', async ev => {
    try { await navigator.clipboard.writeText($('#pxCode').value); } catch (e) { $('#pxCode').select(); document.execCommand('copy'); }
    ev.target.textContent = 'Código copiado!'; setTimeout(() => { ev.target.textContent = 'Copiar código'; }, 1800);
  });
  $('#pxClose').addEventListener('click', () => { stop(); $('#pix').hidden = true; });
})();
</script>
<?php page_end();
