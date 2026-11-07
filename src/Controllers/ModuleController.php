<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
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
                    'Abbildung eines Shops und von Instagram als Datenmodell',
                    'SQL-Werkbank: Abfragen ausführen, Ergebnisse als Tabelle, Rechte je Person',
                    'Automatisch generierte ER-Diagramme (Mermaid-Quelltext zum Weiterverwenden)',
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
                'title' => 'Klassendiagramme',
                'icon' => '📐',
                'phase' => 'Phase 4',
                'status' => 'fertig',
                'level' => 6,
                'route' => 'diagramme',
                'description' => 'Klassen strukturieren: Diagramme zeichnen und automatisch aus Java, Python und SQL erzeugen.',
                'features' => [
                    'Klassendiagramme selbst zeichnen (Mermaid) mit Live-Vorschau und Zwischenspeichern',
                    'Automatisch aus Java- und Python-Skripten: Klassen, Attribute, Methoden und Vererbung',
                    'SQL-Tabellen als Klassendiagramm übernehmen: Spalten, Primärschlüssel, Beziehungen',
                    'Export als PNG, Mermaid-Quelltext bleibt immer kopierbar',
                    'Lehrkräfte deaktivieren die automatische Erzeugung pro Klasse (selbst zeichnen bleibt erlaubt)',
                ],
                'lehrplan' => 'Objektorientierte Modellierung: Klassen, Objekte, Attribute, Methoden, Beziehungen.',
            ],
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
            'quiz' => [
                'slug' => 'quiz',
                'title' => 'Quiz-Arena',
                'icon' => '🏆',
                'phase' => 'Phase 5',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'quiz',
                'description' => 'Wissen im Wettbewerb festigen, im Stil von Kahoot.',
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
                'title' => 'Wiki & Präsentationen',
                'icon' => '📚',
                'phase' => 'Phase 6',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'wiki',
                'description' => 'Nachschlagen, vertiefen, vorbereiten.',
                'features' => [
                    'Wiki-Seiten zu Fachbegriffen und Konzepten, ab Jahrgangsstufe 6',
                    'Lehrkräfte legen Seiten mit einfacher Markdown-Formatierung an',
                    'Stufen-Filter: Schülerinnen und Schüler sehen Seiten bis zur eigenen Klassenstufe',
                    'Präsentationen und Lernvideos folgen',
                ],
                'lehrplan' => 'Übergreifend: Fachbegriffe und Konzepte, Selbstlernen.',
            ],
            'tasks' => [
                'slug' => 'tasks',
                'title' => 'Aufgabenpool',
                'icon' => '📝',
                'phase' => 'Phase 7',
                'status' => 'in-arbeit',
                'level' => 6,
                'route' => 'aufgaben',
                'description' => 'Üben und abfragen, mit Aufgabenpool und eigenen Aufgaben.',
                'features' => [
                    'Lehrkräfte legen Aufgaben an und teilen sie an Klassen oder Einzelne aus',
                    'Schülerinnen und Schüler sehen ihre Aufgaben bis zur eigenen Klassenstufe',
                    'Umfangreicher Aufgabenpool, abgestimmt auf Präsentationen (folgt)',
                ],
                'lehrplan' => 'Übergreifend: Anwendung und Festigung der Übungs- und Prüfungsinhalte.',
            ],
        ];
    }

    /**
     * Module, die diese Person sehen darf: Schülerinnen und Schüler nur bis
     * zu ihrer Jahrgangsstufe (plus ausgeteilte Inhalte), Lehrkräfte alle.
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
        $visible = [];
        foreach ($modules as $key => $module) {
            if (($module['level'] ?? 6) <= $level) {
                $visible[$key] = $module;
            }
        }
        // Ausgeteilte Datenbanken sind sichtbar, auch unterhalb der Stufe
        if (!isset($visible['sql']) && SqlTemplate::hasGrants($user)) {
            $visible['sql'] = $modules['sql'];
        }
        return $visible;
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
