<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\AiSettings;
use Infocraft\Models\HtmlSanitizer;
use Infocraft\Models\Project;
use Infocraft\Models\ProjectFile;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\Task;
use Infocraft\Models\TaskCatalog;
use Infocraft\Models\TaskGrading;
use Infocraft\Models\TaskMaterial;
use Infocraft\Models\TaskSubmission;
use Infocraft\Models\TaskTodo;
use Infocraft\Models\TaskTopic;
use Infocraft\Models\SiteSetting;
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
            // Wiederkehrende Aufgaben: überfällige Abgabetermine einmal je
            // Aufruf in die Zukunft schieben (idempotent, ohne Migration).
            Task::rollRecurringDeadlines();
            $classes = SchoolClass::forTeacher($user->id, $user->role === 'admin');
            // Unter-Navigation des Aufgabenpools: Meine Aufgaben (Kacheln),
            // Aufgabenkatalog (fertige Aufgaben) und gemeinsamer Pool.
            $view = (string) ($_GET['view'] ?? 'meine');
            if (!in_array($view, ['meine', 'katalog', 'pool'], true)) {
                $view = 'meine';
            }
            // Filter: Klasse (kaskadiert auf deren Jahrgangsstufe), Themengebiet,
            // Jahrgangsstufe und Typ. Alle Filter sind optional und kombinierbar.
            $filterClassId = max(0, (int) ($_GET['class'] ?? 0));
            $filterTopicId = max(0, (int) ($_GET['topic'] ?? 0));
            $filterLevel = max(0, min(13, (int) ($_GET['level'] ?? 0)));
            $filterType = (string) ($_GET['type'] ?? '');
            if (!in_array($filterType, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
                $filterType = '';
            }
            $filterClass = null;
            foreach ($classes as $class) {
                if ($class->id === $filterClassId) {
                    $filterClass = $class;
                }
            }
            if ($filterClass === null) {
                $filterClassId = 0;
            }
            // Themengebiete: bei gewählter Klasse nur die der Klassenstufe.
            $topicChoices = $filterClass !== null
                ? TaskTopic::forLevel($filterClass->level())
                : TaskTopic::all();
            if ($filterTopicId > 0) {
                $topicValid = false;
                foreach ($topicChoices as $topic) {
                    if ($topic->id === $filterTopicId) {
                        $topicValid = true;
                    }
                }
                if (!$topicValid) {
                    $filterTopicId = 0;
                }
            }

            $tasks = Task::forTeacher($user->id);
            $allTitles = array_map(static fn (Task $task): string => mb_strtolower(trim($task->title)), $tasks);
            $targets = [];
            foreach ($tasks as $task) {
                $targets[$task->id] = Task::targets($task->id);
            }
            // Nächste Abgabetermine für die Termin-Übersicht (vor den Filtern,
            // damit die Übersicht immer das ganze Pool zeigt).
            $now = date('Y-m-d H:i');
            $upcoming = [];
            foreach ($tasks as $task) {
                foreach ($targets[$task->id] as $target) {
                    if ($target['dueAt'] !== null && $target['dueAt'] !== '' && $target['dueAt'] >= $now) {
                        $upcoming[] = ['id' => $task->id, 'title' => $task->title, 'dueAt' => (string) $target['dueAt']];
                    }
                }
            }
            usort($upcoming, static fn (array $a, array $b): int => $a['dueAt'] <=> $b['dueAt']);
            $upcoming = array_slice($upcoming, 0, 6);

            if ($filterTopicId > 0) {
                $tasks = array_values(array_filter($tasks, static fn (Task $task): bool => $task->topicId === $filterTopicId));
            }
            if ($filterLevel > 0) {
                $tasks = array_values(array_filter($tasks, static fn (Task $task): bool => $task->level === $filterLevel));
            }
            if ($filterType !== '') {
                $tasks = array_values(array_filter($tasks, static fn (Task $task): bool => $task->type === $filterType));
            }
            if ($filterClass !== null) {
                $classStudentIds = [];
                foreach (User::byClass($filterClass->id) as $student) {
                    $classStudentIds[$student->id] = true;
                }
                $tasks = array_values(array_filter($tasks, static function (Task $task) use ($targets, $classStudentIds): bool {
                    foreach ($targets[$task->id] as $target) {
                        if ($target['classId'] !== null || ($target['userId'] !== null && isset($classStudentIds[$target['userId']]))) {
                            return true;
                        }
                    }
                    return false;
                }));
            }

            // Abgabe-Fortschritt je Zuweisung (x von y abgegeben) vorbereiten.
            $progressByTask = [];
            $openGradings = [];
            $totalsByTask = [];
            $classSizeCache = [];
            foreach ($tasks as $task) {
                $submissions = TaskSubmission::forTask($task->id);
                $gradings = TaskGrading::forTask($task->id);
                $open = 0;
                foreach ($submissions as $studentId => $submission) {
                    $grading = $gradings[$studentId] ?? null;
                    if ($grading === null || ($grading['source'] === 'api' && !$grading['checked'])) {
                        $open++;
                    }
                }
                $openGradings[$task->id] = $open;
                $progressByTask[$task->id] = [];
                $totalsByTask[$task->id] = ['total' => 0, 'submitted' => 0];
                foreach ($targets[$task->id] as $target) {
                    $total = 0;
                    $submitted = 0;
                    if ($target['classId'] !== null) {
                        if (!isset($classSizeCache[$target['classId']])) {
                            $classSizeCache[$target['classId']] = User::byClass($target['classId']);
                        }
                        foreach ($classSizeCache[$target['classId']] as $student) {
                            $total++;
                            if (isset($submissions[$student->id])) {
                                $submitted++;
                            }
                        }
                    } else {
                        $total = 1;
                        $submitted = isset($submissions[$target['userId']]) ? 1 : 0;
                    }
                    $progressByTask[$task->id][$target['id']] = ['total' => $total, 'submitted' => $submitted];
                    $totalsByTask[$task->id]['total'] += $total;
                    $totalsByTask[$task->id]['submitted'] += $submitted;
                }
            }

            // Meine Aufgaben didaktisch gruppieren: Abschnitte je Jahrgangsstufe,
            // darunter je Themengebiet - so bleibt der Pool auch bei vielen
            // Aufgaben übersichtlich.
            $groupedTasks = [];
            foreach ($tasks as $task) {
                $level = (int) $task->level;
                $topicKey = $task->topicId > 0 ? (int) $task->topicId : 0;
                $groupedTasks[$level][$topicKey][] = $task;
            }
            ksort($groupedTasks);

            $catalog = TaskCatalog::all();
            if ($filterLevel > 0) {
                $catalog = array_values(array_filter($catalog, static fn (array $entry): bool => $entry['level'] === $filterLevel));
            }
            if ($filterType !== '') {
                $catalog = array_values(array_filter($catalog, static fn (array $entry): bool => $entry['type'] === $filterType));
            }
            if ($filterTopicId > 0) {
                $catalogTopic = TaskTopic::find($filterTopicId);
                $catalog = array_values(array_filter($catalog, static fn (array $entry): bool => ($entry['topic'] ?? '') === ($catalogTopic !== null ? $catalogTopic->name : '~')));
            }
            // Katalog ebenfalls nach Jahrgangsstufe und Themengebiet gliedern.
            $groupedCatalog = [];
            foreach ($catalog as $entry) {
                $groupedCatalog[(int) $entry['level']][$entry['topic'] ?? 'ohne Themengebiet'][] = $entry;
            }
            $pool = Task::pool($user->id);
            if ($filterTopicId > 0) {
                $pool = array_values(array_filter($pool, static fn (array $entry): bool => $entry['task']->topicId === $filterTopicId));
            }
            if ($filterLevel > 0) {
                $pool = array_values(array_filter($pool, static fn (array $entry): bool => $entry['task']->level === $filterLevel));
            }
            if ($filterType !== '') {
                $pool = array_values(array_filter($pool, static fn (array $entry): bool => $entry['task']->type === $filterType));
            }

            $taskIds = [];
            foreach ($tasks as $task) {
                $taskIds[] = $task->id;
            }
            render('tasks_teacher', [
                'title' => 'Aufgabenpool',
                'active' => 'tasks',
                'view' => $view,
                'tasks' => $tasks,
                'groupedTasks' => $groupedTasks,
                'groupedCatalog' => $groupedCatalog,
                'targets' => $targets,
                'classes' => $classes,
                'studentsByClass' => self::studentsByClass($user),
                'catalog' => $catalog,
                'pool' => $pool,
                'topics' => $topicChoices,
                'allTopics' => TaskTopic::all(),
                'filterClassId' => $filterClassId,
                'filterTopicId' => $filterTopicId,
                'filterLevel' => $filterLevel,
                'filterType' => $filterType,
                'progress' => $progressByTask,
                'totals' => $totalsByTask,
                'openGradings' => $openGradings,
                'upcoming' => $upcoming,
                'todoCounts' => TaskTodo::templatesForTasks($taskIds),
                'materialCounts' => TaskMaterial::forTasks($taskIds),
                'ownedTitles' => $allTitles,
                'aiConfigured' => AiSettings::resolve($user->id) !== null,
                'user' => $user,
            ]);
            return;
        }
        $level = Auth::levelOf($user) ?? 0;
        $entries = Task::forStudent($user->id, $user->classId, $level);
        $now = date('Y-m-d H:i');

        // Nächster anstehender Abgabetermin über alle Aufgaben (ungefiltert).
        $nextDue = null;
        foreach ($entries as $entry) {
            if ($entry['dueAt'] !== null && $entry['dueAt'] >= $now && ($nextDue === null || $entry['dueAt'] < $nextDue)) {
                $nextDue = $entry['dueAt'];
            }
        }

        // Filter für Schülerinnen und Schüler: Themengebiet, Typ und Status.
        $filterTopicId = max(0, (int) ($_GET['topic'] ?? 0));
        $filterType = (string) ($_GET['type'] ?? '');
        if (!in_array($filterType, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
            $filterType = '';
        }
        $filterStatus = (string) ($_GET['status'] ?? '');
        if (!in_array($filterStatus, ['offen', 'abgegeben', 'korrigiert', 'ueberfaellig'], true)) {
            $filterStatus = '';
        }
        $topicChoices = [];
        foreach ($entries as $entry) {
            if ($entry['task']->topicId > 0 && !isset($topicChoices[$entry['task']->topicId])) {
                $topic = TaskTopic::find($entry['task']->topicId);
                if ($topic !== null) {
                    $topicChoices[$entry['task']->topicId] = $topic;
                }
            }
        }
        $entries = array_values(array_filter($entries, static function (array $entry) use ($filterTopicId, $filterType, $filterStatus, $now): bool {
            $task = $entry['task'];
            if ($filterTopicId > 0 && $task->topicId !== $filterTopicId) {
                return false;
            }
            if ($filterType !== '' && $task->type !== $filterType) {
                return false;
            }
            if ($filterStatus !== '') {
                $submitted = $entry['submission'] !== null;
                $graded = $entry['grading'] !== null && ($entry['grading']['source'] === 'lk' || $entry['grading']['checked']);
                if ($filterStatus === 'korrigiert' && !$graded) {
                    return false;
                }
                if ($filterStatus === 'abgegeben' && ($graded || !$submitted)) {
                    return false;
                }
                if ($filterStatus === 'offen' && $submitted) {
                    return false;
                }
                if ($filterStatus === 'ueberfaellig' && ($entry['dueAt'] === null || $entry['dueAt'] >= $now || $submitted)) {
                    return false;
                }
            }
            return true;
        }));

        $taskIds = [];
        foreach ($entries as $entry) {
            $taskIds[] = $entry['task']->id;
        }
        render('tasks_student', [
            'title' => 'Meine Aufgaben',
            'active' => 'module-tasks',
            'tasks' => $entries,
            'nextDue' => $nextDue,
            'topics' => $topicChoices,
            'filterTopicId' => $filterTopicId,
            'filterType' => $filterType,
            'filterStatus' => $filterStatus,
            'todoTemplates' => TaskTodo::templatesForTasks($taskIds),
            'studentTodos' => TaskTodo::forStudentTasks($user->id, $taskIds),
            'materials' => TaskMaterial::forTasks($taskIds),
            // Eigene Projekte der Werkstatt für die Abgabe per Projektverweis (Issue #11).
            'studentProjects' => Project::all($user->id),
            'user' => $user,
        ]);
    }

    /** Eigene Seite: neue Aufgabe anlegen (das Formular hat hier Platz). */
    public static function createPage(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben anlegen.');
            redirect('/');
        }
        render('tasks_new', [
            'title' => 'Neue Aufgabe',
            'active' => 'tasks',
            'allTopics' => TaskTopic::all(),
            'aiConfigured' => AiSettings::resolve($user->id) !== null,
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
            redirect('/aufgaben/neu');
        }
        $type = (string) ($_POST['type'] ?? 'allgemein');
        if (!in_array($type, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
            $type = 'allgemein';
        }
        $level = (int) ($_POST['level'] ?? 6);
        $lateLock = ($_POST['late_lock'] ?? '') === '1' ? 1 : 0;
        $maxPoints = max(0, min(1000, (int) ($_POST['max_points'] ?? 0)));
        [$topicId, $description, $descriptionFormat] = self::readTaskForm();
        $solutionVisible = ($_POST['solution_visible'] ?? '') === '1' ? 1 : 0;
        $task = Task::createFull($user->id, $title, $description, $type, $level, 0, (string) ($_POST['sample_solution'] ?? ''), $lateLock, $maxPoints, (string) ($_POST['rubric'] ?? ''), $topicId, $descriptionFormat, 0, $solutionVisible);
        flash('success', 'Aufgabe angelegt. Jetzt ToDos und Vorlagen ergänzen oder direkt verteilen.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
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
        $topics = TaskTopic::forLevel($task->level);
        if ($task->topicId > 0) {
            $known = false;
            foreach ($topics as $topic) {
                if ($topic->id === $task->topicId) {
                    $known = true;
                }
            }
            if (!$known) {
                $current = TaskTopic::find($task->topicId);
                if ($current !== null) {
                    $topics[] = $current;
                }
            }
        }
        // Verteilen & Termine leben jetzt hier: Fortschritt und offene
        // Korrekturen je Zuweisung mitliefern.
        $targets = Task::targets($task->id);
        $submissions = TaskSubmission::forTask($task->id);
        $gradings = TaskGrading::forTask($task->id);
        $open = 0;
        foreach ($submissions as $studentId => $submission) {
            $grading = $gradings[$studentId] ?? null;
            if ($grading === null || ($grading['source'] === 'api' && !$grading['checked'])) {
                $open++;
            }
        }
        $classes = SchoolClass::forTeacher($user->id, $user->role === 'admin');
        $progressByTarget = [];
        $classSizeCache = [];
        foreach ($targets as $target) {
            $total = 0;
            $submitted = 0;
            if ($target['classId'] !== null) {
                if (!isset($classSizeCache[$target['classId']])) {
                    $classSizeCache[$target['classId']] = User::byClass($target['classId']);
                }
                foreach ($classSizeCache[$target['classId']] as $student) {
                    $total++;
                    if (isset($submissions[$student->id])) {
                        $submitted++;
                    }
                }
            } else {
                $total = 1;
                $submitted = isset($submissions[$target['userId']]) ? 1 : 0;
            }
            $progressByTarget[$target['id']] = ['total' => $total, 'submitted' => $submitted];
        }
        render('tasks_edit', [
            'title' => 'Aufgabe bearbeiten',
            'active' => 'tasks',
            'task' => $task,
            'topics' => $topics,
            'materials' => TaskMaterial::forTask($task->id),
            'todos' => TaskTodo::templatesForTask($task->id),
            'targets' => $targets,
            'progress' => $progressByTarget,
            'openGradings' => $open,
            'classes' => $classes,
            'studentsByClass' => self::studentsByClass($user),
            'defaultLanguage' => SiteSetting::defaultLanguage(),
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
        $lateLock = ($_POST['late_lock'] ?? '') === '1' ? 1 : 0;
        $maxPoints = max(0, min(1000, (int) ($_POST['max_points'] ?? 0)));
        [$topicId, $description, $descriptionFormat] = self::readTaskForm();
        $solutionVisible = ($_POST['solution_visible'] ?? '') === '1' ? 1 : 0;
        Task::update($task->id, $title, $description, $type, $level, (string) ($_POST['sample_solution'] ?? ''), $lateLock, $maxPoints, (string) ($_POST['rubric'] ?? ''), $topicId, $descriptionFormat, $solutionVisible);
        flash('success', 'Aufgabe gespeichert.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /**
     * Liest Themengebiet und Beschreibung aus dem Formular. Die Beschreibung
     * kommt entweder aus dem WYSIWYG-Editor (description_html = 1, wird mit
     * dem Whitelist-Sanitizer gereinigt) oder als Klartext.
     * @return array{0: int, 1: string, 2: string}
     */
    private static function readTaskForm(): array
    {
        $topicId = max(0, (int) ($_POST['topic_id'] ?? 0));
        $newTopic = trim((string) ($_POST['new_topic'] ?? ''));
        $level = max(1, min(13, (int) ($_POST['level'] ?? 6)));
        if ($newTopic !== '') {
            $topic = TaskTopic::findOrCreate($newTopic, $level);
            $topicId = $topic->id;
        } elseif ($topicId > 0 && TaskTopic::find($topicId) === null) {
            $topicId = 0;
        }
        $description = (string) ($_POST['description'] ?? '');
        $descriptionFormat = 'text';
        if (($_POST['description_html'] ?? '') === '1') {
            $descriptionFormat = 'html';
            $description = HtmlSanitizer::clean($description);
        } else {
            $description = trim($description);
        }
        return [$topicId, $description, $descriptionFormat];
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
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $share = ($_POST['shared'] ?? '') === '1';
        Task::setShared($task->id, $share);
        flash('success', $share
            ? 'Aufgabe ist jetzt im gemeinsamen Pool aller Lehrkräfte sichtbar.'
            : 'Aufgabe ist nicht mehr im gemeinsamen Pool geteilt.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
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
            redirect('/aufgaben?view=katalog');
        }
        if (Task::hasTitle($user->id, $entry['title'])) {
            flash('error', 'Diese Aufgabe hast du schon im Pool.');
            redirect('/aufgaben?view=katalog');
        }
        $topic = ($entry['topic'] ?? '') !== ''
            ? TaskTopic::findOrCreate($entry['topic'], (int) $entry['level'])
            : null;
        $task = Task::createFull($user->id, $entry['title'], $entry['description'], $entry['type'], $entry['level'], 0, (string) ($entry['solution'] ?? ''), 0, 0, (string) ($entry['rubric'] ?? ''), $topic !== null ? $topic->id : 0, 'text');
        self::seedCatalogExtras($task->id, $entry);
        flash('success', 'Aufgabe aus dem Katalog übernommen.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
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
            redirect('/aufgaben?view=katalog');
        }
        $count = 0;
        foreach (TaskCatalog::forLevel($level) as $entry) {
            if (!Task::hasTitle($user->id, $entry['title'])) {
                $topic = ($entry['topic'] ?? '') !== ''
                    ? TaskTopic::findOrCreate($entry['topic'], (int) $entry['level'])
                    : null;
                $task = Task::createFull($user->id, $entry['title'], $entry['description'], $entry['type'], $entry['level'], 0, (string) ($entry['solution'] ?? ''), 0, 0, (string) ($entry['rubric'] ?? ''), $topic !== null ? $topic->id : 0, 'text');
                self::seedCatalogExtras($task->id, $entry);
                $count++;
            }
        }
        if ($count === 0) {
            flash('error', 'Dein Pool enthält alle Aufgaben dieser Jahrgangsstufe bereits.');
        } else {
            flash('success', $count . ' Aufgabe' . ($count === 1 ? '' : 'n') . ' der Jahrgangsstufe ' . $level . ' übernommen.');
        }
        redirect('/aufgaben?view=katalog&level=' . $level);
    }

    /**
     * Katalog-Aufgaben bringen didaktisches Material mit: abhakbare ToDos,
     * Vorlagen (z. B. eine Beispieldatenbank als .sql) und eine Musterlösung.
     * Alles wird beim Übernehmen als Kopie mitgegeben.
     * @param array{id: int, title: string, description: string, type: string, level: int, topic: string, todos?: list<string>, files?: list<array{name: string, content: string}>, solution?: string, rubric?: string} $entry
     */
    private static function seedCatalogExtras(int $taskId, array $entry): void
    {
        foreach ((array) ($entry['todos'] ?? []) as $todoTitle) {
            TaskTodo::addTemplate($taskId, (string) $todoTitle);
        }
        foreach ((array) ($entry['files'] ?? []) as $file) {
            TaskMaterial::add($taskId, (string) ($file['name'] ?? ''), (string) ($file['content'] ?? ''));
        }
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

    /** Aufgabe austeilen, mit optionalem Starttermin (Sichtbarkeit) und Abgabedatum mit Uhrzeit. */
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
        $dueAt = self::parseAt((string) ($_POST['due_at'] ?? ''));
        if ($dueAt === false) {
            flash('error', 'Bitte den Abgabetermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30).');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $visibleFrom = self::parseAt((string) ($_POST['visible_from'] ?? ''));
        if ($visibleFrom === false) {
            flash('error', 'Bitte den Starttermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30) oder leer lassen.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        // Wiederkehrender Rhythmus (optional): '', weekly, biweekly, monthly.
        $recurrence = (string) ($_POST['recurrence'] ?? '');
        if (!in_array($recurrence, ['', 'weekly', 'biweekly', 'monthly'], true)) {
            $recurrence = '';
        }
        // Einzelne Personen: Auswahl im Verteilen-Panel (Mehrfachauswahl)
        // oder klassisches Einzelfeld.
        $userIds = [];
        if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
            foreach ($_POST['user_ids'] as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $userIds[$uid] = $uid;
                }
            }
        }
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($singleId > 0) {
            $userIds[$singleId] = $singleId;
        }
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                flash('error', 'Du unterrichtest diese Klasse nicht.');
                redirect('/aufgaben/' . $task->id . '/bearbeiten');
            }
            Task::assign($task->id, $classId, null, $dueAt, $visibleFrom, $recurrence);
            $recurrenceLabels = ['weekly' => ' (wöchentlich)', 'biweekly' => ' (alle 2 Wochen)', 'monthly' => ' (monatlich)'];
            flash('success', $visibleFrom === null
                ? 'Aufgabe an die Klasse ausgeteilt.' . ($recurrenceLabels[$recurrence] ?? '')
                : 'Aufgabe an die Klasse ausgeteilt.' . ($recurrenceLabels[$recurrence] ?? '') . ' Sie erscheint am ' . $visibleFrom . ' Uhr.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        if ($userIds !== []) {
            $count = 0;
            $skipped = 0;
            foreach ($userIds as $sid) {
                $student = User::find($sid);
                if ($student === null || $student->role !== 'student' || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher((int) $student->classId, $user->id)))) {
                    continue;
                }
                if (Task::assign($task->id, null, $student->id, $dueAt, $visibleFrom, $recurrence)) {
                    $count++;
                } else {
                    $skipped++;
                }
            }
            if ($count === 0 && $skipped === 0) {
                flash('error', 'Diese Personen sind nicht in einer deiner Klassen.');
                redirect('/aufgaben/' . $task->id . '/bearbeiten');
            }
            flash($count > 0 ? 'success' : 'error', ($count > 0
                ? 'Aufgabe an ' . $count . ' Person(en) ausgeteilt.'
                : 'Es gab keine neue Zuweisung.')
                . ($skipped > 0 ? ' Bereits zugewiesen: ' . $skipped . '.' : '')
                . ($visibleFrom !== null ? ' Sie erscheint am ' . $visibleFrom . ' Uhr.' : ''));
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        flash('error', 'Kein Ziel angegeben.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** Starttermin (Sichtbarkeit) einer bestehenden Zuweisung ändern oder streichen (= sofort sichtbar). */
    public static function startDate(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Starttermine setzen.');
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
        $visibleFrom = self::parseAt((string) ($_POST['visible_from'] ?? ''));
        if ($visibleFrom === false) {
            flash('error', 'Bitte den Starttermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30) oder leer lassen.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        Task::setTargetVisibleFrom((int) $target['id'], $visibleFrom);
        flash('success', $visibleFrom === null
            ? 'Starttermin gestrichen, die Aufgabe ist sofort sichtbar.'
            : 'Starttermin gespeichert. Die Aufgabe erscheint für die Klasse bzw. Person zum angegebenen Zeitpunkt.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** Zuweisung zurücknehmen: die Aufgabe ist für die Klasse bzw. Person nicht mehr sichtbar. */
    public static function withdraw(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Zuweisungen zurücknehmen.');
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
        Task::deleteTarget((int) $target['id']);
        flash('success', 'Zuweisung eingezogen. Die Aufgabe ist für die Klasse bzw. Person nicht mehr sichtbar (bereits abgegebene Lösungen bleiben erhalten).');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** ToDos der Lehrkraft an einer Aufgabe pflegen (Vorlage für alle Empfänger). */
    public static function todoTeacher(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können ToDos hinterlegen.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $deleteId = (int) ($_POST['delete_todo_id'] ?? 0);
        if ($deleteId > 0) {
            TaskTodo::deleteTemplate($deleteId);
            flash('success', 'ToDo entfernt.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $title = trim((string) ($_POST['todo_title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Text für das ToDo angeben.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        TaskTodo::addTemplate($task->id, $title);
        flash('success', 'ToDo hinzugefügt. Alle Schülerinnen und Schüler der Aufgabe sehen es ab jetzt.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** ToDos der Schülerin bzw. des Schülers: Vorlage abhaken, eigene pflegen. */
    public static function todoStudent(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            flash('error', 'ToDos gehören zur Schüler-Sicht der Aufgabe.');
            redirect('/aufgaben');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !self::taskReachesStudent($task, $user)) {
            flash('error', 'Diese Aufgabe ist nicht für dich ausgeteilt.');
            redirect('/aufgaben');
        }
        $deleteId = (int) ($_POST['delete_todo_id'] ?? 0);
        if ($deleteId > 0) {
            TaskTodo::deleteStudent($deleteId, $user->id);
            flash('success', 'Eigenes ToDo entfernt.');
            redirect('/aufgaben');
        }
        $toggleId = (int) ($_POST['toggle_todo_id'] ?? 0);
        if ($toggleId > 0) {
            TaskTodo::toggleStudent($toggleId, $user->id);
            redirect('/aufgaben');
        }
        $templateId = (int) ($_POST['toggle_template_id'] ?? 0);
        if ($templateId > 0) {
            TaskTodo::toggleTemplate($task->id, $user->id, $templateId);
            redirect('/aufgaben');
        }
        $title = trim((string) ($_POST['todo_title'] ?? ''));
        if ($title !== '') {
            TaskTodo::addStudent($task->id, $user->id, $title);
            flash('success', 'Eigenes ToDo hinzugefügt.');
        }
        redirect('/aufgaben');
    }

    /** Material (Datei oder Textvorlage) an einer Aufgabe speichern oder löschen. */
    public static function materialSave(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Materialien verwalten.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $deleteId = (int) ($_POST['delete_material_id'] ?? 0);
        if ($deleteId > 0) {
            TaskMaterial::delete($deleteId);
            flash('success', 'Material entfernt.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $filename = trim((string) ($_FILES['file']['name'] ?? ''));
        if ($filename !== '' && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ((int) $_FILES['file']['size'] > TaskMaterial::MAX_BYTES) {
                flash('error', 'Die Datei ist zu groß (maximal 512 KiB).');
                redirect('/aufgaben/' . $task->id . '/bearbeiten');
            }
            $content = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
            if (TaskMaterial::add($task->id, $filename, $content)) {
                flash('success', 'Material gespeichert und zum Austeilen bereit.');
            } else {
                flash('error', 'Das Material konnte nicht gespeichert werden.');
            }
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $filename = trim((string) ($_POST['filename'] ?? ''));
        $content = (string) ($_POST['material_content'] ?? '');
        if ($filename !== '' && $content !== '') {
            if (TaskMaterial::add($task->id, $filename, $content)) {
                flash('success', 'Material gespeichert und zum Austeilen bereit.');
            } else {
                flash('error', 'Das Material konnte nicht gespeichert werden (Name oder Größe ungültig).');
            }
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        flash('error', 'Bitte einen Dateinamen und Inhalt angeben oder eine Datei auswählen.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** Material für Schülerinnen und Schüler oder Lehrkraft herunterladen. */
    public static function materialDownload(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        $material = TaskMaterial::find((int) ($params['mid'] ?? 0));
        if ($task === null || $material === null || $material->taskId !== $task->id) {
            flash('error', 'Material nicht gefunden.');
            redirect('/aufgaben');
        }
        if (!Task::ownedBy($task, $user) && !self::taskReachesStudent($task, $user)) {
            flash('error', 'Dieses Material gehört nicht zu einer deiner Aufgaben.');
            redirect('/aufgaben');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($material->filename) . '"');
        header('Content-Length: ' . strlen($material->raw()));
        echo $material->raw();
        exit;
    }

    /**
     * Präsentationsmodus starten (Issue #35): Die Lehrkraft arbeitet in einer
     * temporären Kopie in der Werkstatt, ohne die Aufgabe oder die Vorlage
     * (Materialien) zu überschreiben. Die Kopie ist als Musterlösung
     * herunterladbar und wird über den Präsentationsmodus wieder gelöscht.
     */
    public static function presentationStart(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Der Präsentationsmodus ist nur für Lehrkräfte.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        // Läuft bereits? Dann direkt weiter im laufenden Projekt.
        if ($task->presentationProjectId !== null) {
            $existing = Project::find($task->presentationProjectId);
            if ($existing !== null && $existing->ownerId === $user->id) {
                redirect('/werkstatt/' . ($existing->type === 'html' ? 'html' : 'code') . '/' . $existing->id);
            }
        }
        // Startinhalt: erstes Code-Material (Java/Python/Assembler/HTML),
        // sonst die Musterlösung. Weitere Code-Materialien werden als Dateien
        // in die temporäre Kopie übernommen. Ohne Vorlage startet der Editor leer.
        $content = '';
        $language = null;
        $type = $task->type === 'html' ? 'html' : 'code';
        $extraFiles = [];
        foreach (TaskMaterial::forTask($task->id) as $material) {
            $lower = mb_strtolower($material->filename);
            if (preg_match('/\.(java|py|asm|html)$/i', $lower, $match) === 1) {
                if ($content === '') {
                    $content = $material->raw();
                    $language = $type === 'html' ? null : ['java' => 'java', 'py' => 'python', 'asm' => 'asm', 'html' => null][$match[1]];
                } else {
                    $extraFiles[] = ['name' => $material->filename, 'content' => $material->raw()];
                }
            }
        }
        if ($content === '' && $task->sampleSolution !== '') {
            $content = $task->sampleSolution;
            $language = $language ?? ($type === 'code' ? 'java' : null);
        }
        $project = Project::create($user->id, null, '🎬 Präsentation: ' . $task->title, $type, $language, $content);
        foreach ($extraFiles as $extra) {
            ProjectFile::create($project->id, $extra['name'], $extra['content']);
        }
        Task::setPresentationProject($task->id, $project->id);
        flash('success', 'Präsentationsmodus gestartet. Deine Änderungen bleiben in der temporären Kopie.');
        redirect('/werkstatt/' . ($type === 'html' ? 'html' : 'code') . '/' . $project->id);
    }

    /** Präsentationsmodus beenden: temporäres Projekt löschen. */
    public static function presentationEnd(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Der Präsentationsmodus ist nur für Lehrkräfte.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        if ($task->presentationProjectId !== null) {
            $project = Project::find($task->presentationProjectId);
            if ($project !== null && $project->ownerId === $user->id) {
                Project::delete($project->id);
            }
            Task::setPresentationProject($task->id, null);
        }
        flash('success', 'Präsentationsmodus beendet, die temporäre Kopie wurde gelöscht.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
    }

    /** Temporäre Kopie (Musterlösung) als Datei herunterladen. */
    public static function presentationDownload(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können die Musterlösung herunterladen.');
            redirect('/');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user) || $task->presentationProjectId === null) {
            flash('error', 'Für diese Aufgabe läuft gerade kein Präsentationsmodus.');
            redirect('/aufgaben');
        }
        $project = Project::find($task->presentationProjectId);
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Das Präsentationsprojekt existiert nicht mehr.');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $task->title) ?? '', '-'));
        $ext = $project->language === 'python' ? 'py' : ($project->language === 'asm' ? 'asm' : ($project->type === 'html' ? 'html' : 'java'));
        $filename = 'Musterloesung-' . ($slug !== '' ? $slug : 'aufgabe-' . $task->id) . '.' . $ext;
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $project->content;
        foreach (ProjectFile::allForProject($project->id) as $file) {
            echo "\n\n/* ===== Datei: " . $file->name . " ===== */\n" . $file->content;
        }
        exit;
    }

    /**
     * Musterlösung in einem eigenen Fenster anzeigen (Issue #36): Nur die
     * Lehrkraft, die die Aufgabe besitzt. Für den Zweitbildschirm, unabhängig
     * vom Arbeitsfenster offenbleibend, ohne Navigation.
     */
    public static function solutionWindow(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Die Musterlösung ist nur für Lehrkräfte sichtbar.');
            redirect('/');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        render('task_solution_window', [
            'title' => 'Musterlösung: ' . $task->title,
            'bare' => true,
            'task' => $task,
        ]);
    }

    /**
     * Aufgabe als Java- oder Python-Variante duplizieren: Kopie mit
     * Rückverweis, Materialien und ToDo-Vorlagen werden mitkopiert. Ist eine
     * Chat-KI-Schnittstelle hinterlegt, wird die Musterlösung automatisch
     * in die Zielsprache konvertiert (sonst 1:1 übernommen).
     */
    public static function variant(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Varianten anlegen.');
            redirect('/');
        }
        csrf_validate();
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        // Standard-Zielsprache kommt aus den Admin-Einstellungen (Standard: Java).
        $target = (string) ($_POST['target'] ?? SiteSetting::defaultLanguage());
        if (!in_array($target, ['java', 'python'], true)) {
            $target = SiteSetting::defaultLanguage();
        }
        $label = $target === 'java' ? 'Java-Variante' : 'Python-Variante';
        $title = $task->title;
        if (mb_stripos($title, $label) === false) {
            $title = mb_substr($title, 0, 200 - strlen($label) - 3) . ' (' . $label . ')';
        }
        if (Task::hasTitle($user->id, $title)) {
            flash('error', 'Es gibt diese Variante bereits in deinem Pool.');
            redirect('/aufgaben');
        }
        $solution = $task->sampleSolution;
        $converted = false;
        $settings = AiSettings::resolve($user->id);
        if ($settings !== null && $settings['mode'] === 'chat' && $solution !== '') {
            try {
                $prompt = 'Konvertiere die folgende Musterlösung einer Informatikaufgabe nach '
                    . ($target === 'java' ? 'Java' : 'Python') . '. '
                    . 'Gib ausschließlich den fertigen Quellcode aus, ohne Erklärungen und ohne Markdown. Musterlösung:'
                    . PHP_EOL . $solution;
                $body = json_encode(['model' => $settings['model'], 'messages' => [['role' => 'user', 'content' => $prompt]]], JSON_UNESCAPED_UNICODE);
                $response = self::httpPost($settings['url'], $body, $settings['key']);
                $decoded = json_decode($response, true);
                $content = is_array($decoded) ? (string) ($decoded['choices'][0]['message']['content'] ?? '') : '';
                $content = trim($content);
                if ($content !== '') {
                    $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content) ?? $content;
                    $content = preg_replace('/```\s*$/', '', $content) ?? $content;
                    if (trim($content) !== '') {
                        $solution = trim($content);
                        $converted = true;
                    }
                }
            } catch (RuntimeException $e) {
                $converted = false;
            }
        }
        $copy = Task::duplicateAsVariant($task, $user->id, $title, $solution, $task->id);
        foreach (TaskMaterial::forTask($task->id) as $material) {
            TaskMaterial::add($copy->id, $material->filename, $material->raw());
        }
        foreach (TaskTodo::templatesForTask($task->id) as $todo) {
            TaskTodo::addTemplate($copy->id, $todo->title);
        }
        flash('success', $label . ' angelegt'
            . ($converted ? ', die Musterlösung wurde von der KI konvertiert. Bitte prüfen!' : '. Materialien und ToDos wurden mitkopiert, die Musterlösung bitte anpassen.')
);
        redirect('/aufgaben/' . $copy->id . '/bearbeiten');
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
        $dueAt = self::parseAt((string) ($_POST['due_at'] ?? ''));
        if ($dueAt === false) {
            flash('error', 'Bitte den Abgabetermin als Datum mit Uhrzeit angeben (z. B. 2026-11-20 14:30).');
            redirect('/aufgaben/' . $task->id . '/bearbeiten');
        }
        Task::setTargetDueAt((int) $target['id'], $dueAt);
        flash('success', $dueAt === null ? 'Abgabetermin gestrichen.' : 'Abgabetermin gespeichert.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
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
        // Projektverweis aus der Code- bzw. HTML-Werkstatt (Issue #11):
        // Bei Typ code/html darf statt Text auch ein eigenes Projekt verknüpft
        // werden. Das Projekt muss der Schülerin bzw. dem Schüler gehören.
        $projectId = max(0, (int) ($_POST['project_id'] ?? 0));
        $project = null;
        if ($projectId > 0) {
            if (!in_array($task->type, ['code', 'html'], true)) {
                flash('error', 'Für diesen Aufgabentyp kannst du kein Projekt verknüpfen.');
                redirect('/aufgaben');
            }
            $project = Project::find($projectId);
            if ($project === null || $project->ownerId !== $user->id) {
                flash('error', 'Das ausgewählte Projekt gehört dir nicht.');
                redirect('/aufgaben');
            }
        }
        if ($content === '' && $project === null) {
            flash('error', 'Bitte schreibe deine Lösung in das Feld oder verknüpfe ein Projekt aus deiner Werkstatt.');
            redirect('/aufgaben');
        }
        $selfRating = max(0, min(3, (int) ($_POST['self_rating'] ?? 0)));
        $entries = Task::forStudent($user->id, $user->classId, Auth::levelOf($user) ?? 0);
        $dueAt = null;
        foreach ($entries as $entry) {
            if ($entry['task']->id === $task->id) {
                $dueAt = $entry['dueAt'];
                break;
            }
        }
        if ($task->lateLock === 1 && $dueAt !== null && date('Y-m-d H:i') > $dueAt) {
            flash('error', 'Der Abgabetermin dieser Aufgabe ist abgelaufen, neue Abgaben sind nicht mehr möglich.');
            redirect('/aufgaben');
        }
        TaskSubmission::upsert($task->id, $user->id, $content, $selfRating, $project !== null ? $project->id : null);
        if ($dueAt !== null && date('Y-m-d H:i') > $dueAt) {
            flash('success', 'Abgabe gespeichert, aber verspätet (Abgabetermin war ' . $dueAt . ' Uhr).');
        } else {
            flash('success', 'Abgabe gespeichert. Du kannst sie bis zum Abgabetermin überschreiben.');
        }
        redirect('/aufgaben');
    }

    /**
     * Aufgabe als JSON exportieren (inklusive Musterlösung, Rubric, Punkten,
     * Thema, Materialien und ToDo-Vorlagen) - für Backups und Austausch mit
     * anderen Lehrkräften unabhängig vom gemeinsamen Pool.
     */
    public static function export(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben exportieren.');
            redirect('/aufgaben');
        }
        $task = Task::find((int) ($params['id'] ?? 0));
        if ($task === null || !Task::ownedBy($task, $user)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        $topic = $task->topic();
        $materials = [];
        foreach (TaskMaterial::forTask($task->id) as $material) {
            $materials[] = ['filename' => $material->filename, 'content' => base64_encode($material->raw())];
        }
        $todos = [];
        foreach (TaskTodo::templatesForTask($task->id) as $todo) {
            $todos[] = ['title' => $todo->title];
        }
        $payload = [
            'infocraft_task' => 1,
            'title' => $task->title,
            'description' => $task->description,
            'description_format' => $task->descriptionFormat,
            'type' => $task->type,
            'level' => $task->level,
            'topic' => $topic !== null ? $topic->name : '',
            'max_points' => $task->maxPoints,
            'rubric' => $task->rubric,
            'sample_solution' => $task->sampleSolution,
            'late_lock' => $task->lateLock,
            'solution_visible' => $task->solutionVisible,
            'todos' => $todos,
            'materials' => $materials,
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode('aufgabe-' . $task->id . '.json') . '"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Aufgabe aus einer JSON-Datei (aus dem Export) als eigene Kopie anlegen.
     * Titel und Beschreibung werden übernommen, die Ids nicht.
     */
    public static function importJson(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Aufgaben importieren.');
            redirect('/aufgaben');
        }
        csrf_validate();
        $json = trim((string) ($_POST['import_json'] ?? ''));
        if ($json === '' && isset($_FILES['import_file']) && is_array($_FILES['import_file']) && (int) ($_FILES['import_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $json = trim((string) file_get_contents((string) $_FILES['import_file']['tmp_name']));
        }
        if ($json === '') {
            flash('error', 'Bitte eine Aufgabendatei (JSON) auswählen oder einfügen.');
            redirect('/aufgaben');
        }
        $data = json_decode($json, true);
        if (!is_array($data) || (int) ($data['infocraft_task'] ?? 0) !== 1 || trim((string) ($data['title'] ?? '')) === '') {
            flash('error', 'Das sieht nicht nach einer InfoCraft-Aufgabendatei aus (Feld "infocraft_task" fehlt).');
            redirect('/aufgaben');
        }
        $title = trim((string) $data['title']);
        $type = (string) ($data['type'] ?? 'allgemein');
        if (!in_array($type, ['allgemein', 'sql', 'html', 'code', 'quiz'], true)) {
            $type = 'allgemein';
        }
        $level = max(1, min(13, (int) ($data['level'] ?? 6)));
        $topicId = 0;
        $topicName = trim((string) ($data['topic'] ?? ''));
        if ($topicName !== '') {
            $topicId = TaskTopic::findOrCreate($topicName, $level)->id;
        }
        $task = Task::createFull(
            $user->id,
            $title,
            (string) ($data['description'] ?? ''),
            $type,
            $level,
            0,
            (string) ($data['sample_solution'] ?? ''),
            ((int) ($data['late_lock'] ?? 0)) === 1 ? 1 : 0,
            max(0, min(1000, (int) ($data['max_points'] ?? 0))),
            (string) ($data['rubric'] ?? ''),
            $topicId,
            ($data['description_format'] ?? 'text') === 'html' ? 'html' : 'text',
            0,
            ((int) ($data['solution_visible'] ?? 0)) === 1 ? 1 : 0,
        );
        foreach ((array) ($data['todos'] ?? []) as $todo) {
            if (is_array($todo) && trim((string) ($todo['title'] ?? '')) !== '') {
                TaskTodo::addTemplate($task->id, trim((string) $todo['title']));
            }
        }
        foreach ((array) ($data['materials'] ?? []) as $material) {
            if (is_array($material) && trim((string) ($material['filename'] ?? '')) !== '' && (string) ($material['content'] ?? '') !== '') {
                $rawContent = base64_decode((string) $material['content'], true);
                if ($rawContent === false) {
                    $rawContent = (string) $material['content'];
                }
                TaskMaterial::add($task->id, (string) $material['filename'], $rawContent);
            }
        }
        flash('success', 'Aufgabe "' . $title . '" importiertien und ToDo-Vorlagen sind mitgekommen.');
        redirect('/aufgaben/' . $task->id . '/bearbeiten');
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
            // Verknüpftes Projekt (Abgabe aus der Code-/HTML-Werkstatt, Issue #11):
            // Für die Quelltext-Ansicht in der Korrektur mitliefern.
            $project = null;
            $projectId = $submission['projectId'] ?? null;
            if ($projectId !== null) {
                $candidate = Project::find($projectId);
                if ($candidate !== null && $candidate->ownerId === $student->id) {
                    $project = $candidate;
                }
            }
            $rows[] = [
                'student' => $student,
                'submission' => $submission,
                'grading' => $grading,
                'dueAt' => $dueAt,
                'late' => $late,
                'project' => $project,
            ];
        }
        // Kennzahlen für die Lehrkraft: Abgabequote, verspätete Abgaben,
        // Korrekturstand und Notenschnitt (aus endgültigen Korrekturen).
        $submittedCount = 0;
        $lateCount = 0;
        $gradedCount = 0;
        $gradeSum = 0.0;
        foreach ($rows as $row) {
            if ($row['submission'] !== null) {
                $submittedCount++;
                if ($row['late']) {
                    $lateCount++;
                }
            }
            if ($row['grading'] !== null && ($row['grading']['source'] === 'lk' || $row['grading']['checked'])) {
                $gradedCount++;
                if (preg_match('/([1-6])/', $row['grading']['grade'], $match) === 1) {
                    $gradeSum += (float) $match[1];
                }
            }
        }
        $stats = [
            'total' => count($rows),
            'submitted' => $submittedCount,
            'late' => $lateCount,
            'graded' => $gradedCount,
            'open' => $submittedCount - $gradedCount,
            'avgGrade' => $gradedCount > 0 ? round($gradeSum / $gradedCount, 2) : null,
        ];
        render('tasks_correction', [
            'title' => 'Korrektur: ' . $task->title,
            'active' => 'tasks',
            'task' => $task,
            'rows' => $rows,
            'stats' => $stats,
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
            'max_points' => $task->maxPoints,
            'rubric' => $task->rubric,
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
                    . ($task->maxPoints > 0 ? 'Maximale Punktzahl: ' . $task->maxPoints . PHP_EOL : '')
                    . ($task->rubric !== '' ? 'Erwartungshorizont/Bewertungskriterien: ' . $task->rubric . PHP_EOL : '')
                    . 'Bewerte jede eingereichte Arbeit mit einer Note (Schulnote 1 bis 6, auch Zwischenwerte wie 2- oder 3+'
                    . ($task->maxPoints > 0 ? '; nenne zusätzlich die erreichte Punktzahl von maximal ' . $task->maxPoints . ' als Teil der Note, z. B. "2- (8/10 Punkte)"' : '')
                    . ') und kurzes, freundliches Feedback in deutscher Sprache. '
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
     * Datum mit Uhrzeit aus einem datetime-local-Feld ('2026-11-20T14:30') oder
     * Freitext ('2026-11-20 14:30') lesen. null = leer/streichen,
     * false = unbrauchbares Format.
     */
    private static function parseAt(string $value): string|false|null
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

    /** Prüft, ob eine Aufgabe für diese Schülerin bzw. diesen Schüler bereits sichtbar ist. */
    private static function taskReachesStudent(Task $task, User $student): bool
    {
        $level = Auth::levelOf($student) ?? 0;
        if ($task->level > $level) {
            return false;
        }
        $stmt = \Infocraft\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM task_targets WHERE task_id = ? AND (user_id = ? OR (class_id IS NOT NULL AND class_id = ?))'
            . " AND (visible_from IS NULL OR visible_from = '' OR visible_from <= ?)"
        );
        $stmt->execute([$task->id, $student->id, $student->classId ?? 0, date('Y-m-d H:i')]);
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
