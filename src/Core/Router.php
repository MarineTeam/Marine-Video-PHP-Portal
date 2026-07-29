<?php
namespace MarinePortal\Core;
class Router {
    private array $config;
    public function __construct(array $config){ $this->config=$config; }
    public function dispatch(): void {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rtrim($uri,'/') ?: '/';

        // Public routes
        if (str_starts_with($uri,'/install')) { require ROOT.'/install/index.php'; return; }
        if ($uri==='/auth/login') { (new \MarinePortal\Auth\Auth0Controller($this->config))->login(); return; }
        if ($uri==='/auth/callback') { (new \MarinePortal\Auth\Auth0Controller($this->config))->callback(); return; }
        if ($uri==='/auth/logout') { (new \MarinePortal\Auth\Auth0Controller($this->config))->logout(); return; }
        if (str_starts_with($uri,'/s/')) { (new \MarinePortal\Controllers\ShareController($this->config))->showShare(substr($uri,3)); return; }
        if (str_starts_with($uri,'/b/')) { (new \MarinePortal\Controllers\ShareController($this->config))->showBundle(substr($uri,3)); return; }
        if (str_starts_with($uri,'/watch/')) { (new \MarinePortal\Controllers\WatchController($this->config))->watch(substr($uri,7)); return; }
        if (str_starts_with($uri,'/api/')) { $this->api($uri); return; }

        // Auth required
        $auth = new \MarinePortal\Auth\AuthGuard($this->config);
        if (!$auth->check()) { header('Location: /auth/login'); exit; }
        if ($uri==='/') { (new \MarinePortal\Controllers\HomeController($this->config))->index(); return; }
        if ($uri==='/admin') { (new \MarinePortal\Controllers\AdminController($this->config))->index(); return; }
        if ($uri==='/activity') { (new \MarinePortal\Controllers\ActivityController($this->config))->index(); return; }

        http_response_code(404); echo "404 Not Found";
    }
    private function api(string $uri): void {
        header('Content-Type: application/json');
        $c = $this->config;
        // Map API
        match(true){
            $uri==='/api/videos' => (new \MarinePortal\Controllers\Api\VideoApi($c))->list(),
            $uri==='/api/collections' => (new \MarinePortal\Controllers\Api\CollectionApi($c))->list(),
            $uri==='/api/progress' => (new \MarinePortal\Controllers\Api\ProgressApi($c))->handle(),
            $uri==='/api/theme' => (new \MarinePortal\Controllers\Api\ThemeApi($c))->handle(),
            str_starts_with($uri,'/api/admin/') => (new \MarinePortal\Controllers\Api\AdminApi($c))->dispatch($uri),
            default => print(json_encode(['error'=>'not found']))
        };
    }
}
