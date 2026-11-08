<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Eigenes Bild eines Code-Projekts (Sprite-Grafik). Hochgeladene Bilder
 * gehoeren zum Projekt, wandern bei verteilten Kopien mit und sind im
 * Code per new Sprite(x, y, "Name") nutzbar.
 */
final class ProjectImage
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly string $name,
        public readonly string $mime,
        public readonly int $size,
        public readonly ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['project_id'],
            (string) $row['name'],
            (string) $row['mime'],
            (int) ($row['size'] ?? 0),
            ($row['created_at'] ?? null) === null ? null : (string) $row['created_at'],
        );
    }

    /** @return list<self> */
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, project_id, name, mime, size, created_at FROM project_images WHERE project_id = ? ORDER BY name');
        $stmt->execute([$projectId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function findByName(int $projectId, string $name): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT id, project_id, name, mime, size, created_at FROM project_images WHERE project_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$projectId, $name]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Rohdaten eines Bildes (fuer das Ausliefern und Kopieren). */
    public static function dataByName(int $projectId, string $name): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT data FROM project_images WHERE project_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$projectId, $name]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public static function countForProject(int $projectId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM project_images WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public static function existsByName(int $projectId, string $name): bool
    {
        return self::findByName($projectId, $name) !== null;
    }

    public static function create(int $projectId, string $name, string $mime, string $data): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO project_images (project_id, name, mime, size, data) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$projectId, $name, $mime, strlen($data), $data]);
        return self::findByName($projectId, $name) ?? new self((int) $pdo->lastInsertId(), $projectId, $name, $mime, strlen($data), null);
    }

    public static function deleteByName(int $projectId, string $name): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_images WHERE project_id = ? AND name = ?');
        $stmt->execute([$projectId, $name]);
    }

    /**
     * Aus einem Dateinamen einen Sprite-Namen im Code bauen: Endung weg,
     * Umlaute und Sonderzeichen umschreiben, auf 40 Zeichen kuerzen.
     * "Mein Ball (1).png" wird zu "MeinBall1".
     */
    public static function sanitizeName(string $filename): string
    {
        $base = preg_replace('/\.[A-Za-z0-9]+$/', '', trim($filename)) ?? '';
        $base = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', 'é', 'è', 'ê', 'á', 'à', 'í', 'ì', 'ó', 'ò', 'ú', 'ù'],
            ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u'],
            $base
        );
        $base = preg_replace('/[^A-Za-z0-9_-]/', '', $base) ?? '';
        $base = trim($base, '-_');
        if ($base === '') {
            $base = 'Bild';
        }
        if (strlen($base) > 40) {
            $base = substr($base, 0, 40);
        }
        return $base;
    }

    /** Freien Namen finden: "Ball", "Ball2", "Ball3", ... */
    public static function uniqueName(int $projectId, string $name): string
    {
        $clean = self::sanitizeName($name);
        if (!self::existsByName($projectId, $clean)) {
            return $clean;
        }
        for ($i = 2; $i <= 99; $i++) {
            $candidate = $clean . $i;
            if (!self::existsByName($projectId, $candidate)) {
                return $candidate;
            }
        }
        return $clean . 'X' . time();
    }
}
