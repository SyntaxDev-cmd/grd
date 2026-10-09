<?php
require __DIR__ . '/../inc/boot.php';
$me = guard(true, true); // conta sem plano ativo ainda navega e vê a prévia; o que é barrado é gerar (api/user.php)
session_write_close();

function dig($arr, string $path)
{
    if ($path === '') return $arr;
    foreach (explode('.', $path) as $k) {
        if (!is_array($arr) || !array_key_exists($k, $arr)) return null;
        $arr = $arr[$k];
    }
    return $arr;
}

/** Lê um campo pelo mapeamento do admin; se não existir, tenta nomes comuns em outras APIs. */
function pick(array $row, array $map, string $field)
{
    static $alias = [
        'time1' => ['time1', 'home', 'home_team', 'mandante', 'team1', 'homeTeam.name', 'teams.home.name'],
        'time2' => ['time2', 'away', 'away_team', 'visitante', 'team2', 'awayTeam.name', 'teams.away.name'],
        'logo1' => ['img_time1_url', 'home_logo', 'logo1', 'escudo_mandante', 'homeTeam.logo', 'teams.home.logo'],
        'logo2' => ['img_time2_url', 'away_logo', 'logo2', 'escudo_visitante', 'awayTeam.logo', 'teams.away.logo'],
        'hora' => ['horario', 'hora', 'time', 'start_time', 'hour'],
        'data' => ['data_jogo', 'data', 'date', 'dia'],
        'competicao' => ['competicao', 'campeonato', 'league', 'competition', 'league.name', 'liga'],
        'logo_competicao' => ['img_competicao_url', 'league_logo', 'logo_campeonato', 'league.logo'],
        'canais' => ['canais', 'channels', 'canal', 'transmissao', 'tv'],
        'placar1' => ['placar_time1', 'home_score', 'placar1', 'goals.home'],
        'placar2' => ['placar_time2', 'away_score', 'placar2', 'goals.away'],
        'status' => ['status', 'situacao', 'state'],
    ];
    $keys = array_merge(empty($map[$field]) ? [] : [(string)$map[$field]], $alias[$field] ?? []);
    foreach ($keys as $k) {
        $v = dig($row, $k);
        if ($v !== null && $v !== '') return $v;
    }
    return null;
}

function source_games(array $src, int $ttl): array
{
    $key = 'games:' . $src['id'] . ':' . md5($src['url'] . $src['headers'] . $src['root'] . $src['map']);
    $hit = cache_get($key, $ttl);
    if ($hit !== null) return $hit;

    $headers = array_values(array_filter(array_map('trim', preg_split('/\R/', $src['headers'])), fn($h) => str_contains($h, ':')));
    $r = http_get($src['url'], $headers, 20);
    $j = json_decode($r['body'], true);
    if ($r['code'] !== 200 || !is_array($j)) {
        return ['error' => $r['error'] ?: 'HTTP ' . $r['code'] . ' / resposta não é JSON', 'games' => []];
    }
    $list = dig($j, trim($src['root']));
    if (!is_array($list)) return ['error' => 'Caminho raiz "' . $src['root'] . '" não encontrado', 'games' => []];
    if (!array_is_list($list)) $list = array_values($list);

    $map = json_decode($src['map'], true) ?: [];
    $chKey = (string)($map['canal_nome'] ?? 'nome');
    $str = fn($v) => is_scalar($v) ? trim((string)$v) : '';
    $games = [];
    foreach ($list as $row) {
        if (!is_array($row)) continue;
        $t1 = $str(pick($row, $map, 'time1')); $t2 = $str(pick($row, $map, 'time2'));
        if ($t1 === '' || $t2 === '') continue;

        $ch = pick($row, $map, 'canais');
        if (is_string($ch)) $ch = preg_split('/\s*[,;|\/]\s*/', $ch);
        $channels = [];
        $chLogos = [];
        foreach (is_array($ch) ? $ch : [] as $c) {
            $n = is_array($c) ? $str($c[$chKey] ?? $c['nome'] ?? $c['name'] ?? '') : $str($c);
            if ($n === '') continue;
            $channels[] = $n;
            $img = is_array($c) ? $str($c['img_url'] ?? $c['logo'] ?? $c['image'] ?? '') : '';
            if ($img !== '') $chLogos[$n] = $img;
        }
        $hora = $str(pick($row, $map, 'hora'));
        if (preg_match('/(\d{1,2}):(\d{2})/', $hora, $m)) $hora = sprintf('%02d:%s', $m[1], $m[2]);
        $games[] = [
            't1' => $t1, 't2' => $t2,
            'l1' => $str(pick($row, $map, 'logo1')), 'l2' => $str(pick($row, $map, 'logo2')),
            'time' => $hora, 'date' => $str(pick($row, $map, 'data')),
            'comp' => $str(pick($row, $map, 'competicao')), 'compLogo' => $str(pick($row, $map, 'logo_competicao')),
            'channels' => array_values(array_unique($channels)), 'chLogos' => $chLogos,
            's1' => $str(pick($row, $map, 'placar1')), 's2' => $str(pick($row, $map, 'placar2')),
            'status' => $str(pick($row, $map, 'status')),
            'src' => $src['name'],
        ];
    }
    $out = ['error' => null, 'games' => $games];
    if ($games) cache_put($key, $out);
    return $out;
}

// Teste de uma fonte (admin)
if (isset($_GET['test'])) {
    if (!is_admin($me)) json_out(['error' => 'Acesso restrito.'], 403);
    $st = db()->prepare('SELECT * FROM sports_apis WHERE id = ?');
    $st->execute([(int)$_GET['test']]);
    $src = $st->fetch();
    if (!$src) json_out(['error' => 'Fonte não encontrada.'], 404);
    $r = source_games($src, 0);
    json_out(['error' => $r['error'], 'count' => count($r['games']), 'sample' => array_slice($r['games'], 0, 3)]);
}

/** Agenda gratuita da ESPN (sem chave): uma consulta por campeonato, em paralelo. Não informa canais de TV. */
function espn_games(DateTime $date, array $leagues, int $ttl): array
{
    $key = 'espn:' . $date->format('Ymd') . ':' . md5(implode(',', $leagues));
    $hit = cache_get($key, $ttl);
    if ($hit !== null) return $hit;

    $mh = curl_multi_init();
    $hs = [];
    foreach ($leagues as $lg) {
        $ch = curl_init('https://site.api.espn.com/apis/site/v2/sports/soccer/' . rawurlencode($lg) . '/scoreboard?dates=' . $date->format('Ymd') . '&lang=pt&region=br&limit=100');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'GeradorGold/1.0']);
        curl_multi_add_handle($mh, $ch);
        $hs[$lg] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st === CURLM_OK);

    $tz = new DateTimeZone('America/Sao_Paulo');
    $games = [];
    $ok = 0;
    foreach ($hs as $lg => $ch) {
        $j = json_decode((string)curl_multi_getcontent($ch), true);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && is_array($j)) $ok++;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $league = $j['leagues'][0] ?? [];
        foreach ($j['events'] ?? [] as $ev) {
            $co = $ev['competitions'][0] ?? [];
            $home = $away = null;
            foreach ($co['competitors'] ?? [] as $c) { if (($c['homeAway'] ?? '') === 'home') $home = $c; else $away = $c; }
            if (!$home || !$away) continue;
            try { $when = (new DateTime((string)$ev['date']))->setTimezone($tz); } catch (Exception $e) { continue; }
            $state = $ev['status']['type']['state'] ?? 'pre';
            $ch2 = [];
            foreach ($co['broadcasts'] ?? [] as $b) foreach ($b['names'] ?? [] as $n) $ch2[] = (string)$n;
            $name = fn($c) => (string)($c['team']['shortDisplayName'] ?? $c['team']['displayName'] ?? '');
            $games[] = [
                't1' => $name($home), 't2' => $name($away),
                'l1' => (string)($home['team']['logo'] ?? ''), 'l2' => (string)($away['team']['logo'] ?? ''),
                'time' => $when->format('H:i'), 'date' => $when->format('Y-m-d'),
                'comp' => (string)($league['name'] ?? ''), 'compLogo' => (string)($league['logos'][0]['href'] ?? ''),
                'channels' => array_values(array_unique($ch2)),
                's1' => $state === 'pre' ? '' : (string)($home['score'] ?? ''), 's2' => $state === 'pre' ? '' : (string)($away['score'] ?? ''),
                'status' => $state === 'pre' ? '' : ($state === 'post' ? 'FIM' : (string)($ev['status']['displayClock'] ?? 'AO VIVO')),
                'src' => 'ESPN',
            ];
        }
    }
    curl_multi_close($mh);
    $out = ['error' => $ok ? null : 'ESPN indisponível', 'games' => $games];
    if ($ok) cache_put($key, $out);
    return $out;
}

/**
 * Logo local de um canal pelo nome (acervo em assets/canais). Devolve [arquivo, sufixo]:
 * "PREMIERE 5" usa o logo "premiere" e mantém o "5" como texto. null se não houver logo.
 */
function ch_logo(string $name): ?array
{
    static $files = null;
    if ($files === null) {
        $files = [];
        // índice do acervo: proporção (a) e se o logo é claro (l)
        foreach (json_decode((string)@file_get_contents(ROOT . '/assets/canais/index.json'), true) ?: [] as $it) {
            if (is_file(ROOT . '/assets/canais/t/' . $it['f'])) $files[basename($it['f'], '.png')] = [(float)($it['a'] ?? 1), (int)($it['l'] ?? 1)];
        }
    }
    $alias = ['recordtv' => 'record', 'tvglobo' => 'globo', 'redeglobo' => 'globo', 'hbomax' => 'max', 'amazonprimevideo' => 'primevideo', 'amazonprime' => 'primevideo',
        'prime' => 'primevideo', 'disney' => 'disneyplus', 'paramount' => 'paramountplus', 'tntsports' => 'tnt', 'bandeirantes' => 'band', 'caze' => 'cazetv',
        'tvcultura' => 'cultura', 'ge' => 'getv', 'nsportsplus' => 'nsports', 'goat' => 'canalgoat', 'premierefc' => 'premiere', 'canalgoat' => 'canalgoat', 'youtube' => ''];
    $n = strtr(mb_strtolower($name), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', '+' => 'plus']);
    $cands = [$n];
    if (preg_match('/\(([^)]+)\)/', $n, $m)) { $cands[] = $m[1]; $cands[] = (string)preg_replace('/\s*\([^)]*\)/', '', $n); }
    foreach ($cands as $c) {
        $k = (string)preg_replace('/[^a-z0-9]/', '', $c);
        foreach ([[$k, ''], preg_match('/^(.*?[a-z])(\d{1,2})$/', $k, $m) ? [$m[1], $m[2]] : null] as $try) {
            if (!$try || $try[0] === '') continue;
            $key = $alias[$try[0]] ?? $try[0];
            if ($key !== '' && isset($files[$key])) return [$key . '.png', $try[1], $files[$key][0], $files[$key][1]];
        }
    }
    return null;
}

// Logos dos canais que aparecem nas fontes de hoje (para os banners de marketing)
if (isset($_GET['channels'])) {
    $logos = [];
    foreach (db()->query('SELECT * FROM sports_apis WHERE active = 1 ORDER BY id') as $src) {
        if (str_contains($src['url'], '{')) continue;
        foreach (source_games($src, max(30, (int)setting('games_ttl', '120')))['games'] as $g) $logos += $g['chLogos'] ?? [];
    }
    $local = mirror_images(array_values($logos));
    json_out(['logos' => array_values(array_unique(array_filter(array_map(fn($u) => $local[$u] ?? null, array_values($logos)))))]);
}

// Dia pedido: 0 = hoje … 6 = daqui a seis dias
$day = max(0, min(6, (int)($_GET['day'] ?? 0)));
$date = new DateTime("today +$day day");
$fresh = isset($_GET['fresh']);
$ttl = $fresh ? 20 : max(30, (int)setting('games_ttl', '120'));
$all = [];
$sources = [];
$seen = [];
$add = function (string $name, array $r) use (&$all, &$sources, &$seen) {
    $sources[] = ['name' => $name, 'count' => count($r['games']), 'error' => $r['error']];
    foreach ($r['games'] as $g) {
        $k = mb_strtolower($g['t1'] . '|' . $g['t2'] . '|' . $g['time']);
        if (isset($seen[$k])) continue; // mesma partida vinda de duas fontes
        $seen[$k] = 1;
        $all[] = $g;
    }
};
foreach (db()->query('SELECT * FROM sports_apis WHERE active = 1 ORDER BY id') as $src) {
    // Fontes com {data} na URL aceitam qualquer dia; as demais só informam os jogos de hoje
    $dated = str_contains($src['url'], '{');
    if ($day > 0 && !$dated) continue;
    $src['url'] = strtr($src['url'], ['{data}' => $date->format('Y-m-d'), '{data_br}' => rawurlencode($date->format('d/m/Y')), '{Ymd}' => $date->format('Ymd'), '{dia}' => (string)$day]);
    $add($src['name'], source_games($src, $ttl));
}
// ESPN: sempre para os próximos dias; para hoje, só quando as outras fontes não trouxeram nada
if (setting('espn_on', '1') === '1' && ($day > 0 || !$all)) {
    $leagues = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)setting('espn_leagues', ESPN_LEAGUES))), fn($l) => (bool)preg_match('/^[a-z0-9._]+$/', $l)));
    if ($leagues) $add('ESPN', espn_games($date, array_slice($leagues, 0, 40), $fresh ? 60 : ($day > 0 ? 1800 : 120)));
}
usort($all, fn($a, $b) => $a['time'] <=> $b['time']);
// Escudos e logos passam a ser arquivos locais: o navegador não precisa chamar o PHP para cada imagem
$urls = [];
foreach ($all as $g) {
    array_push($urls, $g['l1'], $g['l2'], $g['compLogo']);
    // logo do canal vindo da própria API só é baixado quando não existe no acervo local
    foreach ($g['channels'] as $c) if (!ch_logo($c) && !empty($g['chLogos'][$c])) $urls[] = $g['chLogos'][$c];
}
$local = mirror_images($urls);
foreach ($all as $i => &$g) {
    $g['id'] = $i;
    foreach (['l1', 'l2', 'compLogo'] as $k) if (isset($local[$g[$k]])) $g[$k] = $local[$g[$k]];
    // ch: um item por canal — n (nome), i (imagem ou ''), x (número que acompanha o logo), t (1 = bloco do acervo, 0 = logo original)
    $g['ch'] = [];
    foreach ($g['channels'] as $c) {
        $lg = ch_logo($c);
        $raw = $local[$g['chLogos'][$c] ?? ''] ?? '';
        $g['ch'][] = $lg ? ['n' => $c, 'i' => url('/assets/canais/t/' . $lg[0]), 'x' => $lg[1], 't' => 1, 'a' => $lg[2], 'l' => $lg[3]] : ['n' => $c, 'i' => $raw, 'x' => '', 't' => 0];
    }
    unset($g['chLogos']);
}
unset($g);
json_out(['games' => $all, 'sources' => $sources, 'day' => $day, 'date' => $date->format('Y-m-d')]);
