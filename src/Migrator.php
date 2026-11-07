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
        self::ensureTaskOrganizationTables($pdo, $driver);
        self::seedTaskTopics($pdo);

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
            self::seedTenthGradeQuizzes($pdo);
            self::seedDemoExams($pdo);
            self::seedTenthGradeExams($pdo);
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
     * Kapitel-Quizze fuer die Jahrgangsstufe 10 (Informatik, Gymnasium Bayern):
     * Zu jedem Präsentations-Deck der 10. Klasse ein fertiges Quiz in der
     * Quiz-Arena (Verwaltung NUR fuer Lehrkraefte). Wird nur angelegt, wenn
     * ein Quiz mit dem Titel noch nicht existiert (idempotent, auch fuer
     * Bestandsinstallationen). Austeilbereit, aber bewusst NICHT ausgeteilt.
     */
    private static function seedTenthGradeQuizzes(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['stefan.schmidt']);
        $stefanId = (int) $stmt->fetchColumn();
        if ($stefanId === 0) {
            return;
        }

        $exists = $pdo->prepare('SELECT COUNT(*) FROM quizzes WHERE title = ?');
        $insertQuiz = $pdo->prepare('INSERT INTO quizzes (teacher_id, title, description, level) VALUES (?, ?, ?, 10)');
        $insertQuestion = $pdo->prepare('INSERT INTO quiz_questions (quiz_id, position, question, answer_1, answer_2, answer_3, answer_4, correct) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

        $quizzes = [
            ['Quiz: Redundanz und Anomalien', 'Kapitel-Quiz zum Deck "Datenbanken: Redundanz, Inkonsistenz und Anomalien". Austeilbereit in der Quiz-Arena.', [
                ['Was bedeutet Redundanz in einer Datenbank?', 'Zwei verschiedene Tabellen mit gleichem Namen', 'Dieselbe Information wird mehrfach gespeichert', 'Ein Schreibfehler im Datensatz', 'Ein zu langer Spaltenname', 2],
                ['Eine Adresse ist in fuenf Zeilen gespeichert; aendert man sie nur in vier, liegt eine ... vor.', 'Einfuegeanomalie', 'Loeschanomalie', 'Aenderungsanomalie', 'Kardinalitaet', 3],
                ['Was ist Inkonsistenz?', 'Die Datenbank ist zu langsam', 'Zwei Tabellen haben keine Beziehung', 'Abweichende Kopien derselben Information widersprechen sich', 'Der Primaerschluessel fehlt', 3],
                ['Wie loest man Redundanz am besten?', 'Alles in eine grosse Tabelle schreiben', 'Information auf mehrere Tabellen mit Beziehungen aufteilen', 'Oefters die Datenbank sichern', 'Spalten loeschen, bis es passt', 2],
                ['Was ist eine Loeschanomalie?', 'Ein Datensatz laesst sich nicht loeschen', 'Beim Loeschen des letzten Schuelers verschwindet der ganze Kurs', 'Das Programm stuerzt beim Loeschen ab', 'Der Fremdschluessel wird mitgeloescht', 2],
            ]],
            ['Quiz: 1:n- und n:m-Beziehungen', 'Kapitel-Quiz zum Deck "Datenmodellierung: 1:n- und n:m-Beziehungen planen". Austeilbereit in der Quiz-Arena.', [
                ['Wo steht der Fremdschluessel bei einer 1:n-Beziehung?', 'Auf der 1-Seite', 'Auf der n-Seite', 'In einer dritten Tabelle', 'Im Primaerschluessel der 1-Seite', 2],
                ['Was beschreibt die Kardinalitaet?', 'Die Laenge einer Spalte', 'Wie viele Datensaetze der anderen Seite verbunden sein duerfen', 'Die Anzahl der Zeilen einer Tabelle', 'Den Typ einer Spalte', 2],
                ['Wie setzt man eine n:m-Beziehung um?', 'Mit einer Spalte mit Kommas', 'Mit zwei Tabellen und einem JOIN', 'Mit einer Hilfstabelle aus beiden Fremdschluesseln', 'Gar nicht, das ist verboten', 3],
                ['Was ist der Primaerschluessel einer Hilfstabelle bei n:m?', 'Nur der erste Fremdschluessel', 'Nur der zweite Fremdschluessel', 'Beide Fremdschluessel zusammen', 'Eine eigene neue id-Spalte ist Pflicht', 3],
                ['Welches Beispiel ist typisch n:m?', 'Klasse und Schueler', 'Schueler und Kurse', 'Schueler und Ausweis', 'Bundesland und Landeshauptstadt', 2],
            ]],
            ['Quiz: JOINs und Fremdschluessel', 'Kapitel-Quiz zum Deck "Datenbanken Level 2: Primaer-/Fremdschluessel und JOIN". Austeilbereit in der Quiz-Arena.', [
                ['Was bewirkt die ON-Klausel bei einem JOIN?', 'Sortiert das Ergebnis', 'Legt fest, welche Zeilen der Tabellen zusammengehoeren', 'Loescht doppelte Zeilen', 'Zaehlt die Zeilen', 2],
                ['Was macht ein Primaerschluessel?', 'Verweist auf eine andere Tabelle', 'Identifiziert jeden Datensatz eindeutig', 'Sortiert die Tabelle', 'Verschluesselt die Daten', 2],
                ['Was liefert ein JOIN?', 'Nur Zeilen der linken Tabelle', 'Verbundene Zeilen aus beiden Tabellen', 'Nur Spaltennamen', 'Eine neue Tabelle im Speicher, die gespeichert werden muss', 2],
                ['Was bedeutet das Praefix in schueler.name?', 'Die Spalte gehoert zur Tabelle schueler', 'Der Schueler heisst "name"', 'Eine Sortierung nach Name', 'Ein Alias fuer die Abfrage', 1],
                ['Im Shop verweist bestellungen.kunde_id wohin?', 'Auf kunden.id', 'Auf produkte.id', 'Auf bestellungen.id', 'Auf eine Hilfstabelle', 1],
            ]],
            ['Quiz: Arrays und Referenzen', 'Kapitel-Quiz zum Deck "Java: Arrays und Referenzen". Austeilbereit in der Quiz-Arena.', [
                ['Mit welchem Index greift man auf das ERSTE Element zu?', '1', '0', '-1', 'length', 2],
                ['Was liefert noten.length?', 'Den letzten Index', 'Die Anzahl der Elemente', 'Die Summe aller Werte', 'Die Laenge des laengsten Elements', 2],
                ['Was bewirkt int[] b = a; ?', 'Eine echte Kopie des Arrays', 'b zeigt auf dasselbe Array wie a (Referenz)', 'b bekommt ein leeres Array', 'Der Code erzeugt einen Fehler', 2],
                ['Nach b[0] = 9; was gibt a[0] aus?', 'Den alten Wert', '9, denn beide zeigen auf dasselbe Array', '0', 'Einen Fehler', 2],
                ['Wann tritt eine ArrayIndexOutOfBounds-Ausnahme auf?', 'Wenn das Array null ist', 'Beim Zugriff auf einen Index ausserhalb des gueltigen Bereichs', 'Bei zu grossen Werten', 'Wenn man length aufruft', 2],
            ]],
            ['Quiz: Datenkapselung', 'Kapitel-Quiz zum Deck "Java: Datenkapselung mit private". Austeilbereit in der Quiz-Arena.', [
                ['Was bewirkt private bei einem Attribut?', 'Nur die eigene Klasse darf direkt darauf zugreifen', 'Das Attribut wird geloescht', 'Nur Lehrkraefte duerfen es lesen', 'Das Attribut ist immer 0', 1],
                ['Was ist ein Getter?', 'Eine Methode zum Lesen eines privaten Attributs', 'Ein Fehler im Programm', 'Ein Konstruktor ohne Parameter', 'Eine Schleife ueber alle Attribute', 1],
                ['setNote(double noteNeu) prueft auf 1 bis 6: Was passiert bei setNote(99)?', 'Die Note wird 99', 'Das Programm stuerzt ab', 'Die Methode lehnt den Wert ab, die Note bleibt unveraendert', 'Das Attribut wird auf null gesetzt', 3],
                ['Was ist ein zentraler Vorteil der Datenkapselung?', 'Schnellere Programme', 'Kontrolle: ungueltige Werte koennen abgelehnt werden', 'Weniger Speicherbedarf', 'Man braucht keine Konstruktoren mehr', 2],
                ['Wie heissen Setter-Parameter in diesem Kurs ueblicherweise?', 'nameStart', 'nameNeu', 'nameAlt', 'x', 2],
            ]],
            ['Quiz: Vererbung und Polymorphismus', 'Kapitel-Quiz zum Deck "Java: Ueberschreiben und Polymorphismus". Austeilbereit in der Quiz-Arena.', [
                ['Tier t = new Hund("Bello"); t.gibLaut(); fuehrt zu ...', 'einem Fehler', 'der gibLaut()-Methode von Hund (dynamische Bindung)', 'der gibLaut()-Methode von Tier', 'gar nichts', 2],
                ['Was bedeutet @Override?', 'Die Methode der Oberklasse wird ueberschrieben', 'Die Methode ist privat', 'Die Methode wird geloescht', 'Die Klasse ist abstrakt', 1],
                ['Wozu dient super(nameStart) im Konstruktor einer Unterklasse?', 'Ein neues Attribut anlegen', 'Den Konstruktor der Oberklasse aufrufen', 'Die Methode ueberschreiben', 'Das Objekt loeschen', 2],
                ['Warum reicht EINE Schleife ueber ein Tier[] mit Hunden, Katzen und Papageien?', 'Weil alle Tiere gleich heissen', 'Weil die dynamische Bindung je Objekt die passende gibLaut()-Methode waehlt', 'Weil Java automatisch if-Abfragen einfuegt', 'Weil Tier[] nur Hunde zulaesst', 2],
                ['Eine Unterklasse ueberschreibt gibLaut() nicht. Was passiert beim Aufruf?', 'Fehler beim Kompilieren', 'Die Methode der Oberklasse wird ausgefuehrt (geerbt)', 'Es wird nichts ausgegeben', 'Java fragt zur Laufzeit nach', 2],
            ]],
            ['Quiz: Projekt in Informatik', 'Kapitel-Quiz zum Deck "Projekt: Von der Idee zur Dokumentation". Austeilbereit in der Quiz-Arena.', [
                ['Welche Reihenfolge der Projektphasen ist korrekt?', 'Implementierung, Planung, Test, Modellierung', 'Planung, Modellierung, Implementierung, Test, Dokumentation', 'Test, Planung, Dokumentation, Implementierung', 'Dokumentation, Modellierung, Planung, Test', 2],
                ['Was ist ein Meilenstein?', 'Ein Fehler im Projektplan', 'Ein Checkpoint mit klarem, ueberpruefbarem Ergebnis', 'Der Name des Projekts', 'Die Note am Ende', 2],
                ['Wozu dient eine Testliste?', 'Um Schueler zu vergleichen', 'Um systematisch zu pruefen, ob das Programm alles kann - auch Grenzfaelle', 'Um den Projektplan zu ersetzen', 'Um die Doku abzukuerzen', 2],
                ['Was gehoert in eine gute Projektdokumentation?', 'Nur der Code', 'Was gebaut wurde, wie es funktioniert und welche Entscheidungen getroffen wurden', 'Nur die Namen des Teams', 'Die Loesung der anderen Teams', 2],
                ['Was macht die Teamrolle "Test"?', 'Code schreiben', 'Grenzfaelle pruefen, Fehler dokumentieren und das Team informieren', 'Nur zuschauen', 'Die praesentation halten', 2],
            ]],
        ];

        foreach ($quizzes as [$title, $description, $questions]) {
            $exists->execute([$title]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
            $insertQuiz->execute([$stefanId, $title, $description]);
            $quizId = (int) $pdo->lastInsertId();
            foreach ($questions as $index => $question) {
                $insertQuestion->execute([$quizId, $index + 1, $question[0], $question[1], $question[2], $question[3], $question[4], $question[5]]);
            }
        }
    }

    /**
     * Zwei Kurzarbeiten fuer die Jahrgangsstufe 10 (Informatik, Gymnasium
     * Bayern) im Pruefungsmodus: KA 1 Datenbanken (LB1), KA 2 OOP (LB2).
     * Je vier Teilaufgaben mit je 5 BE (= 20 BE) und Musterloesung, als
     * Schularbeit ohne Sperre in der Phase Vorbereitung. Titel-Idempotenz.
     */
    private static function seedTenthGradeExams(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login_name = ?');
        $stmt->execute(['stefan.schmidt']);
        $stefanId = (int) $stmt->fetchColumn();
        if ($stefanId === 0) {
            return;
        }

        $exists = $pdo->prepare('SELECT COUNT(*) FROM exams WHERE title = ?');
        $insertExam = $pdo->prepare('INSERT INTO exams (teacher_id, kind, title, description, level, phase) VALUES (?, \'arbeit\', ?, ?, 10, \'vorbereitung\')');
        $insertTask = $pdo->prepare('INSERT INTO exam_tasks (exam_id, position, title, description, be, sample_solution) VALUES (?, ?, ?, ?, ?, ?)');

        $exams = [
            ['Kurzarbeit 1: Datenbanken (Informatik 10)', 'Kurzarbeit zum Lernbereich 1: Datenmodellierung und relationale Datenbanksysteme. 20 BE, Hilfsmittel: keine.', [
                [1, 'Beziehungen bestimmen', 'Ein Sportverein speichert Mitglieder, Sportarten und Trainer. Jedes Mitglied kann mehrere Sportarten belegen, jede Sportart hat viele Mitglieder. Ein Trainer leitet genau eine Sportart. Gib fuer alle drei Beziehungen die Kardinalitaet an (1:n oder n:m) und begruende kurz.', 5, 'Mitglied-Sportart: n:m, denn jedes Mitglied belegt viele Sportarten und jede Sportart hat viele Mitglieder. Trainer-Sportart: 1:n (bzw. Trainer 1:1 zu seiner Sportart, Sportart zu Trainer 1:1 bzw. je Sportart genau ein Trainer) - Kern: Trainer leitet genau eine Sportart. Begruendung ueber die Frage: Passt der Verweis in eine Spalte (1:1, 1:n-Fremdschluessel) oder braucht man eine Hilfstabelle (n:m)?'],
                [2, 'Tabellen entwerfen', 'Entwirf das Tabellenschema fuer den Verein: Tabellen mit Spalten, Primaerschluessel (PK) und Fremdschluessel (FK). Nutze eine Hilfstabelle fuer die n:m-Beziehung.', 5, 'mitglieder(id PK, name, vorname), sportarten(id PK, titel, trainer_name bzw. trainer_id FK), belegung(mitglied_id FK, sportart_id FK, PK aus beiden Spalten). Bewertung: korrekte PKs (2 BE), FK auf der n-Seite bzw. in der Hilfstabelle (2 BE), keine Redundanz (1 BE).'],
                [3, 'JOIN-Abfrage schreiben', 'Gegeben: kunden(id, vorname, nachname) und bestellungen(id, kunde_id, datum). Schreibe eine SQL-Abfrage, die zu jeder Bestellung Vorname, Nachname und Datum anzeigt.', 5, "SELECT k.vorname, k.nachname, b.datum FROM bestellungen b JOIN kunden k ON k.id = b.kunde_id;  Bewertung: korrektes SELECT der drei Spalten (2 BE), JOIN mit ON ueber kunde_id (2 BE), saubere Praefixe/Aliase (1 BE)."],
                [4, 'Anomalien erkennen', 'In einer Tabelle wird fuer jede Bestellung der Kundenname komplett mitgespeichert. Nenne zwei Anomalie-Typen, die hier auftreten koennen, und erklaere je ein Beispiel. Wie loest man das Problem?', 5, 'Aenderungsanomalie: Kunde zieht um, Name muss in jeder seiner Bestellzeilen geaendert werden - vergisst man eine, ist die Datenbank inkonsistent. Loeschanomalie: Loescht man die letzte Bestellung eines Kunden, verschwindet der Kunde komplett. Loesung: Kundendaten in eigene Tabelle auslagern und per Fremdschluessel (kunde_id) referenzieren - jede Information nur einmal speichern.'],
            ]],
            ['Kurzarbeit 2: Objektorientierte Programmierung (Informatik 10)', 'Kurzarbeit zum Lernbereich 2: Objektorientierte Modellierung und Programmierung. 20 BE, Hilfsmittel: keine.', [
                [1, 'Klasse modellieren', 'Modelliere die Klasse Konto: zwei Attribute, ein Konstruktor mit ...Start-Parametern und zwei Methoden (einzahlen und Kontostand ausgeben). Gib die Klasse als Java-Code an.', 5, "class Konto { double stand; Konto(double standStart) { stand = standStart; } void einzahlen(double betrag) { if (betrag > 0) { stand = stand + betrag; } } void standAusgeben() { System.out.println(\"Kontostand: \" + stand); } }  Bewertung: Attribute (1 BE), Konstruktor (1 BE), Methode einzahlen (2 BE), Ausgabe (1 BE)."],
                [2, 'Datenkapselung', 'Die Klasse Schueler soll die private Note (double) schuetzen: notiere das Attribut private und schreibe setNote(double noteNeu), der Noten ausserhalb von 1 bis 6 ablehnt. Was ist der Vorteil dieser Kapselung?', 5, "private double note; void setNote(double noteNeu) { if (noteNeu >= 1 && noteNeu <= 6) { note = noteNeu; } }  Vorteil: ungueltige Werte koennen abgelehnt werden, das Objekt schuetzt sich selbst - Aenderungen am Innenleben brechen Nutzer der Klasse nicht. Bewertung: private (1 BE), Pruefung (2 BE), Vorteil erklaert (2 BE)."],
                [3, 'Array verarbeiten', 'Gegeben: int[] noten = {2, 1, 3, 2}; Schreibe eine Methode durchschnitt(int[] notenStart), die den Notendurchschnitt als double zurueckgibt. Achte auf die korrekte Division.', 5, "double durchschnitt(int[] notenStart) { int summe = 0; for (int i = 0; i < notenStart.length; i++) { summe = summe + notenStart[i]; } return (double) summe / notenStart.length; }  Bewertung: Schleife (2 BE), Summe (1 BE), Cast auf double fuer korrekte Division (2 BE)."],
                [4, 'Polymorphismus', 'Gegeben sind die Klassen Tier mit gibLaut() sowie die Unterklassen Hund und Katze, die gibLaut() ueberschreiben. Erklaere, was ein Tier[] gehege mit Hund und Katze bei gehege[i].gibLaut() ausgibt und warum. Wie nennt man dieses Prinzip?', 5, 'Es wird jeweils die Methode des konkreten Objekts aufgerufen: der Hund bellt, die Katze miaut - obwohl die Variable vom Typ Tier ist. Grund: dynamische Bindung, Java entscheidet zur Laufzeit anhand des echten Objekts. Das Prinzip heisst Polymorphismus ("viele Gestalten"). Bewertung: Ausgabe beschrieben (2 BE), dynamische Bindung erklaert (2 BE), Begriff genannt (1 BE).'],
            ]],
        ];

        foreach ($exams as [$title, $description, $tasks]) {
            $exists->execute([$title]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
            $insertExam->execute([$stefanId, $title, $description]);
            $examId = (int) $pdo->lastInsertId();
            foreach ($tasks as [$position, $taskTitle, $taskDescription, $be, $solution]) {
                $insertTask->execute([$examId, $position, $taskTitle, $taskDescription, $be, $solution]);
            }
        }
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
     * Aufgabenverwaltung 3: Tabellen für Themengebiete, Materialien und
     * ToDos idempotent nachziehen (CREATE IF NOT EXISTS, bestehende
     * Installationen, deren Schema-Datei noch älter war).
     */
    private static function ensureTaskOrganizationTables(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_topics (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                level INT NOT NULL DEFAULT 6,
                sort INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_topics_level (level)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_materials (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                task_id INT UNSIGNED NOT NULL,
                filename VARCHAR(200) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_materials_task (task_id),
                CONSTRAINT fk_materials_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_todos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                task_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NULL,
                template_id INT UNSIGNED NULL,
                title VARCHAR(300) NOT NULL,
                done TINYINT(1) NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_todos_task (task_id),
                KEY idx_todos_student (student_id),
                CONSTRAINT fk_todos_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
                CONSTRAINT fk_todos_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_topics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                level INTEGER NOT NULL DEFAULT 6,
                sort INTEGER NOT NULL DEFAULT 0
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
                filename TEXT NOT NULL,
                content TEXT NOT NULL,
                size INTEGER NOT NULL DEFAULT 0,
                sort INTEGER NOT NULL DEFAULT 0
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_todos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
                student_id INTEGER NULL REFERENCES users (id) ON DELETE CASCADE,
                template_id INTEGER NULL,
                title TEXT NOT NULL,
                done INTEGER NOT NULL DEFAULT 0,
                sort INTEGER NOT NULL DEFAULT 0
            )');
        }
        // Spalten an tasks nachziehen (beide Treiber, idempotent).
        $taskColumns = [];
        if ($driver === 'mysql') {
            foreach ($pdo->query('SHOW COLUMNS FROM tasks')->fetchAll() as $column) {
                $taskColumns[] = (string) ($column['Field'] ?? '');
            }
            $types = [
                'topic_id' => 'ALTER TABLE tasks ADD COLUMN topic_id INT UNSIGNED NOT NULL DEFAULT 0',
                'description_format' => "ALTER TABLE tasks ADD COLUMN description_format VARCHAR(8) NOT NULL DEFAULT 'text'",
                'variant_of' => 'ALTER TABLE tasks ADD COLUMN variant_of INT UNSIGNED NOT NULL DEFAULT 0',
                'solution_visible' => 'ALTER TABLE tasks ADD COLUMN solution_visible TINYINT(1) NOT NULL DEFAULT 0',
            ];
        } else {
            foreach ($pdo->query('PRAGMA table_info(tasks)')->fetchAll() as $column) {
                $taskColumns[] = (string) ($column['name'] ?? '');
            }
            $types = [
                'topic_id' => 'ALTER TABLE tasks ADD COLUMN topic_id INTEGER NOT NULL DEFAULT 0',
                'description_format' => "ALTER TABLE tasks ADD COLUMN description_format TEXT NOT NULL DEFAULT 'text'",
                'variant_of' => 'ALTER TABLE tasks ADD COLUMN variant_of INTEGER NOT NULL DEFAULT 0',
                'solution_visible' => 'ALTER TABLE tasks ADD COLUMN solution_visible INTEGER NOT NULL DEFAULT 0',
            ];
        }
        foreach ($types as $column => $statement) {
            if (!in_array($column, $taskColumns, true)) {
                $pdo->exec($statement);
            }
        }
        // Selbsteinschaetzung an Abgaben nachziehen (nur Lehrkraefte sehen sie).
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM task_submissions LIKE 'self_rating'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE task_submissions ADD COLUMN self_rating TINYINT UNSIGNED NOT NULL DEFAULT 0');
            }
        } else {
            $found = false;
            foreach ($pdo->query('PRAGMA table_info(task_submissions)')->fetchAll() as $column) {
                if ((string) ($column['name'] ?? '') === 'self_rating') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $pdo->exec('ALTER TABLE task_submissions ADD COLUMN self_rating INTEGER NOT NULL DEFAULT 0');
            }
        }
    }

    /**
     * Themengebiete kuratieren (nur bei leerer Tabelle), abgestimmt auf den
     * LehrplanPLUS Informatik Bayern: Gymnasium NTG ab Jgst. 8 (Algorithmen
     * und Programmierung, Datenmodellierung und relationale Datenbanken,
     * Objektorientierte Modellierung, Projekt), davor Pluskurse bzw.
     * Arbeitsgemeinschaften (z. B. Informationen darstellen, erste
     * Webseiten), Oberstufe Q11/Q12 (Datenstrukturen, Theoretische
     * Informatik, Softwareentwicklung). Eigene Themengebiete bleiben
     * unberührt, der Seed läuft nur einmal.
     */
    private static function seedTaskTopics(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM task_topics')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $topics = [
            [6, 'Informationen darstellen', 1],
            [6, 'Gut präsentieren', 2],
            [6, 'Erste Webseiten (HTML)', 3],
            [6, 'Algorithmen spielen und verstehen', 4],
            [7, 'Hypertext und Internet', 1],
            [7, 'Digitale Kommunikation', 2],
            [7, 'Webseiten mit HTML bauen', 3],
            [7, 'Algorithmen und Flussdiagramme', 4],
            [8, 'Algorithmen und Programmierung (Python)', 1],
            [8, 'Daten und Informationen', 2],
            [8, 'Informatik, Mensch und Gesellschaft', 3],
            [8, 'Projekt', 4],
            [9, 'Tabellenkalkulation und Funktionen', 1],
            [9, 'Java: Klassen und Objekte', 2],
            [9, 'Datenmodellierung und SQL', 3],
            [9, 'Datenschutz und Sicherheit', 4],
            [10, 'Relationale Datenbanken und JOINs', 1],
            [10, 'Objektorientierte Modellierung (OOP)', 2],
            [10, 'Java: Vererbung und Polymorphismus', 3],
            [10, 'Projekt Informatik', 4],
            [11, 'Algorithmen und Datenstrukturen', 1],
            [11, 'Datenbanken fortgeschritten', 2],
            [12, 'Softwareentwicklung und Projekte', 1],
            [12, 'Theoretische Informatik', 2],
            [13, 'Abiturvorbereitung Informatik', 1],
        ];
        $insert = $pdo->prepare('INSERT INTO task_topics (name, level, sort) VALUES (?, ?, ?)');
        foreach ($topics as [$level, $name, $sort]) {
            $insert->execute([$name, $level, $sort]);
        }
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
