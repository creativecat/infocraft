# 🔨 InfoCraft

**Digitale Informatik-Werkstatt für den bayerischen Unterricht.**
InfoCraft bündelt SQL, HTML, Java/Python, Quiz, Wiki und Aufgabenpool hinter einer Oberfläche, konsequent auf den bayerischen LehrplanPLUS abgestimmt.

| Modul | Inhalt | Status |
| --- | --- | --- |
| 🗄️ SQL-Werkstatt | Shop-/Instagram-Datenmodell, Tabellenansicht, Mermaid-Diagramme, SQL-Adventure | geplant (Phase 2) |
| 🎨 HTML-Werkstatt | WYSIWYG-Editor mit Sofortvorschau | geplant (Phase 3) |
| 💻 Code-Werkstatt | Java/Python-IDE mit Schritt-für-Schritt-Steuerung | geplant (Phase 4) |
| 🏆 Quiz-Arena | Quizrunden im Kahoot-Stil | geplant (Phase 5) |
| 📚 Wiki | Umfangreiches Themen-Wiki, Präsentationen, später Lernvideos | geplant (Phase 6) |
| 📝 Aufgaben | Aufgabenpool, eigene Aufgaben, Austeilen durch Lehrkräfte | geplant (Phase 7) |

Phase 1 (fertig): Grundgerüst, Anmeldung (Microsoft-SSO, CSV-Import, Demo-Modus), Rollen (Schülerin/Schüler, Lehrkraft, Admin), Präsentationsmodus (Basis). Details: [docs/ROADMAP.md](docs/ROADMAP.md)

## ⚡ Schnellstart (lokal oder GitHub Codespaces)

Voraussetzung: PHP 8.3+ (getestet bis 8.5). Keine Composer-Abhängigkeiten, keine externe Datenbank nötig.

```bash
git clone https://github.com/creativecat/infocraft.git
cd infocraft
php -S localhost:8080 -t public public/index.php
```

Dann http://localhost:8080 öffnen. Beim ersten Aufruf legt InfoCraft automatisch eine SQLite-Datenbank mit Beispieldaten an (1 Admin, 2 Lehrkräfte, 11 Schülerinnen/Schüler, 3 Klassen).

## 🧪 Auf GitHub testen

- **Actions**: Bei jedem Push läuft die CI: PHP-Syntaxprüfung (8.3 und 8.5), Migration mit Beispieldaten, Smoke-Tests mit Demo-Anmeldung gegen den Entwicklungsserver. Siehe Reiter *Actions*.
- **Codespaces**: Repository in einem Codespace öffnen, den Schnellstart oben ausführen, Port 8080 wird automatisch weitergeleitet.

## 🌍 Auf eigenem Webspace betreiben (Apache, PHP 8.3+)

1. Repo hochladen (FTP oder git clone) und den DocumentRoot auf `public/` zeigen lassen.
2. `config/config.example.php` nach `config/config.php` kopieren und anpassen:
   - `db.driver` auf `mysql` und die Zugangsdaten eintragen (oder SQLite belassen),
   - `app.demo_mode` auf `false`,
   - optional SSO konfigurieren ([docs/sso-m365.md](docs/sso-m365.md)).
3. Einmalig das Schema einspielen: `php tools/migrate.php` (per SSH/Konsole). Alternativ `sql/schema.mysql.sql` in phpMyAdmin importieren.

## 👤 Demo-Zugänge (Demo-Modus)

| Anmeldename | Passwort | Rolle | Klasse |
| --- | --- | --- | --- |
| matthias.glienke | demo1234 | Administration | |
| stefan.schmidt | demo1234 | Lehrkraft | |
| claudia.fischer | demo1234 | Lehrkraft | |
| lena.müller bis nele.adler | demo1234 | Schülerin/Schüler | 6R / 9R |

Auf der Anmeldeseite gibt es zusätzlich Ein-Klick-Demo-Buttons, solange `app.demo_mode` aktiv ist.

## 🔐 Anmeldung & Datenschutz

- **Microsoft-365-SSO** (OpenID Connect) als Standardweg für den Produktivbetrieb, siehe [docs/sso-m365.md](docs/sso-m365.md).
- **CSV-Import** für Klassenlisten: nur Vorname, Nachname, Klasse, Rolle, nichts darüber.
- **Demo-Modus** nur für Entwicklung und Präsentation.

## 🗂 Projektstruktur

```text
public/      Einstieg (index.php), Assets, .htaccess
src/         PHP-Klassen (Router, Auth, SSO, CSV-Import, Models, Controller)
templates/   Views (Layout, Login, Module, Admin)
sql/         Schemata für MySQL/MariaDB und SQLite
tools/       migrate.php (Schema + Beispieldaten)
docs/        Roadmap, Lehrplan-Referenz, SSO-Einrichtung
```

## 📜 Lizenz

Noch offen (folgt).
