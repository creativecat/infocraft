<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;
use Infocraft\Models\User;

/**
 * SQL-Vorlage (z. B. Shop, Instagram, Adventure) mit Missionen
 * und Rechte-Zuweisungen (read/write/admin) pro Person.
 */
final class SqlTemplate
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly int $level,
        public readonly bool $isAdventure,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['title'],
            (string) ($row['description'] ?? ''),
            (int) ($row['level'] ?? 9),
            (int) ($row['is_adventure'] ?? 0) === 1,
        );
    }

    /** @return list<self> */
    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT * FROM sql_templates ORDER BY is_adventure, id')->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(string $slug): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sql_templates WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function findById(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sql_templates WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function hasGrants(User $user): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM sql_grants WHERE user_id = ?');
        $stmt->execute([$user->id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Datenbanken, die einer Person zugeteilt sind. @return list<array{template: self, grantId: int, permission: string}> */
    public static function grantsFor(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT g.id AS grant_id, g.permission, t.* FROM sql_grants g JOIN sql_templates t ON t.id = g.template_id WHERE g.user_id = ? ORDER BY t.is_adventure, t.id');
        $stmt->execute([$userId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $permission = (string) $row['permission'];
            unset($row['permission']);
            $list[] = ['template' => self::fromRow($row), 'grantId' => (int) $row['grant_id'], 'permission' => $permission];
        }
        return $list;
    }

    /** @return array{grantId: int, permission: string}|null */
    public static function grantFor(int $templateId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, permission FROM sql_grants WHERE template_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$templateId, $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : ['grantId' => (int) $row['id'], 'permission' => (string) $row['permission']];
    }

    public static function grant(int $templateId, int $userId, string $permission): void
    {
        $pdo = Database::pdo();
        if (Database::driver() === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO sql_grants (template_id, user_id, permission) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permission = ?');
            $stmt->execute([$templateId, $userId, $permission, $permission]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO sql_grants (template_id, user_id, permission) VALUES (?, ?, ?) ON CONFLICT(template_id, user_id) DO UPDATE SET permission = excluded.permission');
            $stmt->execute([$templateId, $userId, $permission]);
        }
    }

    public static function revoke(int $grantId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM sql_grants WHERE id = ?');
        $stmt->execute([$grantId]);
    }

    /** @return list<array{id: int, position: int, kind: string, title: string, story: string, hint: string, solution_sql: string}> */
    public static function missions(int $templateId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sql_missions WHERE template_id = ? ORDER BY position, id');
        $stmt->execute([$templateId]);
        return $stmt->fetchAll();
    }

    public static function findMission(int $missionId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sql_missions WHERE id = ? LIMIT 1');
        $stmt->execute([$missionId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<int> */
    public static function solvedMissionIds(int $grantId): array
    {
        $stmt = Database::pdo()->prepare('SELECT mission_id FROM sql_solves WHERE grant_id = ?');
        $stmt->execute([$grantId]);
        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $ids[] = (int) $row['mission_id'];
        }
        return $ids;
    }

    public static function solve(int $grantId, int $missionId): void
    {
        $pdo = Database::pdo();
        if (Database::driver() === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO sql_solves (grant_id, mission_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE solved_at = CURRENT_TIMESTAMP');
            $stmt->execute([$grantId, $missionId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO sql_solves (grant_id, mission_id) VALUES (?, ?) ON CONFLICT(grant_id, mission_id) DO NOTHING');
            $stmt->execute([$grantId, $missionId]);
        }
    }

    /**
     * Übersicht für Lehrkräfte: alle Zuweisungen der eigenen Klassen
     * (Admins sehen alle).
     * @return list<array{grantId: int, templateTitle: string, slug: string, userName: string, className: ?string, permission: string}>
     */
    public static function grantsOverview(int $teacherId, bool $all): array
    {
        $sql = 'SELECT g.id AS grant_id, g.permission, t.title AS template_title, t.slug, u.first_name, u.last_name, c.name AS class_name
                FROM sql_grants g
                JOIN sql_templates t ON t.id = g.template_id
                JOIN users u ON u.id = g.user_id
                LEFT JOIN classes c ON c.id = u.class_id'
            . ($all ? '' : ' JOIN class_teachers ct ON ct.class_id = u.class_id AND ct.teacher_id = ' . (int) $teacherId)
            . ' ORDER BY t.id, u.last_name, u.first_name';
        $list = [];
        foreach (Database::pdo()->query($sql)->fetchAll() as $row) {
            $list[] = [
                'grantId' => (int) $row['grant_id'],
                'templateTitle' => (string) $row['template_title'],
                'slug' => (string) $row['slug'],
                'userName' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
                'className' => $row['class_name'] !== null ? (string) $row['class_name'] : null,
                'permission' => (string) $row['permission'],
            ];
        }
        return $list;
    }

    /** Prüfen, ob eine Zuweisung zur Klasse einer Lehrkraft gehört (Admins: immer). */
    public static function grantBelongsToTeacher(int $grantId, int $teacherId, bool $admin): bool
    {
        if ($admin) {
            return true;
        }
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM sql_grants g JOIN users u ON u.id = g.user_id JOIN class_teachers ct ON ct.class_id = u.class_id WHERE g.id = ? AND ct.teacher_id = ?');
        $stmt->execute([$grantId, $teacherId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
