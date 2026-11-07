<?php
declare(strict_types=1);

namespace Infocraft;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(?string $driver = null): PDO
    {
        $driver ??= (string) Config::get('db.driver', 'sqlite');

        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            $host = (string) Config::get('db.mysql.host', 'localhost');
            $port = (int) Config::get('db.mysql.port', 3306);
            $name = (string) Config::get('db.mysql.name', 'infocraft');
            $charset = (string) Config::get('db.mysql.charset', 'utf8mb4');
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset;
            self::$pdo = new PDO($dsn, (string) Config::get('db.mysql.user', ''), (string) Config::get('db.mysql.pass', ''), $options);
        } else {
            $path = (string) Config::get('db.sqlite.path', INFOCRAFT_ROOT . '/database/infocraft.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }

        return self::$pdo;
    }

    public static function driver(): string
    {
        return (string) Config::get('db.driver', 'sqlite');
    }
}
