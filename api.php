<?php
/*
 * Painel de API do revendedor: gera e revoga chaves, mostra o início rápido e o link da documentação.
 * A chave completa só aparece uma vez, logo depois de criada (guardamos apenas o hash).
 */
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/ui.php';
$u = guard();
if (!can_manage($u)) redirect('/painel.php');
$pdo = db();
// Rede de segurança: garante a tabela mesmo se a migração ainda não tiver rodado (deploy parcial).
$pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE, prefix TEXT NOT NULL DEFAULT '', label TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1, calls INTEGER NOT NULL DEFAULT 0,
    rl_window INTEGER NOT NULL DEFAULT 0, rl_count INTEGER NOT NULL DEFAULT 0,
    last_used TEXT, last_ip TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL)");

if (is_post()) {
    csrf_check();
    $a = $_POST['a'] ?? '';
    if ($a === 'create') {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM api_keys WHERE user_id = ' . (int)$u['id'] . ' AND active = 1')->fetchColumn();
        if ($n >= 5) flash('Você já tem 5 chaves ativas. Revogue uma antes de criar outra.', 'err');
        else {
            $r = api_key_create((int)$u['id'], trim((string)($_POST['label'] ?? '')) ?: 'Minha integração');
            $_SESSION['api_new'] = $r['token']; // mostrado uma única vez
            flash('Chave criada! Copie e guarde agora — ela não será mostrada de novo.');
        }
    } elseif ($a === 'revoke') {
        $pdo->prepare('UPDATE api_keys SET active = 0 WHERE id = ? AND user_id = ?')->execute([(int)($_POST['id'] ?? 0), $u['id']]);
        flash('Chave revogada.');
    }
    redirect('/api.php');
}

$newToken = $_SESSION['api_new'] ?? null;
unset($_SESSION['api_new']);
$base = site_link() . 'api/v1';
$keys = $pdo->prepare('SELECT * FROM api_keys WHERE user_id = ? ORDER BY active DESC, id DESC');
$keys->execute([$u['id']]);
$keys = $keys->fetchAll();
$sampleKey = $newToken ?: 'SUA_CHAVE';
page_start('API & Integração', 'api', $u);
?>
<div class="card" style="max-width:720px">
  <h2>API de Integração</h2>
  <p style="color:var(--mut);margin-top:0">Crie e gerencie contas de clientes direto do <b>seu</b> sistema (site de vendas, bot, planilha…). Cada chamada respeita a sua cota e hierarquia, igual ao painel.</p>
  <div class="row wrap" style="gap:8px">
    <a class="btn gold" href="<?= url('/apidocs.php') ?>" target="_blank" rel="noopener">Abrir documentação →</a>
    <code class="api-base">Base: <?= e($base) ?></code>
  </div>
</div>

<?php if ($newToken): ?>
<div class="card" style="max-width:720px;border-color:var(--gold)">
  <h2>Sua nova chave</h2>
  <div class="alert warn" style="text-align:left">Copie agora — por segurança, ela <b>não aparece de novo</b>. Se perder, revogue e crie outra.</div>
  <label class="fld"><span>Chave (Authorization: Bearer)</span>
    <span class="row" style="gap:8px"><input type="text" id="tok" readonly value="<?= e($newToken) ?>"><button type="button" class="btn sm" data-copy="tok">Copiar</button></span>
  </label>
</div>
<?php endif; ?>

<div class="card" style="max-width:720px">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h2 style="margin:0">Suas chaves</h2>
    <form method="post" class="row" style="gap:8px;margin:0">
      <?= csrf_field() ?><input type="hidden" name="a" value="create">
      <input type="text" name="label" placeholder="Nome da chave (ex: Site de vendas)" maxlength="60" style="width:230px">
      <button class="btn gold sm">Gerar nova chave</button>
    </form>
  </div>
  <?php if (!$keys): ?>
    <div class="empty" style="margin-top:14px">Nenhuma chave ainda. Gere a primeira acima.</div>
  <?php else: ?>
  <div class="tbl-wrap" style="margin-top:14px"><table>
    <thead><tr><th>Nome</th><th>Prefixo</th><th>Chamadas</th><th>Último uso</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($keys as $k): ?>
      <tr style="<?= $k['active'] ? '' : 'opacity:.5' ?>">
        <td><?= e($k['label']) ?></td>
        <td><code><?= e($k['prefix']) ?>…</code></td>
        <td><?= (int)$k['calls'] ?></td>
        <td><?= $k['last_used'] ? e(fmt_date($k['last_used'], true)) : '—' ?></td>
        <td><?= $k['active'] ? '<span style="color:var(--ok)">Ativa</span>' : 'Revogada' ?></td>
        <td style="text-align:right"><?php if ($k['active']): ?>
          <form method="post" onsubmit="return confirm('Revogar esta chave? Integrações que a usam vão parar de funcionar.')">
            <?= csrf_field() ?><input type="hidden" name="a" value="revoke"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <button class="btn sm ghost">Revogar</button>
          </form>
        <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card" style="max-width:720px">
  <h2>Início rápido</h2>
  <p style="color:var(--mut);margin-top:0">Criar uma conta de cliente com 1 mês de acesso:</p>
  <pre class="api-code"><code>curl -X POST <?= e($base) ?>/users \
  -H "Authorization: Bearer <?= e($sampleKey) ?>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Cliente Exemplo","months":1}'</code></pre>
  <p style="color:var(--mut)">A resposta traz o <b>usuário</b> e a <b>senha gerada</b> (mostrada só nessa hora) e o link de login. Veja todos os endpoints na <a href="<?= url('/apidocs.php') ?>" target="_blank" rel="noopener">documentação</a>.</p>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
  const i = document.getElementById(b.dataset.copy);
  try { await navigator.clipboard.writeText(i.value); } catch (e) { i.select(); document.execCommand('copy'); }
  const t = b.textContent; b.textContent = 'Copiado!'; setTimeout(() => b.textContent = t, 1500);
}));
</script>
<?php page_end();
