<?php
/*
 * Empacotador do Gerador Gold para VENDA (linha de comando).
 * Uso:  php tools/empacotar.php URL_DO_SERVIDOR [--tag=COMPRADOR] [--ofusca]
 * (O mesmo também pode ser feito pelo painel: Administração → Licenças do Script → Baixar script.)
 */
if (PHP_SAPI !== 'cli') exit("Só pela linha de comando.\n");
chdir(dirname(__DIR__));
require __DIR__ . '/../inc/boot.php';
require __DIR__ . '/../inc/packager.php';

$args = array_slice($argv, 1);
$ofusca = in_array('--ofusca', $args, true);
$tag = ''; $server = '';
foreach ($args as $a) { if (str_starts_with($a, '--tag=')) $tag = substr($a, 6); elseif (!str_starts_with($a, '--')) $server = $server ?: $a; }
if ($server === '') { echo "URL do servidor de licença (.../api/license_server.php): "; $server = trim((string)fgets(STDIN)); }

try {
    $r = gg_build_package($server, $tag, $ofusca);
} catch (Throwable $e) { exit('Erro: ' . $e->getMessage() . "\n"); }

$final = getcwd() . '/' . $r['name'];
rename($r['zip'], $final);
echo "Pacote pronto: $final\n";
echo "Arquivos: {$r['copied']} | Marca do comprador: {$r['tag']} | Servidor: {$r['server']}\n";
echo $ofusca ? "Ofuscação aplicada em {$r['obf']} arquivos PHP.\n" : "Sem ofuscação (use --ofusca).\n";
echo "Tamanho: " . round(filesize($final) / 1024) . " KB\n";
