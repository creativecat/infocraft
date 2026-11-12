# Changelog

Alle nennenswerten Änderungen an InfoCraft werden in dieser Datei dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt Semantic Versioning (#145): neue Funktionen erhöhen MINOR,
reine Fehlerbehebungen PATCH, inkompatible Umstiege MAJOR.

## [0.15.0] - 2026-11-12

### Block-Werkstatt

- Koordinatensystem wie in der Coding-Werkstatt: Ursprung oben links, x nach rechts (0 bis 480), y nach unten (0 bis 360). Ältere Projekte (Version 1/2) werden beim Laden automatisch umgerechnet (#144).
- Lernmodus und Expertenmodus (#151): Der Lernmodus zeigt das Basis-Angebot, der Expertenmodus zusätzliche Blöcke, die Java-/Python-Ansicht und die Konsole. Die Wahl wird pro Browser gemerkt.
- Konsole für die Bühne (#152): Im Expertenmodus steuert eine Konsole Figuren gezielt, auch namentlich (`robo.gehe 50`, Namen mit Leerzeichen wie `Figur 1.gehe 50` funktionieren), mit Befehlen wie `hilfe`, `info`, `gehe`, `drehe`, `x`, `y`, `zeige`, `verstecke`, `sage`, `kostuem`, `ton`, `sende`, `stoppe`.
- Objektorientierte Code-Ansicht (#152): Der Java-/Python-Generator erzeugt für jede Figur eine eigene Klasse, die von der Basisklasse `Figur` erbt; die Bühne wird ebenfalls ein Objekt. Skripte werden Methoden, ▶-Skripte starten in `main` als parallele Threads. Der erzeugte Python-Code ist direkt lauffähig kompilierbar.
- Bunte Kategorie-Knöpfe (#150): Jeder Knopf in der Palette trägt die Farbe der Blöcke dahinter.
- Einfüge-Marke in Schleifen und Verzweigungen (#148): Beim Ziehen in einen Block-Körper entscheidet die obere oder untere Hälfte des Zielblocks über die genaue Stelle; eine pulsende Linie zeigt sie.
- Ziehen ohne Flackern (#153): Der gezogene Block bleibt während des Ziehens an seinem Platz sichtbar (nur abgeblendet), statt dass die Skriptfläche kurz leer wird.
- Optik-Überholung (#149): durchgängiges 8px-Raster, einheitliche Radien, zweistufige Schatten, Hover-Zustände, animierte Einfüge-Linien, Terminal-Optik für Protokoll und Code-Ansicht.

### Optimierungen

- Der gezogene Block folgt dem Zeiger per `transform` statt `left`/`top`: kein Bildaufbau (Reflow) mehr pro Mausbewegung, flüssigeres Ziehen auch bei schnellen Bewegungen.
- Sprechblasen messen ihre Textbreite nur noch beim ersten Auftreten eines Blasentexts statt bei jedem Bild; die Breite wird pro Figur wiederverwendet.
- Das Konsolenprotokoll ist auf 200 Zeilen gedeckelt, damit das Element in langen Sitzungen nicht unbegrenzt wächst und ältere Einträge den Browser beim Neurichten bremsen.

### Projekt und Dokumentation

- Versionierung nach Semantic Versioning mit `APP_VERSION` und Anzeige im Footer (#145).
- Dateikopf mit Autor, Lizenz und Version in jeder Projektdatei (#145).
- CHANGELOG.md (diese Datei) und LICENSE (CC BY-NC-SA 4.0) ergänzt (#145).
- Wiki-Dokumentation für Lehrkräfte (Bedienung, Lern-/Expertenmodus, Block-Referenz) und Mitentwickler (Architektur, Koordinaten und Migration, Testkonzept) (#146, #147).

## [0.14.0] - 2026-11-11

### Block-Werkstatt

- Klang-Kategorie (#130): Töne spielen und starten, alle Klänge stoppen, Lautstärke; ohne Audio-Unterstützung läuft das Skript still weiter.
- Nachrichten (#131): senden, senden und warten, Wenn-Nachricht-kommt-Hut; wenn eine Nachricht ankommt, laufen alle passenden Skripte im selben Bild los.
- Sensoren und Zeiger (#133): Maus-x/y, Maus geklickt, Figur berührt Figur, Abstand zu; Klicks auf der Bühne treffen die oberste Figur oder die Bühne.
- Bewegung-Plus (#134): gleiten zu, vom Rand abprallen (mit Einrücken), sich zu drehen.
- Aussehen-Plus (#135): Denkblase, nächstes Kostüm, Farbeffekte (Farbe, Helligkeit) setzen, ändern und löschen, Ebenen nach vorn und hinten.
- Rechnen-Plus (#136): Rest von, runde, Wurzel, Betrag, verbinde, Buchstabe von, Länge von.
- Code-Ansicht (#140): Java- und Python-Ansicht des Block-Programms im Dialog, mit Kopieren, Herunterladen und Übernahme als neues Java- oder Python-Projekt (#141).
