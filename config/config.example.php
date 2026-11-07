<?php
declare(strict_types=1);

/**
 * InfoCraft-Konfiguration (Beispiel).
 * Für die Produktion diese Datei nach config/config.php kopieren und anpassen.
 * config/config.php steht in .gitignore und landet nicht im Repository.
 */

return [
    'app' => [
        'name' => 'InfoCraft',
        'demo_mode' => true,           // Demo-Konten und Schnellanmeldung (Produktivbetrieb: false)
        'timezone' => 'Europe/Berlin',
    ],

    'db' => [
        // 'sqlite' = sofort lauffähig ohne Server-Datenbank, 'mysql' = klassischer Webspace
        'driver' => 'sqlite',
        'sqlite' => [
            'path' => __DIR__ . '/../database/infocraft.sqlite',
        ],
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'infocraft',
            'user' => 'nutzername',
            'pass' => 'passwort',
            'charset' => 'utf8mb4',
        ],
    ],

    'sso' => [
        'enabled' => false,            // Einrichtung: docs/sso-m365.md
        'tenant_id' => '',             // Entra ID: Mandanten-ID der Schule
        'client_id' => '',             // App-Registrierung: Anwendungs-ID (Client)
        'client_secret' => '',         // App-Registrierung: Geheimer Clientschlüssel
        'redirect_uri' => '',          // z. B. https://informatik.schule.de/auth/sso/callback
        'auto_create' => true,         // Unbekannte Schulkonten automatisch anlegen
    ],
];
