<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Folder;
use Infocraft\Models\Project;
use Infocraft\Models\ProjectFile;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SiteSetting;
use Infocraft\Models\User;

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
            'folderNames' => self::folderNames($folders),
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
            default => in_array($_POST['language'] ?? '', ['python', 'java'], true) ? (string) $_POST['language'] : SiteSetting::defaultLanguage(),
        };
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $folderId = $folderId > 0 && Folder::belongsTo($folderId, $user->id) ? $folderId : null;
        Project::create($user->id, $folderId, $title, $type, $language, self::starterContent($type, $language, $title));
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
        Folder::create($user->id, $name);
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
        $files = ProjectFile::allForProject($project->id);
        if ($files === [] && $project->type === 'code' && $project->language === 'java') {
            // Noch keine Dateien: virtuelle Datei aus dem Projektinhalt anbieten.
            $name = self::javaFileName($project->content, $project->title);
            $files = [new ProjectFile(0, $project->id, $name, $project->content, 0, null)];
        }
        $owner = User::find($project->ownerId);
        render('code_project', [
            'title' => $project->title,
            'active' => 'module-code',
            'project' => $project,
            'files' => $files,
            'user' => $user,
            'ownerName' => $owner !== null ? $owner->fullName() : '',
            'isForeign' => $project->ownerId !== $user->id,
            'diagramAuto' => SchoolClass::diagramAutoForUser($user),
            'dbPanel' => SchoolClass::dbPanelForUser($user),
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
        $postedFiles = $_POST['files'] ?? null;
        if ($isJava && is_array($postedFiles)) {
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
                    // Virtuelle Datei (Projekt hatte noch keine Dateien): erste echte Datei anlegen.
                    ProjectFile::create($project->id, self::javaFileName($content, $title), $content);
                } elseif (isset($map[$fileId])) {
                    $map[$fileId]->save($content);
                }
            }
            $fresh = ProjectFile::allForProject($project->id);
            foreach ($fresh as $file) {
                $combined .= $file->content . "\n";
            }
            $project->save($title, rtrim($combined, "\n"));
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
        flash('success', 'Gespeichert.');
        redirect('/werkstatt/code/' . $project->id);
    }

    public static function addFile(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !self::canOpen($user, $project) || $project->type !== 'code' || $project->language !== 'java') {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,50}\.java$/', $name)) {
            flash('error', 'Der Dateiname muss wie eine Java-Klasse aufgebaut sein, z. B. Auto.java.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $files = ProjectFile::allForProject($project->id);
        if ($files === []) {
            // Bisher lag der Quelltext nur im Projektinhalt (virtuelle Datei):
            // als echte Datei uebernehmen, damit nichts verloren geht.
            ProjectFile::create($project->id, self::javaFileName($project->content, $project->title), $project->content);
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
        $class = substr($name, 0, -5);
        ProjectFile::create($project->id, $name, "public class " . $class . " {\n    \n}\n");
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

    /** Dateiname fuer eine virtuelle Java-Datei: erste Klasse im Quelltext oder Main.java. */
    private static function javaFileName(string $content, string $title): string
    {
        if (preg_match('/(?:class|interface)\s+([A-Za-z][A-Za-z0-9_]*)/', $content, $m) === 1) {
            return $m[1] . '.java';
        }
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $title);
        return ($clean === '' ? 'Main' : $clean) . '.java';
    }

    private static function starterContent(string $type, string $language, string $title): string
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
        return "# " . $title . "\nprint(\"Hallo InfoCraft!\")\n";
    }

    /** @return array<int, string> */
    private static function folderNames(array $folders): array
    {
        $names = [0 => 'Ohne Ordner'];
        foreach ($folders as $folder) {
            $names[$folder->id] = $folder->name;
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
