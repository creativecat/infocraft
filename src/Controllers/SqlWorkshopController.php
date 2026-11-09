<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\User;
use PDO;
use PDOException;
use Infocraft\SqlWorkshop;

final class SqlWorkshopController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $isTeacher = Auth::isTeacher();
        render('sql_overview', [
            'title' => 'SQL-Werkstatt',
            'active' => 'module-sql',
            'grants' => SqlTemplate::grantsFor($user->id),
            'isTeacher' => $isTeacher,
            'templates' => $isTeacher ? SqlTemplate::all() : [],
            'classes' => $isTeacher ? SchoolClass::forTeacher($user->id, $user->role === 'admin') : [],
            'studentsByClass' => $isTeacher ? self::studentsByClass($user) : [],
            'assignments' => $isTeacher ? SqlTemplate::grantsOverview($user->id, $user->role === 'admin') : [],
            'permissions' => SqlWorkshop::PERMISSIONS,
        ]);
    }

    public static function workspace(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        $grant = self::requireGrant($template, $user);
        if ($grant === null) {
            flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
            redirect('/werkstatt/sql');
        }

        $result = null;
        $lastSql = '';
        if (isset($_SESSION['sql_result']) && is_array($_SESSION['sql_result']) && ($_SESSION['sql_result']['slug'] ?? '') === $template->slug) {
            $result = $_SESSION['sql_result']['result'] ?? null;
            $lastSql = (string) ($_SESSION['sql_result']['sql'] ?? '');
            unset($_SESSION['sql_result']);
        }

        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id, $grant['permission']);
        render('sql_workspace', [
            'title' => $template->title,
            'active' => 'module-sql',
            'template' => $template,
            'permission' => $grant['permission'],
            'permissionLabel' => SqlWorkshop::PERMISSIONS[$grant['permission']] ?? $grant['permission'],
            'tables' => SqlWorkshop::tables($pdo),
            'er' => SqlWorkshop::erDiagram($pdo),
            'diagramAuto' => SchoolClass::diagramAutoForUser($user),
            'missions' => SqlTemplate::missions($template->id),
            'solved' => SqlTemplate::solvedMissionIds($grant['grantId']),
            'result' => $result,
            'lastSql' => $lastSql,
            'user' => $user,
        ]);
    }

    public static function query(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        $grant = self::requireGrant($template, $user);
        if ($grant === null) {
            flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
            redirect('/werkstatt/sql');
        }
        $sql = (string) ($_POST['sql'] ?? '');
        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id, $grant['permission']);
        $result = SqlWorkshop::run($grant['permission'], $sql, $pdo);
        $_SESSION['sql_result'] = ['slug' => $template->slug, 'result' => $result, 'sql' => $sql];
        redirect('/werkstatt/sql/' . $template->slug);
    }

    public static function mission(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        $grant = self::requireGrant($template, $user);
        if ($grant === null) {
            flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
            redirect('/werkstatt/sql');
        }
        $mission = SqlTemplate::findMission((int) ($params['id'] ?? 0));
        if ($mission === null || (int) $mission['template_id'] !== $template->id) {
            http_response_code(404);
            render('error', ['title' => 'Unbekannte Aufgabe', 'message' => 'Diese Mission gibt es nicht.']);
            return;
        }
        $sql = (string) ($_POST['mission_sql'] ?? '');
        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id, $grant['permission']);
        $run = SqlWorkshop::run($grant['permission'], $sql, $pdo);
        if (!$run['ok']) {
            flash('error', 'Die Anweisung war nicht ausführbar: ' . $run['error']);
            redirect('/werkstatt/sql/' . $template->slug . '#aufgaben');
        }

        $solved = false;
        if (($mission['kind'] ?? 'query') === 'query') {
            $solved = SqlWorkshop::matchesSolution($sql, $pdo, SqlWorkshop::pristinePdo($template->slug), (string) $mission['solution_sql']);
        } else {
            $value = SqlWorkshop::checkValue($pdo, (string) $mission['check_sql']);
            $solved = $value !== null && trim($value) === trim((string) ($mission['check_expected'] ?? ''));
        }

        if ($solved) {
            SqlTemplate::solve($grant['grantId'], (int) $mission['id']);
            flash('success', 'Aufgabe gelöst: ' . (string) $mission['title'] . ' 🎉');
        } else {
            $message = 'Noch nicht gelöst. Dein Ergebnis passt noch nicht zur Aufgabe.';
            if (($mission['kind'] ?? 'query') === 'action') {
                $message = 'Anweisung ausgeführt, aber die Kontrolle ist noch nicht erfüllt.';
            }
            flash('error', $message . ' Tipp: ' . (string) $mission['hint']);
        }
        redirect('/werkstatt/sql/' . $template->slug . '#aufgaben');
    }

    /** Vorlage an eine Klasse oder einzelne Person austeilen (Rechte wählbar). */
    public static function distribute(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können verteilen.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $template = SqlTemplate::findById((int) ($_POST['template_id'] ?? 0));
        if ($template === null) {
            flash('error', 'Vorlage nicht gefunden.');
            redirect('/werkstatt/sql');
        }
        $permission = (string) ($_POST['permission'] ?? 'read');
        if (!array_key_exists($permission, SqlWorkshop::PERMISSIONS)) {
            $permission = 'read';
        }

        $recipients = self::distributionTargets($user, $isAdmin);
        if ($recipients === []) {
            flash('error', 'Kein Ziel angegeben oder du unterrichtest diese Klasse nicht.');
            redirect('/werkstatt/sql');
        }
        $count = 0;
        foreach ($recipients as $recipient) {
            SqlTemplate::grant($template->id, $recipient->id, $permission);
            $count++;
        }
        flash('success', $template->title . ' an ' . $count . ' Person(en) ausgeteilt (' . SqlWorkshop::PERMISSIONS[$permission] . ').');
        redirect('/werkstatt/sql');
    }

    /** Eigene Übungskopie mit Vollzugriff anlegen. */
    public static function selfCopy(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Übungskopien anlegen.');
            redirect('/');
        }
        csrf_validate();
        $template = SqlTemplate::findById((int) ($_POST['template_id'] ?? 0));
        if ($template === null) {
            flash('error', 'Vorlage nicht gefunden.');
            redirect('/werkstatt/sql');
        }
        SqlTemplate::grant($template->id, $user->id, 'admin');
        flash('success', 'Übungskopie von ' . $template->title . ' angelegt.');
        redirect('/werkstatt/sql');
    }

    public static function revoke(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Zuweisungen verwalten.');
            redirect('/');
        }
        csrf_validate();
        $grantId = (int) ($_POST['grant_id'] ?? 0);
        if (!SqlTemplate::grantBelongsToTeacher($grantId, $user->id, $user->role === 'admin')) {
            flash('error', 'Diese Zuweisung gehört nicht zu deinen Klassen.');
            redirect('/werkstatt/sql');
        }
        SqlTemplate::revoke($grantId);
        flash('success', 'Zuweisung entfernt.');
        redirect('/werkstatt/sql');
    }

    /** @return array{grantId: int, permission: string}|null */
    private static function requireGrant(?SqlTemplate $template, User $user): ?array
    {
        if ($template === null) {
            return null;
        }
        $grant = SqlTemplate::grantFor($template->id, $user->id);
        if ($grant === null && ($user->role === 'teacher' || $user->role === 'admin')) {
            SqlTemplate::grant($template->id, $user->id, 'admin');
            $grant = SqlTemplate::grantFor($template->id, $user->id);
        }
        return $grant;
    }

    /** @return list<User> */
    private static function distributionTargets(User $user, bool $isAdmin): array
    {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                return [];
            }
            return User::byClass($classId);
        }
        if ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student') {
                return [];
            }
            if (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id))) {
                return [];
            }
            return [$student];
        }
        return [];
    }

    /* ===== App-Ansichten (#46, #47): Mobil-Frontend auf dem SQL-Backend =====
     * Shop und InstaCraft bekommen eine Handy-Ansicht, die dieselbe Datenbank
     * nutzt wie die Werkstatt: Was in der App passiert, sieht man mit SELECT -
     * und umgekehrt. */

    private const APP_SLUGS = ['shop', 'instacraft'];

    public static function appView(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        $grant = self::requireGrant($template, $user);
        if ($grant === null) {
            flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
            redirect('/werkstatt/sql');
        }
        $slug = (string) $template->slug;
        if (!in_array($slug, self::APP_SLUGS, true)) {
            http_response_code(404);
            render('error', ['title' => 'Keine App-Ansicht', 'message' => 'Für diese Datenbank gibt es (noch) keine App-Ansicht.']);
            return;
        }
        $canWrite = $grant['permission'] !== 'read';
        try {
            $pdo = SqlWorkshop::workspacePdo($slug, $user->id, $grant['permission']);
            if ($slug === 'shop') {
                $kundeId = self::appShopKunde($pdo);
                render('sql_app_shop', [
                    'title' => $template->title . '-App',
                    'active' => 'module-sql',
                    'template' => $template,
                    'canWrite' => $canWrite,
                    'kundeId' => $kundeId,
                    'kunden' => self::rows($pdo, 'SELECT id, vorname, nachname, stadt FROM kunden ORDER BY id'),
                    'produkte' => self::rows($pdo, 'SELECT p.id, p.name, p.preis, p.lager, k.name AS kategorie FROM produkte p JOIN kategorien k ON k.id = p.kategorie_id ORDER BY p.id'),
                    'bestellungen' => self::rows($pdo, 'SELECT b.id, b.datum, b.status, COUNT(bp.id) AS positionen, COALESCE(SUM(bp.menge * p.preis), 0) AS summe FROM bestellungen b LEFT JOIN bestellpositionen bp ON bp.bestellung_id = b.id LEFT JOIN produkte p ON p.id = bp.produkt_id WHERE b.kunde_id = ' . (int) $kundeId . ' GROUP BY b.id ORDER BY b.id DESC LIMIT 20'),
                    'user' => $user,
                ]);
                return;
            }
            $ich = self::appInstaUser($pdo);
            $ichId = (int) $ich['id'];
            render('sql_app_instacraft', [
                'title' => $template->title . '-App',
                'active' => 'module-sql',
                'template' => $template,
                'canWrite' => $canWrite,
                'ich' => $ich,
                'nutzer' => self::rows($pdo, 'SELECT id, benutzername, name, bio FROM nutzer ORDER BY id'),
                'posts' => self::rows($pdo, 'SELECT p.id, p.beschreibung, p.datum, p.nutzer_id, n.benutzername, (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes, (SELECT COUNT(*) FROM kommentare k WHERE k.post_id = p.id) AS kommentare, EXISTS(SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.nutzer_id = ' . $ichId . ') AS geliked, EXISTS(SELECT 1 FROM folgt f WHERE f.folger_id = ' . $ichId . ' AND f.folgt_id = p.nutzer_id) AS folgt FROM posts p JOIN nutzer n ON n.id = p.nutzer_id ORDER BY p.id DESC'),
                'kommentare' => self::rows($pdo, 'SELECT k.post_id, n.benutzername, k.text FROM kommentare k JOIN nutzer n ON n.id = k.nutzer_id ORDER BY k.id'),
                'user' => $user,
            ]);
        } catch (PDOException $e) {
            render('error', [
                'title' => 'App kann die Datenbank nicht lesen',
                'message' => 'Deine Datenbank hat vermutlich nicht mehr alle Tabellen der App (DROP TABLE in der Werkstatt?). Lege die fehlende Tabelle wieder an oder frage deine Lehrkraft.',
            ]);
        }
    }

    public static function appAction(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        $grant = self::requireGrant($template, $user);
        if ($grant === null) {
            flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
            redirect('/werkstatt/sql');
        }
        $slug = (string) $template->slug;
        if (!in_array($slug, self::APP_SLUGS, true)) {
            http_response_code(404);
            render('error', ['title' => 'Keine App-Ansicht', 'message' => 'Für diese Datenbank gibt es (noch) keine App-Ansicht.']);
            return;
        }
        if ($grant['permission'] === 'read') {
            flash('error', 'Du hast nur Leserechte für diese Datenbank: Die App darf nichts verändern. Deine Lehrkraft kann dir Schreibrechte geben.');
            redirect('/werkstatt/sql/' . $slug . '/app');
        }
        $action = (string) ($_POST['action'] ?? '');
        try {
            $pdo = SqlWorkshop::workspacePdo($slug, $user->id, $grant['permission']);
            if ($slug === 'shop') {
                self::appShopAction($pdo, $action);
            } else {
                self::appInstaAction($pdo, $action);
            }
        } catch (PDOException $e) {
            flash('error', 'In der App ist etwas schiefgegangen: ' . $e->getMessage());
        }
        redirect('/werkstatt/sql/' . $slug . '/app');
    }

    /** @return list<array<string, mixed>> */
    private static function rows(PDO $pdo, string $sql): array
    {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Aktuell gewählter Kunde der Shop-App (Stand in der Session). */
    private static function appShopKunde(PDO $pdo): int
    {
        $kundeId = (int) ($_SESSION['sql_app_shop_kunde'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM kunden WHERE id = ?');
        $stmt->execute([$kundeId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return $kundeId;
        }
        $first = $pdo->query('SELECT id FROM kunden ORDER BY id LIMIT 1')->fetchColumn();
        $kundeId = $first === false ? 0 : (int) $first;
        $_SESSION['sql_app_shop_kunde'] = $kundeId;
        return $kundeId;
    }

    /** Aktuell gewähltes Profil der InstaCraft-App (Stand in der Session). */
    private static function appInstaUser(PDO $pdo): array
    {
        $nutzerId = (int) ($_SESSION['sql_app_insta_user'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, benutzername, name FROM nutzer WHERE id = ?');
        $stmt->execute([$nutzerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        $row = $pdo->query('SELECT id, benutzername, name FROM nutzer ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['id' => 0, 'benutzername' => '?', 'name' => '?'];
        }
        $_SESSION['sql_app_insta_user'] = (int) $row['id'];
        return $row;
    }

    private static function appShopAction(PDO $pdo, string $action): void
    {
        if ($action === 'kunde') {
            $kundeId = (int) ($_POST['kunde_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM kunden WHERE id = ?');
            $stmt->execute([$kundeId]);
            if ((int) $stmt->fetchColumn() === 0) {
                flash('error', 'Diesen Kunden gibt es nicht.');
                return;
            }
            $_SESSION['sql_app_shop_kunde'] = $kundeId;
            flash('success', 'Du bestellst jetzt als dieser Kunde.');
            return;
        }
        if ($action === 'kaufen') {
            $produktId = (int) ($_POST['produkt_id'] ?? 0);
            $menge = max(1, min(99, (int) ($_POST['menge'] ?? 1)));
            $stmt = $pdo->prepare('SELECT name, lager FROM produkte WHERE id = ?');
            $stmt->execute([$produktId]);
            $produkt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($produkt)) {
                flash('error', 'Dieses Produkt gibt es nicht.');
                return;
            }
            if ((int) $produkt['lager'] < $menge) {
                flash('error', 'Nicht genug auf Lager: Es sind nur ' . (int) $produkt['lager'] . ' da.');
                return;
            }
            $kundeId = self::appShopKunde($pdo);
            if ($kundeId === 0) {
                flash('error', 'Es gibt keinen Kunden zum Bestellen.');
                return;
            }
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO bestellungen (kunde_id, datum, status) VALUES (?, ?, 'app')")->execute([$kundeId, date('Y-m-d')]);
            $bestellungId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO bestellpositionen (bestellung_id, produkt_id, menge) VALUES (?, ?, ?)')->execute([$bestellungId, $produktId, $menge]);
            $pdo->prepare('UPDATE produkte SET lager = lager - ? WHERE id = ?')->execute([$menge, $produktId]);
            $pdo->commit();
            flash('success', 'Bestellung gespeichert: Prüfe sie in der Werkstatt mit SELECT * FROM bestellpositionen;');
            return;
        }
        if ($action === 'storno') {
            $bestellungId = (int) ($_POST['bestellung_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bestellungen WHERE id = ? AND status = 'app'");
            $stmt->execute([$bestellungId]);
            if ((int) $stmt->fetchColumn() === 0) {
                flash('error', 'Nur Bestellungen aus der App lassen sich hier stornieren.');
                return;
            }
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM bestellpositionen WHERE bestellung_id = ?')->execute([$bestellungId]);
            $pdo->prepare('DELETE FROM bestellungen WHERE id = ?')->execute([$bestellungId]);
            $pdo->commit();
            flash('success', 'Bestellung storniert. DELETE wirkt: In der Werkstatt ist sie weg.');
            return;
        }
        flash('error', 'Unbekannte Aktion in der Shop-App.');
    }

    private static function appInstaAction(PDO $pdo, string $action): void
    {
        $ich = self::appInstaUser($pdo);
        $ichId = (int) $ich['id'];
        if ($ichId === 0) {
            flash('error', 'Es gibt kein Profil zum Bedienen der App.');
            return;
        }
        if ($action === 'nutzer') {
            $nutzerId = (int) ($_POST['nutzer_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM nutzer WHERE id = ?');
            $stmt->execute([$nutzerId]);
            if ((int) $stmt->fetchColumn() === 0) {
                flash('error', 'Dieses Profil gibt es nicht.');
                return;
            }
            $_SESSION['sql_app_insta_user'] = $nutzerId;
            flash('success', 'Du bist jetzt als dieses Profil in der App unterwegs.');
            return;
        }
        if ($action === 'like' || $action === 'unlike') {
            $postId = (int) ($_POST['post_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE id = ?');
            $stmt->execute([$postId]);
            if ((int) $stmt->fetchColumn() === 0) {
                flash('error', 'Diesen Post gibt es nicht.');
                return;
            }
            if ($action === 'like') {
                $pdo->prepare('INSERT OR IGNORE INTO likes (post_id, nutzer_id) VALUES (?, ?)')->execute([$postId, $ichId]);
                flash('success', 'Gefällt! INSERT INTO likes - in der Werkstatt siehst du die neue Zeile.');
            } else {
                $pdo->prepare('DELETE FROM likes WHERE post_id = ? AND nutzer_id = ?')->execute([$postId, $ichId]);
                flash('success', 'Like zurückgezogen (DELETE FROM likes).');
            }
            return;
        }
        if ($action === 'kommentar') {
            $postId = (int) ($_POST['post_id'] ?? 0);
            $text = trim((string) ($_POST['text'] ?? ''));
            if ($text === '') {
                flash('error', 'Schreibe zuerst einen Kommentar.');
                return;
            }
            if (mb_strlen($text) > 500) {
                $text = mb_substr($text, 0, 500);
            }
            $pdo->prepare('INSERT INTO kommentare (post_id, nutzer_id, text, datum) VALUES (?, ?, ?, ?)')->execute([$postId, $ichId, $text, date('Y-m-d')]);
            flash('success', 'Kommentar gespeichert: SELECT * FROM kommentare zeigt es.');
            return;
        }
        if ($action === 'folgen' || $action === 'entfolgen') {
            $targetId = (int) ($_POST['nutzer_id'] ?? 0);
            if ($targetId === $ichId) {
                flash('error', 'Dir selbst folgen? Das geht auch in echten Apps nicht.');
                return;
            }
            if ($action === 'folgen') {
                $pdo->prepare('INSERT OR IGNORE INTO folgt (folger_id, folgt_id) VALUES (?, ?)')->execute([$ichId, $targetId]);
                flash('success', 'Du folgst jetzt diesem Profil (INSERT INTO folgt).');
            } else {
                $pdo->prepare('DELETE FROM folgt WHERE folger_id = ? AND folgt_id = ?')->execute([$ichId, $targetId]);
                flash('success', 'Nicht mehr gefolgt (DELETE FROM folgt).');
            }
            return;
        }
        flash('error', 'Unbekannte Aktion in der InstaCraft-App.');
    }

    /** @return array<int, list<User>> */
    private static function studentsByClass(User $user): array
    {
        $isAdmin = $user->role === 'admin';
        $map = [];
        foreach (SchoolClass::forTeacher($user->id, $isAdmin) as $class) {
            $map[$class->id] = User::byClass($class->id);
        }
        return $map;
    }
}
