<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Config;
use Infocraft\SsoM365;
use Infocraft\User;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        render('login', [
            'title' => 'Anmelden',
            'bare' => true,
            'ssoEnabled' => SsoM365::enabled(),
            'demoUsers' => self::demoUsers(),
        ]);
    }

    public static function login(): void
    {
        csrf_validate();
        $loginName = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = User::findByLoginName($loginName);
        if ($user !== null && $user->passwordHash !== null && password_verify($password, $user->passwordHash)) {
            Auth::login($user);
            flash('success', 'Willkommen, ' . $user->firstName . '!');
            redirect('/');
        }

        flash('error', 'Anmeldung fehlgeschlagen. Bitte Anmeldename und Passwort prüfen.');
        redirect('/login');
    }

    public static function demoLogin(): void
    {
        if (!Config::get('app.demo_mode')) {
            http_response_code(404);
            exit('Der Demo-Modus ist deaktiviert.');
        }
        csrf_validate();
        $user = User::find((int) ($_POST['user_id'] ?? 0));
        if ($user === null) {
            flash('error', 'Unbekanntes Demo-Konto.');
            redirect('/login');
        }
        Auth::login($user);
        flash('success', 'Angemeldet als ' . $user->fullName() . ' (Demo).');
        redirect('/');
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public static function ssoStart(): void
    {
        if (!SsoM365::enabled()) {
            flash('error', 'Microsoft-SSO ist nicht konfiguriert (siehe docs/sso-m365.md).');
            redirect('/login');
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['sso_state'] = $state;
        header('Location: ' . SsoM365::authorizeUrl($state));
        exit;
    }

    public static function ssoCallback(): void
    {
        $code = trim((string) ($_GET['code'] ?? ''));
        $state = (string) ($_GET['state'] ?? '');

        if ($code === '') {
            flash('error', 'SSO wurde abgebrochen.');
            redirect('/login');
        }
        if (!isset($_SESSION['sso_state']) || !hash_equals((string) $_SESSION['sso_state'], $state)) {
            flash('error', 'SSO-State ungültig, eventuell ist die Sitzung abgelaufen. Bitte erneut versuchen.');
            redirect('/login');
        }
        unset($_SESSION['sso_state']);

        try {
            $claims = SsoM365::handleCallback($code);
        } catch (\RuntimeException $e) {
            flash('error', 'SSO fehlgeschlagen: ' . $e->getMessage());
            redirect('/login');
        }

        $oid = (string) ($claims['oid'] ?? '');
        $givenName = trim((string) ($claims['given_name'] ?? ''));
        $familyName = trim((string) ($claims['family_name'] ?? ''));
        if ($oid === '') {
            flash('error', 'Die SSO-Antwort war unvollständig.');
            redirect('/login');
        }

        $user = User::findByExternalId($oid);
        if ($user === null && $givenName !== '' && $familyName !== '') {
            $user = User::findByName($givenName, $familyName);
            if ($user !== null) {
                $user->setExternalId($oid);
            }
        }
        if ($user === null) {
            if (!Config::get('sso.auto_create', true)) {
                flash('error', 'Für dieses Schulkonto besteht noch kein Zugang.');
                redirect('/login');
            }
            if ($givenName === '' || $familyName === '') {
                flash('error', 'Der Name im Schulkonto ist unvollständig.');
                redirect('/login');
            }
            $user = User::create($givenName, $familyName, 'student', null, null, null, $oid);
            flash('info', 'Dein Zugang wurde eingerichtet. Rolle und Klasse ergänzt die Lehrkraft.');
        }

        Auth::login($user);
        flash('success', 'Willkommen, ' . $user->firstName . '!');
        redirect('/');
    }

    /** @return list<array{user: User, class: ?string}> */
    private static function demoUsers(): array
    {
        if (!Config::get('app.demo_mode')) {
            return [];
        }
        return User::allWithClass(12, true);
    }
}
