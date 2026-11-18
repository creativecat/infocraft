<?php
/**
 * InfoCraft - offene Lernplattform fuer Informatik
 * Datei: Stufen-Filter (#263)
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.38.0
 */
declare(strict_types=1);

namespace Infocraft;

use Infocraft\Models\User;

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
