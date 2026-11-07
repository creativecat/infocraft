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
    /** @return list<array{id: int, title: string, description: string, type: string, level: int}> */
    public static function all(): array
    {
        return [
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
            // Jahrgangsstufe 10: Vertiefung
            ['id' => 16, 'title' => 'SQL: Auswertungen mit GROUP BY', 'description' => 'Bilde in der Shop-Datenbank je Hersteller die Anzahl der Produkte und den Durchschnittspreis. Sortiere nach Durchschnittspreis absteigend und erkläre den Unterschied zwischen WHERE und HAVING.', 'type' => 'sql', 'level' => 10],
            ['id' => 17, 'title' => 'Java: Vererbung am Beispiel Tiere', 'description' => 'Baue eine Klasse Tier mit Attributen und einer Methode gibLaut(). Erzeuge davon die Klassen Hund und Katze, überschreibe gibLaut() und teste mit einer Referenz vom Typ Tier (dynamische Bindung).', 'type' => 'code', 'level' => 10],
            ['id' => 18, 'title' => 'ER-Modell für eine Schulverwaltung', 'description' => 'Entwirf für eine kleine Notenverwaltung ein ER-Modell: Schueler, Kurse und Noten mit Beziehungen und Kardinalitäten (z. B. m:n mit Zwischentabelle). Markiere Primär- und Fremdschlüssel.', 'type' => 'sql', 'level' => 10],
            // Oberstufe
            ['id' => 19, 'title' => 'Normalisierung bis zur dritten Normalform', 'description' => 'Gegeben ist eine unstrukturierte Schueler-Tabelle mit Redundanzen. Überführe sie schrittweise in die 1., 2. und 3. Normalform und begründe jeden Schritt mit der verletzten Regel.', 'type' => 'sql', 'level' => 11],
            ['id' => 20, 'title' => 'Laufzeiten vergleichen (O-Notation)', 'description' => 'Ordne die folgenden Algorithmen ihrer Komplexitätsklasse zu: lineare Suche, binäre Suche, Bubblesort, ein verschachtelter Durchlauf. Begründe je eine Zeile und skizziere die Laufzeit als Funktion der Eingabegröße.', 'type' => 'allgemein', 'level' => 11],
            ['id' => 21, 'title' => 'Python: Funktionen und Rückgaben sauber strukturieren', 'description' => 'Schreibe ein Programm zur Notenverwaltung: eine Funktion eingabeNoten, eine Funktion durchschnitt mit Rückgabewert und eine Ausgabe-Funktion. Vermeide globale Variablen und teste jede Funktion einzeln.', 'type' => 'code', 'level' => 11],
            ['id' => 22, 'title' => 'Rekursion verstehen und anwenden', 'description' => 'Implementiere die Fakultät einmal iterativ und einmal rekursiv. Erkläre Abbruchbedingung und Selbstaufruf am Call-Stack. Erweitere danach um eine rekursive Fibonacci-Funktion und vergleiche die Aufrufzahlen.', 'type' => 'code', 'level' => 12],
            ['id' => 23, 'title' => 'SQL: Sichten und Transaktionen', 'description' => 'Erstelle eine VIEW, die nur lieferbare Produkte zeigt. Erkläre danach an einem Überweisungsbeispiel (ABBUCHEN und GUTSCHREIBEN), warum Transaktionen ALL-OR-NOTHING sein müssen (ACID).', 'type' => 'sql', 'level' => 12],
            ['id' => 24, 'title' => 'Endliche Automaten: Getränkeautomat modellieren', 'description' => 'Modelliere einen Getränkeautomaten als endlichen Automaten: Zustände (bereit, Geld eingeworfen, Ausgabe), Übergänge und Ausgabezeichen. Zeichne den Zustandsgraphen und finde eine Eingabefolge, die zu einem Fehlerzustand führt.', 'type' => 'allgemein', 'level' => 13],
        ];
    }

    /** @return array{id: int, title: string, description: string, type: string, level: int}|null */
    public static function find(int $id): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        return null;
    }

    /** @return list<array{id: int, title: string, description: string, type: string, level: int}> */
    public static function forLevel(int $level): array
    {
        return array_values(array_filter(self::all(), static fn (array $entry): bool => $entry['level'] === $level));
    }
}
