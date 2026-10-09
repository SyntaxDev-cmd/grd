<?php
// Gestão de licenças — só o administrador do Gerador Gold (sistema base). Ultra Masters não entram aqui.
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
require __DIR__ . '/../inc/packager.php';
$me = guard(false, false, true);
$pdo = db();

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'download') {
        // Marca o pacote com o comprador da licença escolhida (ou texto livre); se não houver, usa o nº da licença
        $tag = trim((string)($_POST['tag'] ?? ''));
        if ($tag === '' && $id) {
            $st = $pdo->prepare('SELECT lkey, buyer FROM licenses WHERE id = ?'); $st->execute([$id]); $l = $st->fetch();
            if ($l) $tag = ($l['buyer'] !== '' ? $l['buyer'] . ' ' : '') . $l['lkey'];
        }
        $server = trim((string)($_POST['server'] ?? '')) ?: (site_link() . 'api/license_server.php');
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        try {
            $r = gg_build_package($server, $tag, !empty($_POST['ofusca']));
        } catch (Throwable $e) { flash('Falha ao gerar o pacote: ' . $e->getMessage(), 'err'); redirect('/admin/licencas.php'); }
        $f = $r['zip'];
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $r['name'] . '"');
        header('Content-Length: ' . filesize($f));
        header('Cache-Control: no-store');
        readfile($f);
        @unlink($f);
        @rmdir(dirname($f));
        exit;
    }
    if ($a === 'new') {
        $exp = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['expires'] ?? '')) ? $_POST['expires'] . ' 23:59:59' : null;
        do { $key = lic_new_key(); $c = $pdo->prepare('SELECT 1 FROM licenses WHERE lkey = ?'); $c->execute([$key]); } while ($c->fetch());
        $pdo->prepare('INSERT INTO licenses (lkey, domain, expires_at, buyer, note, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([
            $key, lic_domain((string)($_POST['domain'] ?? '')), $exp,
            mb_substr(trim((string)($_POST['buyer'] ?? '')), 0, 80), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200), now()]);
        flash('Licença criada: ' . $key);
    } elseif ($a === 'toggle') {
        $st = $pdo->prepare('SELECT status FROM licenses WHERE id = ?'); $st->execute([$id]);
        if (($cur = $st->fetchColumn()) !== false) { $pdo->prepare('UPDATE licenses SET status = ? WHERE id = ?')->execute([$cur === 'active' ? 'blocked' : 'active', $id]); flash($cur === 'active' ? 'Licença bloqueada. O script do comprador trava na próxima verificação.' : 'Licença reativada.'); }
    } elseif ($a === 'rebind') {
        $pdo->prepare("UPDATE licenses SET domain = ? WHERE id = ?")->execute([lic_domain((string)($_POST['domain'] ?? '')), $id]);
        flash('Domínio da licença atualizado.');
    } elseif ($a === 'renew') {
        $m = max(1, min(120, (int)($_POST['months'] ?? 12)));
        $st = $pdo->prepare('SELECT expires_at FROM licenses WHERE id = ?'); $st->execute([$id]);
        $pdo->prepare('UPDATE licenses SET expires_at = ? WHERE id = ?')->execute([add_months($st->fetchColumn() ?: null, $m), $id]);
        flash("+$m meses nesta licença.");
    } elseif ($a === 'perp') {
        $pdo->prepare('UPDATE licenses SET expires_at = NULL WHERE id = ?')->execute([$id]);
        flash('Licença tornada vitalícia.');
    } elseif ($a === 'clearhits') {
        $pdo->prepare('DELETE FROM license_hits WHERE license_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE licenses SET violations = 0 WHERE id = ?')->execute([$id]);
        flash('Histórico de acessos desta licença limpo.');
    } elseif ($a === 'delete') {
        $pdo->prepare('DELETE FROM licenses WHERE id = ?')->execute([$id]);
        flash('Licença excluída. O comprador perde o acesso na próxima verificação.');
    }
    redirect('/admin/licencas.php');
}

if (isset($_GET['view'])) {
    $st = $pdo->prepare('SELECT * FROM licenses WHERE id = ?'); $st->execute([(int)$_GET['view']]); $lic = $st->fetch();
    if (!$lic) redirect('/admin/licencas.php');
    $hs = $pdo->prepare('SELECT * FROM license_hits WHERE license_id = ? ORDER BY foreign_use DESC, last_seen DESC');
    $hs->execute([$lic['id']]);
    $hits = $hs->fetchAll();
    page_start('Acessos da licença', 'a-lic', $me);
    echo '<a class="btn sm ghost" href="' . url('/admin/licencas.php') . '" style="margin-bottom:14px">‹ Licenças</a>';
    echo '<div class="card"><h2>' . e($lic['lkey']) . ' <small>' . e($lic['buyer']) . '</small></h2>';
    echo '<p style="margin-top:0;color:var(--mut)">Licenciada para <b>' . ($lic['domain'] !== '' ? e($lic['domain']) : 'não ativada') . '</b>'
        . ($lic['buyer_tag'] !== '' ? ' · marca do pacote: <b>' . e($lic['buyer_tag']) . '</b>' : '') . '</p>';
    if ((int)$lic['violations'] > 0) echo '<div class="alert err">Esta licença foi usada em ' . (int)$lic['violations'] . ' domínio(s) diferente(s) do autorizado. As linhas em vermelho são os suspeitos de vazamento.</div>';
    if (!$hits) { echo '<div class="empty">Nenhum acesso registrado ainda.</div>'; }
    else {
        echo '<div class="tbl-wrap"><table><tr><th>Domínio</th><th>IP</th><th>Marca do pacote</th><th>Acessos</th><th>Primeiro</th><th>Último</th></tr>';
        foreach ($hits as $h) {
            $bad = (int)$h['foreign_use'] === 1;
            echo '<tr' . ($bad ? ' style="background:rgba(255,84,104,.09)"' : '') . '>'
                . '<td><b>' . e($h['domain']) . '</b>' . ($bad ? ' <span class="tag bad">estranho</span>' : ' <span class="tag ok">autorizado</span>') . '</td>'
                . '<td>' . e($h['ip']) . '</td><td>' . ($h['build'] !== '' ? e($h['build']) : '—') . '</td>'
                . '<td>' . (int)$h['hits'] . '</td><td><small>' . fmt_date($h['first_seen'], true) . '</small></td><td><small>' . fmt_date($h['last_seen'], true) . '</small></td></tr>';
        }
        echo '</table></div>';
        echo '<form method="post" style="margin-top:12px" onsubmit="return confirm(\'Limpar o histórico de acessos desta licença?\')">' . csrf_field()
            . '<input type="hidden" name="a" value="clearhits"><input type="hidden" name="id" value="' . (int)$lic['id'] . '"><button class="btn sm ghost">Limpar histórico</button></form>';
    }
    echo '</div>';
    page_end();
    return;
}
$rows = $pdo->query('SELECT * FROM licenses ORDER BY violations DESC, id DESC')->fetchAll();
$n = $pdo->query('SELECT COUNT(*) FROM licenses')->fetchColumn();
$act = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active' AND (expires_at IS NULL OR expires_at >= '" . now() . "')")->fetchColumn();
$hasKey = is_file(DATA . '/license_private.pem');
$stLabel = ['active' => ['Ativa', 'ok'], 'blocked' => ['Bloqueada', 'bad']];
page_start('Licenças do Script', 'a-lic', $me);
?>
<?php if (!$hasKey): ?><div class="alert err">A chave de assinatura das licenças não foi encontrada em <code>data/</code>. Sem ela o servidor não valida ninguém.</div><?php endif; ?>
<?php $leaks = (int)$pdo->query('SELECT COUNT(*) FROM licenses WHERE violations > 0')->fetchColumn(); ?>
<div class="stats">
  <div class="stat hl"><b><?= (int)$act ?></b><span>Licenças ativas</span></div>
  <div class="stat"><b><?= (int)$n ?></b><span>Licenças no total</span></div>
  <div class="stat"><b style="<?= $leaks ? 'color:var(--bad)' : '' ?>"><?= $leaks ?></b><span>Com uso em domínio estranho</span></div>
</div>
<?php if ($leaks): ?><div class="alert err"><b>Possível vazamento:</b> <?= $leaks ?> licença(s) estão sendo usadas em domínios diferentes do autorizado. Abra "Ver acessos" para identificar o domínio e a marca do pacote.</div><?php endif; ?>
<form class="card" method="post" style="max-width:720px">
  <?= csrf_field() ?><input type="hidden" name="a" value="download">
  <h2>Baixar o script para venda</h2>
  <p style="color:var(--mut);margin-top:0">Gera o .zip pronto para o comprador, já com a verificação de licença embutida. Os arquivos do vendedor (este painel, o servidor de licença e a chave privada) não vão no pacote.</p>
  <div class="two">
    <label class="fld"><span>Marca do comprador (rastreio)</span><input type="text" name="tag" maxlength="60" placeholder="Nome ou contato — aparece nos acessos"><em>Em branco: usa o comprador + a chave da licença selecionada abaixo.</em></label>
    <label class="fld"><span>Vincular a uma licença (opcional)</span><select name="id"><option value="0">— não vincular —</option>
      <?php foreach ($pdo->query("SELECT id, lkey, buyer FROM licenses ORDER BY id DESC") as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['lkey'] . ($o['buyer'] ? ' · ' . $o['buyer'] : '')) ?></option><?php endforeach; ?>
    </select></label>
  </div>
  <label class="fld"><span>URL do servidor de licença</span><input type="text" name="server" value="<?= e(site_link() . 'api/license_server.php') ?>"><em>Deixe assim, a menos que o servidor de licença fique em outro endereço.</em></label>
  <label class="c-check" style="margin-bottom:14px"><input type="checkbox" name="ofusca" value="1" checked><i></i><span>Ofuscar o código (recomendado — embaralha o PHP e esconde a checagem)</span></label>
  <button class="btn gold">Gerar e baixar .zip</button>
  <p class="note" style="margin-top:10px">A geração leva alguns segundos. Ofuscação grátis dificulta mas não é à prova de especialista; para blindar de verdade, use um encoder (ionCube) por cima.</p>
</form>

<form class="card" method="post" style="max-width:720px">
  <?= csrf_field() ?><input type="hidden" name="a" value="new">
  <h2>Nova licença</h2>
  <div class="two">
    <label class="fld"><span>Comprador</span><input type="text" name="buyer" maxlength="80" placeholder="Nome ou contato"></label>
    <label class="fld"><span>Domínio (opcional)</span><input type="text" name="domain" maxlength="120" placeholder="deixe vazio: trava no 1º acesso"><em>Em branco, a licença gruda no primeiro domínio onde o script for ativado.</em></label>
    <label class="fld"><span>Vence em (opcional)</span><input type="date" name="expires"><em>Vazio = vitalícia.</em></label>
    <label class="fld"><span>Observação</span><input type="text" name="note" maxlength="200"></label>
  </div>
  <button class="btn gold">Gerar licença</button>
</form>
<div class="card">
  <h2>Licenças emitidas</h2>
  <?php if (!$rows): ?><div class="empty">Nenhuma licença ainda.</div><?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Chave</th><th>Comprador</th><th>Domínio</th><th>Vencimento</th><th>Situação</th><th>Última verificação</th><th></th></tr>
    <?php foreach ($rows as $r):
      $exp = $r['expires_at'] !== null && $r['expires_at'] < now();
      [$lb, $cl] = $exp ? ['Expirada', 'warn'] : ($stLabel[$r['status']] ?? [$r['status'], 'mut']);
    ?>
      <tr>
        <td><code style="font-size:12px"><?= e($r['lkey']) ?></code></td>
        <td><?= e($r['buyer']) ?><?= $r['note'] ? '<br><small>' . e($r['note']) . '</small>' : '' ?></td>
        <td><?= $r['domain'] !== '' ? e($r['domain']) : '<small>não ativada</small>' ?></td>
        <td><?= $r['expires_at'] ? fmt_date($r['expires_at']) : 'Vitalícia' ?></td>
        <td><span class="tag <?= $cl ?>"><?= $lb ?></span><?= (int)$r['violations'] > 0 ? '<br><span class="tag bad">' . (int)$r['violations'] . ' domínio(s) estranho(s)</span>' : '' ?></td>
        <td><small><?= $r['last_check'] ? fmt_date($r['last_check'], true) . '<br>' . e($r['last_ip']) . ' · ' . (int)$r['checks'] . 'x' : '—' ?></small></td>
        <td style="white-space:nowrap;text-align:right">
          <a class="btn sm <?= (int)$r['violations'] > 0 ? 'bad' : 'ghost' ?>" href="<?= url('/admin/licencas.php?view=' . $r['id']) ?>">Ver acessos</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm"><?= $r['status'] === 'active' ? 'Bloquear' : 'Reativar' ?></button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="renew"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="months" value="12"><button class="btn sm" title="Somar 12 meses">+12m</button></form>
          <form method="post" onsubmit="return confirm('Liberar esta licença para um novo domínio? Use quando o comprador trocar de domínio.')"><?= csrf_field() ?><input type="hidden" name="a" value="rebind"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="domain" value=""><button class="btn sm ghost" title="Zerar o domínio">Soltar domínio</button></form>
          <form method="post" onsubmit="return confirm('Excluir a licença <?= e($r['lkey']) ?>? O comprador perde o acesso.')"><?= csrf_field() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm bad">Excluir</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php page_end();
