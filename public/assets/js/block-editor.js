/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Block-Werkstatt: Editor, Laufzeit, Code-Generator
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.17.0
 */
/* InfoCraft Block-Editor: Programmieren mit Blöcken per Drag & Drop (#116-#128).
 *
 * Die Werkstatt kennt daneben Java, Python und Assembler - hier kommt eine
 * blockbasierte Sprache dazu: Blöcke aus der Palette ziehen, untereinander
 * andocken, Werte und Wahrheitswerte in Steckplätze einsetzen. Gestartet
 * wird mit dem ▶-Knopf, die Buehne rechts zeigt alle Figuren.
 *
 * Aufbau:
 *   - Registry (Kategorien + Bloecke) und Befehlsuebersicht daraus
 *   - Programmmodell (Version 2): eigene Skripte pro Figur, die Buehne
 *     ist ein eigenes Ziel mit eigenen Skripten; Serialisierung nach JSON
 *   - Laufzeit (frame-basiert, ohne DOM testbar): Jeder Thread gehört zu
 *     seiner Figur (oder zur Buehne), jeder frame() schiebt alle Threads
 *     bis zum naechsten Yield weiter
 *   - Editor-Oberflaeche: Palette, Figurenliste, Skriptflaeche mit
 *     Drag&Drop (Geister-Element, Drop-Vorschau, Zurueckstellen statt
 *     Verlieren), Buehne mit Stift-Layer
 *
 * Alles ES5 wie die anderen Werkstatt-Module, keine Abhaengigkeiten.
 * Node-Tests: module.exports; Browser: window.IC_BLOCKS.
 */
(function () {
  'use strict';

  /* ---------- Kategorien ---------- */
  var CATS = [
    { id: 'events', label: 'Ereignisse', color: '#d97706' },
    { id: 'motion', label: 'Bewegung', color: '#2563eb' },
    { id: 'looks', label: 'Aussehen', color: '#9333ea' },
    { id: 'sound', label: 'Klang', color: '#e11d48' },
    { id: 'control', label: 'Steuerung', color: '#16a34a' },
    { id: 'sensing', label: 'Wahrnehmung', color: '#0891b2' },
    { id: 'ops', label: 'Operatoren', color: '#059669' },
    { id: 'vars', label: 'Variablen', color: '#db2777', pro: true },
    { id: 'pen', label: 'Stift', color: '#65a30d' },
    /* Meine Bloecke (#138): eigene Bausteine (Prozeduren ohne Parameter). */
    { id: 'myblocks', label: 'Meine Blöcke', color: '#7c3aed' },
    /* Buehne (#139): Hintergruende wechseln, nur als Ziel Buehne sichtbar. */
    { id: 'stage', label: 'Bühne', color: '#0f766e', stageOnly: true }
  ];
  /* Fuer die Buehne (kein Koerper, kein Stift) ausgeblendete Kategorien. */
  var STAGE_HIDDEN_CATS = { motion: true, looks: true, pen: true };

  function catColor(id) {
    var i;
    for (i = 0; i < CATS.length; i++) {
      if (CATS[i].id === id) { return CATS[i].color; }
    }
    return '#334155';
  }

  /* ---------- Auswahl-Quellen ---------- */
  var KEY_OPTIONS = [
    { v: 'leertaste', l: 'Leertaste' },
    { v: 'pfeil hoch', l: 'Pfeil hoch' },
    { v: 'pfeil runter', l: 'Pfeil runter' },
    { v: 'pfeil links', l: 'Pfeil links' },
    { v: 'pfeil rechts', l: 'Pfeil rechts' },
    { v: 'enter', l: 'Enter' }
  ];
  (function () {
    var letters = 'abcdefghijklmnopqrstuvwxyz'.split('');
    var digits = '0123456789'.split('');
    var i;
    for (i = 0; i < letters.length; i++) { KEY_OPTIONS.push({ v: letters[i], l: letters[i].toUpperCase() }); }
    for (i = 0; i < digits.length; i++) { KEY_OPTIONS.push({ v: digits[i], l: digits[i] }); }
  })();
  var COSTUME_OPTIONS = [
    { v: 'robot', l: '🤖 Roboter' },
    { v: 'rocket', l: '🚀 Rakete' },
    { v: 'fish', l: '🐟 Fisch' },
    { v: 'ball', l: '⚽ Ball' }
  ];
  var COSTUME_EMOJI = { robot: '🤖', rocket: '🚀', fish: '🐟', ball: '⚽' };
  var PEN_COLORS = [
    { v: '#1f2937', l: '⚫ Schwarz' },
    { v: '#dc2626', l: '🔴 Rot' },
    { v: '#2563eb', l: '🔵 Blau' },
    { v: '#16a34a', l: '🟢 Grün' }
  ];
  /* Klang (#130): Tonhoehe als Auswahl, Frequenz in Hertz dafuer. */
  var TONE_OPTIONS = [
    { v: 'c4', l: '♪ C (tief)' }, { v: 'd4', l: '♪ D' },
    { v: 'e4', l: '♪ E' }, { v: 'f4', l: '♪ F' },
    { v: 'g4', l: '♪ G' }, { v: 'a4', l: '♪ A' },
    { v: 'h4', l: '♪ H' }, { v: 'c5', l: '♪ C (hoch)' }
  ];
  var TONE_FREQ = { c4: 261.63, d4: 293.66, e4: 329.63, f4: 349.23, g4: 392, a4: 440, h4: 493.88, c5: 523.25 };
  /* Nachrichten (#131): feste Postfaecher, die Senden/Empfangen verbinden. */
  var MSG_OPTIONS = [
    { v: 'nachricht1', l: 'Nachricht 1' }, { v: 'nachricht2', l: 'Nachricht 2' },
    { v: 'nachricht3', l: 'Nachricht 3' }, { v: 'nachricht4', l: 'Nachricht 4' },
    { v: 'nachricht5', l: 'Nachricht 5' }
  ];
  var COSTUME_ORDER = ['robot', 'rocket', 'fish', 'ball'];
  /* Buehnen-Hintergruende (#139): schlichte Farbverlaeufe, direkt im
   * Canvas gezeichnet, unter allen Spuren und Figuren. */
  var BACKDROP_OPTIONS = [
    { v: 'paper', l: '⚪ Papier' },
    { v: 'sunrise', l: '🌅 Sonnenaufgang' },
    { v: 'sea', l: '🌊 Meer' },
    { v: 'meadow', l: '🌾 Wiese' }
  ];
  var BACKDROP_ORDER = ['paper', 'sunrise', 'sea', 'meadow'];
  /* Klone (#132): Obergrenze wie bei klassischen Block-Plattformen. */
  var CLONE_LIMIT = 300;
  /* Beispielprogramme (#142): vier Vorlagen im serialisierten Format. */
  var TEMPLATES = [
    {
      name: 'Begrüßung', desc: 'Die Figur sagt Hallo und bedankt sich für Klicks.', prog: {
        v: 3, vars: [], lists: [],
        sprites: [{
          id: 'tpl-robot', name: 'Roboter', costume: 'robot',
          x: 240, y: 180, dir: 90, size: 100, visible: true, procs: [],
          scripts: [
            { id: 'tpl-s1', x: 40, y: 40, top: { op: 'flag', id: 'tpl-t1', f: {}, sub: [], next:
              { op: 'say_for', id: 'tpl-t2', f: { msg: { v: 'Hallo!' }, secs: { v: '2' } }, sub: [], next:
              { op: 'say', id: 'tpl-t3', f: { msg: { v: 'Klick mich!' } }, sub: [], next: null } } } },
            { id: 'tpl-s2', x: 40, y: 150, top: { op: 'when_clicked', id: 'tpl-t4', f: {}, sub: [], next:
              { op: 'say', id: 'tpl-t5', f: { msg: { v: 'Danke!' } }, sub: [], next: null } } }
          ]
        }],
        stage: { scripts: [], procs: [], backdrop: 'paper' }
      }
    },
    {
      name: 'Labyrinth zeichnen', desc: 'Der Stift zeichnet ein Quadrat.', prog: {
        v: 3, vars: [], lists: [],
        sprites: [{
          id: 'tpl-robot', name: 'Zeichner', costume: 'robot',
          x: 120, y: 120, dir: 90, size: 100, visible: true, procs: [],
          scripts: [
            { id: 'tpl-s1', x: 40, y: 40, top: { op: 'flag', id: 'tpl-t1', f: {}, sub: [], next:
              { op: 'pen_down', id: 'tpl-t2', f: {}, sub: [], next:
              { op: 'repeat', id: 'tpl-t3', f: { count: { v: '4' } }, sub: [
                { op: 'move_steps', id: 'tpl-t4', f: { steps: { v: '100' } }, sub: [], next:
                { op: 'turn_right', id: 'tpl-t5', f: { deg: { v: '90' } }, sub: [], next: null } }
              ], next: null } } } }
          ]
        }],
        stage: { scripts: [], procs: [], backdrop: 'paper' }
      }
    },
    {
      name: 'Verfolger-Spiel', desc: 'Der Roboter folgt dem Fisch, du steuerst mit den Pfeiltasten.', prog: {
        v: 3, vars: [], lists: [],
        sprites: [
          {
            id: 'tpl-robot', name: 'Verfolger', costume: 'robot',
            x: 60, y: 300, dir: 90, size: 100, visible: true, procs: [],
            scripts: [
              { id: 'tpl-s1', x: 40, y: 40, top: { op: 'flag', id: 'tpl-t1', f: {}, sub: [], next:
                { op: 'forever', id: 'tpl-t2', f: {}, sub: [
                  { op: 'point_towards', id: 'tpl-t3', f: { name: { v: 'tpl-fisch' } }, sub: [], next:
                  { op: 'move_steps', id: 'tpl-t4', f: { steps: { v: '3' } }, sub: [], next: null } }
                ], next: null } } }
            ]
          },
          {
            id: 'tpl-fisch', name: 'Fisch', costume: 'fish',
            x: 400, y: 80, dir: 90, size: 100, visible: true, procs: [],
            scripts: [
              { id: 'tpl-f1', x: 40, y: 40, top: { op: 'when_key', id: 'tpl-k1', f: { key: { v: 'pfeil links' } }, sub: [], next:
                { op: 'change_x', id: 'tpl-k2', f: { dx: { v: '-20' } }, sub: [], next: null } } },
              { id: 'tpl-f2', x: 40, y: 120, top: { op: 'when_key', id: 'tpl-k3', f: { key: { v: 'pfeil rechts' } }, sub: [], next:
                { op: 'change_x', id: 'tpl-k4', f: { dx: { v: '20' } }, sub: [], next: null } } },
              { id: 'tpl-f3', x: 40, y: 200, top: { op: 'when_key', id: 'tpl-k5', f: { key: { v: 'pfeil hoch' } }, sub: [], next:
                { op: 'change_y', id: 'tpl-k6', f: { dy: { v: '-20' } }, sub: [], next: null } } },
              { id: 'tpl-f4', x: 40, y: 280, top: { op: 'when_key', id: 'tpl-k7', f: { key: { v: 'pfeil runter' } }, sub: [], next:
                { op: 'change_y', id: 'tpl-k8', f: { dy: { v: '20' } }, sub: [], next: null } } }
            ]
          }
        ],
        stage: { scripts: [], procs: [], backdrop: 'paper' }
      }
    },
    {
      name: 'Rhythmus', desc: 'Zwei Töne im Wechsel, endlos.', prog: {
        v: 3, vars: [], lists: [],
        sprites: [{
          id: 'tpl-robot', name: 'Schlagzeug', costume: 'ball',
          x: 240, y: 180, dir: 90, size: 100, visible: true, procs: [],
          scripts: [
            { id: 'tpl-s1', x: 40, y: 40, top: { op: 'flag', id: 'tpl-t1', f: {}, sub: [], next:
              { op: 'forever', id: 'tpl-t2', f: {}, sub: [
                { op: 'play_note', id: 'tpl-t3', f: { tone: { v: 'c4' }, secs: { v: '0.5' } }, sub: [], next:
                { op: 'play_note', id: 'tpl-t4', f: { tone: { v: 'g4' }, secs: { v: '0.5' } }, sub: [], next: null } }
              ], next: null } } }
            ]
        }],
        stage: { scripts: [], procs: [], backdrop: 'paper' }
      }
    }
  ];

  function templateBlockCount(prog) {
    var nBlocks = 0;
    function cntChain(node) {
      while (node) {
        nBlocks++;
        var i;
        for (i = 0; i < (node.sub || []).length; i++) { cntChain(node.sub[i]); }
        node = node.next;
      }
    }
    var i, all = [];
    for (i = 0; i < prog.sprites.length; i++) { all = all.concat(prog.sprites[i].scripts); }
    all = all.concat(prog.stage.scripts);
    var s;
    for (s = 0; s < all.length; s++) { cntChain(all[s].top); }
    return nBlocks;
  }

  function optionSource(name, vars, sprites, lists, procs) {
    if (name === 'keys') { return KEY_OPTIONS; }
    if (name === 'costumes') { return COSTUME_OPTIONS; }
    if (name === 'colors') { return PEN_COLORS; }
    if (name === 'tones') { return TONE_OPTIONS; }
    if (name === 'msgs') { return MSG_OPTIONS; }
    if (name === 'sprites') {
      var outS = [];
      var sps = sprites || [];
      var j;
      for (j = 0; j < sps.length; j++) { outS.push({ v: sps[j].id, l: sps[j].name }); }
      if (outS.length === 0) { outS.push({ v: '', l: '(keine Figur)' }); }
      return outS;
    }
    if (name === 'vars') {
      var out = [];
      var vs = vars || [];
      var i;
      for (i = 0; i < vs.length; i++) { out.push({ v: vs[i], l: vs[i] }); }
      if (out.length === 0) { out.push({ v: '', l: '(keine Variable)' }); }
      return out;
    }
    if (name === 'lists') {
      var outL = [];
      var ls = lists || [];
      var il;
      for (il = 0; il < ls.length; il++) { outL.push({ v: ls[il], l: ls[il] }); }
      if (outL.length === 0) { outL.push({ v: '', l: '(keine Liste)' }); }
      return outL;
    }
    if (name === 'procs') {
      var outP = [];
      var ps = procs || [];
      var ip;
      for (ip = 0; ip < ps.length; ip++) { outP.push({ v: ps[ip], l: ps[ip] }); }
      if (outP.length === 0) { outP.push({ v: '', l: '(kein Baustein)' }); }
      return outP;
    }
    if (name === 'backdrops') { return BACKDROP_OPTIONS; }
    if (name === 'sprites_self') {
      var outSelf = [{ v: '', l: '(diese Figur)' }];
      var sps2 = sprites || [];
      var j2;
      for (j2 = 0; j2 < sps2.length; j2++) { outSelf.push({ v: sps2[j2].id, l: sps2[j2].name }); }
      return outSelf;
    }
    return [];
  }

  /* ---------- Block-Registry ----------
   * kind: 'hat' (Skriptanfang), 'stack' (Befehl), 'c' (Block mit Oeffnung),
   * 'c2' (mit sonst-Zweig), 'rep' (Wert-Bloeckchen, Pille), 'bool' (Sechseck).
   * Die Form entscheidet allein, was wohin passt (#127).
   * parts: Textbausteine, {in:name} fuer Eingaben, {sub:i} fuer die Oeffnung.
   * inputs: name -> {type:'slot'|'bool'|'select', def, src}
   *   slot = runde Pille (Reporter einsteckbar)
   *   bool = sechseckiger Wahrheitswert-Steckplatz
   *   select = Auswahlmenue                                          */
  var BLOCKS = {
    /* Ereignisse */
    flag: { cat: 'events', kind: 'hat', inputs: {}, parts: ['Wenn ▶ angeklickt wird'],
      help: 'Startet dieses Skript, sobald du oben auf den ▶-Knopf klickst.' },
    when_key: { cat: 'events', kind: 'hat', inputs: { key: { type: 'select', src: 'keys', def: 'leertaste' } },
      parts: ['Wenn Taste ', { in: 'key' }, ' gedrückt wird'],
      help: 'Startet dieses Skript jedes Mal, wenn du die ausgewählte Taste drückst.' },
    when_msg: { cat: 'events', kind: 'hat', inputs: { msg: { type: 'select', src: 'msgs', def: 'nachricht1' } }, pro: true,
      parts: ['Wenn ich ', { in: 'msg' }, ' erhalte'],
      help: 'Startet dieses Skript, sobald jemand die ausgewählte Nachricht sendet (#131).' },
    when_clicked: { cat: 'events', kind: 'hat', inputs: {},
      parts: ['Wenn ich angeklickt werde'],
      help: 'Startet dieses Skript, wenn du die Figur auf der Bühne anklickst (#133).' },
    /* Klone (#132): dieses Skript läuft in jedem frisch erzeugten Klon. */
    when_clone: { cat: 'events', kind: 'hat', inputs: {},
      parts: ['Wenn ich als Klon gestartet werde'],
      help: 'Startet dieses Skript in jedem neu erzeugten Klon (#132).' },
    broadcast: { cat: 'events', kind: 'stack', inputs: { msg: { type: 'select', src: 'msgs', def: 'nachricht1' } }, pro: true,
      parts: ['sende ', { in: 'msg' }],
      help: 'Schickt eine Nachricht an alle Figuren: Jede mit passendem Empfänger-Block startet (#131).' },
    broadcast_wait: { cat: 'events', kind: 'stack', inputs: { msg: { type: 'select', src: 'msgs', def: 'nachricht1' } }, pro: true,
      parts: ['sende ', { in: 'msg' }, ' und warte'],
      help: 'Wie senden, aber dieses Skript wartet, bis alle Empfänger fertig sind (#131).' },

    /* Bewegung */
    move_steps: { cat: 'motion', kind: 'stack', inputs: { steps: { type: 'slot', def: '10' } },
      parts: ['gehe ', { in: 'steps' }, ' Schritte'],
      help: 'Bewegt die Figur in ihre aktuelle Richtung (0 Grad = oben, 90 = rechts).' },
    turn_right: { cat: 'motion', kind: 'stack', inputs: { deg: { type: 'slot', def: '15' } },
      parts: ['drehe dich ↻ um ', { in: 'deg' }, ' Grad'],
      help: 'Dreht die Figur im Uhrzeigersinn.' },
    turn_left: { cat: 'motion', kind: 'stack', inputs: { deg: { type: 'slot', def: '15' } },
      parts: ['drehe dich ↺ um ', { in: 'deg' }, ' Grad'],
      help: 'Dreht die Figur gegen den Uhrzeigersinn.' },
    goto_xy: { cat: 'motion', kind: 'stack',
      inputs: { x: { type: 'slot', def: '240' }, y: { type: 'slot', def: '180' } },
      parts: ['gehe zu x: ', { in: 'x' }, ' y: ', { in: 'y' }],
      help: 'Setzt die Figur an eine Position. Der Ursprung (0, 0) ist oben links, die Mitte der Bühne liegt bei x = 240, y = 180 (#144).' },
    point_dir: { cat: 'motion', kind: 'stack', inputs: { deg: { type: 'slot', def: '90' } },
      parts: ['setze Richtung auf ', { in: 'deg' }, ' Grad'],
      help: 'Legt die Richtung fest: 0 Grad schaut nach oben, 90 Grad nach rechts.' },
    change_x: { cat: 'motion', kind: 'stack', inputs: { dx: { type: 'slot', def: '10' } },
      parts: ['ändere x um ', { in: 'dx' }],
      help: 'Verschiebt die Figur nach rechts (positiv) oder links (negativ).' },
    set_x: { cat: 'motion', kind: 'stack', inputs: { x: { type: 'slot', def: '0' } },
      parts: ['setze x auf ', { in: 'x' }], help: 'Setzt die waagerechte Position.' },
    change_y: { cat: 'motion', kind: 'stack', inputs: { dy: { type: 'slot', def: '10' } },
      parts: ['ändere y um ', { in: 'dy' }],
      help: 'Verschiebt die Figur nach oben (positiv) oder unten (negativ).' },
    set_y: { cat: 'motion', kind: 'stack', inputs: { y: { type: 'slot', def: '0' } },
      parts: ['setze y auf ', { in: 'y' }], help: 'Setzt die senkrechte Position.' },
    glide: { cat: 'motion', kind: 'stack',
      inputs: { secs: { type: 'slot', def: '1' }, x: { type: 'slot', def: '0' }, y: { type: 'slot', def: '0' } },
      parts: ['gleite in ', { in: 'secs' }, ' Sekunden zu x: ', { in: 'x' }, ' y: ', { in: 'y' }],
      help: 'Bewegt die Figur weich und gleichmäßig zur Zielposition (#134).' },
    bounce: { cat: 'motion', kind: 'stack', inputs: {}, parts: ['pralle vom Rand ab'], pro: true,
      help: 'Wenn die Figur den Rand berührt, dreht sie sich und läuft zurück (#134).' },
    point_towards: { cat: 'motion', kind: 'stack', inputs: { name: { type: 'select', src: 'sprites', def: '' } }, pro: true,
      parts: ['drehe dich zu ', { in: 'name' }],
      help: 'Richtet die Figur auf eine andere Figur aus (#134).' },

    /* Aussehen */
    say_for: { cat: 'looks', kind: 'stack',
      inputs: { msg: { type: 'slot', def: 'Hallo!' }, secs: { type: 'slot', def: '2' } },
      parts: ['sage ', { in: 'msg' }, ' für ', { in: 'secs' }, ' Sekunden'],
      help: 'Zeigt eine Sprechblase für die angegebene Zeit und wartet solange.' },
    say: { cat: 'looks', kind: 'stack', inputs: { msg: { type: 'slot', def: 'Hallo!' } },
      parts: ['sage ', { in: 'msg' }], help: 'Zeigt eine Sprechblase, bis etwas anderes gesagt wird.' },
    show: { cat: 'looks', kind: 'stack', inputs: {}, parts: ['zeige dich'],
      help: 'Macht die Figur sichtbar.' },
    hide: { cat: 'looks', kind: 'stack', inputs: {}, parts: ['verstecke dich'],
      help: 'Versteckt die Figur, das Skript läuft weiter.' },
    costume: { cat: 'looks', kind: 'stack', inputs: { costume: { type: 'select', src: 'costumes', def: 'robot' } },
      parts: ['wechsle zu Kostüm ', { in: 'costume' }],
      help: 'Wählt ein anderes Bild für die Figur: Roboter, Rakete, Fisch oder Ball.' },
    size_change: { cat: 'looks', kind: 'stack', inputs: { d: { type: 'slot', def: '10' } },
      parts: ['ändere Größe um ', { in: 'd' }, ' %'], help: 'Macht die Figur größer oder kleiner.' },
    size_set: { cat: 'looks', kind: 'stack', inputs: { s: { type: 'slot', def: '100' } },
      parts: ['setze Größe auf ', { in: 's' }, ' %'], help: 'Legt die Größe in Prozent fest (100 % = normal).' },
    think: { cat: 'looks', kind: 'stack', inputs: { msg: { type: 'slot', def: 'Hmm ...' } },
      parts: ['denke ', { in: 'msg' }], help: 'Zeigt eine Denkblase, bis etwas anderes gedacht wird (#135).' },
    think_for: { cat: 'looks', kind: 'stack',
      inputs: { msg: { type: 'slot', def: 'Hmm ...' }, secs: { type: 'slot', def: '2' } },
      parts: ['denke ', { in: 'msg' }, ' für ', { in: 'secs' }, ' Sekunden'],
      help: 'Zeigt eine Denkblase für die angegebene Zeit und wartet solange (#135).' },
    next_costume: { cat: 'looks', kind: 'stack', inputs: {}, parts: ['wechsle zum nächsten Kostüm'],
      help: 'Schaltet aufs nächste Bild weiter: Roboter, Rakete, Fisch, Ball (#135).' },
    change_effect: { cat: 'looks', kind: 'stack', inputs: { d: { type: 'slot', def: '25' } }, pro: true,
      parts: ['ändere Farbeffekt um ', { in: 'd' }], help: 'Verdreht die Farbe der Figur weiter (0 bis 200) (#135).' },
    set_effect: { cat: 'looks', kind: 'stack', inputs: { v: { type: 'slot', def: '0' } }, pro: true,
      parts: ['setze Farbeffekt auf ', { in: 'v' }], help: 'Legt die Farbmischung fest: 0 = normal, 100 = halb gedreht (#135).' },
    clear_effects: { cat: 'looks', kind: 'stack', inputs: {}, parts: ['Farbeffekte aus'], pro: true,
      help: 'Stellt die Originalfarbe der Figur wieder her (#135).' },
    go_front: { cat: 'looks', kind: 'stack', inputs: {}, parts: ['gehe zu vorderster Ebene'], pro: true,
      help: 'Legt die Figur ganz nach vorn, sie verdeckt alle anderen (#135).' },
    go_back: { cat: 'looks', kind: 'stack', inputs: { n: { type: 'slot', def: '1' } }, pro: true,
      parts: ['gehe ', { in: 'n' }, ' Ebenen nach hinten'],
      help: 'Schiebt die Figur um n Positionen nach hinten (#135).' },

    /* Klang (#130) */
    play_note: { cat: 'sound', kind: 'stack',
      inputs: { tone: { type: 'select', src: 'tones', def: 'c4' }, secs: { type: 'slot', def: '0.5' } },
      parts: ['spiele Ton ', { in: 'tone' }, ' für ', { in: 'secs' }, ' Sekunden'],
      help: 'Spielt eine Tonhöhe ab und wartet solange (#130).' },
    start_note: { cat: 'sound', kind: 'stack', inputs: { tone: { type: 'select', src: 'tones', def: 'c4' } },
      parts: ['starte Ton ', { in: 'tone' }],
      help: 'Startet eine Tonhöhe und läuft sofort weiter, bis gestoppt wird (#130).' },
    stop_sounds: { cat: 'sound', kind: 'stack', inputs: {}, parts: ['stoppe alle Töne'],
      help: 'Beendet alle laufenden Töne aller Figuren (#130).' },
    set_volume: { cat: 'sound', kind: 'stack', inputs: { v: { type: 'slot', def: '70' } },
      parts: ['setze Lautstärke auf ', { in: 'v' }, ' %'],
      help: 'Legt fest, wie laut neue Töne sind (0 bis 100) (#130).' },
    volume: { cat: 'sound', kind: 'rep', inputs: {}, parts: ['Lautstärke'],
      help: 'Die aktuelle Lautstärke in Prozent, zum Einstecken in Steckplätze (#130).' },

    /* Steuerung */
    wait: { cat: 'control', kind: 'stack', inputs: { secs: { type: 'slot', def: '1' } },
      parts: ['warte ', { in: 'secs' }, ' Sekunden'],
      help: 'Hält das Skript für die angegebene Zeit an.' },
    repeat: { cat: 'control', kind: 'c', inputs: { count: { type: 'slot', def: '10' } },
      parts: ['wiederhole ', { in: 'count' }, ' mal', { sub: 0 }],
      help: 'Führt die innen liegenden Blöcke bis zu n mal aus (ein Durchlauf pro Bild).' },
    forever: { cat: 'control', kind: 'c', inputs: {}, parts: ['wiederhole fortlaufend', { sub: 0 }],
      help: 'Führt die innen liegenden Blöcke endlos aus, ein Durchlauf pro Bild.' },
    if_then: { cat: 'control', kind: 'c', inputs: { cond: { type: 'bool' } },
      parts: ['wenn ', { in: 'cond' }, ' dann', { sub: 0 }],
      help: 'Führt die innen liegenden Blöcke nur aus, wenn die Bedingung wahr ist.' },
    if_else: { cat: 'control', kind: 'c2', inputs: { cond: { type: 'bool' } },
      parts: ['wenn ', { in: 'cond' }, ' sonst', { sub: 0 }, { sub: 1 }],
      help: 'Zwei Wege: Bedingung wahr -> dann-Zweig, sonst -> sonst-Zweig.' },
    wait_until: { cat: 'control', kind: 'stack', inputs: { cond: { type: 'bool' } },
      parts: ['warte bis ', { in: 'cond' }],
      help: 'Wartet so lange, bis die Bedingung wahr wird.' },
    repeat_until: { cat: 'control', kind: 'c', inputs: { cond: { type: 'bool' } },
      parts: ['wiederhole bis ', { in: 'cond' }, { sub: 0 }],
      help: 'Läuft im Kreis, bis die Bedingung wahr wird.' },
    stop_all: { cat: 'control', kind: 'stack', inputs: {}, parts: ['stoppe alles'],
      help: 'Beendet sofort alle laufenden Skripte.' },
    /* Klone (#132): Original belassen, Kopie erzeugen und wieder entfernen. */
    clone_create: { cat: 'control', kind: 'stack',
      inputs: { name: { type: 'select', src: 'sprites_self', def: '' } },
      parts: ['erzeuge Klon von ', { in: 'name' }],
      help: 'Erzeugt eine Kopie einer Figur und startet deren Klon-Skript. Diese Figur bedeutet: die Figur selbst (#132).' },
    clone_delete: { cat: 'control', kind: 'stack', inputs: {},
      parts: ['lösche diesen Klon'],
      help: 'Entfernt diesen Klon von der Bühne. Beim Original passiert nichts (#132).' },

    /* Wahrnehmung */
    touching_edge: { cat: 'sensing', kind: 'bool', inputs: {}, parts: ['berühre ich den Rand?'],
      help: 'Wahr, wenn die Figur den Rand der Bühne berührt.' },
    key_pressed: { cat: 'sensing', kind: 'bool', inputs: { key: { type: 'select', src: 'keys', def: 'leertaste' } },
      parts: ['Taste ', { in: 'key' }, ' gedrückt?'], help: 'Wahr, solange die Taste gehalten wird.' },
    timer: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['Stoppuhr'],
      help: 'Die Sekunden seit dem letzten Start (▶) oder seit dem Zurücksetzen.' },
    reset_timer: { cat: 'sensing', kind: 'stack', inputs: {}, parts: ['setze Stoppuhr zurück'],
      help: 'Stellt die Stoppuhr auf 0.' },
    xpos: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['x-Position'],
      help: 'Die waagerechte Position der Figur (0 bis 480), gemessen vom linken Rand (#144).' },
    ypos: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['y-Position'],
      help: 'Die senkrechte Position der Figur (0 bis 360), gemessen vom oberen Rand (#144).' },
    direction: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['Richtung'],
      help: 'Die aktuelle Richtung in Grad (0 = oben, 90 = rechts).' },
    mouse_x: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['Maus-x'],
      help: 'Die waagerechte Position des Mauszeigers auf der Bühne (#133).' },
    mouse_y: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['Maus-y'],
      help: 'Die senkrechte Position des Mauszeigers auf der Bühne (#133).' },
    mouse_down: { cat: 'sensing', kind: 'bool', inputs: {}, parts: ['Maus gedrückt?'], pro: true,
      help: 'Wahr, solange die Maustaste auf der Bühne gehalten wird (#133).' },
    touching_sprite: { cat: 'sensing', kind: 'bool', inputs: { name: { type: 'select', src: 'sprites', def: '' } }, pro: true,
      parts: ['berühre ich ', { in: 'name' }, '?'],
      help: 'Wahr, wenn die Figur die ausgewählte andere Figur berührt (#133).' },
    distance_to: { cat: 'sensing', kind: 'rep', inputs: { name: { type: 'select', src: 'sprites', def: '' } }, pro: true,
      parts: ['Abstand zu ', { in: 'name' }],
      help: 'Wie weit die ausgewählte Figur entfernt ist, in Bühnenschritten (#133).' },

    /* Operatoren */
    add: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' + ', { in: 'b' }], help: 'Addiert zwei Zahlen (oder hängt Text aneinander).' },
    sub: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' − ', { in: 'b' }], help: 'Zieht die zweite Zahl von der ersten ab.' },
    mul: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' × ', { in: 'b' }], help: 'Multipliziert zwei Zahlen.' },
    div: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' / ', { in: 'b' }], help: 'Teilt zwei Zahlen. Division durch 0 bleibt ohne Absturz.' },
    random: { cat: 'ops', kind: 'rep',
      inputs: { from: { type: 'slot', def: '1' }, to: { type: 'slot', def: '10' } },
      parts: ['Zufallszahl von ', { in: 'from' }, ' bis ', { in: 'to' }],
      help: 'Eine ganzzahlige Zufallszahl zwischen beiden Werten (Grenzen inklusive).' },
    gt: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' > ', { in: 'b' }], help: 'Wahr, wenn der erste Wert größer ist.' },
    lt: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' < ', { in: 'b' }], help: 'Wahr, wenn der erste Wert kleiner ist.' },
    eq: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: [{ in: 'a' }, ' = ', { in: 'b' }], help: 'Wahr, wenn beide Werte gleich sind (Zahlen oder Text).' },
    and: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'bool' }, b: { type: 'bool' } },
      parts: [{ in: 'a' }, ' und ', { in: 'b' }], help: 'Wahr, wenn beide Bedingungen wahr sind.' },
    or: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'bool' }, b: { type: 'bool' } },
      parts: [{ in: 'a' }, ' oder ', { in: 'b' }], help: 'Wahr, wenn mindestens eine Bedingung wahr ist.' },
    not: { cat: 'ops', kind: 'bool', inputs: { a: { type: 'bool' } },
      parts: ['nicht ', { in: 'a' }], help: 'Kehrt einen Wahrheitswert um.' },
    mod: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } }, pro: true,
      parts: ['Rest von ', { in: 'a' }, ' durch ', { in: 'b' }],
      help: 'Der Divisionsrest, wie in der Grundschule: 7 durch 3 = Rest 1 (#136).' },
    round: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' } },
      parts: ['runde ', { in: 'a' }], help: 'Rundet kaufmännisch auf die nächste ganze Zahl (#136).' },
    sqrt: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' } }, pro: true,
      parts: ['Wurzel von ', { in: 'a' }], help: 'Die Quadratwurzel, z. B. Wurzel aus 25 = 5 (#136).' },
    abs: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' } }, pro: true,
      parts: ['Betrag von ', { in: 'a' }], help: 'Macht aus −7 die Zahl 7, das Vorzeichen fällt weg (#136).' },
    join: { cat: 'ops', kind: 'rep', inputs: { a: { type: 'slot', def: '' }, b: { type: 'slot', def: '' } },
      parts: ['verbinde ', { in: 'a' }, ' und ', { in: 'b' }],
      help: 'Hängt zwei Texte oder Zahlen aneinander (#136).' },
    letter_of: { cat: 'ops', kind: 'rep', inputs: { n: { type: 'slot', def: '1' }, txt: { type: 'slot', def: 'Text' } }, pro: true,
      parts: ['Buchstabe ', { in: 'n' }, ' von ', { in: 'txt' }],
      help: 'Der n-te Buchstabe eines Textes, gezählt ab 1 (#136).' },
    length_of: { cat: 'ops', kind: 'rep', inputs: { txt: { type: 'slot', def: 'Text' } }, pro: true,
      parts: ['Länge von ', { in: 'txt' }], help: 'Wie viele Zeichen ein Text hat (#136).' },

    /* Variablen */
    var_set: { cat: 'vars', kind: 'stack',
      inputs: { name: { type: 'select', src: 'vars', def: '' }, value: { type: 'slot', def: '0' } },
      parts: ['setze ', { in: 'name' }, ' auf ', { in: 'value' }],
      help: 'Speichert einen Wert in einer Variablen (oben anlegbar über ＋ Variable).' },
    var_change: { cat: 'vars', kind: 'stack',
      inputs: { name: { type: 'select', src: 'vars', def: '' }, d: { type: 'slot', def: '1' } },
      parts: ['ändere ', { in: 'name' }, ' um ', { in: 'd' }],
      help: 'Addiert einen Wert auf den aktuellen Variablenwert.' },
    var_value: { cat: 'vars', kind: 'rep', inputs: { name: { type: 'select', src: 'vars', def: '' } },
      parts: [{ in: 'name' }], help: 'Liest den Wert einer Variablen, zum Einstecken in Steckplätze.' },
    /* Listen (#137): Werte sammeln, verwalten und nachschlagen. */
    list_add: { cat: 'vars', kind: 'stack', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' }, value: { type: 'slot', def: '1' } },
      parts: ['füge ', { in: 'value' }, ' zu Liste ', { in: 'list' }, ' hinzu'],
      help: 'Hängt einen Wert hinten an die Liste an (#137).' },
    list_del: { cat: 'vars', kind: 'stack', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' }, n: { type: 'slot', def: '1' } },
      parts: ['lösche ', { in: 'n' }, '. aus Liste ', { in: 'list' }],
      help: 'Entfernt den Eintrag an der Position, 1 ist der erste (#137).' },
    list_set: { cat: 'vars', kind: 'stack', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' }, n: { type: 'slot', def: '1' }, value: { type: 'slot', def: '1' } },
      parts: ['ersetze ', { in: 'n' }, '. in Liste ', { in: 'list' }, ' durch ', { in: 'value' }],
      help: 'Überschreibt den Eintrag an der Position (#137).' },
    list_get: { cat: 'vars', kind: 'rep', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' }, n: { type: 'slot', def: '1' } },
      parts: [{ in: 'n' }, '. Eintrag von ', { in: 'list' }],
      help: 'Liest den Eintrag an der Position, 1 ist der erste (#137).' },
    list_len: { cat: 'vars', kind: 'rep', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' } },
      parts: ['Länge von ', { in: 'list' }],
      help: 'Zählt die Einträge in der Liste (#137).' },
    list_has: { cat: 'vars', kind: 'bool', pro: true,
      inputs: { list: { type: 'select', src: 'lists', def: '' }, value: { type: 'slot', def: '1' } },
      parts: ['enthält ', { in: 'list' }, ' ', { in: 'value' }, '?'],
      help: 'Wahr, wenn der Wert in der Liste steht (#137).' },

    /* Stift */
    pen_down: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['Stift ab, fange an zu zeichnen'],
      help: 'Ab jetzt hinterlässt die Figur beim Bewegen eine Spur.' },
    pen_up: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['Stift hoch, stoppe das Zeichnen'],
      help: 'Stoppt das Zeichnen, Bewegungen hinterlassen keine Spur mehr.' },
    pen_clear: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['lösche alle Spuren'],
      help: 'Entfernt alle gezeichneten Spuren von der Bühne.' },
    pen_color: { cat: 'pen', kind: 'stack', inputs: { color: { type: 'select', src: 'colors', def: '#1f2937' } },
      parts: ['setze Stiftfarbe auf ', { in: 'color' }], help: 'Wählt die Farbe künftiger Spuren.' },

    /* Meine Blöcke (#138): eigene Bausteine ohne Parameter. */
    proc_def: { cat: 'myblocks', kind: 'hat', inputs: { name: { type: 'slot', def: 'Mein Baustein' } },
      parts: ['Definiere ', { in: 'name' }],
      help: 'Gibt einem eigenen Baustein einen Namen, darunter kommt was er tun soll (#138).' },
    proc_call: { cat: 'myblocks', kind: 'stack',
      inputs: { name: { type: 'select', src: 'procs', def: '' } },
      parts: [{ in: 'name' }],
      help: 'Ruft einen eigenen Baustein auf (#138).' },

    /* Bühne (#139): Hintergründe wechseln, nur im Ziel Bühne sichtbar. */
    backdrop_switch: { cat: 'stage', kind: 'stack',
      inputs: { name: { type: 'select', src: 'backdrops', def: 'paper' } },
      parts: ['wechsle zu Hintergrund ', { in: 'name' }],
      help: 'Legt den Bühnen-Hintergrund fest (#139).' },
    backdrop_next: { cat: 'stage', kind: 'stack', inputs: {},
      parts: ['nächster Hintergrund'],
      help: 'Blättert zum nächsten Hintergrund weiter (#139).' }
  };

  /* ---------- Modell (#125, #126) ---------- */
  var uidSeq = 0;
  function uid(prefix) {
    uidSeq++;
    return (prefix || 'b') + uidSeq;
  }

  function newBlock(op) {
    var spec = BLOCKS[op];
    var node = { op: op, id: uid(), f: {}, sub: [], next: null };
    var k;
    for (k in spec.inputs) {
      if (!Object.prototype.hasOwnProperty.call(spec.inputs, k)) { continue; }
      node.f[k] = { v: spec.inputs[k].def };
    }
    if (spec.kind === 'c') { node.sub = [null]; }
    if (spec.kind === 'c2') { node.sub = [null, null]; }
    return node;
  }

  function defaultSprite(costume, name) {
    return {
      id: uid('sp'), name: name || 'Figur 1',
      costume: COSTUME_EMOJI[costume] ? costume : 'robot',
      x: 240, y: 180, dir: 90, size: 100, visible: true,
      scripts: [],
      procs: []
    };
  }

  function emptyProgram() {
    return {
      v: 3,
      vars: [],
      lists: [],
      sprites: [defaultSprite('robot', 'Figur 1')],
      stage: { scripts: [], procs: [], backdrop: 'paper' }
    };
  }

  /* ---------- Serialisierung und Validierung (#121, #144) ----------
   * Gespeichert wird ein JSON-Objekt der Version 3: globale Variablen,
   * die Liste der Figuren mit JE eigenen Skripten und die Buehne mit
   * eigenen Skripten. Das Koordinatensystem hat seinen Ursprung oben
   * links wie in der Coding-Werkstatt: x laeuft 0..480, y 0..360 nach
   * unten. Beim Laden wird alles bereinigt: unbekannte
   * Bloecke entfallen, Felder werden gekuerzt, Grenzen greifen. Alte
   * Programme der Version 1 (eine Figur, flache Skriptliste) und der
   * Version 2 (zentrierter Ursprung, y nach oben) werden automatisch
   * auf Version 3 gehoben, Positionen werden dabei umgerechnet
   * (x + 240, y zu 180 - y). */
  var LIMITS = { scripts: 60, depth: 12, blocks: 800, fields: 200, vars: 30, sprites: 12, lists: 30, procs: 20 };

  function serializeBlock(node) {
    var out = { op: node.op, id: node.id, f: {}, sub: [], next: null };
    var k;
    for (k in node.f) {
      if (!Object.prototype.hasOwnProperty.call(node.f, k)) { continue; }
      var fld = node.f[k];
      out.f[k] = fld && fld.b ? { b: serializeBlock(fld.b) } : { v: fld ? String(fld.v) : '' };
    }
    var i;
    for (i = 0; i < (node.sub ? node.sub.length : 0); i++) {
      out.sub[i] = node.sub[i] ? serializeBlock(node.sub[i]) : null;
    }
    if (node.next) { out.next = serializeBlock(node.next); }
    return out;
  }

  function serializeScriptList(list) {
    var out = [];
    var i;
    for (i = 0; i < (list || []).length; i++) {
      var s = list[i];
      out.push({ id: s.id, x: num(s.x), y: num(s.y), top: serializeBlock(s.top) });
    }
    return out;
  }

  function serializeProgram(prog) {
    var sprites = [];
    var i;
    for (i = 0; i < prog.sprites.length; i++) {
      var sp = prog.sprites[i];
      sprites.push({
        id: sp.id, name: String(sp.name), costume: String(sp.costume),
        x: num(sp.x), y: num(sp.y), dir: num(sp.dir),
        size: clamp(num(sp.size), 10, 400), visible: !!sp.visible,
        scripts: serializeScriptList(sp.scripts),
        procs: (sp.procs || []).slice(0, LIMITS.procs)
      });
    }
    return {
      v: 3,
      vars: (prog.vars || []).slice(0),
      lists: (prog.lists || []).slice(0, LIMITS.lists),
      sprites: sprites,
      stage: {
        scripts: serializeScriptList(prog.stage.scripts),
        procs: (prog.stage.procs || []).slice(0, LIMITS.procs),
        backdrop: prog.stage.backdrop || 'paper'
      }
    };
  }

  function parseBlock(raw, budget, depth) {
    if (!raw || typeof raw !== 'object') { return null; }
    if (budget.count >= LIMITS.blocks || depth > LIMITS.depth) { return null; }
    var spec = BLOCKS[raw.op];
    if (!spec) { return null; }
    budget.count++;
    var node = { op: raw.op, id: uid(), f: {}, sub: [], next: null };
    var k;
    for (k in spec.inputs) {
      if (!Object.prototype.hasOwnProperty.call(spec.inputs, k)) { continue; }
      var rf = raw.f && raw.f[k];
      if (rf && rf.b && spec.inputs[k].type !== 'select') {
        var inner = parseBlock(rf.b, budget, depth + 1);
        if (inner && (BLOCKS[inner.op].kind === 'rep' || BLOCKS[inner.op].kind === 'bool')) {
          node.f[k] = { b: inner };
        } else {
          node.f[k] = { v: fieldDefault(spec.inputs[k]) };
        }
      } else if (rf && typeof rf.v !== 'undefined') {
        node.f[k] = { v: String(rf.v).slice(0, LIMITS.fields) };
      } else if (rf && rf.b) {
        node.f[k] = { v: fieldDefault(spec.inputs[k]) };
      } else {
        node.f[k] = { v: fieldDefault(spec.inputs[k]) };
      }
    }
    var subs = spec.kind === 'c' ? 1 : (spec.kind === 'c2' ? 2 : 0);
    var i;
    for (i = 0; i < subs; i++) {
      var rs = raw.sub && raw.sub[i];
      node.sub[i] = rs ? parseBlock(rs, budget, depth + 1) : null;
    }
    if (raw.next) { node.next = parseBlock(raw.next, budget, depth); }
    return node;
  }

  function fieldDefault(inputSpec) {
    return inputSpec.def !== undefined ? String(inputSpec.def) : '';
  }

  function parseScriptList(rawList, budget) {
    var out = [];
    if (Object.prototype.toString.call(rawList) !== '[object Array]') { return out; }
    var i;
    for (i = 0; i < rawList.length && out.length < LIMITS.scripts; i++) {
      var rs = rawList[i];
      if (!rs || typeof rs !== 'object') { continue; }
      var top = parseBlock(rs.top, budget, 0);
      if (!top) { continue; }
      out.push({
        id: uid('s'), x: clamp(Math.round(num(rs.x)), -4000, 4000),
        y: clamp(Math.round(num(rs.y)), -4000, 4000), top: top
      });
    }
    return out;
  }

  function parseSprite(raw, index, legacyCoords) {
    var sp = defaultSprite(raw && raw.costume, 'Figur ' + (index + 1));
    if (!raw || typeof raw !== 'object') { return sp; }
    if (typeof raw.id === 'string' && raw.id.length > 0 && raw.id.length <= 24) { sp.id = raw.id; }
    if (typeof raw.name === 'string' && raw.name.trim() !== '') { sp.name = raw.name.trim().slice(0, 24); }
    /* Nur vorhandene Properties uebernehmen: ein leeres Objekt {} soll die
     * Defaults (z. B. Richtung 90) nicht mit 0 ueberschreiben.
     * Alte Koordinaten (Version 1/2, Ursprung Mitte, y nach oben) werden
     * beim Laden auf Ursprung oben links umgerechnet (#144). */
    if (typeof raw.x !== 'undefined') {
      sp.x = legacyCoords ? clamp(num(raw.x) + 240, 0, 480) : clamp(num(raw.x), 0, 480);
    }
    if (typeof raw.y !== 'undefined') {
      sp.y = legacyCoords ? clamp(180 - num(raw.y), 0, 360) : clamp(num(raw.y), 0, 360);
    }
    if (typeof raw.dir !== 'undefined') { sp.dir = num(raw.dir); }
    if (typeof raw.size !== 'undefined') { sp.size = clamp(num(raw.size), 10, 400); }
    sp.visible = raw.visible !== false;
    if (COSTUME_EMOJI[raw.costume]) { sp.costume = String(raw.costume); }
    /* Eigene Bloecke (#138): Namen pro Figur, ohne Dubletten. */
    if (Object.prototype.toString.call(raw.procs) === '[object Array]') {
      var pi;
      for (pi = 0; pi < raw.procs.length && sp.procs.length < LIMITS.procs; pi++) {
        var pn = sanitizeVarName(String(raw.procs[pi]));
        if (pn !== '' && sp.procs.indexOf(pn) === -1) { sp.procs.push(pn); }
      }
    }
    return sp;
  }

  function parseProgram(text) {
    var data = null;
    try { data = JSON.parse(String(text)); } catch (e) { data = null; }
    var prog = emptyProgram();
    if (!data || typeof data !== 'object') { return prog; }
    /* Version 3 nutzt Ursprung oben links; aeltere Programme werden
     * beim Laden umgerechnet (#144). */
    var legacyCoords = !(num(data.v) >= 3);
    var i;
    if (Object.prototype.toString.call(data.vars) === '[object Array]') {
      for (i = 0; i < data.vars.length && i < LIMITS.vars; i++) {
        var name = sanitizeVarName(String(data.vars[i]));
        if (name !== '' && prog.vars.indexOf(name) === -1) { prog.vars.push(name); }
      }
    }
    /* Listen (#137): globale Namensliste wie bei Variablen. */
    if (Object.prototype.toString.call(data.lists) === '[object Array]') {
      for (i = 0; i < data.lists.length && prog.lists.length < LIMITS.lists; i++) {
        var lname = sanitizeVarName(String(data.lists[i]));
        if (lname !== '' && prog.lists.indexOf(lname) === -1) { prog.lists.push(lname); }
      }
    }
    var budget = { count: 0 };
    if (Object.prototype.toString.call(data.sprites) === '[object Array]') {
      /* Version 2/3: Liste von Figuren, jede mit eigenen Skripten. */
      prog.sprites = [];
      for (i = 0; i < data.sprites.length && i < LIMITS.sprites; i++) {
        var sp = parseSprite(data.sprites[i], i, legacyCoords);
        sp.scripts = parseScriptList(data.sprites[i] && data.sprites[i].scripts, budget);
        prog.sprites.push(sp);
      }
      if (data.stage && Object.prototype.toString.call(data.stage.scripts) === '[object Array]') {
        prog.stage.scripts = parseScriptList(data.stage.scripts, budget);
        if (Object.prototype.toString.call(data.stage.procs) === '[object Array]') {
          for (i = 0; i < data.stage.procs.length && prog.stage.procs.length < LIMITS.procs; i++) {
            var sname = sanitizeVarName(String(data.stage.procs[i]));
            if (sname !== '' && prog.stage.procs.indexOf(sname) === -1) { prog.stage.procs.push(sname); }
          }
        }
        if (BACKDROP_ORDER.indexOf(data.stage.backdrop) !== -1) { prog.stage.backdrop = String(data.stage.backdrop); }
      }
    } else {
      /* Version 1 (vor #125): eine Figur, Skripte direkt am Programm. */
      var legacy = parseSprite(data.sprite, 0, true);
      if (data.sprite && typeof data.sprite === 'object') {
        if (typeof data.sprite.x !== 'undefined') { legacy.x = clamp(num(data.sprite.x) + 240, 0, 480); }
        if (typeof data.sprite.y !== 'undefined') { legacy.y = clamp(180 - num(data.sprite.y), 0, 360); }
        if (typeof data.sprite.dir !== 'undefined') { legacy.dir = num(data.sprite.dir); }
        if (typeof data.sprite.size !== 'undefined') { legacy.size = clamp(num(data.sprite.size), 10, 400); }
        legacy.visible = data.sprite.visible !== false;
        if (COSTUME_EMOJI[data.sprite.costume]) { legacy.costume = String(data.sprite.costume); }
      }
      legacy.scripts = parseScriptList(data.scripts, budget);
      prog.sprites = [legacy];
    }
    return prog;
  }

  function sanitizeVarName(name) {
    var v = String(name).trim().replace(/\s+/g, ' ').slice(0, 24);
    if (!/^[A-Za-z0-9_äöüÄÖÜß ][A-Za-z0-9_äöüÄÖÜß ]*$/.test(v)) { return ''; }
    return v;
  }

  function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }

  /* ---------- Wert-Hilfen: Zahl oder Text (#120) ---------- */
  function isNum(v) {
    if (typeof v === 'number') { return isFinite(v); }
    return /^-?\d+(\.\d+)?$/.test(String(v).trim());
  }
  function num(v) {
    var n = typeof v === 'number' ? v : parseFloat(String(v));
    return isFinite(n) ? n : 0;
  }
  function toBool(v) {
    if (typeof v === 'boolean') { return v; }
    if (typeof v === 'number') { return v !== 0; }
    return v === 'wahr' || v === true || String(v).trim() === '';
  }
  function BlkErr(msg) { return { blkErr: true, msg: msg }; }

  /* ---------- Laufzeit kompilieren ----------
   * Jede Hut-Kette wird beim Start in eine Instruktionsliste uebersetzt:
   * Spruenge (jmp/jmpf/jzm), Zaehler-Slots (mem) fuer wiederhole n mal,
   * Yields fuer Atemholen pro Bild und Calls mit vorberechneten Lese-Funktionen
   * fuer die Steckplaetze. Reporter und Calls bekommen den Thread (und damit
   * seine Figur) mit: so wirken Bewegung und Aussehen immer auf die Figur,
   * zu der das Skript gehoert - oder auf die Buehne. frame(now) schiebt jeden
   * Thread bis zum naechsten Yield weiter - ganz ohne DOM, deshalb ist alles
   * auch in Node testbar. */
  function valFn(field) {
    if (!field) { return function () { return 0; }; }
    if (field.b) { return reporterFn(field.b); }
    var v = String(field.v);
    return function () { return isNum(v) ? num(v) : v; };
  }

  function reporterFn(node) {
    var a, b, fa, fb;
    switch (node.op) {
      case 'add':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) {
          var x = fa(rt, th), y = fb(rt, th);
          if (isNum(x) && isNum(y)) { return num(x) + num(y); }
          return String(x) + String(y);
        };
      case 'sub':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return num(fa(rt, th)) - num(fb(rt, th)); };
      case 'mul':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return num(fa(rt, th)) * num(fb(rt, th)); };
      case 'div':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) {
          var d = num(fb(rt, th));
          if (d === 0) { throw BlkErr('Division durch 0 ist nicht erlaubt.'); }
          return num(fa(rt, th)) / d;
        };
      case 'random':
        fa = valFn(node.f.from); fb = valFn(node.f.to);
        return function (rt) {
          var lo = Math.round(num(fa(rt))), hi = Math.round(num(fb(rt)));
          if (hi < lo) { var t = lo; lo = hi; hi = t; }
          var r = rt.rand ? rt.rand() : Math.random();
          return Math.floor(r * (hi - lo + 1)) + lo;
        };
      case 'gt':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return num(fa(rt, th)) > num(fb(rt, th)); };
      case 'lt':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return num(fa(rt, th)) < num(fb(rt, th)); };
      case 'eq':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) {
          var x = fa(rt, th), y = fb(rt, th);
          if (isNum(x) && isNum(y)) { return num(x) === num(y); }
          return String(x) === String(y);
        };
      case 'and':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return toBool(fa(rt, th)) && toBool(fb(rt, th)); };
      case 'or':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return toBool(fa(rt, th)) || toBool(fb(rt, th)); };
      case 'not':
        fa = valFn(node.f.a);
        return function (rt, th) { return !toBool(fa(rt, th)); };
      case 'var_value':
        a = String(node.f.name.v);
        return function (rt) { return Object.prototype.hasOwnProperty.call(rt.vars, a) ? rt.vars[a] : 0; };
      /* Listen (#137): Position 1 ist der erste Eintrag. */
      case 'list_get':
        a = String(node.f.list.v);
        fa = valFn(node.f.n);
        return function (rt) {
          if (!Object.prototype.hasOwnProperty.call(rt.lists, a)) { throw BlkErr('Unbekannte Liste: ' + a + '.'); }
          var idx = Math.round(num(fa(rt)));
          if (idx < 1 || idx > rt.lists[a].length) { throw BlkErr('Position ' + idx + ' liegt nicht in Liste ' + a + '.'); }
          return rt.lists[a][idx - 1];
        };
      case 'list_len':
        a = String(node.f.list.v);
        return function (rt) { return Object.prototype.hasOwnProperty.call(rt.lists, a) ? rt.lists[a].length : 0; };
      case 'list_has':
        a = String(node.f.list.v);
        fa = valFn(node.f.value);
        return function (rt) { return Object.prototype.hasOwnProperty.call(rt.lists, a) ? rt.lists[a].indexOf(fa(rt)) !== -1 : false; };
      case 'xpos':
        return function (rt, th) { return th && th.tgt ? Math.round(th.tgt.x * 100) / 100 : 0; };
      case 'ypos':
        return function (rt, th) { return th && th.tgt ? Math.round(th.tgt.y * 100) / 100 : 0; };
      case 'direction':
        return function (rt, th) { return th && th.tgt ? th.tgt.dir : 90; };
      case 'timer':
        return function (rt) { return (rt.now - rt.timerStart) / 1000; };
      case 'touching_edge':
        return function (rt, th) { return th && th.tgt ? touchingEdge(th.tgt) : false; };
      case 'key_pressed':
        a = String(node.f.key.v);
        return function (rt) { return rt.keys[a] === true; };
      /* ---------- Klang (#130) ---------- */
      case 'volume':
        return function (rt) { return rt.volume; };
      /* ---------- Sensoren (#133) ---------- */
      case 'mouse_x':
        return function (rt) { return rt.mouse ? Math.round(rt.mouse.x * 100) / 100 : 0; };
      case 'mouse_y':
        return function (rt) { return rt.mouse ? Math.round(rt.mouse.y * 100) / 100 : 0; };
      case 'mouse_down':
        return function (rt) { return !!(rt.mouse && rt.mouse.down); };
      case 'touching_sprite':
        a = String(node.f.name.v);
        return function (rt, th) {
          if (!th || !th.tgt) { return false; }
          var o = findSpriteById(rt, a);
          if (!o || o === th.tgt || !o.visible) { return false; }
          var r = 24 * Math.max(10, th.tgt.size) / 100 + 24 * Math.max(10, o.size) / 100;
          return spriteDist(th.tgt.x - o.x, th.tgt.y - o.y) <= r;
        };
      case 'distance_to':
        a = String(node.f.name.v);
        return function (rt, th) {
          var o = findSpriteById(rt, a);
          if (!th || !th.tgt || !o) { return 0; }
          return Math.round(spriteDist(th.tgt.x - o.x, th.tgt.y - o.y) * 100) / 100;
        };
      /* ---------- Rechnen-Plus (#136) ---------- */
      case 'mod':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) {
          var d = num(fb(rt, th));
          if (d === 0) { throw BlkErr('Rest mit 0 ist nicht erlaubt.'); }
          return num(fa(rt, th)) % d;
        };
      case 'round':
        fa = valFn(node.f.a);
        return function (rt, th) { return Math.round(num(fa(rt, th))); };
      case 'sqrt':
        fa = valFn(node.f.a);
        return function (rt, th) {
          var v = num(fa(rt, th));
          if (v < 0) { throw BlkErr('Wurzel aus einer negativen Zahl ist nicht erlaubt.'); }
          return Math.sqrt(v);
        };
      case 'abs':
        fa = valFn(node.f.a);
        return function (rt, th) { return Math.abs(num(fa(rt, th))); };
      case 'join':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt, th) { return String(fa(rt, th)) + String(fb(rt, th)); };
      case 'letter_of':
        fa = valFn(node.f.n); fb = valFn(node.f.txt);
        return function (rt, th) {
          var s = String(fb(rt, th));
          var n = Math.round(num(fa(rt, th)));
          if (n < 1 || n > s.length) { return ''; }
          return s.charAt(n - 1);
        };
      case 'length_of':
        fa = valFn(node.f.txt);
        return function (rt, th) { return String(fa(rt, th)).length; };
      default:
        return function () { return 0; };
    }
  }

  function touchingEdge(sp) {
    var half = 24 * Math.max(10, sp.size) / 100;
    /* Ursprung oben links (#144): der Rand ist x/y 0 oder 480/360. */
    return sp.x - half <= 0 || sp.x + half >= 480 || sp.y - half <= 0 || sp.y + half >= 360;
  }

  /* ---------- Helfer fuer Sensoren, Klang und Ebenen (#130, #133, #135) ---------- */
  function spriteDist(dx, dy) { return Math.sqrt(dx * dx + dy * dy); }

  function findSpriteById(rt, id) {
    var i;
    for (i = 0; i < rt.sprites.length; i++) {
      if (rt.sprites[i].id === id) { return rt.sprites[i]; }
    }
    return null;
  }

  /* Klang (#130): Web-Audio direkt im Browser, kein Tonmaterial noetig.
   * Ohne AudioContext (Headless-Test, alter Browser) greift der stumme Stub,
   * die Blocklogik bleibt identisch. */
  function ensureAudio(rt) {
    if (rt.audio !== undefined) { return rt.audio; }
    var AC = null;
    if (typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext)) {
      AC = window.AudioContext || window.webkitAudioContext;
    } else if (typeof globalThis !== 'undefined' && (globalThis.AudioContext || globalThis.webkitAudioContext)) {
      AC = globalThis.AudioContext || globalThis.webkitAudioContext;
    }
    if (AC) {
      try { rt.audio = { ctx: new AC() }; } catch (e) { rt.audio = { ctx: null }; }
    } else {
      rt.audio = { ctx: null };
    }
    return rt.audio;
  }

  function playTone(rt, tone, ms) {
    var until = (ms === null || ms === undefined) ? null : rt.now + ms;
    var snd = { osc: null, stop: function () {}, until: until };
    var a = ensureAudio(rt);
    if (a && a.ctx) {
      try {
        var osc = a.ctx.createOscillator();
        var gain = a.ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.value = TONE_FREQ[tone] || 440;
        gain.gain.value = clamp(num(rt.volume), 0, 100) / 100 * 0.3;
        osc.connect(gain);
        gain.connect(a.ctx.destination);
        osc.start();
        snd.osc = osc;
        snd.stop = function () { try { osc.stop(); } catch (e) {} };
      } catch (e) { /* stumm weiterlaufen */ }
    }
    rt.sounds.push(snd);
  }

  function stopAllSounds(rt) {
    var i;
    for (i = 0; i < rt.sounds.length; i++) { rt.sounds[i].stop(); }
    rt.sounds.length = 0;
  }

  /* Ebenen (#135): eigene Reihenfolge-Liste, damit die Zuordnung
   * Figur-Skript (Index in rt.sprites) unangetastet bleibt. */
  function moveLayer(rt, th, delta) {
    if (!th || !th.tgt) { return; }
    var id = th.tgt.id;
    var idx = rt.order.indexOf(id);
    if (idx === -1) { return; }
    rt.order.splice(idx, 1);
    var nidx = clamp(idx + delta, 0, rt.order.length);
    rt.order.splice(nidx, 0, id);
  }

  /* Abprallen (#134): an der Seitenwand die waagerechte Bewegung
   * spiegeln, am Boden an der Decke die senkrechte. */
  function bounceSprite(sp) {
    var half = 24 * Math.max(10, sp.size) / 100;
    /* Ursprung oben links (#144): Spiegelung an der beruehrten Seite. */
    if (sp.x + half >= 480 || sp.x - half <= 0) {
      sp.dir = (360 - sp.dir) % 360;
      sp.x = clamp(sp.x, half, 480 - half);
    } else if (sp.y + half >= 360 || sp.y - half <= 0) {
      sp.dir = (180 - sp.dir + 360) % 360;
      sp.y = clamp(sp.y, half, 360 - half);
    }
  }

  function argsOf(node) {
    var A = {};
    var k;
    for (k in node.f) {
      if (Object.prototype.hasOwnProperty.call(node.f, k)) { A[k] = valFn(node.f[k]); }
    }
    return A;
  }

  function compileChain(head, ctx, out) {
    var n = head;
    while (n) {
      compileBlock(n, ctx, out);
      n = n.next;
    }
  }

  function compileBlock(node, ctx, out) {
    var spec = BLOCKS[node.op];
    if (!spec) { return; }
    switch (node.op) {
      case 'repeat':
        /* zwei Slots: m = Zaehler, m+1 = Vergleichsergebnis der Runde */
        var m = ctx.mem; ctx.mem += 2;
        out.push({ t: 'setm', m: m });
        var loop = out.length;
        out.push({ t: 'ltm', m: m + 1, c: m, fn: valFn(node.f.count) });
        out.push({ t: 'jzm', m: m + 1, to: 0 });
        var jzIdx = out.length - 1;
        compileChain(node.sub[0], ctx, out);
        out.push({ t: 'incm', m: m });
        out.push({ t: 'yield' });
        out.push({ t: 'jmp', to: loop });
        out[jzIdx].to = out.length;
        return;
      case 'forever':
        var loop2 = out.length;
        compileChain(node.sub[0], ctx, out);
        out.push({ t: 'yield' });
        out.push({ t: 'jmp', to: loop2 });
        return;
      case 'if_then':
        out.push({ t: 'jmpf', fn: valFn(node.f.cond), to: 0 });
        var jfIdx = out.length - 1;
        compileChain(node.sub[0], ctx, out);
        out[jfIdx].to = out.length;
        return;
      case 'if_else':
        out.push({ t: 'jmpf', fn: valFn(node.f.cond), to: 0 });
        var jfIdx2 = out.length - 1;
        compileChain(node.sub[0], ctx, out);
        out.push({ t: 'jmp', to: 0 });
        var jmpIdx = out.length - 1;
        out[jfIdx2].to = out.length;
        compileChain(node.sub[1], ctx, out);
        out[jmpIdx].to = out.length;
        return;
      case 'wait_until':
        var loop3 = out.length;
        out.push({ t: 'jmpf', fn: valFn(node.f.cond), to: 0 });
        var jfIdx3 = out.length - 1;
        out.push({ t: 'yield' });
        out.push({ t: 'jmp', to: loop3 });
        out[jfIdx3].to = out.length;
        return;
      case 'repeat_until':
        var loop4 = out.length;
        out.push({ t: 'jmpf', fn: valFn(node.f.cond), to: 0 });
        var jfIdx4 = out.length - 1;
        compileChain(node.sub[0], ctx, out);
        out.push({ t: 'yield' });
        out.push({ t: 'jmp', to: loop4 });
        out[jfIdx4].to = out.length;
        return;
      case 'wait':
        out.push({ t: 'wait', fn: valFn(node.f.secs) });
        return;
      case 'say_for':
        out.push({ t: 'sayfor', fn: valFn(node.f.msg), fn2: valFn(node.f.secs) });
        return;
      case 'think_for':
        out.push({ t: 'thinkfor', fn: valFn(node.f.msg), fn2: valFn(node.f.secs) });
        return;
      /* Klang (#130): spielt und wartet wie sage-fuer. */
      case 'play_note':
        out.push({ t: 'note', fn: valFn(node.f.tone), fn2: valFn(node.f.secs) });
        return;
      /* Bewegung (#134): sanftes Gleiten ueber mehrere Bilder. */
      case 'glide':
        out.push({ t: 'glide', fx: valFn(node.f.x), fy: valFn(node.f.y), fn: valFn(node.f.secs) });
        return;
      /* Nachrichten (#131): senden startet sofort, und-warte blockiert. */
      case 'broadcast_wait':
        out.push({ t: 'bcastwait', fn: valFn(node.f.msg) });
        return;
      /* Eigene Bloecke (#138): Sprung in die Baustein-Kette, Ruecksprung
       * liegt auf dem Rueckkehrstapel des Threads. */
      case 'proc_call':
        out.push({ t: 'callproc', name: valFn(node.f.name) });
        return;
      default:
        out.push({ t: 'call', op: node.op, A: argsOf(node) });
    }
  }

  /* ---------- Befehle ausfuehren: immer auf die Figur des Threads ---------- */
  function applyCall(rt, th, op, A) {
    var sp = th && th.tgt ? th.tgt : null;
    switch (op) {
      case 'move_steps':
        if (!sp) { break; }
        var rad = sp.dir * Math.PI / 180;
        /* y waechst nach unten (#144): der Richtungskosinus kehrt sich um. */
        moveBy(rt, sp, Math.sin(rad) * num(A.steps(rt, th)), -Math.cos(rad) * num(A.steps(rt, th)));
        break;
      case 'turn_right': if (sp) { sp.dir = (sp.dir + num(A.deg(rt, th))) % 360; } break;
      case 'turn_left': if (sp) { sp.dir = (sp.dir - num(A.deg(rt, th))) % 360; } break;
      case 'goto_xy': if (sp) { moveBy(rt, sp, num(A.x(rt, th)) - sp.x, num(A.y(rt, th)) - sp.y); } break;
      case 'point_dir': if (sp) { sp.dir = num(A.deg(rt, th)); } break;
      case 'change_x': if (sp) { moveBy(rt, sp, num(A.dx(rt, th)), 0); } break;
      case 'set_x': if (sp) { moveBy(rt, sp, num(A.x(rt, th)) - sp.x, 0); } break;
      case 'change_y': if (sp) { moveBy(rt, sp, 0, num(A.dy(rt, th))); } break;
      case 'set_y': if (sp) { moveBy(rt, sp, 0, num(A.y(rt, th)) - sp.y); } break;
      case 'say': if (sp) { sp.bubble = { text: String(A.msg(rt, th)), until: null }; } break;
      case 'show': if (sp) { sp.visible = true; } break;
      case 'hide': if (sp) { sp.visible = false; sp.bubble = null; } break;
      case 'costume': if (sp) { sp.costume = String(A.costume(rt, th)); } break;
      case 'size_change': if (sp) { sp.size = clamp(sp.size + num(A.d(rt, th)), 10, 400); } break;
      case 'size_set': if (sp) { sp.size = clamp(num(A.s(rt, th)), 10, 400); } break;
      case 'stop_all': rt.stopped = true; break;
      case 'reset_timer': rt.timerStart = rt.now; break;
      case 'var_set': rt.vars[String(A.name(rt, th))] = A.value(rt, th); break;
      case 'var_change':
        var vn = String(A.name(rt, th));
        rt.vars[vn] = num(Object.prototype.hasOwnProperty.call(rt.vars, vn) ? rt.vars[vn] : 0) + num(A.d(rt, th));
        break;
      case 'pen_down': if (sp) { sp.pen.down = true; } break;
      case 'pen_up': if (sp) { sp.pen.down = false; } break;
      case 'pen_clear':
        var i;
        for (i = 0; i < rt.sprites.length; i++) { rt.sprites[i].pen.segments = []; }
        if (rt.penCtx) { rt.penCtx.clearRect(0, 0, 480, 360); }
        break;
      case 'pen_color': if (sp) { sp.pen.color = String(A.color(rt, th)); } break;
      /* ---------- Klang (#130) ---------- */
      case 'start_note': playTone(rt, String(A.tone(rt, th)), null); break;
      case 'stop_sounds': stopAllSounds(rt); break;
      case 'set_volume': rt.volume = clamp(num(A.v(rt, th)), 0, 100); break;
      /* ---------- Nachrichten (#131) ---------- */
      case 'broadcast':
        if (rt.api.broadcast) { rt.api.broadcast(String(A.msg(rt, th)), rt.now); }
        break;
      /* ---------- Bewegung-Plus (#134) ---------- */
      case 'bounce': if (sp) { bounceSprite(sp); } break;
      case 'point_towards':
        if (sp) {
          var target2 = findSpriteById(rt, String(A.name(rt, th)));
          /* y nach unten (#144): der Richtungswinkel dreht die y-Achse um. */
          if (target2) { sp.dir = Math.atan2(target2.x - sp.x, sp.y - target2.y) * 180 / Math.PI; }
        }
        break;
      /* ---------- Aussehen-Plus (#135) ---------- */
      case 'think':
        if (sp) { sp.bubble = { text: String(A.msg(rt, th)), until: null, think: true }; }
        break;
      case 'next_costume':
        if (sp) {
          var ci = COSTUME_ORDER.indexOf(sp.costume);
          sp.costume = COSTUME_ORDER[(ci + 1 + COSTUME_ORDER.length) % COSTUME_ORDER.length] || 'robot';
        }
        break;
      case 'change_effect':
        if (sp) { sp.effect = ((num(sp.effect) + num(A.d(rt, th))) % 200 + 200) % 200; }
        break;
      case 'set_effect': if (sp) { sp.effect = clamp(num(A.v(rt, th)), 0, 200); } break;
      case 'clear_effects': if (sp) { sp.effect = 0; } break;
      case 'go_front': moveLayer(rt, th, 999); break;
      case 'go_back': moveLayer(rt, th, -Math.round(num(A.n(rt, th)))); break;
      /* ---------- Klone (#132) ---------- */
      case 'clone_create': {
        var cName = String(A.name(rt, th));
        var cSrc = cName === '' ? (sp || null) : findSpriteById(rt, cName);
        if (!cSrc) { break; }
        var cCount = 0;
        var ci;
        for (ci = 0; ci < rt.sprites.length; ci++) { if (rt.sprites[ci].isClone) { cCount++; } }
        if (cCount >= CLONE_LIMIT) { throw BlkErr('Klon-Grenze von ' + CLONE_LIMIT + ' erreicht.'); }
        var clone = {
          id: uid('cl'), name: cSrc.name, costume: String(cSrc.costume),
          x: num(cSrc.x), y: num(cSrc.y), dir: num(cSrc.dir),
          size: clamp(num(cSrc.size), 10, 400), visible: cSrc.visible !== false,
          bubble: null, effect: num(cSrc.effect),
          pen: { down: false, color: String(cSrc.pen.color), segments: [] },
          isClone: true, srcId: cSrc.isClone ? cSrc.srcId : cSrc.id
        };
        rt.sprites.push(clone);
        rt.order.push(clone.id);
        if (rt._startScripts) { rt._startScripts('when_clone', null, rt.now, clone); }
        break;
      }
      case 'clone_delete':
        if (sp && sp.isClone) {
          var di = rt.sprites.indexOf(sp);
          if (di !== -1) { rt.sprites.splice(di, 1); }
          var doidx = rt.order.indexOf(sp.id);
          if (doidx !== -1) { rt.order.splice(doidx, 1); }
          var keepC = [];
          var ti;
          for (ti = 0; ti < rt.threads.length; ti++) {
            if (rt.threads[ti].tgt !== sp) { keepC.push(rt.threads[ti]); }
          }
          rt.threads = keepC;
          return 'kill';
        }
        break;
      /* ---------- Listen (#137): Position 1 ist der erste Eintrag ---------- */
      case 'list_add': {
        var la = String(A.list(rt, th));
        if (la === '') { throw BlkErr('Wähle zuerst eine Liste aus.'); }
        if (!Object.prototype.hasOwnProperty.call(rt.lists, la)) { rt.lists[la] = []; }
        rt.lists[la].push(A.value(rt, th));
        break;
      }
      case 'list_del': {
        var ld = String(A.list(rt, th));
        if (Object.prototype.hasOwnProperty.call(rt.lists, ld)) {
          var ln = Math.round(num(A.n(rt, th)));
          if (ln >= 1 && ln <= rt.lists[ld].length) { rt.lists[ld].splice(ln - 1, 1); }
        }
        break;
      }
      case 'list_set': {
        var ls = String(A.list(rt, th));
        if (Object.prototype.hasOwnProperty.call(rt.lists, ls)) {
          var lsn = Math.round(num(A.n(rt, th)));
          if (lsn >= 1 && lsn <= rt.lists[ls].length) { rt.lists[ls][lsn - 1] = A.value(rt, th); }
        }
        break;
      }
      /* ---------- Buehne (#139): Hintergründe ---------- */
      case 'backdrop_switch': rt.backdrop = String(A.name(rt, th)); break;
      case 'backdrop_next': {
        var bi = BACKDROP_ORDER.indexOf(rt.backdrop);
        rt.backdrop = BACKDROP_ORDER[(bi + 1 + BACKDROP_ORDER.length) % BACKDROP_ORDER.length] || 'paper';
        break;
      }
    }
  }

  function moveBy(rt, sp, dx, dy) {
    var nx = sp.x + dx;
    var ny = sp.y + dy;
    if (sp.pen.down && (dx !== 0 || dy !== 0)) {
      var seg = { x1: sp.x, y1: sp.y, x2: nx, y2: ny, color: sp.pen.color };
      sp.pen.segments.push(seg);
      if (rt.penCtx) {
        rt.penCtx.strokeStyle = seg.color;
        rt.penCtx.lineWidth = 2;
        rt.penCtx.beginPath();
        /* Ursprung oben links (#144): Stift-Koordinaten sind direkt Pixel. */
        rt.penCtx.moveTo(seg.x1, seg.y1);
        rt.penCtx.lineTo(seg.x2, seg.y2);
        rt.penCtx.stroke();
      }
    }
    sp.x = nx;
    sp.y = ny;
  }

  /* ---------- Code-Ansicht: Bloecke als Java oder Python (#140) ----------
   * Blöcke sind nur eine andere Schreibweise für Programmcode: Der
   * Generator wandelt jedes Skript in eine Methode (Java) bzw. Funktion
   * (Python) um, jeder Block wird zu einem Aufruf, der Originaltext des
   * Blocks steht als Kommentar daneben. Befehle ohne fertige Uebersetzung
   * werden als Kommentar sichtbar, statt still zu verschwinden. */
  var LANG_CALLS = {
    move_steps: ['gehe_schritte', ['steps'], 'void'],
    turn_right: ['drehe_rechts', ['deg'], 'void'],
    turn_left: ['drehe_links', ['deg'], 'void'],
    goto_xy: ['gehe_zu', ['x', 'y'], 'void'],
    point_dir: ['setze_richtung', ['deg'], 'void'],
    change_x: ['aendere_x', ['dx'], 'void'],
    set_x: ['setze_x', ['x'], 'void'],
    change_y: ['aendere_y', ['dy'], 'void'],
    set_y: ['setze_y', ['y'], 'void'],
    glide: ['gleite', ['secs', 'x', 'y'], 'void'],
    bounce: ['pralle_ab', [], 'void'],
    point_towards: ['drehe_zu', ['name'], 'void'],
    say: ['sage', ['msg'], 'void'],
    say_for: ['sage_fuer', ['msg', 'secs'], 'void'],
    think: ['denke', ['msg'], 'void'],
    think_for: ['denke_fuer', ['msg', 'secs'], 'void'],
    show: ['zeige_dich', [], 'void'],
    hide: ['verstecke_dich', [], 'void'],
    costume: ['wechsle_kostuem', ['costume'], 'void'],
    next_costume: ['naechstes_kostuem', [], 'void'],
    size_change: ['aendere_groesse', ['d'], 'void'],
    size_set: ['setze_groesse', ['s'], 'void'],
    change_effect: ['aendere_farbeffekt', ['d'], 'void'],
    set_effect: ['setze_farbeffekt', ['v'], 'void'],
    clear_effects: ['farbeffekte_aus', [], 'void'],
    go_front: ['nach_vorne', [], 'void'],
    go_back: ['gehe_hinten', ['n'], 'void'],
    play_note: ['spiele_ton', ['tone', 'secs'], 'void'],
    start_note: ['starte_ton', ['tone'], 'void'],
    stop_sounds: ['stoppe_toene', [], 'void'],
    set_volume: ['setze_lautstaerke', ['v'], 'void'],
    broadcast: ['sende', ['msg'], 'void'],
    broadcast_wait: ['sende_und_warte', ['msg'], 'void'],
    wait: ['warte', ['secs'], 'void'],
    stop_all: ['stoppe_alles', [], 'void'],
    reset_timer: ['stoppuhr_zurueck', [], 'void'],
    var_set: ['setze_variable', ['name', 'value'], 'void'],
    var_change: ['aendere_variable', ['name', 'd'], 'void'],
    pen_down: ['stift_ab', [], 'void'],
    pen_up: ['stift_hoch', [], 'void'],
    pen_clear: ['loesche_spuren', [], 'void'],
    pen_color: ['stift_farbe', ['color'], 'void'],
    /* Klone (#132) */
    clone_create: ['erzeuge_klon_von', ['name'], 'void'],
    clone_delete: ['loesche_diesen_klon', [], 'void'],
    /* Listen (#137) */
    list_add: ['fuege_hinzu', ['list', 'value'], 'void'],
    list_del: ['loesche_aus_liste', ['list', 'n'], 'void'],
    list_set: ['ersetze_in_liste', ['list', 'n', 'value'], 'void'],
    /* Eigene Bloecke (#138) */
    proc_call: ['rufe_baustein', ['name'], 'void'],
    /* Buehne (#139) */
    backdrop_switch: ['wechsle_hintergrund', ['name'], 'void'],
    backdrop_next: ['naechster_hintergrund', [], 'void']
  };

  function langQuote(v, lang) {
    var s = String(v);
    if (lang === 'py') { return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'"; }
    return '"' + s.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
  }

  function langLiteral(field, lang) {
    var v = field && field.v !== undefined ? field.v : '';
    if (isNum(v)) { return String(num(v)); }
    return langQuote(v, lang);
  }

  function blockText(node) {
    var spec = BLOCKS[node.op];
    if (!spec) { return node.op; }
    var s = '';
    var i;
    for (i = 0; i < spec.parts.length; i++) {
      var p = spec.parts[i];
      if (typeof p === 'string') { s += p; }
      else if (p.in) {
        var fld = node.f[p.in];
        s += (fld && fld.b) ? '⬢' : (fld ? String(fld.v) : '');
      }
    }
    return s.replace(/\s+/g, ' ').trim();
  }

  function makeHelpers() {
    return {
      used: {},
      counter: 0,
      use: function (name, kind, n) { this.used[name] = { kind: kind, n: n || 0 }; },
      nextVar: function () { this.counter++; return 'zaehler' + this.counter; }
    };
  }

  function langValue(field, lang, helpers) {
    if (field && field.b) { return langReporter(field.b, lang, helpers); }
    return langLiteral(field, lang);
  }

  function langReporterRaw(node, lang, helpers) {
    var v2 = function (name) { return langValue(node.f[name], lang, helpers); };
    var q = function (name) { return langQuote(node.f[name] && node.f[name].v, lang); };
    switch (node.op) {
      case 'add': return '(' + v2('a') + ' + ' + v2('b') + ')';
      case 'sub': return '(' + v2('a') + ' - ' + v2('b') + ')';
      case 'mul': return '(' + v2('a') + ' * ' + v2('b') + ')';
      case 'div': return '(' + v2('a') + ' / ' + v2('b') + ')';
      case 'mod': return '(' + v2('a') + ' % ' + v2('b') + ')';
      case 'round': helpers.use('runde', 'double', 1); return 'runde(' + v2('a') + ')';
      case 'sqrt': helpers.use('wurzel', 'double', 1); return 'wurzel(' + v2('a') + ')';
      case 'abs': helpers.use('betrag', 'double', 1); return 'betrag(' + v2('a') + ')';
      case 'join': helpers.use('verbinde', 'String', 2); return 'verbinde(' + v2('a') + ', ' + v2('b') + ')';
      case 'letter_of': helpers.use('buchstabe', 'String', 2); return 'buchstabe(' + v2('n') + ', ' + v2('txt') + ')';
      case 'length_of': helpers.use('laenge', 'double', 1); return 'laenge(' + v2('txt') + ')';
      case 'random': helpers.use('zufallszahl', 'double', 2); return 'zufallszahl(' + v2('from') + ', ' + v2('to') + ')';
      case 'gt': return '(' + v2('a') + ' > ' + v2('b') + ')';
      case 'lt': return '(' + v2('a') + ' < ' + v2('b') + ')';
      case 'eq': return '(' + v2('a') + ' == ' + v2('b') + ')';
      case 'and': return '(' + v2('a') + (lang === 'py' ? ' and ' : ' && ') + v2('b') + ')';
      case 'or': return '(' + v2('a') + (lang === 'py' ? ' or ' : ' || ') + v2('b') + ')';
      case 'not': return lang === 'py' ? '(not ' + v2('a') + ')' : '(!' + v2('a') + ')';
      case 'var_value': helpers.use('wert', 'double', 1); return 'wert(' + q('name') + ')';
      case 'list_get': helpers.use('liste_eintrag', 'double', 2); return 'liste_eintrag(' + q('list') + ', ' + v2('n') + ')';
      case 'list_len': helpers.use('liste_laenge', 'double', 1); return 'liste_laenge(' + q('list') + ')';
      case 'list_has': helpers.use('liste_enthaelt', 'boolean', 2); return 'liste_enthaelt(' + q('list') + ', ' + v2('value') + ')';
      case 'xpos': helpers.use('x_position', 'double', 0); return 'x_position()';
      case 'ypos': helpers.use('y_position', 'double', 0); return 'y_position()';
      case 'direction': helpers.use('richtung', 'double', 0); return 'richtung()';
      case 'timer': helpers.use('stoppuhr', 'double', 0); return 'stoppuhr()';
      case 'volume': helpers.use('lautstaerke', 'double', 0); return 'lautstaerke()';
      case 'mouse_x': helpers.use('maus_x', 'double', 0); return 'maus_x()';
      case 'mouse_y': helpers.use('maus_y', 'double', 0); return 'maus_y()';
      case 'touching_edge': helpers.use('wird_rand_beruehrt', 'boolean', 0); return 'wird_rand_beruehrt()';
      case 'touching_sprite': helpers.use('beruehrt_figur', 'boolean', 1); return 'beruehrt_figur(' + q('name') + ')';
      case 'distance_to': helpers.use('abstand_zu', 'double', 1); return 'abstand_zu(' + q('name') + ')';
      case 'key_pressed': helpers.use('taste_gedrueckt', 'boolean', 1); return 'taste_gedrueckt(' + q('key') + ')';
      case 'mouse_down': helpers.use('maus_geklickt', 'boolean', 0); return 'maus_geklickt()';
      default:
        return (lang === 'py' ? '0  # Block ohne Textfassung: ' : '0  /* Block ohne Textfassung: */ ') + node.op;
    }
  }

  /* Platzhalter-Reporter sind in Python Instanzmethoden und brauchen
   * daher ein self. davor (#152); reine Ausdruecke starten mit '(' . */
  function langReporter(node, lang, helpers) {
    var r = langReporterRaw(node, lang, helpers);
    if (lang === 'py' && /^[a-z]/.test(r)) { return 'self.' + r; }
    return r;
  }

  function langCallLine(node, lang, helpers) {
    var def = LANG_CALLS[node.op];
    if (!def) {
      return null; /* Kontrollblock oder noch ohne Uebersetzung */
    }
    helpers.use(def[0], def[2], def[1].length);
    var args = [];
    var i;
    for (i = 0; i < def[1].length; i++) { args.push(langValue(node.f[def[1][i]], lang, helpers)); }
    var call = def[0] + '(' + args.join(', ') + ')';
    if (node.op === 'wait' && lang === 'py') { call = 'time.sleep(' + args[0] + ')'; }
    else if (lang === 'py') { call = 'self.' + call; }
    return call;
  }

  function langChain(head, lang, ind, out, helpers) {
    var n = head;
    var IND = '    ';
    while (n) {
      var call = langCallLine(n, lang, helpers);
      if (call !== null) {
        out.push(ind + call + (lang === 'py' ? '' : ';') + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
        n = n.next;
        continue;
      }
      switch (n.op) {
        case 'repeat':
          var zv = helpers.nextVar();
          out.push(ind + (lang === 'py'
            ? 'for ' + zv + ' in range(int(' + langValue(n.f.count, lang, helpers) + ')):'
            : 'for (int ' + zv + ' = 0; ' + zv + ' < ' + langValue(n.f.count, lang, helpers) + '; ' + zv + '++) {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          var before = out.length;
          langChain(n.sub[0], lang, ind + IND, out, helpers);
          if (lang === 'py' && out.length === before) { out.push(ind + IND + 'pass'); }
          if (lang !== 'py') { out.push(ind + '}'); }
          break;
        case 'forever':
          out.push(ind + (lang === 'py' ? 'while True:' : 'while (true) {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          var before = out.length;
          langChain(n.sub[0], lang, ind + IND, out, helpers);
          if (lang === 'py' && out.length === before) { out.push(ind + IND + 'pass'); }
          if (lang !== 'py') { out.push(ind + '}'); }
          break;
        case 'if_then':
          out.push(ind + (lang === 'py'
            ? 'if ' + langValue(n.f.cond, lang, helpers) + ':'
            : 'if (' + langValue(n.f.cond, lang, helpers) + ') {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          var before = out.length;
          langChain(n.sub[0], lang, ind + IND, out, helpers);
          if (lang === 'py' && out.length === before) { out.push(ind + IND + 'pass'); }
          if (lang !== 'py') { out.push(ind + '}'); }
          break;
        case 'if_else':
          out.push(ind + (lang === 'py'
            ? 'if ' + langValue(n.f.cond, lang, helpers) + ':'
            : 'if (' + langValue(n.f.cond, lang, helpers) + ') {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          langChain(n.sub[0], lang, ind + IND, out, helpers);
          out.push(ind + (lang === 'py' ? 'else:' : '} else {'));
          var before2 = out.length;
          langChain(n.sub[1], lang, ind + IND, out, helpers);
          if (lang === 'py' && out.length === before2) { out.push(ind + IND + 'pass'); }
          if (lang !== 'py') { out.push(ind + '}'); }
          break;
        case 'repeat_until':
          out.push(ind + (lang === 'py'
            ? 'while not ' + langValue(n.f.cond, lang, helpers) + ':'
            : 'while (!(' + langValue(n.f.cond, lang, helpers) + ')) {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          var before = out.length;
          langChain(n.sub[0], lang, ind + IND, out, helpers);
          if (lang === 'py' && out.length === before) { out.push(ind + IND + 'pass'); }
          if (lang !== 'py') { out.push(ind + '}'); }
          break;
        case 'wait_until':
          out.push(ind + (lang === 'py'
            ? 'while not ' + langValue(n.f.cond, lang, helpers) + ':'
            : 'while (!(' + langValue(n.f.cond, lang, helpers) + ')) {')
            + '  ' + (lang === 'py' ? '# ' : '// ') + blockText(n));
          if (lang === 'py') { out.push(ind + '    pass'); }
          else { out.push(ind + '}'); }
          break;
        default:
          out.push(ind + (lang === 'py' ? '# Block ohne Textfassung: ' + n.op : '/* Block ohne Textfassung: ' + n.op + ' */'));
      }
      n = n.next;
    }
  }

  function langHatName(op) {
    if (op === 'flag') { return 'wenn_flag_angeklickt'; }
    if (op === 'when_key') { return 'wenn_taste_gedrueckt'; }
    if (op === 'when_msg') { return 'wenn_nachricht_kommt'; }
    if (op === 'when_clicked') { return 'wenn_angeklickt'; }
    if (op === 'when_clone') { return 'wenn_ich_als_klon_gestartet_werde'; }
    if (op === 'proc_def') { return 'baustein'; }
    return 'skript';
  }

  function javaClassName(title) {
    var n = String(title || '').replace(/[^A-Za-z0-9]/g, '');
    if (n === '') { n = 'Werkstatt'; }
    return n.charAt(0).toUpperCase() + n.slice(1);
  }

  function langScriptsOf(list, lang, out, helpers, usedNames, ind) {
    /* Skripte einer Figur werden Methoden ihrer Klasse (#152): ind ist der
     * Einzug der Methodenkoepfe, der Methodenkoerper liegt 4 Spalten tiefer.
     * Rueckgabe: Liste der erzeugten Methoden mit ihrem Hut-Typ. */
    var made = [];
    var i, s, head;
    for (i = 0; i < list.length; i++) {
      s = list[i];
      head = s.top;
      if (!head) { continue; }
      var base = langHatName(head.op);
      var name = base;
      var k = 2;
      while (usedNames[name]) { name = base + '_' + k; k++; }
      usedNames[name] = true;
      made.push({ op: head.op, name: name });
      var hatText = blockText(head);
      out.push('');
      out.push(ind + (lang === 'py' ? '# ' : '// ') + 'Skript: ' + hatText);
      if (lang === 'py') {
        out.push(ind + 'def ' + name + '(self):');
        var before = out.length;
        langChain(head.next, 'py', ind + '    ', out, helpers);
        if (out.length === before) { out.push(ind + '    pass'); }
      } else {
        out.push(ind + 'void ' + name + '() {');
        langChain(head.next, 'java', ind + '    ', out, helpers);
        out.push(ind + '}');
      }
    }
    return made;
  }

  function usedNameIn(set, base) {
    var name = base;
    var k = 2;
    while (set[name]) { name = base + k; k++; }
    set[name] = true;
    return name;
  }

  var RESERVED_NAMES = {
    new: 1, int: 1, for: 1, if: 1, while: 1, return: 1, class: 1, void: 1,
    double: 1, boolean: 1, static: 1, final: 1, do: 1, else: 1, break: 1,
    continue: 1, this: 1, super: 1, import: 1, args: 1, def: 1, pass: 1,
    lambda: 1, None: 1, True: 1, False: 1, print: 1, time: 1, threading: 1,
    self: 1, not: 1, and: 1, or: 1, in: 1, is: 1
  };

  function varNameFor(cls) {
    var v = cls.charAt(0).toLowerCase() + cls.slice(1);
    if (RESERVED_NAMES[v]) { v = v + '_figur'; }
    return v;
  }

  function helperDecl(name, info, lang, ind) {
    /* Platzhalter-Methode der Basisklasse Figur (#152). */
    var i;
    var params = [];
    for (i = 0; i < info.n; i++) { params.push('p' + (i + 1)); }
    if (lang === 'py') {
      var ps = params.length > 0 ? ', ' + params.join(', ') : '';
      if (info.kind === 'void') { return [ind + 'def ' + name + '(self' + ps + '):', ind + '    pass']; }
      var ret = info.kind === 'boolean' ? 'False' : (info.kind === 'String' ? "''" : '0');
      return [ind + 'def ' + name + '(self' + ps + '):', ind + '    return ' + ret];
    }
    var jp = [];
    for (i = 0; i < params.length; i++) { jp.push('Object ' + params[i]); }
    if (info.kind === 'void') { return [ind + 'void ' + name + '(' + jp.join(', ') + ') { }']; }
    var jr = info.kind === 'boolean' ? 'false' : (info.kind === 'String' ? '""' : '0');
    return [ind + info.kind + ' ' + name + '(' + jp.join(', ') + ') { return ' + jr + '; }'];
  }

  function programToJava(prog, title) {
    var helpers = makeHelpers();
    var body = [];
    var mainL = [];
    var flagCalls = [];
    var classes = { Figur: true };
    var stageHas = prog.stage.scripts.length > 0;
    if (stageHas) { classes.Buehne = true; }
    var i, j;
    /* Zunaechst die Klassen je Figur generieren, damit die Platzhalter
     * der Basisklasse danach komplett bekannt sind (#152). */
    for (i = 0; i < prog.sprites.length; i++) {
      var sp = prog.sprites[i];
      var cls = usedNameIn(classes, javaClassName(sp.name));
      var varName = varNameFor(cls);
      var usedN = {};
      var part = [];
      var made = langScriptsOf(sp.scripts, 'java', part, helpers, usedN, '        ');
      body.push('');
      body.push('    /** ' + sp.name + ' (Kostuem: ' + (COSTUME_EMOJI[sp.costume] || sp.costume) + ') */');
      body.push('    static class ' + cls + ' extends Figur {');
      if (made.length === 0) { body.push('        /* Diese Figur hat noch keine Skripte. */'); }
      for (j = 0; j < part.length; j++) { body.push(part[j]); }
      body.push('    }');
      mainL.push('        ' + cls + ' ' + varName + ' = new ' + cls + '();');
      for (j = 0; j < made.length; j++) {
        if (made[j].op === 'flag') {
          flagCalls.push('        new Thread(() -> ' + varName + '.' + made[j].name + '()).start();');
        }
      }
    }
    if (stageHas) {
      var usedS = {};
      var partS = [];
      var madeS = langScriptsOf(prog.stage.scripts, 'java', partS, helpers, usedS, '        ');
      body.push('');
      body.push('    /** Die Buehne: Hintergrund und eigene Skripte. */');
      body.push('    static class Buehne extends Figur {');
      if (madeS.length === 0) { body.push('        /* Die Buehne hat noch keine Skripte. */'); }
      for (j = 0; j < partS.length; j++) { body.push(partS[j]); }
      body.push('    }');
      mainL.push('        Buehne buehne = new Buehne();');
      for (j = 0; j < madeS.length; j++) {
        if (madeS[j].op === 'flag') {
          flagCalls.push('        new Thread(() -> buehne.' + madeS[j].name + '()).start();');
        }
      }
    }
    var out = [];
    out.push('/* Automatisch aus der Block-Werkstatt erzeugt (#140, #152).');
    out.push(' * Objektorientiert: Jede Figur ist eine eigene Klasse, die von der');
    out.push(' * Basisklasse Figur erbt; die Buehne ist ebenfalls ein Objekt. Jede');
    out.push(' * Skriptspur wird eine Methode ihrer Klasse, jeder Aufruf entspricht');
    out.push(' * einem Block, der Blocktext steht daneben. main erzeugt die Objekte');
    out.push(' * und startet alle Skripte mit dem Fahnen-Hut parallel als Threads -');
    out.push(' * genau wie auf der Buehne. */');
    out.push('public class ' + javaClassName(title) + ' {');
    out.push('');
    out.push('    /** Gemeinsame Basis: alles, was jede Figur kann (#152).');
    out.push('     * Die Platzhalter zeigen, was auf der Buehne passieren wuerde. */');
    out.push('    static class Figur {');
    out.push('        double x = 240, y = 180;   /* Ursprung: oben links (#144) */');
    out.push('        int richtung = 90;         /* 90 Grad = nach rechts */');
    out.push('        boolean sichtbar = true;');
    out.push('');
    var names = Object.keys(helpers.used).sort();
    for (i = 0; i < names.length; i++) {
      var decl = helperDecl(names[i], helpers.used[names[i]], 'java', '        ');
      for (j = 0; j < decl.length; j++) { out.push(decl[j]); }
    }
    if (names.length === 0) {
      out.push('        /* Hier stehen die Platzhalter-Aktionen, sobald deine Figuren */');
      out.push('        /* Bloecke mit Aktionen und Reportern verwenden. */');
    }
    out.push('    }');
    for (i = 0; i < body.length; i++) { out.push(body[i]); }
    out.push('');
    out.push('    public static void main(String[] args) {');
    if (mainL.length === 0) {
      out.push('        /* Noch keine Figuren in diesem Projekt. */');
    }
    for (i = 0; i < mainL.length; i++) { out.push(mainL[i]); }
    out.push('');
    if (flagCalls.length > 0) {
      for (i = 0; i < flagCalls.length; i++) { out.push(flagCalls[i]); }
    } else {
      out.push('        /* Noch kein Skript mit dem Fahnen-Hut: main legt nur die Objekte an. */');
    }
    out.push('        /* Ereignis-Skripte (Taste, Nachricht, Klick) ruft die Laufzeit auf, */');
    out.push('        /* sobald das Ereignis eintritt. */');
    out.push('    }');
    out.push('}');
    return out.join('\n') + '\n';
  }

  function programToPython(prog, title) {
    var helpers = makeHelpers();
    var body = [];
    var mainL = [];
    var flagCalls = [];
    var classes = { Figur: true };
    var stageHas = prog.stage.scripts.length > 0;
    if (stageHas) { classes.Buehne = true; }
    var i, j;
    for (i = 0; i < prog.sprites.length; i++) {
      var sp = prog.sprites[i];
      var cls = usedNameIn(classes, javaClassName(sp.name));
      var varName = varNameFor(cls);
      var usedN = {};
      var part = [];
      var made = langScriptsOf(sp.scripts, 'py', part, helpers, usedN, '    ');
      body.push('class ' + cls + '(Figur):');
      body.push('    """' + sp.name + ' (Kostuem: ' + (COSTUME_EMOJI[sp.costume] || sp.costume) + ')"""');
      for (j = 0; j < part.length; j++) { body.push(part[j]); }
      body.push('');
      mainL.push(varName + ' = ' + cls + '()');
      for (j = 0; j < made.length; j++) {
        if (made[j].op === 'flag') {
          flagCalls.push('threading.Thread(target=' + varName + '.' + made[j].name + ').start()');
        }
      }
    }
    if (stageHas) {
      var usedS = {};
      var partS = [];
      var madeS = langScriptsOf(prog.stage.scripts, 'py', partS, helpers, usedS, '    ');
      body.push('class Buehne(Figur):');
      body.push('    """Die Buehne: Hintergrund und eigene Skripte."""');
      for (j = 0; j < partS.length; j++) { body.push(partS[j]); }
      body.push('');
      mainL.push('buehne = Buehne()');
      for (j = 0; j < madeS.length; j++) {
        if (madeS[j].op === 'flag') {
          flagCalls.push('threading.Thread(target=buehne.' + madeS[j].name + ').start()');
        }
      }
    }
    var out = [];
    out.push('# Automatisch aus der Block-Werkstatt erzeugt (#140, #152).');
    out.push('# Objektorientiert: Jede Figur ist eine eigene Klasse, die von der');
    out.push('# Basisklasse Figur erbt; die Buehne ist ebenfalls ein Objekt. Jede');
    out.push('# Skriptspur wird eine Methode ihrer Klasse, jeder Aufruf entspricht');
    out.push('# einem Block, der Blocktext steht daneben. Unten entstehen die');
    out.push('# Objekte, und alle Skripte mit dem Fahnen-Hut starten parallel als');
    out.push('# Threads - genau wie auf der Buehne.');
    out.push('import threading');
    out.push('import time');
    out.push('');
    out.push('class Figur:');
    out.push('    """Gemeinsame Basis: alles, was jede Figur kann (#152).');
    out.push('    Die Platzhalter zeigen, was auf der Buehne passieren wuerde."""');
    out.push('    x = 240.0   # Ursprung: oben links (#144)');
    out.push('    y = 180.0');
    out.push('    richtung = 90   # 90 Grad = nach rechts');
    out.push('    sichtbar = True');
    out.push('');
    var names = Object.keys(helpers.used).sort();
    for (i = 0; i < names.length; i++) {
      var decl = helperDecl(names[i], helpers.used[names[i]], 'py', '    ');
      for (j = 0; j < decl.length; j++) { out.push(decl[j]); }
    }
    if (names.length === 0) {
      out.push('    # Hier stehen die Platzhalter-Aktionen, sobald deine Figuren');
      out.push('    # Bloecke mit Aktionen und Reportern verwenden.');
      out.push('    pass');
    }
    out.push('');
    for (i = 0; i < body.length; i++) { out.push(body[i]); }
    if (mainL.length === 0) {
      out.push('# Noch keine Figuren in diesem Projekt.');
    }
    for (i = 0; i < mainL.length; i++) { out.push(mainL[i]); }
    if (mainL.length > 0) { out.push(''); }
    if (flagCalls.length > 0) {
      for (i = 0; i < flagCalls.length; i++) { out.push(flagCalls[i]); }
    } else {
      out.push('# Noch kein Skript mit dem Fahnen-Hut: es entstehen nur die Objekte.');
    }
    out.push('# Ereignis-Skripte (Taste, Nachricht, Klick) ruft die Laufzeit auf,');
    out.push('# sobald das Ereignis eintritt.');
    return out.join('\n') + '\n';
  }

  /* ---------- Laufzeit: alle Figuren und die Buehne parallel ---------- */
  function newRuntime(prog, opts) {
    opts = opts || {};
    var rt = {
      vars: {},
      lists: {},
      backdrop: prog.stage.backdrop || 'paper',
      _procCache: {},
      _startScripts: null,
      sprites: [],
      bubbleFor: {}, /* nicht genutzt, Sprechblasen liegen an der Figur */
      penCtx: opts.penCtx || null,
      timerStart: 0,
      keys: {},
      threads: [],
      now: 0,
      stopped: false,
      errors: [],
      rand: opts.rand || null,
      mouse: { x: 0, y: 0, down: false },
      volume: 70,
      sounds: [],
      order: [],
      api: {}
    };
    var i;
    for (i = 0; i < prog.vars.length; i++) { rt.vars[prog.vars[i]] = 0; }
    for (i = 0; i < (prog.lists || []).length; i++) { rt.lists[prog.lists[i]] = []; }
    for (i = 0; i < prog.sprites.length; i++) {
      var p = prog.sprites[i];
      rt.sprites.push({
        id: p.id, name: p.name, costume: String(p.costume),
        x: num(p.x), y: num(p.y), dir: num(p.dir),
        size: clamp(num(p.size), 10, 400), visible: p.visible !== false,
        bubble: null,
        effect: 0,
        pen: { down: false, color: '#1f2937', segments: [] }
      });
      rt.order.push(p.id);
    }

    function scriptTargets() {
      var lists = [];
      var i2, j2, src;
      lists.push({ scripts: prog.stage.scripts, tgt: null });
      for (i2 = 0; i2 < prog.sprites.length; i2++) {
        lists.push({ scripts: prog.sprites[i2].scripts, tgt: rt.sprites[i2] });
      }
      /* Klone (#132): ein Klon laeuft mit den Skripten seines Quell-Sprites. */
      for (i2 = 0; i2 < rt.sprites.length; i2++) {
        if (!rt.sprites[i2].isClone) { continue; }
        src = null;
        for (j2 = 0; j2 < prog.sprites.length; j2++) {
          if (prog.sprites[j2].id === rt.sprites[i2].srcId) { src = prog.sprites[j2]; break; }
        }
        if (src) { lists.push({ scripts: src.scripts, tgt: rt.sprites[i2] }); }
      }
      return lists;
    }

    /* Eigene Bloecke (#138): Kette eines Bausteins suchen und uebersetzen.
     * Buehne-Bausteine liegen an prog.stage, Figuren-Bausteine am Sprite
     * (Klone greifen auf die Bausteine ihres Quell-Sprites zu). */
    function findProcChain(tgt, procName) {
      var srcScripts = null;
      var i2, src, head;
      if (!tgt) {
        srcScripts = prog.stage.scripts;
      } else {
        var oid = tgt.srcId || tgt.id;
        for (i2 = 0; i2 < prog.sprites.length; i2++) {
          if (prog.sprites[i2].id === oid) { src = prog.sprites[i2]; break; }
        }
        srcScripts = src ? src.scripts : null;
      }
      if (!srcScripts) { return null; }
      for (i2 = 0; i2 < srcScripts.length; i2++) {
        head = srcScripts[i2].top;
        if (head && head.op === 'proc_def' && String(head.f.name.v) === procName) {
          var out = [];
          compileChain(head.next, { mem: 0 }, out);
          return out;
        }
      }
      return null;
    }

    function startScripts(op, keyName, now, onlyTgt) {
      rt.now = now;
      var lists = scriptTargets();
      var started = [];
      var l, i2, s, head;
      for (l = 0; l < lists.length; l++) {
        for (i2 = 0; i2 < lists[l].scripts.length; i2++) {
          s = lists[l].scripts[i2];
          head = s.top;
          if (!head || head.op !== op) { continue; }
          if (op === 'when_key' && String(head.f.key.v) !== keyName) { continue; }
          if (op === 'when_msg' && String(head.f.msg.v) !== keyName) { continue; }
          if (onlyTgt !== undefined && lists[l].tgt !== onlyTgt) { continue; }
          /* laufenden Thread dieses Skripts sauber neu starten */
          var keep = [];
          var t;
          for (t = 0; t < rt.threads.length; t++) {
            if (rt.threads[t].scriptId !== s.id) { keep.push(rt.threads[t]); }
          }
          rt.threads = keep;
          var ctx = { mem: 0 };
          var out = [];
          compileChain(head.next, ctx, out);
          var th = { scriptId: s.id, tgt: lists[l].tgt, instrs: out, ip: 0, mem: [], retStack: [], waitT: null, glide: null, msgThreads: null };
          rt.threads.push(th);
          started.push(th);
        }
      }
      return started;
    }

    function stepThread(th) {
      var guard = 0;
      /* Eigene Bloecke (#138): die innere Schleife laeuft die aktuelle
       * Kette; ist der Rueckkehrstapel gefuellt, wird nach Kette-Ende die
       * aufrufende Kette wiederhergestellt und weitergelaufen. */
      var stepRun = true;
      while (stepRun) {
      while (th.ip < th.instrs.length) {
        if (++guard > 100000) { rt.errors.push('Endlosschleife gestoppt: baue einen warte-Block ein.'); return 'dead'; }
        var ins = th.instrs[th.ip];
        try {
          switch (ins.t) {
            case 'setm': th.mem[ins.m] = 0; th.ip++; break;
            case 'ltm': th.mem[ins.m] = num(th.mem[ins.c]) < num(ins.fn(rt, th)) ? 1 : 0; th.ip++; break;
            case 'incm': th.mem[ins.m] = num(th.mem[ins.m]) + 1; th.ip++; break;
            case 'jzm': th.ip = !th.mem[ins.m] ? ins.to : th.ip + 1; break;
            case 'jmpf': th.ip = !toBool(ins.fn(rt, th)) ? ins.to : th.ip + 1; break;
            case 'jmp': th.ip = ins.to; break;
            case 'yield': th.ip++; return 'alive';
            case 'wait':
              if (th.waitT === null) { th.waitT = rt.now + num(ins.fn(rt, th)) * 1000; }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              th.ip++;
              break;
            case 'sayfor':
              if (th.waitT === null) {
                if (th.tgt) {
                  th.tgt.bubble = { text: String(ins.fn(rt, th)), until: rt.now + num(ins.fn2(rt, th)) * 1000 };
                }
                th.waitT = rt.now + num(ins.fn2(rt, th)) * 1000;
              }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              if (th.tgt && th.tgt.bubble && th.tgt.bubble.until !== null && rt.now >= th.tgt.bubble.until) { th.tgt.bubble = null; }
              th.ip++;
              break;
            /* Denkblase (#135): wie sage-fuer, nur mit Punkt-Stil. */
            case 'thinkfor':
              if (th.waitT === null) {
                if (th.tgt) {
                  th.tgt.bubble = { text: String(ins.fn(rt, th)), until: rt.now + num(ins.fn2(rt, th)) * 1000, think: true };
                }
                th.waitT = rt.now + num(ins.fn2(rt, th)) * 1000;
              }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              if (th.tgt && th.tgt.bubble && th.tgt.bubble.until !== null && rt.now >= th.tgt.bubble.until) { th.tgt.bubble = null; }
              th.ip++;
              break;
            /* Klang (#130): Ton starten und warten. */
            case 'note':
              if (th.waitT === null) {
                playTone(rt, String(ins.fn(rt, th)), num(ins.fn2(rt, th)) * 1000);
                th.waitT = rt.now + num(ins.fn2(rt, th)) * 1000;
              }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              th.ip++;
              break;
            /* Bewegung (#134): in jedem Bild ein Stueck zum Ziel. */
            case 'glide':
              if (!th.glide) {
                var gsecs = num(ins.fn(rt, th));
                th.glide = {
                  sx: th.tgt ? th.tgt.x : 0, sy: th.tgt ? th.tgt.y : 0,
                  tx: num(ins.fx(rt, th)), ty: num(ins.fy(rt, th)),
                  start: rt.now, dur: Math.max(16, gsecs * 1000)
                };
              }
              var gg = th.glide;
              var gfrac = clamp((rt.now - gg.start) / gg.dur, 0, 1);
              if (th.tgt) { moveBy(rt, th.tgt, (gg.sx + (gg.tx - gg.sx) * gfrac) - th.tgt.x, (gg.sy + (gg.ty - gg.sy) * gfrac) - th.tgt.y); }
              if (gfrac >= 1) { th.glide = null; th.ip++; break; }
              return 'alive';
            /* Nachrichten (#131): alle angestossenen Empfaenger abwarten. */
            case 'bcastwait':
              if (!th.msgThreads) {
                th.msgThreads = startScripts('when_msg', String(ins.fn(rt, th)), rt.now) || [];
              }
              var aliveM = 0;
              var it;
              for (it = 0; it < th.msgThreads.length; it++) {
                if (rt.threads.indexOf(th.msgThreads[it]) !== -1) { aliveM++; }
              }
              if (aliveM === 0) { th.msgThreads = null; th.ip++; break; }
              return 'alive';
            /* Eigene Bloecke (#138): in die Baustein-Kette springen, der
             * Ruecksprung landet auf dem Rueckkehrstapel des Threads. */
            case 'callproc': {
              var pName = String(ins.name(rt, th));
              var pKey = (th.tgt ? (th.tgt.srcId || th.tgt.id) : 'buehne') + '::' + pName;
              var chain = rt._procCache[pKey];
              if (!chain) {
                chain = findProcChain(th.tgt, pName);
                if (!chain) { throw BlkErr('Unbekannter Baustein: ' + pName + '.'); }
                rt._procCache[pKey] = chain;
              }
              if (th.retStack.length >= 64) { throw BlkErr('Zu viele verschachtelte Bausteine.'); }
              th.retStack.push({ instrs: th.instrs, ip: th.ip + 1, mem: th.mem });
              th.instrs = chain;
              th.ip = 0;
              th.mem = [];
              break;
            }
            case 'call':
              if (applyCall(rt, th, ins.op, ins.A) === 'kill') { return 'dead'; }
              if (rt.stopped) { return 'dead'; }
              th.ip++;
              break;
            default: th.ip++;
          }
        } catch (e) {
          if (e && e.blkErr) { rt.errors.push(e.msg); return 'dead'; }
          throw e;
        }
      }
      /* Kette beendet: bei offenem Ruecksprung zurueck zum Aufrufer (#138). */
      if (th.retStack.length) {
        var rRet = th.retStack.pop();
        th.instrs = rRet.instrs;
        th.ip = rRet.ip;
        th.mem = rRet.mem;
        continue;
      }
      stepRun = false;
      }
      return 'dead';
    }

    rt._startScripts = startScripts;
    rt.api.flag = function (now) {
      rt.stopped = false;
      rt.timerStart = now;
      startScripts('flag', null, now);
    };
    rt.api.press = function (keyName, now) { startScripts('when_key', keyName, now); };
    /* Nachrichten (#131): Senden startet alle Empfaenger-Skripte. */
    rt.api.broadcast = function (msgName, now) { return startScripts('when_msg', msgName, now); };
    /* Sensoren (#133): Zeiger-Position und -Druck von der Buehne. */
    rt.api.setMouse = function (x, y, down) {
      if (typeof x === 'number') { rt.mouse.x = x; }
      if (typeof y === 'number') { rt.mouse.y = y; }
      if (typeof down === 'boolean') { rt.mouse.down = down; }
    };
    /* Klick-Ereignis (#133): trifft die oberste Figur unter dem Zeiger,
     * sonst die Buehne selbst. */
    rt.api.click = function (now) {
      var oi, sp2, r2;
      for (oi = rt.order.length - 1; oi >= 0; oi--) {
        sp2 = findSpriteById(rt, rt.order[oi]);
        if (!sp2 || !sp2.visible) { continue; }
        r2 = 24 * Math.max(10, sp2.size) / 100 + 6;
        if (spriteDist(rt.mouse.x - sp2.x, rt.mouse.y - sp2.y) <= r2) {
          return startScripts('when_clicked', null, now, sp2);
        }
      }
      return startScripts('when_clicked', null, now, null);
    };
    rt.api.frame = function (now) {
      rt.now = now;
      var i3, i4;
      for (i3 = 0; i3 < rt.sprites.length; i3++) {
        var sp = rt.sprites[i3];
        if (sp.bubble && sp.bubble.until !== null && now > sp.bubble.until) { sp.bubble = null; }
      }
      /* Abgelaufene Toene stoppen (#130), in-place: die exportierte
       * Liste bleibt dieselbe Instanz. */
      for (i3 = rt.sounds.length - 1; i3 >= 0; i3--) {
        if (rt.sounds[i3].until !== null && now > rt.sounds[i3].until) {
          rt.sounds[i3].stop();
          rt.sounds.splice(i3, 1);
        }
      }
      /* Snapshot + Wellen: startScripts darf waehrend des Frames neue
       * Threads anstossen (Nachrichten, Klicks); jede neue Welle laeuft
       * im selben Bild mit, bis nichts mehr nachkommt (max. 8 Wellen). */
      var queue = rt.threads.slice();
      var alive = [];
      var rounds = 0;
      while (queue.length > 0 && rounds < 8) {
        var wave = queue;
        queue = [];
        var w;
        for (w = 0; w < wave.length; w++) {
          if (stepThread(wave[w]) === 'alive' && rt.threads.indexOf(wave[w]) !== -1) {
            alive.push(wave[w]);
          }
        }
        for (w = 0; w < rt.threads.length; w++) {
          var nt = rt.threads[w];
          if (alive.indexOf(nt) === -1 && queue.indexOf(nt) === -1 && wave.indexOf(nt) === -1) {
            queue.push(nt);
          }
        }
        rounds++;
      }
      rt.threads = alive;
    };
    rt.api.stopAll = function () {
      rt.stopped = true;
      rt.threads = [];
      stopAllSounds(rt);
      var i5;
      for (i5 = 0; i5 < rt.sprites.length; i5++) { rt.sprites[i5].bubble = null; }
    };
    rt.api.running = function () { return rt.threads.length > 0; };
    return {
      flag: rt.api.flag, press: rt.api.press, frame: rt.api.frame,
      stopAll: rt.api.stopAll, running: rt.api.running,
      broadcast: rt.api.broadcast, setMouse: rt.api.setMouse, click: rt.api.click,
      vars: rt.vars, sprites: rt.sprites, keys: rt.keys,
      lists: rt.lists,
      backdrop: function () { return rt.backdrop; },
      mouse: rt.mouse, sounds: rt.sounds, order: rt.order,
      volume: function () { return rt.volume; },
      errors: rt.errors,
      bubble: function () {
        var i6;
        for (i6 = 0; i6 < rt.sprites.length; i6++) {
          if (rt.sprites[i6].bubble) { return rt.sprites[i6].bubble; }
        }
        return null;
      },
      threads: function () { return rt.threads.length; }
    };
  }

  /* ---------- Editor-Oberflaeche ---------- */
  function attach(doc) {
    var root = doc.getElementById('blk-ide');
    if (!root) { return null; }
    var els = {
      script: doc.getElementById('blk-script'),
      scriptwrap: doc.getElementById('blk-scriptwrap'),
      pal: doc.getElementById('blk-pal'),
      cats: doc.getElementById('blk-cats'),
      help: doc.getElementById('blk-help'),
      flagBtn: doc.getElementById('blk-flag'),
      stopBtn: doc.getElementById('blk-stop'),
      codeBtn: doc.getElementById('blk-code'),
      runInfo: doc.getElementById('blk-runinfo'),
      saveInfo: doc.getElementById('blk-saveinfo'),
      varAdd: doc.getElementById('blk-var-add'),
      listAdd: doc.getElementById('blk-list-add'),
      procAdd: doc.getElementById('blk-proc-add'),
      templatesBtn: doc.getElementById('blk-templates'),
      fullBtn: doc.getElementById('blk-full'),
      sprites: doc.getElementById('blk-sprites'),
      stage: doc.getElementById('blk-stage'),
      bgCanvas: doc.getElementById('blk-bg'),
      penCanvas: doc.getElementById('blk-pen'),
      ctrCanvas: doc.getElementById('blk-ctr'),
      saveForm: doc.getElementById('blocks-save-form'),
      jsonTa: doc.getElementById('blocks-json'),
      mode: doc.getElementById('blk-mode'),
      modeLearn: doc.getElementById('blk-mode-learn'),
      modeExpert: doc.getElementById('blk-mode-expert'),
      consoleBox: doc.getElementById('blk-console'),
      consoleLog: doc.getElementById('blk-console-log'),
      consoleIn: doc.getElementById('blk-console-in'),
      consoleSend: doc.getElementById('blk-console-send')
    };
    if (!els.script || !els.pal || !els.jsonTa) { return null; }

    var prog = parseProgram(els.jsonTa.value);
    var activeCat = 'events';
    /* Ziel: die Figur, deren Skripte gerade bearbeitet werden - oder die
     * Buehne (target === null). */
    var target = prog.sprites.length > 0 ? prog.sprites[0] : null;
    var rt = null;
    var loopOn = false;
    var drag = null;
    var dropHint = null;
    var pickerOpen = false;
    /* Lernmodus/Expertenmodus (#161): Standard ist der Lernmodus. Der
     * Modus kommt vom Nutzerkonto (data-blk-mode am Werkstatt-Geruest);
     * nur als Fallback dient der Browser-Speicher (#151). Der Experten-
     * modus zeigt die erweiterte Palette, den Code-Knopf und die Konsole
     * (#152). */
    var expertMode = false;
    var presetMode = root.getAttribute('data-blk-mode');
    if (presetMode === 'expert') { expertMode = true; }
    else if (presetMode !== 'learn') {
      try {
        if (typeof window !== 'undefined' && window.localStorage) {
          expertMode = window.localStorage.getItem('blk.expert') === '1';
        }
      } catch (eStore) { expertMode = false; }
    }

    function applyMode() {
      var isLearn = !expertMode;
      /* #161: Segment-Schalter mit zwei beschrifteten Haelften, die
       * aktive Haelfte ist farblich hervorgehoben (aria-pressed). */
      if (els.modeLearn) {
        els.modeLearn.setAttribute('aria-pressed', isLearn ? 'true' : 'false');
        if (isLearn) { els.modeLearn.classList.add('active'); }
        else { els.modeLearn.classList.remove('active'); }
      }
      if (els.modeExpert) {
        els.modeExpert.setAttribute('aria-pressed', expertMode ? 'true' : 'false');
        if (expertMode) { els.modeExpert.classList.add('active'); }
        else { els.modeExpert.classList.remove('active'); }
      }
      if (els.mode) {
        els.mode.setAttribute('data-active', expertMode ? 'expert' : 'learn');
      }
      if (els.codeBtn) {
        if (expertMode) { els.codeBtn.removeAttribute('hidden'); }
        else { els.codeBtn.setAttribute('hidden', 'hidden'); }
      }
      if (els.consoleBox) {
        if (expertMode) { els.consoleBox.removeAttribute('hidden'); }
        else { els.consoleBox.setAttribute('hidden', 'hidden'); }
      }
      /* #162: Der Knopf fuer neue Variablen gehoert zum Expertenmodus. */
      if (els.varAdd) {
        if (expertMode) { els.varAdd.removeAttribute('hidden'); }
        else { els.varAdd.setAttribute('hidden', 'hidden'); }
      }
      /* #162/#137: Der Knopf fuer neue Listen ebenso, eigene Bloecke (#138)
       * duerfen auch im Lernmodus angelegt werden. */
      if (els.listAdd) {
        if (expertMode) { els.listAdd.removeAttribute('hidden'); }
        else { els.listAdd.setAttribute('hidden', 'hidden'); }
      }
      /* #162: Die Knopfleiste gehoert zum Modus, sonst bliebe die
       * Variablen-Kategorie beim Umschalten stehen oder fehlte. */
      renderCats();
      renderPalette();
    }

    function isStage() { return target === null; }
    function targetScripts() { return isStage() ? prog.stage.scripts : target.scripts; }
    /* Eigene Bloecke (#138): der Ziel-Bausteinvorrat, Buehne oder Figur. */
    function procsOfTarget() {
      if (isStage()) { return prog.stage.procs || []; }
      return (target && target.procs) || [];
    }

    function el(tag, cls, text) {
      var e = doc.createElement(tag);
      if (cls) { e.className = cls; }
      if (text !== undefined) { e.textContent = text; }
      return e;
    }

    function colorOf(node) { return catColor(BLOCKS[node.op].cat); }

    function renderField(node, name, spec, opts) {
      var fld = node.f[name];
      var wrap;
      if (spec.type === 'select') {
        wrap = el('select', 'blk-sel');
        var options = optionSource(spec.src, prog.vars, prog.sprites, prog.lists, procsOfTarget());
        var found = false;
        var i;
        for (i = 0; i < options.length; i++) {
          var o = el('option');
          o.setAttribute('value', options[i].v);
          o.textContent = options[i].l;
          if (String(fld.v) === options[i].v) { o.setAttribute('selected', 'selected'); found = true; }
          wrap.appendChild(o);
        }
        if (!found && options.length > 0) { fld.v = options[0].v; }
        if (!opts.staticMode) {
          (function (sel, fldRef) {
            sel.addEventListener('change', function (ev) {
              fldRef.v = ev.target.value;
              autosaveSoon();
            });
          })(wrap, fld);
        } else { wrap.setAttribute('disabled', 'disabled'); }
        return wrap;
      }
      if (spec.type === 'bool') {
        wrap = el('span', 'blk-bslot');
        wrap.setAttribute('data-f', name);
        if (fld.b) { wrap.appendChild(renderBlock(fld.b, opts)); }
        return wrap;
      }
      /* slot */
      wrap = el('span', 'blk-slot');
      wrap.setAttribute('data-f', name);
      if (fld.b) {
        wrap.appendChild(renderBlock(fld.b, opts));
      } else {
        var inp = el('input', 'blk-slot-in');
        inp.setAttribute('value', String(fld.v));
        if (!opts.staticMode) {
          (function (input, fldRef) {
            input.addEventListener('input', function (ev) {
              fldRef.v = ev.target.value;
              autosaveSoon();
            });
          })(inp, fld);
        } else { inp.setAttribute('disabled', 'disabled'); }
        wrap.appendChild(inp);
      }
      return wrap;
    }

    function renderBlock(node, opts) {
      opts = opts || {};
      var spec = BLOCKS[node.op];
      var b = el('div', 'blk blk-' + spec.kind);
      b.setAttribute('data-id', node.id);
      b.setAttribute('data-op', node.op);
      b.style['--c'] = colorOf(node);
      b.style.background = colorOf(node);
      /* #153: Der gezogene Block bleibt sichtbar, nur abgeblendet. */
      if (node._drag && b.classList && b.classList.add) { b.classList.add('blk-dragging'); }
      var row = el('div', 'blk-row');
      var i, part;
      for (i = 0; i < spec.parts.length; i++) {
        part = spec.parts[i];
        if (typeof part === 'string') {
          row.appendChild(el('span', 'blk-lbl', part));
        } else if (part && part.in) {
          row.appendChild(renderField(node, part.in, spec.inputs[part.in], opts));
        }
      }
      if (!opts.staticMode && !opts.palette) {
        var x = el('button', 'blk-x', '✕');
        x.setAttribute('title', 'Block mit allem darunter löschen');
        x.setAttribute('type', 'button');
        (function (btn, id) {
          btn.addEventListener('click', function (ev) {
            if (ev.stopPropagation) { ev.stopPropagation(); }
            deleteBlockTree(id);
          });
        })(x, node.id);
        row.appendChild(x);
      }
      b.appendChild(row);
      if (spec.kind === 'c' || spec.kind === 'c2') {
        var sub0 = el('div', 'blk-sub');
        sub0.setAttribute('data-sub', '0');
        b.appendChild(sub0);
        appendChain(sub0, node.sub[0], opts);
        if (spec.kind === 'c2') {
          b.appendChild(el('div', 'blk-mid', 'sonst'));
          var sub1 = el('div', 'blk-sub');
          sub1.setAttribute('data-sub', '1');
          b.appendChild(sub1);
          appendChain(sub1, node.sub[1], opts);
        }
        b.appendChild(el('div', 'blk-cap'));
      }
      return b;
    }

    function appendChain(parent, head, opts) {
      var n = head;
      while (n) {
        parent.appendChild(renderBlock(n, opts));
        n = n.next;
      }
    }

    /* ---------- Skriptflaeche: immer die Skripte des Ziels ---------- */
    function renderScripts() {
      while (els.script.children.length > 0) { els.script.removeChild(els.script.children[0]); }
      var list = targetScripts();
      var i;
      for (i = 0; i < list.length; i++) {
        var s = list[i];
        var chain = el('div', 'blk-chain');
        chain.setAttribute('data-script', s.id);
        chain.style.left = s.x + 'px';
        chain.style.top = s.y + 'px';
        appendChain(chain, s.top, {});
        els.script.appendChild(chain);
      }
    }

    /* ---------- Palette: Kategorien je nach Ziel (#126) ---------- */
    function catAllowed(catId) {
      var i;
      /* Buehnen-Kategorie (#139): nur im Ziel Buehne sichtbar. */
      for (i = 0; i < CATS.length; i++) {
        if (CATS[i].id === catId && CATS[i].stageOnly) { return isStage(); }
      }
      if (isStage() && STAGE_HIDDEN_CATS[catId]) { return false; }
      /* #162: Kategorien fuer Fortgeschrittene (Variablen, Listen) stehen
       * nur im Expertenmodus auf den Knopfleisten. */
      if (!expertMode) {
        for (i = 0; i < CATS.length; i++) {
          if (CATS[i].id === catId) { return !CATS[i].pro; }
        }
      }
      return true;
    }

    function renderCats() {
      while (els.cats.children.length > 0) { els.cats.removeChild(els.cats.children[0]); }
      var i;
      for (i = 0; i < CATS.length; i++) {
        if (!catAllowed(CATS[i].id)) { continue; }
        (function (cat) {
          var btn = el('button', 'blk-cat-btn' + (cat.id === activeCat ? ' active' : ''), cat.label);
          btn.setAttribute('type', 'button');
          /* #150: Jeder Kategorie-Knopf traegt die Farbe der Bloecke
           * dahinter, damit die Zuordnung sofort ins Auge faellt. */
          btn.style.background = cat.color;
          btn.style.borderColor = cat.color;
          btn.style['--c'] = cat.color;
          btn.addEventListener('click', function () {
            activeCat = cat.id;
            renderCats();
            renderPalette();
          });
          els.cats.appendChild(btn);
        })(CATS[i]);
      }
    }

    function renderPalette() {
      while (els.pal.children.length > 0) { els.pal.removeChild(els.pal.children[0]); }
      var ci, catSpec = null;
      for (ci = 0; ci < CATS.length; ci++) {
        if (CATS[ci].id === activeCat) { catSpec = CATS[ci]; break; }
      }
      /* #162: Ist die gewaehlte Kategorie im Lernmodus nicht zugelassen,
       * springt die Palette auf die Ereignisse zurueck. */
      if (catSpec && !catAllowed(activeCat)) {
        activeCat = 'events';
        renderCats();
      }
      var op;
      for (op in BLOCKS) {
        if (!Object.prototype.hasOwnProperty.call(BLOCKS, op)) { continue; }
        if (BLOCKS[op].cat !== activeCat) { continue; }
        /* #151: Im Lernmodus bleibt der Basis-Vorrat, der Expertenmodus
         * zeigt zusaetzlich die erweiterten Bloecke. Im Skript bereits
         * liegende Expertenbloecke bleiben sichtbar und lauffaehig. */
        if (!expertMode && BLOCKS[op].pro) { continue; }
        var demo = newBlock(op);
        var b = renderBlock(demo, { palette: true });
        b.setAttribute('data-pal-op', op);
        els.pal.appendChild(b);
      }
      /* Meine Bloecke (#138): zusaetzlich ein Aufruf-Knopf je Baustein. */
      if (activeCat === 'myblocks') {
        var procsHere = procsOfTarget();
        var pi;
        for (pi = 0; pi < procsHere.length; pi++) {
          var pdemo = newBlock('proc_call');
          pdemo.f.name.v = procsHere[pi];
          var pb = renderBlock(pdemo, { palette: true });
          pb.setAttribute('data-pal-op', 'proc_call');
          pb.setAttribute('data-pal-proc', procsHere[pi]);
          els.pal.appendChild(pb);
        }
      }
    }

    /* ---------- Befehlsuebersicht: automatisch aus der Registry (#117) ---------- */
    function renderHelp() {
      while (els.help.children.length > 0) { els.help.removeChild(els.help.children[0]); }
      var i, op;
      for (i = 0; i < CATS.length; i++) {
        var cat = CATS[i];
        var det = el('details', 'blk-help-cat');
        det.appendChild(el('summary', 'blk-help-sum', cat.label));
        var list = el('div', 'blk-help-list');
        for (op in BLOCKS) {
          if (!Object.prototype.hasOwnProperty.call(BLOCKS, op)) { continue; }
          if (BLOCKS[op].cat !== cat.id) { continue; }
          var row = el('div', 'blk-help-row');
          row.appendChild(renderBlock(newBlock(op), { staticMode: true }));
          row.appendChild(el('p', 'blk-help-txt', BLOCKS[op].help));
          list.appendChild(row);
        }
        det.appendChild(list);
        els.help.appendChild(det);
      }
    }

    /* ---------- Figurenliste (#125) und Buehne als Ziel (#126) ---------- */
    function renderSprites() {
      if (!els.sprites) { return; }
      while (els.sprites.children.length > 0) { els.sprites.removeChild(els.sprites.children[0]); }
      var stageCard = el('button', 'blk-spr-card' + (isStage() ? ' active' : ''));
      stageCard.setAttribute('type', 'button');
      stageCard.setAttribute('data-target', 'stage');
      stageCard.appendChild(el('span', 'blk-spr-ico', '🎬'));
      stageCard.appendChild(el('span', 'blk-spr-name', 'Bühne'));
      stageCard.addEventListener('click', function () { selectTarget(null); });
      els.sprites.appendChild(stageCard);
      var i;
      for (i = 0; i < prog.sprites.length; i++) {
        (function (sp, idx) {
          var card = el('div', 'blk-spr-card' + (target === sp ? ' active' : ''));
          card.setAttribute('data-sprite', sp.id);
          var pick = el('button', 'blk-spr-pick');
          pick.setAttribute('type', 'button');
          pick.appendChild(el('span', 'blk-spr-ico', COSTUME_EMOJI[sp.costume] || '🤖'));
          pick.appendChild(el('span', 'blk-spr-name', sp.name));
          pick.addEventListener('click', function () { selectTarget(sp); });
          card.appendChild(pick);
          var del = el('button', 'blk-spr-del', '✕');
          del.setAttribute('type', 'button');
          del.setAttribute('title', 'Diese Figur löschen');
          del.addEventListener('click', function (ev) {
            if (ev.stopPropagation) { ev.stopPropagation(); }
            deleteSprite(sp);
          });
          card.appendChild(del);
          els.sprites.appendChild(card);
        })(prog.sprites[i], i);
      }
      if (pickerOpen) {
        var picker = el('div', 'blk-spr-picker');
        var c;
        for (c = 0; c < COSTUME_OPTIONS.length; c++) {
          (function (opt) {
            var b = el('button', 'blk-spr-opt', COSTUME_EMOJI[opt.v] + ' ' + opt.l);
            b.setAttribute('type', 'button');
            b.addEventListener('click', function () {
              pickerOpen = false;
              addSprite(opt.v);
            });
            picker.appendChild(b);
          })(COSTUME_OPTIONS[c]);
        }
        els.sprites.appendChild(picker);
      } else {
        var add = el('button', 'blk-spr-add', '＋ Figur');
        add.setAttribute('type', 'button');
        add.setAttribute('title', 'Neue Figur anlegen');
        add.addEventListener('click', function () {
          pickerOpen = !pickerOpen;
          renderSprites();
        });
        els.sprites.appendChild(add);
      }
    }

    function addSprite(costume) {
      if (prog.sprites.length >= LIMITS.sprites) { return null; }
      var n = prog.sprites.length + 1;
      var sp = defaultSprite(costume, 'Figur ' + n);
      /* etwas versetzt starten, damit neue Figuren nicht deckungsgleich liegen */
      sp.x = ((n % 3) - 1) * 60;
      sp.y = ((n % 3) - 1) * 30;
      prog.sprites.push(sp);
      selectTarget(sp);
      autosaveSoon();
      return sp;
    }

    function deleteSprite(sp) {
      var i;
      for (i = 0; i < prog.sprites.length; i++) {
        if (prog.sprites[i] === sp) { prog.sprites.splice(i, 1); break; }
      }
      if (target === sp) { selectTarget(prog.sprites.length > 0 ? prog.sprites[0] : null); }
      else { renderSprites(); drawStage(); }
      autosaveSoon();
    }

    function selectTarget(sp) {
      target = sp || null;
      if (!catAllowed(activeCat)) { activeCat = 'events'; }
      pickerOpen = false;
      renderSprites();
      renderCats();
      renderPalette();
      renderScripts();
      drawStage();
    }

    /* ---------- Bloecke suchen / loeschen ---------- */
    function findBlockPos(id) {
      var result = null;
      var list = targetScripts();
      var i;
      function scanChain(head, script, parentKind, parentNode, subIdx) {
        var n = head;
        while (n && !result) {
          if (n.id === id) {
            result = { script: script, parentKind: parentKind, parentNode: parentNode, subIdx: subIdx, node: n };
            return;
          }
          var k;
          for (k = 0; k < (n.sub ? n.sub.length : 0); k++) {
            scanChain(n.sub[k], script, 'sub', n, k);
            if (result) { return; }
          }
          var fk;
          for (fk in n.f) {
            if (Object.prototype.hasOwnProperty.call(n.f, fk) && n.f[fk].b) {
              scanChain(n.f[fk].b, script, 'field', n, fk);
              if (result) { return; }
            }
          }
          /* Weiter in der Kette: der Nachfolger haengt am aktuellen Block
           * ('next'), nicht mehr am Kettenanfang (#129: vorher trug jeder
           * Block unter dem Hut parentKind 'top' und loeschte beim
           * Wegnehmen den ganzen Stapel). */
          var prev = n;
          n = n.next;
          if (n) { parentKind = 'next'; parentNode = prev; }
        }
      }
      for (i = 0; i < list.length; i++) {
        scanChain(list[i].top, list[i], 'top', null, 0);
        if (result) { return result; }
      }
      return null;
    }

    function deleteBlockTree(id) {
      var pos = findBlockPos(id);
      if (!pos) { return; }
      if (pos.parentKind === 'top') { removeScript(pos.script); return; }
      if (pos.parentKind === 'next') { pos.parentNode.next = null; }
      if (pos.parentKind === 'sub') { pos.parentNode.sub[pos.subIdx] = null; }
      if (pos.parentKind === 'field') {
        var spec = BLOCKS[pos.parentNode.op];
        pos.parentNode.f[pos.subIdx] = { v: fieldDefault(spec.inputs[pos.subIdx]) };
      }
      renderScripts();
      autosaveSoon();
    }

    function removeScript(s) {
      var list = targetScripts();
      var i;
      for (i = 0; i < list.length; i++) {
        if (list[i] === s) { list.splice(i, 1); break; }
      }
      renderScripts();
      autosaveSoon();
    }

    function removeScriptQuiet(s) {
      var list = targetScripts();
      var i;
      for (i = 0; i < list.length; i++) {
        if (list[i] === s) { list.splice(i, 1); return; }
      }
    }

    /* ---------- Drag & Drop (#122, #123) ----------
     * Ein Block wird erst nach einer kleinen Bewegung (Schwellwert) aus
     * dem Skript geloest. Waehrend des Ziehens folgt ein Geister-Element
     * dem Zeiger und eine Drop-Vorschau zeigt das Ziel. Bricht das Ziehen
     * ab (pointercancel, zweiter Finger, Drop daneben), wandert der Block
     * an seinen alten Platz zurueck - nichts geht mehr verloren. Nur das
     * bewusste Zurueckziehen auf die Palette loescht. */
    function walkUp(target, pred) {
      var n = target;
      while (n && n !== doc.body) {
        if (pred(n)) { return n; }
        n = n.parent || n.parentNode;
      }
      return null;
    }

    var DRAG_THRESHOLD = 6;

    function onPointerDown(ev) {
      if (drag) { return; } /* zweiter Finger: ignorieren, nichts kaputt machen */
      var targetEl = ev.target;
      if (!targetEl) { return; }
      var inField = walkUp(targetEl, function (n) {
        return n.tagName === 'INPUT' || n.tagName === 'SELECT' || n.tagName === 'BUTTON';
      });
      if (inField) { return; }
      var bel = walkUp(targetEl, function (n) {
        return n.classList && n.classList.contains('blk');
      });
      if (!bel) { return; }
      if (ev.preventDefault) { ev.preventDefault(); }
      var grab = grabOffset(ev, bel);
      drag = {
        phase: 'pending', startX: ev.clientX, startY: ev.clientY,
        grabDX: grab.dx, grabDY: grab.dy,
        pointerId: ev.pointerId !== undefined ? ev.pointerId : null,
        ghost: null, node: null, origin: null, restore: null, overPal: false, drop: null
      };
      var op = bel.getAttribute('data-pal-op');
      if (op) {
        drag.origin = 'palette';
        drag.palOp = op;
      } else {
        var id = bel.getAttribute('data-id');
        if (!id) { drag = null; return; }
        drag.origin = 'script';
        drag.blockId = id;
      }
      doc.addEventListener('pointermove', onPointerMove);
      doc.addEventListener('pointerup', onPointerUp);
      doc.addEventListener('pointercancel', onPointerCancel);
    }

    function grabOffset(ev, bel) {
      var r = rectOf(bel);
      if (!r) { return { dx: 14, dy: 10 }; }
      return { dx: clamp(ev.clientX - r.left, 0, r.width), dy: clamp(ev.clientY - r.top, 0, r.height) };
    }

    function startDrag(ev) {
      drag.phase = 'dragging';
      if (drag.origin === 'palette') {
        drag.node = newBlock(drag.palOp);
      } else {
        var pos = findBlockPos(drag.blockId);
        if (!pos) { drag = null; return; }
        /* #153: Der gezogene Block samt Teilbaum bleibt waehrend des Ziehens
         * an seinem Platz sichtbar (nur abgeblendet markiert). Frueher wurde
         * er sofort aus dem Modell geloest, wodurch die ganze Skriptflaeche
         * kurz leer flackerte. Geloesst wird erst beim Drop (removeDragged). */
        drag.restore = {
          kind: pos.parentKind, parentNode: pos.parentNode, subIdx: pos.subIdx,
          script: pos.script, scriptIdx: targetScripts().indexOf(pos.script)
        };
        drag.node = pos.node;
        markDragged(drag.node, true);
        renderScripts();
      }
      var ghost = renderBlock(drag.node, { staticMode: true });
      ghost.classList.add('blk-ghost');
      var host = doc.body || root;
      host.appendChild(ghost);
      drag.ghost = ghost;
      moveGhost(ev.clientX, ev.clientY);
    }

    function moveGhost(px, py) {
      if (!drag || !drag.ghost) { return; }
      /* Optimierung: transform statt left/top. Ein Klick auf left/top
       * loest einen Bildaufbau (Layout) aus, transform hingegen wird
       * vom Compositor erledigt: Das Ziehen bleibt auch bei schnellen
       * Mausbewegungen fluessig. */
      drag.ghost.style.transform = 'translate(' + (px - drag.grabDX) + 'px, ' + (py - drag.grabDY) + 'px)';
    }

    /* #153: Den kompletten gezogenen Teilbaum markieren (next, Koerper,
     * Steckplaetze), damit er auf der Skriptflaeche abgeblendet bleibt,
     * statt zu verschwinden. */
    function markDragged(node, on) {
      var n = node;
      while (n) {
        n._drag = on;
        var i, k;
        for (i = 0; i < (n.sub ? n.sub.length : 0); i++) {
          if (n.sub[i]) { markDragged(n.sub[i], on); }
        }
        for (k in n.f) {
          if (n.f[k] && n.f[k].b) { markDragged(n.f[k].b, on); }
        }
        n = n.next;
      }
    }

    /* Liegt die Block-ID im gezogenen Teilbaum? Ziel eines Drops darf
     * nie der eigene Teilbaum sein (Zirkelgefahr, #153). */
    function inDraggedTree(node, id) {
      if (!node) { return false; }
      if (node.id === id) { return true; }
      var i, k;
      if (inDraggedTree(node.next, id)) { return true; }
      for (i = 0; i < (node.sub ? node.sub.length : 0); i++) {
        if (inDraggedTree(node.sub[i], id)) { return true; }
      }
      for (k in node.f) {
        if (n_f_b(node, k) && inDraggedTree(node.f[k].b, id)) { return true; }
      }
      return false;
    }
    function n_f_b(node, k) { return node.f[k] && node.f[k].b; }

    /* #153: Loest den gezogenen Block erst beim Drop aus dem Modell - */
    function removeDragged() {
      if (!drag || !drag.restore || !drag.node) { return; }
      var r = drag.restore;
      if (r.kind === 'top') {
        r.script.top = null;
        removeScriptQuiet(r.script);
      } else if (r.kind === 'next') {
        r.parentNode.next = null;
      } else if (r.kind === 'sub') {
        r.parentNode.sub[r.subIdx] = null;
      } else if (r.kind === 'field') {
        r.parentNode.f[r.subIdx] = { v: fieldDefault(BLOCKS[r.parentNode.op].inputs[r.subIdx]) };
      }
    }

    function restoreDrag() {
      /* Abbruch ohne Drop (#122, #153): Der Block war nie aus dem Modell
       * geloest, er bleibt an seinem Platz - nur die Markierung fliegt weg. */
      if (!drag || !drag.node) { return false; }
      markDragged(drag.node, false);
      return true;
    }

    function endDragVisuals() {
      if (drag && drag.ghost && drag.ghost.parentNode) {
        drag.ghost.parentNode.removeChild(drag.ghost);
      }
      if (dropHint && dropHint.parentNode) { dropHint.parentNode.removeChild(dropHint); }
      dropHint = null;
      doc.removeEventListener('pointermove', onPointerMove);
      doc.removeEventListener('pointerup', onPointerUp);
      doc.removeEventListener('pointercancel', onPointerCancel);
    }

    function rectOf(node) {
      if (!node || typeof node.getBoundingClientRect !== 'function') { return null; }
      var r = node.getBoundingClientRect();
      if (typeof r.left !== 'number') { return null; }
      return r;
    }

    function dist(px, py, r) {
      var cx = Math.max(r.left, Math.min(px, r.left + r.width));
      var cy = Math.max(r.top, Math.min(py, r.top + r.height));
      return Math.sqrt((px - cx) * (px - cx) + (py - cy) * (py - cy));
    }

    function overPalette(px, py) {
      var palR = rectOf(els.pal);
      if (!palR) { return false; }
      return px >= palR.left && px <= palR.left + palR.width && py >= palR.top && py <= palR.top + palR.height;
    }

    function computeDrop(px, py) {
      var node = drag.node;
      var kind = BLOCKS[node.op].kind;
      var pal = overPalette(px, py);
      if (pal) { return { type: 'palette' }; }
      var wrapR = rectOf(els.scriptwrap);
      if (wrapR && (px < wrapR.left || px > wrapR.left + wrapR.width || py < wrapR.top || py > wrapR.top + wrapR.height)) {
        return { type: 'outside' };
      }
      if (kind === 'rep' || kind === 'bool') {
        var bestSlot = null, bestD = 56;
        var slots = els.script.querySelectorAll('.blk-slot, .blk-bslot');
        var i;
        for (i = 0; i < slots.length; i++) {
          var r = rectOf(slots[i]);
          if (!r) { continue; }
          var d = dist(px, py, r);
          if (d < bestD) { bestD = d; bestSlot = slots[i]; }
        }
        if (bestSlot) { return { type: 'slot', el: bestSlot }; }
        return { type: 'outside' };
      }
      if (kind === 'hat') { return { type: 'free' }; }
      var best = null, bestScore = 64;
      var blocks = els.script.querySelectorAll('.blk');
      var j;
      for (j = 0; j < blocks.length; j++) {
        var be = blocks[j];
        if (!be.getAttribute('data-id')) { continue; }
        if (be.classList && be.classList.contains('blk-dragging')) { continue; }
        var br = rectOf(be);
        if (!br) { continue; }
        var d2 = Math.sqrt((px - (br.left + 14)) * (px - (br.left + 14)) + (py - br.bottom) * (py - br.bottom));
        if (d2 < bestScore) { bestScore = d2; best = { type: 'below', el: be }; }
      }
      var subs = els.script.querySelectorAll('.blk-sub');
      var k;
      for (k = 0; k < subs.length; k++) {
        var sr = rectOf(subs[k]);
        if (!sr) { continue; }
        var d3 = dist(px, py, sr);
        if (d3 >= bestScore) { continue; }
        /* #148: Wo genau im Koerper landet der Block? Ueber einem Kind
         * entscheidet die Haelfte: oben davor, unten dahinter - die
         * Einfuege-Linie zeigt die genaue Stelle statt nur den Koerper. */
        var kids = subs[k].children || [];
        var picked = null;
        var prevKid = null;
        var ci;
        for (ci = 0; ci < kids.length; ci++) {
          var ke = kids[ci];
          if (!ke.classList || !ke.classList.contains('blk') || !ke.getAttribute || !ke.getAttribute('data-id')) { continue; }
          if (ke.classList.contains('blk-dragging')) { continue; }
          var kr = rectOf(ke);
          if (!kr) { continue; }
          if (px >= kr.left - 10 && px <= kr.left + kr.width + 10 && py >= kr.top - 8 && py <= kr.top + kr.height + 8) {
            if (py <= kr.top + kr.height / 2) {
              picked = prevKid ? { type: 'below', el: prevKid } : { type: 'sub', el: subs[k] };
            } else {
              picked = { type: 'below', el: ke };
            }
            break;
          }
          prevKid = ke;
        }
        if (!picked) { picked = { type: 'sub', el: subs[k] }; }
        best = picked;
        bestScore = d3;
      }
      if (best) { return best; }
      return { type: 'free' };
    }

    function showDropHint(drop) {
      if (!dropHint) {
        dropHint = el('div', 'blk-drop-hint');
        (els.scriptwrap || els.script).appendChild(dropHint);
      }
      var wrapR = rectOf(els.scriptwrap);
      if (!wrapR) { return; }
      var r = rectOf(drop.el);
      if (!r) { return; }
      dropHint.className = 'blk-drop-hint ' + drop.type;
      if (drop.type === 'below') {
        dropHint.style.left = (r.left - wrapR.left) + 'px';
        dropHint.style.top = (r.bottom - wrapR.top - 3) + 'px';
        dropHint.style.width = Math.max(40, r.width) + 'px';
        dropHint.style.height = '6px';
      } else if (drop.type === 'sub') {
        /* #148: Auch im (leeren) Koerper zeigt eine Linie die genaue
         * Einfuegestelle am Anfang des Koerpers statt nur ein Rahmen. */
        dropHint.style.left = (r.left - wrapR.left) + 'px';
        dropHint.style.top = (r.top - wrapR.top - 3) + 'px';
        dropHint.style.width = Math.max(40, r.width) + 'px';
        dropHint.style.height = '6px';
      } else {
        dropHint.style.left = (r.left - wrapR.left - 2) + 'px';
        dropHint.style.top = (r.top - wrapR.top - 2) + 'px';
        dropHint.style.width = (r.width + 4) + 'px';
        dropHint.style.height = (r.height + 4) + 'px';
      }
    }

    function hideDropHint() {
      if (dropHint && dropHint.parentNode) { dropHint.parentNode.removeChild(dropHint); }
      dropHint = null;
    }

    function applyDrop(drop) {
      var node = drag.node;
      var kind = BLOCKS[node.op].kind;
      if (drop.type === 'slot') {
        var hostEl = walkUp(drop.el, function (n) {
          return n.classList && n.classList.contains('blk') && n.getAttribute('data-id');
        });
        if (!hostEl) { return false; }
        var hostId = hostEl.getAttribute('data-id');
        if (drag.origin === 'script' && inDraggedTree(node, hostId)) { return false; }
        var pos = findBlockPos(hostId);
        if (!pos) { return false; }
        var fname = drop.el.getAttribute('data-f');
        var want = BLOCKS[pos.node.op].inputs[fname];
        var fits = want && ((kind === 'bool' && want.type === 'bool') || (kind === 'rep' && want.type === 'slot'));
        if (!fits) { return false; }
        if (drag.origin === 'script') { removeDragged(); }
        pos.node.f[fname] = { b: node };
        return true;
      }
      if (drop.type === 'below') {
        var tid = drop.el.getAttribute('data-id');
        if (drag.origin === 'script' && inDraggedTree(node, tid)) { return false; }
        var tpos = findBlockPos(tid);
        if (!tpos) { return false; }
        var tail = node;
        while (tail.next) { tail = tail.next; }
        if (drag.origin === 'script') { removeDragged(); }
        tail.next = tpos.node.next;
        tpos.node.next = node;
        return true;
      }
      if (drop.type === 'sub') {
        var host = drop.el.parent || drop.el.parentNode;
        while (host && !(host.classList && host.classList.contains('blk') && host.getAttribute('data-id'))) { host = host.parent || host.parentNode; }
        if (!host) { return false; }
        var hid = host.getAttribute('data-id');
        if (drag.origin === 'script' && inDraggedTree(node, hid)) { return false; }
        var hpos = findBlockPos(hid);
        if (!hpos) { return false; }
        var idx = parseInt(drop.el.getAttribute('data-sub'), 10) || 0;
        var tail2 = node;
        while (tail2.next) { tail2 = tail2.next; }
        if (drag.origin === 'script') { removeDragged(); }
        tail2.next = hpos.node.sub[idx];
        hpos.node.sub[idx] = node;
        return true;
      }
      if (drop.type === 'free') {
        if (drag.origin === 'script') { removeDragged(); }
        var wrapR = rectOf(els.script) || { left: 0, top: 0 };
        targetScripts().push({
          id: uid('s'), x: clamp(Math.round(drag.lastX - wrapR.left - 12), 0, 4000),
          y: clamp(Math.round(drag.lastY - wrapR.top - 10), 0, 4000), top: node
        });
        return true;
      }
      return false;
    }

    function onPointerMove(ev) {
      if (!drag) { return; }
      if (drag.pointerId !== null && ev.pointerId !== undefined && ev.pointerId !== drag.pointerId) { return; }
      drag.lastX = ev.clientX;
      drag.lastY = ev.clientY;
      if (drag.phase === 'pending') {
        var dx = ev.clientX - drag.startX;
        var dy = ev.clientY - drag.startY;
        if (dx * dx + dy * dy < DRAG_THRESHOLD * DRAG_THRESHOLD) { return; }
        startDrag(ev);
        if (!drag) { return; }
      }
      if (drag.phase !== 'dragging') { return; }
      moveGhost(ev.clientX, ev.clientY);
      var drop = computeDrop(ev.clientX, ev.clientY);
      drag.drop = drop;
      drag.overPal = drop.type === 'palette';
      if (drag.ghost) {
        if (drag.overPal && drag.origin === 'script') { drag.ghost.classList.add('blk-del'); }
        else { drag.ghost.classList.remove('blk-del'); }
      }
      if (drop.type === 'below' || drop.type === 'sub' || drop.type === 'slot') {
        showDropHint(drop);
      } else {
        hideDropHint();
      }
    }

    function finishDrag(cancelled) {
      if (!drag) { return; }
      var handled = false;
      if (drag.phase === 'pending') {
        /* nur ein Klick ohne Ziehen: nichts tun, nichts verschieben (#122) */
        handled = true;
      } else if (cancelled) {
        /* Abbruch: Block zurueckstellen statt verlieren */
        if (drag.origin === 'script') { restoreDrag(); }
        handled = true;
      } else {
        var drop = drag.drop || { type: 'outside' };
        if (drop.type === 'palette') {
          if (drag.origin === 'script') {
            /* bewusst auf die Palette gezogen: loeschen (#153: erst hier
             * wird der Block wirklich aus dem Modell geloest) */
            removeDragged();
            handled = true;
          } else {
            handled = true; /* Palette-Block zurueck: nichts zu tun */
          }
        } else if (drop.type === 'outside' || drop.type === 'slot') {
          if (drop.type === 'slot') { handled = applyDrop(drop); }
          if (!handled) {
            if (drag.origin === 'script') { handled = restoreDrag(); }
            else { handled = true; /* ausserhalb liegengelassen: nicht einfuegen */ }
          }
        } else {
          handled = applyDrop(drop);
          if (!handled && drag.origin === 'script') { handled = restoreDrag(); }
        }
      }
      var wasReal = drag.phase === 'dragging';
      if (drag.node) { markDragged(drag.node, false); }
      endDragVisuals();
      drag = null;
      if (wasReal) {
        renderScripts();
        autosaveSoon();
      }
    }

    function onPointerUp(ev) {
      if (!drag) { return; }
      if (drag.pointerId !== null && ev.pointerId !== undefined && ev.pointerId !== drag.pointerId) { return; }
      drag.lastX = ev.clientX;
      drag.lastY = ev.clientY;
      if (drag.phase === 'dragging') { drag.drop = computeDrop(ev.clientX, ev.clientY); }
      finishDrag(false);
    }

    function onPointerCancel() {
      if (!drag) { return; }
      finishDrag(true);
    }

    /* ---------- Variablen ---------- */
    function addVariable(name) {
      var clean = sanitizeVarName(name);
      if (clean === '') { return false; }
      if (prog.vars.indexOf(clean) !== -1) { return false; }
      if (prog.vars.length >= LIMITS.vars) { return false; }
      prog.vars.push(clean);
      renderScripts();
      renderHelp();
      autosaveSoon();
      return true;
    }

    /* ---------- Listen (#137) ---------- */
    function addList(name) {
      var clean = sanitizeVarName(name);
      if (clean === '') { return false; }
      if (prog.lists.indexOf(clean) !== -1) { return false; }
      if (prog.lists.length >= LIMITS.lists) { return false; }
      prog.lists.push(clean);
      renderScripts();
      renderPalette();
      renderHelp();
      autosaveSoon();
      return true;
    }

    /* ---------- Eigene Bloecke (#138) ---------- */
    function addProc(name) {
      var clean = sanitizeVarName(name);
      if (clean === '') { return false; }
      var pool = procsOfTarget();
      if (pool.indexOf(clean) !== -1) { return false; }
      if (pool.length >= LIMITS.procs) { return false; }
      pool.push(clean);
      renderPalette();
      renderScripts();
      renderHelp();
      autosaveSoon();
      return true;
    }

    /* ---------- Vorlagen (#142): Beispielprogramm laden ---------- */
    function loadTemplate(idx) {
      var t = TEMPLATES[idx];
      if (!t) { return false; }
      stopRun();
      prog = parseProgram(JSON.stringify(t.prog));
      target = prog.sprites.length > 0 ? prog.sprites[0] : null;
      activeCat = 'events';
      renderSprites();
      renderCats();
      renderPalette();
      renderScripts();
      renderHelp();
      drawStage();
      autosaveSoon();
      return true;
    }

    var templatesBox = null;
    function ensureTemplatesBox() {
      if (templatesBox) { return templatesBox; }
      templatesBox = el('div', 'blk-templates-box');
      templatesBox.setAttribute('id', 'blk-templates-box');
      var head = el('div', 'blk-templates-head');
      head.appendChild(el('strong', '', 'Beispielprogramme'));
      var closeT = el('button', 'blk-x', '✕');
      closeT.setAttribute('type', 'button');
      closeT.setAttribute('aria-label', 'Vorlagen schließen');
      closeT.addEventListener('click', function () { templatesBox.setAttribute('hidden', 'hidden'); });
      head.appendChild(closeT);
      templatesBox.appendChild(head);
      templatesBox.appendChild(el('p', 'blk-templates-note',
        'Achtung: Laden ersetzt das aktuelle Programm.'));
      var i;
      for (i = 0; i < TEMPLATES.length; i++) {
        (function (idx) {
          var row = el('div', 'blk-templates-row');
          var info = el('div', 'blk-templates-info');
          info.appendChild(el('strong', '', TEMPLATES[idx].name));
          info.appendChild(el('span', '', TEMPLATES[idx].desc + ' (' +
            templateBlockCount(TEMPLATES[idx].prog) + ' Blöcke)'));
          row.appendChild(info);
          var loadBtn = el('button', 'btn btn-primary', 'Laden');
          loadBtn.setAttribute('type', 'button');
          loadBtn.addEventListener('click', function () {
            loadTemplate(idx);
            templatesBox.setAttribute('hidden', 'hidden');
          });
          row.appendChild(loadBtn);
          templatesBox.appendChild(row);
        })(i);
      }
      root.appendChild(templatesBox);
      templatesBox.setAttribute('hidden', 'hidden');
      return templatesBox;
    }

    function openTemplates() {
      var box = ensureTemplatesBox();
      box.removeAttribute('hidden');
    }

    /* ---------- Vollbild (#143): Buehne gross ueber dem Editor ---------- */
    var fullOverlay = null;
    var stageHome = null;

    function fullscreenOn() {
      if (fullOverlay || !els.stage) { return false; }
      fullOverlay = el('div', 'blk-fullbox');
      fullOverlay.setAttribute('id', 'blk-fullbox');
      stageHome = els.stage.parentNode || null;
      var bar = el('div', 'blk-fullbar');
      var startBtn = el('button', 'btn btn-primary', '▶ Start');
      startBtn.setAttribute('type', 'button');
      startBtn.addEventListener('click', function () { startRun(); });
      var stopBtn = el('button', 'btn', '⏹ Stopp');
      stopBtn.setAttribute('type', 'button');
      stopBtn.addEventListener('click', function () { stopRun(); });
      var closeBtn = el('button', 'btn', '✕ Beenden');
      closeBtn.setAttribute('type', 'button');
      closeBtn.addEventListener('click', function () { fullscreenOff(); });
      bar.appendChild(startBtn);
      bar.appendChild(stopBtn);
      bar.appendChild(closeBtn);
      fullOverlay.appendChild(bar);
      fullOverlay.appendChild(els.stage);
      root.appendChild(fullOverlay);
      try {
        if (typeof window !== 'undefined' && window.innerHeight) {
          var sc = Math.min(window.innerHeight / 360, window.innerWidth / 480);
          if (sc > 0.1 && els.stage.style) {
            els.stage.style.transform = 'scale(' + sc + ')';
            els.stage.style.transformOrigin = 'center top';
          }
        }
      } catch (eScale) { /* ohne Fenster bleibt die Buehne 1:1 */ }
      drawStage();
      return true;
    }

    function fullscreenOff() {
      if (!fullOverlay) { return false; }
      if (els.stage.style) {
        try { els.stage.style.transform = ''; } catch (eT) { /* egal */ }
      }
      if (stageHome) { stageHome.appendChild(els.stage); }
      var ob = fullOverlay;
      fullOverlay = null;
      stageHome = null;
      if (ob.parentNode) { ob.parentNode.removeChild(ob); }
      drawStage();
      return true;
    }

    function isFullscreen() { return fullOverlay !== null; }

    /* ---------- Autospeichern (#121) ---------- */
    var saveTimer = null;
    var lastSave = null;

    function programJson() {
      return JSON.stringify(serializeProgram(prog));
    }

    function saveNow() {
      els.jsonTa.value = programJson();
      lastSave = programJson();
      if (!els.saveForm || typeof fetch !== 'function' || typeof URLSearchParams !== 'function') {
        return null;
      }
      var action = els.saveForm.getAttribute('action') || (typeof window !== 'undefined' && window.location ? window.location.href : '');
      var params = new URLSearchParams();
      var csrf = els.saveForm.querySelector('input[name="csrf"]');
      if (csrf) { params.append('csrf', csrf.value); }
      var title = els.saveForm.querySelector('input[name="title"]');
      if (title) { params.append('title', title.value); }
      params.append('content', programJson());
      return fetch(action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
        keepalive: true
      }).then(function () {
        var d = new Date();
        var hh = ('0' + d.getHours()).slice(-2);
        var mi = ('0' + d.getMinutes()).slice(-2);
        if (els.saveInfo) {
          els.saveInfo.textContent = '✓ automatisch gespeichert um ' + hh + ':' + mi + ' Uhr';
          els.saveInfo.hidden = false;
        }
      }).catch(function () {
        if (els.saveInfo) {
          els.saveInfo.textContent = '⚠ Speichern fehlgeschlagen – bitte prüfen, ob du noch online bist.';
          els.saveInfo.hidden = false;
        }
      });
    }

    function autosaveSoon() {
      els.jsonTa.value = programJson();
      if (saveTimer !== null) { clearTimeout(saveTimer); }
      saveTimer = setTimeout(function () { saveTimer = null; saveNow(); }, 900);
    }

    /* Buehnen-Hintergrund (#139): schlichte Farbverlaeufe auf dem
     * Hintergrund-Canvas, unter allen Spuren und Figuren. */
    function drawBackdrop() {
      if (!els.bgCanvas || typeof els.bgCanvas.getContext !== 'function') { return; }
      var bctx = els.bgCanvas.getContext('2d');
      if (!bctx) { return; }
      var bd = rt ? rt.backdrop() : (prog.stage.backdrop || 'paper');
      try {
        if (bd === 'sunrise') {
          var g1 = bctx.createLinearGradient(0, 0, 0, 360);
          g1.addColorStop(0, '#fde68a');
          g1.addColorStop(1, '#fb7185');
          bctx.fillStyle = g1;
        } else if (bd === 'sea') {
          var g2 = bctx.createLinearGradient(0, 0, 0, 360);
          g2.addColorStop(0, '#38bdf8');
          g2.addColorStop(1, '#0c4a6e');
          bctx.fillStyle = g2;
        } else if (bd === 'meadow') {
          var g3 = bctx.createLinearGradient(0, 0, 0, 360);
          g3.addColorStop(0, '#86efac');
          g3.addColorStop(1, '#166534');
          bctx.fillStyle = g3;
        } else {
          bctx.fillStyle = '#ffffff';
        }
        bctx.fillRect(0, 0, 480, 360);
      } catch (eBg) { /* Canvas ohne echte 2D-Kontext: nur der Hintergrund fehlt */ }
    }

    /* ---------- Buehne: alle Figuren zeichnen (#119, #125) ---------- */
    function drawStage() {
      drawBackdrop();
      if (!els.ctrCanvas || typeof els.ctrCanvas.getContext !== 'function') { return; }
      var ctx = els.ctrCanvas.getContext('2d');
      if (!ctx) { return; }
      ctx.clearRect(0, 0, 480, 360);
      var list = rt ? rt.sprites : prog.sprites;
      var i, sp;
      /* Ebenen (#135): bei laufender Runtime zeichnet die order-Liste hinten
       * nach vorn, sonst die Natur-Reihenfolge. */
      var drawList = [];
      if (rt) {
        for (i = 0; i < rt.order.length; i++) {
          var osp = findSpriteById(rt, rt.order[i]);
          if (osp) { drawList.push(osp); }
        }
      } else {
        drawList = list;
      }
      for (i = 0; i < drawList.length; i++) {
        sp = drawList[i];
        if (!sp.visible) { continue; }
        ctx.save();
        /* Ursprung oben links (#144): x/y sind direkt Buehnen-Pixel. */
        ctx.translate(sp.x, sp.y);
        ctx.rotate(sp.dir * Math.PI / 180);
        ctx.font = Math.round(40 * sp.size / 100) + 'px system-ui, "Segoe UI Symbol", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        /* Farbeffekt (#135): hue-rotate faerbt das Kostuem um. */
        var effectOn = num(sp.effect) !== 0;
        if (effectOn && typeof ctx.filter === 'string') {
          try { ctx.filter = 'hue-rotate(' + Math.round(num(sp.effect) * 1.8) + 'deg)'; } catch (e) { effectOn = false; }
        }
        ctx.fillText(COSTUME_EMOJI[sp.costume] || '🤖', 0, 0);
        if (effectOn && typeof ctx.filter === 'string') {
          try { ctx.filter = 'none'; } catch (e) {}
        }
        ctx.restore();
        var bub = sp.bubble;
        if (bub) {
          var bx = sp.x + 18;
          var by = sp.y - 44;
          ctx.font = '14px system-ui, sans-serif';
          var w;
          /* Optimierung: Die Textbreite einer Sprechblase aendert sich nur
           * mit dem Text. measureText ist aufwaendig, also wird pro Figur
           * und Blasentext einmal gemessen und dann wiederverwendet. Die
           * Cache-Felder beginnen mit _ und werden beim Speichern
           * herausgefiltert. */
          if (sp._bubTxt === bub.text && typeof sp._bubW === 'number') {
            w = sp._bubW;
          } else {
            w = ctx.measureText ? (ctx.measureText(bub.text).width || 60) : 60;
            sp._bubTxt = bub.text;
            sp._bubW = w;
          }
          ctx.fillStyle = '#ffffff';
          ctx.strokeStyle = '#334155';
          ctx.fillRect(bx, by - 18, Math.max(40, w + 14), 28);
          if (typeof ctx.strokeRect === 'function') { ctx.strokeRect(bx, by - 18, Math.max(40, w + 14), 28); }
          ctx.fillStyle = '#0f172a';
          ctx.textAlign = 'left';
          ctx.textBaseline = 'middle';
          ctx.fillText(bub.text, bx + 7, by - 4);
          /* Denkblase (#135): drei Punkte statt Sprechblasen-Haken. */
          if (bub.think) {
            ctx.fillStyle = '#334155';
            ctx.fillRect(bx - 6, by + 14, 5, 5);
            ctx.fillRect(bx - 13, by + 22, 4, 4);
            ctx.fillRect(bx - 19, by + 29, 3, 3);
          }
        }
      }
      /* Aktive Figur im Bearbeitungsmodus hervorheben */
      if (!rt && target && target.visible && typeof ctx.beginPath === 'function' && typeof ctx.arc === 'function') {
        ctx.strokeStyle = 'rgba(37, 99, 235, .8)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(target.x, target.y, 24 * Math.max(10, target.size) / 100 + 8, 0, Math.PI * 2);
        if (typeof ctx.setLineDash === 'function') { ctx.setLineDash([6, 4]); }
        ctx.stroke();
        if (typeof ctx.setLineDash === 'function') { ctx.setLineDash([]); }
      }
    }

    function clearPen() {
      if (els.penCanvas && typeof els.penCanvas.getContext === 'function') {
        var c = els.penCanvas.getContext('2d');
        if (c) { c.clearRect(0, 0, 480, 360); }
      }
    }

    function nowMs() {
      return (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
    }

    function loop(ts) {
      if (!rt) { loopOn = false; return; }
      rt.frame(ts);
      if (rt.errors.length > 0 && els.runInfo) {
        els.runInfo.textContent = '⚠ ' + rt.errors[rt.errors.length - 1];
      }
      drawStage();
      var busy = rt.running() || rt.bubble() !== null;
      if (busy && typeof window !== 'undefined' && window.requestAnimationFrame) {
        window.requestAnimationFrame(loop);
      } else {
        loopOn = false;
        if (els.runInfo && !rt.running()) { els.runInfo.textContent = ''; }
      }
    }

    function ensureLoop() {
      if (loopOn) { return; }
      loopOn = true;
      if (typeof window !== 'undefined' && window.requestAnimationFrame) {
        window.requestAnimationFrame(loop);
      }
    }

    function startRun() {
      clearPen();
      rt = newRuntime(prog, { penCtx: (els.penCanvas && els.penCanvas.getContext) ? els.penCanvas.getContext('2d') : null });
      rt.flag(nowMs());
      if (els.runInfo) { els.runInfo.textContent = '▶ läuft'; }
      drawStage();
      ensureLoop();
    }

    function stopRun() {
      if (rt) { rt.stopAll(); }
      loopOn = false;
      drawStage();
    }

    /* ---------- Tastatur: global, ohne Fokusforderung (#119) ---------- */
    function normKey(key) {
      if (key === ' ' || key === 'Spacebar') { return 'leertaste'; }
      if (key === 'ArrowUp') { return 'pfeil hoch'; }
      if (key === 'ArrowDown') { return 'pfeil runter'; }
      if (key === 'ArrowLeft') { return 'pfeil links'; }
      if (key === 'ArrowRight') { return 'pfeil rechts'; }
      if (key === 'Enter') { return 'enter'; }
      if (/^[a-zA-Z]$/.test(key)) { return key.toLowerCase(); }
      if (/^[0-9]$/.test(key)) { return key; }
      return null;
    }

    function inEditable(targetEl) {
      return walkUp(targetEl, function (n) {
        return n.tagName === 'INPUT' || n.tagName === 'SELECT' || n.tagName === 'TEXTAREA';
      }) !== null;
    }

    doc.addEventListener('keydown', function (ev) {
      if (inEditable(ev.target)) { return; }
      var k = normKey(ev.key);
      if (!k) { return; }
      if (!rt) {
        clearPen();
        rt = newRuntime(prog, { penCtx: (els.penCanvas && els.penCanvas.getContext) ? els.penCanvas.getContext('2d') : null });
      }
      rt.keys[k] = true;
      rt.press(k, nowMs());
      drawStage();
      ensureLoop();
    });
    doc.addEventListener('keyup', function (ev) {
      if (!rt) { return; }
      var k = normKey(ev.key);
      if (k) { delete rt.keys[k]; }
    });

    /* ---------- Buehne: Zeiger und Klicks (#133) ---------- */
    function stagePoint(ev) {
      if (!els.ctrCanvas || typeof els.ctrCanvas.getBoundingClientRect !== 'function') { return null; }
      var r = els.ctrCanvas.getBoundingClientRect();
      if (!r || !r.width) { return null; }
      return {
        /* Ursprung oben links (#144): Buehnen-Pixel ohne Versatz. */
        x: clamp((ev.clientX - r.left) / r.width * 480, 0, 480),
        y: clamp((ev.clientY - r.top) / r.height * 360, 0, 360)
      };
    }
    if (els.ctrCanvas) {
      els.ctrCanvas.addEventListener('pointermove', function (ev) {
        if (!rt) { return; }
        var p = stagePoint(ev);
        if (p) { rt.setMouse(p.x, p.y); }
      });
      els.ctrCanvas.addEventListener('pointerdown', function (ev) {
        var p = stagePoint(ev) || { x: 0, y: 0 };
        if (!rt) {
          clearPen();
          rt = newRuntime(prog, { penCtx: (els.penCanvas && els.penCanvas.getContext) ? els.penCanvas.getContext('2d') : null });
        }
        rt.setMouse(p.x, p.y, true);
        rt.click(nowMs());
        drawStage();
        ensureLoop();
      });
      els.ctrCanvas.addEventListener('pointerup', function () {
        if (rt) { rt.setMouse(undefined, undefined, false); }
      });
      els.ctrCanvas.addEventListener('pointerleave', function () {
        if (rt) { rt.setMouse(undefined, undefined, false); }
      });
    }

    /* ---------- Verdrahtung ---------- */
    if (els.flagBtn) { els.flagBtn.addEventListener('click', function () { startRun(); }); }
    if (els.stopBtn) { els.stopBtn.addEventListener('click', function () { stopRun(); }); }
    /* #161: Modus wechseln und je Konto speichern. Der Browser-Speicher
     * dient nur als Fallback, die Quelle ist die Kontoeinstellung. */
    function persistMode() {
      var url = root.getAttribute('data-mode-url');
      if (!url || typeof fetch !== 'function') { return; }
      var token = '';
      var tokenInput = (els.saveForm && els.saveForm.querySelector)
        ? els.saveForm.querySelector('input[name="csrf"]') : null;
      if (tokenInput) { token = tokenInput.value || ''; }
      var body = 'csrf=' + encodeURIComponent(token)
        + '&mode=' + (expertMode ? 'expert' : 'learn');
      try {
        fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body
        }).catch(function () {});
      } catch (eFetch) {}
    }
    function switchMode(on) {
      if (!!on === expertMode) { return; }
      expertMode = !!on;
      try {
        if (typeof window !== 'undefined' && window.localStorage) {
          window.localStorage.setItem('blk.expert', expertMode ? '1' : '0');
        }
      } catch (eStore2) {}
      persistMode();
      applyMode();
    }
    if (els.modeLearn) { els.modeLearn.addEventListener('click', function () { switchMode(false); }); }
    if (els.modeExpert) { els.modeExpert.addEventListener('click', function () { switchMode(true); }); }

    /* ---------- Konsole: gezielt steuern im Expertenmodus (#152) ---------- */
    function consoleLog(text, kind) {
      if (!els.consoleLog) { return; }
      var line = el('div', 'blk-console-line' + (kind === 'cmd' ? ' blk-console-cmd' : (kind === 'err' ? ' blk-console-err' : '')), text);
      els.consoleLog.appendChild(line);
      /* Optimierung: Das Protokoll wird gedeckelt, damit das Element
       * in langen Sitzungen nicht unbegrenzt waechst und der Browser
       * den wachsenden Teil bei jedem neuen Befehl neu richten muss.
       * Die aeltesten Zeilen fallen zuerst weg. */
      while (els.consoleLog.children.length > 200) {
        els.consoleLog.removeChild(els.consoleLog.children[0]);
      }
    }

    function consoleEnsureRuntime() {
      if (!rt) {
        clearPen();
        rt = newRuntime(prog, { penCtx: (els.penCanvas && els.penCanvas.getContext) ? els.penCanvas.getContext('2d') : null });
      }
      return rt;
    }

    function consoleTarget(name) {
      var r = consoleEnsureRuntime();
      if (name) {
        var i;
        for (i = 0; i < r.sprites.length; i++) {
          if (String(r.sprites[i].name).toLowerCase() === String(name).toLowerCase()) { return r.sprites[i]; }
        }
        return null;
      }
      if (target) { return findSpriteById(r, target.id); }
      return null;
    }

    function consoleRun(cmdRaw) {
      var cmd = String(cmdRaw || '').trim();
      if (cmd === '') { return; }
      consoleLog('» ' + cmd, 'cmd');
      var parts = cmd.replace(/\s+/g, ' ').split(' ');
      /* "robo.gehe 50" steuert die benannte Figur, sonst die ausgewaehlte (#152).
       * Der Befehl ist das Wort mit dem Punkt; davor steht der Name, der
       * durchaus Leerzeichen enthalten darf ("Figur 1.gehe 50"). */
      var namePrefix = null;
      var dotIdx = -1;
      for (var pi = 0; pi < parts.length; pi++) {
        if (parts[pi].indexOf('.') !== -1) { dotIdx = pi; break; }
      }
      if (dotIdx !== -1) {
        var seg = parts[dotIdx].split('.');
        var cmdWord = seg[seg.length - 1];
        var namePart = seg.slice(0, seg.length - 1).join('.');
        if (dotIdx > 0) { namePart = parts.slice(0, dotIdx).join(' ') + (namePart ? ' ' + namePart : ''); }
        if (namePart) { namePrefix = namePart; }
        parts = [cmdWord].concat(parts.slice(dotIdx + 1));
      }
      var op = parts[0].toLowerCase();
      var arg = parts.slice(1).join(' ');
      var n = num(arg);
      var i2;
      if (op === 'hilfe') {
        consoleLog('Befehle: gehe N - drehe N - x N - y N - zeige - verstecke - sage Text - kostuem N - ton Name [Sek] - sende Nachricht - info - stoppe');
        consoleLog('Mit Figurnamen gezielt steuern: robo.gehe 50. Ohne Namen trifft der Befehl die ausgewaehlte Figur.');
        return;
      }
      if (op === 'info') {
        var r = consoleEnsureRuntime();
        if (r.sprites.length === 0) { consoleLog('Es gibt noch keine Figuren auf der Buehne.'); return; }
        for (i2 = 0; i2 < r.sprites.length; i2++) {
          var s2 = r.sprites[i2];
          consoleLog(s2.name + ': x ' + Math.round(s2.x) + ', y ' + Math.round(s2.y) + ', Richtung ' + Math.round(s2.dir) + (s2.visible ? '' : ', versteckt'));
        }
        return;
      }
      if (op === 'sende') {
        if (arg === '') { consoleLog('Welche Nachricht? Beispiel: sende nachricht1', 'err'); return; }
        var rr = consoleEnsureRuntime();
        if (rr.api.broadcast) { rr.api.broadcast(arg, nowMs()); }
        consoleLog('Nachricht "' + arg + '" gesendet.');
        drawStage();
        ensureLoop();
        return;
      }
      if (op === 'stoppe') {
        stopRun();
        consoleLog('Alles angehalten.');
        return;
      }
      var sp = consoleTarget(namePrefix);
      if (!sp) {
        if (namePrefix) { consoleLog('Ich kenne keine Figur namens "' + namePrefix + '". info zeigt alle Figuren.', 'err'); }
        else { consoleLog('Waehle links eine Figur aus oder steuere sie direkt mit Namen: robo.gehe 50', 'err'); }
        return;
      }
      switch (op) {
        case 'gehe':
          sp.x = clamp(sp.x + Math.sin(sp.dir * Math.PI / 180) * n, 0, 480);
          sp.y = clamp(sp.y - Math.cos(sp.dir * Math.PI / 180) * n, 0, 360);
          consoleLog(sp.name + ' geht ' + Math.round(n) + ' Schritte.');
          break;
        case 'drehe':
          sp.dir = (sp.dir + n) % 360;
          consoleLog(sp.name + ' dreht um ' + Math.round(n) + ' Grad.');
          break;
        case 'x': sp.x = clamp(n, 0, 480); consoleLog('x von ' + sp.name + ' ist jetzt ' + Math.round(sp.x) + '.'); break;
        case 'y': sp.y = clamp(n, 0, 360); consoleLog('y von ' + sp.name + ' ist jetzt ' + Math.round(sp.y) + '.'); break;
        case 'zeige': sp.visible = true; consoleLog(sp.name + ' ist sichtbar.'); break;
        case 'verstecke': sp.visible = false; sp.bubble = null; consoleLog(sp.name + ' ist versteckt.'); break;
        case 'sage':
          if (arg === '') { consoleLog('Was soll ' + sp.name + ' sagen? Beispiel: sage Hallo!', 'err'); return; }
          sp.bubble = { text: arg, until: null };
          consoleLog(sp.name + ' sagt: "' + arg + '"');
          break;
        case 'kostuem':
          sp.costume = COSTUME_EMOJI[arg] ? arg : (COSTUME_ORDER[clamp(Math.round(n) - 1, 0, COSTUME_ORDER.length - 1)] || 'robot');
          consoleLog(sp.name + ' traegt jetzt ' + COSTUME_EMOJI[sp.costume] + '.');
          break;
        case 'ton':
          playTone(consoleEnsureRuntime(), parts[1] || 'c4', parts[2] ? num(parts[2]) * 1000 : null);
          consoleLog('Ton ' + (parts[1] || 'c4') + ' gestartet.');
          break;
        default:
          consoleLog('Ich kenne "' + parts[0] + '" nicht. hilfe zeigt alle Befehle.', 'err');
          return;
      }
      drawStage();
      ensureLoop();
    }

    if (els.consoleSend) {
      els.consoleSend.addEventListener('click', function () {
        if (els.consoleIn) {
          consoleRun(els.consoleIn.value);
          els.consoleIn.value = '';
        }
      });
    }
    if (els.consoleIn) {
      els.consoleIn.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
          consoleRun(els.consoleIn.value);
          els.consoleIn.value = '';
        }
      });
    }

    /* ---------- Code-Ansicht und Portierung (#140, #141) ---------- */
    var codeBox = null;
    var codeLang = 'java';

    function titleOf() {
      if (els.saveForm) {
        var kids = els.saveForm.children || [];
        var i;
        for (i = 0; i < kids.length; i++) {
          if (kids[i].getAttribute && kids[i].getAttribute('name') === 'title') { return kids[i].value || ''; }
        }
      }
      return '';
    }

    function csrfValue() {
      if (els.saveForm) {
        var kids = els.saveForm.children || [];
        var i;
        for (i = 0; i < kids.length; i++) {
          if (kids[i].getAttribute && kids[i].getAttribute('name') === 'csrf') { return kids[i].value || kids[i].getAttribute('value') || ''; }
        }
      }
      return '';
    }

    function currentCode() {
      return codeLang === 'py'
        ? programToPython(prog, titleOf() || 'Block-Programm')
        : programToJava(prog, titleOf() || 'Block-Programm');
    }

    function syncCodeTabs(tabJava, tabPy) {
      tabJava.className = 'btn blk-code-tab' + (codeLang === 'java' ? ' active' : '');
      tabPy.className = 'btn blk-code-tab' + (codeLang === 'py' ? ' active' : '');
    }

    function fillCode(pre) {
      pre.textContent = currentCode();
    }

    function portCode() {
      var form = el('form', 'blk-port-form');
      form.setAttribute('method', 'post');
      var portUrl = (els.codeBtn && els.codeBtn.getAttribute('data-port')) || '/werkstatt/code/blocks/port';
      form.setAttribute('action', portUrl);
      function addField(name, value) {
        var inp = el('input');
        inp.setAttribute('type', 'hidden');
        inp.setAttribute('name', name);
        inp.setAttribute('value', String(value));
        form.appendChild(inp);
      }
      var t = titleOf() || 'Block-Programm';
      addField('csrf', csrfValue());
      addField('language', codeLang === 'py' ? 'python' : 'java');
      addField('title', t + (codeLang === 'py' ? ' (Python)' : ' (Java)'));
      addField('code', currentCode());
      ensureCodeBox().appendChild(form);
      if (typeof form.submit === 'function') { form.submit(); }
    }

    var codePre = null;
    var codeTabJava = null;
    var codeTabPy = null;

    function ensureCodeBox() {
      if (codeBox) { return codeBox; }
      codeBox = el('div', 'blk-codebox');
      codeBox.setAttribute('id', 'blk-code-dialog');
      var head = el('div', 'blk-code-head');
      var tabs = el('div', 'blk-code-tabs');
      codeTabJava = el('button', 'btn blk-code-tab active', 'Java');
      codeTabJava.setAttribute('type', 'button');
      codeTabPy = el('button', 'btn blk-code-tab', 'Python');
      codeTabPy.setAttribute('type', 'button');
      tabs.appendChild(codeTabJava);
      tabs.appendChild(codeTabPy);
      head.appendChild(tabs);
      var close = el('button', 'btn btn-ghost', '✕');
      close.setAttribute('type', 'button');
      close.setAttribute('title', 'Code-Ansicht schließen');
      close.addEventListener('click', function () { codeBox.setAttribute('hidden', 'hidden'); });
      head.appendChild(close);
      codeBox.appendChild(head);
      codePre = el('pre', 'blk-code-view mono');
      codePre.setAttribute('id', 'blk-code-view');
      codeBox.appendChild(codePre);
      var row = el('div', 'btn-row blk-code-actions');
      var copyBtn = el('button', 'btn', 'Kopieren');
      copyBtn.setAttribute('type', 'button');
      copyBtn.addEventListener('click', function () {
        if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(currentCode());
        }
      });
      var dlBtn = el('button', 'btn', 'Datei laden');
      dlBtn.setAttribute('type', 'button');
      dlBtn.addEventListener('click', function () {
        if (typeof Blob !== 'function' || typeof URL === 'undefined' || !URL.createObjectURL) { return; }
        var code = currentCode();
        var fname = codeLang === 'py' ? 'werkstatt_programm.py' : javaClassName(titleOf() || 'Werkstatt') + '.java';
        var blob = new Blob([code], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);
        var a = el('a');
        a.setAttribute('href', url);
        a.setAttribute('download', fname);
        if (typeof a.click === 'function') { a.click(); }
      });
      var portBtn = el('button', 'btn btn-primary', 'Als neues Projekt übernehmen');
      portBtn.setAttribute('type', 'button');
      portBtn.setAttribute('title', 'Legt ein neues Java- oder Python-Projekt mit diesem Code an (#141)');
      portBtn.addEventListener('click', function () { portCode(); });
      row.appendChild(copyBtn);
      row.appendChild(dlBtn);
      row.appendChild(portBtn);
      codeBox.appendChild(row);
      codeTabJava.addEventListener('click', function () {
        codeLang = 'java';
        syncCodeTabs(codeTabJava, codeTabPy);
        fillCode(codePre);
      });
      codeTabPy.addEventListener('click', function () {
        codeLang = 'py';
        syncCodeTabs(codeTabJava, codeTabPy);
        fillCode(codePre);
      });
      root.appendChild(codeBox);
      codeBox.setAttribute('hidden', 'hidden');
      return codeBox;
    }

    function openCodeDialog() {
      var box = ensureCodeBox();
      box.removeAttribute('hidden');
      syncCodeTabs(codeTabJava, codeTabPy);
      fillCode(codePre);
    }

    if (els.codeBtn) { els.codeBtn.addEventListener('click', function () { openCodeDialog(); }); }

    if (els.varAdd) {
      els.varAdd.addEventListener('click', function () {
        var name = typeof window !== 'undefined' && window.prompt ? window.prompt('Name der neuen Variable:') : '';
        if (name) { addVariable(name); }
      });
    }
    if (els.listAdd) {
      els.listAdd.addEventListener('click', function () {
        var name = typeof window !== 'undefined' && window.prompt ? window.prompt('Name der neuen Liste:') : '';
        if (name) { addList(name); }
      });
    }
    if (els.procAdd) {
      els.procAdd.addEventListener('click', function () {
        var name = typeof window !== 'undefined' && window.prompt ? window.prompt('Name des neuen Bausteins:') : '';
        if (name) { addProc(name); }
      });
    }
    if (els.templatesBtn) {
      els.templatesBtn.addEventListener('click', function () { openTemplates(); });
    }
    if (els.fullBtn) {
      els.fullBtn.addEventListener('click', function () { fullscreenOn(); });
    }
    doc.addEventListener('keydown', function (ev) {
      if (fullOverlay && ev && ev.key === 'Escape') { fullscreenOff(); }
    });
    doc.addEventListener('pointerdown', function (ev) {
      var targetEl = ev.target;
      if (!targetEl) { return; }
      /* nur wenn der Klick aus Palette oder Skriptflaeche kommt */
      var inArea = walkUp(targetEl, function (n) { return n === els.pal || n === els.script; });
      if (!inArea) { return; }
      onPointerDown(ev);
    });
    if (els.saveForm) {
      els.saveForm.addEventListener('submit', function () {
        els.jsonTa.value = programJson();
      });
    }

    renderSprites();
    renderCats();
    applyMode();
    renderHelp();
    renderScripts();
    /* Buehne einmal zeichnen: alle Figuren sind sofort sichtbar */
    drawStage();

    var ui = {
      program: prog,
      renderScripts: renderScripts,
      renderPalette: renderPalette,
      renderHelp: renderHelp,
      renderSprites: renderSprites,
      addVariable: addVariable,
      addList: addList,
      addProc: addProc,
      loadTemplate: loadTemplate,
      templateCount: function () { return TEMPLATES.length; },
      openTemplates: openTemplates,
      fullscreenOn: fullscreenOn,
      fullscreenOff: fullscreenOff,
      isFullscreen: isFullscreen,
      saveNow: saveNow,
      autosaveSoon: autosaveSoon,
      programJson: programJson,
      addSprite: addSprite,
      deleteSprite: deleteSprite,
      selectTarget: selectTarget,
      target: function () { return target; },
      isStage: isStage,
      targetScripts: targetScripts,
      addBlock: function (op, x, y) {
        targetScripts().push({ id: uid('s'), x: x || 24, y: y || 24, top: newBlock(op) });
        renderScripts();
        autosaveSoon();
        return targetScripts()[targetScripts().length - 1];
      },
      deleteBlockTree: deleteBlockTree,
      isDragging: function () { return drag !== null && drag.phase === 'dragging'; },
      startRun: startRun,
      stopRun: stopRun,
      openCodeDialog: openCodeDialog,
      toJava: function () { return programToJava(prog, titleOf() || 'Block-Programm'); },
      toPython: function () { return programToPython(prog, titleOf() || 'Block-Programm'); },
      tick: function (ts) {
        if (rt) { rt.frame(ts); drawStage(); }
      },
      runtime: function () { return rt; },
      isExpert: function () { return expertMode; },
      setMode: function (on) {
        expertMode = !!on;
        applyMode();
      },
      consoleRun: consoleRun,
      consoleLogEl: function () { return els.consoleLog; },
      newRuntime: function () {
        rt = newRuntime(prog, { penCtx: (els.penCanvas && els.penCanvas.getContext) ? els.penCanvas.getContext('2d') : null });
        return rt;
      }
    };
    return ui;
  }
  /* Letzte Editor-Instanz (fuer Debugging und Node-Tests). */
  function attachAndExpose(doc) {
    var ui = attach(doc);
    if (ui) { api.ui = ui; }
    return ui;
  }

  /* ---------- Exponat ---------- */
  var api = {
    CATS: CATS,
    BLOCKS: BLOCKS,
    KEY_OPTIONS: KEY_OPTIONS,
    COSTUME_OPTIONS: COSTUME_OPTIONS,
    newBlock: newBlock,
    emptyProgram: emptyProgram,
    defaultSprite: defaultSprite,
    parseProgram: parseProgram,
    serializeProgram: serializeProgram,
    newRuntime: newRuntime,
    programToJava: programToJava,
    programToPython: programToPython,
    attach: attachAndExpose
  };
  if (typeof window !== 'undefined') { window.IC_BLOCKS = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }

  /* Autoboot: nur wenn die Seite den Editor auch zeigt */
  if (typeof document !== 'undefined' && typeof document.getElementById === 'function' && document.getElementById('blk-ide')) {
    attachAndExpose(document);
  }
}());
