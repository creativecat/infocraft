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
            $byClass[] = ['class' => $class, 'students' => User::byClass($class->id)];
        }
        render('teacher_classes', [
            'title' => 'Meine Klassen',
            'active' => 'unterricht',
            'byClass' => $byClass,
            'teachers' => $isAdmin ? User::teachers() : [],
            'allClasses' => $isAdmin ? SchoolClass::all() : [],
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
}
