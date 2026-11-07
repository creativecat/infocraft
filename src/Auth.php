<?php
declare(strict_types=1);

namespace Infocraft;

final class Auth
{
    public static function user(): ?User
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $user = User::find((int) $_SESSION['uid']);
        if ($user === null) {
            unset($_SESSION['uid']);
        }
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user->role === 'admin';
    }

    public static function isTeacher(): bool
    {
        $user = self::user();
        return $user !== null && ($user->role === 'teacher' || $user->role === 'admin');
    }

    public static function login(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $user->id;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
