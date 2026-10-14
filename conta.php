<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard(false, true);
if (is_post()) {
    csrf_check();
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $email = trim((string)($_POST['email'] ?? ''));
    $new = (string)($_POST['new'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('E-mail inválido.', 'err');
    elseif ($new !== '' && !password_verify((string)($_POST['cur'] ?? ''), $u['pass'])) flash('Senha atual incorreta.', 'err');
    elseif ($new !== '' && strlen($new) < 6) flash('A nova senha deve ter pelo menos 6 caracteres.', 'err');
    else {
        db()->prepare('UPDATE users SET name = ?, email = ?, pass = ? WHERE id = ?')
            ->execute([$name, $email, $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : $u['pass'], $u['id']]);
        if ($new !== '') @unlink(DATA . '/ADMIN_INICIAL.txt');
        flash('Dados atualizados.');
    }
    redirect('/conta.php');
}
page_start('Minha Conta', 'conta', $u);
?>
<form class="card" method="post" style="max-width:560px">
  <?= csrf_field() ?>
  <h2>Dados de acesso</h2>
  <label class="fld"><span>Usuário</span><input type="text" value="<?= e($u['username']) ?>" disabled></label>
  <label class="fld"><span>Nome</span><input type="text" name="name" maxlength="60" value="<?= e($u['name']) ?>"></label>
  <label class="fld"><span>E-mail</span><input type="email" name="email" value="<?= e($u['email']) ?>"></label>
  <h2 style="margin-top:20px">Trocar senha <small>deixe em branco para manter</small></h2>
  <div class="two">
    <label class="fld"><span>Senha atual</span><input type="password" name="cur" autocomplete="current-password"></label>
    <label class="fld"><span>Nova senha</span><input type="password" name="new" minlength="6" autocomplete="new-password"></label>
  </div>
  <button class="btn gold">Salvar</button>
</form>
<?php
$code = ref_code($u);
$trialLink = site_link() . 't/' . $code;
$rs = db()->prepare('SELECT COUNT(*) n, COALESCE(SUM(paid), 0) p FROM users WHERE referred_by = ?');
$rs->execute([$u['id']]);
$rs = $rs->fetch();
?>
<div class="card" style="max-width:560px">
  <h2>Seu link de teste rápido</h2>
  <p style="color:var(--mut);margin-top:0">Mande este link para o cliente. Ele cria o acesso de teste em <b>1 clique</b>, sem telefone e sem cartão — e já entra no sistema. Todo mundo que entrar por ele fica ligado à sua conta.</p>
  <label class="fld"><span>Link de teste (1 clique)</span><input type="text" id="trialLink" readonly value="<?= e($trialLink) ?>"></label>
  <div class="row wrap">
    <button type="button" class="btn gold" id="trialCopy">Copiar link</button>
    <a class="btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Testa grátis esse gerador de banners de filmes, séries e jogos — cria teu acesso em 1 clique, sem telefone: ' . $trialLink) ?>">Enviar pelo WhatsApp</a>
  </div>
  <div class="stats" style="margin:16px 0 0">
    <div class="stat"><b><?= (int)$rs['n'] ?></b><span>Testes pelo seu link</span></div>
    <div class="stat hl"><b><?= (int)$rs['p'] ?></b><span>Já assinaram</span></div>
  </div>
</div>
<script>
document.getElementById('trialCopy').addEventListener('click', async ev => {
  const i = document.getElementById('trialLink');
  try { await navigator.clipboard.writeText(i.value); } catch (e) { i.select(); document.execCommand('copy'); }
  ev.target.textContent = 'Link copiado!'; setTimeout(() => { ev.target.textContent = 'Copiar link'; }, 1800);
});
</script>
<?php if (!is_admin($u)):
    $link = site_link() . 'registrar.php?ref=' . $code;
?>
<div class="card" style="max-width:560px">
  <h2>Indique e ganhe desconto</h2>
  <p style="color:var(--mut);margin-top:0">Quem se cadastrar pelo seu link ganha <b><?= (int)setting('ref_new_pct', '10') ?>%</b> de desconto na primeira assinatura. A cada indicado que assinar, você ganha um cupom de <b><?= (int)setting('ref_owner_pct', '10') ?>%</b> para a sua próxima renovação.</p>
  <label class="fld"><span>Seu link de indicação</span><input type="text" id="refLink" readonly value="<?= e($link) ?>"></label>
  <div class="row wrap">
    <button type="button" class="btn gold" id="refCopy">Copiar link</button>
    <a class="btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Crie banners profissionais de filmes, séries e jogos. Teste grátis: ' . $link) ?>">Enviar pelo WhatsApp</a>
  </div>
  <div class="stats" style="margin:16px 0 0">
    <div class="stat"><b><?= (int)$rs['n'] ?></b><span>Cadastros pelo seu link</span></div>
    <div class="stat"><b><?= (int)$rs['p'] ?></b><span>Já assinaram</span></div>
    <div class="stat hl"><b><?= (int)$u['ref_coupons'] ?></b><span>Cupons disponíveis</span></div>
  </div>
</div>
<script>
document.getElementById('refCopy').addEventListener('click', async ev => {
  const i = document.getElementById('refLink');
  try { await navigator.clipboard.writeText(i.value); } catch (e) { i.select(); document.execCommand('copy'); }
  ev.target.textContent = 'Link copiado!'; setTimeout(() => { ev.target.textContent = 'Copiar link'; }, 1800);
});
</script>
<?php endif; ?>
<?php page_end();
