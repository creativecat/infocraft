<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Abgabe einer Schülerin oder eines Schülers zu einer Aufgabe.
 * Eine Abgabe je Aufgabe; bis zum Abgabetermin beliebig oft
 * überschreibbar (Zeitstempel wird mitgeführt), danach verspätet.
 */
final class TaskSubmission
{
    /**
     * Selbsteinschätzung vor der Abgabe (0 = nicht angegeben, 1 = noch unsicher,
     * 2 = teils/teils, 3 = ziemlich sicher). Nur die Lehrkraft sieht sie.
     */
    public const SELF_RATING_LABELS = [1 => 'noch unsicher', 2 => 'teils/teils', 3 => 'ziemlich sicher'];

    /** @return array{content: string, submittedAt: string, selfRating: int}|null */
    public static function find(int $taskId, int $studentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT content, submitted_at, self_rating FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$taskId, $studentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return ['content' => (string) $row['content'], 'submittedAt' => (string) $row['submitted_at'], 'selfRating' => (int) ($row['self_rating'] ?? 0)];
    }

    public static function upsert(int $taskId, int $studentId, string $content, int $selfRating = 0): void
    {
        // Zeitstempel aus PHP (Zeitzone der Konfiguration), damit Vergleiche
        // mit dem Abgabetermin (lokale Eingabe) konsistent bleiben.
        $now = date('Y-m-d H:i:s');
        $selfRating = max(0, min(3, $selfRating));
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM task_submissions WHERE task_id = ? AND student_id = ?');
        $exists->execute([$taskId, $studentId]);
        if ((int) $exists->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE task_submissions SET content = ?, submitted_at = ?, self_rating = ? WHERE task_id = ? AND student_id = ?');
            $update->execute([$content, $now, $selfRating, $taskId, $studentId]);
            return;
        }
        $insert = $pdo->prepare('INSERT INTO task_submissions (task_id, student_id, content, submitted_at, self_rating) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$taskId, $studentId, $content, $now, $selfRating]);
    }

    /**
     * Alle Abgaben einer Aufgabe, indiziert nach Schüler-ID.
     * @return array<int, array{content: string, submittedAt: string, selfRating: int, firstName: string, lastName: string}>
     */
    public static function forTask(int $taskId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.content, s.submitted_at, s.self_rating, u.id AS student_id, u.first_name, u.last_name
             FROM task_submissions s
             JOIN users u ON u.id = s.student_id
             WHERE s.task_id = ?'
        );
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[(int) $row['student_id']] = [
                'content' => (string) $row['content'],
                'submittedAt' => (string) $row['submitted_at'],
                'selfRating' => (int) ($row['self_rating'] ?? 0),
                'firstName' => (string) $row['first_name'],
                'lastName' => (string) $row['last_name'],
            ];
        }
        return $list;
    }
}
