<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Globale Hilfsfunktionen, Assets, CSRF, Versionierung
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.16.0
 */
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
 * Asset-Version: Bei jeder Änderung an den JS-/CSS-Dateien erhöhen. Zusammen mit
 * der Datei-Zeitstempel sichert das das Cache-Busting, auch wenn Deployments die
 * Zeitstempel erhalten (rsync --times, Docker-Layer): Browser laden dann garantiert
 * die neue Datei statt einer alten Version aus dem Cache.
 */
const ASSET_VERSION = '73';

/**
 * Programmversion nach Semantic Versioning (#145): MAJOR.MINOR.PATCH.
 * Eine neue Funktion erhoeht MINOR, reine Fehlerbehebung PATCH; MAJOR
 * bleibt fuer inkompatible Umstiege reserviert. Die Entwicklungshistorie
 * steht in CHANGELOG.md im Wurzelverzeichnis.
 */
const APP_VERSION = '0.17.0';

/**
 * Dateisystem-Pfad eines Assets unter public/. __DIR__ ist hier src/, der
 * Projektstamm liegt also EIN Verzeichnis hoeher (dirname(__DIR__) genuegt,
 * nicht dirname(__DIR__, 2), das waere das Verzeichnis UEBER dem Stamm).
 * INFOCRAFT_ROOT aus bootstrap.php wird bevorzugt, der Fallback deckt
 * direktes require von Support.php ab.
 */
function ic_public_file(string $rel): string
{
    $root = defined('INFOCRAFT_ROOT') ? INFOCRAFT_ROOT : dirname(__DIR__);
    return $root . '/public/' . ltrim($rel, '/');
}

/**
 * Asset-URL mit Cache-Busting: haengt die Aenderungszeit der Datei als
 * Versionsparameter an, damit Browser nach einem Update die neue Datei
 * laden und keine alte Version aus dem Cache zeigen.
 */
function asset_url(string $path): string
{
    $file = ic_public_file($path);
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return url($path) . '?v=' . ASSET_VERSION . '.' . $v;
}

/**
 * Vendor-Skript (highlight.js, mermaid) lokal ausliefern, damit die Werkstatt
 * auch in Schulnetzen ohne freigeschaltete CDNs laeuft. Fehlt die Datei oder
 * ist sie unvollstaendig (abgebrochener Download), greift ersatzweise das CDN:
 * Die Seite bleibt damit immer bedienbar, offline laeuft sie sobald die Datei
 * einmal im Repo liegt (der CI-Runner legt sie automatisch bereit).
 */
function vendor_asset(string $repoRel, string $cdn, int $minBytes = 1000): string
{
    $file = ic_public_file($repoRel);
    if (is_file($file) && (int) filesize($file) >= $minBytes) {
        return asset_url($repoRel);
    }
    return $cdn;
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

function render(string $viewFile, array $data = []): void
{
    // Reihenfolge wichtig: extract(EXTR_SKIP) ueberschreibt niemals bereits
    // gesetzte Variablen. Titel, aktiver Navi-Punkt und die Layout-Wahl stehen
    // deshalb bewusst NACH dem extract und werden aus $data gelesen, sonst
    // wuerde jeder Seite der Default-Titel bleiben bzw. das bare-Layout
    // (Login, Musterloesungsfenster) nie greifen.
    // Der Parameter heisst bewusst $viewFile: EXTR_SKIP ueberspringt Daten-
    // Schluessel, die bereits belegte Variablen treffen. Mit $template wuerde
    // der Schluessel 'template' (z. B. das SqlTemplate-Objekt der Werkstatt)
    // verschattet, mit $view der Schluessel 'view' (Unternavigation Aufgaben,
    // z. B. 'katalog'). $viewFile wird von keinem Controller als Schluessel
    // uebergeben (#46/#47 Nacharbeit).
    $user = Infocraft\Auth::user();
    extract($data, EXTR_SKIP);
    $title = isset($data['title']) ? (string) $data['title'] : 'InfoCraft';
    $active = isset($data['active']) ? (string) $data['active'] : '';
    $bare = isset($data['bare']) ? (bool) $data['bare'] : false;
    ob_start();
    require INFOCRAFT_ROOT . '/templates/' . $viewFile . '.php';
    $content = (string) ob_get_clean();
    require INFOCRAFT_ROOT . '/templates/layout.php';
}
