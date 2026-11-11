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
    { id: 'control', label: 'Steuerung', color: '#16a34a' },
    { id: 'sensing', label: 'Wahrnehmung', color: '#0891b2' },
    { id: 'ops', label: 'Operatoren', color: '#059669' },
    { id: 'vars', label: 'Variablen', color: '#db2777' },
    { id: 'pen', label: 'Stift', color: '#65a30d' }
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

  function optionSource(name, vars) {
    if (name === 'keys') { return KEY_OPTIONS; }
    if (name === 'costumes') { return COSTUME_OPTIONS; }
    if (name === 'colors') { return PEN_COLORS; }
    if (name === 'vars') {
      var out = [];
      var vs = vars || [];
      var i;
      for (i = 0; i < vs.length; i++) { out.push({ v: vs[i], l: vs[i] }); }
      if (out.length === 0) { out.push({ v: '', l: '(keine Variable)' }); }
      return out;
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
      inputs: { x: { type: 'slot', def: '0' }, y: { type: 'slot', def: '0' } },
      parts: ['gehe zu x: ', { in: 'x' }, ' y: ', { in: 'y' }],
      help: 'Setzt die Figur an eine Position. Die Mitte der Bühne ist x = 0, y = 0.' },
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
      help: 'Die waagerechte Position der Figur (-240 bis 240).' },
    ypos: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['y-Position'],
      help: 'Die senkrechte Position der Figur (-180 bis 180).' },
    direction: { cat: 'sensing', kind: 'rep', inputs: {}, parts: ['Richtung'],
      help: 'Die aktuelle Richtung in Grad (0 = oben, 90 = rechts).' },

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

    /* Stift */
    pen_down: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['Stift ab, fange an zu zeichnen'],
      help: 'Ab jetzt hinterlässt die Figur beim Bewegen eine Spur.' },
    pen_up: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['Stift hoch, stoppe das Zeichnen'],
      help: 'Stoppt das Zeichnen, Bewegungen hinterlassen keine Spur mehr.' },
    pen_clear: { cat: 'pen', kind: 'stack', inputs: {}, parts: ['lösche alle Spuren'],
      help: 'Entfernt alle gezeichneten Spuren von der Bühne.' },
    pen_color: { cat: 'pen', kind: 'stack', inputs: { color: { type: 'select', src: 'colors', def: '#1f2937' } },
      parts: ['setze Stiftfarbe auf ', { in: 'color' }], help: 'Wählt die Farbe künftiger Spuren.' }
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
      x: 0, y: 0, dir: 90, size: 100, visible: true,
      scripts: []
    };
  }

  function emptyProgram() {
    return {
      v: 2,
      vars: [],
      sprites: [defaultSprite('robot', 'Figur 1')],
      stage: { scripts: [] }
    };
  }

  /* ---------- Serialisierung und Validierung (#121) ----------
   * Gespeichert wird ein JSON-Objekt der Version 2: globale Variablen,
   * die Liste der Figuren mit JE eigenen Skripten und die Buehne mit
   * eigenen Skripten. Beim Laden wird alles bereinigt: unbekannte
   * Bloecke entfallen, Felder werden gekuerzt, Grenzen greifen. Alte
   * Programme der Version 1 (eine Figur, flache Skriptliste) werden
   * automatisch auf Version 2 gehoben. */
  var LIMITS = { scripts: 60, depth: 12, blocks: 800, fields: 200, vars: 30, sprites: 12 };

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
        scripts: serializeScriptList(sp.scripts)
      });
    }
    return {
      v: 2,
      vars: (prog.vars || []).slice(0),
      sprites: sprites,
      stage: { scripts: serializeScriptList(prog.stage.scripts) }
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

  function parseSprite(raw, index) {
    var sp = defaultSprite(raw && raw.costume, 'Figur ' + (index + 1));
    if (!raw || typeof raw !== 'object') { return sp; }
    if (typeof raw.id === 'string' && raw.id.length > 0 && raw.id.length <= 24) { sp.id = raw.id; }
    if (typeof raw.name === 'string' && raw.name.trim() !== '') { sp.name = raw.name.trim().slice(0, 24); }
    /* Nur vorhandene Properties uebernehmen: ein leeres Objekt {} soll die
     * Defaults (z. B. Richtung 90) nicht mit 0 ueberschreiben. */
    if (typeof raw.x !== 'undefined') { sp.x = clamp(num(raw.x), -240, 240); }
    if (typeof raw.y !== 'undefined') { sp.y = clamp(num(raw.y), -180, 180); }
    if (typeof raw.dir !== 'undefined') { sp.dir = num(raw.dir); }
    if (typeof raw.size !== 'undefined') { sp.size = clamp(num(raw.size), 10, 400); }
    sp.visible = raw.visible !== false;
    if (COSTUME_EMOJI[raw.costume]) { sp.costume = String(raw.costume); }
    return sp;
  }

  function parseProgram(text) {
    var data = null;
    try { data = JSON.parse(String(text)); } catch (e) { data = null; }
    var prog = emptyProgram();
    if (!data || typeof data !== 'object') { return prog; }
    var i;
    if (Object.prototype.toString.call(data.vars) === '[object Array]') {
      for (i = 0; i < data.vars.length && i < LIMITS.vars; i++) {
        var name = sanitizeVarName(String(data.vars[i]));
        if (name !== '' && prog.vars.indexOf(name) === -1) { prog.vars.push(name); }
      }
    }
    var budget = { count: 0 };
    if (Object.prototype.toString.call(data.sprites) === '[object Array]') {
      /* Version 2: Liste von Figuren, jede mit eigenen Skripten. */
      prog.sprites = [];
      for (i = 0; i < data.sprites.length && i < LIMITS.sprites; i++) {
        var sp = parseSprite(data.sprites[i], i);
        sp.scripts = parseScriptList(data.sprites[i] && data.sprites[i].scripts, budget);
        prog.sprites.push(sp);
      }
      if (data.stage && Object.prototype.toString.call(data.stage.scripts) === '[object Array]') {
        prog.stage.scripts = parseScriptList(data.stage.scripts, budget);
      }
    } else {
      /* Version 1 (vor #125): eine Figur, Skripte direkt am Programm. */
      var legacy = parseSprite(data.sprite, 0);
      if (data.sprite && typeof data.sprite === 'object') {
        if (typeof data.sprite.x !== 'undefined') { legacy.x = clamp(num(data.sprite.x), -240, 240); }
        if (typeof data.sprite.y !== 'undefined') { legacy.y = clamp(num(data.sprite.y), -180, 180); }
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
      default:
        return function () { return 0; };
    }
  }

  function touchingEdge(sp) {
    var half = 24 * Math.max(10, sp.size) / 100;
    return Math.abs(sp.x) + half >= 240 || Math.abs(sp.y) + half >= 180;
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
        moveBy(rt, sp, Math.sin(rad) * num(A.steps(rt, th)), Math.cos(rad) * num(A.steps(rt, th)));
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
        rt.penCtx.moveTo(240 + seg.x1, 180 - seg.y1);
        rt.penCtx.lineTo(240 + seg.x2, 180 - seg.y2);
        rt.penCtx.stroke();
      }
    }
    sp.x = nx;
    sp.y = ny;
  }

  /* ---------- Laufzeit: alle Figuren und die Buehne parallel ---------- */
  function newRuntime(prog, opts) {
    opts = opts || {};
    var rt = {
      vars: {},
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
      api: {}
    };
    var i;
    for (i = 0; i < prog.vars.length; i++) { rt.vars[prog.vars[i]] = 0; }
    for (i = 0; i < prog.sprites.length; i++) {
      var p = prog.sprites[i];
      rt.sprites.push({
        id: p.id, name: p.name, costume: String(p.costume),
        x: num(p.x), y: num(p.y), dir: num(p.dir),
        size: clamp(num(p.size), 10, 400), visible: p.visible !== false,
        bubble: null,
        pen: { down: false, color: '#1f2937', segments: [] }
      });
    }

    function scriptTargets() {
      var lists = [];
      var i2;
      lists.push({ scripts: prog.stage.scripts, tgt: null });
      for (i2 = 0; i2 < prog.sprites.length; i2++) {
        lists.push({ scripts: prog.sprites[i2].scripts, tgt: rt.sprites[i2] });
      }
      return lists;
    }

    function startScripts(op, keyName, now) {
      rt.now = now;
      var lists = scriptTargets();
      var l, i2, s, head;
      for (l = 0; l < lists.length; l++) {
        for (i2 = 0; i2 < lists[l].scripts.length; i2++) {
          s = lists[l].scripts[i2];
          head = s.top;
          if (!head || head.op !== op) { continue; }
          if (op === 'when_key' && String(head.f.key.v) !== keyName) { continue; }
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
          rt.threads.push({ scriptId: s.id, tgt: lists[l].tgt, instrs: out, ip: 0, mem: [], waitT: null });
        }
      }
    }

    function stepThread(th) {
      var guard = 0;
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
            case 'call':
              applyCall(rt, th, ins.op, ins.A);
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
      return 'dead';
    }

    rt.api.flag = function (now) {
      rt.stopped = false;
      rt.timerStart = now;
      startScripts('flag', null, now);
    };
    rt.api.press = function (keyName, now) { startScripts('when_key', keyName, now); };
    rt.api.frame = function (now) {
      rt.now = now;
      var i3, i4;
      for (i3 = 0; i3 < rt.sprites.length; i3++) {
        var sp = rt.sprites[i3];
        if (sp.bubble && sp.bubble.until !== null && now > sp.bubble.until) { sp.bubble = null; }
      }
      var alive = [];
      for (i4 = 0; i4 < rt.threads.length; i4++) {
        if (stepThread(rt.threads[i4]) === 'alive') { alive.push(rt.threads[i4]); }
      }
      rt.threads = alive;
    };
    rt.api.stopAll = function () {
      rt.stopped = true;
      rt.threads = [];
      var i5;
      for (i5 = 0; i5 < rt.sprites.length; i5++) { rt.sprites[i5].bubble = null; }
    };
    rt.api.running = function () { return rt.threads.length > 0; };
    return {
      flag: rt.api.flag, press: rt.api.press, frame: rt.api.frame,
      stopAll: rt.api.stopAll, running: rt.api.running,
      vars: rt.vars, sprites: rt.sprites, keys: rt.keys,
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
      runInfo: doc.getElementById('blk-runinfo'),
      saveInfo: doc.getElementById('blk-saveinfo'),
      varAdd: doc.getElementById('blk-var-add'),
      sprites: doc.getElementById('blk-sprites'),
      penCanvas: doc.getElementById('blk-pen'),
      ctrCanvas: doc.getElementById('blk-ctr'),
      saveForm: doc.getElementById('blocks-save-form'),
      jsonTa: doc.getElementById('blocks-json')
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

    function isStage() { return target === null; }
    function targetScripts() { return isStage() ? prog.stage.scripts : target.scripts; }

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
        var options = optionSource(spec.src, prog.vars);
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
      if (isStage() && STAGE_HIDDEN_CATS[catId]) { return false; }
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
      var op;
      for (op in BLOCKS) {
        if (!Object.prototype.hasOwnProperty.call(BLOCKS, op)) { continue; }
        if (BLOCKS[op].cat !== activeCat) { continue; }
        var demo = newBlock(op);
        var b = renderBlock(demo, { palette: true });
        b.setAttribute('data-pal-op', op);
        els.pal.appendChild(b);
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
          n = n.next;
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
        /* Merken, wo der Block herkam, damit ein Abbruch ihn zurueckstellen kann */
        drag.restore = {
          kind: pos.parentKind, parentNode: pos.parentNode, subIdx: pos.subIdx,
          script: pos.script, scriptIdx: targetScripts().indexOf(pos.script)
        };
        if (pos.parentKind === 'top') {
          pos.script.top = null;
          removeScriptQuiet(pos.script);
        } else if (pos.parentKind === 'next') {
          pos.parentNode.next = null;
        } else if (pos.parentKind === 'sub') {
          pos.parentNode.sub[pos.subIdx] = null;
        } else if (pos.parentKind === 'field') {
          pos.parentNode.f[pos.subIdx] = { v: fieldDefault(BLOCKS[pos.parentNode.op].inputs[pos.subIdx]) };
        }
        drag.node = pos.node;
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
      drag.ghost.style.left = (px - drag.grabDX) + 'px';
      drag.ghost.style.top = (py - drag.grabDY) + 'px';
    }

    function restoreDrag() {
      /* Abbruch ohne Drop: der Block geht an seinen alten Platz (#122). */
      if (!drag || !drag.restore || !drag.node) { return false; }
      var r = drag.restore;
      if (r.kind === 'top') {
        r.script.top = drag.node;
        var list = targetScripts();
        var at = clamp(r.scriptIdx, 0, list.length);
        list.splice(at, 0, r.script);
      } else if (r.kind === 'next') {
        r.parentNode.next = drag.node;
      } else if (r.kind === 'sub') {
        r.parentNode.sub[r.subIdx] = drag.node;
      } else if (r.kind === 'field') {
        r.parentNode.f[r.subIdx] = { b: drag.node };
      }
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
        if (d3 < bestScore) { bestScore = d3; best = { type: 'sub', el: subs[k] }; }
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
        var pos = findBlockPos(hostId);
        if (!pos) { return false; }
        var fname = drop.el.getAttribute('data-f');
        var want = BLOCKS[pos.node.op].inputs[fname];
        var fits = want && ((kind === 'bool' && want.type === 'bool') || (kind === 'rep' && want.type === 'slot'));
        if (!fits) { return false; }
        pos.node.f[fname] = { b: node };
        return true;
      }
      if (drop.type === 'below') {
        var tid = drop.el.getAttribute('data-id');
        var tpos = findBlockPos(tid);
        if (!tpos) { return false; }
        var tail = node;
        while (tail.next) { tail = tail.next; }
        tail.next = tpos.node.next;
        tpos.node.next = node;
        return true;
      }
      if (drop.type === 'sub') {
        var host = drop.el.parent || drop.el.parentNode;
        while (host && !(host.classList && host.classList.contains('blk') && host.getAttribute('data-id'))) { host = host.parent || host.parentNode; }
        if (!host) { return false; }
        var hpos = findBlockPos(host.getAttribute('data-id'));
        if (!hpos) { return false; }
        var idx = parseInt(drop.el.getAttribute('data-sub'), 10) || 0;
        var tail2 = node;
        while (tail2.next) { tail2 = tail2.next; }
        tail2.next = hpos.node.sub[idx];
        hpos.node.sub[idx] = node;
        return true;
      }
      if (drop.type === 'free') {
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
            /* bewusst auf die Palette gezogen: loeschen */
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

    /* ---------- Buehne: alle Figuren zeichnen (#119, #125) ---------- */
    function drawStage() {
      if (!els.ctrCanvas || typeof els.ctrCanvas.getContext !== 'function') { return; }
      var ctx = els.ctrCanvas.getContext('2d');
      if (!ctx) { return; }
      ctx.clearRect(0, 0, 480, 360);
      var list = rt ? rt.sprites : prog.sprites;
      var i, sp;
      for (i = 0; i < list.length; i++) {
        sp = list[i];
        if (!sp.visible) { continue; }
        ctx.save();
        ctx.translate(240 + sp.x, 180 - sp.y);
        ctx.rotate(sp.dir * Math.PI / 180);
        ctx.font = Math.round(40 * sp.size / 100) + 'px system-ui, "Segoe UI Symbol", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(COSTUME_EMOJI[sp.costume] || '🤖', 0, 0);
        ctx.restore();
        var bub = sp.bubble;
        if (bub) {
          var bx = 240 + sp.x + 18;
          var by = 180 - sp.y - 44;
          ctx.font = '14px system-ui, sans-serif';
          var w = ctx.measureText ? (ctx.measureText(bub.text).width || 60) : 60;
          ctx.fillStyle = '#ffffff';
          ctx.strokeStyle = '#334155';
          ctx.fillRect(bx, by - 18, Math.max(40, w + 14), 28);
          if (typeof ctx.strokeRect === 'function') { ctx.strokeRect(bx, by - 18, Math.max(40, w + 14), 28); }
          ctx.fillStyle = '#0f172a';
          ctx.textAlign = 'left';
          ctx.textBaseline = 'middle';
          ctx.fillText(bub.text, bx + 7, by - 4);
        }
      }
      /* Aktive Figur im Bearbeitungsmodus hervorheben */
      if (!rt && target && target.visible && typeof ctx.beginPath === 'function' && typeof ctx.arc === 'function') {
        ctx.strokeStyle = 'rgba(37, 99, 235, .8)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(240 + target.x, 180 - target.y, 24 * Math.max(10, target.size) / 100 + 8, 0, Math.PI * 2);
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

    /* ---------- Verdrahtung ---------- */
    if (els.flagBtn) { els.flagBtn.addEventListener('click', function () { startRun(); }); }
    if (els.stopBtn) { els.stopBtn.addEventListener('click', function () { stopRun(); }); }
    if (els.varAdd) {
      els.varAdd.addEventListener('click', function () {
        var name = typeof window !== 'undefined' && window.prompt ? window.prompt('Name der neuen Variable:') : '';
        if (name) { addVariable(name); }
      });
    }
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
    renderPalette();
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
      tick: function (ts) {
        if (rt) { rt.frame(ts); drawStage(); }
      },
      runtime: function () { return rt; },
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
    attach: attachAndExpose
  };
  if (typeof window !== 'undefined') { window.IC_BLOCKS = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }

  /* Autoboot: nur wenn die Seite den Editor auch zeigt */
  if (typeof document !== 'undefined' && typeof document.getElementById === 'function' && document.getElementById('blk-ide')) {
    attachAndExpose(document);
  }
}());
