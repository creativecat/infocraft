<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Prüfungsmodus: Anlegen, Durchlauf, Korrektur
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Config;
use Infocraft\Models\Exam;
use Infocraft\Models\Project;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

/**
 * Pruefungen und Schularbeiten: vorbereiten, durchfuehren, korrigieren,
 * herausgeben. Waehrend der Durchfuehrung einer Pruefung sperrt guard()
 * fuer Schuelerinnen und Schueler alle anderen Bereiche.
 */
final class ExamController
{
    /**
     * Pruefungssperre: Schueler mit laufender Pruefung duerfen nur die
     * Pruefungsseiten, Anmeldung/Abmeldung und genau ihr eigenes
     * Arbeitsprojekt oeffnen. Wird in public/index.php vor dem Dispatch
     * aufgerufen.
     */
    public static function guard(string $path): void
    {
        $user = Auth::user();
        if ($user === null || $user->role !== 'student') {
            return;
        }
        $lock = Exam::lockForUser($user->id);
        if ($lock === null) {
            return;
        }
        $allowed = $path === '/' || $path === '/login' || $path === '/logout'
            || str_starts_with($path, '/auth/') || str_starts_with($path, '/pruefungen');
        if (!$allowed && (str_starts_with($path, '/werkstatt/code/') || str_starts_with($path, '/werkstatt/html/'))) {
            if (preg_match('#^/werkstatt/(?:code|html)/(\d+)#', $path, $matches) === 1) {
                $allowed = $lock['projectId'] !== null && (int) $matches[1] === $lock['projectId'];
            }
        }
        if (!$allowed) {
            flash('error', 'Prüfungsmodus: Während der Prüfung sind alle anderen Bereiche gesperrt.');
            redirect('/pruefungen');
        }
    }

    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            render('exam_overview', [
                'title' => 'Prüfungen &amp; Arbeiten',
                'active' => 'module-pruefung',
                'isTeacher' => true,
                'exams' => Exam::forTeacher($user->id),
            ]);
            return;
        }
        if ((Auth::levelOf($user) ?? 0) < 6) {
            redirect('/');
        }
        $entries = [];
        foreach (Exam::forStudent($user->id) as $exam) {
            $entries[] = [
                'exam' => $exam,
                'work' => Exam::workFor($exam->id, $user->id),
            ];
        }
        render('exam_overview', [
            'title' => 'Prüfungen &amp; Arbeiten',
            'active' => 'module-pruefung',
            'isTeacher' => false,
            'exams' => $entries,
        ]);
    }

    public static function create(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Prüfungen und Schularbeiten anlegen.');
            redirect('/');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/pruefungen');
        }
        $kind = ($_POST['kind'] ?? '') === 'arbeit' ? 'arbeit' : 'pruefung';
        $level = (int) ($_POST['level'] ?? 6);
        if ($level < 6 || $level > 13) {
            flash('error', 'Klassenstufe 6 bis 13 auswählen.');
            redirect('/pruefungen');
        }
        $exam = Exam::create($user->id, $kind, $title, (string) ($_POST['description'] ?? ''), $level);
        flash('success', '\'' . $exam->title . '\' angelegt. Jetzt Gruppen, Vorlage und Teilaufgaben vorbereiten.');
        redirect('/pruefungen/' . $exam->id);
    }

    public static function manage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        render('exam_manage', [
            'title' => $exam->title,
            'active' => 'module-pruefung',
            'exam' => $exam,
            'groups' => Exam::groups($exam->id),
            'classes' => SchoolClass::forTeacher($user->id, $user->role === 'admin'),
            'studentsByClass' => self::studentsByClass($user),
            'templateProjects' => Project::all($user->id),
            'tasks' => Exam::tasks($exam->id),
            'totalBe' => Exam::totalBe($exam->id),
            'works' => Exam::works($exam->id),
        ]);
    }

    public static function addGroup(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'vorbereitung') {
            flash('error', 'Gruppen können nur in der Vorbereitung geändert werden.');
            redirect('/pruefungen/' . $exam->id);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Bitte einen Gruppennamen angeben, z. B. Gruppe A.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::addGroup($exam->id, $name);
        flash('success', 'Gruppe \'' . $name . '\' angelegt.');
        redirect('/pruefungen/' . $exam->id);
    }

    public static function addMember(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'vorbereitung') {
            flash('error', 'Mitglieder können nur in der Vorbereitung geändert werden.');
            redirect('/pruefungen/' . $exam->id);
        }
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $memberId = (int) ($_POST['user_id'] ?? 0);
        if (!Exam::groupBelongsTo($groupId, $exam->id)) {
            flash('error', 'Diese Gruppe gehört nicht zu der Prüfung.');
            redirect('/pruefungen/' . $exam->id);
        }
        $member = User::find($memberId);
        if ($member === null || $member->role !== 'student') {
            flash('error', 'Bitte eine Schülerin oder einen Schüler auswählen.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::addMember($groupId, $memberId);
        flash('success', $member->fullName() . ' ist dabei.');
        redirect('/pruefungen/' . $exam->id);
    }

    public static function setTemplate(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'vorbereitung') {
            flash('error', 'Vorlagen können nur in der Vorbereitung geändert werden.');
            redirect('/pruefungen/' . $exam->id);
        }
        $groupId = (int) ($_POST['group_id'] ?? 0);
        if (!Exam::groupBelongsTo($groupId, $exam->id)) {
            flash('error', 'Diese Gruppe gehört nicht zu der Prüfung.');
            redirect('/pruefungen/' . $exam->id);
        }
        $projectId = (int) ($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            Exam::setTemplate($groupId, null);
            flash('info', 'Vorlage entfernt.');
            redirect('/pruefungen/' . $exam->id);
        }
        $project = Project::find($projectId);
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Nur eigene Projekte sind als Vorlage möglich.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::setTemplate($groupId, $projectId);
        flash('success', 'Vorlage \'' . $project->title . '\' festgelegt. Jedes Gruppenmitglied bekommt beim Start eine eigene Kopie.');
        redirect('/pruefungen/' . $exam->id);
    }

    public static function addTask(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'vorbereitung') {
            flash('error', 'Teilaufgaben können nur in der Vorbereitung geändert werden.');
            redirect('/pruefungen/' . $exam->id);
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $be = (int) ($_POST['be'] ?? 0);
        if ($title === '' || $be <= 0 || $be > 100) {
            flash('error', 'Teilaufgabe braucht einen Titel und 1 bis 100 BE.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::addTask($exam->id, $title, (string) ($_POST['description'] ?? ''), $be, (string) ($_POST['sample_solution'] ?? ''));
        flash('success', 'Teilaufgabe \'' . $title . '\' ergänzt (' . $be . ' BE).');
        redirect('/pruefungen/' . $exam->id);
    }

    public static function start(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'vorbereitung') {
            flash('error', 'Die Prüfung wurde bereits gestartet.');
            redirect('/pruefungen/' . $exam->id);
        }
        if (Exam::tasks($exam->id) === []) {
            flash('error', 'Ohne Teilaufgaben kann die Prüfung nicht starten.');
            redirect('/pruefungen/' . $exam->id);
        }
        $count = 0;
        foreach (Exam::groups($exam->id) as $group) {
            if ($group['members'] === []) {
                continue;
            }
            $template = $group['templateProjectId'] !== null ? Project::find($group['templateProjectId']) : null;
            foreach ($group['members'] as $member) {
                $projectId = null;
                if ($template !== null) {
                    $projectId = Project::create($member->id, null, $exam->title, $template->type, $template->language, $template->content, $user->id)->id;
                }
                Exam::createWork($exam->id, $member->id, $group['id'], $projectId);
                $count++;
            }
        }
        if ($count === 0) {
            flash('error', 'Es gibt keine Gruppe mit Mitgliedern. Ohne Teilnehmer kein Start.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::setPhase($exam->id, 'durchfuehrung');
        Exam::markTimestamp($exam->id, 'started_at');
        $message = $exam->kind === 'pruefung'
            ? 'Die Prüfung läuft: ' . $count . ' Arbeiten ausgeteilt. Für die Schülerinnen und Schüler sind jetzt alle anderen Bereiche gesperrt.'
            : 'Die Schularbeit läuft: ' . $count . ' Arbeiten ausgeteilt.';
        flash('success', $message);
        redirect('/pruefungen/' . $exam->id);
    }

    public static function end(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'durchfuehrung') {
            flash('error', 'Nur eine laufende Prüfung kann beendet werden.');
            redirect('/pruefungen/' . $exam->id);
        }
        Exam::setPhase($exam->id, 'korrektur');
        flash('info', 'Abgabe beendet, die Sperre ist aufgehoben. Die Korrektur kann jetzt starten.');
        redirect('/pruefungen/' . $exam->id . '/korrektur');
    }

    public static function work(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireMember($user, (int) ($params['id'] ?? 0));
        if ($exam->phase === 'vorbereitung') {
            flash('info', 'Die Prüfung wurde noch nicht gestartet.');
            redirect('/pruefungen');
        }
        if ($exam->phase === 'korrektur') {
            flash('info', 'Die Abgabe ist beendet, deine Arbeit wird korrigiert.');
            redirect('/pruefungen');
        }
        if ($exam->phase === 'herausgegeben') {
            redirect('/pruefungen/' . $exam->id . '/ergebnis');
        }
        $work = self::ensureWork($exam, $user);
        $project = $work['project_id'] !== null ? Project::find($work['project_id']) : null;
        render('exam_work', [
            'title' => $exam->title,
            'active' => 'module-pruefung',
            'exam' => $exam,
            'work' => $work,
            'project' => $project,
            'tasks' => Exam::tasks($exam->id),
            'answers' => Exam::answersFor($exam->id, $user->id),
        ]);
    }

    /** Autosave-Endpunkt fuer die Teilaufgaben-Antworten. */
    public static function saveAnswer(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireMember($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        header('Content-Type: application/json; charset=utf-8');
        if ($exam->phase !== 'durchfuehrung') {
            echo json_encode(['ok' => false, 'error' => 'Die Prüfung läuft nicht.']);
            exit;
        }
        $work = Exam::workFor($exam->id, $user->id);
        if ($work !== null && $work['submitted_at'] !== null) {
            echo json_encode(['ok' => false, 'error' => 'Deine Arbeit ist bereits abgegeben.']);
            exit;
        }
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $valid = false;
        foreach (Exam::tasks($exam->id) as $task) {
            if ($task['id'] === $taskId) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            echo json_encode(['ok' => false, 'error' => 'Unbekannte Teilaufgabe.']);
            exit;
        }
        Exam::saveAnswer($taskId, $user->id, (string) ($_POST['answer'] ?? ''));
        echo json_encode(['ok' => true]);
        exit;
    }

    public static function submit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireMember($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'durchfuehrung') {
            flash('error', 'Die Prüfung läuft nicht.');
            redirect('/pruefungen');
        }
        self::ensureWork($exam, $user);
        Exam::submit($exam->id, $user->id);
        flash('success', 'Deine Arbeit ist abgegeben. Viel Erfolg!');
        redirect('/pruefungen');
    }

    /** Ergebnis fuer Schuelerinnen und Schueler nach der Herausgabe. */
    public static function result(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireMember($user, (int) ($params['id'] ?? 0));
        if ($exam->phase !== 'herausgegeben') {
            flash('error', 'Das Ergebnis wurde noch nicht freigegeben.');
            redirect('/pruefungen');
        }
        $totals = Exam::totalsFor($exam->id, $user->id);
        $percent = $totals['total'] > 0 ? $totals['reached'] / $totals['total'] * 100.0 : 0.0;
        render('exam_result', [
            'title' => 'Ergebnis: ' . $exam->title,
            'active' => 'module-pruefung',
            'exam' => $exam,
            'work' => Exam::workFor($exam->id, $user->id),
            'tasks' => Exam::tasks($exam->id),
            'answers' => Exam::answersFor($exam->id, $user->id),
            'gradings' => Exam::gradingsFor($exam->id, $user->id),
            'totals' => $totals,
            'note' => Exam::gradeFor($percent),
        ]);
    }

    /** Korrekturuebersicht aller Arbeiten einer Pruefung. */
    public static function correction(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        $tasks = Exam::tasks($exam->id);
        $works = Exam::works($exam->id);
        $summary = [];
        $allGraded = $works !== [] && $tasks !== [];
        foreach ($works as $work) {
            $gradings = Exam::gradingsFor($exam->id, $work['userId']);
            $unchecked = 0;
            $apiCount = 0;
            foreach ($gradings as $grading) {
                if (!$grading['checked']) {
                    $unchecked++;
                }
                if ($grading['source'] === 'api') {
                    $apiCount++;
                }
            }
            $totals = Exam::totalsFor($exam->id, $work['userId']);
            $percent = $totals['total'] > 0 ? $totals['reached'] / $totals['total'] * 100.0 : 0.0;
            $summary[] = [
                'work' => $work,
                'totals' => $totals,
                'note' => Exam::gradeFor($percent),
                'graded' => count($gradings),
                'unchecked' => $unchecked,
                'apiCount' => $apiCount,
            ];
            if (count($gradings) < count($tasks) || $unchecked > 0) {
                $allGraded = false;
            }
        }
        $api = Config::get('exam.api');
        render('exam_correction', [
            'title' => 'Korrektur: ' . $exam->title,
            'active' => 'module-pruefung',
            'exam' => $exam,
            'tasks' => $tasks,
            'summary' => $summary,
            'allGraded' => $allGraded,
            'apiConfigured' => is_array($api) && trim((string) ($api['url'] ?? '')) !== '',
        ]);
    }

    /** Detailansicht: eine einzelne Arbeit korrigieren. */
    public static function correct(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        $student = User::find((int) ($params['userId'] ?? 0));
        if ($student === null || !Exam::isMember($exam->id, $student->id)) {
            self::notFound();
        }
        $work = Exam::workFor($exam->id, $student->id);
        $projectContent = null;
        if ($work !== null && $work['project_id'] !== null) {
            $project = Project::find($work['project_id']);
            if ($project !== null) {
                $projectContent = $project->content;
            }
        }
        render('exam_correct', [
            'title' => 'Korrektur: ' . $student->fullName(),
            'active' => 'module-pruefung',
            'exam' => $exam,
            'student' => $student,
            'work' => $work,
            'tasks' => Exam::tasks($exam->id),
            'answers' => Exam::answersFor($exam->id, $student->id),
            'gradings' => Exam::gradingsFor($exam->id, $student->id),
            'projectContent' => $projectContent,
        ]);
    }

    /** Bewertungen einer Arbeit speichern (immer Quelle lk, geprueft). */
    public static function saveGrading(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'korrektur') {
            flash('error', 'Bewertet werden kann nur in der Korrekturphase.');
            redirect('/pruefungen/' . $exam->id);
        }
        $studentId = (int) ($_POST['user_id'] ?? 0);
        $student = User::find($studentId);
        if ($student === null || !Exam::isMember($exam->id, $student->id)) {
            self::notFound();
        }
        $saved = 0;
        foreach (Exam::tasks($exam->id) as $task) {
            $field = 'be_' . $task['id'];
            if (!isset($_POST[$field])) {
                continue;
            }
            $be = (float) str_replace(',', '.', (string) $_POST[$field]);
            $be = max(0.0, min($be, (float) $task['be']));
            $comment = (string) ($_POST['comment_' . $task['id']] ?? '');
            Exam::saveGrading($task['id'], $student->id, $be, $comment, 'lk', true);
            $saved++;
        }
        flash($saved > 0 ? 'success' : 'error', $saved > 0 ? 'Bewertung gespeichert und als geprüft markiert.' : 'Keine Bewertung übernommen.');
        redirect('/pruefungen/' . $exam->id . '/korrektur/' . $student->id);
    }

    /**
     * Anonymisierte API-Korrektur fuer alle Arbeiten der Pruefung.
     * Vorschlaege werden als Quelle api ohne Geprueft-Haken gespeichert
     * und muessen von der Lehrkraft gegengeprueft werden.
     */
    public static function apiCorrection(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'korrektur') {
            flash('error', 'Die API-Korrektur läuft nur in der Korrekturphase.');
            redirect('/pruefungen/' . $exam->id . '/korrektur');
        }
        $api = Config::get('exam.api');
        if (!is_array($api) || trim((string) ($api['url'] ?? '')) === '') {
            flash('error', 'API-Korrektur ist nicht konfiguriert: In config/config.php unter exam.api url (und mode) eintragen.');
            redirect('/pruefungen/' . $exam->id . '/korrektur');
        }
        $mode = ($api['mode'] ?? 'json') === 'chat' ? 'chat' : 'json';
        $endpoint = (string) $api['url'];
        $apiKey = (string) ($api['key'] ?? '');
        $model = (string) ($api['model'] ?? '');
        $tasks = Exam::tasks($exam->id);
        $works = Exam::works($exam->id);
        if ($tasks === [] || $works === []) {
            flash('error', 'Ohne Teilaufgaben oder Arbeiten gibt es nichts zu korrigieren.');
            redirect('/pruefungen/' . $exam->id . '/korrektur');
        }
        // Pseudonyme vergeben: die API sieht keine Namen.
        $pseudonyms = [];
        $letter = 'A';
        foreach ($works as $work) {
            $pseudonyms[$work['userId']] = 'Arbeit ' . $letter;
            $letter++;
        }
        $payloadTasks = [];
        foreach ($tasks as $task) {
            $payloadTasks[] = [
                'position' => $task['position'],
                'be' => $task['be'],
                'sample_solution' => $task['sample_solution'],
            ];
        }
        $payloadWorks = [];
        foreach ($works as $work) {
            $material = '';
            if ($work['projectId'] !== null) {
                $project = Project::find($work['projectId']);
                if ($project !== null) {
                    $material = $project->content;
                }
            }
            if (mb_strlen($material) > 20000) {
                $material = mb_substr($material, 0, 20000);
            }
            $answers = Exam::answersFor($exam->id, $work['userId']);
            $answerList = [];
            foreach ($tasks as $task) {
                $answerList[] = ['position' => $task['position'], 'answer' => $answers[$task['id']] ?? ''];
            }
            $payloadWorks[] = [
                'pseudonym' => $pseudonyms[$work['userId']],
                'answers' => $answerList,
                'material' => $material,
            ];
        }
        $payload = json_encode([
            'exam' => ['title' => $exam->title, 'kind' => $exam->kind],
            'tasks' => $payloadTasks,
            'works' => $payloadWorks,
        ], JSON_UNESCAPED_UNICODE);
        $result = null;
        try {
            if ($mode === 'chat') {
                $prompt = 'Bewerte die eingereichten Schülerarbeiten. Vergib pro Teilaufgabe BE (Bewertungseinheiten) und schreibe kurzes, freundliches Feedback. Antworte ausschließlich mit JSON im Format {"results":[{"pseudonym":"Arbeit A","tasks":[{"position":1,"be":3.5,"feedback":"..."}]}]}. Daten: ' . $payload;
                $body = json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]]], JSON_UNESCAPED_UNICODE);
                $response = self::httpPost($endpoint, $body, $apiKey);
                $decoded = json_decode($response, true);
                $content = is_array($decoded) ? (string) ($decoded['choices'][0]['message']['content'] ?? '') : '';
                if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
                    $result = json_decode($matches[0], true);
                }
            } else {
                $response = self::httpPost($endpoint, $payload, $apiKey);
                $result = json_decode($response, true);
            }
        } catch (\RuntimeException $e) {
            flash('error', 'API-Aufruf fehlgeschlagen: ' . $e->getMessage());
            redirect('/pruefungen/' . $exam->id . '/korrektur');
        }
        if (!is_array($result) || !is_array($result['results'] ?? null)) {
            flash('error', 'Die API-Antwort war unbrauchbar, es wurde nichts gespeichert.');
            redirect('/pruefungen/' . $exam->id . '/korrektur');
        }
        $taskByPosition = [];
        foreach ($tasks as $task) {
            $taskByPosition[$task['position']] = $task;
        }
        $applied = 0;
        foreach ($result['results'] as $entry) {
            $pseudonym = (string) ($entry['pseudonym'] ?? '');
            $userId = array_search($pseudonym, $pseudonyms, true);
            if ($userId === false) {
                continue;
            }
            foreach ((array) ($entry['tasks'] ?? []) as $gradedTask) {
                $position = (int) ($gradedTask['position'] ?? 0);
                if (!isset($taskByPosition[$position])) {
                    continue;
                }
                $task = $taskByPosition[$position];
                $be = (float) ($gradedTask['be'] ?? 0);
                $be = max(0.0, min($be, (float) $task['be']));
                $feedback = (string) ($gradedTask['feedback'] ?? '');
                Exam::saveGrading($task['id'], (int) $userId, $be, $feedback, 'api', false);
                $applied++;
            }
        }
        flash($applied > 0 ? 'success' : 'error', $applied > 0 ? 'API-Vorschläge gespeichert (' . $applied . ' Bewertungen, anonymisiert). Alle Vorschläge müssen noch gegengeprüft werden.' : 'Die API hat keine passenden Bewertungen geliefert.');
        redirect('/pruefungen/' . $exam->id . '/korrektur');
    }

    public static function release(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $exam = self::requireOwner($user, (int) ($params['id'] ?? 0));
        csrf_validate();
        if ($exam->phase !== 'korrektur') {
            flash('error', 'Herausgegeben werden kann nur aus der Korrekturphase.');
            redirect('/pruefungen/' . $exam->id);
        }
        $tasks = Exam::tasks($exam->id);
        foreach (Exam::works($exam->id) as $work) {
            $gradings = Exam::gradingsFor($exam->id, $work['userId']);
            if (count($gradings) < count($tasks)) {
                flash('error', 'Noch nicht alle Arbeiten sind vollständig bewertet.');
                redirect('/pruefungen/' . $exam->id . '/korrektur');
            }
            foreach ($gradings as $grading) {
                if (!$grading['checked']) {
                    flash('error', 'Es gibt noch ungeprüfte API-Vorschläge. Bitte alle Bewertungen gegenprüfen und speichern.');
                    redirect('/pruefungen/' . $exam->id . '/korrektur');
                }
            }
        }
        Exam::setPhase($exam->id, 'herausgegeben');
        Exam::markTimestamp($exam->id, 'released_at');
        flash('success', 'Herausgegeben: Die Schülerinnen und Schüler sehen jetzt Punkte, Note und Musterlösung im Vergleich.');
        redirect('/pruefungen/' . $exam->id);
    }

    /** Pruefung einer Lehrkraft (Admins sehen alle). */
    private static function requireOwner(User $user, int $examId): Exam
    {
        $exam = Exam::find($examId);
        if ($exam === null || ($exam->teacherId !== $user->id && $user->role !== 'admin')) {
            self::notFound();
        }
        return $exam;
    }

    /** Pruefung, an der diese Person teilnimmt. */
    private static function requireMember(User $user, int $examId): Exam
    {
        $exam = Exam::find($examId);
        if ($exam === null || !Exam::isMember($exam->id, $user->id)) {
            self::notFound();
        }
        return $exam;
    }

    private static function ensureWork(Exam $exam, User $user): array
    {
        $work = Exam::workFor($exam->id, $user->id);
        if ($work !== null) {
            return $work;
        }
        $group = Exam::memberGroup($exam->id, $user->id);
        $projectId = null;
        if ($group !== null && $group['templateProjectId'] !== null) {
            $template = Project::find($group['templateProjectId']);
            if ($template !== null) {
                $projectId = Project::create($user->id, null, $exam->title, $template->type, $template->language, $template->content, $exam->teacherId)->id;
            }
        }
        Exam::createWork($exam->id, $user->id, $group['id'] ?? null, $projectId);
        return Exam::workFor($exam->id, $user->id) ?? throw new \RuntimeException('Arbeit konnte nicht angelegt werden.');
    }

    /** @return array<int, list<User>> classId => Schueler */
    private static function studentsByClass(User $user): array
    {
        $map = [];
        foreach (SchoolClass::forTeacher($user->id, $user->role === 'admin') as $class) {
            $map[$class->id] = User::byClass($class->id);
        }
        return $map;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Prüfung gibt es nicht oder du hast keinen Zugriff darauf.']);
        exit;
    }

    private static function httpPost(string $url, string $body, string $apiKey): string
    {
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 60,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Der Endpunkt ist nicht erreichbar.');
        }
        return $response;
    }
}
