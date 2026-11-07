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
    /** @return array{content: string, submittedAt: string}|null */
    public static function find(int $taskId, int $studentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT content, submitted_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$taskId, $studentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return ['content' => (string) $row['content'], 'submittedAt' => (string) $row['submitted_at']];
    }

    public static function upsert(int $taskId, int $studentId, string $content): void
    {
        // Zeitstempel aus PHP (Zeitzone der Konfiguration), damit Vergleiche
        // mit dem Abgabetermin (lokale Eingabe) konsistent bleiben.
        $now = date('Y-m-d H:i:s');
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM task_submissions WHERE task_id = ? AND student_id = ?');
        $exists->execute([$taskId, $studentId]);
        if ((int) $exists->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE task_submissions SET content = ?, submitted_at = ? WHERE task_id = ? AND student_id = ?');
            $update->execute([$content, $now, $taskId, $studentId]);
            return;
        }
        $insert = $pdo->prepare('INSERT INTO task_submissions (task_id, student_id, content, submitted_at) VALUES (?, ?, ?, ?)');
        $insert->execute([$taskId, $studentId, $content, $now]);
    }

    /**
     * Alle Abgaben einer Aufgabe, indiziert nach Schüler-ID.
     * @return array<int, array{content: string, submittedAt: string, firstName: string, lastName: string}>
     */
    public static function forTask(int $taskId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.content, s.submitted_at, u.id AS student_id, u.first_name, u.last_name
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
                'firstName' => (string) $row['first_name'],
                'lastName' => (string) $row['last_name'],
            ];
        }
        return $list;
    }
}
