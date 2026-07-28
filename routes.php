<?php
use MarineVideoPortal\Auth\Auth0Service;
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($uri==='/auth/login'){ header('Location: '.Auth0Service::loginUrl()); exit; }
if($uri==='/auth/callback'){ try{ Auth0Service::handleCallback(); header('Location: /'); exit; }catch(Throwable $e){ die('Auth error: '.htmlspecialchars($e->getMessage())); } }
if($uri==='/auth/logout'){ session_destroy(); header('Location: '.Auth0Service::logoutUrl()); exit; }
