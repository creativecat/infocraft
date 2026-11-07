<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Config;
use Infocraft\Database;

/**
 * Hinterlegte KI-Schnittstelle: je Lehrkraft (user_id) oder schulweit
 * (user_id = NULL, nur Administration). mode 'chat' = OpenAI-/Mistral-
 * kompatible Chat-API, 'json' = eigener Endpunkt mit festem JSON-Vertrag.
 * Auflösung: eigene Angaben, sonst schulweite, sonst exam.api aus der
 * config/config.php.
 */
final class AiSettings
{
    /** @return array{url: string, key: string, model: string, mode: string}|null */
    public static function forUser(int $userId): ?array
    {
        return self::row('SELECT url, api_key, model, mode FROM ai_settings WHERE user_id = ? LIMIT 1', [$userId]);
    }

    /** @return array{url: string, key: string, model: string, mode: string}|null */
    public static function forSchool(): ?array
    {
        return self::row('SELECT url, api_key, model, mode FROM ai_settings WHERE user_id IS NULL LIMIT 1', []);
    }

    /** @return array{url: string, key: string, model: string, mode: string}|null */
    private static function row(string $sql, array $params): ?array
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'url' => (string) $row['url'],
            'key' => (string) $row['api_key'],
            'model' => (string) $row['model'],
            'mode' => (string) $row['mode'],
        ];
    }

    public static function saveForUser(?int $userId, string $url, string $apiKey, string $model, string $mode): void
    {
        $pdo = Database::pdo();
        if ($userId === null) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM ai_settings WHERE user_id IS NULL');
            $exists->execute();
            if ((int) $exists->fetchColumn() > 0) {
                $update = $pdo->prepare('UPDATE ai_settings SET url = ?, api_key = ?, model = ?, mode = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id IS NULL');
                $update->execute([$url, $apiKey, $model, $mode]);
                return;
            }
            $insert = $pdo->prepare('INSERT INTO ai_settings (user_id, url, api_key, model, mode, updated_at) VALUES (NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $insert->execute([$url, $apiKey, $model, $mode]);
            return;
        }
        $exists = $pdo->prepare('SELECT COUNT(*) FROM ai_settings WHERE user_id = ?');
        $exists->execute([$userId]);
        if ((int) $exists->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE ai_settings SET url = ?, api_key = ?, model = ?, mode = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
            $update->execute([$url, $apiKey, $model, $mode, $userId]);
            return;
        }
        $insert = $pdo->prepare('INSERT INTO ai_settings (user_id, url, api_key, model, mode, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $insert->execute([$userId, $url, $apiKey, $model, $mode]);
    }

    /**
     * Wirksame Schnittstelle für eine Lehrkraft: eigene, sonst schulweite,
     * sonst exam.api aus der Konfiguration. null = keine hinterlegt.
     * @return array{url: string, key: string, model: string, mode: string}|null
     */
    public static function resolve(int $userId): ?array
    {
        $own = self::forUser($userId);
        if ($own !== null && trim($own['url']) !== '') {
            return $own;
        }
        $school = self::forSchool();
        if ($school !== null && trim($school['url']) !== '') {
            return $school;
        }
        $config = Config::get('exam.api');
        if (is_array($config) && trim((string) ($config['url'] ?? '')) !== '') {
            return [
                'url' => (string) $config['url'],
                'key' => (string) ($config['key'] ?? ''),
                'model' => (string) ($config['model'] ?? ''),
                'mode' => ($config['mode'] ?? 'json') === 'chat' ? 'chat' : 'json',
            ];
        }
        return null;
    }
}
