<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Netzwerk-Werkstatt: simuliertes Klassennetz (JSON-Zustand)
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.36.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Auth;
use Infocraft\Database;
use PDO;
use RuntimeException;

/**
 * Ein simuliertes Netz (Router, Geräte, Webseiten, DNS, Log) wird als
 * JSON-Dokument gespeichert: je Klasse eins (Schülerinnen und Schüler
 * derselben Klasse teilen sich das Netz), ohne Klasse je Person.
 * Alle Änderungen laufen als kleine Aktionen über apply(), damit
 * gleichzeitige Bearbeitungen im Unterricht nicht gegenseitig
 * Überschreiben (kein Komplett-Schreiben vom Client).
 */
final class Network
{
    public const MAX_DEVICES = 12;
    public const MAX_PAGES = 60;
    public const MAX_DNS = 100;
    public const MAX_DEVICE_LOGS = 20; // Webserver-Log je Geraet (#218)
    public const MAX_LOGS = 40;
    public const MAX_HTML = 20000;
    public const MAX_MESSAGES = 100; // Messenger (#227): Deckel gegen endlose Chats
    public const MAX_MSG_TEXT = 200;

    public const TYPES = ['pc', 'phone', 'printer', 'webserver', 'dns', 'ap'];

    /** Geräte, die beim Provider hängen dürfen (#227 Provider-Netze). */
    public const WAN_TYPES = ['webserver', 'dns'];

    /** Geräte, die per WLAN beitreten können (#231). */
    public const WLAN_TYPES = ['phone', 'printer'];

    /**
     * Beispielseiten für den Webserver (#229): pro Knopfdruck landet eine
     * fertige, lustige Seite auf dem Server (noch nicht freigeschaltet,
     * damit die 404-Lektion erhalten bleibt).
     */
    public const EXAMPLES = [
        'zeitung' => [
            'title' => 'Schulzeitung',
            'domain' => 'schulzeitung.net',
            'html' => '<h1>Schulzeitung</h1><p style="border-left:4px solid #0f766e;padding-left:8px"><em>Alles, was in der Klasse passiert.</em></p><h2>Ausgabe 1</h2><ul><li>Sportfest: Die 7. Klasse gewinnt den Staffelpokal</li><li>Neuer Informatikraum: 25 Rechner ans Netz</li><li>Wetterbericht: Morgen sonnig</li></ul><p><strong>Tipp:</strong> Diese Seite kannst du hier frei bearbeiten.</p>',
        ],
        'sport' => [
            'title' => 'Sportclub Eichenwald',
            'domain' => 'eichenwald-sport.net',
            'html' => '<h1>Sportclub Eichenwald ⚽</h1><table border="1" cellpadding="6"><tr><th>Mannschaft</th><th>Tore</th><th>Punkte</th></tr><tr><td>Eichenwald 1</td><td>12</td><td>9</td></tr><tr><td>Stadt-Team</td><td>7</td><td>6</td></tr><tr><td>River United</td><td>5</td><td>3</td></tr></table><h2>Nächstes Spiel</h2><p>Samstag, 10 Uhr, Sportplatz am Fluss. <strong>Kommt alle!</strong></p>',
        ],
        'wetter' => [
            'title' => 'Wetterdienst Schulnet',
            'domain' => 'wetterdienst.net',
            'html' => '<h1>Wetterdienst Schulnet 🌤️</h1><p style="background:#e0f2fe;padding:8px;border-radius:8px">Die Vorhersage für morgen:</p><ul><li>☀️ Vormittag: sonnig, 21 Grad</li><li>⛅ Mittag: leicht bewölkt, 23 Grad</li><li>🌧️ Abend: Schauer, 16 Grad</li></ul><p>Übrigens: Diese Seite hängt auf einem Webserver im Schulnetz.</p>',
        ],
        'kochen' => [
            'title' => 'Kochblog Klassenküche',
            'domain' => 'klassenkueche.net',
            'html' => '<h1>Kochblog Klassenküche 🍝</h1><h2>Rezept: Spaghetti für die Pause</h2><ol><li>Wasser kochen (das Netzwerk braucht auch Energie!)</li><li>Nudeln hinein, 9 Minuten</li><li>Soße aus der Kantine dazu</li></ol><p style="color:#b91c1c"><strong>Geheimtipp:</strong> Parmesan macht alles besser.</p>',
        ],
    ];

    /** Standard-Netz: ein Router, noch keine Geräte. */
    public static function empty(): array
    {
        return [
            'v' => 1,
            'level' => 1,
            'router' => ['name' => 'Router', 'ssid' => '', 'pass' => ''],
            'provider' => ['name' => 'Schulprovider', 'ip' => '62.153.20.1'],
            'devices' => [],
            'pages' => [],
            'dns' => [],
            'logs' => [],
            'messages' => [],
        ];
    }

    /** Zeile für die Klasse (Schüler) bzw. die eigene Person laden, notfalls anlegen. */
    public static function rowFor(?int $classId, int $userId): array
    {
        $pdo = Database::pdo();
        if ($classId !== null) {
            $stmt = $pdo->prepare('SELECT * FROM net_networks WHERE class_id = ?');
            $stmt->execute([$classId]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM net_networks WHERE class_id IS NULL AND owner_id = ?');
            $stmt->execute([$userId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }
        $content = json_encode(self::empty(), JSON_UNESCAPED_UNICODE) ?: '';
        if ($classId !== null) {
            $stmt = $pdo->prepare('INSERT INTO net_networks (class_id, owner_id, content, updated_at) VALUES (?, NULL, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$classId, $content]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO net_networks (class_id, owner_id, content, updated_at) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$userId, $content]);
        }
        return [
            'id' => (int) $pdo->lastInsertId(),
            'class_id' => $classId,
            'owner_id' => $userId,
            'content' => $content,
        ];
    }

    /** Parsedes Netz für eine Person (Schüler: Klasse, Lehrkraft: wählbare Klasse). */
    public static function forUser(User $user, ?int $classId = null): array
    {
        if ($classId === null && (int) $user->class_id > 0) {
            $classId = (int) $user->class_id;
        }
        $row = self::rowFor($classId, $user->id);
        $net = json_decode((string) $row['content'], true);
        if (!is_array($net) || !isset($net['devices'])) {
            return self::empty();
        }
        // (#233) Auch historische oder per Import entstandene Zeilen immer
        // normalisiert ausliefern: alte Stände (fehlendes level, cable,
        // Objekte statt Listen) dürfen die Werkstatt im Browser nicht killen.
        return self::normalize($net);
    }

    /** Netz einer Klasse DIREKT laden (Lehrkraft-Manager, ohne eigenen Kontext). */
    public static function forClass(int $classId): array
    {
        $row = self::rowFor($classId, 0);
        $net = json_decode((string) $row['content'], true);
        return is_array($net) && isset($net['devices']) ? self::normalize($net) : self::empty(); // (#233)
    }

    /** Netz speichern (Klasse oder eigene Person). */
    public static function save(array $net, ?int $classId, int $userId): void
    {
        $json = json_encode($net, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Netz konnte nicht gespeichert werden.');
        }
        $pdo = Database::pdo();
        self::rowFor($classId, $userId); // Existenz sicherstellen
        if ($classId !== null) {
            $stmt = $pdo->prepare('UPDATE net_networks SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE class_id = ?');
            $stmt->execute([$json, $classId]);
        } else {
            $stmt = $pdo->prepare('UPDATE net_networks SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE class_id IS NULL AND owner_id = ?');
            $stmt->execute([$json, $userId]);
        }
    }

    /* ==================================================================
     * Aktionen: apply() führt eine Mutation am Netz aus und gibt das
     * aktualisierte Netz zurück. Alles wird hier validiert, der Client
     * darf nichts Ungeprüftes einbringen.
     * ================================================================== */

    /**
     * @param array<string, mixed> $params
     * @return array{net: array, error: ?string}
     */
    public static function apply(array $net, string $action, array $params, int $userId, bool $isTeacher): array
    {
        $err = static function (?string $msg): array {
            return ['net' => null, 'error' => $msg];
        };
        $net = self::normalize($net);

        switch ($action) {
            case 'addDevice':
                $type = (string) ($params['type'] ?? '');
                if (!in_array($type, self::TYPES, true)) {
                    return $err('Diesen Gerätetyp gibt es nicht.');
                }
                if (count($net['devices']) >= self::MAX_DEVICES) {
                    return $err('Am Router sind alle Ports belegt (maximal ' . self::MAX_DEVICES . ' Geräte).');
                }
                $name = self::cleanName((string) ($params['name'] ?? ''), $type);
                $device = [
                    'id' => self::nextId($net['devices']),
                    'type' => $type,
                    'name' => $name,
                    'ip' => self::nextIp($net),
                    'owner' => $userId,
                    'cable' => $type !== 'phone', // Smartphones nur per WLAN, alles andere per Kabel (#231)
                    'wifi' => false,
                ];
                if ($type === 'ap') {
                    // Access Point: Name ist zugleich die SSID des eigenen WLANs.
                    $device['pass'] = '';
                }
                $net['devices'][] = $device;
                self::log($net, 'angeschlossen: ' . $name . ' (' . $type . ')');
                self::log($net, 'IP-Vergabe (DHCP): ' . $name . ' erhaelt ' . $device['ip']); // #218
                break;

            case 'removeDevice':
                $net['devices'] = self::removeById($net['devices'], (string) ($params['id'] ?? ''));
                // Webseiten und DNS-Einträge des Geräts mit entfernen
                $net['pages'] = array_values(array_filter($net['pages'], static fn (array $p): bool => $p['server'] !== ($params['id'] ?? '')));
                break;

            case 'renameDevice':
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === (string) ($params['id'] ?? '')) {
                        $net['devices'][$i]['name'] = self::cleanName((string) ($params['name'] ?? ''), $d['type']);
                    }
                }
                break;

            case 'moveDevice': // Drag & Drop: Position eines Geräts auf der Bühne sichern
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === (string) ($params['id'] ?? '')) {
                        $net['devices'][$i]['pos'] = [
                            'x' => (int) round(max(0, min(900, (float) ($params['x'] ?? 0)))),
                            'y' => (int) round(max(0, min(560, (float) ($params['y'] ?? 0)))),
                        ];
                    }
                }
                break;

            case 'setAp':
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === (string) ($params['id'] ?? '') && $d['type'] === 'ap') {
                        $net['devices'][$i]['name'] = self::cleanName((string) ($params['name'] ?? ''), 'ap');
                        $net['devices'][$i]['pass'] = self::cleanText((string) ($params['pass'] ?? ''), 32);
                    }
                }
                break;

            case 'setWlan':
                $net['router']['ssid'] = self::cleanText((string) ($params['ssid'] ?? ''), 32);
                $net['router']['pass'] = self::cleanText((string) ($params['pass'] ?? ''), 32);
                break;

            case 'joinWlan':
                $dev = self::device($net, (string) ($params['id'] ?? ''));
                if ($dev === null || !in_array($dev['type'], self::WLAN_TYPES, true)) {
                    return $err('Nur Smartphones und Drucker verbinden sich über WLAN.');
                }
                $ssid = (string) ($params['ssid'] ?? '');
                $pass = (string) ($params['pass'] ?? '');
                $networks = self::wlanNetworks($net);
                if (!in_array($ssid, $networks, true)) {
                    return $err('Dieses WLAN gibt es im Netz nicht.');
                }
                $expected = self::wlanPass($net, $ssid);
                if ($expected === null || $expected === '' || $pass !== $expected) {
                    return $err('Falsches WLAN-Passwort.');
                }
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === $dev['id']) {
                        $net['devices'][$i]['wifi'] = true;
                        $net['devices'][$i]['ssid'] = $ssid;
                    }
                }
                self::log($net, $dev['name'] . ' ist jetzt im WLAN "' . $ssid . '"');
                break;

            case 'setCable': // #231: Drucker per Kabel oder WLAN
                $dev = self::device($net, (string) ($params['id'] ?? ''));
                if ($dev === null || $dev['type'] !== 'printer') {
                    return $err('Nur Drucker lassen sich vom Kabel trennen.');
                }
                $cable = (string) ($params['cable'] ?? '1') === '1';
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === $dev['id']) {
                        $net['devices'][$i]['cable'] = $cable;
                    }
                }
                self::log($net, $dev['name'] . ': Kabel ' . ($cable ? 'angeschlossen' : 'getrennt'));
                break;

            case 'loadExample': // #229: fertige Beispielseite auf den Webserver laden
                $server = self::device($net, (string) ($params['server'] ?? ''));
                if ($server === null || $server['type'] !== 'webserver') {
                    return $err('Beispielseiten gehören auf einen Webserver.');
                }
                $example = self::EXAMPLES[(string) ($params['example'] ?? '')] ?? null;
                if ($example === null) {
                    return $err('Diese Beispielseite gibt es nicht.');
                }
                if (count($net['pages']) >= self::MAX_PAGES) {
                    return $err('Zu viele Webseiten im Netz.');
                }
                $net['pages'][] = [
                    'id' => self::nextId($net['pages']),
                    'server' => $server['id'],
                    'title' => $example['title'],
                    'domain' => $example['domain'],
                    'html' => $example['html'],
                    'published' => false, // 404 bleibt sichtbar, bis freigeschaltet wird
                ];
                self::log($net, 'Beispielseite "' . $example['title'] . '" auf ' . $server['name'] . ' geladen');
                break;

            case 'printPage': // #231: Netzdrucker aus dem Browser heraus
                $printer = self::device($net, (string) ($params['printer'] ?? ''));
                if ($printer === null || $printer['type'] !== 'printer') {
                    return $err('Kein Drucker gewählt.');
                }
                if ($printer['cable'] === false && $printer['wifi'] !== true) {
                    return $err('Dieser Drucker ist offline (kein Kabel, kein WLAN).');
                }
                $url = self::cleanText((string) ($params['url'] ?? ''), 120);
                self::log($net, (string) ($params['who'] ?? 'Browser') . ' druckt ' . ($url !== '' ? $url : 'die Seite') . ' auf ' . $printer['name']);
                break;

            case 'setLevel': // #232: Stufen-Schalter der Lehrkraft
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft schaltet die Stufe um.');
                }
                $net['level'] = (string) ($params['level'] ?? '1') === '2' ? 2 : 1;
                break;

            case 'savePage':
                $server = self::device($net, (string) ($params['server'] ?? ''));
                if ($server === null || $server['type'] !== 'webserver') {
                    return $err('Webseiten gehören auf einen Webserver.');
                }
                $title = self::cleanText((string) ($params['title'] ?? 'Meine Seite'), 60);
                $domain = self::cleanDomain((string) ($params['domain'] ?? ''));
                $html = mb_substr((string) ($params['html'] ?? ''), 0, self::MAX_HTML);
                if ($html === '') {
                    return $err('Die Seite braucht Inhalt (HTML).');
                }
                $id = (string) ($params['id'] ?? '');
                $found = false;
                foreach ($net['pages'] as $i => $p) {
                    if ($p['id'] === $id && $p['server'] === $server['id']) {
                        $net['pages'][$i] = ['id' => $p['id'], 'server' => $server['id'], 'title' => $title, 'domain' => $domain, 'html' => $html, 'published' => $p['published']];
                        $found = true;
                    }
                }
                if (!$found) {
                    if (count($net['pages']) >= self::MAX_PAGES) {
                        return $err('Zu viele Webseiten im Netz.');
                    }
                    $net['pages'][] = ['id' => self::nextId($net['pages']), 'server' => $server['id'], 'title' => $title, 'domain' => $domain, 'html' => $html, 'published' => false];
                }
                break;

            case 'deletePage':
                $net['pages'] = self::removeById($net['pages'], (string) ($params['id'] ?? ''));
                break;

            case 'publishPage':
                foreach ($net['pages'] as $i => $p) {
                    if ($p['id'] === (string) ($params['id'] ?? '')) {
                        $net['pages'][$i]['published'] = true;
                        self::publishDns($net, $net['pages'][$i]);
                        self::log($net, 'freigeschaltet: ' . $p['title'] . ($p['domain'] !== '' ? ' (' . $p['domain'] . ')' : ''));
                    }
                }
                break;

            case 'unpublishPage':
                foreach ($net['pages'] as $i => $p) {
                    if ($p['id'] === (string) ($params['id'] ?? '')) {
                        $net['pages'][$i]['published'] = false;
                    }
                }
                break;

            case 'saveDns':
                $domain = self::cleanDomain((string) ($params['domain'] ?? ''));
                $ip = self::cleanIp((string) ($params['ip'] ?? ''));
                if ($domain === '' || $ip === '') {
                    return $err('DNS-Eintrag braucht Domain und IP.');
                }
                $id = (string) ($params['id'] ?? '');
                $found = false;
                foreach ($net['dns'] as $i => $e) {
                    if ($e['domain'] === $domain) {
                        $net['dns'][$i]['ip'] = $ip;
                        $found = true;
                    }
                    if ($id !== '' && $e['id'] === $id) {
                        $net['dns'][$i] = ['id' => $e['id'], 'domain' => $domain, 'ip' => $ip];
                        $found = true;
                    }
                }
                if (!$found) {
                    if (count($net['dns']) >= self::MAX_DNS) {
                        return $err('Die DNS-Tabelle ist voll.');
                    }
                    $net['dns'][] = ['id' => self::nextId($net['dns']), 'domain' => $domain, 'ip' => $ip];
                }
                break;

            case 'deleteDns':
                $net['dns'] = self::removeById($net['dns'], (string) ($params['id'] ?? ''));
                break;

            case 'browse':
                // Protokollieren: der Browser simuliert lokal, der Weg landet im
                // gemeinsamen Router-Log und im Webserver-Log des Zielgeräts (#218).
                $who = self::cleanText((string) ($params['who'] ?? 'Browser'), 60);
                $url = self::cleanText((string) ($params['url'] ?? ''), 120);
                $code = (int) ($params['code'] ?? 0);
                if ($url !== '') {
                    self::log($net, $who . ' ruft ' . $url . ' auf' . (in_array($code, [200, 404], true) ? ' (HTTP ' . $code . ')' : ''));
                    $serverId = (string) ($params['server'] ?? '');
                    if ($serverId !== '' && in_array($code, [200, 404], true)) {
                        foreach ($net['devices'] as $i => $d) {
                            if ($d['id'] === $serverId && $d['type'] === 'webserver') {
                                $devLog = is_array($d['log'] ?? null) ? $d['log'] : [];
                                $devLog[] = [
                                    't' => date('H:i'),
                                    'ip' => self::cleanIp((string) ($params['ip'] ?? '')),
                                    'path' => self::cleanText((string) ($params['path'] ?? '/'), 60),
                                    'code' => $code,
                                ];
                                $net['devices'][$i]['log'] = array_slice($devLog, -self::MAX_DEVICE_LOGS);
                            }
                        }
                    }
                }
                break;

            case 'clearLogs':
                $net['logs'] = [];
                foreach ($net['devices'] as $i => $d) { // #218: auch Webserver-Logs leeren
                    if (is_array($d['log'] ?? null)) {
                        $net['devices'][$i]['log'] = [];
                    }
                }
                break;

            case 'reset':
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft setzt das Netz zurück.');
                }
                $net = self::empty();
                break;

            case 'import':
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft stellt ein Netz wieder her.');
                }
                $import = json_decode((string) ($params['content'] ?? ''), true);
                if (!is_array($import) || !isset($import['devices'])) {
                    return $err('Das ist kein gespeichertes Netz.');
                }
                $net = self::normalize($import);
                break;

            case 'setZone': // #227: Webserver/DNS zwischen Schulnetz und Provider-Zone umziehen
                $dev = self::device($net, (string) ($params['id'] ?? ''));
                if ($dev === null || !in_array($dev['type'], self::WAN_TYPES, true)) {
                    return $err('Nur Webserver und DNS-Server können zum Provider ziehen.');
                }
                $zone = (string) ($params['zone'] ?? 'lan') === 'wan' ? 'wan' : 'lan';
                foreach ($net['devices'] as $i => $d) {
                    if ($d['id'] === $dev['id']) {
                        $net['devices'][$i]['zone'] = $zone;
                        // Umzug gibt eine passende IP im Zielnetz (LAN 192.168.0.x, WAN 62.153.20.x)
                        $net['devices'][$i]['ip'] = $zone === 'wan' ? self::nextWanIp($net) : self::nextIp($net);
                        // Mit umgezogene DNS-Einträge auf die neue IP umschreiben
                        foreach ($net['dns'] as $di => $entry) {
                            if ($entry['ip'] === $dev['ip']) {
                                $net['dns'][$di]['ip'] = $net['devices'][$i]['ip'];
                            }
                        }
                    }
                }
                self::log($net, $dev['name'] . ' hängt jetzt ' . ($zone === 'wan'
                    ? 'beim Provider ' . $net['provider']['name'] . ' (Website-Anbieter)'
                    : 'wieder im Schulnetz'));
                break;

            case 'setProvider': // #227: Name des Providers (Lehrkraft)
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft benennt den Provider um.');
                }
                $net['provider']['name'] = self::cleanText((string) ($params['name'] ?? 'Schulprovider'), 40);
                break;

            case 'sendMessage': // #227: Messenger zwischen Geräten
                $from = self::device($net, (string) ($params['from'] ?? ''));
                $to = self::device($net, (string) ($params['to'] ?? ''));
                if ($from === null || $to === null || $from['id'] === $to['id']) {
                    return $err('Sender und Empfänger müssen zwei verschiedene Geräte sein.');
                }
                if (!in_array($from['type'], ['pc', 'phone'], true) || !in_array($to['type'], ['pc', 'phone'], true)) {
                    return $err('Messenger läuft auf Computern und Smartphones.');
                }
                if (($from['cable'] ?? true) !== true && $from['wifi'] !== true) {
                    return $err($from['name'] . ' ist offline und kann nicht senden.');
                }
                if (($to['cable'] ?? true) !== true && $to['wifi'] !== true) {
                    return $err($to['name'] . ' ist offline und kann keine Nachrichten empfangen.');
                }
                $text = self::cleanText((string) ($params['text'] ?? ''), self::MAX_MSG_TEXT);
                if ($text === '') {
                    return $err('Die Nachricht ist leer.');
                }
                $net['messages'][] = self::messageRow($net, $from['id'], $from['name'], $to['id'], $to['name'], $text, false, '');
                self::log($net, $from['name'] . ' schickt eine Nachricht an ' . $to['name']);
                break;

            case 'spoofMessage': // #227: Phishing-Demo (Lehrkraft täuscht die Absender-Identität)
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft darf für die Phishing-Demo eine Identität täuschen.');
                }
                $victim = self::device($net, (string) ($params['from'] ?? ''));
                $to = self::device($net, (string) ($params['to'] ?? ''));
                if ($victim === null || $to === null || $victim['id'] === $to['id']) {
                    return $err('Täuschung braucht zwei verschiedene Geräte.');
                }
                if (!in_array($victim['type'], ['pc', 'phone'], true) || !in_array($to['type'], ['pc', 'phone'], true)) {
                    return $err('Messenger läuft auf Computern und Smartphones.');
                }
                $text = self::cleanText((string) ($params['text'] ?? ''), self::MAX_MSG_TEXT);
                if ($text === '') {
                    return $err('Die Nachricht ist leer.');
                }
                $net['messages'][] = self::messageRow($net, $victim['id'], $victim['name'], $to['id'], $to['name'], $text, true, 'Lehrkraft');
                self::log($net, 'PHISHING-DEMO: Nachricht angeblich von ' . $victim['name'] . ' an ' . $to['name']);
                break;

            case 'deleteMessage': // #227: Lehrkraft kann Phishing-Nachrichten zurückziehen
                if (!$isTeacher) {
                    return $err('Nur die Lehrkraft entfernt Nachrichten.');
                }
                $net['messages'] = array_values(array_filter($net['messages'],
                    static fn (array $m): bool => $m['id'] !== (string) ($params['id'] ?? '')));
                break;

            default:
                return $err('Unbekannte Aktion.');
        }

        return ['net' => self::normalize($net), 'error' => null];
    }

    /* ==================================================================
     * Helfer (auch vom Controller für den Export genutzt)
     * ================================================================== */

    /** Struktur auffrischen und Deckel erzwingen (unabhängig vom Client). */
    public static function normalize(array $net): array
    {
        $net = array_merge(self::empty(), $net);
        $net['level'] = (int) ($net['level'] ?? 1) === 2 ? 2 : 1;
        $net['router'] = array_merge(self::empty()['router'], is_array($net['router'] ?? null) ? $net['router'] : []);
        // Provider-Knoten (#227): Cloud zwischen Schul-Router und Website-Anbietern
        $net['provider'] = array_merge(self::empty()['provider'], is_array($net['provider'] ?? null) ? $net['provider'] : []);
        $net['provider']['name'] = self::cleanText((string) ($net['provider']['name'] ?? 'Schulprovider'), 40);
        $net['devices'] = array_slice(array_values(array_filter($net['devices'], 'is_array')), 0, self::MAX_DEVICES);
        $net['pages'] = array_slice(array_values(array_filter($net['pages'], 'is_array')), 0, self::MAX_PAGES);
        $net['dns'] = array_slice(array_values(array_filter($net['dns'], 'is_array')), 0, self::MAX_DNS);
        $net['logs'] = array_slice(array_values(array_filter($net['logs'], 'is_array')), -self::MAX_LOGS);
        // Messenger (#227): Nachrichten zwischen Geräten, Lehrkraft kann für
        // Phishing-Szenarien Absender täuschen (spoof = true, realFrom bleibt wahr).
        $msgs = [];
        if (is_array($net['messages'] ?? null)) {
            foreach ($net['messages'] as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $msgs[] = [
                    'id' => (string) ($m['id'] ?? ''),
                    'from' => (string) ($m['from'] ?? ''),
                    'fromName' => self::cleanText((string) ($m['fromName'] ?? 'Gerät'), 40),
                    'to' => (string) ($m['to'] ?? ''),
                    'toName' => self::cleanText((string) ($m['toName'] ?? 'Gerät'), 40),
                    'text' => self::cleanText((string) ($m['text'] ?? ''), self::MAX_MSG_TEXT),
                    't' => self::cleanText((string) ($m['t'] ?? ''), 12),
                    'spoof' => ($m['spoof'] ?? false) === true,
                    'realFrom' => self::cleanText((string) ($m['realFrom'] ?? ''), 40),
                ];
            }
        }
        $net['messages'] = array_slice($msgs, -self::MAX_MESSAGES);
        foreach ($net['devices'] as $i => $d) {
            $net['devices'][$i]['id'] = (string) ($d['id'] ?? self::nextId($net['devices']));
            $net['devices'][$i]['type'] = in_array($d['type'] ?? '', self::TYPES, true) ? $d['type'] : 'pc';
            $net['devices'][$i]['name'] = self::cleanText((string) ($d['name'] ?? 'Gerät'), 40);
            $net['devices'][$i]['ip'] = self::cleanIp((string) ($d['ip'] ?? '')) ?: '192.168.0.2';
            $net['devices'][$i]['wifi'] = ($d['wifi'] ?? false) === true;
            // Kabel-Standard: alles außer Smartphones (#231)
            $net['devices'][$i]['cable'] = ($net['devices'][$i]['type'] === 'phone')
                ? (($d['cable'] ?? false) === true)
                : (($d['cable'] ?? true) !== false);
            $net['devices'][$i]['owner'] = (int) ($d['owner'] ?? 0);
            if (($d['ssid'] ?? '') !== '') {
                $net['devices'][$i]['ssid'] = self::cleanText((string) $d['ssid'], 32);
            }
            // Webserver-Log je Geraet normalisieren (#218)
            if (is_array($d['log'] ?? null)) {
                $devLog = [];
                foreach ($d['log'] as $le) {
                    if (!is_array($le)) {
                        continue;
                    }
                    $devLog[] = [
                        't' => self::cleanText((string) ($le['t'] ?? ''), 12),
                        'ip' => self::cleanIp((string) ($le['ip'] ?? '')),
                        'path' => self::cleanText((string) ($le['path'] ?? '/'), 60),
                        'code' => in_array((int) ($le['code'] ?? 0), [200, 404], true) ? (int) $le['code'] : 0,
                    ];
                }
                $net['devices'][$i]['log'] = array_slice($devLog, -self::MAX_DEVICE_LOGS);
            }
            // Position auf der Bühne (Drag & Drop): nur gültige Koordinatenpaare übernehmen
            if (isset($d['pos']) && is_array($d['pos'])
                && is_numeric($d['pos']['x'] ?? null) && is_numeric($d['pos']['y'] ?? null)) {
                $net['devices'][$i]['pos'] = [
                    'x' => (int) round(max(0, min(900, (float) $d['pos']['x']))),
                    'y' => (int) round(max(0, min(560, (float) $d['pos']['y']))),
                ];
            }
            if ($net['devices'][$i]['type'] === 'ap') {
                $net['devices'][$i]['pass'] = self::cleanText((string) ($d['pass'] ?? ''), 32);
            }
            // Provider-Zone (#227): Webserver und DNS-Server dürfen beim
            // Provider hängen (Website-Anbieter), alles andere bleibt im LAN.
            $net['devices'][$i]['zone'] = (in_array($net['devices'][$i]['type'], self::WAN_TYPES, true)
                && (($d['zone'] ?? 'lan') === 'wan')) ? 'wan' : 'lan';
        }
        foreach ($net['pages'] as $i => $p) {
            $net['pages'][$i]['id'] = (string) ($p['id'] ?? self::nextId($net['pages']));
            $net['pages'][$i]['server'] = (string) ($p['server'] ?? '');
            $net['pages'][$i]['title'] = self::cleanText((string) ($p['title'] ?? 'Seite'), 60);
            $net['pages'][$i]['domain'] = self::cleanDomain((string) ($p['domain'] ?? ''));
            $net['pages'][$i]['html'] = mb_substr((string) ($p['html'] ?? ''), 0, self::MAX_HTML);
            $net['pages'][$i]['published'] = ($p['published'] ?? false) === true;
        }
        foreach ($net['dns'] as $i => $e) {
            $net['dns'][$i]['id'] = (string) ($e['id'] ?? self::nextId($net['dns']));
            $net['dns'][$i]['domain'] = self::cleanDomain((string) ($e['domain'] ?? ''));
            $net['dns'][$i]['ip'] = self::cleanIp((string) ($e['ip'] ?? ''));
        }
        foreach ($net['logs'] as $i => $l) {
            $net['logs'][$i] = ['t' => self::cleanText((string) ($l['t'] ?? ''), 12), 'msg' => self::cleanText((string) ($l['msg'] ?? ''), 200)];
        }
        return $net;
    }

    private static function device(array $net, string $id): ?array
    {
        foreach ($net['devices'] as $d) {
            if ((string) $d['id'] === $id) {
                return $d;
            }
        }
        return null;
    }

    private static function nextId(array $list): string
    {
        $max = 0;
        foreach ($list as $item) {
            $n = (int) preg_replace('/\D/', '', (string) ($item['id'] ?? ''));
            if ($n > $max) {
                $max = $n;
            }
        }
        return 'n' . (string) ($max + 1);
    }

    private static function nextIp(array $net): string
    {
        $used = ['192.168.0.1' => true];
        foreach ($net['devices'] as $d) {
            $used[(string) $d['ip']] = true;
        }
        for ($i = 2; $i < 255; $i++) {
            if (!isset($used['192.168.0.' . $i])) {
                return '192.168.0.' . $i;
            }
        }
        return '192.168.0.254';
    }

    /** Nächste freie IP in der Provider-Zone (62.153.20.x, #227). */
    private static function nextWanIp(array $net): string
    {
        $used = ['62.153.20.1' => true]; // der Provider selbst
        foreach ($net['devices'] as $d) {
            $used[(string) $d['ip']] = true;
        }
        for ($i = 2; $i < 255; $i++) {
            if (!isset($used['62.153.20.' . $i])) {
                return '62.153.20.' . $i;
            }
        }
        return '62.153.20.254';
    }

    /** Messenger-Nachricht als einheitliche Zeile (#227). */
    private static function messageRow(array $net, string $from, string $fromName, string $to, string $toName, string $text, bool $spoof, string $realFrom): array
    {
        return [
            'id' => self::nextId($net['messages']),
            'from' => $from,
            'fromName' => $fromName,
            'to' => $to,
            'toName' => $toName,
            'text' => $text,
            't' => date('H:i'),
            'spoof' => $spoof,
            'realFrom' => $realFrom,
        ];
    }

    private static function wlanNetworks(array $net): array
    {
        $out = [];
        if (($net['router']['ssid'] ?? '') !== '') {
            $out[] = (string) $net['router']['ssid'];
        }
        foreach ($net['devices'] as $d) {
            if ($d['type'] === 'ap' && !in_array($d['name'], $out, true)) {
                $out[] = $d['name'];
            }
        }
        return $out;
    }

    private static function wlanPass(array $net, string $ssid): ?string
    {
        if ($ssid === (string) ($net['router']['ssid'] ?? '')) {
            return (string) ($net['router']['pass'] ?? '');
        }
        foreach ($net['devices'] as $d) {
            if ($d['type'] === 'ap' && $d['name'] === $ssid) {
                return (string) ($d['pass'] ?? '');
            }
        }
        return null;
    }

    /** Beim Freischalten: Domain automatisch in die DNS-Tabelle eintragen. */
    private static function publishDns(array &$net, array $page): void
    {
        if ($page['domain'] === '' || $page['server'] === '') {
            return;
        }
        $serverIp = '';
        foreach ($net['devices'] as $d) {
            if ((string) $d['id'] === (string) $page['server']) {
                $serverIp = (string) $d['ip'];
            }
        }
        if ($serverIp === '') {
            return;
        }
        foreach ($net['devices'] as $d) {
            if ($d['type'] !== 'dns') {
                continue;
            }
            $found = false;
            foreach ($net['dns'] as $i => $e) {
                if ($e['domain'] === $page['domain']) {
                    $net['dns'][$i]['ip'] = $serverIp;
                    $found = true;
                }
            }
            if (!$found) {
                if (count($net['dns']) >= self::MAX_DNS) {
                    return;
                }
                $net['dns'][] = ['id' => self::nextId($net['dns']), 'domain' => $page['domain'], 'ip' => $serverIp];
            }
            return;
        }
    }

    private static function log(array &$net, string $msg): void
    {
        $net['logs'][] = ['t' => date('H:i'), 'msg' => $msg];
        $net['logs'] = array_slice($net['logs'], -self::MAX_LOGS);
    }

    private static function removeById(array $list, string $id): array
    {
        return array_values(array_filter($list, static fn (array $item): bool => (string) ($item['id'] ?? '') !== $id));
    }

    private static function cleanText(string $value, int $max): string
    {
        $value = trim(preg_replace('/[\p{C}]/u', '', $value) ?? '');
        return mb_substr($value, 0, $max);
    }

    private static function cleanName(string $value, string $type): string
    {
        $fallback = ['pc' => 'Computer', 'phone' => 'Smartphone', 'printer' => 'Drucker', 'webserver' => 'Webserver', 'dns' => 'DNS-Server', 'ap' => 'Access Point'][$type] ?? 'Gerät';
        $name = self::cleanText($value, 40);
        return $name === '' ? $fallback : $name;
    }

    private static function cleanDomain(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (preg_match('/^[a-z0-9][a-z0-9.-]{0,38}[a-z0-9]$/', $value) !== 1) {
            return '';
        }
        return $value;
    }

    private static function cleanIp(string $value): string
    {
        if (preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $value) !== 1) {
            return '';
        }
        return $value;
    }
}
