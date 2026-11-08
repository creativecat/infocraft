<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Config;
use Infocraft\CsvImporter;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SiteSetting;
use Infocraft\Models\User;

final class AdminController
{
    public static function users(): void
    {
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        render('admin_users', [
            'title' => 'Nutzerverwaltung',
            'active' => 'admin',
            'users' => User::allWithClass(),
            'stats' => User::countByRole(),
            'classes' => SchoolClass::all(),
            'demo' => (bool) Config::get('app.demo_mode'),
        ]);
    }

    public static function importForm(): void
    {
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
            redirect('/');
        }
        render('admin_import', [
            'title' => 'CSV-Import',
            'active' => 'admin',
        ]);
    }

    public static function import(): void
    {
        if (!Auth::isTeacher()) {
            flash('error', 'Dieser Bereich ist nur für Lehrkräfte zugänglich.');
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
}
