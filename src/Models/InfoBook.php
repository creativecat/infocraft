<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * InfoBook (#62): Notizbuecher im OneNote-Stil.
 *
 * Es gibt persoenliche Buecher (nur die Lehrkraft, optional an alle
 * Lehrkraefte geteilt) und genau ein Klassen-InfoBook pro Klasse mit drei
 * Bereichen:
 *  - 'lernbereich': Inhalte der Lehrkraft, Schuelerinnen und Schueler lesen nur
 *  - 'gemeinsam':   alle der Klasse duerfen mitschreiben
 *  - 'eigen':       privater Bereich je Schuelerin/Schueler (nur sie/er und die Lehrkraft)
 *
 * Eine Seite besteht aus Bloecken (JSON): text, image, audio, video, embed,
 * zeichnen. Bilder/Audio/Video liegen als BLOB in ibook_media, Freihand-
 * Notizen (Apple Pencil) werden als PNG gespeichert.
 */
final class InfoBook
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly ?int $classId,
        public readonly string $title,
        public readonly bool $shared,
        public readonly ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            isset($row['class_id']) && $row['class_id'] !== null ? (int) $row['class_id'] : null,
            (string) $row['title'],
            ((int) ($row['shared'] ?? 0)) === 1,
            ($row['created_at'] ?? null) === null ? null : (string) $row['created_at'],
        );
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT id, owner_id, class_id, title, shared, created_at FROM ibooks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Persoenliche Buecher einer Lehrkraft (eigene und an alle geteilte). @return list<self> */
    public static function personalBooks(int $teacherId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, owner_id, class_id, title, shared, created_at FROM ibooks WHERE class_id IS NULL AND (owner_id = ? OR shared = 1) ORDER BY owner_id = ? DESC, title'
        );
        $stmt->execute([$teacherId, $teacherId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function classBook(int $classId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT id, owner_id, class_id, title, shared, created_at FROM ibooks WHERE class_id = ? LIMIT 1');
        $stmt->execute([$classId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /**
     * Klassen-InfoBook einer Klasse: legt es beim ersten Aufruf automatisch
     * an (mit den drei Standard-Bereichen). Lehrkraft = anlegende Person.
     */
    public static function ensureClassBook(int $classId, int $teacherId): self
    {
        $existing = self::classBook($classId);
        if ($existing !== null) {
            return $existing;
        }
        $class = SchoolClass::find($classId);
        $title = 'Klassen-InfoBook ' . ($class !== null ? $class->name : (string) $classId);
        Database::pdo()->prepare('INSERT INTO ibooks (owner_id, class_id, title, shared) VALUES (?, ?, ?, 0)')
            ->execute([$teacherId, $classId, $title]);
        $book = self::find((int) Database::pdo()->lastInsertId());
        $book = $book ?? new self(0, $teacherId, $classId, $title, false, null);
        $pos = 0;
        foreach ([['lernbereich', 'Lernbereich'], ['gemeinsam', 'Zusammenarbeit']] as [$area, $name]) {
            Database::pdo()->prepare('INSERT INTO ibook_sections (book_id, area, owner_user_id, title, position) VALUES (?, ?, NULL, ?, ?)')
                ->execute([$book->id, $area, $name, $pos]);
            $pos++;
        }
        return $book;
    }

    /** Abschnitt anlegen. area in lernbereich/gemeinsam/eigen. */
    public static function createSection(int $bookId, string $area, ?int $ownerUserId, string $title): int
    {
        $area = in_array($area, ['lernbereich', 'gemeinsam', 'eigen'], true) ? $area : 'lernbereich';
        $stmt = Database::pdo()->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM ibook_sections WHERE book_id = ?');
        $stmt->execute([$bookId]);
        $pos = (int) $stmt->fetchColumn();
        Database::pdo()->prepare('INSERT INTO ibook_sections (book_id, area, owner_user_id, title, position) VALUES (?, ?, ?, ?, ?)')
            ->execute([$bookId, $area, $ownerUserId, $title, $pos]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Privaten Schuelerbereich ('eigen') sicherstellen und zurueckgeben. */
    public static function ensureOwnSection(int $bookId, int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, book_id, area, owner_user_id, title, position FROM ibook_sections WHERE book_id = ? AND area = \'eigen\' AND owner_user_id = ? LIMIT 1');
        $stmt->execute([$bookId, $userId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            return $row;
        }
        $user = User::find($userId);
        $name = 'Mein Bereich' . ($user !== null ? ' - ' . $user->firstName : '');
        self::createSection($bookId, 'eigen', $userId, $name);
        $stmt->execute([$bookId, $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    /** @return list<array{id: int, book_id: int, area: string, owner_user_id: ?int, title: string, position: int}> */
    public static function sections(int $bookId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, book_id, area, owner_user_id, title, position FROM ibook_sections WHERE book_id = ? ORDER BY position, id');
        $stmt->execute([$bookId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function findSection(int $sectionId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, book_id, area, owner_user_id, title, position FROM ibook_sections WHERE id = ? LIMIT 1');
        $stmt->execute([$sectionId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function deleteSection(int $sectionId): void
    {
        Database::pdo()->prepare('DELETE FROM ibook_sections WHERE id = ?')->execute([$sectionId]);
    }

    /** @return list<array{id: int, section_id: int, title: string, content: string, position: int}> */
    public static function pages(int $sectionId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, section_id, title, content, position FROM ibook_pages WHERE section_id = ? ORDER BY position, id');
        $stmt->execute([$sectionId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function findPage(int $pageId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, section_id, title, content, position FROM ibook_pages WHERE id = ? LIMIT 1');
        $stmt->execute([$pageId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function createPage(int $sectionId, string $title): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM ibook_pages WHERE section_id = ?');
        $stmt->execute([$sectionId]);
        $pos = (int) $stmt->fetchColumn();
        Database::pdo()->prepare('INSERT INTO ibook_pages (section_id, title, content, position) VALUES (?, ?, \'[]\', ?)')
            ->execute([$sectionId, $title, $pos]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Zahl fuer die Freie Flaeche begrenzen: alles innerhalb der Flaeche,
     * ganze Zahlen bleiben ganze Zahlen (DB-JSON bleibt kompakt).
     */
    private static function cvNum(mixed $value, float $min, float $max): float|int
    {
        $n = round((float) $value, 2);
        if ($n < $min) {
            $n = $min;
        }
        if ($n > $max) {
            $n = $max;
        }
        return (int) $n === $n ? (int) $n : $n;
    }

    /** Elemente der Freien Flaeche (#63) bereinigen: Positionen begrenzen, nur gueltige Bilder/Texte. @param list<mixed> $items */
    private static function cleanCanvasItems(mixed $items, float $w, float $h): array
    {
        if (!is_array($items)) {
            return [];
        }
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item) || count($clean) >= 200) {
                continue;
            }
            $kind = (string) ($item['kind'] ?? '');
            if ($kind === 'image') {
                $media = (int) ($item['media'] ?? 0);
                if ($media <= 0) {
                    continue;
                }
                $it = [
                    'kind' => 'image',
                    'media' => $media,
                    'name' => mb_substr((string) ($item['name'] ?? ''), 0, 160),
                    'x' => self::cvNum($item['x'] ?? 0, 0, $w),
                    'y' => self::cvNum($item['y'] ?? 0, 0, $h),
                    'w' => self::cvNum($item['w'] ?? 160, 16, $w),
                    'h' => self::cvNum($item['h'] ?? 120, 16, $h),
                ];
                if (isset($item['alt'])) {
                    $it['alt'] = mb_substr((string) $item['alt'], 0, 200);
                }
                $clean[] = $it;
            } elseif ($kind === 'text') {
                $clean[] = [
                    'kind' => 'text',
                    'body' => mb_substr((string) ($item['body'] ?? ''), 0, 20000),
                    'x' => self::cvNum($item['x'] ?? 0, 0, $w),
                    'y' => self::cvNum($item['y'] ?? 0, 0, $h),
                    'w' => self::cvNum($item['w'] ?? 320, 60, $w),
                ];
            }
        }
        return $clean;
    }

    /** Seite speichern: Titel und Bloecke (JSON wird serverseitig bereinigt). */
    public static function savePage(int $pageId, string $title, array $blocks): void
    {
        $clean = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            $entry = ['type' => in_array($type, ['text', 'image', 'audio', 'video', 'embed', 'zeichnen', 'canvas'], true) ? $type : 'text'];
            if ($entry['type'] === 'text') {
                $entry['body'] = mb_substr((string) ($block['body'] ?? ''), 0, 20000);
            } elseif ($entry['type'] === 'embed') {
                $entry['url'] = self::cleanEmbedUrl((string) ($block['url'] ?? ''));
                $entry['title'] = mb_substr((string) ($block['title'] ?? ''), 0, 200);
                if ($entry['url'] === '') {
                    continue;
                }
            } elseif ($entry['type'] === 'canvas') {
                $w = self::cvNum($block['w'] ?? 1600, 400, 4000);
                $h = self::cvNum($block['h'] ?? 1000, 300, 4000);
                $items = self::cleanCanvasItems($block['items'] ?? [], (float) $w, (float) $h);
                $ink = (int) ($block['ink'] ?? 0);
                if ($ink > 0) {
                    $entry['ink'] = $ink;
                    $entry['inkName'] = mb_substr((string) ($block['inkName'] ?? ''), 0, 160);
                }
                $entry['w'] = $w;
                $entry['h'] = $h;
                $entry['items'] = $items;
            } else {
                $entry['media'] = (int) ($block['media'] ?? 0);
                $entry['name'] = mb_substr((string) ($block['name'] ?? ''), 0, 160);
                if ($entry['media'] <= 0) {
                    continue;
                }
                if (isset($block['alt'])) {
                    $entry['alt'] = mb_substr((string) $block['alt'], 0, 200);
                }
            }
            $clean[] = $entry;
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '[]';
        }
        Database::pdo()->prepare('UPDATE ibook_pages SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([mb_substr($title, 0, 160), $json, $pageId]);
    }

    public static function deletePage(int $pageId): void
    {
        Database::pdo()->prepare('DELETE FROM ibook_pages WHERE id = ?')->execute([$pageId]);
    }

    /** Bloecke einer Seite als Array. @return list<array<string, mixed>> */
    public static function pageBlocks(array $page): array
    {
        $decoded = json_decode((string) ($page['content'] ?? '[]'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Einbettungs-URLs: Nur YouTube (nocookie) und LearningApps erlauben,
     * sonst nichts einbetten (Schulnetz-Sicherheit, keine offene iframe-Bastellei).
     */
    public static function cleanEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        if ($host === 'www.youtube-nocookie.com' || $host === 'youtube-nocookie.com') {
            return $url;
        }
        if ($host === 'www.youtube.com' || $host === 'youtube.com' || $host === 'm.youtube.com') {
            $query = [];
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
            $video = preg_match('/^[A-Za-z0-9_-]{6,20}$/', (string) ($query['v'] ?? '')) ? (string) $query['v'] : '';
            if ($video !== '') {
                return 'https://www.youtube-nocookie.com/embed/' . $video;
            }
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            if (str_starts_with($path, '/embed/')) {
                return 'https://www.youtube-nocookie.com' . $path;
            }
            return '';
        }
        if ($host === 'learningapps.org' || $host === 'www.learningapps.org') {
            return $url;
        }
        return '';
    }

    // ---------------- Medien (Bilder, Audio, Video, Zeichnungen) ----------------

    public static function addMedia(int $bookId, string $kind, string $name, string $mime, string $data): array
    {
        $kind = in_array($kind, ['image', 'audio', 'video'], true) ? $kind : 'image';
        $name = self::uniqueMediaName($bookId, $name);
        Database::pdo()->prepare('INSERT INTO ibook_media (book_id, kind, name, mime, size, data) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$bookId, $kind, $name, $mime, strlen($data), $data]);
        return ['id' => (int) Database::pdo()->lastInsertId(), 'book_id' => $bookId, 'kind' => $kind, 'name' => $name, 'mime' => $mime, 'size' => strlen($data)];
    }

    /** @return array<string, mixed>|null */
    public static function findMedia(int $mediaId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, book_id, kind, name, mime, size, created_at FROM ibook_media WHERE id = ? LIMIT 1');
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function mediaData(int $mediaId): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT data FROM ibook_media WHERE id = ? LIMIT 1');
        $stmt->execute([$mediaId]);
        $data = $stmt->fetchColumn();
        return $data === false ? null : (string) $data;
    }

    /** @return list<array<string, mixed>> */
    public static function mediaOfBook(int $bookId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, book_id, kind, name, mime, size, created_at FROM ibook_media WHERE book_id = ? ORDER BY id DESC');
        $stmt->execute([$bookId]);
        return $stmt->fetchAll();
    }

    private static function uniqueMediaName(int $bookId, string $wanted): string
    {
        $base = preg_replace('/[^A-Za-z0-9._ -]/', '_', mb_substr(trim($wanted) !== '' ? $wanted : 'medium', 0, 60)) ?? 'medium';
        $name = $base;
        $i = 1;
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM ibook_media WHERE book_id = ? AND name = ?');
        while (true) {
            $stmt->execute([$bookId, $name]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $name;
            }
            $i++;
            $dot = strrpos($base, '.');
            $name = $dot === false ? $base . '-' . $i : substr($base, 0, $dot) . '-' . $i . substr($base, $dot);
        }
    }

    // ---------------- Verteilen und Präsentation ----------------

    /** Alle Medien-IDs eines Block-Arrays: normale Medienblöcke plus Bilder und Freihand-Ebene der Freien Flaeche (#63). @param list<array<string, mixed>> $blocks @return list<int> */
    public static function mediaIdsOfBlocks(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            $mediaId = (int) ($block['media'] ?? 0);
            if ($mediaId > 0) {
                $ids[] = $mediaId;
            }
            if ($type === 'canvas') {
                $ink = (int) ($block['ink'] ?? 0);
                if ($ink > 0) {
                    $ids[] = $ink;
                }
                foreach (($block['items'] ?? []) as $item) {
                    if (is_array($item) && (string) ($item['kind'] ?? '') === 'image') {
                        $imgId = (int) ($item['media'] ?? 0);
                        if ($imgId > 0) {
                            $ids[] = $imgId;
                        }
                    }
                }
            }
        }
        return $ids;
    }

    /**
     * Seite in einen anderen Abschnitt kopieren (Verteilen): Bloecke werden
     * mitgenommen, gebundene Medien (Bild/Audio/Video/Zeichnung und alles in
     * der Freien Flaeche, inklusive Freihand-Ebene) werden in das Ziel-Buch
     * dupliziert und die Block-Referenzen auf die neuen Medien-IDs umgebogen,
     * damit das Ziel unabhaengig ist.
     */
    public static function copyPage(int $pageId, int $targetSectionId, string $titleSuffix = ''): int
    {
        $page = self::findPage($pageId);
        $target = self::findSection($targetSectionId);
        if ($page === null || $target === null) {
            return 0;
        }
        $sourceBook = self::sectionBookId((int) $page['section_id']);
        $targetBook = (int) $target['book_id'];
        $blocks = self::pageBlocks($page);
        $copies = [];
        if ($targetBook !== $sourceBook) {
            foreach (self::mediaIdsOfBlocks($blocks) as $mediaId) {
                $media = self::findMedia($mediaId);
                if ($media === null || (int) $media['book_id'] !== $sourceBook) {
                    continue;
                }
                $data = self::mediaData($mediaId);
                if ($data === null) {
                    continue;
                }
                $copy = self::addMedia($targetBook, (string) $media['kind'], (string) $media['name'], (string) $media['mime'], $data);
                $copies[$mediaId] = $copy['id'];
            }
        }
        foreach ($blocks as $i => $block) {
            $mediaId = (int) ($block['media'] ?? 0);
            if ($mediaId > 0 && isset($copies[$mediaId])) {
                $blocks[$i]['media'] = $copies[$mediaId];
            }
            if (is_array($block) && (string) ($block['type'] ?? '') === 'canvas') {
                $ink = (int) ($block['ink'] ?? 0);
                if ($ink > 0 && isset($copies[$ink])) {
                    $blocks[$i]['ink'] = $copies[$ink];
                }
                foreach (($block['items'] ?? []) as $j => $item) {
                    if (is_array($item) && (string) ($item['kind'] ?? '') === 'image') {
                        $imgId = (int) ($item['media'] ?? 0);
                        if ($imgId > 0 && isset($copies[$imgId])) {
                            $blocks[$i]['items'][$j]['media'] = $copies[$imgId];
                        }
                    }
                }
            }
        }
        $title = (string) $page['title'] . ($titleSuffix !== '' ? ' ' . $titleSuffix : '');
        $newId = self::createPage($targetSectionId, $title);
        self::savePage($newId, $title, $blocks);
        return $newId;
    }

    private static function sectionBookId(int $sectionId): int
    {
        $section = self::findSection($sectionId);
        return $section === null ? 0 : (int) $section['book_id'];
    }

    public static function deleteBook(int $bookId): void
    {
        Database::pdo()->prepare('DELETE FROM ibooks WHERE id = ?')->execute([$bookId]);
    }

    public static function setShared(int $bookId, bool $shared): void
    {
        Database::pdo()->prepare('UPDATE ibooks SET shared = ? WHERE id = ?')->execute([$shared ? 1 : 0, $bookId]);
    }
}
