<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
$g = array_count_values(array_column(layouts_enabled('promo'), 'g'));
$n = fn(string $s) => count(library($s));
$mk = fn(string $grp) => '/marketing.php?g=' . rawurlencode($grp);
$models = fn(int $c) => $c . ' modelo' . ($c === 1 ? '' : 's');
$items = fn(int $c) => $c ? $c . ' ite' . ($c === 1 ? 'm' : 'ns') : 'Em breve';
// [chave da arte, ícone, 1ª linha, 2ª linha (em destaque), descrição, destino, contagem]
$cards = [
    ['futebol', 'ball', 'Destaques do', 'Futebol', 'Tabelas, jogos, resultados e programação.', '/jogos.php', $models(count(layouts_enabled('games')))],
    ['vods', 'play', '', 'VODs', 'Filmes, séries, novelas, doramas e muito mais.', '/filmes.php', $models(count(layouts_enabled('movie')))],
    ['valor', 'tag', 'Banners', 'com Valor', 'Modelos prontos com espaço para preços.', $mk('Banners com valor'), $models($g['Banners com valor'] ?? 0)],
    ['divulgacao', 'bolt', 'Banners', 'Divulgação', 'Artes promocionais para divulgar seu servidor.', $mk('Banners de divulgação'), $models($g['Banners de divulgação'] ?? 0)],
    ['revendas', 'users', 'Captação de', 'Revendas', 'Artes para atrair e conquistar revendedores.', $mk('Captação de revendas'), $models($g['Captação de revendas'] ?? 0)],
    ['apps', 'phone', 'Artes para', 'Aplicativos', 'Divulgação de apps, ícones e telas promocionais.', $mk('Artes para aplicativos'), $models($g['Artes para aplicativos'] ?? 0)],
    ['videos', 'film', 'Vídeos', 'Divulgação', 'Vídeos prontos para divulgar seu servidor.', '/motion.php', MOTION_PATTERNS . ' padrões'],
    ['tutoriais', 'school', 'Vídeos', 'Tutoriais', 'Tutoriais prontos para ensinar seus clientes.', '/biblioteca.php?s=tutoriais', $items($n('tutoriais'))],
    ['cortes', 'bolt', 'Cortes', 'Virais', 'Cortes prontos para Reels, Shorts e TikTok.', '/biblioteca.php?s=cortes', $items($n('cortes'))],
    ['codigo', 'code', 'Código para', 'Baixar Apps', 'Códigos, links e instruções de instalação.', '/biblioteca.php?s=apps', $items($n('apps'))],
];
page_start('Divulgação', 'divulgacao', $u);
?>
<div class="hub">
  <?php foreach ($cards as [$key, $ic, $l1, $l2, $desc, $href, $count]): ?>
    <a href="<?= e(url($href)) ?>">
      <div class="hub-art" style="background-image:url('<?= e(hub_art($key)) ?>')"></div>
      <em><?= e($count) ?></em>
      <div class="hub-body">
        <span class="hub-ic"><?= icon($ic) ?></span>
        <h3><?= e($l1) ?><span><?= e($l2) ?></span></h3>
        <p><?= e($desc) ?></p>
        <span class="go">Acessar <i>→</i></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php page_end();
