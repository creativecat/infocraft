<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Aufgabe im Aufgabenpool einer Lehrkraft, ausgeteilt an ganze
 * Klassen (class_id) oder einzelne Personen (user_id).
 * shared = 1: bewusst in den gemeinsamen Pool aller Lehrkräfte geteilt.
 * sample_solution: Musterlösung, u. a. als Vergleich für die Korrektur
 * und als Kontext für die KI-Erstbewertung.
 */
final class Task
{
    public function __construct(
        public readonly int $id,
        public readonly int $teacherId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $type,
        public readonly int $level,
        public readonly int $shared = 0,
        public readonly string $sampleSolution = '',
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['title'],
            (string) ($row['description'] ?? ''),
            (string) $row['type'],
            (int) ($row['level'] ?? 6),
            (int) ($row['shared'] ?? 0),
            (string) ($row['sample_solution'] ?? ''),
        );
    }

    /** @return list<self> */
    public static function forTeacher(int $teacherId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM tasks WHERE teacher_id = ? ORDER BY id DESC');
        $stmt->execute([$teacherId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /**
     * Für Schülerinnen und Schüler: ausgeteilte Aufgaben bis zur eigenen
     * Jahrgangsstufe, mit frühstem Abgabetermin, eigener Abgabe und Korrektur.
     *
     * @return list<array{task: self, className: ?string, teacherName: string, dueAt: ?string, submission: ?array{content: string, submittedAt: string}, grading: ?array{grade: string, comment: string, source: string, checked: bool}}>
     */
    public static function forStudent(int $userId, ?int $classId, int $level): array
    {
        $sql = 'SELECT t.*, u.first_name, u.last_name,
                    (SELECT MIN(tt.due_at) FROM task_targets tt
                        WHERE tt.task_id = t.id
                          AND (tt.user_id = ? OR (tt.class_id IS NOT NULL AND tt.class_id = ?))) AS due_at,
                    (SELECT c2.name FROM task_targets tt3
                        JOIN classes c2 ON c2.id = tt3.class_id
                        WHERE tt3.task_id = t.id AND tt3.class_id = ? LIMIT 1) AS class_name,
                    (SELECT s.content FROM task_submissions s
                        WHERE s.task_id = t.id AND s.student_id = ?) AS sub_content,
                    (SELECT s.submitted_at FROM task_submissions s
                        WHERE s.task_id = t.id AND s.student_id = ?) AS submitted_at,
                    (SELECT g.grade FROM task_gradings g
                        WHERE g.task_id = t.id AND g.student_id = ?) AS grade,
                    (SELECT g.comment FROM task_gradings g
                        WHERE g.task_id = t.id AND g.student_id = ?) AS g_comment,
                    (SELECT g.source FROM task_gradings g
                        WHERE g.task_id = t.id AND g.student_id = ?) AS g_source,
                    (SELECT g.checked FROM task_gradings g
                        WHERE g.task_id = t.id AND g.student_id = ?) AS g_checked
                FROM tasks t
                JOIN users u ON u.id = t.teacher_id
                WHERE t.level <= ?
                  AND EXISTS (
                        SELECT 1 FROM task_targets tt2
                        WHERE tt2.task_id = t.id
                          AND (tt2.user_id = ? OR (tt2.class_id IS NOT NULL AND tt2.class_id = ?)))
                ORDER BY t.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            $userId, $classId ?? 0, $classId ?? 0,
            $userId, $userId, $userId, $userId, $userId, $userId,
            $level, $userId, $classId ?? 0,
        ]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $task = self::fromRow($row);
            $submission = null;
            if ($row['sub_content'] !== null) {
                $submission = [
                    'content' => (string) $row['sub_content'],
                    'submittedAt' => (string) $row['submitted_at'],
                ];
            }
            $grading = null;
            if ($row['grade'] !== null) {
                $grading = [
                    'grade' => (string) $row['grade'],
                    'comment' => (string) $row['g_comment'],
                    'source' => (string) $row['g_source'],
                    'checked' => (int) $row['g_checked'] === 1,
                ];
            }
            $list[] = [
                'task' => $task,
                'className' => ($row['class_name'] ?? null) !== null ? (string) $row['class_name'] : null,
                'teacherName' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
                'dueAt' => ($row['due_at'] ?? null) !== null && $row['due_at'] !== '' ? (string) $row['due_at'] : null,
                'submission' => $submission,
                'grading' => $grading,
            ];
        }
        return $list;
    }

    public static function create(int $teacherId, string $title, string $description, string $type, int $level): self
    {
        return self::createFull($teacherId, $title, $description, $type, $level, 0, '');
    }

    public static function createFull(int $teacherId, string $title, string $description, string $type, int $level, int $shared, string $sampleSolution): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO tasks (teacher_id, title, description, type, level, shared, sample_solution) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $teacherId,
            substr(trim($title), 0, 200),
            $description,
            $type,
            max(1, min(13, $level)),
            $shared === 1 ? 1 : 0,
            $sampleSolution,
        ]);
        $stmt2 = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt2->execute([(int) $pdo->lastInsertId()]);
        $row = $stmt2->fetch();
        return self::fromRow($row);
    }

    /** Eigene Aufgabe nachträglich bearbeiten. */
    public static function update(int $id, string $title, string $description, string $type, int $level, string $sampleSolution): void
    {
        $stmt = Database::pdo()->prepare('UPDATE tasks SET title = ?, description = ?, type = ?, level = ?, sample_solution = ? WHERE id = ?');
        $stmt->execute([
            substr(trim($title), 0, 200),
            $description,
            $type,
            max(1, min(13, $level)),
            $sampleSolution,
            $id,
        ]);
    }

    /** Aufgabe in den gemeinsamen Pool der Lehrkräfte teilen bzw. zurückziehen. */
    public static function setShared(int $id, bool $shared): void
    {
        $stmt = Database::pdo()->prepare('UPDATE tasks SET shared = ? WHERE id = ?');
        $stmt->execute([$shared ? 1 : 0, $id]);
    }

    /**
     * Gemeinsamer Pool: von anderen Lehrkräften geteilte Aufgaben mit Urhebername.
     * @return list<array{task: self, authorName: string}>
     */
    public static function pool(int $teacherId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.*, u.first_name, u.last_name FROM tasks t
             JOIN users u ON u.id = t.teacher_id
             WHERE t.shared = 1 AND t.teacher_id <> ?
             ORDER BY t.id DESC'
        );
        $stmt->execute([$teacherId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'task' => self::fromRow($row),
                'authorName' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
            ];
        }
        return $list;
    }

    /** Aufgabe aus dem gemeinsamen Pool als eigene, frei anpassbare Kopie übernehmen. */
    public static function adopt(self $source, int $teacherId): self
    {
        return self::createFull(
            $teacherId,
            $source->title,
            $source->description,
            $source->type,
            $source->level,
            0,
            $source->sampleSolution,
        );
    }

    /** Prüft, ob eine Lehrkraft eine Aufgabe direkt sehen darf (Eigentümer oder Admin). */
    public static function ownedBy(self $task, User $user): bool
    {
        return $user->role === 'admin' || $task->teacherId === $user->id;
    }

    /** Aufgabe aus teilen; false, wenn es die Zuweisung schon gab. */
    public static function assign(int $taskId, ?int $classId, ?int $userId, ?string $dueAt = null): bool
    {
        if ($classId === null && $userId === null) {
            return false;
        }
        $pdo = Database::pdo();
        if ($classId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM task_targets WHERE task_id = ? AND class_id = ?');
            $stmt->execute([$taskId, $classId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM task_targets WHERE task_id = ? AND user_id = ?');
            $stmt->execute([$taskId, $userId]);
        }
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $insert = $pdo->prepare('INSERT INTO task_targets (task_id, class_id, user_id, due_at) VALUES (?, ?, ?, ?)');
        $insert->execute([$taskId, $classId, $userId, $dueAt]);
        return true;
    }

    /** Abgabetermin einer bestehenden Zuweisung setzen oder streichen. */
    public static function setTargetDueAt(int $targetId, ?string $dueAt): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE task_targets SET due_at = ? WHERE id = ?');
        $stmt->execute([$dueAt, $targetId]);
        return $stmt->rowCount() > 0;
    }

    /** Eine bestimmte Zuweisung finden (für Terminänderungen der Lehrkraft). */
    public static function findTarget(int $targetId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM task_targets WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Empfänger einer Aufgabe: Klassen und einzelne Personen mit Abgabetermin.
     * @return list<array{id: int, kind: string, name: string, dueAt: ?string, classId: ?int, userId: ?int}>
     */
    public static function targets(int $taskId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT tt.id, tt.class_id, tt.user_id, tt.due_at, c.name AS class_name, u.first_name, u.last_name
             FROM task_targets tt
             LEFT JOIN classes c ON c.id = tt.class_id
             LEFT JOIN users u ON u.id = tt.user_id
             WHERE tt.task_id = ? ORDER BY tt.id'
        );
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['class_name'] !== null) {
                $list[] = [
                    'id' => (int) $row['id'],
                    'kind' => 'Klasse',
                    'name' => (string) $row['class_name'],
                    'dueAt' => ($row['due_at'] ?? null) !== null && $row['due_at'] !== '' ? (string) $row['due_at'] : null,
                    'assignedAt' => (string) $row['created_at'],
                    'classId' => (int) $row['class_id'],
                    'userId' => null,
                ];
            } else {
                $list[] = [
                    'id' => (int) $row['id'],
                    'kind' => 'Person',
                    'name' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
                    'dueAt' => ($row['due_at'] ?? null) !== null && $row['due_at'] !== '' ? (string) $row['due_at'] : null,
                    'assignedAt' => (string) $row['created_at'],
                    'classId' => null,
                    'userId' => (int) $row['user_id'],
                ];
            }
        }
        return $list;
    }

    /** Prueft, ob eine Lehrkraft bereits eine Aufgabe mit diesem Titel im Pool hat. */
    public static function hasTitle(int $teacherId, string $title): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM tasks WHERE teacher_id = ? AND LOWER(title) = LOWER(?)');
        $stmt->execute([$teacherId, trim($title)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
    }
}
