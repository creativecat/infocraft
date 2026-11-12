<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Verwaltung: Konten, Klassen, Einstellungen, Import
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.19.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\CsvImporter;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SiteSetting;
use Infocraft\Models\User;

final class AdminController
{
    public static function users(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        /* #179: Lehrkräfte sehen nur die Konten ihrer eigenen Klassen,
         * Admins alle. Die eigene Person bleibt sichtbar. */
        $isAdmin = Auth::isAdmin();
        $classes = $isAdmin ? SchoolClass::all() : SchoolClass::forTeacher($user->id, false);
        $entries = User::allWithClass(500);
        $stats = ['student' => 0, 'teacher' => 0, 'admin' => 0];
        if (!$isAdmin) {
            $classIds = [];
            foreach ($classes as $class) {
                $classIds[] = (int) $class->id;
            }
            $filtered = [];
            foreach ($entries as $entry) {
                $inOwnClass = $entry['user']->classId !== null && in_array((int) $entry['user']->classId, $classIds, true);
                if ($inOwnClass || $entry['user']->id === $user->id) {
                    $filtered[] = $entry;
                }
            }
            $entries = $filtered;
        }
        foreach ($entries as $entry) {
            $role = $entry['user']->role;
            if (isset($stats[$role])) {
                $stats[$role]++;
            }
        }
        render('admin_users', [
            'title' => 'Nutzerverwaltung',
            'active' => 'admin',
            'users' => $entries,
            'stats' => $stats,
            'classes' => $classes,
            'isAdminView' => $isAdmin,
            'demo' => ic_demo_mode(),
        ]);
    }

    /**
     * Nutzerliste als CSV (#183): Export der Konten für die Administration,
     * Semikolon-getrennt, direkt öffnenbar in Tabellenkalkulationen.
     */
    public static function usersCsv(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Der Export ist nur für die Administration zugänglich.');
            redirect('/admin/users');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nutzerliste.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Vorname', 'Nachname', 'Anmeldename', 'Rolle', 'Klasse'], ';');
        foreach (User::allWithClass(2000) as $entry) {
            fputcsv($out, [
                $entry['user']->firstName,
                $entry['user']->lastName,
                $entry['user']->loginName ?? '-',
                $entry['user']->role,
                $entry['class'] ?? '-',
            ], ';');
        }
        fclose($out);
        exit;
    }

    public static function importForm(): void
    {
        /* #178: Der CSV-Import ist bewusst eine Admin-Aufgabe (ganze Schule),
         * Lehrkräfte pflegen ihre Klassen über ihre Klassen-Seiten. */
        if (!Auth::isAdmin()) {
            flash('error', 'Der CSV-Import ist nur für die Administration zugänglich.');
            redirect('/');
        }
        render('admin_import', [
            'title' => 'CSV-Import',
            'active' => 'admin',
        ]);
    }

    public static function import(): void
    {
        if (!Auth::isAdmin()) {
            flash('error', 'Der CSV-Import ist nur für die Administration zugänglich.');
            redirect('/');
        }
        csrf_validate();

        $csv = '';
        if (isset($_FILES['csv_file']) && (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            $csv = (string) file_get_contents($_FILES['csv_file']['tmp_name']);
        } else {
            $csv = (string) ($_POST['csv_text'] ?? '');
        }

        $result = CsvImporter::import($csv);
        flash('success', 'Import abgeschlossen: ' . $result['created'] . ' neu angelegt, ' . $result['updated'] . ' aktualisiert.');
        foreach ($result['errors'] as $error) {
            flash('error', $error);
        }
        if ($result['otps'] !== []) {
            $_SESSION['otp_results'] = $result['otps'];
            redirect('/unterricht/passwort/ergebnis');
        }
        redirect('/admin/import');
    }

    /**
     * Schulweite Einstellungen (nur Admin): Standard-Programmiersprache
     * für die Code-Werkstatt, Aufgaben-Varianten und neue Projekte.
     */
    public static function settings(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Dieser Bereich ist nur für Admins zugänglich.');
            redirect('/');
        }
        render('admin_settings', [
            'title' => 'Admin-Einstellungen',
            'active' => 'admin',
            'defaultLanguage' => SiteSetting::defaultLanguage(),
            'legal' => self::legalValues(),
            'demo' => ic_demo_mode(),
            'announcement' => SiteSetting::get('announcement', ''),
            'user' => $user,
        ]);
    }

    public static function settingsSave(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Dieser Bereich ist nur für Admins zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $language = (string) ($_POST['default_language'] ?? 'java');
        SiteSetting::set('default_language', $language === 'python' ? 'python' : 'java');
        flash('success', 'Einstellungen gespeichert. Die Standard-Programmiersprache ist jetzt ' . ($language === 'python' ? 'Python' : 'Java') . '.');
        redirect('/admin/einstellungen');
    }

    /**
     * Demo-Modus umschalten (#180): Seiteneinstellung 'demo_mode' ('1'/'0'),
     * uebersteuert den Wert aus der Konfigurationsdatei. Aus schaltet die
     * Demo-Konten auf der Login-Seite ab.
     */
    public static function demoSave(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Dieser Bereich ist nur für Admins zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $on = ($_POST['demo_mode'] ?? '') === '1';
        SiteSetting::set('demo_mode', $on ? '1' : '0');
        flash('success', 'Demo-Modus ' . ($on ? 'aktiviert. Die Demo-Konten stehen wieder bereit.' : 'deaktiviert. Die Schnellanmeldung ist aus.'));
        redirect('/admin/einstellungen');
    }

    /**
     * Ankuendigung auf der Startseite (#183): kurzer Hinweis fuer alle
     * Konten; ein leerer Text loescht die Ankuendigung.
     */
    public static function announcementSave(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Dieser Bereich ist nur für Admins zugänglich.');
            redirect('/');
        }
        csrf_validate();
        $text = trim((string) ($_POST['announcement'] ?? ''));
        SiteSetting::set('announcement', $text);
        flash('success', $text === '' ? 'Ankündigung entfernt.' : 'Ankündigung gespeichert. Sie erscheint jetzt auf der Startseite.');
        redirect('/admin/einstellungen');
    }

    /**
     * Impressum und Datenschutz (#160): strukturierte Angaben der Instanz,
     * schulweit in den Seiteneinstellungen. Die Felder sind bewusst frei
     * lassbar; erst hinterlegte Inhalte erscheinen im Frontend.
     */
    private static function legalKeys(): array
    {
        return ['kind', 'operator', 'person', 'street', 'zip', 'city', 'phone', 'email', 'extra', 'dsb', 'hosting', 'data', 'text'];
    }

    /** @return array<string, string> */
    private static function legalValues(): array
    {
        $values = [];
        foreach (self::legalKeys() as $key) {
            /* Mustervorlage (#172): Das Formular zeigt den Vorgabetext als
             * vorbelegten Text; speichern ueberschreibt ihn dauerhaft. */
            $values[$key] = ic_legal_field($key, SiteSetting::get('legal_' . $key, ''));
        }
        return $values;
    }

    public static function legalSave(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isAdmin()) {
            flash('error', 'Dieser Bereich ist nur für Admins zugänglich.');
            redirect('/');
        }
        csrf_validate();
        foreach (self::legalKeys() as $key) {
            $value = isset($_POST['legal_' . $key]) ? trim((string) $_POST['legal_' . $key]) : '';
            /* Betreiber-Art (#167): Schule/Institution oder Privatperson.
             * Ungueltige Werte fallen auf den Schulfall zurueck. */
            if ($key === 'kind') {
                $value = $value === 'private' ? 'private' : 'school';
            }
            SiteSetting::set('legal_' . $key, $value);
        }
        flash('success', 'Impressum und Datenschutz gespeichert. Die Seiten im Fuß der Plattform zeigen die Angaben sofort.');
        redirect('/admin/einstellungen');
    }
}
