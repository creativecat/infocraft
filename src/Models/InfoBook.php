<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Notizbücher, Seiten, Flächen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
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
        /* Neue Seiten sind direkt eine Freie Flaeche (#64): Eine Seite, alle
         * Elemente (Text, Stift-Streiche, Bilder, Medien) frei darauf platziert. */
        Database::pdo()->prepare('INSERT INTO ibook_pages (section_id, title, content, position) VALUES (?, ?, ?, ?)')
            ->execute([$sectionId, $title, '[{"type":"canvas","w":1600,"h":1000,"items":[]}]', $pos]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** #101: Reihenfolge der Seiten eines Abschnitts setzen. Erwartet die
     * vollstaendige, geordnete Liste der Seiten-IDs dieses Abschnitts;
     * Seiten anderer Abschnitte bleiben unberuehrt (WHERE section_id). */
    public static function reorderSectionPages(int $sectionId, array $orderedIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE ibook_pages SET position = ? WHERE id = ? AND section_id = ?');
            foreach (array_values($orderedIds) as $pos => $pageId) {
                $stmt->execute([$pos, (int) $pageId, $sectionId]);
            }
            $pdo->commit();
        } catch (\Throwable $eReo) {
            $pdo->rollBack();
            throw $eReo;
        }
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

    /** Elemente der Freien Flaeche (#63/#64) bereinigen: Positionen begrenzen, nur gueltige Elemente.
     * $bookPageIds (#73): Seiten-IDs desselben Buchs, auf die verlinkt werden darf. @param list<mixed> $items */
    private static function cleanCanvasItems(mixed $items, float $w, float $h, array $bookPageIds = []): array
    {
        if (!is_array($items)) {
            return [];
        }
        $clean = [];
        $inkPoints = 0;
        foreach ($items as $item) {
            if (!is_array($item) || count($clean) >= 200) {
                continue;
            }
            $kind = (string) ($item['kind'] ?? '');
            $left = self::cvNum($item['x'] ?? 0, 0, $w);
            $top = self::cvNum($item['y'] ?? 0, 0, $h);
            /* Gruppen-ID (#68): Lasso-Auswahl gruppierbar, Mitglieder bewegen
             * sich gemeinsam. 0 = ohne Gruppe. */
            $grp = (int) ($item['g'] ?? 0) > 0 ? (int) ($item['g'] ?? 0) : 0;
            /* Seiten-Link (#73): Ziel muss eine Seite desselben Buchs sein. */
            $link = (int) ($item['link'] ?? 0);
            if ($link > 0 && !isset($bookPageIds[$link])) {
                $link = 0;
            }
            /* Praesentations-Schritt (#191): Elemente erscheinen in der
             * Praesentation Schritt fuer Schritt, gleiche Nummern zusammen;
             * 0 oder fehlend = sofort mit der Folie. anim ist die Art der
             * Einblend-Animation aus einer festen Liste. */
            $step = (int) ($item['step'] ?? 0);
            if ($step < 0 || $step > 99) {
                $step = $step < 0 ? 0 : 99;
            }
            $anim = in_array((string) ($item['anim'] ?? ''), ['fade', 'up', 'left', 'zoom'], true) ? (string) ($item['anim'] ?? '') : '';
            if ($kind === 'text') {
                $it = [
                    'kind' => 'text',
                    'body' => mb_substr((string) ($item['body'] ?? ''), 0, 20000),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 320, 60, $w),
                    'bg' => ((string) ($item['bg'] ?? '') === 'none') ? 'none' : self::cvColor((string) ($item['bg'] ?? ''), ''),
                    'align' => in_array((string) ($item['align'] ?? 'left'), ['left', 'center', 'right'], true) ? (string) ($item['align'] ?? 'left') : 'left',
                ];
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'image') {
                $media = (int) ($item['media'] ?? 0);
                if ($media <= 0) {
                    continue;
                }
                $it = [
                    'kind' => 'image',
                    'media' => $media,
                    'name' => mb_substr((string) ($item['name'] ?? ''), 0, 160),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 160, 16, $w),
                    'h' => self::cvNum($item['h'] ?? 120, 16, $h),
                ];
                if (isset($item['alt'])) {
                    $it['alt'] = mb_substr((string) $item['alt'], 0, 200);
                }
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'ink') {
                /* Stift-Strich als echtes Objekt (#64): Punkte relativ zum
                 * Ursprung des Strichs, flaches [x0,y0,x1,y1,...], Farbe aus
                 * Allowlist, Begrenzung der Punktzahl je Strich und je Seite. */
                $points = [];
                if (is_array($item['p'] ?? null)) {
                    $vals = array_values($item['p']);
                    for ($i = 0; $i + 1 < count($vals); $i += 2) {
                        if (count($points) >= 4000 || $inkPoints >= 30000) {
                            break;
                        }
                        $points[] = self::cvNum($vals[$i], -4000, 4000);
                        $points[] = self::cvNum($vals[$i + 1], -4000, 4000);
                        $inkPoints++;
                    }
                }
                if (count($points) >= 2) {
                    $it = [
                        'kind' => 'ink',
                        'c' => self::cvColor((string) ($item['c'] ?? ''), '#1f2937'),
                        'sw' => self::cvNum($item['sw'] ?? 4, 1, 32),
                        'p' => $points,
                        'x' => $left,
                        'y' => $top,
                        'w' => self::cvNum($item['w'] ?? 40, 4, $w),
                        'h' => self::cvNum($item['h'] ?? 40, 4, $h),
                    ];
                    if ($grp > 0) {
                        $it['g'] = $grp;
                    }
                    if ($link > 0) { $it['link'] = $link; }
                    if ($step > 0) { $it['step'] = $step; }
                    if ($anim !== '') { $it['anim'] = $anim; }
                    $clean[] = $it;
                }
            } elseif ($kind === 'embed') {
                $url = self::cleanEmbedUrl((string) ($item['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $it = [
                    'kind' => 'embed',
                    'url' => $url,
                    'title' => mb_substr((string) ($item['title'] ?? ''), 0, 200),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 480, 120, $w),
                ];
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'code') {
                /* Code-Fenster mit Konsole (#75/#81): Quelltext wird im Editor und
                 * Lesemodus clientseitig ausgefuehrt, nie auf dem Server. Die
                 * Sprache (Standard Java) entscheidet ueber VM/Pyodide/eval. */
                $it = [
                    'kind' => 'code',
                    'code' => mb_substr((string) ($item['code'] ?? ''), 0, 20000),
                    'lang' => in_array((string) ($item['lang'] ?? 'java'), ['java', 'javascript', 'python'], true) ? (string) ($item['lang'] ?? 'java') : 'java',
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 520, 120, $w),
                ];
                /* Importierte Klassen (#87): Karte mit Name, Projekt und
                 * Quelltext. Die Java-VM bekommt sie im Browser als eigene
                 * Dateien, der Server speichert nur den Text (wie code). */
                $clsClean = [];
                if (is_array($item['cls'] ?? null)) {
                    foreach ($item['cls'] as $c) {
                        if (!is_array($c) || (string) ($c['name'] ?? '') === '' || count($clsClean) >= 20) {
                            continue;
                        }
                        $clsName = preg_replace('/[^A-Za-z0-9_.\-]/', '', basename((string) $c['name']));
                        if ($clsName === '' || strlen($clsName) > 100) {
                            continue;
                        }
                        $clsClean[] = [
                            'name' => $clsName,
                            'project' => mb_substr((string) ($c['project'] ?? ''), 0, 120),
                            'code' => mb_substr((string) ($c['code'] ?? ''), 0, 20000),
                        ];
                    }
                }
                if ($clsClean) {
                    $it['cls'] = $clsClean;
                }
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'diagram') {
                /* Diagramm-Element (#77/#79): Mermaid-Code, gerendert wird im
                 * Client (Mermaid.js), der Server speichert nur den Text.
                 * dmode 'split' zeigt im Lesemodus Code + Diagramm nebeneinander. */
                $it = [
                    'kind' => 'diagram',
                    'src' => mb_substr((string) ($item['src'] ?? ''), 0, 8000),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 480, 160, $w),
                ];
                if ((string) ($item['dmode'] ?? '') === 'split') {
                    $it['dmode'] = 'split';
                }
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'db') {
                /* Datenbank-Element (#76): Schema/Seed als SQL-Text, Abfrage
                 * dazu. Ausgefuehrt wird alles clientseitig (IC_DB_RUN). */
                $it = [
                    'kind' => 'db',
                    'dbseed' => mb_substr((string) ($item['dbseed'] ?? ''), 0, 20000),
                    'dbq' => mb_substr((string) ($item['dbq'] ?? ''), 0, 2000),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 420, 160, $w),
                ];
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            } elseif ($kind === 'audio' || $kind === 'video') {
                $media = (int) ($item['media'] ?? 0);
                if ($media <= 0) {
                    continue;
                }
                $it = [
                    'kind' => $kind,
                    'media' => $media,
                    'name' => mb_substr((string) ($item['name'] ?? ''), 0, 160),
                    'x' => $left,
                    'y' => $top,
                    'w' => self::cvNum($item['w'] ?? 360, 80, $w),
                ];
                if ($kind === 'video') {
                    $it['h'] = self::cvNum($item['h'] ?? 270, 60, $h);
                }
                if ($grp > 0) {
                    $it['g'] = $grp;
                }
                if ($link > 0) { $it['link'] = $link; }
                if ($step > 0) { $it['step'] = $step; }
                if ($anim !== '') { $it['anim'] = $anim; }
                $clean[] = $it;
            }
        }
        return $clean;
    }

    /** Freie-Flaeche-Farben: Nur bekannte Palette, sonst Rueckfallfarbe. */
    private static function cvColor(string $wanted, string $fallback): string
    {
        $palette = ['#1f2937', '#ef4444', '#3b82f6', '#059669', '#f59e0b', '#fffbe6', '#ffffff', '#eff6ff', '#f0fdf4', '#fef2f2'];
        return in_array($wanted, $palette, true) ? $wanted : $fallback;
    }

    /** Seite speichern: Titel und Bloecke (JSON wird serverseitig bereinigt). */
    public static function savePage(int $pageId, string $title, array $blocks, bool $sharedContext = false): void
    {
        /* Seiten-Link-Ziele (#73): erlaubt sind alle Seiten desselben Buchs.
         * #203: Im Bereich Zusammenarbeit duerfen Links nur auf Seiten zeigen,
         * die der ganzen Klasse mindestens zum Lesen zur Verfuegung stehen,
         * private "Mein Bereich"-Seiten bleiben als Ziel draussen. */
        $bookPageIds = [];
        $pageRow = self::findPage($pageId);
        $pageSection = $pageRow !== null ? self::findSection((int) $pageRow['section_id']) : null;
        if ($pageSection !== null) {
            foreach (self::sections((int) $pageSection['book_id']) as $linkSection) {
                if ($sharedContext && !in_array((string) $linkSection['area'], ['lernbereich', 'gemeinsam'], true)) {
                    continue;
                }
                foreach (self::pages((int) $linkSection['id']) as $linkPage) {
                    $bookPageIds[(int) $linkPage['id']] = true;
                }
            }
        }
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
                $items = self::cleanCanvasItems($block['items'] ?? [], (float) $w, (float) $h, $bookPageIds);
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
     * Einbettungs-URLs: Nur YouTube (nocookie), LearningApps und H5P erlauben,
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
        if ($host === 'h5p.org' || $host === 'www.h5p.org' || $host === 'h5p.com' || $host === 'www.h5p.com') {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            if (preg_match('#^/(node|content)/(\d+)/?$#', $path, $m)) {
                return 'https://' . $host . '/' . $m[1] . '/' . $m[2] . '/embed';
            }
            if (str_ends_with($path, '/embed')) {
                return $url;
            }
            return '';
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
                    if (is_array($item)) {
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
    public static function copyPage(int $pageId, int $targetSectionId, string $titleSuffix = '', bool $sharedContext = false): int
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
            /* #202/#203: Seiten-Links sind buchintern. Beim Kopieren in ein
             * anderes Buch wuerde eine uebernommene Link-ID auf eine ganz
             * andere Seite des Zielbuchs zeigen; die Links werden deshalb
             * beim Buchuebergang entfernt und muessen im Ziel neu gesetzt
             * werden. */
            foreach ($blocks as $i => $block) {
                if (is_array($block) && (string) ($block['type'] ?? '') === 'canvas') {
                    foreach (($block['items'] ?? []) as $j => $item) {
                        if (is_array($item) && (int) ($item['link'] ?? 0) > 0) {
                            $blocks[$i]['items'][$j]['link'] = 0;
                        }
                    }
                }
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
        self::savePage($newId, $title, $blocks, $sharedContext);
        return $newId;
    }

    /**
     * #114: Fertiges Buchpaket (JSON) importieren. Erwartet
     * {"sections":[{"title":"...","area":"lernbereich","pages":[{"title":"...","blocks":[...]}]}]}.
     * Abschnitte und Seiten werden ueber die normalen create/save-Methoden
     * angelegt, jede Seite laeuft durch die savePage-Bereinigung (Canvas-Limits,
     * Farb-Allowlist, Embed-Freigabe). Deckel: 20 Abschnitte, 100 Seiten je
     * Abschnitt. Gebundene Medien (Bild/Audio/Video) koennen nicht mitkommen,
     * weil ihre IDs buchlokal sind; Text, DB, Code, Diagramm und Einbettungen
     * funktionieren komplett.
     * @return array{sections: int, pages: int}
     */
    public static function importContent(int $bookId, array $data): array
    {
        $stats = ['sections' => 0, 'pages' => 0, 'first_section_id' => 0];
        $sections = is_array($data['sections'] ?? null) ? array_values($data['sections']) : [];
        foreach (array_slice($sections, 0, 20) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $title = trim((string) ($section['title'] ?? ''));
            if ($title === '') {
                $title = 'Importierter Abschnitt';
            }
            $area = (string) ($section['area'] ?? 'lernbereich');
            $area = in_array($area, ['lernbereich', 'gemeinsam'], true) ? $area : 'lernbereich';
            $sectionId = self::createSection($bookId, $area, null, mb_substr($title, 0, 160));
            $stats['sections']++;
            if ($stats['first_section_id'] === 0) {
                /* #185: Erster importierter Abschnitt, damit der Import
                 * direkt in dessen Abschnittsansicht zurueckspringt. */
                $stats['first_section_id'] = $sectionId;
            }
            $pages = is_array($section['pages'] ?? null) ? array_values($section['pages']) : [];
            foreach (array_slice($pages, 0, 100) as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $pageTitle = trim((string) ($page['title'] ?? ''));
                if ($pageTitle === '') {
                    $pageTitle = 'Importierte Seite';
                }
                $pageTitle = mb_substr($pageTitle, 0, 160);
                $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
                $pageId = self::createPage($sectionId, $pageTitle);
                self::savePage($pageId, $pageTitle, $blocks);
                $stats['pages']++;
            }
        }
        return $stats;
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
