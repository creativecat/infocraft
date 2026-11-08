<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Diagramm einer Person: selbst gezeichnet (manuell) oder automatisch
 * aus Java-, Python- bzw. SQL-Quellen erzeugt. Neben Klassendiagrammen
 * (Mermaid classDiagram) verwaltet die Tabelle auch Struktogramme
 * (Nassi-Shneiderman) und Datenflussdiagramme mit eigener, an Mermaid
 * angelehnter DSL (src/DiagramDsl.php). Der Quelltext bleibt immer
 * editierbar und exportierbar.
 */
final class ClassDiagram
{
    /** Art des Diagramms (Anzeige und Filter). */
    public const KINDS = [
        'manuell' => '✏️ selbst gezeichnet',
        'java' => '☕ aus Java',
        'python' => '🐍 aus Python',
        'sql' => '🗄️ aus SQL',
        'struktogramm' => '🧱 Struktogramm',
        'datenfluss' => '🌊 Datenflussdiagramm',
    ];

    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly string $title,
        public readonly string $kind,
        public readonly ?int $sourceId,
        public readonly string $mermaid,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            (string) $row['title'],
            (string) ($row['kind'] ?? 'manuell'),
            ($row['source_id'] ?? null) === null ? null : (int) $row['source_id'],
            (string) ($row['mermaid'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
        );
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['manuell'];
    }

    /** @return list<self> */
    public static function all(int $ownerId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM class_diagrams WHERE owner_id = ? ORDER BY updated_at DESC, id DESC');
        $stmt->execute([$ownerId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM class_diagrams WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $ownerId, string $title, string $kind, ?int $sourceId, string $mermaid): self
    {
        if (!array_key_exists($kind, self::KINDS)) {
            $kind = 'manuell';
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO class_diagrams (owner_id, title, kind, source_id, mermaid) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$ownerId, substr(trim($title), 0, 200), $kind, $sourceId, $mermaid]);
        $diagram = self::find((int) $pdo->lastInsertId());
        return $diagram ?? throw new \RuntimeException('Klassendiagramm konnte nicht angelegt werden.');
    }

    public function save(string $title, string $mermaid, ?string $kind = null): void
    {
        // Typwechsel nur zwischen den selbst angelegten Diagrammen: automatisch
        // erzeugte (Java, Python, SQL) behalten ihre Herkunft.
        $newKind = $this->kind;
        if ($kind !== null
            && in_array($kind, ['manuell', 'struktogramm', 'datenfluss'], true)
            && in_array($this->kind, ['manuell', 'struktogramm', 'datenfluss'], true)) {
            $newKind = $kind;
        }
        $stmt = Database::pdo()->prepare('UPDATE class_diagrams SET title = ?, mermaid = ?, kind = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([substr(trim($title), 0, 200), $mermaid, $newKind, $this->id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM class_diagrams WHERE id = ?');
        $stmt->execute([$id]);
    }
}
