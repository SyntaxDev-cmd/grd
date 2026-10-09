<?php
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
if (user()) redirect('/painel.php');
// Link de uma plataforma (/p/nome): a tela assume a marca dela e o cadastro entra nela. ?t=base volta ao sistema base.
if (isset($_GET['t'])) {
    $tn = $_GET['t'] === 'base' ? null : tenant_by_slug((string)$_GET['t']);
    if ($tn) $_SESSION['tenant'] = (int)$tn['id']; else unset($_SESSION['tenant']);
}

$err = '';
if (is_post()) {
    csrf_check();
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - 900]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) {
        $err = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
    } else {
        $login = trim((string)($_POST['login'] ?? ''));
        $st = $pdo->prepare("SELECT * FROM users WHERE username = ? OR (email <> '' AND email = ? COLLATE NOCASE) LIMIT 1");
        $st->execute([$login, $login]);
        $u = $st->fetch();
        // Conta de outra plataforma não entra por este link (as bases de clientes não se misturam). O admin entra por qualquer um.
        $here = !empty($_SESSION['tenant']) ? (int)$_SESSION['tenant'] : null;
        if ($u && $u['role'] !== 'admin' && $here !== null && brand_tid($u) !== $here) $u = null;
        if ($u && password_verify((string)($_POST['pass'] ?? ''), $u['pass'])) {
            if ($u['status'] !== 'active') {
                $err = 'Sua conta está bloqueada. Fale com o suporte.';
            } else {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                $_SESSION['tenant'] = brand_tid($u); // ao sair, a tela de login continua com a marca da plataforma da conta
                if ($u['last_login'] === null) $_SESSION['first_login'] = 1; // mostra as boas-vindas no painel
                $pdo->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([now(), $u['id']]);
                $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                redirect($u['role'] === 'admin' ? '/admin/' : '/painel.php');
            }
        } else {
            $pdo->prepare('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)')->execute([$ip, time()]);
            $err = 'Usuário ou senha incorretos.';
        }
    }
}
// Instalação nova (inclusive cópia recém-ativada): o admin ainda está com a senha padrão. Mostra o aviso até ser trocada.
$defAdmin = false;
if (!$err && empty($_SESSION['tenant'])) {
    $st = db()->prepare("SELECT pass FROM users WHERE username = 'admin' AND role = 'admin'");
    $st->execute();
    $ap = $st->fetchColumn();
    $defAdmin = $ap !== false && password_verify('admin', (string)$ap);
}
head('Entrar');
?>
<body>
<div class="auth">
  <form class="auth-box" method="post" autocomplete="on">
    <div class="brand"><span class="brand-mark"><?= e(mb_strtoupper(mb_substr((string)setting('site_name'), 0, 1))) ?></span><span><?= e(setting('site_name')) ?></span></div>
    <p class="sub">Banners profissionais de filmes, séries, jogos e divulgação<br><b style="color:var(--gold)"><?= e(models_claim()) ?></b></p>
    <?php if ($defAdmin): ?><div class="alert warn" style="text-align:left">Primeiro acesso — entre com:<br><b>usuário: admin &nbsp;·&nbsp; senha: admin</b><br>Troque a senha em <b>Minha Conta</b> logo após entrar.</div><?php endif; ?>
    <?php if ($f = flash()): ?><div class="alert <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="fld"><span>Usuário ou e-mail</span><input type="text" name="login" required autofocus autocomplete="username" value="<?= e($_POST['login'] ?? '') ?>"></label>
    <label class="fld"><span>Senha</span><input type="password" name="pass" required autocomplete="current-password"></label>
    <button class="btn gold">Entrar</button>
    <?php if (setting('allow_register') === '1'): ?>
      <div class="foot">Ainda não tem conta? <a href="<?= url('/registrar.php') ?>">Criar conta grátis</a></div>
    <?php endif; ?>
  </form>
</div>
</body>
</html>
