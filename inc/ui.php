<?php

function icon(string $n): string
{
    static $p = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/>',
        'film' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4M3 12h18"/>',
        'ball' => '<circle cx="12" cy="12" r="9"/><path d="m12 7 4.5 3.3-1.7 5.2H9.2l-1.7-5.2zM12 3v4M3.5 9.5l4 .8M20.5 9.5l-4 .8M6.5 19l2.7-3.5M17.5 19l-2.7-3.5"/>',
        'brush' => '<path d="M18.4 2.6a2 2 0 0 1 3 3L11 16l-4 1 1-4z"/><path d="M7 17c0 2-1.5 4-4 4 1-1 1-2 1-3a3 3 0 0 1 3-1z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.500 7 6M16 4.500a3.500 3.500 0 0 1 0 7M18 14c2.500.6 4 2.600 4 6"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',
        'plug' => '<path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.700 1.700 0 0 0 .3 1.800l.1.100a2 2 0 1 1-2.800 2.800l-.1-.1a1.700 1.700 0 0 0-2.800 1.200V21a2 2 0 1 1-4 0v-.1a1.700 1.700 0 0 0-2.900-1.200l-.1.100a2 2 0 1 1-2.800-2.800l.1-.1A1.700 1.700 0 0 0 3.100 14H3a2 2 0 1 1 0-4h.1a1.700 1.700 0 0 0 1.200-2.900l-.1-.1a2 2 0 1 1 2.800-2.800l.1.100a1.700 1.700 0 0 0 2.900-1.200V3a2 2 0 1 1 4 0v.1a1.700 1.700 0 0 0 2.800 1.200l.1-.1a2 2 0 1 1 2.800 2.800l-.1.100a1.700 1.700 0 0 0 1.200 2.900H21a2 2 0 1 1 0 4h-.1a1.700 1.700 0 0 0-1.500 1z"/>',
        'out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'down' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.600 13.500 6.800 4M15.400 6.500l-6.800 4"/>',
        'play' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="m10 9 5 3-5 3z"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.500" cy="8.500" r="1.500"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2.500"/><path d="M11 18h2"/>',
        'school' => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11.500V16c0 1.500 2.700 3 6 3s6-1.500 6-3v-4.500M22 9v6"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'code' => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
        'crown' => '<path d="m3 8 4.500 4L12 5l4.500 7L21 8l-2 11H5z"/>',
        'rocket' => '<path d="M5 15c-1.500 1.500-2 5-2 5s3.500-.5 5-2M9 15l-2-2c1-5 5-10 13-10 0 8-5 12-10 13zM15 9h.01"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.600-6.400M21 3v6h-6"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($p[$n] ?? '') . '</svg>';
}

function head(string $title): void
{
    $v = filemtime(ROOT . '/assets/app.css');
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · <?= e(setting('site_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Archivo+Black&family=Barlow+Condensed:wght@500;600;700;800&family=Bebas+Neue&family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Oswald:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=Teko:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/app.css?v=' . $v) ?>">
<?php $c1 = (string)setting('ui_color1', ''); $c2 = (string)setting('ui_color2', ''); if (is_hex($c1) && is_hex($c2)): ?><style>:root{--gold:<?= $c1 ?>;--gold2:<?= $c2 ?>}</style><?php endif; ?>
<script>
/* Blindagem contra o firewall da hospedagem (mod_security/"modo sob ataque"), que devolve "Forbidden"
   em requisições com token do TMDB, URLs ou emojis. Tudo trafega embrulhado em base64:
   - POST: campos no parâmetro _gg
   - GET:  query string no parâmetro _q (base64url)
   O servidor desembrulha os dois em inc/boot.php. */
window.GG_b64 = function (s) { return btoa(unescape(encodeURIComponent(s))); };
window.GG_b64url = function (s) { return window.GG_b64(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); };
/* Reescreve "/api/x.php?a=1&q=..." em "/api/x.php?_q=<base64url>" (deixa passar o que já estiver embrulhado). */
window.GG_url = function (path) {
  var i = path.indexOf('?');
  if (i < 0) return path;
  var qs = path.slice(i + 1);
  if (qs.indexOf('_q=') === 0) return path;
  return path.slice(0, i) + '?_q=' + window.GG_b64url(qs);
};
/* Monta um corpo de POST embrulhado em _gg a partir de um objeto ou URLSearchParams. */
window.GG_body = function (params) {
  var qs = (params instanceof URLSearchParams) ? params.toString() : new URLSearchParams(params).toString();
  return new URLSearchParams({ _gg: window.GG_b64(qs) });
};
/* Empacota os campos de cada formulário em base64 (_gg) antes de enviar. Arquivos ficam de fora. */
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f || f.tagName !== 'FORM' || f.hasAttribute('data-raw') || f.__gg) return;
  var pairs = [], els = f.elements;
  for (var i = 0; i < els.length; i++) {
    var el = els[i], t = (el.type || '').toLowerCase();
    if (!el.name || el.disabled || el.tagName === 'BUTTON' || t === 'submit' || t === 'file') continue;
    if ((t === 'checkbox' || t === 'radio') && !el.checked) continue;
    if (el.tagName === 'SELECT' && el.multiple) { for (var k = 0; k < el.options.length; k++) if (el.options[k].selected) pairs.push(enc(el.name) + '=' + enc(el.options[k].value)); el.removeAttribute('name'); continue; }
    pairs.push(enc(el.name) + '=' + enc(el.value));
    el.removeAttribute('name'); // evita que o campo original (em texto) também seja enviado
  }
  if (!pairs.length) return;
  function enc(s) { return encodeURIComponent(s); }
  var h = document.createElement('input');
  h.type = 'hidden'; h.name = '_gg';
  h.value = btoa(unescape(encodeURIComponent(pairs.join('&'))));
  f.appendChild(h); f.__gg = 1;
});
</script>
</head>
<?php
}

function page_start(string $title, string $active, array $u, string $bodyClass = ''): void
{
    // Pagou o PIX e fechou a tela antes da confirmação? Na próxima página aberta o pedido pendente é conferido no Mercado Pago
    if (!is_admin($u)) {
        $po = db()->prepare("SELECT * FROM orders WHERE user_id = ? AND status = 'pending' AND mp_id <> '' AND created_at > ? ORDER BY id DESC LIMIT 1");
        $po->execute([$u['id'], date('Y-m-d H:i:s', time() - 2 * 3600)]);
        if (($o = $po->fetch()) && order_sync($o) === 'approved') {
            $fu = db()->prepare('SELECT * FROM users WHERE id = ?');
            $fu->execute([$u['id']]);
            $u = $fu->fetch() ?: $u;
            flash('Pagamento confirmado! Seu plano ' . $o['plan_name'] . ' está ativo.');
        }
    }
    head($title);
    $nav = [
        ['painel', '/painel.php', 'home', 'Painel'],
        ['filmes', '/filmes.php', 'film', 'Filmes e Séries'],
        ['motion', '/motion.php', 'play', 'Motion'],
        ['jogos', '/jogos.php', 'ball', 'Jogos do Dia'],
        ['divulgacao', '/divulgacao.php', 'tag', 'Divulgação'],
        ['marca', '/marca.php', 'brush', 'Minha Marca'],
        ['assinar', '/assinar.php', 'crown', 'Assinar plano'],
        ['conta', '/conta.php', 'user', 'Minha Conta'],
    ];
    $adm = [
        ['a-home', '/admin/', 'chart', 'Visão Geral'],
        ['a-users', '/admin/usuarios.php', 'users', 'Usuários'],
        ['a-lib', '/admin/conteudos.php', 'film', 'Conteúdos e Banners'],
        ['a-plans', '/admin/planos.php', 'tag', 'Planos e Pagamentos'],
        ['a-apis', '/admin/apis.php', 'plug', 'APIs de Jogos'],
        ['api', '/api.php', 'code', 'API de Revenda'],
        ['a-config', '/admin/config.php', 'gear', 'Configurações'],
    ];
    if (is_file(ROOT . '/admin/licencas.php')) $adm[] = ['a-lic', '/admin/licencas.php', 'lock', 'Licenças do Script'];
    $link = fn($n) => '<a class="nav-a' . ($n[0] === $active ? ' on' : '') . '" href="' . url($n[1]) . '">' . icon($n[2]) . '<span>' . $n[3] . '</span></a>';
    $d = days_left($u);
    ?>
<body class="<?= $bodyClass ?>">
<aside class="side" id="side">
  <a class="brand" href="<?= url('/painel.php') ?>"><?php $sl = (string)setting('site_logo', ''); if ($sl !== '' && is_file(ROOT . '/uploads/' . $sl)): ?><img class="brand-logo" src="<?= url('/uploads/' . $sl) ?>" alt="<?= e(setting('site_name')) ?>"><?php else: ?><span class="brand-mark"><?= e(mb_strtoupper(mb_substr((string)setting('site_name'), 0, 1))) ?></span><span><?= e(setting('site_name')) ?></span><?php endif; ?></a>
  <nav>
    <?php foreach ($nav as $n) { if ($n[0] === 'assinar' && is_admin($u)) continue; echo $link($n); } ?>
    <?php if (is_admin($u)): ?>
      <div class="nav-h">Administração</div>
      <?php foreach ($adm as $n) echo $link($n); ?>
    <?php elseif ($u['role'] === 'ultra'): ?>
      <div class="nav-h">Minha plataforma</div>
      <?= $link(['a-plat', '/admin/plataforma.php', 'gear', 'Meu Gerador']) ?>
      <?= $link(['a-users', '/admin/usuarios.php', 'users', 'Clientes']) ?>
      <?= $link(['a-plans', '/admin/planos.php', 'tag', 'Planos e Pagamentos']) ?>
      <?= $link(['a-lib', '/admin/conteudos.php', 'film', 'Conteúdos e Banners']) ?>
      <?= $link(['api', '/api.php', 'plug', 'API & Integração']) ?>
    <?php elseif (can_manage($u)): ?>
      <div class="nav-h"><?= ROLES[$u['role']] ?></div>
      <?= $link(['a-users', '/admin/usuarios.php', 'users', 'Meus Clientes']) ?>
      <?= $link(['api', '/api.php', 'plug', 'API & Integração']) ?>
    <?php endif; ?>
  </nav>
  <div class="side-foot">
    <div class="who">
      <b><?= e($u['name'] ?: $u['username']) ?></b>
      <small><?= is_admin($u) ? 'Administrador' : (can_manage($u) ? ROLES[$u['role']] . ' · ' : '') . (!(int)$u['paid'] && $d !== null && $d >= 0 ? 'Teste grátis · ' : '') . ($d === null ? 'Plano vitalício' : ($d < 0 ? 'Plano expirado' : "Plano: $d dia" . ($d === 1 ? '' : 's'))) ?></small>
    </div>
    <form method="post" action="<?= url('/logout.php') ?>"><?= csrf_field() ?><button class="icon-btn" title="Sair"><?= icon('out') ?></button></form>
  </div>
</aside>
<div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>
<main class="main">
  <header class="top">
    <button class="icon-btn only-m" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu"><?= icon('menu') ?></button>
    <h1><?= e($title) ?></h1>
  </header>
  <?php if ($f = flash()): ?><div class="alert <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>
  <?php $ac = access_state($u); if ($active !== 'assinar' && $ac['locked']): ?>
    <div class="alert warn"><b><?= $ac['reason'] === 'expired' ? 'Seu plano venceu.' : ($ac['reason'] === 'trial_limit' ? 'Você usou todos os banners do teste grátis.' : 'Seu teste grátis terminou.') ?></b> Você pode ver tudo normalmente, mas para gerar e baixar é preciso <a href="<?= url('/assinar.php') ?>"><b>assinar um plano</b></a>.</div>
  <?php elseif ($active !== 'assinar' && $ac['trial']): ?>
    <div class="alert ok">Teste grátis: restam <b><?= $ac['left'] ?></b> banner<?= $ac['left'] === 1 ? '' : 's' ?>, até <?= fmt_date($u['expires_at'], true) ?>. <a href="<?= url('/assinar.php') ?>"><b>Assinar agora</b></a></div>
  <?php endif; ?>
<?php
}

function page_end(): void
{
    echo "</main>\n</body>\n</html>";
}

function plan_badge(array $u): string
{
    if ($u['status'] !== 'active') return '<span class="tag bad">Bloqueado</span>';
    if ($u['role'] === 'admin') return '<span class="tag gold">Admin</span>';
    $d = days_left($u);
    if ($d === null) return '<span class="tag ok">Vitalício</span>';
    if ($d < 0 || is_expired($u)) return '<span class="tag bad">Expirado</span>';
    return '<span class="tag ' . ($d <= 7 ? 'warn' : 'ok') . '">' . $d . ' dia' . ($d === 1 ? '' : 's') . '</span>';
}

function fmt_date(?string $d, bool $time = false): string
{
    return $d ? date($time ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d)) : '—';
}

/* ---------- Painel de estilo do editor (compartilhado entre filmes e jogos) ---------- */

function ctl_color(string $k, string $label): string
{
    return "<label class=\"c-color\"><input type=\"color\" data-st=\"$k\"><span>$label</span></label>";
}
function ctl_check(string $k, string $label): string
{
    return "<label class=\"c-check\"><input type=\"checkbox\" data-st=\"$k\"><i></i><span>$label</span></label>";
}
function ctl_text(string $k, string $label, int $max = 60): string
{
    return "<label class=\"fld\"><span>$label</span><input type=\"text\" data-st=\"$k\" maxlength=\"$max\"></label>";
}
function ctl_select(string $k, string $label, array $opts): string
{
    $h = '';
    $list = array_is_list($opts);
    foreach ($opts as $v => $t) $h .= '<option value="' . e((string)($list ? $t : $v)) . '">' . e($t) . '</option>';
    return "<label class=\"fld\"><span>$label</span><select data-st=\"$k\">$h</select></label>";
}
function ctl_range(string $k, string $label, int $min, int $max): string
{
    return "<label class=\"fld\"><span>$label</span><input type=\"range\" data-st=\"$k\" min=\"$min\" max=\"$max\"></label>";
}

/** Grupo "Modelos" (categorias + miniaturas). Pode ser impresso fora do painel de estilo. */
function editor_models(): void
{
    ?>
    <div class="grp">
      <h3>Modelos <small id="lcount" style="text-transform:none;letter-spacing:0"></small></h3>
      <div class="chips" id="lgroups" style="margin-top:0"></div>
      <div class="thumbs" id="thumbs"></div>
    </div>
<?php
}

function editor_controls(string $kind, bool $withModels = true): void
{
    ?>
  <section class="panel controls" id="controls">
    <div class="grp">
      <h3>Formato</h3>
      <div class="seg" id="formats">
        <button type="button" data-format="story"><b>Stories</b><small>1080×1920</small></button>
        <button type="button" data-format="feed"><b>Feed</b><small>1080×1350</small></button>
        <button type="button" data-format="square"><b>Quadrado</b><small>1080×1080</small></button>
      </div>
    </div>
    <?php if ($withModels) editor_models(); ?>
    <div class="grp">
      <h3>Cores <button type="button" class="link" id="brandColors">usar cores da marca</button></h3>
      <div class="colors">
        <?= ctl_color('primary', 'Destaque') . ctl_color('secondary', 'Secundária') . ctl_color('bg', 'Fundo') . ctl_color('text', 'Texto') ?>
      </div>
      <div class="swatches" id="palettes"></div>
    </div>
    <div class="grp">
      <h3>Textos</h3>
      <?php if ($kind === 'movie'): ?>
        <?= ctl_text('headline', 'Chamada', 30) . ctl_text('rankTitle', 'Título do ranking / catálogo', 30) ?>
      <?php elseif ($kind === 'promo'): ?>
        <?= ctl_text('title', 'Título', 60) . ctl_text('sub', 'Subtítulo', 70) . ctl_text('devTitle', 'Frase dos dispositivos', 50) ?>
      <?php else: ?>
        <?= ctl_text('title', 'Título', 30) ?>
      <?php endif; ?>
      <?= ctl_text('cta', 'Botão (CTA)', 34) . ctl_text('footer', 'Rodapé / contato', 60) ?>
      <div class="two">
        <?= ctl_select('fontTitle', 'Fonte dos títulos', FONTS_TITLE) . ctl_select('fontBody', 'Fonte dos textos', FONTS_BODY) ?>
      </div>
    </div>
    <div class="grp">
      <h3>Elementos</h3>
      <div class="checks">
        <?= ctl_check('showLogo', 'Logo') ?>
        <?php if ($kind === 'promo'): ?>
          <?= ctl_check('showDevices', 'Dispositivos') . ctl_check('showFeatures', 'Destaques') ?>
        <?php elseif ($kind === 'movie'): ?>
          <?= ctl_check('showRating', 'Nota') . ctl_check('showYear', 'Ano') . ctl_check('showGenres', 'Gêneros') . ctl_check('showInfo', 'Duração') . ctl_check('showSynopsis', 'Sinopse') . ctl_check('useTitleLogo', 'Logo do título') . ctl_check('upper', 'Maiúsculas') ?>
        <?php else: ?>
          <?= ctl_check('showDate', 'Data') . ctl_check('showComp', 'Campeonato') . ctl_check('showChannels', 'Canais') . ctl_check('showScore', 'Placar ao vivo') ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($kind === 'games'): ?>
    <div class="grp">
      <h3>Fundo do banner</h3>
      <div class="bgs" id="bgs">
        <button type="button" data-bg="auto"><i class="b-auto"></i><span>Do modelo</span></button>
        <button type="button" data-bg="pitch"><i class="b-pitch"></i><span>Gramado</span></button>
        <button type="button" data-bg="stadium"><i class="b-stadium"></i><span>Estádio</span></button>
        <button type="button" data-bg="net"><i class="b-net"></i><span>Rede</span></button>
        <button type="button" data-bg="upload"><i class="b-up" id="bgThumb"></i><span>Meu fundo</span></button>
      </div>
      <input type="file" id="bgFile" accept="image/png,image/jpeg,image/webp" hidden>
      <button type="button" class="btn sm ghost wide" id="bgPick" style="margin-top:8px">Enviar uma imagem de fundo</button>
    </div>
    <?php endif; ?>
    <div class="grp">
      <h3>Ajustes</h3>
      <div class="two">
        <?= ctl_select('logoAlign', 'Posição da logo', ['left' => 'Esquerda', 'center' => 'Centro', 'right' => 'Direita']) ?>
        <?php if ($kind === 'promo'): ?>
        <?php elseif ($kind === 'movie'): ?>
          <?= ctl_select('art', 'Arte de fundo', ['auto' => 'Automática', 'backdrop' => 'Cena', 'poster' => 'Pôster']) ?>
        <?php else: ?>
          <?= ctl_select('perPage', 'Jogos por banner', ['0' => 'Automático', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '10' => '10', '12' => '12']) ?>
        <?php endif; ?>
      </div>
      <?= ctl_range('logoSize', 'Tamanho da logo', 50, 200) . ctl_range('overlay', $kind === 'promo' ? 'Escurecer capas' : 'Escurecer fundo', 0, 100) ?>
    </div>
    <div class="grp">
      <h3>Meus estilos</h3>
      <div class="row">
        <select id="presetSel"><option value="">— selecione —</option></select>
        <button type="button" class="btn sm ghost" id="presetDel" title="Excluir estilo">✕</button>
      </div>
      <button type="button" class="btn sm ghost wide" id="presetSave">Salvar estilo atual</button>
    </div>
  </section>
<?php
}

function editor_stage(string $kind): void
{
    ?>
  <section class="stage">
    <div class="canvas-wrap" id="canvasWrap"><canvas id="cv" width="1080" height="1920"></canvas><div class="spin" id="spin"></div></div>
    <?php if ($kind === 'games'): ?>
      <div class="pager" id="pager"><button type="button" class="btn sm ghost" id="pgPrev">‹</button><span id="pgInfo">1 / 1</span><button type="button" class="btn sm ghost" id="pgNext">›</button></div>
    <?php endif; ?>
    <div class="actions">
      <button type="button" class="btn gold" id="dl"><?= icon('down') ?> Baixar PNG</button>
      <?php if ($kind === 'games'): ?><button type="button" class="btn" id="dlAll"><?= icon('down') ?> Baixar todos</button><?php endif; ?>
      <button type="button" class="btn ghost" id="share" hidden><?= icon('share') ?> Compartilhar</button>
    </div>
    <label class="c-check"><input type="checkbox" id="allFmt"><i></i><span>Baixar nos 3 formatos (Stories, Feed e Quadrado)</span></label>
  </section>
<?php
}

function editor_boot(string $kind, array $u, array $scripts = ['engine.js', 'editor.js']): void
{
    $presets = [];
    if ($kind !== 'video') {
        $st = db()->prepare('SELECT id, name, data FROM presets WHERE user_id = ? AND kind = ? ORDER BY name COLLATE NOCASE');
        $st->execute([$u['id'], $kind]);
        $presets = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'data' => json_decode($r['data'], true)], $st->fetchAll());
    }
    $b = brand_of($u);
    $cfg = [
        'kind' => $kind, 'base' => BASE, 'csrf' => csrf(), 'uid' => (int)$u['id'], 'locked' => access_state($u)['locked'],
        'layouts' => $kind === 'video' ? [] : layouts_enabled($kind), 'presets' => $presets,
        'brand' => ['name' => $b['name'], 'logo' => $b['logoUrl'], 'bgImage' => $b['bgImageUrl'],
            'primary' => $b['primary'], 'secondary' => $b['secondary'], 'bg' => $b['bg'], 'text' => $b['text'],
            'fontTitle' => $b['fontTitle'], 'fontBody' => $b['fontBody'], 'cta' => $b['cta'], 'footer' => $b['footer']],
    ];
    echo '<script>window.GGCFG = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n";
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>' . "\n";
    foreach ($scripts as $f) {
        echo '<script src="' . url('/assets/' . $f . '?v=' . filemtime(ROOT . '/assets/' . $f)) . '"></script>' . "\n";
    }
}
