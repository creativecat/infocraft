<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Präsentationen und Folien
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Präsentation einer Lehrkraft: Folien als JSON im content-Feld.
 * Jede Folie ist ein Feld mit type ('text' oder 'code'), title und
 * je nach Typ body (Text, Markdown-lite) oder lang + code.
 * Schülerinnen und Schüler sehen Präsentationen ab der angegebenen Jahrgangsstufe.
 */
final class Presentation
{
    public function __construct(
        public readonly int $id,
        public readonly int $teacherId,
        public readonly string $title,
        public readonly ?string $slug,
        public readonly int $level,
        public readonly string $content,
        public readonly int $position,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['title'],
            isset($row['slug']) ? ((string) $row['slug'] !== '' ? (string) $row['slug'] : null) : null,
            (int) ($row['level'] ?? 6),
            (string) ($row['content'] ?? '[]'),
            (int) ($row['position'] ?? 0),
        );
    }

    /**
     * Folien der Präsentation als Liste von Feldern. 'notes' enthält die
     * Sprechernotiz: Sie ist NUR im Moderatorenbildschirm für Lehrkräfte
     * sichtbar, in der Schüler-Ansicht wird sie nie ausgegeben.
     * @return list<array{type: string, title: string, body?: string, lang?: string, code?: string, notes?: string}>
     */
    public function slides(): array
    {
        $decoded = json_decode($this->content, true);
        if (!is_array($decoded)) {
            return [];
        }
        $slides = [];
        foreach ($decoded as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $type = ($slide['type'] ?? '') === 'code' ? 'code' : 'text';
            $entry = [
                'type' => $type,
                'title' => (string) ($slide['title'] ?? ''),
            ];
            if ($type === 'code') {
                $entry['lang'] = self::cleanLang((string) ($slide['lang'] ?? 'java'));
                $entry['code'] = (string) ($slide['code'] ?? '');
            } else {
                $entry['body'] = (string) ($slide['body'] ?? '');
            }
            $notes = trim((string) ($slide['notes'] ?? ''));
            if ($notes !== '') {
                $entry['notes'] = $notes;
            }
            $slides[] = $entry;
        }
        return $slides;
    }

    /** Erlaubte Sprachen für Code-Folien (highlight.js im Browser kennt alle vier). */
    public static function cleanLang(string $lang): string
    {
        return in_array($lang, ['java', 'python', 'sql', 'html'], true) ? $lang : 'java';
    }

    /** @param list<array{type: string, title: string, body?: string, lang?: string, code?: string, notes?: string}> $slides */
    public function setSlides(array $slides): void
    {
        $clean = [];
        foreach ($slides as $slide) {
            $type = ($slide['type'] ?? '') === 'code' ? 'code' : 'text';
            $entry = [
                'type' => $type,
                'title' => mb_substr(trim((string) ($slide['title'] ?? '')), 0, 200),
            ];
            if ($type === 'code') {
                $entry['lang'] = self::cleanLang((string) ($slide['lang'] ?? 'java'));
                $entry['code'] = (string) ($slide['code'] ?? '');
            } else {
                $entry['body'] = (string) ($slide['body'] ?? '');
            }
            $notes = trim((string) ($slide['notes'] ?? ''));
            if ($notes !== '') {
                $entry['notes'] = $notes;
            }
            $clean[] = $entry;
        }
        $stmt = Database::pdo()->prepare('UPDATE presentations SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '[]', $this->id]);
    }

    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT * FROM presentations ORDER BY level, position, id')->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** @return list<self> */
    public static function visibleFor(int $level): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM presentations WHERE level <= ? ORDER BY level, position, id');
        $stmt->execute([max(0, $level)]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM presentations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Themenseite: Präsentation über ihren Slug finden (z. B. datenschutz). */
    public static function findBySlug(string $slug): ?self
    {
        $slug = substr(trim($slug), 0, 60);
        if ($slug === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM presentations WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $teacherId, string $title, int $level): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM presentations');
        $stmt->execute();
        $position = (int) $stmt->fetchColumn() + 1;
        $stmt = $pdo->prepare('INSERT INTO presentations (teacher_id, title, level, content, position) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$teacherId, substr(trim($title), 0, 200), max(1, min(13, $level)), '[]', $position]);
        $page = self::find((int) $pdo->lastInsertId());
        return $page ?? throw new \RuntimeException('Präsentation konnte nicht angelegt werden.');
    }

    public function save(string $title, int $level): void
    {
        $stmt = Database::pdo()->prepare('UPDATE presentations SET title = ?, level = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([substr(trim($title), 0, 200), max(1, min(13, $level)), $this->id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM presentations WHERE id = ?');
        $stmt->execute([$id]);
    }
}
