<?php
declare(strict_types=1);

namespace Infocraft;

use function e;

/**
 * Diagramm-DSL für Struktogramme (Nassi-Shneiderman) und Datenflussdiagramme.
 *
 * Die Syntax ist bewusst an Mermaid angelehnt: zeilenbasiert, Schlüsselwörter
 * statt Klammern, %% für Kommentare. Wie beim Klassendiagramm-Editor bleibt
 * der Quelltext immer editierbar; die Vorschau entsteht daraus.
 *
 * Struktogramm (Struktur statt Flusspfeile):
 *   struktogramm
 *   anweisung "Punkte einlesen"
 *   wenn "punkte >= 90" dann
 *     anweisung "sehr gut ausgeben"
 *   sonst
 *     anweisung "weiter ueben"
 *   ende
 *   solange "weitere Schulaufgabe?"
 *     anweisung "Punkte einlesen"
 *   ende
 *   wiederhole
 *     anweisung "ruehren"
 *   bis "Teig glatt"
 *
 * Datenfluss (funktionale Modellierung):
 *   datenfluss
 *   quelle "Lehrkraft" --> (notenErfassen)
 *   speicher "Schuelerliste" --> (notenErfassen)
 *   (notenErfassen) -->|Noten| (schnittBerechnen)
 *   (schnittBerechnen) -->|Schnitt| senke "Zeugnis"
 */
final class DiagramDsl
{
    /**
     * Führende Typzeile entfernen. Die Auswahl des Diagrammtyps läuft über die
     * Buttons im Editor, niemand muss "struktogramm", "datenfluss" oder
     * "classDiagram" mehr eintippen. Zeilen mit weiterem Inhalt bleiben
     * unberührt, damit echte Diagrammzeilen erhalten bleiben.
     */
    public static function stripKindLine(string $source): string
    {
        return (string) preg_replace('/^\s*(?:struktogramm|datenfluss|classDiagram)\s*\R/i', '', $source, 1);
    }

    /* ===================== Struktogramme ===================== */

    /**
     * Struktogramm-Quelltext in geschachteltes HTML (Nassi-Shneiderman) übersetzen.
     * @return array{error: ?string, html: string}
     */
    public static function struktogrammHtml(string $source): array
    {
        $parsed = self::parseStruktogramm($source);
        if ($parsed['error'] !== null) {
            return ['error' => $parsed['error'], 'html' => ''];
        }
        return ['error' => null, 'html' => self::renderStrukturNode($parsed['root'])];
    }

    /**
     * Zeilen in einen Syntaxbaum (sequenz / anweisung / wenn / solange / wiederhole) bauen.
     * @return array{error: ?string, root: ?array}
     */
    private static function parseStruktogramm(string $source): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $source) ?: [] as $index => $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '' || str_starts_with($trimmed, '%%') || str_starts_with($trimmed, '//')) {
                continue;
            }
            if ($lines === [] && str_starts_with(mb_strtolower($trimmed), 'struktogramm')) {
                continue; // optionale erste Zeile
            }
            $parts = preg_split('/\s+/u', $trimmed, 2) ?: [];
            $lines[] = [
                'ln' => $index + 1,
                'kw' => mb_strtolower((string) $parts[0]),
                'rest' => trim((string) ($parts[1] ?? '')),
            ];
        }

        $pos = 0;
        $result = self::parseStrukturSeq($lines, $pos, []);
        if ($result['error'] !== null) {
            return ['error' => $result['error'], 'root' => null];
        }
        if ($result['items'] === []) {
            return ['error' => 'Das Struktogramm ist leer. Beginne zum Beispiel mit: anweisung "Erster Schritt"', 'root' => null];
        }
        return ['error' => null, 'root' => ['type' => 'seq', 'items' => $result['items']]];
    }

    /**
     * Eine Sequenz von Anweisungen bis zu einem der Abbruchwörter lesen.
     * @param list<array{ln: int, kw: string, rest: string}> $lines
     * @return array{items: list<array>, term: ?string, error: ?string}
     */
    private static function parseStrukturSeq(array $lines, int &$pos, array $terminators): array
    {
        $items = [];
        while ($pos < count($lines)) {
            $line = $lines[$pos];
            $kw = $line['kw'];
            if (in_array($kw, $terminators, true)) {
                return ['items' => $items, 'term' => $kw, 'error' => null];
            }
            if ($kw === 'anweisung') {
                $items[] = ['type' => 'stmt', 'text' => self::strukturText($line['rest'])];
                $pos += 1;
                continue;
            }
            if ($kw === 'wenn') {
                $cond = $line['rest'];
                if ($cond === '' || !preg_match('/\s*dann$/ui', $cond)) {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Bei wenn fehlt die Bedingung mit dann, z. B. wenn "punkte >= 90" dann'];
                }
                $cond = trim((string) preg_replace('/\s*dann$/ui', '', $cond));
                $pos += 1;
                $truePart = self::parseStrukturSeq($lines, $pos, ['sonst', 'ende']);
                if ($truePart['error'] !== null) {
                    return $truePart;
                }
                if ($truePart['term'] === null) {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Der wenn-Block muss mit ende geschlossen werden.'];
                }
                $falseItems = [];
                if ($truePart['term'] === 'sonst') {
                    $pos += 1;
                    $falsePart = self::parseStrukturSeq($lines, $pos, ['ende']);
                    if ($falsePart['error'] !== null) {
                        return $falsePart;
                    }
                    if ($falsePart['term'] !== 'ende') {
                        return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Der sonst-Zweig muss mit ende geschlossen werden.'];
                    }
                    $falseItems = $falsePart['items'];
                }
                $pos += 1; // ende verbrauchen
                $items[] = ['type' => 'if', 'cond' => self::strukturText($cond), 'true' => $truePart['items'], 'false' => $falseItems];
                continue;
            }
            if ($kw === 'solange') {
                $cond = $line['rest'];
                if ($cond === '') {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Bei solange fehlt die Bedingung, z. B. solange "summe < 10"'];
                }
                $pos += 1;
                $body = self::parseStrukturSeq($lines, $pos, ['ende']);
                if ($body['error'] !== null) {
                    return $body;
                }
                if ($body['term'] !== 'ende') {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Der solange-Block muss mit ende geschlossen werden.'];
                }
                $pos += 1; // ende verbrauchen
                $items[] = ['type' => 'while', 'cond' => self::strukturText($cond), 'body' => $body['items']];
                continue;
            }
            if ($kw === 'wiederhole') {
                $pos += 1;
                $body = self::parseStrukturSeq($lines, $pos, ['bis']);
                if ($body['error'] !== null) {
                    return $body;
                }
                if ($body['term'] !== 'bis') {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Der wiederhole-Block muss mit bis "Bedingung" geschlossen werden.'];
                }
                $cond = $lines[$pos]['rest'];
                $pos += 1; // bis-Zeile verbrauchen
                if ($cond === '') {
                    return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Bei bis fehlt die Bedingung, z. B. bis "teig glatt"'];
                }
                $items[] = ['type' => 'until', 'cond' => self::strukturText($cond), 'body' => $body['items']];
                continue;
            }
            if ($kw === 'ende' || $kw === 'sonst' || $kw === 'bis') {
                $want = $terminators === []
                    ? 'eine neue Anweisung (anweisung, wenn, solange, wiederhole)'
                    : '"' . implode('" oder "', $terminators) . '"';
                return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': "' . $kw . '" passt hier nicht, hier gehört ' . $want . ' hin.'];
            }
            return ['items' => $items, 'term' => null, 'error' => 'Zeile ' . $line['ln'] . ': Unbekannte Anweisung "' . $line['kw'] . '". Erlaubt sind: anweisung, wenn/dann/sonst, solange, wiederhole/bis, ende.'];
        }
        return ['items' => $items, 'term' => null, 'error' => null];
    }

    /** Anweisungstext von optionalen Anführungszeichen befreien. */
    private static function strukturText(string $text): string
    {
        $text = trim($text);
        if (strlen($text) >= 2 && str_starts_with($text, '"') && str_ends_with($text, '"')) {
            $text = substr($text, 1, -1);
        }
        return trim($text);
    }

    /** Syntaxbaum (eine Sequenz) als Nassi-Shneiderman-HTML rendern. Text wird hier maskiert. */
    private static function renderStrukturNode(array $node): string
    {
        $html = '<div class="nassi"><div class="nassi-seq">';
        foreach ($node['items'] as $item) {
            $html .= self::renderStrukturItem($item);
        }
        return $html . '</div></div>';
    }

    private static function renderStrukturItem(array $item): string
    {
        if ($item['type'] === 'stmt') {
            return '<div class="nassi-stmt">' . ($item['text'] !== '' ? e($item['text']) : '&nbsp;') . '</div>';
        }
        if ($item['type'] === 'if') {
            $html = '<div class="nassi-if"><div class="nassi-cond"><span class="nassi-tri"></span>' . e($item['cond']) . '</div><div class="nassi-branches">';
            $html .= '<div class="nassi-branch"><span class="nassi-branch-label">wahr</span>' . self::renderStrukturSeqHtml($item['true']) . '</div>';
            $html .= '<div class="nassi-branch"><span class="nassi-branch-label">falsch</span>' . self::renderStrukturSeqHtml($item['false']) . '</div>';
            return $html . '</div></div>';
        }
        if ($item['type'] === 'while') {
            return '<div class="nassi-loop nassi-while"><div class="nassi-loop-head">' . e($item['cond']) . '</div><div class="nassi-loop-body">' . self::renderStrukturSeqHtml($item['body']) . '</div></div>';
        }
        return '<div class="nassi-loop nassi-until"><div class="nassi-loop-body">' . self::renderStrukturSeqHtml($item['body']) . '</div><div class="nassi-loop-head">' . e($item['cond']) . '</div></div>';
    }

    /** Eine Item-Liste als vertikale Sequenz (leere Listen bekommen eine Schraffur). */
    private static function renderStrukturSeqHtml(array $items): string
    {
        if ($items === []) {
            return '<div class="nassi-empty"></div>';
        }
        $html = '<div class="nassi-seq">';
        foreach ($items as $item) {
            $html .= self::renderStrukturItem($item);
        }
        return $html . '</div>';
    }

    /* ===================== Datenflussdiagramme ===================== */

    /**
     * Datenfluss-Quelltext in ein Mermaid-flowchart übersetzen (Kreise für
     * Funktionen, Rechtecke für Quellen/Senken, Zylinder für Datenspeicher).
     * @return array{error: ?string, mermaid: string}
     */
    public static function datenflussToMermaid(string $source): array
    {
        $node = '(?:(quelle|senke|speicher)\s+"([^"]*)"|\(([A-Za-z_][A-Za-z0-9_]*)\))';
        $reEdgeLabel = '/^' . $node . '\s*-->\s*\|([^|]*)\|\s*' . $node . '$/ui';
        $reEdge = '/^' . $node . '\s*-->\s*' . $node . '$/ui';
        $reNode = '/^' . $node . '$/ui';

        $ids = [];
        $order = 0;
        $shapes = [];
        $edges = [];
        $anyNode = false;

        $register = static function (array $match, int $offset) use (&$ids, &$order, &$shapes, &$anyNode): string {
            $kind = $match[$offset] !== '' ? $match[$offset] : 'funktion';
            $text = $match[$offset] !== '' ? $match[$offset + 1] : $match[$offset + 2];
            $key = $kind . ':' . $text;
            if (!isset($ids[$key])) {
                $order += 1;
                $ids[$key] = 'N' . $order;
                $clean = preg_replace('/["\[\]()<>|]/u', '', $text) ?? $text;
                if ($kind === 'funktion') {
                    $shapes[] = 'N' . $order . '(("' . $clean . '"))';
                } elseif ($kind === 'speicher') {
                    $shapes[] = 'N' . $order . '[("' . $clean . '")]';
                } else {
                    $shapes[] = 'N' . $order . '["' . $clean . '"]';
                }
            }
            $anyNode = true;
            return $ids[$key];
        };

        foreach (preg_split('/\r\n|\r|\n/', $source) ?: [] as $index => $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '' || str_starts_with($trimmed, '%%') || str_starts_with($trimmed, '//')) {
                continue;
            }
            $first = mb_strtolower((string) (preg_split('/\s+/u', $trimmed, 2)[0] ?? ''));
            if ($first === 'datenfluss') {
                continue; // optionale erste Zeile
            }
            if (preg_match($reEdgeLabel, $trimmed, $m)) {
                $from = $register($m, 1);
                $to = $register($m, 5);
                $label = preg_replace('/["\[\]()<>|]/u', '', $m[4]) ?? $m[4];
                $edges[] = $from . ' -->|' . $label . '| ' . $to;
                continue;
            }
            if (preg_match($reEdge, $trimmed, $m)) {
                $edges[] = $register($m, 1) . ' --> ' . $register($m, 4);
                continue;
            }
            if (preg_match($reNode, $trimmed, $m)) {
                $register($m, 1);
                continue;
            }
            return ['error' => 'Zeile ' . ($index + 1) . ': "' . $trimmed . '" verstehe ich nicht. Erlaubt sind: quelle "Name", senke "Name", speicher "Name", (funktionsName) und Pfeile --> bzw. -->|Daten|.', 'mermaid' => ''];
        }

        if (!$anyNode) {
            return ['error' => 'Das Datenflussdiagramm ist leer. Beginne zum Beispiel mit: quelle "Eingabe" --> (verarbeiten)', 'mermaid' => ''];
        }

        $lines = array_merge(['flowchart TB'], $shapes, $edges);
        return ['error' => null, 'mermaid' => implode("\n", $lines)];
    }
}
