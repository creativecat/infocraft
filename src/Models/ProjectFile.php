<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Einzelne Quelldatei eines Code-Projekts (Java: mehrere Dateien pro Projekt,
 * eine Klasse pro Datei). Projekte ohne Dateien nutzen weiterhin project.content.
 */
final class ProjectFile
{
    public function __construct(
        public readonly int $id,
        public readonly int $projectId,
        public readonly string $name,
        public readonly string $content,
        public readonly int $position,
        public readonly ?string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['project_id'],
            (string) $row['name'],
            (string) ($row['content'] ?? ''),
            (int) ($row['position'] ?? 0),
            ($row['updated_at'] ?? null) === null ? null : (string) $row['updated_at'],
        );
    }

    /** @return list<self> */
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY position, id');
        $stmt->execute([$projectId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $projectId, string $name, string $content): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM project_files WHERE project_id = ?');
        $stmt->execute([$projectId]);
        $position = (int) $stmt->fetchColumn() + 1;
        $stmt = $pdo->prepare('INSERT INTO project_files (project_id, name, content, position) VALUES (?, ?, ?, ?)');
        $stmt->execute([$projectId, $name, $content, $position]);
        $file = self::find((int) $pdo->lastInsertId());
        return $file ?? throw new \RuntimeException('Projektdatei konnte nicht angelegt werden.');
    }

    public function save(string $content): void
    {
        $stmt = Database::pdo()->prepare('UPDATE project_files SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$content, $this->id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_files WHERE id = ?');
        $stmt->execute([$id]);
    }
}
