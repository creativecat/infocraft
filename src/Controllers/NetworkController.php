<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Netzwerk-Werkstatt: Seiten, Stand-Polling und Aktionen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.29.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Network;
use Infocraft\Models\Project;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

final class NetworkController
{
    /** Die Werkstatt-Seite: Topologie, Router-UI, Missionen, Lehrkraft-Manager. */
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $isTeacher = Auth::isTeacher();
        $classId = self::classId($user, $isTeacher);
        render('network_workshop', [
            'title' => 'Netzwerk-Werkstatt',
            'active' => 'module-netzwerk',
            'isTeacher' => $isTeacher,
            'net' => self::payload($user, $classId),
            'classes' => $isTeacher ? self::teachableClasses($user) : [],
            'classId' => $classId,
            'types' => Network::TYPES,
        ]);
    }

    /** Netz mit der Liste der eigenen HTML-Projekte (#230). */
    private static function payload(User $user, ?int $classId): array
    {
        return array_merge(Network::forUser($user, $classId), [
            'htmlProjects' => self::htmlProjects($user),
        ]);
    }

    /** Eigene HTML-Projekte der HTML-Werkstatt für die Brücke (#230). */
    private static function htmlProjects(User $user): array
    {
        $out = [];
        foreach (Project::all((int) $user->id) as $project) {
            if ($project->type === 'html') {
                $out[] = ['id' => (int) $project->id, 'title' => $project->title];
                if (count($out) >= 20) {
                    break;
                }
            }
        }
        return $out;
    }

    /** Aktueller Stand als JSON (Polling, ca. alle 5 Sekunden). */
    public static function stand(): void
    {
        $user = Auth::user();
        if ($user === null) {
            http_response_code(403);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::payload($user, self::classId($user, Auth::isTeacher())), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Eine Aktion am Netz ausführen und den neuen Stand zurückgeben. */
    public static function aktion(): void
    {
        $user = Auth::user();
        if ($user === null) {
            http_response_code(403);
            exit;
        }
        csrf_validate();
        $isTeacher = Auth::isTeacher();
        $classId = self::classId($user, $isTeacher);
        $action = (string) ($_POST['aktion'] ?? '');
        $params = [];
        if (is_string($_POST['params'] ?? null)) {
            $decoded = json_decode($_POST['params'], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }
        $net = Network::forUser($user, $classId);

        // Brücke zur HTML-Werkstatt (#230): diese Aktionen brauchen echte
        // Projekt-Datensätze und laufen deshalb hier, nicht im Modell.
        if ($action === 'toHtml') {
            $page = null;
            foreach ($net['pages'] as $p) {
                if ((string) $p['id'] === (string) ($params['id'] ?? '')) {
                    $page = $p;
                }
            }
            if ($page === null) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Diese Webseite gibt es nicht.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $project = Project::create((int) $user->id, null, $page['title'], 'html', 'html', (string) $page['html']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'url' => url('werkstatt/html/' . (int) $project->id)], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'fromHtml') {
            $project = Project::find((int) ($params['projectId'] ?? 0));
            if ($project === null || (int) $project->owner_id !== (int) $user->id) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Dieses HTML-Projekt gehört dir nicht.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $params['title'] = $project->title;
            $params['html'] = (string) $project->content;
            $action = 'savePage';
        }

        $result = Network::apply($net, $action, $params, (int) $user->id, $isTeacher);
        $error = null;
        if ($result['net'] === null) {
            $error = $result['error'] ?? 'Unbekannter Fehler.';
        } else {
            Network::save($result['net'], $classId, (int) $user->id);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $error === null,
            'error' => $error,
            'net' => $error === null ? array_merge($result['net'], ['htmlProjects' => self::htmlProjects($user)]) : array_merge($net, ['htmlProjects' => self::htmlProjects($user)]),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Gesamtes Netz als JSON-Datei herunterladen (Lehrkraft: sichern). */
    public static function export(): void
    {
        $user = Auth::user();
        if ($user === null || !Auth::isTeacher()) {
            redirect('/werkstatt/netzwerk');
        }
        $net = Network::forUser($user, self::classId($user, true));
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="infocraft-netzwerk.json"');
        echo json_encode($net, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /** Klasse, deren Netz gemeint ist: Schüler immer die eigene, Lehrkraft wählbar. */
    private static function classId(User $user, bool $isTeacher): ?int
    {
        if (!$isTeacher) {
            return (int) $user->class_id > 0 ? (int) $user->class_id : null;
        }
        $requested = (int) ($_GET['klasse'] ?? 0);
        if ($requested > 0) {
            $classes = SchoolClass::forTeacher((int) $user->id, $user->role === 'admin');
            foreach ($classes as $class) {
                if ((int) $class->id === $requested) {
                    return $requested;
                }
            }
        }
        // Ohne Klassenwahl arbeitet die Lehrkraft im eigenen Netz (Übungsnetz).
        return null;
    }

    /** Klassen der Lehrkraft für den Netzwerk-Manager. */
    private static function teachableClasses(User $user): array
    {
        return SchoolClass::forTeacher((int) $user->id, $user->role === 'admin');
    }
}
