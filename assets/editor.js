/* Gerador Gold — editor (estado do estilo, preview ao vivo, miniaturas, estilos salvos e download) */
(function () {
  'use strict';
  const C = window.GGCFG, kind = C.kind, $ = s => document.querySelector(s), $$ = s => Array.from(document.querySelectorAll(s));
  const LS = 'gg:' + C.uid + ':' + kind;
  const brandStyle = () => ({
    primary: C.brand.primary, secondary: C.brand.secondary, bg: C.brand.bg, text: C.brand.text,
    fontTitle: C.brand.fontTitle, fontBody: C.brand.fontBody,
  });
  const DEFAULTS = Object.assign({
    format: 'story', layout: C.layouts[0].id, cta: C.brand.cta, footer: C.brand.footer,
    showLogo: true, logoAlign: 'center', logoSize: 100, overlay: 60,
  }, brandStyle(), kind === 'promo' ? {
    title: 'FILMES, SÉRIES E CANAIS', sub: 'Tudo em um só lugar, sem travar', devTitle: 'Disponível em todos os dispositivos',
    showDevices: true, showFeatures: true, overlay: 45,
  } : kind === 'movie' ? {
    headline: 'JÁ DISPONÍVEL', rankTitle: 'TOP DA SEMANA', art: 'auto', useTitleLogo: false, upper: true,
    showRating: true, showYear: true, showGenres: true, showInfo: true, showSynopsis: true,
  } : {
    title: 'JOGOS DO DIA', showDate: true, showComp: true, showChannels: true, showScore: false, bgStyle: 'auto', perPage: '0',
  });

  // Aceita só chaves conhecidas e com o mesmo tipo do padrão (protege contra dados antigos/corrompidos)
  function clean(src) {
    const out = {};
    Object.keys(DEFAULTS).forEach(k => {
      if (src && typeof src[k] === typeof DEFAULTS[k]) out[k] = src[k];
    });
    ['primary', 'secondary', 'bg', 'text'].forEach(k => { if (out[k] && !/^#[0-9a-f]{6}$/i.test(out[k])) delete out[k]; });
    if (out.format && !GG.FORMATS[out.format]) delete out.format;
    if (out.bgStyle && !['auto', 'pitch', 'stadium', 'net', 'upload'].includes(out.bgStyle)) delete out.bgStyle;
    if (out.layout && !C.layouts.some(l => l.id === out.layout)) delete out.layout;
    return out;
  }

  let saved = null;
  try { saved = JSON.parse(localStorage.getItem(LS)); } catch (e) { /* sem armazenamento local */ }
  const st = Object.assign({}, DEFAULTS, clean(saved));
  // Se a marca mudou de cores ou fontes desde a última visita, o estilo guardado aqui é atualizado junto
  const sig = Object.values(brandStyle()).join('|');
  try {
    if (localStorage.getItem(LS + ':brand') !== sig) { Object.assign(st, brandStyle()); localStorage.setItem(LS + ':brand', sig); }
  } catch (e) { /* sem armazenamento local */ }

  const Editor = window.Editor = { st, data: null, page: 0 };
  const cv = $('#cv'), wrap = $('#canvasWrap');

  function toast(msg) {
    $$('.toast').forEach(t => t.remove());
    const t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2800);
  }
  Editor.toast = toast;

  /* ---------- Especificação do banner ---------- */
  function pagesFor(layout) {
    const games = (Editor.data && Editor.data.games) || [], per = GG.perPage(layout, st.format, st), out = [];
    for (let i = 0; i < games.length; i += per) out.push(games.slice(i, i + per));
    return { per, pages: out.length ? out : [[]] };
  }
  function spec(layout, page) {
    const s = { kind, layout, format: st.format, st, brand: C.brand, locked: !!C.locked };
    if (kind === 'promo') { s.data = Editor.data || {}; return s; }
    if (kind === 'movie') { s.data = Editor.data || { title: 'Escolha um título', overview: '', genres: [] }; return s; }
    const p = pagesFor(layout), idx = Math.min(page == null ? Editor.page : page, p.pages.length - 1);
    s.data = { games: p.pages[idx], page: idx + 1, pages: p.pages.length, perPage: p.per, dateLabel: (Editor.data && Editor.data.dateLabel) || '' };
    return s;
  }

  /* ---------- Render com descarte de pedidos antigos ---------- */
  let tok = 0, timer = 0, thumbTimer = 0;
  async function render() {
    const my = ++tok;
    wrap.classList.add('busy');
    await GG.fonts(st);
    if (kind === 'games') {
      const n = pagesFor(st.layout).pages.length;
      Editor.page = Math.max(0, Math.min(Editor.page, n - 1));
      $('#pgInfo').textContent = (Editor.page + 1) + ' / ' + n;
      $('#pager').style.visibility = n > 1 ? 'visible' : 'hidden';
      $('#dlAll').hidden = n < 2;
    }
    await GG.render(cv, Object.assign(spec(st.layout), { guard: () => my === tok }));
    if (my !== tok) return;
    wrap.classList.remove('busy');
    clearTimeout(thumbTimer);
    thumbTimer = setTimeout(() => thumbs(my), 350);
  }
  async function thumbs(my) {
    // Só as miniaturas da categoria aberta são desenhadas (com dezenas de modelos, desenhar todas pesaria no navegador)
    for (const l of C.layouts) {
      if (my !== tok) return;
      if ((l.g || '') !== group) continue;
      const c = document.querySelector('.thumb[data-layout="' + l.id + '"] canvas');
      await GG.render(c, Object.assign(spec(l.id, 0), { guard: () => my === tok }), 0.15);
    }
  }
  Editor.render = function () {
    clearTimeout(timer);
    timer = setTimeout(render, 50);
    try { localStorage.setItem(LS, JSON.stringify(st)); } catch (e) { /* ignora */ }
  };

  /* ---------- Controles ---------- */
  function sync() {
    $$('[data-st]').forEach(el => {
      const v = st[el.dataset.st];
      if (el.type === 'checkbox') el.checked = !!v; else el.value = v;
    });
    $$('#formats button').forEach(b => b.classList.toggle('on', b.dataset.format === st.format));
    $$('.thumb').forEach(b => b.classList.toggle('on', b.dataset.layout === st.layout));
    $$('#bgs button').forEach(b => b.classList.toggle('on', b.dataset.bg === st.bgStyle));
  }
  $$('[data-st]').forEach(el => el.addEventListener(el.tagName === 'SELECT' || el.type === 'checkbox' ? 'change' : 'input', () => {
    const k = el.dataset.st;
    st[k] = el.type === 'checkbox' ? el.checked : el.type === 'range' ? +el.value : el.value;
    if (k === 'perPage') Editor.page = 0;
    Editor.render();
  }));
  $$('#formats button').forEach(b => b.addEventListener('click', () => { st.format = b.dataset.format; Editor.page = 0; sync(); Editor.render(); }));

  const th = $('#thumbs');
  C.layouts.forEach(l => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'thumb'; b.dataset.layout = l.id;
    b.dataset.group = l.g || '';
    b.title = 'Modelo ' + l.n + ' · ' + l.name;
    b.innerHTML = '<canvas width="162" height="288"></canvas><i class="mnum"></i><span></span>';
    b.querySelector('.mnum').textContent = l.n;
    b.lastChild.textContent = l.name;
    b.addEventListener('click', () => { st.layout = l.id; Editor.page = 0; sync(); Editor.render(); });
    th.appendChild(b);
  });

  // Categorias de modelos: o usuário escolhe a categoria e depois o modelo
  const groups = C.layouts.map(l => l.g || '').filter((g, i, a) => a.indexOf(g) === i);
  let group = (C.layouts.find(l => l.id === st.layout) || C.layouts[0]).g || '';
  const want = new URLSearchParams(location.search).get('g');
  if (want && groups.includes(want) && want !== group) { group = want; st.layout = C.layouts.find(l => (l.g || '') === want).id; }
  function showGroup(g) {
    group = g;
    $$('#lgroups .chip').forEach(c => c.classList.toggle('on', c.dataset.g === g));
    $$('.thumb').forEach(b => { b.hidden = b.dataset.group !== g; });
    $('#lcount').textContent = C.layouts.filter(l => (l.g || '') === g).length + ' nesta categoria · ' + C.layouts.length + ' no total';
    clearTimeout(thumbTimer);
    thumbTimer = setTimeout(() => thumbs(tok), 120);
  }
  groups.forEach(g => {
    const c = document.createElement('button');
    c.type = 'button'; c.className = 'chip'; c.dataset.g = g; c.textContent = g;
    c.addEventListener('click', () => showGroup(g));
    $('#lgroups').appendChild(c);
  });
  $('#lgroups').hidden = groups.length < 2;
  showGroup(group);

  const PALETTES = [
    ['#F5C518', '#FF8A00', '#0B0B12', '#FFFFFF'], ['#E50914', '#FF5F6D', '#0A0A0A', '#FFFFFF'], ['#00E5FF', '#7C4DFF', '#070B1A', '#FFFFFF'],
    ['#22C55E', '#A3E635', '#06130C', '#FFFFFF'], ['#FF2D95', '#FFB800', '#12061F', '#FFFFFF'], ['#3B82F6', '#22D3EE', '#0A1020', '#FFFFFF'],
    ['#FF6B00', '#FFD000', '#140A02', '#FFFFFF'], ['#A855F7', '#EC4899', '#0D0716', '#FFFFFF'], ['#FFFFFF', '#C7C7D1', '#101014', '#FFFFFF'],
  ];
  PALETTES.forEach(p => {
    const b = document.createElement('button');
    b.type = 'button'; b.title = 'Aplicar paleta';
    b.style.background = 'linear-gradient(135deg,' + p[2] + ' 0 45%,' + p[0] + ' 45% 75%,' + p[1] + ' 75%)';
    b.addEventListener('click', () => { [st.primary, st.secondary, st.bg, st.text] = p; sync(); Editor.render(); });
    $('#palettes').appendChild(b);
  });
  $('#brandColors').addEventListener('click', () => { Object.assign(st, brandStyle()); sync(); Editor.render(); });

  if (kind === 'games') {
    // Fundo: o do modelo, um dos três prontos ou uma imagem enviada (fica guardada na marca do usuário)
    const thumb = () => { $('#bgThumb').style.backgroundImage = C.brand.bgImage ? 'url("' + C.brand.bgImage + '")' : ''; };
    thumb();
    $$('#bgs button').forEach(b => b.addEventListener('click', () => {
      if (b.dataset.bg === 'upload' && !C.brand.bgImage) { $('#bgFile').click(); return; }
      st.bgStyle = b.dataset.bg; sync(); Editor.render();
    }));
    $('#bgPick').addEventListener('click', () => $('#bgFile').click());
    $('#bgFile').addEventListener('change', async ev => {
      const f = ev.target.files[0];
      ev.target.value = '';
      if (!f) return;
      if (f.size > 6 * 1024 * 1024) { toast('Imagem muito grande (máx. 6 MB).'); return; }
      const fd = new FormData();
      fd.append('a', 'bg_upload'); fd.append('bg', f);
      try {
        const r = await fetch(C.base + '/api/user.php', { method: 'POST', headers: { 'X-CSRF': C.csrf }, body: fd });
        const j = await r.json().catch(() => ({ error: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }));
        if (!r.ok || j.error) throw new Error(j.error || 'Erro ' + r.status);
        C.brand.bgImage = j.url; thumb();
        st.bgStyle = 'upload'; sync(); Editor.render();
        toast('Fundo enviado. Ele fica salvo na sua marca.');
      } catch (e) { toast(e.message); }
    });
    $('#pgPrev').addEventListener('click', () => { Editor.page = Math.max(0, Editor.page - 1); Editor.render(); });
    $('#pgNext').addEventListener('click', () => { Editor.page++; Editor.render(); });
  }

  /* ---------- API ---------- */
  async function post(params) {
    const body = window.GG_body(Object.assign({ kind }, params));
    const r = await fetch(C.base + '/api/user.php', { method: 'POST', headers: { 'X-CSRF': C.csrf }, body });
    const j = await r.json().catch(() => ({ error: 'Resposta inválida do servidor.' }));
    if (!r.ok || j.error) throw new Error(j.error || 'Erro ' + r.status);
    return j;
  }
  Editor.get = async function (path) {
    const r = await fetch(C.base + window.GG_url(path));
    const j = await r.json().catch(() => ({ error: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }));
    if (r.status === 401) { location.href = C.base + '/login.php'; throw new Error(j.error); }
    if (r.status === 403 && !j.error.includes('restrito')) throw new Error('O servidor bloqueou a requisição (403). Aguarde alguns minutos e recarregue a página.');
    if (r.status === 402) throw new Error(j.error || 'Assine um plano para continuar.');
    if (!r.ok || j.error) throw new Error(j.error || 'Erro ' + r.status);
    return j;
  };

  /* ---------- Estilos salvos ---------- */
  const sel = $('#presetSel');
  let presets = C.presets;
  function fillPresets(keep) {
    sel.innerHTML = '<option value="">— selecione —</option>';
    presets.forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.name; sel.appendChild(o); });
    if (keep) sel.value = keep;
  }
  sel.addEventListener('change', () => {
    const p = presets.find(x => String(x.id) === sel.value);
    if (!p) return;
    Object.assign(st, clean(p.data));
    const lg = C.layouts.find(l => l.id === st.layout);
    if (lg && (lg.g || '') !== group) showGroup(lg.g || '');
    Editor.page = 0; sync(); Editor.render();
  });
  $('#presetSave').addEventListener('click', async () => {
    const cur = presets.find(x => String(x.id) === sel.value);
    const name = (prompt('Nome do estilo:', cur ? cur.name : '') || '').trim();
    if (!name) return;
    try {
      presets = (await post({ a: 'preset_save', name, data: JSON.stringify(st) })).presets;
      const p = presets.find(x => x.name === name);
      fillPresets(p && p.id);
      toast('Estilo "' + name + '" salvo.');
    } catch (e) { toast(e.message); }
  });
  $('#presetDel').addEventListener('click', async () => {
    const p = presets.find(x => String(x.id) === sel.value);
    if (!p || !confirm('Excluir o estilo "' + p.name + '"?')) return;
    try { presets = (await post({ a: 'preset_delete', id: p.id })).presets; fillPresets(); toast('Estilo excluído.'); } catch (e) { toast(e.message); }
  });

  /* ---------- Download / compartilhar ---------- */
  const slug = s => String(s || 'banner').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) || 'banner';
  const baseName = () => kind === 'promo' ? 'marketing-' + st.layout : kind === 'movie' ? slug(Editor.data && Editor.data.title) : 'jogos-' + slug((Editor.data && Editor.data.dateLabel) || 'do-dia');
  // data/format opcionais: usados na geração em lote, sem mexer no que está na tela
  async function blob(page, data, format) {
    const c = document.createElement('canvas'), s = spec(st.layout, page);
    if (data) s.data = data;
    if (format) { s.format = format; if (kind === 'games') Object.assign(s, specFor(format, page)); }
    await GG.fonts(st);
    await GG.render(c, s);
    return new Promise((res, rej) => c.toBlob(b => { c.width = c.height = 0; b ? res(b) : rej(new Error('Não foi possível gerar a imagem.')); }, 'image/png'));
  }
  // Páginas de jogos mudam conforme o formato (cabem mais jogos no Stories do que no Quadrado)
  function pagesIn(format) {
    const games = (Editor.data && Editor.data.games) || [], per = GG.perPage(st.layout, format, st), out = [];
    for (let i = 0; i < games.length; i += per) out.push(games.slice(i, i + per));
    return { per, pages: out.length ? out : [[]] };
  }
  function specFor(format, page) {
    const p = pagesIn(format), idx = Math.min(page || 0, p.pages.length - 1);
    return { data: { games: p.pages[idx], page: idx + 1, pages: p.pages.length, perPage: p.per, dateLabel: (Editor.data && Editor.data.dateLabel) || '' } };
  }
  async function zip(files, name) {
    if (files.length === 1) { save(files[0][1], files[0][0]); return; }
    if (window.JSZip) {
      const z = new JSZip();
      files.forEach(f => z.file(f[0], f[1]));
      save(await z.generateAsync({ type: 'blob' }), name);
    } else {
      for (const f of files) { save(f[1], f[0]); await new Promise(r => setTimeout(r, 400)); }
    }
  }
  const allFormats = () => { const el = $('#allFmt'); return el && el.checked ? Object.keys(GG.FORMATS) : [st.format]; };
  function save(b, name) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(b); a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }
  const logTitle = () => kind === 'promo' ? 'Marketing' : kind === 'movie' ? ((Editor.data && Editor.data.title) || '') : ((Editor.data && Editor.data.games.length) || 0) + ' jogos';
  // Pede autorização ao servidor ANTES de gerar: é ali que o uso é contado e que o teste grátis ou o plano vencido barram
  const claim = n => post({ a: 'log', n, layout: st.layout, format: st.format, title: logTitle() });
  const subscribe = () => { toast('Assine um plano para gerar e baixar seus banners.'); setTimeout(() => { location.href = C.base + '/assinar.php'; }, 1600); };
  const ready = () => {
    if (kind === 'promo') return true;
    if (kind === 'movie' ? !Editor.data : !(Editor.data && Editor.data.games.length)) { toast(kind === 'movie' ? 'Escolha um título primeiro.' : 'Selecione ao menos um jogo.'); return false; }
    return true;
  };
  async function busy(btn, fn) {
    if (C.locked) { subscribe(); return; }
    btn.disabled = true;
    try { await fn(); } catch (e) { toast(e.message || 'Falha ao gerar o banner.'); if (/assine|teste grátis|plano venceu/i.test(e.message || '')) setTimeout(() => { location.href = C.base + '/assinar.php'; }, 2600); }
    btn.disabled = false;
  }

  $('#dl').addEventListener('click', ev => ready() && busy(ev.currentTarget, async () => {
    const fmts = allFormats(), files = [];
    if (fmts.length === 1) {
      const n = kind === 'games' ? pagesFor(st.layout).pages.length : 1;
      await claim(1);
      save(await blob(Editor.page), baseName() + '-' + st.format + (n > 1 ? '-' + (Editor.page + 1) : '') + '.png');
      return;
    }
    // Um arquivo por formato (nos jogos, só a página que está na tela)
    await claim(fmts.length);
    for (const f of fmts) files.push([baseName() + '-' + f + '.png', await blob(kind === 'games' ? Math.min(Editor.page, pagesIn(f).pages.length - 1) : 0, null, f)]);
    await zip(files, baseName() + '-formatos.zip');
  }));

  if (kind === 'games') {
    $('#dlAll').addEventListener('click', ev => ready() && busy(ev.currentTarget, async () => {
      const files = [], fl = allFormats();
      await claim(fl.reduce((a, f) => a + pagesIn(f).pages.length, 0));
      for (const f of fl) {
        const n = pagesIn(f).pages.length;
        for (let i = 0; i < n; i++) files.push([baseName() + '-' + f + '-' + (i + 1) + '.png', await blob(i, null, f)]);
      }
      await zip(files, baseName() + '.zip');
    }));
  }

  // Geração em lote (filmes/séries): recebe uma lista de { name, load() } e devolve tudo em um ZIP
  Editor.batch = async function (btn, jobs, zipName) {
    const label = btn.textContent, files = [], fmts = allFormats();
    if (C.locked) { subscribe(); return; }
    btn.disabled = true;
    try {
      await post({ a: 'log', n: jobs.length * fmts.length, layout: st.layout, format: fmts.length > 1 ? 'todos' : st.format, title: jobs.length + ' títulos em lote' });
      for (let i = 0; i < jobs.length; i++) {
        btn.textContent = 'Gerando ' + (i + 1) + ' de ' + jobs.length + '…';
        const data = await jobs[i].load();
        for (const f of fmts) files.push([slug(jobs[i].name) + '-' + f + '.png', await blob(0, data, f)]);
      }
      await zip(files, zipName);
      toast(files.length + ' banners gerados.');
    } catch (e) { toast(e.message || 'Falha ao gerar os banners.'); }
    btn.disabled = false; btn.textContent = label;
  };

  const shareBtn = $('#share');
  try {
    if (navigator.canShare && navigator.canShare({ files: [new File([''], 'a.png', { type: 'image/png' })] })) shareBtn.hidden = false;
  } catch (e) { /* sem suporte */ }
  shareBtn.addEventListener('click', ev => ready() && busy(ev.currentTarget, async () => {
    await claim(1);
    const file = new File([await blob(Editor.page)], baseName() + '-' + st.format + '.png', { type: 'image/png' });
    try { await navigator.share({ files: [file] }); } catch (e) { if (e.name !== 'AbortError') throw e; }
  }));

  Editor.sync = sync;

  fillPresets();
  sync();
  Editor.render();
})();
