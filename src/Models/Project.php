<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Projekt in der Code-Werkstatt (und später weiteren Werkstätten).
 * Projekte liegen in Ordnern und können von Lehrkräften an Klassen
 * oder Einzelne ausgeteilt werden (Kopie mit created_by).
 */
final class Project
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly ?int $folderId,
        public readonly string $title,
        public readonly string $type,
        public readonly ?string $language,
        public readonly string $content,
        public readonly ?int $createdBy,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            ($row['folder_id'] ?? null) === null ? null : (int) $row['folder_id'],
            (string) $row['title'],
            (string) $row['type'],
            ($row['language'] ?? null) === null || $row['language'] === '' ? null : (string) $row['language'],
            (string) ($row['content'] ?? ''),
            ($row['created_by'] ?? null) === null ? null : (int) $row['created_by'],
        );
    }

    /** @return list<self> */
    public static function all(int $ownerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE owner_id = ? ORDER BY updated_at DESC, id DESC');
        $stmt->execute([$ownerId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $ownerId, ?int $folderId, string $title, string $type, ?string $language, string $content, ?int $createdBy = null): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO projects (owner_id, folder_id, title, type, language, content, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ownerId, $folderId, substr(trim($title), 0, 200), $type, $language, $content, $createdBy]);
        $project = self::find((int) $pdo->lastInsertId());
        return $project ?? throw new \RuntimeException('Projekt konnte nicht angelegt werden.');
    }

    public function save(string $title, string $content): void
    {
        $stmt = Database::pdo()->prepare('UPDATE projects SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([substr(trim($title), 0, 200), $content, $this->id]);
    }

    public function moveTo(?int $folderId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE projects SET folder_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$folderId, $this->id]);
    }

    /**
     * Kopie für eine andere Person anlegen (Austeilen durch die Lehrkraft).
     * Mehrere Quelldateien und eigene Bilder (Sprites) wandern mit.
     */
    public static function copyTo(self $project, int $ownerId, ?int $createdBy): self
    {
        $copy = self::create($ownerId, null, $project->title, $project->type, $project->language, $project->content, $createdBy);
        foreach (ProjectFile::allForProject($project->id) as $file) {
            ProjectFile::create($copy->id, $file->name, $file->content);
        }
        foreach (ProjectImage::allForProject($project->id) as $image) {
            $data = ProjectImage::dataByName($project->id, $image->name);
            if ($data !== null) {
                ProjectImage::create($copy->id, $image->name, $image->mime, $data);
            }
        }
        return $copy;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }
}
