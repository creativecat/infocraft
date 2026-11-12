<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Seiteneinstellungen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Schulweite Einstellungen (Admin-Bereich), z. B. die Standard-
 * Programmiersprache in der Code-Werkstatt. Werte liegen in der
 * Tabelle site_settings und werden pro Lauf gecacht.
 */
final class SiteSetting
{
    /** @var array<string, string> */
    private static array $cache = [];

    /** @var bool */
    private static bool $loaded = false;

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        self::load();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
            $update->execute([$value, $key]);
        } else {
            $insert = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
            $insert->execute([$key, $value]);
        }
        self::$cache[$key] = $value;
    }

    /**
     * Standard-Programmiersprache der Schule (java oder python).
     * Siegreich ist der Wert aus dem Admin-Bereich; ohne Eintrag gilt Java.
     */
    public static function defaultLanguage(): string
    {
        $value = self::get('default_language', 'java');
        return $value === 'python' ? 'python' : 'java';
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        foreach (Database::pdo()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
            self::$cache[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }
}
