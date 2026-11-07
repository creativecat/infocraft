<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\Task;
use Infocraft\Models\TaskCatalog;
use Infocraft\Models\User;

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
            render('tasks_teacher', [
                'title' => 'Aufgabenpool',
                'active' => 'tasks',
                'tasks' => $tasks,
                'targets' => $targets,
                'classes' => SchoolClass::forTeacher($user->id, $user->role === 'admin'),
                'studentsByClass' => self::studentsByClass($user),
                'catalog' => $catalog,
                'filterLevel' => $filterLevel,
                'filterType' => $filterType,
                'ownedTitles' => array_map(static fn (Task $task): string => mb_strtolower(trim($task->title)), $tasks),
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
        if ($task === null || ($user->role !== 'admin' && $task->teacherId !== $user->id)) {
            flash('error', 'Aufgabe nicht gefunden.');
            redirect('/aufgaben');
        }
        Task::delete($task->id);
        flash('success', 'Aufgabe gelöscht.');
        redirect('/aufgaben');
    }

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
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                flash('error', 'Du unterrichtest diese Klasse nicht.');
                redirect('/aufgaben');
            }
            Task::assign($task->id, $classId, null);
            flash('success', 'Aufgabe an die Klasse ausgeteilt.');
            redirect('/aufgaben');
        }
        if ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student' || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id)))) {
                flash('error', 'Diese Person ist nicht in einer deiner Klassen.');
                redirect('/aufgaben');
            }
            Task::assign($task->id, null, $student->id);
            flash('success', 'Aufgabe ausgeteilt.');
            redirect('/aufgaben');
        }
        flash('error', 'Kein Ziel angegeben.');
        redirect('/aufgaben');
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
