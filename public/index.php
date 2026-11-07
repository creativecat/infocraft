<?php
declare(strict_types=1);

// Eingebauter PHP-Server (Entwicklung): statische Dateien direkt ausliefern.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

use Infocraft\AdminController;
use Infocraft\AuthController;
use Infocraft\HomeController;
use Infocraft\ModuleController;
use Infocraft\Router;

// Basispfad, falls InfoCraft in einem Unterverzeichnis installiert ist.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_PATH', $scriptDir === '/' ? '' : $scriptDir);

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/login/demo', [AuthController::class, 'demoLogin']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/sso/start', [AuthController::class, 'ssoStart']);
$router->get('/auth/sso/callback', [AuthController::class, 'ssoCallback']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/import', [AdminController::class, 'importForm']);
$router->post('/admin/import', [AdminController::class, 'import']);
$router->get('/modul/{slug}', [ModuleController::class, 'show']);

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
if ($path === '') {
    $path = '/';
}

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (PDOException $e) {
    error_log('InfoCraft: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Datenbankfehler. Bitte prüfen, ob das Schema eingespielt ist (php tools/migrate.php) und ob config/config.php korrekt ist.';
} catch (Throwable $e) {
    error_log('InfoCraft: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Interner Fehler. Details stehen im Server-Log.';
}
