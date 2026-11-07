<?php
declare(strict_types=1);

namespace Infocraft;

use PDO;
use RuntimeException;

final class Migrator
{
    /** @return array{statements: int, classes: int, users: int} */
    public static function run(string $driver, bool $withDemoData = true): array
    {
        $pdo = Database::pdo($driver);

        $schemaFile = INFOCRAFT_ROOT . '/sql/schema.' . $driver . '.sql';
        if (!is_file($schemaFile)) {
            throw new RuntimeException('Schema-Datei nicht gefunden: ' . $schemaFile);
        }
        $sql = (string) file_get_contents($schemaFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)), static fn (string $statement): bool => $statement !== '');
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $classes = 0;
        $users = 0;
        if ($withDemoData) {
            $seed = self::seedDemoData($pdo);
            $classes = $seed['classes'];
            $users = $seed['users'];
        }

        return ['statements' => count($statements), 'classes' => $classes, 'users' => $users];
    }

    /** @return array{classes: int, users: int} */
    private static function seedDemoData(PDO $pdo): array
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return ['classes' => 0, 'users' => 0];
        }

        $classIds = [];
        $insertClass = $pdo->prepare('INSERT INTO classes (name) VALUES (?)');
        foreach (['6R', '9R', '10G'] as $name) {
            $insertClass->execute([$name]);
            $classIds[$name] = (int) $pdo->lastInsertId();
        }

        $hash = password_hash('demo1234', PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare('INSERT INTO users (first_name, last_name, login_name, password_hash, role, class_id) VALUES (?, ?, ?, ?, ?, ?)');

        $people = [
            ['Matthias', 'Glienke', 'matthias.glienke', 'admin', null],
            ['Stefan', 'Schmidt', 'stefan.schmidt', 'teacher', null],
            ['Claudia', 'Fischer', 'claudia.fischer', 'teacher', null],
            ['Lena', 'Müller', 'lena.müller', 'student', '6R'],
            ['Jonas', 'Wagner', 'jonas.wagner', 'student', '6R'],
            ['Sofia', 'Brand', 'sofia.brand', 'student', '6R'],
            ['Tim', 'Hoffmann', 'tim.hoffmann', 'student', '6R'],
            ['Amira', 'Öztürk', 'amira.oeztuerk', 'student', '6R'],
            ['Luca', 'Steiner', 'luca.steiner', 'student', '6R'],
            ['Emma', 'Krüger', 'emma.krueger', 'student', '9R'],
            ['Noah', 'Braun', 'noah.braun', 'student', '9R'],
            ['Mira', 'Wolf', 'mira.wolf', 'student', '9R'],
            ['Finn', 'Sauer', 'finn.sauer', 'student', '9R'],
            ['Nele', 'Adler', 'nele.adler', 'student', '9R'],
        ];

        foreach ($people as $person) {
            $insertUser->execute([
                $person[0],
                $person[1],
                $person[2],
                $hash,
                $person[3],
                $person[4] === null ? null : $classIds[$person[4]],
            ]);
        }

        return ['classes' => count($classIds), 'users' => count($people)];
    }
}
