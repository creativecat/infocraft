<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: InfoBook: Notizbücher und Seiten
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.22.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\ClassDiagram;
use Infocraft\Models\InfoBook;
use Infocraft\Models\Project;
use Infocraft\Models\ProjectFile;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\User;
use Infocraft\SqlWorkshop;

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
        $sections = self::navSectionsFor($book, $user);
        /* #185: Navigation wie auf der Seitenseite aufbauen: Abschnitte mit
         * ihren Seiten, damit die Abschnittsansicht dieselbe Seitenleiste
         * zeichnet (Partial ibook_nav.php) und Abschnitte mit Seiten ohne
         * Umweg auf ihre erste Seite verlinken. */
        $nav = [];
        foreach ($sections as $navSection) {
            $nav[] = ['section' => $navSection, 'pages' => InfoBook::pages((int) $navSection['id']), 'canWrite' => self::canWriteSection($navSection, $user)];
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
        if ($activeId === 0) {
            /* #185: Ohne gewaehlten Abschnitt startet das Buch direkt auf
             * einer Seite: zuerst die zuletzt besuchte (Cookie je Buch und
             * Browser), sonst die erste Seite des ersten Abschnitts mit
             * Seiten. Die Abschnittsansicht bleibt ueber die Breadcrumb und
             * den abschnitt-Parameter erreichbar. */
            $lastPageId = self::lastVisitedPage($book, $sections);
            if ($lastPageId !== null) {
                redirect('/infobuecher/' . $book->id . '/seite/' . $lastPageId);
            }
            foreach ($nav as $navEntry) {
                if (!empty($navEntry['pages'])) {
                    redirect('/infobuecher/' . $book->id . '/seite/' . (int) $navEntry['pages'][0]['id']);
                }
            }
        }
        /* Seiten des aktiven Abschnitts aus der Navigation (statt doppelt
         * zu laden) und den Renderer fuettern. */
        $pages = [];
        if ($active !== null) {
            foreach ($nav as $navEntry) {
                if ((int) $navEntry['section']['id'] === (int) $active['id']) {
                    $pages = $navEntry['pages'];
                }
            }
        }
        render('ibook_book', [
            'title' => $book->title,
            'active' => 'module-infobuecher',
            'book' => $book,
            'nav' => $nav,
            'activeSectionId' => (int) ($active['id'] ?? 0),
            'activePageId' => 0,
            'sections' => $sections,
            'activeSection' => $active,
            'pages' => $pages,
            'canManage' => self::canManage($book, $user),
            'canWriteSection' => $active !== null && self::canWriteSection($active, $user),
            'canWrite' => $active !== null && self::canWriteSection($active, $user),
            'navNewSection' => self::canManage($book, $user) || self::bookWritableBy($book, $user),
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

    /**
     * #101: Seiten per Drag&Drop sortieren. app.js schickt die neue
     * Reihenfolge als Komma-Liste (page_ids); akzeptiert werden nur Seiten
     * des Buchs, fuer deren Abschnitt Schreibrecht besteht. Sortiert wird
     * je Abschnitt: jede zusammenhaengende Gruppe im Abschnitt bekommt ihre
     * Position neu, Abschnitte mischen sich nicht.
     */
    public static function reorderPages(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $book = InfoBook::find((int) ($params['id'] ?? 0));
        if ($book === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses InfoBook gibt es nicht.']);
            return;
        }
        $ids = [];
        foreach (explode(',', (string) ($_POST['page_ids'] ?? '')) as $rawId) {
            $pid = (int) trim($rawId);
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            flash('error', 'Keine Seiten zum Sortieren übergeben.');
            redirect('/infobuecher/' . $book->id);
        }
        /* Seiten nach Abschnitt gruppieren: Reihenfolge gilt innerhalb
         * eines Abschnitts, jede Seite bleibt wo sie ist. */
        $bySection = [];
        foreach ($ids as $pid) {
            $navPage = InfoBook::findPage($pid);
            if ($navPage === null) {
                continue;
            }
            $navSection = InfoBook::findSection((int) $navPage['section_id']);
            if ($navSection === null || (int) $navSection['book_id'] !== $book->id || !self::canWriteSection($navSection, $user)) {
                continue;
            }
            $bySection[(int) $navSection['id']][] = $pid;
        }
        foreach ($bySection as $sectionId => $orderedIds) {
            InfoBook::reorderSectionPages($sectionId, $orderedIds);
        }
        flash('success', 'Reihenfolge der Seiten gespeichert.');
        redirect('/infobuecher/' . $book->id . '/seite/' . (int) $ids[0]);
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
        /* #67: ?lesen=1 zeigt Bearbeiterinnen die eigene Seite im Lesemodus. */
        $preview = $canWrite && (int) ($_GET['lesen'] ?? 0) === 1;
        if ($preview) {
            $canWrite = false;
        }
        /* #185: Letzte besuchte Seite je Buch im Browser merken: Der
         * Buch-Start (und der Buch-Link in der Breadcrumb) oeffnet genau
         * diese Seite wieder. Cookie nur mit Lesezugriff auf genau diese
         * Seite, sie ist oben geprueft. */
        setcookie('ic_ibook_last[' . (int) $book->id . ']', (string) (int) $page['id'], [
            'expires' => time() + 15552000,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        /* Linke Navigation (#68): Abschnitte und Seiten des Buchs, damit man
         * auf der Seitenseite zwischen den Seiten springen kann. #184: Fuer
         * Schuelerinnen und Schueler nur die sichtbaren Abschnitte (der eigene
         * Bereich wird dabei wie in der Buchansicht sicher angelegt). */
        $nav = [];
        foreach (self::navSectionsFor($book, $user) as $navSection) {
            /* #72: Schreibrecht je Abschnitt mitgeben, damit die Sidebar nur
             * dort das "Neue Seite"-Formular zeigt, wo es erlaubt ist. */
            $nav[] = ['section' => $navSection, 'pages' => InfoBook::pages((int) $navSection['id']), 'canWrite' => self::canWriteSection($navSection, $user)];
        }
        /* #73: Seiten des Buchs als Link-Ziele fuer den Editor und zur
         * Pruefung im Lesemodus: gerendert werden nur Links innerhalb
         * desselben Buchs. */
        $navPages = [];
        $linkPages = [];
        foreach ($nav as $navEntry) {
            foreach ($navEntry['pages'] as $navPage) {
                $navPages[] = ['id' => (int) $navPage['id'], 't' => (string) $navPage['title'], 's' => (string) $navEntry['section']['title']];
                $linkPages[(int) $navPage['id']] = true;
            }
        }
        $blocks = InfoBook::pageBlocks($page);
        foreach ($blocks as $i => $block) {
            $blocks[$i]['html'] = self::renderBlock($block, (int) $book->id, $linkPages);
        }
        $media = self::canManage($book, $user) || $canWrite ? InfoBook::mediaOfBook($book->id) : [];
        /* #79: Eigene Diagramm-Bibliothek fuer den Editor - Auswahl uebernimmt
         * den Mermaid-Code in ein Diagramm-Element. */
        $diagrams = [];
        if ($canWrite && $user !== null) {
            foreach (ClassDiagram::all((int) $user->id) as $diagram) {
                $diagrams[] = ['id' => (int) $diagram->id, 't' => (string) $diagram->title, 'kind' => (string) $diagram->kind, 'src' => (string) $diagram->mermaid];
            }
        }
        /* #80: Vorhandene Datenbanken der SQL-Werkstatt als Vorlage: Schema
         * und Beispieldaten fuellen das DB-Element per Dropdown. */
        $dbTemplates = [];
        if ($canWrite) {
            $tplTitles = [];
            foreach (SqlTemplate::all() as $tpl) {
                $tplTitles[(string) $tpl->slug] = (string) $tpl->title;
            }
            foreach (glob(SqlWorkshop::templatePath('*')) as $tplFile) {
                $slug = basename($tplFile, '.sql');
                $seed = (string) @file_get_contents($tplFile);
                if ($seed === '') {
                    continue;
                }
                $dbTemplates[] = ['slug' => $slug, 't' => $tplTitles[$slug] ?? ucfirst($slug), 'sql' => $seed];
            }
        }
        /* #86: Java-Klassen aus eigenen Projekten der Code-Werkstatt: Die
         * Auswahl fuegt den Klassentext in ein Code-Fenster ein. */
        $codeClasses = [];
        if ($canWrite && $user !== null) {
            foreach (Project::all((int) $user->id) as $proj) {
                if ($proj->type !== 'code' || $proj->language !== 'java') {
                    continue;
                }
                foreach (ProjectFile::allForProject((int) $proj->id) as $projFile) {
                    if (count($codeClasses) >= 60) {
                        break 2;
                    }
                    $fileName = (string) $projFile->name;
                    $fileCode = (string) $projFile->content;
                    if (substr($fileName, -5) !== '.java' || $fileCode === '' || strlen($fileCode) > 20000) {
                        continue;
                    }
                    $codeClasses[] = ['project' => (string) $proj->title, 'name' => $fileName, 'code' => $fileCode];
                }
            }
        }
        render('ibook_page', [
            'title' => (string) $page['title'],
            'active' => 'module-infobuecher',
            'book' => $book,
            'section' => $section,
            'page' => $page,
            'blocks' => $blocks,
            'media' => $media,
            'canWrite' => $canWrite,
            'canManage' => self::canManage($book, $user),
            'preview' => $preview,
            'nav' => $nav,
            'navPages' => $navPages,
            'activeSectionId' => (int) $section['id'],
            'activePageId' => (int) $page['id'],
            'navNewSection' => false,
            'diagrams' => $diagrams,
            'dbTemplates' => $dbTemplates,
            'codeClasses' => $codeClasses,
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
        /* #67: Autospeichern aus dem Editor antwortet mit JSON statt Redirect. */
        if (($_SERVER['HTTP_X_IC_AUTOSAVE'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'saved_at' => date('H:i')]);
            return;
        }
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

    /**
     * #114: Fertiges Buchpaket (JSON mit Abschnitten + Seiten) importieren.
     * Nur Lehrkraefte mit Verwaltungsrecht am Buch; die Bloecke laufen auf
     * Serverseite durch dieselbe Bereinigung wie beim normalen Speichern.
     */
    public static function import(array $params): void
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
        if (!self::canManage($book, $user)) {
            flash('error', 'Nur die Lehrkraft kann Inhalte importieren.');
            redirect('/infobuecher/' . $book->id);
        }
        $data = json_decode((string) ($_POST['paket'] ?? ''), true);
        if (!is_array($data) || !is_array($data['sections'] ?? null) || $data['sections'] === []) {
            flash('error', 'Das Paket enthält keine Abschnitte. Erwartet wird JSON wie {"sections":[{"title":"...","area":"lernbereich","pages":[{"title":"...","blocks":[...]}]}]}');
            redirect('/infobuecher/' . $book->id);
        }
        $stats = InfoBook::importContent($book->id, $data);
        if ($stats['sections'] === 0) {
            flash('error', 'Im Paket war kein gültiger Abschnitt.');
            redirect('/infobuecher/' . $book->id);
        }
        flash('success', 'Import fertig: ' . (int) $stats['sections'] . ' Abschnitt(e) und ' . (int) $stats['pages'] . ' Seite(n) angelegt.');
        /* #185: Nach dem Import direkt in die Abschnittsansicht des ersten
         * importierten Abschnitts: Dort stehen die neuen Seiten als Karten
         * und das Import-Formular bleibt in Reichweite. */
        redirect('/infobuecher/' . $book->id . '?abschnitt=' . (int) ($stats['first_section_id'] ?? 0));
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
        $sections = self::navSectionsFor($book, $user);
        $wanted = (int) ($_GET['abschnitt'] ?? 0);
        $slides = [];
        $linkPages = [];
        foreach ($sections as $section) {
            if ($wanted > 0 && (int) $section['id'] !== $wanted) {
                continue;
            }
            foreach (InfoBook::pages((int) $section['id']) as $page) {
                $linkPages[(int) $page['id']] = true; /* #73: verlinkbare Seiten */
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
                $html .= self::renderBlock($block, (int) $book->id, $linkPages);
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

    /**
     * #184: Abschnitte fuer die linke Navigation, die Buchansicht und die
     * Präsentation. Lehrkraefte und Administration sehen alle Abschnitte des
     * Buchs; Schuelerinnen und Schueler nur die sichtbaren: Der eigene private
     * Bereich wird dabei sicher angelegt (vor dem Laden) und fremde "Mein
     * Bereich"-Abschnitte bleiben voellig aussen vor.
     * @return list<array<string, mixed>>
     */
    private static function navSectionsFor(InfoBook $book, User $user): array
    {
        if ($user->role !== 'student') {
            return InfoBook::sections($book->id);
        }
        if ($book->classId !== null && $book->classId === $user->classId) {
            InfoBook::ensureOwnSection($book->id, $user->id);
        }
        $sections = InfoBook::sections($book->id);
        return array_values(array_filter($sections, static function (array $s) use ($user): bool {
            return self::visibleSectionForStudent($s, $user);
        }));
    }

    /**
     * #185: Zuletzt besuchte Seite dieses Buchs aus dem Browser-Cookie
     * (ic_ibook_last[buchId], gesetzt beim Oeffnen einer Seite). Nur wenn
     * es die Seite noch gibt und ihr Abschnitt fuer dieses Konto sichtbar
     * ist, wird sie als Startseite zurueckgegeben.
     * @param list<array<string, mixed>> $sections
     */
    private static function lastVisitedPage(InfoBook $book, array $sections): ?int
    {
        $raw = $_COOKIE['ic_ibook_last'] ?? null;
        if (!is_array($raw)) {
            return null;
        }
        $pageId = (int) ($raw[(string) $book->id] ?? 0);
        if ($pageId <= 0) {
            return null;
        }
        $page = InfoBook::findPage($pageId);
        if ($page === null) {
            return null;
        }
        foreach ($sections as $section) {
            if ((int) $section['id'] === (int) $page['section_id']) {
                return $pageId;
            }
        }
        return null;
    }

    /** Prozentwert (0 bis 100) ohne unnötige Nullen, fuer die Positionen der Freien Flaeche. */
    private static function pct(mixed $value, float $total): string
    {
        if ($total <= 0) {
            $total = 1;
        }
        $n = max(0.0, min(100.0, round(((float) $value) / $total * 100, 2)));
        $s = number_format($n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }

    /**
     * Freie Flaeche (#63/#64) als HTML rendern: Text, Bilder, Stift-Striche
     * (als SVG), Einbettungen und Audio/Video absolut positioniert in Prozent,
     * dadurch skaliert die Flaeche in jeder Breite mit (Lesen und
     * Präsentationsmodus ohne JavaScript).
     * @param array<string, mixed> $block
     */
    private static function renderCanvas(array $block, int $bookId = 0, array $linkPages = []): string
    {
        $w = max(50.0, (float) ($block['w'] ?? 1600));
        $h = max(50.0, (float) ($block['h'] ?? 1000));
        $inner = '';
        foreach (($block['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $kind = (string) ($item['kind'] ?? '');
            $left = self::pct($item['x'] ?? 0, $w);
            $top = self::pct($item['y'] ?? 0, $h);
            /* Seiten-Link (#73): Ziel muss eine Seite desselben Buchs sein,
             * dann liegt das Element im Lesemodus in einem Link. */
            $itemLink = 0;
            if ($bookId > 0 && isset($item['link']) && isset($linkPages[(int) $item['link']])) {
                $itemLink = (int) $item['link'];
            }
            $pre = $itemLink > 0 ? '<a class="icv-item-link" href="' . e(url('infobuecher/' . $bookId . '/seite/' . $itemLink)) . '">' : '';
            $lenBefore = strlen($inner);
            if ($kind === 'text') {
                $width = self::pct($item['w'] ?? 320, $w);
                $bg = (string) ($item['bg'] ?? '');
                $align = (string) ($item['align'] ?? 'left');
                /* Post-It-Hintergrund (#68): Farbe auf den GESAMTEN Zettel,
                 * 'none' ist ganz ohne Hintergrund (auch ohne gelbes Post-It). */
                $bgStyle = '';
                if ($bg === 'none') {
                    $bgStyle = ';background:none;border-color:transparent;box-shadow:none';
                } elseif ($bg !== '') {
                    $bgStyle = ';background:' . $bg;
                }
                $inner .= $pre . '<div class="icv-item icv-text" style="left:' . $left . '%;top:' . $top . '%;width:' . $width . '%' . $bgStyle
                    . ';text-align:' . ($align !== '' ? $align : 'left') . '">'
                    . PresentationController::renderSlideBody((string) ($item['body'] ?? '')) . '</div>';
            } elseif ($kind === 'image') {
                $mediaId = (int) ($item['media'] ?? 0);
                if ($mediaId <= 0) {
                    continue;
                }
                $width = self::pct($item['w'] ?? 160, $w);
                $height = self::pct($item['h'] ?? 120, $h);
                $alt = (string) ($item['alt'] ?? '');
                $name = (string) ($item['name'] ?? '');
                $inner .= $pre . '<div class="icv-item icv-image" style="left:' . $left . '%;top:' . $top . '%;width:' . $width . '%;height:' . $height . '%">'
                    . '<img src="' . e(url('/infobuecher/medium/' . $mediaId)) . '" alt="' . e($alt !== '' ? $alt : ($name !== '' ? $name : 'Bild')) . '"></div>';
            } elseif ($kind === 'ink') {
                /* Stift-Strich als SVG-Pfad (#64): Punkte sind relativ zum
                 * Ursprung, das SVG skaliert mit dem Element mit. */
                $points = is_array($item['p'] ?? null) ? $item['p'] : [];
                if (count($points) < 2) {
                    continue;
                }
                $iw = max(4.0, (float) ($item['w'] ?? 40));
                $ih = max(4.0, (float) ($item['h'] ?? 40));
                $d = '';
                for ($i = 0; $i + 1 < count($points); $i += 2) {
                    $d .= ($i === 0 ? 'M ' : 'L ') . self::svgNum($points[$i]) . ' ' . self::svgNum($points[$i + 1]) . ' ';
                }
                $inner .= $pre . '<div class="icv-item icv-stroke" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($iw, $w) . '%;height:' . self::pct($ih, $h) . '%">'
                    . '<svg class="icv-ink-svg" viewBox="0 0 ' . self::svgNum($iw) . ' ' . self::svgNum($ih) . '" preserveAspectRatio="none" aria-hidden="true">'
                    . '<path d="' . $d . '" fill="none" stroke="' . e((string) ($item['c'] ?? '#1f2937')) . '" stroke-width="' . self::svgNum($item['sw'] ?? 4)
                    . '" stroke-linecap="round" stroke-linejoin="round"/></svg></div>';
            } elseif ($kind === 'embed') {
                $url = InfoBook::cleanEmbedUrl((string) ($item['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $title = (string) ($item['title'] ?? '');
                $inner .= $pre . '<div class="icv-item icv-embed" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 480, $w) . '%">'
                    . '<div class="embed-frame"><iframe src="' . e($url) . '" title="' . e($title !== '' ? $title : 'Eingebetteter Inhalt') . '" loading="lazy" allowfullscreen referrerpolicy="no-referrer"></iframe></div></div>';
            } elseif ($kind === 'audio') {
                $mediaId = (int) ($item['media'] ?? 0);
                if ($mediaId <= 0) {
                    continue;
                }
                $inner .= $pre . '<div class="icv-item icv-audio" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 360, $w) . '%">'
                    . '<audio controls preload="metadata" src="' . e(url('/infobuecher/medium/' . $mediaId)) . '"></audio></div>';
            } elseif ($kind === 'video') {
                $mediaId = (int) ($item['media'] ?? 0);
                if ($mediaId <= 0) {
                    continue;
                }
                $inner .= $pre . '<div class="icv-item icv-video" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 480, $w) . '%;height:' . self::pct($item['h'] ?? 270, $h) . '%">'
                    . '<video controls preload="metadata" src="' . e(url('/infobuecher/medium/' . $mediaId)) . '"></video></div>';
            } elseif ($kind === 'code') {
                /* Code-Fenster mit Konsole (#75/#81): Der Quelltext wird unveraendert
                 * ausgegeben, ausgefuehrt wird er erst im Browser (window.IC_RUN_CODE
                 * in app.js) - der Server fuehrt nie fremden Code aus. Die Sprache
                 * steht als Tag am Fenster und steuert die Ausfuehrung. */
                $code = (string) ($item['code'] ?? '');
                $codeLang = in_array((string) ($item['lang'] ?? 'java'), ['java', 'javascript', 'python'], true) ? (string) ($item['lang'] ?? 'java') : 'java';
                $codeLangLabel = $codeLang === 'javascript' ? 'JavaScript' : ($codeLang === 'python' ? 'Python' : 'Java');
                /* Importierte Klassen (#87): Im Lesemodus als Chips ueber dem
                 * Code, beim Ausfuehren reicht data-clsfiles sie der Java-VM
                 * als eigene Dateien mit (der Quelltext bleibt unsichtbar). */
                $clsList = [];
                if (is_array($item['cls'] ?? null)) {
                    foreach ($item['cls'] as $c) {
                        if (!is_array($c) || (string) ($c['name'] ?? '') === '') { continue; }
                        $clsList[] = ['name' => (string) $c['name'], 'code' => (string) ($c['code'] ?? '')];
                    }
                }
                $clsAttr = '';
                $clsChips = '';
                if ($clsList) {
                    $clsAttr = ' data-clsfiles="' . e(json_encode($clsList, JSON_UNESCAPED_UNICODE)) . '"';
                    $clsChips = '<div class="icv-code-cards">';
                    foreach ($clsList as $c) {
                        /* #99: data-clsname verknuepft den Chip mit der
                         * Klasse, app.js baut daraus die aufklappbare
                         * Klassenkarte (Attribute und Methoden, nur lesen). */
                        $clsChips .= '<div class="icv-code-card icv-code-card-ro" data-clsname="' . e(pathinfo((string) $c['name'], PATHINFO_FILENAME)) . '" title="' . e((string) ($c['name'] ?? 'Klasse')) . '">' . e(pathinfo((string) $c['name'], PATHINFO_FILENAME)) . '</div>';
                    }
                    $clsChips .= '</div>';
                }
                $inner .= $pre . '<div class="icv-item icv-code" data-lang="' . e($codeLang) . '" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 520, $w) . '%">'
                    . '<div class="icv-code-head"><span class="icv-code-langtag">' . e($codeLangLabel) . '</span></div>'
                    . $clsChips
                    /* Code editierbar im Lesemodus (#92): wie beim geteilten
                     * Diagramm (#85) wird aenderungen nicht gespeichert, sie
                     * gelten nur fuer die laufende Ansicht. */
                    . '<textarea class="mono icv-code-src" spellcheck="false" aria-label="Programmcode">' . e($code) . '</textarea>'
                    . '<button type="button" class="icv-code-run" data-lang="' . e($codeLang) . '"' . $clsAttr . '>▶ Ausführen</button>'
                    . '<pre class="icv-code-out" aria-live="polite"></pre></div>';
            } elseif ($kind === 'db') {
                /* Datenbank-Element (#76): Schema serverseitig aus den CREATE-
                 * TABLE-Zeilen, Seed als versteckter Quelltext. Abfragen laufen
                 * clientseitig (IC_DB_RUN in app.js), der Server bleibt raus. */
                $seed = (string) ($item['dbseed'] ?? '');
                $query = (string) ($item['dbq'] ?? '');
                $inner .= $pre . '<div class="icv-item icv-db" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 420, $w) . '%">'
                    . '<div class="icv-db-head">🗄️ Datenbank-Schema</div>'
                    . '<pre class="icv-db-schema">' . e(self::dbSchemaText($seed)) . '</pre>'
                    . '<textarea class="icv-db-in mono" rows="2" spellcheck="false">' . e($query) . '</textarea>'
                    . '<button type="button" class="icv-db-run">▶ Abfrage ausführen</button>'
                    . '<div class="icv-db-out mono" aria-live="polite"></div>'
                    . '<pre class="icv-db-seed-src" hidden>' . e($seed) . '</pre></div>';
            } elseif ($kind === 'html') {
                /* HTML-Baustein (#174): Eigene HTML-Seite (z. B. Eingabe-
                 * maske) als dritter Kasten neben Java- und SQL-Fenster.
                 * Der Server gibt nur Text weiter; ausgefuehrt wird der
                 * Quelltext im Browser ausschliesslich in einem abge-
                 * schotteten iframe (Sandbox ohne same-origin, app.js),
                 * der weder Cookies noch die Plattform erreichen kann. */
                $src = (string) ($item['html'] ?? '');
                $inner .= $pre . '<div class="icv-item icv-html" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 440, $w) . '%">'
                    . '<div class="icv-html-head">🌐 HTML-Seite (Eingabemaske)</div>'
                    . '<div class="icv-html-frame" aria-label="HTML-Seite"></div>'
                    . '<pre class="icv-html-src" hidden>' . e($src) . '</pre></div>';
            } elseif ($kind === 'diagram') {
                /* Diagramm-Element (#77/#79): pur (Standard) zeigt im
                 * Lesemodus nur das Diagramm, geteilt zeigt Code und
                 * Diagramm nebeneinander mit ziehbarem Trenner. */
                $src = (string) ($item['src'] ?? '');
                if ((string) ($item['dmode'] ?? '') === 'split') {
                    /* Geteilt (#79/#85): Der Quelltext bleibt im Lesemodus
                     * bearbeitbar, um live zu präsentieren; app.js zeichnet
                     * das Diagramm bei jeder Aenderung neu. Gespeichert wird
                     * nur in der Bearbeitung. */
                    $inner .= $pre . '<div class="icv-item icv-diagram icv-diagram-split-ro" data-src="' . e($src) . '" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 480, $w) . '%">'
                        . '<div class="icv-diagram-split"><textarea class="mono icv-diagram-code" spellcheck="false" aria-label="Mermaid-Quelltext">' . e($src) . '</textarea>'
                        . '<div class="icv-diagram-split-bar" title="Ziehen: Fenstergröße anpassen"></div>'
                        . '<div class="icv-diagram-prev" aria-live="polite"></div></div>'
                    . '</div>';
                } else {
                    $inner .= $pre . '<div class="icv-item icv-diagram icv-diagram-ro" data-src="' . e($src) . '" style="left:' . $left . '%;top:' . $top . '%;width:' . self::pct($item['w'] ?? 480, $w) . '%">'
                        . '<div class="icv-diagram-prev" aria-live="polite"></div></div>';
                }
            }
            /* Nur schliessen, wenn der Zweig wirklich etwas angehaengt hat. */
            if ($itemLink > 0 && strlen($inner) > $lenBefore) {
                $inner .= '</a>';
            }
        }
        $ink = (int) ($block['ink'] ?? 0);
        if ($ink > 0) {
            /* Alte Seiten (#63): komplette Freihand-Ebene als PNG ueber der Flaeche. */
            $inner .= '<img class="icv-ink" src="' . e(url('/infobuecher/medium/' . $ink)) . '" alt="Freihandzeichnung">';
        }
        if ($inner === '') {
            $inner = '<div class="icv-empty muted">Noch keine Inhalte auf dieser Fläche.</div>';
        }
        return '<div class="icv-stage icv-readonly" style="aspect-ratio:' . $w . '/' . $h . '">' . $inner . '</div>';
    }

    /** Zahl fuer SVG-Attribute: kompakt, ohne exponentiale Schreibweise. */
    private static function svgNum(mixed $value): string
    {
        $n = round((float) $value, 2);
        if (abs($n) < 0.01 && $n != 0.0) {
            $n = 0.0;
        }
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Datenbank-Schema (#76): alle CREATE-TABLE-Zeilen des Seeds als
     * lesbare Auflistung "tabelle (spalte TYP, ...)". */
    private static function dbSchemaText(string $seed): string
    {
        if (!preg_match_all('/CREATE\s+TABLE\s+(\w+)\s*\(([^)]*)\)/is', $seed, $m, PREG_SET_ORDER)) {
            return 'Noch keine Tabellen: CREATE TABLE ... im Element definieren.';
        }
        $lines = [];
        foreach ($m as $tbl) {
            $cols = [];
            foreach (preg_split('/,/', $tbl[2]) ?: [] as $col) {
                $parts = preg_split('/\s+/', trim($col)) ?: [];
                if ($parts[0] === '') {
                    continue;
                }
                $cols[] = $parts[0] . ' ' . strtoupper($parts[1] ?? 'TEXT');
            }
            $lines[] = $tbl[1] . ' (' . implode(', ', $cols) . ')';
        }
        return implode("\n", $lines);
    }

    /** Einen Block als HTML rendern (fuer Seitenansicht und Präsentationsmodus).
     * $bookId/$linkPages (#73): erlaubte Link-Ziele, nur sie werden verlinkt. @param array<string, mixed> $block */
    public static function renderBlock(array $block, int $bookId = 0, array $linkPages = []): string
    {
        $type = (string) ($block['type'] ?? 'text');
        if ($type === 'text') {
            return '<div class="iblock-text">' . PresentationController::renderSlideBody((string) ($block['body'] ?? '')) . '</div>';
        }
        if ($type === 'canvas') {
            return self::renderCanvas($block, $bookId, $linkPages);
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
