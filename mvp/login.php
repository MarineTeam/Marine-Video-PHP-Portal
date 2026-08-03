<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect(SITE_URL . '/index.php');
}

$returnTo = $_GET['returnTo'] ?? (SITE_URL . '/index.php');
redirect(auth0_login_url($returnTo));
