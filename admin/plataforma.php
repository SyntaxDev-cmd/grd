<?php
// "Meu Gerador": o Ultra Master configura a marca e as mensagens da plataforma dele.
// Não há aqui nada das APIs nem da configuração do sistema base — isso é só do administrador.
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard_owner();
if ($me['role'] !== 'ultra') redirect('/admin/config.php');
$tid = (int)$me['id'];

if (is_post()) {
    csrf_check();
    try {
        $name = mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 40);
        set_setting('site_name', $name !== '' ? $name : ($me['name'] ?: $me['username']));
        foreach (['support_link', 'channel_link'] as $k) {
            $v = trim((string)($_POST[$k] ?? ''));
            set_setting($k, $v === '' || preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 300) : '');
        }
        set_setting('dash_tagline', mb_substr(trim((string)($_POST['dash_tagline'] ?? '')), 0, 120));
        set_setting('dash_features', mb_substr(trim((string)($_POST['dash_features'] ?? '')), 0, 400) ?: DASH_FEATURES);
        set_setting('welcome_panel', mb_substr(trim((string)($_POST['welcome_panel'] ?? '')), 0, 600) ?: WELCOME_PANEL);
        set_setting('welcome_msg', mb_substr(trim((string)($_POST['welcome_msg'] ?? '')), 0, 1200) ?: WELCOME_MSG);
        foreach (['ui_color1', 'ui_color2'] as $k) if (is_hex((string)($_POST[$k] ?? ''))) set_setting($k, strtoupper($_POST[$k]));
        $sl = (string)setting('site_logo', '');
        if (!empty($_POST['rm_site_logo'])) { delete_upload($sl); $sl = ''; }
        if (!empty($_FILES['site_logo']['name'])) { $new = save_upload($_FILES['site_logo'], 900, true); delete_upload($sl); $sl = $new; }
        set_setting('site_logo', $sl);
        flash('Plataforma atualizada.');
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('/admin/plataforma.php');
}
$v = fn(string $k, string $d = '') => e((string)setting($k, $d));
$link = tenant_link($tid);
$q = quota($me);
$hasTok = trim((string)setting('mp_token', '')) !== '';
$nPlans = (int)db()->query('SELECT COUNT(*) FROM plans WHERE active = 1 AND tenant_id = ' . $tid)->fetchColumn();
page_start('Meu Gerador', 'a-plat', $me);
?>
<div class="card" style="border-color:color-mix(in srgb, var(--gold) 45%, transparent)">
  <h2>Link da sua plataforma</h2>
  <p style="color:var(--mut);margin-top:0">Divulgue este endereço. Quem entra por ele vê a sua marca, cria conta na sua base e paga no seu Mercado Pago. Seus clientes e pagamentos não se misturam com os de outras plataformas.</p>
  <div class="two">
    <label class="fld"><span>Entrar</span><input type="text" readonly value="<?= e($link) ?>" onclick="this.select()"></label>
    <label class="fld"><span>Criar conta (teste grátis)</span><input type="text" readonly value="<?= e($link) ?>/cadastro" onclick="this.select()"></label>
  </div>
  <div class="stats" style="margin:6px 0 0">
    <div class="stat hl"><b><?= $q['users_free'] ?></b><span>Usuários livres de <?= $q['users_max'] ?></span></div>
    <div class="stat"><b><?= $q['res_used'] ?> / <?= $q['res_max'] ?></b><span>Masters e revendas</span></div>
    <div class="stat"><b><?= $nPlans ?></b><span>Planos à venda</span></div>
    <div class="stat"><b><?= $hasTok ? 'Ligado' : 'Desligado' ?></b><span>PIX do Mercado Pago</span></div>
  </div>
  <?php if (!$hasTok || !$nPlans): ?><div class="alert warn" style="margin:14px 0 0">Para vender: <?= !$nPlans ? 'crie seus planos' : '' ?><?= !$nPlans && !$hasTok ? ' e ' : '' ?><?= !$hasTok ? 'informe o token do seu Mercado Pago' : '' ?> em <a href="<?= url('/admin/planos.php') ?>">Planos e Pagamentos</a>.</div><?php endif; ?>
</div>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="two" style="align-items:start;gap:18px">
    <div class="card">
      <h2>Marca da plataforma</h2>
      <label class="fld"><span>Nome</span><input type="text" name="site_name" maxlength="40" value="<?= $v('site_name') ?>"></label>
      <label class="fld"><span>Logo <?= setting('site_logo', '') ? '(já enviada)' : '' ?></span><input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp"><em>Aparece no menu, na tela de entrada e no painel dos seus clientes.</em></label>
      <?php if (setting('site_logo', '')): ?><label class="c-check" style="margin-bottom:12px"><input type="checkbox" name="rm_site_logo" value="1"><i></i><span>Remover a logo</span></label><?php endif; ?>
      <div class="fld"><span>Cores do painel</span>
        <div class="colors" style="max-width:260px">
          <label class="c-color"><input type="color" name="ui_color1" value="<?= $v('ui_color1', '#F5C518') ?>"><span>Principal</span></label>
          <label class="c-color"><input type="color" name="ui_color2" value="<?= $v('ui_color2', '#FF9D00') ?>"><span>Degradê</span></label>
        </div></div>
      <label class="fld"><span>Link de suporte / renovação</span><input type="text" name="support_link" value="<?= $v('support_link') ?>" placeholder="https://wa.me/5511999999999"></label>
      <label class="fld"><span>Link do canal de novidades</span><input type="text" name="channel_link" value="<?= $v('channel_link') ?>" placeholder="https://t.me/seucanal"></label>
    </div>
    <div class="card">
      <h2>Painel inicial e boas-vindas</h2>
      <label class="fld"><span>Frase do topo do painel</span><textarea name="dash_tagline" rows="2" maxlength="120"><?= $v('dash_tagline', "Crie conteúdos profissionais\nem poucos cliques.") ?></textarea></label>
      <label class="fld"><span>Itens do cartão do plano (um por linha)</span><textarea name="dash_features" rows="5" maxlength="400"><?= $v('dash_features', DASH_FEATURES) ?></textarea></label>
      <label class="fld"><span>Texto de boas-vindas no primeiro acesso</span><textarea name="welcome_panel" rows="4" maxlength="600"><?= $v('welcome_panel', WELCOME_PANEL) ?></textarea></label>
      <label class="fld"><span>Mensagem para enviar ao criar um cliente</span><textarea name="welcome_msg" rows="9" maxlength="1200"><?= $v('welcome_msg', WELCOME_MSG) ?></textarea>
        <em>Campos: {sistema} {link} {usuario} {senha} {validade} {nome} {canal}.</em></label>
    </div>
  </div>
  <button class="btn gold">Salvar plataforma</button>
</form>
<?php page_end();
