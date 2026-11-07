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

## Phase 2: Termine steuern, einziehen, bewerten (erste Umsetzung umgesetzt)

1. ✅ **Starttermin (Zuweisungsdatum/Zeit):** Aufgaben lassen sich vorbereiten
   und erst zu einem späteren Datum mit Uhrzeit für die Klasse bzw. Person
   sichtbar schalten (direkt beim Austeilen oder nachträglich pro Zuweisung,
   streichbar für sofortige Sichtbarkeit). Vor dem Starttermin ist die
   Aufgabe für Schülerinnen und Schüler unsichtbar, auch beim Abgeben.
2. ✅ **Zuweisung einziehen:** Eine ausgeteilte Aufgabe lässt sich pro
   Klasse oder Person zurücknehmen, ohne sie zu löschen. Bereits abgegebene
   Lösungen und Korrekturen bleiben erhalten.
3. ✅ **Frist-Sperre:** Pro Aufgabe einstellbar (Anlegen oder Bearbeiten),
   ob nach dem Abgabetermin noch verspätete Abgaben möglich sind (Standard)
   oder ob die Abgabe komplett gesperrt wird.
4. ✅ **Punktzahl:** Aufgaben können eine maximale Punktzahl erhalten
   (z. B. 10 Punkte). Die Punktzahl erscheint in der Korrekturübersicht,
   im Aufgabenpool und wird der KI als Rahmen mitgegeben.
5. ✅ **Erwartungshorizont/Bewertungskriterien (Rubrics):** Pro Aufgabe
   lässt sich ein Erwartungshorizont hinterlegen. Er erscheint der Lehrkraft
   in der Korrekturübersicht und wird der KI-Bewertung als
   Bewertungshilfe mitgegeben ([#19](https://github.com/creativecat/infocraft/issues/19)).

## Phase 3: Ideen (🔜 geplant)

- 🔜 **Abgabe als Projektverweis:** Statt oder zusätzlich zum Text eine
  Lösung aus der Code-Werkstatt (Java/Python) oder HTML-Werkstatt als
  Abgabe verknüpfen, Korrektur mit Quelltext-Ansicht. ([#11](https://github.com/creativecat/infocraft/issues/11))
- 🔜 **Fälligkeits-Übersicht:** Dashboard-Kachel für Lehrkräfte mit
  fälligen und überfälligen Aufgaben, für Schülerinnen und Schüler die
  nächste anstehende Abgabe. ([#12](https://github.com/creativecat/infocraft/issues/12))
- 🔜 **Statistiken:** Abgabequote, Verspätungsquote und Notenverteilung
  pro Aufgabe und Klasse. ([#13](https://github.com/creativecat/infocraft/issues/13))
- 🔜 **Erinnerungen:** dezente Hinweise auf noch nicht korrigierte
  Abgaben. ([#14](https://github.com/creativecat/infocraft/issues/14))
- 🔜 **Export/Import:** Aufgaben als Datei exportieren und importieren,
  um Aufgaben zwischen Schulen oder Instanzen auszutauschen. ([#15](https://github.com/creativecat/infocraft/issues/15))
- 🔜 **Unteraufgaben:** Aufgabe in Teilschritte mit eigener
  Erledigungs-Checkliste für Schülerinnen und Schüler. ([#16](https://github.com/creativecat/infocraft/issues/16))
- 🔜 **Selbsteinschätzung:** Kurze Selbsteinschätzung vor der Abgabe
  („Wie sicher fühlst du dich?"), sichtbar nur für die Lehrkraft. ([#17](https://github.com/creativecat/infocraft/issues/17))
- 🔜 **Wiederkehrende Aufgaben:** Aufgaben mit Wiederholrhythmus
  (z. B. wöchentlich) und automatischem Austeilen. ([#18](https://github.com/creativecat/infocraft/issues/18))
- 🔜 **Notenschlüssel:** aus Punkten automatisch Tendenznoten vorschlagen
  (schulweiter oder persönlicher Schlüssel).
- 🔜 **Musterlösung freigeben:** Musterlösung erst nach dem Abgabetermin
  oder nach der Korrektur für die Schülerinnen und Schüler sichtbar
  schalten.
- 🔜 **Aufgabenpakete:** mehrere Aufgaben als Wochenplan-Paket auf einmal
  austeilen (mit gemeinsamem Start- und Abgabetermin).
