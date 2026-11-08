<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Database;
use Infocraft\Models\Folder;
use Infocraft\Models\Project;
use Infocraft\Models\ProjectState;
use Infocraft\Models\ProjectFile;
use Infocraft\Models\ProjectImage;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\SiteSetting;
use Infocraft\Models\Task;
use Infocraft\Models\TaskMaterial;
use Infocraft\Models\TaskSubmission;
use Infocraft\Models\TaskTodo;
use Infocraft\Models\TaskWorking;
use Infocraft\Models\User;
use Infocraft\SqlWorkshop;

/**
 * Code-Werkstatt: Projekte in Ordnern, Drag & Drop, Austeilen durch
 * Lehrkräfte. (Die IDE zum Ausführen folgt in Phase 4.)
 */
final class CodeController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $projects = Project::all($user->id);
        $folders = Folder::all($user->id);
        $folderRows = Folder::orderedTree($folders);
        $projectsByFolder = [];
        foreach ($projects as $project) {
            $projectsByFolder[$project->folderId ?? 0][] = $project;
        }
        $counts = [];
        foreach ($projects as $project) {
            $key = $project->folderId ?? 0;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $isTeacher = Auth::isTeacher();
        render('code_overview', [
            'title' => 'Code-Werkstatt',
            'active' => 'module-code',
            'projects' => $projects,
            'folders' => $folders,
            'folderRows' => $folderRows,
            'folderNames' => self::folderNames($folderRows),
            'projectsByFolder' => $projectsByFolder,
            'counts' => $counts,
            'isTeacher' => $isTeacher,
            'defaultLanguage' => SiteSetting::defaultLanguage(),
            'classes' => $isTeacher ? SchoolClass::forTeacher($user->id, $user->role === 'admin') : [],
            'studentsByClass' => $isTeacher ? self::studentsByClass($user) : [],
            'user' => $user,
        ]);
    }

    public static function createProject(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/werkstatt/code');
        }
        $type = ($_POST['type'] ?? 'code') === 'html' ? 'html' : 'code';
        $language = match ($type) {
            'html' => 'html',
            default => in_array($_POST['language'] ?? '', ['python', 'java', 'asm'], true) ? (string) $_POST['language'] : SiteSetting::defaultLanguage(),
        };
        if ($language === 'python' && !self::pythonAllowed($user)) {
            flash('error', 'Python ist für deine Klasse (noch) nicht freigegeben.');
            redirect('/werkstatt/code');
        }
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $folderId = $folderId > 0 && Folder::belongsTo($folderId, $user->id) ? $folderId : null;
        $starter = (string) ($_POST['starter'] ?? '');
        Project::create($user->id, $folderId, $title, $type, $language, self::starterContent($type, $language, $title, $starter));
        flash('success', 'Projekt angelegt.');
        redirect('/werkstatt/code');
    }

    public static function createFolder(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Bitte einen Ordnernamen angeben.');
            redirect('/werkstatt/code');
        }
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $parentId = $parentId > 0 && Folder::belongsTo($parentId, $user->id) ? $parentId : null;
        Folder::create($user->id, $name, $parentId);
        flash('success', 'Ordner angelegt.');
        redirect('/werkstatt/code');
    }

    /** Drag & Drop: Projekt in einen Ordner verschieben (Antwort als JSON). */
    public static function move(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        header('Content-Type: application/json; charset=utf-8');
        $project = Project::find((int) ($_POST['project_id'] ?? 0));
        if ($project === null || $project->ownerId !== $user->id) {
            echo '{"ok":false,"error":"Projekt nicht gefunden."}';
            exit;
        }
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        if ($folderId > 0) {
            if (!Folder::belongsTo($folderId, $user->id)) {
                echo '{"ok":false,"error":"Ordner nicht gefunden."}';
                exit;
            }
            $folderId = (int) $folderId;
        } else {
            $folderId = 0;
        }
        $project->moveTo($folderId > 0 ? $folderId : null);
        echo '{"ok":true}';
        exit;
    }


    /**
     * Issue #45: Beispieldatenbank aus der SQL-Werkstatt in die Mini-Datenbank
     * eines Java-Projekts laden (Lehrkraft: alle Vorlagen, Schülerinnen und
     * Schüler: ihre Datenbanken mit Schreibrechten). Ersetzt den bisherigen
     * Stand, damit das Projekt mit den Tabellen und Daten der Vorlage startet.
     */
    public static function loadDatabase(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || $project->type !== 'code' || $project->language !== 'java' || !self::canOpen($user, $project)) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $templates = self::loadableDatabaseTemplates($user);
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $template = $templateId > 0 ? ($templates[$templateId] ?? null) : null;
        if ($templateId > 0 && $template === null) {
            flash('error', 'Diese Datenbank steht dir hier nicht zur Verfügung.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $seed = '';
        if ($template !== null) {
            $file = SqlWorkshop::templatePath($template->slug);
            $seed = is_file($file) ? trim((string) file_get_contents($file)) : '';
        }
        $state = ProjectState::find($project->id);
        ProjectState::save($project->id, $state['replText'] ?? '', $seed);
        flash('success', $template !== null
            ? $template->title . ' geladen: Bei jedem ▶ Ausführen startet die Mini-Datenbank mit diesen Tabellen.'
            : 'Mini-Datenbank geleert.');
        redirect('/werkstatt/code/' . $project->id);
    }

    /** @return array<int, SqlTemplate> */
    private static function loadableDatabaseTemplates(User $user): array
    {
        $list = [];
        if (Auth::isTeacher()) {
            foreach (SqlTemplate::all() as $template) {
                $list[$template->id] = $template;
            }
            return $list;
        }
        foreach (SqlTemplate::grantsFor($user->id) as $grant) {
            if ($grant['permission'] === 'write' || $grant['permission'] === 'admin') {
                $list[$grant['template']->id] = $grant['template'];
            }
        }
        return $list;
    }

    /**
     * Issue #45: Ordner per Drag & Drop in einen anderen Ordner (oder auf die
     * Hauptebene) verschieben. Verhindert Zirkel: Ein Ordner kann nicht in
     * sich selbst oder einen seiner Unterordner geschoben werden.
     */
    public static function moveFolder(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        header('Content-Type: application/json; charset=utf-8');
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        if ($folderId <= 0 || !Folder::belongsTo($folderId, $user->id)) {
            echo '{"ok":false,"error":"Ordner nicht gefunden."}';
            exit;
        }
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        if ($parentId === $folderId) {
            echo '{"ok":false,"error":"Ein Ordner kann nicht in sich selbst verschoben werden."}';
            exit;
        }
        if ($parentId !== 0 && !Folder::belongsTo($parentId, $user->id)) {
            echo '{"ok":false,"error":"Zielordner nicht gefunden."}';
            exit;
        }
        $pdo = Database::pdo();
        $current = $parentId;
        $guard = 0;
        while ($current > 0 && $guard++ < 100) {
            if ($current === $folderId) {
                echo '{"ok":false,"error":"Ein Ordner kann nicht in einen seiner Unterordner verschoben werden."}';
                exit;
            }
            $stmt = $pdo->prepare('SELECT parent_id FROM folders WHERE id = ?');
            $stmt->execute([$current]);
            $up = $stmt->fetchColumn();
            $current = ($up === false || $up === null) ? 0 : (int) $up;
        }
        $pdo->prepare('UPDATE folders SET parent_id = ? WHERE id = ?')->execute([$parentId, $folderId]);
        echo '{"ok":true}';
        exit;
    }

    /** Lehrkraft teilt ein eigenes Projekt als Kopie an Klasse oder Einzelperson aus. */
    public static function distribute(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Projekte austeilen.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $project = Project::find((int) ($_POST['project_id'] ?? 0));
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        $recipients = [];
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                flash('error', 'Du unterrichtest diese Klasse nicht.');
                redirect('/werkstatt/code');
            }
            $recipients = User::byClass($classId);
        } elseif ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student' || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id)))) {
                flash('error', 'Diese Person ist nicht in einer deiner Klassen.');
                redirect('/werkstatt/code');
            }
            $recipients = [$student];
        }
        if ($recipients === []) {
            flash('error', 'Kein Ziel angegeben.');
            redirect('/werkstatt/code');
        }
        foreach ($recipients as $recipient) {
            Project::copyTo($project, $recipient->id, $user->id);
        }
        flash('success', 'Projekt an ' . count($recipients) . ' Person(en) ausgeteilt.');
        redirect('/werkstatt/code');
    }

    public static function delete(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($_POST['project_id'] ?? 0));
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        Project::delete($project->id);
        flash('success', 'Projekt gelöscht.');
        redirect('/werkstatt/code');
    }

    public static function open(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !self::canOpen($user, $project)) {
            http_response_code(404);
            render('error', ['title' => 'Projekt nicht gefunden', 'message' => 'Dieses Projekt gibt es nicht oder gehört dir nicht.']);
            return;
        }
        if ($project->language === 'python' && !self::pythonAllowed($user)) {
            flash('error', 'Python ist für deine Klasse (noch) nicht freigegeben.');
            redirect('/werkstatt/code');
        }
        $files = ProjectFile::allForProject($project->id);
        $isMultiFile = $project->type === 'code' && ($project->language === 'java' || $project->language === 'asm');
        if ($files === [] && $isMultiFile) {
            // Noch keine Dateien: virtuelle Datei aus dem Projektinhalt anbieten.
            $name = $project->language === 'asm'
                ? self::asmFileName($project->content, $project->title)
                : self::javaFileName($project->content, $project->title);
            $files = [new ProjectFile(0, $project->id, $name, $project->content, 0, null)];
        }
        $owner = User::find($project->ownerId);
        $images = [];
        $imageUrls = [];
        if ($project->type === 'code' && $project->language === 'java') {
            $images = ProjectImage::allForProject($project->id);
            foreach ($images as $image) {
                $imageUrls[$image->name] = url('werkstatt/code/' . $project->id . '/bild/' . rawurlencode($image->name));
            }
        }
        // Präsentationsmodus (Issue #35): läuft dieses Projekt als temporäre
        // Kopie einer Aufgabe, zeigt die IDE einen Hinweis-Banner an.
        $presentationTask = Task::findByPresentationProject($project->id);
        if ($presentationTask !== null && $presentationTask->teacherId !== $user->id) {
            $presentationTask = null;
        }
        // Präsentationsmodus: Beschreibung, ToDos und Materialien der Aufgabe
        // direkt neben dem Editor anzeigen.
        $presentationTodos = [];
        $presentationMaterials = [];
        if ($presentationTask !== null) {
            $presentationTodos = TaskTodo::templatesForTask($presentationTask->id);
            $presentationMaterials = TaskMaterial::forTask($presentationTask->id);
        }
        // Objektkonsole und Mini-Datenbank des Projekts laden (Issue #44):
        // Der Editor füllt das Anweisungsfeld und startet die Datenbank mit
        // dem gespeicherten SQL-Seed, statt bei jedem Ausführen leer zu sein.
        $state = ProjectState::find($project->id);
        // Aufgabe „In der Werkstatt öffnen“ (Schüler-Sicht): Beschreibung,
        // ToDos, Materialien und Abgeben direkt in der Werkstatt anzeigen.
        $workingTask = null;
        $workingTodos = [];
        $workingDone = [];
        $workingOwnTodos = [];
        $workingMaterials = [];
        $workingSubmitted = false;
        $dbReadOnly = false;
        if ($presentationTask === null) {
            $working = TaskWorking::forProject($project->id);
            if ($working !== null && $working->studentId === $user->id) {
                $workingTask = Task::find($working->taskId);
                // Issue #45: Angehängte Datenbank der Aufgabe nur mit
                // Schreibrechten beschreibbar, sonst ist die Mini-Datenbank
                // lesbar (alle teilen sich dieselben Daten).
                if ($workingTask !== null && $workingTask->dbTemplateId > 0 && $workingTask->dbWrite !== 1) {
                    $dbReadOnly = true;
                }
            }
        }
        if ($workingTask !== null) {
            $workingTodos = TaskTodo::templatesForTask($workingTask->id);
            $studentTodos = TaskTodo::forStudentTasks($user->id, [$workingTask->id])[$workingTask->id] ?? [];
            foreach ($studentTodos as $studentTodo) {
                if ($studentTodo->templateId !== null) {
                    $workingDone[$studentTodo->templateId] = $studentTodo->done === 1;
                } else {
                    $workingOwnTodos[] = $studentTodo;
                }
            }
            $workingMaterials = TaskMaterial::forTask($workingTask->id);
            $submission = TaskSubmission::find($workingTask->id, $user->id);
            $workingSubmitted = $submission !== null && $submission['projectId'] === $project->id;
        }
        render('code_project', [
            'title' => $project->title,
            'active' => 'module-code',
            'project' => $project,
            'files' => $files,
            'user' => $user,
            'presentationTask' => $presentationTask,
            'presentationTodos' => $presentationTodos,
            'presentationMaterials' => $presentationMaterials,
            'workingTask' => $workingTask,
            'workingTodos' => $workingTodos,
            'workingDone' => $workingDone,
            'workingOwnTodos' => $workingOwnTodos,
            'workingMaterials' => $workingMaterials,
            'workingSubmitted' => $workingSubmitted,
            'replText' => $state['replText'] ?? '',
            'dbSql' => $state['dbSql'] ?? '',
            'dbReadOnly' => $dbReadOnly,
            'ownerName' => $owner !== null ? $owner->fullName() : '',
            'isForeign' => $project->ownerId !== $user->id,
            'diagramAuto' => SchoolClass::diagramAutoForUser($user),
            'dbPanel' => SchoolClass::dbPanelForUser($user),
            'dbTemplates' => $dbReadOnly ? [] : self::loadableDatabaseTemplates($user),
            'images' => $images,
            'imageUrls' => $imageUrls,
        ]);
    }

    public static function save(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !self::canOpen($user, $project)) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $title = trim((string) ($_POST['title'] ?? $project->title));
        if ($title === '') {
            $title = $project->title;
        }
        $isJava = $project->type === 'code' && $project->language === 'java';
        $isAsm = $project->type === 'code' && $project->language === 'asm';
        $postedFiles = $_POST['files'] ?? null;
        if (($isJava || $isAsm) && is_array($postedFiles)) {
            $files = ProjectFile::allForProject($project->id);
            $combined = '';
            $map = [];
            foreach ($files as $file) {
                $map[$file->id] = $file;
            }
            foreach ($postedFiles as $fileId => $fileContent) {
                $fileId = (int) $fileId;
                $content = (string) $fileContent;
                if ($fileId === 0) {
                    // Virtuelle Datei (Projekt hatte noch keine Dateien): die erste
                    // echte Datei anlegen - sonst die vorhandene aktualisieren.
                    // Wichtig (Issue #44): niemals bei jedem Autospeichern erneut
                    // anlegen, sonst entstehen doppelte Dateien.
                    if ($files === []) {
                        ProjectFile::create($project->id, $isAsm ? self::asmFileName($content, $title) : self::javaFileName($content, $title), $content);
                    } else {
                        $wanted = $isAsm ? self::asmFileName($content, $title) : self::javaFileName($content, $title);
                        $target = $files[0];
                        foreach ($files as $file) {
                            if (strcasecmp($file->name, $wanted) === 0) {
                                $target = $file;
                                break;
                            }
                        }
                        $target->save($content);
                    }
                } elseif (isset($map[$fileId])) {
                    $map[$fileId]->save($content);
                }
            }
            $fresh = ProjectFile::allForProject($project->id);
            foreach ($fresh as $file) {
                $combined .= $file->content . "\n";
            }
            $project->save($title, rtrim($combined, "\n"));
            self::saveProjectState($project->id);
            flash('success', 'Gespeichert.');
            redirect('/werkstatt/code/' . $project->id);
        }
        // Kompatibilitaet: Save ohne Dateiliste (z. B. aeltere clients, E2E, Python/HTML).
        $content = (string) ($_POST['content'] ?? '');
        $project->save($title, $content);
        $files = ProjectFile::allForProject($project->id);
        if ($files !== []) {
            $files[0]->save($content);
        }
        self::saveProjectState($project->id);
        flash('success', 'Gespeichert.');
        redirect('/werkstatt/code/' . $project->id);
    }

    /**
     * Objektkonsole (Anweisungsfeld) und Mini-Datenbank des Projekts
     * mitspeichern (Issue #44): Beide kommen aus dem Autospeichern des
     * Java-Editors. Fehlt der POST-Parameter, bleibt der bisherige Stand
     * erhalten, damit alte Clients und Fremdprojekte nichts verlieren.
     */
    private static function saveProjectState(int $projectId): void
    {
        $hasRepl = array_key_exists('repl', $_POST);
        $hasDb = array_key_exists('db', $_POST);
        if (!$hasRepl && !$hasDb) {
            return;
        }
        $state = ProjectState::find($projectId);
        $repl = $hasRepl ? (string) $_POST['repl'] : ($state['replText'] ?? '');
        $db = $hasDb ? (string) $_POST['db'] : ($state['dbSql'] ?? '');
        ProjectState::save($projectId, $repl, $db);
    }

    public static function addFile(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !self::canOpen($user, $project) || $project->type !== 'code' || ($project->language !== 'java' && $project->language !== 'asm')) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $isJava = $project->language === 'java';
        $isAsm = $project->language === 'asm';
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($isAsm) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,50}\.asm$/', $name)) {
                flash('error', 'Der Dateiname muss mit einem Buchstaben beginnen und auf .asm enden, z. B. programm.asm.');
                redirect('/werkstatt/code/' . $project->id);
            }
        } elseif (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,50}\.java$/', $name)) {
            flash('error', 'Der Dateiname muss wie eine Java-Klasse aufgebaut sein, z. B. Auto.java.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $files = ProjectFile::allForProject($project->id);
        if ($files === []) {
            // Bisher lag der Quelltext nur im Projektinhalt (virtuelle Datei):
            // als echte Datei uebernehmen, damit nichts verloren geht.
            ProjectFile::create($project->id, $isAsm ? self::asmFileName($project->content, $project->title) : self::javaFileName($project->content, $project->title), $project->content);
            $files = ProjectFile::allForProject($project->id);
        }
        if (count($files) >= 20) {
            flash('error', 'Mehr als 20 Dateien sind pro Projekt nicht möglich.');
            redirect('/werkstatt/code/' . $project->id);
        }
        foreach ($files as $file) {
            if (strcasecmp($file->name, $name) === 0) {
                flash('error', 'Eine Datei mit diesem Namen existiert bereits.');
                redirect('/werkstatt/code/' . $project->id);
            }
        }
        if ($isAsm) {
            ProjectFile::create($project->id, $name, "; " . $name . "\n; Schreibe hier deine Befehle, Marken mit Doppelpunkt (z. B. schleife:)\nHALT\n");
        } else {
            $class = substr($name, 0, -5);
            ProjectFile::create($project->id, $name, "public class " . $class . " {\n    \n}\n");
        }
        flash('success', 'Datei angelegt.');
        redirect('/werkstatt/code/' . $project->id);
    }

    public static function deleteFile(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        $file = ProjectFile::find((int) ($params['fileId'] ?? 0));
        if ($project === null || !self::canOpen($user, $project) || $file === null || $file->projectId !== $project->id) {
            flash('error', 'Datei nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $files = ProjectFile::allForProject($project->id);
        if (count($files) <= 1) {
            flash('error', 'Mindestens eine Datei muss bleiben.');
            redirect('/werkstatt/code/' . $project->id);
        }
        ProjectFile::delete($file->id);
        flash('success', 'Datei gelöscht.');
        redirect('/werkstatt/code/' . $project->id);
    }

    /** Eigenes Bild (Sprite) fuer ein Java-Projekt hochladen. */
    public static function uploadImage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !self::canOpen($user, $project)) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        if ($project->type !== 'code' || $project->language !== 'java') {
            flash('error', 'Bilder gibt es nur in Java-Projekten.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            flash('error', 'Bitte eine Bilddatei auswählen.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 1024 * 1024) {
            flash('error', 'Das Bild ist zu groß. Bitte maximal 1 MB.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $info = @getimagesize((string) $file['tmp_name']);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if ($info === false || !isset($allowed[(string) ($info['mime'] ?? '')])) {
            flash('error', 'Das Format wird nicht unterstützt. Bitte PNG, JPEG, GIF oder WebP hochladen.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $mime = (string) $info['mime'];
        if (ProjectImage::countForProject($project->id) >= 8) {
            flash('error', 'Pro Projekt sind maximal 8 Bilder möglich. Bitte zuerst ein Bild löschen.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $name = ProjectImage::uniqueName($project->id, (string) ($file['name'] ?? 'Bild'));
        $data = (string) file_get_contents((string) $file['tmp_name']);
        ProjectImage::create($project->id, $name, $mime, $data);
        flash('success', 'Bild "' . $name . '" hochgeladen. Im Code nutzt du es mit new Sprite(x, y, "' . $name . '").');
        redirect('/werkstatt/code/' . $project->id);
    }

    /** Eigenes Bild loeschen. */
    public static function deleteImage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        $name = (string) ($params['name'] ?? '');
        if ($project === null || !self::canOpen($user, $project)) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        if (!ProjectImage::existsByName($project->id, $name)) {
            flash('error', 'Bild nicht gefunden.');
            redirect('/werkstatt/code/' . $project->id);
        }
        ProjectImage::deleteByName($project->id, $name);
        flash('success', 'Bild "' . $name . '" gelöscht.');
        redirect('/werkstatt/code/' . $project->id);
    }

    /** Eigenes Bild ausliefern (fuer die Sprite-Darstellung in der IDE). */
    public static function serveImage(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $project = Project::find((int) ($params['id'] ?? 0));
        $name = (string) ($params['name'] ?? '');
        if ($project === null || $name === '' || !self::canOpen($user, $project)) {
            http_response_code(404);
            exit;
        }
        $image = ProjectImage::findByName($project->id, $name);
        $data = $image !== null ? ProjectImage::dataByName($project->id, $name) : null;
        if ($image === null || $data === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $image->mime);
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=86400');
        echo $data;
        exit;
    }

    /** Dateiname fuer eine virtuelle Java-Datei: erste Klasse im Quelltext oder Main.java. */
    private static function javaFileName(string $content, string $title): string
    {
        if (preg_match('/(?:class|interface)\s+([A-Za-z][A-Za-z0-9_]*)/', $content, $m) === 1) {
            return $m[1] . '.java';
        }
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $title);
        return ($clean === '' ? 'Main' : $clean) . '.java';
    }

    /** Dateiname fuer eine virtuelle Assembler-Datei: Titel oder programm.asm. */
    private static function asmFileName(string $content, string $title): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $title);
        return ($clean === '' ? 'programm' : $clean) . '.asm';
    }

    /**
     * Python pro Klasse freigeben oder sperren (Issue #6): Lehrkräfte und
     * Admins dürfen immer; Schülerinnen und Schüler nur, wenn die Klasse
     * Python nicht gesperrt hat (Freigabe vorzeitig, sonst Standard ab
     * Jahrgangsstufe 6).
     */
    private static function pythonAllowed(User $user): bool
    {
        if ($user->role !== 'student') {
            return true;
        }
        if ($user->classId === null) {
            return true;
        }
        $mode = \Infocraft\Controllers\ModuleController::modesFor((int) $user->classId)['python'] ?? '';
        if ($mode === 'sperren') {
            return false;
        }
        if ($mode === 'freigeben') {
            return true;
        }
        // Standard: Python ab Jahrgangsstufe 6 (wie das Code-Werkstatt-Modul).
        return 6 <= (Auth::levelOf($user) ?? 0);
    }

    private static function starterContent(string $type, string $language, string $title, string $starter = ''): string
    {
        if ($type === 'html') {
            return "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <title>" . $title . "</title>\n</head>\n<body>\n  <h1>" . $title . "</h1>\n  <p>Hier entsteht deine Seite.</p>\n</body>\n</html>\n";
        }
        if ($language === 'java') {
            $name = preg_replace('/[^A-Za-z0-9]/', '', $title);
            if ($name === '') {
                $name = 'MeineKlasse';
            }
            /* Bewusst ohne main-Methode: Objekte lassen sich direkt in der Datei
             * erzeugen (siehe unten) und danach in der Objektkonsole weiter
             * benutzen (Objektdiagramm). */
            $var = lcfirst($name);
            return "public class " . $name . " {\n    private String name;\n\n    public " . $name . "(String nameStart) {\n        name = nameStart;\n    }\n\n    public String getName() {\n        return name;\n    }\n\n    public void vorstellen() {\n        System.out.println(\"Ich bin \" + name);\n    }\n}\n\n" . $name . " " . $var . " = new " . $name . "(\"Alex\");\n" . $var . ".vorstellen();\n";
        }
        if ($language === 'asm') {
            /* Einstiegsbeispiel: zwei Zahlen addieren und ausgeben,
             * mit Textausgabe zum Ausprobieren. */
            return "; " . $title . "\n; Zwei Zahlen addieren und ausgeben\nLOAD R1, 5\nLOAD R2, 7\nADD R1, R2\nOUT R1\n\n; Text ausgeben (Anfuehrungszeichen noetig)\nOUTS \"Fertig!\"\nHALT\n";
        }
        if ($language === 'python') {
            // Startpakete für die Unterstufe (Issue #6): weniger Tipparbeit,
            // sofort ein sichtbares Ergebnis. Pyodide kennt keine echte
            // Turtle-Grafik, daher zeichnen wir mit Zeichenketten.
            if ($starter === 'zaehlen') {
                return "# " . $title . "\n# Zählen mit Python: eine Schleife mit Ausgabe\nfor zahl in range(1, 11):\n    print(\"Zahl:\", zahl)\n\n# Und rückwärts:\nfor zahl in range(10, 0, -1):\n    print(\"Rückwärts:\", zahl)\n";
            }
            if ($starter === 'zeichnen') {
                return "# " . $title . "\n# Zeichnen mit Zeichen: mit Zeichenketten ein Bild ausgeben\n# Pyodide hat keine Turtle-Grafik, aber mit print() zeichnen wir genauso.\n\nstern = \"\"\"\n    *\n   ***\n  *****\n   ***\n    *\n\"\"\"\nprint(stern)\n\n# Eigene Figur: unten eine Linie mit Wiederholung\nprint(\"-\" * 20)\n";
            }
            return "# " . $title . "\nprint(\"Hallo InfoCraft!\")\n";
        }
        return "# " . $title . "\nprint(\"Hallo InfoCraft!\")\n";
    }

    /** @return array<int, string> */
    /** @param list<array{folder: Folder, depth: int}> $folderRows */
    private static function folderNames(array $folderRows): array
    {
        $names = [0 => 'Ohne Ordner'];
        foreach ($folderRows as $row) {
            $names[$row['folder']->id] = str_repeat('· ', $row['depth']) . $row['folder']->name;
        }
        return $names;
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

    /**
     * Zugriffsrecht auf ein Projekt: die Eigentümerin oder der Eigentümer immer.
     * Lehrkräfte und die Administration duerfen Projekte der Schuelerinnen
     * und Schueler ihrer Klassen oeffnen und bearbeiten (unterstuetzen, helfen,
     * Zwischenstand ansehen). Auch von der HTML-Werkstatt genutzt.
     */
    public static function canOpen(User $user, Project $project): bool
    {
        if ($project->ownerId === $user->id) {
            return true;
        }
        if ($user->role === 'admin') {
            return true;
        }
        if (!Auth::isTeacher()) {
            return false;
        }
        $owner = User::find($project->ownerId);
        return $owner !== null
            && $owner->role === 'student'
            && $owner->classId !== null
            && SchoolClass::hasTeacher($owner->classId, $user->id);
    }

    /**
     * Projekte einer Schuelerin oder eines Schuelers (nur fuer Lehrkraefte,
     * nur fuer eigene Klassen): zum Ansehen und Unterstuetzen oeffnen.
     */
    public static function studentProjects(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/werkstatt/code');
        }
        $student = User::find((int) ($params['id'] ?? 0));
        $isAdmin = $user->role === 'admin';
        if ($student === null || $student->role !== 'student'
            || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id)))) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Schülerin oder dieser Schüler ist nicht in einer deiner Klassen.']);
            return;
        }
        $class = $student->classId !== null ? SchoolClass::find($student->classId) : null;
        render('code_student', [
            'title' => 'Projekte von ' . $student->fullName(),
            'active' => 'module-code',
            'student' => $student,
            'className' => $class !== null ? $class->name : '',
            'projects' => Project::all($student->id),
            'user' => $user,
        ]);
    }
}
