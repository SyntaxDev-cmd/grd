<?php
/*
 * API REST para revendedores (v1). Autentica por chave (Authorization: Bearer gg_live_… ou X-API-Key).
 * Permite criar e gerenciar as contas abaixo da sua, respeitando as MESMAS regras do painel
 * (cota, hierarquia e teto de vencimento). Resposta sempre em JSON.
 *
 * Base: <seu-site>/api/v1   (ou /api/v1.php)
 * Documentação: <seu-site>/apidocs.php
 */
require __DIR__ . '/../inc/boot.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// Rede de segurança: garante a tabela mesmo se a migração ainda não tiver rodado (deploy parcial).
db()->exec("CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE, prefix TEXT NOT NULL DEFAULT '', label TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1, calls INTEGER NOT NULL DEFAULT 0,
    rl_window INTEGER NOT NULL DEFAULT 0, rl_count INTEGER NOT NULL DEFAULT 0,
    last_used TEXT, last_ip TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL)");

/* ---------- Autenticação por chave ---------- */
function bearer_token(): string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    }
    if (preg_match('/Bearer\s+(\S+)/i', (string)$h, $m)) return $m[1];
    return (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
}

$token = bearer_token();
if ($token === '') api_err('Chave de API ausente. Envie o cabeçalho "Authorization: Bearer SUA_CHAVE".', 401, 'auth');
$key = api_find_key($token);
if (!$key) api_err('Chave de API inválida ou revogada.', 401, 'auth');

$pdo = db();
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$key['user_id']]);
$owner = $st->fetch() ?: null;
if (!$owner || $owner['status'] !== 'active') api_err('A conta dona desta chave está inativa.', 403, 'auth');
if (!can_manage($owner)) api_err('Esta conta não pode usar a API de revenda.', 403, 'auth');
if (access_state($owner)['locked']) api_err('O plano da conta dona da chave está vencido. Renove para usar a API.', 402, 'plan');

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!api_rate_ok($key)) api_err('Limite de ' . API_RATE_PER_MIN . ' chamadas por minuto excedido. Tente de novo em instantes.', 429, 'rate_limited');
api_touch($key, $ip);

$admin = is_admin($owner);

/* ---------- Roteamento ---------- */
$path = (string)($_SERVER['PATH_INFO'] ?? ($_GET['path'] ?? ''));
$seg = array_values(array_filter(explode('/', trim($path, '/')), fn($s) => $s !== ''));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$in = api_input();
if ($method === 'POST' && strtolower((string)($in['_method'] ?? ($_GET['_method'] ?? ''))) === 'delete') $method = 'DELETE';

/* ---------- Helpers de contas ---------- */
function api_find_managed(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
/** O dono pode mexer nesta conta? (abaixo dele na árvore e com papel inferior) */
function api_can_touch(array $owner, ?array $t): bool
{
    if (!$t) return false;
    if ($owner['role'] === 'admin') return true;
    return ROLE_RANK[$t['role']] < ROLE_RANK[$owner['role']] && in_tree($owner, $t);
}
function api_unique_username(string $base): string
{
    $base = preg_replace('/[^a-z0-9._-]/', '', strtolower($base));
    $base = $base !== '' ? substr($base, 0, 20) : 'cliente';
    if (strlen($base) < 3) $base .= 'cli';
    $st = db()->prepare('SELECT 1 FROM users WHERE username = ?');
    for ($i = 0; $i < 50; $i++) {
        $try = $i === 0 ? $base : $base . random_int(10, 99999);
        $st->execute([$try]);
        if (!$st->fetch()) return $try;
    }
    return $base . bin2hex(random_bytes(3));
}
function api_gen_password(int $n = 10): string
{
    $al = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $al[random_int(0, strlen($al) - 1)];
    return $s;
}

/* ---------- Endpoints ---------- */

// GET /  → informações da API
if (!$seg && $method === 'GET') {
    api_out(['ok' => true, 'api' => 'Gerador — API de Revenda', 'version' => 'v1',
        'base_url' => site_link() . 'api/v1', 'docs' => site_link() . 'apidocs.php',
        'endpoints' => ['GET /me', 'GET /plans', 'GET /users', 'POST /users', 'GET /users/{id}',
            'POST /users/{id}/renew', 'POST /users/{id}/suspend', 'POST /users/{id}/activate', 'POST /users/{id}/password', 'DELETE /users/{id}']]);
}

// GET /me  → conta dona da chave + cota
if ($seg === ['me'] && $method === 'GET') {
    $q = quota($owner);
    api_out(['ok' => true, 'account' => [
        'id' => (int)$owner['id'], 'username' => $owner['username'], 'name' => $owner['name'],
        'role' => $owner['role'], 'expires_at' => $owner['expires_at'],
        'can_create_roles' => array_values(array_diff(creatable_roles($owner), ['ultra'])),
    ], 'quota' => [
        'users_used' => $q['users_used'], 'users_max' => $q['users_max'], 'users_free' => $admin ? null : $q['users_free'],
        'resellers_used' => $q['res_used'], 'resellers_max' => $q['res_max'], 'resellers_free' => $admin ? null : $q['res_free'],
    ], 'rate_limit_per_min' => API_RATE_PER_MIN]);
}

// GET /plans  → planos disponíveis na plataforma do dono
if ($seg === ['plans'] && $method === 'GET') {
    $where = tenant_where(brand_tid($owner));
    $rows = $pdo->query("SELECT id, name, descr, role, months, price, active FROM plans WHERE active = 1 AND $where ORDER BY sort, id")->fetchAll();
    $plans = array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'description' => $p['descr'],
        'role' => $p['role'], 'months' => (int)$p['months'], 'price_cents' => (int)$p['price'], 'price' => brl((int)$p['price'])], $rows);
    api_out(['ok' => true, 'plans' => $plans]);
}

// /users
if (($seg[0] ?? '') === 'users') {
    // GET /users  → lista as contas sob o dono
    if (count($seg) === 1 && $method === 'GET') {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $q = trim((string)($_GET['q'] ?? ''));
        if ($admin) { $scope = '1=1'; $args = []; }
        elseif ($owner['role'] === 'ultra') { $scope = 'tenant_id = ?'; $args = [$owner['id']]; }
        else { $scope = 'parent_id = ?'; $args = [$owner['id']]; }
        $like = '';
        if ($q !== '') { $like = ' AND (username LIKE ? OR name LIKE ? OR email LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $scope$like");
        $cnt->execute($args);
        $total = (int)$cnt->fetchColumn();
        $sql = $pdo->prepare("SELECT * FROM users WHERE $scope$like ORDER BY id DESC LIMIT ? OFFSET ?");
        $sql->execute(array_merge($args, [$limit, $offset]));
        api_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset,
            'users' => array_map('api_user_view', $sql->fetchAll())]);
    }

    // POST /users  → cria uma conta
    if (count($seg) === 1 && $method === 'POST') {
        $role = (string)($in['role'] ?? 'user');
        $allowed = array_values(array_diff(creatable_roles($owner), ['ultra'])); // API nunca cria Ultra Master nem admin
        if (!in_array($role, $allowed, true)) api_err('Você não pode criar contas do tipo "' . $role . '". Permitidos: ' . implode(', ', $allowed) . '.', 403, 'forbidden');

        $username = trim((string)($in['username'] ?? ''));
        if ($username === '') $username = api_unique_username((string)($in['name'] ?? 'cliente'));
        $pass = (string)($in['password'] ?? '');
        $genPass = $pass === '';
        if ($genPass) $pass = api_gen_password();
        $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 60);
        $email = trim((string)($in['email'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) api_err('Usuário deve ter de 3 a 30 letras, números, ponto, hífen ou sublinhado.', 422, 'validation');
        if (strlen($pass) < 6) api_err('A senha deve ter pelo menos 6 caracteres.', 422, 'validation');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) api_err('E-mail inválido.', 422, 'validation');
        $ck = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $ck->execute([$username]);
        if ($ck->fetch()) api_err('Já existe uma conta com esse usuário.', 409, 'conflict');

        // Cota
        $quota = quota($owner);
        $lu = 0; $lr = 0;
        if (!$admin) {
            if ($role === 'user' && $quota['users_free'] < 1) api_err('Seu limite de usuários acabou.', 409, 'quota');
            if (($role === 'revenda' || $role === 'master') && $quota['res_free'] < 1) api_err('Seu limite de revendas acabou.', 409, 'quota');
        }
        if ($role === 'revenda' || $role === 'master') {
            $lu = max(0, min(100000, (int)($in['limit_users'] ?? 0)));
            $lr = $role === 'master' ? max(0, min(100000, (int)($in['limit_resellers'] ?? 0))) : 0;
            if (!$admin && $lu > $quota['users_free']) api_err('Você só tem ' . $quota['users_free'] . ' usuários livres para repassar.', 409, 'quota');
        }

        // Vencimento: teste (trial=true) ou meses de plano; nunca passa do vencimento do dono
        $trial = !empty($in['trial']);
        $paid = $trial ? 0 : 1;
        if ($trial) {
            $exp = date('Y-m-d H:i:s', time() + max(1, (int)setting('trial_days', '1')) * 86400);
        } else {
            $months = max(1, min($admin ? 120 : 12, (int)($in['months'] ?? 1)));
            $exp = add_months(null, $months);
        }
        if ($e = exp_error_api($owner, $exp)) api_err($e, 422, 'validation');

        $pdo->prepare('INSERT INTO users (username, name, email, pass, role, status, expires_at, notes, paid, limit_users, limit_resellers, parent_id, referred_by, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$username, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, 'active', $exp,
                'Criado via API', $paid, $lu, $lr, $owner['id'], $owner['id'], now(), brand_tid($owner)]);
        $id = (int)$pdo->lastInsertId();
        $new = api_find_managed($id);
        $out = api_user_view($new);
        $out['password'] = $pass;                 // só retornado agora, na criação
        $out['login_url'] = site_link() . 'login.php';
        api_out(['ok' => true, 'user' => $out, 'password_generated' => $genPass], 201);
    }

    // Daqui pra baixo precisa de id: /users/{id}[/acao]
    $id = (int)($seg[1] ?? 0);
    $target = $id ? api_find_managed($id) : null;
    if (!$target) api_err('Conta não encontrada.', 404, 'not_found');
    if (!api_can_touch($owner, $target)) api_err('Você não tem permissão sobre esta conta.', 403, 'forbidden');
    $action = $seg[2] ?? '';

    // GET /users/{id}
    if ($action === '' && $method === 'GET') api_out(['ok' => true, 'user' => api_user_view($target)]);

    // DELETE /users/{id}
    if ($action === '' && $method === 'DELETE') {
        if ($target['role'] === 'ultra') api_err('Contas de plataforma (Ultra Master) não podem ser excluídas pela API.', 403, 'forbidden');
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        api_out(['ok' => true, 'deleted' => $id]);
    }

    // POST /users/{id}/renew
    if ($action === 'renew' && $method === 'POST') {
        $months = max(1, min($admin ? 120 : 12, (int)($in['months'] ?? 1)));
        $exp = add_months($target['expires_at'], $months);
        if ($e = exp_error_api($owner, $exp)) api_err($e, 422, 'validation');
        $pdo->prepare('UPDATE users SET expires_at = ?, paid = 1 WHERE id = ?')->execute([$exp, $id]);
        api_out(['ok' => true, 'user' => api_user_view(api_find_managed($id))]);
    }

    // POST /users/{id}/suspend  |  /activate
    if (($action === 'suspend' || $action === 'activate') && $method === 'POST') {
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$action === 'suspend' ? 'blocked' : 'active', $id]);
        api_out(['ok' => true, 'user' => api_user_view(api_find_managed($id))]);
    }

    // POST /users/{id}/password  → redefine a senha (gera se não vier)
    if ($action === 'password' && $method === 'POST') {
        $pass = (string)($in['password'] ?? '');
        $gen = $pass === '';
        if ($gen) $pass = api_gen_password();
        if (strlen($pass) < 6) api_err('A senha deve ter pelo menos 6 caracteres.', 422, 'validation');
        $pdo->prepare('UPDATE users SET pass = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        api_out(['ok' => true, 'user' => api_user_view(api_find_managed($id)), 'password' => $pass, 'password_generated' => $gen]);
    }

    api_err('Ação ou método não suportado para este recurso.', 405, 'method_not_allowed');
}

api_err('Recurso não encontrado. Veja a documentação em ' . site_link() . 'apidocs.php', 404, 'not_found');

/** Teto de vencimento: ninguém passa do vencimento do próprio dono (admin é ilimitado). */
function exp_error_api(array $owner, ?string $exp): ?string
{
    if ($owner['role'] === 'admin' || $owner['expires_at'] === null) return null;
    if ($exp === null || $exp > $owner['expires_at']) return 'O vencimento não pode passar do seu próprio plano (' . $owner['expires_at'] . ').';
    return null;
}
