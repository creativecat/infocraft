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
            $seedUsers = self::seedUsers($pdo);
            $classes = $seedUsers['classes'];
            $users = $seedUsers['users'];
            self::seedClassTeachers($pdo);
            self::seedSqlTemplates($pdo);
            self::seedDemoGrants($pdo);
            self::seedDemoProjects($pdo);
            self::seedDemoTasks($pdo);
            self::seedDemoQuizzes($pdo);
        }

        return ['statements' => count($statements), 'classes' => $classes, 'users' => $users];
    }

    /** @return array{classes: int, users: int} */
    private static function seedUsers(PDO $pdo): array
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

    private static function seedClassTeachers(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM class_teachers')->fetchColumn() > 0) {
            return;
        }
        $stmt = $pdo->prepare('INSERT INTO class_teachers (teacher_id, class_id) SELECT u.id, c.id FROM users u JOIN classes c ON c.name = ? WHERE u.login_name = ?');
        $stmt->execute(['6R', 'stefan.schmidt']);
        $stmt->execute(['9R', 'claudia.fischer']);
    }

    private static function seedSqlTemplates(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM sql_templates')->fetchColumn() > 0) {
            return;
        }
        $templates = [
            ['shop', 'Online-Shop', 'Kunden, Produkte und Bestellungen: von einfachen SELECTs bis zu JOINs und GROUP BY.', 9, 0],
            ['instagram', 'Instagram-Modell', 'Profile, Posts, Likes und Follower als Datenmodell nachgebaut.', 9, 0],
            ['adventure', 'Dateninsel-Abenteuer', 'Rette die Insel mit SQL: erzählte Missionen vom SELECT bis zum UPDATE. Für die Aufgaben sind Schreibrechte nötig.', 9, 1],
        ];
        $insert = $pdo->prepare('INSERT INTO sql_templates (slug, title, description, level, is_adventure) VALUES (?, ?, ?, ?, ?)');
        foreach ($templates as $template) {
            $insert->execute([$template[0], $template[1], $template[2], $template[3], $template[4]]);
            $templateId = (int) $pdo->lastInsertId();
            self::insertMissions($pdo, $templateId, $template[0]);
        }
    }

    private static function insertMissions(PDO $pdo, int $templateId, string $slug): void
    {
        $insert = $pdo->prepare('INSERT INTO sql_missions (template_id, position, kind, title, story, hint, solution_sql, check_sql, check_expected) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach (self::missionsFor($slug) as $mission) {
            $insert->execute([
                $templateId,
                $mission['position'],
                $mission['kind'],
                $mission['title'],
                $mission['story'],
                $mission['hint'],
                $mission['solution_sql'],
                $mission['check_sql'] ?? '',
                $mission['check_expected'] ?? null,
            ]);
        }
    }

    /** @return list<array{position: int, kind: string, title: string, story: string, hint: string, solution_sql: string, check_sql?: string, check_expected?: string}> */
    private static function missionsFor(string $slug): array
    {
        if ($slug === 'shop') {
            return [
                ['position' => 1, 'kind' => 'query', 'title' => 'Das Schaufenster', 'story' => 'Zeige alle Produkte mit Name und Preis, sortiert nach Preis (günstigste zuerst).', 'hint' => 'SELECT name, preis FROM produkte ORDER BY preis', 'solution_sql' => 'SELECT name, preis FROM produkte ORDER BY preis'],
                ['position' => 2, 'kind' => 'query', 'title' => 'Schnäppchenjagd', 'story' => 'Finde alle Produkte, die weniger als 15 Euro kosten.', 'hint' => 'Mit WHERE und dem Vergleich < schränkst du die Zeilen ein.', 'solution_sql' => 'SELECT name, preis FROM produkte WHERE preis < 15'],
                ['position' => 3, 'kind' => 'query', 'title' => 'Kundenservice', 'story' => 'Zeige Vor- und Nachname aller Kunden aus München.', 'hint' => "WHERE stadt = 'München'", 'solution_sql' => "SELECT vorname, nachname FROM kunden WHERE stadt = 'München'"],
                ['position' => 4, 'kind' => 'query', 'title' => 'Offene Bestellungen', 'story' => 'Wie viele Bestellungen sind noch offen?', 'hint' => "SELECT COUNT(*) mit WHERE status = 'offen' zählt die passenden Zeilen.", 'solution_sql' => "SELECT COUNT(*) AS offen FROM bestellungen WHERE status = 'offen'"],
                ['position' => 5, 'kind' => 'query', 'title' => 'Bestellübersicht', 'story' => 'Zeige zu jeder Bestellung den Namen des Kunden und das Datum.', 'hint' => 'Verbinde bestellungen mit kunden über den JOIN und die kunde_id.', 'solution_sql' => 'SELECT k.vorname, k.nachname, b.datum FROM bestellungen b JOIN kunden k ON k.id = b.kunde_id'],
                ['position' => 6, 'kind' => 'query', 'title' => 'Der Umsatz', 'story' => 'Berechne den Gesamtwert aller bestellten Produkte (Menge mal Einzelpreis).', 'hint' => 'JOIN bestellpositionen mit produkte und summiere menge * preis.', 'solution_sql' => 'SELECT SUM(bp.menge * p.preis) AS umsatz FROM bestellpositionen bp JOIN produkte p ON p.id = bp.produkt_id'],
            ];
        }
        if ($slug === 'instagram') {
            return [
                ['position' => 1, 'kind' => 'query', 'title' => 'Die Profile', 'story' => 'Zeige alle Benutzernamen mit dem echten Namen dahinter.', 'hint' => 'SELECT benutzername, name FROM nutzer', 'solution_sql' => 'SELECT benutzername, name FROM nutzer'],
                ['position' => 2, 'kind' => 'query', 'title' => 'Aktivität', 'story' => 'Wie viele Posts gibt es insgesamt?', 'hint' => 'SELECT COUNT(*) zählt alle Zeilen der Tabelle posts.', 'solution_sql' => 'SELECT COUNT(*) AS posts FROM posts'],
                ['position' => 3, 'kind' => 'query', 'title' => 'Beliebtester Post', 'story' => 'Wie viele Likes hat der Post mit der ID 3?', 'hint' => 'SELECT COUNT(*) FROM likes WHERE post_id = 3', 'solution_sql' => 'SELECT COUNT(*) AS likes FROM likes WHERE post_id = 3'],
                ['position' => 4, 'kind' => 'query', 'title' => 'Kommentare lesen', 'story' => 'Zeige zu jedem Kommentar den Benutzernamen und den Text.', 'hint' => 'JOIN kommentare mit nutzer über die nutzer_id.', 'solution_sql' => 'SELECT n.benutzername, k.text FROM kommentare k JOIN nutzer n ON n.id = k.nutzer_id'],
                ['position' => 5, 'kind' => 'query', 'title' => 'Fanzahl', 'story' => "Wie viele Nutzer folgen dem Profil 'luna'?", 'hint' => 'Eine Unterabfrage findet zuerst die id von luna.', 'solution_sql' => "SELECT COUNT(*) AS follower FROM folgt WHERE folgt_id = (SELECT id FROM nutzer WHERE benutzername = 'luna')"],
            ];
        }
        // adventure (Dateninsel)
        return [
            ['position' => 1, 'kind' => 'query', 'title' => 'Ankunft im Hafen', 'story' => 'Der Sturm hat die Dateninsel verwüstet. Der Lotse sagt: Sieh dich erst einmal um. Zeige alle Orte der Insel.', 'hint' => 'SELECT * FROM orte zeigt alle Spalten und Zeilen.', 'solution_sql' => 'SELECT * FROM orte'],
            ['position' => 2, 'kind' => 'query', 'title' => 'Die Bewohner', 'story' => 'Zeige Name und Beruf aller Bewohner.', 'hint' => 'SELECT name, beruf FROM bewohner', 'solution_sql' => 'SELECT name, beruf FROM bewohner'],
            ['position' => 3, 'kind' => 'query', 'title' => 'Wo wohnen sie?', 'story' => 'Zeige die Namen der Bewohner zusammen mit ihren Orten.', 'hint' => 'JOIN bewohner mit orte über die ort_id.', 'solution_sql' => 'SELECT b.name, o.name FROM bewohner b JOIN orte o ON o.id = b.ort_id'],
            ['position' => 4, 'kind' => 'query', 'title' => 'Die Verzauberten', 'story' => 'Manche Bewohner sind vom Sturm verzaubert worden. Finde sie.', 'hint' => "WHERE status = 'verzaubert'", 'solution_sql' => "SELECT name FROM bewohner WHERE status = 'verzaubert'"],
            ['position' => 5, 'kind' => 'action', 'title' => 'Die Befreiung', 'story' => 'Befreie alle verzauberten Bewohner mit einer einzigen Anweisung.', 'hint' => "UPDATE bewohner SET status = 'frei' WHERE status = 'verzaubert'", 'solution_sql' => "UPDATE bewohner SET status = 'frei' WHERE status = 'verzaubert'", 'check_sql' => "SELECT COUNT(*) FROM bewohner WHERE status = 'verzaubert'", 'check_expected' => '0'],
            ['position' => 6, 'kind' => 'query', 'title' => 'Der Goldschatz', 'story' => 'Wie viel Gold ist auf der Insel verteilt? Summiere den Wert aller Gegenstände.', 'hint' => 'SELECT SUM(wert) FROM gegenstaende', 'solution_sql' => 'SELECT SUM(wert) AS gold FROM gegenstaende'],
            ['position' => 7, 'kind' => 'query', 'title' => 'Der beste Schatz', 'story' => 'Zeige den wertvollsten Gegenstand der Insel (Name und Wert).', 'hint' => 'ORDER BY wert DESC LIMIT 1 zeigt nur die erste Zeile.', 'solution_sql' => 'SELECT name, wert FROM gegenstaende ORDER BY wert DESC LIMIT 1'],
            ['position' => 8, 'kind' => 'action', 'title' => 'Das Silberne Schwert', 'story' => 'Übergib das Silberne Schwert dem Ritter Roland. Setze dazu die besitzer_id.', 'hint' => 'UPDATE gegenstaende SET besitzer_id = ... WHERE name = Silbernes Schwert. Die id von Ritter Roland findest du mit einem SELECT heraus.', 'solution_sql' => "UPDATE gegenstaende SET besitzer_id = (SELECT id FROM bewohner WHERE name = 'Ritter Roland') WHERE name = 'Silbernes Schwert'", 'check_sql' => "SELECT COUNT(*) FROM gegenstaende WHERE name = 'Silbernes Schwert' AND besitzer_id IS NOT NULL", 'check_expected' => '1'],
        ];
    }

    private static function seedDemoGrants(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM sql_grants')->fetchColumn() > 0) {
            return;
        }
        $templateIds = [];
        foreach ($pdo->query('SELECT id, slug FROM sql_templates')->fetchAll() as $row) {
            $templateIds[(string) $row['slug']] = (int) $row['id'];
        }
        if ($templateIds === []) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['claudia.fischer']);
        $claudiaId = (int) $stmt->fetchColumn();

        // Lehrkraft darf alle Vorlagen mit Vollzugriff ausprobieren
        $grant = $pdo->prepare('INSERT INTO sql_grants (template_id, user_id, permission) VALUES (?, ?, ?)');
        foreach ($templateIds as $templateId) {
            $grant->execute([$templateId, $claudiaId, 'admin']);
        }

        // 9R arbeitet am Shop (schreiben), Instagram (lesen) und am Abenteuer (schreiben)
        $students = $pdo->query("SELECT u.id FROM users u JOIN classes c ON c.id = u.class_id WHERE c.name = '9R' AND u.role = 'student'")->fetchAll();
        foreach ($students as $row) {
            $studentId = (int) $row['id'];
            $grant->execute([$templateIds['shop'], $studentId, 'write']);
            $grant->execute([$templateIds['instagram'], $studentId, 'read']);
            $grant->execute([$templateIds['adventure'], $studentId, 'write']);
        }
    }

    private static function seedDemoProjects(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() > 0) {
            return;
        }
        $idFor = static function (PDO $pdo, string $login): int {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
            $stmt->execute([$login]);
            return (int) $stmt->fetchColumn();
        };
        $adminId = $idFor($pdo, 'matthias.glienke');
        $lenaId = $idFor($pdo, 'lena.müller');

        $insertFolder = $pdo->prepare('INSERT INTO folders (owner_id, name, position) VALUES (?, ?, ?)');
        $insertFolder->execute([$adminId, 'Beispiele', 1]);
        $beispiele = (int) $pdo->lastInsertId();
        $insertFolder->execute([$adminId, 'Ideen', 2]);
        $ideen = (int) $pdo->lastInsertId();

        $insertProject = $pdo->prepare('INSERT INTO projects (owner_id, folder_id, title, type, language, content) VALUES (?, ?, ?, ?, ?, ?)');
        $insertProject->execute([$adminId, $beispiele, 'Begrüßung mit Python', 'code', 'python', "# Erstes Programm\nprint(\"Hallo InfoCraft!\")\nprint(\"Willkommen in der Werkstatt.\")\n"]);
        $insertProject->execute([$adminId, $beispiele, 'Meine erste Seite', 'html', 'html', "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <title>Meine Seite</title>\n</head>\n<body>\n  <h1>Hallo Welt!</h1>\n</body>\n</html>\n"]);
        $insertProject->execute([$adminId, $ideen, 'Ideen sammeln', 'code', 'python', "# Notizen fuer neue Projekte\n# ...\n"]);
        $insertProject->execute([$lenaId, null, 'Lenas Webseite', 'html', 'html', "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <title>Lenas Seite</title>\n</head>\n<body>\n  <h1>Meine erste eigene Seite</h1>\n</body>\n</html>\n"]);
    }

    private static function seedDemoTasks(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn() > 0) {
            return;
        }
        $idFor = static function (PDO $pdo, string $login): int {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
            $stmt->execute([$login]);
            return (int) $stmt->fetchColumn();
        };
        $classIdFor = static function (PDO $pdo, string $name): int {
            $stmt = $pdo->prepare('SELECT id FROM classes WHERE name = ?');
            $stmt->execute([$name]);
            return (int) $stmt->fetchColumn();
        };
        $stefanId = $idFor($pdo, 'stefan.schmidt');
        $claudiaId = $idFor($pdo, 'claudia.fischer');
        $class6R = $classIdFor($pdo, '6R');
        $class9R = $classIdFor($pdo, '9R');

        $insertTask = $pdo->prepare('INSERT INTO tasks (teacher_id, title, description, type, level) VALUES (?, ?, ?, ?, ?)');
        $insertTarget = $pdo->prepare('INSERT INTO task_targets (task_id, class_id, user_id) VALUES (?, ?, NULL)');

        $insertTask->execute([$stefanId, 'HTML-Grundgerüst entwerfen', 'Erstelle eine eigene Seite mit Überschrift, Absatz und Liste. Nutze die Code-Werkstatt.', 'html', 6]);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class6R]);
        $insertTask->execute([$stefanId, 'Python-Schleifen üben', 'Schreibe ein Programm, das die Zahlen von 1 bis 10 untereinander ausgibt.', 'code', 6]);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class6R]);
        $insertTask->execute([$claudiaId, 'SQL-Einstieg: SELECT üben', 'Öffne die verteilte Shop-Datenbank und löse die ersten zwei Missionen.', 'sql', 9]);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class9R]);
    }

    private static function seedDemoQuizzes(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM quizzes')->fetchColumn() > 0) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['claudia.fischer']);
        $claudiaId = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT id FROM classes WHERE name = ?');
        $stmt->execute(['9R']);
        $class9R = (int) $stmt->fetchColumn();

        $insertQuiz = $pdo->prepare('INSERT INTO quizzes (teacher_id, title, description, level) VALUES (?, ?, ?, ?)');
        $insertQuiz->execute([$claudiaId, 'SQL-Begriffe', 'Kurzes Warm-up vor den Missionen in der SQL-Werkstatt.', 9]);
        $quizId = (int) $pdo->lastInsertId();

        $insertQuestion = $pdo->prepare('INSERT INTO quiz_questions (quiz_id, position, question, answer_1, answer_2, answer_3, answer_4, correct) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $questions = [
            ['Mit welchem Befehl liest man Zeilen aus einer Tabelle?', 'SELECT', 'UPDATE', 'INSERT', 'DROP', 1],
            ['Was macht ein JOIN?', 'Er loescht Zeilen', 'Er verbindet zwei Tabellen ueber eine Spalte', 'Er sortiert Zeilen', 'Er zaehlt Zeilen', 2],
            ['Welche Funktion zaehlt Zeilen?', 'COUNT', 'SUM', 'AVG', 'MAX', 1],
            ['Wie heisst eine Spalte, die jede Zeile eindeutig macht?', 'Primaerschluessel', 'Fremdschluessel', 'Index', 'View', 1],
        ];
        foreach ($questions as $index => $question) {
            $insertQuestion->execute([$quizId, $index + 1, $question[0], $question[1], $question[2], $question[3], $question[4], $question[5]]);
        }

        $insertTarget = $pdo->prepare('INSERT INTO quiz_targets (quiz_id, class_id, user_id) VALUES (?, ?, NULL)');
        $insertTarget->execute([$quizId, $class9R]);
    }
}

