<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
$b = brand_of($u);

if (is_post()) {
    csrf_check();
    try {
        $n = array_intersect_key($b, BRAND_DEFAULTS);
        foreach (['name' => 40, 'cta' => 34, 'footer' => 60] as $k => $max) $n[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
        foreach (['primary', 'secondary', 'bg', 'text'] as $k) if (is_hex((string)($_POST[$k] ?? ''))) $n[$k] = strtoupper($_POST[$k]);
        if (in_array($_POST['fontTitle'] ?? '', FONTS_TITLE, true)) $n['fontTitle'] = $_POST['fontTitle'];
        if (in_array($_POST['fontBody'] ?? '', FONTS_BODY, true)) $n['fontBody'] = $_POST['fontBody'];
        $newLogo = false;
        foreach (['logo' => [900, true], 'bgImage' => [1920, false]] as $k => [$side, $alpha]) {
            if (!empty($_POST['rm_' . $k])) { delete_upload($n[$k]); $n[$k] = ''; }
            if (!empty($_FILES[$k]['name'])) {
                $new = save_upload($_FILES[$k], $side, $alpha);
                delete_upload($n[$k]);
                $n[$k] = $new;
                if ($k === 'logo') $newLogo = true;
            }
        }
        // Cores pela logo: ao enviar uma logo nova (se a opção estiver marcada) ou ao pedir o recálculo
        $msg = 'Marca salva. Ela já vale para os próximos banners.';
        if ($n['logo'] && (($newLogo && !empty($_POST['auto_colors'])) || isset($_POST['recalc']))) {
            $pal = logo_palette(ROOT . '/uploads/' . $n['logo']);
            if ($pal) { $n = array_merge($n, $pal); $msg = 'Marca salva e cores ajustadas pela logo. Você pode alterar qualquer uma delas abaixo.'; }
            else $msg = 'Marca salva. A logo não tem cores suficientes (é preta, branca ou cinza), então as cores foram mantidas.';
        }
        db()->prepare('UPDATE users SET brand = ? WHERE id = ?')->execute([json_encode($n, JSON_UNESCAPED_UNICODE), $u['id']]);
        flash($msg);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('/marca.php');
}
$opt = fn(array $list, string $cur) => implode('', array_map(fn($f) => '<option' . ($f === $cur ? ' selected' : '') . '>' . e($f) . '</option>', $list));
page_start('Minha Marca', 'marca', $u);
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="two" style="align-items:start;gap:18px">
    <div class="card">
      <h2>Logo <small>PNG com fundo transparente fica melhor</small></h2>
      <div class="logo-prev"><?= $b['logoUrl'] ? '<img src="' . e($b['logoUrl']) . '" alt="Logo">' : '<small>Nenhuma logo enviada</small>' ?></div>
      <label class="fld"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
      <label class="c-check" style="margin-bottom:10px"><input type="checkbox" name="auto_colors" value="1" checked><i></i><span>Ajustar as cores automaticamente ao enviar uma logo</span></label>
      <?php if ($b['logoUrl']): ?>
        <div class="row wrap" style="margin-bottom:10px">
          <button class="btn sm" name="recalc" value="1">Recalcular cores pela logo atual</button>
          <label class="c-check"><input type="checkbox" name="rm_logo" value="1"><i></i><span>Remover logo</span></label>
        </div>
      <?php endif; ?>
      <label class="fld" style="margin-top:14px"><span>Nome da marca</span><input type="text" name="name" maxlength="40" value="<?= e($b['name']) ?>" placeholder="Usado quando não há logo"><em>Aparece no lugar da logo se nenhuma imagem for enviada.</em></label>

      <h2 style="margin-top:22px">Imagem de fundo <small>opcional, para banners de jogos</small></h2>
      <?php if ($b['bgImageUrl']): ?><div class="logo-prev"><img src="<?= e($b['bgImageUrl']) ?>" alt="Fundo"></div><?php endif; ?>
      <label class="fld"><input type="file" name="bgImage" accept="image/png,image/jpeg,image/webp"></label>
      <?php if ($b['bgImageUrl']): ?><label class="c-check"><input type="checkbox" name="rm_bgImage" value="1"><i></i><span>Remover fundo</span></label><?php endif; ?>
    </div>
    <div class="card">
      <h2>Cores <small>ajustadas pela logo; altere se quiser</small></h2>
      <div class="colors" style="margin-bottom:18px">
        <label class="c-color"><input type="color" name="primary" value="<?= e($b['primary']) ?>"><span>Destaque</span></label>
        <label class="c-color"><input type="color" name="secondary" value="<?= e($b['secondary']) ?>"><span>Secundária</span></label>
        <label class="c-color"><input type="color" name="bg" value="<?= e($b['bg']) ?>"><span>Fundo</span></label>
        <label class="c-color"><input type="color" name="text" value="<?= e($b['text']) ?>"><span>Texto</span></label>
      </div>
      <h2>Fontes</h2>
      <div class="two">
        <label class="fld"><span>Títulos</span><select name="fontTitle"><?= $opt(FONTS_TITLE, $b['fontTitle']) ?></select></label>
        <label class="fld"><span>Textos</span><select name="fontBody"><?= $opt(FONTS_BODY, $b['fontBody']) ?></select></label>
      </div>
      <h2 style="margin-top:10px">Textos padrão</h2>
      <label class="fld"><span>Botão (CTA)</span><input type="text" name="cta" maxlength="34" value="<?= e($b['cta']) ?>" placeholder="ASSINE AGORA"></label>
      <label class="fld"><span>Rodapé / contato</span><input type="text" name="footer" maxlength="60" value="<?= e($b['footer']) ?>" placeholder="WhatsApp (11) 99999-9999 • @seuperfil"></label>
      <button class="btn gold">Salvar marca</button>
    </div>
  </div>
</form>
<?php page_end();
