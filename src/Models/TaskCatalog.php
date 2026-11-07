<?php
declare(strict_types=1);

namespace Infocraft\Models;

/**
 * Fester Aufgabenkatalog: kuratierte, fertige Aufgaben passend zum
 * bayerischen LehrplanPLUS (Gymnasium). Lehrkraefte uebernehmen einzelne
 * Aufgaben oder alle einer Jahrgangsstufe mit einem Klick in den eigenen
 * Pool (dort sind sie frei editierbar bzw. austeilbar als eigene Kopie).
 */
final class TaskCatalog
{
    /**
     * Themengebiete (LehrplanPLUS) je Katalog-Eintrag. Beim Übernehmen wird
     * das Thema übernommen; existiert es noch nicht, wird es mit der Stufe
     * des Eintrags angelegt.
     * @return array<int, string>
     */
    private static function topicById(): array
    {
        return [
            1 => 'Algorithmen spielen und verstehen',
            2 => 'Algorithmen spielen und verstehen',
            3 => 'Informationen darstellen',
            4 => 'Erste Webseiten (HTML)',
            5 => 'Algorithmen spielen und verstehen',
            6 => 'Digitale Kommunikation',
            7 => 'Webseiten mit HTML bauen',
            8 => 'Algorithmen und Flussdiagramme',
            9 => 'Algorithmen und Programmierung (Python)',
            10 => 'Daten und Informationen',
            11 => 'Daten und Informationen',
            12 => 'Datenmodellierung und SQL',
            13 => 'Datenmodellierung und SQL',
            14 => 'Java: Klassen und Objekte',
            15 => 'HTML und CSS: Seiten gestalten',
            25 => 'Funktionale Modellierung',
            26 => 'Tabellenkalkulation und Funktionen',
            27 => 'Datenschutz und Sicherheit',
            28 => 'Java: Klassen und Objekte',
            16 => 'Relationale Datenbanken und JOINs',
            17 => 'Java: Vererbung und Polymorphismus',
            18 => 'Relationale Datenbanken und JOINs',
            29 => 'Objektorientierte Modellierung (OOP)',
            30 => 'Objektorientierte Modellierung (OOP)',
            31 => 'Projekt Informatik',
            32 => 'Relationale Datenbanken und JOINs',
            33 => 'Relationale Datenbanken und JOINs',
            34 => 'Relationale Datenbanken und JOINs',
            35 => 'Relationale Datenbanken und JOINs',
            36 => 'Objektorientierte Modellierung (OOP)',
            37 => 'Objektorientierte Modellierung (OOP)',
            38 => 'Projekt Informatik',
            19 => 'Datenbanken fortgeschritten',
            20 => 'Algorithmen und Datenstrukturen',
            21 => 'Algorithmen und Datenstrukturen',
            22 => 'Theoretische Informatik',
            23 => 'Softwareentwicklung und Projekte',
            24 => 'Abiturvorbereitung Informatik',
        ];
    }

    /** @return list<array{id: int, title: string, description: string, type: string, level: int, topic: string}> */
    public static function all(): array
    {
        $topics = self::topicById();
        $list = [
            // Jahrgangsstufe 6: Algorithmik, erste Schritte
            ['id' => 1, 'title' => 'Einen Alltag als Algorithmus beschreiben', 'description' => 'Beschreibe Schritt für Schritt, wie man einen Toast mit Butter und Marmelade zubereitet. Formuliere jede Anweisung so eindeutig, dass ein Roboter sie ausführen könnte. Vergleiche danach mit einem Mitschüler und verbessere ungenaue Stellen.', 'type' => 'allgemein', 'level' => 6],
            ['id' => 2, 'title' => 'Flussdiagramm für einen morgendlichen Ablauf zeichnen', 'description' => 'Zeichne ein Flussdiagramm für das Anziehen am Morgen: Wenn es kalt ist, ziehe eine Jacke an, sonst nur ein T-Shirt. Nutze Rauten für Entscheidungen und Rechtecke für Anweisungen. Markiere Start und Ende.', 'type' => 'allgemein', 'level' => 6],
            ['id' => 3, 'title' => 'Binärzahlen verstehen', 'description' => 'Wandle die Dezimalzahlen 5, 12 und 30 in Binärzahlen um und die Binärzahlen 1011 und 110110 zurück in Dezimalzahlen. Erkläre danach in zwei Sätzen, warum Computer mit Nullen und Einsen rechnen.', 'type' => 'allgemein', 'level' => 6],
            ['id' => 4, 'title' => 'Erste eigene HTML-Seite', 'description' => 'Erstelle in der HTML-Werkstatt eine Seite über dein Lieblingstier mit einer großen Überschrift, zwei Absätzen und einer Liste. Gib der Seite einen aussagekräftigen Titel.', 'type' => 'html', 'level' => 6],
            ['id' => 5, 'title' => 'Was macht einen Algorithmus aus?', 'description' => 'Nenne und erkläre die drei Eigenschaften eines Algorithmus (eindeutig, endlich, allgemein) mit je einem eigenen Beispiel. Finde außerdem ein Beispiel, das KEIN Algorithmus ist, und begründe warum.', 'type' => 'allgemein', 'level' => 6],
            // Jahrgangsstufe 7: Algorithmen vertiefen, erste Programme
            ['id' => 6, 'title' => 'Caesar-Verschlüsselung entschlüsseln', 'description' => 'Der Text "FKRAFOKDKIMU" wurde mit der Caesar-Verschlüsselung um 3 Zeichen verschoben. Entschlüssele ihn und beschreibe anschließend in eigenen Worten, wie das Verfahren funktioniert und warum es heute nicht mehr sicher ist.', 'type' => 'code', 'level' => 7],
            ['id' => 7, 'title' => 'HTML-Seite mit Links und Bildern', 'description' => 'Erstelle eine kleine Seite zu deinem Hobby: mindestens zwei Unterseiten, die über Links miteinander verbunden sind, und ein passendes Bild pro Seite. Achte auf sinnvolle Dateinamen.', 'type' => 'html', 'level' => 7],
            ['id' => 8, 'title' => 'Struktogramm für einen Notenrechner', 'description' => 'Entwirf ein Struktogramm, das aus zwei Schulaufgabennoten den Schnitt berechnet: Sind beide Noten besser als 4, soll zusätzlich "Weiter so!" ausgegeben werden, sonst "Mehr üben!". Prüfe dein Struktogramm mit drei Testfällen.', 'type' => 'allgemein', 'level' => 7],
            // Jahrgangsstufe 8: Erste Programmierung
            ['id' => 9, 'title' => 'Schleifen in Python: kleine 1x1-Tabelle', 'description' => 'Schreibe in der Code-Werkstatt ein Python-Programm, das mit zwei verschachtelten for-Schleifen das kleine Einmaleins von 1 bis 10 als Tabelle ausgibt. Erweitere es danach so, dass nur Produkte über 50 ausgegeben werden (if-Anweisung).', 'type' => 'code', 'level' => 8],
            ['id' => 10, 'title' => 'Kontaktformular mit HTML bauen', 'description' => 'Baue eine Kontaktseite mit einem Formular: Felder für Name, E-Mail-Adresse und Nachricht, passende Buttons zum Absenden und Zurücksetzen. Erkläre in einem Kommentar, was jedes Feld-Attribut bewirkt.', 'type' => 'html', 'level' => 8],
            ['id' => 11, 'title' => 'Addition im Dualsystem', 'description' => 'Rechne die Dualzahlen 1011 und 1101 schriftlich im Dualsystem zusammen (Übertrage beachten!). Zeige jeden Zwischenschritt und kontrolliere das Ergebnis über die Dezimalwerte.', 'type' => 'allgemein', 'level' => 8],
            // Jahrgangsstufe 9: SQL, Java, Datenbanken
            ['id' => 12, 'title' => 'SQL: gezielt suchen mit WHERE', 'description' => 'Öffne den Shop in der SQL-Werkstatt und schreibe Abfragen: alle Produkte über 50 Euro, alle Produkte eines Herstellers deiner Wahl, sortiert nach Preis absteigend. Erkläre die Reihenfolge der Schlüsselwörter in einer SELECT-Anweisung.', 'type' => 'sql', 'level' => 9],
            ['id' => 13, 'title' => 'SQL: Tabellen verbinden mit JOIN', 'description' => 'Verbinde in der Shop-Datenbank die Tabelle produkte mit der Tabelle der Hersteller: Gib Produktname, Hersteller und Preis gemeinsam aus. Erweitere die Abfrage so, dass nur Produkte unter 20 Euro erscheinen.', 'type' => 'sql', 'level' => 9],
            ['id' => 14, 'title' => 'Erste eigene Java-Klasse', 'description' => 'Lege in der Code-Werkstatt eine Klasse Auto an: Attribute für Farbe und Geschwindigkeit, ein Konstruktor, eine Methode beschleunigen und eine Methode, die den Status mit System.out.println ausgibt. Teste alles in der main-Methode.', 'type' => 'code', 'level' => 9],
            ['id' => 15, 'title' => 'HTML und CSS: Layout einer Steckbrief-Seite', 'description' => 'Gestalte einen Steckbrief deiner Lieblingsband: Bild mit Text umflossen, farbige Abschnitts-Überschriften und ein zentriertes Layout. Nutze inline CSS oder eine style-Anweisung und halte dich an einen Farbkreis.', 'type' => 'html', 'level' => 9],
            ['id' => 25, 'title' => 'Funktionale Modellierung: Getränkeautomat als Datenfluss', 'description' => 'Modelliere einen Getränkeautomaten als verkettete Funktionen: Nenne drei Funktionen mit jeweils Eingabe und Ausgabe, zeichne das Datenflussdiagramm in dein Heft und ergänze eine Prüffunktion, die zu wenig eingeworfenes Geld erkennt. Vergleiche danach mit der Präsentation *Funktionale Modellierung*.', 'type' => 'allgemein', 'level' => 9],
            ['id' => 26, 'title' => 'Tabellenkalkulation: absolute Bezüge richtig setzen', 'description' => 'Gegeben ist eine Artikelliste mit Nettopreisen (Spalte B); in F1 steht fest der Mehrwertsteuersatz 19%. Schreibe die Formeln für den Bruttopreis (kopierbar, absoluter Bezug auf F1), eine Rundung auf zwei Nachkommastellen und eine WENN-Formel, die unter 5 Euro "geschenkt" ausgibt. Erkläre, warum der Bezug absolut sein muss.', 'type' => 'allgemein', 'level' => 9],
            ['id' => 27, 'title' => 'Datenschutz: Deine Daten in der Schule', 'description' => 'Liste alle personenbezogenen Daten, die deine Schule über dich speichert. Markiere für jeden Eintrag, wer zugreifen darf (du, Lehrkräfte, Verwaltung, Mitschülerinnen und Mitschüler). Finde je ein Beispiel für Datensparsamkeit und eine Situation, in der du Auskunft verlangen könntest, und erkläre den Begriff Data-Mining an einem Beispiel.', 'type' => 'allgemein', 'level' => 9],
            ['id' => 28, 'title' => 'Java: if-else mit Struktogramm (Führerschein-Check)', 'description' => 'Zeichne zuerst ein Struktogramm für die Methode darfFuehren(alterStart): ab 18 "Darf Auto fahren!", genau 17 "Nur mit Begleitung.", darunter "Noch warten ...". Schreibe danach die Methode in der Code-Werkstatt und teste sie mit 18, 17 und 15. Erkläre, warum hier else if besser passt als zwei einzelne if-Anweisungen.', 'type' => 'code', 'level' => 9],
            // Jahrgangsstufe 10: Vertiefung
            ['id' => 16, 'title' => 'SQL: Auswertungen mit GROUP BY', 'description' => 'Bilde in der Shop-Datenbank je Hersteller die Anzahl der Produkte und den Durchschnittspreis. Sortiere nach Durchschnittspreis absteigend und erkläre den Unterschied zwischen WHERE und HAVING.', 'type' => 'sql', 'level' => 10],
            ['id' => 17, 'title' => 'Java: Vererbung am Beispiel Tiere', 'description' => 'Baue eine Klasse Tier mit Attributen und einer Methode gibLaut(). Erzeuge davon die Klassen Hund und Katze, überschreibe gibLaut() und teste mit einer Referenz vom Typ Tier (dynamische Bindung).', 'type' => 'code', 'level' => 10],
            ['id' => 18, 'title' => 'ER-Modell für eine Schulverwaltung', 'description' => 'Entwirf für eine kleine Notenverwaltung ein ER-Modell: Schueler, Kurse und Noten mit Beziehungen und Kardinalitäten (z. B. m:n mit Zwischentabelle). Markiere Primär- und Fremdschlüssel.', 'type' => 'sql', 'level' => 10],
            ['id' => 29, 'title' => 'Java: Datenkapselung mit private', 'description' => 'Baue die Klasse Schueler mit privaten Attributen name (String) und note (double): Konstruktor mit ...Start-Parametern, getName(), setName(String nameNeu) und setNote(double noteNeu), der Noten außerhalb von 1 bis 6 ablehnt. Teste mit setNote(99): Die Note darf sich nicht ändern. Erkläre den Vorteil der Kapselung in zwei Sätzen.', 'type' => 'code', 'level' => 10],
            ['id' => 30, 'title' => 'Java: Arrays und Referenzen', 'description' => 'Gegeben: int[] zeiten = {312, 287, 305, 298}. Schreibe eine Methode besteZeit(int[] zeitenStart), die die kleinste Zeit zurückgibt, und drucke zusätzlich den Index. Erkläre danach, warum int[] b = a; dazu führt, dass Änderungen über b auch in a sichtbar sind.', 'type' => 'code', 'level' => 10],
            ['id' => 31, 'title' => 'Projekt: Planung und Dokumentation', 'description' => 'Einigt euch auf eine Projektidee (Java-Programm oder Datenbankanwendung) und schreibt einen Projektplan: Ziel, Teamrollen, vier Meilensteine mit Terminen und eine grobe Modellierung (Klassen bzw. Tabellen als Skizze). Legt fest, wann ihr testet und was dabei geprüft wird. Die Vorstellung des Plans dauert 2 Minuten.', 'type' => 'allgemein', 'level' => 10],
            ['id' => 32, 'title' => 'SQL: JOIN mit Bedingungen', 'description' => 'Öffne die Shop-Datenbank: Zeige zu jeder Bestellung den Vor- und Nachnamen des Kunden und das Datum (JOIN über kunde_id), danach zu jeder Bestellposition das Produkt und die Menge (JOIN über produkt_id). Schränke eine der Abfragen mit WHERE ein und berechne als Bonus den Gesamtwert aller Bestellungen mit SUM.', 'type' => 'sql', 'level' => 10],
            ['id' => 33, 'title' => 'SQL: Daten gezielt ändern mit UPDATE und DELETE', 'description' => 'In der Shop-Datenbank sind Preise gestiegen: Erhöhe alle Produkte über 100 Euro um 5 Prozent (UPDATE mit WHERE und berechneter Spalte). Lösche danach alle Bestellungen, die älter als ein selbst gewähltes Datum sind. Erkläre, warum ein UPDATE ohne WHERE gefährlich ist.', 'type' => 'sql', 'level' => 10],
            ['id' => 34, 'title' => 'Datenmodellierung: Bibliothek mit n:m-Beziehung', 'description' => 'Eine Bibliothek verwaltet Bücher, Mitglieder und Ausleihen. Ein Mitglied leiht viele Bücher aus, ein Buch wird von vielen Mitgliedern ausgeliehen (n:m mit Ausleihdatum in der Hilfstabelle). Entwirf das Tabellenschema mit Primär- und Fremdschlüsseln und schreibe anschließend die CREATE-TABLE-Anweisungen in der SQL-Werkstatt.', 'type' => 'sql', 'level' => 10],
            ['id' => 35, 'title' => 'SQL: JOIN über drei Tabellen', 'description' => 'Verbinde in der Shop-Datenbank Bestellungen, Kunden und Produkte in EINER Abfrage: Zeige Vor- und Nachname des Kunden, den Produktnamen und die Menge je Bestellposition. Sortiere nach Nachname. Erkläre deinem Nachbarn, warum hier zwei JOINs nötig sind.', 'type' => 'sql', 'level' => 10],
            ['id' => 36, 'title' => 'Java: Fahrradverleih - Flotte als Array verwalten', 'description' => 'Baue die Klasse Fahrradverleih: ein Array der Länge 5 für Fahrräder, eine Methode vermieten(String farbe), die das nächste freie Fach belegt, und eine Methode zurueckgeben(int index). Erweitere um eine Methode freiePlaetze(), die die Anzahl freier Fächer als int zurückgibt. Teste mit drei Vermietungen und einer Rückgabe.', 'type' => 'code', 'level' => 10],
            ['id' => 37, 'title' => 'Java: Spartenabhängige Mitgliedsbeiträge mit Polymorphismus', 'description' => 'Der Verein berechnet den Beitrag je Sparte: Klasse Mitglied mit Methode beitrag() (Basic 30 Euro), Unterklassen TennisMitglied (45 Euro) und SchwimmMitglied (40 Euro), jeweils mit @Override. Lege ein Mitglied[] mit allen drei Typen an und berechne in einer Schleife die Gesamtbeiträge. Erkläre, warum die Schleife ohne if-Abfrage auskommt.', 'type' => 'code', 'level' => 10],
            ['id' => 38, 'title' => 'Projekt: Buchungssystem - Modellierung und erster Sprint', 'description' => 'Plant ein Buchungssystem für einen Fahrradverleih: Zeichnet das Klassendiagramm (Fahrrad, EBike, Kunde, Buchung) mit Beziehungen und Kardinalitäten, teilt die Klassen im Team auf und implementiert je Person eine Klasse mit Konstruktor, Datenkapselung und einer sinnvollen Methode. Testet gemeinsam: Was passiert bei einer Buchung ohne freies Fahrrad?', 'type' => 'allgemein', 'level' => 10],
            // Oberstufe
            ['id' => 19, 'title' => 'Normalisierung bis zur dritten Normalform', 'description' => 'Gegeben ist eine unstrukturierte Schueler-Tabelle mit Redundanzen. Überführe sie schrittweise in die 1., 2. und 3. Normalform und begründe jeden Schritt mit der verletzten Regel.', 'type' => 'sql', 'level' => 11],
            ['id' => 20, 'title' => 'Laufzeiten vergleichen (O-Notation)', 'description' => 'Ordne die folgenden Algorithmen ihrer Komplexitätsklasse zu: lineare Suche, binäre Suche, Bubblesort, ein verschachtelter Durchlauf. Begründe je eine Zeile und skizziere die Laufzeit als Funktion der Eingabegröße.', 'type' => 'allgemein', 'level' => 11],
            ['id' => 21, 'title' => 'Python: Funktionen und Rückgaben sauber strukturieren', 'description' => 'Schreibe ein Programm zur Notenverwaltung: eine Funktion eingabeNoten, eine Funktion durchschnitt mit Rückgabewert und eine Ausgabe-Funktion. Vermeide globale Variablen und teste jede Funktion einzeln.', 'type' => 'code', 'level' => 11],
            ['id' => 22, 'title' => 'Rekursion verstehen und anwenden', 'description' => 'Implementiere die Fakultät einmal iterativ und einmal rekursiv. Erkläre Abbruchbedingung und Selbstaufruf am Call-Stack. Erweitere danach um eine rekursive Fibonacci-Funktion und vergleiche die Aufrufzahlen.', 'type' => 'code', 'level' => 12],
            ['id' => 23, 'title' => 'SQL: Sichten und Transaktionen', 'description' => 'Erstelle eine VIEW, die nur lieferbare Produkte zeigt. Erkläre danach an einem Überweisungsbeispiel (ABBUCHEN und GUTSCHREIBEN), warum Transaktionen ALL-OR-NOTHING sein müssen (ACID).', 'type' => 'sql', 'level' => 12],
            ['id' => 24, 'title' => 'Endliche Automaten: Getränkeautomat modellieren', 'description' => 'Modelliere einen Getränkeautomaten als endlichen Automaten: Zustände (bereit, Geld eingeworfen, Ausgabe), Übergänge und Ausgabezeichen. Zeichne den Zustandsgraphen und finde eine Eingabefolge, die zu einem Fehlerzustand führt.', 'type' => 'allgemein', 'level' => 13],
        ];
        foreach ($list as $index => $entry) {
            $entry['topic'] = $topics[$entry['id']] ?? '';
            $list[$index] = $entry;
        }
        return $list;
    }

    /** @return array{id: int, title: string, description: string, type: string, level: int, topic: string}|null */
    public static function find(int $id): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        return null;
    }

    /** @return list<array{id: int, title: string, description: string, type: string, level: int, topic: string}> */
    public static function forLevel(int $level): array
    {
        return array_values(array_filter(self::all(), static fn (array $entry): bool => $entry['level'] === $level));
    }
}
