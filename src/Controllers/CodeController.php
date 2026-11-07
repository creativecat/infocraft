<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Folder;
use Infocraft\Models\Project;
use Infocraft\Models\SchoolClass;
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
            default => in_array($_POST['language'] ?? '', ['python', 'java'], true) ? (string) $_POST['language'] : 'python',
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
        if ($project === null || $project->ownerId !== $user->id) {
            http_response_code(404);
            render('error', ['title' => 'Projekt nicht gefunden', 'message' => 'Dieses Projekt gibt es nicht oder gehört dir nicht.']);
            return;
        }
        render('code_project', [
            'title' => $project->title,
            'active' => 'module-code',
            'project' => $project,
            'user' => $user,
            'diagramAuto' => SchoolClass::diagramAutoForUser($user),
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
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        $title = trim((string) ($_POST['title'] ?? $project->title));
        if ($title === '') {
            $title = $project->title;
        }
        $project->save($title, (string) ($_POST['content'] ?? ''));
        flash('success', 'Gespeichert.');
        redirect('/werkstatt/code/' . $project->id);
    }

    private static function starterContent(string $type, string $language, string $title): string
    {
        if ($type === 'html') {
            return "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <title>" . $title . "</title>\n</head>\n<body>\n  <h1>" . $title . "</h1>\n  <p>Hier entsteht deine Seite.</p>\n</body>\n</html>\n";
        }
        if ($language === 'java') {
            return "public class " . preg_replace('/[^A-Za-z0-9]/', '', $title) . " {\n    public static void main(String[] args) {\n        System.out.println(\"Hallo InfoCraft!\");\n    }\n}\n";
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
}
