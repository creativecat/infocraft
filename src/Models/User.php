<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;
use RuntimeException;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $role,
        public readonly ?int $classId,
        public readonly ?string $externalId,
        public readonly ?string $loginName,
        public readonly ?string $passwordHash,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['role'],
            self::nullableInt($row['class_id'] ?? null),
            self::nullableString($row['external_id'] ?? null),
            self::nullableString($row['login_name'] ?? null),
            self::nullableString($row['password_hash'] ?? null),
        );
    }

    public function fullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Administration',
            'teacher' => 'Lehrkraft',
            default => 'Schülerin/Schüler',
        };
    }

    public function setExternalId(string $externalId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET external_id = ? WHERE id = ?');
        $stmt->execute([$externalId, $this->id]);
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function findByLoginName(string $loginName): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE login_name = ? LIMIT 1');
        $stmt->execute([$loginName]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function findByExternalId(string $externalId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE external_id = ? LIMIT 1');
        $stmt->execute([$externalId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function findByName(string $firstName, string $lastName): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE lower(first_name) = lower(?) AND lower(last_name) = lower(?) LIMIT 1');
        $stmt->execute([$firstName, $lastName]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** @return list<array{user: self, class: ?string}> */
    public static function allWithClass(int $limit = 500, bool $demoOrder = false): array
    {
        $order = $demoOrder
            ? "ORDER BY CASE u.role WHEN 'admin' THEN 1 WHEN 'teacher' THEN 2 ELSE 3 END, u.last_name, u.first_name"
            : 'ORDER BY u.role, u.last_name, u.first_name';
        $rows = Database::pdo()
            ->query('SELECT u.*, c.name AS class_name FROM users u LEFT JOIN classes c ON c.id = u.class_id ' . $order . ' LIMIT ' . (int) $limit)
            ->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['user' => self::fromRow($row), 'class' => isset($row['class_name']) ? (string) $row['class_name'] : null];
        }
        return $list;
    }

    /** @return list<self> */
    public static function byClass(int $classId): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE class_id = ? AND role = 'student' ORDER BY last_name, first_name");
        $stmt->execute([$classId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** @return list<self> */
    public static function teachers(): array
    {
        $rows = Database::pdo()->query("SELECT * FROM users WHERE role IN ('teacher', 'admin') ORDER BY role, last_name, first_name")->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function create(
        string $firstName,
        string $lastName,
        string $role,
        ?int $classId,
        ?string $loginName,
        ?string $passwordHash,
        ?string $externalId
    ): self {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, login_name, password_hash, role, class_id, external_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$firstName, $lastName, $loginName, $passwordHash, $role, $classId, $externalId]);
        $user = self::find((int) $pdo->lastInsertId());
        if ($user === null) {
            throw new RuntimeException('Nutzer konnte nicht angelegt werden.');
        }
        return $user;
    }

    public static function updateRoleAndClass(int $id, ?int $classId, string $role): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET class_id = COALESCE(?, class_id), role = ? WHERE id = ?');
        $stmt->execute([$classId, $role, $id]);
    }

    /** @return array<string, int> */
    public static function countByRole(): array
    {
        $stats = ['student' => 0, 'teacher' => 0, 'admin' => 0];
        $rows = Database::pdo()->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role')->fetchAll();
        foreach ($rows as $row) {
            $stats[(string) $row['role']] = (int) $row['n'];
        }
        return $stats;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
