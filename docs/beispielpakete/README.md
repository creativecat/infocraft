# Beispiel-Buchpakete für InfoCraft

Diese Ordner enthalten fertige Buchpakete im JSON-Format für das InfoBook-Modul
von InfoCraft. Die Pakete basieren auf den Informatik-Präsentationen für die
Klasse 9 (github.com/creativecat/website, docs/informatik/).

## Enthaltene Pakete

| Datei | Thema | Seiten |
| --- | --- | --- |
| `if-anweisungen-java.json` | if / else / else if in Java | 11 |
| `vererbung-java.json` | Vererbung mit extends und super | 10 |

Beide Pakete nutzen die Schritt-Funktion der Präsentation (ab Version 0.23.0):
Elemente mit `step` erscheinen erst per Klick, gleiche Schrittnummern gemeinsam;
`anim` wählt die Einblend-Animation (`fade`, `up`, `left`, `zoom`).

## So importierst du ein Paket

1. Ein InfoBook anlegen oder öffnen (Verwaltungsrecht durch die Lehrkraft nötig).
2. In der InfoBook-Navigation unten den Punkt **Buchpaket importieren**
   aufklappen.
3. Den kompletten Inhalt einer JSON-Datei einfügen und absenden.
4. Der Import legt den Abschnitt mit allen Seiten an und springt direkt
   in die Abschnittsansicht; von dort führt der **Präsentation**-Link
   in den Präsentationsmodus (Klick oder Pfeil rechts blendet den nächsten
   Schritt ein, Pfeil links blendet ihn wieder aus).
5. Schritte und Animationen lassen sich im Bearbeitungsmodus ändern: Element
   auswählen, im Kontextbereich **Schritt** (0 = sofort sichtbar) und
   **Einblenden** (Art der Animation) setzen.
