# InfoCraft

Digitale Informatik-Werkstatt für den bayerischen LehrplanPLUS (Gymnasium): SQL, HTML, Java/Python, Quiz, Wiki und Aufgaben, mit Rollen für Lehrkräfte, Schülerinnen und Schüler und Administration.

## Arbeitsbereiche

| Bereich | Ab Jahrgangsstufe | Status |
| --- | --- | --- |
| SQL-Werkstatt (Shop, Instagram, Dateninsel-Adventure) | 9 | fertig (Basis) |
| Code-Werkstatt (Projekte, Ordner, Austeilen; Python läuft im Browser) | 6 | in Arbeit (Python fertig) |
| Aufgaben / Meine Aufgaben | 6 | in Arbeit (Basis fertig) |
| HTML-Werkstatt (WYSIWYG-Editor mit Sofortvorschau, Vorlagen) | 6 | fertig (Basis) |
| Quiz-Arena | 6 | geplant |
| Wiki & Präsentationen | 6 | geplant |

Schülerinnen und Schüler sehen nur die Bereiche, die laut LehrplanPLUS für ihre Klassenstufe relevant sind. Ausgeteilte Inhalte (z. B. Datenbanken) bleiben auch darunter sichtbar.

## Kernfunktionen

- **Rollen und Anmeldung:** SSO über Microsoft 365 (docs/sso-m365.md) oder CSV-Import mit minimalen Daten (Vorname, Nachname, Klasse, Rolle).
- **OTP-Passwörter:** Neue Konten und Passwort-Resets erzeugen immer Einmalpasswörter nach der Satzbaulogik (Merksatz: "Apfel, Berg, Katze und Wolke" ergibt z. B. Abkw42!). Lehrkräfte können OTPs für einzelne Personen oder ganze Klassen erzeugen.
- **SQL-Werkstatt:** Lehrkräfte teilen Datenbankvorlagen an Klassen aus, mit Rechten pro Person: nur lesen, lesen und schreiben oder Tabellen anlegen. Jede Person arbeitet an ihrer eigenen Kopie, ER-Diagramme (Mermaid) und Missionen inklusive.
- **Code-Werkstatt:** Projekte anlegen, in Ordnern per Drag & Drop sortieren, von Lehrkräften an Klassen oder Einzelne austellen. Python-Programme laufen direkt im Browser (Pyodide), es wird kein Code auf dem Server ausgeführt.
- **Aufgabenpool:** Aufgaben anlegen und austeilen, Schülerinnen und Schüler sehen nur Aufgaben bis zu ihrer Jahrgangsstufe.
- **Präsentationsmodus:** Ansicht per Klick vergrößern.

## Schnellstart (lokal)

```bash
git clone https://github.com/creativecat/infocraft.git
cd infocraft
php tools/migrate.php --driver sqlite
php -S localhost:8080 -t public public/index.php
```

SQLite läuft ohne weiteren Datenbankserver, die Migration erzeugt Schema und Beispieldaten.

## Demo-Konten (Demo-Modus)

Passwort jeweils **demo1234**. Ein-Klick-Anmeldung auf der Login-Seite, wenn demo_mode aktiv ist.

| Anmeldename | Rolle | Hinweis |
| --- | --- | --- |
| matthias.glienke | Administration | sieht alles |
| stefan.schmidt | Lehrkraft | unterrichtet 6R |
| claudia.fischer | Lehrkraft | unterrichtet 9R, hat alle SQL-Vorlagen |
| lena.müller | Schülerin, 6R | sieht kein SQL (erst ab Jgst. 9) |
| emma.krueger | Schülerin, 9R | sieht SQL: Shop (schreiben), Instagram (lesen), Adventure (schreiben) |

## Installation auf Webspace (PHP 8.3+, auch 8.5)

1. Dateien hochladen, DocumentRoot der Domain auf public/ zeigen lassen.
2. config/config.example.php nach config/config.php kopieren und anpassen (Treiber sqlite oder mysql, demo_mode, ggf. SSO).
3. Einmalig php tools/migrate.php ausführen (Schema + Beispieldaten).
4. Für MySQL: sql/schema.mysql.sql liegt bei, alternativ einfach die Migration laufen lassen.

Ohne Composer, ohne Node-Build: nur PHP mit PDO.

## Qualitätssicherung

GitHub Actions prüft jeden Push mit PHP 8.3 und 8.5: Syntax, Migration (inkl. Idempotenz) und Smoke-Tests über HTTP (Login, Werkstatt-Seiten, SQL-Ausführung, Rechtevergabe, Stufen-Filterung).

## Dokumentation

- docs/ROADMAP.md: Ausbaustufen und Status
- docs/lehrplan.md: Zuordnung der Bereiche zu Jahrgangsstufen (LehrplanPLUS)
- docs/sso-m365.md: Einrichtung von Microsoft-365-SSO

Lizenz: privat (creativecat).
