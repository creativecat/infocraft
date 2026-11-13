<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: SQL-Werkstatt: Abenteuer-Beschreibungen und Szenenbilder
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.24.0
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
}
