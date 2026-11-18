<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: ToDos zu Aufgaben
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * ToDos an einer Aufgabe. Drei Arten von Zeilen:
 *  - student_id NULL, template_id NULL: Vorlage der Lehrkraft (ausgeteilt
 *    an alle, die die Aufgabe bearbeiten),
 *  - student_id gesetzt, template_id gesetzt: Abhak-Kopie einer Schülerin
 *    bzw. eines Schülers für eine Vorlage (zustand der Vorlage pro Person),
 *  - student_id gesetzt, template_id NULL: eigenes ToDo der Schülerin bzw.
 *    des Schülers.
 */
final class TaskTodo
{
    public function __construct(
        public readonly int $id,
        public readonly int $taskId,
        public readonly ?int $studentId,
        public readonly ?int $templateId,
        public readonly string $title,
        public readonly int $done,
        public readonly int $sort = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['task_id'],
            ($row['student_id'] ?? null) !== null ? (int) $row['student_id'] : null,
            ($row['template_id'] ?? null) !== null ? (int) $row['template_id'] : null,
            (string) $row['title'],
            (int) ($row['done'] ?? 0),
            (int) ($row['sort'] ?? 0),
        );
    }

    /** @return list<self> Vorlagen der Lehrkraft an einer Aufgabe */
    public static function templatesForTask(int $taskId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_todos WHERE task_id = ? AND student_id IS NULL AND template_id IS NULL ORDER BY sort ASC, id ASC');
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /**
     * Vorlagen mehrerer Aufgaben auf einmal.
     * @param list<int> $taskIds
     * @return array<int, list<self>>
     */
    public static function templatesForTasks(array $taskIds): array
    {
        $map = [];
        $ids = [];
        foreach ($taskIds as $taskId) {
            $ids[] = (int) $taskId;
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return $map;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare('SELECT * FROM task_todos WHERE student_id IS NULL AND template_id IS NULL AND task_id IN (' . $placeholders . ') ORDER BY sort ASC, id ASC');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $todo = self::fromRow($row);
            $map[$todo->taskId][] = $todo;
        }
        return $map;
    }

    /**
     * Alle Zeilen einer Schülerin bzw. eines Schülers an mehreren Aufgaben
     * (Abhak-Kopien für Vorlagen und eigene ToDos).
     * @param list<int> $taskIds
     * @return array<int, list<self>> Map von task_id auf Zeilen
     */
    public static function forStudentTasks(int $studentId, array $taskIds): array
    {
        $map = [];
        $ids = [];
        foreach ($taskIds as $taskId) {
            $ids[] = (int) $taskId;
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return $map;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $params = $ids;
        array_unshift($params, $studentId);
        $stmt = Database::pdo()->prepare('SELECT * FROM task_todos WHERE student_id = ? AND task_id IN (' . $placeholders . ') ORDER BY sort ASC, id ASC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $todo = self::fromRow($row);
            $map[$todo->taskId][] = $todo;
        }
        return $map;
    }

    /** Vorlage der Lehrkraft anlegen. */
    public static function addTemplate(int $taskId, string $title): void
    {
        $title = trim(mb_substr($title, 0, 300));
        if ($title === '') {
            return;
        }
        $sort = (int) Database::pdo()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM task_todos WHERE task_id = ' . (int) $taskId . ' AND student_id IS NULL AND template_id IS NULL')->fetchColumn();
        $stmt = Database::pdo()->prepare('INSERT INTO task_todos (task_id, student_id, template_id, title, done, sort) VALUES (?, NULL, NULL, ?, 0, ?)');
        $stmt->execute([$taskId, $title, $sort]);
    }

    /** Vorlage löschen, inklusive aller Abhak-Kopien. */
    public static function deleteTemplate(int $templateId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM task_todos WHERE (id = ? AND student_id IS NULL AND template_id IS NULL) OR template_id = ?');
        $stmt->execute([$templateId, $templateId]);
    }

    /**
     * Vorlage abhaken bzw. Haken entfernen: Abhak-Kopie anlegen oder
     * umschalten, damit der Zustand pro Schülerin bzw. Schüler erhalten
     * bleibt und die Vorlage für andere unberührt bleibt.
     */
    public static function toggleTemplate(int $taskId, int $studentId, int $templateId): void
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_todos WHERE student_id = ? AND template_id = ? LIMIT 1');
        $stmt->execute([$studentId, $templateId]);
        $row = $stmt->fetch();
        if ($row === false) {
            $find = Database::pdo()->prepare('SELECT title, sort FROM task_todos WHERE id = ? LIMIT 1');
            $find->execute([$templateId]);
            $template = $find->fetch();
            if ($template === false) {
                return;
            }
            $insert = Database::pdo()->prepare('INSERT INTO task_todos (task_id, student_id, template_id, title, done, sort) VALUES (?, ?, ?, ?, 1, ?)');
            $insert->execute([$taskId, $studentId, $templateId, (string) $template['title'], (int) $template['sort']]);
            return;
        }
        $update = Database::pdo()->prepare('UPDATE task_todos SET done = ? WHERE id = ?');
        $update->execute([(int) $row['done'] === 1 ? 0 : 1, (int) $row['id']]);
    }

    /** Eigenes ToDo einer Schülerin bzw. eines Schülers anlegen. */
    public static function addStudent(int $taskId, int $studentId, string $title): void
    {
        $title = trim(mb_substr($title, 0, 300));
        if ($title === '') {
            return;
        }
        $sort = (int) Database::pdo()->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM task_todos WHERE task_id = ' . (int) $taskId . ' AND student_id = ' . (int) $studentId)->fetchColumn();
        $stmt = Database::pdo()->prepare('INSERT INTO task_todos (task_id, student_id, template_id, title, done, sort) VALUES (?, ?, NULL, ?, 0, ?)');
        $stmt->execute([$taskId, $studentId, $title, $sort]);
    }

    /** Eigenes ToDo abhaken bzw. Haken entfernen. */
    public static function toggleStudent(int $todoId, int $studentId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE task_todos SET done = 1 - done WHERE id = ? AND student_id = ? AND template_id IS NULL');
        $stmt->execute([$todoId, $studentId]);
    }

    /** Eigenes ToDo löschen (nur die eigene Zeile). */
    public static function deleteStudent(int $todoId, int $studentId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM task_todos WHERE id = ? AND student_id = ? AND template_id IS NULL');
        $stmt->execute([$todoId, $studentId]);
    }
}
