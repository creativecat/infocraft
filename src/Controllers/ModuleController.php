<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

final class ModuleController
{
    /**
     * Zentrale Modul-Registry: Hier neue Arbeitsbereiche eintragen (auch künftige, z. B. für die Oberstufe).
     *
     * @return array<string, array{slug: string, title: string, icon: string, phase: string, status: string, description: string, features: array<int, string>, lehrplan: string}>
     */
    public static function modules(): array
    {
        return [
            'sql' => [
                'slug' => 'sql',
                'title' => 'SQL-Werkstatt',
                'icon' => '🗄️',
                'phase' => 'Phase 2',
                'status' => 'geplant',
                'description' => 'Datenbanken begreifbar machen: von der Tabelle bis zum SQL-Abenteuer.',
                'features' => [
                    'Abbildung eines Shops und von Instagram als Datenmodell',
                    'Backend mit Tabellenansicht zum Durchstöbern und Bearbeiten',
                    'Automatisch generierte ER- und Klassendiagramme (Mermaid-Quelltext zum Weiterverwenden)',
                    'SQL-Text-Adventure im Stil von SQL Island',
                    'Präsentations- und Prüfungsmodus für diesen Bereich',
                ],
                'lehrplan' => 'Datenbanken: Datenerfassung und -verwaltung, SQL-Grundlagen, Datenmodellierung.',
            ],
            'html' => [
                'slug' => 'html',
                'title' => 'HTML-Werkstatt',
                'icon' => '🎨',
                'phase' => 'Phase 3',
                'status' => 'geplant',
                'description' => 'Webseiten bauen mit sofort sichtbarem Ergebnis.',
                'features' => [
                    'Einfacher WYSIWYG-Editor mit Sofortvorschau',
                    'Vorlagen und gestufte Aufgaben (Webseiten gestalten)',
                    'Präsentations- und Prüfungsmodus',
                ],
                'lehrplan' => 'Webseiten gestalten: HTML, CSS, Layout und Gestaltung.',
            ],
            'code' => [
                'slug' => 'code',
                'title' => 'Code-Werkstatt',
                'icon' => '💻',
                'phase' => 'Phase 4',
                'status' => 'geplant',
                'description' => 'Programmieren lernen mit Java und Python, geführt Schritt für Schritt.',
                'features' => [
                    'Online-IDE für Java und Python (Aufbau wie bekannte Online-IDEs)',
                    'Automatische Dateiendungen (.java, .py)',
                    'Schritt-für-Schritt-Steuerung durch geführte Skripte',
                    'Präsentationsmodus',
                ],
                'lehrplan' => 'Algorithmen und Programmieren: Grundlagen, Kontrollstrukturen, Methoden.',
            ],
            'quiz' => [
                'slug' => 'quiz',
                'title' => 'Quiz-Arena',
                'icon' => '🏆',
                'phase' => 'Phase 5',
                'status' => 'geplant',
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
                'status' => 'geplant',
                'description' => 'Üben und abfragen, mit Aufgabenpool und eigenen Aufgaben.',
                'features' => [
                    'Umfangreicher Aufgabenpool, abgestimmt auf die Präsentationen',
                    'Eigene Aufgaben erstellen (Schülerinnen und Schüler sowie Lehrkräfte)',
                    'Lehrkräfte teilen Aufgaben an Klassen aus',
                ],
                'lehrplan' => 'Übergreifend: Anwendung und Festigung der Übungs- und Prüfungsinhalte.',
            ],
        ];
    }

    public static function show(array $params): void
    {
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
        render('module', [
            'title' => $modules[$slug]['title'],
            'active' => 'module-' . $slug,
            'module' => $modules[$slug],
        ]);
    }
}
