<?php
require __DIR__ . '/inc/boot.php';
if (is_post()) {
    csrf_check();
    $tn = $_SESSION['tenant'] ?? null;
    $_SESSION = [];
    session_regenerate_id(true);
    if ($tn) $_SESSION['tenant'] = $tn; // volta para a tela de login da mesma plataforma
}
redirect('/login.php');
