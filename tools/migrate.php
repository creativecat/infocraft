<?php
declare(strict_types=1);

/**
 * InfoCraft-Migration: Schema einspielen und (optional) Beispieldaten anlegen.
 *
 * Aufruf:
 *   php tools/migrate.php               (Treiber aus config/config.php)
 *   php tools/migrate.php --driver sqlite
 *   php tools/migrate.php --driver mysql
 *
 * Kann gefahrlos mehrfach ausgeführt werden (CREATE TABLE IF NOT EXISTS,
 * Beispieldaten nur bei leerer users-Tabelle).
 */

if (PHP_SAPI !== 'cli') {
    exit('Dieses Werkzeug läuft nur auf der Kommandozeile.' . PHP_EOL);
}

require __DIR__ . '/../src/bootstrap.php';

use Infocraft\Config;
use Infocraft\Migrator;

$driver = (string) Config::get('db.driver', 'sqlite');
$args = $_SERVER['argv'] ?? [];
foreach ($args as $index => $arg) {
    if ($arg === '--driver' && isset($args[$index + 1])) {
        $driver = (string) $args[$index + 1];
    }
}
if (!in_array($driver, ['sqlite', 'mysql'], true)) {
    exit('Unbekannter Treiber: ' . $driver . ' (erlaubt: sqlite, mysql)' . PHP_EOL);
}

echo 'InfoCraft-Datenbank: Treiber ' . $driver . PHP_EOL;
$result = Migrator::run($driver, true);

echo 'Schema angewendet (' . $result['statements'] . ' Anweisungen).' . PHP_EOL;
if ($result['users'] > 0) {
    echo 'Beispieldaten angelegt: ' . $result['classes'] . ' Klassen, ' . $result['users'] . ' Personen (Passwort jeweils demo1234).' . PHP_EOL;
} else {
    echo 'Beispieldaten übersprungen (bereits Nutzer vorhanden).' . PHP_EOL;
}
echo 'Fertig.' . PHP_EOL;
