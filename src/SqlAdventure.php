<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: SQL-Werkstatt: Abenteuer-Beschreibungen und Szenenbilder
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.25.0
 */
declare(strict_types=1);

namespace Infocraft;

/**
 * Statische Beschreibungen der SQL-Abenteuer (#195, #196): Teaser, Intro und
 * Finale für die Quest-Übersicht und die Kapitel-Ansicht. Jedes Abenteuer
 * bekommt ein handgebautes SVG-Szenenbild, damit die Quest-Auswahl ohne
 * externe Bilddateien eine eigene, schülerfreundliche Stimmung bekommt.
 */
final class SqlAdventure
{
    /**
     * Beschreibung je Abenteuer-Slug:
     * emoji, teaser (Quest-Karte), intro (Kapitel-Ansicht), finale (alles
     * gelöst) und scene (Inline-SVG-Szenenbild).
     * @return array{emoji: string, teaser: string, intro: string, finale: string, scene: string}
     */
    public static function meta(string $slug): array
    {
        return match ($slug) {
            'adventure' => [
                'emoji' => '🏝️',
                'teaser' => 'Ein Sturm hat die Dateninsel verwüstet: Verzauberte Bewohner, verlorene Schätze und eine geheimnisvolle Bibliothek warten auf deine SQL-Anweisungen.',
                'intro' => 'Der Sturm ist vorübergezogen und hat die Dateninsel durcheinandergewirbelt: Manche Bewohner sind verzaubert, Schätze liegen verstreut, und niemand weiß mehr, wohin die Wege führen. Der Häuptling hat dich gerufen, weil du dich mit SQL auskennst. Löse Kapitel für Kapitel und rette die Insel.',
                'finale' => 'Die Insel ist gerettet: Die Verzauberten sind frei, die Bibliothek ist geordnet, und der Häuptling nennt dich ehrenhalber Insel-Datenmeisterin bzw. Insel-Datenmeister. Der Lotse verneigt sich, während die Sonne über dem Hafen untergeht.',
                'scene' => self::islandScene(),
            ],
            'raumstation' => [
                'emoji' => '🚀',
                'teaser' => 'Meteoriten haben die Raumstation Alpha getroffen: Die Crew ist verstreut, Tanks lecken, und nur SQL bringt Ordnung ins Chaos.',
                'intro' => 'Dein Zubringer hat gerade angedockt. Der Meteoritenschauer hat Module beschädigt, zwei Crew-Mitglieder gelten als vermisst und die Sauerstofftanks verlieren Druck. Kommandantin Kira braucht jemanden, der die Daten der Station im Griff hat: Du. Arbeitse dich Kapitel für Kapitel durch den Notfall.',
                'finale' => 'Alle Tanks sind dicht, die Crew ist auf Einsatzbereitschaft, und der Energiebericht geht an das Kontrollzentrum. Kommandantin Kira verleiht dir das Ehrenabzeichen der Raumstation Alpha: Datenretterin bzw. Datenretter im Weltraum.',
                'scene' => self::spaceScene(),
            ],
            'museum' => [
                'emoji' => '🖼️',
                'teaser' => 'Das berühmte Gemälde \"Der Morgen\" ist verschwunden: Kläre mit SQL auf, wer in der Nacht im Museum unterwegs war.',
                'intro' => 'Regnerisch, kurz vor Mitternacht: Im Stadtmuseum fehlt das Gemälde \"Der Morgen\". Ermittlerin Mara Klar hat die Museumdatenbank gesichert und schiebt dir den Laptop hin: Räume, Werke, Besuche und Kameras. Deine Aufgabe: die Daten in eine Reihenfolge bringen, die den Fall löst.',
                'finale' => 'Der Fall ist aufgeklärt: Du hast die letzte Person im Raum des Gemäldes ermittelt, die Kameras sind scharf und das Museum kann wieder öffnen. Mara Klar notiert deinen Namen in ihr schwarzes Buch der besten Daten-Analystinnen und Analysten.',
                'scene' => self::museumScene(),
            ],
            'festival' => [
                'emoji' => '🎪',
                'teaser' => 'Das Sommerfestival steht vor der Tür, aber Auftritte und Technik sind durcheinander: Stelle das Programm mit SQL wieder zusammen.',
                'intro' => 'In drei Tagen öffnet das Festival der jungen Bühnen seine Tore, doch die Veranstaltungsdatenbank ist ein einziges Chaos: Auftritte sind unklar, Technik falsch verteilt und ein Gewitter zieht auf. Festivalchefin Jule vertraut dir die Datenbank an. Bring das Festival Kapitel für Kapitel auf die Bühne.',
                'finale' => 'Das Programmheft ist gedruckt, alle Auftritte bestätigt und die Wiesen-Auftritte retten sich unter das Dach der Waldbühne. Beim ersten Akkord des Abends hebt Jule ihr Wurfblatt: \"Dieses Festival hat SQL gerettet!\".',
                'scene' => self::festivalScene(),
            ],
            default => [
                'emoji' => '🧭',
                'teaser' => 'Ein neues erzähltes Abenteuer mit SQL-Missionen.',
                'intro' => 'Ein neues Abenteuer wartet: Löse die Kapitel der Reihe nach und trage die Geschichte mit deinen SQL-Anweisungen zu Ende.',
                'finale' => 'Alle Kapitel sind gelöst: Das Abenteuer ist zu Ende, deine Daten sind in Ordnung.',
                'scene' => self::compassScene(),
            ],
        };
    }

    /** Insel im Abendlicht mit Palme, Schatztruhe und Boot. */
    private static function islandScene(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 400 180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Insel mit Palme und Schatztruhe im Abendlicht">
  <defs>
    <linearGradient id="adv-insel-sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#7dd3fc"/><stop offset="1" stop-color="#fde68a"/>
    </linearGradient>
    <linearGradient id="adv-insel-sea" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#38bdf8"/><stop offset="1" stop-color="#0c4a6e"/>
    </linearGradient>
  </defs>
  <rect width="400" height="180" fill="url(#adv-insel-sky)"/>
  <circle cx="322" cy="48" r="26" fill="#fff7ed" opacity="0.95"/>
  <path d="M70 34 q 7 -7 14 0 q 7 -7 14 0" stroke="#7c2d12" stroke-width="2" fill="none"/>
  <path d="M110 22 q 6 -6 12 0 q 6 -6 12 0" stroke="#7c2d12" stroke-width="2" fill="none"/>
  <ellipse cx="150" cy="40" rx="30" ry="10" fill="#ffffff" opacity="0.85"/>
  <ellipse cx="172" cy="34" rx="22" ry="8" fill="#ffffff" opacity="0.85"/>
  <rect y="92" width="400" height="88" fill="url(#adv-insel-sea)"/>
  <path d="M0 104 q 25 -7 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0" stroke="#bae6fd" stroke-width="3" fill="none" opacity="0.8"/>
  <path d="M0 128 q 25 -7 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0" stroke="#7dd3fc" stroke-width="3" fill="none" opacity="0.6"/>
  <path d="M0 156 q 25 -7 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0 t 50 0" stroke="#38bdf8" stroke-width="3" fill="none" opacity="0.5"/>
  <ellipse cx="168" cy="128" rx="118" ry="24" fill="#fcd34d"/>
  <ellipse cx="168" cy="120" rx="100" ry="17" fill="#4ade80"/>
  <path d="M150 122 q -12 -32 6 -58" stroke="#92400e" stroke-width="7" fill="none" stroke-linecap="round"/>
  <path d="M156 64 q -30 -14 -46 4" stroke="#16a34a" stroke-width="6" fill="none" stroke-linecap="round"/>
  <path d="M156 64 q -6 -32 -26 -34" stroke="#16a34a" stroke-width="6" fill="none" stroke-linecap="round"/>
  <path d="M156 64 q 4 -34 24 -36" stroke="#22c55e" stroke-width="6" fill="none" stroke-linecap="round"/>
  <path d="M156 64 q 30 -16 46 0" stroke="#22c55e" stroke-width="6" fill="none" stroke-linecap="round"/>
  <circle cx="150" cy="70" r="4" fill="#78350f"/>
  <circle cx="160" cy="74" r="4" fill="#78350f"/>
  <rect x="212" y="108" width="28" height="17" rx="2" fill="#92400e"/>
  <rect x="209" y="101" width="34" height="9" rx="2" fill="#b45309"/>
  <circle cx="226" cy="106" r="3" fill="#fbbf24"/>
  <path d="M312 132 l 22 0 l -6 10 l -11 0 z" fill="#7c2d12"/>
  <path d="M323 132 l 0 -26 l 15 26 z" fill="#ffffff"/>
  <rect x="256" y="118" width="10" height="10" rx="2" fill="#0f766e"/>
  <rect x="272" y="122" width="8" height="8" rx="2" fill="#b91c1c"/>
</svg>
SVG;
    }

    /** Raumstation mit Solarpaneelen, Planet und Meteoriten. */
    private static function spaceScene(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 400 180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Raumstation mit Solarpaneelen vor Planet und Sternen">
  <defs>
    <linearGradient id="adv-station-sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#1e1b4b"/><stop offset="1" stop-color="#0f172a"/>
    </linearGradient>
    <linearGradient id="adv-station-planet" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#fb923c"/><stop offset="1" stop-color="#c2410c"/>
    </linearGradient>
  </defs>
  <rect width="400" height="180" fill="url(#adv-station-sky)"/>
  <circle cx="30" cy="30" r="2" fill="#ffffff"/><circle cx="70" cy="140" r="1.6" fill="#ffffff" opacity="0.8"/>
  <circle cx="120" cy="24" r="2" fill="#ffffff" opacity="0.9"/><circle cx="150" cy="150" r="2" fill="#ffffff"/>
  <circle cx="220" cy="36" r="1.5" fill="#ffffff" opacity="0.7"/><circle cx="260" cy="140" r="2" fill="#ffffff" opacity="0.9"/>
  <circle cx="330" cy="26" r="1.6" fill="#ffffff"/><circle cx="365" cy="120" r="2" fill="#ffffff" opacity="0.8"/>
  <circle cx="45" cy="100" r="1.4" fill="#ffffff" opacity="0.7"/><circle cx="300" cy="70" r="1.4" fill="#ffffff" opacity="0.6"/>
  <circle cx="196" cy="120" r="1.4" fill="#ffffff" opacity="0.7"/><circle cx="375" cy="55" r="1.8" fill="#ffffff"/>
  <circle cx="66" cy="56" r="26" fill="url(#adv-station-planet)"/>
  <ellipse cx="66" cy="56" rx="40" ry="9" fill="none" stroke="#fdba74" stroke-width="3" opacity="0.85" transform="rotate(-18 66 56)"/>
  <circle cx="66" cy="48" r="6" fill="#ea580c" opacity="0.7"/>
  <circle cx="74" cy="60" r="4" fill="#ea580c" opacity="0.7"/>
  <path d="M118 76 q 8 -16 20 -8" stroke="#fbbf24" stroke-width="4" fill="none" stroke-linecap="round"/>
  <path d="M262 30 q 14 -12 26 -2" stroke="#f97316" stroke-width="4" fill="none" stroke-linecap="round"/>
  <circle cx="148" cy="64" r="3" fill="#fde68a"/><circle cx="292" cy="27" r="3" fill="#fdba74"/>
  <rect x="158" y="52" width="12" height="34" rx="3" fill="#cbd5e1"/>
  <rect x="234" y="52" width="12" height="34" rx="3" fill="#cbd5e1"/>
  <rect x="168" y="60" width="68" height="20" rx="8" fill="#e2e8f0"/>
  <circle cx="186" cy="70" r="5" fill="#0ea5e9"/><circle cx="202" cy="70" r="5" fill="#0ea5e9"/>
  <circle cx="218" cy="70" r="5" fill="#0ea5e9"/>
  <rect x="92" y="58" width="60" height="18" rx="3" fill="#1d4ed8"/>
  <path d="M104 58 l 0 18 M116 58 l 0 18 M128 58 l 0 18 M140 58 l 0 18" stroke="#93c5fd" stroke-width="2"/>
  <rect x="250" y="58" width="60" height="18" rx="3" fill="#1d4ed8"/>
  <path d="M262 58 l 0 18 M274 58 l 0 18 M286 58 l 0 18 M298 58 l 0 18" stroke="#93c5fd" stroke-width="2"/>
  <rect x="146" y="88" width="18" height="18" rx="4" fill="#94a3b8"/>
  <rect x="240" y="88" width="18" height="18" rx="4" fill="#94a3b8"/>
  <path d="M164 70 l 14 22 M240 70 l -14 22" stroke="#64748b" stroke-width="3"/>
</svg>
SVG;
    }

    /** Museumsaal mit Bildern, rotem Seil und leerem Rahmen. */
    private static function museumScene(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 400 180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Museumsaal mit Bildern, rotem Seil und leerem Rahmen">
  <defs>
    <linearGradient id="adv-museum-wall" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#faf5eb"/><stop offset="1" stop-color="#e7dcc8"/>
    </linearGradient>
    <linearGradient id="adv-museum-light" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fde68a" stop-opacity="0.55"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/>
    </linearGradient>
  </defs>
  <rect width="400" height="180" fill="url(#adv-museum-wall)"/>
  <rect y="140" width="400" height="40" fill="#a16207"/>
  <path d="M0 140 l 400 0" stroke="#78350f" stroke-width="3"/>
  <path d="M0 156 l 400 0" stroke="#78350f" stroke-width="2" opacity="0.5"/>
  <path d="M20 130 l 44 0" stroke="#d6c7a1" stroke-width="2"/>
  <rect x="28" y="36" width="60" height="46" rx="2" fill="#b45309"/>
  <rect x="33" y="41" width="50" height="36" fill="#60a5fa"/>
  <circle cx="70" cy="52" r="7" fill="#fef3c7"/>
  <path d="M33 68 q 12 -10 25 0 t 25 0 l 0 9 l -50 0 z" fill="#16a34a"/>
  <rect x="108" y="26" width="70" height="54" rx="2" fill="#b45309"/>
  <rect x="114" y="32" width="58" height="42" fill="#1e293b"/>
  <circle cx="132" cy="48" r="6" fill="#fbbf24"/>
  <path d="M136 74 l 14 -22 l 12 22 z" fill="#f472b6"/>
  <path d="M156 74 l 8 -12 l 8 12 z" fill="#34d399"/>
  <rect x="228" y="34" width="64" height="50" rx="2" fill="#78350f" stroke="#b45309" stroke-width="3" stroke-dasharray="7 5"/>
  <text x="260" y="66" font-family="sans-serif" font-size="26" fill="#a16207" text-anchor="middle">?</text>
  <rect x="312" y="40" width="56" height="44" rx="2" fill="#b45309"/>
  <rect x="317" y="45" width="46" height="34" fill="#fde68a"/>
  <path d="M320 74 q 10 -26 20 -6 q 8 -16 20 6 z" fill="#c2410c"/>
  <polygon points="80,0 130,0 110,140 60,140" fill="url(#adv-museum-light)"/>
  <polygon points="270,0 320,0 300,140 250,140" fill="url(#adv-museum-light)"/>
  <rect x="96" y="118" width="8" height="22" fill="#78350f"/>
  <rect x="296" y="118" width="8" height="22" fill="#78350f"/>
  <path d="M100 126 q 100 26 200 0" stroke="#dc2626" stroke-width="4" fill="none"/>
  <circle cx="150" cy="137" r="2.6" fill="#b91c1c"/><circle cx="200" cy="140" r="2.6" fill="#b91c1c"/><circle cx="250" cy="137" r="2.6" fill="#b91c1c"/>
</svg>
SVG;
    }

    /** Festivalbühne bei Nacht mit Lichtkegeln und Publikum. */
    private static function festivalScene(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 400 180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Festivalbühne bei Nacht mit Lichtkegeln und Publikum">
  <defs>
    <linearGradient id="adv-festival-sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#312e81"/><stop offset="1" stop-color="#7c3aed"/>
    </linearGradient>
    <linearGradient id="adv-festival-beam" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fde047" stop-opacity="0.5"/><stop offset="1" stop-color="#fde047" stop-opacity="0.05"/>
    </linearGradient>
  </defs>
  <rect width="400" height="180" fill="url(#adv-festival-sky)"/>
  <circle cx="342" cy="36" r="18" fill="#fef9c3"/>
  <circle cx="40" cy="30" r="1.8" fill="#ffffff"/><circle cx="90" cy="52" r="1.4" fill="#ffffff" opacity="0.8"/>
  <circle cx="140" cy="26" r="1.8" fill="#ffffff"/><circle cx="200" cy="46" r="1.4" fill="#ffffff" opacity="0.7"/>
  <circle cx="250" cy="22" r="1.8" fill="#ffffff" opacity="0.9"/><circle cx="300" cy="58" r="1.4" fill="#ffffff"/>
  <circle cx="370" cy="80" r="1.4" fill="#ffffff" opacity="0.7"/>
  <path d="M20 12 q 40 14 80 0 q 40 14 80 0 q 40 14 80 0 q 40 14 80 0" stroke="#a78bfa" stroke-width="1.6" fill="none" opacity="0.8"/>
  <circle cx="40" cy="18" r="3" fill="#fde047"/><circle cx="80" cy="23" r="3" fill="#4ade80"/>
  <circle cx="120" cy="18" r="3" fill="#f472b6"/><circle cx="160" cy="23" r="3" fill="#fde047"/>
  <circle cx="200" cy="18" r="3" fill="#60a5fa"/><circle cx="240" cy="23" r="3" fill="#f472b6"/>
  <circle cx="280" cy="18" r="3" fill="#4ade80"/><circle cx="320" cy="23" r="3" fill="#fde047"/>
  <polygon points="60,0 110,0 190,150 10,150" fill="url(#adv-festival-beam)"/>
  <polygon points="290,0 340,0 390,150 240,150" fill="url(#adv-festival-beam)"/>
  <rect x="90" y="60" width="220" height="90" rx="6" fill="#4c1d95"/>
  <path d="M90 60 l 30 -18 l 0 18 z" fill="#5b21b6"/>
  <path d="M310 60 l -30 -18 l 0 18 z" fill="#5b21b6"/>
  <circle cx="150" cy="96" r="12" fill="#0f172a" opacity="0.75"/>
  <circle cx="150" cy="112" r="10" fill="#0f172a" opacity="0.75"/>
  <rect x="136" y="118" width="28" height="26" rx="6" fill="#0f172a" opacity="0.75"/>
  <rect x="118" y="42" width="10" height="18" rx="2" fill="#a78bfa"/>
  <circle cx="123" cy="40" r="5" fill="#fde047"/>
  <rect x="272" y="42" width="10" height="18" rx="2" fill="#a78bfa"/>
  <circle cx="277" cy="40" r="5" fill="#fde047"/>
  <path d="M100 60 l 30 0 l -10 14 l -10 0 z" fill="#7c3aed"/>
  <path d="M300 60 l -30 0 l 10 14 l 10 0 z" fill="#7c3aed"/>
  <rect x="60" y="150" width="280" height="14" rx="4" fill="#7c2d12"/>
  <rect x="40" y="164" width="320" height="16" fill="#78350f"/>
  <circle cx="80" cy="146" r="7" fill="#0f172a"/><rect x="70" y="150" width="20" height="22" rx="5" fill="#0f172a"/>
  <circle cx="120" cy="143" r="7" fill="#0f172a"/><rect x="110" y="147" width="20" height="24" rx="5" fill="#0f172a"/>
  <circle cx="160" cy="147" r="7" fill="#0f172a"/><rect x="150" y="151" width="20" height="20" rx="5" fill="#0f172a"/>
  <circle cx="205" cy="144" r="7" fill="#0f172a"/><rect x="195" y="148" width="20" height="23" rx="5" fill="#0f172a"/>
  <circle cx="245" cy="146" r="7" fill="#0f172a"/><rect x="235" y="150" width="20" height="21" rx="5" fill="#0f172a"/>
  <circle cx="285" cy="143" r="7" fill="#0f172a"/><rect x="275" y="147" width="20" height="24" rx="5" fill="#0f172a"/>
  <circle cx="325" cy="146" r="7" fill="#0f172a"/><rect x="315" y="150" width="20" height="21" rx="5" fill="#0f172a"/>
  <rect x="176" y="132" width="48" height="34" rx="4" fill="#166534"/>
  <circle cx="182" cy="128" r="5" fill="#fbbf24"/>
</svg>
SVG;
    }

    /** Schlichte Kompass-Szene für Abenteuer ohne eigenes Szenenbild. */
    private static function compassScene(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 400 180" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kompass über einer Landkarte">
  <defs>
    <linearGradient id="adv-kompass-bg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#e0f2fe"/><stop offset="1" stop-color="#bae6fd"/>
    </linearGradient>
  </defs>
  <rect width="400" height="180" fill="url(#adv-kompass-bg)"/>
  <path d="M0 120 q 60 -30 120 -10 q 80 24 160 0 q 70 -20 120 6 l 0 64 l -400 0 z" fill="#bbf7d0"/>
  <path d="M40 100 l 60 -8 M90 130 l 70 -12 M250 120 l 60 -10" stroke="#94a3b8" stroke-width="3" stroke-dasharray="8 6" fill="none"/>
  <circle cx="200" cy="86" r="44" fill="#f8fafc" stroke="#0f766e" stroke-width="5"/>
  <path d="M200 52 l 9 30 l -9 8 l -9 -8 z" fill="#dc2626"/>
  <path d="M200 120 l 9 -30 l -9 -8 l -9 8 z" fill="#334155"/>
  <circle cx="200" cy="86" r="5" fill="#0f766e"/>
  <text x="200" y="34" font-family="sans-serif" font-size="15" fill="#0f766e" text-anchor="middle">N</text>
</svg>
SVG;
    }

    /**
     * Runde 25 (#199): Szenenbild je Kapitel, damit jede Station des Wegs ein
     * eigenes kleines Bild bekommt. Die Dateninsel meldet sich mit dem Slug
     * "adventure". Unbekannte Kombinationen bekommen das Kompass-Bild.
     */
    public static function chapterScene(string $slug, int $position): string
    {
        $scenes = match ($slug) {
            'raumstation' => self::spaceChapterScenes(),
            'museum' => self::museumChapterScenes(),
            'festival' => self::festivalChapterScenes(),
            'adventure', 'insel' => self::islandChapterScenes(),
            default => [],
        };
        return $scenes[$position] ?? self::compassScene();
    }


    /** @return array<int, string> Weltraum-Kapitelbilder (Raumstation Alpha). */
    private static function spaceChapterScenes(): array
    {
        return [
            1 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Brücke der Raumstation mit Sternenfenster und Konsole">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="30" y="12" width="200" height="66" rx="10" fill="#0b2544" stroke="#22d3ee" stroke-width="2"/>
  <circle cx="70" cy="30" r="2" fill="#ffffff"/><circle cx="120" cy="22" r="1.5" fill="#ffffff"/><circle cx="160" cy="38" r="2" fill="#ffffff"/><circle cx="200" cy="26" r="1.5" fill="#ffffff"/><circle cx="96" cy="48" r="1.5" fill="#a78bfa"/><circle cx="180" cy="55" r="2" fill="#a78bfa"/>
  <rect x="18" y="84" width="224" height="28" rx="6" fill="#1e293b"/>
  <circle cx="50" cy="98" r="4" fill="#22d3ee"/><circle cx="70" cy="98" r="4" fill="#fde047"/><circle cx="90" cy="98" r="4" fill="#a78bfa"/><rect x="110" y="92" width="110" height="12" rx="4" fill="#334155"/></svg>
SVG,
            2 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Terminal mit Crew-Liste">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="45" y="12" width="170" height="82" rx="8" fill="#0f172a" stroke="#22d3ee" stroke-width="2"/>
  <rect x="45" y="12" width="170" height="14" rx="7" fill="#1e293b"/>
  <circle cx="56" cy="19" r="3" fill="#22d3ee"/><circle cx="68" cy="19" r="3" fill="#fde047"/>
  <rect x="58" y="36" width="10" height="10" rx="5" fill="#a78bfa"/><rect x="74" y="39" width="90" height="5" rx="2" fill="#e2e8f0"/>
  <rect x="58" y="54" width="10" height="10" rx="5" fill="#22d3ee"/><rect x="74" y="57" width="70" height="5" rx="2" fill="#e2e8f0"/>
  <rect x="58" y="72" width="10" height="10" rx="5" fill="#94a3b8"/><rect x="74" y="75" width="100" height="5" rx="2" fill="#e2e8f0"/></svg>
SVG,
            3 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Stationsmodule mit Crew-Punkten">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="30" y="20" width="60" height="46" rx="8" fill="#1e293b" stroke="#94a3b8" stroke-width="2"/>
  <circle cx="60" cy="43" r="8" fill="#a78bfa"/>
  <rect x="100" y="20" width="60" height="46" rx="8" fill="#1e293b" stroke="#22d3ee" stroke-width="2"/>
  <circle cx="130" cy="43" r="8" fill="#fde047"/>
  <rect x="170" y="20" width="60" height="46" rx="8" fill="#1e293b" stroke="#94a3b8" stroke-width="2"/>
  <path d="M192 50 l16 0 m-8 -8 l0 16 m-16 -8 l0 0" stroke="#22d3ee" stroke-width="2" fill="none"/>
  <rect x="30" y="76" width="200" height="14" rx="4" fill="#334155"/><circle cx="130" cy="83" r="4" fill="#22d3ee"/></svg>
SVG,
            4 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Meteoriteneinschlag mit Alarmsymbol">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<path d="M250 6 L188 46" stroke="#fbbf24" stroke-width="4" stroke-linecap="round"/>
  <circle cx="186" cy="47" r="7" fill="#fde047"/>
  <path d="M170 60 l12 -10 m-12 2 l10 12" stroke="#f97316" stroke-width="3" stroke-linecap="round"/>
  <path d="M60 74 l24 0 l-12 -22 Z" fill="#fde047" stroke="#0b1220" stroke-width="3"/>
  <rect x="52" cy="0" width="24" height="9" rx="2" fill="#fde047"/>
  <path d="M70 78 l0 18" stroke="#22d3ee" stroke-width="3" stroke-linecap="round"/>
  <rect x="30" y="96" width="200" height="16" rx="4" fill="#1e293b"/><circle cx="130" cy="104" r="5" fill="#f97316"/></svg>
SVG,
            5 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Sauerstofftanks, einer mit Leck">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="40" y="26" width="46" height="72" rx="14" fill="#1e293b" stroke="#22d3ee" stroke-width="2"/>
  <rect x="52" y="40" width="22" height="44" rx="8" fill="#22d3ee" opacity="0.6"/>
  <rect x="150" y="26" width="46" height="72" rx="14" fill="#1e293b" stroke="#f87171" stroke-width="2"/>
  <rect x="162" y="52" width="22" height="32" rx="8" fill="#f87171" opacity="0.5"/>
  <path d="M196 66 q6 6 0 12" stroke="#22d3ee" stroke-width="2" fill="none"/>
  <path d="M204 58 q6 6 0 12" stroke="#22d3ee" stroke-width="2" fill="none"/>
  <circle cx="204" cy="86" r="3" fill="#38bdf8"/><circle cx="210" cy="96" r="3" fill="#38bdf8"/></svg>
SVG,
            6 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Reparaturdrohne über Tank">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="96" y="52" width="68" height="26" rx="10" fill="#334155" stroke="#22d3ee" stroke-width="2"/>
  <circle cx="104" cy="44" r="10" fill="none" stroke="#a78bfa" stroke-width="3"/>
  <circle cx="156" cy="44" r="10" fill="none" stroke="#a78bfa" stroke-width="3"/>
  <path d="M104 36 l0 16 M96 44 l16 0" stroke="#a78bfa" stroke-width="2"/>
  <path d="M156 36 l0 16 M148 44 l16 0" stroke="#a78bfa" stroke-width="2"/>
  <circle cx="130" cy="66" r="4" fill="#fde047"/>
  <path d="M130 78 l0 10" stroke="#fde047" stroke-width="2"/>
  <rect x="76" y="92" width="108" height="18" rx="6" fill="#1e293b" stroke="#22d3ee" stroke-width="2"/></svg>
SVG,
            7 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Balkendiagramm des Sauerstoffs je Modul">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<path d="M50 96 L210 96" stroke="#94a3b8" stroke-width="2"/>
  <rect x="60" y="52" width="24" height="44" rx="4" fill="#22d3ee"/>
  <rect x="104" y="36" width="24" height="60" rx="4" fill="#22d3ee"/>
  <rect x="148" y="62" width="24" height="34" rx="4" fill="#a78bfa"/>
  <rect x="192" y="28" width="24" height="68" rx="4" fill="#22d3ee"/>
  <circle cx="72" cy="24" r="3" fill="#fde047"/><circle cx="116" cy="24" r="3" fill="#fde047"/><circle cx="204" cy="20" r="3" fill="#fde047"/></svg>
SVG,
            8 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kälteschlaf-Kapsel mit Crew-Mitglied">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="70" y="18" width="120" height="84" rx="16" fill="#1e293b" stroke="#a78bfa" stroke-width="2"/>
  <rect x="82" y="30" width="96" height="60" rx="10" fill="#0f172a"/>
  <circle cx="118" cy="58" r="12" fill="#a78bfa"/>
  <path d="M104 82 q14 -18 28 0 Z" fill="#a78bfa"/>
  <path d="M186 26 l4 10 l-4 10 l-4 -10 Z" fill="#e2e8f0"/>
  <path d="M204 38 l3 8 l-3 8 l-3 -8 Z" fill="#94a3b8"/>
  <path d="M218 52 l2 6 l-2 6 l-2 -6 Z" fill="#94a3b8"/></svg>
SVG,
            9 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Frachtcontainer mit Gewichts-Pfeil">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="40" y="66" width="52" height="40" rx="6" fill="#334155" stroke="#94a3b8" stroke-width="2"/>
  <rect x="104" y="52" width="52" height="54" rx="6" fill="#334155" stroke="#94a3b8" stroke-width="2"/>
  <rect x="168" y="38" width="52" height="68" rx="6" fill="#1e293b" stroke="#fde047" stroke-width="3"/>
  <path d="M194 12 l0 18 m-8 -8 l8 8 l8 -8" stroke="#fde047" stroke-width="3" fill="none" stroke-linecap="round"/>
  <path d="M168 62 l52 0 M168 76 l52 0" stroke="#475569" stroke-width="2"/></svg>
SVG,
            10 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Sendeantenne und Erde fuer den Energiebericht">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#0b1220"/>
<rect x="20" y="60" width="140" height="52" rx="8" fill="#1e293b"/>
  <path d="M70 60 q0 -26 30 -26" stroke="#94a3b8" stroke-width="5" fill="none"/>
  <path d="M100 34 l-16 -8 l4 18 Z" fill="#22d3ee"/>
  <path d="M112 22 q14 12 0 24 M124 12 q24 22 0 44" stroke="#22d3ee" stroke-width="2" fill="none" opacity="0.7"/>
  <circle cx="212" cy="84" r="34" fill="#1d4ed8"/>
  <path d="M188 74 q14 8 22 -2 q10 -10 24 -4" stroke="#4ade80" stroke-width="6" fill="none"/>
  <circle cx="222" cy="66" r="10" fill="#e2e8f0" opacity="0.6"/></svg>
SVG
        ];
    }


    /** @return array<int, string> Museum-Kapitelbilder. */
    private static function museumChapterScenes(): array
    {
        return [
            1 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Klemmbrett mit Inventarliste">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="72" y="18" width="116" height="88" rx="8" fill="#fffbeb" stroke="#b45309" stroke-width="3"/>
  <rect x="112" y="10" width="36" height="14" rx="6" fill="#b45309"/>
  <path d="M88 38 l10 10 l16 -18" stroke="#15803d" stroke-width="4" fill="none" stroke-linecap="round"/>
  <rect x="88" y="50" width="84" height="6" rx="3" fill="#d6c9b0"/>
  <path d="M88 66 l10 10 l16 -18" stroke="#15803d" stroke-width="4" fill="none" stroke-linecap="round"/>
  <rect x="88" y="80" width="66" height="6" rx="3" fill="#d6c9b0"/>
  <rect x="88" y="92" width="74" height="6" rx="3" fill="#d6c9b0"/></svg>
SVG,
            2 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Grundriss des Museums mit Flügeln">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="40" y="24" width="70" height="70" fill="none" stroke="#292524" stroke-width="3"/>
  <rect x="110" y="24" width="60" height="70" fill="none" stroke="#292524" stroke-width="3"/>
  <path d="M110 24 L110 94" stroke="#b45309" stroke-width="3"/>
  <rect x="170" y="44" width="50" height="50" fill="none" stroke="#292524" stroke-width="3"/>
  <rect x="52" y="38" width="26" height="20" fill="#b45309" opacity="0.25"/>
  <rect x="120" y="40" width="24" height="16" fill="#15803d" opacity="0.3"/>
  <rect x="180" y="56" width="20" height="16" fill="#b91c1c" opacity="0.25"/>
  <path d="M75 94 l0 0 M140 94 l0 0" stroke="#292524" stroke-width="6"/></svg>
SVG,
            3 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Uhr am Tatabend neben Museumstür">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<circle cx="80" cy="46" r="30" fill="#fffbeb" stroke="#292524" stroke-width="3"/>
  <path d="M80 46 L80 26" stroke="#292524" stroke-width="4" stroke-linecap="round"/>
  <path d="M80 46 L66 56" stroke="#b91c1c" stroke-width="4" stroke-linecap="round"/>
  <circle cx="80" cy="46" r="3" fill="#292524"/>
  <rect x="170" y="34" width="52" height="62" rx="4" fill="#78350f"/>
  <circle cx="212" cy="66" r="4" fill="#fde047"/>
  <path d="M150 96 q30 -12 66 0" stroke="#292524" stroke-width="2" fill="none" stroke-dasharray="4 4"/></svg>
SVG,
            4 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Rahmen mit Leihgabe-Etikett">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="34" y="18" width="110" height="84" rx="4" fill="#b45309"/>
  <rect x="44" y="28" width="90" height="64" fill="#93c5fd"/>
  <circle cx="80" cy="52" r="14" fill="#fde047"/>
  <path d="M50 92 q30 -22 78 0" stroke="#15803d" stroke-width="4" fill="none"/>
  <path d="M144 44 l40 -14" stroke="#292524" stroke-width="2" stroke-dasharray="3 3"/>
  <path d="M186 26 l24 0 l0 16 l-24 0 Z" fill="#15803d"/>
  <circle cx="196" cy="44" r="3" fill="#15803d"/><circle cx="206" cy="44" r="3" fill="#15803d"/></svg>
SVG,
            5 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Der Ostflügel mit Bildern an der Wand">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="24" y="30" width="212" height="66" rx="4" fill="#e7dcc8" stroke="#b45309" stroke-width="3"/>
  <rect x="40" y="42" width="34" height="42" fill="#93c5fd" stroke="#292524" stroke-width="2"/>
  <rect x="90" y="48" width="30" height="30" fill="#fde68a" stroke="#292524" stroke-width="2"/>
  <rect x="136" y="42" width="34" height="42" fill="#fda4af" stroke="#292524" stroke-width="2"/>
  <rect x="186" y="48" width="30" height="30" fill="#bbf7d0" stroke="#292524" stroke-width="2"/>
  <path d="M24 96 q40 10 212 0" stroke="#b45309" stroke-width="3" fill="none"/>
  <text x="130" y="20" font-size="14" text-anchor="middle" fill="#b45309" font-family="sans-serif">Ost</text></svg>
SVG,
            6 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kameras mit Sichtkegeln in zwei Flügeln">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<path d="M60 18 L34 40 L34 66 L60 88 Z" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <path d="M200 18 L226 40 L226 66 L200 88 Z" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <rect x="72" y="46" width="26" height="16" rx="4" fill="#292524"/>
  <path d="M98 46 L138 30 L138 78 Z" fill="#b91c1c" opacity="0.2"/>
  <rect x="166" y="46" width="26" height="16" rx="4" fill="#292524"/>
  <path d="M166 46 L128 30 L128 78 Z" fill="#15803d" opacity="0.2"/>
  <circle cx="85" cy="54" r="3" fill="#fde047"/><circle cx="179" cy="54" r="3" fill="#fde047"/></svg>
SVG,
            7 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kamera wird scharf geschaltet">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="88" y="40" width="40" height="24" rx="5" fill="#292524"/>
  <path d="M128 40 L164 26 L164 78 Z" fill="#15803d" opacity="0.25"/>
  <circle cx="108" cy="52" r="4" fill="#fde047"/>
  <circle cx="176" cy="42" r="18" fill="#15803d"/>
  <path d="M168 42 l6 8 l12 -16" stroke="#fffbeb" stroke-width="4" fill="none" stroke-linecap="round"/>
  <path d="M40 60 q10 -10 0 -20 M32 60 q16 -16 0 -32" stroke="#15803d" stroke-width="3" fill="none" opacity="0.6"/>
  <rect x="92" y="70" width="8" height="30" fill="#292524"/>
  <rect x="84" y="98" width="24" height="8" rx="3" fill="#292524"/></svg>
SVG,
            8 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Wegspur durch die Ausstellungsräume">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="26" y="20" width="90" height="34" rx="4" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <rect x="26" y="66" width="90" height="34" rx="4" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <rect x="146" y="42" width="88" height="58" rx="4" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <path d="M70 54 q60 0 100 12 q30 10 30 30" stroke="#b45309" stroke-width="3" fill="none" stroke-dasharray="6 6"/>
  <ellipse cx="70" cy="54" rx="6" ry="9" fill="#b45309"/>
  <ellipse cx="130" cy="60" rx="6" ry="9" fill="#b45309" transform="rotate(8 130 60)"/>
  <ellipse cx="180" cy="72" rx="6" ry="9" fill="#b45309" transform="rotate(20 180 72)"/>
  <ellipse cx="204" cy="86" rx="6" ry="9" fill="#b45309" transform="rotate(30 204 86)"/></svg>
SVG,
            9 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Besucherzahlen je Raum als Balken">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<path d="M40 100 L220 100" stroke="#292524" stroke-width="2"/>
  <rect x="52" y="44" width="26" height="56" rx="4" fill="#b45309"/>
  <rect x="98" y="24" width="26" height="76" rx="4" fill="#15803d"/>
  <rect x="144" y="58" width="26" height="42" rx="4" fill="#b45309"/>
  <rect x="190" y="38" width="26" height="62" rx="4" fill="#b91c1c"/>
  <circle cx="65" cy="32" r="5" fill="#292524"/><circle cx="111" cy="14" r="5" fill="#292524"/><circle cx="203" cy="26" r="5" fill="#292524"/>
  <rect x="40" y="106" width="180" height="6" rx="3" fill="#d6c9b0"/></svg>
SVG,
            10 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Lupe über der letzten Person im Raum">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#f5efe6"/>
<rect x="24" y="34" width="120" height="62" rx="4" fill="#e7dcc8" stroke="#292524" stroke-width="2"/>
  <circle cx="70" cy="60" r="10" fill="#292524"/>
  <path d="M52 96 q18 -24 36 0 Z" fill="#292524"/>
  <circle cx="170" cy="50" r="26" fill="none" stroke="#b45309" stroke-width="5"/>
  <circle cx="170" cy="50" r="26" fill="#fde047" opacity="0.15"/>
  <path d="M188 70 l16 16" stroke="#b45309" stroke-width="7" stroke-linecap="round"/>
  <path d="M100 30 q10 -12 24 0" stroke="#b45309" stroke-width="2" fill="none" stroke-dasharray="3 3"/></svg>
SVG
        ];
    }


    /** @return array<int, string> Festival-Kapitelbilder. */
    private static function festivalChapterScenes(): array
    {
        return [
            1 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Festivalplakat mit Line-up">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<rect x="76" y="14" width="108" height="92" rx="6" fill="#fffbeb" stroke="#a78bfa" stroke-width="3"/>
  <rect x="90" y="26" width="80" height="12" rx="4" fill="#a78bfa"/>
  <circle cx="104" cy="58" r="6" fill="#f472b6"/><rect x="116" y="54" width="46" height="8" rx="4" fill="#fbbf24"/>
  <circle cx="104" cy="76" r="6" fill="#fb7185"/><rect x="116" y="72" width="56" height="8" rx="4" fill="#fbbf24"/>
  <circle cx="104" cy="94" r="6" fill="#a78bfa"/><rect x="116" y="90" width="38" height="8" rx="4" fill="#fbbf24"/>
  <path d="M20 60 l18 -8 l0 16 Z" fill="#fbbf24"/><path d="M240 60 l-18 -8 l0 16 Z" fill="#fbbf24"/></svg>
SVG,
            2 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Zwei Festivalbühnen mit Zeltdach">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M30 34 L70 20 L110 34 Z" fill="#f472b6"/>
  <rect x="44" y="34" width="52" height="24" fill="#be185d" opacity="0.3"/>
  <rect x="44" y="58" width="52" height="30" fill="#f9a8d4" opacity="0.5"/>
  <path d="M150 34 L190 20 L230 34 Z" fill="#a78bfa"/>
  <rect x="164" y="34" width="52" height="24" fill="#6d28d9" opacity="0.3"/>
  <rect x="164" y="58" width="52" height="30" fill="#c4b5fd" opacity="0.5"/>
  <path d="M70 88 q30 8 60 0" stroke="#15803d" stroke-width="3" fill="none"/>
  <circle cx="70" cy="34" r="3" fill="#fde047"/><circle cx="190" cy="34" r="3" fill="#fde047"/></svg>
SVG,
            3 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Bands als Punkte am roten Faden">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M30 84 Q130 20 230 74" stroke="#b91c1c" stroke-width="3" fill="none" stroke-dasharray="8 6"/>
  <circle cx="30" cy="84" r="9" fill="#f472b6" stroke="#292524" stroke-width="2"/>
  <circle cx="94" cy="50" r="9" fill="#fbbf24" stroke="#292524" stroke-width="2"/>
  <circle cx="166" cy="40" r="9" fill="#a78bfa" stroke="#292524" stroke-width="2"/>
  <circle cx="230" cy="74" r="9" fill="#fb7185" stroke="#292524" stroke-width="2"/>
  <rect x="86" y="68" width="16" height="6" rx="2" fill="#15803d"/><rect x="158" y="58" width="16" height="6" rx="2" fill="#15803d"/></svg>
SVG,
            4 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kalenderraster mit Fragezeichen">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<rect x="40" y="18" width="180" height="86" rx="6" fill="#fffbeb" stroke="#f472b6" stroke-width="3"/>
  <rect x="40" y="18" width="180" height="18" rx="6" fill="#f472b6"/>
  <rect x="54" y="46" width="44" height="26" rx="4" fill="#bbf7d0"/><text x="76" y="64" font-size="16" text-anchor="middle" fill="#15803d" font-family="sans-serif">?</text>
  <rect x="108" y="46" width="44" height="26" rx="4" fill="#bbf7d0"/><text x="130" y="64" font-size="16" text-anchor="middle" fill="#15803d" font-family="sans-serif">?</text>
  <rect x="162" y="46" width="44" height="26" rx="4" fill="#fecaca"/><text x="184" y="64" font-size="16" text-anchor="middle" fill="#b91c1c" font-family="sans-serif">?</text>
  <rect x="54" y="80" width="44" height="18" rx="4" fill="#e7dcc8"/><rect x="108" y="80" width="44" height="18" rx="4" fill="#e7dcc8"/><rect x="162" y="80" width="44" height="18" rx="4" fill="#e7dcc8"/></svg>
SVG,
            5 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Auslastung der Bühnen als Füllstände">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<circle cx="62" cy="52" r="24" fill="none" stroke="#f472b6" stroke-width="6"/>
  <path d="M62 28 a24 24 0 0 1 22 34 a24 24 0 0 1 -30 10 Z" fill="#f472b6" opacity="0.5"/>
  <circle cx="130" cy="52" r="24" fill="none" stroke="#fbbf24" stroke-width="6"/>
  <path d="M130 28 a24 24 0 0 1 0 48 Z" fill="#fbbf24" opacity="0.5"/>
  <circle cx="198" cy="52" r="24" fill="none" stroke="#a78bfa" stroke-width="6"/>
  <circle cx="198" cy="52" r="24" fill="#a78bfa" opacity="0.35"/>
  <path d="M40 90 q90 12 180 0" stroke="#15803d" stroke-width="3" fill="none"/>
  <rect x="56" y="96" width="12" height="8" fill="#7c2d12"/><rect x="124" y="96" width="12" height="8" fill="#7c2d12"/><rect x="192" y="96" width="12" height="8" fill="#7c2d12"/></svg>
SVG,
            6 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Auftritte bekommen Bestätigungs-Stempel">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<rect x="52" y="16" width="156" height="88" rx="6" fill="#fffbeb" stroke="#a78bfa" stroke-width="3"/>
  <rect x="66" y="28" width="80" height="8" rx="4" fill="#e7dcc8"/>
  <rect x="66" y="44" width="100" height="8" rx="4" fill="#e7dcc8"/>
  <rect x="66" y="60" width="64" height="8" rx="4" fill="#e7dcc8"/>
  <rect x="66" y="76" width="90" height="8" rx="4" fill="#e7dcc8"/>
  <circle cx="176" cy="76" r="16" fill="#15803d" opacity="0.85"/>
  <path d="M168 76 l5 6 l11 -13" stroke="#fffbeb" stroke-width="4" fill="none" stroke-linecap="round"/>
  <circle cx="176" cy="46" r="12" fill="#15803d" opacity="0.5"/>
  <path d="M170 46 l4 5 l9 -10" stroke="#fffbeb" stroke-width="3" fill="none" stroke-linecap="round"/></svg>
SVG,
            7 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Lautsprecher und Kabel zur Waldbühne">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M120 20 q40 -14 80 8" stroke="#7c2d12" stroke-width="4" fill="none" stroke-dasharray="10 6"/>
  <rect x="40" y="30" width="36" height="46" rx="6" fill="#7c2d12"/>
  <rect x="48" y="38" width="20" height="30" rx="4" fill="#fed7aa"/>
  <rect x="46" y="80" width="24" height="12" fill="#7c2d12"/>
  <path d="M186 34 L206 14 L206 62 L186 84 Z" fill="#15803d" opacity="0.7"/>
  <rect x="176" y="30" width="20" height="56" rx="4" fill="#7c2d12"/>
  <circle cx="186" cy="46" r="5" fill="#fed7aa"/><circle cx="186" cy="64" r="5" fill="#fed7aa"/>
  <circle cx="58" cy="24" r="12" fill="#fde047"/><path d="M58 12 l0 -4 M58 36 l0 4 M46 24 l-4 0 M70 24 l4 0" stroke="#fde047" stroke-width="3"/></svg>
SVG,
            8 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Zeitstrahl fuer den Samstagsplan">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M30 62 L230 62" stroke="#292524" stroke-width="4" stroke-linecap="round"/>
  <circle cx="60" cy="62" r="9" fill="#f472b6"/>
  <circle cx="110" cy="62" r="9" fill="#fbbf24"/>
  <circle cx="160" cy="62" r="9" fill="#a78bfa"/>
  <circle cx="210" cy="62" r="9" fill="#fb7185"/>
  <rect x="46" y="34" width="28" height="18" rx="4" fill="#fffbeb" stroke="#f472b6" stroke-width="2"/>
  <rect x="96" y="76" width="28" height="18" rx="4" fill="#fffbeb" stroke="#fbbf24" stroke-width="2"/>
  <rect x="196" y="34" width="28" height="18" rx="4" fill="#fffbeb" stroke="#fb7185" stroke-width="2"/>
  <circle cx="130" cy="26" r="14" fill="none" stroke="#292524" stroke-width="3"/>
  <path d="M130 26 L130 16 M130 26 L138 30" stroke="#292524" stroke-width="3"/></svg>
SVG,
            9 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Aufgeschlagenes Programmheft">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M130 26 L52 38 L52 96 L130 84 Z" fill="#fffbeb" stroke="#a78bfa" stroke-width="3"/>
  <path d="M130 26 L208 38 L208 96 L130 84 Z" fill="#fef9c3" stroke="#a78bfa" stroke-width="3"/>
  <rect x="66" y="48" width="44" height="6" rx="3" fill="#fbbf24"/>
  <rect x="66" y="60" width="44" height="6" rx="3" fill="#e7dcc8"/>
  <rect x="66" y="72" width="34" height="6" rx="3" fill="#e7dcc8"/>
  <rect x="150" y="48" width="44" height="6" rx="3" fill="#f472b6"/>
  <rect x="150" y="60" width="44" height="6" rx="3" fill="#e7dcc8"/>
  <rect x="150" y="72" width="38" height="6" rx="3" fill="#e7dcc8"/>
  <path d="M130 26 L130 84" stroke="#a78bfa" stroke-width="3"/></svg>
SVG,
            10 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Regenschirm schützt die Bühne vor Gewitter">
  <rect x="0" y="0" width="260" height="120" rx="8" fill="#bae6fd"/><rect x="0" y="88" width="260" height="32" rx="8" fill="#86efac"/>
<path d="M40 34 q0 -26 30 -26 q26 0 30 18 q4 -18 30 -18 q30 0 30 26 Z" fill="#f472b6"/>
  <path d="M130 34 l0 18 q0 10 -12 10" stroke="#292524" stroke-width="4" fill="none"/>
  <rect x="60" y="76" width="76" height="28" rx="4" fill="#a78bfa"/>
  <rect x="70" y="82" width="56" height="16" rx="3" fill="#c4b5fd"/>
  <path d="M180 30 l-10 22 l10 0 l-8 20" stroke="#fde047" stroke-width="4" fill="none" stroke-linecap="round"/>
  <path d="M28 44 l-4 10 M48 50 l-4 10 M212 44 l4 10 M234 52 l4 10" stroke="#38bdf8" stroke-width="3" stroke-linecap="round"/></svg>
SVG
        ];
    }


    /** @return array<int, string> Insel-Kapitelbilder (Dateninsel). */
    private static function islandChapterScenes(): array
    {
        return [
            1 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Boot legt am Holzsteg der Insel an">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M20 26 L20 84 M40 26 L40 84 M60 26 L60 84" stroke="#7c2d12" stroke-width="6"/>
  <path d="M10 84 L74 84" stroke="#7c2d12" stroke-width="6"/>
  <path d="M150 60 L150 96 L196 96 L196 60 Z" fill="#92400e"/>
  <path d="M150 60 L173 40 L196 60" fill="#b45309"/>
  <path d="M173 40 L173 12" stroke="#292524" stroke-width="3"/>
  <path d="M173 16 L206 40 L173 44 Z" fill="#fde047"/>
  <path d="M100 84 q30 8 60 0" stroke="#0ea5e9" stroke-width="3" fill="none"/></svg>
SVG,
            2 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Hütten der Inselbewohner">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M40 60 L40 100 L92 100 L92 60 Z" fill="#b45309"/>
  <path d="M32 60 L66 38 L100 60 Z" fill="#7c2d12"/>
  <path d="M150 68 L150 100 L202 100 L202 68 Z" fill="#b45309"/>
  <path d="M142 68 L176 46 L210 68 Z" fill="#7c2d12"/>
  <rect x="58" y="80" width="16" height="20" rx="3" fill="#292524"/>
  <rect x="168" y="84" width="16" height="16" rx="3" fill="#292524"/>
  <circle cx="66" cy="74" r="3" fill="#fde047"/><circle cx="176" cy="82" r="3" fill="#fde047"/></svg>
SVG,
            3 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Inselkarte mit Huetten und Wegen">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><rect x="0" y="62" width="260" height="58" rx="8" fill="#38bdf8"/><path d="M20 62 Q60 46 130 54 Q200 62 240 54 L240 90 Q160 106 20 90 Z" fill="#fde68a"/>
  <path d="M60 88 q30 -14 60 -6 q30 8 44 0" stroke="#7c2d12" stroke-width="3" fill="none" stroke-dasharray="6 5"/>
  <rect x="46" y="70" width="18" height="14" rx="3" fill="#b45309"/>
  <rect x="118" y="64" width="18" height="14" rx="3" fill="#15803d"/>
  <rect x="186" y="72" width="18" height="14" rx="3" fill="#b45309"/>
  <path d="M96 42 l20 0 m-10 -10 l0 20" stroke="#292524" stroke-width="2"/>
  <circle cx="28" cy="30" r="10" fill="#fde047"/></svg>
SVG,
            4 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Verzauberte Bewohner mit Funken">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><circle cx="120" cy="48" r="14" fill="#a78bfa"/>
  <path d="M96 92 q24 -34 48 0 Z" fill="#a78bfa"/>
  <path d="M96 64 L144 64" stroke="#a78bfa" stroke-width="2"/>
  <path d="M86 30 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 Z" fill="#fde047"/>
  <path d="M158 24 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 l5 -2 Z" fill="#fde047"/>
  <path d="M170 56 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 l5 -2 Z" fill="#f472b6"/>
  <circle cx="120" cy="44" r="3" fill="#fde68a"/><circle cx="128" cy="48" r="3" fill="#fde68a"/></svg>
SVG,
            5 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Kette zerbricht und gib die Figur frei">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><circle cx="120" cy="44" r="13" fill="#22c55e"/>
  <path d="M98 90 q22 -32 44 0 Z" fill="#22c55e"/>
  <path d="M84 58 q8 12 0 22 M84 58 q-8 12 0 22" stroke="#94a3b8" stroke-width="4" fill="none"/>
  <path d="M158 54 q-6 10 0 18" stroke="#94a3b8" stroke-width="4" fill="none"/>
  <circle cx="158" cy="76" r="4" fill="#fde047"/>
  <path d="M66 34 l4 9 l9 4 l-9 4 l-4 9 l-4 -9 l-9 -4 l9 -4 Z" fill="#fde047"/>
  <path d="M176 30 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 Z" fill="#fde047"/></svg>
SVG,
            6 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Goldtruhe mit Münzen">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M64 56 L196 56 L186 92 L74 92 Z" fill="#92400e"/>
  <path d="M64 56 Q130 36 196 56 L196 56" fill="#b45309"/>
  <path d="M64 56 Q130 40 196 56" stroke="#fde047" stroke-width="4" fill="none"/>
  <circle cx="108" cy="44" r="7" fill="#fbbf24"/><circle cx="130" cy="38" r="7" fill="#fbbf24"/><circle cx="152" cy="44" r="7" fill="#fbbf24"/>
  <rect x="122" y="60" width="16" height="20" rx="3" fill="#fde047"/>
  <circle cx="130" cy="70" r="3" fill="#92400e"/>
  <path d="M40 76 l8 0 m-4 -4 l0 8" stroke="#fde047" stroke-width="2"/></svg>
SVG,
            7 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Der größte Schatz bekommt einen Stern">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M40 92 L100 92 L70 40 Z" fill="#94a3b8"/>
  <path d="M110 92 L162 92 L136 52 Z" fill="#fbbf24"/>
  <path d="M172 92 L214 92 L193 60 Z" fill="#94a3b8"/>
  <path d="M136 20 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 l8 -3 Z" fill="#fde047"/>
  <path d="M40 100 L214 100" stroke="#7c2d12" stroke-width="3"/>
  <circle cx="70" cy="76" r="4" fill="#e2e8f0"/><circle cx="136" cy="80" r="4" fill="#f59e0b"/><circle cx="193" cy="82" r="4" fill="#e2e8f0"/></svg>
SVG,
            8 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Silbernes Schwert auf einem Sockel">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><rect x="104" y="84" width="52" height="16" rx="4" fill="#7c2d12"/>
  <rect x="118" y="76" width="24" height="8" rx="3" fill="#b45309"/>
  <path d="M130 76 L130 16 L122 30 L130 24 L138 30 L130 16" fill="#e2e8f0"/>
  <path d="M130 16 L130 44" stroke="#94a3b8" stroke-width="2"/>
  <path d="M114 60 L146 60" stroke="#f59e0b" stroke-width="6" stroke-linecap="round"/>
  <path d="M158 30 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 Z" fill="#fde047"/>
  <path d="M84 40 l2 6 l6 2 l-6 2 l-2 6 l-2 -6 l-6 -2 l6 -2 Z" fill="#fde047"/></svg>
SVG,
            9 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Bibliothek der Insel oeffnet ihre Regale">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><rect x="42" y="24" width="120" height="76" rx="4" fill="#7c2d12"/>
  <rect x="52" y="34" width="100" height="26" rx="3" fill="#fde68a"/>
  <rect x="52" y="66" width="100" height="26" rx="3" fill="#fde68a"/>
  <rect x="58" y="38" width="10" height="22" fill="#b91c1c"/><rect x="72" y="40" width="10" height="20" fill="#15803d"/><rect x="86" y="38" width="10" height="22" fill="#1d4ed8"/><rect x="100" y="40" width="10" height="20" fill="#a16207"/>
  <rect x="58" y="70" width="10" height="22" fill="#1d4ed8"/><rect x="72" y="72" width="10" height="20" fill="#b91c1c"/><rect x="86" y="70" width="10" height="22" fill="#a16207"/><rect x="100" y="72" width="10" height="20" fill="#15803d"/>
  <path d="M190 30 L190 94 L232 94 L232 30 Z" fill="#92400e"/>
  <path d="M211 30 L211 94" stroke="#fde047" stroke-width="4"/>
  <circle cx="202" cy="62" r="3" fill="#fde047"/><circle cx="220" cy="62" r="3" fill="#fde047"/></svg>
SVG,
            10 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Feder schreibt Autorinnen-Namen ins Buch">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M60 34 L60 92 L196 92 L196 34 Z" fill="#fffbeb" stroke="#92400e" stroke-width="4"/>
  <path d="M60 46 L196 46" stroke="#e7dcc8" stroke-width="3"/>
  <rect x="76" y="56" width="88" height="6" rx="3" fill="#a16207"/>
  <rect x="76" y="70" width="66" height="6" rx="3" fill="#a16207"/>
  <path d="M196 44 q26 -6 30 -30 q-24 4 -32 26 Z" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
  <path d="M204 36 L228 14" stroke="#292524" stroke-width="3"/>
  <circle cx="216" cy="78" r="6" fill="#fde047"/>
  <path d="M40 60 l4 10 l10 4 l-10 4 l-4 10 l-4 -10 l-10 -4 l10 -4 Z" fill="#fde047"/></svg>
SVG,
            11 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Schatzkarte mit Orten und Kreuzen">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><rect x="0" y="62" width="260" height="58" rx="8" fill="#38bdf8"/><path d="M20 62 Q80 48 130 56 Q190 64 240 56 L240 92 Q150 104 20 92 Z" fill="#fde68a"/>
  <path d="M44 82 l10 0 M49 77 l0 10 M44 82 l0 0" stroke="#b91c1c" stroke-width="4"/>
  <path d="M100 74 l12 0 M106 68 l0 12" stroke="#b91c1c" stroke-width="5"/>
  <path d="M170 80 l10 0 M175 75 l0 10" stroke="#b91c1c" stroke-width="4"/>
  <path d="M40 70 q60 -18 130 -4" stroke="#7c2d12" stroke-width="2" fill="none" stroke-dasharray="5 5"/>
  <rect x="34" y="28" width="60" height="26" rx="4" fill="#fffbeb" stroke="#b45309" stroke-width="2"/>
  <path d="M64 46 L64 38 M64 46 L58 42 M64 46 L70 42" stroke="#292524" stroke-width="2"/></svg>
SVG,
            12 => <<<'SVG'
<svg viewBox="0 0 260 120" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Waage vergleicht Schaetze mit dem Durchschnitt">
<rect x="0" y="0" width="260" height="120" rx="8" fill="#7dd3fc"/><path d="M0 78 Q130 58 260 78 L260 120 L0 120 Z" fill="#fde68a"/><path d="M130 30 L130 88" stroke="#7c2d12" stroke-width="6"/>
  <path d="M130 88 L108 96 M130 88 L152 96" stroke="#7c2d12" stroke-width="6"/>
  <path d="M64 34 L196 34" stroke="#7c2d12" stroke-width="5"/>
  <path d="M64 34 L40 62 L88 62 Z" fill="none" stroke="#94a3b8" stroke-width="4"/>
  <path d="M196 34 L172 62 L220 62 Z" fill="none" stroke="#94a3b8" stroke-width="4"/>
  <circle cx="52" cy="72" r="7" fill="#fbbf24"/><circle cx="70" cy="76" r="6" fill="#fbbf24"/><circle cx="60" cy="62" r="3" fill="#94a3b8"/>
  <circle cx="188" cy="72" r="7" fill="#e2e8f0"/><circle cx="204" cy="76" r="6" fill="#e2e8f0"/><circle cx="196" cy="62" r="3" fill="#94a3b8"/>
  <path d="M92 52 q38 0 76 0" stroke="#15803d" stroke-width="3" stroke-dasharray="6 5"/></svg>
SVG
        ];
    }
}
