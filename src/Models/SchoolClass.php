<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

final class SchoolClass
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (string) $row['name']);
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
}
