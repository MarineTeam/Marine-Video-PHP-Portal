<?php
namespace MarinePortal\Core;
class Bootstrap {
    public static function run(): void {
        $config = require ROOT . '/config.php';
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
        require ROOT . '/src/Core/Router.php';
        require ROOT . '/src/Core/helpers.php';
        $router = new Router($config);
        $router->dispatch();
    }
}
