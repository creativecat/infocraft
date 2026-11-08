<?php
declare(strict_types=1);

namespace Infocraft;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Kern der SQL-Werkstatt: Vorlagen aus sql/templates/ werden pro Person als
 * eigene SQLite-Kopie angelegt. Rechte (read/write/admin) werden bei jeder
 * Anweisung geprüft, ER-Diagramme werden aus dem echten Schema erzeugt.
 */
final class SqlWorkshop
{
    /** Anzeigename der Rechte-Stufen. */
    public const PERMISSIONS = [
        'read' => 'Nur lesen',
        'write' => 'Lesen + Schreiben',
        'admin' => 'Vollzugriff (Tabellen anlegen)',
    ];

    /** Ausführungsrechte je Stufe (erste Schlüsselwörter). */
    private const ALLOWED = [
        'read' => ['select', 'with'],
        'write' => ['select', 'with', 'insert', 'update', 'delete'],
        'admin' => ['select', 'with', 'insert', 'update', 'delete', 'create', 'alter', 'drop'],
    ];

    /** Nie erlaubt, egal welche Stufe (Dateisystem und Interna bleiben zu). */
    private const BLOCKED = ['attach', 'detach', 'pragma', 'vacuum', 'reindex', 'copy', 'analyze'];

    public static function templatePath(string $slug): string
    {
        return INFOCRAFT_ROOT . '/sql/templates/' . $slug . '.sql';
    }

    public static function workspacePath(string $slug, int $userId): string
    {
        $dir = INFOCRAFT_ROOT . '/database/sql';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir . '/' . $slug . '_u' . $userId . '.sqlite';
    }

    /**
     * PDO für die Arbeit in der Werkstatt (#45, Redundanz vermeiden):
     * Bei Leserechten reicht die gemeinsame Vorlage im Speicher - es wird
     * KEINE eigene Kopie pro Person angelegt. Nur bei Schreibrechten
     * entsteht die eigene (datensparsame) Kopie je Person.
     */
    public static function workspacePdo(string $slug, int $userId, string $permission = 'write'): PDO
    {
        if ($permission === 'read') {
            return self::pristinePdo($slug);
        }
        $path = self::workspacePath($slug, $userId);
        $fresh = !is_file($path);
        $pdo = self::sqlite($path);
        if ($fresh) {
            self::applyTemplate($pdo, $slug);
        }
        return $pdo;
    }

    /** Frische Kopie im Speicher, um Musterlösungen gegen unveränderte Daten zu vergleichen. */
    public static function pristinePdo(string $slug): PDO
    {
        $pdo = self::sqlite(':memory:');
        self::applyTemplate($pdo, $slug);
        return $pdo;
    }

    /** @return array{name: string, columns: list<array{name: string, type: string, pk: bool}>, fks: list<array{column: string, table: string, ref: string}>} */
    public static function tableMeta(PDO $pdo, string $name): array
    {
        $columns = [];
        foreach ($pdo->query('PRAGMA table_info(' . $name . ')')->fetchAll() as $col) {
            $columns[] = [
                'name' => (string) $col['name'],
                'type' => (string) ($col['type'] !== '' ? $col['type'] : 'TEXT'),
                'pk' => (int) $col['pk'] > 0,
            ];
        }
        $fks = [];
        foreach ($pdo->query('PRAGMA foreign_key_list(' . $name . ')')->fetchAll() as $fk) {
            $fks[] = ['column' => (string) $fk['from'], 'table' => (string) $fk['table'], 'ref' => (string) $fk['to']];
        }
        return ['name' => $name, 'columns' => $columns, 'fks' => $fks];
    }

    /** @return list<array{name: string, columns: list<array{name: string, type: string, pk: bool}>, fks: list<array{column: string, table: string, ref: string}>}> */
    public static function tables(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
        $tables = [];
        foreach ($rows as $row) {
            $tables[] = self::tableMeta($pdo, (string) $row['name']);
        }
        return $tables;
    }

    /** Mermaid-ER-Diagramm aus dem echten Schema erzeugen (Quelltext bleibt exportierbar). */
    public static function erDiagram(PDO $pdo): string
    {
        $tables = self::tables($pdo);
        $lines = ['erDiagram'];
        foreach ($tables as $table) {
            $lines[] = '  ' . strtoupper($table['name']) . ' {';
            foreach ($table['columns'] as $column) {
                $type = str_replace(' ', '-', $column['type']);
                $lines[] = '    ' . $type . ' ' . $column['name'] . ($column['pk'] ? ' PK' : '');
            }
            $lines[] = '  }';
        }
        foreach ($tables as $table) {
            foreach ($table['fks'] as $fk) {
                $lines[] = '  ' . strtoupper($fk['table']) . ' ||--o{ ' . strtoupper($table['name']) . ' : "' . $fk['column'] . '"';
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Eine Anweisung gegen die Rechte prüfen und ausführen.
     * @return array{ok: bool, error: ?string, columns: list<string>, rows: list<array>, affected: int}
     */
    public static function run(string $permission, string $sql, PDO $pdo): array
    {
        $result = ['ok' => false, 'error' => null, 'columns' => [], 'rows' => [], 'affected' => 0];
        $sql = trim($sql);
        if ($sql === '') {
            $result['error'] = 'Bitte eine SQL-Anweisung eingeben.';
            return $result;
        }
        $allowed = self::ALLOWED[$permission] ?? self::ALLOWED['read'];
        if (preg_match('/^\s*([A-Za-z]+)/', $sql, $m) !== 1) {
            $result['error'] = 'Das ist keine erkennbare SQL-Anweisung.';
            return $result;
        }
        $keyword = strtolower($m[1]);
        if (in_array($keyword, self::BLOCKED, true)) {
            $result['error'] = 'Diese Anweisung ist in der Werkstatt nicht erlaubt.';
            return $result;
        }
        if (!in_array($keyword, $allowed, true)) {
            $result['error'] = 'Deine Rechte (' . (self::PERMISSIONS[$permission] ?? $permission) . ') erlauben das nicht. Erlaubt sind hier: ' . strtoupper(implode(', ', $allowed)) . '.';
            return $result;
        }
        if (count(self::statements($sql)) > 1) {
            $result['error'] = 'Bitte nur eine Anweisung gleichzeitig ausführen.';
            return $result;
        }
        try {
            if (in_array($keyword, ['select', 'with'], true)) {
                $stmt = $pdo->query($sql);
                $columns = [];
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $meta = $stmt->getColumnMeta($i);
                    $columns[] = is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : 'Spalte ' . ($i + 1);
                }
                $result['columns'] = $columns;
                $result['rows'] = $stmt->fetchAll();
                $result['ok'] = true;
            } else {
                $result['affected'] = (int) $pdo->exec($sql);
                $result['ok'] = true;
            }
        } catch (PDOException $e) {
            $result['error'] = 'SQLite meldet: ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * Musterlösung prüfen: Ergebnis der Abfrage der Schülerin/des Schülers
     * mit der Lösung gegen die unveränderte Vorlage vergleichen.
     */
    public static function matchesSolution(string $studentSql, PDO $workspace, PDO $pristine, string $solutionSql): bool
    {
        $studentRows = self::fetchRows($workspace, $studentSql);
        $solutionRows = self::fetchRows($pristine, $solutionSql);
        if ($studentRows === null || $solutionRows === null) {
            return false;
        }
        return self::valuesOnly($studentRows) === self::valuesOnly($solutionRows);
    }

    /** Ergebnis einer Kontrollabfrage (erste Spalte der ersten Zeile) als String. */
    public static function checkValue(PDO $pdo, string $sql): ?string
    {
        try {
            $row = $pdo->query($sql)->fetch();
        } catch (PDOException) {
            return null;
        }
        if ($row === false) {
            return null;
        }
        $value = reset($row);
        return $value === false ? null : (string) $value;
    }

    /** @return list<string> Zeilen als reine Werte (Spaltennamen egal, Reihenfolge wichtig). */
    private static function valuesOnly(array $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach (array_values($row) as $value) {
                $parts[] = (string) $value;
            }
            $lines[] = implode('|', $parts);
        }
        return $lines;
    }

    /** @return list<array>|null */
    private static function fetchRows(PDO $pdo, string $sql): ?array
    {
        try {
            $stmt = $pdo->query($sql);
            return $stmt === false ? null : $stmt->fetchAll();
        } catch (PDOException) {
            return null;
        }
    }

    private static function sqlite(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    private static function applyTemplate(PDO $pdo, string $slug): void
    {
        $file = self::templatePath($slug);
        if (!is_file($file)) {
            throw new RuntimeException('Vorlage nicht gefunden: ' . $file);
        }
        $sql = (string) file_get_contents($file);
        foreach (self::statements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== ''));
    }
}
