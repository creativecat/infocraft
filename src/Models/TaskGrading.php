<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Korrektur einer Aufgabe-Abgabe.
 * source 'lk' = Bewertung der Lehrkraft (zählt sofort),
 * source 'api' = KI-Vorschlag (checked = 0), bis die Lehrkraft ihn
 * übernommen und gespeichert hat.
 */
final class TaskGrading
{
    /** @return array{grade: string, comment: string, source: string, checked: bool}|null */
    public static function find(int $taskId, int $studentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT grade, comment, source, checked FROM task_gradings WHERE task_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$taskId, $studentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'grade' => (string) $row['grade'],
            'comment' => (string) $row['comment'],
            'source' => (string) $row['source'],
            'checked' => (int) $row['checked'] === 1,
        ];
    }

    public static function save(int $taskId, int $studentId, string $grade, string $comment, string $source, bool $checked): void
    {
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM task_gradings WHERE task_id = ? AND student_id = ?');
        $exists->execute([$taskId, $studentId]);
        if ((int) $exists->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE task_gradings SET grade = ?, comment = ?, source = ?, checked = ?, graded_at = CURRENT_TIMESTAMP WHERE task_id = ? AND student_id = ?');
            $update->execute([$grade, $comment, $source, $checked ? 1 : 0, $taskId, $studentId]);
            return;
        }
        $insert = $pdo->prepare('INSERT INTO task_gradings (task_id, student_id, grade, comment, source, checked, graded_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $insert->execute([$taskId, $studentId, $grade, $comment, $source, $checked ? 1 : 0]);
    }

    /**
     * Alle Korrekturen einer Aufgabe, indiziert nach Schüler-ID.
     * @return array<int, array{grade: string, comment: string, source: string, checked: bool}>
     */
    public static function forTask(int $taskId): array
    {
        $stmt = Database::pdo()->prepare('SELECT student_id, grade, comment, source, checked FROM task_gradings WHERE task_id = ?');
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[(int) $row['student_id']] = [
                'grade' => (string) $row['grade'],
                'comment' => (string) $row['comment'],
                'source' => (string) $row['source'],
                'checked' => (int) $row['checked'] === 1,
            ];
        }
        return $list;
    }

    public static function delete(int $taskId, int $studentId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM task_gradings WHERE task_id = ? AND student_id = ?');
        $stmt->execute([$taskId, $studentId]);
    }
}
