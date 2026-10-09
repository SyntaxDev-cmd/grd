<?php
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$u = guard(false, false, true);
$pdo = db();
$n = now();
$one = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn(); })();
$total = $one("SELECT COUNT(*) FROM users WHERE role = 'user'");
$active = $one("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active' AND (expires_at IS NULL OR expires_at >= ?)", [$n]);
$expired = $one("SELECT COUNT(*) FROM users WHERE role = 'user' AND expires_at IS NOT NULL AND expires_at < ?", [$n]);
$today = $one('SELECT COUNT(*) FROM logs WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
$banners = $one('SELECT COALESCE(SUM(banners), 0) FROM users');
$st = $pdo->prepare("SELECT * FROM users WHERE role = 'user' AND status = 'active' AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? ORDER BY expires_at LIMIT 10");
$st->execute([$n, date('Y-m-d H:i:s', time() + 7 * 86400)]);
$soon = $st->fetchAll();
$logs = $pdo->query('SELECT l.*, u.username FROM logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 10')->fetchAll();
$apis = $pdo->query('SELECT COUNT(*) FROM sports_apis WHERE active = 1')->fetchColumn();
page_start('Visão Geral', 'a-home', $u);
?>
<?php if (is_file(DATA . '/ADMIN_INICIAL.txt')): ?>
  <div class="alert warn">Você ainda está usando a senha inicial do administrador. <a href="<?= url('/conta.php') ?>">Troque a senha agora</a>.</div>
<?php endif; ?>
<div class="stats">
  <div class="stat hl"><b><?= $active ?></b><span>Usuários ativos</span></div>
  <div class="stat"><b><?= $total ?></b><span>Usuários cadastrados</span></div>
  <div class="stat"><b><?= $expired ?></b><span>Planos expirados</span></div>
  <div class="stat"><b><?= $today ?></b><span>Downloads hoje</span></div>
  <div class="stat"><b><?= $banners ?></b><span>Banners gerados</span></div>
  <div class="stat"><b><?= (int)$apis ?></b><span>APIs de jogos ativas</span></div>
</div>
<div class="two" style="align-items:start;gap:18px">
  <div class="card">
    <h2>Vencem nos próximos 7 dias</h2>
    <?php if (!$soon): ?><div class="empty">Nenhum plano vencendo.</div><?php else: ?>
    <table>
      <?php foreach ($soon as $s): ?>
        <tr><td><b><?= e($s['username']) ?></b><br><small><?= e($s['name']) ?></small></td><td><?= fmt_date($s['expires_at']) ?></td><td><?= plan_badge($s) ?></td>
        <td><a class="btn sm" href="<?= url('/admin/usuarios.php?edit=' . $s['id']) ?>">Renovar</a></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Atividade recente</h2>
    <?php if (!$logs): ?><div class="empty">Nenhum banner baixado ainda.</div><?php else: ?>
    <table>
      <?php foreach ($logs as $l): ?>
        <tr><td><b><?= e($l['username'] ?? '—') ?></b><br><small><?= fmt_date($l['created_at'], true) ?></small></td>
        <td><?= e($l['title']) ?><br><small><?= e(LAYOUTS[$l['kind']][$l['layout']] ?? $l['layout']) ?> · <?= e($l['format']) ?></small></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php page_end();
