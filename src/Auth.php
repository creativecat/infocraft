<?php
declare(strict_types=1);

namespace Infocraft;

use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

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

    /**
     * Jahrgangsstufe einer Schülerin/eines Schülers aus dem Klassennamen
     * (6R -> 6). Lehrkräfte und Administration: null = unbegrenzt.
     */
    public static function levelOf(User $user): ?int
    {
        if ($user->role !== 'student') {
            return null;
        }
        if ($user->classId === null) {
            return 0;
        }
        $class = SchoolClass::find($user->classId);
        return $class === null ? 0 : $class->level();
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
