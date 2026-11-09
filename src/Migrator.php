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

        // Bestehende Installationen: Spalten classes.diagram_auto und classes.db_panel idempotent nachziehen.
        self::ensureDiagramAutoColumn($pdo, $driver);
        self::ensureDbPanelColumn($pdo, $driver);
        self::ensureProjectImagesTable($pdo, $driver);

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
        // Issue #45: angehängte Beispieldatenbanken an Aufgaben und Ordnerbaum
        // (Unterordner) in Bestehensinstallationen idempotent nachziehen.
        self::ensureTaskDatabaseColumns($pdo, $driver);
        self::ensureFolderParentColumn($pdo, $driver);
        self::ensureSiteSettingsTable($pdo, $driver);
        self::ensureUserPrefsTable($pdo, $driver);
        self::ensureProjectStateTable($pdo, $driver);

        // Live-Abstimmung während der Präsentation (Issue #3): Tabellen
        // fehlen evtl. noch in Bestehensinstallationen, idempotent anlegen.
        self::ensurePollTables($pdo, $driver);
        // Dateninsel Kapitel 2 (Issue #7): Missionen 9 bis 12 und die
        // Bibliothek fehlen evtl. noch in Bestehensinstallationen.
        self::ensureAdventureChapter2($pdo);
        self::seedSiteSettings($pdo);
        self::seedTaskTopics($pdo);
        self::removeLevel8Topics($pdo);

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
        /* Idempotent je Slug (#45): Bestehende Installationen bekommen neue
         * Beispieldatenbanken nach, ohne dass alte Vorlagen angerührt werden. */
        $templates = [
            ['shop', 'Online-Shop', 'Kunden, Produkte und Bestellungen: von einfachen SELECTs bis zu JOINs und GROUP BY.', 9, 0],
            ['instacraft', 'InstaCraft', 'Profile, Posts, Likes und Follower: das Datenmodell eines Social-Feeds zum Erforschen.', 9, 0],
            ['adventure', 'Dateninsel-Abenteuer', 'Rette die Insel mit SQL: erzählte Missionen vom SELECT bis zum UPDATE. Für die Aufgaben sind Schreibrechte nötig.', 9, 1],
            ['schule', 'Schule', 'Lehrkräfte, Kurse, Schülerinnen und Schüler und ihre Kursteilnahmen: Noten auswerten mit JOIN und GROUP BY.', 9, 0],
            ['bibliothek', 'Schulbibliothek', 'Bücher, Leserinnen und Leser und Ausleihen: unterwegs mit SELECT, JOIN und IS NULL.', 9, 0],
        ];
        /* Bestehende Installationen: alte Vorlage hieß mal anders (#45
         * Nacharbeit), Zeile umbenennen, damit Grants und Missionen erhalten
         * bleiben und keine zweite Karte entsteht. */
        $pdo->prepare("UPDATE sql_templates SET slug = 'instacraft', title = 'InstaCraft', description = 'Profile, Posts, Likes und Follower: das Datenmodell eines Social-Feeds zum Erforschen.' WHERE slug = 'instagram'")->execute();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM sql_templates WHERE slug = ?');
        $insert = $pdo->prepare('INSERT INTO sql_templates (slug, title, description, level, is_adventure) VALUES (?, ?, ?, ?, ?)');
        foreach ($templates as $template) {
            $exists->execute([$template[0]]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
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
        if ($slug === 'instacraft') {
            return [
                ['position' => 1, 'kind' => 'query', 'title' => 'Die Profile', 'story' => 'Zeige alle Benutzernamen mit dem echten Namen dahinter.', 'hint' => 'SELECT benutzername, name FROM nutzer', 'solution_sql' => 'SELECT benutzername, name FROM nutzer'],
                ['position' => 2, 'kind' => 'query', 'title' => 'Aktivität', 'story' => 'Wie viele Posts gibt es insgesamt?', 'hint' => 'SELECT COUNT(*) zählt alle Zeilen der Tabelle posts.', 'solution_sql' => 'SELECT COUNT(*) AS posts FROM posts'],
                ['position' => 3, 'kind' => 'query', 'title' => 'Beliebtester Post', 'story' => 'Wie viele Likes hat der Post mit der ID 3?', 'hint' => 'SELECT COUNT(*) FROM likes WHERE post_id = 3', 'solution_sql' => 'SELECT COUNT(*) AS likes FROM likes WHERE post_id = 3'],
                ['position' => 4, 'kind' => 'query', 'title' => 'Kommentare lesen', 'story' => 'Zeige zu jedem Kommentar den Benutzernamen und den Text.', 'hint' => 'JOIN kommentare mit nutzer über die nutzer_id.', 'solution_sql' => 'SELECT n.benutzername, k.text FROM kommentare k JOIN nutzer n ON n.id = k.nutzer_id'],
                ['position' => 5, 'kind' => 'query', 'title' => 'Fanzahl', 'story' => "Wie viele Nutzer folgen dem Profil 'luna'?", 'hint' => 'Eine Unterabfrage findet zuerst die id von luna.', 'solution_sql' => "SELECT COUNT(*) AS follower FROM folgt WHERE folgt_id = (SELECT id FROM nutzer WHERE benutzername = 'luna')"],
            ];
        }
        if ($slug === 'schule') {
            return [
                ['position' => 1, 'kind' => 'query', 'title' => 'Das Kursangebot', 'story' => 'Zeige alle Kurse mit Name und Jahrgangsstufe.', 'hint' => 'SELECT name, stufe FROM kurse', 'solution_sql' => 'SELECT name, stufe FROM kurse'],
                ['position' => 2, 'kind' => 'query', 'title' => 'Wer unterrichtet was?', 'story' => 'Zeige zu jedem Kurs den Namen der Lehrkraft.', 'hint' => 'JOIN kurse mit lehrkraefte über die lehrkraft_id.', 'solution_sql' => 'SELECT k.name, l.name FROM kurse k JOIN lehrkraefte l ON l.id = k.lehrkraft_id'],
                ['position' => 3, 'kind' => 'query', 'title' => 'Noch keine Note', 'story' => 'Manche Kursteilnahmen sind noch nicht bewertet. Zeige Vor- und Nachname aller Schülerinnen und Schüler mit einer Teilnahme ohne Note.', 'hint' => 'JOIN kursteilnahmen mit schuelerinnen und filtere mit note IS NULL.', 'solution_sql' => 'SELECT DISTINCT s.vorname, s.nachname FROM kursteilnahmen t JOIN schuelerinnen s ON s.id = t.schuelerin_id WHERE t.note IS NULL'],
                ['position' => 4, 'kind' => 'query', 'title' => 'Der Kursdurchschnitt', 'story' => 'Wie gut ist der Kurs Informatik 9a im Durchschnitt? Zeige Kursname und Durchschnittsnote.', 'hint' => 'GROUP BY mit AVG(note), gefiltert auf den einen Kurs.', 'solution_sql' => "SELECT k.name, AVG(t.note) FROM kursteilnahmen t JOIN kurse k ON k.id = t.kurs_id WHERE k.name = 'Informatik 9a' GROUP BY k.name"],
                ['position' => 5, 'kind' => 'query', 'title' => 'Die Klasse 9a im Vergleich', 'story' => 'Berechne die Durchschnittsnote je Kurs der Klasse 9a, sortiert nach bestem Schnitt.', 'hint' => 'JOIN über kursteilnahmen, kurse und schuelerinnen, GROUP BY kurs, ORDER BY AVG(note).', 'solution_sql' => "SELECT k.name, AVG(t.note) FROM kursteilnahmen t JOIN kurse k ON k.id = t.kurs_id JOIN schuelerinnen s ON s.id = t.schuelerin_id WHERE s.klasse = '9a' GROUP BY k.name ORDER BY AVG(t.note)"],
            ];
        }
        if ($slug === 'bibliothek') {
            return [
                ['position' => 1, 'kind' => 'query', 'title' => 'Das Regal', 'story' => 'Zeige alle Bücher mit Titel und Autor, sortiert nach Titel.', 'hint' => 'SELECT titel, autor FROM buecher ORDER BY titel', 'solution_sql' => 'SELECT titel, autor FROM buecher ORDER BY titel'],
                ['position' => 2, 'kind' => 'query', 'title' => 'Fantasy-Abteilung', 'story' => 'Zeige Titel aller Fantasy-Bücher.', 'hint' => "WHERE genre = 'Fantasy'", 'solution_sql' => "SELECT titel FROM buecher WHERE genre = 'Fantasy'"],
                ['position' => 3, 'kind' => 'query', 'title' => 'Noch nicht zurück', 'story' => 'Welche Bücher sind gerade ausgeliehen? Zeige Titel und Ausleihdatum.', 'hint' => 'JOIN ausleihen mit buecher und filtere zurueck_am IS NULL.', 'solution_sql' => 'SELECT b.titel, a.ausgeliehen_am FROM ausleihen a JOIN buecher b ON b.id = a.buch_id WHERE a.zurueck_am IS NULL'],
                ['position' => 4, 'kind' => 'query', 'title' => 'Stammleserinnen', 'story' => 'Wie viele Ausleihen hat jede Leserin und jeder Leser? Zeige Name und Anzahl.', 'hint' => 'JOIN ausleihen mit leser, dann GROUP BY name.', 'solution_sql' => 'SELECT l.name, COUNT(*) FROM ausleihen a JOIN leser l ON l.id = a.leser_id GROUP BY l.name ORDER BY l.name'],
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
            // Kapitel 2: die Bibliothek der Insel (Issue #7): GROUP BY, HAVING, Unterabfragen.
            ['position' => 9, 'kind' => 'query', 'title' => 'Die Bibliothek öffnet', 'story' => 'Die Bibliothek im Süden der Insel ist nach dem Sturm wieder zugänglich. Die Magierin Mira bittet dich: Zeige alle Bücher mit Titel und Autor.', 'hint' => 'SELECT titel, autor FROM buecher ORDER BY titel - sortiert, damit nichts durcheinandergerät.', 'solution_sql' => 'SELECT titel, autor FROM buecher ORDER BY titel'],
            ['position' => 10, 'kind' => 'query', 'title' => 'Die fleißigen Autorinnen', 'story' => 'Mira will wissen, wer sich um die Bibliothek verdient gemacht hat: Wie viele Bücher hat jede Autorin geschrieben? Zeige Name und Anzahl, sortiert nach Name.', 'hint' => 'GROUP BY autor fasst die Zeilen je Autorin zusammen, COUNT(*) zählt sie.', 'solution_sql' => 'SELECT autor, COUNT(*) FROM buecher GROUP BY autor ORDER BY autor'],
            ['position' => 11, 'kind' => 'query', 'title' => 'Schatzreiche Orte', 'story' => 'Der Lotse flüstert: An manchen Orten liegt mehr Gold als an anderen. Zeige alle Orte mit mehr als einem Gegenstand samt Anzahl.', 'hint' => 'JOIN gegen orte, GROUP BY ort und HAVING COUNT(*) > 1 als Filter für ganze Gruppen.', 'solution_sql' => 'SELECT o.name, COUNT(*) FROM gegenstaende g JOIN orte o ON o.id = g.ort_id GROUP BY o.name HAVING COUNT(*) > 1 ORDER BY o.name'],
            ['position' => 12, 'kind' => 'query', 'title' => 'Über dem Durchschnitt', 'story' => 'Zum Abschluss prüft der Häuptling deinen Blick für das Besondere: Finde alle Gegenstände, die mehr wert sind als der Durchschnittswert aller Gegenstände der Insel.', 'hint' => 'Eine Unterabfrage berechnet den Durchschnitt: wert > (SELECT AVG(wert) FROM gegenstaende).', 'solution_sql' => 'SELECT name, wert FROM gegenstaende WHERE wert > (SELECT AVG(wert) FROM gegenstaende) ORDER BY wert DESC'],
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

        // 9R arbeitet am Shop (schreiben), InstaCraft (lesen) und am Abenteuer (schreiben)
        $students = $pdo->query("SELECT u.id FROM users u JOIN classes c ON c.id = u.class_id WHERE c.name = '9R' AND u.role = 'student'")->fetchAll();
        foreach ($students as $row) {
            $studentId = (int) $row['id'];
            $grant->execute([$templateIds['shop'], $studentId, 'write']);
            $grant->execute([$templateIds['instacraft'], $studentId, 'read']);
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

    /** Bestehende Installationen: Spalte classes.db_panel fehlt evtl. noch. */
    private static function ensureDbPanelColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM classes LIKE 'db_panel'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE classes ADD COLUMN db_panel TINYINT(1) NOT NULL DEFAULT 1');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(classes)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'db_panel') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE classes ADD COLUMN db_panel INTEGER NOT NULL DEFAULT 1');
    }

    /** Bestehende Installationen: Tabelle project_images (Sprite-Bilder) fehlt evtl. noch. */
    private static function ensureProjectImagesTable(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS project_images (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id INT UNSIGNED NOT NULL,
                name VARCHAR(60) NOT NULL,
                mime VARCHAR(30) NOT NULL,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                data LONGBLOB NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pimages (project_id, name),
                CONSTRAINT fk_pimages_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
            return;
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS project_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            mime TEXT NOT NULL,
            size INTEGER NOT NULL DEFAULT 0,
            data BLOB NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (project_id, name)
        )');
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
    /** Bestehende Installationen: Tabellen polls/poll_votes (Live-Abstimmung, Issue #3). */
    private static function ensurePollTables(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS polls (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                presentation_id INT UNSIGNED NOT NULL,
                teacher_id INT UNSIGNED NOT NULL,
                question VARCHAR(300) NOT NULL,
                answers TEXT NOT NULL,
                state VARCHAR(10) NOT NULL DEFAULT \'open\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                closed_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                KEY idx_polls_pres (presentation_id),
                CONSTRAINT fk_polls_pres FOREIGN KEY (presentation_id) REFERENCES presentations (id) ON DELETE CASCADE,
                CONSTRAINT fk_polls_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
            $pdo->exec('CREATE TABLE IF NOT EXISTS poll_votes (
                poll_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                choice INT UNSIGNED NOT NULL,
                voted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (poll_id, user_id),
                CONSTRAINT fk_pvotes_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE,
                CONSTRAINT fk_pvotes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
            return;
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS polls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            presentation_id INTEGER NOT NULL REFERENCES presentations (id) ON DELETE CASCADE,
            teacher_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            question TEXT NOT NULL,
            answers TEXT NOT NULL DEFAULT \'[]\',
            state TEXT NOT NULL DEFAULT \'open\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at TEXT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS poll_votes (
            poll_id INTEGER NOT NULL REFERENCES polls (id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            choice INTEGER NOT NULL,
            voted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (poll_id, user_id)
        )');
    }

    /**
     * Dateninsel Kapitel 2 (Issue #7): Die Missionen 9 bis 12 (Bibliothek,
     * GROUP BY, HAVING, Unterabfragen) fehlen evtl. noch in bestehenden
     * Installationen, ebenso die Bibliothek in bereits angelegten
     * SQLite-Arbeitskopien. Alles idempotent.
     */
    private static function ensureAdventureChapter2(PDO $pdo): void
    {
        $stmt = $pdo->prepare("SELECT id FROM sql_templates WHERE slug = 'adventure'");
        $stmt->execute();
        $templateId = (int) $stmt->fetchColumn();
        if ($templateId > 0) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM sql_missions WHERE template_id = ? AND position = ?');
            $insert = $pdo->prepare('INSERT INTO sql_missions (template_id, position, kind, title, story, hint, solution_sql, check_sql, check_expected) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach (self::missionsFor('adventure') as $mission) {
                if ((int) $mission['position'] < 9) {
                    continue;
                }
                $exists->execute([$templateId, (int) $mission['position']]);
                if ((int) $exists->fetchColumn() > 0) {
                    continue;
                }
                $insert->execute([
                    $templateId,
                    (int) $mission['position'],
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

        // Bereits angelegte Arbeitskopien um Ort 6 (Bibliothek) und die
        // Tabellen buecher/ausleihen ergaenzen; frische Kopien bekommen
        // alles aus der aktualisierten Vorlage.
        $dir = INFOCRAFT_ROOT . '/database/sql';
        if (!is_dir($dir)) {
            return;
        }
        $patch = static function (PDO $sqlite): void {
            $sqlite->exec('CREATE TABLE IF NOT EXISTS buecher (
                id INTEGER PRIMARY KEY,
                titel TEXT NOT NULL,
                autor TEXT NOT NULL,
                jahr INTEGER NOT NULL DEFAULT 0,
                ort_id INTEGER NOT NULL REFERENCES orte (id)
            )');
            $sqlite->exec('CREATE TABLE IF NOT EXISTS ausleihen (
                buch_id INTEGER NOT NULL REFERENCES buecher (id),
                bewohner_id INTEGER NOT NULL REFERENCES bewohner (id),
                ausgeliehen_am TEXT NOT NULL
            )');
            $sqlite->exec("INSERT OR IGNORE INTO orte (id, name, beschreibung) VALUES (6, 'Bibliothek', 'Die alte Bibliothek der Insel mit verstaubten Regalen')");
            $sqlite->exec('INSERT OR IGNORE INTO wege (von_ort_id, nach_ort_id, tage) VALUES (5, 6, 1)');
            if ((int) $sqlite->query('SELECT COUNT(*) FROM buecher')->fetchColumn() === 0) {
                $insertBook = $sqlite->prepare('INSERT INTO buecher (id, titel, autor, jahr, ort_id) VALUES (?, ?, ?, ?, ?)');
                $books = [
                    [1, 'SQL für Seefahrer', 'Magierin Mira', 1801, 6],
                    [2, 'Die Kunst des Kompassbaus', 'Magierin Mira', 1815, 6],
                    [3, 'Inselkräuter von A bis Z', 'Kräuterfrau Ida', 1820, 6],
                    [4, 'Heilkunde aus dem Wald', 'Kräuterfrau Ida', 1823, 6],
                    [5, 'Sturmprotokolle der Insel', 'Häuptling Mika', 1790, 6],
                    [6, 'Briefe an die Lotse', 'Fischer Finn', 1828, 6],
                    [7, 'Vom Werden der Wellen', 'Magierin Mira', 1831, 6],
                ];
                foreach ($books as $row) {
                    $insertBook->execute($row);
                }
                $loan = $sqlite->prepare('INSERT INTO ausleihen (buch_id, bewohner_id, ausgeliehen_am) VALUES (?, ?, ?)');
                $loan->execute([1, 2, '1828-03-12']);
                $loan->execute([3, 7, '1828-04-02']);
                $loan->execute([4, 6, '1828-04-19']);
                $loan->execute([7, 8, '1828-05-01']);
            }
        };
        foreach (glob($dir . '/adventure_u*.sqlite') ?: [] as $path) {
            try {
                $sqlite = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $patch($sqlite);
            } catch (PDOException) {
                // Defekte Kopie einfach stehen lassen: die Migration darf
                // niemals abbrechen, die Werkstatt regeneriert Kopien selbst.
            }
        }
    }

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
            // Issue #9: Vorlage für die Kodex-Übung am Ende der Präsentation
            // \"Digitale Kommunikation\" (Jgst. 7). Lückentext gemeinsam im
            // Unterricht ausfüllen und als eigene Seite der Klasse speichern.
            ['Klassen-Kodex (Vorlage)', 7, "# Klassen-Kodex (Vorlage)\n\nSo gehen wir in unserer Klasse miteinander um - digital und im echten Leben. Diese Vorlage füllen wir gemeinsam aus: An allen Stellen mit `_____` ergänzt ihr unsere Beschlüsse.\n\n## 1. Umgangston\n\nWir schreiben im Netz so, wie wir auch persönlich miteinander reden: `_____` (z. B. freundlich, ohne Beleidigungen).\n\n## 2. Bilder und Videos von anderen\n\nBevor wir ein Foto oder Video von jemandem teilen, fragen wir: `_____`.\n\n## 3. Passwörter und Geräte\n\nUnsere Passwörter behandeln wir so: `_____`. Die Geräte anderer benutzen wir nur: `_____`.\n\n## 4. Quellen angeben\n\nWenn wir Texte oder Bilder verwenden, die nicht von uns sind, dann: `_____`.\n\n## 5. Wenn etwas schiefgeht\n\nWenn uns etwas komisch vorkommt oder jemand gemobbt wird, dann: `_____` (z. B. einer Lehrkraft melden, nicht selbst schlagen im Chat).\n\n## Unsere Unterschriften\n\nWir beschließen diesen Kodex für die Klasse `_____` am `_____`."],
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
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_working (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                task_id INT UNSIGNED NOT NULL,
                student_id INT UNSIGNED NOT NULL,
                project_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_working (task_id, student_id),
                CONSTRAINT fk_working_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
                CONSTRAINT fk_working_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
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
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_working (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
                student_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                project_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_task_working_unique ON task_working (task_id, student_id)');
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
                'presentation_project_id' => 'ALTER TABLE tasks ADD COLUMN presentation_project_id INT UNSIGNED NULL',
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
                'presentation_project_id' => 'ALTER TABLE tasks ADD COLUMN presentation_project_id INTEGER NULL',
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
            $exists = $pdo->query("SHOW COLUMNS FROM task_submissions LIKE 'project_id'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE task_submissions ADD COLUMN project_id INT UNSIGNED NULL');
            }
            $exists = $pdo->query("SHOW COLUMNS FROM task_targets LIKE 'recurrence'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec("ALTER TABLE task_targets ADD COLUMN recurrence VARCHAR(12) NOT NULL DEFAULT ''");
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
            $found = false;
            foreach ($pdo->query('PRAGMA table_info(task_submissions)')->fetchAll() as $column) {
                if ((string) ($column['name'] ?? '') === 'project_id') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $pdo->exec('ALTER TABLE task_submissions ADD COLUMN project_id INTEGER NULL');
            }
            $found = false;
            foreach ($pdo->query('PRAGMA table_info(task_targets)')->fetchAll() as $column) {
                if ((string) ($column['name'] ?? '') === 'recurrence') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $pdo->exec("ALTER TABLE task_targets ADD COLUMN recurrence TEXT NOT NULL DEFAULT ''");
            }
        }
    }

    /**
     * Schulweite Einstellungen (Admin-Bereich), z. B. die Standard-
     * Programmiersprache. Tabelle idempotent anlegen.
     */
    /**
     * Persistenter Zustand je Werkstatt-Projekt (Issue #44): Inhalt der
     * Objektkonsole (Anweisungsfeld) und Mini-Datenbank als SQL-Text, damit
     * beides über das Neuladen und die Aufgabenzuordnung hinaus erhalten
     * bleibt.
     */
    private static function ensureProjectStateTable(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS project_state (
                project_id INT UNSIGNED NOT NULL,
                repl_text MEDIUMTEXT NOT NULL,
                db_sql MEDIUMTEXT NOT NULL,
                PRIMARY KEY (project_id),
                CONSTRAINT fk_state_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS project_state (
                project_id INTEGER NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
                repl_text TEXT NOT NULL,
                db_sql TEXT NOT NULL,
                PRIMARY KEY (project_id)
            )');
        }
    }

    /**
     * Issue #45: Spalten tasks.db_template_id (angehängte Beispieldatenbank)
     * und tasks.db_write (Schreibrechte für Schülerinnen und Schüler)
     * in Bestehensinstallationen idempotent nachziehen.
     */
    private static function ensureTaskDatabaseColumns(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            foreach ([
                ['db_template_id', 'ALTER TABLE tasks ADD COLUMN db_template_id INT UNSIGNED NOT NULL DEFAULT 0'],
                ['db_write', 'ALTER TABLE tasks ADD COLUMN db_write TINYINT(1) NOT NULL DEFAULT 0'],
            ] as [$column, $statement]) {
                $exists = $pdo->query('SHOW COLUMNS FROM tasks LIKE ' . $pdo->quote($column))->fetchColumn();
                if ($exists === false || $exists === null) {
                    $pdo->exec($statement);
                }
            }
            return;
        }
        $columns = [];
        foreach ($pdo->query('PRAGMA table_info(tasks)')->fetchAll() as $column) {
            $columns[] = (string) ($column['name'] ?? '');
        }
        if (!in_array('db_template_id', $columns, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN db_template_id INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('db_write', $columns, true)) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN db_write INTEGER NOT NULL DEFAULT 0');
        }
    }

    /** Issue #45: Spalte folders.parent_id (Unterordner) idempotent nachziehen. */
    private static function ensureFolderParentColumn(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $exists = $pdo->query("SHOW COLUMNS FROM folders LIKE 'parent_id'")->fetchColumn();
            if ($exists === false || $exists === null) {
                $pdo->exec('ALTER TABLE folders ADD COLUMN parent_id INT UNSIGNED NOT NULL DEFAULT 0');
            }
            return;
        }
        foreach ($pdo->query('PRAGMA table_info(folders)')->fetchAll() as $column) {
            if ((string) ($column['name'] ?? '') === 'parent_id') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE folders ADD COLUMN parent_id INTEGER NOT NULL DEFAULT 0');
    }

    private static function ensureSiteSettingsTable(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
                setting_key TEXT NOT NULL,
                setting_value TEXT NOT NULL,
                PRIMARY KEY (setting_key)
            )');
        }
    }

    /**
     * Persönliche Einstellungen je Nutzerkonto, z. B. die gewählte Ansicht
     * der Seitennavigation (volle Beschriftung oder nur Icons).
     */
    private static function ensureUserPrefsTable(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_prefs (
                user_id INT UNSIGNED NOT NULL,
                pref_key VARCHAR(100) NOT NULL,
                pref_value TEXT NOT NULL,
                PRIMARY KEY (user_id, pref_key),
                CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_prefs (
                user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                pref_key TEXT NOT NULL,
                pref_value TEXT NOT NULL,
                PRIMARY KEY (user_id, pref_key)
            )');
        }
    }

    /**
     * Schulweite Einstellungen setzen (nur wenn noch nicht vorhanden):
     * Standard-Programmiersprache ist Java, bleibt im Admin-Bereich
     * umschaltbar.
     */
    private static function seedSiteSettings(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?');
        $stmt->execute(['default_language']);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        $insert = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        $insert->execute(['default_language', 'java']);
    }

    /**
     * Themengebiete kuratieren (nur bei leerer Tabelle), abgestimmt auf den
     * LehrplanPLUS Informatik Bayern: Am Gymnasium gibt es kein Informatik in
     * der Jahrgangsstufe 8, der Basiskurs beginnt ab Jahrgangsstufe 9
     * (funktionale Modellierung, Tabellenkalkulation, Datenmodellierung und
     * SQL, Java-OO, Datenschutz), in Jahrgangsstufe 10 folgen verknüpfte
     * Datenbanken, objektorientierte Programmierung und das Projekt;
     * davor Pluskurse bzw. Arbeitsgemeinschaften (z. B. Informationen
     * darstellen, erste Webseiten), Oberstufe Q11/Q12 (Datenstrukturen,
     * Theoretische Informatik, Softwareentwicklung). Eigene Themengebiete
     * bleiben unberührt, der Seed läuft nur einmal.
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
            [9, 'Funktionale Modellierung', 1],
            [9, 'Tabellenkalkulation und Funktionen', 2],
            [9, 'Java: Klassen und Objekte', 3],
            [9, 'Datenmodellierung und SQL', 4],
            [9, 'Datenschutz und Sicherheit', 5],
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
     * Bestehende Installationen aufräumen: Am Gymnasium gibt es kein Informatik
     * in der Jahrgangsstufe 8 (der Basiskurs beginnt ab Jahrgangsstufe 9).
     * Themengebiete der Stufe 8 werden entfernt, sofern keine Aufgabe sie noch
     * referenziert; referenzierte Themen bleiben als eigene Themen stehen.
     */
    private static function removeLevel8Topics(PDO $pdo): void
    {
        $pdo->exec(
            "DELETE FROM task_topics WHERE level = 8 AND id NOT IN ("
            . "SELECT topic_id FROM tasks WHERE topic_id IS NOT NULL)"
        );
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
            ['slug' => 'java-polymorphismus', 'title' => 'Java: Überschreiben und Polymorphismus', 'level' => 10, 'v' => 3, 'legacy' => [], 'notes' => ['Einstieg: Tier-Variable, Hund-Objekt: Welche Methode laeuft? (5 Min)', 'Tier[] mit Hund und Katze fuellen; gibLaut in der Schleife vorfuehren. (10 Min)', 'SuS: Klasse Kuh anlegen und ins Gehege-Array; Ueberschreiben ueben. (12 Min)', 'Loesung zeigen; Merksatz: Typ der Variablen, Klasse des Objekts entscheidet. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Dynamische Bindung', 'body' => "# Polymorphismus\n\nEin Objekt einer **Unterklasse** darf in einer Variable des **Oberklassen-Typs** liegen:\n\n```java\nTier t = new Hund(\"Bello\");\nt.gibLaut();  // bellt!\n```\n\nJava ruft **immer die Methode des konkreten Objekts** auf (**dynamische Bindung**) - obwohl t vom Typ Tier ist, bellt es.\n\n## Warum ist das mächtig?\n\nMan sammelt Tier-Objekte in einem Array - Hunde, Katzen, Papageien - und ruft für alle `gibLaut()` auf. Eine Methode, **viele Erscheinungsformen**: Poly-morphismus."],
                ['type' => 'code', 'title' => 'Live-Demo: Das Tierheim-Gehege', 'lang' => 'java', 'code' => '// Klassen Tier, Hund, Katze, Papagei wie in der Vererbungs-Stunde\n\nTier[] gehege = new Tier[3];\ngehege[0] = new Hund("Bello");\ngehege[1] = new Katze("Mimi");\ngehege[2] = new Papagei("Rio");\n\nfor (int i = 0; i < gehege.length; i++) {\n    gehege[i].gibLaut();\n}'],
                ['type' => 'text', 'title' => 'Übung: Neues Tier im Gehege', 'body' => "**Übung: Gehege erweitern**\n\n1. Schreibe eine weitere Unterklasse von Tier, z. B. **Kuh**: Konstruktor mit `nameStart`, `@Override gibLaut()`.\n2. Vergrößere das gehege-Array auf 4 Tiere und füge deine Kuh hinzu.\n3. Erkläre deinem Nachbarn: Warum reicht **eine** Schleife für alle Tiere - ganz ohne if-Abfrage?\n\nBonus: Was passiert, wenn eine Unterklasse `gibLaut()` **nicht** überschreibt?"],
                ['type' => 'code', 'title' => 'Lösung: Kuh im Gehege', 'lang' => 'java', 'code' => 'class Kuh extends Tier {\n    // Konstruktor\n    Kuh(String nameStart) {\n        super(nameStart);\n    }\n\n    // eigene Methode (ueberschreibt Tier)\n    @Override\n    void gibLaut() {\n        System.out.println(name + " muht: Muh!");\n    }\n}\n\nTier[] gehege = new Tier[4];\ngehege[3] = new Kuh("Berta");\ngehege[3].gibLaut();'],
            ]],
            ['slug' => 'projekt', 'title' => 'Projekt: Von der Idee zur Dokumentation', 'level' => 10, 'v' => 2, 'legacy' => [], 'notes' => ['Einstieg: Projekte im Alltag; die fuenf Phasen vorstellen. (5 Min)', 'Planung vertiefen; Tabelle mit Meilensteinen zeigen. (8 Min)', 'Teams bilden; je Team einen Plan fuer alle Phasen auf Papier erstellen. (15 Min)', 'Einen Plan gemeinsam vergleichen; Dokumentation ist Teil der Arbeit. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Die fünf Projektphasen', 'body' => "# Das Projekt\n\nIn der 10. Klasse realisiert ihr ein **Projekt im Team oder in Einzelarbeit**: ein **Java-Programm mit Klassendiagrammen** oder eine **Datenbankanwendung**.\n\n## Die fünf Phasen\n\n1. **Planung**: Ziel festlegen, Aufgaben verteilen, Zeit planen\n2. **Modellierung**: Klassen bzw. Tabellen entwerfen (Diagramm!)\n3. **Implementierung**: Code schreiben - in der Code-Werkstatt\n4. **Test**: Läuft es? Grenzfälle? Fehler dokumentieren\n5. **Dokumentation**: Was habt ihr gebaut, wie funktioniert es?\n\nAm Ende: **Vorstellung** eures Projekts vor der Klasse."],
                ['type' => 'text', 'title' => 'Gut geplant ist halb gewonnen', 'body' => "## Tipps für jedes Team\n\n- **Rollenkarten**: Wer macht was (Modellierung, Code, Test, Doku)? Rollen können wechseln.\n- **Meilensteine**: Setzt 3-4 Checkpoints mit klarem Ergebnis.\n- **Alles festhalten**: Entscheidungen und ihre Gründe - ihr braucht sie bei der Vorstellung.\n\n## Bewährte Projektideen\n\n- Java: Tierheim-Verwaltung (Vererbung + Datenkapselung)\n- Java: Quiz mit Fragen-Verwaltung (Arrays + Klassen)\n- Datenbank: Bibliothek oder Vereinsverwaltung (JOIN + Rechte)"],
                ['type' => 'text', 'title' => 'Übung: Euer Projektplan', 'body' => "**Übung: Der Projektplan für dein Team**\n\n1. Einigt euch auf eine **Projektidee** (Java oder Datenbank).\n2. Schreibt einen **Projektplan** in euer Heft: Ziel, Teamrollen, 4 Meilensteine mit Terminen, grobe Modellierung (Klassen bzw. Tabellen als Skizze).\n3. Legt fest, **wann** ihr testet und was dabei geprüft wird.\n\nNächste Stunde: Vorstellung des Plans in **2 Minuten**."],
                ['type' => 'text', 'title' => 'Lösung: Musterprojektplan', 'body' => "**Muster: Tierheim-Verwaltung**\n\n- **Ziel**: Verwaltung mit Tieren (Tier, Hund, Katze, Papagei), Zuweisung zu Gehegen, Menü mit Auswahl\n- **Rollen**: Modellierung (Klassendiagramm), Implementierung (2 Personen), Test + Dokumentation\n- **Meilensteine**: 1. Klassendiagramm freigegeben, 2. Klassen laufen (Konsolenausgabe), 3. Menü + Testläufe, 4. Doku + Vorstellung\n- **Testliste**: leeres Gehege, Tier ohne Name, viele Tiere gleichzeitig\n\nDer Plan passt auf eine Seite - das ist ein gutes Maß."],
            ]],
            ['slug' => 'java-datenbank', 'title' => 'Java trifft Datenbank: die Klasse Datenbank', 'level' => 10, 'v' => 2, 'legacy' => ['Java trifft Datenbank: die Klasse Datenbank'], 'notes' => ['Einstieg: Warum Java plus Datenbank? Apps brauchen beides. (5 Min)', 'Klasse Datenbank mit Treiber-Code zeigen; SQL-Strings betonen. (10 Min)', 'SuS: SELECT in Java ausfuehren; Tipp: Werkstatt-Datenbank nutzen. (12 Min)', 'Loesung vergleichen; SQL-Strings sauber setzen und Ergebnis ausgeben. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'SQL direkt in Java', 'body' => "In der Code-Werkstatt gibt es die eingebaute Klasse **Datenbank**.\n\n## Die wichtigsten Methoden\n- `Datenbank.ausfuehren(sql)` - Tabelle anlegen, Daten einfügen, ändern, löschen\n- `Datenbank.abfrage(sql)` - SELECT als formatierte Tabelle\n- `Datenbank.anzahl(sql)` - Zeilen zählen\n- `Datenbank.wert(sql)` - genau einen Wert holen\n- `Datenbank.tabellen()` - alle Tabellen zeigen\n\nDie Datenbank ist pro Programmlauf neu: kein Server, kein Setup."],
                ['type' => 'code', 'title' => 'Live-Demo: Datenbank in Java ansteuern', 'lang' => 'java', 'code' => 'Datenbank.ausfuehren("CREATE TABLE schueler (id, name, klasse, note)");\n\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (1, \'Lena\', \'10G\', 1.7)");\nDatenbank.ausfuehren("INSERT INTO schueler (id, name, klasse, note) VALUES (2, \'Jonas\', \'10G\', 2.3)");\n\nSystem.out.println(Datenbank.tabellen());\nSystem.out.println(Datenbank.abfrage("SELECT name, note FROM schueler ORDER BY note"));\n\nint gesamt = Datenbank.anzahl("SELECT * FROM schueler");\nSystem.out.println("Schueler insgesamt: " + gesamt);\n\nString beste = Datenbank.wert("SELECT name FROM schueler ORDER BY note LIMIT 1");\nSystem.out.println("Beste Note hat: " + beste);'],
                ['type' => 'text', 'title' => 'Übung: Bücher-Verwaltung in Java', 'body' => "**Übung: Eigene Tabelle mit der Klasse Datenbank**\n\nNutze die Klasse **Datenbank** in der Code-Werkstatt:\n\n1. Lege die Tabelle **buecher** an (id, titel, autor, jahr).\n2. Füge 3 Bücher ein (`Datenbank.ausfuehren`).\n3. Gib alle Bücher sortiert nach Titel aus (`Datenbank.abfrage`).\n4. Zähle die Bücher mit `Datenbank.anzahl`.\n\nTipp: Erst die SQL-Anweisung in der **SQL-Werkstatt** testen, dann in Java einbauen."],
                ['type' => 'code', 'title' => 'Lösung: Bücher in Java', 'lang' => 'java', 'code' => 'Datenbank.ausfuehren("CREATE TABLE buecher (id, titel, autor, jahr)");\nDatenbank.ausfuehren("INSERT INTO buecher (id, titel, autor, jahr) VALUES (1, \'Java lernen\', \'Ada Lovelace\', 2024)");\nDatenbank.ausfuehren("INSERT INTO buecher (id, titel, autor, jahr) VALUES (2, \'SQL fuer Einsteiger\', \'Grace Hopper\', 2023)");\n\nSystem.out.println(Datenbank.abfrage("SELECT titel, autor FROM buecher ORDER BY titel"));\n\nint gesamt = Datenbank.anzahl("SELECT * FROM buecher");\nSystem.out.println("Buecher: " + gesamt);'],
            ]],
            ['slug' => 'db-redundanz-anomalien', 'title' => 'Datenbanken: Redundanz, Inkonsistenz und Anomalien', 'level' => 10, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Eine Tabelle fuer alles fragen: Was passiert beim Umzug? (4 Min)', 'Definitionen an Beispieltabelle zeigen; Inkonsistenz live erzeugen. (8 Min)', 'Die drei Anomalien je mit einem Satz; Karteikarten-Quiz anschlussfaehig. (8 Min)', 'SuS finden die Probleme in der Vereins-Tabelle; Partnerarbeit. (12 Min)', 'Loesung gemeinsam entwickeln; Merksatz: Information nur einmal speichern. (6 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Warum mehrere Tabellen?', 'body' => "# Eine Tabelle fuer alles?\n\nStellt euch eine grosse Tabelle **vereine** vor: Mitglieder, deren Sportarten und die Trainer je Zeile.\n\n**Problem**: Viele Infos stehen mehrfach, aehnliche Infos leicht unterschiedlich.\n\n**Heute klaeren wir**: Warum eine grosse Tabelle kippt und wie man sie sauber aufteilt."],
                ['type' => 'text', 'title' => 'Redundanz und Inkonsistenz', 'body' => "## Zwei Kernbegriffe\n\n- **Redundanz**: Dieselbe Information wird mehrfach gespeichert (z. B. die Adresse in fuenf Zeilen).\n- **Inkonsistenz**: Zwei Kopien widersprechen sich, weil nur eine aktualisiert wurde.\n\n**Merksatz**: Redundanz ist die Ursache, Inkonsistenz die Folge."],
                ['type' => 'text', 'title' => 'Die drei Anomalien', 'body' => "## Wenn eine Tabelle kippt\n\n- **Einfuegeanomalie**: Ein neuer Kurs kann nicht angelegt werden, solange kein Mitglied existiert.\n- **Aenderungsanomalie**: Eine Adresse muss in vielen Zeilen geaendert werden; eine vergessene Zeile macht die Daten unbrauchbar.\n- **Loeschanomalie**: Loescht man die letzte Bestellung, verschwindet der ganze Kunde.\n\n**Loesung**: Informationen aufteilen auf mehrere Tabellen mit Beziehungen."],
                ['type' => 'text', 'title' => 'Uebung: Die Vereins-Tabelle', 'body' => "**Uebung: Probleme finden**\n\nGegeben ist eine Tabelle **mitglieder_kurse**: jede Zeile speichert Mitglied, Adresse, Sportart, Trainer und Trainer-Telefon.\n\n1. Markiere alle redundanten Spalten.\n2. Beschreibe je ein Beispiel fuer Einfuege-, Aenderungs- und Loeschanomalie.\n3. Welche Informationen gehoeren in eigene Tabellen?\n\nArbeitet zu zweit, wir vergleichen danach gemeinsam."],
                ['type' => 'text', 'title' => 'Loesung: Information nur einmal', 'body' => "**Musterloesung**\n\n1. Redundant: Adresse (je Mitglied mehrfach), Trainer und Trainer-Telefon (je Kurs mehrfach).\n2. Anomalien: Trainer neu ohne Mitglied (Einfuege), Umzug nur in einer Zeile (Aenderung), letztes Mitglied loeschen vernichtet Kursdaten (Loeschung).\n3. Tabellen: **mitglieder** (mit Adresse), **kurse** (mit Trainer), **belegung** verbindet beide.\n\n**Bewertung**: Redundanzen erkannt (2 BE), je Anomalie ein Beispiel (3 BE), Aufteilung begruendet (2 BE)."]
            ]],
            ['slug' => 'db-relationen-modellieren', 'title' => 'Datenmodellierung: 1:n- und n:m-Beziehungen planen', 'level' => 10, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Beispiele sammeln: Schueler-Ausweis, Klasse-Schueler, Schueler-Kurse. (5 Min)', 'Fremdschluessel an der 1:n-Beziehung zeigen: auf der n-Seite. (8 Min)', 'Hilfstabelle einfuehren: beide Fremdschluessel gemeinsam als Primaerschluessel. (8 Min)', 'Frage-Technik ueben: Passt der Verweis in eine Spalte? (6 Min)', 'SuS modellieren die Bibliothek auf Papier; Beziehungspfeile beschriften. (12 Min)', 'Loesung vergleichen; auf die Hilfstabelle ausleihen achten. (6 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Beziehungen zwischen Tabellen', 'body' => "# Wie Tabellen zusammenhaengen\n\nNach der Aufteilung braucht man **Beziehungen** zwischen den Tabellen.\n\n- **1:1**: Ein Schueler hat genau einen Ausweis.\n- **1:n**: Eine Klasse hat viele Schueler, jeder Schueler genau eine Klasse.\n- **n:m**: Schueler besuchen viele Kurse, Kurse haben viele Schueler.\n\nZunaechst: Wie baut man 1:n in SQL?"],
                ['type' => 'text', 'title' => 'Fremdschluessel: der Verweis', 'body' => "## 1:n sauber umsetzen\n\nBei **1:n** liegt der **Fremdschluessel (FK)** auf der n-Seite:\n\n```sql\nCREATE TABLE schueler (\n  id INTEGER PRIMARY KEY,\n  name TEXT,\n  klassen_id INTEGER REFERENCES klassen(id)\n);\n```\n\nJeder Schueler speichert genau **einen Verweis** auf seine Klasse - ein Wert pro Spalte, keine Listen."],
                ['type' => 'text', 'title' => 'n:m mit Hilfstabelle', 'body' => "## Zwei Fremdschluessel verbinden\n\nBei **n:m** reicht eine Spalte nicht. Loesung: eine **Hilfstabelle** mit beiden Fremdschluesseln:\n\n```sql\nCREATE TABLE belegung (\n  schueler_id INTEGER REFERENCES schueler(id),\n  kurs_id INTEGER REFERENCES kurse(id),\n  PRIMARY KEY (schueler_id, kurs_id)\n);\n```\n\nJede Zeile der Hilfstabelle ist **eine** Zuordnung - dadurch keine Redundanz."],
                ['type' => 'text', 'title' => 'Kardinalitaet bestimmen', 'body' => "## Die Trickfrage\n\n**Passt der Verweis in eine Spalte?**\n\n- Passt er in **eine** Spalte der anderen Tabelle -> **1:1** oder **1:n** (Fremdschluessel genuegt).\n- Braucht man **viele Verweise auf beiden Seiten** -> **n:m** (Hilfstabelle).\n\nTest: Frag in beide Richtungen!\n\n- Schueler -> Kurse: viele.\n- Kurse -> Schueler: viele.\n\nAlso: n:m."],
                ['type' => 'text', 'title' => 'Uebung: Die Schulbibliothek', 'body' => "**Uebung: Modellieren wie im echten Leben**\n\nEine Schulbibliothek soll ihre Ausleihen speichern: **buecher** (Titel, Autor), **schueler** (Name, Klasse) und die Ausleihen mit Datum.\n\n1. Bestimme die Kardinalitaet zwischen Buechern und Schuelern.\n2. Zeichne alle Tabellen mit Primaerschluesseln und Fremdschluesseln.\n3. Markiere die Hilfstabelle und begruende, warum sie noetig ist.\n\nAuf Papier arbeiten, 15 Minuten."],
                ['type' => 'text', 'title' => 'Loesung: Bibliothek-Modell', 'body' => "**Musterloesung**\n\n1. Ein Buch wird von vielen Schuelern ausgeliehen (nacheinander), ein Schueler leiht viele Buecher: **n:m**.\n2. Tabellen: **buecher** (id PK, titel, autor), **schueler** (id PK, name, klasse), **ausleihen** (buch_id FK, schueler_id FK, datum; PK aus buch_id, schueler_id, datum).\n3. Die Hilfstabelle **ausleihen** ist noetig, weil keine Seite mit einer Spalte auskommt; jede Zeile ist genau eine Ausleihe.\n\n**Bewertung**: Kardinalitaet mit Begruendung (3 BE), korrekte PKs und FKs (4 BE), Hilfstabelle begruendet (3 BE)."]
            ]],
            // Oberstufe (Jgst. 11/12 NTG, Issue #4): Algorithmen/Datenstrukturen,
            // Kryptografie, Netze, KI, Datenbanken vertieft.
            ['slug' => 'algorithmen-datenstrukturen', 'title' => 'Algorithmen und Datenstrukturen: Listen, Bäume, Suche und Sortieren', 'level' => 11, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Adressbuch als Analogie: Wie findet man einen Eintrag schnell? (5 Min)', 'Lineare vs. binäre Suche am Kartenstapel demonstrieren. (10 Min)', 'Listen und Bäume an der Folie skizzieren; SuS merken: Der Baum sortiert mit. (10 Min)', 'Sortierverfahren vergleichen; O-Notation einfuehren. (8 Min)', 'SuS: Bubblesort und binäre Suche implementieren. (15 Min)', 'Loesung vergleichen; Merksatz: Datenstruktur bestimmt Laufzeit. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Warum Datenstrukturen?', 'body' => "# Algorithmen und Datenstrukturen\n\nEin **Algorithmus** ist ein eindeutiges, endliches Verfahren; eine **Datenstruktur** entscheidet, wie Daten im Speicher liegen - und damit, wie schnell der Algorithmus arbeiten kann.\n\n- **Liste/Array**: Elemente hintereinander, direkter Zugriff über Index.\n- **Baum**: Elemente hierarchisch, sortiert verzweigt - halbiert den Suchraum.\n\nFaustregel: Erst die Datenstruktur waehlen, dann den Algorithmus - beides gehoert zusammen."],
                ['type' => 'text', 'title' => 'Suchen: linear oder binär', 'body' => "## Lineare Suche\n\nJedes Element wird der Reihe nach geprueft - funktioniert immer, braucht aber im Schnitt n/2 Vergleiche.\n\n## Binaere Suche (nur bei sortierten Daten)\n\n1. Pruefe das Element in der Mitte.\n2. Suchwert kleiner? -> **links** weiter, groesser? -> **rechts** weiter.\n3. Wiederholen, bis gefunden oder der Bereich leer ist.\n\nBei 1.000.000 Elementen braucht die binaere Suche hoechstens **20 Vergleiche** - die Anzahl der Reste halbiert sich: log2(n)."],
                ['type' => 'text', 'title' => 'Sortieren: Bubblesort und friends', 'body' => "## Bubblesort\n\nNachbarn vergleichen und bei Bedarf tauschen; das groesste Element wandert pro Durchlauf nach hinten - wie eine Blase nach oben.\n\n```text\n[5, 2, 9, 1] -> [2, 5, 1, 9] -> [2, 1, 5, 9] -> [1, 2, 5, 9]\n```\n\nBesser: **Mergesort** teilt die Liste rekursiv in Haelften und fuegt sortierte Teile zusammen: n * log2(n) Vergleiche.\n\nBei n = 1.000: Bubblesort ca. 1.000.000 Operationen, Mergesort ca. 10.000."],
                ['type' => 'text', 'title' => 'Übung: Bubblesort implementieren', 'body' => "**Uebung: Sortieren und messen**\n\nGegeben: `int[] werte = {5, 2, 9, 1, 7}`\n\n1. Implementiere **Bubblesort** (die Methode sortieren(int[] a) arbeitet direkt auf dem Array).\n2. Ergaenze eine Ausgabe nach jedem Durchlauf.\n3. Zaehle die Vergleiche mit.\n4. Begrunde: Warum ist das groesste Element nach dem ersten Durchlauf ganz hinten?\n\nIn der Code-Werkstatt arbeiten, 15 Minuten."],
                ['type' => 'code', 'title' => 'Lösung: Bubblesort', 'lang' => 'java', 'code' => "void sortieren(int[] a) {\n    for (int i = 0; i < a.length - 1; i++) {\n        for (int j = 0; j < a.length - 1 - i; j++) {\n            if (a[j] > a[j + 1]) {\n                int tmp = a[j];\n                a[j] = a[j + 1];\n                a[j + 1] = tmp;\n            }\n        }\n        System.out.println(\"Durchlauf \" + (i + 1) + \": \" + java.util.Arrays.toString(a));\n    }\n}\n\nint[] werte = {5, 2, 9, 1, 7};\nsortieren(werte);\n\n// Nach dem ersten Durchlauf steht das Maximum (9) am Ende:\n// Jeder Tausch schiebt den groesseren Nachbarn eine Position weiter nach hinten,\n// das groesste Element wandert in einem Durchlauf bis zur letzten Position."],
            ]],
            ['slug' => 'kryptografie', 'title' => 'Kryptografie: Von Caesar zu RSA', 'level' => 11, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Geheimbotschaft aus der 7. Klasse abrufen: Caesar knicken wir heute. (5 Min)', 'Symmetrisch vs. asymmetrisch am Schluesselkasten-Modell erlaeutern. (10 Min)', 'RSA-Idee (ohne Mathe-Detail) an der Folie; E-Commerce als Anwendungsfall. (10 Min)', 'Hashes und Passwoerter: Warum speichert man Passwoerter nie im Klartext? (8 Min)', 'SuS: Caesar mit Schluessel brechen (Haeufigkeitsanalyse). (12 Min)', 'Loesung besprechen; Merksatz: Sicherheit liegt im Schluessel, nicht im Verfahren. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Symmetrische Verfahren', 'body' => "# Kryptografie\n\n**Symmetrisch**: Alle Beteiligten nutzen denselben Schluessel zum Ver- und Entschluesseln.\n\n- Caesar (Verschiebung): trivial zu brechen, gut zum Verstaendnis.\n- AES: moderner Standard, wird heute ueberall eingesetzt.\n\nProblem: Der Schluessel muss verteilt werden - ueber einen **sicheren Kanal**. Doch wie tauscht man Schluessel mit jemandem aus, dem man noch nie begegnet ist?"],
                ['type' => 'text', 'title' => 'Asymmetrisch: RSA-Idee', 'body' => "## Zwei Schluessel\n\n**Asymmetrische Verfahren** nutzen ein Schluesselpaar:\n\n- **Oeffentlicher Schluessel**: darf jeder haben (wie ein Briefkasten).\n- **Privater Schluessel**: bleibt beim Empfaenger (wie das Briefkastenschloss).\n\nWer an den oeffentlichen Schluessel verschluesselt, kann mit ihm selbst nicht mehr entschluesseln - nur der private Schluessel kann das.\n\nRSA nutzt die Schwierigkeit, grosse Zahlen in ihre Primfaktoren zu zerlegen. Daraus folgt: **Sicherheit durch Rechenaufwand**, nicht durch Geheimhaltung des Verfahrens (Kerckhoffs-Prinzip)."],
                ['type' => 'text', 'title' => 'Hashes und Passwörter', 'body' => "## Einwegfunktion\n\nEin **Hash** wandelt Daten beliebiger Laenge in eine feste Pruefsumme um - zurueckrechnen geht praktisch nicht.\n\n- Integritaet: Hat sich die Datei veraendert?\n- Passwoerter: Gespeichert wird nur der **Hash**; beim Login wird gehasht und verglichen.\n\nDiskussionsfrage fuer den Unterricht: Warum reicht Hashing allein nicht (Regenbogentabellen), und was leisten **Salz** und moderne Verfahren wie bcrypt?"],
                ['type' => 'text', 'title' => 'Übung: Caesar brechen', 'body' => "**Uebung: Haeufigkeitsanalyse**\n\nDer Geheimtext wurde mit Caesar verschluesselt:\n\n`GLHYHUVFKOXHVVHOXQJ`\n\n1. Zaehle die Buchstabenhaeufigkeiten (E und N sind im Deutschen besonders haeufig).\n2. Bestimme die Verschiebung und entschluessle den Text.\n3. Erklaere, warum dieses Verfahren heute nicht mehr sicher ist.\n\nArbeit zu zweit, 12 Minuten."],
                ['type' => 'text', 'title' => 'Lösung: Caesar brechen', 'body' => "**Musterloesung**\n\nText: `GLHYHUVFKOXHVVHOXQJ`\n\nDer haeufigste Buchstabe ist **H** (4-mal) - im Deutschen ist das meist das verschobene **E**. H - E = 3, also Verschiebung um **3** zurueckrechnen:\n\nKlartext: **DIEVERSCHLUESSELUNG** (die Verschluesselung).\n\nWarum unsicher: Es gibt nur 26 moegliche Schluessel - ein Computer probiert alle in Millisekunden durch. Moderne Verfahren wie AES haben Schluessel mit 128 Bit oder mehr (ueber 10^38 Moeglichkeiten).\n\nBewertung: Haeufigkeitszaehlung (3 BE), korrekte Verschiebung (3 BE), Begruendung der Unsicherheit: 26 moegliche Schluessel sind mit einem PC in Millisekunden durchprobiert (4 BE)."],
            ]],
            ['slug' => 'netze-protokolle', 'title' => 'Netze und Protokolle: Vom Kabel bis zum HTTP-Request', 'level' => 11, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Was passiert in der 20 Millisekunden zwischen Klick und Bild? (5 Min)', 'Schichtenmodell an Handyrezept-Analogie erklaeren. (10 Min)', 'TCP vs UDP: Paketverschluesseltes Paketdienst-Modell. (8 Min)', 'DNS und HTTP live mit Browser-Devtools zeigen. (10 Min)', 'SuS: Ablaufdiagramm eines Seitenaufrufs zeichnen. (12 Min)', 'Loesung vergleichen; Merksatz: Protokoll ist ein Vertrag. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Schichtenmodell', 'body' => "# Netze in Schichten\n\nEin Netz ist in **Schichten** organisiert, jede hat eine Aufgabe:\n\n- **Anwendung** (HTTP, DNS, SMTP): Sprache der Apps.\n- **Transport** (TCP, UDP): Zuverlaessigkeit/Verbindung.\n- **Vermittlung** (IP): Adressierung und Wegfindung.\n- **Sicherung/Netzzugang** (Ethernet, WLAN): Bits auf der Leitung.\n\nVorteil: Schichten sind austauschbar (WLAN statt Kabel), ohne dass HTTP sich aendert."],
                ['type' => 'text', 'title' => 'TCP, UDP und IP', 'body' => "## Pakete statt Leitungen\n\nDas Internet ist ein **Paketvermittlungsnetz**: Daten werden in Pakete zerlegt, einzeln geroutet und beim Empfaenger zusammengesetzt.\n\n- **TCP**: Verbindung, Bestaetigung, erneutes Senden verlorener Pakete - zuverlaessig (Web, Mail).\n- **UDP**: ohne Verbindung, schnell, Pakete duerfen verloren gehen (Streaming, Games).\n- **IP-Adresse**: Nummer des Anschlusses; **DNS** uebersetzt Namen (infocraft.de) in IP-Adressen - das Telefonbuch des Netzes."],
                ['type' => 'text', 'title' => 'HTTP: Request und Response', 'body' => "## Der Browser spricht HTTP\n\n```text\nGET /index.html HTTP/1.1\nHost: www.beispiel.de\n\n---\nHTTP/1.1 200 OK\nContent-Type: text/html\n```\n\nDer Browser fordert (Request), der Server antwortet (Response) - ein **Zustandsloses** Protokoll: Jeder Request steht fuer sich. HTTPS packt das Ganze in eine TLS-Verschluesselung.\n\n**Zustand** entsteht erst durch Cookies und Sessions (Stichwort Datenschutz)."],
                ['type' => 'text', 'title' => 'Übung: Der Weg einer Anfrage', 'body' => "**Uebung: Ablaufdiagramm eines Seitenaufrufs**\n\nZeichne den kompletten Ablauf, wenn ihr `www.beispiel.de/index.html` aufruft:\n\n1. DNS-Anfrage: Name -> IP-Adresse.\n2. TCP-Verbindungsaufbau (Drei-Wege-Handschlag).\n3. HTTP-Request des Browsers.\n4. Antwort des Servers (Statuscode, HTML).\n5. Nachfolgende Requests: CSS, Bilder.\n6. TCP-Abbau.\n\nNummeriert die Schritte und beschriftet, welche Schicht jeweils aktiv ist. 12 Minuten."],
                ['type' => 'text', 'title' => 'Lösung: Ablaufdiagramm', 'body' => "**Musterloesung**\n\n1. **DNS-Anfrage** (Anwendungsschicht): Beispiel.de -> 93.184.216.34.\n2. **TCP-Handschlag** (Transport): SYN, SYN-ACK, ACK - die Verbindung steht.\n3. **HTTP GET /** (Anwendung): Request an den Server.\n4. **HTTP 200 OK** mit HTML (Anwendung): Server liefert die Seite.\n5. **Weitere GETs** auf style.css und logo.png - der Browser parst HTML und fordert Referenzen nach.\n6. **FIN/ACK** (Transport): Verbindung wird geschlossen.\n\nBewertung: DNS (2 BE), TCP-Handschlag (3 BE), Request/Response-Paar (3 BE), Nachfolge-Requests (2 BE).\n\nHinweis fuer die Diskussion: Beim zweiten Aufruf kommt vieles aus dem **Cache** - messbar in den Devtools."],
            ]],
            ['slug' => 'kuenstliche-intelligenz', 'title' => 'Künstliche Intelligenz: Daten, Modelle, Grenzen', 'level' => 12, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Was kann KI heute (Sprachassistent, Uebersetzung, Bilderkennung) und was nicht? (5 Min)', 'Unterschied symbolische KI vs. Maschinelles Lernen herausarbeiten. (10 Min)', 'Training an der Katzen/Hunde-Folie: Merkmale, Trainingsdaten, Overfitting. (10 Min)', 'Bias und Erklaerbarkeit an einem Beispiel diskutieren. (10 Min)', 'SuS: Entwurf eines eigenen Mini-Datensatzes mit Merkmalen. (10 Min)', 'Zusammenfassung: KI ist Statistik aus Daten - Datenqualitaet entscheidet. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Was KI heute ist', 'body' => "# Kuenstliche Intelligenz\n\nDer Begriff **KI** umspannt alles von Regelwerken (fruehere *symbolische KI*) bis zu **Maschinellem Lernen**: Systeme, die aus Beispieldaten Muster lernen, statt fest programmiert zu werden.\n\nAnwendungen heute: Sprachmodelle, Bilderkennung, Uebersetzung, Empfehlungen.\n\nKernfrage fuer Informatik: Woher kommen die Daten, was lernt das Modell daraus - und wo versagt es?"],
                ['type' => 'text', 'title' => 'Training: Merkmale und Daten', 'body' => "## Wie ein Modell lernt\n\nEin **Klassifikator** soll Katzen von Hunden unterscheiden. Statt Regeln zu schreiben, zeigt man ihm **Trainingsdaten** mit Label:\n\n- Merkmale: Fellfarbe, Ohrenform, Schnauzenlaenge, Schwanzform ...\n- Der Algorithmus sucht eine **Trennregel** in den Merkmalen.\n\nGefahren:\n- **Overfitting**: Das Modell lernt die Trainingsbilder auswendig statt des Musters.\n- **Bias**: Sind in den Daten nur kurzhaarige Katzen, versagt das Modell bei langhaarigen."],
                ['type' => 'text', 'title' => 'Grenzen: Bias und Schwarzkasten', 'body' => "## Vertrauen messbar machen\n\n- **Verzerrter Datensatz** -> verzerrtes Modell: Bekannte Faelle sind Erkennungsdienste, die Frauen schlechter erkennen, weil Trainingsdaten einseitig waren.\n- **Schwarzkasten**: Tiefe Netze entscheiden, aber erklaeren es nicht - deshalb fordern Gesetze (EU AI Act) Nachvollziehbarkeit.\n- **Halluzination**: Sprachmodelle erfinden fakten ueberzeugt klingende Antworten.\n\nMerksatz: **KI ist Statistik aus Daten** - Verantwortung liegt bei Menschen, die Daten und Einsatzbereich waehlen."],
                ['type' => 'text', 'title' => 'Übung: Eigener Mini-Datensatz', 'body' => "**Uebung: Merkmale statt Magie**\n\nEntwirft einen Mini-Klassifikator fuer den Schulweg: **kommen zu spaet** oder **puenktlich**?\n\n1. Sammelt 10 Beispieldaten: Aufstehzeit, Verkehrsmittel, Wetter, Weglaenge.\n2. Waehlt **drei Merkmale**, die gut trennen, und begruendet die Wahl.\n3. Formuliert eine Trennregel (wenn ... dann zu spaet).\n4. Testet die Regel gegen zwei Beispiele, die ihr vorher weggelegt habt.\n5. Diskutiert: Welche Verzerrung koennte euer Datensatz haben?\n\nArbeit in Paaren, 12 Minuten."],
                ['type' => 'text', 'title' => 'Lösung: Trennregel pünktlich', 'body' => "**Beispiel-Loesung**\n\nMerkmale: **Aufstehzeit vor 7:00 Uhr**, **Bahnnutzer** (ja/nein), **Regen** (ja/nein).\n\nTrennregel: Aufsteh vor 7:00 UND kein Regen -> puenktlich. Bahnnutzer im Regen -> Risiko zu spaet (+5 Minuten Puffer).\n\nTest gegen zurueckgehaltene Beispiele: Zwei von drei richtig - Regel verfeinern (Fahrrad bei Regen).\n\nVerzerrung: Der Datensatz enthaelt nur einen Schueler mit Fahrrad - **kleine Stichprobe**, Ergebnis nicht verallgemeinerbar.\n\nBewertung: Merkmalwahl mit Begruendung (3 BE), Trennregel (3 BE), Test und Verfeinerung (2 BE), Bias-Diskussion (2 BE)."],
            ]],
            ['slug' => 'db-vertieft', 'title' => 'Datenbanken vertieft: Normalisierung, JOINs, Transaktionen', 'level' => 12, 'v' => 1, 'legacy' => [], 'notes' => ['Einstieg: Fehlertabelle aus der 10. Klasse zeigen: Wo widerspricht sie sich? (5 Min)', 'Normalisierungsschritte an der Tabelle entwickeln lassen. (12 Min)', 'JOIN-Arten an zwei Tabellen demonstrieren. (10 Min)', 'Transaktion am Ueberweisungsbeispiel: ALL OR NOTHING. (8 Min)', 'SuS: Gegebene Tabelle in 3. NF ueberfuehren (SQL-Werkstatt). (12 Min)', 'Loesung vergleichen; Merksatz: Jede Information genau einmal. (5 Min)'], 'slides' => [
                ['type' => 'text', 'title' => 'Normalisierung bis 3NF', 'body' => "# Datenbanken vertieft\n\n**1. Normalform (1NF)**: Nur atomare Werte - keine Listen in einer Spalte.\n**2. Normalform (2NF)**: 1NF und jedes Nichtschluesselattribut haengt vom **gesamten** Schluessel ab (relevant bei zusammengesetzten Schluesseln).\n**3. Normalform (3NF)**: 2NF und keine Abhaengigkeit von Nichtschluesselattributen: *Lehrername haengt von kurs_id ab, nicht von schueler_id*.\n\nJede Verletzung erzeugt die bekannten **Anomalien** (Update/Insert/Delete) aus der 10. Klasse - die Normalform verhindert sie konstruktiv."],
                ['type' => 'text', 'title' => 'JOINs im Blick', 'body' => "## INNER, LEFT, RIGHT\n\n```sql\nSELECT s.name, k.thema\nFROM schueler s LEFT JOIN belegung b ON b.schueler_id = s.id\nLEFT JOIN kurs k ON k.id = b.kurs_id;\n```\n\n- **INNER JOIN**: nur Treffer auf beiden Seiten.\n- **LEFT JOIN**: alle links, auch ohne Treffer (NULL) - *welcher Schueler belegt noch keinen Kurs?*\n- **RIGHT JOIN**: spiegelbildlich; in den meisten Schemas durch Vertauschen als LEFT schreibbar.\n\nFaustregel: Die Frage \"Was fehlt?\" beantwortet der OUTER-Join."],
                ['type' => 'text', 'title' => 'Transaktionen: ACID', 'body' => "## Alles oder nichts\n\nEine **Transaktion** bündelt mehrere Anweisungen: Entweder alle laufen, oder keine.\n\nUeberweisung: `UPDATE konto SET stand = stand - 50 WHERE id = 1;` und `UPDATE konto SET stand = stand + 50 WHERE id = 2;`\n\n- **A**tomicity: Buchung + Gutschrift zusammen.\n- **C**onsistency: Summe der Konten bleibt gleich.\n- **I**solation: Parallelitaet stoert nicht.\n- **D**urability: Nach COMMIT bleibt es, auch bei Stromausfall.\n\nIn SQL: `BEGIN`, dann `COMMIT` - oder `ROLLBACK` zum Zuruecknehmen."],
                ['type' => 'text', 'title' => 'Übung: In die 3. NF überführen', 'body' => "**Uebung: Normalisierung anwenden**\n\nGegeben (1NF-Verstoss inklusive):\n\n| id | schueler | kurse | kursleiter |\n|----|----------|-------|-----------|\n| 1  | Lena     | Mathe, Info | Herr Maier, Frau Fischer |\n| 2  | Jonas    | Mathe | Herr Maier |\n\n1. Ueberfuehrt die Tabelle schrittweise in die **3. Normalform**.\n2. Legt die Tabellen in der SQL-Werkstatt an.\n3. Schreibt einen JOIN, der Schueler mit allen Kursen zeigt.\n4. Bonus: Ein LEFT JOIN zeigt, wer noch keinen Kurs belegt.\n\nArbeit in Paaren, 15 Minuten."],
                ['type' => 'code', 'title' => 'Lösung: 3NF mit JOIN', 'lang' => 'sql', 'code' => "CREATE TABLE schueler (id INTEGER PRIMARY KEY, name TEXT);\nCREATE TABLE kurs (id INTEGER PRIMARY KEY, thema TEXT, lehrer TEXT);\nCREATE TABLE belegung (\n    schueler_id INTEGER REFERENCES schueler(id),\n    kurs_id INTEGER REFERENCES kurs(id),\n    PRIMARY KEY (schueler_id, kurs_id)\n);\n\nINSERT INTO schueler VALUES (1, 'Lena'), (2, 'Jonas'), (3, 'Emma');\nINSERT INTO kurs VALUES (1, 'Mathe', 'Herr Maier'), (2, 'Info', 'Frau Fischer');\nINSERT INTO belegung VALUES (1, 1), (1, 2), (2, 1);\n\n-- Schueler mit allen Kursen (INNER JOIN)\nSELECT s.name, k.thema, k.lehrer\nFROM belegung b\nJOIN schueler s ON s.id = b.schueler_id\nJOIN kurs k ON k.id = b.kurs_id;\n\n-- Wer belegt noch keinen Kurs? (LEFT JOIN)\nSELECT s.name\nFROM schueler s\nLEFT JOIN belegung b ON b.schueler_id = s.id\nWHERE b.schueler_id IS NULL;"],
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
            $content = json_encode(self::normalizeSeedSlides($seed['slides'], $seed['notes'] ?? []), JSON_UNESCAPED_UNICODE) ?: '[]';
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
    private static function normalizeSeedSlides(array $slides, array $notes = []): array
    {
        foreach ($slides as $index => $slide) {
            if (($slide['type'] ?? '') === 'code') {
                $slide['code'] = str_replace('\\n', "\n", (string) ($slide['code'] ?? ''));
            }
            $note = trim((string) ($notes[$index] ?? ''));
            if ($note !== '') {
                $slide['notes'] = $note;
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
        return "# Anleitung für Lehrkräfte\n\nWillkommen bei **InfoCraft**! Diese Seite fasst die wichtigsten Arbeitsschritte zusammen. Du kannst sie hier bearbeiten und um eigene Hinweise ergänzen.\n\n## Erste Schritte\n\n1. Anmelden über die Startseite.\n2. Unter **Nutzer** Konten für Schülerinnen und Schüler anlegen oder per **CSV-Import** ganze Klassen einspielen.\n3. Unter **Meine Klassen** deinem Konto Klassen zuordnen: Du siehst dann nur deine Klassen und deine Schülerinnen und Schüler.\n\n## Module passend zum Lehrplan freigeben\n\nUnter **Meine Klassen** legst du für jede Klasse fest, welche Arbeitsbereiche sichtbar sind:\n\n- **Standard**: Der LehrplanPLUS-Stufe des Moduls folgt (z. B. SQL erst ab Jgst. 9).\n- **Freigeben**: Die Klasse sieht das Modul früher.\n- **Sperren**: Die Klasse sieht das Modul nicht.\n\nDas gilt auch für die direkte URL: gesperrte Module sind nicht aufrufbar.\n\n## Arbeiten austeilen\n\n- **Code-Werkstatt**: Projekte anlegen und an Klassen oder Einzelne austeilen; über die Schüleransicht erreichst du die Projekte deiner Schülerinnen und Schüler.\n- **SQL-Werkstatt**: Vorlagen (Datenbanken) ausgeben mit Rechten für *nur lesen*, *lesen und schreiben* oder *Tabellen anlegen*.\n- **Aufgabenpool**: eigene Aufgaben anlegen oder fertige Aufgaben (Jgst. 6 bis 13) aus dem Katalog übernehmen und austeilen.\n\n## Präsentieren und Nachschlagen\n\n- **Wiki**: Diese Nachschlage-Seiten hier. Der Stufen-Filter blendet Seiten aus, die über der Klassenstufe liegen.\n- **Präsentationen**: Folien aus Text und Code, kompakt gruppiert nach Jahrgangsstufe (Jgst. 6 bis 12). Im Präsentationsmodus:\n    - **Vollbild** über den Knopf oder Taste **F**\n    - **Pfeiltasten** oder Leertaste blättern\n    - Code-Folien lassen sich über *Code verändern* direkt während der Präsentation bearbeiten und speichern\n- **Moderatorenbildschirm** (nur für Lehrkräfte): Der Knopf **Moderator** öffnet eine Steuerzentrale mit aktueller und nächster Folie, Folienliste, Timer und Uhr. Öffne sie am Laptop und die Präsentationsansicht im Vollbild auf dem Beamer - beide Fenster laufen im selben Browser synchron mit (Pfeiltasten steuern beide).\n\n## Prüfungen und Schularbeiten\n\nUnter **Prüfungen & Arbeiten** legst du Gruppen mit eigenen Vorlagen an und startest die Prüfung. Während der Prüfung sind für die betroffene Klasse alle anderen Bereiche gesperrt - andere Klassen arbeiten normal weiter. Korrektur mit Punkten und Noten, optional anonymisiert; Schularbeiten ohne Sperre sind ebenfalls möglich.";
    }
}

