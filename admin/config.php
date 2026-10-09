<?php
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard(false, false, true);

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? 'save';
    if ($a === 'cache') {
        flash(cache_clear() . ' arquivos de cache removidos.');
    } elseif ($a === 'tmdb') {
        $token = trim((string)setting('tmdb_token'));
        $r = http_get('https://api.themoviedb.org/3/configuration' . ($token === '' ? '?api_key=' . urlencode((string)setting('tmdb_key')) : ''), $token !== '' ? ['Authorization: Bearer ' . $token] : []);
        if ($r['code'] === 200) flash('TMDB respondeu corretamente. Credenciais válidas.');
        else flash('TMDB recusou as credenciais (HTTP ' . $r['code'] . '). Confira a chave e o token.', 'err');
    } else {
        $site = mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 40);
        set_setting('site_name', $site !== '' ? $site : 'Gerador Gold');
        foreach (['tmdb_key', 'tmdb_token', 'support_link', 'channel_link'] as $k) set_setting($k, trim((string)($_POST[$k] ?? '')));
        set_setting('dash_tagline', mb_substr(trim((string)($_POST['dash_tagline'] ?? '')), 0, 120));
        set_setting('dash_features', mb_substr(trim((string)($_POST['dash_features'] ?? '')), 0, 400) ?: DASH_FEATURES);
        set_setting('welcome_panel', mb_substr(trim((string)($_POST['welcome_panel'] ?? '')), 0, 600) ?: WELCOME_PANEL);
        set_setting('welcome_msg', mb_substr(trim((string)($_POST['welcome_msg'] ?? '')), 0, 1200) ?: WELCOME_MSG);
        // Logo do sistema (aparece no menu e no painel inicial)
        $sl = (string)setting('site_logo', '');
        try {
            if (!empty($_POST['rm_site_logo'])) { delete_upload($sl); $sl = ''; }
            if (!empty($_FILES['site_logo']['name'])) { $new = save_upload($_FILES['site_logo'], 900, true); delete_upload($sl); $sl = $new; }
        } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); redirect('/admin/config.php'); }
        set_setting('site_logo', $sl);
        foreach (['ui_color1', 'ui_color2'] as $k) if (is_hex((string)($_POST[$k] ?? ''))) set_setting($k, strtoupper($_POST[$k]));
        set_setting('tmdb_lang', preg_match('/^[a-z]{2}-[A-Z]{2}$/', (string)($_POST['tmdb_lang'] ?? '')) ? $_POST['tmdb_lang'] : 'pt-BR');
        set_setting('tmdb_region', preg_match('/^[A-Z]{2}$/', (string)($_POST['tmdb_region'] ?? '')) ? $_POST['tmdb_region'] : 'BR');
        set_setting('games_ttl', (string)max(30, min(3600, (int)($_POST['games_ttl'] ?? 120))));
        set_setting('allow_register', empty($_POST['allow_register']) ? '0' : '1');
        set_setting('trial_days', (string)max(0, min(365, (int)($_POST['trial_days'] ?? 3))));
        set_setting('expired_msg', mb_substr(trim((string)($_POST['expired_msg'] ?? '')), 0, 300));

        $off = [];
        foreach (LAYOUTS as $kind => $list) foreach ($list as $id => $_) if (empty($_POST['layout']["$kind:$id"])) $off[] = "$kind:$id";
        set_setting('layouts_off', json_encode($off));

        $db = [];
        foreach (['primary', 'secondary', 'bg', 'text'] as $k) if (is_hex((string)($_POST['b_' . $k] ?? ''))) $db[$k] = strtoupper($_POST['b_' . $k]);
        $db['cta'] = mb_substr(trim((string)($_POST['b_cta'] ?? '')), 0, 34);
        set_setting('default_brand', json_encode($db, JSON_UNESCAPED_UNICODE));
        flash('Configurações salvas.');
    }
    redirect('/admin/config.php');
}

$off = json_decode((string)setting('layouts_off', '[]'), true) ?: [];
$db = array_merge(BRAND_DEFAULTS, json_decode((string)setting('default_brand', '{}'), true) ?: []);
$v = fn(string $k) => e(setting($k, ''));
page_start('Configurações', 'a-config', $me);
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="two" style="align-items:start;gap:18px">
    <div>
      <div class="card">
        <h2>Sistema</h2>
        <label class="fld"><span>Nome do sistema</span><input type="text" name="site_name" maxlength="40" value="<?= $v('site_name') ?>"></label>
        <label class="fld"><span>Link de suporte / renovação</span><input type="text" name="support_link" value="<?= $v('support_link') ?>" placeholder="https://wa.me/5511999999999"><em>Mostrado ao usuário quando o plano está perto de vencer ou expirado.</em></label>
        <div class="fld"><span>Cor do sistema (botões, destaques e bordas do painel)</span>
          <div class="colors" style="max-width:260px">
            <label class="c-color"><input type="color" name="ui_color1" value="<?= e(setting('ui_color1', '#F5C518')) ?>"><span>Principal</span></label>
            <label class="c-color"><input type="color" name="ui_color2" value="<?= e(setting('ui_color2', '#FF9D00')) ?>"><span>Degradê</span></label>
          </div><em>Muda a aparência do painel para todos. Não altera as cores dos banners, que vêm da marca de cada usuário.</em></div>
        <label class="fld"><span>Logo do sistema <?= setting('site_logo', '') ? '(já enviada)' : '' ?></span><input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp"><em>Aparece no menu e no topo do painel inicial. PNG com fundo transparente fica melhor.</em></label>
        <?php if (setting('site_logo', '')): ?><label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="rm_site_logo" value="1"><i></i><span>Remover a logo do sistema</span></label><?php endif; ?>
        <label class="fld"><span>Mensagem de plano expirado</span><textarea name="expired_msg" maxlength="300"><?= $v('expired_msg') ?></textarea></label>
      </div>
      <div class="card">
        <h2>Painel inicial e boas-vindas</h2>
        <label class="fld"><span>Frase do topo do painel</span><textarea name="dash_tagline" rows="2" maxlength="120"><?= e(setting('dash_tagline', "Crie conteúdos profissionais\nem poucos cliques.")) ?></textarea></label>
        <label class="fld"><span>Itens do cartão do plano (um por linha)</span><textarea name="dash_features" rows="5" maxlength="400"><?= e(setting('dash_features', DASH_FEATURES)) ?></textarea></label>
        <label class="fld"><span>Link do canal (Telegram, WhatsApp…)</span><input type="text" name="channel_link" value="<?= $v('channel_link') ?>" placeholder="https://t.me/seucanal"><em>Mostrado no primeiro acesso, no painel e na mensagem de boas-vindas.</em></label>
        <label class="fld"><span>Texto de boas-vindas no primeiro acesso</span><textarea name="welcome_panel" rows="4" maxlength="600"><?= e(setting('welcome_panel', WELCOME_PANEL)) ?></textarea></label>
        <label class="fld"><span>Mensagem para enviar ao criar um usuário</span><textarea name="welcome_msg" rows="10" maxlength="1200"><?= e(setting('welcome_msg', WELCOME_MSG)) ?></textarea>
          <em>Aparece pronta para copiar logo depois de criar a conta. Campos: {sistema} {link} {usuario} {senha} {validade} {nome} {canal}. Uma linha cujo campo esteja vazio é retirada.</em></label>
        <small>Os banners da faixa “Novo” são trocados em <a href="<?= url('/admin/conteudos.php') ?>">Conteúdos e Banners</a>.</small>
      </div>
      <div class="card">
        <h2>Cadastro público</h2>
        <label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="allow_register" value="1" <?= setting('allow_register') === '1' ? 'checked' : '' ?>><i></i><span>Permitir que visitantes criem conta de teste</span></label>
        <label class="fld" style="max-width:220px"><span>Dias de teste grátis</span><input type="number" name="trial_days" min="0" max="365" value="<?= $v('trial_days') ?>"></label>
      </div>
      <div class="card">
        <h2>Marca padrão <small>para novos usuários</small></h2>
        <div class="colors" style="margin-bottom:14px">
          <?php foreach (['primary' => 'Destaque', 'secondary' => 'Secundária', 'bg' => 'Fundo', 'text' => 'Texto'] as $k => $l): ?>
            <label class="c-color"><input type="color" name="b_<?= $k ?>" value="<?= e($db[$k]) ?>"><span><?= $l ?></span></label>
          <?php endforeach; ?>
        </div>
        <label class="fld"><span>Texto padrão do botão (CTA)</span><input type="text" name="b_cta" maxlength="34" value="<?= e($db['cta']) ?>"></label>
      </div>
    </div>
    <div>
      <div class="card">
        <h2>TMDB <small>filmes e séries</small></h2>
        <p style="color:var(--mut);margin-top:0;font-size:12.500px">Fonte das capas, sinopses e destaques. O aviso de origem deixou de aparecer para os usuários; os termos do TMDB pedem que quem usa a API dê o crédito, então essa decisão fica por sua conta.</p>
        <label class="fld"><span>Chave da API (v3)</span><input type="text" name="tmdb_key" value="<?= $v('tmdb_key') ?>" autocomplete="off"></label>
        <label class="fld"><span>Token de leitura (v4)</span><textarea class="code" name="tmdb_token" rows="4" autocomplete="off"><?= $v('tmdb_token') ?></textarea><em>Se preenchido, o token é usado no lugar da chave.</em></label>
        <div class="two">
          <label class="fld"><span>Idioma</span><input type="text" name="tmdb_lang" value="<?= $v('tmdb_lang') ?>" placeholder="pt-BR"></label>
          <label class="fld"><span>Região</span><input type="text" name="tmdb_region" value="<?= $v('tmdb_region') ?>" placeholder="BR"></label>
        </div>
      </div>
      <div class="card">
        <h2>Jogos</h2>
        <label class="fld" style="max-width:260px"><span>Cache das APIs de jogos (segundos)</span><input type="number" name="games_ttl" min="30" max="3600" value="<?= $v('games_ttl') ?>"></label>
        <small>As fontes de jogos são gerenciadas em <a href="<?= url('/admin/apis.php') ?>">APIs de Jogos</a>.</small>
      </div>
      <div class="card">
        <h2>Layouts disponíveis para os usuários</h2>
        <?php foreach (['movie' => 'Filmes e Séries', 'games' => 'Jogos do Dia', 'promo' => 'Marketing'] as $kind => $label): ?>
          <div class="fld"><span><?= $label ?></span>
            <div class="checks">
              <?php foreach (LAYOUTS[$kind] as $id => $name): ?>
                <label class="c-check"><input type="checkbox" name="layout[<?= "$kind:$id" ?>]" value="1" <?= in_array("$kind:$id", $off, true) ? '' : 'checked' ?>><i></i><span><?= e($name) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <button class="btn gold">Salvar configurações</button>
</form>
<div class="card" style="margin-top:18px">
  <h2>Manutenção</h2>
  <div class="row wrap">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="tmdb"><button class="btn">Testar conexão com o TMDB</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="cache"><button class="btn">Limpar cache (dados e imagens)</button></form>
  </div>
</div>
<?php page_end();
