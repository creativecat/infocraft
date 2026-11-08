<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Arbeitskopie einer Aufgabe in der Werkstatt einer Schülerin bzw. eines
 * Schülers: Aufgabe „In der Werkstatt öffnen“ legt genau ein Projekt pro
 * Aufgabe und Schüler an (oder öffnet das vorhandene wieder).
 */
final class TaskWorking
{
    public function __construct(
        public readonly int $id,
        public readonly int $taskId,
        public readonly int $studentId,
        public readonly int $projectId,
    ) {
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (int) ($row['task_id'] ?? 0),
            (int) ($row['student_id'] ?? 0),
            (int) ($row['project_id'] ?? 0),
        );
    }

    /** Zu Aufgabe und Schüler: vorhandene Arbeitskopie finden. */
    public static function find(int $taskId, int $studentId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_working WHERE task_id = ? AND student_id = ?');
        $stmt->execute([$taskId, $studentId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Arbeitskopie zu einem Projekt (zeigt die Werkstatt die Aufgabeninfo an?). */
    public static function forProject(int $projectId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_working WHERE project_id = ?');
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $taskId, int $studentId, int $projectId): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO task_working (task_id, student_id, project_id) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $studentId, $projectId]);
    }
}
