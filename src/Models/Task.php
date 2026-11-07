<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Aufgabe im Aufgabenpool einer Lehrkraft, ausgeteilt an ganze
 * Klassen (class_id) oder einzelne Personen (user_id).
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
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['title'],
            (string) ($row['description'] ?? ''),
            (string) $row['type'],
            (int) ($row['level'] ?? 6),
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

    /**
     * Für Schülerinnen und Schüler: ausgeteilte Aufgaben bis zur eigenen Jahrgangsstufe.
     * @return list<array{task: self, className: ?string, teacherName: string}>
     */
    public static function forStudent(int $userId, ?int $classId, int $level): array
    {
        $sql = 'SELECT t.*, u.first_name, u.last_name, c.name AS class_name
                FROM tasks t
                JOIN users u ON u.id = t.teacher_id
                JOIN task_targets tt ON tt.task_id = t.id
                LEFT JOIN classes c ON c.id = tt.class_id
                WHERE t.level <= ?
                  AND (tt.user_id = ? OR (tt.class_id IS NOT NULL AND tt.class_id = ?))
                GROUP BY t.id
                ORDER BY t.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$level, $userId, $classId ?? 0]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $task = self::fromRow($row);
            $list[] = [
                'task' => $task,
                'className' => ($row['class_name'] ?? null) !== null ? (string) $row['class_name'] : null,
                'teacherName' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
            ];
        }
        return $list;
    }

    public static function create(int $teacherId, string $title, string $description, string $type, int $level): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO tasks (teacher_id, title, description, type, level) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$teacherId, substr(trim($title), 0, 200), $description, $type, max(1, min(13, $level))]);
        $stmt2 = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
        $stmt2->execute([(int) $pdo->lastInsertId()]);
        $row = $stmt2->fetch();
        return self::fromRow($row);
    }

    /** Aufgabe aus teilen; false, wenn es die Zuweisung schon gab. */
    public static function assign(int $taskId, ?int $classId, ?int $userId): bool
    {
        if ($classId === null && $userId === null) {
            return false;
        }
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM task_targets WHERE task_id = ? AND ((class_id IS NULL AND ? IS NULL AND user_id = ?) OR (user_id IS NULL AND ? IS NULL AND class_id = ?))');
        $stmt->execute([$taskId, $userId, $userId, $classId, $classId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $insert = Database::pdo()->prepare('INSERT INTO task_targets (task_id, class_id, user_id) VALUES (?, ?, ?)');
        $insert->execute([$taskId, $classId, $userId]);
        return true;
    }

    /** @return list<array{kind: string, name: string}> */
    public static function targets(int $taskId): array
    {
        $stmt = Database::pdo()->prepare('SELECT tt.class_id, tt.user_id, c.name AS class_name, u.first_name, u.last_name FROM task_targets tt LEFT JOIN classes c ON c.id = tt.class_id LEFT JOIN users u ON u.id = tt.user_id WHERE tt.task_id = ? ORDER BY tt.id');
        $stmt->execute([$taskId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['class_name'] !== null) {
                $list[] = ['kind' => 'Klasse', 'name' => (string) $row['class_name']];
            } else {
                $list[] = ['kind' => 'Person', 'name' => (string) $row['first_name'] . ' ' . (string) $row['last_name']];
            }
        }
        return $list;
    }
}
