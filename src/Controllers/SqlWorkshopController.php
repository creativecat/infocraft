<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\User;
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

        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id);
        render('sql_workspace', [
            'title' => $template->title,
            'active' => 'module-sql',
            'template' => $template,
            'permission' => $grant['permission'],
            'permissionLabel' => SqlWorkshop::PERMISSIONS[$grant['permission']] ?? $grant['permission'],
            'tables' => SqlWorkshop::tables($pdo),
            'er' => SqlWorkshop::erDiagram($pdo),
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
        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id);
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
        $pdo = SqlWorkshop::workspacePdo($template->slug, $user->id);
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
