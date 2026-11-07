<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

final class SchoolClass
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $diagramAuto = 1,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (string) $row['name'], (int) ($row['diagram_auto'] ?? 1));
    }

    /** Jahrgangsstufe aus dem Klassennamen lesen (6R -> 6, 10G -> 10). */
    public function level(): int
    {
        return preg_match('/(\d+)/', $this->name, $m) === 1 ? (int) $m[1] : 0;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM classes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function findOrCreate(string $name): self
    {
        $name = substr(trim($name), 0, 20);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM classes WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return new self((int) $row['id'], $name);
        }
        $insert = $pdo->prepare('INSERT INTO classes (name) VALUES (?)');
        $insert->execute([$name]);
        return new self((int) $pdo->lastInsertId(), $name);
    }

    /** @return list<self> */
    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT * FROM classes ORDER BY name')->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** Klassen einer Lehrkraft (für Admins: alle). @return list<self> */
    public static function forTeacher(int $teacherId, bool $all = false): array
    {
        if ($all) {
            return self::all();
        }
        $rows = Database::pdo()
            ->query('SELECT c.* FROM classes c JOIN class_teachers ct ON ct.class_id = c.id WHERE ct.teacher_id = ' . (int) $teacherId . ' ORDER BY c.name')
            ->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function assignTeacher(int $teacherId, int $classId): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'INSERT IGNORE INTO class_teachers (teacher_id, class_id) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO class_teachers (teacher_id, class_id) VALUES (?, ?)';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$teacherId, $classId]);
    }

    public static function hasTeacher(int $classId, int $teacherId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM class_teachers WHERE class_id = ? AND teacher_id = ?');
        $stmt->execute([$classId, $teacherId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Automatisches Erzeugen von Klassendiagrammen für die Klasse ein- oder ausschalten. */
    public static function setDiagramAuto(int $classId, bool $enabled): void
    {
        $stmt = Database::pdo()->prepare('UPDATE classes SET diagram_auto = ? WHERE id = ?');
        $stmt->execute([$enabled ? 1 : 0, $classId]);
    }

    /** Darf diese Person Klassendiagramme automatisch aus Skripten erzeugen lassen? */
    public static function diagramAutoForUser(User $user): bool
    {
        if ($user->role !== 'student' || $user->classId === null) {
            return true;
        }
        $stmt = Database::pdo()->prepare('SELECT diagram_auto FROM classes WHERE id = ? LIMIT 1');
        $stmt->execute([$user->classId]);
        $value = $stmt->fetchColumn();
        return $value === false || (int) $value === 1;
    }
}
