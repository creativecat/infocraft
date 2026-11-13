# Changelog

Alle nennenswerten Änderungen an InfoCraft werden in dieser Datei dokumentiert.
Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt Semantic Versioning (#145): neue Funktionen erhöhen MINOR,
reine Fehlerbehebungen PATCH, inkompatible Umstiege MAJOR.

## [0.26.0] - 2026-11-13

### SQL-Werkstatt: Cursor deckungsgleich auch bei langen Anweisungen, Sprung zu den Aufgaben oben (#200)

- Der Cursor im SQL-Bereich läuft nicht mehr vor dem farbigen Text davon, egal wie lang die Anweisung wird (#200): Die SQL-Eingabefelder brechen lange Zeilen jetzt nicht mehr weich um, sondern scrollen horizontal wie ein Code-Editor, und das farbige Overlay bricht genauso wenig um. Der Versatz kam daher, dass das Eingabefeld am Zeilenende umbroch, das Overlay aber geradeaus lief, und mit jeder umbrochenen Zeile weiter auseinanderdriftete. Zusätzlich übernimmt das Overlay jetzt auch Kerning-, Ligaturen- und Breiteneigenschaften aus dem Eingabefeld.
- Oben auf der Abenteuer-Seite gibt es jetzt zwei Knöpfe: direkt zu den Kapiteln und zur Abenteuer-Übersicht, ohne an der Konsole vorbei zu scrollen. Die klassische Werkstatt bekommt denselben Sprung zu ihren Aufgaben (#200).

### InfoBooks: Verteilen mit Ziel-Auswahl, reparierte Knöpfe und ehrliche Hinweise (#201-#204)

- Der Knopf „Alle InfoBooks" funktioniert für Schülerinnen und Schüler wieder (#201): Er zeigte auf die Übersicht, die sie sofort auf ihr Klassen-InfoBook zurückleitet, sodass nichts passierte. Für sie zeigt er jetzt auf den Buch-Start, für Lehrkräfte bleibt alles wie bisher.
- Beim Verteilen einer Seite lässt sich jetzt der Ziel-Abschnitt der Klasse direkt auswählen oder ein neuer Abschnitt mit eigenem Titel anlegen (#202), statt automatisch im ersten Abschnitt des Zielbereichs zu landen. Ganze Abschnitte lassen sich an eine Klasse verteilen: alle Seiten landen als Kopien im gewählten Ziel-Abschnitt. Und ein ganzes Buch lässt sich an eine Klasse verteilen: Für jeden Abschnitt mit Seiten (Lernbereich und Zusammenarbeit, private Bereiche bleiben aus) entsteht ein eigener Abschnitt im Klassen-InfoBook.
- Beim Kopieren in ein anderes Buch werden Seiten-Links sicher entfernt (#202): Link-IDs gelten nur innerhalb eines Buchs, eine übernommene ID hätte im Ziel auf eine ganz andere Seite gezeigt.
- Der Hinweis im Lesemodus sagt jetzt der Wahrheit (#203): In der eigenen Vorschau steht deutlich, dass „Bearbeiten" oben rechts zurück in den Editor führt; der bisherige Lernbereich-Text („Änderungen macht die Lehrkraft") ließ die Bearbeitung von Zusammenarbeit-Seiten verloren wirken.
- Links im Bereich Zusammenarbeit zeigen nur noch auf Seiten, die der ganzen Klasse mindestens zum Lesen zur Verfügung stehen (#203): Beim Speichern, im Editor und beim Anzeigen bleiben private „Mein Bereich"-Seiten als Link-Ziele draußen.
- Die Abschnittsansicht für Lehrkräfte erklärt die Sichtbarkeit (#204): Ein Hinweiskasten macht transparent, dass die Ansicht ausschließlich Abschnitte dieses einen Buchs zeigt und was die Klasse jeweils sieht (Lernbereich und Zusammenarbeit für alle, private Bereiche nur für ihre Besitzerin oder ihren Besitzer).

## [0.25.0] - 2026-11-13

### SQL-Werkstatt: Kapitel-Weg mit Szenenbildern, echte Tipps und erhaltene Eingaben (#197-#199)

- Der Cursor im SQL-Bereich sitzt jetzt exakt auf dem farbig hervorgehobenen Text (#197): Das Overlay, das die Syntaxfarben zeichnet, übernimmt beim Anlegen alle schriftrelevanten Einstellungen direkt aus dem Eingabefeld, statt auf parallele CSS-Regeln zu vertrauen. Die beiden Regeln für die SQL-Schriftgröße sind zu einer zusammengefasst, sodass Cursor-Metrik und farbiger Text nicht mehr auseinanderlaufen können.
- Die Tipps der Abenteuer-Kapitel verraten die Lösung nicht mehr (#198): Jedes der 42 Kapitel hat einen pädagogischen Hinweis, der die Richtung weist (etwa „bündele die Zeilen je Gruppe“), und zusätzlich fein abgestimmte Varianten für typische Fehlversuche, z. B. fehlendes WHERE, vergessene Sortierung, falsche Spaltenzahl oder ein UPDATE ohne Begrenzung.
- Fehlversuche bleiben erhalten (#198): Nach einem nicht gelösten Versuch steht die eingegebene Anweisung weiter im Feld und zusätzlich in einer roten Hinweis-Karte unter der Aufgabe, zusammen mit einer gezielten Rückmeldung, was daran noch nicht passt. Nicht ausführbare Anweisungen bekommen einen Hinweis zu Schreibweise und Aufbau. Die Analyse vergleicht Tabelle, Spalten und Zeilenzahl mit der Musterlösung und wählt daraus den passenden Hinweistext.
- Die Kapitel eines Abenteuers liegen jetzt als Weg mit Stationen (#199): Statt breiter Vollzeilen-Karten stehen die Kapitel in einem versetzten, zwei Spalten mäandernden Layout mit Nummern-Stationen und gestrichelter Verbindungslinie. Jedes Kapitel beginnt mit einem eigenen Szenenbild (Raumbrücke, Inventarliste, Festivalplakat, Schatzkarte ...), erzählt zuerst die Geschichte und hebt dann die konkrete Aufgabe in einem eigenen Kasten hervor. Verschlossene Kapitel zeigen ihr Bild gedimmt.
- Bestehende Installationen bekommen alle neuen Texte, Tipps und Bilder automatisch über den Migrator; gelöste Kapitel bleiben gelöst, da Lösungen und Prüfungen unverändert bleiben.

## [0.24.0] - 2026-11-13

### SQL-Werkstatt: Abenteuer-Bereich mit Quest-Übersicht und drei neuen Abenteuern (#195, #196)

- Die SQL-Werkstatt bekommt einen eigenen Abenteuer-Bereich für erzählte SQL-Missionen (#195): Eine Quest-Übersicht unter „werkstatt/sql/abenteuer“ zeigt alle Abenteuer als Karten mit handgebauten Szenenbildern (Insel, Raumstation, Museum, Festival), Story-Teaser und Fortschrittsbalken. Die Übersicht ist von der Werkstatt-Startseite aus erreichbar.
- Abenteuer öffnen sich in einer Kapitel-Ansicht (#195): Rahmenhandlung und Szenenbild oben, dazu die SQL-Konsole und ein aufklappbarer Werkzeugkasten mit Tabellen und ER-Diagramm. Die Missionen erscheinen als Kapitel: Gelöste zeigen die Musterlösung, das aktuelle ist violett hervorgehoben, kommende sind noch verschlossen, bis die Geschichte weitergeht. Ist alles gelöst, gratuliert ein Abschluss-Banner zum Ende der Geschichte.
- Drei neue Abenteuer mit je 10 Kapiteln und eigenen Seed-Datenbanken (#196): „Raumstation Alpha“ (Meteoritenschauer, Crew retten: SELECT, WHERE, JOIN, COUNT, UPDATE, GROUP BY), „Das Museum des verschwundenen Meisterwerks“ (Gemäldediebstahl aufklären: JOIN, IS NULL, GROUP BY mit HAVING, UPDATE, Unterabfragen) und „Festival der jungen Bühnen“ (Programm retten: ORDER BY, GROUP BY, UPDATE mit Unterabfragen, Dreifach-Join).
- Das bestehende „Dateninsel-Abenteuer“ zieht in die neue Oberfläche ein; Austeilen, Rechte und Lösungskontrolle laufen für alle Abenteuer unverändert über die bewährte Infrastruktur. Die Beispielklasse 9R erhält die neuen Abenteuer direkt mit Schreibrechten; bestehende Installationen bekommen alles idempotent über den Migrator.

## [0.23.0] - 2026-11-13

### InfoBook: Präsentation mit Einblend-Schritten, Beispiel-Buchpakete (#191-#194)

- Die Präsentation bekommt ein echtes Regie-System (#191): Im Bearbeitungsmodus legt der Kontextbereich für jedes Element fest, in welchem **Schritt** es erscheint (0 = sofort mit der Folie, gleiche Nummern erscheinen gemeinsam) und mit welcher Animation es einblendet (weich, von unten, von links oder als Zoom). Ein kleines Badge am ausgewählten Element zeigt die Schrittnummer, die Anzeige „Schritt X / Y" nennt den Fortschritt. Die Folie passt sich dabei in den Bildschirm, statt zu übergroß zu scrollen.
- Das Ein-/Ausklapp-Icon der InfoBook-Navigation ist kein Hamburger mehr: „«" klappt die Navigation zu, „»" wieder auf (#192). Das Icon der Hauptnavigation darüber bleibt unberührt.
- Die Überschrift „Freie Fläche" ist aus dem Editor entfernt (#193). Der Hintergrund braucht keine Benennung; ein Klick auf die Fläche wählt weiterhin nichts aus.
- Zwei importierbare Beispiel-Buchpakete für Informatik 9 liegen unter docs/beispielpakete: „if-Anweisungen in Java" (11 Seiten) und „Vererbung in Java" (10 Seiten), nachgebaut aus den Website-Präsentationen (#194). Beide nutzen die neuen Schritte und Animationen; eine README beschreibt den Import über die InfoBook-Navigation.

## [0.22.0] - 2026-11-12

### InfoBook: Navigation, Präsentation und Klassenkarte verfeinert (#186-#190)

- Die Abschnitte in der Seitennavigation sind aufklappbar: Jeder Abschnitt steht als eigener Eintrag, der seine Seiten direkt in der Navigation zeigt (#186). Der aktive Abschnitt ist offen markiert, der Aufklappzustand bleibt je Abschnitt im Browser gespeichert. Das Feld „Neue Seite" bleibt allein am aktiven Abschnitt, egal wie viele Abschnitte offen sind.
- „Buchpaket importieren" hat einen festen Platz: unten in der Navigation, immer am selben Ort, aufklappbar (nur Verwalter) (#187). Ein leerer Abschnitt zeigt dasselbe Karten-Raster wie ein gefüllter, mit einer Platzhalter-Karte statt einer fremd aussehenden Hinweisbox.
- Der Präsentationsmodus blendet die Elemente einer Folie einzeln per Klick (oder Leertaste bzw. Pfeiltaste rechts) ein, Pfeil links blendet rückwärts wieder aus (#188). Autoplay mit Zeitsteuerung, Fortschrittsbalken und die Zeitauswahl entfallen; eine kleine Anzeige nennt das nächste Element.
- Die Vorschau (Lesemodus) einer Seite öffnet im gleichen Fenster statt in einem neuen Tab; der Knopf „Bearbeiten" führt zurück (#189).
- Die Klassenkarte im Code-Modul ist ein echter UML-Klassenkasten: Klassenname oben, darunter die Abteilungen Attribute und Methoden (#190). Editor und Lesemodus teilen sich denselben Baustein.

## [0.21.0] - 2026-11-13

### InfoBook: Buch- und Seitenansicht vereinheitlicht (#185)

- Die Buchansicht ist zur Abschnittsansicht geworden und sieht aus wie die Seitenansicht: gleiche Kopfzeile, gleiche Seitennavigation (gemeinsames Template-Partial), im Hauptbereich die Seiten des Abschnitts als Karten. Ein großer Hero-Kopf und eine zweite, anders aufgebaute Navigation entfallen.
- Breadcrumb in der Kopfzeile beider Ansichten: 📓 Buch › Abschnitt › Seite zeigt immer, wo man im InfoBook steht. Der Buch-Eintrag startet die zuletzt besuchte Seite, der Abschnitt führt in die Abschnittsansicht.
- Der Buch-Link startet direkt auf einer Seite: zuerst die zuletzt besuchte Seite (je Buch im Browser gemerkt), sonst die erste Seite des ersten Abschnitts mit Seiten. Die Abschnittsansicht bleibt über die Breadcrumb und den Abschnitts-Parameter erreichbar.
- Der Ein-/Ausklapp-Knopf der Navigation ist ein schmaler Icon-Knopf (☰/▸) im Stil der Kopfzeilen-Buttons, in beiden Ansichten gleich.
- Abschnitte in der Navigation führen direkt auf ihre erste Seite (vorher: Umweg über die Buchansicht); Abschnitte ohne Seiten führen in die Abschnittsansicht mit dem Neue-Seite-Formular.
- Verwaltungsaufgaben (Buchpaket importieren, Abschnitt löschen) sitzen im Hauptbereich der Abschnittsansicht statt in der Navigation; nach einem Import landet man direkt in der neuen Abschnittsansicht.

## [0.20.0] - 2026-11-12

### InfoBook: Seitennavigation (#184)

- Die linke Navigation auf einer InfoBook-Seite zeigt nur noch die Seiten des aktiven Abschnitts; die anderen Abschnitte stehen als je eine Zeile in der Liste (mit Bereichsüberschriften), statt alle Seiten aller Abschnitte aufzuklappen.
- Genau ein „Neue Seite“-Feld, direkt unter den Seiten des aktiven Abschnitts, statt einem je Abschnitt. Der aktive Abschnitt ist als Label erkennbar und kein Link mehr auf sich selbst.
- „Neuer Abschnitt“ unten klappt auf (ohne JavaScript nutzbar), statt als Dauerformular Platz zu verbrauchen.
- Datenschärfung: Schülerinnen und Schüler sehen in der Navigation nur die für sie sichtbaren Abschnitte; fremde „Mein Bereich“-Bereiche erscheinen nicht mehr (der eigene wird wie in der Buchansicht sicher angelegt). Gilt für Seitennavigation, Buchansicht und Präsentationsmodus gemeinsam.

## [0.19.0] - 2026-11-12

### Übersicht (#175 bis #183)

- Rückmeldungs-Runde: neun Verbesserungen aus dem Testbetrieb, von der Code-Werkstatt über die Admin-Einstellungen bis zur Startseite.

### Code-Werkstatt (#175, #176)

- „Projekt verteilen“ zieht als aufklappbarer Button nach oben rechts über die Kästen; das Formular öffnet sich direkt darunter.
- Neuer Ordner liegt jetzt unten direkt bei den Ordnern und Projekten, wahlweise auf der Hauptebene oder als Unterordner eines bestehenden Ordners; der Unterordner-Knopf ist als kleiner Pill-Button hervorgehoben.

### Präsentationsmodus im InfoBook (#177)

- Das eigenständige Präsentations-Modul entfällt aus der Navigation: InfoBook-Seiten zeigen sich jetzt mit Folien, gestaffelt einblendenden Bausteinen, Autoplay mit wählbarer Foliendauer (5 bis 30 Sekunden), Fortschrittsbalken und Tastatursteuerung (Pfeiltasten, Leertaste, P für Autoplay, F für Vollbild). Alte Präsentationen bleiben über ihre Direktlinks erreichbar.

### Rechte und Verwaltung (#178, #179, #182, #183)

- Der CSV-Import steht nur noch der Administration offen.
- Lehrkräfte sehen in der Nutzerverwaltung nur noch die Konten ihrer eigenen Klassen.
- Demo-Modus in den Admin-Einstellungen umschaltbar (Fallback bleibt die Konfigurationsdatei).
- Neue Admin-Funktionen: Ankündigung auf der Startseite und Kontenliste als CSV-Export.
- Jahrgangsstufen-Hinweise und Systemstatus auf der Startseite sehen nur noch Administratoren.

### Impressum (#181)

- Klarere Optik mit strukturiert Angabentabelle; Issue-Nummern sind aus den Beschriftungen entfernt.

## [0.18.0] - 2026-11-12

### Übersicht (#168, #169, #170)

- Drei eigene Kästen oben: Neues Projekt, Neuer Ordner, Verteilen; darunter die Liste der Ordner und Projekte in voller Breite.
- Beim Anlegen fragt InfoCraft nur noch eine „Projektart" ab (Java-Programm, Python-Programm, Assembler, HTML-Seite, Blöcke, Automat, Kellerautomat, Turingmaschine, Schaltwerk); die doppelte Abfrage aus Sprache und Art entfällt.
- Jede Projektart zeigt ihr eigenes Symbol in der Liste, auch die neuen Werkstätten (🤖, 🥞, 🧾, 🔌) statt des Python-Symbols.

### Rechtliches (#171)

- Impressum und Datenschutzerklärung starten mit einer gut recherchierten Mustervorlage: Informationen nach § 18 Abs. 2 MStV (Schule oder Privatperson), Katalog der verarbeiteten Daten, Rechtsgrundlagen der DSGVO (Art. 6), Speicherdauer, Betroffenenrechte (Art. 15 bis 21) und Beschwerderecht beim BayLDA. Eigene Angaben überschreiben die Musterfelder.

### Automaten-Werkstätten (#172)

- Kellerautomat und Turingmaschine erhalten dieselben Funktionen wie der endliche Automat: Rückgängig, Wiederherstellen, Vergrößern, Verkleinern, Einpassen, Bild-Export und Abspielen. Ein gemeinsamer Werkzeugkasten stellt die Bausteine dafür bereit.
- Beide Werkstätten zeigen die formale Beschreibung als Tupel mit Übergangstabelle; die Turingmaschine prüft zusätzlich mehrere Wörter auf einmal und warnt bei Nichtdeterminismus.

### InfoBook (#173)

- Neuer Baustein „HTML-Seite": eigene Eingabemasken laufen in einer abgeschotteten Vorschau (Sandbox ohne Zugriff auf die Plattform) und lassen sich mit Java- und SQL-Fenstern auf einer Seite kombinieren, etwa Maske, Verarbeitung und Datenausgabe.

### Fehlerbehebung (#174)

- Endlicher Automat: Die Zustandskreise erschienen im Browser als schwarze Fläche. Ursache waren Farbwerte, die die Werkstatt aus nicht vorhandenen Stilvariablen las; SVG füllte die Kreise daraufhin schwarz. Die Werkstatt fällt jetzt auf ihre Standardfarben zurück und das Stylesheet definiert die Farbwerte zentral.

## [0.17.0] - 2026-11-12

### Rechtliches (#167)

- Impressum und Datenschutz unterscheiden den Betreiber: Schule oder Privatperson. In den Admin-Einstellungen wählt die Betreiberin die Art, die Seite zeigt nur die passenden Felder.

### InfoBook (#94, #95, #96)

- Die Eingabe rückt nach einer öffnenden Klammer automatisch ein.
- Code-Vervollständigung mit Klassenfeldern und Methoden: nach einem Punkt erscheinen die Vorschläge, aufzuklappende Klassenkarten entfallen.
- Mermaid-Diagramme lassen sich in Code-Zellen per Vervollständigung einfügen.

### Block-Werkstatt (#132, #137, #138, #139, #142, #143)

- Klone: Blöcke „Wenn ich als Klon gestartet werde“, „erzeuge Klon von“, „lösche diesen Klon“; Obergrenze 300 Klone.
- Listen: anlegen, Eintrag anhängen, löschen, setzen, lesen, Länge, enthält; nur im Expertenmodus.
- Eigene Bausteine: Kategorie „Meine Blöcke“ mit Baustein-Definition und Aufruf, Rücksprung bei Verschachtelung bis 64 Ebenen.
- Bühnen-Hintergründe: vier Vorlagen (Papier, Sonnenaufgang, Meer, Wiese) über die Bühnen-Skripte.
- Vorlagen-Knopf mit vier Beispielprogrammen (Begrüßung, Labyrinth, Verfolger-Spiel, Rhythmus).
- Vollbild-Knopf für die Bühne mit Start, Stopp und Beenden, Beenden auch mit Esc.

### Neue Werkstätten (#164, #165, #166)

- Kellerautomat: Zustände und Übergänge zeichnen, Wörter mit Keller prüfen, Akzeptanz am Endzustand oder bei leerem Keller, Wortlisten, Bild-Export.
- Turingmaschine: Regeln im Format „lesen / schreiben, Richtung“, Band mit Lese-/Schreibkopf, Schrittliste, Determinismus-Warnung.
- Schaltwerk: Schalter, Lampen, UND, ODER, NICHT, NAND, XOR, Taktgeber und D-Flipflop, Drähte ziehen, Auswertung, laufender Takt.

## [0.16.0] - 2026-11-12

### Rechtliches (#160)

- Impressum und Datenschutzerklärung als eigene, öffentliche Seiten (`/impressum`, `/datenschutz`), die sich aus den Angaben der Admin-Einstellungen aufbauen.
- Neues Panel „Impressum und Datenschutz“ in den Admin-Einstellungen: Anbieter, Vertretung, Anschrift, Kontakt, ergänzende Angaben, Datenschutzbeauftragte Person, Hosting, verarbeitete Daten und freier Text.
- Der Fuß der Plattform verlinkt Impressum und Datenschutz, sobald Angaben hinterlegt sind.

### Block-Werkstatt (#161, #162)

- Lern- und Expertenmodus als klar beschrifteter Segment-Schalter mit sichtbarem Aktivzustand; Standard ist der Lernmodus.
- Die Moduswahl wird im Nutzerkonto gespeichert und gilt plattformweit, nicht nur im jeweiligen Browser.
- Der Lernmodus verzichtet auf die Kategorien für Fortgeschrittene: Variablen, Nachrichten-Blöcke (senden, empfangen, warten) und der Knopf für neue Variablen erscheinen nur im Expertenmodus. Bereits im Skript liegende Experten-Blöcke bleiben sichtbar und lauffähig.

### Automaten-Werkstatt (#163)

- Neuer Projekttyp „Automat“ für endliche Automaten: Zustände zeichnen, Übergänge beschriften, Start- und Endzustände setzen.
- Wörter prüfen: sofort, Schritt für Schritt oder als Abspielfahrt mit farbiger Zustandsanzeige; mehrere Wörter auf einmal testen.
- Formale Beschreibung live: Fünf-Tupel, Chips für Vollständigkeit und Determinismus, Übergangstabelle δ.
- Trap-Zustand auf Knopfdruck: alle fehlenden Übergänge führen künftig in einen (vollständigen) Trap-Zustand.
- Analyse meldet fehlende Übergänge und Nichtdeterminismus; die Simulation rechnet mit Zustandsmengen und bleibt auch bei NEA korrekt.
- Bild-Export des Automaten als SVG-Datei; automatisches Speichern wie in den anderen Werkstätten.
- Engine ohne DOM-Abhängigkeiten, dadurch in Node-Tests vollständig prüfbar (18 neue UI-Tests, T114-Serie).

### Tests

- 27 neue UI-Tests (511 → 538): Modus-Schalter mit Konto-Speicherung, Lernmodus-Kategorien, Automaten-Engine.

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
