<?php
/*
 * Monta o pacote .zip do script para venda, com o cliente de licença embutido e os arquivos do VENDEDOR removidos.
 * Usado tanto pela linha de comando (tools/empacotar.php) quanto pelo painel de licenças (download direto).
 * Devolve ['zip' => caminho, 'name' => nome, 'copied' => n, 'obf' => n, 'tag' => marca, 'server' => url].
 */
function gg_build_package(string $server, string $tag = '', bool $ofusca = false): array
{
    if (!preg_match('#^https?://.+/api/license_server\.php$#', $server)) throw new RuntimeException('URL do servidor de licença inválida (deve terminar em /api/license_server.php).');
    $pub = (string)@file_get_contents(DATA . '/license_public.pem');
    if (strlen($pub) < 100) throw new RuntimeException('Chave pública de licença não encontrada em data/license_public.pem.');
    if ($tag === '') $tag = 'build-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $tag = mb_substr(preg_replace('/[^\w.\- ]/u', '', $tag), 0, 60);

    $stamp = date('Ymd-His');
    $name = 'gerador-script-' . $stamp;
    $base = sys_get_temp_dir() . '/gg-pkg-' . $stamp . '-' . bin2hex(random_bytes(3));
    $out = $base . '/' . $name;
    @mkdir($out, 0755, true);

    $skip = ['api/license_server.php', 'admin/licencas.php', 'inc/packager.php', 'tools', 'previa', 'node_modules',
        'data/license_private.pem', 'data/license_public.pem'];
    $skipExt = ['bak', 'log', 'sqlite', 'sqlite-wal', 'sqlite-shm'];
    $copied = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(ROOT) + 1);
        foreach ($skip as $sk) if ($rel === $sk || str_starts_with($rel, $sk . '/')) continue 2;
        if (str_contains($rel, '.bak')) continue;
        if (in_array(strtolower($f->getExtension()), $skipExt, true)) continue;
        // mantém a estrutura de data/ e uploads/ mas sem conteúdo (comprador começa limpo)
        if (preg_match('#^(data|uploads)/#', $rel) && $f->isFile() && $f->getFilename() !== '.htaccess') continue;
        $dest = $out . '/' . $rel;
        if ($f->isDir()) { @mkdir($dest, 0755, true); continue; }
        @mkdir(dirname($dest), 0755, true);
        copy($f->getPathname(), $dest);
        $copied++;
    }
    @mkdir($out . '/data/cache/img', 0755, true);
    @mkdir($out . '/uploads/c', 0755, true);

    // Liga a verificação de licença no comprador (presença deste arquivo = build vendido)
    file_put_contents($out . '/inc/license_config.php',
        "<?php\n// Gerado pelo empacotador — não editar. Liga a verificação de licença.\nreturn [\n"
        . "    'server' => " . var_export($server, true) . ",\n"
        . "    'build'  => " . var_export($tag, true) . ",\n"
        . "    'pubkey' => <<<'PEM'\n" . trim($pub) . "\nPEM,\n];\n");

    file_put_contents($out . '/LEIA-ME.txt',
        "Gerador de Banners — instalação\n================================\n\n"
        . "1. Envie todos os arquivos desta pasta para o seu domínio.\n"
        . "2. Dê permissão de escrita às pastas 'data' e 'uploads'.\n"
        . "3. Abra o endereço no navegador e informe a CHAVE DE LICENÇA recebida na compra (válida para UM domínio).\n"
        . "4. Depois de ativar, entre com usuario 'admin' e senha 'admin'. TROQUE a senha em Minha Conta logo apos entrar.\n\n"
        . "Requisitos: PHP 8.1+ com pdo_sqlite, gd, curl e openssl.\n");

    $obf = 0;
    if ($ofusca) {
        $it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
        foreach ($it2 as $f) {
            if (strtolower($f->getExtension()) !== 'php') continue;
            $min = @php_strip_whitespace($f->getPathname());
            if ($min !== '' && $min !== false) { file_put_contents($f->getPathname(), $min); $obf++; }
        }
        foreach (['inc/license.php', 'inc/license_config.php'] as $lf) {
            $src = preg_replace('/^<\?php\s*/', '', (string)file_get_contents($out . '/' . $lf), 1);
            $enc = base64_encode(gzdeflate($src, 9));
            $call = str_starts_with(ltrim($src), 'return') ? 'return eval' : 'eval';
            file_put_contents($out . '/' . $lf, "<?php\n/* protegido */\n$call(gzinflate(base64_decode('" . $enc . "')));\n");
        }
    }

    $zip = $base . '/' . $name . '.zip';
    $za = new ZipArchive();
    if ($za->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Não foi possível criar o arquivo .zip.');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $za->addFile($f->getPathname(), $name . '/' . substr($f->getPathname(), strlen($out) + 1));
    $za->close();

    // apaga a árvore de arquivos, mantém só o zip
    $rm = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($rm as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($out);

    return ['zip' => $zip, 'name' => $name . '.zip', 'copied' => $copied, 'obf' => $obf, 'tag' => $tag, 'server' => $server];
}
