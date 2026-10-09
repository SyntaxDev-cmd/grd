<?php
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard(false, false, true);
$pdo = db();

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($a === 'save') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
        $url = trim((string)($_POST['url'] ?? ''));
        $map = trim((string)($_POST['map'] ?? ''));
        if ($map === '') $map = json_encode(GAMES_MAP_DEFAULT, JSON_PRETTY_PRINT);
        if ($name === '' || !preg_match('#^https?://#i', $url) || !filter_var(strtr($url, ['{' => '', '}' => '']), FILTER_VALIDATE_URL)) {
            flash('Informe um nome e uma URL válida (http/https).', 'err');
        } elseif (!is_array(json_decode($map, true))) {
            flash('O mapeamento de campos precisa ser um JSON válido.', 'err');
        } else {
            $vals = [$name, $url, trim((string)($_POST['headers'] ?? '')), trim((string)($_POST['root'] ?? '')), $map, empty($_POST['active']) ? 0 : 1];
            if ($id) $pdo->prepare('UPDATE sports_apis SET name = ?, url = ?, headers = ?, root = ?, map = ?, active = ? WHERE id = ?')->execute([...$vals, $id]);
            else $pdo->prepare('INSERT INTO sports_apis (name, url, headers, root, map, active) VALUES (?, ?, ?, ?, ?, ?)')->execute($vals);
            flash('API salva. Use "Testar" para conferir se os jogos estão sendo lidos.');
            redirect('/admin/apis.php');
        }
        redirect('/admin/apis.php' . ($id ? '?edit=' . $id : '?new=1'));
    }
    if ($a === 'espn') {
        set_setting('espn_on', empty($_POST['espn_on']) ? '0' : '1');
        $lg = array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($_POST['espn_leagues'] ?? ''))), fn($l) => (bool)preg_match('/^[a-z0-9._]+$/', $l));
        set_setting('espn_leagues', $lg ? implode(', ', array_slice($lg, 0, 40)) : ESPN_LEAGUES);
        flash('Agenda gratuita atualizada.');
        redirect('/admin/apis.php');
    }
    if ($a === 'toggle') $pdo->prepare('UPDATE sports_apis SET active = 1 - active WHERE id = ?')->execute([$id]);
    if ($a === 'delete') { $pdo->prepare('DELETE FROM sports_apis WHERE id = ?')->execute([$id]); flash('API removida.'); }
    redirect('/admin/apis.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM sports_apis WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$form = $edit || isset($_GET['new']);
$rows = $pdo->query('SELECT * FROM sports_apis ORDER BY id')->fetchAll();
page_start('APIs de Jogos', 'a-apis', $me);
?>
<?php if ($form): ?>
<form class="card" method="post" style="max-width:820px">
  <?= csrf_field() ?>
  <input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Editar API' : 'Nova API de jogos' ?></h2>
  <div class="two">
    <label class="fld"><span>Nome</span><input type="text" name="name" required maxlength="80" value="<?= e($edit['name'] ?? '') ?>"></label>
    <label class="fld"><span>URL (deve retornar JSON)</span><input type="url" name="url" required value="<?= e($edit['url'] ?? '') ?>" placeholder="https://…"></label>
  </div>
  <div class="two">
    <label class="fld"><span>Cabeçalhos HTTP (opcional, um por linha)</span><textarea class="code" name="headers" placeholder="Authorization: Bearer SEU_TOKEN&#10;x-api-key: SUA_CHAVE"><?= e($edit['headers'] ?? '') ?></textarea></label>
    <label class="fld"><span>Caminho da lista no JSON (opcional)</span><input type="text" name="root" value="<?= e($edit['root'] ?? '') ?>" placeholder="ex.: data.jogos">
      <em>Deixe vazio se a resposta já for a lista de jogos. Use pontos para níveis internos.</em></label>
  </div>
  <label class="fld"><span>Mapeamento de campos (nosso campo → campo da API)</span>
    <textarea class="code" name="map" rows="14"><?= e($edit['map'] ?? json_encode(GAMES_MAP_DEFAULT, JSON_PRETTY_PRINT)) ?></textarea>
    <em>À esquerda ficam os nomes usados pelo gerador; à direita, o nome do campo na API (aceita pontos, ex.: <code>teams.home.name</code>). Nomes comuns em outras APIs são detectados automaticamente.</em></label>
  <label class="c-check" style="margin-bottom:14px"><input type="checkbox" name="active" value="1" <?= !$edit || $edit['active'] ? 'checked' : '' ?>><i></i><span>Ativa</span></label>
  <div class="row"><button class="btn gold">Salvar</button><a class="btn ghost" href="<?= url('/admin/apis.php') ?>">Cancelar</a></div>
</form>
<?php endif; ?>

<div class="card">
  <div class="row" style="margin-bottom:14px">
    <h2 style="margin:0">Fontes de jogos</h2><span class="sp"></span>
    <a class="btn gold" href="<?= url('/admin/apis.php?new=1') ?>">+ Nova API</a>
  </div>
  <p style="color:var(--mut);margin-top:0">Os jogos de todas as APIs ativas são combinados na tela "Jogos do Dia". Partidas repetidas (mesmos times e horário) aparecem uma única vez.</p>
  <?php if (!$rows): ?><div class="empty">Nenhuma API cadastrada.</div><?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Nome</th><th>URL</th><th>Situação</th><th>Teste</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['name']) ?></b></td>
      <td><small style="word-break:break-all"><?= e($r['url']) ?></small></td>
      <td><span class="tag <?= $r['active'] ? 'ok' : 'mut' ?>"><?= $r['active'] ? 'Ativa' : 'Inativa' ?></span></td>
      <td><button type="button" class="btn sm" data-test="<?= $r['id'] ?>">Testar</button> <small data-res="<?= $r['id'] ?>"></small></td>
      <td style="white-space:nowrap;text-align:right">
        <a class="btn sm" href="<?= url('/admin/apis.php?edit=' . $r['id']) ?>">Editar</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm"><?= $r['active'] ? 'Desativar' : 'Ativar' ?></button></form>
        <form method="post" onsubmit="return confirm('Remover esta API?')"><?= csrf_field() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm bad">Excluir</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<form class="card" method="post">
  <?= csrf_field() ?><input type="hidden" name="a" value="espn">
  <h2>Agenda gratuita dos próximos dias <small>ESPN, sem chave</small></h2>
  <p style="color:var(--mut);margin-top:0">Usada para os jogos de amanhã até seis dias à frente, e também para hoje quando as fontes acima não respondem. Traz horário, times, escudos e campeonato; <b>não informa os canais de TV</b>.</p>
  <label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="espn_on" value="1" <?= setting('espn_on', '1') === '1' ? 'checked' : '' ?>><i></i><span>Ativar agenda gratuita</span></label>
  <label class="fld"><span>Campeonatos (códigos da ESPN, separados por vírgula)</span>
    <textarea class="code" name="espn_leagues" rows="3"><?= e(setting('espn_leagues', ESPN_LEAGUES)) ?></textarea>
    <em>Exemplos: <code>bra.1</code> Brasileirão A · <code>bra.2</code> Série B · <code>bra.copa_do_brazil</code> Copa do Brasil · <code>conmebol.libertadores</code> · <code>uefa.champions</code> · <code>eng.1</code> Premier League · <code>esp.1</code> LaLiga. Deixe vazio para restaurar a lista padrão.</em></label>
  <button class="btn gold">Salvar</button>
</form>
<div class="card">
  <h2>Outras APIs com data</h2>
  <p style="color:var(--mut);margin:0">Se uma API sua aceitar a data na URL, use <code>{data}</code> (2026-10-08), <code>{data_br}</code> (08/10/2026), <code>{Ymd}</code> (20261008) ou <code>{dia}</code> (0 = hoje, 1 = amanhã). APIs com esses marcadores passam a valer também para os próximos dias.</p>
</div>
<script>
document.querySelectorAll('[data-test]').forEach(btn => btn.addEventListener('click', async () => {
  const out = document.querySelector('[data-res="' + btn.dataset.test + '"]');
  btn.disabled = true; out.textContent = 'testando…';
  try {
    const j = await (await fetch(<?= json_encode(url('/api/games.php')) ?> + window.GG_url('?test=' + btn.dataset.test))).json();
    if (j.error) { out.textContent = 'Erro: ' + j.error; out.style.color = 'var(--bad)'; }
    else if (!j.count) { out.textContent = 'Respondeu, mas nenhum jogo foi reconhecido. Revise o mapeamento.'; out.style.color = 'var(--warn)'; }
    else { out.textContent = j.count + ' jogos · ex.: ' + j.sample[0].t1 + ' x ' + j.sample[0].t2 + ' ' + j.sample[0].time; out.style.color = 'var(--ok)'; }
  } catch (e) { out.textContent = 'Falha na requisição.'; out.style.color = 'var(--bad)'; }
  btn.disabled = false;
}));
</script>
<?php page_end();
