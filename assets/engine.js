/* Gerador Gold — motor de renderização dos banners (Canvas 2D, base 1080px de largura) */
(function () {
  'use strict';
  const GG = window.GG = {};
  const W = 1080;
  GG.FORMATS = { story: { w: 1080, h: 1920 }, feed: { w: 1080, h: 1350 }, square: { w: 1080, h: 1080 } };
  GG.BASE = (window.GGCFG && window.GGCFG.base) || '';
  GG.tmdb = (path, size) => path ? 'https://image.tmdb.org/t/p/' + size + path : '';

  /* ---------- Fontes ---------- */
  const ONLY400 = new Set(['Bebas Neue', 'Anton', 'Archivo Black']);
  const MAX700 = new Set(['Oswald', 'Teko']);
  const TIGHT = new Set(['Bebas Neue', 'Teko']);
  const wt = (fam, w) => ONLY400.has(fam) ? 400 : MAX700.has(fam) ? Math.min(w, 700) : w;
  const font = (ctx, fam, size, w) => { ctx.font = wt(fam, w) + ' ' + size + 'px "' + fam + '", Arial, sans-serif'; };

  GG.fonts = function (st) {
    if (!document.fonts) return Promise.resolve();
    const jobs = [];
    [st.fontTitle, st.fontBody].forEach(f => [400, 500, 600, 700, 800].forEach(w => jobs.push(document.fonts.load(wt(f, w) + ' 40px "' + f + '"').catch(() => {}))));
    return Promise.all(jobs);
  };

  /* ---------- Imagens ---------- */
  const cache = new Map();
  // Endereço vai em base64url: URLs completas na query string são bloqueadas por firewalls de hospedagem (403)
  const b64 = u => btoa(unescape(encodeURIComponent(u))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const proxied = u => GG.BASE + (window.GG_url ? window.GG_url('/api/img.php?i=' + b64(u)) : '/api/img.php?i=' + b64(u));
  const load = (src, cors) => new Promise(res => {
    const im = new Image();
    if (cors) im.crossOrigin = 'anonymous';
    im.onload = () => res(im);
    im.onerror = () => res(null);
    im.src = src;
  });
  // No máximo 4 downloads simultâneos pelo proxy, para não parecer ataque ao servidor
  let running = 0;
  const queue = [];
  const viaProxy = u => new Promise(res => {
    const run = () => { running++; load(proxied(u)).then(im => { running--; res(im); if (queue.length) queue.shift()(); }); };
    running < 4 ? run() : queue.push(run);
  });
  GG.img = function (url) {
    if (!url) return Promise.resolve(null);
    if (cache.has(url)) return cache.get(url);
    const local = url.startsWith('/') || url.startsWith(location.origin) || url.startsWith('data:') || url.startsWith('blob:');
    // O TMDB libera CORS: carrega direto do CDN deles; se falhar, tenta pelo proxy
    const p = local ? load(url) : url.startsWith('https://image.tmdb.org/') ? load(url, true).then(im => im || viaProxy(url)) : viaProxy(url);
    cache.set(url, p);
    return p;
  };

  /* ---------- Cores ---------- */
  const rgb = hex => { const n = parseInt(String(hex).slice(1), 16) || 0; return [n >> 16 & 255, n >> 8 & 255, n & 255]; };
  const A = (hex, a) => { const c = rgb(hex); return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; };
  const mix = (a, b, t) => { const x = rgb(a), y = rgb(b); return '#' + x.map((v, i) => Math.round(v + (y[i] - v) * t).toString(16).padStart(2, '0')).join(''); };
  const lum = hex => { const c = rgb(hex); return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255; };
  const on = hex => lum(hex) > 0.6 ? '#111111' : '#FFFFFF';

  /* ---------- Primitivas de desenho ---------- */
  function lin(ctx, x0, y0, x1, y1, stops) {
    const g = ctx.createLinearGradient(x0, y0, x1, y1);
    stops.forEach((s, i) => Array.isArray(s) ? g.addColorStop(s[0], s[1]) : g.addColorStop(i / (stops.length - 1), s));
    return g;
  }
  function glow(ctx, x, y, r, color) {
    const g = ctx.createRadialGradient(x, y, 0, x, y, r);
    g.addColorStop(0, color); g.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = g; ctx.fillRect(x - r, y - r, r * 2, r * 2);
  }
  function rr(ctx, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function poly(ctx, pts) {
    ctx.beginPath();
    pts.forEach((p, i) => i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]));
    ctx.closePath();
  }
  function cover(ctx, img, x, y, w, h, ax, ay) {
    const s = Math.max(w / img.width, h / img.height), sw = w / s, sh = h / s;
    ctx.drawImage(img, (img.width - sw) * (ax == null ? .5 : ax), (img.height - sh) * (ay == null ? .5 : ay), sw, sh, x, y, w, h);
  }
  function contain(ctx, img, x, y, w, h, ax, ay) {
    const s = Math.min(w / img.width, h / img.height), dw = img.width * s, dh = img.height * s;
    const r = { x: x + (w - dw) * (ax == null ? .5 : ax), y: y + (h - dh) * (ay == null ? .5 : ay), w: dw, h: dh };
    ctx.drawImage(img, r.x, r.y, dw, dh);
    return r;
  }
  // Desfoque por redução/ampliação (funciona em todos os navegadores, inclusive Safari)
  function blurCover(ctx, img, x, y, w, h) {
    const c = document.createElement('canvas');
    c.width = 36; c.height = Math.max(8, Math.round(36 * h / w));
    cover(c.getContext('2d'), img, 0, 0, c.width, c.height);
    ctx.save(); ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(c, 1, 1, c.width - 2, c.height - 2, x, y, w, h);
    ctx.restore();
  }

  /* ---------- Texto ---------- */
  function ell(ctx, t, maxW) {
    if (ctx.measureText(t).width <= maxW) return t;
    while (t.length > 1 && ctx.measureText(t + '…').width > maxW) t = t.slice(0, -1);
    return t.replace(/[\s,.;:–-]+$/, '') + '…';
  }
  function fitLine(ctx, t, fam, size, min, w, maxW) {
    font(ctx, fam, size, w);
    while (size > min && ctx.measureText(t).width > maxW) { size -= 2; font(ctx, fam, size, w); }
    return { size, text: ell(ctx, t, maxW) };
  }
  function wrap(ctx, text, maxW, maxLines) {
    const words = String(text).split(/\s+/).filter(Boolean), lines = [];
    let cur = '';
    for (const wd of words) {
      const t = cur ? cur + ' ' + wd : wd;
      if (cur && ctx.measureText(t).width > maxW) { lines.push(cur); cur = wd; } else cur = t;
    }
    if (cur) lines.push(cur);
    if (lines.length > maxLines) {
      const keep = lines.slice(0, maxLines);
      keep[maxLines - 1] = ell(ctx, keep[maxLines - 1] + ' ' + lines[maxLines], maxW - 1).replace(/…?$/, '…');
      if (ctx.measureText(keep[maxLines - 1]).width > maxW) keep[maxLines - 1] = ell(ctx, keep[maxLines - 1].slice(0, -1), maxW);
      return keep;
    }
    return lines;
  }
  const spacedW = (ctx, t, sp) => ctx.measureText(t).width + sp * Math.max(0, t.length - 1);
  function spaced(ctx, t, x, y, sp) {
    ctx.textAlign = 'left';
    for (const ch of t) { ctx.fillText(ch, x, y); x += ctx.measureText(ch).width + sp; }
  }
  const alignX = (al, x, w, bw) => al === 'center' ? x + (w - bw) / 2 : al === 'right' ? x + w - bw : x;
  function pill(ctx, st, t, x, y, h, size, style) {
    font(ctx, st.fontBody, size, 800);
    const w = spacedW(ctx, t, 3) + h;
    rr(ctx, x, y, w, h, h / 2);
    if (style === 'outline') {
      ctx.fillStyle = A(st.primary, .12); ctx.fill();
      ctx.lineWidth = 2.5; ctx.strokeStyle = st.primary; ctx.stroke();
      ctx.fillStyle = st.primary;
    } else {
      ctx.fillStyle = lin(ctx, x, 0, x + w, 0, [st.primary, st.secondary]); ctx.fill();
      ctx.fillStyle = on(st.primary);
    }
    spaced(ctx, t, x + h / 2, y + h / 2 + size * .36, 3);
    return w;
  }
  function pillW(ctx, st, t, h, size) { font(ctx, st.fontBody, size, 800); return spacedW(ctx, t, 3) + h; }

  /* ---------- Logo da marca ---------- */
  function logo(e, y, maxH) {
    const { ctx, P, st, im, brand } = e;
    if (!st.showLogo) return y;
    const k = (st.logoSize || 100) / 100, h = (maxH || 110) * k, w = Math.min(480 * k, W - 2 * P);
    const ax = st.logoAlign === 'left' ? 0 : st.logoAlign === 'right' ? 1 : .5;
    const x = P + (W - 2 * P - w) * ax;
    if (im.logo) {
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 4;
      const r = contain(ctx, im.logo, x, y, w, h, ax, 0);
      ctx.restore();
      return r.y + r.h;
    }
    if (brand && brand.name) {
      const f = fitLine(ctx, brand.name.toUpperCase(), st.fontTitle, h * .62, 24, 800, W - 2 * P);
      ctx.fillStyle = st.text; ctx.textAlign = ['left', 'center', 'right'][ax * 2];
      ctx.fillText(f.text, P + (W - 2 * P) * ax, y + f.size * .85);
      ctx.textAlign = 'left';
      return y + f.size;
    }
    return y;
  }

  /* ---------- Blocos de texto empilháveis (filmes/séries + CTA/rodapé) ---------- */
  function blocks(e, o) {
    const { ctx, st, d, im } = e;
    const w = o.w, al = o.align || 'left', col = st.text;
    const parts = o.parts || ['headline', 'title', 'meta', 'syn', 'cta', 'footer'];
    const has = p => parts.includes(p);
    const ax = x => al === 'center' ? x + w / 2 : al === 'right' ? x + w : x;
    const list = [];
    const add = (h, draw, gap) => list.push({ h, draw, gap: gap == null ? 26 : gap });
    const shadow = on => { ctx.shadowColor = on && !o.flat ? 'rgba(0,0,0,.55)' : 'transparent'; ctx.shadowBlur = on ? 18 : 0; ctx.shadowOffsetY = on ? 3 : 0; };

    if (has('headline') && st.headline) {
      const t = st.headline.toUpperCase(), h = 52, bw = pillW(ctx, st, t, h, 25);
      add(h, (x, y) => pill(ctx, st, t, alignX(al, x, w, bw), y, h, 25, o.headlineStyle), 22);
    }

    if (has('title')) {
      const tl = st.useTitleLogo && im.titleLogo;
      if (tl) {
        const k = Math.min(w / tl.width, (o.logoH || 230) / tl.height), dw = tl.width * k, dh = tl.height * k;
        add(dh, (x, y) => { ctx.save(); shadow(true); ctx.drawImage(tl, alignX(al, x, w, dw), y, dw, dh); ctx.restore(); }, 30);
      } else {
        const t = st.upper ? String(d.title || '').toUpperCase() : String(d.title || '');
        const base = o.titleSize || 120, maxL = o.titleLines || 3, fam = st.fontTitle;
        let size = base, lines;
        for (;;) {
          font(ctx, fam, size, 800);
          const all = wrap(ctx, t, w, 99);
          if ((all.length <= maxL && all.every(l => ctx.measureText(l).width <= w)) || size <= base * .5) { lines = wrap(ctx, t, w, maxL); break; }
          size -= 6;
        }
        const lh = size * (TIGHT.has(fam) ? .96 : 1.12);
        add(lines.length * lh, (x, y) => {
          ctx.save();
          font(ctx, fam, size, 800); ctx.textAlign = al;
          ctx.fillStyle = o.titleFill ? o.titleFill(x, y, w, lines.length * lh) : col;
          if (o.glow) { ctx.shadowColor = o.glow; ctx.shadowBlur = 34; } else shadow(true);
          lines.forEach((l, i) => ctx.fillText(l, ax(x), y + size * .84 + i * lh));
          ctx.restore();
        }, TIGHT.has(fam) ? 14 : 22);
      }
    }

    if (has('meta')) {
      const size = o.metaSize || 31, sep = 40, segs = [];
      if (st.showRating && d.rating > 0) segs.push({ t: '★ ' + Number(d.rating).toFixed(1), c: st.primary });
      if (d.typeLabel) segs.push({ t: d.typeLabel });
      if (st.showYear && d.year) segs.push({ t: String(d.year) });
      if (st.showInfo && d.info) segs.push({ t: d.info });
      if (st.showGenres && d.genres && d.genres.length) segs.push({ t: d.genres.slice(0, 2).join(', ') });
      font(ctx, st.fontBody, size, 700);
      segs.forEach(s => { s.w = ctx.measureText(s.t).width; });
      const total = () => segs.reduce((a, s) => a + s.w, 0) + sep * Math.max(0, segs.length - 1);
      while (segs.length > 1 && total() > w) segs.pop();
      if (segs.length) {
        const tw = total();
        add(size * 1.15, (x, y) => {
          let cx = alignX(al, x, w, tw);
          ctx.save(); font(ctx, st.fontBody, size, 700); ctx.textAlign = 'left'; shadow(true);
          segs.forEach((s, i) => {
            ctx.fillStyle = s.c || A(col, .92);
            ctx.fillText(s.t, cx, y + size * .9);
            cx += s.w;
            if (i < segs.length - 1) { ctx.fillStyle = A(col, .45); ctx.beginPath(); ctx.arc(cx + sep / 2, y + size * .58, 4, 0, 7); ctx.fill(); cx += sep; }
          });
          ctx.restore();
        });
      }
    }

    if (has('syn') && st.showSynopsis && d.overview && o.synLines > 0) {
      const size = o.synSize || 30, lh = size * 1.45;
      font(ctx, st.fontBody, size, 500);
      const lines = wrap(ctx, d.overview, w, o.synLines);
      add(lines.length * lh - size * .35, (x, y) => {
        ctx.save(); font(ctx, st.fontBody, size, 500); ctx.textAlign = al; ctx.fillStyle = A(col, .86); shadow(true);
        lines.forEach((l, i) => ctx.fillText(l, ax(x), y + size * .92 + i * lh));
        ctx.restore();
      }, 34);
    }

    if (has('cta') && st.cta) {
      const f = fitLine(ctx, st.cta.toUpperCase(), st.fontBody, 32, 20, 800, w - 90);
      const bw = Math.min(w, ctx.measureText(f.text).width + 100), bh = 84;
      add(bh, (x, y) => {
        const bx = alignX(al, x, w, bw);
        ctx.save();
        ctx.shadowColor = A(st.primary, .5); ctx.shadowBlur = 34; ctx.shadowOffsetY = 10;
        ctx.fillStyle = lin(ctx, bx, 0, bx + bw, 0, [st.primary, st.secondary]);
        rr(ctx, bx, y, bw, bh, bh / 2); ctx.fill();
        ctx.restore();
        font(ctx, st.fontBody, f.size, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
        ctx.fillText(f.text, bx + bw / 2, y + bh / 2 + f.size * .36);
        ctx.textAlign = 'left';
      }, 24);
    }

    if (has('footer') && st.footer) {
      const f = fitLine(ctx, st.footer, st.fontBody, 27, 18, 600, w);
      add(34, (x, y) => {
        ctx.save(); font(ctx, st.fontBody, f.size, 600); ctx.textAlign = al; ctx.fillStyle = A(col, .82); shadow(true);
        ctx.fillText(f.text, ax(x), y + 26);
        ctx.restore();
      });
    }

    const h = list.reduce((a, b, i) => a + b.h + (i < list.length - 1 ? b.gap : 0), 0);
    return { h, draw(x, y) { list.forEach(b => { b.draw(x, y); y += b.h + b.gap; }); } };
  }

  // Reduz sinopse e depois o título até caber na altura disponível
  function fitBlocks(e, o, maxH) {
    const oo = Object.assign({ synLines: 0 }, o);
    let b = blocks(e, oo);
    while (b.h > maxH && oo.synLines > 0) { oo.synLines--; b = blocks(e, oo); }
    for (let i = 0; b.h > maxH && i < 4; i++) { oo.titleSize = (oo.titleSize || 120) * .86; oo.logoH = (oo.logoH || 230) * .8; b = blocks(e, oo); }
    return b;
  }

  const art = (e, prefer) => {
    const { st, im } = e, p = st.art === 'auto' || !st.art ? prefer : st.art;
    return p === 'poster' ? (im.poster || im.backdrop) : (im.backdrop || im.poster);
  };

  function posterCard(e, x, y, w, h, r) {
    const { ctx, st, im, d } = e;
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 28;
    ctx.fillStyle = mix(st.bg, st.text, .12); rr(ctx, x, y, w, h, r); ctx.fill();
    ctx.restore();
    ctx.save(); rr(ctx, x, y, w, h, r); ctx.clip();
    if (im.poster) cover(ctx, im.poster, x, y, w, h);
    else {
      font(ctx, st.fontTitle, w * .5, 800); ctx.fillStyle = A(st.text, .25); ctx.textAlign = 'center';
      ctx.fillText(String(d.title || '?').charAt(0).toUpperCase(), x + w / 2, y + h / 2 + w * .17);
    }
    ctx.restore(); ctx.textAlign = 'left';
  }

  function goldFrame(e) {
    const { ctx, H, st } = e;
    ctx.save();
    ctx.strokeStyle = lin(ctx, 0, 0, W, H, [st.primary, st.secondary, st.primary]);
    ctx.lineWidth = 4; rr(ctx, 26, 26, W - 52, H - 52, 34); ctx.stroke();
    ctx.globalAlpha = .45; ctx.lineWidth = 1.5; rr(ctx, 42, 42, W - 84, H - 84, 24); ctx.stroke();
    ctx.restore();
  }

  /* =====================  FILMES E SÉRIES  ===================== */
  const MOVIE = {};

  MOVIE.cinematic = function (e) {
    const { ctx, H, P, st, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const a = art(e, story ? 'poster' : 'backdrop');
    if (a) cover(ctx, a, 0, 0, W, H, .5, .15);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .6)], [.2, A(st.bg, ov * .15)], [.4, A(st.bg, .08 + ov * .35)], [.7, A(st.bg, .78 + ov * .2)], [1, st.bg]]);
    ctx.fillRect(0, 0, W, H);
    logo(e, 56);
    const b = fitBlocks(e, { w: W - 2 * P, align: 'left', titleSize: story ? 136 : fmt === 'feed' ? 120 : 104, synLines: story ? 5 : fmt === 'feed' ? 3 : 2 }, H * .62);
    b.draw(P, H - P - b.h - 12);
    ctx.fillStyle = lin(ctx, 0, 0, W, 0, [st.primary, st.secondary]);
    ctx.fillRect(0, H - 12, W, 12);
  };

  function posterStack(e, skin) {
    if (skin === 'clean') e = Object.assign({}, e, { st: Object.assign({}, e.st, { bg: mix(e.st.text, e.st.bg, .04), text: e.st.bg }) });
    const { ctx, H, st, im, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const P = skin === 'gold' ? 84 : e.P;
    e = Object.assign({}, e, { P });
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);

    if (skin === 'poster') {
      const a = im.poster || im.backdrop;
      if (a) blurCover(ctx, a, 0, 0, W, H);
      ctx.fillStyle = A(st.bg, .3 + ov * .5); ctx.fillRect(0, 0, W, H);
      const g = ctx.createRadialGradient(W / 2, H / 2, W * .3, W / 2, H / 2, H * .75);
      g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, A(st.bg, .7));
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    } else if (skin === 'neon') {
      glow(ctx, W * .08, H * .1, W * .95, A(st.primary, .4));
      glow(ctx, W * .95, H * .92, W * .95, A(st.secondary, .34));
      ctx.strokeStyle = A(st.text, .045); ctx.lineWidth = 2; ctx.beginPath();
      for (let x = 0; x <= W; x += 72) { ctx.moveTo(x, 0); ctx.lineTo(x, H); }
      for (let y = 0; y <= H; y += 72) { ctx.moveTo(0, y); ctx.lineTo(W, y); }
      ctx.stroke();
    } else if (skin === 'clean') {
      ctx.fillStyle = A(st.primary, .2); ctx.beginPath(); ctx.arc(W * .95, H * .04, W * .56, 0, 7); ctx.fill();
      ctx.fillStyle = A(st.secondary, .16); ctx.beginPath(); ctx.arc(W * .02, H, W * .42, 0, 7); ctx.fill();
    } else if (skin === 'spot') {
      glow(ctx, W / 2, H * .36, W * .95, A(st.primary, .34));
      ctx.fillStyle = lin(ctx, 0, 0, 0, H * .8, [A(st.text, .13), A(st.text, 0)]);
      poly(ctx, [[W * .32, 0], [W * .68, 0], [W * 1.08, H * .8], [-W * .08, H * .8]]); ctx.fill();
      glow(ctx, W / 2, H * .95, W * .7, A(st.secondary, .22));
    } else if (skin === 'gold') {
      const a = art(e, 'backdrop'), ah = H * .56;
      if (a) {
        cover(ctx, a, 0, 0, W, ah, .5, .2);
        ctx.fillStyle = lin(ctx, 0, 0, 0, ah, [[0, A(st.bg, .55 + ov * .3)], [.55, A(st.bg, .65 + ov * .3)], [1, st.bg]]);
        ctx.fillRect(0, 0, W, ah + 2);
      }
      glow(ctx, W / 2, H * .45, W * .85, A(st.primary, .16));
      goldFrame(e);
    }

    const y0 = (st.showLogo ? logo(e, skin === 'gold' ? 86 : 56) : P - 20) + 34;
    const o = { flat: skin === 'clean', headlineStyle: skin === 'neon' || skin === 'gold' ? 'outline' : 'fill' };
    if (skin === 'neon') o.glow = st.primary;
    if (skin === 'gold') o.titleFill = (x, y, w, h) => lin(ctx, 0, y, 0, y + h, [mix(st.primary, '#FFFFFF', .35), st.primary, st.secondary]);

    let px, py, pw, ph, b, bx, by;
    if (fmt === 'square') {
      ph = H - y0 - P - 10; pw = ph * 2 / 3;
      if (pw > W * .4) { pw = W * .4; ph = pw * 1.5; }
      px = P; py = y0 + (H - y0 - P - ph) / 2;
      const tw = W - 2 * P - pw - 50;
      b = fitBlocks(e, Object.assign(o, { w: tw, align: 'left', titleSize: 92, synLines: 3 }), H - y0 - P);
      bx = px + pw + 50; by = y0 + (H - y0 - P - b.h) / 2;
    } else {
      b = fitBlocks(e, Object.assign(o, { w: W - 2 * P, align: 'center', titleSize: story ? 112 : 96, synLines: story ? 3 : 0 }), H * .42);
      const bot = H - P - b.h - 46;
      ph = bot - y0; pw = ph * 2 / 3;
      const maxW = W - 2 * P - 60;
      if (pw > maxW) { pw = maxW; ph = pw * 1.5; }
      px = (W - pw) / 2; py = y0 + (bot - y0 - ph) / 2;
      bx = P; by = H - P - b.h;
    }

    if (skin === 'neon') {
      ctx.save(); ctx.strokeStyle = st.secondary; ctx.lineWidth = 3; ctx.globalAlpha = .8;
      rr(ctx, px + 20, py + 20, pw, ph, 26); ctx.stroke(); ctx.restore();
    }
    ctx.save();
    if (skin === 'spot') {
      // leve inclinação em torno do centro do pôster (um pouco menor para não encostar nos textos)
      ctx.translate(px + pw / 2, py + ph / 2); ctx.rotate(-0.045); ctx.scale(.94, .94); ctx.translate(-px - pw / 2, -py - ph / 2);
    }
    posterCard(e, px, py, pw, ph, 26);
    if (skin === 'spot') {
      ctx.strokeStyle = A(st.text, .92); ctx.lineWidth = 8;
      rr(ctx, px, py, pw, ph, 26); ctx.stroke();
    } else if (skin === 'neon') {
      ctx.strokeStyle = st.primary; ctx.lineWidth = 6; ctx.shadowColor = st.primary; ctx.shadowBlur = 46;
      rr(ctx, px, py, pw, ph, 26); ctx.stroke(); ctx.stroke();
    } else if (skin === 'gold') {
      ctx.strokeStyle = lin(ctx, px, py, px + pw, py + ph, [st.primary, st.secondary, st.primary]); ctx.lineWidth = 6;
      rr(ctx, px, py, pw, ph, 26); ctx.stroke();
    } else if (skin === 'poster') {
      ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.lineWidth = 2;
      rr(ctx, px, py, pw, ph, 26); ctx.stroke();
    }
    ctx.restore();
    b.draw(bx, by);
  }
  MOVIE.poster = e => posterStack(e, 'poster');
  MOVIE.neon = e => posterStack(e, 'neon');
  MOVIE.clean = e => posterStack(e, 'clean');
  MOVIE.gold = e => posterStack(e, 'gold');
  MOVIE.spot = e => posterStack(e, 'spot');

  // Na Smart TV: a cena dentro de uma TV desenhada — mostra o produto em uso
  MOVIE.tv = function (e) {
    const { ctx, H, P, st, im, fmt } = e, story = fmt === 'story';
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.primary, .1), st.bg, mix(st.bg, '#000000', .4)]); ctx.fillRect(0, 0, W, H);
    const y0 = (st.showLogo ? logo(e, 56) : P - 20) + 40;
    const b = fitBlocks(e, { w: W - 2 * P, align: 'center', titleSize: story ? 104 : 86, synLines: story ? 3 : 0 }, H * .44);
    const bot = H - P - b.h - 70;
    let tw = W - 2 * P, th = tw * 9 / 16;
    if (th > bot - y0 - 60) { th = Math.max(120, bot - y0 - 60); tw = th * 16 / 9; }
    const tx = (W - tw) / 2, ty = y0 + Math.max(0, (bot - y0 - th - 60) / 2);
    glow(ctx, W / 2, ty + th / 2, tw * .8, A(st.primary, .28));
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.7)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 26;
    ctx.fillStyle = '#08080B'; rr(ctx, tx, ty, tw, th, 16); ctx.fill(); ctx.restore();
    const a = im.backdrop || im.poster;
    ctx.save(); rr(ctx, tx + 12, ty + 12, tw - 24, th - 24, 6); ctx.clip();
    ctx.fillStyle = '#111'; ctx.fillRect(tx, ty, tw, th);
    if (a) cover(ctx, a, tx + 12, ty + 12, tw - 24, th - 24, .5, .25);
    ctx.fillStyle = lin(ctx, tx, ty, tx + tw * .5, ty + th, [A('#FFFFFF', .1), A('#FFFFFF', 0)]); ctx.fillRect(tx, ty, tw, th);
    ctx.restore();
    ctx.strokeStyle = A(st.text, .28); ctx.lineWidth = 2; rr(ctx, tx, ty, tw, th, 16); ctx.stroke();
    ctx.fillStyle = '#16161C'; ctx.fillRect(W / 2 - tw * .035, ty + th, tw * .07, 30);
    rr(ctx, W / 2 - tw * .17, ty + th + 30, tw * .34, 12, 6); ctx.fill();
    b.draw(P, H - P - b.h);
  };

  // Tela do app: cartão da cena, pôster sobreposto e gêneros em etiquetas, como a página do título num streaming
  MOVIE.appui = function (e) {
    const { ctx, H, P, st, d, im, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.text, .05), st.bg]); ctx.fillRect(0, 0, W, H);
    const y0 = (st.showLogo ? logo(e, 56) : P - 20) + 36, bh = Math.round(w * (story ? .62 : fmt === 'feed' ? .46 : .36));
    const a = im.backdrop || im.poster;
    ctx.save(); rr(ctx, P, y0, w, bh, 30); ctx.clip();
    ctx.fillStyle = mix(st.bg, st.text, .1); ctx.fillRect(P, y0, w, bh);
    if (a) cover(ctx, a, P, y0, w, bh, .5, .2);
    ctx.fillStyle = lin(ctx, 0, y0 + bh * .4, 0, y0 + bh, [A(st.bg, 0), A(st.bg, .85)]); ctx.fillRect(P, y0, w, bh);
    ctx.restore();
    // botão de play
    const pr = story ? 54 : 44, px0 = P + w - pr - 34, py0 = y0 + bh - pr - 30;
    ctx.fillStyle = lin(ctx, px0 - pr, 0, px0 + pr, 0, [st.primary, st.secondary]); ctx.beginPath(); ctx.arc(px0, py0, pr, 0, 7); ctx.fill();
    ctx.fillStyle = on(st.primary); poly(ctx, [[px0 - pr * .28, py0 - pr * .4], [px0 + pr * .46, py0], [px0 - pr * .28, py0 + pr * .4]]); ctx.fill();
    const pw = story ? 290 : fmt === 'feed' ? 230 : 180, ph = pw * 1.5, px = P + 34, py = y0 + bh - ph * .42;
    posterCard(e, px, py, pw, ph, 20);
    ctx.strokeStyle = A(st.text, .9); ctx.lineWidth = 4; rr(ctx, px, py, pw, ph, 20); ctx.stroke();
    const b3 = blocks(e, { w, align: 'left', parts: ['cta', 'footer'] }), bottom = H - P - b3.h - (b3.h ? 30 : 0);
    const tx = px + pw + 34, b1 = fitBlocks(e, { w: P + w - tx, align: 'left', parts: ['headline', 'title'], titleSize: story ? 92 : fmt === 'feed' ? 78 : 64 }, bottom - y0 - bh - 30);
    b1.draw(tx, y0 + bh + 28);
    let y = Math.max(y0 + bh + 28 + b1.h, py + ph) + 30;
    // gêneros em etiquetas
    const gens = st.showGenres ? (d.genres || []).slice(0, 4) : [];
    if (gens.length && y + 52 < bottom) {
      let gx = P;
      font(ctx, st.fontBody, 24, 700);
      gens.forEach(t => {
        const tw = ctx.measureText(t).width + 40;
        if (gx + tw > P + w) return;
        rr(ctx, gx, y, tw, 48, 24); ctx.fillStyle = A(st.text, .08); ctx.fill(); ctx.strokeStyle = A(st.text, .22); ctx.lineWidth = 1.5; ctx.stroke();
        ctx.fillStyle = st.text; ctx.textAlign = 'left'; ctx.fillText(t, gx + 20, y + 33);
        gx += tw + 12;
      });
      y += 74;
    }
    const e2 = Object.assign({}, e, { st: Object.assign({}, st, { showGenres: false }) });
    const b2 = fitBlocks(e2, { w, align: 'left', parts: ['meta', 'syn'], synLines: story ? 6 : 3 }, bottom - y);
    if (b2.h <= bottom - y) b2.draw(P, y);
    b3.draw(P, H - P - b3.h);
  };

  // Nota em destaque: a avaliação do público num anel, para títulos bem avaliados
  MOVIE.score = function (e) {
    const { ctx, H, P, st, d, im, fmt } = e, story = fmt === 'story', ov = st.overlay / 100;
    const a = im.backdrop || im.poster;
    if (a) blurCover(ctx, a, 0, 0, W, H);
    ctx.fillStyle = A(st.bg, .6 + ov * .34); ctx.fillRect(0, 0, W, H);
    const y0 = (st.showLogo ? logo(e, 56) : P - 20) + 40;
    const e2 = Object.assign({}, e, { st: Object.assign({}, st, { showRating: false }) });
    const b = fitBlocks(e2, { w: W - 2 * P, align: 'center', titleSize: story ? 104 : 86, synLines: story ? 3 : 0 }, H * .46);
    const bot = H - P - b.h - 50, R = Math.max(70, Math.min(story ? 230 : 170, (bot - y0 - 90) / 2)), cy = y0 + 60 + Math.max(R, (bot - y0 - 60) / 2), r = Math.max(0, Math.min(10, +d.rating || 0));
    const t = 'AVALIAÇÃO DO PÚBLICO';
    font(ctx, st.fontBody, 22, 800); ctx.fillStyle = A(st.text, .7); spaced(ctx, t, (W - spacedW(ctx, t, 5)) / 2, cy - R - 36, 5);
    ctx.lineWidth = R * .13; ctx.lineCap = 'round';
    ctx.strokeStyle = A(st.text, .14); ctx.beginPath(); ctx.arc(W / 2, cy, R, 0, Math.PI * 2); ctx.stroke();
    if (r > 0) {
      ctx.save(); ctx.shadowColor = A(st.primary, .6); ctx.shadowBlur = 30;
      ctx.strokeStyle = lin(ctx, W / 2 - R, 0, W / 2 + R, 0, [st.primary, st.secondary]);
      ctx.beginPath(); ctx.arc(W / 2, cy, R, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * r / 10); ctx.stroke(); ctx.restore();
    }
    ctx.lineCap = 'butt';
    font(ctx, st.fontTitle, R * .95, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.text;
    ctx.fillText(r > 0 ? r.toFixed(1) : '—', W / 2, cy + R * .24);
    font(ctx, st.fontBody, R * .13, 700); ctx.fillStyle = A(st.text, .65); ctx.fillText('DE 10', W / 2, cy + R * .5);
    ctx.textAlign = 'left';
    b.draw(P, H - P - b.h);
  };

  /* ---------- Coleção 2: dez composições extras de filmes e séries ---------- */

  // Palco central: desenha a logo, reserva os textos embaixo e entrega a área livre (y0..bot) para a arte
  function centerStage(e, opts, stage) {
    const { H, P, st, fmt } = e, story = fmt === 'story';
    const y0 = (st.showLogo ? logo(e, 56) : P - 20) + 34;
    const b = fitBlocks(e, Object.assign({ w: W - 2 * P, align: 'center', titleSize: story ? 108 : 90, synLines: story ? 3 : 0 }, opts), H * .44);
    const bot = H - P - b.h - 46;
    stage(y0, bot);
    b.draw(P, H - P - b.h);
  }
  function posterIn(y0, bot, maxW) {
    let ph = Math.max(60, bot - y0), pw = ph * 2 / 3;
    if (pw > maxW) { pw = maxW; ph = pw * 1.5; }
    return { pw, ph, px: (W - pw) / 2, py: y0 + (bot - y0 - ph) / 2 };
  }
  const softBg = e => {
    const { ctx, H, st, im } = e, a = im.poster || im.backdrop;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (a) blurCover(ctx, a, 0, 0, W, H);
    ctx.fillStyle = A(st.bg, .62 + st.overlay / 100 * .3); ctx.fillRect(0, 0, W, H);
  };

  // Moldura: arte em tela cheia com uma moldura fina e cantos marcados, como cartaz de galeria
  MOVIE.frameart = function (e) {
    const { ctx, H, P, st, fmt } = e, story = fmt === 'story', ov = st.overlay / 100, m = 34;
    const a = art(e, story ? 'poster' : 'backdrop');
    if (a) cover(ctx, a, 0, 0, W, H, .5, .2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .6)], [.3, A(st.bg, .1 + ov * .2)], [.58, A(st.bg, .35 + ov * .3)], [1, A(st.bg, .94)]]); ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = A(st.text, .85); ctx.lineWidth = 3; ctx.strokeRect(m, m, W - 2 * m, H - 2 * m);
    ctx.fillStyle = st.primary;
    [[m, m], [W - m, m], [m, H - m], [W - m, H - m]].forEach(c => ctx.fillRect(c[0] - 9, c[1] - 9, 18, 18));
    const e2 = Object.assign({}, e, { P: P + 30 });
    logo(e2, m + 40);
    const b = fitBlocks(e2, { w: W - 2 * e2.P, align: 'center', titleSize: story ? 124 : 104, synLines: story ? 4 : 2 }, H * .55);
    b.draw(e2.P, H - m - 46 - b.h);
  };

  // Meio a meio: cena em cima, bloco na cor da marca embaixo com texto invertido
  MOVIE.halves = function (e) {
    const { ctx, H, P, st, fmt } = e, story = fmt === 'story', hT = Math.round(H * (story ? .46 : .42));
    const ink = on(st.primary), dark = ink === '#111111' ? st.bg : mix(st.primary, '#000000', .7);
    const e2 = Object.assign({}, e, { st: Object.assign({}, st, { text: ink, bg: st.primary, primary: dark, secondary: dark }) });
    ctx.fillStyle = st.primary; ctx.fillRect(0, 0, W, H);
    const a = art(e, 'backdrop');
    if (a) cover(ctx, a, 0, 0, W, hT, .5, .2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, 260, [A(st.bg, .7), A(st.bg, 0)]); ctx.fillRect(0, 0, W, 260);
    logo(e, 56);
    const pw = story ? 300 : fmt === 'feed' ? 240 : 190, ph = pw * 1.5, px = P, py = hT - ph * .55;
    posterCard(e, px, py, pw, ph, 18);
    ctx.strokeStyle = ink; ctx.lineWidth = 5; rr(ctx, px, py, pw, ph, 18); ctx.stroke();
    const b3 = blocks(e2, { w: W - 2 * P, align: 'left', parts: ['cta', 'footer'], flat: true }), bottom = H - P - b3.h - (b3.h ? 30 : 0);
    const b1 = fitBlocks(e2, { w: W - 2 * P - pw - 40, align: 'left', parts: ['headline', 'title'], titleSize: story ? 100 : 78, flat: true }, bottom - hT - 40);
    b1.draw(px + pw + 40, hT + 26);
    const y2 = Math.max(py + ph, hT + 26 + b1.h) + 30;
    const b2 = fitBlocks(e2, { w: W - 2 * P, align: 'left', parts: ['meta', 'syn'], synLines: story ? 6 : 3, flat: true }, bottom - y2);
    if (b2.h <= bottom - y2) b2.draw(P, y2);
    b3.draw(P, H - P - b3.h);
  };

  // Coluna: texto numa coluna escura à esquerda, arte ocupando o resto
  MOVIE.sidebar = function (e) {
    const { ctx, H, st, fmt } = e, story = fmt === 'story', cw = Math.round(W * .47), pad = 46;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    const a = art(e, 'poster');
    if (a) cover(ctx, a, cw, 0, W - cw, H, .5, .2);
    ctx.fillStyle = lin(ctx, cw, 0, cw + 200, 0, [st.bg, A(st.bg, 0)]); ctx.fillRect(cw, 0, 200, H);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [st.primary, st.secondary]); ctx.fillRect(0, 0, 10, H);
    const e2 = Object.assign({}, e, { P: pad, st: Object.assign({}, st, { logoAlign: 'left', logoSize: Math.min(st.logoSize, 90) }) });
    logo(e2, 60);
    const b = fitBlocks(e2, { w: cw - pad - 10, align: 'left', titleSize: story ? 96 : 76, synLines: story ? 8 : 3 }, H * .72);
    b.draw(pad, H - 64 - b.h);
  };

  // Círculo: a arte recortada num círculo com anel colorido
  MOVIE.circle = function (e) {
    const { ctx, st } = e;
    softBg(e);
    glow(ctx, W / 2, e.H * .32, W * .8, A(st.primary, .22));
    centerStage(e, {}, (y0, bot) => {
      const R = Math.max(60, Math.min((W - 2 * e.P) / 2 - 30, (bot - y0) / 2 - 24)), cy = y0 + (bot - y0) / 2, a = art(e, 'poster');
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 20;
      ctx.fillStyle = '#111'; ctx.beginPath(); ctx.arc(W / 2, cy, R, 0, 7); ctx.fill(); ctx.restore();
      ctx.save(); ctx.beginPath(); ctx.arc(W / 2, cy, R, 0, 7); ctx.clip();
      if (a) cover(ctx, a, W / 2 - R, cy - R, R * 2, R * 2, .5, .2);
      ctx.restore();
      ctx.strokeStyle = lin(ctx, W / 2 - R, cy - R, W / 2 + R, cy + R, [st.primary, st.secondary]); ctx.lineWidth = 10;
      ctx.beginPath(); ctx.arc(W / 2, cy, R + 16, 0, 7); ctx.stroke();
      ctx.strokeStyle = A(st.text, .5); ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(W / 2, cy, R + 38, -.5, .6); ctx.stroke();
      ctx.beginPath(); ctx.arc(W / 2, cy, R + 38, 2.6, 3.7); ctx.stroke();
    });
  };

  // Inclinado: a cena num cartão girado, sobre uma faixa diagonal na cor da marca
  MOVIE.tilted = function (e) {
    const { ctx, H, st, fmt } = e, story = fmt === 'story';
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.text, .06), st.bg]); ctx.fillRect(0, 0, W, H);
    centerStage(e, {}, (y0, bot) => {
      const cy = y0 + (bot - y0) / 2, a = im2(e), cw = W * .84, ch = Math.min(cw * (story ? .72 : .56), (bot - y0) * .86);
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(-0.07);
      ctx.fillStyle = lin(ctx, -W, 0, W, 0, [st.primary, st.secondary]); ctx.fillRect(-W, -ch * .2, W * 2, ch * .4);
      ctx.restore();
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(0.045);
      ctx.shadowColor = 'rgba(0,0,0,.65)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 26;
      ctx.fillStyle = '#111'; rr(ctx, -cw / 2, -ch / 2, cw, ch, 24); ctx.fill(); ctx.shadowColor = 'transparent';
      rr(ctx, -cw / 2, -ch / 2, cw, ch, 24); ctx.clip();
      if (a) cover(ctx, a, -cw / 2, -ch / 2, cw, ch, .5, .2);
      ctx.restore();
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(0.045); ctx.strokeStyle = st.text; ctx.lineWidth = 6; rr(ctx, -cw / 2, -ch / 2, cw, ch, 24); ctx.stroke(); ctx.restore();
    });
  };
  const im2 = e => e.im.backdrop || e.im.poster;

  // Película: três quadros da cena numa tira de filme inclinada
  MOVIE.filmstrip = function (e) {
    const { ctx, st } = e;
    softBg(e);
    centerStage(e, {}, (y0, bot) => {
      const cy = y0 + (bot - y0) / 2, sh = Math.min(460, (bot - y0) * .7), a = im2(e), fw = sh * .86 * 1.2, gap = 18, hole = 26;
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(-0.06);
      ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 20;
      ctx.fillStyle = '#060608'; ctx.fillRect(-W, -sh / 2, W * 2, sh); ctx.shadowColor = 'transparent';
      ctx.fillStyle = A(st.text, .85);
      for (let x = -W; x < W; x += 54) { rr(ctx, x, -sh / 2 + 12, 30, hole, 6); ctx.fill(); rr(ctx, x, sh / 2 - 12 - hole, 30, hole, 6); ctx.fill(); }
      [-1, 0, 1].forEach(k => {
        const fx = k * (fw + gap) - fw / 2, fy = -sh / 2 + hole + 26, fh = sh - 2 * hole - 52;
        ctx.save(); rr(ctx, fx, fy, fw, fh, 8); ctx.clip(); ctx.fillStyle = '#16161c'; ctx.fillRect(fx, fy, fw, fh);
        if (a) cover(ctx, a, fx, fy, fw, fh, .5 + k * .38, .25);
        ctx.restore();
      });
      ctx.restore();
    });
  };

  // Tipografia gigante: o título enorme em contorno atrás do pôster
  MOVIE.bigtype = function (e) {
    const { ctx, H, st, d } = e;
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.primary, .12), st.bg]); ctx.fillRect(0, 0, W, H);
    centerStage(e, { parts: ['headline', 'meta', 'syn', 'cta', 'footer'] }, (y0, bot) => {
      const words = String(d.title || '').toUpperCase().split(/\s+/).filter(Boolean).slice(0, 4), n = Math.max(1, words.length), lh = (bot - y0) / n;
      ctx.save(); ctx.textAlign = 'center'; ctx.lineWidth = 3; ctx.strokeStyle = A(st.text, .22);
      words.forEach((wd, i) => {
        let s = Math.min(lh * 1.05, 520);
        font(ctx, st.fontTitle, s, 800);
        const tw = ctx.measureText(wd).width;
        if (tw > W * 1.04) { s *= W * 1.04 / tw; font(ctx, st.fontTitle, s, 800); }
        ctx.strokeText(wd, W / 2, y0 + i * lh + lh / 2 + s * .36);
      });
      ctx.restore();
      const r = posterIn(y0 + 10, bot - 10, W * .52);
      posterCard(e, r.px, r.py, r.pw, r.ph, 22);
      ctx.strokeStyle = lin(ctx, r.px, r.py, r.px + r.pw, r.py + r.ph, [st.primary, st.secondary]); ctx.lineWidth = 6; rr(ctx, r.px, r.py, r.pw, r.ph, 22); ctx.stroke();
    });
  };

  // Vidro: arte em tela cheia e os textos num painel translúcido
  MOVIE.glass = function (e) {
    const { ctx, H, P, st, fmt } = e, story = fmt === 'story', ov = st.overlay / 100;
    const a = art(e, story ? 'poster' : 'backdrop');
    if (a) cover(ctx, a, 0, 0, W, H, .5, .15);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .6)], [.25, A(st.bg, .05 + ov * .2)], [1, A(st.bg, .3 + ov * .3)]]); ctx.fillRect(0, 0, W, H);
    logo(e, 56);
    const pad = 44, b = fitBlocks(e, { w: W - 2 * P - 2 * pad, align: 'left', titleSize: story ? 104 : 86, synLines: story ? 4 : 2, flat: true }, H * .52);
    const ph = b.h + 2 * pad, py = H - P - ph;
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 18;
    ctx.fillStyle = A(st.bg, .66); rr(ctx, P, py, W - 2 * P, ph, 36); ctx.fill(); ctx.restore();
    ctx.fillStyle = lin(ctx, 0, py, 0, py + ph * .5, [A(st.text, .14), A(st.text, 0)]); rr(ctx, P, py, W - 2 * P, ph, 36); ctx.fill();
    ctx.strokeStyle = A(st.text, .3); ctx.lineWidth = 2; rr(ctx, P, py, W - 2 * P, ph, 36); ctx.stroke();
    b.draw(P + pad, py + pad);
  };

  // Faixas: três faixas diagonais coloridas cruzando atrás do pôster
  MOVIE.bands = function (e) {
    const { ctx, H, st } = e;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    centerStage(e, {}, (y0, bot) => {
      const cy = y0 + (bot - y0) / 2, bh = (bot - y0) * .2;
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(-0.32);
      [[st.primary, -1.15], [st.secondary, 0], [A(st.text, .16), 1.15]].forEach(c => { ctx.fillStyle = c[0]; ctx.fillRect(-W * 1.2, c[1] * bh - bh / 2, W * 2.4, bh * .86); });
      ctx.restore();
      const r = posterIn(y0, bot, W * .56);
      posterCard(e, r.px, r.py, r.pw, r.ph, 22);
      ctx.strokeStyle = st.text; ctx.lineWidth = 6; rr(ctx, r.px, r.py, r.pw, r.ph, 22); ctx.stroke();
    });
  };

  // Selo: pôster em destaque com um selo recortado trazendo a chamada
  MOVIE.seal = function (e) {
    const { ctx, st } = e;
    softBg(e);
    glow(ctx, W / 2, e.H * .35, W * .8, A(st.primary, .2));
    centerStage(e, { parts: ['title', 'meta', 'syn', 'cta', 'footer'] }, (y0, bot) => {
      const r = posterIn(y0 + 30, bot, W * .6);
      posterCard(e, r.px, r.py, r.pw, r.ph, 24);
      ctx.strokeStyle = A(st.text, .25); ctx.lineWidth = 2; rr(ctx, r.px, r.py, r.pw, r.ph, 24); ctx.stroke();
      if (!st.headline) return;
      const R = Math.max(70, r.pw * .27), cx = r.px + r.pw - R * .25, cy = r.py + R * .35;
      ctx.save(); ctx.translate(cx, cy); ctx.rotate(.2);
      ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 10;
      ctx.fillStyle = lin(ctx, -R, -R, R, R, [st.primary, st.secondary]); ctx.beginPath();
      for (let i = 0; i < 40; i++) { const an = i * Math.PI / 20, k = i % 2 ? R * .88 : R; ctx.lineTo(Math.cos(an) * k, Math.sin(an) * k); }
      ctx.closePath(); ctx.fill(); ctx.shadowColor = 'transparent';
      ctx.strokeStyle = A(on(st.primary), .5); ctx.lineWidth = 2.5; ctx.beginPath(); ctx.arc(0, 0, R * .74, 0, 7); ctx.stroke();
      font(ctx, st.fontTitle, R * .36, 800);
      const lines = wrap(ctx, st.headline.toUpperCase(), R * 1.25, 2), lh = R * .36;
      ctx.fillStyle = on(st.primary); ctx.textAlign = 'center';
      lines.forEach((l, i) => ctx.fillText(l, 0, lh * .34 + (i - (lines.length - 1) / 2) * lh));
      ctx.restore(); ctx.textAlign = 'left';
    });
  };

  // Revista: arte em tela cheia, título grande no topo e chamada embaixo
  MOVIE.magazine = function (e) {
    const { ctx, H, P, st, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const a = art(e, 'poster');
    if (a) cover(ctx, a, 0, 0, W, H, .5, .2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .86)], [.3, A(st.bg, .12 + ov * .25)], [.58, A(st.bg, .08 + ov * .25)], [.82, A(st.bg, .86)], [1, st.bg]]);
    ctx.fillRect(0, 0, W, H);
    let y = (st.showLogo ? logo(e, 56) : P - 24) + 28;
    const bt = blocks(e, { w: W - 2 * P, align: 'center', parts: ['title'], titleSize: story ? 150 : fmt === 'feed' ? 124 : 100, titleLines: 2 });
    bt.draw(P, y);
    y += bt.h + 20;
    ctx.fillStyle = lin(ctx, W / 2 - 100, 0, W / 2 + 100, 0, [st.primary, st.secondary]);
    ctx.fillRect(W / 2 - 100, y, 200, 7);
    const bb = fitBlocks(e, { w: W - 2 * P, align: 'center', parts: ['headline', 'meta', 'syn', 'cta', 'footer'], synLines: story ? 3 : fmt === 'feed' ? 2 : 0 }, H * .42);
    bb.draw(P, H - P - bb.h);
  };

  // Faixa lateral: chamada escrita na vertical sobre uma faixa colorida
  MOVIE.ribbon = function (e) {
    const { ctx, H, st, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const a = art(e, story ? 'poster' : 'backdrop');
    if (a) cover(ctx, a, 0, 0, W, H, .5, .15);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .6)], [.22, A(st.bg, ov * .15)], [.45, A(st.bg, .1 + ov * .3)], [.72, A(st.bg, .82 + ov * .15)], [1, st.bg]]);
    ctx.fillRect(0, 0, W, H);
    const bw = st.headline ? 112 : 26;
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [st.primary, st.secondary]);
    ctx.fillRect(0, 0, bw, H);
    if (st.headline) {
      const t = st.headline.toUpperCase();
      ctx.save();
      ctx.translate(bw / 2 + 16, H / 2); ctx.rotate(-Math.PI / 2);
      const f = fitLine(ctx, t, st.fontBody, 46, 22, 800, H * .8);
      ctx.fillStyle = on(st.primary); ctx.textAlign = 'center';
      ctx.fillText(f.text, 0, 0);
      ctx.restore(); ctx.textAlign = 'left';
    }
    const e2 = Object.assign({}, e, { P: bw + 46 });
    logo(e2, 56);
    const b = fitBlocks(e2, { w: W - 2 * e2.P, align: 'left', parts: ['title', 'meta', 'syn', 'cta', 'footer'], titleSize: story ? 124 : fmt === 'feed' ? 108 : 92, synLines: story ? 5 : fmt === 'feed' ? 3 : 2 }, H * .6);
    b.draw(e2.P, H - e.P - b.h);
  };

  // Catálogo: grade de pôsteres (usa os títulos selecionados ou os primeiros da lista)
  MOVIE.catalog = function (e) {
    const { ctx, H, P, st, d, im, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const first = (im.list && im.list[0]) || im.poster;
    if (first) blurCover(ctx, first, 0, 0, W, H);
    ctx.fillStyle = A(st.bg, .62 + ov * .32); ctx.fillRect(0, 0, W, H);
    glow(ctx, W / 2, 0, W * .9, A(st.primary, .25));
    let y = (st.showLogo ? logo(e, 56) : P - 30) + 26;
    const f = fitLine(ctx, String(st.rankTitle || '').toUpperCase(), st.fontTitle, story ? 118 : fmt === 'feed' ? 100 : 86, 44, 800, W - 2 * P);
    ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = lin(ctx, 0, y, 0, y + f.size, [mix(st.primary, '#FFFFFF', .3), st.primary, st.secondary]);
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 20;
    ctx.fillText(f.text, W / 2, y + f.size * .84); ctx.restore();
    y += f.size * .96;
    const b = blocks(e, { w: W - 2 * P, align: 'center', parts: ['cta', 'footer'] });
    b.draw(P, H - P - b.h);
    const top = y + 26, bot = H - P - b.h - (b.h ? 30 : 0);
    const cols = story ? 2 : 3, rows = story ? 3 : fmt === 'feed' ? 2 : 1, gap = 24, cap = 54;
    const items = (d.list || []).slice(0, cols * rows);
    if (!items.length) return;
    const used = Math.ceil(items.length / cols);
    let ph = (bot - top - gap * (rows - 1)) / rows - cap, pw = ph * 2 / 3;
    const maxW = (W - 2 * P - gap * (cols - 1)) / cols;
    if (pw > maxW) { pw = maxW; ph = pw * 1.5; }
    const gw = cols * pw + gap * (cols - 1), gh = used * (ph + cap) + gap * (used - 1);
    const x0 = (W - gw) / 2, y0 = top + Math.max(0, (bot - top - gh) / 2);
    items.forEach((it, i) => {
      const px = x0 + (i % cols) * (pw + gap), py = y0 + Math.floor(i / cols) * (ph + cap + gap);
      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,.55)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 12;
      ctx.fillStyle = mix(st.bg, st.text, .12); rr(ctx, px, py, pw, ph, 18); ctx.fill();
      ctx.restore();
      ctx.save(); rr(ctx, px, py, pw, ph, 18); ctx.clip();
      if (im.list && im.list[i]) cover(ctx, im.list[i], px, py, pw, ph);
      ctx.restore();
      ctx.strokeStyle = A(st.text, .16); ctx.lineWidth = 2; rr(ctx, px, py, pw, ph, 18); ctx.stroke();
      if (st.showRating && it.rating > 0) {
        font(ctx, st.fontBody, 22, 800);
        const s = '★ ' + Number(it.rating).toFixed(1), sw = ctx.measureText(s).width + 24;
        ctx.fillStyle = st.primary; rr(ctx, px + 12, py + 12, sw, 38, 10); ctx.fill();
        ctx.fillStyle = on(st.primary); ctx.textAlign = 'left'; ctx.fillText(s, px + 24, py + 39);
      }
      const t = fitLine(ctx, st.upper ? it.title.toUpperCase() : it.title, st.fontBody, 25, 16, 700, pw);
      ctx.fillStyle = st.text; ctx.textAlign = 'center';
      ctx.fillText(t.text, px + pw / 2, py + ph + 36);
      ctx.textAlign = 'left';
    });
  };

  MOVIE.split = function (e) {
    const { ctx, H, P, st, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const hTop = Math.round(H * (story ? .5 : fmt === 'feed' ? .47 : .45)), cut = 110;
    ctx.fillStyle = lin(ctx, 0, hTop - cut, 0, H, [mix(st.bg, st.primary, .09), st.bg]);
    ctx.fillRect(0, 0, W, H);
    const a = art(e, 'backdrop');
    ctx.save();
    poly(ctx, [[0, 0], [W, 0], [W, hTop - cut], [0, hTop]]); ctx.clip();
    if (a) cover(ctx, a, 0, 0, W, hTop, .5, .2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, hTop, [[0, A(st.bg, .65)], [.35, A(st.bg, ov * .3)], [1, A(st.bg, .15 + ov * .45)]]);
    ctx.fillRect(0, 0, W, hTop);
    ctx.restore();
    ctx.fillStyle = lin(ctx, 0, 0, W, 0, [st.primary, st.secondary]);
    poly(ctx, [[0, hTop], [W, hTop - cut], [W, hTop - cut + 16], [0, hTop + 16]]); ctx.fill();
    logo(e, 56);

    const pw = story ? 340 : fmt === 'feed' ? 290 : 230, ph = pw * 1.5, px = W - P - pw, py = hTop - cut - ph * .52;
    posterCard(e, px, py, pw, ph, 22);
    ctx.save(); ctx.strokeStyle = A(st.text, .9); ctx.lineWidth = 5; rr(ctx, px, py, pw, ph, 22); ctx.stroke(); ctx.restore();

    const y1 = hTop + 16 + 36;
    const b3 = blocks(e, { w: W - 2 * P, align: 'left', parts: ['cta', 'footer'] });
    const bottom = H - P - b3.h - (b3.h ? 34 : 0);
    const b1 = fitBlocks(e, { w: W - 2 * P - pw - 44, align: 'left', parts: ['headline', 'title'], titleSize: story ? 112 : fmt === 'feed' ? 96 : 80 }, bottom - y1 - 50);
    b1.draw(P, y1);
    const y2 = Math.max(y1 + b1.h, py + ph) + 34;
    let b2 = fitBlocks(e, { w: W - 2 * P, align: 'left', parts: ['meta', 'syn'], synLines: story ? 7 : 4 }, bottom - y2);
    if (b2.h > bottom - y2) b2 = blocks(e, { w: W - 2 * P - pw - 44, align: 'left', parts: ['meta'] });
    if (b2.h <= bottom - y2) b2.draw(P, y2);
    else if (y1 + b1.h + 26 + b2.h <= bottom) b2.draw(P, y1 + b1.h + 26);
    b3.draw(P, H - P - b3.h);
  };

  MOVIE.ranking = function (e) {
    const { ctx, H, P, st, d, im, fmt } = e, ov = st.overlay / 100, story = fmt === 'story';
    const first = (im.list && im.list[0]) || im.poster;
    if (first) blurCover(ctx, first, 0, 0, W, H);
    ctx.fillStyle = A(st.bg, .58 + ov * .36); ctx.fillRect(0, 0, W, H);
    glow(ctx, W / 2, 0, W * .9, A(st.primary, .25));
    let y = (st.showLogo ? logo(e, 56) : P - 30) + 26;
    const f = fitLine(ctx, String(st.rankTitle || '').toUpperCase(), st.fontTitle, story ? 124 : fmt === 'feed' ? 106 : 92, 46, 800, W - 2 * P);
    ctx.save(); ctx.textAlign = 'center'; ctx.fillStyle = lin(ctx, 0, y, 0, y + f.size, [mix(st.primary, '#FFFFFF', .3), st.primary, st.secondary]);
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 20;
    ctx.fillText(f.text, W / 2, y + f.size * .84); ctx.restore();
    y += f.size * .96;

    const b = blocks(e, { w: W - 2 * P, align: 'center', parts: ['cta', 'footer'] });
    b.draw(P, H - P - b.h);
    const top = y + 24, bot = H - P - b.h - (b.h ? 30 : 0);
    const items = (d.list || []).slice(0, story ? 5 : fmt === 'feed' ? 4 : 3);
    if (!items.length) return;
    const rh = Math.min((bot - top) / items.length, 280);
    let ry = top + (bot - top - rh * items.length) / 2;
    items.forEach((it, i) => {
      const cy = ry + 8, ch = rh - 16;
      rr(ctx, P, cy, W - 2 * P, ch, 24);
      ctx.fillStyle = A(st.text, .08); ctx.fill();
      ctx.strokeStyle = A(st.text, .1); ctx.lineWidth = 1.5; ctx.stroke();
      // número
      const ns = ch * .74;
      font(ctx, st.fontTitle, ns, 800); ctx.textAlign = 'center';
      ctx.fillStyle = lin(ctx, 0, cy, 0, cy + ch, [st.primary, st.secondary]);
      ctx.fillText(String(i + 1), P + 86, cy + ch / 2 + ns * .34);
      // pôster
      const ph = ch - 24, pw = ph * 2 / 3, px = P + 168, py = cy + 12;
      ctx.save(); rr(ctx, px, py, pw, ph, 12); ctx.clip();
      ctx.fillStyle = A(st.text, .1); ctx.fillRect(px, py, pw, ph);
      if (im.list && im.list[i]) cover(ctx, im.list[i], px, py, pw, ph);
      ctx.restore();
      // textos
      const tx = px + pw + 34, tw = W - P - 30 - tx;
      const t = fitLine(ctx, st.upper ? it.title.toUpperCase() : it.title, st.fontTitle, Math.min(62, ch * .3), 30, 800, tw);
      ctx.textAlign = 'left'; ctx.fillStyle = st.text;
      ctx.fillText(t.text, tx, cy + ch / 2 - 4);
      const ms = Math.min(30, ch * .15);
      font(ctx, st.fontBody, ms, 700);
      let mx = tx;
      const my = cy + ch / 2 + ms * 1.55;
      if (it.rating > 0) { ctx.fillStyle = st.primary; const s = '★ ' + Number(it.rating).toFixed(1); ctx.fillText(s, mx, my); mx += ctx.measureText(s).width + 26; }
      ctx.fillStyle = A(st.text, .75);
      ctx.fillText([it.typeLabel, it.year].filter(Boolean).join('  •  '), mx, my);
      ry += rh;
    });
  };

  /* =====================  JOGOS DO DIA  ===================== */

  // Três fundos prontos para os banners de jogos, desenhados na hora (sem imagens externas)
  function presetBg(e, kind) {
    const { ctx, H, st } = e;
    let seed = 7;
    const rnd = () => { seed = (seed * 16807) % 2147483647; return seed / 2147483647; };
    if (kind === 'pitch') {
      // Gramado visto de cima: faixas de corte e marcações do campo
      const g1 = '#0E5A2B', g2 = '#12702F', sw = 150;
      for (let y = 0, i = 0; y < H; y += sw, i++) { ctx.fillStyle = i % 2 ? g1 : g2; ctx.fillRect(0, y, W, sw); }
      ctx.strokeStyle = 'rgba(255,255,255,.5)'; ctx.lineWidth = 6;
      ctx.strokeRect(60, 60, W - 120, H - 120);
      ctx.beginPath(); ctx.moveTo(60, H / 2); ctx.lineTo(W - 60, H / 2); ctx.stroke();
      ctx.beginPath(); ctx.arc(W / 2, H / 2, 190, 0, 7); ctx.stroke();
      ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.beginPath(); ctx.arc(W / 2, H / 2, 10, 0, 7); ctx.fill();
      [[60, 1], [H - 60, -1]].forEach(([y, d]) => {
        ctx.strokeRect(W / 2 - 330, d > 0 ? y : y - 300, 660, 300);
        ctx.strokeRect(W / 2 - 150, d > 0 ? y : y - 110, 300, 110);
        ctx.beginPath(); ctx.arc(W / 2, y + d * 300, 130, d > 0 ? .25 : Math.PI + .25, d > 0 ? Math.PI - .25 : Math.PI * 2 - .25); ctx.stroke();
      });
      const v = ctx.createRadialGradient(W / 2, H / 2, W * .2, W / 2, H / 2, H * .7);
      v.addColorStop(0, 'rgba(0,0,0,0)'); v.addColorStop(1, 'rgba(0,0,0,.55)');
      ctx.fillStyle = v; ctx.fillRect(0, 0, W, H);
    } else if (kind === 'stadium') {
      // Estádio à noite: refletores, arquibancada pontilhada de luzes e o gramado embaixo
      ctx.fillStyle = lin(ctx, 0, 0, 0, H, ['#05070F', '#0B1430', '#06140C']); ctx.fillRect(0, 0, W, H);
      for (let i = 0; i < 260; i++) { const x = rnd() * W, y = H * .16 + rnd() * H * .3, r = 1.5 + rnd() * 3; ctx.fillStyle = 'rgba(255,255,255,' + (.08 + rnd() * .3) + ')'; ctx.beginPath(); ctx.arc(x, y, r, 0, 7); ctx.fill(); }
      ctx.fillStyle = lin(ctx, 0, H * .44, 0, H * .52, ['rgba(5,7,15,0)', 'rgba(5,7,15,.9)']); ctx.fillRect(0, H * .44, W, H * .08);
      ctx.fillStyle = lin(ctx, 0, H * .52, 0, H, ['#0C4A22', '#06200F']); ctx.fillRect(0, H * .52, W, H * .48);
      for (let x = -200; x < W; x += 260) { ctx.fillStyle = 'rgba(255,255,255,.035)'; poly(ctx, [[x + 200, H * .52], [x + 330, H * .52], [x + 130, H], [x - 130, H]]); ctx.fill(); }
      [[W * .12, 1], [W * .88, -1]].forEach(([x, d]) => {
        ctx.fillStyle = lin(ctx, x, 0, x + d * W * .5, H * .7, ['rgba(255,255,255,.3)', 'rgba(255,255,255,0)']);
        poly(ctx, [[x - 60, 60], [x + 60, 60], [x + d * W * .75, H * .8], [x + d * W * .2, H * .8]]); ctx.fill();
        for (let r = 0; r < 3; r++) for (let c = 0; c < 4; c++) { ctx.fillStyle = '#FFFFFF'; ctx.beginPath(); ctx.arc(x - 54 + c * 36, 40 + r * 30, 11, 0, 7); ctx.fill(); }
        glow(ctx, x, 70, 260, 'rgba(255,255,255,.5)');
      });
    } else {
      // Rede do gol sobre as cores da marca
      ctx.fillStyle = lin(ctx, 0, 0, W, H, [mix(st.bg, st.primary, .45), mix(st.bg, st.secondary, .2), st.bg]); ctx.fillRect(0, 0, W, H);
      glow(ctx, W / 2, H * .4, W * .9, A(st.primary, .35));
      ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.lineWidth = 3; ctx.beginPath();
      for (let x = -H; x < W + H; x += 74) { ctx.moveTo(x, 0); ctx.lineTo(x + H * .6, H); ctx.moveTo(x, 0); ctx.lineTo(x - H * .6, H); }
      ctx.stroke();
    }
  }

  function gBg(e, skin) {
    const { ctx, H, st, im } = e, ov = st.overlay / 100;
    const bs = st.bgStyle || 'auto', preset = bs === 'pitch' || bs === 'stadium' || bs === 'net';
    // "hasImg" também vale para os fundos prontos: os enfeites do modelo (linhas de campo etc.) ficam de fora
    const hasImg = (bs === 'upload' && im.bg) || preset;
    if (preset) {
      presetBg(e, bs);
      ctx.fillStyle = A(st.bg, skin === 'clean' ? .74 + ov * .2 : .08 + ov * .55); ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .7)], [.26, A(st.bg, 0)], [.8, A(st.bg, 0)], [1, A(st.bg, .8)]]); ctx.fillRect(0, 0, W, H);
      if (skin === 'gold') goldFrame(e);
      return;
    }
    if (skin === 'clean') {
      ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
      if (hasImg && im.bg) { cover(ctx, im.bg, 0, 0, W, H); ctx.fillStyle = A(st.bg, .7 + ov * .25); ctx.fillRect(0, 0, W, H); }
      ctx.fillStyle = A(st.primary, .2); ctx.beginPath(); ctx.arc(W * .95, H * .03, W * .55, 0, 7); ctx.fill();
      ctx.fillStyle = A(st.secondary, .15); ctx.beginPath(); ctx.arc(0, H, W * .4, 0, 7); ctx.fill();
      return;
    }
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, mix(st.bg, st.primary, .12)], [.45, st.bg], [1, mix(st.bg, '#000000', .45)]]);
    ctx.fillRect(0, 0, W, H);
    if (hasImg && im.bg) { cover(ctx, im.bg, 0, 0, W, H); ctx.fillStyle = A(st.bg, .4 + ov * .5); ctx.fillRect(0, 0, W, H); }
    if (skin === 'arena' || skin === 'hero' || skin === 'duel') {
      glow(ctx, W / 2, -60, W * .95, A(st.primary, .3));
      if (!hasImg) {
        ctx.save(); ctx.strokeStyle = A(st.text, .045); ctx.lineWidth = 5;
        ctx.beginPath(); ctx.arc(W / 2, H * .54, 250, 0, 7); ctx.moveTo(30, H * .54); ctx.lineTo(W - 30, H * .54); ctx.stroke();
        ctx.strokeRect(30, 30, W - 60, H - 60);
        ctx.restore();
        ctx.fillStyle = A(st.text, .025);
        poly(ctx, [[0, 0], [260, 0], [W * .62, H], [W * .3, H]]); ctx.fill();
        poly(ctx, [[W, 0], [W - 200, 0], [W * .52, H], [W * .78, H]]); ctx.fill();
      }
    } else if (skin === 'neon') {
      glow(ctx, 0, H * .12, W * .9, A(st.primary, .36));
      glow(ctx, W, H * .9, W * .9, A(st.secondary, .32));
      ctx.strokeStyle = A(st.text, .04); ctx.lineWidth = 2; ctx.beginPath();
      for (let x = 0; x <= W; x += 72) { ctx.moveTo(x, 0); ctx.lineTo(x, H); }
      for (let y = 0; y <= H; y += 72) { ctx.moveTo(0, y); ctx.lineTo(W, y); }
      ctx.stroke();
    } else if (skin === 'gold') {
      glow(ctx, W / 2, 0, W, A(st.primary, .2));
      goldFrame(e);
    } else if (skin === 'compact') {
      glow(ctx, W, 0, W * .8, A(st.primary, .22));
      ctx.fillStyle = A(st.text, .018);
      for (let x = -H; x < W; x += 110) { poly(ctx, [[x, H], [x + 46, H], [x + 46 + H * .55, 0], [x + H * .55, 0]]); ctx.fill(); }
    }
  }

  // Cabeçalho comum (logo, título, data) — devolve o Y logo abaixo
  function gHeader(e, skin, size) {
    const { ctx, P, st, d } = e;
    const top = skin === 'gold' ? 72 : 50;
    let y = st.showLogo ? logo(e, top, 96) + 16 : top;
    const f = fitLine(ctx, String(st.title || '').toUpperCase(), st.fontTitle, size, 46, 800, W - 2 * P);
    ctx.save(); ctx.textAlign = 'center';
    if (skin === 'gold') ctx.fillStyle = lin(ctx, 0, y, 0, y + f.size, [mix(st.primary, '#FFFFFF', .35), st.primary, st.secondary]);
    else ctx.fillStyle = st.text;
    if (skin === 'neon') { ctx.shadowColor = st.primary; ctx.shadowBlur = 36; }
    else if (skin !== 'clean') { ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 4; }
    ctx.fillText(f.text, W / 2, y + f.size * .84);
    ctx.restore();
    y += f.size * (TIGHT.has(st.fontTitle) ? .9 : 1.02);
    if (st.showDate && d.dateLabel) {
      const t = String(d.dateLabel).toUpperCase(), h = 48, pw = pillW(ctx, st, t, h, 24);
      y += 10;
      pill(ctx, st, t, (W - pw) / 2, y, h, 24, skin === 'neon' || skin === 'gold' ? 'outline' : 'fill');
      y += h;
    }
    return y;
  }

  function gFooter(e) {
    const { ctx, H, P, st, d } = e;
    const b = blocks(e, { w: W - 2 * P, align: 'center', parts: ['cta', 'footer'], flat: true });
    b.draw(P, H - P - b.h);
    if (d.pages > 1) {
      font(ctx, st.fontBody, 22, 700); ctx.textAlign = 'right'; ctx.fillStyle = A(st.text, .5);
      ctx.fillText(d.page + '/' + d.pages, W - P, H - P + 4); ctx.textAlign = 'left';
    }
    return H - P - b.h - (b.h ? 28 : 0);
  }

  function crest(e, img, name, x, y, s) {
    const { ctx, st } = e;
    if (img) { contain(ctx, img, x, y, s, s); return; }
    ctx.fillStyle = A(st.text, .12); ctx.beginPath(); ctx.arc(x + s / 2, y + s / 2, s / 2, 0, 7); ctx.fill();
    font(ctx, st.fontTitle, s * .42, 800); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .7);
    ctx.fillText(String(name).replace(/[^A-Za-zÀ-ú ]/g, '').split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase(), x + s / 2, y + s / 2 + s * .15);
    ctx.textAlign = 'left';
  }

  const hasScore = (st, g) => st.showScore && g.s1 !== '' && g.s2 !== '' && g.status !== '';
  // Canais de um jogo em linha: logo quando existe, nome quando não. ax/cy = âncora horizontal e centro vertical
  function chRow(e, g, gi, ax, cy, h, maxW, align, color, onLight) {
    const { ctx, st, im } = e, list = g.ch && g.ch.length ? g.ch : (g.channels || []).map(n => ({ n, i: '', x: '', t: 0 }));
    const imgs = (im.ch && im.ch[gi]) || [], gap = h * .34, fs = h * .52, items = [];
    let tw = 0, more = 0;
    font(ctx, st.fontBody, fs, 800);
    for (let k = 0; k < list.length; k++) {
      const c = list[k], img = imgs[k], it = { c, img };
      if (img) {
        // Logos recortados: os largos (ESPN) ganham largura, os quadrados (Globo) ganham altura — peso visual parecido
        const a = c.t ? (c.a || img.width / img.height) : img.width / img.height;
        it.dh = Math.min(h, 1.5 * h / Math.sqrt(a)); it.dw = it.dh * a;
        if (it.dw > h * 3.2) { it.dw = h * 3.2; it.dh = it.dw / a; }
        // chapa atrás do logo só quando falta contraste (logo claro em cartão claro, ou escuro em cartão escuro)
        it.chip = c.t ? ((c.l ? 1 : 0) === (onLight ? 1 : 0)) : false;
        it.pad = it.chip ? h * .2 : 0;
        it.xw = c.x ? ctx.measureText(c.x).width + h * .16 : 0;
        it.w = it.dw + 2 * it.pad + it.xw;
      } else { it.t = c.n.toUpperCase(); it.w = ctx.measureText(it.t).width; }
      const reserve = k < list.length - 1 ? fs * 1.8 : 0;
      if (items.length && tw + gap + it.w > maxW - reserve) { more = list.length - k; break; }
      if (!items.length && it.w > maxW) {
        if (it.t) { it.t = ell(ctx, it.t, maxW); it.w = ctx.measureText(it.t).width; }
        else { const k2 = maxW / it.w; it.dw *= k2; it.dh *= k2; it.pad *= k2; it.w = maxW; }
      }
      tw += (items.length ? gap : 0) + it.w;
      items.push(it);
    }
    const mt = more ? '+' + more : '', mw = more ? ctx.measureText(mt).width + gap : 0;
    let x = align === 'right' ? ax - tw - mw : align === 'center' ? ax - (tw + mw) / 2 : ax;
    ctx.textAlign = 'left';
    items.forEach(it => {
      if (it.img) {
        if (it.chip) { ctx.fillStyle = it.c.l ? '#15151D' : '#FFFFFF'; rr(ctx, x, cy - h / 2, it.dw + 2 * it.pad, h, h * .2); ctx.fill(); }
        ctx.drawImage(it.img, x + it.pad, cy - it.dh / 2, it.dw, it.dh);
        if (it.c.x) { font(ctx, st.fontBody, fs, 800); ctx.fillStyle = color; ctx.fillText(it.c.x, x + it.dw + 2 * it.pad + h * .16, cy + fs * .36); }
      } else { font(ctx, st.fontBody, fs, 800); ctx.fillStyle = color; ctx.fillText(it.t, x, cy + fs * .36); }
      x += it.w + gap;
    });
    if (more) { font(ctx, st.fontBody, fs, 800); ctx.fillStyle = color; ctx.fillText(mt, x, cy + fs * .36); }
  }

  function gameCard(e, g, gi, i, x, y, w, h, skin) {
    const { ctx, st, im } = e;
    const comp = st.showComp && g.comp, chan = st.showChannels && g.channels.length;
    const sh = comp || chan ? Math.min(chan ? 80 : 46, h * (chan ? .42 : .3)) : 0, mh = h - sh, my = y + mh / 2 + (sh ? 3 : 0);
    const R = skin === 'compact' ? 12 : 22;

    ctx.save();
    rr(ctx, x, y, w, h, R);
    if (skin === 'neon') {
      ctx.fillStyle = A(st.bg, .6); ctx.fill();
      ctx.strokeStyle = st.primary; ctx.lineWidth = 2.5; ctx.shadowColor = st.primary; ctx.shadowBlur = 20; ctx.stroke();
    } else if (skin === 'clean') {
      ctx.shadowColor = 'rgba(0,0,0,.13)'; ctx.shadowBlur = 26; ctx.shadowOffsetY = 8;
      ctx.fillStyle = '#FFFFFF'; ctx.fill();
    } else if (skin === 'gold') {
      ctx.fillStyle = A(st.text, .05); ctx.fill();
      ctx.strokeStyle = lin(ctx, x, y, x + w, y + h, [st.primary, A(st.secondary, .35), st.primary]); ctx.lineWidth = 2; ctx.stroke();
    } else if (skin === 'compact') {
      ctx.fillStyle = A(st.text, i % 2 ? .05 : .1); ctx.fill();
    } else if (skin === 'duel') {
      // metade de cada cor, encontrando-se no centro
      ctx.fillStyle = A(st.text, .07); ctx.fill();
      ctx.clip();
      ctx.fillStyle = lin(ctx, x, 0, x + w / 2, 0, [A(st.primary, .42), A(st.primary, 0)]); ctx.fillRect(x, y, w / 2, h);
      ctx.fillStyle = lin(ctx, x + w, 0, x + w / 2, 0, [A(st.secondary, .42), A(st.secondary, 0)]); ctx.fillRect(x + w / 2, y, w / 2, h);
    } else {
      ctx.fillStyle = A(st.text, .08); ctx.fill();
      ctx.strokeStyle = A(st.text, .1); ctx.lineWidth = 1.5; ctx.stroke();
    }
    ctx.restore();
    if (skin === 'arena') { ctx.fillStyle = lin(ctx, 0, y, 0, y + h, [st.primary, st.secondary]); rr(ctx, x, y + 20, 7, h - 40, 4); ctx.fill(); }

    const txt = skin === 'clean' ? mix(st.text, '#000000', .1) : st.text;
    const ip = 22, ls = Math.min(mh - 22, 104), cw = 200, nameW = (w - cw) / 2 - ip - ls - 18;
    crest(e, im.l1[gi], g.t1, x + ip, my - ls / 2, ls);
    crest(e, im.l2[gi], g.t2, x + w - ip - ls, my - ls / 2, ls);
    const ns = Math.min(38, mh * .36);
    ctx.fillStyle = txt;
    let f = fitLine(ctx, g.t1.toUpperCase(), st.fontBody, ns, 20, 800, nameW);
    ctx.textAlign = 'left'; ctx.fillText(f.text, x + ip + ls + 18, my + f.size * .36);
    f = fitLine(ctx, g.t2.toUpperCase(), st.fontBody, ns, 20, 800, nameW);
    ctx.textAlign = 'right'; ctx.fillText(f.text, x + w - ip - ls - 18, my + f.size * .36);

    // centro: horário ou placar
    const cs = Math.min(66, mh * .58);
    ctx.textAlign = 'center';
    ctx.fillStyle = skin === 'clean' && lum(st.primary) > .75 ? txt : st.primary;
    if (hasScore(st, g)) {
      font(ctx, st.fontTitle, cs, 800);
      ctx.fillText(g.s1 + ' - ' + g.s2, x + w / 2, my + cs * .34 - (mh > 100 ? 8 : 0));
      if (mh > 100) { font(ctx, st.fontBody, 18, 700); ctx.fillStyle = A(txt, .65); ctx.fillText(g.time, x + w / 2, my + cs * .34 + 16); }
    } else {
      font(ctx, st.fontTitle, cs, 800);
      ctx.fillText(g.time || 'x', x + w / 2, my + cs * .35);
    }

    if (sh) {
      const sy = y + mh, fs = Math.min(21, sh * .34), by = sy + sh / 2 + fs * .32;
      ctx.fillStyle = A(txt, .09); ctx.fillRect(x + ip, sy, w - 2 * ip, 1.5);
      let lx = x + ip;
      const half = (w - 2 * ip) * (comp && chan ? .46 : .96);
      if (comp) {
        if (im.comp[gi]) { const s = Math.min(sh - 16, 40); contain(ctx, im.comp[gi], lx, sy + (sh - s) / 2, s, s); lx += s + 10; }
        f = fitLine(ctx, g.comp.toUpperCase(), st.fontBody, fs, 14, 600, (chan ? (w - 2 * ip) * .36 : half) - (lx - x - ip));
        ctx.textAlign = 'left'; ctx.fillStyle = A(txt, .7); ctx.fillText(f.text, lx, by);
      }
      if (chan) {
        chRow(e, g, gi, comp ? x + w - ip : x + w / 2, sy + sh / 2 + 1, sh - 22, comp ? (w - 2 * ip) * .6 : half, comp ? 'right' : 'center',
          skin === 'clean' ? mix(st.primary, '#000000', lum(st.primary) > .6 ? .45 : 0) : st.primary, skin === 'clean');
      }
    }
    ctx.textAlign = 'left';
  }

  function cleanEnv(e, skin) {
    return skin === 'clean' ? Object.assign({}, e, { st: Object.assign({}, e.st, { bg: mix(e.st.text, e.st.bg, .05), text: e.st.bg }) }) : e;
  }

  function gamesList(e, skin) {
    e = cleanEnv(e, skin);
    if (skin === 'gold') e = Object.assign({}, e, { P: 74 });
    const { ctx, P, st, d, fmt } = e;
    gBg(e, skin);
    const top = gHeader(e, skin, fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26;
    const bot = gFooter(e);
    const games = d.games || [];
    if (!games.length) {
      font(ctx, st.fontBody, 34, 600); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .55);
      ctx.fillText('Selecione os jogos para montar o banner', W / 2, (top + bot) / 2); ctx.textAlign = 'left';
      return;
    }
    const compact = skin === 'compact', n = Math.max(d.perPage || games.length, games.length), gap = compact ? 6 : 14;
    const ch = Math.min((bot - top - gap * (n - 1)) / n, compact ? 124 : 180);
    let y = top + Math.max(0, (bot - top - (games.length * ch + gap * (games.length - 1))) / 2);
    games.forEach((g, i) => { gameCard(e, g, i, i, P, y, W - 2 * P, ch, skin); y += ch + gap; });
  }

  function gameHero(e, mode) {
    const { ctx, H, P, st, d, im, fmt } = e, story = fmt === 'story', classic = mode === 'classic';
    if (classic) {
      // Clássico: campo dividido na diagonal, uma cor para cada lado
      ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = lin(ctx, 0, 0, 0, H, [A(st.primary, .6), A(st.primary, .14)]); poly(ctx, [[0, 0], [W * .62, 0], [W * .38, H], [0, H]]); ctx.fill();
      ctx.fillStyle = lin(ctx, 0, 0, 0, H, [A(st.secondary, .14), A(st.secondary, .6)]); poly(ctx, [[W * .62, 0], [W, 0], [W, H], [W * .38, H]]); ctx.fill();
      ctx.strokeStyle = A(st.text, .45); ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(W * .62, 0); ctx.lineTo(W * .38, H); ctx.stroke();
      ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .78)], [.26, A(st.bg, .12)], [.66, A(st.bg, .3)], [1, A(st.bg, .94)]]); ctx.fillRect(0, 0, W, H);
    } else gBg(e, 'hero');
    const top = gHeader(e, 'hero', story ? 112 : fmt === 'feed' ? 94 : 80) + 20;
    const bot = gFooter(e);
    const g = (d.games || [])[0];
    if (!g) return;
    const comp = st.showComp && g.comp, chan = st.showChannels && g.channels.length;
    const zone = bot - top;
    const fixed = (comp ? 66 + 26 : 0) + 30 + 62 + 26 + (chan ? 26 + 58 : 0);
    const ts = Math.min(story ? 200 : 150, zone * .2);
    const L = Math.max(120, Math.min(story ? 340 : 290, zone - fixed - ts));
    let y = top + Math.max(0, (zone - fixed - ts - L) / 2);

    if (comp) {
      font(ctx, st.fontBody, 30, 700);
      const t = g.comp.toUpperCase(), tw = ctx.measureText(t).width, lw = im.comp[0] ? 76 : 0;
      let x = (W - tw - lw) / 2;
      if (im.comp[0]) { contain(ctx, im.comp[0], x, y, 62, 62); x += lw; }
      ctx.fillStyle = A(st.text, .85); ctx.textAlign = 'left'; ctx.fillText(t, x, y + 43);
      y += 66 + 26;
    }
    const c1 = W * .25, c2 = W * .75;
    if (!classic) { glow(ctx, c1, y + L / 2, L * .9, A(st.primary, .22)); glow(ctx, c2, y + L / 2, L * .9, A(st.secondary, .22)); }
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = classic ? 40 : 0; ctx.shadowOffsetY = classic ? 14 : 0;
    crest(e, im.l1[0], g.t1, c1 - L / 2, y, L);
    crest(e, im.l2[0], g.t2, c2 - L / 2, y, L);
    ctx.restore();
    const vr = Math.max(46, L * .2);
    if (classic) {
      ctx.save(); font(ctx, st.fontTitle, vr * 2.1, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.text; ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 24;
      ctx.fillText('VS', W / 2, y + L / 2 + vr * .72); ctx.restore(); ctx.textAlign = 'center';
    } else {
      ctx.save(); ctx.shadowColor = A(st.primary, .6); ctx.shadowBlur = 40;
      ctx.fillStyle = lin(ctx, W / 2 - vr, 0, W / 2 + vr, 0, [st.primary, st.secondary]);
      ctx.beginPath(); ctx.arc(W / 2, y + L / 2, vr, 0, 7); ctx.fill(); ctx.restore();
      font(ctx, st.fontTitle, vr * .95, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
      ctx.fillText('VS', W / 2, y + L / 2 + vr * .33);
    }
    y += L + 30;
    ctx.fillStyle = st.text; ctx.textAlign = 'center';
    [[g.t1, c1], [g.t2, c2]].forEach(([n, cx]) => {
      const f = fitLine(ctx, n.toUpperCase(), st.fontBody, 46, 22, 800, W / 2 - P - 20);
      ctx.fillText(f.text, cx, y + 46);
    });
    y += 62 + 26;
    ctx.save(); font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = classic ? st.text : st.primary; ctx.shadowColor = classic ? 'rgba(0,0,0,.5)' : A(st.primary, .5); ctx.shadowBlur = 40;
    ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || ''), W / 2, y + ts * .82);
    ctx.restore();
    y += ts;
    if (chan) chRow(e, g, 0, W / 2, y + 26 + 38, 76, W - 2 * P, 'center', st.text);
    ctx.textAlign = 'left';
  }

  function gEmpty(e, top, bot) {
    const { ctx, st } = e;
    font(ctx, st.fontBody, 34, 600); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .55);
    ctx.fillText('Selecione os jogos para montar o banner', W / 2, (top + bot) / 2); ctx.textAlign = 'left';
  }

  // Linha do tempo: horário à esquerda ligado por uma linha, times em duas linhas (nomes longos cabem inteiros)
  function gameTimeline(e) {
    const { ctx, P, st, d, im, fmt } = e;
    gBg(e, 'compact');
    const top = gHeader(e, 'arena', fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26, bot = gFooter(e), games = d.games || [];
    if (!games.length) return gEmpty(e, top, bot);
    const n = Math.max(d.perPage || games.length, games.length), gap = 14;
    const ch = Math.min((bot - top - gap * (n - 1)) / n, 196);
    const y0 = top + Math.max(0, (bot - top - (games.length * ch + gap * (games.length - 1))) / 2);
    const lx = P + 74, x0 = P + 164, cw = W - P - x0;
    if (games.length > 1) {
      ctx.strokeStyle = A(st.text, .2); ctx.lineWidth = 4; ctx.beginPath();
      ctx.moveTo(lx, y0 + ch / 2); ctx.lineTo(lx, y0 + (games.length - 1) * (ch + gap) + ch / 2); ctx.stroke();
    }
    games.forEach((g, i) => {
      const y = y0 + i * (ch + gap), cy = y + ch / 2;
      // horário
      const ph = Math.min(58, ch * .42), pw = 148;
      ctx.fillStyle = lin(ctx, lx - pw / 2, 0, lx + pw / 2, 0, [st.primary, st.secondary]);
      rr(ctx, lx - pw / 2, cy - ph / 2, pw, ph, ph / 2); ctx.fill();
      font(ctx, st.fontTitle, ph * .66, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
      ctx.fillText(g.time || '--:--', lx, cy + ph * .23);
      // cartão
      rr(ctx, x0, y, cw, ch, 20); ctx.fillStyle = A(st.text, .085); ctx.fill();
      ctx.strokeStyle = A(st.text, .1); ctx.lineWidth = 1.5; ctx.stroke();
      const comp = st.showComp && g.comp, chan = st.showChannels && g.channels.length;
      const sh = (comp || chan) && ch >= 120 ? (chan ? Math.min(58, ch * .3) : 36) : 0, rh = (ch - sh - 12) / 2, ls = Math.min(rh - 8, 66), sc = hasScore(st, g);
      [[g.t1, im.l1[i], g.s1], [g.t2, im.l2[i], g.s2]].forEach((t, k) => {
        const ry = y + 6 + k * rh, my = ry + rh / 2;
        crest(e, t[1], t[0], x0 + 20, my - ls / 2, ls);
        const f = fitLine(ctx, t[0].toUpperCase(), st.fontBody, Math.min(34, rh * .5), 18, 800, cw - ls - 60 - (sc ? 70 : 0));
        ctx.textAlign = 'left'; ctx.fillStyle = st.text; ctx.fillText(f.text, x0 + 20 + ls + 18, my + f.size * .36);
        if (sc) { font(ctx, st.fontTitle, Math.min(44, rh * .62), 800); ctx.textAlign = 'right'; ctx.fillStyle = st.primary; ctx.fillText(t[2], x0 + cw - 24, my + Math.min(44, rh * .62) * .35); }
      });
      if (sh) {
        const sy = y + ch - sh, by = sy + sh / 2 + 7;
        ctx.fillStyle = A(st.text, .09); ctx.fillRect(x0 + 20, sy, cw - 40, 1.5);
        const half = (cw - 40) * (comp && chan ? .47 : .96);
        if (comp) { const f = fitLine(ctx, g.comp.toUpperCase(), st.fontBody, 19, 13, 600, half); ctx.textAlign = 'left'; ctx.fillStyle = A(st.text, .7); ctx.fillText(f.text, x0 + 20, by); }
        if (chan) chRow(e, g, i, x0 + cw - 20, sy + sh / 2 + 1, sh - 16, comp ? (cw - 40) * .58 : half, 'right', st.primary);
      }
    });
    ctx.textAlign = 'left';
  }

  // Grade: dois jogos por linha, escudos grandes
  function gameGrid(e) {
    const { ctx, P, st, d, im, fmt } = e;
    gBg(e, 'arena');
    const top = gHeader(e, 'arena', fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26, bot = gFooter(e), games = d.games || [];
    if (!games.length) return gEmpty(e, top, bot);
    const n = Math.max(d.perPage || games.length, games.length), rows = Math.ceil(n / 2), used = Math.ceil(games.length / 2), gap = 14;
    const cw = (W - 2 * P - gap) / 2, ch = Math.min((bot - top - gap * (rows - 1)) / rows, 270);
    const y0 = top + Math.max(0, (bot - top - (used * ch + gap * (used - 1))) / 2);
    games.forEach((g, i) => {
      // jogo sozinho na última linha fica centralizado
      const alone = i === games.length - 1 && games.length % 2 === 1;
      const x = alone ? (W - cw) / 2 : P + (i % 2) * (cw + gap), y = y0 + Math.floor(i / 2) * (ch + gap);
      rr(ctx, x, y, cw, ch, 20); ctx.fillStyle = A(st.text, .085); ctx.fill();
      ctx.strokeStyle = A(st.text, .11); ctx.lineWidth = 1.5; ctx.stroke();
      ctx.fillStyle = lin(ctx, x, 0, x + cw, 0, [st.primary, st.secondary]); rr(ctx, x + cw / 2 - 50, y, 100, 6, 3); ctx.fill();
      const ts = Math.min(50, ch * .23);
      font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.primary;
      ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || ''), x + cw / 2, y + 16 + ts * .82);
      const ls = Math.min(ch * (st.showChannels && g.channels.length ? .28 : .34), 96), ly = y + 22 + ts;
      crest(e, im.l1[i], g.t1, x + cw * .25 - ls / 2, ly, ls);
      crest(e, im.l2[i], g.t2, x + cw * .75 - ls / 2, ly, ls);
      font(ctx, st.fontBody, 24, 800); ctx.fillStyle = A(st.text, .45); ctx.textAlign = 'center'; ctx.fillText('X', x + cw / 2, ly + ls / 2 + 9);
      const ns = Math.min(24, ch * .105);
      ctx.fillStyle = st.text;
      [[g.t1, .25], [g.t2, .75]].forEach(t => { const f = fitLine(ctx, t[0].toUpperCase(), st.fontBody, ns, 13, 800, cw / 2 - 18); ctx.textAlign = 'center'; ctx.fillText(f.text, x + cw * t[1], ly + ls + ns + 8); });
      if (ch >= 170) {
        if (st.showChannels && g.channels.length) { const hh = Math.min(46, ch * .17); chRow(e, g, i, x + cw / 2, y + ch - 12 - hh / 2, hh, cw - 28, 'center', st.primary); }
        else if (st.showComp && g.comp) { const f = fitLine(ctx, g.comp.toUpperCase(), st.fontBody, 17, 12, 600, cw - 28); ctx.fillStyle = A(st.text, .65); ctx.textAlign = 'center'; ctx.fillText(f.text, x + cw / 2, y + ch - 14); }
      }
    });
    ctx.textAlign = 'left';
  }

  // Moldura comum das listas: cabeçalho, rodapé e linhas de mesma altura centralizadas
  function gList(e, bg, maxH, gap, row) {
    const { P, d, fmt } = e;
    gBg(e, bg);
    const top = gHeader(e, 'arena', fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26, bot = gFooter(e), games = d.games || [];
    if (!games.length) return gEmpty(e, top, bot);
    const n = Math.max(d.perPage || games.length, games.length), ch = Math.min((bot - top - gap * (n - 1)) / n, maxH);
    let y = top + Math.max(0, (bot - top - (games.length * ch + gap * (games.length - 1))) / 2);
    games.forEach((g, i) => { row(g, i, P, y, W - 2 * P, ch); y += ch + gap; });
    e.ctx.textAlign = 'left';
  }
  // Times numa linha: escudo, nome, "x", nome, escudo
  function teamsRow(e, g, i, x, cy, w, ls, size) {
    const { ctx, st, im } = e, nw = (w - 2 * ls - 28 - 44) / 2;
    crest(e, im.l1[i], g.t1, x, cy - ls / 2, ls);
    crest(e, im.l2[i], g.t2, x + w - ls, cy - ls / 2, ls);
    ctx.fillStyle = st.text;
    let f = fitLine(ctx, g.t1.toUpperCase(), st.fontBody, size, 17, 800, nw);
    ctx.textAlign = 'left'; ctx.fillText(f.text, x + ls + 14, cy + f.size * .36);
    f = fitLine(ctx, g.t2.toUpperCase(), st.fontBody, size, 17, 800, nw);
    ctx.textAlign = 'right'; ctx.fillText(f.text, x + w - ls - 14, cy + f.size * .36);
    font(ctx, st.fontBody, size * .6, 800); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .4); ctx.fillText('X', x + w / 2, cy + size * .22);
  }
  function compLabel(e, g, cx, y, maxW, align) {
    const { ctx, st } = e, f = fitLine(ctx, g.comp.toUpperCase(), st.fontBody, 17, 12, 700, maxW);
    ctx.textAlign = align; ctx.fillStyle = A(st.text, .55); ctx.fillText(f.text, cx, y);
  }

  // Transmissão: bloco de horário colorido à esquerda, confronto no meio, canais à direita
  const gameBroadcast = e => gList(e, 'compact', 176, 14, (g, i, x, y, w, h) => {
    const { ctx, st, im } = e, tb = Math.min(160, h * 1.02), chan = st.showChannels && g.channels.length, comp = st.showComp && g.comp, sc = hasScore(st, g);
    const rw = chan || comp ? Math.min(250, w * .27) : 0;
    ctx.save(); rr(ctx, x, y, w, h, 18); ctx.fillStyle = A(st.text, .075); ctx.fill(); ctx.clip();
    ctx.fillStyle = lin(ctx, x, y, x + tb, y + h, [st.primary, st.secondary]); ctx.fillRect(x, y, tb, h);
    ctx.restore();
    const ts = Math.min(62, h * .42);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
    ctx.fillText(g.time || '--:--', x + tb / 2, y + h / 2 + ts * .34);
    // times empilhados: nomes longos cabem inteiros e ficam grandes
    const mx = x + tb + 26, mw = w - tb - rw - 52, rh = (h - 20) / 2, ls = Math.min(rh - 10, 60), ns = Math.min(36, rh * .52);
    [[g.t1, im.l1[i], g.s1], [g.t2, im.l2[i], g.s2]].forEach((t, k) => {
      const my = y + 10 + k * rh + rh / 2;
      crest(e, t[1], t[0], mx, my - ls / 2, ls);
      const f = fitLine(ctx, t[0].toUpperCase(), st.fontBody, ns, 17, 800, mw - ls - 18 - (sc ? 54 : 0));
      ctx.textAlign = 'left'; ctx.fillStyle = st.text; ctx.fillText(f.text, mx + ls + 18, my + f.size * .36);
      if (sc) { font(ctx, st.fontTitle, ns * 1.2, 800); ctx.textAlign = 'right'; ctx.fillStyle = st.primary; ctx.fillText(t[2], mx + mw, my + ns * .42); }
    });
    if (rw) {
      ctx.fillStyle = A(st.text, .12); ctx.fillRect(x + w - rw, y + 18, 1.5, h - 36);
      const cx = x + w - rw / 2, both = chan && comp && h >= 120;
      if (comp && (both || !chan)) compLabel(e, g, cx, y + (both ? h * .3 : h / 2 + 6), rw - 28, 'center');
      if (chan) chRow(e, g, i, cx, y + (both ? h * .62 : h / 2), Math.min(60, h * .36), rw - 28, 'center', st.text);
    }
  });

  // Ingresso: cada jogo como um bilhete, com o canhoto trazendo horário e canal
  const gameTicket = e => gList(e, 'arena', 196, 16, (g, i, x, y, w, h) => {
    const { ctx, st } = e, sw = Math.min(250, w * .27), mw = w - sw, chan = st.showChannels && g.channels.length, comp = st.showComp && g.comp && h >= 126;
    ctx.save(); rr(ctx, x, y, w, h, 22); ctx.fillStyle = A(st.text, .085); ctx.fill(); ctx.clip();
    ctx.fillStyle = A(st.primary, .16); ctx.fillRect(x + mw, y, sw, h);
    ctx.restore();
    ctx.strokeStyle = A(st.text, .14); ctx.lineWidth = 1.5; rr(ctx, x, y, w, h, 22); ctx.stroke();
    ctx.save(); ctx.setLineDash([9, 9]); ctx.strokeStyle = A(st.text, .32); ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(x + mw, y + 18); ctx.lineTo(x + mw, y + h - 18); ctx.stroke(); ctx.restore();
    ctx.fillStyle = st.bg; ctx.beginPath(); ctx.arc(x + mw, y, 14, 0, 7); ctx.arc(x + mw, y + h, 14, 0, 7); ctx.fill();
    if (comp) compLabel(e, g, x + 30, y + 34, mw - 60, 'left');
    teamsRow(e, g, i, x + 26, y + h / 2 + (comp ? 14 : 0), mw - 52, Math.min(h * .46, 82), Math.min(32, h * .2));
    const ts = Math.min(68, h * .38), sc = hasScore(st, g);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.primary;
    ctx.fillText(sc ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + mw + sw / 2, y + (chan ? h * .46 : h / 2 + ts * .34));
    if (chan) chRow(e, g, i, x + mw + sw / 2, y + h * .74, Math.min(50, h * .28), sw - 30, 'center', st.text);
  });

  // Cartões grandes: poucos jogos por banner, escudos e canais bem visíveis
  function bigRow(e, g, i, x, y, w, h, tone) {
    const { ctx, st, im } = e, chan = st.showChannels && g.channels.length, comp = st.showComp && g.comp, sc = hasScore(st, g);
    const sh = chan ? Math.min(92, h * .28) : 0, mh = h - sh;
    ctx.save(); rr(ctx, x, y, w, h, 26); ctx.fillStyle = A(st.text, .085); ctx.fill();
    if (tone) {
      ctx.clip();
      ctx.fillStyle = lin(ctx, x, 0, x + w * .55, 0, [A(st.primary, .5), A(st.primary, 0)]); poly(ctx, [[x, y], [x + w * .58, y], [x + w * .42, y + h], [x, y + h]]); ctx.fill();
      ctx.fillStyle = lin(ctx, x + w, 0, x + w * .45, 0, [A(st.secondary, .5), A(st.secondary, 0)]); poly(ctx, [[x + w * .58, y], [x + w, y], [x + w, y + h], [x + w * .42, y + h]]); ctx.fill();
    }
    ctx.restore();
    ctx.strokeStyle = A(st.text, .12); ctx.lineWidth = 1.5; rr(ctx, x, y, w, h, 26); ctx.stroke();
    const ls = Math.min(mh * .56, 150), cy = y + mh * .44, c1 = x + w * .19, c2 = x + w * .81;
    crest(e, im.l1[i], g.t1, c1 - ls / 2, cy - ls / 2, ls);
    crest(e, im.l2[i], g.t2, c2 - ls / 2, cy - ls / 2, ls);
    const ns = Math.min(34, mh * .13);
    ctx.fillStyle = st.text;
    [[g.t1, c1], [g.t2, c2]].forEach(t => { const f = fitLine(ctx, t[0].toUpperCase(), st.fontBody, ns, 16, 800, w * .34); ctx.textAlign = 'center'; ctx.fillText(f.text, t[1], cy + ls / 2 + ns + 12); });
    // centro: horário em cápsula, campeonato embaixo
    const ph = Math.min(84, mh * .34), pw = ph * 2.6, py = cy - ph / 2 - (comp ? 14 : 0);
    ctx.fillStyle = lin(ctx, x + w / 2 - pw / 2, 0, x + w / 2 + pw / 2, 0, [st.primary, st.secondary]); rr(ctx, x + w / 2 - pw / 2, py, pw, ph, ph / 2); ctx.fill();
    font(ctx, st.fontTitle, ph * .74, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
    ctx.fillText(sc ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + w / 2, py + ph * .76);
    if (comp) compLabel(e, g, x + w / 2, py + ph + 34, w * .3, 'center');
    if (sh) {
      ctx.fillStyle = A(st.text, .1); ctx.fillRect(x + 30, y + mh, w - 60, 1.5);
      chRow(e, g, i, x + w / 2, y + mh + sh / 2, sh - 30, w - 80, 'center', st.text);
    }
  }
  const gameBig = e => gList(e, 'arena', 340, 18, (g, i, x, y, w, h) => bigRow(e, g, i, x, y, w, h));

  // Placar: barra de TV esportiva com o horário num bloco inclinado no centro
  const gameScore = e => gList(e, 'compact', 190, 14, (g, i, x, y, w, h) => {
    const { ctx, st, im } = e, chan = st.showChannels && g.channels.length, comp = st.showComp && g.comp, sc = hasScore(st, g);
    const sh = comp || chan ? Math.min(chan ? 74 : 44, h * (chan ? .4 : .28)) : 0, mh = h - sh, cy = y + mh / 2, cw = Math.min(230, w * .24), sk = 22;
    ctx.save(); rr(ctx, x, y, w, h, 16); ctx.fillStyle = A(st.text, .05); ctx.fill(); ctx.clip();
    ctx.fillStyle = A(st.text, .1); ctx.fillRect(x, y, w, mh);
    ctx.fillStyle = lin(ctx, x + w / 2 - cw / 2, 0, x + w / 2 + cw / 2, 0, [st.primary, st.secondary]);
    poly(ctx, [[x + w / 2 - cw / 2 + sk, y], [x + w / 2 + cw / 2 + sk, y], [x + w / 2 + cw / 2 - sk, y + mh], [x + w / 2 - cw / 2 - sk, y + mh]]); ctx.fill();
    ctx.restore();
    const ts = Math.min(70, mh * .6);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
    ctx.fillText(sc ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + w / 2, cy + ts * .35);
    const ls = Math.min(mh - 24, 96), nw = (w - cw) / 2 - sk - ls - 48, ns = Math.min(36, mh * .34);
    crest(e, im.l1[i], g.t1, x + 18, cy - ls / 2, ls);
    crest(e, im.l2[i], g.t2, x + w - 18 - ls, cy - ls / 2, ls);
    ctx.fillStyle = st.text;
    let f = fitLine(ctx, g.t1.toUpperCase(), st.fontBody, ns, 17, 800, nw);
    ctx.textAlign = 'left'; ctx.fillText(f.text, x + 18 + ls + 16, cy + f.size * .36);
    f = fitLine(ctx, g.t2.toUpperCase(), st.fontBody, ns, 17, 800, nw);
    ctx.textAlign = 'right'; ctx.fillText(f.text, x + w - 18 - ls - 16, cy + f.size * .36);
    if (sh) {
      if (comp) compLabel(e, g, x + 22, y + mh + sh / 2 + 6, chan ? w * .36 : w - 44, 'left');
      if (chan) chRow(e, g, i, comp ? x + w - 22 : x + w / 2, y + mh + sh / 2, sh - 22, comp ? w * .56 : w - 44, comp ? 'right' : 'center', st.text);
    }
  });

  // Agenda: jogos agrupados por campeonato, cada grupo com seu cabeçalho
  function gameAgenda(e, byTime) {
    const { ctx, P, st, d, im, fmt } = e, w = W - 2 * P;
    gBg(e, 'compact');
    const top = gHeader(e, 'arena', fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26, bot = gFooter(e), games = d.games || [];
    if (!games.length) return gEmpty(e, top, bot);
    const groups = [];
    games.forEach((g, i) => { const k = byTime ? (g.time || '--:--') : (g.comp || 'Jogos'); let gr = groups.find(x => x.k === k); if (!gr) groups.push(gr = { k, i, rows: [] }); gr.rows.push([g, i]); });
    const n = Math.max(d.perPage || games.length, games.length), G = groups.length, hdr = 62, gap = 10, gg = 20;
    const rh = Math.min((bot - top - G * hdr - (G - 1) * gg - n * gap) / n, 128);
    const total = G * hdr + (G - 1) * gg + games.length * (rh + gap);
    let y = top + Math.max(0, (bot - top - total) / 2);
    groups.forEach(gr => {
      let lx = P;
      if (!byTime && im.comp[gr.i]) { contain(ctx, im.comp[gr.i], P, y + 4, 44, 44); lx += 56; }
      const f = fitLine(ctx, gr.k.toUpperCase(), st.fontTitle, byTime ? 52 : 38, 22, 800, w - 80);
      ctx.textAlign = 'left'; ctx.fillStyle = st.primary; ctx.fillText(f.text, lx, y + 40);
      const tw = ctx.measureText(f.text).width;
      ctx.fillStyle = A(st.text, .14); ctx.fillRect(lx + tw + 18, y + 26, Math.max(0, P + w - lx - tw - 18), 1.5);
      y += hdr;
      gr.rows.forEach(([g, i]) => {
        const chan = st.showChannels && g.channels.length, rw = chan ? Math.min(250, w * .27) : 0, tb = byTime ? 0 : 132;
        rr(ctx, P, y, w, rh, 14); ctx.fillStyle = A(st.text, .075); ctx.fill();
        const ts = Math.min(50, rh * .46);
        if (tb) {
          font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.primary;
          ctx.fillText(hasScore(st, g) ? g.s1 + '-' + g.s2 : (g.time || '--:--'), P + tb / 2, y + rh / 2 + ts * .35);
          ctx.fillStyle = A(st.text, .12); ctx.fillRect(P + tb, y + 14, 1.5, rh - 28);
        }
        teamsRow(e, g, i, P + tb + 20, y + rh / 2, w - tb - rw - 40, Math.min(rh - 26, 72), Math.min(30, rh * .27));
        if (chan) chRow(e, g, i, P + w - 20, y + rh / 2, Math.min(52, rh * .44), rw - 24, 'right', st.text);
        y += rh + gap;
      });
      y += gg;
    });
    ctx.textAlign = 'left';
  }

  /* ---------- Coleção 2: dez composições extras de jogos ---------- */

  // Linha padrão: escudo, nome, horário no centro, nome, escudo + faixa com campeonato e canais
  function rowStd(e, g, i, x, y, w, h, onLight, timeColor) {
    const { ctx, st, im } = e, comp = st.showComp && g.comp, chan = st.showChannels && g.channels.length;
    const sh = comp || chan ? Math.min(chan ? 76 : 44, h * (chan ? .42 : .3)) : 0, mh = h - sh, cy = y + mh / 2 + (sh ? 3 : 0), ip = 22;
    const ls = Math.min(mh - 22, 100), cw = 200, nw = (w - cw) / 2 - ip - ls - 18, ns = Math.min(38, mh * .36), ts = Math.min(66, mh * .58);
    crest(e, im.l1[i], g.t1, x + ip, cy - ls / 2, ls);
    crest(e, im.l2[i], g.t2, x + w - ip - ls, cy - ls / 2, ls);
    ctx.fillStyle = st.text;
    let f = fitLine(ctx, g.t1.toUpperCase(), st.fontBody, ns, 18, 800, nw);
    ctx.textAlign = 'left'; ctx.fillText(f.text, x + ip + ls + 18, cy + f.size * .36);
    f = fitLine(ctx, g.t2.toUpperCase(), st.fontBody, ns, 18, 800, nw);
    ctx.textAlign = 'right'; ctx.fillText(f.text, x + w - ip - ls - 18, cy + f.size * .36);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = timeColor || st.primary;
    ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + w / 2, cy + ts * .35);
    if (sh) {
      const sy = y + mh;
      ctx.fillStyle = A(st.text, .1); ctx.fillRect(x + ip, sy, w - 2 * ip, 1.5);
      if (comp) compLabel(e, g, x + ip, sy + sh / 2 + 6, chan ? (w - 2 * ip) * .36 : w - 2 * ip, 'left');
      if (chan) chRow(e, g, i, comp ? x + w - ip : x + w / 2, sy + sh / 2 + 1, sh - 22, comp ? (w - 2 * ip) * .6 : w - 2 * ip, comp ? 'right' : 'center', st.text, onLight);
    }
  }

  // Cápsulas: cada jogo numa cápsula, escudos em círculos nas pontas
  const gamePill = e => gList(e, 'arena', 170, 16, (g, i, x, y, w, h) => {
    const { ctx, st, im } = e, r = h / 2, chan = st.showChannels && g.channels.length, cy = y + r;
    rr(ctx, x, y, w, h, r); ctx.fillStyle = A(st.text, .085); ctx.fill(); ctx.strokeStyle = A(st.text, .14); ctx.lineWidth = 1.5; ctx.stroke();
    [[x + r, im.l1[i], g.t1], [x + w - r, im.l2[i], g.t2]].forEach(t => {
      ctx.fillStyle = A(st.text, .1); ctx.beginPath(); ctx.arc(t[0], cy, r - 10, 0, 7); ctx.fill();
      crest(e, t[1], t[2], t[0] - (r - 10) * .7, cy - (r - 10) * .7, (r - 10) * 1.4);
    });
    const cw = 220, nw = (w - cw) / 2 - 2 * r - 14, ns = Math.min(34, h * .22), ts = Math.min(62, h * .4);
    ctx.fillStyle = st.text;
    let f = fitLine(ctx, g.t1.toUpperCase(), st.fontBody, ns, 17, 800, nw);
    ctx.textAlign = 'left'; ctx.fillText(f.text, x + 2 * r + 8, cy + f.size * .36);
    f = fitLine(ctx, g.t2.toUpperCase(), st.fontBody, ns, 17, 800, nw);
    ctx.textAlign = 'right'; ctx.fillText(f.text, x + w - 2 * r - 8, cy + f.size * .36);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'center'; ctx.fillStyle = st.primary;
    ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + w / 2, cy + ts * .35 - (chan ? h * .14 : 0));
    if (chan) chRow(e, g, i, x + w / 2, cy + h * .27, Math.min(40, h * .26), cw, 'center', st.text);
  });

  // Minimal: sem cartões — horário em destaque, linha fina entre os jogos
  const gameMinimal = e => gList(e, 'compact', 150, 0, (g, i, x, y, w, h) => {
    const { ctx, st } = e, chan = st.showChannels && g.channels.length, tb = 170, rw = chan ? Math.min(260, w * .28) : 0, ts = Math.min(72, h * .5);
    ctx.fillStyle = A(st.text, .14); ctx.fillRect(x, y + h - 1.5, w, 1.5);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'left'; ctx.fillStyle = st.primary;
    ctx.fillText(hasScore(st, g) ? g.s1 + '-' + g.s2 : (g.time || '--:--'), x, y + h / 2 + ts * .35);
    teamsRow(e, g, i, x + tb, y + h / 2, w - tb - rw - 20, Math.min(h * .5, 72), Math.min(32, h * .24));
    if (chan) chRow(e, g, i, x + w, y + h / 2, Math.min(52, h * .36), rw - 10, 'right', st.text);
  });

  // Cartões claros: cartões brancos sobre fundo escuro, contraste máximo
  const gameLight = e => gList(e, 'compact', 184, 16, (g, i, x, y, w, h) => {
    const { ctx, st } = e, e2 = Object.assign({}, e, { st: Object.assign({}, st, { text: st.bg, bg: st.text }) });
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 26; ctx.shadowOffsetY = 10;
    ctx.fillStyle = st.text; rr(ctx, x, y, w, h, 22); ctx.fill(); ctx.restore();
    ctx.fillStyle = lin(ctx, 0, y, 0, y + h, [st.primary, st.secondary]); rr(ctx, x, y + 18, 8, h - 36, 4); ctx.fill();
    rowStd(e2, g, i, x, y, w, h, true, lum(st.primary) > .7 ? st.bg : st.primary);
  });

  // Listrado: faixas de ponta a ponta alternando a cor da marca
  const gameStripes = e => gList(e, 'compact', 176, 0, (g, i, x, y, w, h) => {
    const { ctx, st } = e;
    ctx.fillStyle = i % 2 ? A(st.text, .05) : A(st.primary, .16); ctx.fillRect(0, y, W, h);
    ctx.fillStyle = i % 2 ? A(st.text, .3) : st.primary; ctx.fillRect(0, y, 10, h);
    rowStd(e, g, i, x, y, w, h, false, i % 2 ? st.primary : st.text);
  });

  // Texto central: sem escudos, o confronto escrito grande no meio
  const gameText = e => gList(e, 'arena', 210, 16, (g, i, x, y, w, h) => {
    const { ctx, st } = e, chan = st.showChannels && g.channels.length, ch = chan ? Math.min(48, h * .24) : 0;
    rr(ctx, x, y, w, h, 22); ctx.fillStyle = A(st.text, .075); ctx.fill();
    const ph = Math.min(46, h * .22), pw = ph * 3;
    ctx.fillStyle = lin(ctx, x + w / 2 - pw / 2, 0, x + w / 2 + pw / 2, 0, [st.primary, st.secondary]); rr(ctx, x + w / 2 - pw / 2, y - 2, pw, ph, 12); ctx.fill();
    font(ctx, st.fontTitle, ph * .78, 800); ctx.textAlign = 'center'; ctx.fillStyle = on(st.primary);
    ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), x + w / 2, y - 2 + ph * .78);
    const mid = y + ph + (h - ph - ch - (chan ? 16 : 0)) / 2;
    const f = fitLine(ctx, (g.t1 + '  x  ' + g.t2).toUpperCase(), st.fontTitle, Math.min(72, h * .36), 24, 800, w - 50);
    ctx.fillStyle = st.text; ctx.fillText(f.text, x + w / 2, mid + f.size * .36);
    if (chan) chRow(e, g, i, x + w / 2, y + h - 14 - ch / 2, ch, w - 60, 'center', st.text);
  });

  // Marca d'água: os escudos gigantes e apagados atrás de cada cartão
  const gameWater = e => gList(e, 'arena', 184, 16, (g, i, x, y, w, h) => {
    const { ctx, st, im } = e;
    ctx.save(); rr(ctx, x, y, w, h, 22); ctx.fillStyle = A(st.text, .075); ctx.fill(); ctx.clip();
    ctx.globalAlpha = .12;
    if (im.l1[i]) contain(ctx, im.l1[i], x - h * .35, y - h * .45, h * 1.9, h * 1.9);
    if (im.l2[i]) contain(ctx, im.l2[i], x + w - h * 1.55, y - h * .45, h * 1.9, h * 1.9);
    ctx.restore();
    ctx.strokeStyle = A(st.text, .12); ctx.lineWidth = 1.5; rr(ctx, x, y, w, h, 22); ctx.stroke();
    rowStd(e, g, i, x, y, w, h);
  });

  // Destaque + lista: o primeiro jogo em cartão grande, os demais em linhas
  function gameFeature(e) {
    const { P, d, fmt } = e, w = W - 2 * P;
    gBg(e, 'arena');
    const top = gHeader(e, 'arena', fmt === 'story' ? 128 : fmt === 'feed' ? 108 : 94) + 26, bot = gFooter(e), games = d.games || [];
    if (!games.length) return gEmpty(e, top, bot);
    const n = Math.max(d.perPage || games.length, games.length), rest = n - 1, gap = 14, zone = bot - top;
    const fh = Math.min(zone * (rest ? .36 : 1), 420), rh = rest ? Math.min((zone - fh - gap * rest) / rest, 170) : 0;
    let y = top + Math.max(0, (zone - (fh + (games.length - 1) * (rh + gap))) / 2);
    bigRow(e, games[0], 0, P, y, w, fh, true);
    y += fh + gap;
    games.slice(1).forEach((g, k) => {
      const { ctx, st } = e;
      rr(ctx, P, y, w, rh, 20); ctx.fillStyle = A(st.text, .075); ctx.fill();
      rowStd(e, g, k + 1, P, y, w, rh);
      y += rh + gap;
    });
    e.ctx.textAlign = 'left';
  }

  // Duplo clássico: dois jogos grandes por banner, cada um em dois tons
  const gameDuo = e => gList(e, 'arena', 640, 22, (g, i, x, y, w, h) => bigRow(e, g, i, x, y, w, h, true));

  // Guia de TV: o canal vem primeiro, grande, e ao lado o que está passando
  const gameGuide = e => gList(e, 'compact', 176, 14, (g, i, x, y, w, h) => {
    const { ctx, st } = e, chan = st.showChannels && g.channels.length, cb = Math.min(270, w * .29), comp = st.showComp && g.comp && h >= 120;
    ctx.save(); rr(ctx, x, y, w, h, 18); ctx.fillStyle = A(st.text, .075); ctx.fill(); ctx.clip();
    ctx.fillStyle = A(st.text, .1); ctx.fillRect(x, y, cb, h);
    ctx.fillStyle = lin(ctx, 0, y, 0, y + h, [st.primary, st.secondary]); ctx.fillRect(x + cb - 5, y, 5, h);
    ctx.restore();
    if (chan) chRow(e, Object.assign({}, g, { ch: (g.ch || []).slice(0, 1), channels: g.channels.slice(0, 1) }), i, x + cb / 2, y + h / 2 - (g.channels.length > 1 ? 10 : 0), Math.min(74, h * .46), cb - 44, 'center', st.text);
    else { font(ctx, st.fontBody, 20, 700); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .5); ctx.fillText('A CONFIRMAR', x + cb / 2, y + h / 2 + 7); }
    if (chan && g.channels.length > 1) { font(ctx, st.fontBody, 18, 700); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .6); ctx.fillText('+' + (g.channels.length - 1) + ' ' + (g.channels.length > 2 ? 'canais' : 'canal'), x + cb / 2, y + h - 20); }
    const mx = x + cb + 22, mw = w - cb - 44, ts = Math.min(46, h * .3);
    font(ctx, st.fontTitle, ts, 800); ctx.textAlign = 'left'; ctx.fillStyle = st.primary;
    ctx.fillText(hasScore(st, g) ? g.s1 + ' - ' + g.s2 : (g.time || '--:--'), mx, y + 18 + ts * .8);
    if (comp) compLabel(e, g, mx + mw, y + 18 + ts * .62, mw - 130, 'right');
    teamsRow(e, g, i, mx, y + h * .68, mw, Math.min(h * .36, 60), Math.min(30, h * .2));
  });

  const GAMES = {
    arena: e => gamesList(e, 'arena'), neon: e => gamesList(e, 'neon'), clean: e => gamesList(e, 'clean'),
    gold: e => gamesList(e, 'gold'), compact: e => gamesList(e, 'compact'), hero: gameHero,
    duel: e => gamesList(e, 'duel'), timeline: gameTimeline, grid: gameGrid,
    broadcast: gameBroadcast, ticket: gameTicket, classic: e => gameHero(e, 'classic'),
    bigcards: gameBig, scoreboard: gameScore, agenda: e => gameAgenda(e),
    pill: gamePill, minimal: gameMinimal, lightcards: gameLight, stripes: gameStripes, textcenter: gameText,
    watermark: gameWater, bytime: e => gameAgenda(e, true), feature: gameFeature, duo: gameDuo, guide: gameGuide,
  };

  GG.perPage = function (layout, format, st) {
    if (layout === 'hero' || layout === 'classic') return 1;
    const n = parseInt(st.perPage, 10) || 0;
    if (n > 0) return n;
    if (layout === 'bigcards') return { story: 4, feed: 3, square: 2 }[format];
    if (layout === 'duo') return { story: 2, feed: 2, square: 1 }[format];
    if (layout === 'feature') return { story: 6, feed: 4, square: 3 }[format];
    if (layout === 'textcenter') return { story: 6, feed: 4, square: 3 }[format];
    if (layout === 'agenda' || layout === 'bytime' || layout === 'minimal') return { story: 9, feed: 6, square: 4 }[format];
    return (layout === 'compact' || layout === 'grid' ? { story: 10, feed: 6, square: 4 } : { story: 7, feed: 4, square: 3 })[format];
  };

  /* =====================  MARKETING (planos, catálogo, dispositivos)  ===================== */

  // Mosaico de capas (ou de logos de canais) recortado em uma área; devolve false se não há imagens
  function mosaic(e, o) {
    const { ctx, im, d } = e, list = (im.covers || []).filter(Boolean);
    if (!list.length) return false;
    const icons = d.coverType === 'icon', pw = (o.cell || 216) * (icons ? .8 : 1), ph = icons ? pw : pw * 1.5, gap = 14;
    ctx.save();
    ctx.beginPath(); ctx.rect(o.x, o.y, o.w, o.h); ctx.clip();
    ctx.translate(o.x + o.w / 2, o.y + o.h / 2);
    if (o.tilt) ctx.rotate(o.tilt);
    const tw = o.tilt ? Math.hypot(o.w, o.h) : o.w, th = o.tilt ? Math.hypot(o.w, o.h) : o.h;
    const cols = Math.ceil(tw / (pw + gap)) + 1, rows = Math.ceil(th / (ph + gap)) + 2;
    const x0 = -(cols * (pw + gap) - gap) / 2, y0 = -(rows * (ph + gap) - gap) / 2;
    for (let c = 0; c < cols; c++) for (let r = 0; r < rows; r++) {
      const x = x0 + c * (pw + gap), y = y0 + r * (ph + gap) + (o.tilt && c % 2 ? (ph + gap) / 2 : 0), img = list[(c * rows + r) % list.length];
      ctx.save(); rr(ctx, x, y, pw, ph, icons ? pw * .2 : 14); ctx.clip();
      cover(ctx, img, x, y, pw, ph);
      ctx.restore();
    }
    ctx.restore();
    return true;
  }
  /* Sistema visual do Marketing: uma cor de destaque, título branco, textos de apoio em cinza,
     capas sempre escurecidas e bastante espaço vazio. Nada de brilhos ou gradientes sobre o texto. */
  function promoBg(e, dark) {
    const { ctx, H, st } = e, ov = st.overlay / 100;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (mosaic(e, { x: 0, y: 0, w: W, h: H, tilt: -0.12, cell: 250 })) { ctx.fillStyle = A(st.bg, Math.min(.96, dark + ov * .2)); ctx.fillRect(0, 0, W, H); }
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .9)], [.28, A(st.bg, 0)], [.68, A(st.bg, 0)], [1, A(st.bg, .96)]]); ctx.fillRect(0, 0, W, H);
  }
  // Linha pequena em maiúsculas espaçadas, na cor de destaque (fica acima do título)
  function eyebrow(e, y, dry) {
    const { ctx, P, st } = e;
    if (!st.sub) return 0;
    if (!dry) {
      const t = st.sub.toUpperCase();
      let size = 26;
      font(ctx, st.fontBody, size, 800);
      while (size > 15 && spacedW(ctx, t, 5) > W - 2 * P) { size -= 1; font(ctx, st.fontBody, size, 800); }
      ctx.fillStyle = st.primary;
      spaced(ctx, t, (W - spacedW(ctx, t, 5)) / 2, y + 28, 5);
    }
    return 56;
  }
  function rule(e, y) { const { ctx, st } = e; ctx.fillStyle = st.primary; rr(ctx, W / 2 - 36, y, 72, 6, 3); ctx.fill(); return 6; }
  // Título centralizado em branco; com dry=true só mede. Devolve a altura
  function pTitle(e, text, y, w, size, maxL, dry, color) {
    const { ctx, st } = e, t = String(text || '').toUpperCase();
    if (!t) return 0;
    let s = size, lines;
    for (;;) {
      font(ctx, st.fontTitle, s, 800);
      const all = wrap(ctx, t, w, 99);
      if ((all.length <= maxL && all.every(l => ctx.measureText(l).width <= w)) || s <= size * .5) { lines = wrap(ctx, t, w, maxL); break; }
      s -= 6;
    }
    const lh = s * (TIGHT.has(st.fontTitle) ? .94 : 1.1);
    if (!dry) { ctx.textAlign = 'center'; ctx.fillStyle = color || st.text; lines.forEach((l, i) => ctx.fillText(l, W / 2, y + s * .84 + i * lh)); ctx.textAlign = 'left'; }
    return lines.length * lh;
  }

  const DEVICES = [['tv', 'Smart TV'], ['phone', 'Celular'], ['tablet', 'Tablet'], ['pc', 'Computador'], ['box', 'TV Box']];
  function devIcon(ctx, type, cx, cy, s) {
    ctx.beginPath();
    if (type === 'tv') { rr(ctx, cx - s * .5, cy - s * .38, s, s * .62, s * .05); ctx.stroke(); ctx.beginPath(); ctx.moveTo(cx, cy + s * .24); ctx.lineTo(cx, cy + s * .38); ctx.moveTo(cx - s * .24, cy + s * .38); ctx.lineTo(cx + s * .24, cy + s * .38); }
    else if (type === 'phone') { rr(ctx, cx - s * .22, cy - s * .42, s * .44, s * .84, s * .07); ctx.stroke(); ctx.beginPath(); ctx.moveTo(cx - s * .07, cy + s * .31); ctx.lineTo(cx + s * .07, cy + s * .31); }
    else if (type === 'tablet') { rr(ctx, cx - s * .34, cy - s * .42, s * .68, s * .84, s * .06); ctx.stroke(); ctx.beginPath(); ctx.moveTo(cx - s * .08, cy + s * .32); ctx.lineTo(cx + s * .08, cy + s * .32); }
    else if (type === 'pc') { rr(ctx, cx - s * .38, cy - s * .32, s * .76, s * .5, s * .04); ctx.stroke(); ctx.beginPath(); ctx.moveTo(cx - s * .5, cy + s * .3); ctx.lineTo(cx + s * .5, cy + s * .3); }
    else { rr(ctx, cx - s * .4, cy + s * .04, s * .8, s * .3, s * .06); ctx.stroke(); ctx.beginPath(); ctx.arc(cx, cy - s * .02, s * .16, Math.PI * 1.22, Math.PI * 1.78); ctx.stroke(); ctx.beginPath(); ctx.arc(cx, cy - s * .02, s * .32, Math.PI * 1.22, Math.PI * 1.78); }
    ctx.stroke();
  }
  // Faixa discreta de dispositivos: legenda em cinza + ícones de traço fino. dry=true só mede
  function devRow(e, y, w, s, dry, noCaption) {
    const { ctx, st } = e;
    if (!st.showDevices) return 0;
    const cap = !noCaption && st.devTitle ? 48 : 0, h = cap + s + s * .46;
    if (dry) return h;
    if (cap) {
      const t = st.devTitle.toUpperCase();
      let size = 21;
      font(ctx, st.fontBody, size, 700);
      while (size > 13 && spacedW(ctx, t, 3) > w) { size -= 1; font(ctx, st.fontBody, size, 700); }
      ctx.fillStyle = A(st.text, .62);
      spaced(ctx, t, (W - spacedW(ctx, t, 3)) / 2, y + 22, 3);
    }
    const step = Math.min(w / DEVICES.length, s * 2.1), x0 = W / 2 - step * (DEVICES.length - 1) / 2;
    ctx.save();
    ctx.strokeStyle = A(st.text, .92); ctx.lineWidth = Math.max(2.5, s * .042); ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    DEVICES.forEach((dv, i) => {
      devIcon(ctx, dv[0], x0 + i * step, y + cap + s / 2, s * .8);
      const f = fitLine(ctx, dv[1], st.fontBody, Math.max(15, s * .2), 11, 600, step - 8);
      ctx.fillStyle = A(st.text, .6); ctx.textAlign = 'center'; ctx.fillText(f.text, x0 + i * step, y + cap + s + s * .3);
    });
    ctx.restore(); ctx.textAlign = 'left';
    return h;
  }
  // Preço com "R$" e centavos menores que o valor inteiro
  function price(e, text, x, y, size, align, color, maxW) {
    const { ctx, st } = e, m = /^\s*(R\$)?\s*(\d{1,3}(?:\.\d{3})+|\d{1,6})(?:[,.](\d{1,2})(?!\d))?\s*(.*)$/.exec(String(text || ''));
    if (!m) {
      const f = fitLine(ctx, String(text || ''), st.fontTitle, size * .6, 18, 800, maxW);
      ctx.fillStyle = color; ctx.textAlign = align; ctx.fillText(f.text, x, y); ctx.textAlign = 'left';
      return;
    }
    const seg = sz => [[m[1] ? 'R$' : '', st.fontBody, sz * .3, -sz * .4, 700], [m[2], st.fontTitle, sz, 0, 800], [m[3] ? ',' + m[3].padEnd(2, '0') : '', st.fontBody, sz * .3, -sz * .4, 700], [m[4] ? ' ' + m[4] : '', st.fontBody, sz * .22, 0, 600]];
    const width = parts => parts.reduce((a, p) => { font(ctx, p[1], p[2], p[4]); return a + (p[0] ? ctx.measureText(p[0]).width + sz0 * .04 : 0); }, 0);
    let sz0 = size, parts = seg(size), tw = width(parts);
    if (tw > maxW) { sz0 = size * maxW / tw; parts = seg(sz0); tw = width(parts); }
    let cx = align === 'center' ? x - tw / 2 : align === 'right' ? x - tw : x;
    ctx.fillStyle = color; ctx.textAlign = 'left';
    parts.forEach(p => { if (!p[0]) return; font(ctx, p[1], p[2], p[4]); ctx.fillText(p[0], cx, y + p[3]); cx += ctx.measureText(p[0]).width + sz0 * .04; });
  }
  const pFooter = e => {
    const b = blocks(e, { w: W - 2 * e.P, align: 'center', parts: ['cta', 'footer'], flat: true });
    b.draw(e.P, e.H - e.P - b.h);
    return e.H - e.P - b.h - (b.h ? 44 : 0);
  };
  const pTop = e => (e.st.showLogo ? logo(e, 60) : e.P - 20) + 44;
  const hair = (e, y) => { e.ctx.fillStyle = A(e.st.text, .12); e.ctx.fillRect(e.P, y, W - 2 * e.P, 1.5); };

  const PROMO = {};

  // Oferta em destaque: um único plano, preço enorme, muito respiro
  PROMO.offer = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .82);
    const top = pTop(e), bot = pFooter(e), ds = story ? 64 : 52;
    const hD = devRow(e, 0, w, ds, true);
    if (hD) { hair(e, bot - hD - 36); devRow(e, bot - hD, w, ds); }
    const z1 = bot - (hD ? hD + 72 : 0);
    const plans = (d.plans || []).filter(p => p && (p.name || p.price)), p = plans.find(x => x.hot) || plans[0];
    const ps = story ? 300 : fmt === 'feed' ? 240 : 190, ns = story ? 62 : 50;
    const hE = eyebrow(e, 0, true), hT = pTitle(e, st.title, 0, w, story ? 84 : 66, 2, true);
    const hP = p ? ns * 1.5 + ps * .92 + (p.detail ? 58 : 0) : 0;
    let y = top + Math.max(0, (z1 - top - (hE + hT + 34 + hP)) / 2);
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, story ? 84 : 66, 2);
    rule(e, y + 14); y += 34;
    if (!p) return;
    const f = fitLine(ctx, String(p.name || '').toUpperCase(), st.fontBody, ns * .56, 18, 700, w);
    ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .8); ctx.fillText(f.text, W / 2, y + ns * 1.1);
    y += ns * 1.5;
    price(e, p.price, W / 2, y + ps * .78, ps, 'center', st.primary, w);
    y += ps * .92;
    if (p.detail) { const g = fitLine(ctx, String(p.detail), st.fontBody, story ? 32 : 27, 16, 500, w); ctx.textAlign = 'center'; ctx.fillStyle = A(st.text, .7); ctx.fillText(g.text, W / 2, y + 40); }
    ctx.textAlign = 'left';
  };

  // Planos e preços: lista limpa, só o plano em destaque recebe cor
  PROMO.plans = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .86);
    const top = pTop(e), bot = pFooter(e), ds = story ? 64 : 50;
    const hD = devRow(e, 0, w, ds, true);
    if (hD) { hair(e, bot - hD - 36); devRow(e, bot - hD, w, ds); }
    let y = top;
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, story ? 104 : fmt === 'feed' ? 84 : 68, 2);
    rule(e, y + 14); y += 20;
    const plans = (d.plans || []).filter(p => p && (p.name || p.price)).slice(0, 4), n = plans.length;
    if (!n) return;
    const z0 = y + (story ? 56 : 40), z1 = bot - (hD ? hD + 72 : 0), gap = story ? 18 : 16;
    const card = (p, x, cy, cw, ch, vertical) => {
      rr(ctx, x, cy, cw, ch, 22);
      if (p.hot) { ctx.fillStyle = st.primary; ctx.fill(); }
      else { ctx.fillStyle = A(st.text, .045); ctx.fill(); ctx.strokeStyle = A(st.text, .14); ctx.lineWidth = 1.5; ctx.stroke(); }
      const fg = p.hot ? on(st.primary) : st.text, soft = p.hot ? A(on(st.primary), .78) : A(st.text, .62), acc = p.hot ? on(st.primary) : st.primary;
      if (p.hot && p.tag) {
        font(ctx, st.fontBody, 19, 800);
        const t = String(p.tag).toUpperCase(), tw = spacedW(ctx, t, 2) + 34, tx = vertical ? x + cw / 2 - tw / 2 : x + cw - tw - 28;
        rr(ctx, tx, cy - 18, tw, 36, 18); ctx.fillStyle = st.text; ctx.fill();
        ctx.fillStyle = st.bg; spaced(ctx, t, tx + 17, cy + 7, 2);
      }
      if (vertical) {
        const f = fitLine(ctx, String(p.name || '').toUpperCase(), st.fontBody, Math.min(28, ch * .09), 15, 700, cw - 36);
        ctx.fillStyle = soft; ctx.textAlign = 'center'; ctx.fillText(f.text, x + cw / 2, cy + ch * .2);
        price(e, p.price, x + cw / 2, cy + ch * .6, Math.min(cw * .5, ch * .36), 'center', acc, cw - 40);
        if (p.detail) { const g = fitLine(ctx, String(p.detail), st.fontBody, 21, 13, 500, cw - 30); ctx.fillStyle = soft; ctx.textAlign = 'center'; ctx.fillText(g.text, x + cw / 2, cy + ch * .84); }
      } else {
        const f = fitLine(ctx, String(p.name || '').toUpperCase(), st.fontTitle, Math.min(58, ch * .34), 22, 800, cw * .5);
        ctx.fillStyle = fg; ctx.textAlign = 'left'; ctx.fillText(f.text, x + 38, cy + ch / 2 + (p.detail ? -2 : f.size * .34));
        if (p.detail) { const g = fitLine(ctx, String(p.detail), st.fontBody, Math.min(24, ch * .15), 13, 500, cw * .5); ctx.fillStyle = soft; ctx.fillText(g.text, x + 38, cy + ch / 2 + g.size + 14); }
        const ps = Math.min(104, ch * .56);
        price(e, p.price, x + cw - 38, cy + ch / 2 + ps * .34, ps, 'right', acc, cw * .4);
      }
      ctx.textAlign = 'left';
    };
    if (story) {
      const ch = Math.min((z1 - z0 - gap * (n - 1)) / n, 196), y0 = z0 + Math.max(0, (z1 - z0 - (n * ch + gap * (n - 1))) / 2);
      plans.forEach((p, i) => card(p, P, y0 + i * (ch + gap), w, ch, false));
    } else {
      const cw = Math.min((w - gap * (n - 1)) / n, 330), ch = Math.min(z1 - z0, 360), x0 = (W - (n * cw + gap * (n - 1))) / 2, y0 = z0 + Math.max(0, (z1 - z0 - ch) / 2);
      plans.forEach((p, i) => card(p, x0 + i * (cw + gap), y0, cw, ch, true));
    }
  };

  // Catálogo completo: capas grandes em cima, mensagem embaixo sobre fundo liso
  PROMO.collage = function (e) {
    const { ctx, H, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, ov = st.overlay / 100;
    const mh = Math.round(H * (story ? .5 : fmt === 'feed' ? .45 : .42));
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (mosaic(e, { x: 0, y: 0, w: W, h: mh, cell: story ? 330 : 256 })) { ctx.fillStyle = A(st.bg, .1 + ov * .5); ctx.fillRect(0, 0, W, mh); }
    ctx.fillStyle = lin(ctx, 0, mh * .4, 0, mh, [A(st.bg, 0), st.bg]); ctx.fillRect(0, mh * .4, W, mh * .6 + 2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, 280, [A(st.bg, .85), A(st.bg, 0)]); ctx.fillRect(0, 0, W, 280);
    if (st.showLogo) logo(e, 60);
    const bot = pFooter(e), ds = story ? 64 : 50, ts = story ? 118 : fmt === 'feed' ? 96 : 78, fs = story ? 31 : 27, fh = fs * 1.75;
    const feats = st.showFeatures ? (d.features || []).slice(0, story ? 4 : fmt === 'feed' ? 3 : 2) : [];
    const hD = devRow(e, 0, w, ds, true);
    if (hD) { hair(e, bot - hD - 36); devRow(e, bot - hD, w, ds); }
    const z0 = mh * .86, z1 = bot - (hD ? hD + 72 : 0);
    const total = eyebrow(e, 0, true) + pTitle(e, st.title, 0, w, ts, 3, true) + 40 + (feats.length ? feats.length * fh + 10 : 0);
    let y = z0 + Math.max(0, (z1 - z0 - total) / 2);
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, ts, 3);
    rule(e, y + 14); y += 40;
    feats.forEach(t => {
      const f = fitLine(ctx, String(t), st.fontBody, fs, 16, 500, w - 60), tw = ctx.measureText(f.text).width, x = (W - tw - 34) / 2;
      ctx.strokeStyle = st.primary; ctx.lineWidth = 4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.beginPath();
      ctx.moveTo(x, y + fh * .52); ctx.lineTo(x + 7, y + fh * .52 + 8); ctx.lineTo(x + 20, y + fh * .52 - 8); ctx.stroke(); ctx.lineCap = 'butt';
      ctx.fillStyle = A(st.text, .86); ctx.textAlign = 'left'; ctx.fillText(f.text, x + 34, y + fh * .52 + f.size * .34);
      y += fh;
    });
    ctx.textAlign = 'left';
  };

  // Todos os dispositivos: frase grande e os cinco ícones num painel
  PROMO.devices = function (e) {
    const { ctx, P, st, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .86);
    const top = pTop(e), bot = pFooter(e), ts = story ? 112 : fmt === 'feed' ? 90 : 72;
    const big = story ? 132 : fmt === 'feed' ? 112 : 92, ph = big * 1.46 + (story ? 110 : 80);
    const all = Object.assign({}, e, { st: Object.assign({}, st, { showDevices: true }) });
    const total = eyebrow(e, 0, true) + pTitle(e, st.devTitle || st.title, 0, w, ts, 3, true) + 40 + (story ? 60 : 36) + ph;
    let y = top + Math.max(0, (bot - top - total) / 2);
    y += eyebrow(e, y);
    y += pTitle(e, st.devTitle || st.title, y, w, ts, 3);
    rule(e, y + 14); y += 40 + (story ? 60 : 36);
    rr(ctx, P, y, w, ph, 28); ctx.fillStyle = A(st.text, .045); ctx.fill();
    ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
    devRow(all, y + (ph - big * 1.46) / 2, w - 40, big, false, true);
  };

  // Grade centralizada de blocos (logos quadrados ou capas 2:3) dentro de uma área; devolve a altura usada
  function tiles(e, imgs, x, y, w, h, cols, rows, gap, poster) {
    const { ctx, st } = e, list = (imgs || []).filter(Boolean).slice(0, cols * rows);
    if (!list.length) return 0;
    const used = Math.ceil(list.length / cols), k = poster ? 1.5 : 1;
    let tw = (w - gap * (cols - 1)) / cols;
    if (tw * k * rows + gap * (rows - 1) > h) tw = (h - gap * (rows - 1)) / rows / k;
    const th = tw * k, gh = used * th + gap * (used - 1), y0 = y + Math.max(0, (h - gh) / 2);
    list.forEach((img, i) => {
      const inRow = Math.min(cols, list.length - Math.floor(i / cols) * cols), rw = inRow * tw + gap * (inRow - 1);
      const tx = x + (w - rw) / 2 + (i % cols) * (tw + gap), ty = y0 + Math.floor(i / cols) * (th + gap), r = poster ? 14 : tw * .2;
      ctx.save(); rr(ctx, tx, ty, tw, th, r); ctx.clip(); cover(ctx, img, tx, ty, tw, th); ctx.restore();
      ctx.strokeStyle = A(st.text, .14); ctx.lineWidth = 1.5; rr(ctx, tx, ty, tw, th, r); ctx.stroke();
    });
    return gh;
  }
  // Cabeçalho padrão (linha pequena, título e régua); devolve o Y logo abaixo
  function pHead(e, y, size, maxL) {
    const w = W - 2 * e.P;
    y += eyebrow(e, y);
    y += pTitle(e, e.st.title, y, w, size, maxL);
    rule(e, y + 14);
    return y + 20;
  }
  function pDevices(e, bot, ds) {
    const w = W - 2 * e.P, hD = devRow(e, 0, w, ds, true);
    if (hD) { hair(e, bot - hD - 36); devRow(e, bot - hD, w, ds); }
    return bot - (hD ? hD + 72 : 0);
  }

  /* ---------- Templates de conversão ----------
     Todos seguem a mesma ordem de leitura: gancho → valor → preço → botão → redutor de risco → confiança. */

  // Selo curto em cápsula, na cor de destaque (urgência ou novidade)
  function pBadge(e, text, y, dry) {
    const { ctx, P, st } = e;
    if (!text) return 0;
    const h = 52;
    if (!dry) {
      const t = String(text).toUpperCase();
      let size = 22;
      font(ctx, st.fontBody, size, 800);
      while (size > 13 && spacedW(ctx, t, 3) + 52 > W - 2 * P) { size -= 1; font(ctx, st.fontBody, size, 800); }
      const bw = spacedW(ctx, t, 3) + 52;
      rr(ctx, (W - bw) / 2, y, bw, h, h / 2); ctx.fillStyle = st.primary; ctx.fill();
      ctx.fillStyle = on(st.primary); spaced(ctx, t, (W - bw) / 2 + 26, y + h / 2 + size * .36, 3);
    }
    return h;
  }
  const pNum = t => { const m = /(\d{1,3}(?:\.\d{3})+|\d{1,6})(?:[,.](\d{1,2})(?!\d))?/.exec(String(t || '')); return m ? parseFloat(m[1].replace(/\./g, '') + '.' + (m[2] || '0')) : NaN; };
  // Linha única centralizada
  function pLine(e, text, y, size, color, weight, dry) {
    const { ctx, P } = e;
    if (!text) return 0;
    if (!dry) { const f = fitLine(ctx, String(text), e.st.fontBody, size, 15, weight || 500, W - 2 * P); ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.fillText(f.text, W / 2, y + f.size); ctx.textAlign = 'left'; }
    return size * 1.55;
  }
  // Preço antigo riscado ("De R$ 49,90")
  function pOld(e, text, y, size, dry) {
    const { ctx, st } = e;
    if (!text) return 0;
    if (!dry) {
      font(ctx, st.fontBody, size, 600);
      const t = 'De ' + String(text), tw = ctx.measureText(t).width;
      ctx.fillStyle = A(st.text, .55); ctx.textAlign = 'center'; ctx.fillText(t, W / 2, y + size); ctx.textAlign = 'left';
      ctx.fillRect(W / 2 - tw / 2 - 6, y + size * .66, tw + 12, 3);
    }
    return size * 1.5;
  }
  // Redutores de risco numa linha: "Sem fidelidade • Ativação imediata" vira itens com ✓
  function pChecks(e, text, y, size, dry) {
    const { ctx, P, st } = e, items = String(text || '').split(/\s*[•|]\s*/).filter(Boolean).slice(0, 3);
    if (!items.length) return 0;
    const gap = 34, maxW = W - 2 * P, min = size * .8;
    let s = size, tw;
    const mark = () => s * .95;
    for (;;) { font(ctx, st.fontBody, s, 600); tw = items.reduce((a, t) => a + ctx.measureText(t).width + mark(), 0) + gap * (items.length - 1); if (tw <= maxW || s <= min) break; s -= 1; }
    const stack = tw > maxW; // não coube numa linha legível
    if (stack) { s = size; font(ctx, st.fontBody, s, 600); }
    const tick = (x, yy) => {
      ctx.strokeStyle = st.primary; ctx.lineWidth = Math.max(3, s * .13); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.beginPath();
      ctx.moveTo(x, yy + s * .62); ctx.lineTo(x + s * .22, yy + s * .86); ctx.lineTo(x + s * .62, yy + s * .36); ctx.stroke(); ctx.lineCap = 'butt';
    };
    if (!dry) {
      ctx.textAlign = 'left';
      if (stack) {
        items.forEach((t, i) => {
          const tx = ell(ctx, t, maxW - mark()), w2 = ctx.measureText(tx).width + mark(), x = (W - w2) / 2, yy = y + i * s * 1.6;
          tick(x, yy); ctx.fillStyle = A(st.text, .82); ctx.fillText(tx, x + mark(), yy + s);
        });
      } else {
        let x = (W - tw) / 2;
        items.forEach(t => { tick(x, y); ctx.fillStyle = A(st.text, .82); ctx.fillText(t, x + mark(), y + s); x += ctx.measureText(t).width + mark() + gap; });
      }
    }
    return stack ? items.length * size * 1.6 + size * .1 : size * 1.7;
  }
  const hotPlan = d => { const l = (d.plans || []).filter(p => p && p.price); return l.find(p => p.hot) || l[0] || null; };
  function star(ctx, cx, cy, r) {
    ctx.beginPath();
    for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + i * Math.PI / 5, k = i % 2 ? r * .45 : r; ctx.lineTo(cx + Math.cos(a) * k, cy + Math.sin(a) * k); }
    ctx.closePath(); ctx.fill();
  }
  function checkDot(e, cx, cy, r) {
    const { ctx, st } = e;
    ctx.fillStyle = st.primary; ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); ctx.fill();
    ctx.strokeStyle = on(st.primary); ctx.lineWidth = r * .22; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.beginPath();
    ctx.moveTo(cx - r * .42, cy + r * .04); ctx.lineTo(cx - r * .1, cy + r * .36); ctx.lineTo(cx + r * .46, cy - r * .3); ctx.stroke(); ctx.lineCap = 'butt';
  }

  // Oferta com preço: capas no topo (o produto), promessa, preço ancorado e redutores de risco
  PROMO.hero = function (e) {
    const { ctx, H, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, ov = st.overlay / 100, o = d.offer || {};
    const mh = Math.round(H * (story ? .42 : fmt === 'feed' ? .38 : .34));
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (mosaic(e, { x: 0, y: 0, w: W, h: mh, cell: story ? 300 : 240 })) { ctx.fillStyle = A(st.bg, .1 + ov * .5); ctx.fillRect(0, 0, W, mh); }
    ctx.fillStyle = lin(ctx, 0, mh * .35, 0, mh, [A(st.bg, 0), st.bg]); ctx.fillRect(0, mh * .35, W, mh * .65 + 2);
    ctx.fillStyle = lin(ctx, 0, 0, 0, 280, [A(st.bg, .85), A(st.bg, 0)]); ctx.fillRect(0, 0, W, 280);
    if (st.showLogo) logo(e, 60);
    offerStack(e, mh * .8, pDevices(e, pFooter(e), story ? 60 : 46), 1);
  };

  // Pilha de oferta centralizada numa área: selo, promessa, preço antigo riscado, preço, plano e redutores de risco
  function offerStack(e, z0, z1, k) {
    const { st, d, fmt } = e, story = fmt === 'story', w = W - 2 * e.P, o = d.offer || {}, p = hotPlan(d);
    const ts = (story ? 104 : fmt === 'feed' ? 82 : 64) * k, ps = (story ? 230 : fmt === 'feed' ? 176 : 132) * k, os = story ? 34 : 28, cs = story ? 27 : 23;
    const label = p ? [p.name, p.detail].filter(Boolean).join('  •  ') : '';
    let y0 = 0;
    const seq = dry => {
      let h = 0, y = y0;
      const add = v => { h += v; y += v; };
      if (o.badge) { add(pBadge(e, o.badge, y, dry)); add(28); }
      add(pTitle(e, st.title, y, w, ts, 3, dry)); add(story ? 30 : 18);
      if (p) {
        add(pOld(e, p.old, y, os, dry));
        if (!dry) price(e, p.price, W / 2, y + ps * .8, ps, 'center', st.primary, w);
        add(ps * .92);
        add(pLine(e, label, y, story ? 28 : 24, A(st.text, .68), 500, dry));
      }
      if (o.note) { add(story ? 16 : 8); add(pChecks(e, o.note, y, cs, dry)); }
      return h;
    };
    y0 = z0 + Math.max(0, (z1 - z0 - seq(true)) / 2);
    seq(false);
  }

  // Tipográfico: fundo na cor da marca, só texto — contraste máximo, lê-se em um segundo
  PROMO.type = function (e) {
    const s0 = e.st, ink = lum(s0.primary) > .6 ? s0.bg : '#FFFFFF', dark = lum(s0.primary) > .6 ? s0.bg : mix(s0.primary, '#000000', .72);
    const e2 = Object.assign({}, e, { st: Object.assign({}, s0, { bg: s0.primary, text: ink, primary: dark, secondary: dark }) });
    const { ctx, H } = e2;
    ctx.fillStyle = s0.primary; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = lin(ctx, 0, 0, W, H, [A('#FFFFFF', .1), A('#000000', .12)]); ctx.fillRect(0, 0, W, H);
    const top = pTop(e2), bot = pFooter(e2);
    let y = top;
    y += eyebrow(e2, y);
    offerStack(e2, y, bot, 1.12);
  };

  // Leque de capas: três capas em leque no topo e a oferta embaixo
  PROMO.fan = function (e) {
    const { ctx, H, st, im, fmt } = e, story = fmt === 'story';
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H * .6, [A(st.primary, .14), A(st.primary, 0)]); ctx.fillRect(0, 0, W, H * .6);
    const top = pTop(e), list = (im.covers || []).filter(Boolean);
    const pw = story ? 330 : fmt === 'feed' ? 250 : 190, ph = e.d.coverType === 'icon' ? pw : pw * 1.5, cy = top + ph * .56 + 10;
    [[-1, list[1]], [1, list[2]], [0, list[0]]].forEach(([k, img]) => {
      if (!img) return;
      const s = k ? .86 : 1, w2 = pw * s, h2 = ph * s;
      ctx.save();
      ctx.translate(W / 2 + k * pw * .72, cy + Math.abs(k) * ph * .05); ctx.rotate(k * .11);
      ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 22;
      ctx.fillStyle = '#111'; rr(ctx, -w2 / 2, -h2 / 2, w2, h2, 18); ctx.fill();
      ctx.shadowColor = 'transparent';
      rr(ctx, -w2 / 2, -h2 / 2, w2, h2, 18); ctx.clip(); cover(ctx, img, -w2 / 2, -h2 / 2, w2, h2);
      if (k) { ctx.fillStyle = A(st.bg, .35); ctx.fillRect(-w2 / 2, -h2 / 2, w2, h2); }
      ctx.restore();
    });
    offerStack(e, list.length ? cy + ph * .56 : top, pFooter(e), story ? .92 : .86);
  };

  // Lista completa: tudo o que está incluído num cartão, com o selo de preço sobreposto
  PROMO.list = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e), p = hotPlan(d);
    const y = pHead(e, top, story ? 100 : fmt === 'feed' ? 80 : 64, 2) + (story ? 70 : 48);
    const feats = (d.features || []).slice(0, story ? 6 : fmt === 'feed' ? 5 : 3), n = feats.length;
    if (!n) return;
    const fs = story ? 34 : 27, rh = Math.min((bot - y - 80) / n, story ? 104 : 78), ch = n * rh + 60, cy = y + Math.max(0, (bot - y - ch) / 2), r = story ? 23 : 19;
    rr(ctx, P, cy, w, ch, 26); ctx.fillStyle = A(st.text, .045); ctx.fill(); ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
    const seal = p ? (story ? 118 : 92) : 0;
    feats.forEach((t, i) => {
      const my = cy + 30 + i * rh + rh / 2;
      if (i) { ctx.fillStyle = A(st.text, .08); ctx.fillRect(P + 34, my - rh / 2, w - 68, 1.5); }
      checkDot(e, P + 40 + r, my, r);
      const f = fitLine(ctx, String(t), st.fontBody, fs, 17, 600, w - 2 * r - 110 - (i === 0 ? seal : 0));
      ctx.fillStyle = st.text; ctx.textAlign = 'left'; ctx.fillText(f.text, P + 40 + 2 * r + 22, my + f.size * .36);
    });
    if (p) {
      const sx = P + w - seal * .78, sy = cy - seal * .18;
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 10;
      ctx.fillStyle = st.primary; ctx.beginPath(); ctx.arc(sx, sy + seal * .4, seal, 0, 7); ctx.fill(); ctx.restore();
      font(ctx, st.fontBody, seal * .15, 800); ctx.fillStyle = on(st.primary); ctx.textAlign = 'center'; ctx.fillText('POR APENAS', sx, sy + seal * .02);
      price(e, p.price, sx, sy + seal * .78, seal * .92, 'center', on(st.primary), seal * 1.7);
    }
    ctx.textAlign = 'left';
  };

  // Teste grátis: convite sem risco e três passos numerados
  PROMO.trial = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, t = d.trial || {};
    promoBg(e, .88);
    const top = pTop(e), bot = pFooter(e);
    let y = top;
    y += eyebrow(e, y);
    y += pTitle(e, t.title || st.title, y, w, story ? 196 : fmt === 'feed' ? 150 : 116, 2, false, st.primary);
    y += pLine(e, t.sub, y + 4, story ? 36 : 30, A(st.text, .86), 500);
    const steps = (t.steps || []).filter(Boolean).slice(0, 3), n = steps.length;
    if (!n) return;
    const z0 = y + (story ? 70 : 40), zone = bot - z0, gap = story ? 22 : 18;
    if (story) {
      const rh = Math.min((zone - gap * (n - 1)) / n, 220), y0 = z0 + Math.max(0, (zone - (n * rh + gap * (n - 1))) * .35), r = 44, cx = P + 34 + r;
      if (n > 1) { ctx.fillStyle = A(st.text, .16); ctx.fillRect(cx - 1.5, y0 + rh / 2, 3, (n - 1) * (rh + gap)); }
      steps.forEach((s, i) => {
        const cy = y0 + i * (rh + gap) + rh / 2;
        rr(ctx, P, cy - rh / 2, w, rh, 22); ctx.fillStyle = A(st.text, .045); ctx.fill(); ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
        ctx.fillStyle = st.primary; ctx.beginPath(); ctx.arc(cx, cy, r, 0, 7); ctx.fill();
        font(ctx, st.fontTitle, 52, 800); ctx.fillStyle = on(st.primary); ctx.textAlign = 'center'; ctx.fillText(String(i + 1), cx, cy + 18);
        font(ctx, st.fontBody, 38, 600);
        const lines = wrap(ctx, String(s), w - 2 * r - 110, 2);
        ctx.fillStyle = st.text; ctx.textAlign = 'left';
        lines.forEach((l, k) => ctx.fillText(l, cx + r + 30, cy + 12 - (lines.length - 1) * 23 + k * 46));
      });
    } else {
      const cw = (w - gap * (n - 1)) / n, ch = Math.min(zone, 300), y0 = z0 + Math.max(0, (zone - ch) / 2), r = 34;
      steps.forEach((s, i) => {
        const x = P + i * (cw + gap);
        rr(ctx, x, y0, cw, ch, 22); ctx.fillStyle = A(st.text, .045); ctx.fill(); ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
        ctx.fillStyle = st.primary; ctx.beginPath(); ctx.arc(x + cw / 2, y0 + 30 + r, r, 0, 7); ctx.fill();
        font(ctx, st.fontTitle, 44, 800); ctx.fillStyle = on(st.primary); ctx.textAlign = 'center'; ctx.fillText(String(i + 1), x + cw / 2, y0 + 30 + r + 15);
        font(ctx, st.fontBody, 25, 600);
        const lines = wrap(ctx, String(s), cw - 36, 3);
        ctx.fillStyle = st.text;
        lines.forEach((l, k) => ctx.fillText(l, x + cw / 2, y0 + 30 + 2 * r + 50 + k * 34));
      });
    }
    ctx.textAlign = 'left';
  };

  // Benefícios: grade de cartões iguais e o preço logo acima do botão
  PROMO.benefits = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e), p = hotPlan(d), ps = story ? 120 : 92;
    let z1 = bot;
    if (p) {
      z1 = bot - ps - 60;
      hair(e, z1 + 4);
      font(ctx, st.fontBody, 21, 700);
      const t = 'A PARTIR DE';
      ctx.fillStyle = A(st.text, .62); spaced(ctx, t, (W - spacedW(ctx, t, 4)) / 2, z1 + 46, 4);
      price(e, p.price, W / 2, z1 + 52 + ps * .8, ps, 'center', st.primary, w);
      z1 -= 26;
    }
    const y = pHead(e, top, story ? 100 : fmt === 'feed' ? 80 : 64, 2) + (story ? 52 : 32);
    const feats = (d.features || []).slice(0, 6), n = feats.length;
    if (!n) return;
    const cols = story ? 2 : 3, rows = Math.ceil(n / cols), gap = story ? 20 : 16, fs = story ? 29 : 23;
    const cw = (w - gap * (cols - 1)) / cols, ch = Math.min((z1 - y - gap * (rows - 1)) / rows, story ? 230 : 170);
    const y0 = y + Math.max(0, (z1 - y - (rows * ch + gap * (rows - 1))) / 2);
    feats.forEach((t, i) => {
      const inRow = Math.min(cols, n - Math.floor(i / cols) * cols), rw = inRow * cw + gap * (inRow - 1);
      const x = P + (w - rw) / 2 + (i % cols) * (cw + gap), cy = y0 + Math.floor(i / cols) * (ch + gap), r = story ? 26 : 21;
      rr(ctx, x, cy, cw, ch, 22); ctx.fillStyle = A(st.text, .045); ctx.fill(); ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
      checkDot(e, x + 28 + r, cy + 28 + r, r);
      font(ctx, st.fontBody, fs, 600);
      const lines = wrap(ctx, String(t), cw - 56, ch > 150 ? 3 : 2);
      ctx.fillStyle = st.text; ctx.textAlign = 'left';
      lines.forEach((l, k) => ctx.fillText(l, x + 28, cy + 28 + 2 * r + fs * 1.5 + k * fs * 1.34));
    });
    ctx.textAlign = 'left';
  };

  // Prova social: estrelas, depoimento do cliente e o número de assinantes
  PROMO.proof = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, pr = d.proof || {};
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e);
    const qs = story ? 44 : fmt === 'feed' ? 36 : 30, lh = qs * 1.42, sr = story ? 26 : 21, cs = story ? 92 : fmt === 'feed' ? 74 : 58;
    font(ctx, st.fontBody, qs, 600);
    const quote = wrap(ctx, String(pr.quote || 'Escreva aqui o depoimento de um cliente seu.'), w - 60, story ? 6 : 4);
    const hA = pr.author ? 60 : 0, hC = pr.count ? cs * 1.2 + 60 : 0;
    const total = sr * 2 + 44 + quote.length * lh + hA + hC;
    let y = top + Math.max(0, (bot - top - total) / 2);
    ctx.fillStyle = st.primary;
    for (let i = 0; i < 5; i++) star(ctx, W / 2 + (i - 2) * (sr * 2 + 12), y + sr, sr);
    y += sr * 2 + 44;
    font(ctx, st.fontBody, qs, 600); ctx.fillStyle = st.text; ctx.textAlign = 'center';
    quote.forEach((l, i) => ctx.fillText(l, W / 2, y + qs + i * lh));
    y += quote.length * lh;
    if (pr.author) {
      const t = String(pr.author).toUpperCase();
      font(ctx, st.fontBody, 22, 700); ctx.fillStyle = A(st.text, .62);
      spaced(ctx, t, (W - spacedW(ctx, t, 3)) / 2, y + 42, 3);
      y += hA;
    }
    if (pr.count) {
      hair(e, y + 28);
      pTitle(e, pr.count, y + 60, w, cs, 2, false, st.primary);
    }
    ctx.textAlign = 'left';
  };

  // Promoção relâmpago: faixa de urgência, desconto gigante e prazo
  PROMO.urgency = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {}, p = hotPlan(d);
    promoBg(e, .86);
    let top = pTop(e);
    if (o.badge) {
      const t = String(o.badge).toUpperCase(), bh = story ? 78 : 62;
      ctx.fillStyle = st.primary; ctx.fillRect(0, top, W, bh);
      let size = story ? 28 : 23;
      font(ctx, st.fontBody, size, 800);
      while (size > 14 && spacedW(ctx, t, 5) > w) { size -= 1; font(ctx, st.fontBody, size, 800); }
      ctx.fillStyle = on(st.primary); spaced(ctx, t, (W - spacedW(ctx, t, 5)) / 2, top + bh / 2 + size * .36, 5);
      top += bh + 20;
    }
    const bot = pFooter(e);
    const a = p ? pNum(p.old) : NaN, b = p ? pNum(p.price) : NaN, pct = a > b && b > 0 ? Math.round((1 - b / a) * 100) : 0;
    const big = story ? 360 : fmt === 'feed' ? 270 : 200, ts = story ? 78 : fmt === 'feed' ? 62 : 50, ps = story ? 170 : fmt === 'feed' ? 130 : 100, os = story ? 34 : 27;
    const seq = dry => {
      let h = 0, y = y0;
      const add = v => { h += v; y += v; };
      if (pct) {
        if (!dry) { font(ctx, st.fontTitle, big, 800); ctx.fillStyle = st.primary; ctx.textAlign = 'center'; ctx.fillText(pct + '%', W / 2, y + big * .8); ctx.textAlign = 'left'; }
        add(big * .84);
        if (!dry) { const t = 'DE DESCONTO'; font(ctx, st.fontBody, story ? 30 : 24, 800); ctx.fillStyle = st.text; spaced(ctx, t, (W - spacedW(ctx, t, 8)) / 2, y + 30, 8); }
        add(story ? 80 : 60);
      }
      add(pTitle(e, st.title, y, w, pct ? ts : ts * 1.5, 2, dry)); add(story ? 34 : 20);
      if (p) {
        add(pOld(e, p.old, y, os, dry));
        if (!dry) price(e, p.price, W / 2, y + ps * .8, ps, 'center', pct ? st.text : st.primary, w);
        add(ps * .92);
      }
      if (o.deadline) add(pLine(e, o.deadline, y + 6, story ? 28 : 24, A(st.text, .7), 500, dry));
      return h;
    };
    let y0 = 0;
    y0 = top + Math.max(0, (bot - top - seq(true)) / 2);
    seq(false);
  };

  /* ---------- Coleção 2: dez composições extras de marketing ---------- */

  // Bloco de preço compacto (preço antigo + preço + plano); dry só mede. Devolve a altura
  function priceBlock(e, y, ps, color, dry) {
    const { st, d, fmt } = e, p = hotPlan(d), w = W - 2 * e.P;
    if (!p) return 0;
    let h = 0;
    h += pOld(e, p.old, y, fmt === 'story' ? 32 : 26, dry);
    if (!dry) price(e, p.price, W / 2, y + h + ps * .8, ps, 'center', color || st.primary, w);
    h += ps * .92;
    h += pLine(e, [p.name, p.detail].filter(Boolean).join('  •  '), y + h, fmt === 'story' ? 27 : 23, A(st.text, .68), 500, dry);
    return h;
  }
  function burst(ctx, cx, cy, R, n, inner) {
    ctx.beginPath();
    for (let i = 0; i < n * 2; i++) { const a = i * Math.PI / n, k = i % 2 ? R * inner : R; ctx.lineTo(cx + Math.cos(a) * k, cy + Math.sin(a) * k); }
    ctx.closePath();
  }

  // Selo de preço: o preço dentro de um selo recortado gigante
  PROMO.pseal = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {}, p = hotPlan(d);
    promoBg(e, .88);
    const top = pTop(e), bot = pFooter(e);
    let y = pHead(e, top, story ? 96 : fmt === 'feed' ? 78 : 62, 2) + 30;
    const hN = o.note ? pChecks(e, o.note, 0, story ? 27 : 23, true) + 24 : 0, z1 = bot - hN;
    const R = Math.max(90, Math.min(story ? 330 : 250, (z1 - y) / 2 - 10, w / 2)), cy = y + (z1 - y) / 2;
    ctx.save(); ctx.shadowColor = A(st.primary, .45); ctx.shadowBlur = 60;
    ctx.fillStyle = lin(ctx, W / 2 - R, cy - R, W / 2 + R, cy + R, [st.primary, st.secondary]); burst(ctx, W / 2, cy, R, 28, .9); ctx.fill(); ctx.restore();
    const ink = on(st.primary);
    ctx.strokeStyle = A(ink, .45); ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(W / 2, cy, R * .78, 0, 7); ctx.stroke();
    if (p) {
      font(ctx, st.fontBody, R * .095, 800); ctx.fillStyle = ink;
      const t = 'POR APENAS'; spaced(ctx, t, W / 2 - spacedW(ctx, t, 4) / 2, cy - R * .36, 4);
      price(e, p.price, W / 2, cy + R * .2, R * .62, 'center', ink, R * 1.3);
      const f = fitLine(ctx, [p.name, p.detail].filter(Boolean).join(' • ').toUpperCase(), st.fontBody, R * .085, 12, 700, R * 1.2);
      ctx.fillStyle = A(ink, .8); ctx.textAlign = 'center'; ctx.fillText(f.text, W / 2, cy + R * .42); ctx.textAlign = 'left';
    }
    if (hN) pChecks(e, o.note, z1 + 14, story ? 27 : 23);
  };

  // Faixa diagonal: uma faixa na cor da marca cruza o banner com a chamada de urgência
  PROMO.pband = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {};
    promoBg(e, .84);
    const top = pTop(e), bot = pFooter(e), ts = story ? 104 : fmt === 'feed' ? 82 : 64, ps = story ? 210 : fmt === 'feed' ? 160 : 120, bh = story ? 110 : 84;
    const hT = eyebrow(e, 0, true) + pTitle(e, st.title, 0, w, ts, 3, true), hP = priceBlock(e, 0, ps, null, true), hN = o.note ? pChecks(e, o.note, 0, story ? 27 : 23, true) + 14 : 0;
    const total = hT + (o.badge ? bh + 90 : 40) + hP + hN;
    let y = top + Math.max(0, (bot - top - total) / 2);
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, ts, 3);
    if (o.badge) {
      const cy = y + 45 + bh / 2, t = String(o.badge).toUpperCase();
      ctx.save(); ctx.translate(W / 2, cy); ctx.rotate(-0.045);
      ctx.fillStyle = lin(ctx, -W, 0, W, 0, [st.primary, st.secondary]); ctx.fillRect(-W, -bh / 2, W * 2, bh);
      let size = story ? 46 : 36;
      font(ctx, st.fontTitle, size, 800);
      while (size > 18 && spacedW(ctx, t, 6) > w) { size -= 2; font(ctx, st.fontTitle, size, 800); }
      ctx.fillStyle = on(st.primary); spaced(ctx, t, -spacedW(ctx, t, 6) / 2, size * .36, 6);
      ctx.restore();
      y += bh + 90;
    } else y += 40;
    y += priceBlock(e, y, ps);
    if (hN) pChecks(e, o.note, y + 8, story ? 27 : 23);
  };

  // Cartão de vidro: capas vivas ao fundo e a oferta num painel translúcido
  PROMO.pglass = function (e) {
    const { ctx, H, P, st, fmt } = e, story = fmt === 'story';
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    if (mosaic(e, { x: 0, y: 0, w: W, h: H, tilt: -0.12, cell: 250 })) { ctx.fillStyle = A(st.bg, .3 + st.overlay / 100 * .4); ctx.fillRect(0, 0, W, H); }
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [[0, A(st.bg, .8)], [.25, A(st.bg, 0)], [.75, A(st.bg, 0)], [1, A(st.bg, .9)]]); ctx.fillRect(0, 0, W, H);
    const top = pTop(e), bot = pFooter(e), py = top + 10, ph = bot - py - 10;
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 18;
    ctx.fillStyle = A(st.bg, .72); rr(ctx, P, py, W - 2 * P, ph, 40); ctx.fill(); ctx.restore();
    ctx.fillStyle = lin(ctx, 0, py, 0, py + ph * .4, [A(st.text, .12), A(st.text, 0)]); rr(ctx, P, py, W - 2 * P, ph, 40); ctx.fill();
    ctx.strokeStyle = A(st.text, .28); ctx.lineWidth = 2; rr(ctx, P, py, W - 2 * P, ph, 40); ctx.stroke();
    offerStack(Object.assign({}, e, { P: P + 44 }), py + 30, py + ph - 30, story ? .92 : .86);
  };

  // Números: os destaques viram cartões com o número em evidência
  PROMO.numbers = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e);
    const y = pHead(e, top, story ? 100 : fmt === 'feed' ? 80 : 64, 2) + (story ? 56 : 34);
    const feats = (d.features || []).slice(0, story ? 4 : 3), n = feats.length;
    if (!n) return;
    const gap = 18, vert = story, cw = vert ? w : (w - gap * (n - 1)) / n, ch = vert ? Math.min((bot - y - gap * (n - 1)) / n, 230) : Math.min(bot - y, 330);
    const y0 = y + Math.max(0, (bot - y - (vert ? n * ch + gap * (n - 1) : ch)) / 2);
    feats.forEach((t, i) => {
      const x = vert ? P : P + i * (cw + gap), cy = vert ? y0 + i * (ch + gap) : y0;
      rr(ctx, x, cy, cw, ch, 24); ctx.fillStyle = A(st.text, .05); ctx.fill(); ctx.strokeStyle = A(st.text, .13); ctx.lineWidth = 1.5; ctx.stroke();
      ctx.fillStyle = lin(ctx, 0, cy, 0, cy + ch, [st.primary, st.secondary]); rr(ctx, x, cy + 24, 7, ch - 48, 4); ctx.fill();
      const m = /^\s*([+\-]?\s*\d[\d.,]*\s*(?:mil|k|%|\+|h|x)?\+?)\s*(.*)$/i.exec(String(t)), big = m ? m[1].replace(/\s+/g, '').toUpperCase() : '', rest = m ? m[2] : String(t);
      if (vert) {
        let bw = Math.min(90, ch * .44);
        if (big) {
          const f = fitLine(ctx, big, st.fontTitle, Math.min(150, ch * .7), 40, 800, cw * .42);
          ctx.fillStyle = st.primary; ctx.textAlign = 'left'; ctx.fillText(f.text, x + 40, cy + ch / 2 + f.size * .34);
          bw = ctx.measureText(f.text).width;
        } else checkDot(e, x + 40 + bw / 2, cy + ch / 2, bw / 2);
        font(ctx, st.fontBody, 32, 600);
        const lines = wrap(ctx, rest, cw - bw - 110, 2);
        ctx.fillStyle = st.text; lines.forEach((l, k) => ctx.fillText(l, x + 40 + bw + 30, cy + ch / 2 + 11 - (lines.length - 1) * 21 + k * 42));
      } else {
        if (big) { const f = fitLine(ctx, big, st.fontTitle, Math.min(130, ch * .42), 36, 800, cw - 40); ctx.fillStyle = st.primary; ctx.textAlign = 'center'; ctx.fillText(f.text, x + cw / 2, cy + ch * .46); }
        else checkDot(e, x + cw / 2, cy + ch * .3, Math.min(44, ch * .14));
        ctx.textAlign = 'center';
        font(ctx, st.fontBody, 24, 600);
        const lines = wrap(ctx, rest, cw - 36, 3);
        ctx.fillStyle = st.text; lines.forEach((l, k) => ctx.fillText(l, x + cw / 2, cy + ch * .46 + 46 + k * 32));
      }
    });
    ctx.textAlign = 'left';
  };

  // Aparelho desenhado (celular ou TV) com o mosaico de capas na tela
  function device(e, kind, x, y, w, h) {
    const { ctx, st } = e, r = kind === 'phone' ? w * .12 : 16, bz = kind === 'phone' ? w * .035 : 12;
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.7)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 26;
    ctx.fillStyle = '#08080B'; rr(ctx, x, y, w, h, r); ctx.fill(); ctx.restore();
    ctx.save(); rr(ctx, x + bz, y + bz, w - 2 * bz, h - 2 * bz, Math.max(4, r - bz)); ctx.clip();
    ctx.fillStyle = '#101016'; ctx.fillRect(x, y, w, h);
    mosaic(e, { x: x + bz, y: y + bz, w: w - 2 * bz, h: h - 2 * bz, cell: kind === 'phone' ? w * .3 : w * .15 });
    ctx.fillStyle = lin(ctx, x, y, x + w * .6, y + h, [A('#FFFFFF', .1), A('#FFFFFF', 0)]); ctx.fillRect(x, y, w, h);
    ctx.restore();
    ctx.strokeStyle = A(st.text, .3); ctx.lineWidth = 2; rr(ctx, x, y, w, h, r); ctx.stroke();
    if (kind === 'phone') { ctx.fillStyle = '#08080B'; rr(ctx, x + w / 2 - w * .16, y + bz + 6, w * .32, 22, 11); ctx.fill(); }
    else { ctx.fillStyle = '#16161C'; ctx.fillRect(x + w / 2 - w * .035, y + h, w * .07, 28); rr(ctx, x + w / 2 - w * .17, y + h + 28, w * .34, 12, 6); ctx.fill(); }
  }
  function deviceOffer(e, kind) {
    const { ctx, H, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {};
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.primary, .1), st.bg, mix(st.bg, '#000000', .4)]); ctx.fillRect(0, 0, W, H);
    const top = pTop(e), bot = pFooter(e), ts = story ? 92 : fmt === 'feed' ? 74 : 58, ps = story ? 170 : fmt === 'feed' ? 130 : 96;
    let y = top;
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, ts, 2) + 30;
    const hP = priceBlock(e, 0, ps, null, true), hN = o.note ? pChecks(e, o.note, 0, story ? 26 : 22, true) + 12 : 0, z1 = bot - hP - hN - 30;
    glow(ctx, W / 2, y + (z1 - y) / 2, w * .7, A(st.primary, .28));
    if (kind === 'phone') { const ph = Math.max(120, z1 - y), pw = Math.min(ph * .5, w * .62); device(e, 'phone', (W - pw) / 2, y + (z1 - y - pw * 2) / 2 > y ? y + (z1 - y - Math.min(ph, pw * 2)) / 2 : y, pw, Math.min(ph, pw * 2)); }
    else { let tw = w, th = tw * 9 / 16; if (th > z1 - y - 50) { th = Math.max(100, z1 - y - 50); tw = th * 16 / 9; } device(e, 'tv', (W - tw) / 2, y + Math.max(0, (z1 - y - th - 50) / 2), tw, th); }
    priceBlock(e, z1 + 20, ps);
    if (hN) pChecks(e, o.note, bot - hN + 6, story ? 26 : 22);
  }
  PROMO.pphone = e => deviceOffer(e, 'phone');
  PROMO.ptv = e => deviceOffer(e, 'tv');

  // Canais e preço: a grade de logos em cima e "todos esses canais por" embaixo
  PROMO.plogos = function (e) {
    const { ctx, P, st, im, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e), ps = story ? 190 : fmt === 'feed' ? 140 : 100;
    const y = pHead(e, top, story ? 92 : fmt === 'feed' ? 74 : 58, 2) + (story ? 46 : 28);
    const hP = priceBlock(e, 0, ps, null, true), z1 = bot - hP - (hP ? 84 : 0);
    tiles(e, im.logos, P, y, w, z1 - y, story ? 4 : 6, story ? 4 : fmt === 'feed' ? 2 : 1, 16);
    if (!hP) return;
    hair(e, z1 + 24);
    const t = 'TUDO ISSO POR';
    font(ctx, st.fontBody, 22, 800); ctx.fillStyle = A(st.text, .65); spaced(ctx, t, (W - spacedW(ctx, t, 5)) / 2, z1 + 66, 5);
    priceBlock(e, z1 + 76, ps);
  };

  // Conversa: a oferta contada em balões de mensagem
  PROMO.pchat = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {}, t = d.trial || {}, p = hotPlan(d);
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e), fs = story ? 42 : 29, lh = fs * 1.36, pad = story ? 36 : 22, bw = w * .86;
    const msgs = [[0, 'Oi! Quero assistir ' + String(st.title || 'tudo').toLowerCase() + '. Como funciona?'], [1, [t.sub, o.note].filter(Boolean).join('. ').replace(/\s*•\s*/g, ', ') || 'É simples: você assina e já começa a assistir.']];
    if (p) msgs.push([0, 'E quanto custa?'], [1, (p.name ? 'Plano ' + p.name + ': ' : '') + p.price + (p.detail ? ' (' + p.detail + ')' : '')]);
    font(ctx, st.fontBody, fs, 600);
    const items = msgs.slice(0, story ? 4 : fmt === 'feed' ? 4 : 2).map(m => { const lines = wrap(ctx, m[1], bw - 2 * pad, 4); return { me: m[0], lines, h: lines.length * lh + 2 * pad - 8, w: Math.min(bw, Math.max(...lines.map(l => ctx.measureText(l).width)) + 2 * pad) }; });
    const gap = story ? 26 : 16, total = items.reduce((a, b) => a + b.h + gap, 0) - gap;
    let y = top + Math.max(0, (bot - top - total) / 2);
    items.forEach(it => {
      const x = it.me ? P + w - it.w : P;
      ctx.fillStyle = it.me ? lin(ctx, x, 0, x + it.w, 0, [st.primary, st.secondary]) : A(st.text, .1);
      rr(ctx, x, y, it.w, it.h, 30); ctx.fill();
      ctx.beginPath(); if (it.me) { ctx.moveTo(x + it.w - 34, y + it.h - 2); ctx.lineTo(x + it.w + 6, y + it.h + 16); ctx.lineTo(x + it.w - 6, y + it.h - 30); } else { ctx.moveTo(x + 34, y + it.h - 2); ctx.lineTo(x - 6, y + it.h + 16); ctx.lineTo(x + 6, y + it.h - 30); } ctx.closePath(); ctx.fill();
      font(ctx, st.fontBody, fs, 600); ctx.fillStyle = it.me ? on(st.primary) : st.text; ctx.textAlign = 'left';
      it.lines.forEach((l, k) => ctx.fillText(l, x + pad, y + pad + fs * .82 + k * lh));
      y += it.h + gap;
    });
  };

  // Garantia: escudo com ✓ e a lista de compromissos
  PROMO.pshield = function (e) {
    const { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, o = d.offer || {}, t = d.trial || {};
    promoBg(e, .9);
    const top = pTop(e), bot = pFooter(e), S = story ? 250 : fmt === 'feed' ? 180 : 130, ts = story ? 104 : fmt === 'feed' ? 82 : 64;
    const items = String(o.note || '').split(/\s*[•|]\s*/).filter(Boolean).slice(0, story ? 4 : 3), rh = story ? 78 : 60;
    const hT = pTitle(e, t.title || st.title, 0, w, ts, 2, true), hS = t.sub ? (story ? 56 : 46) : 0;
    const total = S + 40 + hT + hS + (items.length ? items.length * rh + 30 : 0);
    let y = top + Math.max(0, (bot - top - total) / 2);
    const cx = W / 2;
    ctx.save(); ctx.shadowColor = A(st.primary, .45); ctx.shadowBlur = 50;
    ctx.fillStyle = lin(ctx, cx - S / 2, y, cx + S / 2, y + S, [st.primary, st.secondary]);
    ctx.beginPath(); ctx.moveTo(cx, y); ctx.lineTo(cx + S * .42, y + S * .16); ctx.lineTo(cx + S * .42, y + S * .52);
    ctx.quadraticCurveTo(cx + S * .4, y + S * .84, cx, y + S); ctx.quadraticCurveTo(cx - S * .4, y + S * .84, cx - S * .42, y + S * .52); ctx.lineTo(cx - S * .42, y + S * .16); ctx.closePath(); ctx.fill(); ctx.restore();
    ctx.strokeStyle = on(st.primary); ctx.lineWidth = S * .075; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.beginPath();
    ctx.moveTo(cx - S * .17, y + S * .5); ctx.lineTo(cx - S * .04, y + S * .63); ctx.lineTo(cx + S * .19, y + S * .36); ctx.stroke(); ctx.lineCap = 'butt';
    y += S + 40;
    y += pTitle(e, t.title || st.title, y, w, ts, 2);
    if (hS) { pLine(e, t.sub, y + 4, story ? 34 : 28, A(st.text, .85), 500); y += hS; }
    if (items.length) {
      y += 30;
      font(ctx, st.fontBody, story ? 32 : 26, 600);
      const mw = Math.min(w - 90, Math.max(...items.map(i => ctx.measureText(i).width))), x0 = (W - mw - 64) / 2;
      items.forEach(it => {
        checkDot(e, x0 + 22, y + rh / 2, story ? 22 : 18);
        const f = fitLine(ctx, it, st.fontBody, story ? 32 : 26, 16, 600, w - 110);
        ctx.fillStyle = st.text; ctx.textAlign = 'left'; ctx.fillText(f.text, x0 + 64, y + rh / 2 + f.size * .36);
        y += rh;
      });
    }
    ctx.textAlign = 'left';
  };

  // Claro: fundo claro, texto escuro, a cor da marca só no preço e no botão
  PROMO.plight = function (e) {
    const s0 = e.st, e2 = Object.assign({}, e, { st: Object.assign({}, s0, { bg: mix(s0.text, s0.bg, .03), text: s0.bg, primary: lum(s0.primary) > .75 ? mix(s0.primary, '#000000', .35) : s0.primary }) });
    const { ctx, H, P, st } = e2;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = A(s0.primary, .16); ctx.beginPath(); ctx.arc(W * .96, H * .02, W * .5, 0, 7); ctx.fill();
    ctx.fillStyle = A(s0.secondary, .12); ctx.beginPath(); ctx.arc(0, H, W * .42, 0, 7); ctx.fill();
    ctx.strokeStyle = A(st.text, .1); ctx.lineWidth = 2; ctx.strokeRect(P / 2, P / 2, W - P, H - P);
    const top = pTop(e2), bot = pFooter(e2);
    let y = top;
    y += eyebrow(e2, y);
    offerStack(e2, y, bot, 1.05);
  };

  /* ---------- Captação de revendas: banners para recrutar revendedores ----------
     Usam os textos do grupo "Captação de revendas" (d.resale) no lugar da oferta ao cliente final. */
  function resaleEnv(e, extra) {
    const r = e.d.resale || {};
    return Object.assign({}, e, {
      st: Object.assign({}, e.st, { title: r.title || 'SEJA UM REVENDEDOR', sub: r.sub || '' }),
      d: Object.assign({}, e.d, { features: r.benefits || [], plans: [], offer: { note: (r.benefits || []).slice(0, 3).join(' • ') },
        trial: { title: r.title || 'SEJA UM REVENDEDOR', sub: r.sub || '', steps: r.steps || [] } }, extra || {}),
    });
  }
  // Chamada + lista de vantagens
  PROMO.rs_hero = e => PROMO.list(resaleEnv(e));
  // Como funciona, em três passos
  PROMO.rs_steps = e => PROMO.trial(resaleEnv(e));
  // Vantagens em cartões (os números ficam em evidência)
  PROMO.rs_numbers = e => PROMO.numbers(resaleEnv(e));
  // Garantia / compromisso com o parceiro
  PROMO.rs_shield = e => PROMO.pshield(resaleEnv(e));

  // Lucro em destaque: o ganho possível escrito gigante
  PROMO.rs_profit = function (e0) {
    const e = resaleEnv(e0), { ctx, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P, r = e0.d.resale || {};
    promoBg(e, .86);
    const top = pTop(e), bot = pFooter(e), ts = story ? 100 : fmt === 'feed' ? 80 : 62, big = story ? 250 : fmt === 'feed' ? 190 : 140, cs = story ? 27 : 23;
    const seq = dry => {
      let h = 0, y = y0;
      const add = v => { h += v; y += v; };
      add(eyebrow(e, y, dry));
      add(pTitle(e, st.title, y, w, ts, 3, dry)); add(story ? 36 : 22);
      if (r.profit) {
        if (!dry) { const t = String(r.profitLabel || 'LUCRE ATÉ').toUpperCase(); font(ctx, st.fontBody, story ? 26 : 22, 800); ctx.fillStyle = A(st.text, .7); spaced(ctx, t, (W - spacedW(ctx, t, 5)) / 2, y + 26, 5); }
        add(story ? 50 : 42);
        if (!dry) price(e, r.profit, W / 2, y + big * .8, big, 'center', st.primary, w);
        add(big * .94);
        add(pLine(e, r.profitNote, y, story ? 28 : 24, A(st.text, .7), 500, dry));
      }
      if (d.offer.note) { add(story ? 20 : 10); add(pChecks(e, d.offer.note, y, cs, dry)); }
      return h;
    };
    let y0 = 0;
    y0 = top + Math.max(0, (bot - top - seq(true)) / 2);
    seq(false);
  };

  // Painel próprio: um notebook com o painel do revendedor (gráfico de crescimento)
  PROMO.rs_panel = function (e0) {
    const e = resaleEnv(e0), { ctx, H, P, st, d, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    ctx.fillStyle = lin(ctx, 0, 0, 0, H, [mix(st.bg, st.primary, .1), st.bg, mix(st.bg, '#000000', .4)]); ctx.fillRect(0, 0, W, H);
    const top = pTop(e), bot = pFooter(e), cs = story ? 27 : 23;
    let y = top;
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, story ? 96 : fmt === 'feed' ? 76 : 60, 2) + 34;
    const hN = d.offer.note ? pChecks(e, d.offer.note, 0, cs, true) + 12 : 0, z1 = bot - hN - 20;
    let tw = w * .9, th = tw * .6;
    if (th > z1 - y - 40) { th = Math.max(100, z1 - y - 40); tw = th / .6; }
    const tx = (W - tw) / 2, ty = y + Math.max(0, (z1 - y - th - 40) / 2);
    glow(ctx, W / 2, ty + th / 2, tw * .8, A(st.primary, .28));
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.7)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 24;
    ctx.fillStyle = '#0A0A0E'; rr(ctx, tx, ty, tw, th, 18); ctx.fill(); ctx.restore();
    ctx.strokeStyle = A(st.text, .3); ctx.lineWidth = 2; rr(ctx, tx, ty, tw, th, 18); ctx.stroke();
    ctx.fillStyle = '#1B1B22'; poly(ctx, [[tx - tw * .08, ty + th + 26], [tx + tw * 1.08, ty + th + 26], [tx + tw, ty + th], [tx, ty + th]]); ctx.fill();
    // tela: três indicadores e um gráfico de barras crescente
    const sx = tx + 26, sy = ty + 26, sw = tw - 52, sh = th - 52, kw = (sw - 28) / 3;
    ['Clientes', 'Renovações', 'Lucro'].forEach((t, i) => {
      rr(ctx, sx + i * (kw + 14), sy, kw, sh * .24, 10); ctx.fillStyle = A(st.text, .07); ctx.fill();
      font(ctx, st.fontBody, Math.max(11, sh * .05), 600); ctx.fillStyle = A(st.text, .6); ctx.textAlign = 'left'; ctx.fillText(t, sx + i * (kw + 14) + 14, sy + sh * .09);
      ctx.fillStyle = i === 2 ? st.primary : A(st.text, .85); rr(ctx, sx + i * (kw + 14) + 14, sy + sh * .13, kw * (.4 + i * .15), sh * .055, 4); ctx.fill();
    });
    const gy = sy + sh * .3, gh = sh * .7, n = 8, bw = (sw - (n - 1) * 12) / n;
    for (let i = 0; i < n; i++) {
      const hh = gh * (.22 + .78 * Math.pow((i + 1) / n, 1.25)) * (i % 3 === 1 ? .88 : 1);
      ctx.fillStyle = i === n - 1 ? lin(ctx, 0, gy + gh - hh, 0, gy + gh, [st.primary, st.secondary]) : A(st.text, .16);
      rr(ctx, sx + i * (bw + 12), gy + gh - hh, bw, hh, 6); ctx.fill();
    }
    if (hN) pChecks(e, d.offer.note, bot - hN + 6, cs);
    ctx.textAlign = 'left';
  };

  // Grade de canais: logos em blocos iguais, nada mais
  PROMO.channels = function (e) {
    const { P, im, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), z1 = pDevices(e, pFooter(e), story ? 64 : 50);
    const y = pHead(e, top, story ? 104 : fmt === 'feed' ? 84 : 68, 2) + (story ? 56 : 36);
    tiles(e, im.logos, P, y, w, z1 - y, story ? 4 : fmt === 'feed' ? 5 : 6, story ? 5 : fmt === 'feed' ? 3 : 2, story ? 20 : 16);
  };

  // Streamings: os aplicativos conhecidos lado a lado e o preço do plano em destaque
  PROMO.streaming = function (e) {
    const { ctx, P, st, d, im, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    promoBg(e, .9);
    const top = pTop(e), z1 = pDevices(e, pFooter(e), story ? 64 : 50);
    let y = pHead(e, top, story ? 104 : fmt === 'feed' ? 84 : 68, 3) + (story ? 56 : 36);
    const plans = (d.plans || []).filter(p => p && p.price), p = plans.find(x => x.hot) || plans[0];
    const ps = story ? 150 : 110, hP = p ? ps + 70 : 0;
    const cols = 4, rows = story ? 3 : fmt === 'feed' ? 2 : 1, gap = story ? 26 : 20;
    const gw = Math.min(w, story ? 860 : fmt === 'feed' ? 760 : 700);
    const used = tiles(e, im.streams, (W - gw) / 2, y, gw, z1 - y - hP, cols, rows, gap);
    if (!p) return;
    const py = y + Math.max(used, 0) + (z1 - y - hP - used) / 2 + (used ? 40 : 0);
    font(ctx, st.fontBody, 22, 700);
    const t = 'A PARTIR DE';
    ctx.fillStyle = A(st.text, .62); spaced(ctx, t, (W - spacedW(ctx, t, 4)) / 2, py + 24, 4);
    price(e, p.price, W / 2, py + 30 + ps * .8, ps, 'center', st.primary, w);
  };

  // Completo: três faixas rotuladas — filmes e séries, canais e streamings
  PROMO.combo = function (e) {
    const { ctx, H, P, st, im, fmt } = e, story = fmt === 'story', w = W - 2 * P;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = lin(ctx, 0, 0, 0, H * .5, [A(st.primary, .1), A(st.primary, 0)]); ctx.fillRect(0, 0, W, H * .5);
    const top = pTop(e), bot = pFooter(e);
    let y = pHead(e, top, story ? 96 : fmt === 'feed' ? 78 : 62, 2) + (story ? 44 : 26);
    const secs = [['FILMES E SÉRIES', im.covers, true, e.d.coverType === 'icon' ? 0 : 2.4], ['CANAIS AO VIVO', im.logos, false, 1], ['STREAMINGS', im.streams, false, 1]]
      .filter(s => (s[1] || []).filter(Boolean).length && s[3]);
    if (!secs.length) return;
    const lab = story ? 50 : 40, sg = story ? 40 : 24, gap = story ? 16 : 12;
    const unit = Math.min((bot - y - secs.length * lab - (secs.length - 1) * sg) / secs.reduce((a, s) => a + s[3], 0), story ? 190 : 110);
    const total = secs.reduce((a, s) => a + lab + s[3] * unit, 0) + (secs.length - 1) * sg;
    y += Math.max(0, (bot - y - total) / 2);
    secs.forEach(s => {
      font(ctx, st.fontBody, story ? 22 : 19, 800);
      ctx.fillStyle = st.primary; spaced(ctx, s[0], P, y + (story ? 24 : 20), 4);
      ctx.fillStyle = A(st.text, .12); ctx.fillRect(P + spacedW(ctx, s[0], 4) + 18, y + (story ? 16 : 13), w - spacedW(ctx, s[0], 4) - 18, 1.5);
      y += lab;
      const hgt = s[3] * unit, tw = s[2] ? hgt / 1.5 : hgt, cols = Math.max(1, Math.floor((w + gap) / (tw + gap)));
      tiles(e, s[1], P, y, w, hgt, cols, 1, gap, s[2]);
      y += hgt + sg;
    });
  };

  // Parede de capas: fileiras em cima e embaixo, mensagem numa faixa central lisa
  PROMO.wall = function (e) {
    const { ctx, H, P, st, fmt } = e, story = fmt === 'story', w = W - 2 * P, ov = st.overlay / 100;
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    const ts = story ? 124 : fmt === 'feed' ? 100 : 80, ds = story ? 64 : 50, pad = story ? 84 : 60;
    const b = blocks(e, { w, align: 'center', parts: ['cta', 'footer'], flat: true });
    const hE = eyebrow(e, 0, true), hT = pTitle(e, st.title, 0, w, ts, 3, true), hD = devRow(e, 0, w, ds, true);
    const band = pad + hE + hT + 40 + (hD ? hD + 40 : 0) + (b.h ? b.h + 36 : 0) + pad - 20, by = Math.round((H - band) / 2);
    const cell = story ? 232 : 190;
    mosaic(e, { x: 0, y: 0, w: W, h: by, cell });
    mosaic(e, { x: 0, y: by + band, w: W, h: H - by - band, cell });
    ctx.fillStyle = A(st.bg, .3 + ov * .45); ctx.fillRect(0, 0, W, by); ctx.fillRect(0, by + band, W, H - by - band);
    ctx.fillStyle = lin(ctx, 0, by - 160, 0, by, [A(st.bg, 0), st.bg]); ctx.fillRect(0, by - 160, W, 161);
    ctx.fillStyle = lin(ctx, 0, by + band, 0, by + band + 160, [st.bg, A(st.bg, 0)]); ctx.fillRect(0, by + band - 1, W, 161);
    ctx.fillStyle = lin(ctx, 0, 0, 0, 280, [A(st.bg, .9), A(st.bg, 0)]); ctx.fillRect(0, 0, W, 280);
    if (st.showLogo) logo(e, 60);
    let y = by + pad;
    y += eyebrow(e, y);
    y += pTitle(e, st.title, y, w, ts, 3);
    rule(e, y + 14); y += 40;
    if (hD) { devRow(e, y + 20, w, ds); y += hD + 40; }
    if (b.h) b.draw(P, y + 16);
  };

  // Ferramentas compartilhadas com o gerador de vídeo
  GG.kit = { lin, glow, rr, cover, contain, blurCover, font, fitLine, wrap, A, mix, on, logo, blocks, posterCard };

  /* ---------- Render ---------- */
  GG.render = async function (canvas, spec, scale) {
    scale = scale || 1;
    const st = spec.st, d = spec.data || {}, H = GG.FORMATS[spec.format].h, brand = spec.brand || {};
    const all = a => Promise.all(a.map(GG.img));
    const im = { logo: await GG.img(st.showLogo ? brand.logo : '') };
    if (spec.kind === 'movie') {
      [im.poster, im.backdrop, im.titleLogo] = await all([d.poster, d.backdrop, st.useTitleLogo ? d.titleLogo : '']);
      if (spec.layout === 'ranking' || spec.layout === 'catalog') im.list = await all((d.list || []).slice(0, 6).map(i => i.poster));
    } else if (spec.kind === 'promo') {
      [im.covers, im.logos, im.streams] = await Promise.all([all((d.covers || []).slice(0, 24)), all((d.logos || []).slice(0, 24)), all((d.streams || []).slice(0, 16))]);
    } else {
      const gs = d.games || [];
      [im.bg, im.l1, im.l2, im.comp, im.ch] = await Promise.all([GG.img(st.bgStyle === 'upload' ? brand.bgImage : ''), all(gs.map(g => g.l1)), all(gs.map(g => g.l2)), all(gs.map(g => st.showComp ? g.compLogo : '')),
        Promise.all(gs.map(g => all((g.ch || []).slice(0, 5).map(c => st.showChannels ? c.i : ''))))]);
    }
    if (spec.guard && !spec.guard()) return false;

    canvas.width = Math.round(W * scale); canvas.height = Math.round(H * scale);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.imageSmoothingQuality = 'high';
    ctx.fillStyle = st.bg; ctx.fillRect(0, 0, W, H);
    const table = spec.kind === 'movie' ? MOVIE : spec.kind === 'promo' ? PROMO : GAMES;
    const fn = table[spec.layout] || table[Object.keys(table)[0]];
    fn({ ctx, W, H, P: spec.kind === 'games' ? 56 : 64, st, d, im, fmt: spec.format, brand });
    if (spec.locked) {
      // Conta sem plano ativo: a prévia aparece com marca d'água
      ctx.save(); ctx.translate(W / 2, H / 2); ctx.rotate(-0.5);
      font(ctx, 'Montserrat', 64, 800); ctx.textAlign = 'center';
      for (let y = -H; y <= H; y += 260) { ctx.fillStyle = 'rgba(255,255,255,.2)'; ctx.fillText('PRÉVIA • ASSINE PARA BAIXAR', 0, y); ctx.strokeStyle = 'rgba(0,0,0,.25)'; ctx.lineWidth = 1.5; ctx.strokeText('PRÉVIA • ASSINE PARA BAIXAR', 0, y); }
      ctx.restore();
    }
    return true;
  };
})();
