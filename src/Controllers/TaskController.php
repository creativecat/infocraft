<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\AiSettings;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\Task;
use Infocraft\Models\TaskCatalog;
use Infocraft\Models\TaskGrading;
use Infocraft\Models\TaskSubmission;
use Infocraft\Models\User;
use RuntimeException;

final class TaskController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            $tasks = Task::forTeacher($user->id);
            $targets = [];
            foreach ($tasks as $task) {
                $targets[$task->id] = Task::targets($task->id);
            }
            $filterLevel = max(0, min(13, (int) ($_GET['level'] ?? 0)));
            $filterType = (string) ($_GET['type'] ?? '');
            if (!in_array($filterType, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
                $filterType = '';
            }
            $catalog = TaskCatalog::all();
            if ($filterLevel > 0) {
                $catalog = array_values(array_filter($catalog, static fn (array $entry): bool => $entry['level'] === $filterLevel));
            }
            if ($filterType !== '') {
                $catalog = array_values(array_filter($catalog, static fn (array $entry): bool => $entry['type'] === $filterType));
            }
            $pool = Task::pool($user->id);
            if ($filterLevel > 0) {
                $pool = array_values(array_filter($pool, static fn (array $entry): bool => $entry['task']->level === $filterLevel));
            }
            if ($filterType !== '') {
                $pool = array_values(array_filter($pool, static fn (array $entry): bool => $entry['task']->type === $filterType));
            }
            render('tasks_teacher', [
                'title' => 'Aufgabenpool',
                'active' => 'tasks',
                'tasks' => $tasks,
                'targets' => $targets,
                'classes' => SchoolClass::forTeacher($user->id, $user->role === 'admin'),
                'studentsByClass' => self::studentsByClass($user),
                'catalog' => $catalog,
                'pool' => $pool,
                'filterLevel' => $filterLevel,
                'filterType' => $filterType,
                'ownedTitles' => array_map(static fn (Task $task): string => mb_strtolower(trim($task->title)), $tasks),
                'aiConfigured' => AiSettings::resolve($user->id) !== null,
                'user' => $user,
            ]);
            return;
        }
        $level = Auth::levelOf($user) ?? 0;
        render('tasks_student', [
            'title' => 'Meine Aufgaben',
            'active' => 'module-tasks',
            'tasks' => Task::forStudent($user->id, $user->classId, $level),
            'user' => $user,
        ]);
    }

    public static function create(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben anlegen.');
            redirect('/');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/aufgaben');
        }
        $type = (string) ($_POST['type'] ?? 'allgemein');
        if (!in_array($type, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
            $type = 'allgemein';
        }
        $level = (int) ($_POST['level'] ?? 6);
        Task::create($user->id, $title, (string) ($_POST['description'] ?? ''), $type, $level);
        flash('success', 'Aufgabe angelegt.');
        redirect('/aufgaben');
    }

    /** Formular zum Bearbeiten einer eigenen Aufgabe. */
    public static function edit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben bearbeiten.');
            redirect('/');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        render('tasks_edit', [
            'title' => 'Aufgabe bearbeiten',
            'active' => 'tasks',
            'task' => $task,
            'user' => $user,
        ]);
    }

    /** Änderungen einer eigenen Aufgabe speichern. */
    public static function update(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben bearbeiten.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $type = (string) ($_POST['type'] ?? 'allgemein');
        if (!in_array($type, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
            $type = 'allgemein';
        }
        $level = (int) ($_POST['level'] ?? 6);
        Task::update($task->id, $title, (string) ($_POST['description'] ?? ''), $type, $level, (string) ($_POST['sample_solution'] ?? ''));
        flash('success', 'Aufgabe gespeichert.');
        redirect('/aufgaben');
    }

    /** Eigene Aufgabe in den gemeinsamen Pool aller Lehrkräfte teilen oder zurückziehen. */
    public static function share(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben teilen.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $share = ($_POST['shared'] ?? '') === '1';
        Task::setShared($task->id, $share);
        flash('success', $share
            ? 'Aufgabe ist jetzt im gemeinsamen Pool aller Lehrkräfte sichtbar.'
            : 'Aufgabe ist nicht mehr im gemeinsamen Pool geteilt.');
        redirect('/aufgaben');
    }

    /** Aufgabe aus dem gemeinsamen Pool als eigene Kopie übernehmen. */
    public static function adopt(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben übernehmen.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || $task->shared !== 1 || $task->teacherId === $user->id) {
            flash('error', 'Diese geteilte Aufgabe gibt es nicht (mehr).');
            redirect('/aufgaben');
        }
        if (Task::hasTitle($user->id, $task->title)) {
            flash('error', 'Diese Aufgabe hast du schon im Pool.');
            redirect('/aufgaben');
        }
        Task::adopt($task, $user->id);
        flash('success', 'Aufgabe als Kopie übernommen. Du kannst sie frei anpassen, das Original bleibt unberührt.');
        redirect('/aufgaben');
    }

    /**
     * Fertige Aufgabe aus dem Katalog in den eigenen Pool uebernehmen.
     * Bereits vorhandene Titel werden nicht doppelt angelegt.
     */
    public static function import(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben übernehmen.');
            redirect('/');
        }
        csrf_validate();
        $entry = TaskCatalog::find((int) ($_POST['template_id'] ?? 0));
        if ($entry === null) {
            flash('error', 'Diese Katalog-Aufgabe gibt es nicht.');
            redirect('/aufgaben');
        }
        if (Task::hasTitle($user->id, $entry['title'])) {
            flash('error', 'Diese Aufgabe hast du schon im Pool.');
            redirect('/aufgaben');
        }
        Task::create($user->id, $entry['title'], $entry['description'], $entry['type'], $entry['level']);
        flash('success', 'Aufgabe aus dem Katalog übernommen.');
        redirect('/aufgaben');
    }

    /**
     * Automatisierung: alle Katalog-Aufgaben einer Jahrgangsstufe auf einmal
     * in den eigenen Pool uebernehmen (vorhandene Titel werden uebersprungen).
     */
    public static function importLevel(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben übernehmen.');
            redirect('/');
        }
        csrf_validate();
        $level = (int) ($_POST['level'] ?? 0);
        if ($level < 6 || $level > 13) {
            flash('error', 'Bitte eine Jahrgangsstufe zwischen 6 und 13 angeben.');
            redirect('/aufgaben');
        }
        $count = 0;
        foreach (TaskCatalog::forLevel($level) as $entry) {
            if (!Task::hasTitle($user->id, $entry['title'])) {
                Task::create($user->id, $entry['title'], $entry['description'], $entry['type'], $entry['level']);
                $count++;
            }
        }
        if ($count === 0) {
            flash('error', 'Dein Pool enthält alle Aufgaben dieser Jahrgangsstufe bereits.');
        } else {
            flash('success', $count . ' Aufgabe' . ($count === 1 ? '' : 'n') . ' der Jahrgangsstufe ' . $level . ' übernommen.');
        }
        redirect('/aufgaben?level=' . $level);
    }

    public static function delete(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben löschen.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        Task::delete($task->id);
        flash('success', 'Aufgabe gelöscht.');
        redirect('/aufgaben');
    }

    /** Aufgabe austeilen, mit optionalem Abgabedatum und Uhrzeit. */
    public static function assign(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben austeilen.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $task = Task::find((int) ($_POST['task_id'] ?? 0));
        if ($task === null || $task->teacherId !== $user->id) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $dueAt = self::parseDueAt((string) ($_POST['due_at'] ?? ''));
        if ($dueAt === false) {
            flash('error', 'Bitte den Abgabetermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30).');
            redirect('/aufgaben');
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                flash('error', 'Du unterrichtest diese Klasse nicht.');
                redirect('/aufgaben');
            }
            Task::assign($task->id, $classId, null, $dueAt);
            flash('success', 'Aufgabe an die Klasse ausgeteilt.');
            redirect('/aufgaben');
        }
        if ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student' || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id)))) {
                flash('error', 'Diese Person ist nicht in einer deiner Klassen.');
                redirect('/aufgaben');
            }
            Task::assign($task->id, null, $student->id, $dueAt);
            flash('success', 'Aufgabe ausgeteilt.');
            redirect('/aufgaben');
        }
        flash('error', 'Kein Ziel angegeben.');
        redirect('/aufgaben');
    }

    /** Abgabetermin einer bestehenden Zuweisung ändern oder streichen. */
    public static function dueDate(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Abgabetermine setzen.');
            redirect('/');
        }
        csrf_validate();
        $target = Task::findTarget((int) ($_POST['target_id'] ?? 0));
        if ($target === null) {
            flash('error', 'Zuweisung nicht gefunden.');
            redirect('/aufgaben');
        }
        $task = Task::find((int) $target['task_id']);
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Zuweisung nicht gefunden.');
            redirect('/aufgaben');
        }
        $dueAt = self::parseDueAt((string) ($_POST['due_at'] ?? ''));
        if ($dueAt === false) {
            flash('error', 'Bitte den Abgabetermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30).');
            redirect('/aufgaben');
        }
        Task::setTargetDueAt((int) $target['id'], $dueAt);
        flash('success', $dueAt === null ? 'Abgabetermin gestrichen.' : 'Abgabetermin gespeichert.');
        redirect('/aufgaben');
    }

    /** Schülerin oder Schüler gibt eine Lösung ab (überschreibt die bisherige). */
    public static function submit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            flash('error', 'Abgeben können nur Schülerinnen und Schüler.');
            redirect('/aufgaben');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !self::taskReachesStudent($task, $user)) {
            flash('error', 'Diese Aufgabe ist nicht für dich ausgeteilt.');
            redirect('/aufgaben');
        }
        $grading = TaskGrading::find($task->id, $user->id);
        if ($grading !== null && ($grading['source'] === 'lk' || $grading['checked'])) {
            flash('error', 'Diese Aufgabe ist bereits korrigiert, eine neue Abgabe ist nicht mehr nötig.');
            redirect('/aufgaben');
        }
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            flash('error', 'Bitte schreibe deine Lösung in das Feld.');
            redirect('/aufgaben');
        }
        TaskSubmission::upsert($task->id, $user->id, $content);
        $entries = Task::forStudent($user->id, $user->classId, Auth::levelOf($user) ?? 0);
        $dueAt = null;
        foreach ($entries as $entry) {
            if ($entry['task']->id === $task->id) {
                $dueAt = $entry['dueAt'];
                break;
            }
        }
        if ($dueAt !== null && date('Y-m-d H:i') > $dueAt) {
            flash('success', 'Abgabe gespeichert, aber verspätet (Abgabetermin war ' . $dueAt . ' Uhr).');
        } else {
            flash('success', 'Abgabe gespeichert. Du kannst sie bis zum Abgabetermin überschreiben.');
        }
        redirect('/aufgaben');
    }

    /** Korrekturübersicht einer Aufgabe für die Lehrkraft. */
    public static function correction(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können korrigieren.');
            redirect('/aufgaben');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $recipients = self::recipients($task);
        $submissions = TaskSubmission::forTask($task->id);
        $gradings = TaskGrading::forTask($task->id);
        $targets = Task::targets($task->id);
        $rows = [];
        foreach ($recipients as $student) {
            $submission = $submissions[$student->id] ?? null;
            $grading = $gradings[$student->id] ?? null;
            // Frühester Abgabetermin, der für diese Schülerin bzw. diesen
            // Schüler gilt (Klasse oder Einzelzuweisung).
            $dueAt = null;
            foreach ($targets as $target) {
                if (($target['classId'] !== null && $target['classId'] === $student->classId) || $target['userId'] === $student->id) {
                    if ($target['dueAt'] !== null && ($dueAt === null || $target['dueAt'] < $dueAt)) {
                        $dueAt = $target['dueAt'];
                    }
                }
            }
            $late = $submission !== null && $dueAt !== null && substr($submission['submittedAt'], 0, 16) > $dueAt;
            $rows[] = [
                'student' => $student,
                'submission' => $submission,
                'grading' => $grading,
                'dueAt' => $dueAt,
                'late' => $late,
            ];
        }
        render('tasks_correction', [
            'title' => 'Korrektur: ' . $task->title,
            'active' => 'tasks',
            'task' => $task,
            'rows' => $rows,
            'sampleSolution' => $task->sampleSolution,
            'aiConfigured' => AiSettings::resolve($user->id) !== null,
            'user' => $user,
        ]);
    }

    /** Korrektur (Note und Kommentar) für eine oder mehrere Abgaben speichern. */
    public static function grade(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können korrigieren.');
            redirect('/aufgaben');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $recipients = self::recipients($task);
        $saved = 0;
        foreach ($recipients as $student) {
            $grade = trim((string) ($_POST['grade_' . $student->id] ?? ''));
            $comment = trim((string) ($_POST['comment_' . $student->id] ?? ''));
            if ($grade === '' && $comment === '') {
                continue;
            }
            TaskGrading::save($task->id, $student->id, mb_substr($grade, 0, 10), mb_substr($comment, 0, 2000), 'lk', true);
            $saved++;
        }
        flash($saved > 0 ? 'success' : 'error', $saved > 0
            ? 'Korrektur gespeichert (' . $saved . 'x). Die Schülerinnen und Schüler sehen Note und Feedback.'
            : 'Es wurde keine Korrektur eingegeben.');
        redirect('/aufgaben/' . $task->id . '/korrektur');
    }

    /**
     * KI-Erstbewertung für alle noch nicht endgültig korrigierten Abgaben
     * einer Aufgabe. Die KI sieht anonymisierte Arbeiten (Pseudonyme statt
     * Namen) und liefert Note-Vorschläge und kurzes Feedback als Vorschlag
     * (Quelle api, nicht geprüft), den die Lehrkraft übernehmen muss.
     */
    public static function aiGrade(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können die KI-Bewertung starten.');
            redirect('/aufgaben');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $settings = AiSettings::resolve($user->id);
        if ($settings === null) {
            flash('error', 'Keine KI-Schnittstelle hinterlegt: Trage unter /aufgaben/ki einen Endpunkt ein (oder lass die Administration schulweite Zugangsdaten hinterlegen).');
            redirect('/aufgaben/' . $task->id . '/korrektur');
        }
        $submissions = TaskSubmission::forTask($task->id);
        $gradings = TaskGrading::forTask($task->id);
        $open = [];
        foreach ($submissions as $studentId => $submission) {
            $grading = $gradings[$studentId] ?? null;
            if ($grading === null || ($grading['source'] === 'api' && !$grading['checked'])) {
                $open[$studentId] = $submission;
            }
        }
        if ($open === []) {
            flash('error', 'Es gibt keine abgegebenen Lösungen, die noch offen sind.');
            redirect('/aufgaben/' . $task->id . '/korrektur');
        }
        // Pseudonyme vergeben: die KI sieht keine Namen.
        $pseudonyms = [];
        $letter = 'A';
        foreach (array_keys($open) as $studentId) {
            $pseudonyms[$studentId] = 'Arbeit ' . $letter;
            $letter++;
        }
        $payloadTask = [
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type,
            'level' => $task->level,
            'sample_solution' => $task->sampleSolution,
        ];
        $payloadWorks = [];
        foreach ($open as $studentId => $submission) {
            $answer = $submission['content'];
            if (mb_strlen($answer) > 20000) {
                $answer = mb_substr($answer, 0, 20000);
            }
            $payloadWorks[] = ['pseudonym' => $pseudonyms[$studentId], 'answer' => $answer];
        }
        $result = null;
        try {
            if ($settings['mode'] === 'chat') {
                $prompt = 'Du korrigierst eine Schulaufgabe der Jahrgangsstufe ' . $task->level . '. '
                    . 'Aufgabe: ' . $task->title . PHP_EOL
                    . 'Aufgabentext: ' . $task->description . PHP_EOL
                    . 'Musterlösung: ' . ($task->sampleSolution !== '' ? $task->sampleSolution : '(keine Musterlösung hinterlegt)') . PHP_EOL
                    . 'Bewerte jede eingereichte Arbeit mit einer Note (Schulnote 1 bis 6, auch Zwischenwerte wie 2- oder 3+) '
                    . 'und kurzes, freundliches Feedback in deutscher Sprache. '
                    . 'Antworte ausschließlich mit JSON im Format {"results":[{"pseudonym":"Arbeit A","grade":"1","feedback":"..."}]}. '
                    . 'Daten: ' . json_encode(['works' => $payloadWorks], JSON_UNESCAPED_UNICODE);
                $body = json_encode(['model' => $settings['model'], 'messages' => [['role' => 'user', 'content' => $prompt]]], JSON_UNESCAPED_UNICODE);
                $response = self::httpPost($settings['url'], $body, $settings['key']);
                $decoded = json_decode($response, true);
                $content = is_array($decoded) ? (string) ($decoded['choices'][0]['message']['content'] ?? '') : '';
                if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
                    $result = json_decode($matches[0], true);
                }
            } else {
                $payload = json_encode(['task' => $payloadTask, 'works' => $payloadWorks], JSON_UNESCAPED_UNICODE);
                $response = self::httpPost($settings['url'], $payload, $settings['key']);
                $result = json_decode($response, true);
            }
        } catch (RuntimeException $e) {
            flash('error', 'KI-Aufruf fehlgeschlagen: ' . $e->getMessage());
            redirect('/aufgaben/' . $task->id . '/korrektur');
        }
        if (!is_array($result) || !is_array($result['results'] ?? null)) {
            flash('error', 'Die KI-Antwort war unbrauchbar, es wurde nichts gespeichert.');
            redirect('/aufgaben/' . $task->id . '/korrektur');
        }
        $applied = 0;
        foreach ($result['results'] as $entry) {
            $pseudonym = (string) ($entry['pseudonym'] ?? '');
            $studentId = array_search($pseudonym, $pseudonyms, true);
            if ($studentId === false) {
                continue;
            }
            $grade = mb_substr(trim((string) ($entry['grade'] ?? '')), 0, 10);
            $feedback = mb_substr(trim((string) ($entry['feedback'] ?? '')), 0, 2000);
            TaskGrading::save($task->id, (int) $studentId, $grade, $feedback, 'api', false);
            $applied++;
        }
        flash($applied > 0 ? 'success' : 'error', $applied > 0
            ? 'KI-Vorschläge gespeichert (' . $applied . 'x, anonymisiert). Alle Vorschläge müssen noch von dir gegengeprüft und übernommen werden.'
            : 'Die KI hat keine passenden Bewertungen geliefert.');
        redirect('/aufgaben/' . $task->id . '/korrektur');
    }

    /** Einstellungen der KI-Schnittstelle (eigene oder schulweite). */
    public static function aiSettings(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können die KI-Schnittstelle einrichten.');
            redirect('/');
        }
        render('tasks_ai', [
            'title' => 'KI-Schnittstelle',
            'active' => 'tasks',
            'own' => AiSettings::forUser($user->id),
            'school' => $user->role === 'admin' ? AiSettings::forSchool() : null,
            'user' => $user,
        ]);
    }

    /** Eigene KI-Zugangsdaten der Lehrkraft speichern. */
    public static function aiSave(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können die KI-Schnittstelle einrichten.');
            redirect('/');
        }
        csrf_validate();
        [$url, $apiKey, $model, $mode] = self::readAiForm();
        AiSettings::saveForUser($user->id, $url, $apiKey, $model, $mode);
        flash('success', trim($url) === ''
            ? 'Eigene KI-Zugangsdaten entfernt. Es gelten wieder schulweite Angaben (falls hinterlegt).'
            : 'KI-Schnittstelle gespeichert. Ab sofort kannst du in der Korrektur KI-Vorschläge anfordern.');
        redirect('/aufgaben/ki');
    }

    /** Schulweite KI-Zugangsdaten der Administration speichern. */
    public static function aiSchool(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user->role !== 'admin') {
            flash('error', 'Schulweite Zugangsdaten kann nur die Administration hinterlegen.');
            redirect('/aufgaben/ki');
        }
        csrf_validate();
        [$url, $apiKey, $model, $mode] = self::readAiForm();
        AiSettings::saveForUser(null, $url, $apiKey, $model, $mode);
        flash('success', trim($url) === ''
            ? 'Schulweite KI-Zugangsdaten entfernt.'
            : 'Schulweite KI-Schnittstelle gespeichert. Sie gilt für alle Lehrkräfte ohne eigene Angaben.');
        redirect('/aufgaben/ki');
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private static function readAiForm(): array
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $apiKey = trim((string) ($_POST['api_key'] ?? ''));
        $model = trim((string) ($_POST['model'] ?? ''));
        $mode = ($_POST['mode'] ?? 'chat') === 'json' ? 'json' : 'chat';
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            flash('error', 'Die Endpunkt-URL muss mit http:// oder https:// beginnen.');
            redirect('/aufgaben/ki');
        }
        return [$url, $apiKey, $model, $mode];
    }

    /**
     * Abgabetermin aus einem datetime-local-Feld ('2026-11-20T14:30') oder
     * Freitext ('2026-11-20 14:30') lesen. null = leer/streichen,
     * false = unbrauchbares Format.
     */
    private static function parseDueAt(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$#', $value, $matches) === 1) {
            $value = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5];
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$#', $value, $matches) !== 1) {
            return false;
        }
        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) || (int) $matches[4] > 23 || (int) $matches[5] > 59) {
            return false;
        }
        return $value;
    }

    /** Prüft, ob eine Aufgabe für diese Schülerin bzw. diesen Schüler sichtbar ist. */
    private static function taskReachesStudent(Task $task, User $student): bool
    {
        $level = Auth::levelOf($student) ?? 0;
        if ($task->level > $level) {
            return false;
        }
        $stmt = \Infocraft\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM task_targets WHERE task_id = ? AND (user_id = ? OR (class_id IS NOT NULL AND class_id = ?))'
        );
        $stmt->execute([$task->id, $student->id, $student->classId ?? 0]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Alle Empfängerinnen und Empfänger einer Aufgabe: Schülerinnen und
     * Schüler aus den zugewiesenen Klassen plus einzeln zugewiesene Personen.
     * @return list<User>
     */
    private static function recipients(Task $task): array
    {
        $byId = [];
        foreach (Task::targets($task->id) as $target) {
            if ($target['classId'] !== null) {
                foreach (User::byClass($target['classId']) as $student) {
                    $byId[$student->id] = $student;
                }
            } elseif ($target['userId'] !== null) {
                $student = User::find($target['userId']);
                if ($student !== null && $student->role === 'student') {
                    $byId[$student->id] = $student;
                }
            }
        }
        return array_values($byId);
    }

    /** @return array<int, list<User>> */
    private static function studentsByClass(User $user): array
    {
        $map = [];
        foreach (SchoolClass::forTeacher($user->id, $user->role === 'admin') as $class) {
            $map[$class->id] = User::byClass($class->id);
        }
        return $map;
    }

    /** POST an einen JSON-Endpunkt (Bearer-Autorisierung, falls Schlüssel hinterlegt). */
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
            throw new RuntimeException('Der Endpunkt ist nicht erreichbar.');
        }
        return $response;
    }
}
