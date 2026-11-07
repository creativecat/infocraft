<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\Task;
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
            render('tasks_teacher', [
                'title' => 'Aufgabenpool',
                'active' => 'tasks',
                'tasks' => $tasks,
                'targets' => $targets,
                'classes' => SchoolClass::forTeacher($user->id, $user->role === 'admin'),
                'studentsByClass' => self::studentsByClass($user),
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
