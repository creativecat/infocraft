<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Material (Vorlage, Datei) an einer Aufgabe: z. B. Java-Starterdatei,
 * Python-Skript, HTML-Vorlage oder SQL-Datei mit CREATE-/INSERT-Anweisungen
 * für eine eigene Datenbank der Aufgabe. Der Inhalt wird base64-kodiert
 * gespeichert, die Größe ist auf 512 KiB begrenzt.
 */
final class TaskMaterial
{
    public const MAX_BYTES = 524288;

    public function __construct(
        public readonly int $id,
        public readonly int $taskId,
        public readonly string $filename,
        public readonly string $content,
        public readonly int $size,
        public readonly int $sort = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['task_id'],
            (string) $row['filename'],
            (string) $row['content'],
            (int) ($row['size'] ?? 0),
            (int) ($row['sort'] ?? 0),
        );
    }

    /** Roher Dateiinhalt (dekodiert). */
    public function raw(): string
    {
        return (string) base64_decode($this->content, true);
    }

    /** @return list<self> Materialien einer Aufgabe */
    public static function forTask(int $taskId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_materials WHERE task_id = ? ORDER BY sort ASC, id ASC');
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /**
     * Materialien mehrerer Aufgaben auf einmal (für die Schüler-Übersicht).
     * @param list<int> $taskIds
     * @return array<int, list<self>> Map von task_id auf Materialien
     */
    public static function forTasks(array $taskIds): array
    {
        $map = [];
        if ($taskIds === []) {
            return $map;
        }
        $ids = [];
        foreach ($taskIds as $taskId) {
            $ids[] = (int) $taskId;
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return $map;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare('SELECT * FROM task_materials WHERE task_id IN (' . $placeholders . ') ORDER BY sort ASC, id ASC');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $material = self::fromRow($row);
            $map[$material->taskId][] = $material;
        }
        return $map;
    }

    /** Neues Material anlegen; false, wenn der Inhalt zu groß ist. */
    public static function add(int $taskId, string $filename, string $rawContent): bool
    {
        $rawContent = (string) mb_substr($rawContent, 0, self::MAX_BYTES);
        if (strlen($rawContent) > self::MAX_BYTES) {
            return false;
        }
        $filename = trim(mb_substr($filename, 0, 200));
        if ($filename === '') {
            return false;
        }
        $filename = str_replace(['/', '\\', "\0"], '-', $filename);
        $sort = (int) Database::pdo()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM task_materials WHERE task_id = ' . (int) $taskId)->fetchColumn();
        $stmt = Database::pdo()->prepare('INSERT INTO task_materials (task_id, filename, content, size, sort) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$taskId, $filename, base64_encode($rawContent), strlen($rawContent), $sort]);
        return true;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_materials WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM task_materials WHERE id = ?');
        $stmt->execute([$id]);
    }
}
