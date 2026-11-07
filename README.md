# InfoCraft

Digitale Informatik-Werkstatt für den bayerischen LehrplanPLUS (Gymnasium): SQL, HTML, Java/Python, Quiz, Wiki und Aufgaben, mit Rollen für Lehrkräfte, Schülerinnen und Schüler und Administration.

## Arbeitsbereiche

| Bereich | Ab Jahrgangsstufe | Status |
| --- | --- | --- |
| SQL-Werkstatt (Shop, Instagram, Dateninsel-Adventure) | 9 | fertig (Basis) |
| Code-Werkstatt (Projekte, Ordner, Austeilen; Python und Java laufen im Browser; Java mit mehreren Dateien, Konsole, Fehlerkonsole und Mini-Datenbank für SQL) | 6 | in Arbeit (Python und Java fertig) |
| Aufgaben / Meine Aufgaben (Pool, Katalog, Austeilen) | 6 | fertig (Basis) |
| HTML-Werkstatt (WYSIWYG-Editor mit Sofortvorschau, Vorlagen) | 6 | fertig (Basis) |
| Quiz-Arena (Quiz anlegen, austellen, spielen, Ergebnisse) | 6 | in Arbeit (Basis fertig) |
| Prüfungen & Schularbeiten (Gruppen mit Vorlagen, BE, Sperre, Korrektur, API-Vorschläge) | 6 | fertig (Basis) |
| Klassendiagramme (Mermaid-Editor, Auto-Erzeugung aus Java/Python/SQL, PNG-Export) | 6 | fertig (Basis) |
| Wiki (Nachschlage-Seiten mit Stufen-Filter, Anleitung für Lehrkräfte) | 6 | fertig (Basis) |
| Präsentationen (Folien mit Vollbild-Modus, Code-Folien live editierbar) | 6 | fertig (Basis) |

Schülerinnen und Schüler sehen nur die Bereiche, die laut LehrplanPLUS für ihre Klassenstufe relevant sind. Ausgeteilte Inhalte (z. B. Datenbanken) bleiben auch darunter sichtbar. Lehrkräfte können jeden Arbeitsbereich pro Klasse zusätzlich freigeben (früher nutzbar) oder sperren (ausgeblendet); gesperrte Bereiche sind für die Klasse auch über die direkte URL nicht erreichbar, andere Klassen sind nie betroffen.

## Kernfunktionen

- **Rollen und Anmeldung:** SSO über Microsoft 365 (docs/sso-m365.md) oder CSV-Import mit minimalen Daten (Vorname, Nachname, Klasse, Rolle).
- **OTP-Passwörter:** Neue Konten und Passwort-Resets erzeugen immer Einmalpasswörter nach der Satzbaulogik (Merksatz: "Apfel, Berg, Katze und Wolke" ergibt z. B. Abkw42!). Lehrkräfte können OTPs für einzelne Personen oder ganze Klassen erzeugen.
- **SQL-Werkstatt:** Lehrkräfte teilen Datenbankvorlagen an Klassen aus, mit Rechten pro Person: nur lesen, lesen und schreiben oder Tabellen anlegen. Jede Person arbeitet an ihrer eigenen Kopie, ER-Diagramme (Mermaid) und Missionen inklusive.
- **Code-Werkstatt:** Projekte anlegen, in Ordnern per Drag & Drop sortieren, von Lehrkräften an Klassen oder Einzelne austellen. Lehrkräfte sehen die Projekte ihrer Schülerinnen und Schüler in einer eigenen Liste und können sie öffnen, ansehen und bearbeiten (unterstützen beim Arbeiten); Fremdklassen bleiben ausgesperrt. Python-Programme laufen direkt im Browser (Pyodide), Java-Programme über eine eigene Java-Simulation direkt im Browser: mehrere Dateien pro Projekt (eine Klasse pro Datei), Ausgaben in der Konsole, Fehler mit Datei und Zeile plus deutscher Hilfestellung in einer eigenen Fehlerkonsole. Für Datenbanken ohne Vorkenntnisse gibt es die eingebaute Klasse Datenbank: CREATE TABLE, INSERT, UPDATE, DELETE und SELECT direkt in Java (ausfuehren, abfrage, anzahl, wert, tabellen), eine Mini-SQL-Engine im Speicher des Browsers, bei jedem Lauf neu und ohne Setup. Es wird kein Code auf dem Server ausgeführt.
- **Syntax-Highlighting:** Alle Code-Editoren (HTML, Python, Java, SQL, Mermaid) heben Schlüsselwörter, Zeichenketten, Zahlen und Kommentare farbig hervor, live beim Tippen und in Musterlösungen. Umgesetzt als Overlay (transparente Textarea über farbigem Code), highlight.js per CDN, alles clientseitig ohne Servercode.
- **Aufgabenpool:** Aufgaben anlegen und austeilen, Schülerinnen und Schüler sehen nur Aufgaben bis zu ihrer Jahrgangsstufe. Dazu ein kuratierter Aufgabenkatalog mit fertigen Aufgaben passend zum LehrplanPLUS (Jahrgangsstufen 6 bis 13, filterbar nach Stufe und Typ): einzelne Aufgaben oder alle einer Jahrgangsstufe lassen sich mit einem Klick als Kopie in den eigenen Pool übernehmen (Duplikate werden übersprungen); eigene Aufgaben können gelöscht werden.
- **Wiki:** Lehrkräfte legen Nachschlage-Seiten zu Fachbegriffen und Konzepten an (einfache Markdown-Formatierung: Überschriften, Listen, fett, kursiv, Code). Seiten sind ab der eingestellten Jahrgangsstufe sichtbar; bearbeiten und löschen kann sie die erstellende Lehrkraft (Admin alle). Beim Einrichten legt der Migrator automatisch eine ausführliche Anleitung für Lehrkräfte plus Beispiel-Seiten an, die den Stufen-Filter zeigen.
- **Präsentationen:** Lehrkräfte stellen Präsentationen aus Text- und Code-Folien zusammen (Java, Python, SQL). Der Präsentationsmodus bietet Vollbild (Taste F), Navigation mit Pfeiltasten und Syntax-Highlighting für Code-Folien; Code lässt sich direkt in der Präsentation verändern und speichern, um live zu demonstrieren. Stufen-Filter wie im Wiki. Startpräsentationen für die Jahrgangsstufen 9 (Datenbanken/SQL) und 10 (Java inklusive der Klasse Datenbank) werden automatisch angelegt.
- **Quiz-Arena:** Quiz mit vier Antworten pro Frage anlegen, an Klassen oder Einzelne austellen. Spielen mit sofortigem Feedback, Bestwertungen und Ergebnisliste für Lehrkräfte.
- **Prüfungsmodus:** Prüfungen und Schularbeiten mit Gruppen und einer Vorlage pro Gruppe, Teilaufgaben mit BE und Musterlösung. Während der Prüfung sind für Teilnehmende alle anderen Bereiche gesperrt (Server-Umleitung und reduzierte Oberfläche), Eingaben speichern automatisch. Korrektur manuell oder anonymisiert per API; Vorschläge müssen gegengeprüft werden. Nach der Herausgabe: Punkte, Note, Feedback und der Vergleich eigene Lösung vs. Musterlösung.
- **Klassendiagramme:** Klassendiagramme selbst zeichnen (Mermaid-Editor mit Live-Vorschau) und jederzeit zwischenspeichern. Automatisch erzeugt InfoCraft Diagramme aus Java- und Python-Skripten (Klassen, Attribute, Methoden, Sichtbarkeiten, Vererbung) und aus den Tabellen einer SQL-Datenbank (Spalten, Primärschlüssel, Fremdschlüssel-Beziehungen). Jedes Diagramm lässt sich als PNG exportieren. Lehrkräfte können das automatische Erzeugen pro Klasse deaktivieren; selbst zeichnen bleibt für Schülerinnen und Schüler immer möglich.
- **Präsentieren-Schalter:** Ansicht per Klick vergrößern (Schriftgröße); die Präsentationen haben zusätzlich ihren eigenen Vollbild-Modus.

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
2. config/config.example.php nach config/config.php kopieren und anpassen (Treiber sqlite oder mysql, demo_mode, ggf. SSO, optional exam.api für die anonymisierte API-Korrektur).
3. Einmalig php tools/migrate.php ausführen (Schema + Beispieldaten).
4. Für MySQL: sql/schema.mysql.sql liegt bei, alternativ einfach die Migration laufen lassen.

Ohne Composer, ohne Node-Build: nur PHP mit PDO.

## Prüfungsmodus und API-Korrektur

Prüfungen und Schularbeiten durchlaufen vier Phasen:

1. **Vorbereitung:** Gruppen bilden, pro Gruppe eine Vorlage aus der eigenen Werkstatt wählen, Teilaufgaben mit BE (Bewertungseinheiten) und Musterlösung anlegen.
2. **Durchführung:** Beim Start bekommt jedes Gruppenmitglied eine eigene Arbeitskopie der Vorlage. Bei Prüfungen (nicht bei Schularbeiten) sind alle anderen Bereiche gesperrt: Der Server leitet Teilnehmende auf die Prüfung um, die Navigation ist reduziert. Eingaben speichern automatisch, ein Live-Monitor zeigt der Lehrkraft Beginn und Abgabe.
3. **Korrektur:** Manuell je Arbeit oder mit einem Klick anonymisiert über eine API. Die API sieht nur Pseudonyme (Arbeit A, Arbeit B, ...), nie Namen. Vorschläge zählen erst, wenn die Lehrkraft sie gegengeprüft und gespeichert hat; erst dann kann herausgegeben werden.
4. **Herausgabe:** Schülerinnen und Schüler sehen erreichte BE, Note (Notenschlüssel: ab 92/81/67/50/30 Prozent die Noten 1 bis 5, sonst 6), Feedback der Lehrkraft und den direkten Vergleich zwischen eigener Lösung und Musterlösung. Dasselbe Feedback-Format gilt auch für Schularbeiten ohne Sperre.

Konfiguration der API-Korrektur in config/config.php (optional, siehe config.example.php):

```json
'exam' => [
    'api' => [
        'mode' => 'json',   // json: bewertet direkt, chat: Chat-Endpunkt (OpenAI-kompatibel)
        'url' => 'https://beispiel.example/korrektur',
        'key' => 'token',   // optional, wird als Authorization: Bearer gesendet
        'model' => '...',   // nur für mode chat
    ],
],
```

Anfrage (anonymisiert, ohne Namen):

```json
{
  "exam": {"title": "HTML-Pruefung", "kind": "pruefung"},
  "tasks": [{"position": 1, "be": 4, "sample_solution": "Musterloesung"}],
  "works": [{"pseudonym": "Arbeit A", "answers": [{"position": 1, "answer": "..."}], "material": "Projektinhalt, gekuerzt"}]
}
```

Erwartete Antwort:

```json
{"results": [{"pseudonym": "Arbeit A", "tasks": [{"position": 1, "be": 3.5, "feedback": "Fast perfekt"}]}]}
```

## Qualitätssicherung

GitHub Actions prüft jeden Push mit PHP 8.3 und 8.5: Syntax, Migration (inkl. Idempotenz), Node-Tests des Java-Interpreters (54 Fälle: Ausgaben, Typen, Kontrollfluss, Objekte, Vererbung, Fehlerfälle mit Hilfestellungen, Mini-Datenbank mit SQL in Java) und Smoke-Tests über HTTP (Login, Werkstatt-Seiten, SQL-Ausführung, Rechtevergabe, Stufen-Filterung, Quiz-Runden, kompletter Prüfungs-Durchlauf mit Sperre, Abgabe, Korrektur, API-Korrektur und Herausgabe; Klassendiagramme: Anlegen, Speichern, Auto-Erzeugung aus Java/Python/SQL, Lehrer-Schalter pro Klasse und Löschen; Java-Multi-Datei-Projekte: Datei anlegen, speichern, synchron halten und löschen; Wiki: Seite anlegen, Schüleransicht, Stufen-Filter, bearbeiten und löschen, Anleitung für Lehrkräfte als Seed; Präsentationen: Startpakete für Jgst. 9 und 10, anlegen, Folie anfügen und speichern, Vollbild-Modus, live editierbare Code-Folien mit Highlighting, Stufen-Filter und Löschen; Aufgabenkatalog: filtern, einzelne und alle Aufgaben einer Jahrgangsstufe übernehmen, Duplikat-Schutz und Löschen; Modul-Freigabe je Klasse: sperren mit gesperrter URL, vorzeitige Freigabe und Zurücksetzen auf den Lehrplan-Standard; Lehrkraft-Zugriff auf Schülerprojekte inklusive Absicherung gegen Fremdklassen; Syntax-Highlighting präsent in HTML-, Code-, SQL- und Diagramm-Editor).

## Dokumentation

- docs/ROADMAP.md: Ausbaustufen und Status
- docs/lehrplan.md: Zuordnung der Bereiche zu Jahrgangsstufen (LehrplanPLUS)
- docs/sso-m365.md: Einrichtung von Microsoft-365-SSO

Lizenz: privat (creativecat).
