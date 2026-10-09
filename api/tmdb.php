<?php
require __DIR__ . '/../inc/boot.php';
guard(true, true); // conta sem plano ativo ainda navega e vê a prévia; o que é barrado é gerar (api/user.php)
session_write_close();

function tmdb(string $path, array $q = [], int $ttl = 21600): array
{
    $q += ['language' => setting('tmdb_lang', 'pt-BR')];
    $token = trim((string)setting('tmdb_token'));
    $headers = ['Accept: application/json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    else $q['api_key'] = trim((string)setting('tmdb_key'));
    $url = 'https://api.themoviedb.org/3' . $path . '?' . http_build_query($q);

    $hit = cache_get('tmdb:' . $url, $ttl);
    if ($hit !== null) return $hit;
    $r = http_get($url, $headers);
    $j = json_decode($r['body'], true);
    if ($r['code'] !== 200 || !is_array($j)) {
        json_out(['error' => 'TMDB: ' . ($j['status_message'] ?? $r['error'] ?: 'erro ' . $r['code'])], 502);
    }
    cache_put('tmdb:' . $url, $j);
    return $j;
}

function norm(array $r, ?string $type = null): ?array
{
    $t = $r['media_type'] ?? $type;
    if (!in_array($t, ['movie', 'tv'], true) || empty($r['poster_path'])) return null;
    return [
        'id' => $r['id'], 'type' => $t,
        'title' => $r['title'] ?? $r['name'] ?? '',
        'year' => substr((string)($r['release_date'] ?? $r['first_air_date'] ?? ''), 0, 4),
        'rating' => round((float)($r['vote_average'] ?? 0), 1),
        'overview' => $r['overview'] ?? '',
        'poster' => $r['poster_path'], 'backdrop' => $r['backdrop_path'] ?? '',
    ];
}

$cats = [
    'trending_all'   => ['/trending/all/week', null],
    'trending_day'   => ['/trending/all/day', null],
    'movie_now'      => ['/movie/now_playing', 'movie'],
    'movie_popular'  => ['/movie/popular', 'movie'],
    'movie_upcoming' => ['/movie/upcoming', 'movie'],
    'movie_top'      => ['/movie/top_rated', 'movie'],
    'tv_popular'     => ['/tv/popular', 'tv'],
    'tv_air'         => ['/tv/on_the_air', 'tv'],
    'tv_top'         => ['/tv/top_rated', 'tv'],
];

$a = $_GET['a'] ?? 'list';

if ($a === 'list' || $a === 'search') {
    $page = max(1, min(20, (int)($_GET['page'] ?? 1)));
    if ($a === 'search') {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') json_out(['items' => []]);
        $j = tmdb('/search/multi', ['query' => $q, 'page' => $page, 'include_adult' => 'false'], 3600);
        $type = null;
    } else {
        [$path, $type] = $cats[$_GET['cat'] ?? ''] ?? $cats['trending_all'];
        $j = tmdb($path, ['page' => $page, 'region' => setting('tmdb_region', 'BR')]);
    }
    $items = array_values(array_filter(array_map(fn($r) => norm($r, $type), $j['results'] ?? [])));
    json_out(['items' => $items, 'pages' => (int)($j['total_pages'] ?? 1)]);
}

// Logos das principais plataformas de streaming no Brasil (dados de disponibilidade do TMDB/JustWatch)
if ($a === 'providers') {
    $j = tmdb('/watch/providers/movie', ['watch_region' => setting('tmdb_region', 'BR')], 86400);
    $list = array_values(array_filter($j['results'] ?? [], fn($p) => !empty($p['logo_path'])));
    usort($list, fn($x, $y) => ($x['display_priorities']['BR'] ?? $x['display_priority'] ?? 999) <=> ($y['display_priorities']['BR'] ?? $y['display_priority'] ?? 999));
    // Só as plataformas principais, na ordem em que o público as reconhece (ignora lojas de aluguel e variações com anúncios)
    $want = ['Netflix', 'Amazon Prime Video', 'Disney Plus', 'HBO Max', 'Max', 'Globoplay', 'Apple TV', 'Apple TV Plus', 'Paramount Plus', 'Crunchyroll',
        'Telecine Amazon Channel', 'Claro video', 'Looke', 'MUBI', 'Pluto TV', 'Star Plus', 'Discovery+', 'Univer Video', 'Oldflix', 'Reserva Imovision'];
    $by = [];
    foreach ($list as $p) $by[(string)$p['provider_name']] ??= $p['logo_path'];
    $out = [];
    foreach ($want as $n) if (isset($by[$n]) && count($out) < 12) $out[] = ['name' => $n, 'logo' => $by[$n]];
    json_out(['providers' => $out]);
}

// Capas para os banners de marketing, por tema
if ($a === 'collage') {
    $soap = ['with_genres' => '10766', 'sort_by' => 'popularity.desc'];
    $themes = [
        'mix'      => [['/trending/all/week', []]],
        'filmes'   => [['/movie/popular', []]],
        'series'   => [['/tv/popular', []]],
        'desenhos' => [['/discover/movie', ['with_genres' => '16', 'sort_by' => 'popularity.desc']], ['/discover/tv', ['with_genres' => '16', 'sort_by' => 'popularity.desc']]],
        'infantil' => [['/discover/tv', ['with_genres' => '10762', 'sort_by' => 'popularity.desc']], ['/discover/movie', ['with_genres' => '10751,16', 'sort_by' => 'popularity.desc']]],
        'novelas'  => [['/discover/tv', $soap + ['with_origin_country' => 'BR']], ['/discover/tv', $soap + ['with_original_language' => 'es|pt|tr']]],
    ];
    $posters = [];
    foreach ($themes[$_GET['theme'] ?? ''] ?? $themes['mix'] as [$path, $q]) {
        foreach (tmdb($path, $q + ['region' => setting('tmdb_region', 'BR')])['results'] ?? [] as $r) {
            if (!empty($r['poster_path'])) $posters[$r['poster_path']] = 1;
        }
    }
    json_out(['posters' => array_slice(array_keys($posters), 0, 36)]);
}

if ($a === 'details') {
    $type = ($_GET['type'] ?? '') === 'tv' ? 'tv' : 'movie';
    $id = (int)($_GET['id'] ?? 0);
    $j = tmdb("/$type/$id", ['append_to_response' => 'images', 'include_image_language' => 'pt,en,null'], 86400);
    if (trim((string)($j['overview'] ?? '')) === '') {
        $en = tmdb("/$type/$id", ['language' => 'en-US'], 86400);
        $j['overview'] = $en['overview'] ?? '';
    }
    $out = norm($j, $type) ?? ['id' => $id, 'type' => $type, 'title' => $j['title'] ?? $j['name'] ?? '', 'poster' => '', 'backdrop' => '', 'year' => '', 'rating' => 0, 'overview' => ''];
    $out['genres'] = array_column($j['genres'] ?? [], 'name');
    if ($type === 'movie') {
        $m = (int)($j['runtime'] ?? 0);
        $out['info'] = $m ? ($m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'min' : $m . 'min') : '';
    } else {
        $s = (int)($j['number_of_seasons'] ?? 0);
        $out['info'] = $s ? $s . ($s > 1 ? ' temporadas' : ' temporada') : '';
    }
    $img = $j['images'] ?? [];
    // Logo do título: prefere português, depois inglês
    $logos = $img['logos'] ?? [];
    usort($logos, fn($x, $y) => (($y['iso_639_1'] ?? '') === 'pt') <=> (($x['iso_639_1'] ?? '') === 'pt'));
    $logos = array_values(array_filter($logos, fn($l) => !str_ends_with($l['file_path'], '.svg')));
    $out['titleLogo'] = $logos[0]['file_path'] ?? '';
    // Cenas sem texto embutido primeiro (ficam melhores sob o título do banner)
    $bds = $img['backdrops'] ?? [];
    usort($bds, fn($x, $y) => (($y['iso_639_1'] ?? null) === null) <=> (($x['iso_639_1'] ?? null) === null));
    $out['backdrops'] = array_slice(array_column($bds, 'file_path'), 0, 10);
    $out['posters'] = array_slice(array_column($img['posters'] ?? [], 'file_path'), 0, 10);
    json_out($out);
}

json_out(['error' => 'Ação inválida.'], 400);
