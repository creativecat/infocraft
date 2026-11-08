<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;
use Infocraft\Otp;

/**
 * Unterricht-Ansicht für Lehrkräfte: eigene Klassen, Schülerlisten,
 * OTP-Passwörter zurücksetzen (einzeln oder für die ganze Klasse).
 */
final class TeacherController
{
    public static function classes(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        $isAdmin = $user->role === 'admin';
        $classes = SchoolClass::forTeacher($user->id, $isAdmin);
        $byClass = [];
        foreach ($classes as $class) {
            $byClass[] = [
                'class' => $class,
                'students' => User::byClass($class->id),
                'moduleModes' => \Infocraft\Controllers\ModuleController::modesFor($class->id),
            ];
        }
        render('teacher_classes', [
            'title' => 'Meine Klassen',
            'active' => 'unterricht',
            'byClass' => $byClass,
            'modules' => \Infocraft\Controllers\ModuleController::modules(),
            'teachers' => $isAdmin ? User::teachers() : [],
            'allClasses' => $isAdmin ? SchoolClass::all() : [],
        ]);
    }

    /**
     * Fortschrittsübersicht einer Klasse (Issue #8): eine Zeile je Schülerin
     * bzw. Schüler mit Quiz-Bestwerten, erledigten Aufgaben und zuletzt
     * bearbeiteten Projekten aus der Werkstatt.
     */
    public static function progress(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        $isAdmin = $user->role === 'admin';
        $classId = (int) ($params['id'] ?? 0);
        $class = null;
        foreach (SchoolClass::forTeacher($user->id, $isAdmin) as $candidate) {
            if ($candidate->id === $classId) {
                $class = $candidate;
                break;
            }
        }
        if ($class === null) {
            flash('error', 'Klasse nicht gefunden oder du unterrichtest sie nicht.');
            redirect('/unterricht/klassen');
        }
        $pdo = \Infocraft\Database::pdo();
        $rows = [];
        foreach (User::byClass($class->id) as $student) {
            // Quiz-Bestwerte: alle Quizze, die dieser Klasse oder der Person
            // ausgeteilt wurden, mit jeweils bester Punktzahl.
            $quizStmt = $pdo->prepare(
                'SELECT q.id, q.title, q.level,
                        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
                        (SELECT MAX(a.score) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.user_id = :uid) AS best_score,
                        (SELECT MIN(a.total) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.user_id = :uid) AS best_total,
                        (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.user_id = :uid) AS tries
                 FROM quizzes q
                 JOIN quiz_targets tt ON tt.quiz_id = q.id
                 WHERE tt.class_id = :cid OR tt.user_id = :uid
                 GROUP BY q.id ORDER BY q.id DESC'
            );
            $quizStmt->execute([':uid' => $student->id, ':cid' => $class->id]);
            $quizRows = [];
            $quizDone = 0;
            foreach ($quizStmt->fetchAll() as $q) {
                $played = $q['best_score'] !== null;
                if ($played) {
                    $quizDone++;
                }
                $quizRows[] = [
                    'title' => (string) $q['title'],
                    'best' => $played ? (int) $q['best_score'] . '/' . (int) $q['best_total'] : null,
                    'tries' => (int) $q['tries'],
                ];
            }
            // Aufgaben: ausgeteilt an die Klasse oder die Person, mit und
            // ohne Abgabe.
            $taskStmt = $pdo->prepare(
                'SELECT t.id, t.title, t.type,
                        (SELECT COUNT(*) FROM task_submissions ts WHERE ts.task_id = t.id AND ts.student_id = :uid) AS submitted,
                        (SELECT ts2.submitted_at FROM task_submissions ts2 WHERE ts2.task_id = t.id AND ts2.student_id = :uid LIMIT 1) AS submitted_at
                 FROM tasks t
                 JOIN task_targets tt ON tt.task_id = t.id
                 WHERE tt.class_id = :cid OR tt.user_id = :uid
                 GROUP BY t.id ORDER BY t.id DESC'
            );
            $taskStmt->execute([':uid' => $student->id, ':cid' => $class->id]);
            $taskRows = [];
            $tasksDone = 0;
            foreach ($taskStmt->fetchAll() as $t) {
                $done = (int) $t['submitted'] > 0;
                if ($done) {
                    $tasksDone++;
                }
                $taskRows[] = [
                    'title' => (string) $t['title'],
                    'type' => (string) $t['type'],
                    'done' => $done,
                    'submittedAt' => $t['submitted_at'] !== null ? (string) $t['submitted_at'] : null,
                ];
            }
            // Zuletzt bearbeitete Projekte aus der Werkstatt.
            $projStmt = $pdo->prepare(
                'SELECT title, type, language, updated_at FROM projects WHERE owner_id = ? ORDER BY updated_at DESC, id DESC LIMIT 3'
            );
            $projStmt->execute([$student->id]);
            $projectRows = [];
            foreach ($projStmt->fetchAll() as $p) {
                $projectRows[] = [
                    'title' => (string) $p['title'],
                    'type' => (string) $p['type'],
                    'language' => $p['language'] !== null ? (string) $p['language'] : '',
                    'updatedAt' => (string) $p['updated_at'],
                ];
            }
            $rows[] = [
                'student' => $student,
                'quizzes' => $quizRows,
                'quizDone' => $quizDone,
                'tasks' => $taskRows,
                'tasksDone' => $tasksDone,
                'projects' => $projectRows,
            ];
        }
        render('teacher_class_progress', [
            'title' => 'Fortschritt: Klasse ' . $class->name,
            'active' => 'unterricht',
            'class' => $class,
            'rows' => $rows,
        ]);
    }

    /** OTP für eine einzelne Schülerin/einen Schüler oder eine ganze Klasse erzeugen. */
    public static function resetPassword(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';

        $targets = [];
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            $class = SchoolClass::find($classId);
            if ($class === null || (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id))) {
                flash('error', 'Diese Klasse unterrichtest du nicht.');
                redirect('/unterricht/klassen');
            }
            $targets = User::byClass($classId);
            if ($targets === []) {
                flash('error', 'In dieser Klasse gibt es keine Schülerinnen und Schüler.');
                redirect('/unterricht/klassen');
            }
        } elseif ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student') {
                flash('error', 'Diese Person wurde nicht gefunden.');
                redirect('/unterricht/klassen');
            }
            if (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id))) {
                flash('error', 'Diese Schülerin bzw. dieser Schüler ist nicht in einer deiner Klassen.');
                redirect('/unterricht/klassen');
            }
            $targets = [$student];
        } else {
            flash('error', 'Keine Klasse oder Person angegeben.');
            redirect('/unterricht/klassen');
        }

        $rows = [];
        $className = null;
        foreach ($targets as $student) {
            $otp = Otp::issueTo($student);
            if ($student->classId !== null) {
                $class = SchoolClass::find($student->classId);
                $className = $class?->name;
            }
            $rows[] = [
                'name' => $student->fullName(),
                'class' => $className,
                'login' => $otp['login'],
                'otp' => $otp['otp'],
                'sentence' => $otp['sentence'],
            ];
        }
        $_SESSION['otp_results'] = $rows;
        flash('info', count($rows) === 1 ? 'Neues Einmalpasswort erzeugt.' : 'Neue Einmalpasswörter für ' . count($rows) . ' Personen erzeugt.');
        redirect('/unterricht/passwort/ergebnis');
    }

    /** OTP-Ergebnisse (einmalig) anzeigen, auch nach dem CSV-Import. */
    public static function otpResults(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        $rows = isset($_SESSION['otp_results']) && is_array($_SESSION['otp_results']) ? $_SESSION['otp_results'] : [];
        unset($_SESSION['otp_results']);
        render('otp_results', [
            'title' => 'Einmalpasswörter',
            'active' => 'unterricht',
            'rows' => $rows,
        ]);
    }

    /** (Administration) Lehrkraft einer Klasse zuordnen. */
    public static function assignTeacher(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isAdmin()) {
            flash('error', 'Nur die Administration kann Lehrkräfte Klassen zuordnen.');
            redirect('/unterricht/klassen');
        }
        csrf_validate();
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $teacher = User::find($teacherId);
        $class = SchoolClass::find($classId);
        if ($teacher === null || !in_array($teacher->role, ['teacher', 'admin'], true) || $class === null) {
            flash('error', 'Lehrkraft oder Klasse wurde nicht gefunden.');
            redirect('/unterricht/klassen');
        }
        SchoolClass::assignTeacher($teacherId, $classId);
        flash('success', $teacher->fullName() . ' unterrichtet jetzt ' . $class->name . '.');
        redirect('/unterricht/klassen');
    }

    /** Automatisches Erzeugen von Klassendiagrammen pro Klasse ein- oder ausschalten. */
    public static function toggleDiagramAuto(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $classId = (int) ($_POST['class_id'] ?? 0);
        $class = SchoolClass::find($classId);
        if ($class === null || (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id))) {
            flash('error', 'Diese Klasse unterrichtest du nicht.');
            redirect('/unterricht/klassen');
        }
        $enabled = (string) ($_POST['enabled'] ?? '') === '1';
        SchoolClass::setDiagramAuto($classId, $enabled);
        flash('success', 'Klasse ' . $class->name . ': Automatisches Erzeugen von Klassendiagrammen ist jetzt ' . ($enabled ? 'erlaubt.' : 'deaktiviert.'));
        redirect('/unterricht/klassen');
    }

    /** Sichtbarkeit des Mini-Datenbank-Fensters im Code-Bereich pro Klasse ein- oder ausschalten. */
    public static function toggleDbPanel(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $classId = (int) ($_POST['class_id'] ?? 0);
        $class = SchoolClass::find($classId);
        if ($class === null || (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id))) {
            flash('error', 'Diese Klasse unterrichtest du nicht.');
            redirect('/unterricht/klassen');
        }
        $enabled = (string) ($_POST['enabled'] ?? '') === '1';
        SchoolClass::setDbPanel($classId, $enabled);
        flash('success', 'Klasse ' . $class->name . ': Das Fenster Mini-Datenbank im Code-Bereich ist jetzt ' . ($enabled ? 'sichtbar.' : 'deaktiviert.'));
        redirect('/unterricht/klassen');
    }

    /**
     * Modul-Freigabe je Klasse: 'freigeben' = vorzeitig sichtbar,
     * 'sperren' = ausgeblendet, 'standard' = LehrplanPLUS-Stufe entscheidet.
     */
    public static function setModule(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $classId = (int) ($_POST['class_id'] ?? 0);
        $class = SchoolClass::find($classId);
        if ($class === null || (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id))) {
            flash('error', 'Diese Klasse unterrichtest du nicht.');
            redirect('/unterricht/klassen');
        }
        $slug = (string) ($_POST['module'] ?? '');
        $modules = \Infocraft\Controllers\ModuleController::modules();
        if (!isset($modules[$slug])) {
            flash('error', 'Dieses Modul gibt es nicht.');
            redirect('/unterricht/klassen');
        }
        $mode = (string) ($_POST['mode'] ?? 'standard');
        \Infocraft\Controllers\ModuleController::setMode($classId, $slug, $mode);
        $title = $modules[$slug]['title'];
        if ($mode === 'sperren') {
            flash('success', 'Klasse ' . $class->name . ': Das Modul ' . $title . ' ist jetzt für die Klasse gesperrt.');
        } elseif ($mode === 'freigeben') {
            flash('success', 'Klasse ' . $class->name . ': Das Modul ' . $title . ' ist jetzt für die Klasse freigegeben (auch unterhalb der Stufe).');
        } else {
            flash('success', 'Klasse ' . $class->name . ': Das Modul ' . $title . ' folgt wieder der Jahrgangsstufe im LehrplanPLUS.');
        }
        redirect('/unterricht/klassen');
    }
}
