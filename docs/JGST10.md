# Jahrgangsstufe 10 komplett vorbereitet (Informatik, Gymnasium Bayern, NTG)

Dieses Startpaket deckt den **FachlehrplanPLUS Informatik 10 (Gymnasium, NTG)** vollständig ab: alle drei Lernbereiche mit Präsentationen (inklusive Sprechernotizen für den Moderatorenbildschirm), Kapitel-Quizzen in der Quiz-Arena, Aufgaben für das ganze Schuljahr aus dem Katalog und zwei Kurzarbeiten für den Prüfungsmodus. Alles ist nach `php tools/migrate.php` sofort einsatzbereit.

**Rollentrennung**: Die Verwaltung der Quiz-Arena, der Moderatorenbildschirm mit Sprechernotizen und die Prüfungsverwaltung sind **ausschließlich für Lehrkräfte** sichtbar. Schülerinnen und Schüler sehen Präsentationen (ohne Notizen), ihre Aufgaben und spielen ausgeteilte Quiz.

**Zugelassene Lehrwerke als inhaltliche Orientierung**: „Informatik - Gymnasium Bayern" (C.C. Buchner, Bände für Jgst. 9/10) und „Informatik - Gymnasiale Oberstufe, Ausgabe 2021" (Westermann); der FachlehrplanPLUS gibt die Lernbereiche vor.

## Lernbereich 1: Datenmodellierung und relationale Datenbanksysteme (ca. 17 Std.)

| Baustein | Bereitstellung |
| --- | --- |
| Einstieg + Jahr-Überblick | Präsentation „Dein Informatik-Jahr in der 10" ([thema/informatik-jahr-10](/praesentationen/thema/informatik-jahr-10)) |
| Redundanz, Inkonsistenz, Anomalien | Präsentation ([thema/db-redundanz-anomalien](/praesentationen/thema/db-redundanz-anomalien)) + Quiz „Quiz: Redundanz und Anomalien" |
| 1:n- und n:m-Beziehungen modellieren | Präsentation ([thema/db-relationen-modellieren](/praesentationen/thema/db-relationen-modellieren)) + Quiz „Quiz: 1:n- und n:m-Beziehungen" |
| Primär-/Fremdschlüssel und JOIN | Präsentation ([thema/sql-join](/praesentationen/thema/sql-join)) + Quiz „Quiz: JOINs und Fremdschluessel" |
| Aufgaben | Katalog Jgst. 10: „ER-Modell für eine Schulverwaltung", „Datenmodellierung: Bibliothek mit n:m-Beziehung", „SQL: JOIN mit Bedingungen", „SQL: JOIN über drei Tabellen", „SQL: Auswertungen mit GROUP BY", „SQL: Daten gezielt ändern mit UPDATE und DELETE" |
| Kurzarbeit 1 | „Kurzarbeit 1: Datenbanken (Informatik 10)" - 4 Teilaufgaben, je 5 BE (20 BE), mit Musterlösungen, im Modul Prüfungen & Arbeiten |

## Lernbereich 2: Objektorientierte Modellierung und Programmierung (ca. 27 Std.)

| Baustein | Bereitstellung |
| --- | --- |
| Warm-up (Wiederholung Jgst. 9) | Präsentation ([thema/java-wiederholung](/praesentationen/thema/java-wiederholung)) - schließt mit Übung und Lösung ab |
| Arrays und Referenzen | Präsentation ([thema/java-arrays](/praesentationen/thema/java-arrays)) + Quiz „Quiz: Arrays und Referenzen" |
| Datenkapselung | Präsentation ([thema/java-datenkapselung](/praesentationen/thema/java-datenkapselung)) + Quiz „Quiz: Datenkapselung" |
| Vererbung und Polymorphismus | Präsentation ([thema/java-polymorphismus](/praesentationen/thema/java-polymorphismus)) + Quiz „Quiz: Vererbung und Polymorphismus" |
| Java trifft Datenbank | Präsentation ([thema/java-datenbank](/praesentationen/thema/java-datenbank)) |
| Aufgaben | Katalog Jgst. 10: „Java: Arrays und Referenzen", „Java: Datenkapselung mit private", „Java: Vererbung am Beispiel Tiere", „Java: Fahrradverleih: Flotte als Array verwalten", „Java: Spartenabhängige Mitgliedsbeiträge mit Polymorphismus" |
| Kurzarbeit 2 | „Kurzarbeit 2: Objektorientierte Programmierung (Informatik 10)" - 4 Teilaufgaben, je 5 BE (20 BE), mit Musterlösungen |

## Lernbereich 3: Projekt (ca. 12 Std.)

| Baustein | Bereitstellung |
| --- | --- |
| Projektphasen und -planung | Präsentation ([thema/projekt](/praesentationen/thema/projekt)) + Quiz „Quiz: Projekt in Informatik" |
| Aufgaben | Katalog Jgst. 10: „Projekt: Planung und Dokumentation", „Projekt: Buchungssystem: Modellierung und erster Sprint" |

## Empfohlener Stundenverlauf (ca. 56 Std.)

1. **Einstieg** (1): Informatik-Jahr-10-Deck, Ausblick, Warm-up ankündigen.
2. **Warm-up** (3): Java-Wiederholung mit Übung, dann Kurzarbeit 1 (Datenbanken) oder direkt in LB1 starten.
3. **LB1 Datenbanken** (17): Redundanz und Anomalien, dann 1:n/n:m modellieren, JOIN praktisch in der SQL-Werkstatt, GROUP BY, UPDATE/DELETE, am Ende Kurzarbeit 1.
4. **LB2 OOP** (27): Arrays → Referenzen → Datenkapselung → Vererbung/Polymorphismus → Objektkommunikation am Fahrradverleih → Java trifft Datenbank, am Ende Kurzarbeit 2.
5. **LB3 Projekt** (12): Projektplan (Deck „Projekt"), Modellierung, arbeitsteilige Implementierung in der Code-Werkstatt, Test, Dokumentation, Vorstellung.

## Quiz-Arena (nur Lehrkräfte verwalten)

Zu jedem Kapitel liegt ein fertiges Quiz bei (7 Quizze, je 5 Fragen), angelegt unter dem Demo-Lehrerkonto und **austeilbereit, aber nicht ausgeteilt**: Austeilen an die eigene Klasse passiert bewusst über die Quiz-Arena, damit du kontrollierst, wann die Klasse spielt. Spielweise: Multiple-Choice mit Punkten und Bestwertung, sofortiges Feedback pro Frage, Ergebnisliste für die Lehrkraft - funktional inspiriert von bekannten Live-Quiz-Apps, ohne deren Markennamen zu verwenden.

## Sprechernotizen (Moderatorenbildschirm, nur Lehrkräfte)

Jede Seed-Präsentation der 10. Klasse enthält kurze Sprechernotizen (Kernbotschaft, Impulsfrage, Zeitangabe). Der Moderatorenbildschirm (Knopf „Moderator") zeigt sie in der Sidebar; die Schülerische Ansicht gibt sie niemals aus. Eigene Notizen schreibst du im Präsentations-Editor je Folie.

## Aufgabenbereich: Rollen und Features

- Schülerinnen und Schüler: Aufgaben mit Status (offen, abgegeben, korrigiert, überfällig), ToDos (Vorlage abhaken + eigene), Materialien-Downloads, Selbsteinschätzung bei der Abgabe (sieht nur die Lehrkraft), optionale Musterlösung nach Korrektur bzw. nach Termin.
- Lehrkräfte: Anlegen mit WYSIWYG-Beschreibung, Themengebiet, Vorlagen (Materialien, ToDo-Vorlagen), Java/Python-Varianten mit KI-Konvertierung, Verteilen an Klassen/Mehrere, Start- und Abgabetermin, Frist-Sperre, Fortschritt und „noch zu korrigieren"-Pillen, Termin-Übersicht, Korrektur mit Kennzahlen (Abgabequote, Verspätete, Notenschnitt), KI-Erstbewertung (anonymisiert, Gegenprüfung Pflicht), Export/Import als Datei.
