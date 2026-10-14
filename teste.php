<?php
/*
 * Teste Rápido — cria uma conta de teste grátis em 1 clique, sem pedir telefone.
 * Cada revenda/master/ultra/admin tem seu próprio link: /t/CODIGO (ou teste.php?r=CODIGO).
 * Quem entra pelo link de alguém fica atribuído a essa pessoa (indicação) e, no caso de
 * plataforma própria (Ultra Master), já entra dentro da plataforma dela.
 */
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
if (user()) redirect('/painel.php');

$pdo = db();

// Dono do link (por código de indicação). Também aceita ?t=slug de uma plataforma.
$owner = null;
if (isset($_GET['r']) && preg_match('/^[A-Za-z0-9]{4,12}$/', (string)$_GET['r'])) {
    $st = $pdo->prepare("SELECT * FROM users WHERE ref_code = ? AND status = 'active'");
    $st->execute([strtoupper((string)$_GET['r'])]);
    $owner = $st->fetch() ?: null;
}
if (!$owner && isset($_GET['t']) && $_GET['t'] !== 'base') {
    $owner = tenant_by_slug((string)$_GET['t']) ?: null;
}
// Define a plataforma em vigor (marca + regras) a partir do dono do link
$tid = $owner ? brand_tid($owner) : null;
if ($tid) $_SESSION['tenant'] = $tid; else unset($_SESSION['tenant']);
$tid = tenant_ctx();

// Respeita a configuração de cadastro público da plataforma em vigor
if (setting('allow_register') !== '1') { flash('Os testes grátis estão fechados no momento. Fale com o suporte.', 'err'); redirect('/login.php'); }

// Plataforma de Ultra Master: precisa ter o plano ativo e cota livre
if ($tid) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$tid]);
    $pOwner = $st->fetch() ?: null;
    if (!$pOwner || access_state($pOwner)['locked'] || quota($pOwner)['users_free'] < 1) { flash('Os testes desta plataforma estão fechados no momento.', 'err'); redirect('/login.php'); }
}

$days  = max(1, (int)setting('trial_days', '1'));
$limit = max(1, (int)setting('trial_limit', '15'));
$err = '';
$created = null; // ['user'=>..., 'pass'=>...] após criar

/** Usuário curto e único a partir do nome (ou "teste"). */
function trial_username(PDO $pdo, string $name): string
{
    $base = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    $base = $base !== '' ? substr($base, 0, 16) : 'teste';
    $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($i = 0; $i < 40; $i++) {
        $try = $base . random_int(10, 9999);
        $st->execute([$try]);
        if (!$st->fetch()) return $try;
    }
    return $base . bin2hex(random_bytes(3));
}

/** Senha curta, legível e sem caracteres ambíguos. */
function trial_password(): string
{
    $al = 'abcdefghijkmnpqrstuvwxyz23456789'; // sem l, o, 0, 1
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $al[random_int(0, strlen($al) - 1)];
    return $s;
}

if (is_post()) {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Anti-robô: campo escondido que precisa ficar vazio
    if (trim((string)($_POST['website'] ?? '')) !== '') redirect('/login.php');
    $pdo->prepare('DELETE FROM signups WHERE ts < ?')->execute([time() - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM signups WHERE ip = ?');
    $st->execute([$ip]);
    $tooMany = (int)$st->fetchColumn() >= 3;

    $name  = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $email = trim((string)($_POST['email'] ?? ''));
    if ($tooMany) {
        $err = 'Você já criou testes demais a partir desta conexão hoje. Tente amanhã ou fale com o suporte.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Esse e-mail não parece válido. Deixe em branco ou corrija.';
    } else {
        // E-mail é opcional; se vier repetido, manda a pessoa fazer login
        if ($email !== '') {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? COLLATE NOCASE');
            $st->execute([$email]);
            if ($st->fetch()) { $err = 'Esse e-mail já tem conta. É só entrar com ele.'; }
        }
        if (!$err) {
            $username = trial_username($pdo, $name);
            $pass = trial_password();
            $pdo->prepare('INSERT INTO users (username, name, email, pass, expires_at, notes, paid, referred_by, created_at, last_login, tenant_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)')->execute([
                $username, $name, $email, password_hash($pass, PASSWORD_DEFAULT),
                date('Y-m-d H:i:s', time() + $days * 86400), 'Teste rápido (1 clique) pelo site', $owner['id'] ?? null, now(), now(), $tid,
            ]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO signups (ip, ts) VALUES (?, ?)')->execute([$ip, time()]);
            // Entra direto na conta recém-criada
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            if ($tid) $_SESSION['tenant'] = $tid; else unset($_SESSION['tenant']);
            $_SESSION['first_login'] = 1;
            $_SESSION['trial_show'] = ['user' => $username, 'pass' => $pass]; // mostra o acesso também no painel
            $created = ['user' => $username, 'pass' => $pass];
        }
    }
}

$site = (string)setting('site_name');
head('Teste grátis');
?>
<body>
<div class="auth">
  <form class="auth-box" method="post" autocomplete="off">
    <div class="brand"><span class="brand-mark"><?= e(mb_strtoupper(mb_substr($site, 0, 1))) ?></span><span><?= e($site) ?></span></div>

    <?php if ($created): ?>
      <p class="sub"><b style="color:var(--gold)">Pronto! Seu acesso de teste está liberado.</b><br>Anote seu usuário e senha abaixo — você já está dentro.</p>
      <div class="alert ok" style="text-align:left">Guarde este acesso para entrar de novo depois. Se quiser, troque a senha em <b>Minha Conta</b>.</div>
      <label class="fld"><span>Seu usuário</span>
        <span class="row" style="gap:8px"><input type="text" id="cU" readonly value="<?= e($created['user']) ?>"><button type="button" class="btn sm" data-copy="cU">Copiar</button></span>
      </label>
      <label class="fld"><span>Sua senha</span>
        <span class="row" style="gap:8px"><input type="text" id="cP" readonly value="<?= e($created['pass']) ?>"><button type="button" class="btn sm" data-copy="cP">Copiar</button></span>
      </label>
      <a class="btn gold" href="<?= url('/painel.php') ?>">Entrar no painel →</a>
      <div class="foot">Dica: salve este acesso no bloco de notas ou tire um print.</div>
    <?php else: ?>
      <p class="sub">Crie seu acesso de teste <b style="color:var(--gold)">grátis por <?= $days ?> dia<?= $days === 1 ? '' : 's' ?></b>.<br>Gere até <?= $limit ?> banners. <b>Sem telefone, sem cartão.</b></p>
      <?php if ($owner): ?><div class="alert ok">Convite de <b><?= e($owner['name'] ?: $owner['username']) ?></b>.</div><?php endif; ?>
      <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
      <ul class="trial-perks">
        <li>✓ Liberação na hora, em 1 clique</li>
        <li>✓ Todos os modelos de banner e vídeo</li>
        <li>✓ Sem número de telefone</li>
      </ul>
      <?= csrf_field() ?>
      <label class="fld"><span>Seu nome <small>(opcional)</small></span><input type="text" name="name" maxlength="60" value="<?= e($_POST['name'] ?? '') ?>"></label>
      <label class="fld"><span>E-mail <small>(opcional, para recuperar o acesso)</small></span><input type="email" name="email" autocomplete="off" value="<?= e($_POST['email'] ?? '') ?>"></label>
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <button class="btn gold">Criar meu acesso grátis agora</button>
      <div class="foot">Já tem conta? <a href="<?= url('/login.php') ?>">Entrar</a></div>
    <?php endif; ?>
  </form>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  const i = document.getElementById(b.dataset.copy);
  try { await navigator.clipboard.writeText(i.value); } catch (e) { i.select(); document.execCommand('copy'); }
  const t = b.textContent; b.textContent = 'Copiado!'; setTimeout(() => { b.textContent = t; }, 1600);
}));
</script>
</body>
</html>
