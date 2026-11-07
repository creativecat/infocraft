<?php
declare(strict_types=1);

/**
 * InfoCraft-Start: Konfiguration, Autoloader, Session.
 * Komplett composer-frei, damit es auf jedem Webspace läuft (PHP 8.3+).
 */

define('INFOCRAFT_ROOT', dirname(__DIR__));

// Konfiguration: config/config.php (Produktion) oder config.example.php (Demo).
$configFile = INFOCRAFT_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = INFOCRAFT_ROOT . '/config/config.example.php';
}
$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('Konfigurationsdatei ungültig.');
}

// Autoloader für den Namensraum Infocraft\ (composer-frei, PSR-4-ähnlich).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Infocraft\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = INFOCRAFT_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

\Infocraft\Config::init($config);

date_default_timezone_set((string) \Infocraft\Config::get('app.timezone', 'Europe/Berlin'));

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // aktivieren, sobald HTTPS sicher genutzt wird
    ]);
    session_name('infocraft_sid');
    session_start();
}

// Demo-Komfort: fehlende SQLite-Datenbank automatisch anlegen und befüllen.
if ((string) \Infocraft\Config::get('db.driver', 'sqlite') === 'sqlite') {
    $sqlitePath = (string) \Infocraft\Config::get('db.sqlite.path', INFOCRAFT_ROOT . '/database/infocraft.sqlite');
    if (!is_file($sqlitePath)) {
        \Infocraft\Migrator::run('sqlite', true);
    }
}
