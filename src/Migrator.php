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

        // Bestehende Installationen: Spalte classes.diagram_auto idempotent nachziehen.
        self::ensureDiagramAutoColumn($pdo, $driver);

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
            self::seedDemoExams($pdo);
            self::seedDemoDiagrams($pdo);
            self::seedWikiPages($pdo);
            self::seedPresentations($pdo);
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


    /**
     * Demo-Daten fuer den Pruefungsmodus:
     * 1) laufende HTML-Pruefung von Stefan (Jonas, 6R) zeigt die Sperre,
     * 2) herausgegebene SQL-Schularbeit von Claudia (Emma, 9R) zeigt
     *    den Ergebnisvergleich mit Musterloesung.
     */
    private static function seedDemoExams(PDO $pdo): void
    {
        // Nachtrag: Fruehere Seeds legten die Demo-Schularbeit mit Phase
        // durchfuehrung an. Hier idempotent auf herausgegeben korrigieren,
        // damit auch bestehende Installationen nach dem Migrieren stimmen.
        $fix = $pdo->prepare("UPDATE exams SET phase = 'herausgegeben', released_at = ? WHERE kind = 'arbeit' AND phase = 'durchfuehrung' AND title = 'Schularbeit: SQL-Grundlagen'");
        $fix->execute([date('Y-m-d H:i:s')]);
        if ((int) $pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn() > 0) {
            return;
        }
        $idFor = static function (PDO $pdo, string $login): int {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
            $stmt->execute([$login]);
            return (int) $stmt->fetchColumn();
        };
        $stefanId = $idFor($pdo, 'stefan.schmidt');
        $claudiaId = $idFor($pdo, 'claudia.fischer');
        $jonasId = $idFor($pdo, 'jonas.wagner');
        $emmaId = $idFor($pdo, 'emma.krueger');
        $now = date('Y-m-d H:i:s');

        // 1) Laufende HTML-Pruefung (6R, Jonas): Pruefungsmodus mit Sperre.
        $vorlage = "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n  <title>Meine erste Seite</title>\n</head>\n<body>\n  <!-- Hier entsteht deine Pruefungsseite -->\n</body>\n</html>\n";
        $insertProject = $pdo->prepare('INSERT INTO projects (owner_id, folder_id, title, type, language, content) VALUES (?, NULL, ?, ?, ?, ?)');
        $insertProject->execute([$stefanId, 'Vorlage: Meine erste Seite', 'html', 'html', $vorlage]);
        $templateId = (int) $pdo->lastInsertId();

        $insertExam = $pdo->prepare('INSERT INTO exams (teacher_id, kind, title, description, level, phase, started_at) VALUES (?, ?, ?, ?, ?, \'durchfuehrung\', ?)');
        $insertExam->execute([$stefanId, 'pruefung', 'HTML-Prüfung: Meine erste Seite', 'Überschrift, Absatz, Liste und ein Link, alles auf einer eigenen Seite.', 6, $now]);
        $examId = (int) $pdo->lastInsertId();

        $insertGroup = $pdo->prepare('INSERT INTO exam_groups (exam_id, name, template_project_id) VALUES (?, ?, ?)');
        $insertGroup->execute([$examId, 'Gruppe A', $templateId]);
        $groupId = (int) $pdo->lastInsertId();

        $insertMember = $pdo->prepare('INSERT INTO exam_members (group_id, user_id) VALUES (?, ?)');
        $insertMember->execute([$groupId, $jonasId]);

        $insertTask = $pdo->prepare('INSERT INTO exam_tasks (exam_id, position, title, description, be, sample_solution) VALUES (?, ?, ?, ?, ?, ?)');
        $insertTask->execute([$examId, 1, 'Grundgerüst', 'Erstelle eine Seite mit einer großen Überschrift und einem einleitenden Absatz.', 4, '<h1>Willkommen auf meiner Seite</h1><p>Hallo! Das ist meine erste eigene Webseite.</p>']);
        $insertTask->execute([$examId, 2, 'Liste und Link', 'Füge eine Liste mit drei Hobbys hinzu und verlinke eines davon.', 4, '<ul><li>Lesen</li><li>Programmieren</li><li>Turnen</li></ul><p><a href="https://www.wikipedia.de">Mein Lieblingslink</a></p>']);

        $insertProject->execute([$jonasId, 'HTML-Prüfung: Meine erste Seite', 'html', 'html', $vorlage]);
        $workProjectId = (int) $pdo->lastInsertId();
        $insertWork = $pdo->prepare('INSERT INTO exam_works (exam_id, user_id, group_id, project_id, started_at) VALUES (?, ?, ?, ?, ?)');
        $insertWork->execute([$examId, $jonasId, $groupId, $workProjectId, $now]);

        // 2) Herausgegebene SQL-Schularbeit (9R, Emma): Ergebnisvergleich.
        $insertExamB = $pdo->prepare('INSERT INTO exams (teacher_id, kind, title, description, level, phase, started_at, released_at) VALUES (?, ?, ?, ?, ?, \'herausgegeben\', ?, ?)');
        $insertExamB->execute([$claudiaId, 'arbeit', 'Schularbeit: SQL-Grundlagen', 'SELECT, WHERE und ORDER BY am Shop-Modell.', 9, $now, $now]);
        $examBId = (int) $pdo->lastInsertId();

        $insertGroupB = $pdo->prepare('INSERT INTO exam_groups (exam_id, name) VALUES (?, ?)');
        $insertGroupB->execute([$examBId, '9R']);
        $groupBId = (int) $pdo->lastInsertId();
        $insertMember->execute([$groupBId, $emmaId]);

        $insertTaskB = $pdo->prepare('INSERT INTO exam_tasks (exam_id, position, title, description, be, sample_solution) VALUES (?, ?, ?, ?, ?, ?)');
        $insertTaskB->execute([$examBId, 1, 'Alle Produkte', 'Zeige alle Produkte mit Name und Preis, sortiert nach Preis (günstigste zuerst).', 5, 'SELECT name, preis FROM produkte ORDER BY preis']);
        $taskB1 = (int) $pdo->lastInsertId();
        $insertTaskB->execute([$examBId, 2, 'Schnäppchen', 'Finde alle Produkte, die weniger als 15 Euro kosten.', 5, 'SELECT name, preis FROM produkte WHERE preis < 15']);
        $taskB2 = (int) $pdo->lastInsertId();

        $insertWorkB = $pdo->prepare('INSERT INTO exam_works (exam_id, user_id, group_id, project_id, started_at, submitted_at) VALUES (?, ?, ?, NULL, ?, ?)');
        $insertWorkB->execute([$examBId, $emmaId, $groupBId, $now, $now]);

        $insertAnswer = $pdo->prepare('INSERT INTO exam_answers (task_id, user_id, answer) VALUES (?, ?, ?)');
        $insertAnswer->execute([$taskB1, $emmaId, 'SELECT name, preis FROM produkte ORDER BY preis']);
        $insertAnswer->execute([$taskB2, $emmaId, 'SELECT * FROM produkte WHERE preis < 15']);

        $insertGrading = $pdo->prepare('INSERT INTO exam_gradings (task_id, user_id, be_erreicht, comment, source, checked) VALUES (?, ?, ?, ?, ?, ?)');
        $insertGrading->execute([$taskB1, $emmaId, 5.0, 'Sauber: Spalten, Tabelle und Sortierung stimmen.', 'lk', 1]);
        $insertGrading->execute([$taskB2, $emmaId, 4.0, 'WHERE stimmt, aber bitte gezielt name und preis auswählen statt SELECT *.', 'api', 1]);
    }

    /** Bestehende Installationen: Spalte classes.diagram_auto fehlt evtl. noch. */
    private static function ensureDiagramAutoColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM classes LIKE 'diagram_auto'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE classes ADD COLUMN diagram_auto TINYINT(1) NOT NULL DEFAULT 1');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(classes)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'diagram_auto') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE classes ADD COLUMN diagram_auto INTEGER NOT NULL DEFAULT 1');
    }

    private static function seedDemoDiagrams(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM class_diagrams')->fetchColumn() > 0) {
            return;
        }
        $lenaId = $pdo->query("SELECT id FROM users WHERE login_name = 'lena.müller' LIMIT 1")->fetchColumn();
        if ($lenaId === false || $lenaId === null) {
            return;
        }
        $mermaid = "classDiagram\nclass Tier {\n  <<abstract>>\n  +String name\n}\nclass Hund {\n  +gib_laut()\n}\nclass Katze {\n  +gib_laut()\n}\nTier <|-- Hund\nTier <|-- Katze\n";
        $stmt = $pdo->prepare('INSERT INTO class_diagrams (owner_id, title, kind, mermaid) VALUES (?, ?, ?, ?)');
        $stmt->execute([(int) $lenaId, 'Beispiel: Tiere', 'manuell', $mermaid]);
    }

    /**
     * Wiki-Startseiten: Anleitung für Lehrkräfte plus ein paar Beispiel-Fachbegriffe,
     * die den Stufen-Filter zeigen. Idempotent: nur fehlende Titel werden ergänzt.
     */
    private static function seedWikiPages(PDO $pdo): void
    {
        $idFor = static function (PDO $pdo, string $login): int {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
            $stmt->execute([$login]);
            $id = $stmt->fetchColumn();
            return $id === false || $id === null ? 1 : (int) $id;
        };
        $adminId = $idFor($pdo, 'matthias.glienke');

        $pages = [
            ['Anleitung für Lehrkräfte', 6, self::wikiTeacherGuide()],
            ['Algorithmus', 6, "# Algorithmus\n\nEin **Algorithmus** ist eine eindeutige Handlungsvorschrift zur Lösung eines Problems: endlich viele, klar festgelegte Schritte in einer festen Reihenfolge.\n\n## Beispiele\n- Rezept für Pfannkuchen\n- Bauanleitung für ein Regal\n- Ein Programm in Java oder Python\n\n## Merksatz\n\nAlgorithmen sind *eindeutig*, *endlich* und *allgemein* gültig."],
            ['SQL', 9, "# SQL\n\n**SQL** (Structured Query Language) ist die Sprache, um Daten in relationalen Datenbanken zu beschreiben, abzufragen und zu ändern.\n\n## Die wichtigsten Befehle\n- `SELECT` - Daten abfragen\n- `INSERT INTO` - Daten einfügen\n- `UPDATE` - Daten ändern\n- `DELETE FROM` - Daten löschen\n- `CREATE TABLE` - Tabelle anlegen\n\n```sql\nSELECT name, note FROM schueler\nWHERE note >= 2\nORDER BY name;\n```"],
            ['Objektorientierung', 10, "# Objektorientierung\n\nEin **Objekt** fasst zusammen, was eine Sache weiß (Attribute) und was sie kann (Methoden). Die **Klasse** ist der Bauplan für solche Objekte.\n\n## Begriffe\n- **Klasse**: Bauplan (z. B. `Auto`)\n- **Objekt**: konkrete Ausprägung (z. B. Lenas rotes Auto)\n- **Attribut**: Eigenschaft, z. B. `farbe`\n- **Methode**: Fähigkeit, z. B. `fahren()`\n- **Vererbung**: Spezialklassen erben von einer allgemeinen Klasse\n\n```java\nclass Auto {\n    String farbe;\n\n    void fahren() {\n        System.out.println(\"Das \" + farbe + \" Auto faehrt los.\");\n    }\n}\n```"],
        ];

        $exists = $pdo->prepare('SELECT COUNT(*) FROM wiki_pages WHERE title = ?');
        $insert = $pdo->prepare('INSERT INTO wiki_pages (teacher_id, title, content, level, position) VALUES (?, ?, ?, ?, ?)');
        $position = 0;
        foreach ($pages as [$title, $level, $content]) {
            $exists->execute([$title]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
            $position += 1;
            $insert->execute([$adminId, $title, $content, $level, $position]);
        }
    }

    /**
     * Beispiel-Präsentationen für die Jahrgangsstufen 9 und 10, je mit
     * Code-Folien für Live-Demos. Läuft nur, wenn die Tabelle leer ist.
     */
    private static function seedPresentations(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM presentations')->fetchColumn() > 0) {
            return;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['matthias.glienke']);
        $adminId = (int) $stmt->fetchColumn();
        if ($adminId === 0) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO presentations (teacher_id, title, level, content, position) VALUES (?, ?, ?, ?, ?)');

        // Jahrgangsstufe 9: Datenbanken und SQL
        $insert->execute([$adminId, 'Datenbanken: Tabellen, Datensätze, Primärschlüssel', 9, json_encode([
            ['type' => 'text', 'title' => 'Was ist eine Datenbank?', 'body' => "Eine **Datenbank** speichert große Mengen zusammengehöriger Daten dauerhaft und übersichtlich.\n\n## Wichtige Begriffe\n- **Tabelle**: Themensammlung, z. B. alle Schülerinnen und Schüler\n- **Datensatz (Zeile)**: ein Objekt, z. B. eine Schülerin\n- **Attribut/Spalte**: eine Eigenschaft, z. B. der Name\n- **Primärschlüssel**: eindeutiges Merkmal jedes Datensatzes"],
            ['type' => 'text', 'title' => 'Von der Tabelle zum Datenmodell', 'body' => "- **Entity-Relationship-Modell**: Kasten für Entitäten, Raute für Beziehungen\n- **Kardinalitäten**: 1:1, 1:n, n:m\n- Jede Tabelle bekommt einen **Primärschlüssel**\n- Bei 1:n wandert der Primärschlüssel als **Fremdschlüssel** in die n-Seite"],
            ['type' => 'code', 'title' => 'Live-Demo: Eine Tabelle anlegen', 'lang' => 'sql', 'code' => "CREATE TABLE schueler (\n    id INTEGER PRIMARY KEY,\n    name TEXT,\n    klasse TEXT,\n    note REAL\n);\n\nINSERT INTO schueler (id, name, klasse, note)\nVALUES (1, 'Lena', '9R', 1.7);"],
        ], JSON_UNESCAPED_UNICODE), 1]);

        $insert->execute([$adminId, 'SQL: Abfragen mit SELECT, WHERE und ORDER BY', 9, json_encode([
            ['type' => 'text', 'title' => 'Abfragen stellen', 'body' => "Mit **SELECT** holst du Daten aus einer Tabelle. Der Aufbau:\n\n```sql\nSELECT spalten FROM tabelle\nWHERE bedingung\nORDER BY spalte;\n```\n\n- `WHERE` filtert Zeilen\n- `ORDER BY` sortiert das Ergebnis (absteigend mit `DESC`)\n- `LIKE` vergleicht Texte mit `%` als Platzhalter"],
            ['type' => 'code', 'title' => 'Live-Demo: Abfragen in der SQL-Werkstatt', 'lang' => 'sql', 'code' => "-- Alle Schueler der 9R alphabetisch\nSELECT name, note FROM schueler\nWHERE klasse = '9R'\nORDER BY name;\n\n-- Nur Namen mit L und Note besser als 2\nSELECT * FROM schueler\nWHERE name LIKE 'L%' AND note < 2;"],
            ['type' => 'text', 'title' => 'Merkhilfe', 'body' => "**SELECT** *was* **FROM** *woher* **WHERE** *Bedingung* **ORDER BY** *Reihenfolge*.\n\nDie Reihenfolge der Schlüsselwörter ist fest - ein gutes Beispiel für einen *Algorithmus*."],
        ], JSON_UNESCAPED_UNICODE), 2]);

        $insert->execute([$adminId, 'SQL: Daten einfügen, ändern, löschen', 9, json_encode([
            ['type' => 'text', 'title' => 'CRUD: Die vier Grundoperationen', 'body' => "- **C**reate: `INSERT INTO` - neue Datensätze\n- **R**ead: `SELECT` - Datensätze lesen\n- **U**pdate: `UPDATE` - Datensätze ändern\n- **D**elete: `DELETE FROM` - Datensätze löschen"],
            ['type' => 'code', 'title' => 'Live-Demo: Einfügen, Ändern, Löschen', 'lang' => 'sql', 'code' => "INSERT INTO schueler (id, name, klasse, note)\nVALUES (2, 'Jonas', '9R', 2.3);\n\n-- Note verbessern (WHERE nicht vergessen!)\nUPDATE schueler SET note = 1.0\nWHERE id = 2;\n\n-- Datensatz entfernen\nDELETE FROM schueler WHERE id = 2;"],
            ['type' => 'text', 'title' => 'Achtung Falle', 'body' => "Ohne `WHERE` trifft `UPDATE` und `DELETE` **jede Zeile** der Tabelle!\n\nImmer erst mit `SELECT` prüfen, welche Zeilen die Bedingung trifft - dann erst ändern oder löschen."],
        ], JSON_UNESCAPED_UNICODE), 3]);

        // Jahrgangsstufe 10: Java (mit Mini-Datenbank)
        $insert->execute([$adminId, 'Java: Klassen und Objekte', 10, json_encode([
            ['type' => 'text', 'title' => 'Vom Bauplan zum Objekt', 'body' => "Eine **Klasse** ist der Bauplan, ein **Objekt** ist die konkrete Ausprägung.\n\n## Syntax einer Klasse\n- `class Name { ... }`\n- **Attribute** beschreiben die Eigenschaften\n- **Konstruktor** legt die Startwerte fest\n- `new Name(...)` erzeugt ein Objekt"],
            ['type' => 'code', 'title' => 'Live-Demo: Klasse Schueler', 'lang' => 'java', 'code' => 'class Schueler {\n    String name;\n    int alter;\n\n    Schueler(String n, int a) {\n        name = n;\n        alter = a;\n    }\n\n    void vorstellen() {\n        System.out.println(\"Ich heisse \" + name + \" und bin \" + alter + \".\");\n    }\n}\n\nSchueler lena = new Schueler(\"Lena\", 16);\nlena.vorstellen();'],
        ], JSON_UNESCAPED_UNICODE), 4]);

        $insert->execute([$adminId, 'Java: Methoden, Parameter und Rückgaben', 10, json_encode([
            ['type' => 'text', 'title' => 'Methoden strukturieren Programme', 'body' => "Eine **Methode** ist ein benannter Arbeitsschritt.\n\n## Aufbau\n`Rückgabetyp name(typ parameter) { ... return wert; }`\n\n- `void`: gibt nichts zurück\n- `return`: gibt genau einen Wert zurück und beendet die Methode\n- Methoden sparen doppelten Code (DRY: *Don't Repeat Yourself*)"],
            ['type' => 'code', 'title' => 'Live-Demo: Rechner mit Rückgabe', 'lang' => 'java', 'code' => 'int addiere(int a, int b) {\n    return a + b;\n}\n\nString noteText(double punkte) {\n    if (punkte >= 90) {\n        return \"sehr gut\";\n    }\n    return \"weiter ueben\";\n}\n\nSystem.out.println(addiere(7, 8));\nSystem.out.println(noteText(95));'],
        ], JSON_UNESCAPED_UNICODE), 5]);

        $insert->execute([$adminId, 'Java: Vererbung und ArrayList', 10, json_encode([
            ['type' => 'text', 'title' => 'Vererbung: nicht doppelt schreiben', 'body' => "Mit `extends` erbt eine Klasse alle Attribute und Methoden einer anderen.\n\n- **Oberklasse**: allgemein, z. B. `Tier`\n- **Unterklasse**: speziell, z. B. `Hund`\n- `@Override` ersetzt eine geerbte Methode\n- Die **ArrayList** verwaltet beliebig viele Objekte"],
            ['type' => 'code', 'title' => 'Live-Demo: Tiere und Liste', 'lang' => 'java', 'code' => 'class Tier {\n    String name;\n    Tier(String n) { name = n; }\n\n    void gib_laut() {\n        System.out.println(name + \" macht ein Geraeusch.\");\n    }\n}\n\nclass Hund extends Tier {\n    Hund(String n) { super(n); }\n\n    @Override\n    void gib_laut() {\n        System.out.println(name + \" bellt: Wuff!\");\n    }\n}\n\nTier bello = new Hund(\"Bello");\nbello.gib_laut();'],
        ], JSON_UNESCAPED_UNICODE), 6]);

        $insert->execute([$adminId, 'Java trifft Datenbank: die Klasse Datenbank', 10, json_encode([
            ['type' => 'text', 'title' => 'SQL direkt in Java', 'body' => "In der Code-Werkstatt gibt es die eingebaute Klasse **Datenbank**.\n\n## Die wichtigsten Methoden\n- `Datenbank.ausfuehren(sql)` - Tabelle anlegen, Daten einfügen, ändern, löschen\n- `Datenbank.abfrage(sql)` - SELECT als formatierte Tabelle\n- `Datenbank.anzahl(sql)` - Zeilen zählen\n- `Datenbank.wert(sql)` - genau einen Wert holen\n- `Datenbank.tabellen()` - alle Tabellen zeigen\n\nDie Datenbank ist pro Programmlauf neu: kein Server, kein Setup."],
            ['type' => 'code', 'title' => 'Live-Demo: Datenbank in Java ansteuern', 'lang' => 'java', 'code' => 'Datenbank.ausfuehren("CREATE TABLE schueler (id, name, klasse, note)");\n\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (1, \'Lena\', \'10G\', 1.7)");\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (2, \'Jonas\', \'10G\', 2.3)");\n\nSystem.out.println(Datenbank.tabellen());\nSystem.out.println(Datenbank.abfrage("SELECT name, note FROM schueler ORDER BY note"));\n\nint gesamt = Datenbank.anzahl("SELECT * FROM schueler");\nSystem.out.println("Schueler insgesamt: " + gesamt);\n\nString beste = Datenbank.wert("SELECT name FROM schueler ORDER BY note LIMIT 1");\nSystem.out.println("Beste Note hat: " + beste);'],
        ], JSON_UNESCAPED_UNICODE), 7]);
    }

    /** Text der Wiki-Anleitung für Lehrkräfte (Markdown-lite). */
    private static function wikiTeacherGuide(): string
    {
        return "# Anleitung für Lehrkräfte\n\nWillkommen bei **InfoCraft**! Diese Seite fasst die wichtigsten Arbeitsschritte zusammen. Du kannst sie hier bearbeiten und um eigene Hinweise ergänzen.\n\n## Erste Schritte\n\n1. Anmelden über die Startseite.\n2. Unter **Nutzer** Konten für Schülerinnen und Schüler anlegen oder per **CSV-Import** ganze Klassen einspielen.\n3. Unter **Meine Klassen** deinem Konto Klassen zuordnen: Du siehst dann nur deine Klassen und deine Schülerinnen und Schüler.\n\n## Module passend zum Lehrplan freigeben\n\nUnter **Meine Klassen** legst du für jede Klasse fest, welche Arbeitsbereiche sichtbar sind:\n\n- **Standard**: Der LehrplanPLUS-Stufe des Moduls folgt (z. B. SQL erst ab Jgst. 9).\n- **Freigeben**: Die Klasse sieht das Modul früher.\n- **Sperren**: Die Klasse sieht das Modul nicht.\n\nDas gilt auch für die direkte URL: gesperrte Module sind nicht aufrufbar.\n\n## Arbeiten austeilen\n\n- **Code-Werkstatt**: Projekte anlegen und an Klassen oder Einzelne austeilen; über die Schüleransicht erreichst du die Projekte deiner Schülerinnen und Schüler.\n- **SQL-Werkstatt**: Vorlagen (Datenbanken) ausgeben mit Rechten für *nur lesen*, *lesen und schreiben* oder *Tabellen anlegen*.\n- **Aufgabenpool**: eigene Aufgaben anlegen oder fertige Aufgaben (Jgst. 6 bis 13) aus dem Katalog übernehmen und austeilen.\n\n## Präsentieren und Nachschlagen\n\n- **Wiki**: Diese Nachschlage-Seiten hier. Der Stufen-Filter blendet Seiten aus, die über der Klassenstufe liegen.\n- **Präsentationen**: Folien aus Text und Code. Im Präsentationsmodus:\n    - **Vollbild** über den Knopf oder Taste **F**\n    - **Pfeiltasten** oder Leertaste blättern\n    - Code-Folien lassen sich über *Code verändern* direkt während der Präsentation bearbeiten und speichern\n\n## Prüfungen und Schularbeiten\n\nUnter **Prüfungen & Arbeiten** legst du Gruppen mit eigenen Vorlagen an und startest die Prüfung. Während der Prüfung sind für die betroffene Klasse alle anderen Bereiche gesperrt - andere Klassen arbeiten normal weiter. Korrektur mit Punkten und Noten, optional anonymisiert; Schularbeiten ohne Sperre sind ebenfalls möglich.";
    }
}

