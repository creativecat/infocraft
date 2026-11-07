<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

final class Folder
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly string $name,
        public readonly int $position,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            (string) $row['name'],
            (int) ($row['position'] ?? 0),
        );
    }

    /** @return list<self> */
    public static function all(int $ownerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE owner_id = ? ORDER BY position, name');
        $stmt->execute([$ownerId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function create(int $ownerId, string $name): self
    {
        $name = substr(trim($name), 0, 100);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO folders (owner_id, name, position) VALUES (?, ?, (SELECT COALESCE(MAX(position), 0) + 1 FROM folders WHERE owner_id = ?))');
        $stmt->execute([$ownerId, $name, $ownerId]);
        return new self((int) $pdo->lastInsertId(), $ownerId, $name, 0);
    }

    public static function belongsTo(int $folderId, int $ownerId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM folders WHERE id = ? AND owner_id = ?');
        $stmt->execute([$folderId, $ownerId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
