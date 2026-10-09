<?php
// A antiga tela de bloqueio deu lugar à de assinatura: conta vencida entra normalmente e assina por lá
require __DIR__ . '/inc/boot.php';
redirect(user() ? '/assinar.php' : '/login.php');
