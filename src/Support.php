<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Globale Hilfsfunktionen, Assets, CSRF, Versionierung
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.26.0
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
const ASSET_VERSION = '82';

/**
 * Programmversion nach Semantic Versioning (#145): MAJOR.MINOR.PATCH.
 * Eine neue Funktion erhoeht MINOR, reine Fehlerbehebung PATCH; MAJOR
 * bleibt fuer inkompatible Umstiege reserviert. Die Entwicklungshistorie
 * steht in CHANGELOG.md im Wurzelverzeichnis.
 */
const APP_VERSION = '0.26.0';

/**
 * Projekt-Icon (#169): passendes Emoji je Projektart. Frueher stand die
 * Kette in den Templates, Neuzugaenge (Kellerautomat, Turingmaschine,
 * Schaltwerk) fielen dabei auf das Python-Icon zurueck. Zentral hier
 * gibt es genau eine Stelle, die alle Arten kennt.
 */
function ic_proj_icon(string $type, string $language): string
{
    if ($type === 'html') {
        return '🌐';
    }
    return match ($language) {
        'java' => '☕',
        'python' => '🐍',
        'asm' => '⚙️',
        'blocks' => '🧩',
        'automaton' => '🤖',
        'pda' => '🥞',
        'turing' => '🧾',
        'logic' => '🔌',
        default => '📄',
    };
}

/**
 * Projekt-Bezeichnung (#171): kurzer Text fuer die Pill am Projekt.
 * Wie ic_proj_icon() zentral gepflegt, damit Uebersicht und Karten
 * ueberall dieselben Namen zeigen.
 */
function ic_proj_kind(string $type, string $language): string
{
    if ($type === 'html') {
        return 'HTML-Seite';
    }
    return match ($language) {
        'java' => 'Java',
        'python' => 'Python',
        'asm' => 'Assembler',
        'blocks' => 'Blöcke',
        'automaton' => 'Automat',
        'pda' => 'Kellerautomat',
        'turing' => 'Turingmaschine',
        'logic' => 'Schaltwerk',
        default => 'Projekt',
    };
}

/**
 * Mustervorlage fuer Impressum und Datenschutz (#172): Vorgabetexte, die
 * sofort gelten, wenn die Verwaltung nichts Eigenes hinterlegt hat. Sie
 * sind auf eine schulische Lernplattform mit zwei Werkstatt-Rollen
 * (Schule/Institution oder Privatperson) zugeschnitten und erfuellen
 * Art. 13 DSGVO (Informationspflichten). Adress- und Namensfelder bleiben
 * bewusst ohne Muster: Sie sind immer instanzspezifisch. Wer eigene Texte
 * in den Admin-Einstellungen speichert, ueberschreibt das Muster.
 *
 * @return array<string, string>
 */
function ic_legal_defaults(): array
{
    return [
        /* Ergaenzende Angaben im Impressum: redaktionelle Verantwortung. */
        'extra' => "Inhaltliche Verantwortung gemäß § 18 Abs. 2 MStV: die Schulleitung bzw. die betreibende Person.",
        /* Verarbeitete Daten: Datenlage der Plattform (Konten, Unterrichtsdaten,
         * nur technisch notwendiges Session-Cookie, kein Tracking). */
        'data' => "Für die Nutzung dieser Lernplattform werden folgende personenbezogene Daten verarbeitet:\n"
            . "• Vorname, Nachname, Benutzername und ein verschlüsseltes Passwort (Unterrichtskonto),\n"
            . "• die Zuordnung zu einer Klasse oder Lerngruppe,\n"
            . "• von Nutzerinnen und Nutzern angelegte Projekte, Aufgabenbearbeitungen, Abgaben, Rückmeldungen, Bewertungen und Quiz-Ergebnisse (pädagogische Dokumentation des Lernstands),\n"
            . "• selbst hochgeladene Bilder und Dateien im eigenen Arbeitsbereich.\n"
            . "Technisch notwendig ist ausschließlich ein Session-Cookie für die Anmeldung. Die Plattform setzt keine Werbe- oder Analyse-Cookies, gibt keine Daten zu Marketingzwecken weiter und bindet keine Tracking-Dienste ein.",
        /* Ergänzende Hinweise: Rechtsgrundlagen, Speicherdauer, Rechte, Beschwerderecht. */
        'text' => "Rechtsgrundlagen: Bei schulischer Nutzung erfolgen die Verarbeitung auf Grundlage der Erfüllung rechtlicher Pflichten und der im öffentlichen Interesse liegenden Bildungsaufgaben (Art. 6 Abs. 1 lit. c und e DSGVO); das schulische Verhältnis begründet zudem die Verarbeitung zur Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO). Bei einem Angebot einer Privatperson stützt sich die Verarbeitung auf Einwilligung und berechtigtes Interesse (Art. 6 Abs. 1 lit. a und f DSGVO).\n"
            . "Speicherdauer: Die Daten werden für die Dauer der Teilnahme am Unterricht und danach entsprechend den gesetzlichen Aufbewahrungs- und Dokumentationspflichten vorgehalten; anschließend werden sie gelöscht.\n"
            . "Ihre Rechte: Sie haben das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch (Art. 21). Widerspruch und Auskunft nehmen Sie bitte per E-Mail an die im Impressum genannte Adresse.\n"
            . "Beschwerderecht: Sie können sich bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO); in Bayern ist das Bayerische Landesamt für Datenschutzaufsicht (BayLDA) zuständig.",
    ];
}

/**
 * Rechtstext eines Feldes (#172): Eigene Angabe der Verwaltung gewinnt,
 * sonst gilt die Mustervorlage aus ic_legal_defaults().
 */
function ic_legal_field(string $key, string $value): string
{
    if ($value !== '') {
        return $value;
    }
    $defaults = ic_legal_defaults();
    return $defaults[$key] ?? '';
}

/**
 * Demo-Modus der Instanz (#180): Die Seiteneinstellung 'demo_mode'
 * ('1'/'0') aus den Admin-Einstellungen gewinnt, ohne Eintrag gilt der
 * Wert aus der Konfigurationsdatei (app.demo_mode). Alle Stellen, die
 * Demo-Konten und Schnellanmeldung steuern, fragen diesen Helfer.
 */
function ic_demo_mode(): bool
{
    $setting = \Infocraft\Models\SiteSetting::get('demo_mode', '');
    if ($setting !== '') {
        return $setting === '1';
    }
    return (bool) \Infocraft\Config::get('app.demo_mode');
}

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
