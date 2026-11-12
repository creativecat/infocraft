<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Präsentationen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Poll;
use Infocraft\Models\Presentation;
use Infocraft\Models\User;

/**
 * Präsentationen: Folien für den Unterricht, mit Vollbild-Modus und
 * editierbarem Code direkt in der Präsentation (Syntax-Highlighting).
 * Lehrkräfte verwalten ihre Präsentationen, Schülerinnen und Schüler
 * sehen die Folien ab der angegebenen Jahrgangsstufe.
 */
final class PresentationController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $isTeacher = Auth::isTeacher();
        $items = $isTeacher ? Presentation::all() : Presentation::visibleFor(Auth::levelOf($user) ?? 0);
        render('praesentation_overview', [
            'title' => 'Präsentationen',
            'active' => 'module-praesentationen',
            'items' => $items,
            'isTeacher' => $isTeacher,
            'user' => $user,
        ]);
    }

    public static function create(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Präsentationen anlegen.');
            redirect('/praesentationen');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        $level = (int) ($_POST['level'] ?? 6);
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/praesentationen');
        }
        $presentation = Presentation::create($user->id, $title, $level);
        flash('success', 'Präsentation angelegt. Füge jetzt die erste Folie hinzu.');
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function edit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $presentation = Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Präsentation gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher()) {
            if ($presentation->level > (Auth::levelOf($user) ?? 0)) {
                http_response_code(404);
                render('error', ['title' => 'Noch nicht dran', 'message' => 'Diese Präsentation gehört zu einer höheren Jahrgangsstufe.']);
                return;
            }
            redirect('/praesentationen/' . $presentation->id . '/zeigen');
        }
        if ($user->role !== 'admin' && $presentation->teacherId !== $user->id) {
            flash('error', 'Diese Präsentation kannst du nicht bearbeiten.');
            redirect('/praesentationen');
        }
        render('praesentation_edit', [
            'title' => $presentation->title . ' bearbeiten',
            'active' => 'module-praesentationen',
            'presentation' => $presentation,
            'slides' => $presentation->slides(),
            'user' => $user,
        ]);
    }

    /** Titel und Jahrgangsstufe speichern. */
    public static function saveMeta(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/praesentationen/' . $presentation->id);
        }
        $presentation->save($title, (int) ($_POST['level'] ?? $presentation->level));
        flash('success', 'Gespeichert.');
        redirect('/praesentationen/' . $presentation->id);
    }

    /** Präsentationsmodus: eine Folie nach der anderen, Vollbild per Knopfdruck. */
    public static function show(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        // Permanenter Themen-Link (/praesentationen/thema/{slug}) oder klassisch per id.
        $presentation = isset($params['slug'])
            ? Presentation::findBySlug((string) $params['slug'])
            : Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Präsentation gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher() && $presentation->level > (Auth::levelOf($user) ?? 0)) {
            http_response_code(404);
            render('error', ['title' => 'Noch nicht dran', 'message' => 'Diese Präsentation gehört zu einer höheren Jahrgangsstufe.']);
            return;
        }
        $slides = $presentation->slides();
        $rendered = [];
        foreach ($slides as $slide) {
            $slide['html'] = ($slide['type'] ?? 'text') === 'text'
                ? self::renderSlideBody((string) ($slide['body'] ?? ''))
                : '';
            $rendered[] = $slide;
        }
        render('praesentation_view', [
            'title' => $presentation->title,
            'active' => 'module-praesentationen',
            'presentation' => $presentation,
            'slides' => $rendered,
            'mayEdit' => Auth::isTeacher() && ($user->role === 'admin' || $presentation->teacherId === $user->id),
            'isTeacher' => Auth::isTeacher(),
            'user' => $user,
        ]);
    }

    /**
     * Moderatorenbildschirm (nur für Lehrkräfte): Steuerzentrale mit aktueller
     * und nächster Folie, Folienliste, Timer und Uhr. Läuft im selben Browser
     * synchron zur Präsentationsansicht auf dem Beamer (BroadcastChannel).
     */
    public static function moderator(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $presentation = Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Präsentation gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher()) {
            // Schülerinnen und Schüler haben im Präsentationsmodus nichts zu steuern.
            redirect('/praesentationen/' . $presentation->id . '/zeigen');
        }
        $slides = $presentation->slides();
        $rendered = [];
        foreach ($slides as $slide) {
            $slide['html'] = ($slide['type'] ?? 'text') === 'text'
                ? self::renderSlideBody((string) ($slide['body'] ?? ''))
                : '';
            $rendered[] = $slide;
        }
        render('praesentation_moderator', [
            'title' => $presentation->title . ' (Moderator)',
            'active' => 'module-praesentationen',
            'presentation' => $presentation,
            'slides' => $rendered,
            'user' => $user,
        ]);
    }

    /**
     * Folien-Text rendern (#58): Markdown-lite plus Bausteine für den Aufbau.
     * Ein Baustein beginnt mit einer Zeile ":::name" und endet mit ":::ende":
     *   :::titel     große Titelfolie (Farbverlauf, zentriert)
     *   :::merksatz  hervorgehobener Merksatz (gold)
     *   :::uebung    Übungsaufgabe (grün)
     *   :::loesung   Musterlösung (blau)
     *   :::hinweis   Tipp/Callout (violett)
     *   :::vergleich zwei Spalten nebeneinander, Trennzeile ":::|"
     *   :::karten    Kartenraster (je "- Punkt" eine Karte)
     *   :::statistik große Zahlen (je "- **42** Text" eine Karte)
     *   :::zitat     Zitat, zentriert und groß
     * Alles außerhalb der Bausteine wird wie bisher als Markdown-lite
     * gerendert; ungeöffnete oder nicht geschlossene Bausteine schaden nicht.
     */
    public static function renderSlideBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        $html = '';
        $plain = [];
        $open = '';
        $buf = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $t = trim($lines[$i]);
            if ($open === '') {
                if (preg_match('/^:::(titel|merksatz|uebung|loesung|hinweis|vergleich|karten|statistik|zitat)$/', $t, $m) === 1) {
                    $html .= WikiController::renderMarkdown(implode("\n", $plain));
                    $plain = [];
                    $open = $m[1];
                    $buf = [];
                    continue;
                }
                $plain[] = $lines[$i];
                continue;
            }
            if ($t === ':::ende') {
                $html .= self::renderSlideBlock($open, implode("\n", $buf));
                $open = '';
                $buf = [];
                continue;
            }
            $buf[] = $lines[$i];
        }
        if ($open !== '') {
            $html .= self::renderSlideBlock($open, implode("\n", $buf));
        }
        $html .= WikiController::renderMarkdown(implode("\n", $plain));
        return $html;
    }

    /** Einen Baustein rendern (siehe renderSlideBody). */
    private static function renderSlideBlock(string $kind, string $content): string
    {
        if ($kind === 'vergleich') {
            $parts = preg_split('/\n?\Q:::|\E\n?/', $content) ?: [];
            $left = WikiController::renderMarkdown(trim((string) ($parts[0] ?? '')));
            $right = WikiController::renderMarkdown(trim((string) ($parts[1] ?? '')));
            if (trim((string) ($parts[1] ?? '')) === '') {
                $right = $left;
                $left = '';
            }
            return '<div class="sblock sblock-vergleich"><div class="sb-col">' . $left . '</div><div class="sb-col">' . $right . '</div></div>';
        }
        $inner = WikiController::renderMarkdown($content);
        $classes = [
            'titel' => 'sblock sblock-titel',
            'merksatz' => 'sblock sblock-merksatz',
            'uebung' => 'sblock sblock-uebung',
            'loesung' => 'sblock sblock-loesung',
            'hinweis' => 'sblock sblock-hinweis',
            'karten' => 'sblock sblock-karten',
            'statistik' => 'sblock sblock-statistik',
            'zitat' => 'sblock sblock-zitat',
        ];
        return '<div class="' . ($classes[$kind] ?? 'sblock') . '">' . $inner . '</div>';
    }

    /** Folie anfügen (Text oder Code), ohne JavaScript. */
    public static function addSlide(array $params): void
    {        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $slides = $presentation->slides();
        $type = ($_POST['type'] ?? '') === 'code' ? 'code' : 'text';
        $template = (string) ($_POST['template'] ?? '');
        $templates = self::slideTemplates();
        if ($type === 'code') {
            $slides[] = ['type' => 'code', 'title' => 'Code zeigen', 'lang' => 'java', 'code' => ''];
        } elseif ($template === 'codedemo') {
            $slides[] = ['type' => 'code', 'title' => 'Live-Demo', 'lang' => 'java', 'code' => '// Live tippen oder vorbereitet zeigen' . "\n" . 'System.out.println("Hallo!");'];
        } elseif (isset($templates[$template])) {
            $slides[] = ['type' => 'text', 'title' => $templates[$template]['title'], 'body' => $templates[$template]['body']];
        } else {
            $slides[] = ['type' => 'text', 'title' => '', 'body' => ''];
        }
        $presentation->setSlides($slides);
        flash('success', 'Folie angefügt.');
        redirect('/praesentationen/' . $presentation->id);
    }

    /**
     * Fertige Folien-Vorlagen (#58): Im Editor mit einem Klick anlegbar.
     * Jede Vorlage nutzt die Bausteine aus renderSlideBody, damit Folien
     * sofort aufgeräumt aussehen - ohne Formatierungsarbeit.
     * @return array<string, array{title: string, body: string, label: string}>
     */
    public static function slideTemplates(): array
    {
        return [
            'titelfolie' => [
                'label' => '🚀 Titelfolie (groß, Farbverlauf)',
                'title' => 'Titel der Präsentation',
                'body' => ":::titel\n# Titel hier eintragen\nUntertitel: Ein Satz, worum es geht\n:::ende\n",
            ],
            'agenda' => [
                'label' => '🗺️ Agenda (Kartenraster)',
                'title' => 'Das kommt heute dran',
                'body' => ":::karten\n- **Teil 1** – Einstieg mit Beispiel\n- **Teil 2** – Erarbeiten am Rechner\n- **Teil 3** – Üben und Festigen\n:::ende\n",
            ],
            'merksatz' => [
                'label' => '📌 Merksatz (gold hervorgehoben)',
                'title' => 'Merksatz',
                'body' => ":::merksatz\n**Merksatz:** Hier steht der eine Satz, den sich alle merken sollen.\n:::ende\n",
            ],
            'uebungloesung' => [
                'label' => '✏️ Übung mit Musterlösung',
                'title' => 'Übung',
                'body' => ":::uebung\n**Übung:** Beschreibe hier die Aufgabe in ein bis drei Sätzen.\n\n1. Erster Schritt\n2. Zweiter Schritt\n:::ende\n\n:::loesung\n**Musterlösung:** So sieht ein guter Weg aus - zum Aufklappen nach der Übung.\n:::ende\n",
            ],
            'vergleich' => [
                'label' => '⚖️ Vergleich (zwei Spalten)',
                'title' => 'Vergleich: links gegen rechts',
                'body' => ":::vergleich\n## Links\n- Punkt auf der linken Seite\n- Noch ein Punkt\n:::|\n## Rechts\n- Punkt auf der rechten Seite\n- Noch ein Punkt\n:::ende\n",
            ],
            'hinweis' => [
                'label' => '💡 Hinweis / Tipp (violett)',
                'title' => 'Hinweis',
                'body' => ":::hinweis\n**Tipp:** Typische Stolperfalle oder guter Hinweis an dieser Stelle.\n:::ende\n",
            ],
            'statistik' => [
                'label' => '📊 Statistik (große Zahlen)',
                'title' => 'Zahlen, die beeindrucken',
                'body' => ":::statistik\n- **42** Erklärung zur Zahl\n- **7** Zweite Zahl mit Erklärung\n- **100 %** Dritte Zahl mit Erklärung\n:::ende\n",
            ],
            'zitat' => [
                'label' => '💬 Zitat (groß, zentriert)',
                'title' => '',
                'body' => ":::zitat\n\"Ein Zitat, das zum Nachdenken anregt.\"\n– Namensnennung\n:::ende\n",
            ],
            'codedemo' => [
                'label' => '💻 Code-Demo (Java mit Highlighting)',
                'title' => 'Live-Demo',
                'body' => '',
            ],
        ];
    }

    /** Folie speichern (aus dem Editor oder direkt aus dem Präsentationsmodus). */
    public static function saveSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $slides = $presentation->slides();
        if (!isset($slides[$pos])) {
            flash('error', 'Diese Folie gibt es nicht.');
            redirect('/praesentationen/' . $presentation->id);
        }
        $type = $slides[$pos]['type'] === 'code' ? 'code' : 'text';
        $slides[$pos]['notes'] = (string) ($_POST['notes'] ?? ($slides[$pos]['notes'] ?? ''));
        if ($type === 'code') {
            $slides[$pos]['title'] = (string) ($_POST['title'] ?? $slides[$pos]['title']);
            $slides[$pos]['lang'] = Presentation::cleanLang((string) ($_POST['lang'] ?? $slides[$pos]['lang']));
            $slides[$pos]['code'] = (string) ($_POST['code'] ?? $slides[$pos]['code']);
        } else {
            $slides[$pos]['title'] = (string) ($_POST['title'] ?? $slides[$pos]['title']);
            $slides[$pos]['body'] = (string) ($_POST['body'] ?? $slides[$pos]['body']);
        }
        $presentation->setSlides($slides);
        flash('success', 'Folie gespeichert.');
        if (($_POST['ziel'] ?? '') === 'zeigen') {
            redirect('/praesentationen/' . $presentation->id . '/zeigen#f' . $pos);
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function deleteSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $slides = $presentation->slides();
        if (isset($slides[$pos])) {
            array_splice($slides, $pos, 1);
            $presentation->setSlides($slides);
            flash('success', 'Folie gelöscht.');
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function moveSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $dir = ($params['dir'] ?? '') === 'hoch' ? 'hoch' : 'runter';
        $slides = $presentation->slides();
        $target = $dir === 'hoch' ? $pos - 1 : $pos + 1;
        if (isset($slides[$pos], $slides[$target])) {
            $tmp = $slides[$pos];
            $slides[$pos] = $slides[$target];
            $slides[$target] = $tmp;
            $presentation->setSlides($slides);
            flash('success', 'Reihenfolge geändert.');
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    /**
     * Live-Abstimmung (Issue #3, Kahoot-Stil): Die Lehrkraft startet eine
     * Abstimmung im Moderatorenbildschirm (Frage mit 2-4 Antworten). Läuft
     * schon eine, wird diese unverändert zurückgegeben.
     */
    public static function pollStart(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $question = trim((string) ($_POST['question'] ?? ''));
        $answers = [];
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            foreach ($_POST['answers'] as $answer) {
                $answer = trim((string) $answer);
                if ($answer !== '' && count($answers) < 4) {
                    $answers[] = mb_substr($answer, 0, 100);
                }
            }
        }
        if ($question === '' || count($answers) < 2) {
            flash('error', 'Bitte eine Frage und mindestens zwei Antworten angeben.');
            redirect('/praesentationen/' . $presentation->id . '/moderator');
        }
        Poll::start($presentation->id, $user->id, $question, $answers);
        flash('success', 'Abstimmung gestartet. Die Schülerinnen und Schüler sehen sie gleich in der Präsentationsansicht.');
        redirect('/praesentationen/' . $presentation->id . '/moderator');
    }

    /** Abstimmung schließen: Ergebnis bleibt zum Besprechen sichtbar. */
    public static function pollEnd(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $poll = Poll::openFor($presentation->id);
        if ($poll !== null) {
            $poll->close();
            flash('success', 'Abstimmung geschlossen. Das Ergebnis bleibt sichtbar.');
        }
        redirect('/praesentationen/' . $presentation->id . '/moderator');
    }

    /** Abstimmungsstatus als JSON (Moderator: Ergebnisbalken, Schüler: Knöpfe). */
    public static function pollStatus(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            http_response_code(401);
            echo '{"ok":false,"error":"Anmeldung nötig"}';
            exit;
        }
        $presentation = Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null || (!Auth::isTeacher() && $presentation->level > (Auth::levelOf($user) ?? 0))) {
            http_response_code(404);
            echo '{"ok":false,"error":"Präsentation nicht gefunden"}';
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        $poll = Poll::openFor($presentation->id);
        if ($poll === null) {
            // Nach dem Schließen zeigt die Ansicht das letzte Ergebnis noch kurz.
            $closed = Poll::lastClosedFor($presentation->id);
            $payload = $closed === null
                ? ['ok' => true, 'active' => false, 'poll' => null]
                : ['ok' => true, 'active' => false, 'poll' => $closed->statusFor($user->id)];
        } else {
            $payload = ['ok' => true, 'active' => true, 'poll' => $poll->statusFor($user->id)];
        }
        // Zählen der eigenen Stimme nur bei offener Abstimmung ausgeben, damit
        // die Schülerinnen und Schüler nicht aus dem Balken ablesen.
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Stimme abgeben (überschreibbar bis zum Schließen). */
    public static function pollVote(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null || (!Auth::isTeacher() && $presentation->level > (Auth::levelOf($user) ?? 0))) {
            flash('error', 'Präsentation nicht gefunden.');
            redirect('/praesentationen');
        }
        $poll = Poll::openFor($presentation->id);
        if ($poll === null) {
            flash('error', 'Gerade läuft keine Abstimmung.');
            redirect('/praesentationen/' . $presentation->id . '/zeigen');
        }
        $choice = (int) ($_POST['choice'] ?? -1);
        if (!$poll->vote($user->id, $choice)) {
            flash('error', 'Diese Antwort gibt es bei dieser Abstimmung nicht.');
        } else {
            flash('success', 'Deine Stimme zählt. Du kannst sie bis zum Schließen ändern.');
        }
        redirect('/praesentationen/' . $presentation->id . '/zeigen');
    }

    public static function delete(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        Presentation::delete($presentation->id);
        flash('success', 'Präsentation gelöscht.');
        redirect('/praesentationen');
    }

    /** Nur Lehrkraft (Eigentümerin/Eigentümer oder Admin) darf bearbeiten. */
    private static function editable(User $user, int $id): Presentation
    {
        $presentation = Presentation::find($id);
        if ($presentation === null || !Auth::isTeacher() || ($user->role !== 'admin' && $presentation->teacherId !== $user->id)) {
            flash('error', 'Diese Präsentation kannst du nicht bearbeiten.');
            redirect('/praesentationen');
        }
        return $presentation;
    }
}
