<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
page_start('Banners de Divulgação', 'divulgacao', $u);
?>
<div class="editor">
  <section class="panel">
    <div class="grp">
      <h3>Capas do fundo <button type="button" class="link" id="shuffle">embaralhar</button></h3>
      <div class="chips" id="themes" style="margin-top:0">
        <button type="button" class="chip" data-theme="mix">Filmes e séries</button>
        <button type="button" class="chip" data-theme="filmes">Só filmes</button>
        <button type="button" class="chip" data-theme="series">Só séries</button>
        <button type="button" class="chip" data-theme="desenhos">Só desenhos</button>
        <button type="button" class="chip" data-theme="infantil">Infantil</button>
        <button type="button" class="chip" data-theme="novelas">Só novelas</button>
        <button type="button" class="chip" data-theme="canais">Logos de canais</button>
        <button type="button" class="chip" data-theme="streaming">Streamings</button>
      </div>
      <small id="themeInfo"></small>
    </div>
    <div class="grp">
      <h3>Canais em destaque <button type="button" class="link" id="shuffleCh">embaralhar</button></h3>
      <div class="chips" id="chcats" style="margin-top:0">
        <button type="button" class="chip" data-cat="">Principais</button>
        <button type="button" class="chip" data-cat="abertos">Abertos</button>
        <button type="button" class="chip" data-cat="esportes">Esportes</button>
        <button type="button" class="chip" data-cat="filmes">Filmes e séries</button>
        <button type="button" class="chip" data-cat="infantil">Infantil</button>
        <button type="button" class="chip" data-cat="noticias">Notícias</button>
        <button type="button" class="chip" data-cat="variedades">Variedades</button>
      </div>
      <div class="logos" id="chPrev"></div>
      <p class="note" style="margin-top:8px">Usados nos layouts "Grade de Canais" e "Completo". Os logos das plataformas de streaming entram nos layouts "Streamings" e "Completo".</p>
    </div>
    <div class="grp">
      <h3>Planos e preços</h3>
      <div id="plans"></div>
      <label class="fld" style="margin-top:6px"><span>Selo do plano em destaque</span><input type="text" id="tag" maxlength="24"></label>
      <p class="note" style="margin-top:0">A estrela marca o plano em destaque, que é o usado nos templates de oferta. "Preço antigo" é opcional: aparece riscado e gera a porcentagem de desconto. Deixe nome e preço vazios para esconder um plano.</p>
    </div>
    <div class="grp">
      <h3>Oferta</h3>
      <label class="fld"><span>Selo de urgência</span><input type="text" data-k="offer.badge" maxlength="40"></label>
      <label class="fld"><span>Garantias (separe com •)</span><input type="text" data-k="offer.note" maxlength="80"></label>
      <label class="fld"><span>Prazo da promoção</span><input type="text" data-k="offer.deadline" maxlength="50"></label>
    </div>
    <div class="grp">
      <h3>Teste grátis</h3>
      <div class="two">
        <label class="fld"><span>Chamada</span><input type="text" data-k="trial.title" maxlength="24"></label>
        <label class="fld"><span>Complemento</span><input type="text" data-k="trial.sub" maxlength="50"></label>
      </div>
      <label class="fld"><span>Passo 1</span><input type="text" data-k="trial.s1" maxlength="60"></label>
      <label class="fld"><span>Passo 2</span><input type="text" data-k="trial.s2" maxlength="60"></label>
      <label class="fld"><span>Passo 3</span><input type="text" data-k="trial.s3" maxlength="60"></label>
    </div>
    <div class="grp">
      <h3>Prova social</h3>
      <label class="fld"><span>Depoimento de um cliente real</span><textarea data-k="proof.quote" rows="3" maxlength="260"></textarea></label>
      <div class="two">
        <label class="fld"><span>Quem disse</span><input type="text" data-k="proof.author" maxlength="40" placeholder="Nome, cidade"></label>
        <label class="fld"><span>Número de clientes</span><input type="text" data-k="proof.count" maxlength="40" placeholder="+ de 500 clientes"></label>
      </div>
      <p class="note" style="margin-top:0">Use só depoimentos e números verdadeiros; anúncio com depoimento inventado é propaganda enganosa.</p>
    </div>
    <div class="grp">
      <h3>Captação de revendas</h3>
      <label class="fld"><span>Chamada</span><input type="text" data-k="resale.title" maxlength="40"></label>
      <label class="fld"><span>Complemento</span><input type="text" data-k="resale.sub" maxlength="60"></label>
      <div class="two">
        <label class="fld"><span>Texto antes do valor</span><input type="text" data-k="resale.profitLabel" maxlength="24"></label>
        <label class="fld"><span>Ganho possível</span><input type="text" data-k="resale.profit" maxlength="16" placeholder="R$ 0,00"></label>
      </div>
      <label class="fld"><span>Observação do ganho</span><input type="text" data-k="resale.profitNote" maxlength="60"></label>
      <label class="fld"><span>Vantagens para o revendedor (uma por linha)</span><textarea data-k="resale.benefitsText" rows="4" maxlength="400"></textarea></label>
      <label class="fld"><span>Passo 1</span><input type="text" data-k="resale.s1" maxlength="60"></label>
      <label class="fld"><span>Passo 2</span><input type="text" data-k="resale.s2" maxlength="60"></label>
      <label class="fld"><span>Passo 3</span><input type="text" data-k="resale.s3" maxlength="60"></label>
      <p class="note" style="margin-top:0">Usados na categoria "Banners de captação de revendas". O ganho vem vazio: informe só um valor que seus revendedores realmente alcançam.</p>
    </div>
    <div class="grp">
      <h3>Destaques <small style="text-transform:none;letter-spacing:0">um por linha</small></h3>
      <textarea id="feats" rows="5" maxlength="400"></textarea>
    </div>
  </section>
  <?php editor_stage('promo'); editor_controls('promo'); ?>
</div>
<?php editor_boot('promo', $u); ?>
<script>
(function () {
  const $ = s => document.querySelector(s), KEY = 'gg:' + GGCFG.uid + ':promo:data';
  const DEF = {
    theme: 'mix', chcat: '', tag: 'MAIS VENDIDO',
    offer: { badge: 'OFERTA POR TEMPO LIMITADO', note: 'Sem fidelidade • Ativação imediata • Suporte todos os dias', deadline: 'Válido somente esta semana' },
    trial: { title: 'TESTE GRÁTIS', sub: 'Experimente antes de assinar', s1: 'Chame no WhatsApp', s2: 'Receba seu acesso na hora', s3: 'Assista em qualquer aparelho' },
    proof: { quote: '', author: '', count: '' },
    resale: { title: 'SEJA UM REVENDEDOR', sub: 'Tenha seu próprio negócio de streaming', profitLabel: 'LUCRE ATÉ', profit: '', profitNote: 'por mês, trabalhando do celular',
      benefitsText: 'Painel próprio para gerenciar clientes\nVocê define o seu preço\nSuporte e material de divulgação\nSem mensalidade para começar', s1: 'Chame no WhatsApp', s2: 'Receba seu painel de revenda', s3: 'Venda e fique com o lucro' },
    plans: [{ name: 'Mensal', price: 'R$ 30,00', detail: '1 tela • 30 dias' }, { name: 'Trimestral', price: 'R$ 75,00', detail: '1 tela • 90 dias', hot: true },
      { name: 'Semestral', price: 'R$ 140,00', detail: '2 telas • 180 dias' }, { name: 'Anual', price: 'R$ 250,00', detail: '2 telas • 365 dias' }],
    features: ['Filmes e séries atualizados', 'Canais ao vivo em HD e 4K', 'Desenhos e novelas completas', 'Sem travamentos', 'Suporte todos os dias'],
  };
  let S = DEF;
  try { const v = JSON.parse(localStorage.getItem(KEY)); if (v && Array.isArray(v.plans) && Array.isArray(v.features)) S = Object.assign({}, DEF, v); } catch (e) { /* padrão */ }
  while (S.plans.length < 4) S.plans.push({ name: '', price: '', detail: '' });
  let covers = [], type = 'poster', channels = [], logos = [], streams = [];
  const chUrl = c => GGCFG.base + '/assets/canais/' + c.f;

  function push() {
    Editor.data = { covers, coverType: type, logos, streams, features: S.features, plans: S.plans.map(p => Object.assign({}, p, { tag: S.tag })),
      offer: S.offer, proof: S.proof,
      resale: Object.assign({}, S.resale, { benefits: String(S.resale.benefitsText || '').split('\n').map(t => t.trim()).filter(Boolean).slice(0, 6), steps: [S.resale.s1, S.resale.s2, S.resale.s3] }),
      trial: { title: S.trial.title, sub: S.trial.sub, steps: [S.trial.s1, S.trial.s2, S.trial.s3] } };
    try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) { /* ignora */ }
    Editor.render();
  }

  // Planos
  const box = $('#plans');
  S.plans.slice(0, 4).forEach((p, i) => {
    const row = document.createElement('div');
    row.className = 'plan-row';
    const mk = (key, ph, max) => { const el = document.createElement('input'); el.type = 'text'; el.placeholder = ph; el.maxLength = max; el.value = p[key] || ''; el.addEventListener('input', () => { p[key] = el.value; push(); }); return el; };
    const star = document.createElement('button');
    star.type = 'button'; star.className = 'star' + (p.hot ? ' on' : ''); star.textContent = '★'; star.title = 'Plano em destaque';
    star.addEventListener('click', () => {
      const was = p.hot;
      S.plans.forEach(x => { x.hot = false; });
      p.hot = !was;
      box.querySelectorAll('.star').forEach((b, k) => b.classList.toggle('on', !!S.plans[k].hot));
      push();
    });
    row.append(mk('name', 'Nome', 20), mk('price', 'R$ 0,00', 16), star, mk('old', 'Preço antigo', 16), mk('detail', 'Detalhe (telas, dias…)', 34));
    box.appendChild(row);
  });
  // Campos de oferta, teste grátis e prova social (data-k="grupo.campo")
  ['offer', 'trial', 'proof', 'resale'].forEach(g => { S[g] = Object.assign({}, DEF[g], S[g] && typeof S[g] === 'object' ? S[g] : {}); });
  document.querySelectorAll('[data-k]').forEach(el => {
    const [g, k] = el.dataset.k.split('.');
    el.value = S[g][k] || '';
    el.addEventListener('input', () => { S[g][k] = el.value; push(); });
  });
  $('#tag').value = S.tag;
  $('#tag').addEventListener('input', e => { S.tag = e.target.value; push(); });
  $('#feats').value = S.features.join('\n');
  $('#feats').addEventListener('input', e => { S.features = e.target.value.split('\n').map(t => t.trim()).filter(Boolean).slice(0, 6); push(); });

  // Capas por tema
  const shuffle = a => { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };
  async function theme(t) {
    S.theme = t;
    document.querySelectorAll('#themes .chip').forEach(c => c.classList.toggle('on', c.dataset.theme === t));
    $('#themeInfo').textContent = 'Carregando capas…';
    try {
      if (t === 'canais' || t === 'streaming') {
        await assets;
        covers = t === 'canais' ? shuffle(channels.slice(0, 60).map(chUrl)) : shuffle(streams.slice());
        type = 'icon';
        $('#themeInfo').textContent = covers.length ? covers.length + (t === 'canais' ? ' logos de canais.' : ' plataformas de streaming.') : 'Nenhum logo disponível.';
      } else {
        const j = await Editor.get('/api/tmdb.php?a=collage&theme=' + encodeURIComponent(t));
        covers = shuffle(j.posters.map(p => GG.tmdb(p, 'w342'))); type = 'poster';
        $('#themeInfo').textContent = covers.length ? covers.length + ' capas carregadas.' : 'Nenhuma capa encontrada para este tema.';
      }
    } catch (e) { $('#themeInfo').textContent = e.message; }
    push();
  }
  // Canais em destaque (arquivos locais) e plataformas de streaming (TMDB)
  function pickChannels(cat, mix) {
    S.chcat = cat;
    document.querySelectorAll('#chcats .chip').forEach(c => c.classList.toggle('on', c.dataset.cat === cat));
    let list = channels.filter(c => cat ? c.c === cat : c.c !== 'streaming');
    if (mix) list = shuffle(list.slice());
    logos = list.slice(0, 24).map(chUrl);
    const prev = $('#chPrev');
    prev.innerHTML = '';
    logos.forEach(u => { const i = new Image(); i.loading = 'lazy'; i.alt = ''; i.src = u; prev.appendChild(i); });
    push();
  }
  const assets = Promise.all([
    fetch(GGCFG.base + '/assets/canais/index.json').then(r => r.json()).then(j => { channels = j; }).catch(() => {}),
    Editor.get('/api/tmdb.php?a=providers').then(j => { streams = j.providers.map(p => GG.tmdb(p.logo, 'w154')); }).catch(() => {}),
  ]).then(() => pickChannels(S.chcat));
  document.querySelectorAll('#chcats .chip').forEach(c => c.addEventListener('click', () => pickChannels(c.dataset.cat)));
  $('#shuffleCh').addEventListener('click', () => pickChannels(S.chcat, true));
  document.querySelectorAll('#themes .chip').forEach(c => c.addEventListener('click', () => theme(c.dataset.theme)));
  $('#shuffle').addEventListener('click', () => { shuffle(covers); push(); });
  push();
  theme(S.theme);
})();
</script>
<?php page_end();
