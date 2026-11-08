<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\ClassDiagramGen;
use Infocraft\DiagramDsl;
use Infocraft\Models\ClassDiagram;
use Infocraft\Models\Project;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\SqlTemplate;
use Infocraft\Models\User;
use Infocraft\SqlWorkshop;

/**
 * Diagramme: Klassendiagramme als Mermaid-Editor je Person mit automatischer
 * Erzeugung aus Java-/Python-Skripten (Code-Werkstatt) und aus SQL-Tabellen;
 * Struktogramme (Nassi-Shneiderman) und Datenflussdiagramme mit eigener,
 * an Mermaid angelehnter DSL (src/DiagramDsl.php). Lehrkraefte koennen das
 * automatische Erzeugen pro Klasse deaktivieren, selbst zeichnen Schuelerinnen
 * und Schueler immer.
 */
final class DiagramController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        render('diagram_overview', [
            'title' => 'Diagramme',
            'active' => 'module-diagramme',
            'diagrams' => ClassDiagram::all($user->id),
            'diagramAuto' => SchoolClass::diagramAutoForUser($user),
        ]);
    }

    public static function create(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/diagramme');
        }
        // Typ des Diagramms (Klassendiagramm, Struktogramm oder Datenflussdiagramm).
        $kind = (string) ($_POST['kind'] ?? 'manuell');
        if (!in_array($kind, ['manuell', 'struktogramm', 'datenfluss'], true)) {
            $kind = 'manuell';
        }
        if ($kind === 'struktogramm') {
            $starter = "anweisung \"Erster Schritt\"\nwenn \"Bedingung\" dann\n  anweisung \"wahr-Zweig\"\nsonst\n  anweisung \"falsch-Zweig\"\nende\nsolange \"weitere Runde?\"\n  anweisung \"wiederholen\"\nende\n";
        } elseif ($kind === 'datenfluss') {
            $starter = "quelle \"Eingabe\" --> (verarbeiten)\n(verarbeiten) -->|Ergebnis| senke \"Ausgabe\"\n";
        } else {
            $starter = "class Tier {\n  +String name\n}\nclass Hund {\n  +gib_laut()\n}\nTier <|-- Hund\n";
        }
        $diagram = ClassDiagram::create($user->id, $title, $kind, null, $starter);
        flash('success', 'Diagramm angelegt. Zeichne los!');
        redirect('/diagramme/' . $diagram->id);
    }

    public static function detail(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $diagram = self::requireOwn($user, (int) ($params['id'] ?? 0));
        // Struktogramm: Struktur wird serverseitig aus der DSL gebaut (kein Mermaid nötig).
        $struktogramm = $diagram->kind === 'struktogramm' ? DiagramDsl::struktogrammHtml($diagram->mermaid) : null;
        // Datenfluss: DSL wird für die Vorschau in ein Mermaid-flowchart übersetzt.
        $datenfluss = $diagram->kind === 'datenfluss' ? DiagramDsl::datenflussToMermaid($diagram->mermaid) : null;
        render('diagram_edit', [
            'title' => $diagram->title,
            'active' => 'module-diagramme',
            'diagram' => $diagram,
            'struktogramm' => $struktogramm,
            'datenfluss' => $datenfluss,
        ]);
    }

    public static function save(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $diagram = self::requireOwn($user, (int) ($params['id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = $diagram->title;
        }
        // Typ-Buttons im Editor: Beim Speichern darf der Typ mit umschalten.
        $kind = (string) ($_POST['kind'] ?? '');
        // Typzeile (struktogramm/datenfluss/classDiagram) kommt weg: Der Typ
        // hängt am Diagramm, nicht am Quelltext.
        $mermaid = DiagramDsl::stripKindLine((string) ($_POST['mermaid'] ?? ''));
        $diagram->save($title, $mermaid, $kind !== '' ? $kind : null);
        $savedKind = ClassDiagram::find($diagram->id);
        $label = $savedKind === null ? 'Diagramm' : ($savedKind->kind === 'struktogramm' ? 'Struktogramm' : ($savedKind->kind === 'datenfluss' ? 'Datenflussdiagramm' : 'Klassendiagramm'));
        flash('success', $label . ' gespeichert.');
        redirect('/diagramme/' . $diagram->id);
    }

    public static function delete(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $diagram = self::requireOwn($user, (int) ($params['id'] ?? 0));
        ClassDiagram::delete($diagram->id);
        flash('success', 'Diagramm gelöscht.');
        redirect('/diagramme');
    }

    /** Klassendiagramm aus einem Code-Werkstatt-Projekt (Java oder Python) erzeugen. */
    public static function fromProject(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $project = Project::find((int) ($params['id'] ?? 0));
        if ($project === null || $project->ownerId !== $user->id) {
            flash('error', 'Projekt nicht gefunden.');
            redirect('/werkstatt/code');
        }
        if (!SchoolClass::diagramAutoForUser($user)) {
            flash('error', 'Deine Lehrkraft hat das automatische Erzeugen von Klassendiagrammen für deine Klasse deaktiviert.');
            redirect('/werkstatt/code/' . $project->id);
        }
        $mermaid = $project->language === 'java'
            ? ClassDiagramGen::fromJava($project->content)
            : ($project->language === 'python' ? ClassDiagramGen::fromPython($project->content) : '');
        if ($mermaid === '') {
            flash('error', 'Im Skript wurden keine Klassen gefunden (Java- und Python-Skripte mit class-Definitionen).');
            redirect('/werkstatt/code/' . $project->id);
        }
        $diagram = ClassDiagram::create($user->id, 'Klassendiagramm: ' . $project->title, (string) $project->language, $project->id, $mermaid);
        flash('success', 'Klassendiagramm aus dem Skript erzeugt. Du kannst es jetzt anpassen und als PNG exportieren.');
        redirect('/diagramme/' . $diagram->id);
    }

    /** Klassendiagramm aus den Tabellen einer SQL-Werkstatt-Datenbank erzeugen. */
    public static function fromSql(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $template = SqlTemplate::find((string) ($params['slug'] ?? ''));
        if ($template === null) {
            flash('error', 'Diese Datenbank wurde nicht gefunden.');
            redirect('/werkstatt/sql');
        }
        $grant = SqlTemplate::grantFor($template->id, $user->id);
        if ($grant === null) {
            if ($user->role === 'teacher' || $user->role === 'admin') {
                SqlTemplate::grant($template->id, $user->id, 'admin');
            } else {
                flash('error', 'Diese Datenbank wurde dir noch nicht zugeteilt.');
                redirect('/werkstatt/sql');
            }
        }
        if (!SchoolClass::diagramAutoForUser($user)) {
            flash('error', 'Deine Lehrkraft hat das automatische Erzeugen von Klassendiagrammen für deine Klasse deaktiviert.');
            redirect('/werkstatt/sql/' . $template->slug);
        }
        $mermaid = ClassDiagramGen::fromSqlTables(SqlWorkshop::tables(SqlWorkshop::workspacePdo($template->slug, $user->id, ($grant !== null ? $grant['permission'] : 'admin'))));
        if ($mermaid === '') {
            flash('error', 'In dieser Datenbank gibt es noch keine Tabellen.');
            redirect('/werkstatt/sql/' . $template->slug);
        }
        $diagram = ClassDiagram::create($user->id, 'Klassendiagramm: ' . $template->title, 'sql', $template->id, $mermaid);
        flash('success', 'Klassendiagramm aus den Tabellen erzeugt. Du kannst es jetzt anpassen und als PNG exportieren.');
        redirect('/diagramme/' . $diagram->id);
    }

    private static function requireOwn(User $user, int $id): ClassDiagram
    {
        $diagram = ClassDiagram::find($id);
        if ($diagram === null || $diagram->ownerId !== $user->id) {
            self::notFound();
        }
        return $diagram;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        render('error', ['title' => 'Diagramm nicht gefunden', 'message' => 'Dieses Diagramm gibt es nicht oder gehört dir nicht.']);
        exit;
    }
}
