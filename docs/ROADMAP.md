# InfoCraft-Roadmap

Übersicht der Ausbaustufen. Details zu den Bereichen stehen in docs/lehrplan.md und im Code.

| Phase | Inhalt | Status |
| --- | --- | --- |
| 1 | Grundgerüst: Front-Controller, Router, Templates, Rollen, Anmeldung (Passwort, Demo, SSO M365, CSV-Import), SQLite-Auto-Migration, Modul-Freigabe je Klasse (Standard, Freigeben, Sperren mit Routenschutz) | fertig |
| 2 | SQL-Werkstatt: Vorlagen (Shop, Instagram, Dateninsel-Adventure), eigene Kopie pro Person, Rechte (lesen, lesen und schreiben, Tabellen anlegen), ER-Diagramme (Mermaid), Tabellen als Klassendiagramm übernehmen, Missionen mit automatischer Prüfung, Austeilen an Klassen und Einzelne | fertig (Basis) |
| 3 | HTML-Werkstatt: WYSIWYG-Editor mit Sofortvorschau (live beim Tippen), Vorlagen (Grundgerüst, Bildergalerie, Kontaktformular), Projekte in Ordnern | fertig (Basis) |
| 4 | Code-Werkstatt: Projekte, Ordner, Drag & Drop, Austeilen; Lehrkräfte sehen und bearbeiten die Projekte ihrer Schülerinnen und Schüler (nur eigene Klassen); Python läuft direkt im Browser (Pyodide); Java läuft direkt im Browser über eine eigene Simulation: Java-IDE mit Dateileiste links, Editor in der Mitte, Konsole rechts; Objektkonsole (Objekte erzeugen, Methoden aufrufen, Ausdrücke auswerten, Autovervollständigung) und Objektdiagramm (alle erzeugten Objekte mit Attributwerten und Verweisen), Startpakete bewusst ohne main-Methode; eingebaute Mini-Datenbank (SQL direkt in Java über die Klasse Datenbank); Klassendiagramme aus Java- und Python-Skripten erzeugen; Auto-Vervollständigung im Editor folgt | fertig (Basis; Auto-Vervollständigung im Editor folgt) |
| 5 | Quiz-Arena: Quiz anlegen und austellen, Spielen mit Punkten und Bestwertung, Ergebnisliste; Live-Runden im Kahoot-Stil folgen. Prüfungsmodus: Gruppen mit Vorlage pro Gruppe, Teilaufgaben mit BE und Musterlösung, Sperre aller Bereiche während der Prüfung, Korrektur manuell und anonymisiert per API (Gegenprüfung Pflicht), Herausgabe mit Musterlösungs-Vergleich; alles auch für Schularbeiten ohne Sperre | fertig (Basis; Live-Runden folgen) |
| 6 | Wiki, Präsentationen, Lernvideos: Wiki mit Nachschlage-Seiten (Markdown-lite, inklusive Links), Stufen-Filter, Lehrkraft-Verwaltung, Anleitung für Lehrkräfte als Seed; Präsentationen aus Text- und Code-Folien (Java, Python, SQL) mit Vollbild-Modus, Pfeiltasten-Navigation und live editierbarem Code mit Syntax-Highlighting; jede Präsentation mit Übungs- und Lösungsfolie, permanente Themen-Links und Wiki-Themenübersicht; Lehrplan-Abdeckung Jgst. 9 (funktionale Modellierung, Tabellenkalkulation, Datenmodellierung, SQL, Java-OO, Datenschutz) und Jgst. 10 (Wiederholung, JOIN, Arrays, Datenkapselung, Polymorphismus, Projekt, Java-Datenbank); Lernvideos folgen | in Arbeit (Wiki und Präsentationen fertig, Lernvideos folgen) |
| 7 | Aufgabenpool: Anlegen und Austeilen an Klassen/Einzelne; kuratierter Katalog mit fertigen Aufgaben (Jgst. 6-13, Filter nach Stufe und Typ), Übernahme einzelner Aufgaben oder aller einer Jahrgangsstufe, Duplikat-Schutz, Löschen eigener Aufgaben | fertig (Basis) |
| 8 | Diagramme: Klassendiagramme (Mermaid-Editor, Auto-Erzeugung aus Java/Python/SQL, PNG-Export) erweitert um Struktogramme (Nassi-Shneiderman: Sequenz, wenn/sonst, solange, wiederhole/bis) und Datenflussdiagramme (Quellen, Senken, Prozesse, Speicher, Kantenbeschriftungen) mit eigener, Mermaid-ähnlicher DSL, serverseitigem Rendering, Live-Vorschau, Fehlermeldungen mit Zeilennummer und PNG-Export (Datenfluss) | fertig (Basis) |

## Nächste Schritte

1. Live-Runden in der Quiz-Arena,
2. Auto-Vervollständigung im Java-Editor (die Objektkonsole hat sie bereits),
3. Lernvideos (Phase 6).

Oberstufen-Funktionen (ab Jahrgangsstufe 11) sind über die Level-Angaben der Module bereits vorbereitet.
