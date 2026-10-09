<?php
require __DIR__ . '/inc/boot.php';
redirect(user() ? '/painel.php' : '/login.php');
