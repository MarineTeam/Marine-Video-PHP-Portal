<?php
declare(strict_types=1);
session_start();
define('ROOT', __DIR__);

// Installer gate - like WordPress
if (!file_exists(ROOT . '/config.php') && !str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/install')) {
    header('Location: /install/');
    exit;
}
if (file_exists(ROOT . '/config.php')) {
    $cfg = require ROOT . '/config.php';
    if (empty($cfg['installed'])) {
        header('Location: /install/');
        exit;
    }
}

require ROOT . '/src/Core/Bootstrap.php';
\MarinePortal\Core\Bootstrap::run();
