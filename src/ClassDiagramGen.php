<?php
declare(strict_types=1);

namespace Infocraft;

/**
 * Erzeugt Mermaid-Quelltext fuer Klassendiagramme aus vorhandenen Quellen:
 * - Java-Skripte (Klassen, Schnittstellen, Aufzaehlungen, Attribute, Methoden, Vererbung)
 * - Python-Skripte (Klassen, Vererbung, Attribute, Methoden)
 * - SQL-Tabellen (Tabellen als Klassen, Primaerschluessel zuerst, Fremdschluessel als Beziehungen)
 * Bewusst schulnah gehalten: klare, einfache Strukturen werden erkannt,
 * exotische Sprachmittel werden ignoriert, statt abzustuerzen.
 */
final class ClassDiagramGen
{
    /** Mermaid-Klassendiagramm aus Java-Quelltext ('' wenn keine Klasse gefunden wurde). */
    public static function fromJava(string $code): string
    {
        $clean = self::javaNoise($code);
        $classes = [];
        $relations = [];
        $count = preg_match_all('/\b(class|interface|enum|record)\s+([A-Za-z_]\w*)\s*(?:<[^{>]*>)?([^{;]*)\{/', $clean, $matches, PREG_OFFSET_CAPTURE);
        if (!$count) {
            return '';
        }
        foreach ($matches[0] as $i => $full) {
            $type = (string) $matches[1][$i][0];
            $name = (string) $matches[2][$i][0];
            $tail = trim((string) $matches[3][$i][0]);
            $start = (int) $full[1] + strlen((string) $full[0]);
            $body = self::braceBody($clean, $start);
            if ($body === null) {
                continue;
            }
            $classes[$name] = ['type' => $type, 'members' => self::javaMembers($body, $name, $type === 'interface')];
            if (preg_match('/\bextends\s+([A-Za-z_]\w*)/', $tail, $m) === 1 && $m[1] !== $name) {
                $relations[] = $m[1] . ' <|-- ' . $name;
            }
            if (preg_match('/\bimplements\s+([A-Za-z_]\w*(?:\s*,\s*[A-Za-z_]\w*)*)/', $tail, $m) === 1) {
                foreach (explode(',', $m[1]) as $iface) {
                    $iface = trim($iface);
                    if ($iface !== '' && $iface !== $name) {
                        $relations[] = $iface . ' <|.. ' . $name;
                    }
                }
            }
        }
        return self::render($classes, $relations);
    }

    /** Mermaid-Klassendiagramm aus Python-Quelltext ('' wenn keine Klasse gefunden wurde). */
    public static function fromPython(string $code): string
    {
        $code = self::pythonNoise($code);
        $classes = [];
        $relations = [];
        $current = null;
        $currentIndent = 0;
        $decorator = '';
        foreach (explode("\n", $code) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^\s*@(\w+)/', $line, $m) === 1) {
                $decorator = $m[1];
                continue;
            }
            if (preg_match('/^(\s*)class\s+([A-Za-z_]\w*)\s*(?:\(([^)]*)\))?\s*:/', $line, $m) === 1) {
                $current = $m[2];
                $currentIndent = strlen($m[1]);
                $classes[$current] = ['type' => 'class', 'members' => []];
                foreach (explode(',', (string) ($m[3] ?? '')) as $base) {
                    $base = trim($base);
                    if (preg_match('/^[A-Za-z_]\w*$/', $base) === 1 && strcasecmp($base, 'object') !== 0) {
                        $relations[] = $base . ' <|-- ' . $current;
                    }
                }
                $decorator = '';
                continue;
            }
            if ($current === null) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            if ($indent <= $currentIndent) {
                $current = null;
                continue;
            }
            $trimmed = trim($line);
            if ($indent > $currentIndent + 4) {
                // Tiefer im Methodenkoerper: self-Attribute aufsammeln.
                if (preg_match('/self\.([A-Za-z_]\w*)\s*(?::\s*([\w\[\], .]+))?\s*=/', $trimmed, $m) === 1) {
                    self::addPythonAttribute($classes[$current]['members'], $m[1], $m[2] ?? '');
                }
                continue;
            }
            // Direkt im Klassenkoerper
            if ($decorator === 'property' && preg_match('/^def\s+([A-Za-z_]\w*)\s*\(/', $trimmed, $m) === 1) {
                $classes[$current]['members'][] = '+' . $m[1];
                $decorator = '';
                continue;
            }
            if (preg_match('/^def\s+([A-Za-z_]\w*)\s*\(\s*(self|cls)\s*,?\s*([^)]*)\)\s*(?:->\s*([^:]+))?/', $trimmed, $m) === 1) {
                $params = [];
                foreach (explode(',', (string) ($m[3] ?? '')) as $part) {
                    $part = trim(self::cleanType($part));
                    if ($part !== '') {
                        $params[] = $part;
                    }
                }
                $member = '+' . $m[1] . '(' . implode(', ', $params) . ')';
                if ($m[2] === 'cls') {
                    $member .= '$';
                } elseif (isset($m[4])) {
                    $member .= ' ' . self::cleanType($m[4]);
                }
                $classes[$current]['members'][] = $member;
                $decorator = '';
                continue;
            }
            if (preg_match('/^([A-Za-z_]\w*)\s*:\s*([\w\[\], .]+)$/', $trimmed, $m) === 1) {
                self::addPythonAttribute($classes[$current]['members'], $m[1], $m[2]);
                continue;
            }
            if (preg_match('/^([A-Za-z_]\w*)\s*(?::\s*([\w\[\], .]+))?\s*=/', $trimmed, $m) === 1) {
                self::addPythonAttribute($classes[$current]['members'], $m[1], $m[2] ?? '');
            }
        }
        return self::render($classes, $relations);
    }

    /**
     * Mermaid-Klassendiagramm aus SQL-Tabellen-Metadaten (wie SqlWorkshop::tables()).
     * @param list<array{name: string, columns: list<array{name: string, type: string, pk: bool}>, fks: list<array{column: string, table: string, ref: string}>}> $tables
     */
    public static function fromSqlTables(array $tables): string
    {
        $classes = [];
        $relations = [];
        foreach ($tables as $table) {
            $name = self::ident((string) $table['name']);
            if ($name === '') {
                continue;
            }
            $members = [];
            foreach ($table['columns'] as $column) {
                if ($column['pk']) {
                    $members[] = '+' . self::ident((string) $column['type']) . ' ' . self::ident((string) $column['name']);
                }
            }
            foreach ($table['columns'] as $column) {
                if (!$column['pk']) {
                    $members[] = '+' . self::ident((string) $column['type']) . ' ' . self::ident((string) $column['name']);
                }
            }
            $classes[$name] = ['type' => 'table', 'members' => $members];
            foreach ($table['fks'] as $fk) {
                $ref = self::ident((string) $fk['table']);
                $column = self::ident((string) $fk['column']);
                if ($ref !== '' && $column !== '' && $ref !== $name) {
                    $relations[] = $ref . ' "1" --> "0..n" ' . $name . ' : ' . $column;
                }
            }
        }
        return self::renderSql($classes, $relations);
    }

    /**
     * @param array<string, array{type: string, members: list<string>}> $classes
     * @param list<string> $relations
     */
    private static function render(array $classes, array $relations): string
    {
        if ($classes === []) {
            return '';
        }
        $lines = ['classDiagram'];
        foreach ($classes as $name => $info) {
            $lines[] = '  class ' . $name . ' {';
            if ($info['type'] === 'interface') {
                $lines[] = '    <<interface>>';
            } elseif ($info['type'] === 'enum') {
                $lines[] = '    <<enum>>';
            } elseif ($info['type'] === 'record') {
                $lines[] = '    <<record>>';
            }
            foreach ($info['members'] as $member) {
                $lines[] = '    ' . $member;
            }
            $lines[] = '  }';
        }
        foreach (array_values(array_unique($relations)) as $relation) {
            $lines[] = '  ' . $relation;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, array{type: string, members: list<string>}> $classes
     * @param list<string> $relations
     */
    private static function renderSql(array $classes, array $relations): string
    {
        if ($classes === []) {
            return '';
        }
        $lines = ['classDiagram', 'direction LR'];
        foreach ($classes as $name => $info) {
            $lines[] = '  class ' . $name . ' {';
            $lines[] = '    <<Table>>';
            foreach ($info['members'] as $member) {
                $lines[] = '    ' . $member;
            }
            $lines[] = '  }';
        }
        foreach ($relations as $relation) {
            $lines[] = '  ' . $relation;
        }
        return implode("\n", $lines);
    }

    /** Klassenrumpf nach der oeffnenden Klammer bis zur passenden schliessenden Klammer. */
    private static function braceBody(string $code, int $start): ?string
    {
        $depth = 1;
        $len = strlen($code);
        for ($i = $start; $i < $len; $i++) {
            $ch = $code[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $start, $i - $start);
                }
            }
        }
        return null;
    }

    /** @return list<string> Mermaid-Zeilen fuer Attribute und Methoden eines Java-Klassenkoerpers. */
    private static function javaMembers(string $body, string $className, bool $isInterface): array
    {
        $members = [];
        $depth = 0;
        foreach (explode("\n", $body) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && $depth === 0) {
                if (preg_match('/^([A-Z_][A-Z0-9_]*(?:\s*,\s*[A-Z_][A-Z0-9_]*)*)\s*;/', $trimmed, $cm) === 1) {
                    // Aufzaehlungskonstanten (z. B. ROT, GRUEN, GELB;)
                    foreach (explode(',', $cm[1]) as $constant) {
                        $constant = trim($constant);
                        $member = '+' . $constant;
                        if ($constant !== '' && !in_array($member, $members, true)) {
                            $members[] = $member;
                        }
                    }
                } else {
                    $member = self::javaMember($trimmed, $className, $isInterface);
                    if ($member !== null && !in_array($member, $members, true)) {
                        $members[] = $member;
                    }
                }
            }
            $depth += substr_count($line, '{') - substr_count($line, '}');
            if ($depth < 0) {
                $depth = 0;
            }
        }
        return $members;
    }

    private static function javaMember(string $line, string $className, bool $isInterface): ?string
    {
        if (str_starts_with($line, '@')) {
            return null;
        }
        $skip = ['if', 'for', 'while', 'switch', 'else', 'do', 'try', 'catch', 'finally', 'return', 'new', 'throw', 'this', 'super', 'break', 'continue', 'case', 'static', 'synchronized', 'class', 'interface', 'enum', 'record'];
        $first = strtolower((string) (preg_split('/[\s(]/', $line, 2)[0] ?? ''));
        if (in_array($first, $skip, true)) {
            return null;
        }
        // Methode mit Rueckgabetyp
        if (preg_match('/^((?:public|private|protected)\s+)?((?:static|abstract|final|default|native|strictfp)\s+)*([A-Za-z_][\w\[\]<>,.]*)\s+([A-Za-z_]\w*)\s*\(([^()]*)\)\s*(?:throws\s+[\w,.\s]+)?\s*[;{]/', $line, $m) === 1) {
            $visibility = self::javaVisibility((string) ($m[1] ?? ''), $isInterface);
            $modifiers = (string) ($m[2] ?? '');
            $returnType = self::cleanType($m[3]);
            $member = $visibility . $m[4] . '(' . self::javaParams($m[5]) . ')';
            if (str_contains($modifiers, 'abstract') || str_ends_with($line, ';')) {
                return $member . '*';
            }
            if ($returnType !== '' && $returnType !== 'void') {
                return $member . ' ' . $returnType;
            }
            if (str_contains($modifiers, 'static')) {
                return $member . '$';
            }
            return $member;
        }
        // Konstruktor (kein Rueckgabetyp, Name wie die Klasse)
        if (preg_match('/^((?:public|private|protected)\s+)?(?:static\s+|final\s+)*(' . $className . ')\s*\(([^()]*)\)\s*(?:throws\s+[\w,.\s]+)?\s*[;{]/', $line, $m) === 1) {
            return self::javaVisibility((string) ($m[1] ?? ''), $isInterface) . $m[2] . '(' . self::javaParams($m[3]) . ')';
        }
        // Attribut
        if (preg_match('/^((?:public|private|protected)\s+)?((?:static|final|transient|volatile)\s+)*([A-Za-z_][\w\[\]<>,.]*)\s+([A-Za-z_]\w*)\s*(?:=[^;]*)?;\s*$/', $line, $m) === 1) {
            return self::javaVisibility((string) ($m[1] ?? ''), $isInterface) . self::cleanType($m[3]) . ' ' . $m[4];
        }
        return null;
    }

    private static function javaVisibility(string $modifier, bool $isInterface): string
    {
        $modifier = trim($modifier);
        if ($modifier === 'public') {
            return '+';
        }
        if ($modifier === 'private') {
            return '-';
        }
        if ($modifier === 'protected') {
            return '#';
        }
        return $isInterface ? '+' : '~';
    }

    private static function javaParams(string $params): string
    {
        $parts = [];
        foreach (explode(',', $params) as $part) {
            $part = trim(self::cleanType($part));
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return implode(', ', $parts);
    }

    /** Typen mermaid-sicher machen: Generics und Sonderzeichen entfernen. */
    private static function cleanType(string $type): string
    {
        $type = trim($type);
        for ($i = 0; $i < 2; $i++) {
            $type = (string) preg_replace('/<[^<>]*>/', '', $type);
        }
        $type = (string) preg_replace('/[^\w. ]/', '', $type);
        return trim((string) preg_replace('/\s+/', ' ', $type));
    }

    private static function javaNoise(string $code): string
    {
        $code = (string) preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $code);
        $code = (string) preg_replace("#'(?:\\\\.|[^'\\\\])*'#", "''", $code);
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);
        return (string) preg_replace('#//[^\n]*#', '', $code);
    }

    private static function pythonNoise(string $code): string
    {
        $code = (string) preg_replace('/"""[\s\S]*?"""/', '""', $code);
        $code = (string) preg_replace("/'''[\s\S]*?'''/", "''", $code);
        return (string) preg_replace('#\#[^\n]*#', '', $code);
    }

    private static function ident(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_]/', '', $value);
    }

    /** @param list<string> $members */
    private static function addPythonAttribute(array &$members, string $name, string $type): void
    {
        $type = self::cleanType($type);
        $member = self::pythonVisibility($name) . ($type !== '' ? $type . ' ' : '') . $name;
        if (!in_array($member, $members, true)) {
            $members[] = $member;
        }
    }

    private static function pythonVisibility(string $name): string
    {
        if (str_starts_with($name, '__')) {
            return '-';
        }
        if (str_starts_with($name, '_')) {
            return '#';
        }
        return '+';
    }
}
