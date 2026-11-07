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
                'status' => 'geplant',
                'level' => 6,
                'route' => null,
                'description' => 'Wissen im Wettbewerb festigen, im Stil von Kahoot.',
                'features' => [
                    'Quizrunden im Kahoot-Stil (live, Punkte, Rangliste)',
                    'Quiz zu allen Themen aus Präsentationen und Wiki',
                    'Prüfungsmodus für alle Bereiche',
                ],
                'lehrplan' => 'Übergreifend: Wiederholung und Wissenssicherung aller Themen.',
            ],
            'wiki' => [
                'slug' => 'wiki',
                'title' => 'Wiki & Präsentationen',
                'icon' => '📚',
                'phase' => 'Phase 6',
                'status' => 'geplant',
                'level' => 6,
                'route' => null,
                'description' => 'Nachschlagen, vertiefen, vorbereiten.',
                'features' => [
                    'Umfangreiches Wiki zu allen Themen',
                    'Präsentationen zu den Inhalten',
                    'Später: Lernvideos zum Einbetten, mit Skripten und Beispielaufgaben',
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
