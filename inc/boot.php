<?php
declare(strict_types=1);

define('ROOT', str_replace('\\', '/', (string)realpath(dirname(__DIR__))));
define('DATA', ROOT . '/data');
define('IS_CLI', PHP_SAPI === 'cli');

date_default_timezone_set('America/Sao_Paulo');

// Verificação de licença (ativa só no script distribuído; inofensiva no Gerador Gold)
if (is_file(__DIR__ . '/license.php')) { require __DIR__ . '/license.php'; if (lic_is_build()) license_guard(); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA . '/php_error.log');

// Caminho base do sistema (funciona em subpasta ou na raiz do domínio)
(function () {
    $rel = substr(str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? '')), strlen(ROOT));
    $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    define('BASE', ($rel !== '' && str_ends_with($sn, $rel)) ? substr($sn, 0, -strlen($rel)) : '');
})();

const LAYOUTS = [
    'movie' => [
        'cinematic' => 'Cinematográfico',
        'poster'    => 'Pôster Destaque',
        'neon'      => 'Neon',
        'split'     => 'Diagonal',
        'clean'     => 'Clean',
        'gold'      => 'Premium Gold',
        'ranking'   => 'Top da Semana',
        'magazine'  => 'Revista',
        'spot'      => 'Holofote',
        'ribbon'    => 'Faixa Lateral',
        'catalog'   => 'Catálogo',
        'tv'        => 'Na Smart TV',
        'appui'     => 'Tela do App',
        'score'     => 'Nota em Destaque',
        'frameart'  => 'Moldura',
        'halves'    => 'Meio a Meio',
        'sidebar'   => 'Coluna',
        'circle'    => 'Círculo',
        'tilted'    => 'Inclinado',
        'filmstrip' => 'Película',
        'bigtype'   => 'Tipografia Gigante',
        'glass'     => 'Vidro',
        'bands'     => 'Faixas',
        'seal'      => 'Selo',
    ],
    'games' => [
        'arena'   => 'Arena',
        'neon'    => 'Neon',
        'clean'   => 'Clean',
        'gold'    => 'Premium Gold',
        'compact' => 'Tabela Compacta',
        'hero'    => 'Jogo Destaque',
        'duel'    => 'Confronto',
        'timeline' => 'Linha do Tempo',
        'grid'    => 'Grade',
        'broadcast' => 'Transmissão',
        'ticket'  => 'Ingresso',
        'classic' => 'Clássico',
        'bigcards' => 'Cartões Grandes',
        'scoreboard' => 'Placar',
        'agenda'  => 'Agenda por Campeonato',
        'pill'    => 'Cápsulas',
        'minimal' => 'Minimal',
        'lightcards' => 'Cartões Claros',
        'stripes' => 'Listrado',
        'textcenter' => 'Texto Central',
        'watermark' => 'Marca d’Água',
        'bytime'  => 'Agenda por Horário',
        'feature' => 'Destaque + Lista',
        'duo'     => 'Duplo Clássico',
        'guide'   => 'Guia de TV',
    ],
    'promo' => [
        'hero'    => 'Oferta com Preço',
        'trial'   => 'Teste Grátis',
        'benefits' => 'Benefícios',
        'urgency' => 'Promoção Relâmpago',
        'proof'   => 'Prova Social',
        'type'    => 'Tipográfico',
        'fan'     => 'Leque de Capas',
        'list'    => 'Lista Completa',
        'plans'   => 'Planos e Preços',
        'offer'   => 'Oferta em Destaque',
        'collage' => 'Catálogo Completo',
        'combo'   => 'Completo',
        'channels' => 'Grade de Canais',
        'streaming' => 'Streamings',
        'devices' => 'Todos os Dispositivos',
        'wall'    => 'Parede de Capas',
        'pseal'   => 'Selo de Preço',
        'pband'   => 'Faixa Diagonal',
        'pglass'  => 'Cartão de Vidro',
        'numbers' => 'Números',
        'pphone'  => 'No Celular',
        'ptv'     => 'Na Smart TV',
        'plogos'  => 'Canais e Preço',
        'pchat'   => 'Conversa',
        'pshield' => 'Garantia',
        'plight'  => 'Claro',
        'rs_hero' => 'Seja Revendedor',
        'rs_profit' => 'Lucro em Destaque',
        'rs_steps' => 'Como Funciona',
        'rs_panel' => 'Painel Próprio',
        'rs_numbers' => 'Vantagens em Números',
        'rs_shield' => 'Parceria Garantida',
    ],
];

// Categorias de modelos em cada seção (o que não estiver listado cai em "Outros")
const LAYOUT_GROUPS = [
    'movie' => [
        'Arte em tela cheia' => ['cinematic', 'magazine', 'ribbon', 'frameart', 'glass', 'sidebar', 'split', 'halves'],
        'Pôster em destaque' => ['poster', 'neon', 'clean', 'gold', 'spot', 'circle', 'bigtype', 'bands', 'seal', 'tilted', 'filmstrip'],
        'Estilo streaming'   => ['tv', 'appui', 'score'],
        'Listas e rankings'  => ['ranking', 'catalog'],
    ],
    'games' => [
        'Listas de jogos'     => ['arena', 'neon', 'clean', 'gold', 'compact', 'duel', 'stripes', 'lightcards', 'watermark', 'minimal', 'pill', 'textcenter'],
        'TV e transmissão'    => ['broadcast', 'scoreboard', 'ticket', 'guide', 'timeline'],
        'Agendas e grades'    => ['agenda', 'bytime', 'grid', 'bigcards', 'feature'],
        'Jogo em destaque'    => ['hero', 'classic', 'duo'],
    ],
    'promo' => [
        'Banners com valor'       => ['hero', 'urgency', 'pband', 'pseal', 'offer', 'plans', 'type', 'plight', 'pglass', 'fan', 'plogos'],
        'Banners de divulgação'   => ['collage', 'combo', 'channels', 'streaming', 'wall', 'list', 'benefits', 'numbers', 'trial', 'pshield', 'proof', 'pchat'],
        'Captação de revendas'    => ['rs_hero', 'rs_profit', 'rs_steps', 'rs_panel', 'rs_numbers', 'rs_shield'],
        'Artes para aplicativos'  => ['devices', 'pphone', 'ptv'],
    ],
];
const MOTION_PATTERNS = 18;

// Seções de conteúdo que o admin alimenta
const LIB_SECTIONS = ['dash' => 'Banners do painel inicial', 'tutoriais' => 'Vídeos Tutoriais', 'cortes' => 'Cortes Virais', 'apps' => 'Códigos para baixar Apps'];

const WELCOME_MSG = "🎉 Bem-vindo(a) ao {sistema}!\n\nSeu acesso está liberado:\n🔗 {link}\n👤 Usuário: {usuario}\n🔑 Senha: {senha}\n📅 Válido até: {validade}\n\n📢 Canal com novidades e tutoriais: {canal}\n\nQualquer dúvida, é só chamar.";
const WELCOME_PANEL = "Seu acesso está ativo. Comece enviando sua logo em Minha Marca: as cores dos banners se ajustam sozinhas a ela.\n\nDepois é só escolher um modelo e baixar.";
const DASH_FEATURES = "Artes profissionais\nBanners de filmes e séries\nTabela de futebol\nVídeos para Stories e TikTok\nSuporte";

const FONTS_TITLE = ['Bebas Neue', 'Anton', 'Oswald', 'Teko', 'Barlow Condensed', 'Archivo Black', 'Montserrat', 'Poppins'];
const FONTS_BODY  = ['Montserrat', 'Poppins', 'Inter', 'Barlow Condensed', 'Oswald'];

const BRAND_DEFAULTS = [
    'name' => '', 'logo' => '', 'bgImage' => '',
    'primary' => '#F5C518', 'secondary' => '#FF8A00', 'bg' => '#0B0B12', 'text' => '#FFFFFF',
    'fontTitle' => 'Bebas Neue', 'fontBody' => 'Montserrat',
    'cta' => 'ASSINE AGORA', 'footer' => '',
];

const ESPN_LEAGUES = 'bra.1, bra.2, bra.copa_do_brazil, conmebol.libertadores, conmebol.sudamericana, fifa.world, fifa.worldq.conmebol, fifa.friendly, uefa.champions, uefa.europa, eng.1, esp.1, ita.1, ger.1, fra.1, por.1, arg.1';

const GAMES_MAP_DEFAULT = [
    'time1' => 'time1', 'time2' => 'time2',
    'logo1' => 'img_time1_url', 'logo2' => 'img_time2_url',
    'hora' => 'horario', 'data' => 'data_jogo',
    'competicao' => 'competicao', 'logo_competicao' => 'img_competicao_url',
    'canais' => 'canais', 'canal_nome' => 'nome',
    'placar1' => 'placar_time1', 'placar2' => 'placar_time2', 'status' => 'status',
];

/* ---------- Banco de dados ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DATA . '/cache/img')) @mkdir(DATA . '/cache/img', 0755, true);
    $pdo = new PDO('sqlite:' . DATA . '/app.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($ver < 1) migrate($pdo);
    if ($ver < 2) {
        // v2: hierarquia admin > master > revenda > usuário, com limites por conta
        $cols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        if (!in_array('parent_id', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN parent_id INTEGER');
        if (!in_array('limit_users', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN limit_users INTEGER NOT NULL DEFAULT 0');
        if (!in_array('limit_resellers', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN limit_resellers INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('CREATE INDEX IF NOT EXISTS users_parent ON users(parent_id)');
        $pdo->exec('PRAGMA user_version = 2');
    }
    if ($ver < 3) {
        // v3: conteúdos geridos pelo admin (banners do painel, tutoriais, cortes virais, códigos de apps)
        $pdo->exec("CREATE TABLE IF NOT EXISTS library (
            id INTEGER PRIMARY KEY AUTOINCREMENT, section TEXT NOT NULL, title TEXT NOT NULL,
            descr TEXT NOT NULL DEFAULT '', url TEXT NOT NULL DEFAULT '', code TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)");
        $pdo->exec('CREATE INDEX IF NOT EXISTS library_section ON library(section, sort)');
        $pdo->exec('PRAGMA user_version = 3');
    }
    if ($ver < 4) {
        // v4: planos, pedidos (PIX), teste grátis e indicação
        $cols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        // paid = já teve plano pago ou liberado por alguém (quem se cadastra sozinho começa em 0: teste grátis)
        if (!in_array('paid', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN paid INTEGER NOT NULL DEFAULT 1');
        if (!in_array('ref_code', $cols, true)) $pdo->exec("ALTER TABLE users ADD COLUMN ref_code TEXT NOT NULL DEFAULT ''");
        if (!in_array('referred_by', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN referred_by INTEGER');
        if (!in_array('ref_coupons', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN ref_coupons INTEGER NOT NULL DEFAULT 0');
        $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, descr TEXT NOT NULL DEFAULT '', role TEXT NOT NULL DEFAULT 'user',
            months INTEGER NOT NULL DEFAULT 1, price INTEGER NOT NULL, limit_users INTEGER NOT NULL DEFAULT 0, limit_resellers INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, plan_id INTEGER NOT NULL, plan_name TEXT NOT NULL,
            price INTEGER NOT NULL, discount INTEGER NOT NULL DEFAULT 0, amount INTEGER NOT NULL, coupon TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'pending', mp_id TEXT NOT NULL DEFAULT '', qr TEXT NOT NULL DEFAULT '', qr_img TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL, paid_at TEXT, note TEXT NOT NULL DEFAULT '')");
        $pdo->exec('CREATE INDEX IF NOT EXISTS orders_user ON orders(user_id, id)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS signups (ip TEXT NOT NULL, ts INTEGER NOT NULL)');
        if (!(int)$pdo->query('SELECT COUNT(*) FROM plans')->fetchColumn()) {
            // Valores de exemplo: o admin ajusta em Planos e Pagamentos
            $ins = $pdo->prepare('INSERT INTO plans (name, descr, role, months, price, limit_users, limit_resellers, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute(['Mensal', 'Todos os modelos por 30 dias', 'user', 1, 2990, 0, 0, 1]);
            $ins->execute(['Trimestral', 'Três meses com desconto', 'user', 3, 7490, 0, 0, 2]);
            $ins->execute(['Anual', 'Doze meses pelo melhor preço', 'user', 12, 24990, 0, 0, 3]);
            $ins->execute(['Master', 'Crie revendas e usuários com o seu painel', 'master', 1, 14990, 50, 10, 4]);
        }
        $pdo->exec('PRAGMA user_version = 4');
    }
    if ($ver < 5) {
        // v5: plataformas próprias (Ultra Master). tenant_id = id do Ultra Master dono da plataforma; NULL = sistema base
        foreach (['users', 'plans', 'library'] as $t) {
            $cols = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(), 'name');
            if (!in_array('tenant_id', $cols, true)) $pdo->exec("ALTER TABLE $t ADD COLUMN tenant_id INTEGER");
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS tenant_settings (tenant_id INTEGER NOT NULL, k TEXT NOT NULL, v TEXT NOT NULL, PRIMARY KEY (tenant_id, k))');
        $pdo->exec('CREATE INDEX IF NOT EXISTS users_tenant ON users(tenant_id)');
        $pdo->exec('PRAGMA user_version = 5');
    }
    if ($ver < 6) {
        // v6: licenças do script vendido (gerenciadas só aqui, no Gerador Gold)
        $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT, lkey TEXT NOT NULL UNIQUE, domain TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active', expires_at TEXT, buyer TEXT NOT NULL DEFAULT '', note TEXT NOT NULL DEFAULT '',
            last_check TEXT, last_ip TEXT NOT NULL DEFAULT '', checks INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)");
        $pdo->exec('PRAGMA user_version = 6');
    }
    if ($ver < 7) {
        // v7: rastreio de vazamento — todo domínio/IP/marca que toca cada licença, e marcação de uso em domínio estranho
        $pdo->exec("CREATE TABLE IF NOT EXISTS license_hits (
            id INTEGER PRIMARY KEY AUTOINCREMENT, license_id INTEGER NOT NULL, lkey TEXT NOT NULL DEFAULT '',
            domain TEXT NOT NULL DEFAULT '', ip TEXT NOT NULL DEFAULT '', build TEXT NOT NULL DEFAULT '',
            foreign_use INTEGER NOT NULL DEFAULT 0, hits INTEGER NOT NULL DEFAULT 1, first_seen TEXT NOT NULL, last_seen TEXT NOT NULL,
            UNIQUE(license_id, domain, ip))");
        $pdo->exec('CREATE INDEX IF NOT EXISTS hits_lic ON license_hits(license_id, last_seen)');
        foreach (['licenses' => ['violations' => 'INTEGER NOT NULL DEFAULT 0', 'buyer_tag' => "TEXT NOT NULL DEFAULT ''"]] as $t => $cols) {
            $have = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(), 'name');
            foreach ($cols as $c => $def) if (!in_array($c, $have, true)) $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def");
        }
        $pdo->exec('PRAGMA user_version = 7');
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        pass TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        status TEXT NOT NULL DEFAULT 'active',
        expires_at TEXT,
        notes TEXT NOT NULL DEFAULT '',
        brand TEXT NOT NULL DEFAULT '{}',
        banners INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        last_login TEXT
    );
    CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS presets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        kind TEXT NOT NULL, name TEXT NOT NULL, data TEXT NOT NULL, created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS sports_apis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL, url TEXT NOT NULL,
        headers TEXT NOT NULL DEFAULT '', root TEXT NOT NULL DEFAULT '',
        map TEXT NOT NULL DEFAULT '{}', active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL, kind TEXT NOT NULL, layout TEXT NOT NULL, format TEXT NOT NULL,
        title TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS logs_user ON logs(user_id, created_at);
    CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, ts INTEGER NOT NULL);
    ");

    $seed = [
        'site_name' => 'Gerador Gold',
        'tmdb_key' => 'e07c33cf263be9cdd38872ebc7389ffc',
        'tmdb_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJlMDdjMzNjZjI2M2JlOWNkZDM4ODcyZWJjNzM4OWZmYyIsIm5iZiI6MTczNjkxNzg3My43Niwic3ViIjoiNjc4NzQzNzE4ZDQ5YzQ0ZmE1YmIwYjk5Iiwic2NvcGVzIjpbImFwaV9yZWFkIl0sInZlcnNpb24iOjF9.IsUbHqh5GuGfXa1drGzCsw91B_V29PHJ87l6QkrQPwU',
        'tmdb_lang' => 'pt-BR',
        'tmdb_region' => 'BR',
        'games_ttl' => '120',
        'allow_register' => '0',
        'trial_days' => '3',
        'support_link' => '',
        'expired_msg' => 'Seu plano expirou. Fale com o suporte para renovar e continuar gerando seus banners.',
        'layouts_off' => '[]',
        'default_brand' => '{}',
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO settings (k, v) VALUES (?, ?)');
    foreach ($seed as $k => $v) $st->execute([$k, $v]);

    if (!(int)$pdo->query('SELECT COUNT(*) FROM sports_apis')->fetchColumn()) {
        $pdo->prepare('INSERT INTO sports_apis (name, url, map) VALUES (?, ?, ?)')->execute([
            'Smart Sollutions – Jogos do dia',
            'https://apiesporte.smartsollutions.com.br/apicron.php',
            json_encode(GAMES_MAP_DEFAULT, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }

    if (!(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
        // Acesso inicial padrão: admin / admin (mostrado na ativação da licença; o usuário troca depois)
        $pdo->prepare("INSERT INTO users (username, name, pass, role, created_at) VALUES ('admin', 'Administrador', ?, 'admin', ?)")
            ->execute([password_hash('admin', PASSWORD_DEFAULT), now()]);
    }
    $pdo->exec('PRAGMA user_version = 1');
}

function now(): string { return date('Y-m-d H:i:s'); }

/* ---------- Plataformas próprias (Ultra Master) ----------
   Cada Ultra Master é dono de uma plataforma isolada: marca, planos, Mercado Pago, cadastro e conteúdos próprios.
   As chaves abaixo são "da plataforma": dentro de uma plataforma elas NUNCA caem para o valor do sistema base
   (principalmente o token do Mercado Pago — o dinheiro de uma plataforma não pode ir para outra conta). */
const TENANT_KEYS = ['site_name', 'site_logo', 'ui_color1', 'ui_color2', 'mp_token', 'support_link', 'channel_link', 'dash_tagline', 'dash_features',
    'welcome_panel', 'welcome_msg', 'expired_msg', 'allow_register', 'trial_days', 'trial_limit', 'ref_new_pct', 'ref_owner_pct', 'default_brand', 'slug'];
const TENANT_DEFAULTS = ['allow_register' => '1', 'trial_days' => '1', 'trial_limit' => '15', 'ref_new_pct' => '10', 'ref_owner_pct' => '10'];

function is_tenant_key(string $k): bool { return in_array($k, TENANT_KEYS, true) || str_starts_with($k, 'hub_img_'); }

/** Plataforma cuja marca e regras valem para a conta: o próprio Ultra Master, ou a plataforma a que a conta pertence. */
function brand_tid(?array $u): ?int
{
    if (!$u) return null;
    if ($u['role'] === 'ultra') return (int)$u['id'];
    return $u['tenant_id'] ? (int)$u['tenant_id'] : null;
}
/** Plataforma que recebe os pagamentos da conta. O Ultra Master paga o plano dele ao sistema base. */
function billing_tid(array $u): ?int { return $u['role'] === 'ultra' ? null : ($u['tenant_id'] ? (int)$u['tenant_id'] : null); }

/** Plataforma em vigor nesta requisição: a da conta logada ou, antes do login, a do link de entrada. */
function tenant_ctx(): ?int
{
    if (IS_CLI) return $GLOBALS['GG_TENANT'] ?? null;
    $u = user();
    if ($u) return brand_tid($u);
    return !empty($_SESSION['tenant']) ? (int)$_SESSION['tenant'] : null;
}

function tenant_setting(?int $tid, string $k, ?string $def = null): ?string
{
    static $cache = [];
    if ($tid === null) return base_setting($k, $def);
    if ($k === '__reload') { unset($cache[$tid]); return null; }
    if (!isset($cache[$tid])) {
        $cache[$tid] = [];
        $st = db()->prepare('SELECT k, v FROM tenant_settings WHERE tenant_id = ?');
        $st->execute([$tid]);
        foreach ($st as $r) $cache[$tid][$r['k']] = $r['v'];
    }
    return $cache[$tid][$k] ?? TENANT_DEFAULTS[$k] ?? $def;
}

function base_setting(string $k, ?string $def = null): ?string
{
    static $all = null;
    if ($all === null || $k === '__reload') {
        $all = [];
        foreach (db()->query('SELECT k, v FROM settings') as $r) $all[$r['k']] = $r['v'];
    }
    return $all[$k] ?? $def;
}

function setting(string $k, ?string $def = null): ?string
{
    if ($k === '__reload') return base_setting($k);
    $tid = is_tenant_key($k) ? tenant_ctx() : null;
    return $tid !== null ? tenant_setting($tid, $k, $def) : base_setting($k, $def);
}

function set_setting(string $k, string $v): void
{
    $tid = is_tenant_key($k) ? tenant_ctx() : null;
    if ($tid !== null) {
        db()->prepare('INSERT INTO tenant_settings (tenant_id, k, v) VALUES (?, ?, ?) ON CONFLICT(tenant_id, k) DO UPDATE SET v = excluded.v')->execute([$tid, $k, $v]);
        tenant_setting($tid, '__reload');
        return;
    }
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
    base_setting('__reload');
}

/** Plataforma pelo endereço curto (slug). */
function tenant_by_slug(string $slug): ?array
{
    $st = db()->prepare("SELECT u.* FROM tenant_settings t JOIN users u ON u.id = t.tenant_id WHERE t.k = 'slug' AND t.v = ? AND u.role = 'ultra' AND u.status = 'active'");
    $st->execute([strtolower($slug)]);
    return $st->fetch() ?: null;
}
/** Link público de entrada da plataforma (login e cadastro com a marca dela). */
function tenant_link(int $tid): string { return site_link() . 'p/' . rawurlencode((string)tenant_setting($tid, 'slug', '')); }

/** SQL para filtrar uma tabela pela plataforma em vigor ("tenant_id IS NULL" no sistema base). */
function tenant_where(?int $tid, string $col = 'tenant_id'): string { return $tid === null ? "$col IS NULL" : "$col = " . (int)$tid; }

function layouts_enabled(string $kind): array
{
    $off = json_decode((string)setting('layouts_off', '[]'), true) ?: [];
    $out = [];
    // Na ordem das categorias; "n" é o número do modelo dentro da categoria (Modelo 1, 2, 3…)
    $groups = LAYOUT_GROUPS[$kind] ?? [];
    $rest = array_diff(array_keys(LAYOUTS[$kind]), array_merge(...array_values($groups ?: [[]])));
    if ($rest) $groups['Outros'] = array_values($rest);
    foreach ($groups as $g => $ids) {
        $n = 0;
        foreach ($ids as $id) {
            if (!isset(LAYOUTS[$kind][$id]) || in_array("$kind:$id", $off, true)) continue;
            $out[] = ['id' => $id, 'name' => LAYOUTS[$kind][$id], 'g' => $g, 'n' => ++$n];
        }
    }
    if (!$out) {
        $first = array_key_first(LAYOUTS[$kind]);
        $out[] = ['id' => $first, 'name' => LAYOUTS[$kind][$first], 'g' => 'Modelos', 'n' => 1];
    }
    return $out;
}

/* ---------- Sessão / autenticação ---------- */

if (!IS_CLI) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('GGSESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    session_start();

    // Formulários enviam os campos em base64 no campo _gg (o JS em ui.php monta isso). Aqui desembrulhamos.
    // Motivo: firewalls de hospedagem (mod_security, "modo sob ataque") barram POST com token do TMDB, URLs e emojis,
    // devolvendo "Forbidden". Em base64 o firewall vê só um bloco opaco e deixa passar.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['_gg'])) {
        $dec = base64_decode((string)$_POST['_gg'], true);
        if ($dec !== false) { parse_str($dec, $m); if (is_array($m)) $_POST = array_merge($_POST, $m); }
        unset($_POST['_gg']);
    }

    // Mesma proteção para a query string: GET com busca/URL/emoji também apanha "Forbidden" de alguns
    // firewalls. O JS manda tudo embrulhado em base64url no parâmetro _q; aqui devolvemos para o $_GET.
    if (isset($_GET['_q'])) {
        $dec = base64_decode(strtr((string)$_GET['_q'], '-_', '+/'), true);
        if ($dec !== false) {
            parse_str($dec, $g);
            if (is_array($g)) { $_GET = array_merge($g, $_GET); $_REQUEST = array_merge($g, $_REQUEST); }
        }
        unset($_GET['_q'], $_REQUEST['_q']);
    }
}

function user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        $ok = $u && $u['status'] === 'active';
        // Revenda ou master bloqueado bloqueia também quem está abaixo dele
        for ($pid = $u['parent_id'] ?? null, $i = 0; $ok && $pid && $i < 4; $i++) {
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p) break;
            if ($p['status'] !== 'active') $ok = false;
            $pid = $p['parent_id'];
        }
        if (!$ok) { $_SESSION = []; $u = null; }
    }
    return $u;
}

function is_admin(?array $u = null): bool { $u ??= user(); return $u && $u['role'] === 'admin'; }

/* ---------- Hierarquia: admin > master > revenda > usuário ---------- */

const ROLES = ['admin' => 'Administrador', 'ultra' => 'Ultra Master', 'master' => 'Master', 'revenda' => 'Revenda', 'user' => 'Usuário'];
const ROLE_RANK = ['user' => 0, 'revenda' => 1, 'master' => 2, 'ultra' => 3, 'admin' => 4];

/** Dono de plataforma: o admin (sistema base) ou um Ultra Master (plataforma própria). */
function is_owner(?array $u = null): bool { $u ??= user(); return $u && in_array($u['role'], ['admin', 'ultra'], true); }

function can_manage(array $u): bool { return ROLE_RANK[$u['role']] >= 1; }

/** Papéis que esta conta pode criar. */
function creatable_roles(array $u): array
{
    return ['admin' => ['user', 'revenda', 'master', 'ultra', 'admin'], 'ultra' => ['user', 'revenda', 'master'], 'master' => ['user', 'revenda'], 'revenda' => ['user']][$u['role']] ?? [];
}

/** O alvo está abaixo do ator na árvore? (admin enxerga todos) */
function in_tree(array $actor, array $target): bool
{
    if ($actor['role'] === 'admin') return true;
    if ($actor['role'] === 'ultra') return (int)($target['tenant_id'] ?? 0) === (int)$actor['id'];
    $st = db()->prepare('SELECT parent_id FROM users WHERE id = ?');
    $pid = $target['parent_id'];
    for ($i = 0; $i < 4 && $pid; $i++) {
        if ((int)$pid === (int)$actor['id']) return true;
        $st->execute([$pid]);
        $pid = $st->fetchColumn();
    }
    return false;
}

/**
 * Uso dos limites de uma conta. A cota de usuários é da árvore inteira:
 * conta os usuários diretos mais o que já foi reservado para as revendas abaixo.
 */
function quota(array $u): array
{
    $pdo = db();
    $one = function (string $sql) use ($pdo, $u) { $s = $pdo->prepare($sql); $s->execute([$u['id']]); return (int)$s->fetchColumn(); };
    if ($u['role'] === 'ultra') {
        // Na plataforma do Ultra Master contam os usuários criados por ele e os que se cadastraram sozinhos pelo link dele
        $direct = $one("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role = 'user' AND (parent_id IS NULL OR parent_id = tenant_id)");
        $given = $one("SELECT COALESCE(SUM(limit_users), 0) FROM users WHERE parent_id = ? AND role IN ('revenda', 'master')");
        $res = $one("SELECT COUNT(*) FROM users WHERE parent_id = ? AND role IN ('revenda', 'master')");
    } else {
        $direct = $one("SELECT COUNT(*) FROM users WHERE parent_id = ? AND role = 'user'");
        $given = $one("SELECT COALESCE(SUM(limit_users), 0) FROM users WHERE parent_id = ? AND role IN ('revenda', 'master')");
        $res = $one("SELECT COUNT(*) FROM users WHERE parent_id = ? AND role = 'revenda'");
    }
    $unl = $u['role'] === 'admin';
    return [
        'users_used' => $direct + $given, 'users_direct' => $direct, 'users_given' => $given,
        'users_max' => $unl ? null : (int)$u['limit_users'],
        'users_free' => $unl ? PHP_INT_MAX : max(0, (int)$u['limit_users'] - $direct - $given),
        'res_used' => $res, 'res_max' => $unl ? null : (int)$u['limit_resellers'],
        'res_free' => $unl ? PHP_INT_MAX : max(0, (int)$u['limit_resellers'] - $res),
    ];
}

function is_expired(array $u): bool
{
    return $u['role'] !== 'admin' && $u['expires_at'] !== null && $u['expires_at'] < now();
}

/**
 * Situação de uso da conta. Travada = entra e navega normalmente, mas não gera banners até assinar.
 * reason: 'trial_end' (teste de 1 dia acabou), 'trial_limit' (usou os banners do teste), 'expired' (plano vencido).
 */
function access_state(array $u): array
{
    if ($u['role'] === 'admin') return ['locked' => false, 'reason' => null, 'trial' => false, 'left' => null];
    $trial = !(int)($u['paid'] ?? 1);
    $left = $trial ? max(0, (int)setting('trial_limit', '15') - (int)$u['banners']) : null;
    if (is_expired($u)) return ['locked' => true, 'reason' => $trial ? 'trial_end' : 'expired', 'trial' => $trial, 'left' => $left];
    if ($trial && $left <= 0) return ['locked' => true, 'reason' => 'trial_limit', 'trial' => true, 'left' => 0];
    return ['locked' => false, 'reason' => null, 'trial' => $trial, 'left' => $left];
}

function brl(int $cents): string { return 'R$ ' . number_format($cents / 100, 2, ',', '.'); }

/** Código de indicação da conta (criado na primeira vez que é pedido). */
function ref_code(array $u): string
{
    if (($u['ref_code'] ?? '') !== '') return $u['ref_code'];
    $pdo = db();
    do {
        $code = strtoupper(substr(strtr(base64_encode(random_bytes(6)), '+/=', 'XYZ'), 0, 7));
        $st = $pdo->prepare('SELECT 1 FROM users WHERE ref_code = ?');
        $st->execute([$code]);
    } while ($st->fetch());
    $pdo->prepare('UPDATE users SET ref_code = ? WHERE id = ?')->execute([$code, $u['id']]);
    return $code;
}

/**
 * Preço de um plano para um usuário, já com o desconto de indicação:
 * - quem chegou por um link de indicação tem desconto na primeira assinatura;
 * - quem indicou ganha um cupom de desconto a cada indicado que assina (usa um por compra).
 */
function plan_price(array $plan, array $u): array
{
    $price = (int)$plan['price'];
    $pct = 0; $coupon = '';
    $bt = billing_tid($u);
    if (!(int)$u['paid'] && $u['referred_by']) { $pct = (int)tenant_setting($bt, 'ref_new_pct', '10'); $coupon = 'indicado'; }
    elseif ((int)$u['ref_coupons'] > 0) { $pct = (int)tenant_setting($bt, 'ref_owner_pct', '10'); $coupon = 'indicou'; }
    $pct = max(0, min(90, $pct));
    $discount = $pct ? (int)round($price * $pct / 100) : 0;
    if (!$discount) $coupon = '';
    return ['price' => $price, 'discount' => $discount, 'amount' => max(100, $price - $discount), 'pct' => $discount ? $pct : 0, 'coupon' => $coupon];
}

/** Chamada à API do Mercado Pago com o token do admin. Devolve [código HTTP, resposta decodificada]. */
function mp_api(string $method, string $path, ?array $body = null, string $idem = '', ?int $tid = null): array
{
    // $tid = plataforma que recebe o pagamento (null = sistema base). O token nunca é emprestado de outra plataforma.
    $token = trim((string)tenant_setting($tid, 'mp_token', ''));
    if ($token === '') return [0, ['message' => 'Pagamento ainda não configurado pelo responsável.']];
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'];
    if ($idem !== '') $headers[] = 'X-Idempotency-Key: ' . $idem;
    $ch = curl_init('https://api.mercadopago.com' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $j = json_decode((string)$raw, true);
    return [$code, is_array($j) ? $j : ['message' => $err ?: 'Resposta inválida do Mercado Pago']];
}

/**
 * Confirma um pedido como pago e libera o plano. Só age uma vez por pedido (a troca de status é atômica),
 * então webhook, consulta do navegador e aprovação manual podem chegar juntos sem liberar em dobro.
 */
function order_activate(int $orderId, string $note = ''): bool
{
    $pdo = db();
    $st = $pdo->prepare("UPDATE orders SET status = 'approved', paid_at = ?, note = ? WHERE id = ? AND status = 'pending'");
    $st->execute([now(), $note, $orderId]);
    if (!$st->rowCount()) return false;

    $o = $pdo->prepare('SELECT o.*, p.role AS p_role, p.months, p.limit_users AS p_lu, p.limit_resellers AS p_lr FROM orders o JOIN plans p ON p.id = o.plan_id WHERE o.id = ?');
    $o->execute([$orderId]);
    $o = $o->fetch();
    $us = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $us->execute([$o['user_id']]);
    $u = $us->fetch();
    if (!$o || !$u) return false;

    // Papel só sobe (um master que compra um plano comum continua master); limites ficam com o maior valor
    $role = ROLE_RANK[$o['p_role']] > ROLE_RANK[$u['role']] ? $o['p_role'] : $u['role'];
    $first = !(int)$u['paid'];
    $pdo->prepare('UPDATE users SET expires_at = ?, paid = 1, role = ?, limit_users = ?, limit_resellers = ?, ref_coupons = ref_coupons - ? WHERE id = ?')->execute([
        add_months($u['expires_at'], max(1, (int)$o['months'])), $role,
        max((int)$u['limit_users'], (int)$o['p_lu']), max((int)$u['limit_resellers'], (int)$o['p_lr']),
        $o['coupon'] === 'indicou' && (int)$u['ref_coupons'] > 0 ? 1 : 0, $u['id'],
    ]);
    // Primeira assinatura de um indicado: quem indicou ganha um cupom
    if ($first && $u['referred_by']) $pdo->prepare('UPDATE users SET ref_coupons = ref_coupons + 1 WHERE id = ?')->execute([$u['referred_by']]);
    return true;
}

/** Consulta um pagamento no Mercado Pago e, se aprovado e conferido, libera o pedido. Devolve o status do pedido. */
function order_sync(array $o): string
{
    if ($o['status'] !== 'pending' || $o['mp_id'] === '') return $o['status'];
    // O pagamento é consultado com o token da plataforma dona do plano
    $pt = db()->prepare('SELECT tenant_id FROM plans WHERE id = ?');
    $pt->execute([$o['plan_id']]);
    $tid = $pt->fetchColumn();
    [$code, $j] = mp_api('GET', '/v1/payments/' . rawurlencode($o['mp_id']), null, '', $tid ? (int)$tid : null);
    if ($code !== 200) return 'pending';
    // Confere que o pagamento é mesmo deste pedido e no valor certo antes de liberar
    $okRef = ($j['external_reference'] ?? '') === 'GG-' . $o['id'];
    $okVal = (float)($j['transaction_amount'] ?? 0) + 0.001 >= $o['amount'] / 100;
    if (($j['status'] ?? '') === 'approved' && $okRef && $okVal) { order_activate((int)$o['id'], 'Mercado Pago ' . $o['mp_id']); return 'approved'; }
    if (in_array($j['status'] ?? '', ['cancelled', 'rejected', 'refunded', 'charged_back'], true)) {
        db()->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$o['id']]);
        return 'cancelled';
    }
    return 'pending';
}

function days_left(array $u): ?int
{
    if ($u['role'] === 'admin' || $u['expires_at'] === null) return null;
    return (int)ceil((strtotime($u['expires_at']) - time()) / 86400);
}

/** Exige login (e plano ativo, salvo $allowExpired). $api responde em JSON em vez de redirecionar. */
function guard(bool $api = false, bool $allowExpired = false, bool $admin = false): array
{
    $u = user();
    if (!$u) {
        if ($api) json_out(['error' => 'Sessão expirada. Faça login novamente.'], 401);
        redirect('/login.php');
    }
    if ($admin && !is_admin($u)) {
        if ($api) json_out(['error' => 'Acesso restrito ao administrador.'], 403);
        redirect('/painel.php');
    }
    // Conta travada (teste encerrado ou plano vencido) entra e navega normalmente; só as APIs que geram banners recusam
    if ($api && !$allowExpired) {
        $ac = access_state($u);
        if ($ac['locked']) json_out(['error' => $ac['reason'] === 'expired' ? 'Seu plano venceu. Assine para continuar gerando.' : 'Seu teste grátis terminou. Assine um plano para continuar gerando.', 'locked' => true], 402);
    }
    return $u;
}

/** Páginas de dono de plataforma: admin (sistema base) ou Ultra Master (a plataforma dele). */
function guard_owner(): array
{
    $u = guard();
    if (!is_owner($u)) redirect('/painel.php');
    if (access_state($u)['locked']) { flash('Seu plano venceu. Renove para voltar a administrar sua plataforma.', 'err'); redirect('/assinar.php'); }
    return $u;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }

function csrf_check(bool $api = false): void
{
    $t = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf(), $t)) {
        if ($api) json_out(['error' => 'Token inválido. Recarregue a página.'], 419);
        http_response_code(419);
        exit('Token inválido. Volte e recarregue a página.');
    }
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

/* ---------- Utilidades ---------- */

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE . $p; }
function redirect(string $p): never { header('Location: ' . url($p)); exit; }

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Total de modelos disponíveis (banners ativos + padrões de motion), para divulgar "mais de X modelos". */
function models_total(): int
{
    $n = MOTION_PATTERNS;
    foreach (array_keys(LAYOUTS) as $kind) $n += count(layouts_enabled($kind));
    return $n;
}
function models_claim(): string
{
    $n = models_total();
    return 'Mais de ' . (intdiv($n, 10) * 10) . ' modelos prontos';
}

const HUB_CARDS = ['futebol' => 'Destaques do Futebol', 'vods' => 'VODs', 'valor' => 'Banners com Valor', 'divulgacao' => 'Banners Divulgação', 'revendas' => 'Captação de Revendas',
    'apps' => 'Artes para Aplicativos', 'videos' => 'Vídeos Divulgação', 'tutoriais' => 'Vídeos Tutoriais', 'cortes' => 'Cortes Virais', 'codigo' => 'Código para Baixar Apps'];

/** Arte de um cartão da Divulgação: a enviada pelo admin ou a padrão. */
function hub_art(string $key): string
{
    $f = (string)setting('hub_img_' . $key, '');
    if ($f !== '' && is_file(ROOT . '/uploads/' . $f)) return url('/uploads/' . $f);
    return is_file(ROOT . "/assets/hub/$key.jpg") ? url("/assets/hub/$key.jpg") : '';
}

function library(string $section): array
{
    $st = db()->prepare('SELECT * FROM library WHERE section = ? AND ' . tenant_where(tenant_ctx()) . ' ORDER BY sort, id DESC');
    $st->execute([$section]);
    return $st->fetchAll();
}

/** Endereço completo do sistema (para a mensagem de boas-vindas). */
function site_link(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE . '/';
}

/** Mensagem de boas-vindas pronta para enviar ao cliente recém-criado. Linhas com campo vazio são retiradas. */
function welcome_message(array $vars): string
{
    $tpl = (string)setting('welcome_msg', WELCOME_MSG);
    $out = [];
    // explode em vez de \R: sem o modo UTF-8, \R corta emojis que contêm o byte 0x85
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $tpl)) as $line) {
        $skip = false;
        $line = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars, &$skip) {
            $v = (string)($vars[$m[1]] ?? '');
            if ($v === '' && array_key_exists($m[1], $vars)) $skip = true;
            return $v;
        }, $line);
        if (!$skip) $out[] = $line;
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
}

/* ---------- Servidor de licenças (só o Gerador Gold assina e gerencia) ---------- */

/** Normaliza um domínio para comparação: tira protocolo, porta, www e barra. */
function lic_domain(string $d): string
{
    $d = strtolower(trim($d));
    $d = (string)preg_replace('#^https?://#', '', $d);
    $d = explode('/', $d)[0];
    $d = explode(':', $d)[0];
    return (string)preg_replace('#^www\.#', '', $d);
}

/** Chave de licença nova no formato GG-XXXX-XXXX-XXXX-XXXX (sem caracteres ambíguos). */
function lic_new_key(): string
{
    $al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];
    for ($g = 0; $g < 4; $g++) { $s = ''; for ($i = 0; $i < 4; $i++) $s .= $al[random_int(0, 31)]; $parts[] = $s; }
    return 'GG-' . implode('-', $parts);
}

/**
 * Registra um acesso de licença (domínio, IP, marca do comprador). Marca como "uso estranho" quando o
 * domínio difere do licenciado, e conta as violações na licença. É assim que se descobre quem vazou.
 */
function lic_record_hit(array $lic, string $domain, string $ip, string $build): void
{
    $foreign = $lic['domain'] !== '' && lic_domain($lic['domain']) !== $domain ? 1 : 0;
    $pdo = db();
    $pdo->prepare('INSERT INTO license_hits (license_id, lkey, domain, ip, build, foreign_use, first_seen, last_seen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(license_id, domain, ip) DO UPDATE SET hits = hits + 1, last_seen = excluded.last_seen, build = excluded.build, foreign_use = excluded.foreign_use')
        ->execute([$lic['id'], $lic['lkey'], $domain, $ip, mb_substr($build, 0, 60), $foreign, now(), now()]);
    // Nº de domínios distintos além do licenciado = sinal de revenda
    $v = $pdo->prepare('SELECT COUNT(DISTINCT domain) FROM license_hits WHERE license_id = ? AND foreign_use = 1');
    $v->execute([$lic['id']]);
    $pdo->prepare('UPDATE licenses SET violations = ? WHERE id = ?')->execute([(int)$v->fetchColumn(), $lic['id']]);
}

/** Resposta assinada do servidor de licença. O cliente confere com a chave pública embutida nele. */
function lic_sign(array $payload): array
{
    $payload['issued'] = time();
    $msg = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $priv = @openssl_pkey_get_private((string)@file_get_contents(DATA . '/license_private.pem'));
    $sig = '';
    if ($priv) openssl_sign($msg, $sig, $priv, OPENSSL_ALGO_SHA256);
    return ['payload' => $msg, 'sig' => base64_encode($sig)];
}

function is_hex(string $c): bool { return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c); }

function brand_of(array $u): array
{
    $def = array_merge(BRAND_DEFAULTS, array_intersect_key(json_decode((string)setting('default_brand', '{}'), true) ?: [], BRAND_DEFAULTS));
    $b = array_merge($def, array_intersect_key(json_decode($u['brand'] ?: '{}', true) ?: [], BRAND_DEFAULTS));
    foreach (['logo', 'bgImage'] as $k) {
        $b[$k . 'Url'] = ($b[$k] && is_file(ROOT . '/uploads/' . $b[$k])) ? url('/uploads/' . $b[$k]) : '';
    }
    return $b;
}

/** Soma meses a partir do vencimento atual (ou de agora, se já vencido). */
function add_months(?string $from, int $months): string
{
    $base = ($from && $from > now()) ? new DateTime($from) : new DateTime();
    return $base->modify("+$months month")->format('Y-m-d 23:59:59');
}

function http_get(string $url, array $headers = [], int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'GeradorGold/1.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $res = ['code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => $body === false ? '' : $body, 'error' => curl_error($ch)];
    curl_close($ch);
    return $res;
}

function cache_get(string $key, int $ttl)
{
    $f = DATA . '/cache/' . sha1($key) . '.json';
    if (is_file($f) && filemtime($f) > time() - $ttl) return json_decode((string)file_get_contents($f), true);
    return null;
}

function cache_put(string $key, $val): void
{
    @file_put_contents(DATA . '/cache/' . sha1($key) . '.json', json_encode($val, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
}

function cache_clear(): int
{
    $n = 0;
    foreach (array_merge(glob(DATA . '/cache/*.json') ?: [], glob(DATA . '/cache/img/*') ?: [], glob(ROOT . '/uploads/c/*') ?: []) as $f) { if (@unlink($f)) $n++; }
    return $n;
}

/** Valida e regrava uma imagem enviada (remove qualquer conteúdo que não seja imagem). Retorna o nome do arquivo. */
function save_upload(array $file, int $maxSide, bool $alpha): string
{
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio do arquivo.');
    if ($file['size'] > 6 * 1024 * 1024) throw new RuntimeException('Imagem muito grande (máx. 6 MB).');
    $info = @getimagesize($file['tmp_name']);
    $src = match ($info[2] ?? 0) {
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if (!$src) throw new RuntimeException('Formato inválido. Use PNG, JPG ou WEBP.');
    $w = imagesx($src); $h = imagesy($src);
    $k = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $name = bin2hex(random_bytes(12)) . ($alpha ? '.png' : '.jpg');
    $ok = $alpha ? imagepng($dst, ROOT . '/uploads/' . $name, 6) : imagejpeg($dst, ROOT . '/uploads/' . $name, 88);
    imagedestroy($src); imagedestroy($dst);
    if (!$ok) throw new RuntimeException('Não foi possível salvar a imagem.');
    return $name;
}

/**
 * Copia imagens externas (escudos, logos) para uploads/c e devolve [url => endereço local].
 * Arquivo estático não ocupa processo PHP a cada exibição — importante em hospedagem compartilhada.
 */
function mirror_images(array $urls): array
{
    $dir = ROOT . '/uploads/c';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $map = [];
    $todo = [];
    foreach (array_unique(array_filter($urls)) as $u) {
        $h = sha1($u);
        foreach ($exts as $e) {
            if (is_file("$dir/$h.$e")) { $map[$u] = url("/uploads/c/$h.$e"); continue 2; }
        }
        // Falhou há menos de 1 hora: não insiste (evita prender o servidor em fonte fora do ar)
        if (is_file("$dir/$h.x") && filemtime("$dir/$h.x") > time() - 3600) continue;
        if (count($todo) < 80) $todo[$h] = $u;
    }
    if (!$todo) return $map;

    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 8);
    $hs = [];
    foreach ($todo as $h => $u) {
        $p = parse_url($u);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = $p['host'] ?? '';
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($host !== '' ? (gethostbynamel($host) ?: []) : []);
        // Só http/https em portas padrão e IP público, fixado na conexão (evita SSRF e DNS rebinding)
        $ok = in_array($scheme, ['http', 'https'], true) && in_array($port, [80, 443], true) && $ips;
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) $ok = false;
        }
        if (!$ok) { @touch("$dir/$h.x"); continue; }
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_RESOLVE => ["$host:$port:{$ips[0]}"], CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_MAXFILESIZE => 3 * 1024 * 1024,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 GeradorGold/1.0',
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$h] = $ch;
    }
    $deadline = microtime(true) + 12;
    do {
        $st = curl_multi_exec($mh, $run);
        if ($run) curl_multi_select($mh, 0.5);
    } while ($run && $st === CURLM_OK && microtime(true) < $deadline);

    $fi = new finfo(FILEINFO_MIME_TYPE);
    foreach ($hs as $h => $ch) {
        $body = (string)curl_multi_getcontent($ch);
        $e = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $body !== '' && strlen($body) <= 3 * 1024 * 1024) ? ($exts[$fi->buffer($body)] ?? null) : null;
        if ($e && file_put_contents("$dir/$h.$e", $body, LOCK_EX)) $map[$todo[$h]] = url("/uploads/c/$h.$e");
        else @touch("$dir/$h.x");
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $map;
}

/**
 * Paleta sugerida a partir da logo: destaque e secundária saem das cores mais presentes e vivas da imagem;
 * o fundo é um quase-preto puxado para o tom do destaque. Devolve null se a logo não tiver cor (preto/branco/cinza).
 */
function logo_palette(string $file): ?array
{
    $src = @imagecreatefromstring((string)@file_get_contents($file));
    if (!$src) return null;
    imagepalettetotruecolor($src);
    $w = imagesx($src); $h = imagesy($src);
    $n = 72; $im = imagecreatetruecolor($n, $n);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagecopyresampled($im, $src, 0, 0, 0, 0, $n, $n, $w, $h);
    imagedestroy($src);

    $toHsv = function (int $r, int $g, int $b): array {
        $r /= 255; $g /= 255; $b /= 255;
        $mx = max($r, $g, $b); $mn = min($r, $g, $b); $d = $mx - $mn;
        $hh = $d == 0 ? 0 : ($mx == $r ? fmod(($g - $b) / $d, 6) : ($mx == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4));
        $hh *= 60; if ($hh < 0) $hh += 360;
        return [$hh, $mx == 0 ? 0 : $d / $mx, $mx];
    };
    $toHex = function (float $hh, float $s, float $v): string {
        $c = $v * $s; $x = $c * (1 - abs(fmod($hh / 60, 2) - 1)); $m = $v - $c;
        [$r, $g, $b] = [[$c, $x, 0], [$x, $c, 0], [0, $c, $x], [0, $x, $c], [$x, 0, $c], [$c, 0, $x]][(int)floor($hh / 60) % 6];
        return sprintf('#%02X%02X%02X', (int)round(($r + $m) * 255), (int)round(($g + $m) * 255), (int)round(($b + $m) * 255));
    };

    // Histograma de matiz (24 faixas de 15°), pesando cores mais saturadas e claras; cinzas e quase-pretos ficam de fora
    $bins = array_fill(0, 24, ['w' => 0.0, 'x' => 0.0, 'y' => 0.0, 's' => 0.0, 'v' => 0.0]);
    $total = 0.0;
    for ($y = 0; $y < $n; $y++) for ($x = 0; $x < $n; $x++) {
        $c = imagecolorat($im, $x, $y);
        if ((($c >> 24) & 127) > 64) continue;
        [$hh, $s, $v] = $toHsv(($c >> 16) & 255, ($c >> 8) & 255, $c & 255);
        if ($s < 0.28 || $v < 0.22) continue;
        $wgt = $s * $v;
        $b = (int)floor($hh / 15) % 24;
        $bins[$b]['w'] += $wgt; $bins[$b]['x'] += cos(deg2rad($hh)) * $wgt; $bins[$b]['y'] += sin(deg2rad($hh)) * $wgt;
        $bins[$b]['s'] += $s * $wgt; $bins[$b]['v'] += $v * $wgt;
        $total += $wgt;
    }
    imagedestroy($im);
    if ($total < 12) return null; // logo sem cor suficiente

    // Junta faixas vizinhas para não dividir uma mesma cor em duas
    $score = [];
    foreach ($bins as $i => $b) $score[$i] = $b['w'] + 0.5 * ($bins[($i + 23) % 24]['w'] + $bins[($i + 1) % 24]['w']);
    arsort($score);
    $pick = function (int $i) use ($bins): array {
        $w = 0; $x = 0; $y = 0; $s = 0; $v = 0;
        foreach ([($i + 23) % 24, $i, ($i + 1) % 24] as $k) { $w += $bins[$k]['w']; $x += $bins[$k]['x']; $y += $bins[$k]['y']; $s += $bins[$k]['s']; $v += $bins[$k]['v']; }
        $hh = rad2deg(atan2($y, $x)); if ($hh < 0) $hh += 360;
        return $w > 0 ? [$hh, $s / $w, $v / $w, $w] : [0.0, 0.0, 0.0, 0.0];
    };
    $ids = array_keys($score);
    [$h1, $s1, $v1] = $pick($ids[0]);
    // Destaque precisa aparecer sobre fundo escuro: garante brilho e saturação mínimos
    $primary = $toHex($h1, max(0.55, min(1, $s1)), max(0.78, min(1, $v1)));

    $secondary = null;
    foreach (array_slice($ids, 1) as $i) {
        [$h2, $s2, $v2, $w2] = $pick($i);
        $dist = min(abs($h2 - $h1), 360 - abs($h2 - $h1));
        if ($dist >= 28 && $w2 >= $total * 0.08) { $secondary = $toHex($h2, max(0.5, min(1, $s2)), max(0.7, min(1, $v2))); break; }
    }
    // Logo de uma cor só: a secundária é a mesma cor mais escura, para os degradês não ficarem chapados
    $secondary ??= $toHex($h1, max(0.6, min(1, $s1 * 1.05)), max(0.5, min(0.8, $v1 * 0.7)));

    return ['primary' => $primary, 'secondary' => $secondary, 'bg' => $toHex($h1, 0.42, 0.075), 'text' => '#FFFFFF'];
}

function delete_upload(string $name): void
{
    if ($name !== '' && preg_match('/^[a-f0-9]{24}\.(png|jpg)$/', $name)) @unlink(ROOT . '/uploads/' . $name);
}
