<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Folder;
use Infocraft\Models\Project;
use Infocraft\Models\Task;
use Infocraft\Models\TaskMaterial;
use Infocraft\Models\TaskSubmission;
use Infocraft\Models\TaskTodo;
use Infocraft\Models\TaskWorking;
use Infocraft\Models\User;

/**
 * HTML-Werkstatt: WYSIWYG-Editor mit Sofortvorschau. Nutzt die gleiche
 * Projekt- und Ordnerverwaltung wie die Code-Werkstatt (Projekte vom Typ 'html').
 */
final class HtmlController
{
    public static function index(): void
    {
        $user = self::requireAccess();
        $projects = array_values(array_filter(
            Project::all($user->id),
            static fn (Project $project): bool => $project->type === 'html'
        ));
        $folders = Folder::all($user->id);
        render('html_overview', [
            'title' => 'HTML-Werkstatt',
            'active' => 'module-html',
            'projects' => $projects,
            'folders' => $folders,
            'isTeacher' => Auth::isTeacher(),
            'user' => $user,
        ]);
    }

    public static function create(): void
    {
        $user = self::requireAccess();
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/werkstatt/html');
        }
        $template = (string) ($_POST['template'] ?? 'grund');
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $folderId = $folderId > 0 && Folder::belongsTo($folderId, $user->id) ? $folderId : null;
        Project::create($user->id, $folderId, $title, 'html', 'html', self::starter($template, $title));
        flash('success', 'Seite angelegt. Viel Spaß beim Gestalten!');
        redirect('/werkstatt/html');
    }

    public static function open(array $params): void
    {
        $user = self::requireAccess();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !CodeController::canOpen($user, $project) || $project->type !== 'html') {
            http_response_code(404);
            render('error', ['title' => 'Seite nicht gefunden', 'message' => 'Diese Seite gibt es nicht oder gehört dir nicht.']);
            return;
        }
        $owner = User::find($project->ownerId);
        // Präsentationsmodus (Issue #35): Hinweis-Banner in temporären Kopien.
        $presentationTask = Task::findByPresentationProject($project->id);
        if ($presentationTask !== null && $presentationTask->teacherId !== $user->id) {
            $presentationTask = null;
        }
        $presentationTodos = [];
        $presentationMaterials = [];
        if ($presentationTask !== null) {
            $presentationTodos = TaskTodo::templatesForTask($presentationTask->id);
            $presentationMaterials = TaskMaterial::forTask($presentationTask->id);
        }
        $workingTask = null;
        $workingTodos = [];
        $workingDone = [];
        $workingOwnTodos = [];
        $workingMaterials = [];
        $workingSubmitted = false;
        if ($presentationTask === null) {
            $working = TaskWorking::forProject($project->id);
            if ($working !== null && $working->studentId === $user->id) {
                $workingTask = Task::find($working->taskId);
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
        render('html_editor', [
            'title' => $project->title,
            'active' => 'module-html',
            'project' => $project,
            'user' => $user,
            'ownerName' => $owner !== null ? $owner->fullName() : '',
            'isForeign' => $project->ownerId !== $user->id,
            'presentationTask' => $presentationTask,
            'presentationTodos' => $presentationTodos,
            'presentationMaterials' => $presentationMaterials,
            'workingTask' => $workingTask,
            'workingTodos' => $workingTodos,
            'workingDone' => $workingDone,
            'workingOwnTodos' => $workingOwnTodos,
            'workingMaterials' => $workingMaterials,
            'workingSubmitted' => $workingSubmitted,
        ]);
    }

    public static function save(array $params): void
    {
        $user = self::requireAccess();
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || !CodeController::canOpen($user, $project) || $project->type !== 'html') {
            flash('error', 'Seite nicht gefunden.');
            redirect('/werkstatt/html');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $project->save($title !== '' ? $title : $project->title, (string) ($_POST['content'] ?? ''));
        flash('success', 'Gespeichert.');
        redirect('/werkstatt/html/' . $project->id);
    }

    /** Anmeldung und Stufen-Guard: HTML gehört zu Jahrgangsstufe 6 aufwärts (LehrplanPLUS). */
    private static function requireAccess(): User
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user->role === 'student' && (Auth::levelOf($user) ?? 0) < 6) {
            flash('error', 'Die HTML-Werkstatt gehört zu einer höheren Jahrgangsstufe.');
            redirect('/');
        }
        return $user;
    }

    private static function starter(string $template, string $title): string
    {
        $map = self::templates();
        $body = $map[$template] ?? $map['grund'];
        return str_replace('{TITEL}', $title, $body);
    }

    /** @return array<string, string> Vorlagen für neue Seiten. */
    private static function templates(): array
    {
        return [
            'grund' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>{TITEL}</title>
  <style>
    body { font-family: sans-serif; max-width: 40rem; margin: 2rem auto; }
    h1 { color: #4f46e5; }
  </style>
</head>
<body>
  <h1>{TITEL}</h1>
  <p>Hier schreibst du deinen eigenen Text.</p>
  <h2>Meine Liste</h2>
  <ul>
    <li>Erster Punkt</li>
    <li>Zweiter Punkt</li>
    <li>Dritter Punkt</li>
  </ul>
</body>
</html>
HTML,
            'galerie' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>{TITEL}</title>
  <style>
    body { font-family: sans-serif; margin: 2rem; }
    .galerie { display: flex; gap: 1rem; flex-wrap: wrap; }
    .bild { width: 10rem; height: 10rem; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
    .rot { background: #fee2e2; } .blau { background: #dbeafe; }
    .gruen { background: #dcfce7; } .gelb { background: #fef3c7; }
  </style>
</head>
<body>
  <h1>{TITEL}</h1>
  <p>Tausche die Emojis gegen eigene Bilder (img-Tag) und Farben.</p>
  <div class="galerie">
    <div class="bild rot">🌻</div>
    <div class="bild blau">🐋</div>
    <div class="bild gruen">🌳</div>
    <div class="bild gelb">🍋</div>
  </div>
</body>
</html>
HTML,
            'formular' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>{TITEL}</title>
  <style>
    body { font-family: sans-serif; max-width: 30rem; margin: 2rem auto; }
    label { display: block; margin-top: 1rem; }
    input, textarea { width: 100%; padding: 8px; }
    button { margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>{TITEL}</h1>
  <form>
    <label for="name">Dein Name</label>
    <input id="name" type="text" placeholder="Vor- und Nachname">
    <label for="nachricht">Deine Nachricht</label>
    <textarea id="nachricht" rows="4"></textarea>
    <button type="submit">Abschicken</button>
  </form>
</body>
</html>
HTML,
            'blank' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>{TITEL}</title>
</head>
<body>
  <h1>{TITEL}</h1>
  <p>Hier entsteht deine Seite.</p>
</body>
</html>
HTML,
        ];
    }
}
