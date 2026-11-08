<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Persistenter Zustand eines Werkstatt-Projekts über die Dateien hinaus
 * (Issue #44): der Inhalt der Objektkonsole (Anweisungsfeld) und die
 * Mini-Datenbank (SQL-Text, mit dem sie bei jedem Ausführen neu aufgebaut
 * wird). Eintrag pro Projekt, per Upsert gespeichert.
 */
final class ProjectState
{
    /**
     * @return array{replText: string, dbSql: string}|null
     */
    public static function find(int $projectId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT repl_text, db_sql FROM project_state WHERE project_id = ?');
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'replText' => (string) ($row['repl_text'] ?? ''),
            'dbSql' => (string) ($row['db_sql'] ?? ''),
        ];
    }

    public static function save(int $projectId, string $replText, string $dbSql): void
    {
        $pdo = Database::pdo();
        $update = $pdo->prepare('UPDATE project_state SET repl_text = ?, db_sql = ? WHERE project_id = ?');
        $update->execute([$replText, $dbSql, $projectId]);
        if ($update->rowCount() === 0) {
            $insert = $pdo->prepare('INSERT INTO project_state (project_id, repl_text, db_sql) VALUES (?, ?, ?)');
            try {
                $insert->execute([$projectId, $replText, $dbSql]);
            } catch (\PDOException) {
                // Nebenläufig angelegt: Zweiter Versuch ist das Update oben.
                $pdo->prepare('UPDATE project_state SET repl_text = ?, db_sql = ? WHERE project_id = ?')
                    ->execute([$replText, $dbSql, $projectId]);
            }
        }
    }
}
