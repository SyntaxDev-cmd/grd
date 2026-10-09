<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
page_start('Filmes e Séries', 'filmes', $u);
?>
<div class="editor flow">
  <section class="panel">
    <div class="grp">
      <h3>Buscar título</h3>
      <input type="search" id="q" placeholder="Nome do filme ou série…" autocomplete="off">
      <div class="chips" id="cats">
        <button type="button" class="chip on" data-cat="trending_all">Em alta</button>
        <button type="button" class="chip" data-cat="movie_now">Nos cinemas</button>
        <button type="button" class="chip" data-cat="movie_popular">Filmes populares</button>
        <button type="button" class="chip" data-cat="movie_upcoming">Em breve</button>
        <button type="button" class="chip" data-cat="tv_popular">Séries populares</button>
        <button type="button" class="chip" data-cat="tv_air">Séries no ar</button>
        <button type="button" class="chip" data-cat="movie_top">Melhores filmes</button>
        <button type="button" class="chip" data-cat="tv_top">Melhores séries</button>
      </div>
      <div class="row wrap" style="margin-top:10px">
        <label class="c-check"><input type="checkbox" id="multi"><i></i><span>Selecionar vários</span></label>
        <span class="sp"></span><small id="selInfo"></small>
      </div>
      <div id="batchBox" hidden style="margin-top:8px">
        <button type="button" class="btn gold wide" id="batchDl">Baixar selecionados</button>
        <button type="button" class="btn sm ghost wide" id="batchClear" style="margin-top:6px">Limpar seleção</button>
      </div>
      <div class="grid strip" id="grid"></div>
      <button type="button" class="btn sm ghost wide" id="more" style="margin-top:8px" hidden>Carregar mais títulos</button>
    </div>
    <?php editor_models(); ?>
    <div class="grp" id="detail" hidden>
      <h3>Conteúdo do banner</h3>
      <label class="fld"><span>Título</span><input type="text" id="fTitle" maxlength="90"></label>
      <label class="fld"><span>Sinopse</span><textarea id="fSyn" rows="4" maxlength="600"></textarea></label>
      <div class="fld" id="bdWrap"><span>Cena de fundo</span><div class="arts" id="backdrops"></div></div>
      <div class="fld" id="psWrap"><span>Pôster</span><div class="arts" id="posters"></div></div>
    </div>
  </section>
  <?php editor_stage('movie'); editor_controls('movie', false); ?>
</div>
<?php editor_boot('movie', $u); ?>
<script>
(function () {
  const $ = s => document.querySelector(s), grid = $('#grid');
  let cat = 'trending_all', q = '', page = 1, items = [], cur = null, reqId = 0, picked = [];
  const MAX_BATCH = 20, same = (a, b) => a.id === b.id && a.type === b.type;
  const label = t => t === 'tv' ? 'SÉRIE' : 'FILME';
  // Ranking e catálogo usam os títulos selecionados; sem seleção, os primeiros da lista
  const listData = () => (picked.length > 1 ? picked : items).slice(0, 6).map(i => ({ title: i.title, year: i.year, rating: i.rating, typeLabel: label(i.type), poster: GG.tmdb(i.poster, 'w342') }));

  function draw() {
    grid.innerHTML = '';
    items.forEach(it => {
      const b = document.createElement('button');
      b.type = 'button'; b.title = it.title + (it.year ? ' (' + it.year + ')' : '');
      const n = picked.findIndex(x => same(x, it));
      if ($('#multi').checked ? n >= 0 : (cur && same(cur, it))) b.className = 'on';
      const img = new Image(); img.loading = 'lazy'; img.alt = it.title; img.src = GG.tmdb(it.poster, 'w185');
      const em = document.createElement('em'); em.textContent = label(it.type);
      b.append(img, em);
      if (n >= 0) { const k = document.createElement('i'); k.className = 'num'; k.textContent = n + 1; b.appendChild(k); }
      b.addEventListener('click', () => $('#multi').checked ? toggle(it) : pick(it));
      grid.appendChild(b);
    });
    if (!items.length) grid.innerHTML = '<div class="empty" style="grid-column:1/-1">Nada encontrado.</div>';
  }

  async function load(reset) {
    const my = ++reqId;
    if (reset) { page = 1; items = []; }
    try {
      const j = await Editor.get('/api/tmdb.php?' + new URLSearchParams(q ? { a: 'search', q, page } : { a: 'list', cat, page }));
      if (my !== reqId) return;
      items = items.concat(j.items);
      $('#more').hidden = page >= j.pages || !j.items.length;
      draw();
      // Vindo dos "Destaques da Semana" do painel: abre direto o título escolhido
      const qs = new URLSearchParams(location.search), wid = parseInt(qs.get('id'), 10);
      if (!cur && wid) pick(items.find(x => x.id === wid && x.type === qs.get('t')) || { id: wid, type: qs.get('t') === 'tv' ? 'tv' : 'movie', title: '' });
      else if (!cur && items[0]) pick(items[0]);
      else if (Editor.data) { Editor.data.list = listData(); Editor.render(); }
    } catch (e) { Editor.toast(e.message); }
  }

  function arts(el, paths, size, key, full) {
    el.innerHTML = '';
    el.parentNode.hidden = paths.length < 2;
    paths.forEach((p, i) => {
      const b = document.createElement('button');
      b.type = 'button'; if (i === 0) b.className = 'on';
      const img = new Image(); img.loading = 'lazy'; img.alt = ''; img.src = GG.tmdb(p, size);
      b.appendChild(img);
      b.addEventListener('click', () => {
        el.querySelectorAll('button').forEach(x => x.classList.remove('on'));
        b.classList.add('on');
        Editor.data[key] = GG.tmdb(p, full); Editor.render();
      });
      el.appendChild(b);
    });
  }

  const uniq = a => a.filter((v, i) => v && a.indexOf(v) === i);
  const details = it => Editor.get('/api/tmdb.php?' + new URLSearchParams({ a: 'details', type: it.type, id: it.id }));
  const toData = d => ({
    title: d.title, year: d.year, rating: d.rating, overview: d.overview, genres: d.genres, info: d.info, typeLabel: label(d.type),
    poster: GG.tmdb(d.poster || d.posters[0], 'w780'), backdrop: GG.tmdb(d.backdrop || d.backdrops[0], 'w1280'), titleLogo: GG.tmdb(d.titleLogo, 'w500'), list: listData(),
  });

  function selInfo() {
    $('#batchBox').hidden = !picked.length;
    $('#selInfo').textContent = picked.length ? picked.length + ' selecionado' + (picked.length > 1 ? 's' : '') + ' (máx. ' + MAX_BATCH + ')' : '';
    $('#batchDl').textContent = 'Baixar ' + picked.length + ' banner' + (picked.length > 1 ? 's' : '');
    if (Editor.data) { Editor.data.list = listData(); Editor.render(); }
  }
  function toggle(it) {
    const n = picked.findIndex(x => same(x, it));
    if (n >= 0) picked.splice(n, 1);
    else if (picked.length >= MAX_BATCH) { Editor.toast('Máximo de ' + MAX_BATCH + ' títulos por vez.'); return; }
    else picked.push(it);
    selInfo();
    if (n < 0) pick(it); else draw();
  }
  $('#multi').addEventListener('change', draw);
  $('#batchClear').addEventListener('click', () => { picked = []; selInfo(); draw(); });
  $('#batchDl').addEventListener('click', ev => Editor.batch(ev.currentTarget,
    picked.map(it => ({ name: it.title, load: async () => toData(await details(it)) })), 'banners-' + picked.length + '-titulos.zip'));

  async function pick(it) {
    cur = it; draw();
    try {
      const d = await details(it);
      if (cur !== it) return;
      const bds = uniq([d.backdrop].concat(d.backdrops)), pss = uniq([d.poster].concat(d.posters));
      Editor.data = toData(d);
      $('#detail').hidden = false;
      $('#fTitle').value = d.title; $('#fSyn').value = d.overview;
      arts($('#backdrops'), bds, 'w300', 'backdrop', 'w1280');
      arts($('#posters'), pss, 'w92', 'poster', 'w780');
      Editor.render();
    } catch (e) { Editor.toast(e.message); }
  }

  $('#fTitle').addEventListener('input', e => { if (Editor.data) { Editor.data.title = e.target.value; Editor.render(); } });
  $('#fSyn').addEventListener('input', e => { if (Editor.data) { Editor.data.overview = e.target.value; Editor.render(); } });
  document.querySelectorAll('#cats .chip').forEach(c => c.addEventListener('click', () => {
    document.querySelectorAll('#cats .chip').forEach(x => x.classList.toggle('on', x === c));
    cat = c.dataset.cat; q = ''; $('#q').value = ''; load(true);
  }));
  let t = 0;
  $('#q').addEventListener('input', e => {
    clearTimeout(t);
    t = setTimeout(() => { q = e.target.value.trim(); load(true); }, 380);
  });
  $('#more').addEventListener('click', () => { page++; load(false); });
  load(true);
})();
</script>
<?php page_end();
