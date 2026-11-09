<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Database;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\User;

final class ModuleController
{
    /**
     * Zentrale Modul-Registry: Hier neue Arbeitsbereiche eintragen (auch künftige, z. B. für die Oberstufe).
     * 'level' = Jahrgangsstufe, ab der Schülerinnen und Schüler das Modul sehen (LehrplanPLUS).
     * 'route' = echte Seite, sobald ein Modul mehr als ein Platzhalter ist.
     *
     * @return array<string, array{slug: string, title: string, icon: string, phase: string, status: string, level: int, route: ?string, description: string, features: array<int, string>, lehrplan: string}>
     */
    public static function modules(): array
    {
        return [
            'html' => [
                'slug' => 'html',
                'title' => 'HTML-Werkstatt',
                'icon' => '🎨',
                'phase' => 'Phase 3',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'werkstatt/html',
                'description' => 'Webseiten bauen mit sofort sichtbarem Ergebnis.',
                'features' => [
                    'WYSIWYG-Editor mit Sofortvorschau (live beim Tippen)',
                    'Vorlagen: Grundgerüst, Bildergalerie, Kontaktformular',
                    'Projekte in Ordnern, Austeilen über die Code-Werkstatt',
                    'Präsentationsmodus; Prüfungsmodus folgt (Phase 5)',
                ],
                'lehrplan' => 'Webseiten gestalten: HTML, CSS, Layout und Gestaltung.',
            ],
            'sql' => [
                'slug' => 'sql',
                'title' => 'SQL-Werkstatt',
                'icon' => '🗄️',
                'phase' => 'Phase 2',
                'status' => 'fertig',
                'level' => 9,
                'route' => 'werkstatt/sql',
                'description' => 'Datenbanken begreifbar machen: von der Tabelle bis zum SQL-Abenteuer.',
                'features' => [
                    'Abbildung eines Shops und eines Social-Feeds als Datenmodell',
                    'SQL-Werkbank: Abfragen ausführen, Ergebnisse als Tabelle, Rechte je Person',
                    'Automatisch generierte ER-Diagramme (Quelltext zum Weiterverwenden)',
                    'SQL-Text-Adventure im Stil von SQL Island (Dateninsel)',
                    'Lehrkräfte teilen Vorlagen an Klassen aus: nur lesen, lesen und schreiben oder Tabellen anlegen',
                ],
                'lehrplan' => 'Datenbanken: Datenerfassung und -verwaltung, SQL-Grundlagen, Datenmodellierung.',
            ],
            'code' => [
                'slug' => 'code',
                'title' => 'Code-Werkstatt',
                'icon' => '💻',
                'phase' => 'Phase 4',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'werkstatt/code',
                'description' => 'Programmieren lernen mit Java und Python, geführt Schritt für Schritt.',
                'features' => [
                    'Projekte anlegen und in Ordnern per Drag & Drop verwalten',
                    'Projekte und Vorlagen durch Lehrkräfte an Klassen oder Einzelne austeilen',
                    'Python-Programme direkt im Browser ausführen (Pyodide, ohne Server)',
                    'Java-Ausführung und Auto-Vervollständigung folgen',
                ],
                'lehrplan' => 'Algorithmen und Programmieren: Grundlagen, Kontrollstrukturen, Methoden.',
            ],
            'diagramme' => [
                'slug' => 'diagramme',
                'title' => 'Diagramme',
                'icon' => '📐',
                'phase' => 'Phase 4',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'diagramme',
                'description' => 'Struktur sichtbar machen: Klassendiagramme, Struktogramme und Datenflussdiagramme zeichnen und automatisch aus Java, Python und SQL erzeugen.',
                'features' => [
                    'Klassendiagramme selbst zeichnen mit Live-Vorschau und Zwischenspeichern',
                    'Struktogramme (Nassi-Shneiderman) mit eigener Syntax: anweisung, wenn/dann/sonst, solange, wiederhole/bis',
                    'Datenflussdiagramme: Quellen, Senken, Datenspeicher und Funktionen mit beschrifteten Pfeilen - Vorschau live beim Tippen',
                    'Automatisch aus Java- und Python-Skripten: Klassen, Attribute, Methoden und Vererbung',
                    'SQL-Tabellen als Klassendiagramm übernehmen: Spalten, Primärschlüssel, Beziehungen',
                    'Export als PNG, Quelltext bleibt immer kopierbar',
                    'Lehrkräfte deaktivieren die automatische Erzeugung pro Klasse (selbst zeichnen bleibt erlaubt)',
                ],
                'lehrplan' => 'Objektorientierte Modellierung: Klassen, Objekte, Attribute, Methoden, Beziehungen; funktionale Modellierung: Datenfluss; Kontrollstrukturen: Struktogramme.',
            ],
            'quiz' => [
                'slug' => 'quiz',
                'title' => 'Quiz-Arena',
                'icon' => '🏆',
                'phase' => 'Phase 5',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'quiz',
                'description' => 'Wissen im Wettbewerb festigen: schnelle Runden, Punkte und Bestenliste. Anlegen, Austeilen und Auswertung nur für Lehrkräfte.',
                'features' => [
                    'Quiz anlegen: Fragen mit vier Antworten, richtige Antwort markieren',
                    'An Klassen oder Einzelne austellen, Spielen mit sofortigem Feedback und Punkten',
                    'Lehrkräfte sehen die Ergebnisse ihrer Klassen',
                    'Live-Runden folgen; Prüfungen und Schularbeiten laufen im eigenen Modul Prüfungen & Arbeiten',
                ],
                'lehrplan' => 'Übergreifend: Wiederholung und Wissenssicherung aller Themen.',
            ],
            'pruefung' => [
                'slug' => 'pruefung',
                'title' => 'Prüfungen & Arbeiten',
                'icon' => '📋',
                'phase' => 'Phase 5',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'pruefungen',
                'description' => 'Prüfungen und Schularbeiten vorbereiten, durchführen, korrigieren und herausgeben.',
                'features' => [
                    'Vorbereitung: Gruppen anlegen, pro Gruppe eine eigene Vorlage, Teilaufgaben mit BE und Musterlösung',
                    'Durchführung: bei Prüfungen sind alle anderen Bereiche gesperrt, Antworten speichern automatisch',
                    'Korrektur: manuell oder anonymisiert über eine API, jede Bewertung bleibt gegenprüfbar',
                    'Herausgabe: Punkte, Note und Vergleich zwischen eigener Lösung und Musterlösung',
                    'Auch für normale Schularbeiten ohne Sperre',
                ],
                'lehrplan' => 'Übergreifend: Leistungserhebung und Feedback in allen Bereichen.',
            ],
            'wiki' => [
                'slug' => 'wiki',
                'title' => 'Wiki',
                'icon' => '📚',
                'phase' => 'Phase 6',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'wiki',
                'description' => 'Nachschlagen und vertiefen.',
                'features' => [
                    'Wiki-Seiten zu Fachbegriffen und Konzepten, ab Jahrgangsstufe 6',
                    'Lehrkräfte legen Seiten mit einfacher Markdown-Formatierung an',
                    'Stufen-Filter: Schülerinnen und Schüler sehen Seiten bis zur eigenen Klassenstufe',
                ],
                'lehrplan' => 'Übergreifend: Fachbegriffe und Konzepte, Selbstlernen.',
            ],
            'praesentationen' => [
                'slug' => 'praesentationen',
                'title' => 'Präsentationen',
                'icon' => '🎬',
                'phase' => 'Phase 6',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'praesentationen',
                'description' => 'Folien für den Unterricht: Vollbild-Modus, Code mit Syntax-Highlighting, live veränderbar.',
                'features' => [
                    'Präsentationen aus Text- und Code-Folien zusammenstellen',
                    'Vollbild-Modus (Taste F) und Navigation mit den Pfeiltasten',
                    'Code-Folien mit Syntax-Highlighting, direkt in der Präsentation editierbar',
                    'Stufen-Filter: Schülerinnen und Schüler sehen Präsentationen bis zur eigenen Klassenstufe',
                ],
                'lehrplan' => 'Übergreifend: Unterrichtsbegleitung und Demonstration aller Themen.',
            ],
            'infobuecher' => [
                'slug' => 'infobuecher',
                'title' => 'InfoBook',
                'icon' => '📓',
                'phase' => 'Phase 7',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'infobuecher',
                'description' => 'Notizbücher im OneNote-Stil: Seiten mit Text, Bildern, Audio, Video und Freihand-Notizen (Apple Pencil), eng verknüpft mit den Präsentationen.',
                'features' => [
                    'Persönliche InfoBooks der Lehrkraft, optional an alle Lehrkräfte geteilt',
                    'Klassen-InfoBook pro Klasse mit Lernbereich (schülerzentriert: alle lesen), Zusammenarbeit (alle schreiben) und privatem Bereich je Schülerin und Schüler',
                    'Seiten aus dem eigenen InfoBook an Klassen verteilen',
                    'Bilder, Audio und Videos direkt auf der Seite abspielbar, YouTube- und LearningApps-Einbettungen',
                    'Freihand schreiben und zeichnen mit dem Apple Pencil, optimiert für Tablets',
                    'Ganzes InfoBook oder einzelne Abschnitte als Präsentation zeigen oder als Präsentation übernehmen',
                ],
                'lehrplan' => 'Übergreifend: Unterrichtsbegleitung, Materialien und Mitschriften aller Themen.',
            ],
            'tasks' => [
                'slug' => 'tasks',
                'title' => 'Aufgabenpool',
                'icon' => '📝',
                'phase' => 'Phase 7',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'aufgaben',
                'description' => 'Üben und abfragen, mit Aufgabenpool, Katalog und eigenen Aufgaben.',
                'features' => [
                    'Lehrkräfte legen Aufgaben an und teilen sie an Klassen oder Einzelne aus',
                    'Schülerinnen und Schüler sehen ihre Aufgaben bis zur eigenen Klassenstufe',
                    'Kuratierter Katalog mit fertigen Aufgaben (Jgst. 6-13): einzeln oder alle einer Jahrgangsstufe übernehmen',
                    'Abgestimmt auf die Präsentationen im Arbeitsbereich Präsentationen',
                ],
                'lehrplan' => 'Übergreifend: Anwendung und Festigung der Übungs- und Prüfungsinhalte.',
            ],
        ];
    }

    /**
     * Module, die diese Person sehen darf: Schülerinnen und Schüler nur bis
     * zu ihrer Jahrgangsstufe (plus ausgeteilte Inhalte), Lehrkräfte alle.
     * Lehrkräfte können pro Klasse freigeben (früher sichtbar) oder sperren.
     * @return array<string, array{slug: string, title: string, icon: string, phase: string, status: string, level: int, route: ?string, description: string, features: array<int, string>, lehrplan: string}>
     */
    public static function visible(?User $user): array
    {
        $modules = self::modules();
        if ($user === null) {
            return [];
        }
        if ($user->role !== 'student') {
            return $modules;
        }
        $level = Auth::levelOf($user) ?? 0;
        $modes = $user->classId === null ? [] : self::modesFor($user->classId);
        $visible = [];
        foreach ($modules as $key => $module) {
            $mode = $modes[$key] ?? '';
            if ($mode === 'sperren') {
                continue;
            }
            if ($mode === 'freigeben' || ($module['level'] ?? 6) <= $level) {
                $visible[$key] = $module;
            }
        }
        // Ausgeteilte Datenbanken sind sichtbar, auch unterhalb der Stufe
        if (!isset($visible['sql']) && ($modes['sql'] ?? '') !== 'sperren' && SqlTemplate::hasGrants($user)) {
            $visible['sql'] = $modules['sql'];
        }
        return $visible;
    }

    /**
     * Einstellungen einer Klasse: Modul-Schluessel => 'freigeben' oder 'sperren'.
     * @return array<string, string>
     */
    public static function modesFor(int $classId): array
    {
        $stmt = Database::pdo()->prepare('SELECT module_key, mode FROM module_settings WHERE class_id = ?');
        $stmt->execute([$classId]);
        $modes = [];
        foreach ($stmt->fetchAll() as $row) {
            $modes[(string) $row['module_key']] = (string) $row['mode'];
        }
        return $modes;
    }

    /**
     * Freigabe einer Klasse setzen: 'freigeben' = vorzeitig sichtbar,
     * 'sperren' = ausgeblendet, alles andere (Standard) = Lehrplan-Stufe entscheidet.
     */
    public static function setMode(int $classId, string $slug, string $mode): void
    {
        if (!isset(self::modules()[$slug])) {
            return;
        }
        $pdo = Database::pdo();
        if ($mode !== 'freigeben' && $mode !== 'sperren') {
            $stmt = $pdo->prepare('DELETE FROM module_settings WHERE class_id = ? AND module_key = ?');
            $stmt->execute([$classId, $slug]);
            return;
        }
        $sql = Database::driver() === 'mysql'
            ? 'INSERT INTO module_settings (class_id, module_key, mode) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mode = VALUES(mode)'
            : 'INSERT INTO module_settings (class_id, module_key, mode) VALUES (?, ?, ?) ON CONFLICT(class_id, module_key) DO UPDATE SET mode = excluded.mode';
        $pdo->prepare($sql)->execute([$classId, $slug, $mode]);
    }

    /**
     * Direktschutz: Gesperrte oder noch nicht dran gewesene Module sind auch
     * über ihre URL nicht erreichbar. Prüfung ist personengebunden (wie der
     * Prüfungsmodus): andere Klassen sind nie betroffen. Wird in
     * public/index.php vor dem Dispatch aufgerufen.
     */
    public static function guard(string $path): void
    {
        $user = Auth::user();
        if ($user === null || $user->role !== 'student') {
            return;
        }
        if (\Infocraft\Models\Exam::lockForUser($user->id) !== null) {
            return;
        }
        $visible = self::visible($user);
        foreach (self::modules() as $key => $module) {
            if (isset($visible[$key]) || empty($module['route'])) {
                continue;
            }
            $route = '/' . $module['route'];
            if ($path === $route || str_starts_with($path, $route . '/')) {
                flash('error', 'Dieser Arbeitsbereich ist für deine Klasse (noch) nicht freigegeben.');
                redirect('/');
            }
        }
    }

    public static function show(array $params): void
    {
        if (!Auth::check()) {
            redirect('/login');
        }
        $user = Auth::user();
        $slug = (string) ($params['slug'] ?? '');
        $modules = self::modules();
        if (!isset($modules[$slug])) {
            http_response_code(404);
            render('error', [
                'title' => 'Unbekanntes Modul',
                'message' => 'Dieser Arbeitsbereich existiert (noch) nicht.',
            ]);
            return;
        }
        if (!isset(self::visible($user)[$slug])) {
            http_response_code(404);
            render('error', [
                'title' => 'Noch nicht dran',
                'message' => 'Dieser Arbeitsbereich gehört zu einer höheren Jahrgangsstufe.',
            ]);
            return;
        }
        $module = $modules[$slug];
        if (!empty($module['route'])) {
            redirect($module['route']);
        }
        render('module', [
            'title' => $module['title'],
            'active' => 'module-' . $slug,
            'module' => $module,
        ]);
    }
}
