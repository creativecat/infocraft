<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Einmalpasswörter für neue Konten und Resets
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft;

use Infocraft\Models\User;

/**
 * Einmalpasswörter (OTP) mit Merksatz nach der Satzbaulogik:
 * Die Anfangsbuchstaben der Wörter des Satzes ergeben das Passwort,
 * dazu zwei Ziffern und ein Sonderzeichen.
 */
final class Otp
{
    /** Deutsche Substantive für den Merksatz (Anfangsbuchstaben bilden das Passwort). */
    private const WORDS = [
        'Apfel', 'Amsel', 'Berg', 'Boot', 'Burg', 'Drache', 'Eule', 'Fahne',
        'Fuchs', 'Garten', 'Haus', 'Hund', 'Insel', 'Katze', 'Kanu', 'Leuchtturm',
        'Meer', 'Nacht', 'Nagel', 'Olive', 'Pferd', 'Qualle', 'Regen', 'Schiff',
        'Segel', 'Stern', 'Tanne', 'Tiger', 'Tulpe', 'Ulme', 'Vogel', 'Welle',
        'Wolke', 'Zelt', 'Zucker',
    ];

    private const SYMBOLS = ['!', '?', '#', '*', '+', '%'];

    /**
     * Einmalpasswort mit Merksatz erzeugen.
     * @return array{otp: string, sentence: string}
     */
    public static function make(): array
    {
        $keys = array_rand(self::WORDS, 4);
        shuffle($keys);
        $words = [];
        foreach ($keys as $key) {
            $words[] = self::WORDS[$key];
        }
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtolower(substr($word, 0, 1));
        }
        $otp = ucfirst($initials)
            . (string) random_int(10, 99)
            . self::SYMBOLS[random_int(0, count(self::SYMBOLS) - 1)];
        $sentence = implode(', ', array_slice($words, 0, 3)) . ' und ' . $words[3];
        return ['otp' => $otp, 'sentence' => $sentence];
    }

    /**
     * OTP für einen Nutzer setzen; fehlt der Anmeldename, wird er aus dem Namen erzeugt.
     * @return array{otp: string, sentence: string, login: string}
     */
    public static function issueTo(User $user): array
    {
        $made = self::make();
        $login = trim((string) $user->loginName);
        if ($login === '') {
            $login = self::loginNameFor($user->firstName, $user->lastName);
        }
        $hash = password_hash($made['otp'], PASSWORD_DEFAULT);
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ?, login_name = ? WHERE id = ?');
        $stmt->execute([$hash, $login, $user->id]);
        return ['otp' => $made['otp'], 'sentence' => $made['sentence'], 'login' => $login];
    }

    /** Anmeldename aus Vor- und Nachname erzeugen (klein, ohne Umlaute, bei Doppelung mit Zähler). */
    public static function loginNameFor(string $firstName, string $lastName): string
    {
        $base = self::slug($firstName) . '.' . self::slug($lastName);
        if ($base === '.') {
            $base = 'nutzer';
        }
        $login = $base;
        $n = 1;
        while (self::loginTaken($login)) {
            $n++;
            $login = $base . $n;
        }
        return $login;
    }

    private static function loginTaken(string $login): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE login_name = ?');
        $stmt->execute([$login]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function slug(string $text): string
    {
        $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue'];
        $text = strtr($text, $map);
        $text = strtolower($text);
        return preg_replace('/[^a-z0-9]+/', '', $text) ?? '';
    }
}
