<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Persönliche Einstellungen je Nutzerkonto, z. B. die gewählte Ansicht der
 * Seitennavigation (full = Icons mit Schrift, mini = nur Icons).
 */
final class UserPref
{
    public static function get(int $userId, string $key, string $default = ''): string
    {
        $stmt = Database::pdo()->prepare('SELECT pref_value FROM user_prefs WHERE user_id = ? AND pref_key = ?');
        $stmt->execute([$userId, $key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    }

    public static function set(int $userId, string $key, string $value): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_prefs WHERE user_id = ? AND pref_key = ?');
        $stmt->execute([$userId, $key]);
        if ((int) $stmt->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE user_prefs SET pref_value = ? WHERE user_id = ? AND pref_key = ?');
            $update->execute([$value, $userId, $key]);
        } else {
            $insert = $pdo->prepare('INSERT INTO user_prefs (user_id, pref_key, pref_value) VALUES (?, ?, ?)');
            $insert->execute([$userId, $key, $value]);
        }
    }

    /** Ansicht der Seitennavigation: 'full' (Standard) oder 'mini' (nur Icons). */
    public static function navView(int $userId): string
    {
        $value = self::get($userId, 'nav_view', 'full');
        return $value === 'mini' ? 'mini' : 'full';
    }
}
