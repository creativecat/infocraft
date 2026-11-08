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
        public readonly ?int $parentId = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            (string) $row['name'],
            (int) ($row['position'] ?? 0),
            ($row['parent_id'] ?? null) !== null && (int) $row['parent_id'] > 0 ? (int) $row['parent_id'] : null,
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

    /** @param list<self> $folders */
    public static function orderedTree(array $folders): array
    {
        $byParent = [];
        foreach ($folders as $folder) {
            $byParent[$folder->parentId ?? 0][] = $folder;
        }
        $rows = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $folder) {
                $rows[] = ['folder' => $folder, 'depth' => $depth];
                $walk($folder->id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $rows;
    }

    public static function create(int $ownerId, string $name, ?int $parentId = null): self
    {
        $name = substr(trim($name), 0, 100);
        if ($parentId !== null && !self::belongsTo($parentId, $ownerId)) {
            $parentId = null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO folders (owner_id, name, parent_id, position) VALUES (?, ?, ?, (SELECT COALESCE(MAX(position), 0) + 1 FROM folders WHERE owner_id = ?))');
        $stmt->execute([$ownerId, $name, $parentId ?? 0, $ownerId]);
        return new self((int) $pdo->lastInsertId(), $ownerId, $name, 0, $parentId);
    }

    public static function belongsTo(int $folderId, int $ownerId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM folders WHERE id = ? AND owner_id = ?');
        $stmt->execute([$folderId, $ownerId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
