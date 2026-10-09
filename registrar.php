<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
if (user()) redirect('/painel.php');
if (isset($_GET['t'])) {
    $tn = $_GET['t'] === 'base' ? null : tenant_by_slug((string)$_GET['t']);
    if ($tn) $_SESSION['tenant'] = (int)$tn['id']; else unset($_SESSION['tenant']);
}

// Link de indicação: guarda o código na sessão até o cadastro terminar
if (isset($_GET['ref']) && preg_match('/^[A-Za-z0-9]{4,12}$/', (string)$_GET['ref'])) $_SESSION['ref'] = strtoupper($_GET['ref']);
$pdo = db();
$refUser = null;
if (!empty($_SESSION['ref'])) {
    $st = $pdo->prepare("SELECT * FROM users WHERE ref_code = ? AND status = 'active'");
    $st->execute([$_SESSION['ref']]);
    $refUser = $st->fetch() ?: null;
    // Quem chega por indicação entra na mesma plataforma de quem indicou
    if ($refUser) { $rt = brand_tid($refUser); if ($rt) $_SESSION['tenant'] = $rt; else unset($_SESSION['tenant']); }
}
$tid = tenant_ctx();
if (setting('allow_register') !== '1') redirect('/login.php');
// Plataforma de um Ultra Master: respeita o limite de usuários que o admin deu a ele, e o plano dele precisa estar ativo
$owner = null;
if ($tid) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$tid]);
    $owner = $st->fetch() ?: null;
    if (!$owner || access_state($owner)['locked'] || quota($owner)['users_free'] < 1) { flash('Os cadastros desta plataforma estão fechados no momento.', 'err'); redirect('/login.php'); }
}

$err = '';
$days = max(1, (int)setting('trial_days', '1'));
$limit = (int)setting('trial_limit', '15');
if (is_post()) {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare('DELETE FROM signups WHERE ts < ?')->execute([time() - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM signups WHERE ip = ?');
    $st->execute([$ip]);
    $tooMany = (int)$st->fetchColumn() >= 3;
    $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    $st->execute([$username]);
    $taken = (bool)$st->fetch();
    $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? COLLATE NOCASE');
    $st->execute([$email]);
    if ($tooMany) $err = 'Muitos cadastros a partir desta conexão hoje. Tente novamente amanhã ou fale com o suporte.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) $err = 'Usuário deve ter de 3 a 30 letras, números, ponto, hífen ou sublinhado.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Informe um e-mail válido.';
    elseif (strlen($pass) < 6) $err = 'A senha deve ter pelo menos 6 caracteres.';
    elseif ($taken) $err = 'Este usuário já existe.';
    elseif ($st->fetch()) $err = 'Este e-mail já tem cadastro. Faça login.';
    else {
        $pdo->prepare('INSERT INTO users (username, name, email, pass, expires_at, notes, paid, referred_by, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)')->execute([
            $username, mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60), $email, password_hash($pass, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s', time() + $days * 86400), 'Cadastro pelo site (teste grátis)', $refUser['id'] ?? null, now(), $tid,
        ]);
        $pdo->prepare('INSERT INTO signups (ip, ts) VALUES (?, ?)')->execute([$ip, time()]);
        unset($_SESSION['ref']);
        flash('Conta criada! Seu teste grátis já está valendo. Faça login para começar.');
        redirect('/login.php');
    }
}
head('Criar conta');
?>
<body>
<div class="auth">
  <form class="auth-box" method="post">
    <div class="brand"><span class="brand-mark"><?= e(mb_strtoupper(mb_substr((string)setting('site_name'), 0, 1))) ?></span><span><?= e(setting('site_name')) ?></span></div>
    <p class="sub"><b style="color:var(--gold)">Teste grátis por <?= $days ?> dia<?= $days === 1 ? '' : 's' ?></b><br>Gere até <?= $limit ?> banners, sem cartão.</p>
    <?php if ($refUser): ?><div class="alert ok">Você foi indicado por <b><?= e($refUser['name'] ?: $refUser['username']) ?></b>: ganha <?= (int)setting('ref_new_pct', '10') ?>% de desconto na primeira assinatura.</div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="fld"><span>Nome</span><input type="text" name="name" maxlength="60" value="<?= e($_POST['name'] ?? '') ?>"></label>
    <label class="fld"><span>Usuário</span><input type="text" name="username" required maxlength="30" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label class="fld"><span>E-mail</span><input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label class="fld"><span>Senha</span><input type="password" name="pass" required minlength="6" autocomplete="new-password"></label>
    <button class="btn gold">Criar conta grátis</button>
    <div class="foot">Já tem conta? <a href="<?= url('/login.php') ?>">Entrar</a></div>
  </form>
</div>
</body>
</html>
