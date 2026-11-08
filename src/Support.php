<?php
declare(strict_types=1);

/**
 * Globale Hilfsfunktionen: HTML-Ausgabe, URLs, CSRF, Flash-Meldungen, Template-Rendering.
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $basePath = defined('BASE_PATH') ? (string) BASE_PATH : '';
    return $basePath . '/' . ltrim($path, '/');
}

/**
 * Jahrgangsstufen mit Informatik am Gymnasium Bayern (LehrplanPLUS):
 * Informatik ist ein eigenstaendiges Fach erst ab Jahrgangsstufe 9,
 * in der 8. Jahrgangsstufe gibt es keinen Informatikunterricht.
 */
function valid_levels(): array
{
    return [6, 7, 9, 10, 11, 12, 13];
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_validate(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function flash_all(): array
{
    $messages = isset($_SESSION['flash']) && is_array($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $messages;
}

function render(string $template, array $data = []): void
{
    $title = 'InfoCraft';
    $active = '';
    $bare = false;
    $user = Infocraft\Auth::user();
    extract($data, EXTR_SKIP);
    ob_start();
    require INFOCRAFT_ROOT . '/templates/' . $template . '.php';
    $content = (string) ob_get_clean();
    require INFOCRAFT_ROOT . '/templates/layout.php';
}
