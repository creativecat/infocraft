<?php
declare(strict_types=1);

// Eingebauter PHP-Server (Entwicklung): statische Dateien direkt ausliefern.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

use Infocraft\Controllers\AdminController;
use Infocraft\Controllers\AuthController;
use Infocraft\Controllers\CodeController;
use Infocraft\Controllers\DiagramController;
use Infocraft\Controllers\ExamController;
use Infocraft\Controllers\HomeController;
use Infocraft\Controllers\HtmlController;
use Infocraft\Controllers\ModuleController;
use Infocraft\Controllers\PresentationController;
use Infocraft\Controllers\QuizController;
use Infocraft\Controllers\SqlWorkshopController;
use Infocraft\Controllers\TaskController;
use Infocraft\Controllers\TeacherController;
use Infocraft\Controllers\WikiController;
use Infocraft\Router;

// Basispfad, falls InfoCraft in einem Unterverzeichnis installiert ist.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_PATH', $scriptDir === '/' ? '' : $scriptDir);

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/login/demo', [AuthController::class, 'demoLogin']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/sso/start', [AuthController::class, 'ssoStart']);
$router->get('/auth/sso/callback', [AuthController::class, 'ssoCallback']);

// Unterricht (Lehrkräfte): eigene Klassen, OTP-Passwörter
$router->get('/unterricht/klassen', [TeacherController::class, 'classes']);
$router->get('/unterricht/klassen/{id}/fortschritt', [TeacherController::class, 'progress']);
$router->get('/unterricht/passwort/ergebnis', [TeacherController::class, 'otpResults']);
$router->post('/unterricht/passwort', [TeacherController::class, 'resetPassword']);
$router->post('/unterricht/klasse/zuordnen', [TeacherController::class, 'assignTeacher']);
$router->post('/unterricht/klassen/modul', [TeacherController::class, 'setModule']);

// SQL-Werkstatt (feste Routen vor den Platzhaltern registrieren)
$router->get('/werkstatt/sql', [SqlWorkshopController::class, 'index']);
$router->post('/werkstatt/sql/verteilen', [SqlWorkshopController::class, 'distribute']);
$router->post('/werkstatt/sql/kopie', [SqlWorkshopController::class, 'selfCopy']);
$router->post('/werkstatt/sql/entfernen', [SqlWorkshopController::class, 'revoke']);
$router->get('/werkstatt/sql/{slug}', [SqlWorkshopController::class, 'workspace']);
$router->post('/werkstatt/sql/{slug}', [SqlWorkshopController::class, 'query']);
$router->post('/werkstatt/sql/{slug}/mission/{id}', [SqlWorkshopController::class, 'mission']);

// Code-Werkstatt
$router->get('/werkstatt/code', [CodeController::class, 'index']);
$router->post('/werkstatt/code/projekt', [CodeController::class, 'createProject']);
$router->post('/werkstatt/code/ordner', [CodeController::class, 'createFolder']);
$router->post('/werkstatt/code/move', [CodeController::class, 'move']);
$router->post('/werkstatt/code/ordner/verschieben', [CodeController::class, 'moveFolder']);
$router->post('/werkstatt/code/verteilen', [CodeController::class, 'distribute']);
$router->post('/werkstatt/code/loeschen', [CodeController::class, 'delete']);
// Schuelerprojekte (Lehrkraefte): vor dem Platzhalter {id} registrieren
$router->get('/werkstatt/code/schueler/{id}', [CodeController::class, 'studentProjects']);
$router->get('/werkstatt/code/{id}', [CodeController::class, 'open']);
$router->post('/werkstatt/code/{id}', [CodeController::class, 'save']);
$router->post('/werkstatt/code/{id}/datei', [CodeController::class, 'addFile']);
$router->post('/werkstatt/code/{id}/datenbank', [CodeController::class, 'loadDatabase']);
$router->post('/werkstatt/code/{id}/datenbank/datei', [CodeController::class, 'uploadDatabase']);
$router->post('/werkstatt/code/{id}/datei/{fileId}/loeschen', [CodeController::class, 'deleteFile']);
$router->post('/werkstatt/code/{id}/bild', [CodeController::class, 'uploadImage']);
$router->get('/werkstatt/code/{id}/bild/{name}', [CodeController::class, 'serveImage']);
$router->post('/werkstatt/code/{id}/bild/{name}/loeschen', [CodeController::class, 'deleteImage']);

// HTML-Werkstatt (WYSIWYG-Editor, nutzt die Projektverwaltung der Code-Werkstatt)
$router->get('/werkstatt/html', [HtmlController::class, 'index']);
$router->post('/werkstatt/html/projekt', [HtmlController::class, 'create']);
$router->get('/werkstatt/html/{id}', [HtmlController::class, 'open']);
$router->post('/werkstatt/html/{id}', [HtmlController::class, 'save']);

// Klassendiagramme: eigener Editor, automatische Erzeugung aus Skripten und SQL,
// Lehrkraefte koennen die automatische Erzeugung pro Klasse deaktivieren.
$router->get('/diagramme', [DiagramController::class, 'index']);
$router->post('/diagramme/anlegen', [DiagramController::class, 'create']);
$router->get('/diagramme/{id}', [DiagramController::class, 'detail']);
$router->post('/diagramme/{id}', [DiagramController::class, 'save']);
$router->post('/diagramme/{id}/loeschen', [DiagramController::class, 'delete']);
$router->post('/werkstatt/code/{id}/diagramm', [DiagramController::class, 'fromProject']);
$router->post('/werkstatt/sql/{slug}/diagramm', [DiagramController::class, 'fromSql']);
$router->post('/unterricht/klassen/diagramm', [TeacherController::class, 'toggleDiagramAuto']);
$router->post('/unterricht/klassen/datenbank', [TeacherController::class, 'toggleDbPanel']);

// Quiz-Arena (Lehrkraefte verwalten, Schuelerinnen und Schueler spielen)
$router->get('/quiz', [QuizController::class, 'index']);
$router->post('/quiz/anlegen', [QuizController::class, 'create']);
$router->get('/quiz/{id}', [QuizController::class, 'manage']);
$router->post('/quiz/{id}/frage', [QuizController::class, 'addQuestion']);
$router->post('/quiz/{id}/austeilen', [QuizController::class, 'distribute']);
$router->get('/quiz/{id}/ergebnisse', [QuizController::class, 'results']);
$router->get('/quiz/{id}/spielen', [QuizController::class, 'play']);
$router->post('/quiz/{id}/antwort', [QuizController::class, 'answer']);
$router->get('/quiz/{id}/ergebnis', [QuizController::class, 'result']);

// Prüfungen & Schularbeiten: Vorbereitung, Durchführung mit Sperre, Korrektur, Herausgabe
$router->get('/pruefungen', [ExamController::class, 'index']);
$router->post('/pruefungen/anlegen', [ExamController::class, 'create']);
$router->get('/pruefungen/{id}', [ExamController::class, 'manage']);
$router->post('/pruefungen/{id}/gruppe', [ExamController::class, 'addGroup']);
$router->post('/pruefungen/{id}/mitglied', [ExamController::class, 'addMember']);
$router->post('/pruefungen/{id}/vorlage', [ExamController::class, 'setTemplate']);
$router->post('/pruefungen/{id}/aufgabe', [ExamController::class, 'addTask']);
$router->post('/pruefungen/{id}/start', [ExamController::class, 'start']);
$router->post('/pruefungen/{id}/ende', [ExamController::class, 'end']);
$router->get('/pruefungen/{id}/korrektur', [ExamController::class, 'correction']);
$router->get('/pruefungen/{id}/korrektur/{userId}', [ExamController::class, 'correct']);
$router->post('/pruefungen/{id}/bewertung', [ExamController::class, 'saveGrading']);
$router->post('/pruefungen/{id}/api-korrektur', [ExamController::class, 'apiCorrection']);
$router->post('/pruefungen/{id}/herausgeben', [ExamController::class, 'release']);
$router->get('/pruefungen/{id}/arbeit', [ExamController::class, 'work']);
$router->post('/pruefungen/{id}/antwort', [ExamController::class, 'saveAnswer']);
$router->post('/pruefungen/{id}/abgeben', [ExamController::class, 'submit']);
$router->get('/pruefungen/{id}/ergebnis', [ExamController::class, 'result']);

// Aufgabenpool (Lehrkräfte) bzw. Meine Aufgaben (Schülerinnen und Schüler)
$router->get('/aufgaben', [TaskController::class, 'index']);
$router->get('/aufgaben/neu', [TaskController::class, 'createPage']);
$router->post('/aufgaben/anlegen', [TaskController::class, 'create']);
$router->post('/aufgaben/austeilen', [TaskController::class, 'assign']);
$router->post('/aufgaben/termin', [TaskController::class, 'dueDate']);
$router->post('/aufgaben/start', [TaskController::class, 'startDate']);
$router->post('/aufgaben/einziehen', [TaskController::class, 'withdraw']);
$router->get('/aufgaben/ki', [TaskController::class, 'aiSettings']);
$router->post('/aufgaben/ki', [TaskController::class, 'aiSave']);
$router->post('/aufgaben/ki/schule', [TaskController::class, 'aiSchool']);
$router->post('/aufgaben/pool/{id}/uebernehmen', [TaskController::class, 'adopt']);
$router->post('/aufgaben/katalog', [TaskController::class, 'import']);
$router->post('/aufgaben/katalog/stufe', [TaskController::class, 'importLevel']);
$router->get('/aufgaben/{id}/bearbeiten', [TaskController::class, 'edit']);
$router->post('/aufgaben/{id}/bearbeiten', [TaskController::class, 'update']);
$router->post('/aufgaben/{id}/teilen', [TaskController::class, 'share']);
$router->get('/aufgaben/{id}/korrektur', [TaskController::class, 'correction']);
$router->post('/aufgaben/{id}/korrigieren', [TaskController::class, 'grade']);
$router->post('/aufgaben/{id}/ki', [TaskController::class, 'aiGrade']);
$router->post('/aufgaben/{id}/abgeben', [TaskController::class, 'submit']);
$router->post('/aufgaben/{id}/oeffnen', [TaskController::class, 'openInWorkshop']);
$router->post('/aufgaben/{id}/praesentation/start', [TaskController::class, 'presentationStart']);
$router->post('/aufgaben/{id}/praesentation/ende', [TaskController::class, 'presentationEnd']);
$router->get('/aufgaben/{id}/praesentation/download', [TaskController::class, 'presentationDownload']);
$router->get('/aufgaben/{id}/loesung/fenster', [TaskController::class, 'solutionWindow']);
$router->post('/aufgaben/{id}/loeschen', [TaskController::class, 'delete']);
$router->post('/aufgaben/{id}/todos', [TaskController::class, 'todoTeacher']);
$router->post('/aufgaben/{id}/todos/schueler', [TaskController::class, 'todoStudent']);
$router->post('/aufgaben/{id}/todos/eigene', [TaskController::class, 'todoOwn']);
$router->post('/aufgaben/{id}/material', [TaskController::class, 'materialSave']);
$router->get('/aufgaben/{id}/material/{mid}', [TaskController::class, 'materialDownload']);
$router->post('/aufgaben/{id}/variante', [TaskController::class, 'variant']);
$router->get('/aufgaben/{id}/export', [TaskController::class, 'export']);
$router->post('/aufgaben/import', [TaskController::class, 'importJson']);

// Wiki: Nachschlage-Seiten mit Stufen-Filter (Lehrkräfte verwalten)
$router->get('/wiki', [WikiController::class, 'index']);
$router->post('/wiki/anlegen', [WikiController::class, 'create']);
$router->get('/wiki/{id}', [WikiController::class, 'show']);
$router->get('/wiki/{id}/bearbeiten', [WikiController::class, 'edit']);
$router->post('/wiki/{id}', [WikiController::class, 'save']);
$router->post('/wiki/{id}/loeschen', [WikiController::class, 'delete']);

// Präsentationen: Folien mit Vollbild-Modus, Code-Folien sind im
// Präsentationsmodus direkt editierbar (Syntax-Highlighting).
$router->get('/praesentationen', [PresentationController::class, 'index']);
$router->post('/praesentationen/anlegen', [PresentationController::class, 'create']);
$router->get('/praesentationen/thema/{slug}', [PresentationController::class, 'show']);
$router->get('/praesentationen/{id}', [PresentationController::class, 'edit']);
$router->post('/praesentationen/{id}', [PresentationController::class, 'saveMeta']);
$router->get('/praesentationen/{id}/zeigen', [PresentationController::class, 'show']);
$router->get('/praesentationen/{id}/moderator', [PresentationController::class, 'moderator']);
$router->post('/praesentationen/{id}/folie', [PresentationController::class, 'addSlide']);
$router->post('/praesentationen/{id}/folie/{pos}', [PresentationController::class, 'saveSlide']);
$router->post('/praesentationen/{id}/folie/{pos}/loeschen', [PresentationController::class, 'deleteSlide']);
$router->post('/praesentationen/{id}/folie/{pos}/{dir}', [PresentationController::class, 'moveSlide']);
$router->post('/praesentationen/{id}/loeschen', [PresentationController::class, 'delete']);
$router->post('/praesentationen/{id}/abstimmung/start', [PresentationController::class, 'pollStart']);
$router->post('/praesentationen/{id}/abstimmung/ende', [PresentationController::class, 'pollEnd']);
$router->get('/praesentationen/{id}/abstimmung/status', [PresentationController::class, 'pollStatus']);
$router->post('/praesentationen/{id}/abstimmung/stimme', [PresentationController::class, 'pollVote']);

// Verwaltung
$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/import', [AdminController::class, 'importForm']);
$router->post('/admin/import', [AdminController::class, 'import']);
$router->get('/admin/einstellungen', [AdminController::class, 'settings']);
$router->post('/admin/einstellungen', [AdminController::class, 'settingsSave']);

// Persönliche Ansichten (je Nutzerkonto gespeichert)
$router->post('/einstellungen/navigation', [AuthController::class, 'saveNavView']);

// Module (Platzhalter und Weiterleitungen)
$router->get('/modul/{slug}', [ModuleController::class, 'show']);

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
if ($path === '') {
    $path = '/';
}

// Prüfungsmodus: Schülerinnen und Schüler mit laufender Prüfung werden auf
// die Prüfung umgeleitet, alle anderen Bereiche sind gesperrt.
Infocraft\Controllers\ExamController::guard($path);

// Modul-Freigabe: gesperrte oder noch nicht dran gewesene Module sind für
// Schülerinnen und Schüler auch über ihre URL nicht erreichbar (personengebunden).
Infocraft\Controllers\ModuleController::guard($path);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (PDOException $e) {
    error_log('InfoCraft: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Datenbankfehler. Bitte prüfen, ob das Schema eingespielt ist (php tools/migrate.php) und ob config/config.php korrekt ist.';
} catch (Throwable $e) {
    error_log('InfoCraft: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Interner Fehler. Details stehen im Server-Log.';
}
