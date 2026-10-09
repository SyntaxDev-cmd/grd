<?php
// Admin: banners do painel inicial e conteúdos da área Divulgação
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard_owner();
$pdo = db();
$tid = brand_tid($me);
$tw = tenant_where($tid);
$sec = ($_GET['s'] ?? '') === 'hub' ? 'hub' : (isset(LIB_SECTIONS[$_GET['s'] ?? '']) ? $_GET['s'] : 'dash');

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $s = isset(LIB_SECTIONS[$_POST['section'] ?? '']) ? $_POST['section'] : 'dash';
    $st = $pdo->prepare("SELECT * FROM library WHERE id = ? AND $tw");
    $st->execute([$id]);
    $row = $st->fetch() ?: null;
    try {
        if ($a === 'hub') {
            // Arte de cada cartão da Divulgação
            foreach (array_keys(HUB_CARDS) as $k) {
                $cur = (string)setting('hub_img_' . $k, '');
                if (!empty($_POST['rm'][$k])) { delete_upload($cur); $cur = ''; }
                if (!empty($_FILES['img']['name'][$k])) {
                    $f = ['name' => $_FILES['img']['name'][$k], 'tmp_name' => $_FILES['img']['tmp_name'][$k], 'error' => $_FILES['img']['error'][$k], 'size' => $_FILES['img']['size'][$k]];
                    $new = save_upload($f, 1000, false); delete_upload($cur); $cur = $new;
                }
                set_setting('hub_img_' . $k, $cur);
            }
            flash('Artes dos cartões salvas.');
            redirect('/admin/conteudos.php?s=hub');
        }
        if ($a === 'save') {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 80);
            $url = trim((string)($_POST['url'] ?? ''));
            if ($title === '') throw new RuntimeException('Informe um título.');
            // Aceita endereço completo (https://…) ou caminho interno do sistema (/filmes.php)
            if ($url !== '' && !preg_match('#^(https?://|/)#i', $url)) throw new RuntimeException('O link deve começar com https:// ou com / (página do próprio sistema).');
            $image = $row['image'] ?? '';
            if (!empty($_POST['rm_image'])) { delete_upload($image); $image = ''; }
            if (!empty($_FILES['image']['name'])) { $new = save_upload($_FILES['image'], 1280, false); delete_upload($image); $image = $new; }
            $vals = [$s, $title, mb_substr(trim((string)($_POST['descr'] ?? '')), 0, 400), mb_substr($url, 0, 500), mb_substr(trim((string)($_POST['code'] ?? '')), 0, 60), $image, (int)($_POST['sort'] ?? 0)];
            if ($row) $pdo->prepare('UPDATE library SET section = ?, title = ?, descr = ?, url = ?, code = ?, image = ?, sort = ? WHERE id = ?')->execute([...$vals, $id]);
            else $pdo->prepare('INSERT INTO library (section, title, descr, url, code, image, sort, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$vals, now(), $tid]);
            flash('Conteúdo salvo.');
        } elseif ($a === 'delete' && $row) {
            delete_upload($row['image']);
            $pdo->prepare('DELETE FROM library WHERE id = ?')->execute([$id]);
            flash('Conteúdo removido.');
            $s = $row['section'];
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('/admin/conteudos.php?s=' . $s);
}

$edit = null;
if (isset($_GET['edit'])) { $st = $pdo->prepare("SELECT * FROM library WHERE id = ? AND $tw"); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; if ($edit) $sec = $edit['section']; }
$rows = $sec === 'hub' ? [] : library($sec);
$isDash = $sec === 'dash';
page_start('Conteúdos e Banners', 'a-lib', $me);
?>
<div class="chips" style="margin-top:0">
  <?php foreach (LIB_SECTIONS as $k => $label): ?><a class="chip <?= $k === $sec ? 'on' : '' ?>" href="<?= url('/admin/conteudos.php?s=' . $k) ?>"><?= e($label) ?></a><?php endforeach; ?>
  <a class="chip <?= $sec === 'hub' ? 'on' : '' ?>" href="<?= url('/admin/conteudos.php?s=hub') ?>">Artes dos cartões da Divulgação</a>
</div>
<?php if ($sec === 'hub'): ?>
<form class="card" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="a" value="hub">
  <h2>Artes dos cartões da Divulgação</h2>
  <p style="color:var(--mut);margin-top:0">A arte aparece no topo de cada cartão. Use imagem na horizontal (cerca de 5:4), com o personagem na parte de cima: a base é esmaecida para o título entrar por cima. Sem envio, vale a arte padrão.</p>
  <div class="hubadm">
    <?php foreach (HUB_CARDS as $k => $label): ?>
      <div>
        <div class="hubadm-prev" style="background-image:url('<?= e(hub_art($k)) ?>')"></div>
        <b><?= e($label) ?></b>
        <input type="file" name="img[<?= $k ?>]" accept="image/png,image/jpeg,image/webp">
        <?php if (setting('hub_img_' . $k, '') !== ''): ?><label class="c-check"><input type="checkbox" name="rm[<?= $k ?>]" value="1"><i></i><span>Voltar à arte padrão</span></label><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="btn gold" style="margin-top:16px">Salvar artes</button>
</form>
<?php page_end(); return; endif; ?>
<div class="two" style="align-items:start;gap:18px">
  <form class="card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><input type="hidden" name="section" value="<?= e($sec) ?>">
    <h2><?= $edit ? 'Editar' : 'Adicionar em' ?> “<?= e(LIB_SECTIONS[$sec]) ?>”</h2>
    <?php if ($isDash): ?><p style="color:var(--mut);margin-top:0">Estes banners aparecem na faixa “Novo” do painel inicial de todos os usuários. Sem nenhum cadastrado, o sistema mostra quatro banners padrão.</p><?php endif; ?>
    <label class="fld"><span>Título</span><input type="text" name="title" required maxlength="80" value="<?= e($edit['title'] ?? '') ?>"></label>
    <?php if (!$isDash): ?><label class="fld"><span>Descrição</span><textarea name="descr" maxlength="400" rows="3"><?= e($edit['descr'] ?? '') ?></textarea></label><?php endif; ?>
    <label class="fld"><span><?= $isDash ? 'Link ao clicar' : 'Link (vídeo, download…)' ?></span><input type="text" name="url" maxlength="500" value="<?= e($edit['url'] ?? '') ?>" placeholder="https://…  ou  /filmes.php">
      <?php if ($isDash): ?><em>Páginas do sistema: /filmes.php · /jogos.php · /divulgacao.php · /motion.php</em><?php endif; ?></label>
    <?php if ($sec === 'apps'): ?><label class="fld"><span>Código (ex.: código do Downloader)</span><input type="text" name="code" maxlength="60" value="<?= e($edit['code'] ?? '') ?>"></label><?php endif; ?>
    <label class="fld"><span>Imagem <?= $isDash ? '(proporção 16:9 fica melhor)' : '(capa, opcional)' ?></span><input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label>
    <?php if (!empty($edit['image'])): ?><label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="rm_image" value="1"><i></i><span>Remover a imagem atual</span></label><?php endif; ?>
    <label class="fld" style="max-width:160px"><span>Ordem</span><input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>"><em>Menor aparece primeiro.</em></label>
    <div class="row"><button class="btn gold">Salvar</button><?php if ($edit): ?><a class="btn ghost" href="<?= url('/admin/conteudos.php?s=' . $sec) ?>">Cancelar</a><?php endif; ?></div>
  </form>
  <div class="card">
    <h2><?= e(LIB_SECTIONS[$sec]) ?> <small><?= count($rows) ?></small></h2>
    <?php if (!$rows): ?><div class="empty">Nada cadastrado ainda.</div><?php else: ?>
    <table>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td style="width:96px"><?php if ($r['image'] && is_file(ROOT . '/uploads/' . $r['image'])): ?><img src="<?= url('/uploads/' . $r['image']) ?>" alt="" style="width:88px;aspect-ratio:16/9;object-fit:cover;border-radius:8px;display:block"><?php endif; ?></td>
          <td><b><?= e($r['title']) ?></b><br><small style="word-break:break-all"><?= e($r['code'] !== '' ? 'Código ' . $r['code'] . ' · ' : '') ?><?= e($r['url']) ?></small></td>
          <td style="white-space:nowrap;text-align:right">
            <a class="btn sm" href="<?= url('/admin/conteudos.php?edit=' . $r['id']) ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Remover este conteúdo?')"><?= csrf_field() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm bad">Excluir</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php page_end();
