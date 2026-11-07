# Aufgabenverwaltung: Roadmap

Übersicht der geplanten und umgesetzten Features rund um den Aufgabenpool,
den Austausch zwischen Lehrkräften und die Korrektur von Abgaben.
Zustand: ✅ umgesetzt, 🔜 geplant.

## Phase 1: Gemeinsamer Pool, Termine, Abgaben, Korrektur, KI (umgesetzt)

1. ✅ **Aufgaben teilen:** Eigene Aufgabe mit einem Klick in den gemeinsamen
   Pool aller Lehrkräfte stellen (und zurückziehen). Geteilte Aufgaben zeigen
   die Urheberin oder den Urheber, das Original bleibt bei der erstellenden
   Lehrkraft unangetastet.
2. ✅ **Gemeinsamer Pool durchstöbern:** Geteilte Aufgaben aller Lehrkräfte,
   filterbar nach Jahrgangsstufe und Typ. Der eigene Pool bleibt privat;
   geteilt wird nur, was bewusst geteilt wurde.
3. ✅ **Als Kopie übernehmen:** Eine Aufgabe aus dem gemeinsamen Pool landet
   als eigene Kopie im eigenen Pool und ist dort frei anpassbar
   (individuelle Anpassungen ohne Auswirkung auf das Original oder die
   Abgaben anderer Klassen).
4. ✅ **Aufgaben bearbeiten:** Titel, Beschreibung, Typ, Jahrgangsstufe und
   Musterlösung jederzeit nachträglich ändern (auch übernommene Kopien).
5. ✅ **Termine:** Abgabedatum mit Uhrzeit direkt beim Austeilen oder
   nachträglich pro Empfänger ändern oder streichen. Der
   Zuweisungszeitpunkt wird automatisch notiert. Schülerinnen und Schüler
   sehen den Abgabetermin, den Abgabe-Status (offen, abgegeben, verspätet,
   korrigiert) und bei Verspätung einen deutlichen Hinweis.
6. ✅ **Abgaben:** Schülerinnen und Schüler geben eine Text-Lösung ab und
   können bis zum Abgabetermin beliebig oft überschreiben; danach ist eine
   verspätete Abgabe noch möglich, wird aber markiert. Nach der Korrektur
   durch die Lehrkraft ist keine neue Abgabe mehr nötig bzw. möglich.
7. ✅ **Korrektur:** Pro Aufgabe eine Korrekturübersicht mit allen
   Empfängerinnen und Empfängern samt Status, Abgabezeit, Lösungstext,
   Musterlösung zum Vergleich, Note (frei, z. B. 1, 2-, 3+) und
   Kommentar. Die Korrektur erscheint bei der Schülerin bzw. dem Schüler.
8. ✅ **KI-Schnittstelle hinterlegen:** Jede Lehrkraft kann unter
   /aufgaben/ki ihren eigenen Zugang hinterlegen (Endpunkt-URL,
   API-Schlüssel, Modell); die Administration kann zusätzlich einen
   schulweiten Zugang hinterlegen, der für alle Lehrkräfte ohne eigene
   Angaben gilt. Unterstützt werden OpenAI-/Mistral-kompatible Chat-APIs
   (mode chat) und eigene JSON-Endpunkte (mode json). Die Zugangsdaten
   bleiben auf dem eigenen Server, es fließen nur Aufgaben- und
   Lösungstexte anonymisiert (Pseudonyme statt Namen) an den Endpunkt.
9. ✅ **KI-Erstbewertung:** Auf Klick bewertet die KI alle eingereichten
   Abgaben einer Aufgabe vor: Note-Vorschlag und kurzes Feedback, in der
   Korrekturansicht deutlich als Vorschlag markiert. Erst wenn die
   Lehrkraft den Vorschlag übernimmt und speichert, zählt die Bewertung.

## Phase 2: Ideen (🔜 geplant)

- 🔜 **Abgabe als Projektverweis:** Statt oder zusätzlich zum Text eine
  Lösung aus der Code-Werkstatt (Java/Python) oder HTML-Werkstatt als
  Abgabe verknüpfen, Korrektur mit Quelltext-Ansicht.
- 🔜 **Fälligkeits-Übersicht:** Dashboard-Kachel für Lehrkräfte mit
  fälligen und überfälligen Aufgaben, für Schülerinnen und Schüler die
  nächste anstehende Abgabe.
- 🔜 **Statistiken:** Abgabequote, Verspätungsquote und Notenverteilung
  pro Aufgabe und Klasse.
- 🔜 **Erinnerungen:** dezente Hinweise auf noch nicht korrigierte
  Abgaben.
- 🔜 **Export/Import:** Aufgaben als Datei exportieren und importieren,
  um Aufgaben zwischen Schulen oder Instanzen auszutauschen.
- 🔜 **Unteraufgaben:** Aufgabe in Teilschritte mit eigener
  Erledigungs-Checkliste für Schülerinnen und Schüler.
- 🔜 **Selbsteinschätzung:** Kurze Selbsteinschätzung vor der Abgabe
  („Wie sicher fühlst du dich?"), sichtbar nur für die Lehrkraft.
- 🔜 **Wiederkehrende Aufgaben:** Aufgaben mit Wiederholrhythmus
  (z. B. wöchentlich) und automatischem Austeilen.
- 🔜 **Bewertungsraster:** Kriterien je Aufgabe definieren, die der
  KI-Bewertung mitgegeben werden (Rubrics).
