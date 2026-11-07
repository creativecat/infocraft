# 🚀 Roadmap

InfoCraft wird modulweise entwickelt. Jede Phase liefert eine nutzbare Einheit, alles orientiert sich am bayerischen LehrplanPLUS (docs/lehrplan.md).

## Phase 1: Fundament (fertig)

- Grundgerüst: Routing, Templates, Design, Präsentationsmodus (Basis: Skalierung, pro Endgerät gespeichert)
- Nutzer: Rollen Schülerin/Schüler, Lehrkraft, Administration
- Anmeldung: Microsoft-365-SSO (OpenID Connect), CSV-Import, Demo-Modus
- Datenhaltung: MySQL/MariaDB oder SQLite, minimale Nutzerdaten (Vor-/Nachname, Klasse, Rolle)
- CI auf GitHub (Syntaxprüfung, Migration, Smoke-Tests)

## Phase 2: SQL-Werkstatt

- Einstiegsprojekt: Abbildung eines Shops und eines Instagram-Klons als Datenbank
- Backend mit Tabellenansicht (Daten ansehen, bearbeiten, filtern)
- Automatisch generierte ER- und Klassendiagramme, als Mermaid-Quelltext exportierbar und editierbar
- SQL-Text-Adventure im Stil von SQL Island (Aufgaben per SQL-Query lösen, Geschichte vorantreiben)
- Präsentations- und Prüfungsmodus für diesen Bereich

## Phase 3: HTML-Werkstatt

- Einfacher WYSIWYG-Editor mit Sofortvorschau
- Vorlagen und Aufgaben aus dem Lehrplan (Webseiten gestalten)

## Phase 4: Code-Werkstatt

- Online-IDE für Java und Python (Aufbau wie bekannte Online-IDEs)
- Automatische Dateiendungen (.java, .py)
- Schritt-für-Schritt-Steuerung durch geführte Skripte
- Präsentationsmodus
- Hinweis zur Ausführung: Python perspektivisch im Browser (Pyodide) oder Sandbox, Java über vorbereitete Lernskripte, bis eine sichere serverseitige Ausführung steht

## Phase 5: Quiz-Arena und Prüfungsmodus

- Quizrunden im Kahoot-Stil (live, Punkte, Rangliste)
- Quiz zu allen Themen aus Präsentationen und Wiki
- Prüfungsmodus für alle Bereiche (gesteuerte Aufgabenstellung, Auswertung für Lehrkräfte)

## Phase 6: Wiki, Präsentationen, Lernvideos

- Umfangreiches Wiki zu allen Inhalten
- Präsentationen zu Themen
- Später: Lernvideos zum Einbetten, mit Skripten und Beispielaufgaben

## Phase 7: Aufgabenpool

- Umfangreicher Aufgabenpool, abgestimmt auf die Präsentationen
- Eigene Aufgaben für Schülerinnen und Schüler und für Lehrkräfte
- Lehrkräfte teilen Aufgaben an Klassen aus

## Offen gehalten (u. a. Oberstufe)

- Fortgeschrittene SQL-Themen (JOINs, Transaktionen, Normalisierung) als Adventure-Level
- Algorithmen und Datenstrukturen für die Oberstufe
- Projektarbeiten und Abiturvorbereitung

Erweiterungen werden so angebaut, dass neue Module ohne Refactoring der Bestandsmodule möglich sind (Modul-Registry, einheitliche Migrations).
