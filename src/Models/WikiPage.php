<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Wiki-Seiten
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Wiki-Seite: Nachschlage-Text einer Lehrkraft, sichtbar fuer
 * Schuelerinnen und Schueler ab der angegebenen Jahrgangsstufe.
 */
final class WikiPage
{
    public function __construct(
        public readonly int $id,
        public readonly int $teacherId,
        public readonly string $title,
        public readonly string $content,
        public readonly int $level,
        public readonly int $position,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['title'],
            (string) ($row['content'] ?? ''),
            (int) ($row['level'] ?? 6),
            (int) ($row['position'] ?? 0),
        );
    }

    /** @return list<self> */
    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT * FROM wiki_pages ORDER BY level, position, id')->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** @return list<self> */
    public static function visibleFor(int $level): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM wiki_pages WHERE level <= ? ORDER BY level, position, id');
        $stmt->execute([max(0, $level)]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM wiki_pages WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $teacherId, string $title, string $content, int $level): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM wiki_pages');
        $stmt->execute();
        $position = (int) $stmt->fetchColumn() + 1;
        $stmt = $pdo->prepare('INSERT INTO wiki_pages (teacher_id, title, content, level, position) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$teacherId, substr(trim($title), 0, 200), $content, max(1, min(13, $level)), $position]);
        $page = self::find((int) $pdo->lastInsertId());
        return $page ?? throw new \RuntimeException('Wiki-Seite konnte nicht angelegt werden.');
    }

    public function save(string $title, string $content, int $level): void
    {
        $stmt = Database::pdo()->prepare('UPDATE wiki_pages SET title = ?, content = ?, level = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([substr(trim($title), 0, 200), $content, max(1, min(13, $level)), $this->id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM wiki_pages WHERE id = ?');
        $stmt->execute([$id]);
    }
}
