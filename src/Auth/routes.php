<?php
use MarineVideoPortal\Auth\Auth0Service;
$u=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($u==='/auth/login'){header('Location: '.Auth0Service::loginUrl());exit;}
if($u==='/auth/callback'){try{Auth0Service::handleCallback();header('Location: /');exit;}catch(Throwable $e){die('Auth error: '.htmlspecialchars($e->getMessage()));}}
if($u==='/auth/logout'){session_destroy();header('Location: '.Auth0Service::logoutUrl());exit;}
