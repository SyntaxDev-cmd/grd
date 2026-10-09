/* Gerador Gold — vídeo promocional (Canvas + MediaRecorder, tudo no navegador)
   Padrões de motion sobre as cenas do TMDB; opcionalmente, um vídeo do próprio usuário como fundo (com áudio). */
(function () {
  'use strict';
  const C = window.GGCFG, K = GG.kit, W = 1080, P = 64;
  // TikTok usa 1080×1920, mas a interface do app cobre o topo, a base e a lateral direita:
  // nesse formato os textos ficam dentro da zona segura
  const FMT = Object.assign({ tiktok: { w: 1080, h: 1920 } }, GG.FORMATS);
  const SAFE = { t: 150, b: 400, r: 130 }, NOSAFE = { t: 0, b: 0, r: 0 };
  const $ = s => document.querySelector(s), $$ = s => Array.from(document.querySelectorAll(s));
  const LS = 'gg:' + C.uid + ':video';
  const brandStyle = () => ({
    primary: C.brand.primary, secondary: C.brand.secondary, bg: C.brand.bg, text: C.brand.text,
    fontTitle: C.brand.fontTitle, fontBody: C.brand.fontBody,
  });
  const DEFAULTS = Object.assign({
    format: 'story', style: 'cinema', duration: '12', quality: '720', fit: 'fit', clip: '30', start: 0,
    hook: 'VOCÊ PRECISA VER ISSO', topTitle: 'TOP 5 DA SEMANA',
    headline: 'JÁ DISPONÍVEL', cta: C.brand.cta, footer: C.brand.footer,
    showLogo: true, logoAlign: 'center', logoSize: 100, upper: true, useTitleLogo: false,
    showRating: true, showYear: true, showGenres: true, showInfo: true, showSynopsis: true,
  }, brandStyle());

  function clean(src) {
    const out = {};
    Object.keys(DEFAULTS).forEach(k => { if (src && typeof src[k] === typeof DEFAULTS[k]) out[k] = src[k]; });
    ['primary', 'secondary', 'bg', 'text'].forEach(k => { if (out[k] && !/^#[0-9a-f]{6}$/i.test(out[k])) delete out[k]; });
    if (out.format && !FMT[out.format]) delete out.format;
    if (out.style && !['cinema', 'slide', 'zoom', 'strips', 'poster', 'neon', 'hook', 'top5', 'blinds', 'iris', 'diag', 'rise', 'doors', 'tiles', 'pan', 'strobe', 'polaroid', 'reveal'].includes(out.style)) delete out.style;
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
  st.start = 0; // o início do trecho vale só para o arquivo aberto agora

  const Video = window.Video = { st, data: null };
  const cv = $('#cv'), ctx = cv.getContext('2d'), wrap = $('#canvasWrap');
  let im = { scenes: [] }, plan = null, t0 = performance.now(), rec = null;

  function toast(msg) {
    $$('.toast').forEach(t => t.remove());
    const t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
  }
  Video.toast = toast;
  Video.get = async function (path) {
    const r = await fetch(C.base + path);
    const j = await r.json().catch(() => ({ error: 'Resposta inválida do servidor (HTTP ' + r.status + ').' }));
    if (r.status === 401) { location.href = C.base + '/login.php'; throw new Error(j.error); }
    if (r.status === 402) throw new Error(j.error || 'Assine um plano para continuar.');
    if (!r.ok || j.error) throw new Error(j.error || 'Erro ' + r.status);
    return j;
  };

  /* ---------- Vídeo próprio de fundo, opcional (fica só no navegador, nada é enviado ao servidor) ---------- */
  const vid = document.createElement('video');
  vid.muted = true; vid.playsInline = true; vid.preload = 'auto';
  let hasFile = false, clipDone = false, audio = null, fileUrl = '';
  const clipStart = () => Math.max(0, Math.min(+st.start || 0, (vid.duration || 1) - 1));
  const clipLen = () => {
    const rem = Math.max(1, (vid.duration || 1) - clipStart());
    return st.clip === 'full' ? rem : Math.min(rem, parseInt(st.clip, 10) || 30);
  };
  Video.hasFile = () => hasFile;
  Video.fileDuration = () => hasFile ? vid.duration : 0;
  Video.setFile = function (file) {
    if (rec) return;
    if (fileUrl) URL.revokeObjectURL(fileUrl);
    fileUrl = ''; hasFile = false; vid.pause();
    if (!file) { vid.removeAttribute('src'); vid.load(); document.dispatchEvent(new Event('gg:file')); Video.refresh(); return; }
    fileUrl = URL.createObjectURL(file);
    vid.src = fileUrl;
  };
  function accept() {
    hasFile = true; st.start = 0; sync();
    document.dispatchEvent(new Event('gg:file'));
    Video.refresh();
  }
  vid.addEventListener('loadedmetadata', () => {
    if (!vid.videoWidth) { toast('Não foi possível ler este vídeo. Use um arquivo MP4.'); return; }
    if (isFinite(vid.duration)) { accept(); return; }
    // Gravações feitas pelo navegador vêm sem duração; saltar para o fim faz o navegador calculá-la
    const fix = () => {
      if (!isFinite(vid.duration)) return;
      vid.removeEventListener('durationchange', fix);
      vid.currentTime = 0;
      accept();
    };
    vid.addEventListener('durationchange', fix);
    vid.currentTime = Number.MAX_SAFE_INTEGER;
  });
  vid.addEventListener('error', () => { if (fileUrl) toast('Este navegador não reproduz esse formato. Use um arquivo MP4 (H.264).'); });

  function coverV(c, x, y, w, h) {
    const s = Math.max(w / vid.videoWidth, h / vid.videoHeight), sw = w / s, sh = h / s;
    c.drawImage(vid, (vid.videoWidth - sw) / 2, (vid.videoHeight - sh) / 2, sw, sh, x, y, w, h);
  }

  /* ---------- Animação ---------- */
  const ease = x => { x = Math.max(0, Math.min(1, x)); return 1 - Math.pow(1 - x, 3); };
  const seg = (t, start, d) => ease((t - start) / d);
  const dur = () => hasFile ? clipLen() + 4 : (parseInt(st.duration, 10) || 12);
  const safe = () => st.format === 'tiktok' ? SAFE : NOSAFE;
  const env = () => ({ ctx, W, H: FMT[st.format].h, P, st, d: Video.data || {}, im, fmt: st.format === 'tiktok' ? 'story' : st.format, brand: C.brand });

  function fx(alpha, dy, fn, scale, cx, cy, dx) {
    if (alpha <= 0.002) return;
    ctx.save();
    ctx.globalAlpha *= alpha;
    if (scale && scale !== 1) { ctx.translate(cx, cy); ctx.scale(scale, scale); ctx.translate(-cx, -cy); }
    ctx.translate(dx || 0, dy);
    fn();
    ctx.restore();
  }

  // Mede e posiciona os textos uma vez por alteração (não a cada quadro)
  function build() {
    const e = env(), H = e.H, story = e.fmt === 'story', feed = e.fmt === 'feed', sf = safe(), HB = H - sf.b, CW = W - 2 * P - sf.r;
    const neon = st.style === 'neon';
    const o = { w: CW, headlineStyle: neon ? 'outline' : 'fill' };
    if (neon) o.glow = st.primary;
    const mk = (parts, extra) => K.blocks(e, Object.assign({}, o, extra, { parts }));
    const top = sf.t + (st.showLogo ? 56 + 110 * st.logoSize / 100 : 30) + 34;

    // Terço inferior durante as cenas
    const low = [mk(['headline', 'title'], { align: 'left', titleSize: story ? 128 : feed ? 112 : 92, titleLines: 3 }), mk(['meta'], { align: 'left' })];
    if (story || feed) low.push(mk(['syn'], { align: 'left', synLines: story ? 3 : 2 }));
    let y = HB - P - 30;
    const lower = low.filter(b => b.h > 0).reverse().map(b => { y -= b.h; const r = { b, y }; y -= 26; return r; }).reverse();

    // Cartão final (no quadrado, pôster à esquerda e textos à direita)
    const side = st.format === 'square';
    let pw = 0, ph = 0, tx = P, tw = CW;
    if (side) {
      ph = H - top - P - 30; pw = ph * 2 / 3;
      if (pw > W * .38) { pw = W * .38; ph = pw * 1.5; }
      tx = P + pw + 50; tw = W - P - tx;
    }
    const fo = { align: side ? 'left' : 'center', w: tw };
    const fin = [['title', mk(['headline', 'title'], Object.assign({ titleSize: story ? 104 : side ? 80 : 88, titleLines: side ? 3 : 2 }, fo))], ['meta', mk(['meta'], fo)],
      ['cta', mk(['cta'], fo)], ['footer', mk(['footer'], fo)]].filter(k => k[1].h > 0);
    const total = fin.reduce((a, k) => a + k[1].h + 26, 0);
    y = side ? top + Math.max(0, (H - P - top - total + 26) / 2) : HB - P - total + 26;
    const end = fin.map(([kind, b]) => { const r = { b, y, x: tx, cx: side ? tx + 150 : P + CW / 2, kind, cta: kind === 'cta', late: kind === 'cta' || kind === 'footer' }; y += b.h + 26; return r; });
    let poster;
    if (side) poster = { x: P, y: top + (H - P - top - ph) / 2, w: pw, h: ph };
    else {
      ph = HB - P - total - 20 - top; pw = ph * 2 / 3;
      if (pw > CW - 60) { pw = CW - 60; ph = pw * 1.5; }
      poster = ph > 120 ? { x: P + (CW - pw) / 2, y: top + (HB - P - total - 20 - top - ph) / 2, w: pw, h: ph } : null;
    }

    // Fundo desfocado pré-renderizado
    let blur = null;
    const src = im.poster || im.scenes[0];
    if (src) {
      blur = document.createElement('canvas'); blur.width = 270; blur.height = Math.round(270 * H / W);
      K.blurCover(blur.getContext('2d'), src, 0, 0, blur.width, blur.height);
    }
    // Padrões virais: gancho em caixas, selos e título centralizado
    const cx = P + CW / 2, hs = story ? 150 : 116;
    K.font(ctx, st.fontTitle, hs, 800);
    const hook = K.wrap(ctx, String(st.hook || '').toUpperCase(), CW - hs * .7, 4);
    const ht = mk(['title'], { align: 'center', titleSize: story ? 120 : 96, titleLines: 2 });
    const htY = HB - P - 30 - ht.h, d = e.d, chips = [];
    if (st.showRating && d.rating > 0) chips.push('★ ' + Number(d.rating).toFixed(1) + ' DE NOTA');
    if (st.showGenres && d.genres && d.genres.length) chips.push(d.genres.slice(0, 2).join(' • ').toUpperCase());
    if ((st.showYear && d.year) || (st.showInfo && d.info)) chips.push([st.showYear && d.year, st.showInfo && d.info].filter(Boolean).join(' • ').toUpperCase());
    const cs = story ? 52 : 42, step = cs * 1.28 + 14;
    K.font(ctx, st.fontTitle, hs * .8, 800);
    const topLines = K.wrap(ctx, String(st.topTitle || '').toUpperCase(), CW - hs * .6, 3);
    const ts = story ? 84 : 66;
    K.font(ctx, st.fontTitle, ts, 800);
    const tops = ((d.list || []).slice(0, 5)).map(it => K.wrap(ctx, String(it.title || '').toUpperCase(), CW - ts * .7, 2));
    plan = { lower, end, poster, blur, H, HB, sf, cx, CW, logoY: 56 + sf.t,
      hook, hs, hookY: sf.t + Math.max(40, (HB - sf.t - hook.length * hs * 1.4) / 2), ht, htY, chips, cs, chipY: htY - 24 - chips.length * step, step,
      topLines, ts, tops, file: hasFile ? buildFile(mk, H, top, sf) : null };
  }

  // Vídeo inteiro no quadro: vídeo ao centro, textos acima e abaixo conforme o espaço que sobra
  function buildFile(mk, H, top, sf) {
    const ar = vid.videoWidth / vid.videoHeight, story = st.format === 'story';
    let vw = W, vh = W / ar;
    if (vh > H * .62) { vh = H * .62; vw = vh * ar; }
    const free = H - sf.b - vh - top - P - 60, c = { align: 'center' };
    const tries = [
      [['headline', 'title'], ['meta', 'cta', 'footer']], [['title'], ['meta', 'cta', 'footer']], [['title'], ['cta', 'footer']],
      [[], ['cta', 'footer']], [[], ['cta']], [[], []],
    ];
    let tb = null, bs = [], needTop = 0, need = 0;
    for (const [tp, bp] of tries) {
      tb = tp.length ? mk(tp, Object.assign({ titleSize: story ? 104 : 76, titleLines: 2 }, c)) : null;
      bs = bp.map(p => mk([p], c)).filter(b => b.h > 0);
      needTop = tb ? tb.h : 0;
      need = needTop + bs.reduce((a, b) => a + b.h + 24, 0) - (bs.length ? 24 : 0);
      if (need <= free) break;
    }
    const extra = Math.max(0, free - need);
    const vy = top + needTop + (needTop ? 30 : 0) + extra / 2;
    let y = vy + vh + 30 + extra / 4;
    const bot = bs.map(b => { const r = { b, y }; y += b.h + 24; return r; });
    const tiny = document.createElement('canvas');
    tiny.width = 40; tiny.height = Math.round(40 * H / W);
    return { vx: (W - vw) / 2, vy, vw, vh, top: tb ? { b: tb, y: top + extra / 4 } : null, bot, tiny };
  }

  function drawClip(e, t, L) {
    const H = plan.H, f = plan.file, ready = vid.readyState >= 2, out = 1 - seg(t, L - 0.2, 0.5), intro = seg(t, 0.2, 0.6);
    if (ready) {
      coverV(f.tiny.getContext('2d'), 0, 0, f.tiny.width, f.tiny.height);
      ctx.drawImage(f.tiny, 1, 1, f.tiny.width - 2, f.tiny.height - 2, 0, 0, W, H);
    }
    if (st.fit === 'fill') {
      if (ready) coverV(ctx, 0, 0, W, H);
      ctx.fillStyle = K.lin(ctx, 0, 0, 0, H, [[0, K.A(st.bg, .6)], [.2, K.A(st.bg, 0)], [.55, K.A(st.bg, .05)], [.8, K.A(st.bg, .85)], [1, st.bg]]);
      ctx.fillRect(0, 0, W, H);
      fx(out, 0, () => K.logo(e, plan.logoY));
      plan.lower.forEach((r, i) => { const a = seg(t, 0.3 + i * 0.35, 0.6); fx(a * out, (1 - a) * 60, () => r.b.draw(P, r.y)); });
      return;
    }
    ctx.fillStyle = K.A(st.bg, .68); ctx.fillRect(0, 0, W, H);
    if (st.style === 'neon') { K.glow(ctx, 0, H * .1, W * .9, K.A(st.primary, .3)); K.glow(ctx, W, H * .9, W * .9, K.A(st.secondary, .28)); }
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.7)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 20;
    ctx.fillStyle = '#000'; ctx.fillRect(f.vx, f.vy, f.vw, f.vh);
    ctx.restore();
    if (ready) ctx.drawImage(vid, f.vx, f.vy, f.vw, f.vh);
    ctx.fillStyle = K.lin(ctx, f.vx, 0, f.vx + f.vw, 0, [st.primary, st.secondary]);
    ctx.fillRect(f.vx, f.vy - 6, f.vw, 6); ctx.fillRect(f.vx, f.vy + f.vh, f.vw, 6);
    fx(out, 0, () => K.logo(e, plan.logoY));
    if (f.top) fx(intro * out, (1 - intro) * -30, () => f.top.b.draw(P, f.top.y));
    f.bot.forEach((r, i) => { const a = seg(t, 0.3 + i * 0.25, 0.6); fx(a * out, (1 - a) * 40, () => r.b.draw(P, r.y)); });
  }

  function scene(img, z, dx) {
    ctx.save();
    ctx.translate(W / 2 + dx, plan.H / 2); ctx.scale(z, z);
    K.cover(ctx, img, -W / 2, -plan.H / 2, W, plan.H, 0.5, 0.25);
    ctx.restore();
  }

  // Entrada dos textos, diferente em cada padrão
  function enter(a, out, r) {
    const m = st.style, cy = r.y + r.b.h / 2, draw = () => r.b.draw(P, r.y);
    if (m === 'slide' || m === 'pan') fx(a * out, 0, draw, 1, 0, 0, (a - 1) * 160);
    else if (m === 'zoom' || m === 'strobe') fx(a * out, 0, draw, 1.28 - 0.28 * a, W / 2, cy);
    else if (m === 'reveal') {
      // Letreiro: o texto é revelado da esquerda para a direita, com um cursor na cor da marca
      if (a * out <= 0.002) return;
      ctx.save(); ctx.globalAlpha *= out;
      ctx.save(); ctx.beginPath(); ctx.rect(P - 10, r.y - 12, (plan.CW + 20) * a, r.b.h + 24); ctx.clip(); draw(); ctx.restore();
      if (a < 0.995) { ctx.fillStyle = st.primary; ctx.fillRect(P - 10 + (plan.CW + 20) * a, r.y - 6, 8, r.b.h + 12); }
      ctx.restore();
    }
    else fx(a * out, (1 - a) * 60, draw);
  }

  // Estrobo: cortes muito rápidos com clarões coloridos — energia de teaser
  function drawStrobe(e, t, endS) {
    const H = plan.H, sc = im.scenes, n = sc.length, cut = 0.5;
    if (n) {
      const k = Math.floor(t / cut), local = t % cut, punch = 1 - seg(local, 0, 0.22);
      scene(sc[k % n], 1.08 + 0.06 * local / cut + punch * 0.22, (k % 2 ? 1 : -1) * 30 * (local / cut - .5));
      if (punch > 0.01 && t > cut / 2) { ctx.fillStyle = K.A(k % 2 ? st.primary : st.secondary, punch * 0.5); ctx.fillRect(0, 0, W, H); }
    } else if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
    shade(H);
    const out = 1 - seg(t, endS - 0.2, 0.5);
    fx(seg(t, 0.4, 0.5) * out, 0, () => K.logo(e, plan.logoY));
    plan.lower.forEach((r, i) => enter(seg(t, 0.8 + i * 0.4, 0.45), out, r));
  }

  // Fotos: as cenas caem como fotografias empilhadas sobre o fundo
  function drawPolaroid(e, t, endS) {
    const H = plan.H, sc = im.scenes, n = sc.length;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
    ctx.fillStyle = K.A(st.bg, .6); ctx.fillRect(0, 0, W, H);
    K.glow(ctx, W / 2, H * .3, W * .9, K.A(st.primary, .2));
    const top = plan.logoY + 150, bottom = plan.lower.length ? plan.lower[0].y - 40 : plan.HB - P, zone = Math.max(200, bottom - top);
    const cw = Math.min(plan.CW * .86, zone * 1.05), ph = cw * .64, fr = cw * .035, ch = ph + fr * 2 + cw * .09, cx = plan.cx, cy = top + zone / 2;
    const rot = [-0.07, 0.05, -0.03, 0.06], ox = [-18, 22, -10, 14], oy = [-14, 10, 18, -8], step = n ? Math.max(0.9, (endS - 1.2) / n) : 1;
    for (let i = 0; i < n; i++) {
      const a = seg(t, 0.5 + i * step, 0.5);
      if (a <= 0.002) continue;
      ctx.save();
      ctx.globalAlpha = a;
      ctx.translate(cx + ox[i % 4], cy + oy[i % 4] + (1 - a) * -80); ctx.rotate(rot[i % 4] * (2 - a)); ctx.scale(1.3 - 0.3 * a, 1.3 - 0.3 * a);
      ctx.shadowColor = 'rgba(0,0,0,.55)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 18;
      ctx.fillStyle = '#F4F1EA'; ctx.fillRect(-cw / 2, -ch / 2, cw, ch);
      ctx.shadowColor = 'transparent';
      ctx.beginPath(); ctx.rect(-cw / 2 + fr, -ch / 2 + fr, cw - 2 * fr, ph); ctx.clip();
      K.cover(ctx, sc[i], -cw / 2 + fr, -ch / 2 + fr, cw - 2 * fr, ph, .5, .25);
      ctx.restore();
    }
    const out = 1 - seg(t, endS - 0.2, 0.5);
    fx(seg(t, 0.3, 0.5) * out, 0, () => K.logo(e, plan.logoY));
    plan.lower.forEach((r, i) => enter(seg(t, 1.2 + i * 0.45, 0.6), out, r));
  }

  function drawScenes(e, t, endS) {
    const H = plan.H, sc = im.scenes, n = sc.length, m = st.style, neon = m === 'neon';
    if (n) {
      const span = (endS - 0.6) / n;
      for (let i = 0; i < n; i++) {
        const local = t - (0.6 + i * span);
        if (local < 0 || local > span + 1) continue;
        const p = local / (span + 1), dir = i % 2 ? 1 : -1, drift = dir * 36 * (p - 0.5);
        if (m === 'slide') {
          // A cena nova empurra a anterior, com uma faixa colorida na borda
          const off = i ? (1 - seg(local, 0, 0.65)) * W * dir : 0;
          scene(sc[i], 1.06 + 0.08 * p, off + drift);
          if (i && Math.abs(off) > 1) {
            ctx.fillStyle = K.lin(ctx, 0, 0, 0, H, [st.primary, st.secondary]);
            ctx.fillRect((dir > 0 ? off : off + W) - 10, 0, 20, H);
          }
        } else if (m === 'zoom') {
          // Corte seco com zoom de impacto e clarão
          const punch = 1 - seg(local, 0, 0.45);
          scene(sc[i], 1.04 + 0.07 * p + punch * 0.34, 0);
          if (punch > 0.01) { ctx.fillStyle = 'rgba(255,255,255,' + (punch * 0.5) + ')'; ctx.fillRect(0, 0, W, H); }
        } else if (m === 'strips') {
          // Três faixas verticais entram alternadas e se juntam na imagem inteira
          for (let k = 0; k < 3; k++) {
            const a = seg(local, k * 0.13, 0.6);
            ctx.save();
            ctx.beginPath(); ctx.rect(Math.floor(k * W / 3), 0, Math.ceil(W / 3) + 1, H); ctx.clip();
            ctx.translate(0, (1 - a) * H * (k % 2 ? 1 : -1));
            scene(sc[i], 1.06 + 0.1 * p, drift);
            ctx.restore();
          }
        } else if (m === 'blinds') {
          // Persianas: seis faixas horizontais se abrem em sequência
          for (let k = 0; k < 6; k++) {
            const a = i ? seg(local, k * 0.07, 0.5) : 1;
            ctx.save(); ctx.beginPath(); ctx.rect(0, k * H / 6, W, H / 6 * a + 1); ctx.clip();
            scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
          }
        } else if (m === 'iris') {
          // Íris: a cena nova abre num círculo a partir do centro
          ctx.save(); ctx.beginPath(); ctx.arc(W / 2, H / 2, Math.hypot(W, H) / 2 * (i ? seg(local, 0, 0.8) : 1) + 1, 0, 7); ctx.clip();
          scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
        } else if (m === 'diag') {
          // Corte diagonal com a borda na cor da marca
          const a = i ? seg(local, 0, 0.75) : 1, ex = -H * .35 + (W + H * .7) * a;
          ctx.save(); ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(ex, 0); ctx.lineTo(ex - H * .35, H); ctx.lineTo(0, H); ctx.closePath(); ctx.clip();
          scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
          if (i && a < 1) { ctx.strokeStyle = st.primary; ctx.lineWidth = 16; ctx.beginPath(); ctx.moveTo(ex, 0); ctx.lineTo(ex - H * .35, H); ctx.stroke(); }
        } else if (m === 'rise') {
          // Subida: a cena nova entra de baixo empurrando uma faixa colorida
          const off = i ? (1 - seg(local, 0, 0.7)) * H : 0;
          ctx.save(); ctx.translate(0, off); scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
          if (off > 1) { ctx.fillStyle = K.lin(ctx, 0, 0, W, 0, [st.primary, st.secondary]); ctx.fillRect(0, off - 10, W, 20); }
        } else if (m === 'doors') {
          // Portas: a cena nova aparece do centro para as laterais
          const a = i ? seg(local, 0, 0.75) : 1;
          ctx.save(); ctx.beginPath(); ctx.rect(W / 2 - W / 2 * a, 0, W * a, H); ctx.clip();
          scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
          if (i && a < 1) { ctx.fillStyle = st.primary; ctx.fillRect(W / 2 - W / 2 * a - 6, 0, 12, H); ctx.fillRect(W / 2 + W / 2 * a - 6, 0, 12, H); }
        } else if (m === 'tiles') {
          // Mosaico: doze blocos surgem um a um
          const tw = W / 3, th = H / 4;
          for (let k = 0; k < 12; k++) {
            const a = i ? seg(local, k * 0.045, 0.4) : 1, cx = (k % 3) * tw + tw / 2, cy = Math.floor(k / 3) * th + th / 2;
            if (a <= 0.002) continue;
            ctx.save(); ctx.beginPath(); ctx.rect(cx - tw / 2 * a - .5, cy - th / 2 * a - .5, tw * a + 1, th * a + 1); ctx.clip();
            scene(sc[i], 1.06 + 0.1 * p, drift); ctx.restore();
          }
        } else if (m === 'pan') {
          // Panorâmica: travelling lateral forte, com fusão
          ctx.save();
          ctx.globalAlpha = i ? seg(local, 0, 0.7) : 1;
          scene(sc[i], 1.3, dir * 150 * (p - 0.5) * 2);
          ctx.restore();
        } else {
          ctx.save();
          ctx.globalAlpha = i ? seg(local, 0, 0.7) : 1;
          scene(sc[i], 1.06 + 0.13 * p, drift);
          ctx.restore();
        }
      }
    } else if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
    ctx.fillStyle = K.lin(ctx, 0, 0, 0, H, [[0, K.A(st.bg, .6)], [.22, K.A(st.bg, .05)], [.48, K.A(st.bg, .12)], [.76, K.A(st.bg, .88)], [1, st.bg]]);
    ctx.fillRect(0, 0, W, H);
    if (neon) { K.glow(ctx, 0, H * .15, W * .9, K.A(st.primary, .28)); K.glow(ctx, W, H * .85, W * .9, K.A(st.secondary, .26)); }
    const out = 1 - seg(t, endS - 0.2, 0.5);
    fx(seg(t, 1.5, 0.6) * out, 0, () => K.logo(e, plan.logoY));
    plan.lower.forEach((r, i) => enter(seg(t, 1.7 + i * 0.45, 0.6), out, r));

    // Abertura: logo sobre preto
    const black = 1 - seg(t, 0.8, 0.8);
    if (black > 0.002) {
      ctx.fillStyle = 'rgba(0,0,0,' + black + ')'; ctx.fillRect(0, 0, W, H);
      const a = seg(t, 0.05, 0.45) * (1 - seg(t, 0.95, 0.45)), s = 0.9 + 0.1 * seg(t, 0, 1.4);
      fx(a, 0, () => {
        K.glow(ctx, W / 2, H / 2, W * .6, K.A(st.primary, .22));
        if (im.logo) K.contain(ctx, im.logo, W * .2, H / 2 - H * .09, W * .6, H * .18);
        else if (C.brand.name) {
          const f = K.fitLine(ctx, C.brand.name.toUpperCase(), st.fontTitle, 130, 40, 800, W - 2 * P);
          ctx.fillStyle = st.text; ctx.textAlign = 'center'; ctx.fillText(f.text, W / 2, H / 2 + f.size * .3); ctx.textAlign = 'left';
        }
      }, s, W / 2, H / 2);
    }
  }

  // Caixa de texto no estilo das legendas do TikTok
  function box(text, cx, y, size, fill, color) {
    K.font(ctx, st.fontTitle, size, 800);
    const w = ctx.measureText(text).width + size * .7, h = size * 1.28;
    ctx.fillStyle = fill; K.rr(ctx, cx - w / 2, y, w, h, size * .18); ctx.fill();
    ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.fillText(text, cx, y + size * .98); ctx.textAlign = 'left';
  }
  // Linhas em caixas que "pulam" uma após a outra
  function popLines(lines, y, size, t, start, out, fill, color) {
    lines.forEach((l, i) => {
      const a = seg(t, start + i * 0.3, 0.26), ly = y + i * size * 1.4;
      fx(a * out, 0, () => box(l, plan.cx, ly, size, fill, color), 1.4 - 0.4 * a, plan.cx, ly + size * .64);
    });
  }
  function shade(H, strong) {
    ctx.fillStyle = K.lin(ctx, 0, 0, 0, H, [[0, K.A(st.bg, .6)], [.22, K.A(st.bg, .08)], [.48, K.A(st.bg, strong ? .3 : .12)], [.76, K.A(st.bg, .88)], [1, st.bg]]);
    ctx.fillRect(0, 0, W, H);
  }

  // Gancho viral: frase de impacto nos 2 primeiros segundos, cortes rápidos e selos aparecendo em sequência
  function drawHook(e, t, endS) {
    const H = plan.H, sc = im.scenes, n = sc.length, cut = 0.95;
    if (n) {
      const local = t % cut, punch = 1 - seg(local, 0, 0.3);
      scene(sc[Math.floor(t / cut) % n], 1.05 + 0.08 * local / cut + punch * 0.3, 0);
      if (punch > 0.01 && t > cut / 2) { ctx.fillStyle = 'rgba(255,255,255,' + (punch * 0.45) + ')'; ctx.fillRect(0, 0, W, H); }
    } else if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
    shade(H);
    const hOut = 1 - seg(t, 2.0, 0.35);
    if (hOut > 0.002) {
      ctx.fillStyle = 'rgba(0,0,0,' + (0.4 * hOut) + ')'; ctx.fillRect(0, 0, W, H);
      popLines(plan.hook, plan.hookY, plan.hs, t, 0.1, hOut, st.primary, K.on(st.primary));
    }
    const out = 1 - seg(t, endS - 0.2, 0.5);
    fx(seg(t, 2.2, 0.5) * out, 0, () => K.logo(e, plan.logoY));
    const a = seg(t, 2.3, 0.45);
    fx(a * out, 0, () => plan.ht.draw(P, plan.htY), 1.3 - 0.3 * a, plan.cx, plan.htY + plan.ht.h / 2);
    plan.chips.forEach((c, i) => {
      const ca = seg(t, 3.0 + i * 0.65, 0.3), y = plan.chipY + i * plan.step;
      fx(ca * out, 0, () => box(c, plan.cx, y, plan.cs, i ? st.text : st.primary, i ? st.bg : K.on(st.primary)), 1.4 - 0.4 * ca, plan.cx, y + plan.cs * .64);
    });
  }

  // Top 5 em contagem regressiva: do 5º ao 1º, um pôster por vez, e chamada no final
  function drawTop5(e, t, D) {
    const H = plan.H, list = ((Video.data && Video.data.list) || []).slice(0, 5), tops = im.tops || [], n = list.length;
    const introE = 1.7, endS = D - 3.2, slot = n ? (endS - introE) / n : 1;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
    ctx.fillStyle = K.A(st.bg, .55); ctx.fillRect(0, 0, W, H);
    K.glow(ctx, W / 2, H * .4, W * .9, K.A(st.primary, .25));
    if (t < introE + 0.3 || !n) {
      const y = plan.sf.t + (plan.HB - plan.sf.t - plan.topLines.length * plan.hs * .8 * 1.4) / 2;
      popLines(plan.topLines, y, plan.hs * .8, t, 0.1, n ? 1 - seg(t, introE - 0.1, 0.35) : 1, st.primary, K.on(st.primary));
    }
    if (n && t >= introE && t < endS + 0.5) {
      const k = Math.min(n - 1, Math.floor((t - introE) / slot)), local = t - introE - k * slot, rank = n - k;
      const it = list[rank - 1], img = tops[rank - 1], punch = 1 - seg(local, 0, 0.35);
      if (img) scene(img, 1.04 + 0.08 * local / slot + punch * 0.28, 0);
      shade(H, true);
      if (punch > 0.01) { ctx.fillStyle = 'rgba(255,255,255,' + (punch * 0.45) + ')'; ctx.fillRect(0, 0, W, H); }
      fx(1, 0, () => K.logo(e, plan.logoY));
      // número gigante entrando pela esquerda
      const a = seg(local, 0.05, 0.35), ns = Math.min(430, H * .23), ny = plan.logoY + 150 + ns * .8;
      fx(a, 0, () => {
        ctx.save();
        K.font(ctx, st.fontTitle, ns, 800);
        ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 8;
        ctx.fillStyle = K.lin(ctx, 0, ny - ns * .8, 0, ny, [K.mix(st.primary, '#FFFFFF', .3), st.primary, st.secondary]);
        ctx.fillText('#' + rank, P, ny);
        ctx.restore();
      }, 1, 0, 0, (a - 1) * 320);
      // título e nota em caixas
      const lines = plan.tops[rank - 1] || [], ts = plan.ts, hasR = st.showRating && it.rating > 0;
      const y0 = plan.HB - P - 40 - lines.length * ts * 1.4 - (hasR ? plan.cs * 1.5 : 0);
      popLines(lines, y0, ts, local, 0.3, 1, st.text, st.bg);
      if (hasR) {
        const ra = seg(local, 0.3 + lines.length * 0.3, 0.26), ry = y0 + lines.length * ts * 1.4 + 4;
        fx(ra, 0, () => box('★ ' + Number(it.rating).toFixed(1), plan.cx, ry, plan.cs, st.primary, K.on(st.primary)), 1.4 - 0.4 * ra, plan.cx, ry + plan.cs * .64);
      }
    }
    const ea = seg(t, endS, 0.6);
    if (ea > 0.002) {
      fx(ea, 0, () => {
        ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
        if (plan.blur) ctx.drawImage(plan.blur, 0, 0, W, H);
        ctx.fillStyle = K.A(st.bg, .66); ctx.fillRect(0, 0, W, H);
        K.glow(ctx, W / 2, H * .4, W * .9, K.A(st.primary, .28));
        const cy = plan.sf.t + (plan.HB - plan.sf.t) * .36;
        if (im.logo && st.showLogo) K.contain(ctx, im.logo, plan.cx - plan.CW * .35, cy - H * .08, plan.CW * .7, H * .16);
        else if (C.brand.name) {
          const f = K.fitLine(ctx, C.brand.name.toUpperCase(), st.fontTitle, 130, 40, 800, plan.CW);
          ctx.fillStyle = st.text; ctx.textAlign = 'center'; ctx.fillText(f.text, plan.cx, cy + f.size * .3); ctx.textAlign = 'left';
        }
      });
      let i = 0;
      plan.end.filter(b => b.late).forEach(b => {
        const a = seg(t, endS + 0.4 + i++ * 0.3, 0.55), pulse = b.cta ? 1 + 0.04 * Math.sin((t - endS) * 5.5) * a : 1;
        fx(a, (1 - a) * 44, () => b.b.draw(b.x, b.y), pulse, b.cx, b.y + b.b.h / 2);
      });
    }
  }

  function posterFrame(e, r, border) {
    K.posterCard(e, r.x, r.y, r.w, r.h, 26);
    ctx.save();
    ctx.strokeStyle = border; ctx.lineWidth = 6;
    if (st.style === 'neon') { ctx.shadowColor = st.primary; ctx.shadowBlur = 44; }
    K.rr(ctx, r.x, r.y, r.w, r.h, 26); ctx.stroke();
    ctx.restore();
  }

  // Pôster flutuando com brilho passando; o botão entra nos segundos finais
  function drawFloat(e, t, D) {
    const H = plan.H, r = plan.poster;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (plan.blur) {
      const z = 1.08 + 0.1 * t / D;
      ctx.save(); ctx.translate(W / 2 + Math.sin(t * .4) * 20, H / 2); ctx.scale(z, z); ctx.drawImage(plan.blur, -W / 2, -H / 2, W, H); ctx.restore();
    }
    ctx.fillStyle = K.A(st.bg, .6); ctx.fillRect(0, 0, W, H);
    K.glow(ctx, W / 2 + Math.sin(t * .7) * W * .32, H * .28, W * .85, K.A(st.primary, .26));
    K.glow(ctx, W / 2 - Math.sin(t * .7) * W * .32, H * .8, W * .85, K.A(st.secondary, .2));
    fx(seg(t, 0.2, 0.6), 0, () => K.logo(e, plan.logoY));
    if (r) {
      const pa = seg(t, 0.35, 0.9), cx = r.x + r.w / 2, cy = r.y + r.h / 2;
      ctx.save();
      ctx.globalAlpha = pa;
      ctx.translate(cx, cy + Math.sin(t * 1.5) * 14 + (1 - pa) * 90);
      ctx.rotate(Math.sin(t * 1.1) * 0.022);
      ctx.scale(0.86 + 0.14 * pa, 0.86 + 0.14 * pa);
      ctx.translate(-cx, -cy);
      posterFrame(e, r, K.lin(ctx, r.x, r.y, r.x + r.w, r.y + r.h, [st.primary, st.secondary, st.primary]));
      // brilho diagonal
      const sx = r.x - r.w + ((t * 0.42) % 1.7) / 1.7 * r.w * 3;
      K.rr(ctx, r.x, r.y, r.w, r.h, 26); ctx.clip();
      ctx.fillStyle = K.lin(ctx, sx, r.y, sx + r.w * .55, r.y + r.h * .4, ['rgba(255,255,255,0)', 'rgba(255,255,255,.3)', 'rgba(255,255,255,0)']);
      ctx.fillRect(r.x, r.y, r.w, r.h);
      ctx.restore();
    }
    let early = 0, late = 0;
    plan.end.forEach(b => {
      const a = b.late ? seg(t, D - 3.6 + late++ * 0.3, 0.6) : seg(t, 1.0 + early++ * 0.35, 0.6);
      const pulse = b.cta ? 1 + 0.035 * Math.sin(t * 5.5) * a : 1;
      fx(a, (1 - a) * 44, () => b.b.draw(b.x, b.y), pulse, b.cx, b.y + b.b.h / 2);
    });
  }

  function progress(t, D) {
    ctx.globalAlpha = 1;
    ctx.fillStyle = K.lin(ctx, 0, 0, W, 0, [st.primary, st.secondary]);
    ctx.fillRect(0, plan.H - 10, W * Math.min(1, t / D), 10);
  }

  function frame(t) {
    if (!plan) return;
    const e = env(), H = plan.H, D = dur(), neon = st.style === 'neon', file = hasFile && plan.file;
    const endS = D - 4;
    ctx.globalAlpha = 1;
    ctx.fillStyle = '#000'; ctx.fillRect(0, 0, W, H);
    if (st.style === 'poster' && !file) { drawFloat(e, t, D); progress(t, D); return; }
    if (st.style === 'top5' && !file) { drawTop5(e, t, D); progress(t, D); return; }
    if (t < endS + 0.7) { if (file) drawClip(e, t, endS); else if (st.style === 'hook') drawHook(e, t, endS); else if (st.style === 'strobe') drawStrobe(e, t, endS); else if (st.style === 'polaroid') drawPolaroid(e, t, endS); else drawScenes(e, t, endS); }

    // Cartão final: pôster, título, botão pulsando
    const ea = seg(t, endS, 0.7);
    if (ea > 0.002) {
      fx(ea, 0, () => {
        ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
        if (plan.blur) {
          const z = 1.05 + 0.05 * (t - endS) / 4;
          ctx.save(); ctx.translate(W / 2, H / 2); ctx.scale(z, z); ctx.drawImage(plan.blur, -W / 2, -H / 2, W, H); ctx.restore();
        }
        ctx.fillStyle = K.A(st.bg, .62); ctx.fillRect(0, 0, W, H);
        if (neon) { K.glow(ctx, W * .1, H * .1, W * .9, K.A(st.primary, .36)); K.glow(ctx, W * .95, H * .9, W * .9, K.A(st.secondary, .3)); }
        else K.glow(ctx, W / 2, H * .4, W * .9, K.A(st.primary, .16));
      });
      fx(seg(t, endS + 0.2, 0.6), 0, () => K.logo(e, plan.logoY));
      const r = plan.poster;
      if (r) {
        const pa = seg(t, endS + 0.25, 0.7);
        fx(pa, (1 - pa) * 40, () => {
          posterFrame(e, r, neon ? st.primary : K.lin(ctx, r.x, r.y, r.x + r.w, r.y + r.h, [st.primary, st.secondary, st.primary]));
        }, 0.9 + 0.1 * pa, r.x + r.w / 2, r.y + r.h / 2);
      }
      plan.end.forEach((b, i) => {
        const a = seg(t, endS + 0.55 + i * 0.28, 0.55);
        const pulse = b.cta ? 1 + 0.035 * Math.sin((t - endS) * 5.5) * a : 1;
        fx(a, (1 - a) * 44, () => b.b.draw(b.x, b.y), pulse, b.cx, b.y + b.b.h / 2);
      });
    }

    progress(t, D);
  }

  function size() {
    const H = FMT[st.format].h, k = (st.quality === '1080' ? 1080 : 720) / W;
    const w = Math.round(W * k), h = Math.round(H * k / 2) * 2; // altura par: exigência de codificadores de vídeo
    if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; }
    ctx.setTransform(k, 0, 0, k, 0, 0);
    ctx.imageSmoothingQuality = 'high';
  }

  /* ---------- Relógio: no modo arquivo, o tempo segue o próprio vídeo (mantém imagem e som em sincronia) ---------- */
  function restart() {
    t0 = performance.now(); clipDone = false;
    if (hasFile) { try { vid.currentTime = clipStart(); } catch (e) { /* ainda carregando */ } vid.play().catch(() => {}); }
  }
  function clock(now) {
    if (hasFile && !clipDone) {
      const L = clipLen(), t = vid.currentTime - clipStart();
      if (t >= L || vid.ended) { clipDone = true; vid.pause(); t0 = now - L * 1000; return L; }
      return Math.max(0, t);
    }
    return (now - t0) / 1000;
  }
  function loop(now) {
    requestAnimationFrame(loop);
    if (!plan) return;
    const D = dur();
    let t = clock(now);
    if (rec) {
      if (t >= D + 0.15) { finish(); return; }
      $('#rec').textContent = 'Gravando… ' + Math.min(100, Math.round(t / D * 100)) + '%';
    } else if (t > D + 1.2) { restart(); t = 0; }
    size();
    frame(Math.min(t, D));
  }

  /* ---------- Carregamento de imagens / replanejamento ---------- */
  let tok = 0;
  async function prepare() {
    const my = ++tok, d = Video.data;
    wrap.classList.add('busy');
    await GG.fonts(st);
    const next = { scenes: [] };
    next.logo = await GG.img(st.showLogo ? C.brand.logo : '');
    if (d) {
      const [poster, titleLogo, ...scenes] = await Promise.all([d.poster, st.useTitleLogo ? d.titleLogo : ''].concat(d.scenes || []).map(GG.img));
      next.poster = poster; next.titleLogo = titleLogo; next.scenes = scenes.filter(Boolean);
      if (st.style === 'top5') next.tops = await Promise.all((d.list || []).slice(0, 5).map(i => GG.img(i.poster)));
    }
    if (my !== tok) return;
    im = next;
    size(); build();
    if (!rec) restart();
    wrap.classList.remove('busy');
  }
  let timer = 0;
  Video.refresh = function () {
    clearTimeout(timer);
    timer = setTimeout(prepare, 80);
    try { localStorage.setItem(LS, JSON.stringify(st)); } catch (e) { /* ignora */ }
  };

  /* ---------- Controles ---------- */
  function sync() {
    $$('[data-st]').forEach(el => { const v = st[el.dataset.st]; if (el.type === 'checkbox') el.checked = !!v; else el.value = v; });
    $$('#formats button').forEach(b => b.classList.toggle('on', b.dataset.format === st.format));
    $$('#styles button').forEach(b => b.classList.toggle('on', b.dataset.style === st.style));
    wrap.classList.toggle('tt', st.format === 'tiktok');
  }
  $$('[data-st]').forEach(el => el.addEventListener(el.tagName === 'SELECT' || el.type === 'checkbox' ? 'change' : 'input', () => {
    if (rec) { sync(); return; }
    st[el.dataset.st] = el.type === 'checkbox' ? el.checked : el.type === 'range' || el.type === 'number' ? (+el.value || 0) : el.value;
    Video.refresh();
  }));
  $$('#formats button').forEach(b => b.addEventListener('click', () => { if (rec) return; st.format = b.dataset.format; sync(); Video.refresh(); }));
  $$('#styles button').forEach(b => b.addEventListener('click', () => { if (rec) return; st.style = b.dataset.style; sync(); Video.refresh(); }));
  [['#F5C518', '#FF8A00', '#0B0B12', '#FFFFFF'], ['#E50914', '#FF5F6D', '#0A0A0A', '#FFFFFF'], ['#00E5FF', '#7C4DFF', '#070B1A', '#FFFFFF'],
    ['#22C55E', '#A3E635', '#06130C', '#FFFFFF'], ['#FF2D95', '#FFB800', '#12061F', '#FFFFFF'], ['#3B82F6', '#22D3EE', '#0A1020', '#FFFFFF'],
    ['#FF6B00', '#FFD000', '#140A02', '#FFFFFF'], ['#A855F7', '#EC4899', '#0D0716', '#FFFFFF']].forEach(p => {
    const b = document.createElement('button');
    b.type = 'button'; b.title = 'Aplicar paleta';
    b.style.background = 'linear-gradient(135deg,' + p[2] + ' 0 45%,' + p[0] + ' 45% 75%,' + p[1] + ' 75%)';
    b.addEventListener('click', () => { if (rec) return; [st.primary, st.secondary, st.bg, st.text] = p; sync(); Video.refresh(); });
    $('#palettes').appendChild(b);
  });
  $('#brandColors').addEventListener('click', () => { if (rec) return; Object.assign(st, brandStyle()); sync(); Video.refresh(); });
  $('#replay').addEventListener('click', () => { if (!rec) restart(); });

  /* ---------- Gravação ---------- */
  const MIMES = [['video/mp4;codecs=avc1.42E01E', 'mp4'], ['video/mp4', 'mp4'], ['video/webm;codecs=vp9', 'webm'], ['video/webm;codecs=vp8', 'webm'], ['video/webm', 'webm']];
  const MIMES_AV = [['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'mp4'], ['video/mp4', 'mp4'], ['video/webm;codecs=vp9,opus', 'webm'], ['video/webm;codecs=vp8,opus', 'webm'], ['video/webm', 'webm']];
  const slug = s => String(s || 'video').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) || 'video';

  // O som do vídeo vai direto para a gravação (não toca nos alto-falantes)
  function audioTracks() {
    if (!hasFile) return [];
    try {
      if (!audio) {
        const AC = window.AudioContext || window.webkitAudioContext, ac = new AC(), dest = ac.createMediaStreamDestination();
        ac.createMediaElementSource(vid).connect(dest);
        audio = { ac, dest };
      }
      audio.ac.resume();
      vid.muted = false; vid.volume = 1;
      return audio.dest.stream.getAudioTracks();
    } catch (e) { return []; }
  }

  function finish() {
    const r = rec;
    rec = null;
    try { r.mr.stop(); } catch (e) { /* já parado */ }
  }

  $('#rec').addEventListener('click', async () => {
    const btn = $('#rec');
    if (rec) return;
    if (!Video.data) { toast('Escolha um título primeiro.'); return; }
    // Autorização antes de gravar: conta o uso e barra teste encerrado ou plano vencido
    const goPlan = msg => { toast(msg); setTimeout(() => { location.href = C.base + '/assinar.php'; }, 2400); };
    if (C.locked) { goPlan('Assine um plano para gerar seus vídeos.'); return; }
    try {
      const r0 = await fetch(C.base + '/api/user.php', { method: 'POST', headers: { 'X-CSRF': C.csrf }, body: new URLSearchParams({ a: 'log', kind: 'video', n: 1, layout: st.style, format: st.format, title: Video.data.title || '' }) });
      if (r0.status === 402) { const j0 = await r0.json().catch(() => ({})); goPlan(j0.error || 'Assine um plano para continuar.'); return; }
    } catch (e) { /* sem conexão: segue e deixa a gravação acontecer */ }
    if (!window.MediaRecorder || !cv.captureStream) { toast('Este navegador não grava vídeo. Use o Chrome, Edge ou Firefox atualizado.'); return; }
    btn.disabled = true;
    await prepare();
    const sound = audioTracks();
    const pick = (sound.length ? MIMES_AV : MIMES).find(m => MediaRecorder.isTypeSupported(m[0]));
    if (!pick) { btn.disabled = false; vid.muted = true; toast('Este navegador não grava vídeo. Use o Chrome, Edge ou Firefox atualizado.'); return; }
    const canvasStream = cv.captureStream(30), chunks = [];
    const stream = new MediaStream(canvasStream.getVideoTracks().concat(sound));
    let mr;
    try { mr = new MediaRecorder(stream, { mimeType: pick[0], videoBitsPerSecond: st.quality === '1080' ? 12e6 : 7e6, audioBitsPerSecond: 160000 }); }
    catch (e) { btn.disabled = false; vid.muted = true; toast('Não foi possível iniciar a gravação neste navegador.'); return; }
    mr.ondataavailable = ev => { if (ev.data && ev.data.size) chunks.push(ev.data); };
    mr.onstop = () => {
      canvasStream.getTracks().forEach(t => t.stop());
      vid.muted = true;
      btn.disabled = false; btn.textContent = 'Gerar vídeo';
      restart();
      const blob = new Blob(chunks, { type: pick[0].split(';')[0] });
      if (blob.size < 2000) { toast('A gravação falhou. Mantenha esta aba aberta e visível e tente de novo.'); return; }
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = slug(Video.data.title) + '-motion-' + st.format + '.' + pick[1];
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 10000);
      toast(pick[1] === 'mp4' ? 'Vídeo MP4 pronto para postar.' : 'Vídeo WEBM gerado. Para MP4 direto, use o Chrome ou Edge atualizado.');
    };
    rec = { mr };
    restart();
    mr.start(250);
  });
  document.addEventListener('visibilitychange', () => {
    if (rec && document.hidden) toast('Mantenha esta aba visível até a gravação terminar.');
  });

  Video.frameAt = t => { size(); frame(t); }; // usado em testes
  Video.ready = prepare;

  sync();
  prepare();
  requestAnimationFrame(loop);
})();
