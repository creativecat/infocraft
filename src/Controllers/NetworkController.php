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
            'net' => Network::forUser($user, $classId),
            'classes' => $isTeacher ? self::teachableClasses($user) : [],
            'classId' => $classId,
            'types' => Network::TYPES,
        ]);
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
        echo json_encode(Network::forUser($user, self::classId($user, Auth::isTeacher())), JSON_UNESCAPED_UNICODE);
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
            'net' => $error === null ? $result['net'] : $net,
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
