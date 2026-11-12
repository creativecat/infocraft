# InfoCraft

Digitale Informatik-Werkstatt für den bayerischen LehrplanPLUS (Gymnasium): SQL, HTML, Java/Python, Aufgaben, Quiz, Wiki, Präsentationen und Prüfungen, mit Rollen für Lehrkräfte, Schülerinnen und Schüler und Administration. Läuft ohne Composer und ohne Node-Build: nur PHP 8.3+ mit PDO.

## Kernfunktionen

- **Anmeldung und Rollen:** SSO über Microsoft 365 oder CSV-Import mit minimalen Daten (Vorname, Nachname, Klasse, Rolle). Neue Konten und Passwort-Resets erhalten Einmalpasswörter (OTP), einzeln oder für ganze Klassen.
- **SQL-Werkstatt:** Lehrkräfte teilen Datenbankvorlagen an Klassen aus, mit Rechten pro Person (lesen, schreiben, Tabellen anlegen). Jede Person arbeitet an ihrer eigenen Kopie, ER-Diagramme (Mermaid) und Missionen inklusive.
- **Code-Werkstatt:** Java- und Python-Projekte laufen komplett im Browser (eigene Java-Simulation bzw. Pyodide). Java-Projekte öffnen als IDE über die volle Breite: Dateifenster, Editor mit Zeilennummern und Auto-Formatierung, Konsole mit Objektkonsole, live gezeichnetes Klassendiagramm nach dem BlueJ-Prinzip, Objektdiagramm mit klickbaren Methoden, Einzelschritt-Debugger, Fehlerkonsole mit Sprung zur Stelle, Grafik-Fenster mit Figuren und Sprites (inkl. Online-IDE-Klassenbibliothek), eingebaute Klasse Datenbank (Mini-SQL direkt in Java) und API-Hilfe. Assembler-Projekte laufen auf einer didaktischen Registermaschine mit Register-Anzeige und Befehlsübersicht. Block-Projekte (🧩 Blöcke) programmieren per Drag & Drop farbiger Blöcke: Palette mit farbigen Kategorien, eigene Skripte pro Figur, programmierbare Bühne, Klang, Nachrichten, Sensoren, Operatoren und Variablen, automatisches Speichern; im Expertenmodus zusätzlich Profi-Blöcke, eine Konsole zum gezielten Steuern und eine objektorientierte Java-/Python-Ansicht (eine Klasse je Figur), die sich als echtes Projekt übernehmen lässt. Projekte lassen sich in Ordnern sortieren und an Klassen oder Einzelne austellen; Lehrkräfte können Projekte ihrer Schülerinnen und Schüler öffnen und unterstützend bearbeiten, Fremdklassen bleiben ausgesperrt.
- **HTML-Werkstatt:** Webseiten mit drei Ansichten: Gestalten (WYSIWYG), Quelltext (Syntax-Highlighting) und Vorschau. 22 HTML-Bausteine auf Deutsch erklärt, HTML-Dateien öffnen und herunterladen.
- **Aufgaben:** Aufgaben mit Musterlösung, Erwartungshorizont und Punktrahmen; Austeilen an Klassen oder einzelne Personen mit Start- und Abgabetermin, jederzeit wieder einziehen. ToDos und Materialien je Aufgabentyp (Java-, HTML- oder SQL-Vorlage), kuratierter Katalog passend zum LehrplanPLUS, Themengebiete und Klasse-Filter, Austausch zwischen Lehrkräften, optionale KI-Vorbewertung (anonymisiert, Vorschläge zählen erst nach Übernahme) sowie Export und Import.
- **Quiz-Arena:** Quiz anlegen, an Klassen oder Einzelne austellen, spielen mit sofortigem Feedback; Bestwertungen und Ergebnisliste für Lehrkräfte.
- **Prüfungsmodus:** Prüfungen und Schularbeiten mit Gruppen, Vorlage und Bewertungseinheiten. Während der Prüfung sind alle anderen Bereiche gesperrt, Eingaben speichern automatisch. Korrektur manuell oder anonymisiert per API, Herausgabe mit Punkten, Note, Feedback und Lösungsvergleich.
- **Diagramme:** Klassendiagramme per Mermaid zeichnen oder automatisch aus Java-, Python- und SQL-Quellen erzeugen, dazu Struktogramme (Nassi-Shneiderman) und Datenflussdiagramme mit eigener, Mermaid-ähnlicher DSL. PNG-Export inklusive.
- **Wiki:** Nachschlage-Seiten mit Stufen-Filter, Anleitung für Lehrkräfte als Startpaket.
- **Präsentationen:** Text- und Code-Folien, in der Präsentation live editierbar, mit Vollbild-Modus, Moderatorenbildschirm (Timer, Folienliste, Sprechernotizen) und Startpaketen für die Jahrgangsstufen 6 bis 10.
- **InfoBook:** OneNote-artige Notizbücher mit freien Flächen: Bilder per Drag & Drop, frei platzierte Textfelder, Zeichnungen als einzelne Strich-Objekte (Apple Pencil), Einbettungen (YouTube, LearningApps, H5P), Audio und Video. Persönliche Bücher der Lehrkraft, pro Klasse ein Klassen-InfoBook mit Lernbereich, Zusammenarbeit und privatem Bereich je Schülerin und Schüler; Seiten an Klassen verteilen, als Präsentation anzeigen oder als echte Präsentation übernehmen.
- **Stufen-Filter:** Schülerinnen und Schüler sehen nur die Bereiche, die laut LehrplanPLUS für ihre Klassenstufe relevant sind. Lehrkräfte können jeden Bereich pro Klasse früher freigeben oder sperren; gesperrte Bereiche sind auch über die direkte URL nicht erreichbar.

## Schnellstart (lokal)

```bash
git clone https://github.com/creativecat/infocraft.git
cd infocraft
php tools/migrate.php --driver sqlite
php -S localhost:8080 -t public public/index.php
```

SQLite läuft ohne weiteren Datenbankserver, die Migration erzeugt Schema und Beispieldaten.

## Demo-Konten (Demo-Modus)

Passwort jeweils **demo1234**, Ein-Klick-Anmeldung auf der Login-Seite, wenn demo_mode aktiv ist.

| Anmeldename | Rolle | Hinweis |
| --- | --- | --- |
| matthias.glienke | Administration | sieht alles |
| stefan.schmidt | Lehrkraft | unterrichtet 6R |
| claudia.fischer | Lehrkraft | unterrichtet 9R, hat alle SQL-Vorlagen |
| lena.müller | Schülerin, 6R | sieht kein SQL (erst ab Jgst. 9) |
| emma.krueger | Schülerin, 9R | sieht SQL: Shop (schreiben), InstaCraft (lesen), Adventure (schreiben) |

## Installation auf Webspace (PHP 8.3+)

1. Dateien hochladen, DocumentRoot der Domain auf `public/` zeigen lassen.
2. `config/config.example.php` nach `config/config.php` kopieren und anpassen (Treiber sqlite oder mysql, demo_mode, ggf. SSO, optional `exam.api` für die anonymisierte API-Korrektur).
3. Einmalig `php tools/migrate.php` ausführen (Schema + Beispieldaten).
4. Für MySQL liegt `sql/schema.mysql.sql` bei, alternativ einfach die Migration laufen lassen.

Das Anfrage- und Antwortformat der API-Korrektur steht als Beispiel in `config.example.php`.

## Qualitätssicherung

GitHub Actions prüft jeden Push mit PHP 8.3 und 8.5: PHP-Syntax, Migration (inkl. Idempotenz), Tests der Java-Simulation und der Registermaschine, UI-Tests mit Fake-DOM und Smoke-Tests über HTTP (Login, Rechtevergabe, Stufen-Filterung, kompletter Prüfungs-Durchlauf mit Korrektur und Herausgabe).

## Dokumentation

- Wiki: Bedienung für Lehrkräfte und Architektur für Mitentwickler
- docs/ROADMAP.md: Ausbaustufen und Status
- docs/lehrplan.md: Zuordnung der Bereiche zu Jahrgangsstufen (LehrplanPLUS)
- docs/sso-m365.md: Einrichtung von Microsoft-365-SSO

## Versionierung

Die Programmversion folgt Semantic Versioning (MAJOR.MINOR.PATCH) und steht im Footer sowie als `APP_VERSION` in src/Support.php. Neue Funktionen erhöhen MINOR, reine Fehlerbehebungen PATCH. Die Entwicklungshistorie steht in CHANGELOG.md; Asset-Änderungen erhöhen zusätzlich `ASSET_VERSION` für das Cache-Busting der Browser.

## Autor und Lizenz

Autor: Matthias Glienke, letima development.
Lizenz: CC BY-NC-SA 4.0 (siehe LICENSE).
