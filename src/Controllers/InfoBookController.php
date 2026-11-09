<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\InfoBook;
use Infocraft\Models\Presentation;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

/**
 * InfoBook (#62): Notizbuecher im OneNote-Stil.
 *
 * Persoenliche Buecher der Lehrkraft (privat oder an alle Lehrkraefte geteilt)
 * und genau ein Klassen-InfoBook pro Klasse mit drei Bereichen:
 *  - Lernbereich: von der Lehrkraft verteilt, Schuelerinnen und Schueler lesen nur
 *  - Zusammenarbeit: alle der Klasse schreiben mit
 *  - Mein Bereich: privat je Person, nur sie/er und die Lehrkraft sehen ihn
 *
 * Seiten bestehen aus Bloecken: Text (Markdown + Folien-Bausteine), Bild, Audio,
 * Video (hochgeladen, wird direkt abgespielt), Einbettung (YouTube/LearningApps)
 * und Freihand-Zeichnung (Apple Pencil). Ganze Buecher lassen sich als
 * Präsentation anzeigen oder als echte Präsentation uebernehmen.
 */
final class InfoBookController
{
    // ---------------- Uebersicht ----------------

    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            if ($user->classId === null) {
                flash('error', 'Für dein Konto ist keine Klasse hinterlegt.');
                redirect('/');
            }
            $book = InfoBook::ensureClassBook($user->classId, $user->id);
            redirect('/infobuecher/' . $book->id);
            return;
        }
        $personal = InfoBook::personalBooks($user->id);
        $classes = SchoolClass::forTeacher($user->id, $user->role === 'admin');
        $classBooks = [];
        foreach ($classes as $class) {
            $classBooks[] = [
                'class' => $class,
                'book' => InfoBook::classBook($class->id),
            ];
        }
        render('ibook_overview', [
            'title' => 'InfoBook',
            'active' => 'module-infobuecher',
            'personal' => $personal,
            'classBooks' => $classBooks,
            'user' => $user,
        ]);
    }

    /** Persoenliches Buch anlegen (nur Lehrkraefte). */
    public static function create(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Eigene InfoBooks können nur Lehrkräfte anlegen.');
            redirect('/infobuecher');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/infobuecher');
        }
        $pdo = \Infocraft\Database::pdo();
        $pdo->prepare('INSERT INTO ibooks (owner_id, class_id, title, shared) VALUES (?, NULL, ?, 0)')
            ->execute([$user->id, mb_substr($title, 0, 160)]);
        $book = InfoBook::find((int) $pdo->lastInsertId());
        flash('success', 'InfoBook angelegt. Jetzt Abschnitte und Seiten hinzufügen.');
        redirect('/infobuecher/' . ($book !== null ? $book->id : 0));
    }

    /** Klassen-InfoBook oeffnen: legt es beim ersten Aufruf automatisch an. */
    public static function openClass(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $classId = (int) ($params['cid'] ?? 0);
        $class = SchoolClass::find($classId);
        if ($class === null || ($user->role !== 'admin' && !SchoolClass::hasTeacher($classId, $user->id))) {
            flash('error', 'Diese Klasse gehört nicht zu deinem Konto.');
            redirect('/infobuecher');
        }
        $book = InfoBook::ensureClassBook($classId, $user->id);
        redirect('/infobuecher/' . $book->id);
    }

    /** Persoenliches Buch an alle Lehrkraefte teilen bzw. Teilen zuruecknehmen. */
    public static function share(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || $book->classId !== null) {
            flash('error', 'Dieses InfoBook gibt es nicht.');
            redirect('/infobuecher');
        }
        if ($book->ownerId !== $user->id && $user->role !== 'admin') {
            flash('error', 'Nur die Besitzerin oder der Besitzer kann dieses InfoBook teilen.');
            redirect('/infobuecher/' . $book->id);
        }
        $shared = ($_POST['shared'] ?? '') === '1';
        InfoBook::setShared($book->id, $shared);
        flash('success', $shared ? 'InfoBook ist jetzt für alle Lehrkräfte sichtbar.' : 'InfoBook ist wieder privat.');
        redirect('/infobuecher/' . $book->id);
    }

    public static function deleteBook(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null) {
            flash('error', 'Dieses InfoBook gibt es nicht.');
            redirect('/infobuecher');
        }
        if ($book->classId !== null) {
            flash('error', 'Das Klassen-InfoBook gehört zur Klasse und kann nicht gelöscht werden.');
            redirect('/infobuecher/' . $book->id);
        }
        if ($book->ownerId !== $user->id && $user->role !== 'admin') {
            flash('error', 'Nur die Besitzerin oder der Besitzer kann dieses InfoBook löschen.');
            redirect('/infobuecher');
        }
        InfoBook::deleteBook($book->id);
        flash('success', 'InfoBook gelöscht.');
        redirect('/infobuecher');
    }

    // ---------------- Buch anzeigen ----------------

    public static function show(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses InfoBook gibt es nicht.']);
            return;
        }
        if (!self::canRead($book, $user)) {
            flash('error', 'Für dieses InfoBook hast du keinen Zugriff.');
            redirect('/infobuecher');
        }
        $sections = InfoBook::sections($book->id);
        if ($user->role === 'student') {
            // Privater Schuelerbereich sicherstellen (existiert danach dauerhaft).
            if ($book->classId !== null && $book->classId === $user->classId) {
                InfoBook::ensureOwnSection($book->id, $user->id);
            }
            $sections = array_values(array_filter($sections, static function (array $s) use ($user): bool {
                return self::visibleSectionForStudent($s, $user);
            }));
        }
        $activeId = (int) ($_GET['abschnitt'] ?? 0);
        $active = null;
        foreach ($sections as $section) {
            if ((int) $section['id'] === $activeId) {
                $active = $section;
            }
        }
        if ($active === null && count($sections) > 0) {
            $active = $sections[0];
        }
        $pages = $active !== null ? InfoBook::pages((int) $active['id']) : [];
        $myClasses = Auth::isTeacher() ? SchoolClass::forTeacher($user->id, $user->role === 'admin') : [];
        render('ibook_book', [
            'title' => $book->title,
            'active' => 'module-infobuecher',
            'book' => $book,
            'sections' => $sections,
            'activeSection' => $active,
            'pages' => $pages,
            'myClasses' => $myClasses,
            'canManage' => self::canManage($book, $user),
            'canWriteSection' => $active !== null && self::canWriteSection($active, $user),
            'user' => $user,
        ]);
    }

    /** Abschnitt anlegen: Lehrkraefte in allen Bereichen, Schueler nur 'gemeinsam'. */
    public static function createSection(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || !self::canRead($book, $user)) {
            flash('error', 'Für dieses InfoBook hast du keinen Zugriff.');
            redirect('/infobuecher');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/infobuecher/' . $book->id);
        }
        $area = (string) ($_POST['area'] ?? 'lernbereich');
        if ($user->role === 'student') {
            if ($book->classId === null || $book->classId !== $user->classId) {
                flash('error', 'Hier kannst du nichts anlegen.');
                redirect('/infobuecher');
            }
            $area = 'gemeinsam';
        } elseif ($book->classId !== null && $area === 'eigen') {
            $area = 'lernbereich';
        }
        $sectionId = InfoBook::createSection($book->id, $area, null, $title);
        flash('success', 'Abschnitt angelegt.');
        redirect('/infobuecher/' . $book->id . '?abschnitt=' . $sectionId);
    }

    public static function deleteSection(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $section = InfoBook::findSection((int) ($params['sid'] ?? 0));
        if ($book === null || $section === null || (int) $section['book_id'] !== $book->id) {
            flash('error', 'Dieser Abschnitt gibt es nicht.');
            redirect('/infobuecher');
        }
        if (!self::canManage($book, $user)) {
            flash('error', 'Nur die Lehrkraft kann Abschnitte löschen.');
            redirect('/infobuecher/' . $book->id);
        }
        if ((string) $section['area'] === 'eigen') {
            flash('error', 'Private Bereiche von Schülerinnen und Schülern werden nicht gelöscht.');
            redirect('/infobuecher/' . $book->id);
        }
        InfoBook::deleteSection((int) $section['id']);
        flash('success', 'Abschnitt gelöscht.');
        redirect('/infobuecher/' . $book->id);
    }

    // ---------------- Seiten ----------------

    public static function createPage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $section = InfoBook::findSection((int) ($_POST['section_id'] ?? 0));
        if ($book === null || $section === null || (int) $section['book_id'] !== $book->id || !self::canWriteSection($section, $user)) {
            flash('error', 'Hier kannst du keine Seite anlegen.');
            redirect('/infobuecher');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = 'Neue Seite';
        }
        $pageId = InfoBook::createPage((int) $section['id'], $title);
        flash('success', 'Seite angelegt. Jetzt Inhalte einfügen: Text, Bild, Audio, Video, Einbettung oder Zeichnung.');
        redirect('/infobuecher/' . $book->id . '/seite/' . $pageId);
    }

    public static function page(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $page = InfoBook::findPage((int) ($params['pid'] ?? 0));
        if ($book === null || $page === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Seite gibt es nicht.']);
            return;
        }
        $section = InfoBook::findSection((int) $page['section_id']);
        if ($section === null || (int) $section['book_id'] !== $book->id || !self::canRead($book, $user)) {
            flash('error', 'Für diese Seite hast du keinen Zugriff.');
            redirect('/infobuecher');
        }
        $canWrite = self::canWriteSection($section, $user);
        $blocks = InfoBook::pageBlocks($page);
        foreach ($blocks as $i => $block) {
            $blocks[$i]['html'] = self::renderBlock($block);
        }
        $media = self::canManage($book, $user) || $canWrite ? InfoBook::mediaOfBook($book->id) : [];
        render('ibook_page', [
            'title' => (string) $page['title'],
            'active' => 'module-infobuecher',
            'book' => $book,
            'section' => $section,
            'page' => $page,
            'blocks' => $blocks,
            'media' => $media,
            'canWrite' => $canWrite,
            'isTeacher' => Auth::isTeacher(),
            'user' => $user,
        ]);
    }

    /** Seite speichern: Titel + Bloecke (JSON aus dem Editor). */
    public static function savePage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $page = InfoBook::findPage((int) ($params['pid'] ?? 0));
        if ($book === null || $page === null) {
            flash('error', 'Diese Seite gibt es nicht.');
            redirect('/infobuecher');
        }
        $section = InfoBook::findSection((int) $page['section_id']);
        if ($section === null || (int) $section['book_id'] !== $book->id || !self::canWriteSection($section, $user)) {
            flash('error', 'Diese Seite kannst du nicht bearbeiten.');
            redirect('/infobuecher/' . $book->id);
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = 'Unbenannte Seite';
        }
        $blocks = json_decode((string) ($_POST['blocks'] ?? '[]'), true);
        if (!is_array($blocks)) {
            $blocks = [];
        }
        InfoBook::savePage((int) $page['id'], $title, $blocks);
        flash('success', 'Seite gespeichert.');
        redirect('/infobuecher/' . $book->id . '/seite/' . (int) $page['id']);
    }

    public static function deletePage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $page = InfoBook::findPage((int) ($params['pid'] ?? 0));
        if ($book === null || $page === null) {
            flash('error', 'Diese Seite gibt es nicht.');
            redirect('/infobuecher');
        }
        $section = InfoBook::findSection((int) $page['section_id']);
        if ($section === null || (int) $section['book_id'] !== $book->id || !self::canWriteSection($section, $user)) {
            flash('error', 'Diese Seite kannst du nicht löschen.');
            redirect('/infobuecher/' . $book->id);
        }
        InfoBook::deletePage((int) $page['id']);
        flash('success', 'Seite gelöscht.');
        redirect('/infobuecher/' . $book->id . '?abschnitt=' . (int) $section['id']);
    }

    // ---------------- Medien ----------------

    /** Medium hochladen (Bild/Audio/Video), Antwort als JSON fuer den Editor. */
    public static function uploadMedia(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || !self::canRead($book, $user) || !self::bookWritableBy($book, $user)) {
            self::json(['ok' => false, 'error' => 'Kein Zugriff.']);
            return;
        }
        $kind = (string) ($_POST['kind'] ?? 'image');
        if (!in_array($kind, ['image', 'audio', 'video'], true)) {
            self::json(['ok' => false, 'error' => 'Unbekannte Medienart.']);
            return;
        }
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            self::json(['ok' => false, 'error' => 'Bitte eine Datei auswählen.']);
            return;
        }
        $size = (int) ($file['size'] ?? 0);
        $limit = $kind === 'image' ? 2 * 1024 * 1024 : 25 * 1024 * 1024;
        if ($size <= 0 || $size > $limit) {
            self::json(['ok' => false, 'error' => 'Die Datei ist zu groß (max. ' . (int) ($limit / 1024 / 1024) . ' MB).']);
            return;
        }
        $allowed = [
            'image' => ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            'audio' => ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/webm' => 'weba'],
            'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/ogg' => 'ogv'],
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!isset($allowed[$kind][$mime])) {
            self::json(['ok' => false, 'error' => 'Format nicht unterstützt (' . $mime . ').']);
            return;
        }
        $data = (string) file_get_contents((string) $file['tmp_name']);
        $media = InfoBook::addMedia($book->id, $kind, (string) ($file['name'] ?? 'medium'), $mime, $data);
        self::json([
            'ok' => true,
            'id' => $media['id'],
            'name' => $media['name'],
            'kind' => $kind,
            'url' => url('/infobuecher/medium/' . $media['id']),
        ]);
    }

    /** Freihandzeichnung (Apple Pencil) als PNG speichern, Antwort als JSON. */
    public static function saveDrawing(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || !self::canRead($book, $user) || !self::bookWritableBy($book, $user)) {
            self::json(['ok' => false, 'error' => 'Kein Zugriff.']);
            return;
        }
        $dataUrl = (string) ($_POST['data'] ?? '');
        $name = (string) ($_POST['name'] ?? 'zeichnung');
        if ($name === '') {
            $name = 'zeichnung';
        }
        if (preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $m) !== 1) {
            self::json(['ok' => false, 'error' => 'Die Zeichnung konnte nicht gelesen werden.']);
            return;
        }
        $data = (string) base64_decode($m[1], true);
        if ($data === '' || strlen($data) > 2 * 1024 * 1024) {
            self::json(['ok' => false, 'error' => 'Die Zeichnung ist zu groß.']);
            return;
        }
        $media = InfoBook::addMedia($book->id, 'image', $name . '.png', 'image/png', $data);
        self::json([
            'ok' => true,
            'id' => $media['id'],
            'name' => $media['name'],
            'kind' => 'image',
            'url' => url('/infobuecher/medium/' . $media['id']),
        ]);
    }

    /** Medium ausliefern (BLOB), nur mit Lesezugriff auf das Buch. */
    public static function serveMedia(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $media = InfoBook::findMedia((int) ($params['mid'] ?? 0));
        if ($media === null) {
            http_response_code(404);
            exit('Nicht gefunden');
        }
        $book = InfoBook::find((int) $media['book_id']);
        if ($book === null || !self::canRead($book, $user)) {
            http_response_code(403);
            exit('Kein Zugriff');
        }
        $data = InfoBook::mediaData((int) $media['id']);
        if ($data === null) {
            http_response_code(404);
            exit('Nicht gefunden');
        }
        header('Content-Type: ' . (string) $media['mime']);
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=3600');
        echo $data;
        exit;
    }

    // ---------------- Verteilen und Präsentation ----------------

    /**
     * Seite aus diesem Buch in ein Klassen-InfoBook verteilen (#62): Ziel ist
     * der Lernbereich (Schüler lesen) oder die Zusammenarbeit (alle schreiben).
     */
    public static function distribute(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte verteilen Seiten.');
            redirect('/infobuecher');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        $page = InfoBook::findPage((int) ($_POST['page_id'] ?? 0));
        $classId = (int) ($_POST['class_id'] ?? 0);
        $bereich = (string) ($_POST['bereich'] ?? 'lernbereich');
        if ($book === null || $page === null || !self::canRead($book, $user)) {
            flash('error', 'Diese Seite gibt es nicht.');
            redirect('/infobuecher');
        }
        $targetBook = InfoBook::ensureClassBook($classId, $user->id);
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $targetSection = $sectionId > 0 ? InfoBook::findSection($sectionId) : null;
        if ($targetSection === null || (int) $targetSection['book_id'] !== $targetBook->id) {
            // Passenden Abschnitt im Zielbereich waehlen (erster vorhanden, sonst neu anlegen).
            foreach (InfoBook::sections($targetBook->id) as $section) {
                if ((string) $section['area'] === $bereich) {
                    $targetSection = $section;
                    break;
                }
            }
            if ($targetSection === null) {
                $sectionId = InfoBook::createSection($targetBook->id, $bereich, null, $bereich === 'lernbereich' ? 'Lernbereich' : 'Zusammenarbeit');
                $targetSection = InfoBook::findSection($sectionId);
            }
        }
        if ($targetSection === null) {
            flash('error', 'Der Zielbereich konnte nicht angelegt werden.');
            redirect('/infobuecher/' . $book->id);
        }
        InfoBook::copyPage((int) $page['id'], (int) $targetSection['id']);
        flash('success', 'Seite verteilt: Sie liegt jetzt im ' . ((string) $targetSection['area'] === 'lernbereich' ? 'Lernbereich' : 'Bereich Zusammenarbeit') . ' der Klasse.');
        redirect('/infobuecher/' . $book->id . '/seite/' . (int) $page['id']);
    }

    /** Seiten als Präsentation ANZEIGEN (live, ohne Kopie): Titel + Bloecke als Folien. */
    public static function present(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || !self::canRead($book, $user)) {
            flash('error', 'Für dieses InfoBook hast du keinen Zugriff.');
            redirect('/infobuecher');
        }
        $sections = InfoBook::sections($book->id);
        if ($user->role === 'student') {
            $sections = array_values(array_filter($sections, static function (array $s) use ($user): bool {
                return self::visibleSectionForStudent($s, $user);
            }));
        }
        $wanted = (int) ($_GET['abschnitt'] ?? 0);
        $slides = [];
        foreach ($sections as $section) {
            if ($wanted > 0 && (int) $section['id'] !== $wanted) {
                continue;
            }
            foreach (InfoBook::pages((int) $section['id']) as $page) {
                $slides[] = [
                    'section' => (string) $section['title'],
                    'title' => (string) $page['title'],
                    'blocks' => InfoBook::pageBlocks($page),
                ];
            }
        }
        foreach ($slides as $i => $slide) {
            $html = '';
            foreach ($slide['blocks'] as $block) {
                $html .= self::renderBlock($block);
            }
            $slides[$i]['html'] = $html;
        }
        render('ibook_present', [
            'title' => $book->title . ' als Präsentation',
            'active' => 'module-infobuecher',
            'book' => $book,
            'slides' => $slides,
            'user' => $user,
        ]);
    }

    /**
     * Buch als ECHTE Präsentation uebernehmen: eine Folie je Seite. Text wird
     * zu Markdown, Medien erscheinen als Hinweiszeile. Danach landschaftlich
     * im Modul Präsentationen bearbeitbar und praesentierbar.
     */
    public static function createPresentation(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können aus einem InfoBook eine Präsentation erstellen.');
            redirect('/infobuecher');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null || !self::canRead($book, $user)) {
            flash('error', 'Für dieses InfoBook hast du keinen Zugriff.');
            redirect('/infobuecher');
        }
        $sections = InfoBook::sections($book->id);
        $wanted = (int) ($_POST['section_id'] ?? 0);
        $level = (int) ($_POST['level'] ?? 6);
        if ($level < 6 || $level > 13) {
            $level = 6;
        }
        $slides = [];
        foreach ($sections as $section) {
            if ($wanted > 0 && (int) $section['id'] !== $wanted) {
                continue;
            }
            foreach (InfoBook::pages((int) $section['id']) as $page) {
                $body = [];
                foreach (InfoBook::pageBlocks($page) as $block) {
                    $type = (string) ($block['type'] ?? 'text');
                    if ($type === 'text') {
                        $body[] = (string) ($block['body'] ?? '');
                    } elseif ($type === 'embed') {
                        $body[] = '🔗 [' . ((string) ($block['title'] ?? 'Einbettung') !== '' ? (string) ($block['title'] ?? 'Einbettung') : 'Einbettung') . '](' . (string) ($block['url'] ?? '') . ')';
                    } else {
                        $label = ['image' => 'Bild', 'audio' => 'Audio', 'video' => 'Video', 'zeichnen' => 'Zeichnung'][$type] ?? 'Medium';
                        $body[] = '📎 ' . $label . ': *' . (string) ($block['name'] ?? 'Medium') . '* (im InfoBook abspielbar)';
                    }
                }
                $slides[] = ['type' => 'text', 'title' => (string) $page['title'], 'body' => implode("\n\n", $body)];
            }
        }
        $presentation = Presentation::create($user->id, $book->title . ' (aus InfoBook)', $level);
        $presentation->setSlides($slides);
        flash('success', 'Präsentation erstellt: eine Folie je InfoBook-Seite. Sie liegt jetzt unter Präsentationen und lässt sich dort weiterbearbeiten.');
        redirect('/praesentationen/' . $presentation->id);
    }

    // ---------------- Rechte ----------------

    /** Lesezugriff: Lehrkraft (eigenes Buch, Klassenbuch, geteiltes Buch), Schueler ueber ihre Klasse. */
    private static function canRead(InfoBook $book, User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'teacher') {
            if ($book->ownerId === $user->id) {
                return true;
            }
            if ($book->classId !== null && SchoolClass::hasTeacher($book->classId, $user->id)) {
                return true;
            }
            if ($book->classId === null && $book->shared) {
                return true;
            }
            return false;
        }
        return $book->classId !== null && $user->classId === $book->classId;
    }

    /** Volle Verwaltung (Abschnitte, Verteilen, Loeschen): Lehrkraefte und Administration. */
    private static function canManage(InfoBook $book, User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role !== 'teacher') {
            return false;
        }
        if ($book->ownerId === $user->id) {
            return true;
        }
        return $book->classId !== null && SchoolClass::hasTeacher($book->classId, $user->id);
    }

    /** Schreibrechte am Buch (fuer Medien-Upload): Verwaltung oder Schueler mit Schreibbereich. */
    private static function bookWritableBy(InfoBook $book, User $user): bool
    {
        if (self::canManage($book, $user)) {
            return true;
        }
        if ($user->role === 'student') {
            foreach (InfoBook::sections($book->id) as $section) {
                if (self::canWriteSection($section, $user)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Schreibrechte an einem Abschnitt: Lehrkraft verwaltet, Schueler schreiben in 'gemeinsam' und im eigenen Bereich. */
    private static function canWriteSection(array $section, User $user): bool
    {
        $book = InfoBook::find((int) $section['book_id']);
        if ($book === null) {
            return false;
        }
        if (self::canManage($book, $user)) {
            return true;
        }
        if ($user->role !== 'student') {
            return false;
        }
        if ($book->classId === null || $book->classId !== $user->classId) {
            return false;
        }
        $area = (string) $section['area'];
        if ($area === 'gemeinsam') {
            return true;
        }
        if ($area === 'eigen') {
            return (int) ($section['owner_user_id'] ?? 0) === $user->id;
        }
        return false;
    }

    /** Sichtbarkeit eines Abschnitts fuer Schuelerinnen und Schueler (Lernbereich, Zusammenarbeit, eigener Bereich). */
    private static function visibleSectionForStudent(array $section, User $user): bool
    {
        $area = (string) $section['area'];
        if ($area === 'lernbereich' || $area === 'gemeinsam') {
            return true;
        }
        if ($area === 'eigen') {
            return (int) ($section['owner_user_id'] ?? 0) === $user->id;
        }
        return false;
    }

    /** Einen Block als HTML rendern (fuer Seitenansicht und Präsentationsmodus). @param array<string, mixed> $block */
    public static function renderBlock(array $block): string
    {
        $type = (string) ($block['type'] ?? 'text');
        if ($type === 'text') {
            return '<div class="iblock-text">' . PresentationController::renderSlideBody((string) ($block['body'] ?? '')) . '</div>';
        }
        if ($type === 'embed') {
            $url = InfoBook::cleanEmbedUrl((string) ($block['url'] ?? ''));
            if ($url === '') {
                return '';
            }
            $title = (string) ($block['title'] ?? '');
            return '<figure class="iblock-embed"><div class="embed-frame"><iframe src="' . e($url) . '" title="' . e($title !== '' ? $title : 'Eingebetteter Inhalt') . '" loading="lazy" allowfullscreen referrerpolicy="no-referrer"></iframe></div>'
                . ($title !== '' ? '<figcaption>' . e($title) . '</figcaption>' : '') . '</figure>';
        }
        $mediaId = (int) ($block['media'] ?? 0);
        $name = (string) ($block['name'] ?? '');
        if ($mediaId <= 0) {
            return '';
        }
        $src = e(url('/infobuecher/medium/' . $mediaId));
        if ($type === 'image' || $type === 'zeichnen') {
            $alt = (string) ($block['alt'] ?? '');
            return '<figure class="iblock-image' . ($type === 'zeichnen' ? ' iblock-zeichnen' : '') . '"><img src="' . $src . '" alt="' . e($alt !== '' ? $alt : ($name !== '' ? $name : 'Bild')) . '">'
                . ($alt !== '' ? '<figcaption>' . e($alt) . '</figcaption>' : '') . '</figure>';
        }
        if ($type === 'audio') {
            return '<figure class="iblock-audio"><audio controls preload="metadata" src="' . $src . '"></audio></figure>';
        }
        if ($type === 'video') {
            return '<figure class="iblock-video"><video controls preload="metadata" src="' . $src . '"></video></figure>';
        }
        return '';
    }

    /** JSON-Antwort (fuer Editor-AJAX: Upload, Zeichnung). */
    private static function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
