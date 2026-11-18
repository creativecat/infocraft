<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Verteilen und Stufen-Filter, zentral statt ueber Controller verteilt (#263)
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.38.0
 */
declare(strict_types=1);

namespace Infocraft;

use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

/**
 * Distribution: Löst aus POST den Verteil-Ziel auf (ganze Klasse oder einzelne
 * Schülerin bzw. Schüler) mit Berechtigungsprüfung. Vorher stand diese Logik
 * in Code-, SQL- und Quiz-Controller fast identisch (#263).
 */
final class Distribution
{
    /**
     * Ziele des Austeilens aus den POST-Feldern class_id/user_id auflösen.
     * Admins dürfen alles, Lehrkräfte nur eigene Klassen.
     *
     * @return list<User> Empfangende Konten, leer bei fehlender Berechtigung oder fehlendem Ziel
     */
    public static function targets(User $user): array
    {
        $isAdmin = $user->role === 'admin';
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                return [];
            }
            return User::byClass($classId);
        }
        if ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student') {
                return [];
            }
            if (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher((int) $student->classId, $user->id))) {
                return [];
            }
            return [$student];
        }
        return [];
    }
}

/**
 * LevelFilter: Die Jahrgangsstufen-Sichtbarkeit war über mehrere Controller
 * verteilt (Auth::levelOf-Vergleiche). Hier hat sie jetzt einen Namen (#263).
 */
final class LevelFilter
{
    /** Jahrgangsstufe des Kontos, 0 ohne Anmeldung oder ohne Klasse. */
    public static function levelOf(?User $user): int
    {
        if ($user === null) {
            return 0;
        }
        return Auth::levelOf($user) ?? 0;
    }

    /**
     * Darf das Konto Inhalte ab dieser Mindeststufe sehen?
     * Lehrkräfte und Admins sehen alles, Schülerinnen und Schüler nur ihre Stufe aufwärts.
     */
    public static function allows(?User $user, int $minLevel): bool
    {
        if ($user === null || $user->role !== 'student') {
            return true;
        }
        return self::levelOf($user) >= $minLevel;
    }
}
