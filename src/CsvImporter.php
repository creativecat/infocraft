<?php
declare(strict_types=1);

namespace Infocraft;

use InfocraftModelsSchoolClass;
use InfocraftModelsUser;

final class CsvImporter
{
    /** @return array{created: int, updated: int, errors: array<int, string>} */
    public static function import(string $csvText): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $csvText = trim($csvText);
        if (str_starts_with($csvText, "\xEF\xBB\xBF")) {
            $csvText = substr($csvText, 3);
        }
        if ($csvText === '') {
            $result['errors'][] = 'Keine Daten gefunden.';
            return $result;
        }

        $lines = preg_split('/\R/u', $csvText);
        if ($lines === false) {
            $lines = [$csvText];
        }
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            $result['errors'][] = 'Keine Zeilen gefunden.';
            return $result;
        }

        $firstLine = $lines[0];
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        if ($counts[$delimiter] === 0) {
            $delimiter = ';';
        }

        $header = null;
        $firstFields = array_map('strtolower', self::splitLine($firstLine, $delimiter));
        if (in_array('vorname', $firstFields, true)) {
            $header = $firstFields;
            array_shift($lines);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($lines as $index => $line) {
                $lineNo = $index + ($header === null ? 1 : 2);
                $fields = self::splitLine($line, $delimiter);

                $record = ['vorname' => '', 'nachname' => '', 'klasse' => '', 'rolle' => ''];
                if ($header === null) {
                    $record['vorname'] = $fields[0] ?? '';
                    $record['nachname'] = $fields[1] ?? '';
                    $record['klasse'] = $fields[2] ?? '';
                    $record['rolle'] = $fields[3] ?? '';
                } else {
                    foreach ($header as $column => $key) {
                        $record[$key] = $fields[$column] ?? '';
                    }
                }

                $firstName = trim((string) $record['vorname']);
                $lastName = trim((string) $record['nachname']);
                if ($firstName === '' || $lastName === '') {
                    $result['errors'][] = 'Zeile ' . $lineNo . ': Vor- und Nachname sind erforderlich.';
                    continue;
                }

                $role = self::normalizeRole((string) $record['rolle']);
                $classId = null;
                $className = trim((string) $record['klasse']);
                if ($className !== '') {
                    $classId = SchoolClass::findOrCreate($className)->id;
                }

                $existing = User::findByName($firstName, $lastName);
                if ($existing === null) {
                    User::create($firstName, $lastName, $role, $classId, null, null, null);
                    $result['created']++;
                } else {
                    User::updateRoleAndClass($existing->id, $classId, $role);
                    $result['updated']++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $result['errors'][] = 'Import abgebrochen: ' . $e->getMessage();
        }

        return $result;
    }

    /** @return array<int, string> */
    private static function splitLine(string $line, string $delimiter): array
    {
        $fields = explode($delimiter, $line);
        return array_map(static fn (string $field): string => trim(trim($field), "\"'"), $fields);
    }

    private static function normalizeRole(string $role): string
    {
        return match (strtolower(trim($role))) {
            'lk', 'lehrkraft', 'lehrer', 'lehrerin', 'teacher', 'l' => 'teacher',
            'admin', 'verwaltung' => 'admin',
            default => 'student',
        };
    }
}
