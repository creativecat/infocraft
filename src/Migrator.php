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

        // Bestehende Installationen: Spalte presentations.slug idempotent nachziehen.
        self::ensurePresentationSlugColumn($pdo, $driver);

        // Bestehende Installationen: Spalte presentations.seed_version nachziehen,
        // damit aktualisierte Seed-Präsentationen sicher erkannt werden.
        self::ensurePresentationSeedVersionColumn($pdo, $driver);

        // Bestehende Installationen: Aufgaben-Pool (Teilen, Musterlösung) und
        // Abgabetermine idempotent nachziehen.
        self::ensureTaskSharedAndSolutionColumns($pdo, $driver);
        self::ensureTaskTargetDueAtColumn($pdo, $driver);
        self::ensureTaskTimingAndGradingColumns($pdo, $driver);

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

        $insertTask = $pdo->prepare('INSERT INTO tasks (teacher_id, title, description, type, level, shared, sample_solution, rubric) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
        $insertTarget = $pdo->prepare('INSERT INTO task_targets (task_id, class_id, user_id, due_at) VALUES (?, ?, NULL, ?)');

        $insertTask->execute([$stefanId, 'HTML-Grundgerüst entwerfen', 'Erstelle eine eigene Seite mit Überschrift, Absatz und Liste. Nutze die Code-Werkstatt.', 'html', 6, '', '']);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class6R, '2030-12-31 14:00']);
        $insertTask->execute([$stefanId, 'Python-Schleifen üben', 'Schreibe ein Programm, das die Zahlen von 1 bis 10 untereinander ausgibt.', 'code', 6, "for i in range(1, 11):\n    print(i)", '']);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class6R, null]);
        // Von Claudia für alle Lehrkräfte geteilte Aufgabe (gemeinsamer Pool)
        $insertSharedTask = $pdo->prepare('INSERT INTO tasks (teacher_id, title, description, type, level, shared, sample_solution, rubric) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
        $insertSharedTask->execute([$claudiaId, 'ER-Modell Schulbibliothek (geteilt)', 'Modelliere eine kleine Bibliothek: Buch, Ausleihe und Mitglied mit Beziehungen und Kardinalitäten. Zeichne das ER-Modell in dein Heft.', 'sql', 10, 'Buch (1) – (n) Ausleihe (n) – (1) Mitglied', '']);
        $insertTask->execute([$claudiaId, 'SQL-Einstieg: SELECT üben', 'Öffne die verteilte Shop-Datenbank und löse die ersten zwei Missionen.', 'sql', 9, '', '']);
        $insertTarget->execute([(int) $pdo->lastInsertId(), $class9R, null]);
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
            ['Präsentationen: Themenübersicht', 6, self::wikiPresentationIndex()],
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
     * Bestehenden Installationen die Slug-Spalte idempotent nachziehen
     * (permanente Themen-Links /praesentationen/thema/{slug}).
     */
    private static function ensurePresentationSlugColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM presentations LIKE 'slug'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE presentations ADD COLUMN slug VARCHAR(60) NULL');
                $pdo->exec('CREATE UNIQUE INDEX idx_pres_slug ON presentations (slug)');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(presentations)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'slug') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE presentations ADD COLUMN slug TEXT');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_pres_slug ON presentations (slug)');
    }

    /**
     * Bestehenden Installationen die Seed-Version-Spalte idempotent nachziehen:
     * seed_version merkt, welcher Folienstand eines Startpakets gespeichert ist.
     * Nur damit lassen sich aktualisierte Seed-Präsentationen sicher erkennen,
     * ohne von Lehrkräften bearbeitete Kopien anzufassen.
     */
    private static function ensurePresentationSeedVersionColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM presentations LIKE 'seed_version'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE presentations ADD COLUMN seed_version INT NOT NULL DEFAULT 0');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(presentations)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'seed_version') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE presentations ADD COLUMN seed_version INTEGER NOT NULL DEFAULT 0');
    }

    /** Aufgaben: shared (gemeinsamer Pool) und sample_solution (Musterlösung) nachziehen. */
    private static function ensureTaskSharedAndSolutionColumns(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'shared'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE tasks ADD COLUMN shared TINYINT(1) NOT NULL DEFAULT 0');
            }
            $exists = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'sample_solution'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec("ALTER TABLE tasks ADD COLUMN sample_solution TEXT NOT NULL");
                $pdo->exec("UPDATE tasks SET sample_solution = ''");
            }
            return;
        }
        $columns = [];
        foreach ($pdo->query('PRAGMA table_info(tasks)')->fetchAll() as $column) {
            $columns[] = (string) ($column['name'] ?? '');
        }
        if (!in_array('shared', $columns, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN shared INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('sample_solution', $columns, true)) {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN sample_solution TEXT NOT NULL DEFAULT ''");
        }
    }

    /** Abgabetermin an Aufgaben-Zuweisungen nachziehen. */
    private static function ensureTaskTargetDueAtColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM task_targets LIKE 'due_at'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE task_targets ADD COLUMN due_at VARCHAR(16) NULL');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(task_targets)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'due_at') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE task_targets ADD COLUMN due_at TEXT NULL');
    }

    /**
     * Aufgabenverwaltung 2: Starttermin an Zuweisungen (visible_from) sowie
     * Punktzahl, Erwartungshorizont und Frist-Sperre an Aufgaben nachziehen.
     */
    private static function ensureTaskTimingAndGradingColumns(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            foreach ([
                ['tasks', 'max_points', 'ALTER TABLE tasks ADD COLUMN max_points INT UNSIGNED NOT NULL DEFAULT 0'],
                ['tasks', 'rubric', "ALTER TABLE tasks ADD COLUMN rubric TEXT NOT NULL"],
                ['tasks', 'late_lock', 'ALTER TABLE tasks ADD COLUMN late_lock TINYINT(1) NOT NULL DEFAULT 0'],
                ['task_targets', 'visible_from', 'ALTER TABLE task_targets ADD COLUMN visible_from VARCHAR(16) NULL'],
            ] as [$table, $column, $statement]) {
                $exists = $pdo->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $pdo->quote($column))->fetchColumn();
                if ($exists === false || $exists === null) {
                    $pdo->exec($statement);
                    if ($column === 'rubric') {
                        $pdo->exec("UPDATE tasks SET rubric = ''");
                    }
                }
            }
            return;
        }
        $taskColumns = [];
        foreach ($pdo->query('PRAGMA table_info(tasks)')->fetchAll() as $column) {
            $taskColumns[] = (string) ($column['name'] ?? '');
        }
        if (!in_array('max_points', $taskColumns, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN max_points INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('rubric', $taskColumns, true)) {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN rubric TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('late_lock', $taskColumns, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN late_lock INTEGER NOT NULL DEFAULT 0');
        }
        foreach ($pdo->query('PRAGMA table_info(task_targets)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'visible_from') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE task_targets ADD COLUMN visible_from TEXT NULL');
    }

    /**
     * Alle Seed-Präsentationen, abgestimmt auf den LehrplanPLUS: Jahrgangsstufe 6
     * (Informationsdarstellung, gut präsentieren), Jahrgangsstufe 7 (Hypertext
     * und Internet, digitale Kommunikation, Algorithmen), Jahrgangsstufe 9
     * (Basiskurs: Einstiegsdeck Informatik-Jahr, funktionale Modellierung,
     * Tabellenkalkulation, Datenmodellierung, SQL, Java-OO, Datenschutz) und
     * Jahrgangsstufe 10 (NTG: Einstiegsdeck Informatik-Jahr, Java-Wiederholung,
     * verknüpfte Tabellen, Arrays/Referenzen, Datenkapselung, Polymorphismus,
     * Projekt). Die Einstiegsdecks (Informatik-Jahr) motivieren ohne Übung,
     * jede Themenpräsentation endet mit einer Übungsaufgabe und Musterlösung.
     * 'legacy' listet frühere Seed-Titel, die beim Nachziehen aktualisiert werden.
     * 'v' ist die Seed-Version: Nur wenn die gespeicherte Version kleiner ist,
     * werden Folien des Admin-Startpakets aktualisiert (bearbeitete Kopien
     * anderer Lehrkräfte bleiben unberührt).
     * @return list<array{slug: string, title: string, level: int, v: int, legacy: list<string>, slides: list<array<string, string>>}>
     */
    private static function presentationSeeds(): array
    {
        return [
            ['slug' => 'informationen-darstellen', 'title' => 'Informationen darstellen: Text, Bild, Tabelle', 'level' => 6, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Was ist eine Information?', 'body' => "# Informationen darstellen\n\nEine **Information** ist eine Antwort auf eine Frage: Wie viele Kinder lernen in dieser Klasse? Wann fährt der Bus?\n\nInformationen gibt es in **verschiedenen Formen**:\n\n- gesprochen (Mündliches)\n- als **Text** (Zeichen und Wörter)\n- als **Bild** (Fotos, Zeichnungen, Piktogramme)\n- als **Tabelle** (Daten übersichtlich in Zeilen und Spalten)\n- als **Diagramm** (Zahlen als Säulen oder Kreise)\n\nKeine Form ist besser oder schlechter: Es kommt darauf an, **wer** die Information **wofür** braucht."],
                ['type' => 'text', 'title' => 'Gleiche Information, andere Darstellung', 'body' => "## Ein Beispiel\n\nDie Klasse 6R lernt für eine Arbeit: **18 Kinder**, verteilt auf drei Lerngruppen (6 lernen allein, 8 zu zweit, 4 in der Dreiergruppe).\n\n- Als **Text**: Ein Satz, gut zum Vorlesen.\n- Als **Tabelle**: übersichtlich zum Nachschlagen.\n- Als **Säulendiagramm**: Zahlen auf einen Blick vergleichbar.\n\n## Faustregel\n\n- Zum **Erzählen** passt Text.\n- Zum **Vergleichen** von Zahlen passt ein Diagramm.\n- Zum **Nachschlagen** vieler Einzelwerte passt eine Tabelle.\n\nDer Computer hilft beim Umwandeln: Aus derselben Tabelle entsteht jedes Diagramm mit einem Klick."],
                ['type' => 'text', 'title' => 'Übung: Klassenfotos und Lieblingsfächer', 'body' => "**Übung: Informationen passend darstellen**\n\nEure Klasse hat eine Umfrage gemacht: **Lieblingsfach** jedes Kindes und **Anzahl der Geschwister**.\n\n1. Entscheide für jede Angabe: **Text, Tabelle oder Diagramm**? Begründe.\n2. Zeichne in dein Heft eine **Tabelle** für 5 Mitschülerinnen und Mitschüler mit Name, Lieblingsfach und Geschwistern.\n3. Zeichne ein **Säulendiagramm** zur Frage: Wie viele Kinder haben 0, 1, 2 oder 3 Geschwister?\n\nTipp für die Präsentation: Denkt daran, was ihr in der nächsten Stunde über gutes Präsentieren lernt."],
                ['type' => 'text', 'title' => 'Lösung: Musterlösung', 'body' => "**Musterlösung**\n\n1. **Lieblingsfach** jedes Kindes: Tabelle (viele Einzelwerte zum Nachschlagen). **Wie viele Kinder pro Fach**: Diagramm (Vergleichen auf einen Blick). Ein **Einleitungssatz** in Text erklärt, worum es geht.\n2. Tabelle mit den Spalten *Name, Lieblingsfach, Geschwister* - pro Kind eine Zeile.\n3. Säulendiagramm: vier Säulen (0, 1, 2, 3 Geschwister), Höhe = Anzahl der Kinder. Nicht vergessen: **Überschrift** und **Achsenbeschriftung**.\n\nMerksatz: Erst überlegen, **wer** die Information liest und **wofür** - dann die Form wählen."],
            ]],
            ['slug' => 'gut-praesentieren', 'title' => 'Gut präsentieren: Vorbereiten, Folien, Freisprechen', 'level' => 6, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Was macht eine gute Präsentation?', 'body' => "# Gut präsentieren\n\nEine Präsentation ist mehr als Folien ablesen. Drei Bausteine gehören zusammen:\n\n1. **Vorbereitung**: Ziel klären (Was sollen die anderen am Ende wissen?), Stichworte sammeln.\n2. **Folien**: Unterstützung, nicht Ersatz fürs Sprechen - wenige Stichworte, große Schrift, ein Bild pro Folie.\n3. **Freisprechen**: Mit Stichworten erzählen statt ablesen, Blick zum Publikum, deutlich sprechen.\n\n## Der rote Faden\n\nAnfang (**Warum** ist das Thema wichtig?) - Mitte (**Was** gibt es zu wissen?) - Ende (**Zusammenfassung** und eine Frage ans Publikum).\n\nGenau nach diesem Muster sind die Präsentationen hier in InfoCraft aufgebaut."],
                ['type' => 'text', 'title' => 'Die fünf Folien-Regeln', 'body' => "## Folien-Regeln für die ganze Schule\n\n- **Faustregel 6x6**: höchstens 6 Zeilen pro Folie, 6 Wörter pro Zeile.\n- **Eine** Kernaussage pro Folie, die Überschrift sagt sie.\n- **Große Schrift**: Auch die letzte Reihe muss alles lesen können.\n- **Bild statt Text**, wo es geht - ein Bild sagt mehr als zehn Zeilen.\n- **Rechtschreibung** prüfen: Fehler lenken ab und kosten Vertrauen.\n\n## Beim Vortrag\n\n- Nicht vor der Leinwand stehen.\n- Zum Publikum sprechen, nicht zum Bildschirm.\n- Am Ende fragen: *Was möchtet ihr noch wissen?*\n\nGenau wie in der echten Vortragswelt gilt: Übung macht den Meister - jede Präsentation macht die nächste besser."],
                ['type' => 'text', 'title' => 'Übung: Zwei Minuten über dein Hobby', 'body' => "**Übung: Die Zwei-Minuten-Präsentation**\n\n1. Wähle ein **Hobby** und notiere drei Stichworte: Warum macht es Spaß? Was braucht man dafür? Was ist dein größtes Erlebnis gewesen?\n2. Entwirf **drei Folien** auf Papier: Titelfolie, eine Folie mit maximal 6 Stichworten, eine Folie mit einem gezeichneten Bild.\n3. Halte den Vortrag **zwei Minuten** vor deiner Gruppe - ohne abzulesen.\n\nDie Gruppe beobachtet mit dem **Präsentations-Check**: roter Faden? Blickkontakt? Folien-Regeln eingehalten?"],
                ['type' => 'text', 'title' => 'Lösung: Der Präsentations-Check', 'body' => "**Musterlösung: Präsentations-Check**\n\n- **Roter Faden**: Anfang-Mitte-Ende erkennbar? (Titel nennt das Thema, Mitte erklärt, Ende fasst zusammen.)\n- **Folien**: 6x6 eingehalten? Überschrift macht die Kernaussage? Bild statt Textlisten?\n- **Vortrag**: frei gesprochen? Blick zum Publikum? deutlich und in Ruhe?\n- **Schluss**: eine Frage oder ein Dank an die Zuhörerinnen und Zuhörer?\n\nFeedback-Regel für die Gruppe: Erst **zwei Dinge, die gut waren**, dann **eine Sache zum Verbessern**. So bleibt Präsentieren motivierend."],
            ]],
            ['slug' => 'hypertext-internet', 'title' => 'Hypertext und Internet: Wie Webseiten verbunden sind', 'level' => 7, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Was ist Hypertext?', 'body' => "# Hypertext und Internet\n\nEin **Hypertext** ist ein Text, der über **Verweise (Links)** mit anderen Texten verbunden ist. Statt alles hintereinander zu lesen, klickst du dich durch - wie in Wikipedia.\n\n## Hypertext im Alltag\n\n- **Wikipedia**: jeder Begriff verlinkt auf Artikel mit mehr Details.\n- **Online-Shops**: Produkt - Bewertungen - Lieferung - alles verlinkt.\n- **Schulseite**: Startseite führt zu Stundenplan, News, Kontakt.\n\n## Warum Hypertext?\n\nPapier ist **linear** (Seite für Seite), Hypertext ist **verzweigt**: Jede Leserin entscheidet selbst, welchen Weg sie geht. Dafür muss die Seite **gut verlinkt** sein - sonst verliert man sich."],
                ['type' => 'text', 'title' => 'Wie kommt eine Seite in den Browser?', 'body' => "## Der Weg einer Webseite\n\n1. Du tippst eine **Adresse (URL)** in den Browser, z. B. `https://www.wikipedia.de`.\n2. Der Browser fragt beim **Server** des Anbieters an - einem Computer, der ständig läuft.\n3. Der Server schickt die **Dateien** zurück: HTML (Inhalt), CSS (Aussehen), Bilder.\n4. Der Browser **baut** daraus die sichtbare Seite.\n\n## Internet = Netz der Netze\n\nMilliarden Computer sind über Kabel und Funk verbunden. Router und Vermittlungsrechner geben die Anfrage weiter, bis sie beim Server ankommt - meist in Sekundenbruchteilen.\n\n**HTML** ist die Sprache der Webseiten: Sie sagt dem Browser, was Überschrift, Absatz, Bild oder Link ist."],
                ['type' => 'code', 'title' => 'Live-Demo: Ein Link in HTML', 'lang' => 'html', 'code' => '<h1>Meine Tierseite</h1>\n\n<p>Hier erfährst du alles ueber <b>Pandas</b>.</p>\n\n<ul>\n  <li><a href="https://www.wikipedia.de">Mehr auf Wikipedia</a></li>\n  <li><a href="kontakt.html">Schreib uns</a></li>\n</ul>'],
                ['type' => 'text', 'title' => 'Übung: Eine verlinkte Seite bauen', 'body' => "**Übung: Drei Seiten, ein Hypertext**\n\n1. Öffne die **HTML-Werkstatt** und lege drei Seiten an: `index.html`, `hobby.html` und `kontakt.html`.\n2. Verlinke auf der Startseite beide anderen Seiten mit `<a href=...>`.\n3. Baue auf jeder Seite einen **Zurück-Link** zur Startseite ein.\n4. Prüfe: Kommt man von jeder Seite zu jeder anderen Seite? Das ist der **rote Faden** durch den Hypertext.\n\nBonus: Ergänze einen Link auf eine echte externe Seite (z. B. Wikipedia) und prüfe die Adresse genau."],
                ['type' => 'text', 'title' => 'Lösung: Verlinkung prüfen', 'body' => "**Musterlösung**\n\n```html\n<h1>Meine Seite</h1>\n<p><a href=\"hobby.html\">Mein Hobby</a></p>\n<p><a href=\"kontakt.html\">Kontakt</a></p>\n<p><a href=\"index.html\">Zur Startseite</a></p>\n```\n\n- **Verzweigt statt linear**: Von der Startseite gehen zwei Wege ab, jede Unterseite führt zurück.\n- **Keine Sackgasse**: Von jeder Seite erreicht man jede andere - das Prüfkriterium für gute Hypertexte.\n- Externe Links brauchen die **vollständige Adresse** mit `https://`, interne Links nur den **Dateinamen**."],
            ]],
            ['slug' => 'digitale-kommunikation', 'title' => 'Digitale Kommunikation: Klug schreiben, wertschätzend bleiben', 'level' => 7, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Schreiben im Netz', 'body' => "# Digitale Kommunikation\n\nIm Netz fehlt, was im Gespräch hilft: **Tonfall, Mimik, Gestik**. Ein Satz wie *\"Das war wohl nicht so gut.\"* kann witzig oder verletzend gemeint sein - am Bildschirm entscheidet die Leserin.\n\n## Wer schreibt, bleibt lesbar\n\nDigitale Nachrichten **verschwinden nicht**: Screenshots, Weiterleitungen und Sicherungen machen alles dauerhaft. Deshalb gilt: Schreibe nichts, was du der Person nicht auch in die Augen sagen würdest.\n\n## Regeln für Klassenchats\n\n- Zuerst lesen, dann senden - und prüfen, ob die Nachricht so ankommt, wie sie gemeint ist.\n- **Themen**- und **Fachchats** nur für ihr Thema nutzen.\n- Keine Nachrichten nach 22 Uhr - auch **Lehrkräfte** und Eltern schlafen."],
                ['type' => 'text', 'title' => 'Netiquette, digitale Spuren, Persönlichkeitsrechte', 'body' => "## Netiquette\n\nDie **Netiquette** ist das ungeschriebene Regelwerk fürs Netz: wertschätzend schreiben, Groß-/Kleinschreibung nicht ignorieren, KEIN SCHREIEN IN GROSSBUCHSTABEN, Verstehens-Fragen stellen statt Vorwürfe.\n\n## Digitale Spuren\n\nJede Anmeldung, jeder Like, jeder Standort hinterlässt Spuren. Aus vielen kleinen Spuren entsteht ein **Profil**. Tipps:\n\n- Klarnamen nur dort, wo nötig.\n- Standort-Freigaben aus, außer du brauchst sie.\n- Alte Accounts löschen, die du nicht mehr nutzt.\n\n## Rechte am eigenen Bild\n\nFotos von anderen nur **mit Einverständnis** veröffentlichen - das ist kein Höflichkeits-, sondern ein **Persönlichkeitsrecht**. Gruppennamen, Chats und Fotos anderer gehören den anderen genauso wie ihre Sachen."],
                ['type' => 'text', 'title' => 'Übung: Ein Klassen-Kodex fürs Netz', 'body' => "**Übung: Der Klassen-Kodex**\n\nEure Klasse plant einen Klassenchat. Entwerft in Kleingruppen einen **Kodex mit fünf Regeln**:\n\n1. Zwei Regeln zum **Umgang miteinander** (wertschätzend schreiben, kein Cyber-Mobbing).\n2. Eine Regel zu **Zeiten** (Wann dürfen Nachrichten kommen?).\n3. Eine Regel zu **Fotos und Namen** anderer.\n4. Eine Regel, was im **Fach-Chat** erlaubt ist.\n\nTragt die Vorschläge zusammen und beschließt die fünf Regeln gemeinsam. Schreibt sie als **Wiki-Seite** in InfoCraft."],
                ['type' => 'text', 'title' => 'Lösung: Muster-Kodex', 'body' => "**Muster-Kodex (für eure Klasse anpassbar)**\n\n1. **Wertschätzend bleiben**: Keine Beleidigungen, keine Bloßstellungen - offline gelten dieselben Regeln wie online.\n2. **Themen trennen**: Der Chat ist für Organisation, Hausaufgaben und Absprachen - Privates bleibt privat.\n3. **Ruhezeiten**: Nach 22 Uhr und an Wochenenden schicken wir keine Nachrichten.\n4. **Fotos und Namen**: Ohne Einverständnis der abgebildeten Person wird nichts geteilt.\n5. **Konflikte lösen**: Wer sich über eine Nachricht ärgert, fragt direkt nach - und klärt es im Zweifel im persönlichen Gespräch.\n\nEin Kodex lebt davon, dass **alle** ihn beschlossen haben - deshalb stand die Abstimmung am Ende der Übung."],
            ]],
            ['slug' => 'algorithmen', 'title' => 'Algorithmen: Schritt für Schritt', 'level' => 7, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Was ist ein Algorithmus?', 'body' => "# Algorithmen: Schritt für Schritt\n\nEin **Algorithmus** ist eine klare Handlungsvorschrift: endlich viele **eindeutige Schritte in fester Reihenfolge**, die ein Problem lösen.\n\n## Algorithmen im Alltag\n\n- Ein **Rezept**: Zutaten (Eingabe), Arbeitsschritte (Verarbeitung), Kuchen (Ausgabe).\n- Eine **Bauanleitung**: Schritt für Schritt zum Regal.\n- Der **Weg zur Schule**: An der Ampel links, am Kiosk rechts.\n\n## Die drei Eigenschaften\n\n- **Eindeutig**: Jeder Schritt ist klar beschrieben, nichts bleibt der Fantasie überlassen.\n- **Endlich**: Irgendwann ist Schluss - der Kuchen ist fertig.\n- **Allgemein**: Das Rezept funktioniert für jeden, der es richtig befolgt.\n\n**Computer können nur Algorithmen**: Sie führen Schritte exakt aus - schnell, aber ohne eigenes Denken. Deshalb muss der Algorithmus vorher stimmen."],
                ['type' => 'code', 'title' => 'Live-Demo: Ein Rezept als Java-Programm', 'lang' => 'java', 'code' => '// Ein Programm ist ein Algorithmus in einer Sprache,\n// die der Computer versteht: Schritte in fester Reihenfolge.\nSystem.out.println("1. Ofen auf 180 Grad vorheizen");\nSystem.out.println("2. Teig in die Form geben");\nSystem.out.println("3. 30 Minuten backen");\nSystem.out.println("4. Abkuehlen lassen");\nSystem.out.println("Der Kuchen ist fertig!");'],
                ['type' => 'text', 'title' => 'Vom Alltag zum Programm', 'body' => "## Warum Schreiben üben?\n\nEin Roboter, der einen Stift hält, zeichnet nur exakt, was vorgegeben ist. Programmieren heißt deshalb: **genau beschreiben können**.\n\n## Der Roboter-Fahrplan\n\nDamit ein Bodenroboter ein **Quadrat** fährt, reicht *fahre ein Quadrat!* nicht - der Roboter kennt kein Quadrat. Er braucht:\n\n```text\nwiederhole 4-mal:\n    fahre ein Stück vorwärts\n    drehe dich um 90 Grad nach rechts\n```\n\nDas ist ein **Algorithmus mit Wiederholung**: statt viermal dasselbe zu schreiben, sagt man einmal, **wie oft** etwas wiederholt wird.\n\nGenau das lernen wir in Java mit der **for-Schleife**."],
                ['type' => 'code', 'title' => 'Live-Demo: Quadrat fahren in Java', 'lang' => 'java', 'code' => '// Der Roboter faehrt ein Quadrat: viermal vorwaerts und drehen\nclass Roboter {\n    void quadratFahren() {\n        for (int i = 0; i < 4; i++) {\n            System.out.println("ein Stueck vorwaerts");\n            System.out.println("90 Grad nach rechts drehen");\n        }\n        System.out.println("Quadrat fertig!");\n    }\n}\n\nRoboter robi = new Roboter();\nrobi.quadratFahren();'],
                ['type' => 'text', 'title' => 'Übung: Der Marmeladen-Algorithmus', 'body' => "**Übung: Schritt für Schritt beschreiben**\n\n1. Beschreibe in deinem Heft einen **Alltags-Algorithmus** in maximal 8 Schritten: einen Pausenbrot-Algorithmus, einen Zahnputz-Algorithmus oder den Schulweg-Algorithmus.\n2. Prüfe die drei Eigenschaften: Sind alle Schritte **eindeutig**? Ist das Verfahren **endlich**? Funktioniert es **für jeden**?\n3. Tausche mit deinem Nachbarn: Führt den Algorithmus **wörtlich** aus - was fehlt, was ist unklar?\n\nBonus für Schnelle: Schreibe deinen Algorithmus als Java-Programm in der **Code-Werkstatt** (jeder Schritt ein `System.out.println`)."],
                ['type' => 'text', 'title' => 'Lösung: Muster-Algorithmus', 'body' => "**Musterlösung: Pausenbrot-Algorithmus**\n\n```text\n1. Nimm zwei Brotscheiben aus der Tuete\n2. Lege eine Scheibe auf den Teller\n3. Streiche Marmelade auf die Scheibe\n4. Lege die zweite Scheibe darauf\n5. Packe das Brot in die Brotdose\nFertig: Pausenbrot bereit\n```\n\n**Prüfung**: Jeder Schritt ist eindeutig (kein *etwas Marmelade*), das Verfahren endet immer, und es funktioniert für jede Person.\n\n**Typische Lücken beim Tausch**: Schritte wie *bestreiche das Brot* verraten, dass **Werkzeug** fehlt (Messer) und **Menge** unklar ist. Genau solche Lücken zeigen, warum Computer unmissverständliche Algorithmen brauchen."],
            ]],
            ['slug' => 'informatik-jahr-9', 'title' => 'Dein Informatik-Jahr in der 9', 'level' => 9, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Willkommen in der 9. Klasse', 'body' => "# Informatik 9\n\n**Was dich dieses Schuljahr erwartet**\n\nBald startest du in dein Informatik-Jahr: vier Lernbereiche, viele Stunden am Computer und ein Ziel - du verstehst, wie die digitale Welt um dich herum funktioniert.\n\nUnd das Beste: **Du programmierst und bastelst selbst** - fast jede Einheit hat einen praktischen Teil am Computer.\n\n*(Grundlage: LehrplanPLUS Bayern, Informatik, Jahrgangsstufe 9, Gymnasium)*"],
                ['type' => 'text', 'title' => 'Deine Reise durch das Schuljahr', 'body' => "## Vier Lernbereiche, ein Ziel\n\n- **Tabellenkalkulation**: Tabellen, die von selbst rechnen - Formeln, WENN-Funktionen, Diagramme\n- **Datenbanken und SQL**: Daten wie die Profis verwalten - praxisnah in einem Online-Shop\n- **Java**: Deine eigenen Programme schreiben - vom Objekt aus dem Alltag zur eigenen Klasse\n- **Datenschutz**: Wer darf eigentlich was über dich wissen?\n\nJedes Thema baut auf dem vorigen auf: Aus Tabellen werden Datenbanken, aus Datenbanken wird Java-Code, und überall begegnen deine Daten."],
                ['type' => 'text', 'title' => 'Tabellenkalkulation', 'body' => "## Excel: Tabellen mit Zauberkraft\n\nStell dir vor, du tippst eine Zahl ein - und **hunderte Berechnungen aktualisieren sich von selbst**. Genau das lernst du mit Formeln und Funktionen:\n\n- den Taschengeld-Kalkulator bauen, der automatisch rechnet\n- **WENN-Funktionen**: Tabellen, die selbstständig Entscheidungen treffen\n- aus Zahlen richtig schicke **Diagramme** machen\n\n```text\n   A            B          C\n1  Artikel      Preis      Bewertung\n2  Heft         1,50       =WENN(B2<2;\"guenstig\";\"teuer\")\n```\n\nDie Formel in C2 kannst du nach unten kopieren - der **relative Bezug** wandert mit."],
                ['type' => 'text', 'title' => 'SQL: Sprich mit einer Datenbank', 'body' => "## Amazon, Spotify, WhatsApp - dahinter stecken Datenbanken\n\nMit **SQL**, der Sprache der Datenbanken, stellst du Fragen und bekommst sofort Antworten:\n\n```sql\n-- Welche Spiele kosten unter 30 Euro?\nSELECT name, preis\nFROM produkte\nWHERE preis < 30;\n```\n\n- Wir starten mit der **Dateninsel**: ein Abenteuer, in dem du mit SQL-Abfragen eine Insel rettest\n- Danach arbeiten wir in einem **Online-Shop** mit Kunden, Produkten und Bestellungen\n- Du verstehst, wie Apps deine Daten speichern - und warum das nicht immer unproblematisch ist"],
                ['type' => 'code', 'title' => 'Java: Dein erstes eigenes Programm', 'lang' => 'java', 'code' => '// Dein erstes Haustier in Java\nclass Haustier {\n    String name;\n    int alter;\n\n    Haustier(String nameStart, int alterStart) {\n        name = nameStart;\n        alter = alterStart;\n    }\n\n    void vorstellen() {\n        System.out.println(name + \" ist \" + alter + \" Jahre alt.\");\n    }\n}\n\nHaustier brock = new Haustier(\"Brock\", 2);\nbrock.vorstellen();'],
                ['type' => 'text', 'title' => 'Deine Daten, deine Rechte', 'body' => "## Datenschutz - warum ausgerechnet du?\n\nDu wirst mit Datenbanken arbeiten - und genau deshalb verstehen, **warum Datenschutz so wichtig ist**:\n\n- **Datenschutzgesetze**: Was darf mit deinen Daten passieren - und was nicht?\n- **Datenmissbrauch**: Identitätsdiebstahl - wie schützt du dich davor?\n- **Data-Mining**: Was Firmen aus deinen Daten alles herauslesen können\n- **Soziale Netzwerke**: Messenger und Co. - klug statt ahnungslos nutzen\n\nAm Ende des Jahres weißt du nicht nur, wie man Daten speichert, sondern auch, **wer sie sehen darf**."],
                ['type' => 'text', 'title' => 'Das nimmst du mit', 'body' => "## Dein Jahr in vier Sätzen\n\n- **Tabellenkalkulation**: Du automatisierst Rechnungen und machst aus Zahlen Diagramme.\n- **SQL und Datenbanken**: Du rettest die Dateninsel und verwaltest Daten wie ein echter Shop-Betreiber.\n- **Java**: Du schreibst deine eigenen Programme - mit Klassen, Objekten und Methoden.\n- **Datenschutz**: Du weißt, was mit deinen Daten passiert, und wie du sie schützt.\n\n**Los geht's!**\n\n*(Grundlage: LehrplanPLUS Bayern, Informatik, Jahrgangsstufe 9, Gymnasium)*"],
            ]],
            ['slug' => 'funktionale-modellierung', 'title' => 'Funktionale Modellierung: Datenfluss und Verkettung', 'level' => 9, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Funktion: Eingabe, Verarbeitung, Ausgabe', 'body' => "# Funktionale Modellierung\n\nEine **Funktion** ist wie eine kleine Maschine: Sie bekommt eine **Eingabe**, verarbeitet sie und liefert eine **Ausgabe**.\n\n## Beispiel Notenschnitt\n- Eingabe: deine Punkte aus zwei Schulaufgaben\n- Verarbeitung: Durchschnitt berechnen\n- Ausgabe: dein Schnitt\n\nFunktionen lassen sich **verketten**: Die Ausgabe der einen ist die Eingabe der nächsten."],
                ['type' => 'text', 'title' => 'Datenflussdiagramm', 'body' => "## Datenflussdiagramm\n\nBoxen sind Funktionen, Pfeile zeigen, wie die Daten fließen:\n\n```\npunkteErfassen  ->  berechneSchnitt  ->  noteAusgeben\n(eingeben)          (rechnen)           (zeigen)\n```\n\nJede Funktion hat genau **eine Aufgabe** - so bleibt das Gesamtsystem überschaubar und einzelne Teile lassen sich austauschen, ohne alles umzubauen."],
                ['type' => 'text', 'title' => 'Übung: Getränkeautomat modellieren', 'body' => "**Übung: Getränkeautomat als Datenfluss**\n\nEin Getränkeautomat soll als verkettete Funktionen modelliert werden:\n\n1. Nenne **drei Funktionen** des Automaten mit jeweils Eingabe und Ausgabe.\n2. Zeichne das **Datenflussdiagramm** (Boxen + Pfeile) in dein Heft.\n3. Was passiert, wenn zu wenig Geld eingeworfen wird? Ergänze eine Funktion *gueltigPruefen* in deinen Datenfluss.\n\nBespreche danach mit deinem Nachbarn: Wo ist eure Verkettung gleich, wo unterschiedlich?"],
                ['type' => 'text', 'title' => 'Lösung: Getränkeautomat', 'body' => "**Musterlösung**\n\n```\ngeldEinwerfen  ->  gueltigPruefen  ->  getraenkAusgeben\n(muenzen)         (preis erreicht?)    (flasche)\n```\n\n- *geldEinwerfen*: Eingabe Münzen, Ausgabe Betrag\n- *gueltigPruefen*: Eingabe Betrag, Ausgabe wahr oder falsch (Entscheidung)\n- *getraenkAusgeben*: Eingabe wahr, Ausgabe Flasche\n\nDie Prüffunktion steht **in der Mitte**: Ohne sie würde der Automat auch bei zu wenig Geld ausgeben."],
            ]],
            ['slug' => 'tabellenkalkulation', 'title' => 'Tabellenkalkulation: Bezüge, Formeln, WENN', 'level' => 9, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Zellbezüge: relativ und absolut', 'body' => "# Tabellenkalkulation\n\nJede Zelle hat eine **Adresse** wie B3 (Spalte B, Zeile 3). Beim Kopieren einer Formel gilt:\n\n- **relativer Bezug** (A1): verschiebt sich mit - passt für ganze Spalten\n- **absoluter Bezug** (\$A\$1): bleibt fest - wichtig für feste Werte wie einen Steuersatz\n- **gemischter Bezug** (\$A1 oder A\$1): nur Spalte oder Zeile bleibt fest"],
                ['type' => 'text', 'title' => 'Formeln und bedingte Funktionen', 'body' => "## Formeln\n\n- `=SUMME(B2:B10)` - addiert einen Bereich\n- `=MITTELWERT(B2:B10)` - Durchschnitt\n- `=WENN(B2>=90;\"sehr gut\";\"gut\")` - bedingte Funktion: Bedingung; Dann-Wert; Sonst-Wert\n\n## Beispiel Notenliste\n\n```\n   A        B        C\n1  Name     Punkte   Note\n2  Lena     92       =WENN(B2>=90;\"sehr gut\";\"gut\")\n3  Jonas    74       =WENN(B3>=90;\"sehr gut\";\"gut\")\n```\n\nDie Formel in C2 lässt sich nach unten **kopieren**, weil der Bezug relativ ist."],
                ['type' => 'text', 'title' => 'Übung: Kassenbuch der Klassenkasse', 'body' => "**Übung: Mehrwertsteuer richtig berechnen**\n\nGegeben: Spalte A enthält Artikel, Spalte B den Nettopreis. In Zelle **F1** steht fest der Mehrwertsteuersatz 19%.\n\n1. Schreibe die Formel für **C2** (Bruttopreis), damit sie nach unten kopierbar ist. Nutze einen **absoluten** Bezug auf F1.\n2. Schreibe in **D2** eine Formel, die den Bruttopreis auf 2 Nachkommastellen gerundet ausgibt (RUNDEN).\n3. Ergänze in **E2** eine WENN-Formel: \"geschenkt\", wenn der Bruttopreis unter 5 Euro liegt, sonst \"zahlen\".\n\nFertig? Vergleicht zu zweit: Habt ihr in Aufgabe 1 beide \$F\$1 genutzt - und warum muss es der absolute Bezug sein?"],
                ['type' => 'text', 'title' => 'Lösung: Kassenbuch', 'body' => "**Musterlösung**\n\n```\nC2:  =B2*(1+\$F\$1)\nD2:  =RUNDEN(C2;2)\nE2:  =WENN(C2<5;\"geschenkt\";\"zahlen\")\n```\n\n- Der absolute Bezug \$F\$1 bleibt beim Kopieren **fest** - ohne \$ würde C3 plötzlich auf F2 zeigen (leer!) und falsch rechnen.\n- Die WENN-Funktion hat drei Teile: Bedingung; Dann; Sonst - genau wie eine if-else-Entscheidung beim Programmieren."],
            ]],
            ['slug' => 'datenmodellierung', 'title' => 'Datenmodellierung: Von der Klasse zur Tabelle', 'level' => 9, 'v' => 1, 'legacy' => ['Datenbanken: Tabellen, Datensätze, Primärschlüssel'], 'slides' => [
                ['type' => 'text', 'title' => 'Von Objekten zu Tabellen', 'body' => "# Datenmodellierung\n\nAus dem Objektmodell wird das **relationale Modell**:\n\n- **Klasse** (z. B. Schueler) wird zur **Tabelle**\n- **Attribute** werden zu **Spalten**\n- Jedes **Objekt** wird zu einem **Datensatz** (Zeile)\n- Der **Primärschlüssel** identifiziert jeden Datensatz eindeutig - meist eine laufende Nummer (id)"],
                ['type' => 'text', 'title' => 'Von der Tabelle zum Datenmodell', 'body' => "## Entity-Relationship-Modell\n\n- **Entität** (Kasten): ein Ding, z. B. Klasse oder Schueler\n- **Beziehung** (Raute): z. B. *gehört zu*\n- **Kardinalitäten**: 1:1, 1:n, n:m\n- Bei 1:n wandert der Primärschlüssel als **Fremdschlüssel** in die n-Seite"],
                ['type' => 'code', 'title' => 'Live-Demo: Eine Tabelle anlegen', 'lang' => 'sql', 'code' => "CREATE TABLE schueler (\n    id INTEGER PRIMARY KEY,\n    name TEXT,\n    klasse TEXT,\n    note REAL\n);\n\nINSERT INTO schueler (id, name, klasse, note)\nVALUES (1, 'Lena', '9R', 1.7);"],
                ['type' => 'text', 'title' => 'Übung: Tabelle klasse entwerfen', 'body' => "**Übung: Eigene Tabelle entwerfen**\n\nEntwirf die Tabelle **klasse** für eine Klassensprecherwahl:\n\n1. Welche **Spalten** (Attribute) brauchst du? Mindestens: Klassenname, Jahrgangsstufe, Klassensprecher.\n2. Was ist der **Primärschlüssel**?\n3. Schreibe das CREATE-Statement in dein Heft.\n\nBonus: Probiere es anschließend in der **SQL-Werkstatt** aus (Recht *Tabellen anlegen* nötig)."],
                ['type' => 'code', 'title' => 'Lösung: Tabelle klasse', 'lang' => 'sql', 'code' => "CREATE TABLE klasse (\n    id INTEGER PRIMARY KEY,\n    klassenname TEXT,\n    jahrgangsstufe INTEGER,\n    klassensprecher TEXT\n);\n\nINSERT INTO klasse (id, klassenname, jahrgangsstufe, klassensprecher)\nVALUES (1, '9R', 9, 'Emma');\n\nSELECT * FROM klasse;"],
            ]],
            ['slug' => 'sql-grundlagen', 'title' => 'SQL: Abfragen mit SELECT, WHERE und ORDER BY', 'level' => 9, 'v' => 1, 'legacy' => ['SQL: Abfragen mit SELECT, WHERE und ORDER BY'], 'slides' => [
                ['type' => 'text', 'title' => 'Abfragen stellen', 'body' => "Mit **SELECT** holst du Daten aus einer Tabelle. Der Aufbau:\n\n```sql\nSELECT spalten FROM tabelle\nWHERE bedingung\nORDER BY spalte;\n```\n\n- `WHERE` filtert Zeilen\n- `ORDER BY` sortiert das Ergebnis (absteigend mit `DESC`)\n- `LIKE` vergleicht Texte mit `%` als Platzhalter"],
                ['type' => 'code', 'title' => 'Live-Demo: Abfragen in der SQL-Werkstatt', 'lang' => 'sql', 'code' => "-- Alle Schueler der 9R alphabetisch\nSELECT name, note FROM schueler\nWHERE klasse = '9R'\nORDER BY name;\n\n-- Nur Namen mit L und Note besser als 2\nSELECT * FROM schueler\nWHERE name LIKE 'L%' AND note < 2;"],
                ['type' => 'text', 'title' => 'Merkhilfe', 'body' => "**SELECT** *was* **FROM** *woher* **WHERE** *Bedingung* **ORDER BY** *Reihenfolge*.\n\nDie Reihenfolge der Schlüsselwörter ist fest - ein gutes Beispiel für einen *Algorithmus*."],
                ['type' => 'text', 'title' => 'Übung: Abfragen im Shop', 'body' => "**Übung: Abfragen schreiben**\n\nÖffne deine ausgeteilte **Shop-Datenbank** in der SQL-Werkstatt und schreibe:\n\n1. Alle Spalten aller Produkte.\n2. Name und Preis aller Produkte **unter 20 Euro**.\n3. Alle Produkte, deren Name mit einem bestimmten Buchstaben beginnt (LIKE), sortiert nach Preis - günstigste zuerst.\n\nTipp: Baue jede Abfrage **Schritt für Schritt** auf - erst SELECT ... FROM, dann WHERE, dann ORDER BY."],
                ['type' => 'code', 'title' => 'Lösung: Abfragen im Shop', 'lang' => 'sql', 'code' => "-- 1) Alle Spalten aller Produkte\nSELECT * FROM produkte;\n\n-- 2) Name und Preis unter 20 Euro\nSELECT name, preis FROM produkte\nWHERE preis < 20;\n\n-- 3) Namen mit M, guenstig zuerst\nSELECT name, preis FROM produkte\nWHERE name LIKE 'M%'\nORDER BY preis;"],
            ]],
            ['slug' => 'sql-aendern', 'title' => 'SQL: Daten einfügen, ändern, löschen', 'level' => 9, 'v' => 1, 'legacy' => ['SQL: Daten einfügen, ändern, löschen'], 'slides' => [
                ['type' => 'text', 'title' => 'CRUD: Die vier Grundoperationen', 'body' => "- **C**reate: `INSERT INTO` - neue Datensätze\n- **R**ead: `SELECT` - Datensätze lesen\n- **U**pdate: `UPDATE` - Datensätze ändern\n- **D**elete: `DELETE FROM` - Datensätze löschen"],
                ['type' => 'code', 'title' => 'Live-Demo: Einfügen, Ändern, Löschen', 'lang' => 'sql', 'code' => "INSERT INTO schueler (id, name, klasse, note)\nVALUES (2, 'Jonas', '9R', 2.3);\n\n-- Note verbessern (WHERE nicht vergessen!)\nUPDATE schueler SET note = 1.0\nWHERE id = 2;\n\n-- Datensatz entfernen\nDELETE FROM schueler WHERE id = 2;"],
                ['type' => 'text', 'title' => 'Achtung Falle', 'body' => "Ohne `WHERE` trifft `UPDATE` und `DELETE` **jede Zeile** der Tabelle!\n\nImmer erst mit `SELECT` prüfen, welche Zeilen die Bedingung trifft - dann erst ändern oder löschen."],
                ['type' => 'text', 'title' => 'Übung: CRUD an der Schueler-Tabelle', 'body' => "**Übung: Daten ändern - sicher!**\n\nArbeite in der **SQL-Werkstatt** an einer Tabelle mit mehreren Datensätzen:\n\n1. **Prüfe** mit SELECT, welche Zeilen deine WHERE-Bedingung trifft.\n2. **Füge** einen neuen Datensatz ein (INSERT INTO).\n3. **Ändere** genau diesen einen Datensatz mit UPDATE - mit WHERE auf seine id.\n4. **Lösche** ihn mit DELETE wieder.\n\nKontrolliere nach **jedem** Schritt mit SELECT, was passiert ist."],
                ['type' => 'code', 'title' => 'Lösung: CRUD Schritt für Schritt', 'lang' => 'sql', 'code' => "-- 1) Kontrolle: Wer ist betroffen?\nSELECT * FROM schueler WHERE id = 3;\n\n-- 2) Einfuegen\nINSERT INTO schueler (id, name, klasse, note)\nVALUES (3, 'Sofia', '9R', 2.0);\n\n-- 3) Aendern (nur diese Zeile!)\nUPDATE schueler SET note = 1.5\nWHERE id = 3;\n\n-- 4) Loeschen\nDELETE FROM schueler WHERE id = 3;\n\nSELECT * FROM schueler;"],
            ]],
            ['slug' => 'java-klassen', 'title' => 'Java: Klassen und Objekte', 'level' => 9, 'v' => 2, 'legacy' => ['Java: Klassen und Objekte'], 'slides' => [
                ['type' => 'text', 'title' => 'Vom Bauplan zum Objekt', 'body' => "Eine **Klasse** ist der Bauplan, ein **Objekt** ist die konkrete Ausprägung.\n\n## Syntax einer Klasse\n- `class Name { ... }`\n- **Attribute** beschreiben die Eigenschaften\n- **Konstruktor** legt die Startwerte fest\n- `new Name(...)` erzeugt ein Objekt"],
                ['type' => 'code', 'title' => 'Live-Demo: Klasse Schueler', 'lang' => 'java', 'code' => 'class Schueler {\n    // Attribute\n    String name;\n    int alter;\n\n    // Konstruktor\n    Schueler(String nameStart, int alterStart) {\n        name = nameStart;\n        alter = alterStart;\n    }\n\n    // eigene Methode\n    void vorstellen() {\n        System.out.println("Ich heisse " + name + " und bin " + alter + ".");\n    }\n}\n\nSchueler lena = new Schueler("Lena", 16);\nlena.vorstellen();'],
                ['type' => 'text', 'title' => 'Übung: Eigene Klasse Auto', 'body' => "**Übung: Klasse Auto schreiben**\n\nSchreibe in der **Code-Werkstatt** die Klasse Auto:\n\n1. **Attribute**: `farbe` (String) und `geschwindigkeit` (int)\n2. **Konstruktor** mit Startwerten (`farbeStart`, `geschwindigkeitStart`)\n3. **Eigene Methode** `fahren()`: druckt, wie schnell das Auto fährt\n4. Erzeuge in der main-Methode ein rotes Auto und rufe `fahren()` auf.\n\nNaming: Konstruktor-Parameter enden auf **Start** (z. B. `farbeStart`) - so braucht es kein this."],
                ['type' => 'code', 'title' => 'Lösung: Klasse Auto', 'lang' => 'java', 'code' => 'class Auto {\n    // Attribute\n    String farbe;\n    int geschwindigkeit;\n\n    // Konstruktor\n    Auto(String farbeStart, int geschwindigkeitStart) {\n        farbe = farbeStart;\n        geschwindigkeit = geschwindigkeitStart;\n    }\n\n    // eigene Methode\n    void fahren() {\n        System.out.println("Das " + farbe + "e Auto faehrt " + geschwindigkeit + " km/h.");\n    }\n}\n\nAuto meinAuto = new Auto("rot", 80);\nmeinAuto.fahren();'],
            ]],
            ['slug' => 'java-methoden', 'title' => 'Java: Methoden, Parameter und Rückgaben', 'level' => 9, 'v' => 2, 'legacy' => ['Java: Methoden, Parameter und Rückgaben'], 'slides' => [
                ['type' => 'text', 'title' => 'Methoden strukturieren Programme', 'body' => "Eine **Methode** ist ein benannter Arbeitsschritt.\n\n## Aufbau\n`Rückgabetyp name(typ parameter) { ... return wert; }`\n\n- `void`: gibt nichts zurück\n- `return`: gibt genau einen Wert zurück und beendet die Methode\n- Methoden sparen doppelten Code (DRY: *Don't Repeat Yourself*)"],
                ['type' => 'code', 'title' => 'Live-Demo: Rechner mit Rückgabe', 'lang' => 'java', 'code' => 'int addiere(int a, int b) {\n    return a + b;\n}\n\nString noteText(double punkte) {\n    if (punkte >= 90) {\n        return "sehr gut";\n    }\n    return "weiter ueben";\n}\n\nSystem.out.println(addiere(7, 8));\nSystem.out.println(noteText(95));'],
                ['type' => 'text', 'title' => 'Übung: Notenrechner als Methoden', 'body' => "**Übung: Zwei Methoden, eine Kette**\n\nSchreibe in der **Code-Werkstatt**:\n\n1. Eine Methode `schnitt(double note1, double note2)`, die den Durchschnitt beider Noten mit `return` zurückgibt.\n2. Eine Methode `ausgeben(double schnitt)`, die eine Bewertung druckt: unter 2 \"sehr gut\", sonst \"weiter ueben\".\n3. Rufe beide **verkettet** auf: `ausgeben(schnitt(1.7, 2.3));`\n\nFrage zum Nachdenken: Warum ist `ausgeben` vom Typ `void`, `schnitt` aber vom Typ `double`?"],
                ['type' => 'code', 'title' => 'Lösung: Notenrechner', 'lang' => 'java', 'code' => 'double schnitt(double note1, double note2) {\n    return (note1 + note2) / 2;\n}\n\nvoid ausgeben(double schnitt) {\n    if (schnitt < 2) {\n        System.out.println("sehr gut");\n    } else {\n        System.out.println("weiter ueben");\n    }\n}\n\nausgeben(schnitt(1.7, 2.3));'],
            ]],
            ['slug' => 'java-kontrollstrukturen', 'title' => 'Java: if, else und Struktogramme', 'level' => 9, 'v' => 2, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Entscheidungen im Programm', 'body' => "# Entscheidungen: if und else\n\n```java\nif (punkte >= 90) {\n    System.out.println(\"sehr gut\");\n} else {\n    System.out.println(\"weiter ueben\");\n}\n```\n\nDie **Bedingung** steht in Klammern und ist immer `true` oder `false`. Der **wahr-Zweig** läuft bei wahr, der **falsch-Zweig** bei falsch.\n\n## Struktogramm\n\nIm Struktogramm steht die Bedingung im Dreieck oben, darunter (getrennt durch eine Linie mit Mittelstrich) links der wahr-Zweig und rechts der falsch-Zweig - so sieht eine Entscheidung auf Papier aus."],
                ['type' => 'text', 'title' => 'Typische Fehler', 'body' => "## Typische Fehler\n\n- `=` ist eine **Zuweisung** (punkte = 90), `==` ist ein **Vergleich** (punkte == 90). In einer Bedingung immer `==`.\n- Ein `;` direkt nach `if (...)` beendet die Entscheidung - der Block läuft dann **immer**.\n- Texte vergleicht man mit `equals`, nicht mit `==`: `name.equals(\"Lena\")`.\n- `!=` heißt ungleich; `<>` gibt es in Java **nicht**."],
                ['type' => 'code', 'title' => 'Live-Demo: Der Held der Klasse Spielcharakter', 'lang' => 'java', 'code' => 'class Spielcharakter {\n    // Attribute\n    String name;\n    int leben;\n    int punkte;\n\n    // Konstruktor\n    Spielcharakter(String nameStart, int lebenStart, int punkteStart) {\n        name = nameStart;\n        leben = lebenStart;\n        punkte = punkteStart;\n    }\n\n    // eigene Methode: nur lebende Helden kassieren den Treffer\n    void trefferErleiden() {\n        if (leben > 0) {\n            leben = leben - 10;\n            System.out.println(name + " hat noch " + leben + " Leben.");\n        }\n    }\n\n    // eigene Methode mit Rueckgabe: Rang je nach Punktestand\n    String kategorie() {\n        if (punkte >= 1000) {\n            return "LEGENDE";\n        } else if (punkte >= 500) {\n            return "GOLD";\n        } else {\n            return "BRONZE";\n        }\n    }\n}\n\nSpielcharakter held = new Spielcharakter("Ryu", 100, 700);\nheld.trefferErleiden();\nSystem.out.println("Rang: " + held.kategorie());'],
                ['type' => 'text', 'title' => 'Übung: Den Helden fertig bauen', 'body' => "**Übung: if-else im Spiel**\n\nDie Klasse **Spielcharakter** aus der Demo steckt in deiner Code-Werkstatt. Erweitere sie:\n\n1. Schreibe eine Methode `sterben()`: Sie druckt **Game Over!**.\n2. Erweitere `trefferErleiden()` mit einem **else**, sodass `sterben()` genau dann aufgerufen wird, wenn `leben` **nicht größer als 0** ist.\n3. Zeichne das **Struktogramm** der erweiterten Methode in dein Heft: Bedingung im Dreieck, wahr-Zweig links, falsch-Zweig rechts.\n\nBonus für Schnelle: Schreibe eine Methode `hatGewonnen()`, die `true` zurückgibt, wenn `punkte >= 1000` ist. Was bedeutet das `return` hier?"],
                ['type' => 'code', 'title' => 'Lösung: Held mit sterben() und hatGewonnen()', 'lang' => 'java', 'code' => '// Ergaenzungen in der Klasse Spielcharakter\nvoid trefferErleiden() {\n    if (leben > 0) {\n        leben = leben - 10;\n        System.out.println(name + " hat noch " + leben + " Leben.");\n    } else {\n        sterben();\n    }\n}\n\nvoid sterben() {\n    System.out.println("Game Over!");\n}\n\n// eigene Methode mit Rueckgabe\nboolean hatGewonnen() {\n    return punkte >= 1000;\n}'],
            ]],
            ['slug' => 'java-vererbung', 'title' => 'Java: Vererbung', 'level' => 9, 'v' => 2, 'legacy' => ['Java: Vererbung und ArrayList'], 'slides' => [
                ['type' => 'text', 'title' => 'Generalisierung und Spezialisierung', 'body' => "# Vererbung: nicht doppelt schreiben\n\nHund und Katze haben gemeinsam: einen **Namen** und die Fähigkeit, sich **lautzugeben**. Das Gemeinsame wandert in die **Oberklasse** Tier (*Generalisierung*), das Besondere bleibt in den **Unterklassen** (*Spezialisierung*).\n\n- `extends` erbt alle Attribute und Methoden\n- `super(...)` ruft den Konstruktor der Oberklasse auf\n- `@Override` überschreibt eine geerbte Methode - jede Klasse lautet dann auf ihre eigene Art"],
                ['type' => 'code', 'title' => 'Oberklasse Tier', 'lang' => 'java', 'code' => 'class Tier {\n    // Attribute\n    String name;\n\n    // Konstruktor\n    Tier(String nameStart) {\n        name = nameStart;\n    }\n\n    // eigene Methode\n    void gibLaut() {\n        System.out.println(name + " macht ein Geraeusch.");\n    }\n}'],
                ['type' => 'code', 'title' => 'Unterklassen: Hund und Katze', 'lang' => 'java', 'code' => 'class Hund extends Tier {\n    // Konstruktor\n    Hund(String nameStart) {\n        super(nameStart);\n    }\n\n    // eigene Methode (ueberschreibt Tier)\n    @Override\n    void gibLaut() {\n        System.out.println(name + " bellt: Wuff!");\n    }\n}\n\nclass Katze extends Tier {\n    // Konstruktor\n    Katze(String nameStart) {\n        super(nameStart);\n    }\n\n    // eigene Methode (ueberschreibt Tier)\n    @Override\n    void gibLaut() {\n        System.out.println(name + " miaut: Miau!");\n    }\n}\n\nTier bello = new Hund("Bello");\nbello.gibLaut();'],
                ['type' => 'text', 'title' => 'Übung: Papagei zieht ins Tierheim', 'body' => "**Übung: Eigene Unterklasse schreiben**\n\n1. Schreibe die Klasse **Papagei** als Unterklasse von Tier: Konstruktor mit `nameStart`, `@Override gibLaut()` - Papageien *sprechen*.\n2. Erzeuge einen Papagei und lasse ihn lautgeben.\n3. Zeichne das **Klassendiagramm** mit Generalisierungspfeil (hohles Dreieck) in dein Heft - oder nutze die Diagramm-Werkstatt.\n\nBonus: Was gibt `new Tier(\"Mystery\").gibLaut()` aus - und warum?"],
                ['type' => 'code', 'title' => 'Lösung: Papagei', 'lang' => 'java', 'code' => 'class Papagei extends Tier {\n    // Konstruktor\n    Papagei(String nameStart) {\n        super(nameStart);\n    }\n\n    // eigene Methode (ueberschreibt Tier)\n    @Override\n    void gibLaut() {\n        System.out.println(name + " spricht: Guten Tag!");\n    }\n}\n\nTier rio = new Papagei("Rio");\nrio.gibLaut();'],
            ]],
            ['slug' => 'datenschutz', 'title' => 'Datenschutz: Deine Daten gehören dir', 'level' => 9, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Personenbezogene Daten', 'body' => "# Datenschutz\n\n**Personenbezogene Daten** sind alle Informationen, die sich auf eine **bestimmte** Person beziehen lassen: Name, Adresse, Geburtsdatum, Fotos, Noten, Standort, Suchverlauf. Auch die **Kombination** mehrerer an sich harmloser Daten kann eine Person identifizierbar machen.\n\n## Warum ist das heikel?\n\nDaten lassen sich vervielfältigen, verknüpfen und auswerten - lange nach dem ursprünglichen Anlass. Wer Daten erhebt, sollte sich fragen: *Welcher Zweck? Wie lange? Wer darf zugreifen?*"],
                ['type' => 'text', 'title' => 'Gesetze und deine Rechte', 'body' => "## Rechtliche Grundlagen\n\nDie **DSGVO** (Datenschutz-Grundverordnung, EU) und das **BayDSG** (Bayerisches Datenschutzgesetz) regeln den Umgang mit personenbezogenen Daten. Kernidee: **Zweckbindung** - Daten dürfen nur für den Zweck verarbeitet werden, für den sie erhoben wurden.\n\n## Deine Rechte als betroffene Person\n\n- **Auskunft**: Was wird über mich gespeichert?\n- **Berichtigung**: Falsche Daten müssen korrigiert werden.\n- **Löschung**: Daten müssen gelöscht werden, wenn der Zweck entfällt.\n- **Widerspruch**: In manchen Fällen kann ich der Verarbeitung widersprechen."],
                ['type' => 'text', 'title' => 'Mehrbenutzer, Missbrauch, Data-Mining', 'body' => "## Mehrbenutzerproblematik\n\nEin Schulsystem nutzen Schülerinnen und Schüler, Lehrkräfte und Verwaltung - aber nicht jede Person darf alles sehen: Deine Noten sehen nur **du** und deine Lehrkräfte, nicht die ganze Klasse. Rechte müssen also **pro Rolle** geregelt werden (genau wie die Rollen in InfoCraft).\n\n## Datenmissbrauch\n\nWeitergabe an Dritte, Verkauf von Profilen, Ausspähen von Zugängen.\n\n## Data-Mining\n\nAutomatische Auswertungen finden Muster in großen Datenmengen: Empfehlungen im Shop, Verkehrsprognosen - aber auch Personenprofile. Nützlich und gefährlich zugleich: Das Ergebnis ist meist **keine Wahrheit**, sondern eine Wahrscheinlichkeit mit Fehlern.\n\n## Goldenes Prinzip\n\n**Datensparsamkeit**: Nur erheben, was nötig ist - und löschen, sobald es nicht mehr gebraucht wird."],
                ['type' => 'text', 'title' => 'Übung: Deine Daten in der Schule', 'body' => "**Übung: Datenkarte deiner Schule**\n\n1. Liste in deinem Heft **alle personenbezogenen Daten** auf, die deine Schule über dich speichert.\n2. Markiere für jeden Eintrag, **wer zugreifen** darf: du, Lehrkräfte, Verwaltung, Mitschülerinnen und Mitschüler?\n3. Finde je ein Beispiel für **Datensparsamkeit** (wo ist die Schule zurückhaltend?) und eine Situation, in der du **Auskunft** verlangen könntest.\n\nDiskutiert zu zweit: Welcher Eintrag aus Aufgabe 1 wäre für **Data-Mining** am interessantesten - und warum?"],
                ['type' => 'text', 'title' => 'Lösung: Deine Daten (Musterantwort)', 'body' => "**Musterantwort (Auszug)**\n\n- **Stammdaten** (Name, Adresse, Geburtsdatum): Verwaltung und Lehrkräfte; für dich per Auskunft einsehbar.\n- **Noten**: nur du und deine Lehrkräfte - klassische **Mehrbenutzerproblematik**: Mitschülerinnen und Mitschüler haben keinen Zugriff.\n- **Fotos aus dem Projekt**: Zweck war der Projektbericht - danach Löschung (**Datensparsamkeit**).\n- **Login-Zeiten im Schulnetz**: für Data-Mining am interessantesten, weil sich daraus Verhalten vorhersagen lässt.\n\nMerke: Auskunft gibt es schriftlich über den Datenschutzbeauftragten der Schule."],
            ]],
            ['slug' => 'informatik-jahr-10', 'title' => 'Dein Informatik-Jahr in der 10', 'level' => 10, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Willkommen in der 10. Klasse', 'body' => "# Informatik 10\n\n**Was dich dieses Schuljahr erwartet**\n\nDu knüpfst direkt an Klasse 9 an: **die Dateninsel, der Online-Shop und dein Java-Wissen** werden zum Fundament für Neues.\n\nDieses Jahr hat vier Stationen: **Warm-up**, **Datenbanken Level 2**, **Java - das große Ganze** und am Ende **dein eigenes Projekt**.\n\n*(Grundlage: LehrplanPLUS Bayern, Informatik, Jahrgangsstufe 10 (NTG), Gymnasium)*"],
                ['type' => 'text', 'title' => 'Deine Reise durchs Schuljahr', 'body' => "## Vier Stationen\n\n- **Warm-up**: Dein Java aus Klasse 9 auffrischen - und mit einer Kurzarbeit beweisen\n- **Datenbanken 2.0**: Mehrere Tabellen verknüpfen - wie echte Profi-Datenbanken\n- **Java: aufbauend**: Objekte, die miteinander reden - Arrays, Referenzen, Vererbung und mehr\n- **Dein Projekt**: Ein eigenes Java-Projekt entwickeln - im Team oder allein\n\nAm Ende des Jahres steht dein eigenes, funktionierendes Projekt, das du der Klasse vorstellst."],
                ['type' => 'text', 'title' => 'Warm-up: Dein Java aus Klasse 9', 'body' => "## Bevor wir Neues starten ...\n\n... machst du dein **Klassen-9-Wissen wieder fit** - du wirst es dieses Jahr ständig brauchen:\n\n- **Klassen, Objekte, Attribute und Methoden**\n- **Konstruktoren** - damit Objekte richtig entstehen\n- **Parameter und Rückgabewerte**\n- **Kontrollstrukturen**: `if`, `while`, `for`\n\nDanach folgt eine **Kurzarbeit** - und dann: Level 2!"],
                ['type' => 'code', 'title' => 'Warm-up-Code: Das kennst du noch?', 'lang' => 'java', 'code' => 'class Haustier {\n    String name;\n    int alter;\n\n    Haustier(String nameStart, int alterStart) {\n        name = nameStart;\n        alter = alterStart;\n    }\n\n    String gibLaut() {\n        if (alter < 3) {\n            return \"Ich bin noch klein!\";\n        }\n        return \"Streichel mich!\";\n    }\n}\n\nHaustier brock = new Haustier(\"Brock\", 2);\nSystem.out.println(brock.name + \" sagt: \" + brock.gibLaut());'],
                ['type' => 'text', 'title' => 'Datenbanken: Level 2', 'body' => "## Mehrere Tabellen, eine Abfrage\n\nIn Klasse 9 hast du eine Tabelle abgefragt. Jetzt wird es richtig interessant: **Echte Datenbanken bestehen aus vielen verknüpften Tabellen** - Kunden, Bestellungen und Produkte gehören zusammen.\n\n- Datenmodelle mit mehreren Klassen entwerfen (Klassendiagramm)\n- Tabellen geschickt verknüpfen: **Primär- und Fremdschlüssel**\n- Abfragen über mehrere Tabellen - z. B. *Was hat welcher Kunde bestellt?*\n- Verstehen, warum doppelte Daten Probleme machen (**Anomalien**)\n\nGenau so arbeiten Shops, Airlines und Streaming-Dienste - nur größer."],
                ['type' => 'code', 'title' => 'JOIN: Zwei Tabellen, eine Abfrage', 'lang' => 'sql', 'code' => '-- Wer hat was bestellt? Zwei Tabellen, eine Abfrage!\nSELECT kunde.name, bestellung.artikel\nFROM kunde\nJOIN bestellung ON kunde.kunden_nr = bestellung.kunden_nr;'],
                ['type' => 'text', 'title' => 'Java: Das große Ganze', 'body' => "## Vom Einzelkämpfer zum System\n\nDeine Objekte aus Klasse 9 waren Einzelkämpfer. Jetzt lernst du, **ganze Systeme daraus zu bauen**, in denen Objekte miteinander sprechen - z. B. ein Fahrradverleih oder die Mitgliederverwaltung eines Vereins.\n\n- Viele gleichartige Objekte verwalten - mit **Arrays**\n- Objekte per **Referenz** miteinander verbinden und Methoden aufrufen lassen\n- **Vererbung und Polymorphismus**: gleiche Nachricht, anderes Verhalten - ein E-Bike zahlt mehr Miete als ein normales Fahrrad"],
                ['type' => 'code', 'title' => 'Live-Demo: Der Fahrradverleih', 'lang' => 'java', 'code' => 'class Fahrrad {\n    String farbe;\n\n    Fahrrad(String farbeStart) {\n        farbe = farbeStart;\n    }\n\n    double miete() {\n        return 5.0;\n    }\n}\n\nclass EBike extends Fahrrad {\n    EBike(String farbeStart) {\n        super(farbeStart);\n    }\n\n    @Override\n    double miete() {\n        return 9.0;\n    }\n}\n\nFahrrad[] flotte = new Fahrrad[2];\nflotte[0] = new Fahrrad(\"rot\");\nflotte[1] = new EBike(\"blau\");\n\nfor (int i = 0; i < flotte.length; i++) {\n    System.out.println(flotte[i].farbe + \" kostet \" + flotte[i].miete() + \" Euro.\");\n}'],
                ['type' => 'text', 'title' => 'Das Herzstück: Dein Projekt', 'body' => "## Dein eigenes Java-Projekt\n\nAuf jeden Fall dabei: **Java, Klassendiagramme und echter Code** von dir. Ob im Team oder allein - am Ende steht dein eigenes, funktionierendes Projekt, das du vorstellst.\n\n- **Idee und Plan**: Ein Szenario wählen - Buchungssystem, Verwaltung oder ein einfaches Spiel\n- **Modellieren**: Klassendiagramme entwerfen - wie echte Softwareentwickler\n- **Implementieren**: Echt programmieren - Klasse für Klasse in der Code-Werkstatt\n- **Testen und Vorstellen**: Prüfen, ob alles läuft - und dein Projekt am Ende präsentieren\n\n**Los geht's!**\n\n*(Grundlage: LehrplanPLUS Bayern, Informatik, Jahrgangsstufe 10 (NTG), Gymnasium)*"],
            ]],
            ['slug' => 'java-wiederholung', 'title' => 'Warm-up: Java-Wiederholung aus der 9. Klasse', 'level' => 10, 'v' => 2, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Was nimmst du mit?', 'body' => "# Warm-up: Java aus der 9. Klasse\n\nStart in die 10: Wir wiederholen, was ihr sicher können solltet:\n\n- **Klassen**: Bauplan mit Attributen und Methoden\n- **Konstruktor**: legt die Startwerte fest (Parameter auf ...Start)\n- **Methoden mit Parameter und Rückgabe** (`return`)\n- **Kontrollstrukturen**: if / else if / else\n\nDanach: kurze **Kurzarbeit**. Anschließend geht es weiter mit Datenbanken Level 2 und neuen Java-Themen (Arrays, Datenkapselung, Polymorphismus)."],
                ['type' => 'code', 'title' => 'Warm-up-Code: Spielcharakter', 'lang' => 'java', 'code' => 'class Spielcharakter {\n    // Attribute\n    String name;\n    int punkte;\n\n    // Konstruktor\n    Spielcharakter(String nameStart, int punkteStart) {\n        name = nameStart;\n        punkte = punkteStart;\n    }\n\n    // eigene Methode mit Rueckgabe\n    String kategorie() {\n        if (punkte >= 1000) {\n            return "Legende";\n        } else if (punkte >= 500) {\n            return "Bronze";\n        }\n        return "Anfaenger";\n    }\n}\n\nSpielcharakter held = new Spielcharakter("Rio", 1500);\nSystem.out.println(held.name + ": " + held.kategorie());'],
                ['type' => 'text', 'title' => 'Übung: Warm-up vor der Kurzarbeit', 'body' => "**Übung: Klasse Song**\n\nSchreibe die Klasse **Song** in der Code-Werkstatt:\n\n1. **Attribute**: `titel` (String), `dauer` (int, Sekunden)\n2. **Konstruktor** mit `titelStart` und `dauerStart`\n3. **Eigene Methode** `vorspielen()`: druckt Titel und Dauer\n4. **Eigene Methode mit Rückgabe** `istLang()`: `true`, wenn der Song länger als 240 Sekunden ist\n\nWer fertig ist: Teste mit einem kurzen und einem langen Song."],
                ['type' => 'code', 'title' => 'Lösung: Klasse Song', 'lang' => 'java', 'code' => 'class Song {\n    // Attribute\n    String titel;\n    int dauer;\n\n    // Konstruktor\n    Song(String titelStart, int dauerStart) {\n        titel = titelStart;\n        dauer = dauerStart;\n    }\n\n    // eigene Methode\n    void vorspielen() {\n        System.out.println(titel + " (" + dauer + " Sekunden)");\n    }\n\n    // eigene Methode mit Rueckgabe\n    boolean istLang() {\n        return dauer > 240;\n    }\n}\n\nSong s = new Song("Sommerhit", 210);\ns.vorspielen();\nSystem.out.println("Lang? " + s.istLang());'],
            ]],
            ['slug' => 'sql-join', 'title' => 'Datenbanken Level 2: Primär-/Fremdschlüssel und JOIN', 'level' => 10, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Warum zwei Tabellen?', 'body' => "# Datenbanken Level 2\n\nSpeichern wir Namen mehrfach (**Redundanz**), drohen **Anomalien**: Ändert sich ein Name, muss er überall geändert werden - vergisst man eine Stelle, ist die Datenbank widersprüchlich.\n\nDie Lösung: **Aufteilen** in mehrere Tabellen mit Beziehungen:\n\n- **Primärschlüssel** (PK): identifiziert jeden Datensatz eindeutig (id)\n- **Fremdschlüssel** (FK): verweist auf den Primärschlüssel einer anderen Tabelle (kunde_id)\n\nSo entsteht das **relationale Modell** aus dem OO-Datenmodell: Jede Klasse wird zur Tabelle, Beziehungen laufen über Schlüssel."],
                ['type' => 'code', 'title' => 'Live-Demo: Verknüpfte Tabellen', 'lang' => 'sql', 'code' => "CREATE TABLE klasse (\n    id INTEGER PRIMARY KEY,\n    name TEXT\n);\n\nCREATE TABLE schueler (\n    id INTEGER PRIMARY KEY,\n    name TEXT,\n    klasse_id INTEGER REFERENCES klasse (id)\n);\n\nINSERT INTO klasse (id, name) VALUES (1, '10G');\nINSERT INTO schueler (id, name, klasse_id) VALUES (1, 'Nele', 1);"],
                ['type' => 'text', 'title' => 'JOIN: Abfrage über zwei Tabellen', 'body' => "## JOIN\n\nEin **JOIN** führt zwei Tabellen für eine Abfrage zusammen - verbunden über den Fremdschlüssel:\n\n```sql\nSELECT schueler.name, klasse.name\nFROM schueler\nJOIN klasse ON schueler.klasse_id = klasse.id;\n```\n\nDas Präfix (schueler./klasse.) sagt, aus welcher Tabelle die Spalte kommt.\n\nIm **Shop** aus der SQL-Werkstatt gilt das genauso: Bestellungen und Kunden sind über `kunde_id` verbunden."],
                ['type' => 'text', 'title' => 'Übung: JOIN im Shop', 'body' => "**Übung: Bestellungen mit Kundennamen**\n\nÖffne deine **Shop-Datenbank** in der SQL-Werkstatt:\n\n1. Zeige zu jeder Bestellung den **Vor- und Nachnamen** des Kunden und das **Datum** (JOIN über `kunde_id`).\n2. Zeige zu jeder Bestellposition das **Produkt** und die **Menge** (JOIN über `produkt_id`).\n3. Bonus: Berechne den **Gesamtwert** aller Bestellungen (Menge mal Preis, SUM).\n\nTipp: Erst die kleine Abfrage schreiben, dann Schritt für Schritt erweitern."],
                ['type' => 'code', 'title' => 'Lösung: JOIN im Shop', 'lang' => 'sql', 'code' => "-- 1) Bestellungen mit Kundennamen\nSELECT k.vorname, k.nachname, b.datum\nFROM bestellungen b\nJOIN kunden k ON k.id = b.kunde_id;\n\n-- 2) Positionen mit Produkt und Menge\nSELECT p.name, bp.menge\nFROM bestellpositionen bp\nJOIN produkte p ON p.id = bp.produkt_id;\n\n-- 3) Gesamtwert\nSELECT SUM(bp.menge * p.preis) AS umsatz\nFROM bestellpositionen bp\nJOIN produkte p ON p.id = bp.produkt_id;"],
            ]],
            ['slug' => 'java-arrays', 'title' => 'Java: Arrays und Referenzen', 'level' => 10, 'v' => 2, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Viele Werte, eine Variable', 'body' => "# Arrays\n\nEin **Array** speichert **mehrere Werte desselben Typs** unter einem Namen:\n\n```java\nint[] noten = new int[4];\nnoten[0] = 1;\nnoten[1] = 2;\n```\n\nDer **Index** beginnt bei 0: `noten[0]` ist das erste Element, `noten[3]` das vierte - `noten[4]` existiert nicht (**ArrayIndexOutOfBounds**).\n\n## Referenz\n\nDie Variable speichert nicht die Werte selbst, sondern eine **Referenz** (Adresse) auf das Array-Objekt im Speicher."],
                ['type' => 'code', 'title' => 'Live-Demo: Notendurchschnitt', 'lang' => 'java', 'code' => 'int[] noten = {1, 2, 3, 2};\n\nint summe = 0;\nfor (int i = 0; i < noten.length; i++) {\n    summe = summe + noten[i];\n}\n\ndouble schnitt = (double) summe / noten.length;\nSystem.out.println("Schnitt: " + schnitt);'],
                ['type' => 'text', 'title' => 'Referenzen verstehen', 'body' => "## Zwei Namen, ein Array\n\n```java\nint[] a = {1, 2, 3};\nint[] b = a;\nb[0] = 9;\nSystem.out.println(a[0]);  // 9!\n```\n\n`b = a` kopiert nicht das Array, sondern die **Referenz**: a und b zeigen auf **dasselbe** Objekt. Änderungen über b sind auch über a sichtbar - ein häufiger Fehler.\n\n## Objekte genauso\n\nGilt auch für Objekte: `Schueler s2 = s1;` - beide Namen zeigen auf **einen** Schüler."],
                ['type' => 'text', 'title' => 'Übung: Bester Lauf', 'body' => "**Übung: Kleinste Zeit finden**\n\nGegeben: `int[] zeiten = {312, 287, 305, 298};` (Rundenzeiten in Sekunden)\n\n1. Schreibe eine Methode `besteZeit(int[] zeitenStart)`, die die **kleinste** Zeit zurückgibt.\n2. Drucke zusätzlich, an welchem **Index** die beste Zeit liegt.\n3. Erkläre deinem Nachbarn: Warum startet man bei Index 1 statt 0?\n\nTipp: Starte mit `zeitenStart[0]` als vorläufigem Bestwert und vergleiche in einer Schleife."],
                ['type' => 'code', 'title' => 'Lösung: besteZeit', 'lang' => 'java', 'code' => 'int besteZeit(int[] zeitenStart) {\n    int beste = zeitenStart[0];\n    int index = 0;\n    for (int i = 1; i < zeitenStart.length; i++) {\n        if (zeitenStart[i] < beste) {\n            beste = zeitenStart[i];\n            index = i;\n        }\n    }\n    System.out.println("Beste Zeit liegt bei Index " + index);\n    return beste;\n}\n\nint[] zeiten = {312, 287, 305, 298};\nSystem.out.println("Beste Zeit: " + besteZeit(zeiten));'],
            ]],
            ['slug' => 'java-datenkapselung', 'title' => 'Java: Datenkapselung mit private', 'level' => 10, 'v' => 2, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Warum Kapselung?', 'body' => "# Datenkapselung\n\nBisher konnte jeder einfach `konto.stand = -99999;` schreiben. **Datenkapselung** schützt:\n\n- Attribute werden `private`: nur die **eigene Klasse** darf direkt darauf zugreifen\n- Zugriff von außen nur über **öffentliche Methoden**: `getKontostand()`, `einzahlen(betrag)`\n\n## Vorteile\n\n- **Kontrolle**: `einzahlen()` kann negative Beträge ablehnen\n- **Änderbar**: Internes kann geändert werden, ohne dass Nutzer der Klasse etwas merken\n- **Verständlich**: Die Methoden sagen, was erlaubt ist"],
                ['type' => 'code', 'title' => 'Live-Demo: Klasse Konto', 'lang' => 'java', 'code' => 'class Konto {\n    // Attribute (privat!)\n    private String inhaber;\n    private double stand;\n\n    // Konstruktor\n    Konto(String inhaberStart, double standStart) {\n        inhaber = inhaberStart;\n        stand = standStart;\n    }\n\n    // eigene Methode: kontrollierter Zugriff\n    void einzahlen(double betrag) {\n        if (betrag > 0) {\n            stand = stand + betrag;\n        }\n    }\n\n    // Lesemethode (getter)\n    double getKontostand() {\n        return stand;\n    }\n\n    // Schreibmethode (setter)\n    void setInhaber(String inhaberNeu) {\n        inhaber = inhaberNeu;\n    }\n}\n\nKonto k = new Konto("Lena", 100);\nk.einzahlen(50);\nSystem.out.println("Neuer Stand: " + k.getKontostand());'],
                ['type' => 'text', 'title' => 'Übung: Noten sicher kapseln', 'body' => "**Übung: Schueler mit Prüfung kapseln**\n\n1. Baue die Klasse **Schueler** mit **private** Attributen `name` (String) und `note` (double), Konstruktor mit ...Start.\n2. Schreibe `getName()`, `setName(String nameNeu)` und `setNote(double noteNeu)`.\n3. `setNote` lehnt Noten außerhalb von **1 bis 6** ab - es passiert dann einfach nichts.\n4. Teste: Was passiert bei `setNote(99)`? Die Note darf sich **nicht** ändern.\n\nNaming: Setter-Parameter enden auf **Neu**."],
                ['type' => 'code', 'title' => 'Lösung: Schueler kapseln', 'lang' => 'java', 'code' => 'class Schueler {\n    // Attribute (privat!)\n    private String name;\n    private double note;\n\n    // Konstruktor\n    Schueler(String nameStart, double noteStart) {\n        name = nameStart;\n        note = noteStart;\n    }\n\n    // Lesemethode (getter)\n    String getName() {\n        return name;\n    }\n\n    // Schreibmethode (setter) mit Pruefung\n    void setNote(double noteNeu) {\n        if (noteNeu >= 1 && noteNeu <= 6) {\n            note = noteNeu;\n        }\n    }\n}\n\nSchueler s = new Schueler("Jonas", 2.3);\ns.setNote(99);\nSystem.out.println("Name bleibt: " + s.getName());'],
            ]],
            ['slug' => 'java-polymorphismus', 'title' => 'Java: Überschreiben und Polymorphismus', 'level' => 10, 'v' => 2, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Dynamische Bindung', 'body' => "# Polymorphismus\n\nEin Objekt einer **Unterklasse** darf in einer Variable des **Oberklassen-Typs** liegen:\n\n```java\nTier t = new Hund(\"Bello\");\nt.gibLaut();  // bellt!\n```\n\nJava ruft **immer die Methode des konkreten Objekts** auf (**dynamische Bindung**) - obwohl t vom Typ Tier ist, bellt es.\n\n## Warum ist das mächtig?\n\nMan sammelt Tier-Objekte in einem Array - Hunde, Katzen, Papageien - und ruft für alle `gibLaut()` auf. Eine Methode, **viele Erscheinungsformen**: Poly-morphismus."],
                ['type' => 'code', 'title' => 'Live-Demo: Das Tierheim-Gehege', 'lang' => 'java', 'code' => '// Klassen Tier, Hund, Katze, Papagei wie in der Vererbungs-Stunde\n\nTier[] gehege = new Tier[3];\ngehege[0] = new Hund("Bello");\ngehege[1] = new Katze("Mimi");\ngehege[2] = new Papagei("Rio");\n\nfor (int i = 0; i < gehege.length; i++) {\n    gehege[i].gibLaut();\n}'],
                ['type' => 'text', 'title' => 'Übung: Neues Tier im Gehege', 'body' => "**Übung: Gehege erweitern**\n\n1. Schreibe eine weitere Unterklasse von Tier, z. B. **Kuh**: Konstruktor mit `nameStart`, `@Override gibLaut()`.\n2. Vergrößere das gehege-Array auf 4 Tiere und füge deine Kuh hinzu.\n3. Erkläre deinem Nachbarn: Warum reicht **eine** Schleife für alle Tiere - ganz ohne if-Abfrage?\n\nBonus: Was passiert, wenn eine Unterklasse `gibLaut()` **nicht** überschreibt?"],
                ['type' => 'code', 'title' => 'Lösung: Kuh im Gehege', 'lang' => 'java', 'code' => 'class Kuh extends Tier {\n    // Konstruktor\n    Kuh(String nameStart) {\n        super(nameStart);\n    }\n\n    // eigene Methode (ueberschreibt Tier)\n    @Override\n    void gibLaut() {\n        System.out.println(name + " muht: Muh!");\n    }\n}\n\nTier[] gehege = new Tier[4];\ngehege[3] = new Kuh("Berta");\ngehege[3].gibLaut();'],
            ]],
            ['slug' => 'projekt', 'title' => 'Projekt: Von der Idee zur Dokumentation', 'level' => 10, 'v' => 1, 'legacy' => [], 'slides' => [
                ['type' => 'text', 'title' => 'Die fünf Projektphasen', 'body' => "# Das Projekt\n\nIn der 10. Klasse realisiert ihr ein **Projekt im Team oder in Einzelarbeit**: ein **Java-Programm mit Klassendiagrammen** oder eine **Datenbankanwendung**.\n\n## Die fünf Phasen\n\n1. **Planung**: Ziel festlegen, Aufgaben verteilen, Zeit planen\n2. **Modellierung**: Klassen bzw. Tabellen entwerfen (Diagramm!)\n3. **Implementierung**: Code schreiben - in der Code-Werkstatt\n4. **Test**: Läuft es? Grenzfälle? Fehler dokumentieren\n5. **Dokumentation**: Was habt ihr gebaut, wie funktioniert es?\n\nAm Ende: **Vorstellung** eures Projekts vor der Klasse."],
                ['type' => 'text', 'title' => 'Gut geplant ist halb gewonnen', 'body' => "## Tipps für jedes Team\n\n- **Rollenkarten**: Wer macht was (Modellierung, Code, Test, Doku)? Rollen können wechseln.\n- **Meilensteine**: Setzt 3-4 Checkpoints mit klarem Ergebnis.\n- **Alles festhalten**: Entscheidungen und ihre Gründe - ihr braucht sie bei der Vorstellung.\n\n## Bewährte Projektideen\n\n- Java: Tierheim-Verwaltung (Vererbung + Datenkapselung)\n- Java: Quiz mit Fragen-Verwaltung (Arrays + Klassen)\n- Datenbank: Bibliothek oder Vereinsverwaltung (JOIN + Rechte)"],
                ['type' => 'text', 'title' => 'Übung: Euer Projektplan', 'body' => "**Übung: Der Projektplan für dein Team**\n\n1. Einigt euch auf eine **Projektidee** (Java oder Datenbank).\n2. Schreibt einen **Projektplan** in euer Heft: Ziel, Teamrollen, 4 Meilensteine mit Terminen, grobe Modellierung (Klassen bzw. Tabellen als Skizze).\n3. Legt fest, **wann** ihr testet und was dabei geprüft wird.\n\nNächste Stunde: Vorstellung des Plans in **2 Minuten**."],
                ['type' => 'text', 'title' => 'Lösung: Musterprojektplan', 'body' => "**Muster: Tierheim-Verwaltung**\n\n- **Ziel**: Verwaltung mit Tieren (Tier, Hund, Katze, Papagei), Zuweisung zu Gehegen, Menü mit Auswahl\n- **Rollen**: Modellierung (Klassendiagramm), Implementierung (2 Personen), Test + Dokumentation\n- **Meilensteine**: 1. Klassendiagramm freigegeben, 2. Klassen laufen (Konsolenausgabe), 3. Menü + Testläufe, 4. Doku + Vorstellung\n- **Testliste**: leeres Gehege, Tier ohne Name, viele Tiere gleichzeitig\n\nDer Plan passt auf eine Seite - das ist ein gutes Maß."],
            ]],
            ['slug' => 'java-datenbank', 'title' => 'Java trifft Datenbank: die Klasse Datenbank', 'level' => 10, 'v' => 1, 'legacy' => ['Java trifft Datenbank: die Klasse Datenbank'], 'slides' => [
                ['type' => 'text', 'title' => 'SQL direkt in Java', 'body' => "In der Code-Werkstatt gibt es die eingebaute Klasse **Datenbank**.\n\n## Die wichtigsten Methoden\n- `Datenbank.ausfuehren(sql)` - Tabelle anlegen, Daten einfügen, ändern, löschen\n- `Datenbank.abfrage(sql)` - SELECT als formatierte Tabelle\n- `Datenbank.anzahl(sql)` - Zeilen zählen\n- `Datenbank.wert(sql)` - genau einen Wert holen\n- `Datenbank.tabellen()` - alle Tabellen zeigen\n\nDie Datenbank ist pro Programmlauf neu: kein Server, kein Setup."],
                ['type' => 'code', 'title' => 'Live-Demo: Datenbank in Java ansteuern', 'lang' => 'java', 'code' => 'Datenbank.ausfuehren("CREATE TABLE schueler (id, name, klasse, note)");\n\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (1, \'Lena\', \'10G\', 1.7)");\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (2, \'Jonas\', \'10G\', 2.3)");\n\nSystem.out.println(Datenbank.tabellen());\nSystem.out.println(Datenbank.abfrage("SELECT name, note FROM schueler ORDER BY note"));\n\nint gesamt = Datenbank.anzahl("SELECT * FROM schueler");\nSystem.out.println("Schueler insgesamt: " + gesamt);\n\nString beste = Datenbank.wert("SELECT name FROM schueler ORDER BY note LIMIT 1");\nSystem.out.println("Beste Note hat: " + beste);'],
                ['type' => 'text', 'title' => 'Übung: Bücher-Verwaltung in Java', 'body' => "**Übung: Eigene Tabelle mit der Klasse Datenbank**\n\nNutze die Klasse **Datenbank** in der Code-Werkstatt:\n\n1. Lege die Tabelle **buecher** an (id, titel, autor, jahr).\n2. Füge 3 Bücher ein (`Datenbank.ausfuehren`).\n3. Gib alle Bücher sortiert nach Titel aus (`Datenbank.abfrage`).\n4. Zähle die Bücher mit `Datenbank.anzahl`.\n\nTipp: Erst die SQL-Anweisung in der **SQL-Werkstatt** testen, dann in Java einbauen."],
                ['type' => 'code', 'title' => 'Lösung: Bücher in Java', 'lang' => 'java', 'code' => 'Datenbank.ausfuehren("CREATE TABLE buecher (id, titel, autor, jahr)");\nDatenbank.ausfuehren("INSERT INTO buecher (id, titel, autor, jahr) VALUES (1, \'Java lernen\', \'Ada Lovelace\', 2024)");\nDatenbank.ausfuehren("INSERT INTO buecher (id, titel, autor, jahr) VALUES (2, \'SQL fuer Einsteiger\', \'Grace Hopper\', 2023)");\n\nSystem.out.println(Datenbank.abfrage("SELECT titel, autor FROM buecher ORDER BY titel"));\n\nint gesamt = Datenbank.anzahl("SELECT * FROM buecher");\nSystem.out.println("Buecher: " + gesamt);'],
            ]],
        ];
    }

    /**
     * Präsentationen anlegen bzw. ältere Seed-Versionen aktualisieren. Idempotent:
     * Existierende Zeilen mit Slug bleiben unangetastet - außer der Admin hat noch
     * die unveränderte Seed-Version (seed_version kleiner als 'v'); dann werden
     * nur die Startpakete des Admin auf den neuen Folienstand gebracht. Von
     * Lehrkräften bearbeitete Kopien werden nie überschrieben. Alte Titel ohne
     * Slug werden erkannt und inklusive neuem Slug nachgezogen.
     */
    private static function seedPresentations(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['matthias.glienke']);
        $adminId = (int) $stmt->fetchColumn();
        if ($adminId === 0) {
            return;
        }

        $findBySlug = $pdo->prepare('SELECT id, seed_version, teacher_id FROM presentations WHERE slug = ?');
        $findByLegacy = $pdo->prepare('SELECT id FROM presentations WHERE title = ? AND slug IS NULL');
        $insert = $pdo->prepare('INSERT INTO presentations (teacher_id, title, slug, level, content, position, seed_version) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $update = $pdo->prepare('UPDATE presentations SET title = ?, slug = ?, level = ?, content = ?, seed_version = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $updateContent = $pdo->prepare('UPDATE presentations SET title = ?, level = ?, content = ?, seed_version = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');

        $position = 0;
        foreach (self::presentationSeeds() as $seed) {
            $content = json_encode(self::normalizeSeedSlides($seed['slides']), JSON_UNESCAPED_UNICODE) ?: '[]';
            $position += 1;

            // Aktuelle Seed-Version existiert bereits: nichts tun (auch eigene Folien-Aenderungen bleiben).
            $findBySlug->execute([$seed['slug']]);
            $existing = $findBySlug->fetch();
            if ($existing !== false) {
                // Nur unveränderte Admin-Startpakete werden auf den neuen Stand gebracht.
                if ((int) $existing['seed_version'] < (int) $seed['v'] && (int) $existing['teacher_id'] === $adminId) {
                    $updateContent->execute([$seed['title'], $seed['level'], $content, (int) $seed['v'], (int) $existing['id']]);
                }
                continue;
            }

            // Aeltere Seed-Version (ohne Slug) aktualisieren: neuer Titel, Slug, Stufe, Folien.
            $matched = false;
            foreach ($seed['legacy'] as $legacyTitle) {
                $findByLegacy->execute([$legacyTitle]);
                $legacyId = $findByLegacy->fetchColumn();
                if ($legacyId !== false) {
                    $update->execute([$seed['title'], $seed['slug'], $seed['level'], $content, (int) $seed['v'], (int) $legacyId]);
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }

            $insert->execute([$adminId, $seed['title'], $seed['slug'], $seed['level'], $content, $position, (int) $seed['v']]);
        }
    }

    /**
     * Code-Folien der Seeds notieren Zeilenumbrüche als \n (lesbarer in den
     * PHP-Quelltexten); vor dem Speichern werden sie in echte Umbrüche
     * übersetzt, damit der Präsentationsmodus sauberen Code zeigt.
     * @param list<array<string, string>> $slides
     * @return list<array<string, string>>
     */
    private static function normalizeSeedSlides(array $slides): array
    {
        foreach ($slides as $index => $slide) {
            if (($slide['type'] ?? '') === 'code') {
                $slide['code'] = str_replace('\\n', "\n", (string) ($slide['code'] ?? ''));
            }
            $slides[$index] = $slide;
        }
        return $slides;
    }

    /** Markdown-lite-Übersicht aller Themen-Präsentationen mit permanenten Links. */
    private static function wikiPresentationIndex(): string
    {
        $text = "# Präsentationen: Themenübersicht\n\nDie **Informatik-Jahr-Decks** (Jgst. 9 und 10) stimmen aufs Schuljahr ein - Motivation und Ausblick, ohne Übung. Die **Themenpräsentationen** vertiefen danach jeden Lernbereich und enden mit einer **Übungsaufgabe samt Musterlösung** - so kannst du das Durchgenommene sofort üben. Alle Decks, gruppiert nach Jahrgangsstufe:\n\n";
        $currentLevel = 0;
        foreach (self::presentationSeeds() as $seed) {
            if ($seed['level'] !== $currentLevel) {
                $currentLevel = $seed['level'];
                $text .= "## Jahrgangsstufe " . $currentLevel . "\n\n";
            }
            $text .= "- [" . $seed['title'] . "](/praesentationen/thema/" . $seed['slug'] . ")\n";
        }
        $text .= "\nDie Links bleiben dauerhaft gültig. In der Präsentation: Taste **F** für Vollbild, Pfeiltasten zum Blättern; als Lehrkraft kannst du Code-Folien live verändern und speichern.";
        return $text;
    }

    /** Text der Wiki-Anleitung für Lehrkräfte (Markdown-lite). */
    private static function wikiTeacherGuide(): string
    {
        return "# Anleitung für Lehrkräfte\n\nWillkommen bei **InfoCraft**! Diese Seite fasst die wichtigsten Arbeitsschritte zusammen. Du kannst sie hier bearbeiten und um eigene Hinweise ergänzen.\n\n## Erste Schritte\n\n1. Anmelden über die Startseite.\n2. Unter **Nutzer** Konten für Schülerinnen und Schüler anlegen oder per **CSV-Import** ganze Klassen einspielen.\n3. Unter **Meine Klassen** deinem Konto Klassen zuordnen: Du siehst dann nur deine Klassen und deine Schülerinnen und Schüler.\n\n## Module passend zum Lehrplan freigeben\n\nUnter **Meine Klassen** legst du für jede Klasse fest, welche Arbeitsbereiche sichtbar sind:\n\n- **Standard**: Der LehrplanPLUS-Stufe des Moduls folgt (z. B. SQL erst ab Jgst. 9).\n- **Freigeben**: Die Klasse sieht das Modul früher.\n- **Sperren**: Die Klasse sieht das Modul nicht.\n\nDas gilt auch für die direkte URL: gesperrte Module sind nicht aufrufbar.\n\n## Arbeiten austeilen\n\n- **Code-Werkstatt**: Projekte anlegen und an Klassen oder Einzelne austeilen; über die Schüleransicht erreichst du die Projekte deiner Schülerinnen und Schüler.\n- **SQL-Werkstatt**: Vorlagen (Datenbanken) ausgeben mit Rechten für *nur lesen*, *lesen und schreiben* oder *Tabellen anlegen*.\n- **Aufgabenpool**: eigene Aufgaben anlegen oder fertige Aufgaben (Jgst. 6 bis 13) aus dem Katalog übernehmen und austeilen.\n\n## Präsentieren und Nachschlagen\n\n- **Wiki**: Diese Nachschlage-Seiten hier. Der Stufen-Filter blendet Seiten aus, die über der Klassenstufe liegen.\n- **Präsentationen**: Folien aus Text und Code, kompakt gruppiert nach Jahrgangsstufe (Jgst. 6 bis 10). Im Präsentationsmodus:\n    - **Vollbild** über den Knopf oder Taste **F**\n    - **Pfeiltasten** oder Leertaste blättern\n    - Code-Folien lassen sich über *Code verändern* direkt während der Präsentation bearbeiten und speichern\n- **Moderatorenbildschirm** (nur für Lehrkräfte): Der Knopf **Moderator** öffnet eine Steuerzentrale mit aktueller und nächster Folie, Folienliste, Timer und Uhr. Öffne sie am Laptop und die Präsentationsansicht im Vollbild auf dem Beamer - beide Fenster laufen im selben Browser synchron mit (Pfeiltasten steuern beide).\n\n## Prüfungen und Schularbeiten\n\nUnter **Prüfungen & Arbeiten** legst du Gruppen mit eigenen Vorlagen an und startest die Prüfung. Während der Prüfung sind für die betroffene Klasse alle anderen Bereiche gesperrt - andere Klassen arbeiten normal weiter. Korrektur mit Punkten und Noten, optional anonymisiert; Schularbeiten ohne Sperre sind ebenfalls möglich.";
    }
}

