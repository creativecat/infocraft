<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Startseite und Navigation
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.19.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Database;
use Infocraft\Models\SiteSetting;
use Infocraft\SsoM365;

final class HomeController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        render('home', [
            'title' => 'Start',
            'active' => 'home',
            'modules' => ModuleController::visible($user),
            /* Ankündigung der Administration (#183): kurzer Hinweis, der
             * auf der Startseite für alle Konten prominent erscheint. */
            'announcement' => SiteSetting::get('announcement', ''),
            'system' => [
                'driver' => Database::driver(),
                /* Demo-Modus (#180): Seiteneinstellung gewinnt, Fallback ist
                 * die Konfigurationsdatei. */
                'demo' => ic_demo_mode(),
                'sso' => SsoM365::enabled(),
            ],
        ]);
    }

    /**
     * Impressum der Instanz (#160): baut sich aus den Angaben der
     * Admin-Einstellungen auf und ist auch ohne Anmeldung erreichbar.
     */
    public static function imprint(): void
    {
        render('legal_imprint', [
            'title' => 'Impressum',
            'active' => '',
            'legal' => self::legalFields(),
        ]);
    }

    /**
     * Datenschutzerklaerung der Instanz (#160): baut sich aus den Angaben
     * der Admin-Einstellungen auf und ist auch ohne Anmeldung erreichbar.
     */
    public static function privacy(): void
    {
        render('legal_privacy', [
            'title' => 'Datenschutz',
            'active' => '',
            'legal' => self::legalFields(),
        ]);
    }

    /** @return array<string, string> */
    private static function legalFields(): array
    {
        $fields = [];
        foreach (['operator', 'person', 'street', 'zip', 'city', 'phone', 'email', 'extra', 'dsb', 'hosting', 'data', 'text'] as $key) {
            /* Mustervorlage (#172): Ohne eigene Angabe gilt der Vorgabetext. */
            $fields[$key] = ic_legal_field($key, SiteSetting::get('legal_' . $key, ''));
        }
        return $fields;
    }
}
