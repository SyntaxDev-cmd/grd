<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
page_start('Jogos do Dia', 'jogos', $u);
?>
<div class="editor">
  <section class="panel">
    <div class="grp">
      <h3>Jogos <button type="button" class="link" id="reload">atualizar</button></h3>
      <div class="chips" id="days" style="margin-top:0"></div>
      <label class="fld"><span>Data exibida no banner</span><input type="text" id="dateLabel" maxlength="40"></label>
      <div class="chips" id="comps"></div>
      <div class="row" style="margin-bottom:10px">
        <button type="button" class="btn sm ghost" id="all">Marcar todos</button>
        <button type="button" class="btn sm ghost" id="none">Limpar</button>
        <span class="sp"></span><small id="count"></small>
      </div>
      <div class="games" id="games"><div class="empty">Carregando jogos…</div></div>
    </div>
    <p class="note" id="srcNote"></p>
  </section>
  <?php editor_stage('games'); editor_controls('games'); ?>
</div>
<?php editor_boot('games', $u); ?>
<script>
(function () {
  const $ = s => document.querySelector(s), box = $('#games');
  const IS_ADMIN = <?= is_admin($u) ? 'true' : 'false' ?>; // os nomes das fontes de dados só aparecem para o administrador
  let games = [], sel = new Set(), comp = '', day = 0;
  // Dias disponíveis: hoje + 6. O rótulo da data e o título do banner acompanham o dia escolhido
  const DAYS = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(); d.setDate(d.getDate() + i);
    const wd = d.toLocaleDateString('pt-BR', { weekday: 'long' }).replace('-feira', '');
    const dm = d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    return { chip: i === 0 ? 'Hoje' : i === 1 ? 'Amanhã' : wd.slice(0, 3) + ' ' + dm, label: wd + ' • ' + dm,
      title: i === 0 ? 'JOGOS DO DIA' : i === 1 ? 'JOGOS DE AMANHÃ' : 'JOGOS DE ' + wd.toUpperCase() };
  });
  const autoTitles = DAYS.map(d => d.title);
  function setDay(i) {
    day = i;
    document.querySelectorAll('#days .chip').forEach((c, k) => c.classList.toggle('on', k === i));
    $('#dateLabel').value = Editor.data.dateLabel = DAYS[i].label;
    // Só troca o título se o usuário não tiver escrito um próprio
    if (autoTitles.includes(Editor.st.title)) { Editor.st.title = DAYS[i].title; Editor.sync(); }
    box.innerHTML = '<div class="empty">Carregando jogos…</div>';
    load(false);
  }
  Editor.data = { games: [], dateLabel: DAYS[0].label };
  DAYS.forEach((d, i) => {
    const c = document.createElement('button');
    c.type = 'button'; c.className = 'chip' + (i === 0 ? ' on' : ''); c.textContent = d.chip;
    c.addEventListener('click', () => setDay(i));
    $('#days').appendChild(c);
  });
  $('#dateLabel').value = DAYS[0].label;

  const visible = () => games.filter(g => !comp || g.comp === comp);
  function push() {
    Editor.data.games = games.filter(g => sel.has(g.id));
    Editor.page = 0;
    $('#count').textContent = sel.size + ' de ' + games.length + ' selecionados';
    Editor.render();
  }
  function draw() {
    box.innerHTML = '';
    const list = visible();
    if (!list.length) { box.innerHTML = '<div class="empty">Nenhum jogo encontrado para este dia.</div>'; return; }
    list.forEach(g => {
      const l = document.createElement('label');
      l.className = 'game' + (sel.has(g.id) ? ' on' : '');
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = sel.has(g.id);
      const d = document.createElement('div'), b = document.createElement('b'), s = document.createElement('small'), t = document.createElement('time');
      t.textContent = g.time; b.append(t, g.t1 + ' x ' + g.t2);
      s.textContent = [g.comp, g.channels.join(', ')].filter(Boolean).join(' · ');
      d.append(b, s); l.append(cb, d);
      cb.addEventListener('change', () => { cb.checked ? sel.add(g.id) : sel.delete(g.id); l.classList.toggle('on', cb.checked); push(); });
      box.appendChild(l);
    });
  }
  function chips() {
    const el = $('#comps'), names = [...new Set(games.map(g => g.comp).filter(Boolean))];
    el.innerHTML = '';
    if (names.length < 2) return;
    [''].concat(names).forEach(n => {
      const c = document.createElement('button');
      c.type = 'button'; c.className = 'chip' + (n === comp ? ' on' : ''); c.textContent = n || 'Todos';
      c.addEventListener('click', () => { comp = n; chips(); draw(); });
      el.appendChild(c);
    });
  }
  async function load(fresh) {
    try {
      const my = day;
      const j = await Editor.get('/api/games.php?day=' + day + (fresh ? '&fresh=1' : ''));
      if (my !== day) return;
      games = j.games; comp = '';
      sel = new Set(games.map(g => g.id));
      const bad = j.sources.filter(s => s.error);
      if (IS_ADMIN) $('#srcNote').textContent = j.sources.length
        ? 'Fontes: ' + j.sources.map(s => s.name + ' (' + (s.error ? 'indisponível' : s.count) + ')').join(' · ')
        : 'Nenhuma fonte de jogos disponível para este dia.';
      if (bad.length) Editor.toast('Uma fonte de jogos está indisponível no momento.');
      chips(); draw(); push();
    } catch (e) { box.innerHTML = '<div class="empty"></div>'; box.firstChild.textContent = e.message; }
  }
  $('#all').addEventListener('click', () => { visible().forEach(g => sel.add(g.id)); draw(); push(); });
  $('#none').addEventListener('click', () => { visible().forEach(g => sel.delete(g.id)); draw(); push(); });
  $('#reload').addEventListener('click', () => load(true));
  $('#dateLabel').addEventListener('input', e => { Editor.data.dateLabel = e.target.value; Editor.render(); });
  load(false);
})();
</script>
<?php page_end();
