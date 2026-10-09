<?php
// Gestão de contas. Admin vê tudo; master vê suas revendas e usuários; revenda vê seus usuários.
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/ui.php';
$me = guard();
if (!can_manage($me)) redirect('/painel.php');
if (access_state($me)['locked']) { flash('Seu plano venceu. Renove para voltar a gerenciar seus clientes.', 'err'); redirect('/assinar.php'); }
$pdo = db();
$admin = is_admin($me);
$roles = creatable_roles($me);

function find_user(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** O ator pode alterar esta conta? Precisa estar abaixo dele na árvore e ter papel inferior (admin altera qualquer uma). */
function can_touch(array $me, ?array $t): bool
{
    if (!$t) return false;
    if ($me['role'] === 'admin') return true;
    return ROLE_RANK[$t['role']] < ROLE_RANK[$me['role']] && in_tree($me, $t);
}

/** Vencimento que o ator pode conceder: ninguém passa do próprio vencimento. Devolve mensagem de erro ou null. */
function exp_error(array $me, ?string $exp): ?string
{
    if ($me['role'] === 'admin' || $me['expires_at'] === null) return null;
    if ($exp === null || $exp > $me['expires_at']) return 'O vencimento não pode passar do seu próprio plano (' . fmt_date($me['expires_at']) . ').';
    return null;
}

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $t = $id ? find_user($id) : null;
    $back = '/admin/usuarios.php' . (!empty($_POST['q']) ? '?q=' . urlencode((string)$_POST['q']) : '');
    if ($id && !can_touch($me, $t)) { flash('Você não tem permissão sobre esta conta.', 'err'); redirect($back); }
    $self = $t && (int)$t['id'] === (int)$me['id'];

    if ($a === 'save') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['pass'] ?? '');
        $status = ($_POST['status'] ?? '') === 'blocked' ? 'blocked' : 'active';
        // Só o admin muda o papel de uma conta existente (trocar papel mexe nas cotas de toda a árvore)
        $role = ($t && !$admin) ? $t['role'] : (string)($_POST['role'] ?? 'user');
        $q = quota($me);
        $lu = max(0, min(100000, (int)($_POST['limit_users'] ?? 0)));
        $lr = max(0, min(100000, (int)($_POST['limit_resellers'] ?? 0)));
        if ($role === 'user' || $role === 'admin') { $lu = 0; $lr = 0; }
        if ($role === 'revenda') $lr = 0;
        // Endereço da plataforma (só para Ultra Master): letras, números e hífen
        $slug = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['slug'] ?? '')) ?: $username);

        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND id <> ?');
        $st->execute([$username, $id]);
        $err = '';
        if (!in_array($role, $t && $admin ? array_keys(ROLES) : $roles, true)) $err = 'Você não pode criar esse tipo de conta.';
        elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) $err = 'Usuário deve ter de 3 a 30 letras, números, ponto, hífen ou sublinhado.';
        elseif ($st->fetch()) $err = 'Já existe uma conta com esse login.';
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'E-mail inválido.';
        elseif (!$t && strlen($pass) < 6) $err = 'Defina uma senha com pelo menos 6 caracteres.';
        elseif ($pass !== '' && strlen($pass) < 6) $err = 'A senha deve ter pelo menos 6 caracteres.';
        elseif ($self && ($role !== $me['role'] || $status !== 'active')) $err = 'Você não pode rebaixar ou bloquear a própria conta.';
        if (!$err && $t && $t['role'] === 'ultra' && $role !== 'ultra') $err = 'Um Ultra Master não pode virar outro tipo de conta: a plataforma dele tem clientes, planos e pagamentos próprios. Se for o caso, exclua a conta.';
        if (!$err && $t && $t['role'] !== 'ultra' && $role === 'ultra' && $t['tenant_id']) $err = 'Esta conta pertence a uma plataforma e não pode virar Ultra Master.';
        if (!$err && $role === 'ultra') {
            $st = $pdo->prepare("SELECT tenant_id FROM tenant_settings WHERE k = 'slug' AND v = ? AND tenant_id <> ?");
            $st->execute([$slug, $id]);
            if (strlen($slug) < 2) $err = 'Informe o endereço da plataforma (ao menos 2 caracteres).';
            elseif ($st->fetch()) $err = 'Já existe uma plataforma com esse endereço.';
        }

        // Limites: o que a conta já usa não pode ficar acima do novo limite, e o ator só distribui o que tem livre
        if (!$err && $t && ROLE_RANK[$role] >= 1 && $role !== 'admin' && $t['role'] === $role) {
            $tq = quota($t);
            if ($lu < $tq['users_used']) $err = "Esta conta já usa {$tq['users_used']} usuários; o limite não pode ser menor que isso.";
            elseif ($role === 'master' && $lr < $tq['res_used']) $err = "Esta conta já tem {$tq['res_used']} revendas; o limite não pode ser menor que isso.";
        }
        if (!$err && !$admin) {
            if (!$t && $role === 'user' && $q['users_free'] < 1) $err = 'Seu limite de usuários acabou. Peça mais ao responsável pela sua conta.';
            if (!$t && $role === 'revenda' && $q['res_free'] < 1) $err = 'Seu limite de revendas acabou.';
            if (!$t && $role === 'master' && $q['res_free'] < 1) $err = 'Seu limite de masters e revendas acabou.';
            if ($role === 'revenda' || $role === 'master') {
                $free = $q['users_free'] + ($t ? (int)$t['limit_users'] : 0);
                if ($lu > $free) $err = "Você só tem $free usuários livres para repassar a esta conta.";
            }
        }

        // Validade: "meses" soma ao vencimento atual; data manual substitui; "vitalício" (só admin) remove o vencimento
        $exp = $t['expires_at'] ?? null;
        $mode = $_POST['plan'] ?? 'keep';
        $months = max(1, min($admin ? 120 : 12, (int)($_POST['months'] ?? 1)));
        if ($mode === 'months') $exp = add_months($exp, $months);
        elseif ($mode === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['date'] ?? ''))) $exp = $_POST['date'] . ' 23:59:59';
        elseif ($mode === 'life' && $admin) $exp = null;
        elseif (!$t) $exp = add_months(null, 1);
        if ($role === 'admin') $exp = null;
        if (!$err && ($mode !== 'keep' || !$t)) $err = (string)exp_error($me, $exp);

        if ($err) { flash($err, 'err'); redirect($t ? '/admin/usuarios.php?edit=' . $id : '/admin/usuarios.php?new=1'); }

        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
        $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 300);
        if ($t) {
            $pdo->prepare('UPDATE users SET username = ?, name = ?, email = ?, pass = ?, role = ?, status = ?, expires_at = ?, notes = ?, limit_users = ?, limit_resellers = ? WHERE id = ?')
                ->execute([$username, $name, $email, $pass !== '' ? password_hash($pass, PASSWORD_DEFAULT) : $t['pass'], $role, $status, $exp, $notes, $lu, $lr, $id]);
            if ($role === 'ultra') $pdo->prepare("INSERT INTO tenant_settings (tenant_id, k, v) VALUES (?, 'slug', ?) ON CONFLICT(tenant_id, k) DO UPDATE SET v = excluded.v")->execute([$id, $slug]);
            flash('Conta atualizada.');
        } else {
            // A conta nova nasce na plataforma de quem a criou (o Ultra Master é a própria plataforma; no sistema base fica vazio)
            $pdo->prepare('INSERT INTO users (username, name, email, pass, role, status, expires_at, notes, limit_users, limit_resellers, parent_id, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$username, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $status, $exp, $notes, $lu, $lr, $me['id'], now(), $role === 'ultra' ? null : brand_tid($me)]);
            $id = (int)$pdo->lastInsertId();
            flash(ROLES[$role] . " \"$username\" criado" . ($exp ? ' com acesso até ' . fmt_date($exp) . '.' : ' com acesso vitalício.'));
            // Mensagem pronta para enviar ao cliente (a senha só existe em texto neste momento)
            if ($role === 'ultra') {
                $ins = $pdo->prepare('INSERT INTO tenant_settings (tenant_id, k, v) VALUES (?, ?, ?) ON CONFLICT(tenant_id, k) DO UPDATE SET v = excluded.v');
                $ins->execute([$id, 'slug', $slug]);
                $ins->execute([$id, 'site_name', $name !== '' ? $name : $username]);
            }
            $_SESSION['welcome_msg'] = welcome_message(['sistema' => (string)setting('site_name'), 'link' => $role === 'ultra' ? site_link() . 'p/' . $slug : (brand_tid($me) ? tenant_link(brand_tid($me)) : site_link()), 'usuario' => $username, 'senha' => $pass,
                'validade' => $exp ? fmt_date($exp) : 'sem vencimento', 'nome' => $name, 'canal' => trim((string)setting('channel_link', ''))]);
        }
    } elseif ($t && $a === 'add' && $t['role'] !== 'admin') {
        $m = max(1, min(12, (int)($_POST['months'] ?? 1)));
        $exp = add_months($t['expires_at'], $m);
        if ($e = exp_error($me, $exp)) flash($e, 'err');
        else {
            $pdo->prepare('UPDATE users SET expires_at = ? WHERE id = ?')->execute([$exp, $id]);
            flash("+$m " . ($m === 1 ? 'mês' : 'meses') . " para {$t['username']}. Novo vencimento: " . fmt_date($exp) . '.');
        }
    } elseif ($t && $a === 'toggle' && !$self) {
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$t['status'] === 'active' ? 'blocked' : 'active', $id]);
        flash($t['status'] === 'active' ? 'Conta bloqueada. Quem está abaixo dela também perde o acesso.' : 'Conta desbloqueada.');
    } elseif ($t && $a === 'delete' && !$self) {
        $b = json_decode($t['brand'], true) ?: [];
        delete_upload((string)($b['logo'] ?? ''));
        delete_upload((string)($b['bgImage'] ?? ''));
        // Quem estava abaixo da conta excluída sobe para o responsável por ela
        $pdo->prepare('UPDATE users SET parent_id = ? WHERE parent_id = ?')->execute([$t['parent_id'], $id]);
        if ($t['role'] === 'ultra') {
            // Fim da plataforma: os clientes dela passam para o sistema base, os planos saem de venda e as configurações (inclusive o token) são apagadas
            $pdo->prepare('UPDATE users SET tenant_id = NULL WHERE tenant_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE plans SET active = 0 WHERE tenant_id = ?')->execute([$id]);
            foreach ($pdo->query('SELECT image FROM library WHERE tenant_id = ' . (int)$id) as $li) delete_upload((string)$li['image']);
            $pdo->prepare('DELETE FROM library WHERE tenant_id = ?')->execute([$id]);
            $lg = $pdo->prepare("SELECT v FROM tenant_settings WHERE tenant_id = ? AND (k = 'site_logo' OR k LIKE 'hub_img_%')");
            $lg->execute([$id]);
            foreach ($lg as $r) delete_upload((string)$r['v']);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = ?')->execute([$id]);
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM logs WHERE user_id = ?')->execute([$id]);
        flash('Conta excluída.');
    }
    redirect($back);
}

$edit = isset($_GET['edit']) ? find_user((int)$_GET['edit']) : null;
if ($edit && !can_touch($me, $edit)) $edit = null;
$form = $edit || (isset($_GET['new']) && $roles);
$q = trim((string)($_GET['q'] ?? ''));
$f = $_GET['f'] ?? '';
$where = []; $args = [];
if ($me['role'] === 'ultra') { $where[] = 'u.tenant_id = ?'; $args[] = $me['id']; }
elseif ($me['role'] === 'master') { $where[] = '(u.parent_id = ? OR u.parent_id IN (SELECT id FROM users WHERE parent_id = ?))'; array_push($args, $me['id'], $me['id']); }
elseif ($me['role'] === 'revenda') { $where[] = 'u.parent_id = ?'; $args[] = $me['id']; }
else $where[] = '1=1';
if ($q !== '') { $where[] = '(u.username LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
if ($f === 'active') { $where[] = "u.status = 'active' AND (u.expires_at IS NULL OR u.expires_at >= ?)"; $args[] = now(); }
if ($f === 'expired') { $where[] = 'u.expires_at IS NOT NULL AND u.expires_at < ?'; $args[] = now(); }
if ($f === 'blocked') $where[] = "u.status = 'blocked'";
if (isset(ROLES[$f])) { $where[] = 'u.role = ?'; $args[] = $f; }
$st = $pdo->prepare('SELECT u.*, p.username AS parent_name FROM users u LEFT JOIN users p ON p.id = u.parent_id WHERE ' . implode(' AND ', $where)
    . " ORDER BY CASE u.role WHEN 'admin' THEN 0 WHEN 'ultra' THEN 1 WHEN 'master' THEN 2 WHEN 'revenda' THEN 3 ELSE 4 END, u.id DESC LIMIT 500");
$st->execute($args);
$users = $st->fetchAll();
$myq = quota($me);
$tag = ['admin' => 'gold', 'ultra' => 'gold', 'master' => 'gold', 'revenda' => 'warn', 'user' => 'mut'];
$tslug = $edit && $edit['role'] === 'ultra' ? (string)tenant_setting((int)$edit['id'], 'slug', '') : '';

page_start($admin ? 'Usuários' : 'Meus Clientes', 'a-users', $me);
?>
<?php if (!empty($_SESSION['welcome_msg'])): $wm = (string)$_SESSION['welcome_msg']; unset($_SESSION['welcome_msg']); ?>
<div class="card" style="border-color:rgba(245,197,24,.4)">
  <h2>Mensagem de boas-vindas <small>copie e envie ao cliente</small></h2>
  <textarea id="wm" rows="11" readonly style="font-size:13px"><?= e($wm) ?></textarea>
  <div class="row" style="margin-top:10px">
    <button type="button" class="btn gold" id="wmCopy">Copiar mensagem</button>
    <small>A senha aparece só agora; depois de sair desta tela ela não pode mais ser vista.</small>
  </div>
</div>
<script>
document.getElementById('wmCopy').addEventListener('click', async ev => {
  const t = document.getElementById('wm');
  try { await navigator.clipboard.writeText(t.value); ev.target.textContent = 'Copiada!'; }
  catch (e) { t.select(); document.execCommand('copy'); ev.target.textContent = 'Copiada!'; }
});
</script>
<?php endif; ?>
<?php if (!$admin): ?>
<div class="stats">
  <div class="stat hl"><b><?= $myq['users_free'] ?></b><span>Usuários livres de <?= $myq['users_max'] ?></span></div>
  <div class="stat"><b><?= $myq['users_direct'] ?></b><span>Usuários criados por você</span></div>
  <?php if ($me['role'] === 'master' || $me['role'] === 'ultra'): ?>
    <div class="stat"><b><?= $myq['users_given'] ?></b><span>Usuários repassados</span></div>
    <div class="stat"><b><?= $myq['res_used'] ?> / <?= $myq['res_max'] ?></b><span><?= $me['role'] === 'ultra' ? 'Masters e revendas' : 'Revendas' ?></span></div>
  <?php endif; ?>
  <div class="stat"><b><?= $me['expires_at'] ? fmt_date($me['expires_at']) : '—' ?></b><span>Seu vencimento</span></div>
</div>
<?php endif; ?>

<?php if ($form):
    $er = $edit['role'] ?? ($roles[0] ?? 'user');
    $opts = $edit && !$admin ? [$edit['role']] : ($edit ? array_keys(ROLES) : $roles);
?>
<form class="card" method="post" style="max-width:780px" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Editar ' . e($edit['username']) : 'Nova conta' ?></h2>
  <div class="two">
    <label class="fld"><span>Tipo de conta</span>
      <select name="role" id="role" <?= count($opts) < 2 ? 'disabled' : '' ?>>
        <?php foreach ($opts as $r): ?><option value="<?= $r ?>" <?= $r === $er ? 'selected' : '' ?>><?= ROLES[$r] ?></option><?php endforeach; ?>
      </select>
      <?php if (count($opts) < 2): ?><input type="hidden" name="role" value="<?= e($opts[0]) ?>"><?php endif; ?>
    </label>
    <label class="fld"><span>Situação</span><select name="status"><option value="active">Ativo</option><option value="blocked" <?= ($edit['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Bloqueado</option></select></label>
    <label class="fld"><span>Usuário (login)</span><input type="text" name="username" required maxlength="30" value="<?= e($edit['username'] ?? '') ?>"></label>
    <label class="fld"><span>Senha<?= $edit ? ' (em branco = manter)' : '' ?></span><input type="text" name="pass" <?= $edit ? '' : 'required' ?> minlength="6" autocomplete="new-password"></label>
    <label class="fld"><span>Nome</span><input type="text" name="name" maxlength="60" value="<?= e($edit['name'] ?? '') ?>"></label>
    <label class="fld"><span>E-mail (opcional)</span><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
  </div>
  <div class="two" data-roles="ultra master revenda">
    <label class="fld"><span>Limite de usuários</span><input type="number" name="limit_users" min="0" max="100000" value="<?= (int)($edit['limit_users'] ?? 10) ?>">
      <em>Total de usuários que esta conta pode ter na árvore dela<?= $admin ? '' : '. Sai do seu saldo: você tem ' . ($myq['users_free'] + (int)($edit['limit_users'] ?? 0)) . ' para repassar' ?>.</em></label>
    <label class="fld" data-roles="ultra master"><span>Limite de revendas (no Ultra Master: masters + revendas)</span><input type="number" name="limit_resellers" min="0" max="100000" value="<?= (int)($edit['limit_resellers'] ?? 5) ?>">
      <em>Quantas revendas o master pode criar. O master divide o limite de usuários entre elas.</em></label>
  </div>
  <label class="fld" data-roles="ultra"><span>Endereço da plataforma</span><input type="text" name="slug" maxlength="40" value="<?= e($tslug) ?>" placeholder="minhamarca"><em>O Ultra Master recebe um gerador próprio em <?= e(site_link()) ?>p/<b>endereço</b>, com marca, planos, Mercado Pago e clientes separados. Em branco, usa o login dele.</em></label>
  <div class="three" data-roles="ultra master revenda user">
    <label class="fld"><span>Plano</span>
      <select name="plan" id="plan">
        <?php if ($edit): ?><option value="keep">Manter (<?= $edit['expires_at'] ? 'até ' . fmt_date($edit['expires_at']) : 'vitalício' ?>)</option><?php endif; ?>
        <option value="months"><?= $edit ? 'Adicionar meses' : 'Liberar por meses' ?></option>
        <option value="date">Definir data de vencimento</option>
        <?php if ($admin): ?><option value="life">Vitalício (sem vencimento)</option><?php endif; ?>
      </select>
    </label>
    <label class="fld" data-plan="months"><span>Quantidade de meses</span><input type="number" name="months" min="1" max="<?= $admin ? 120 : 12 ?>" value="1"></label>
    <label class="fld" data-plan="date"><span>Vence em</span><input type="date" name="date" value="<?= e(substr((string)($edit['expires_at'] ?? ''), 0, 10)) ?>" <?= !$admin && $me['expires_at'] ? 'max="' . e(substr($me['expires_at'], 0, 10)) . '"' : '' ?>></label>
  </div>
  <label class="fld"><span>Observações internas</span><input type="text" name="notes" maxlength="300" value="<?= e($edit['notes'] ?? '') ?>" placeholder="Ex.: WhatsApp do cliente, forma de pagamento…"></label>
  <div class="row"><button class="btn gold">Salvar</button><a class="btn ghost" href="<?= url('/admin/usuarios.php') ?>">Cancelar</a></div>
</form>
<script>
(function () {
  const role = document.getElementById('role'), plan = document.getElementById('plan');
  const sync = () => {
    document.querySelectorAll('[data-roles]').forEach(el => el.hidden = !el.dataset.roles.split(' ').includes(role.value));
    document.querySelectorAll('[data-plan]').forEach(el => el.hidden = el.dataset.plan !== plan.value);
  };
  role.addEventListener('change', sync); plan.addEventListener('change', sync); sync();
})();
</script>
<?php endif; ?>

<div class="card">
  <form class="row wrap" method="get" style="margin-bottom:14px">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por usuário, nome ou e-mail" style="max-width:300px">
    <select name="f" style="max-width:180px" onchange="this.form.submit()">
      <?php $filters = ['' => 'Todos', 'active' => 'Ativos', 'expired' => 'Expirados', 'blocked' => 'Bloqueados'];
      if ($me['role'] !== 'revenda') $filters += ['ultra' => 'Só Ultra Masters', 'master' => 'Só masters', 'revenda' => 'Só revendas', 'user' => 'Só usuários'];
      if (!$admin) unset($filters['ultra']);
      if ($me['role'] === 'master') unset($filters['master']);
      foreach ($filters as $k => $v): ?>
        <option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Filtrar</button>
    <span class="sp"></span>
    <?php if ($roles): ?><a class="btn gold" href="<?= url('/admin/usuarios.php?new=1') ?>">+ Nova conta</a><?php endif; ?>
  </form>
  <?php if (!$users): ?><div class="empty">Nenhuma conta encontrada.</div><?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Conta</th><th>Tipo</th><th>Plano</th><th>Vencimento</th><th>Limites</th><th>Banners</th><th>Adicionar meses</th><th></th></tr>
    <?php foreach ($users as $r):
        $self = (int)$r['id'] === (int)$me['id'];
        $ok = can_touch($me, $r);
        $rq = in_array($r['role'], ['ultra', 'master', 'revenda'], true) ? quota($r) : null;
    ?>
    <tr>
      <td><b><?= e($r['username']) ?></b><br><small><?= e($r['name'] ?: $r['email']) ?><?= $r['notes'] ? ' · ' . e($r['notes']) : '' ?>
        <?= $r['parent_name'] && (int)$r['parent_id'] !== (int)$me['id'] ? ' · de ' . e($r['parent_name']) : '' ?></small></td>
      <td><span class="tag <?= $tag[$r['role']] ?>"><?= ROLES[$r['role']] ?></span></td>
      <td><?= $r['role'] === 'admin' ? '—' : plan_badge($r) ?></td>
      <td><?= $r['role'] === 'admin' ? '—' : ($r['expires_at'] ? fmt_date($r['expires_at']) : 'Sem vencimento') ?></td>
      <td><small><?= $rq ? "{$rq['users_used']} / {$rq['users_max']} usuários" . ($r['role'] !== 'revenda' ? "<br>{$rq['res_used']} / {$rq['res_max']} " . ($r['role'] === 'ultra' ? 'masters e revendas' : 'revendas') : '') . ($r['role'] === 'ultra' ? '<br>/p/' . e((string)tenant_setting((int)$r['id'], 'slug', '')) : '') : '—' ?></small></td>
      <td><?= (int)$r['banners'] ?></td>
      <td>
        <?php if ($ok && $r['role'] !== 'admin'): foreach ([1, 3, 6, 12] as $m): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="add"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="months" value="<?= $m ?>"><button class="btn sm" title="Adicionar <?= $m ?> mês(es)">+<?= $m ?></button></form>
        <?php endforeach; endif; ?>
      </td>
      <td style="white-space:nowrap;text-align:right">
        <?php if ($ok): ?>
          <a class="btn sm" href="<?= url('/admin/usuarios.php?edit=' . $r['id']) ?>">Editar</a>
          <?php if (!$self): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm"><?= $r['status'] === 'active' ? 'Bloquear' : 'Desbloquear' ?></button></form>
            <form method="post" onsubmit="return confirm('Excluir a conta <?= e($r['username']) ?>? Os estilos salvos dela são apagados e quem estiver abaixo dela passa para o responsável acima. Esta ação não pode ser desfeita.')"><?= csrf_field() ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm bad">Excluir</button></form>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php page_end();
