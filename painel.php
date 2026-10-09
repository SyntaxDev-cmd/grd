<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
$b = brand_of($u);
$d = days_left($u);
$pdo = db();
$cnt = function (string $where) use ($pdo, $u) { $s = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE user_id = ? AND $where"); $s->execute([$u['id']]); return (int)$s->fetchColumn(); };
$stats = [
    ['brush', 'Artes criadas', $cnt("kind IN ('movie', 'promo')")],
    ['play', 'Vídeos gerados', $cnt("kind = 'video'")],
    ['ball', 'Tabelas de jogos', $cnt("kind = 'games'")],
    ['down', 'Downloads', (int)$u['banners']],
];
$site = (string)setting('site_name');
$sl = (string)setting('site_logo', '');
$logo = $sl !== '' && is_file(ROOT . '/uploads/' . $sl) ? url('/uploads/' . $sl) : '';
$feats = array_slice(array_filter(array_map('trim', explode("\n", (string)setting('dash_features', DASH_FEATURES)))), 0, 6);
$plan = is_admin($u) ? 'Administrador' : (can_manage($u) ? ROLES[$u['role']] : 'Plano ativo');
// Faixa "Novo": banners do admin; sem nenhum, quatro atalhos padrão
$news = array_map(fn($r) => [$r['title'], $r['url'] !== '' ? (str_starts_with($r['url'], '/') ? url($r['url']) : $r['url']) : '',
    $r['image'] && is_file(ROOT . '/uploads/' . $r['image']) ? url('/uploads/' . $r['image']) : ''], library('dash'));
if (!$news) $news = [['Novos banners VOD', url('/filmes.php'), ''], ['Novas tabelas de futebol', url('/jogos.php'), ''], ['Novos modelos de divulgação', url('/divulgacao.php'), ''], ['Novos vídeos', url('/motion.php'), '']];
$first = !empty($_SESSION['first_login']);
unset($_SESSION['first_login']);
$channel = trim((string)setting('channel_link', ''));
page_start('Início', 'painel', $u);
?>
<div class="dash-hero">
  <div class="dh-brand">
    <small>Bem-vindo(a) ao</small>
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($site) ?>"><?php else: ?><h2><?= e($site) ?></h2><?php endif; ?>
  </div>
  <p class="dh-tag"><?= nl2br(e((string)setting('dash_tagline', "Crie conteúdos profissionais\nem poucos cliques."))) ?></p>
  <div class="dh-plan">
    <div class="dh-plan-h"><?= icon('crown') ?><div><b><?= e($plan) ?></b><span><?= $d === null ? 'Sem data de vencimento' : ($d < 0 ? 'Plano expirado' : 'Válido até ' . fmt_date($u['expires_at']) . ' · ' . $d . ' dia' . ($d === 1 ? '' : 's')) ?></span></div></div>
    <ul><?php foreach ($feats as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
    <?php if ($d !== null && $d <= 7 && ($l = trim((string)setting('support_link')))): ?><a class="btn gold sm" href="<?= e($l) ?>" target="_blank" rel="noopener">Renovar plano</a><?php endif; ?>
  </div>
</div>

<div class="dash-new">
  <div class="dn-head"><?= icon('rocket') ?><div><b><span>Novo</span> no <?= e($site) ?></b><small><?= e(models_claim()) ?> — confira!</small></div></div>
  <div class="dn-cards">
    <?php foreach ($news as $i => [$t, $href, $img]): ?>
      <a <?= $href !== '' ? 'href="' . e($href) . '"' . (preg_match('#^https?://#i', $href) ? ' target="_blank" rel="noopener"' : '') : '' ?> class="dn-card dn-<?= $i % 4 ?>" <?= $img ? 'style="background-image:url(\'' . e($img) . '\')"' : '' ?>><span><?= e($t) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="stats">
  <?php foreach ($stats as [$ic, $label, $val]): ?>
    <div class="stat stat-ic"><span><?= icon($ic) ?></span><div><b><?= $val ?></b><span><?= $label ?></span></div></div>
  <?php endforeach; ?>
</div>
<?php if (!$b['logoUrl']): ?>
  <div class="alert warn">Você ainda não enviou sua logo. <a href="<?= url('/marca.php') ?>">Configure sua marca</a>: as cores dos banners se ajustam sozinhas a ela.</div>
<?php endif; ?>
<?php if ($first): ?>
<div class="modal" id="welcome">
  <div class="modal-box">
    <h2>Bem-vindo(a), <?= e($u['name'] ?: $u['username']) ?>!</h2>
    <p><?= nl2br(e((string)setting('welcome_panel', WELCOME_PANEL))) ?></p>
    <div class="row wrap">
      <?php if ($channel !== ''): ?><a class="btn gold" href="<?= e($channel) ?>" target="_blank" rel="noopener">Entrar no canal de novidades</a><?php endif; ?>
      <a class="btn" href="<?= url('/marca.php') ?>">Enviar minha logo</a>
      <button type="button" class="btn ghost" onclick="document.getElementById('welcome').remove()">Começar</button>
    </div>
  </div>
</div>
<?php elseif ($channel !== ''): ?>
  <div class="alert ok" style="background:rgba(245,197,24,.08);border-color:rgba(245,197,24,.3);color:var(--txt)">Novidades, modelos novos e tutoriais no nosso canal. <a href="<?= e($channel) ?>" target="_blank" rel="noopener"><b>Entrar no canal</b></a></div>
<?php endif; ?>
<div class="card">
  <div class="row" style="margin-bottom:14px">
    <h2 style="margin:0">Destaques da Semana <small>clique numa capa para gerar o banner</small></h2><span class="sp"></span>
    <a class="btn sm" href="<?= url('/filmes.php') ?>">Ver todos</a>
  </div>
  <div class="hl-grid" id="hl"><div class="empty" style="grid-column:1/-1">Carregando destaques…</div></div>
</div>
<div class="card">
  <div class="row" style="margin-bottom:14px">
    <h2 style="margin:0">Jogos de Hoje</h2><span class="sp"></span>
    <a class="btn gold sm" href="<?= url('/jogos.php') ?>">Gerar banner dos jogos</a>
  </div>
  <div class="hl-games" id="hg"><div class="empty">Carregando jogos…</div></div>
</div>
<script>
(function () {
  const base = <?= json_encode(BASE) ?>, hl = document.getElementById('hl'), hg = document.getElementById('hg');
  const get = p => fetch(base + p).then(r => r.json());
  const empty = (el, msg) => { el.innerHTML = '<div class="empty" style="grid-column:1/-1"></div>'; el.firstChild.textContent = msg; };
  get('/api/tmdb.php?a=list&cat=trending_all').then(j => {
    if (!j.items || !j.items.length) return empty(hl, j.error || 'Nenhum destaque disponível agora.');
    hl.innerHTML = '';
    j.items.slice(0, 12).forEach(it => {
      const a = document.createElement('a');
      a.href = base + '/filmes.php?t=' + it.type + '&id=' + it.id;
      a.title = 'Gerar banner de ' + it.title;
      const img = new Image(); img.loading = 'lazy'; img.alt = it.title; img.src = 'https://image.tmdb.org/t/p/w342' + it.poster;
      const b = document.createElement('b'); b.textContent = it.title;
      const s = document.createElement('small'); s.textContent = (it.type === 'tv' ? 'Série' : 'Filme') + (it.rating > 0 ? ' · ★ ' + it.rating.toFixed(1) : '');
      a.append(img, b, s); hl.appendChild(a);
    });
  }).catch(() => empty(hl, 'Não foi possível carregar os destaques.'));
  get('/api/games.php?day=0').then(j => {
    if (!j.games || !j.games.length) return empty(hg, j.error || 'Nenhum jogo encontrado para hoje.');
    hg.innerHTML = '';
    j.games.slice(0, 8).forEach(g => {
      const r = document.createElement('a'); r.href = base + '/jogos.php';
      const t = document.createElement('time'); t.textContent = g.time;
      const m = document.createElement('b'); m.textContent = g.t1 + ' x ' + g.t2;
      const c = document.createElement('small'); c.textContent = [g.comp, g.channels.join(', ')].filter(Boolean).join(' · ');
      r.append(t, m, c); hg.appendChild(r);
    });
    if (j.games.length > 8) { const more = document.createElement('small'); more.textContent = '+ ' + (j.games.length - 8) + ' jogos'; more.style.padding = '8px 4px'; hg.appendChild(more); }
  }).catch(() => empty(hg, 'Não foi possível carregar os jogos.'));
})();
</script>
<?php page_end();
