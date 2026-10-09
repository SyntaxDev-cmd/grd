<?php
// Conteúdos alimentados pelo admin: tutoriais, cortes virais e códigos de aplicativos
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
$s = (string)($_GET['s'] ?? '');
if (!isset(LIB_SECTIONS[$s]) || $s === 'dash') redirect('/divulgacao.php');
$rows = library($s);
page_start(LIB_SECTIONS[$s], 'divulgacao', $u);
?>
<div class="row" style="margin-bottom:16px">
  <a class="btn sm ghost" href="<?= url('/divulgacao.php') ?>">‹ Divulgação</a>
  <?php if ($s === 'cortes'): ?><span class="sp"></span><a class="btn sm gold" href="<?= url('/motion.php') ?>">Criar um corte no Motion</a><?php endif; ?>
</div>
<?php if (!$rows): ?>
  <div class="card"><div class="empty">Ainda não há conteúdo aqui. <?= is_admin($u) ? 'Adicione em Administração → Conteúdos e Banners.' : 'Volte em breve.' ?></div></div>
<?php else: ?>
<div class="lib">
  <?php foreach ($rows as $r): $img = $r['image'] && is_file(ROOT . '/uploads/' . $r['image']) ? url('/uploads/' . $r['image']) : ''; ?>
    <div class="lib-card">
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><div class="lib-ph"><?= icon($s === 'apps' ? 'code' : 'play') ?></div><?php endif; ?>
      <div class="lib-body">
        <b><?= e($r['title']) ?></b>
        <?php if ($r['descr'] !== ''): ?><p><?= nl2br(e($r['descr'])) ?></p><?php endif; ?>
        <?php if ($r['code'] !== ''): ?>
          <div class="lib-code"><code><?= e($r['code']) ?></code><button type="button" class="btn sm" data-copy="<?= e($r['code']) ?>">Copiar</button></div>
        <?php endif; ?>
        <?php if ($r['url'] !== ''): ?>
          <div class="row" style="margin-top:10px">
            <a class="btn sm gold" href="<?= e($r['url']) ?>" target="_blank" rel="noopener"><?= $s === 'apps' ? 'Abrir link' : 'Assistir' ?></a>
            <button type="button" class="btn sm ghost" data-copy="<?= e($r['url']) ?>">Copiar link</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  const old = b.textContent;
  try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Copiado!'; }
  catch (e) { prompt('Copie:', b.dataset.copy); }
  setTimeout(() => { b.textContent = old; }, 1500);
}));
</script>
<?php endif; ?>
<?php page_end();
