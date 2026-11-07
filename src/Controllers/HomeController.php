<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Config;
use Infocraft\Database;
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
            'system' => [
                'driver' => Database::driver(),
                'demo' => (bool) Config::get('app.demo_mode'),
                'sso' => SsoM365::enabled(),
            ],
        ]);
    }
}
