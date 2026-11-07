<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Themengebiet für Aufgaben, kuratiert nach dem LehrplanPLUS Informatik
 * Bayern (pro Jahrgangsstufe) und frei ergänzbar. Der Filter im
 * Aufgabenbereich kaskadiert: erst die Klasse (Stufe), dann das Thema.
 */
final class TaskTopic
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $level,
        public readonly int $sort = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (int) ($row['level'] ?? 6),
            (int) ($row['sort'] ?? 0),
        );
    }

    /** @return list<self> alle Themengebiete, sortiert nach Stufe und Reihenfolge */
    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM task_topics ORDER BY level ASC, sort ASC, id ASC');
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** @return list<self> Themengebiete einer Jahrgangsstufe */
    public static function forLevel(int $level): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_topics WHERE level = ? ORDER BY sort ASC, id ASC');
        $stmt->execute([$level]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM task_topics WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Eigenes Themengebiet anlegen (oder vorhandenes zurückgeben). */
    public static function findOrCreate(string $name, int $level): self
    {
        $name = trim(mb_substr($name, 0, 120));
        $stmt = Database::pdo()->prepare('SELECT * FROM task_topics WHERE LOWER(name) = LOWER(?) AND level = ? LIMIT 1');
        $stmt->execute([$name, max(5, min(13, $level))]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return self::fromRow($row);
        }
        $sort = (int) Database::pdo()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM task_topics WHERE level = ' . max(5, min(13, $level)))->fetchColumn();
        $insert = Database::pdo()->prepare('INSERT INTO task_topics (name, level, sort) VALUES (?, ?, ?)');
        $insert->execute([$name, max(5, min(13, $level)), $sort]);
        $find = Database::pdo()->prepare('SELECT * FROM task_topics WHERE id = ?');
        $find->execute([(int) Database::pdo()->lastInsertId()]);
        return self::fromRow($find->fetch());
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM task_topics WHERE id = ?');
        $stmt->execute([$id]);
    }
}
