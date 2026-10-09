<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
page_start('Motion', 'motion', $u);
?>
<div class="editor">
  <section class="panel">
    <div class="grp">
      <h3>Buscar título</h3>
      <input type="search" id="q" placeholder="Nome do filme ou série…" autocomplete="off">
      <div class="chips" id="cats">
        <button type="button" class="chip on" data-cat="trending_all">Em alta</button>
        <button type="button" class="chip" data-cat="movie_now">Nos cinemas</button>
        <button type="button" class="chip" data-cat="movie_upcoming">Em breve</button>
        <button type="button" class="chip" data-cat="movie_popular">Filmes populares</button>
        <button type="button" class="chip" data-cat="tv_popular">Séries populares</button>
        <button type="button" class="chip" data-cat="tv_air">Séries no ar</button>
      </div>
      <div class="grid strip" id="grid"></div>
      <button type="button" class="btn sm ghost wide" id="more" style="margin-top:8px" hidden>Carregar mais títulos</button>
    </div>
    <div class="grp" id="detail" hidden>
      <h3>Conteúdo do motion</h3>
      <label class="fld"><span>Título</span><input type="text" id="fTitle" maxlength="90"></label>
      <label class="fld"><span>Sinopse</span><textarea id="fSyn" rows="3" maxlength="600"></textarea></label>
      <div id="trailerBox" hidden style="margin-top:8px">
        <div class="row" style="gap:8px;align-items:center">
          <a id="trailerLink" class="btn sm" target="_blank" rel="noopener" href="#">▶ Abrir trailer oficial</a>
          <button type="button" class="btn sm ghost" id="trailerCopy">Copiar link</button>
        </div>
        <p class="note" style="margin-top:6px">Trailer oficial do título no YouTube. Abra, baixe o trecho que quiser e envie em “Vídeo de fundo” acima para o motion sair com cenas reais e som.</p>
      </div>
    </div>
    <div class="grp">
      <h3>Vídeo de fundo (opcional)</h3>
      <input type="file" id="trFile" accept="video/mp4,video/webm,video/quicktime,video/*" hidden>
      <button type="button" class="btn wide" id="trPick">Usar um vídeo do meu aparelho</button>
      <div class="row" id="trInfo" hidden style="margin-top:8px">
        <small id="trName" class="sp" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></small>
        <button type="button" class="btn sm ghost" id="trClear">Remover</button>
      </div>
      <div id="trOpts" hidden style="margin-top:10px">
        <?= ctl_select('fit', 'Enquadramento', ['fit' => 'Vídeo inteiro, com fundo desfocado', 'fill' => 'Tela cheia (corta as laterais)']) ?>
        <div class="two">
          <?= ctl_select('clip', 'Trecho', ['15' => '15 segundos', '30' => '30 segundos', '60' => '60 segundos', '90' => '90 segundos', 'full' => 'Vídeo completo']) ?>
          <label class="fld"><span>Começar em (segundos)</span><input type="number" data-st="start" min="0" step="1" value="0"></label>
        </div>
      </div>
      <p class="note" style="margin-top:8px">Sem vídeo, o motion é montado com as cenas oficiais do título. Com um vídeo seu (MP4), ele entra com o som original, mais sua logo, título, botão e cartão final. O arquivo não é enviado ao servidor.</p>
    </div>
  </section>

  <section class="stage">
    <div class="canvas-wrap" id="canvasWrap"><canvas id="cv" width="720" height="1280"></canvas><div class="safe"><i></i><i></i><i></i></div><div class="spin" id="spin"></div></div>
    <div class="actions">
      <button type="button" class="btn gold" id="rec">Gerar vídeo</button>
      <button type="button" class="btn ghost" id="replay"><?= icon('refresh') ?> Reiniciar prévia</button>
    </div>
    <small style="text-align:center;max-width:420px">A gravação leva o mesmo tempo do vídeo. Mantenha esta aba aberta e visível até o download começar.</small>
  </section>

  <section class="panel controls" id="controls">
    <div class="grp">
      <h3>Formato</h3>
      <div class="seg four" id="formats">
        <button type="button" data-format="tiktok"><b>TikTok</b><small>1080×1920 · zona segura</small></button>
        <button type="button" data-format="story"><b>Stories / Reels</b><small>9:16</small></button>
        <button type="button" data-format="feed"><b>Feed</b><small>4:5</small></button>
        <button type="button" data-format="square"><b>Quadrado</b><small>1:1</small></button>
      </div>
    </div>
    <div class="grp">
      <h3>Padrão de motion</h3>
      <div class="pats" id="styles">
        <button type="button" data-style="hook"><b>Gancho viral</b><small>Frase de impacto e cortes rápidos</small></button>
        <button type="button" data-style="top5"><b>Top 5 contagem</b><small>Do 5º ao 1º da lista atual</small></button>
        <button type="button" data-style="cinema"><b>Cinematográfico</b><small>Zoom lento e fusão entre cenas</small></button>
        <button type="button" data-style="slide"><b>Deslizar</b><small>Cenas empurram umas às outras</small></button>
        <button type="button" data-style="zoom"><b>Impacto</b><small>Cortes secos com zoom e clarão</small></button>
        <button type="button" data-style="strips"><b>Faixas</b><small>Três tiras que se juntam</small></button>
        <button type="button" data-style="poster"><b>Pôster flutuante</b><small>Pôster em movimento com brilho</small></button>
        <button type="button" data-style="neon"><b>Neon</b><small>Luzes coloridas e título brilhante</small></button>
        <button type="button" data-style="strobe"><b>Estrobo</b><small>Cortes muito rápidos com clarões</small></button>
        <button type="button" data-style="polaroid"><b>Fotos</b><small>Cenas caem como fotografias</small></button>
        <button type="button" data-style="reveal"><b>Letreiro</b><small>Texto revelado com cursor</small></button>
        <button type="button" data-style="iris"><b>Íris</b><small>Abre em círculo pelo centro</small></button>
        <button type="button" data-style="diag"><b>Corte diagonal</b><small>Varredura inclinada colorida</small></button>
        <button type="button" data-style="doors"><b>Portas</b><small>Abre do centro para os lados</small></button>
        <button type="button" data-style="rise"><b>Subida</b><small>Cena nova entra de baixo</small></button>
        <button type="button" data-style="blinds"><b>Persianas</b><small>Seis faixas em sequência</small></button>
        <button type="button" data-style="tiles"><b>Mosaico</b><small>Doze blocos surgem um a um</small></button>
        <button type="button" data-style="pan"><b>Panorâmica</b><small>Travelling lateral com fusão</small></button>
      </div>
      <div class="two">
        <div id="durFld"><?= ctl_select('duration', 'Duração', ['8' => '8 segundos', '10' => '10 segundos', '12' => '12 segundos', '15' => '15 segundos', '20' => '20 segundos']) ?></div>
        <?= ctl_select('quality', 'Qualidade', ['720' => 'HD 720p (recomendado)', '1080' => 'Full HD 1080p']) ?>
      </div>
    </div>
    <div class="grp">
      <h3>Cores <button type="button" class="link" id="brandColors">usar cores da marca</button></h3>
      <div class="colors">
        <?= ctl_color('primary', 'Destaque') . ctl_color('secondary', 'Secundária') . ctl_color('bg', 'Fundo') . ctl_color('text', 'Texto') ?>
      </div>
      <div class="swatches" id="palettes"></div>
    </div>
    <div class="grp">
      <h3>Textos</h3>
      <?= ctl_text('hook', 'Gancho (padrão Gancho viral)', 40) . ctl_text('topTitle', 'Título (padrão Top 5)', 30) . ctl_text('headline', 'Chamada', 30) . ctl_text('cta', 'Botão (CTA)', 34) . ctl_text('footer', 'Rodapé / contato', 60) ?>
      <div class="two">
        <?= ctl_select('fontTitle', 'Fonte dos títulos', FONTS_TITLE) . ctl_select('fontBody', 'Fonte dos textos', FONTS_BODY) ?>
      </div>
    </div>
    <div class="grp">
      <h3>Elementos</h3>
      <div class="checks">
        <?= ctl_check('showLogo', 'Logo') . ctl_check('showRating', 'Nota') . ctl_check('showYear', 'Ano') . ctl_check('showGenres', 'Gêneros') . ctl_check('showInfo', 'Duração') . ctl_check('showSynopsis', 'Sinopse') . ctl_check('useTitleLogo', 'Logo do título') . ctl_check('upper', 'Maiúsculas') ?>
      </div>
    </div>
    <div class="grp">
      <h3>Logo</h3>
      <?= ctl_select('logoAlign', 'Posição', ['left' => 'Esquerda', 'center' => 'Centro', 'right' => 'Direita']) . ctl_range('logoSize', 'Tamanho', 50, 200) ?>
    </div>
  </section>
</div>
<?php editor_boot('video', $u, ['engine.js', 'video.js']); ?>
<script>
(function () {
  const $ = s => document.querySelector(s), grid = $('#grid');
  let cat = 'trending_all', q = '', page = 1, items = [], cur = null, reqId = 0;
  const label = t => t === 'tv' ? 'SÉRIE' : 'FILME';
  // Top 5: os cinco primeiros da lista que está na tela (Em alta, busca etc.)
  const listData = () => items.slice(0, 5).map(i => ({ title: i.title, rating: i.rating, poster: GG.tmdb(i.poster, 'w780') }));

  function draw() {
    grid.innerHTML = '';
    items.forEach(it => {
      const b = document.createElement('button');
      b.type = 'button'; b.title = it.title + (it.year ? ' (' + it.year + ')' : '');
      if (cur && cur.id === it.id && cur.type === it.type) b.className = 'on';
      const img = new Image(); img.loading = 'lazy'; img.alt = it.title; img.src = GG.tmdb(it.poster, 'w185');
      const em = document.createElement('em'); em.textContent = label(it.type);
      b.append(img, em);
      b.addEventListener('click', () => pick(it));
      grid.appendChild(b);
    });
    if (!items.length) grid.innerHTML = '<div class="empty" style="grid-column:1/-1">Nada encontrado.</div>';
  }

  async function load(reset) {
    const my = ++reqId;
    if (reset) { page = 1; items = []; }
    try {
      const j = await Video.get('/api/tmdb.php?' + new URLSearchParams(q ? { a: 'search', q, page } : { a: 'list', cat, page }));
      if (my !== reqId) return;
      items = items.concat(j.items);
      $('#more').hidden = page >= j.pages || !j.items.length;
      draw();
      if (!cur && items[0]) pick(items[0]);
      else if (Video.data) { Video.data.list = listData(); Video.refresh(); }
    } catch (e) { Video.toast(e.message); }
  }

  async function pick(it) {
    cur = it; draw();
    try {
      const d = await Video.get('/api/tmdb.php?' + new URLSearchParams({ a: 'details', type: it.type, id: it.id }));
      if (cur !== it) return;
      const uniq = a => a.filter((v, i) => v && a.indexOf(v) === i);
      Video.data = {
        title: d.title, year: d.year, rating: d.rating, overview: d.overview, genres: d.genres, info: d.info, typeLabel: label(d.type),
        poster: GG.tmdb(d.poster, 'w780'), titleLogo: GG.tmdb(d.titleLogo, 'w500'),
        scenes: uniq([d.backdrop].concat(d.backdrops)).slice(0, 4).map(p => GG.tmdb(p, 'w1280')),
        list: listData(),
      };
      $('#detail').hidden = false;
      $('#fTitle').value = d.title; $('#fSyn').value = d.overview;
      const tb = $('#trailerBox'), tl = $('#trailerLink');
      if (d.trailer) { tl.href = d.trailer; tb.hidden = false; } else { tb.hidden = true; }
      Video.refresh();
    } catch (e) { Video.toast(e.message); }
  }

  $('#trPick').addEventListener('click', () => $('#trFile').click());
  $('#trFile').addEventListener('change', e => {
    const f = e.target.files[0];
    if (!f) return;
    if (f.size > 600 * 1024 * 1024) { Video.toast('Arquivo muito grande (máx. 600 MB).'); e.target.value = ''; return; }
    $('#trName').textContent = f.name;
    Video.setFile(f);
  });
  $('#trClear').addEventListener('click', () => { $('#trFile').value = ''; Video.setFile(null); });
  document.addEventListener('gg:file', () => {
    const on = Video.hasFile(), d = Math.round(Video.fileDuration());
    $('#trInfo').hidden = $('#trOpts').hidden = !on;
    $('#durFld').hidden = on;
    if (on) $('#trName').textContent += ' · ' + Math.floor(d / 60) + ':' + String(d % 60).padStart(2, '0');
  });
  $('#trailerCopy').addEventListener('click', async () => {
    const url = $('#trailerLink').href;
    try { await navigator.clipboard.writeText(url); Video.toast('Link do trailer copiado.'); }
    catch (e) { window.prompt('Copie o link do trailer:', url); }
  });
  $('#fTitle').addEventListener('input', e => { if (Video.data) { Video.data.title = e.target.value; Video.refresh(); } });
  $('#fSyn').addEventListener('input', e => { if (Video.data) { Video.data.overview = e.target.value; Video.refresh(); } });
  document.querySelectorAll('#cats .chip').forEach(c => c.addEventListener('click', () => {
    document.querySelectorAll('#cats .chip').forEach(x => x.classList.toggle('on', x === c));
    cat = c.dataset.cat; q = ''; $('#q').value = ''; load(true);
  }));
  let t = 0;
  $('#q').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => { q = e.target.value.trim(); load(true); }, 380); });
  $('#more').addEventListener('click', () => { page++; load(false); });
  load(true);
})();
</script>
<?php page_end();
