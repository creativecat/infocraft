/* InfoCraft Block-Editor: Programmieren mit Blöcken per Drag & Drop (#116-#121).
 *
 * Die Werkstatt kennt daneben Java, Python und Assembler - hier kommt eine
 * blockbasierte Sprache dazu: Blöcke aus der Palette ziehen, untereinander
 * andocken, Werte und Wahrheitswerte in Steckplätze einsetzen. Die Buehne
 * rechts zeigt die Figur, der Start knuepft an die klassische Fahne an.
 *
 * Aufbau:
 *   - Registry (Kategorien + Bloecke) und Befehlsuebersicht daraus
 *   - Programmmodell: Skripte mit Blockketten, Serialisierung nach JSON
 *   - Laufzeit (frame-basiert, ohne DOM testbar): Hutfenster starten Threads,
 *     jeder frame() schaukelt alle Threads bis zum naechsten Yield weiter
 *   - Editor-Oberflaeche: Palette, Skriptflaeche mit Drag&Drop, Buehne
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

  function catColor(id) {
    for (var i = 0; i < CATS.length; i++) {
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
      for (var i = 0; i < vs.length; i++) { out.push({ v: vs[i], l: vs[i] }); }
      if (out.length === 0) { out.push({ v: '', l: '(keine Variable)' }); }
      return out;
    }
    return [];
  }

  /* ---------- Block-Registry ----------
   * kind: 'hat' (Skriptanfang), 'stack' (Befehl), 'c' (Block mit Oeffnung),
   * 'c2' (mit sonst-Zweig), 'rep' (Wert-Bloeckchen, rund), 'bool' (Wahrheitswert).
   * parts: Textbausteine, {in:name} fuer Eingaben, {sub:i} fuer die Oeffnung.
   * inputs: name -> {type:'slot'|'bool'|'select', def, src}
   *   slot = runder Steckplatz mit eingebettetem Eingabefeld (Reporter einsteckbar)
   *   bool = sechseckiger Wahrheitswert-Steckplatz
   *   select = Auswahlmenue                                          */
  var BLOCKS = {
    /* Ereignisse */
    flag: { cat: 'events', kind: 'hat', inputs: {}, parts: ['Wenn ⚑ angeklickt wird'],
      help: 'Startet dieses Skript, sobald du oben auf die Fahne ▶ klickst.' },
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
      help: 'Die Sekunden seit dem letzten Start (Fahne) oder seit dem Zurücksetzen.' },
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

  /* ---------- Modell ---------- */
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

  function emptyProgram() {
    return {
      v: 1,
      vars: [],
      sprite: { x: 0, y: 0, dir: 90, costume: 'robot', size: 100, visible: true },
      scripts: []
    };
  }

  /* ---------- Serialisierung und Validierung (#121) ----------
   * Gespeichert wird ein JSON-Objekt: Version, Variablen, Startwerte der
   * Figur und alle Skripte mit Positionen. Beim Laden wird alles bereinigt:
   * unbekannte Bloecke entfallen, Felder werden gekuerzt, Grenzen
   * (Skript-Anzahl, Verschachtelungstiefe, Block-Budget) greifen. */
  var LIMITS = { scripts: 60, depth: 12, blocks: 800, fields: 200, vars: 30 };

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

  function serializeProgram(prog) {
    var out = {
      v: 1,
      vars: (prog.vars || []).slice(0),
      sprite: {
        x: num(prog.sprite.x), y: num(prog.sprite.y), dir: num(prog.sprite.dir),
        costume: String(prog.sprite.costume), size: num(prog.sprite.size),
        visible: !!prog.sprite.visible
      },
      scripts: []
    };
    var i;
    for (i = 0; i < prog.scripts.length; i++) {
      var s = prog.scripts[i];
      out.scripts.push({ id: s.id, x: num(s.x), y: num(s.y), top: serializeBlock(s.top) });
    }
    return out;
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
    if (data.sprite && typeof data.sprite === 'object') {
      if (typeof data.sprite.x !== 'undefined') { prog.sprite.x = clamp(num(data.sprite.x), -240, 240); }
      if (typeof data.sprite.y !== 'undefined') { prog.sprite.y = clamp(num(data.sprite.y), -180, 180); }
      if (typeof data.sprite.dir !== 'undefined') { prog.sprite.dir = num(data.sprite.dir); }
      if (typeof data.sprite.size !== 'undefined') { prog.sprite.size = clamp(num(data.sprite.size), 10, 400); }
      prog.sprite.visible = data.sprite.visible !== false;
      if (COSTUME_EMOJI[data.sprite.costume]) { prog.sprite.costume = String(data.sprite.costume); }
    }
    if (Object.prototype.toString.call(data.scripts) === '[object Array]') {
      var budget = { count: 0 };
      for (i = 0; i < data.scripts.length && i < LIMITS.scripts; i++) {
        var rs = data.scripts[i];
        if (!rs || typeof rs !== 'object') { continue; }
        var top = parseBlock(rs.top, budget, 0);
        if (!top) { continue; }
        prog.scripts.push({
          id: uid('s'), x: clamp(Math.round(num(rs.x)), -4000, 4000),
          y: clamp(Math.round(num(rs.y)), -4000, 4000), top: top
        });
      }
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
   * fuer die Steckplaetze. frame(now) schiebt jeden Thread bis zum naechsten
   * Yield - ganz ohne DOM, deshalb ist alles auch in Node testbar. */
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
        return function (rt) {
          var x = fa(rt), y = fb(rt);
          if (isNum(x) && isNum(y)) { return num(x) + num(y); }
          return String(x) + String(y);
        };
      case 'sub':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) { return num(fa(rt)) - num(fb(rt)); };
      case 'mul':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) { return num(fa(rt)) * num(fb(rt)); };
      case 'div':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) {
          var d = num(fb(rt));
          if (d === 0) { throw BlkErr('Division durch 0 ist nicht erlaubt.'); }
          return num(fa(rt)) / d;
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
        return function (rt) { return num(fa(rt)) > num(fb(rt)); };
      case 'lt':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) { return num(fa(rt)) < num(fb(rt)); };
      case 'eq':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) {
          var x = fa(rt), y = fb(rt);
          if (isNum(x) && isNum(y)) { return num(x) === num(y); }
          return String(x) === String(y);
        };
      case 'and':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) { return toBool(fa(rt)) && toBool(fb(rt)); };
      case 'or':
        fa = valFn(node.f.a); fb = valFn(node.f.b);
        return function (rt) { return toBool(fa(rt)) || toBool(fb(rt)); };
      case 'not':
        fa = valFn(node.f.a);
        return function (rt) { return !toBool(fa(rt)); };
      case 'var_value':
        a = String(node.f.name.v);
        return function (rt) { return Object.prototype.hasOwnProperty.call(rt.vars, a) ? rt.vars[a] : 0; };
      case 'xpos':
        return function (rt) { return Math.round(rt.sprite.x * 100) / 100; };
      case 'ypos':
        return function (rt) { return Math.round(rt.sprite.y * 100) / 100; };
      case 'direction':
        return function (rt) { return rt.sprite.dir; };
      case 'timer':
        return function (rt) { return (rt.now - rt.timerStart) / 1000; };
      case 'touching_edge':
        return function (rt) { return touchingEdge(rt); };
      case 'key_pressed':
        a = String(node.f.key.v);
        return function (rt) { return rt.keys[a] === true; };
      default:
        return function () { return 0; };
    }
  }

  function touchingEdge(rt) {
    var half = 24 * Math.max(10, rt.sprite.size) / 100;
    return Math.abs(rt.sprite.x) + half >= 240 || Math.abs(rt.sprite.y) + half >= 180;
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

  /* ---------- Befehle ausfuehren ---------- */
  function applyCall(rt, op, A) {
    var sp = rt.sprite;
    switch (op) {
      case 'move_steps':
        var rad = sp.dir * Math.PI / 180;
        moveBy(rt, Math.sin(rad) * num(A.steps(rt)), Math.cos(rad) * num(A.steps(rt)));
        break;
      case 'turn_right': sp.dir = (sp.dir + num(A.deg(rt))) % 360; break;
      case 'turn_left': sp.dir = (sp.dir - num(A.deg(rt))) % 360; break;
      case 'goto_xy': moveBy(rt, num(A.x(rt)) - sp.x, num(A.y(rt)) - sp.y); break;
      case 'point_dir': sp.dir = num(A.deg(rt)); break;
      case 'change_x': moveBy(rt, num(A.dx(rt)), 0); break;
      case 'set_x': moveBy(rt, num(A.x(rt)) - sp.x, 0); break;
      case 'change_y': moveBy(rt, 0, num(A.dy(rt))); break;
      case 'set_y': moveBy(rt, 0, num(A.y(rt)) - sp.y); break;
      case 'say': rt.bubble = { text: String(A.msg(rt)), until: null }; break;
      case 'show': sp.visible = true; break;
      case 'hide': sp.visible = false; rt.bubble = null; break;
      case 'costume': sp.costume = String(A.costume(rt)); break;
      case 'size_change': sp.size = clamp(sp.size + num(A.d(rt)), 10, 400); break;
      case 'size_set': sp.size = clamp(num(A.s(rt)), 10, 400); break;
      case 'stop_all': rt.stopped = true; break;
      case 'reset_timer': rt.timerStart = rt.now; break;
      case 'var_set': rt.vars[String(A.name(rt))] = A.value(rt); break;
      case 'var_change':
        var vn = String(A.name(rt));
        rt.vars[vn] = num(Object.prototype.hasOwnProperty.call(rt.vars, vn) ? rt.vars[vn] : 0) + num(A.d(rt));
        break;
      case 'pen_down': rt.pen.down = true; break;
      case 'pen_up': rt.pen.down = false; break;
      case 'pen_clear':
        rt.pen.segments = [];
        if (rt.penCtx) { rt.penCtx.clearRect(0, 0, 480, 360); }
        break;
      case 'pen_color': rt.pen.color = String(A.color(rt)); break;
    }
  }

  function moveBy(rt, dx, dy) {
    var sp = rt.sprite;
    var nx = sp.x + dx;
    var ny = sp.y + dy;
    if (rt.pen.down && (dx !== 0 || dy !== 0)) {
      var seg = { x1: sp.x, y1: sp.y, x2: nx, y2: ny, color: rt.pen.color };
      rt.pen.segments.push(seg);
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

  /* ---------- Laufzeit ---------- */
  function newRuntime(prog, opts) {
    opts = opts || {};
    var rt = {
      vars: {},
      sprite: {
        x: num(prog.sprite.x), y: num(prog.sprite.y), dir: num(prog.sprite.dir),
        costume: String(prog.sprite.costume), size: clamp(num(prog.sprite.size), 10, 400),
        visible: prog.sprite.visible !== false
      },
      bubble: null,
      pen: { down: false, color: '#1f2937', segments: [] },
      timerStart: 0,
      keys: {},
      threads: [],
      now: 0,
      stopped: false,
      errors: [],
      rand: opts.rand || null,
      penCtx: opts.penCtx || null,
      api: {}
    };
    var i;
    for (i = 0; i < prog.vars.length; i++) { rt.vars[prog.vars[i]] = 0; }

    function startScripts(op, keyName, now) {
      rt.now = now;
      var i2, s, head;
      for (i2 = 0; i2 < prog.scripts.length; i2++) {
        s = prog.scripts[i2];
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
        rt.threads.push({ scriptId: s.id, instrs: out, ip: 0, mem: [], waitT: null });
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
            case 'ltm': th.mem[ins.m] = num(th.mem[ins.c]) < num(ins.fn(rt)) ? 1 : 0; th.ip++; break;
            case 'incm': th.mem[ins.m] = num(th.mem[ins.m]) + 1; th.ip++; break;
            case 'jzm': th.ip = !th.mem[ins.m] ? ins.to : th.ip + 1; break;
            case 'jmpf': th.ip = !toBool(ins.fn(rt)) ? ins.to : th.ip + 1; break;
            case 'jmp': th.ip = ins.to; break;
            case 'yield': th.ip++; return 'alive';
            case 'wait':
              if (th.waitT === null) { th.waitT = rt.now + num(ins.fn(rt)) * 1000; }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              th.ip++;
              break;
            case 'sayfor':
              if (th.waitT === null) {
                rt.bubble = { text: String(ins.fn(rt)), until: rt.now + num(ins.fn2(rt)) * 1000 };
                th.waitT = rt.now + num(ins.fn2(rt)) * 1000;
              }
              if (rt.now < th.waitT) { return 'alive'; }
              th.waitT = null;
              if (rt.bubble && rt.bubble.until !== null && rt.now >= rt.bubble.until) { rt.bubble = null; }
              th.ip++;
              break;
            case 'call':
              applyCall(rt, ins.op, ins.A);
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
      if (rt.bubble && rt.bubble.until !== null && now > rt.bubble.until) { rt.bubble = null; }
      var alive = [];
      var i3;
      for (i3 = 0; i3 < rt.threads.length; i3++) {
        if (stepThread(rt.threads[i3]) === 'alive') { alive.push(rt.threads[i3]); }
      }
      rt.threads = alive;
    };
    rt.api.stopAll = function () {
      rt.stopped = true;
      rt.threads = [];
      rt.bubble = null;
    };
    rt.api.running = function () { return rt.threads.length > 0; };
    return {
      flag: rt.api.flag, press: rt.api.press, frame: rt.api.frame,
      stopAll: rt.api.stopAll, running: rt.api.running,
      vars: rt.vars, sprite: rt.sprite, pen: rt.pen, keys: rt.keys,
      errors: rt.errors, bubble: function () { return rt.bubble; },
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
      penCanvas: doc.getElementById('blk-pen'),
      ctrCanvas: doc.getElementById('blk-ctr'),
      saveForm: doc.getElementById('blocks-save-form'),
      jsonTa: doc.getElementById('blocks-json')
    };
    if (!els.script || !els.pal || !els.jsonTa) { return null; }

    var prog = parseProgram(els.jsonTa.value);
    var activeCat = 'events';
    var rt = null;
    var loopOn = false;
    var drag = null;

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
        }
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
            ev.stopPropagation ? ev.stopPropagation() : null;
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

    /* ---------- Skriptflaeche ---------- */
    function renderScripts() {
      while (els.script.children.length > 0) { els.script.removeChild(els.script.children[0]); }
      var i;
      for (i = 0; i < prog.scripts.length; i++) {
        var s = prog.scripts[i];
        var chain = el('div', 'blk-chain');
        chain.setAttribute('data-script', s.id);
        chain.style.left = s.x + 'px';
        chain.style.top = s.y + 'px';
        appendChain(chain, s.top, {});
        els.script.appendChild(chain);
      }
    }

    /* ---------- Palette ---------- */
    function renderCats() {
      while (els.cats.children.length > 0) { els.cats.removeChild(els.cats.children[0]); }
      var i;
      for (i = 0; i < CATS.length; i++) {
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

    /* ---------- Bloecke suchen / loeschen ---------- */
    function findBlockPos(id) {
      var result = null;
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
      for (i = 0; i < prog.scripts.length; i++) {
        scanChain(prog.scripts[i].top, prog.scripts[i], 'top', null, 0);
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
      var i;
      for (i = 0; i < prog.scripts.length; i++) {
        if (prog.scripts[i] === s) { prog.scripts.splice(i, 1); break; }
      }
      renderScripts();
      autosaveSoon();
    }

    /* Drag&Drop: pointerdown nimmt einen Block aus der Palette (frische
     * Instanz) oder loest ihn aus dem Skript, pointerup dockt an: unter
     * einen Block, in eine Oeffnung, in einen Steckplatz - oder legt ein
     * neues Skript an. Loslassen ueber der Palette loescht. */
    function walkUp(target, pred) {
      var n = target;
      while (n && n !== doc.body) {
        if (pred(n)) { return n; }
        n = n.parent || n.parentNode;
      }
      return null;
    }

    function onPointerDown(ev) {
      var target = ev.target;
      if (!target) { return; }
      var inField = walkUp(target, function (n) {
        return n.tagName === 'INPUT' || n.tagName === 'SELECT' || n.tagName === 'BUTTON';
      });
      if (inField) { return; }
      var bel = walkUp(target, function (n) {
        return n.classList && n.classList.contains('blk');
      });
      if (!bel) { return; }
      ev.preventDefault ? ev.preventDefault() : null;
      var op = bel.getAttribute('data-pal-op');
      if (op) {
        drag = { node: newBlock(op), origin: 'palette' };
      } else {
        var id = bel.getAttribute('data-id');
        if (!id) { return; }
        var pos = findBlockPos(id);
        if (!pos) { return; }
        if (pos.parentKind === 'top') { pos.script.top = null; removeScriptQuiet(pos.script); }
        else if (pos.parentKind === 'next') { pos.parentNode.next = null; }
        else if (pos.parentKind === 'sub') { pos.parentNode.sub[pos.subIdx] = null; }
        else if (pos.parentKind === 'field') {
          pos.parentNode.f[pos.subIdx] = { v: fieldDefault(BLOCKS[pos.parentNode.op].inputs[pos.subIdx]) };
        }
        drag = { node: pos.node, origin: 'script' };
        renderScripts();
      }
      doc.addEventListener('pointermove', onPointerMove);
      doc.addEventListener('pointerup', onPointerUp);
    }

    function removeScriptQuiet(s) {
      var i;
      for (i = 0; i < prog.scripts.length; i++) {
        if (prog.scripts[i] === s) { prog.scripts.splice(i, 1); return; }
      }
    }

    function onPointerMove(ev) {
      if (!drag) { return; }
      drag.x = ev.clientX;
      drag.y = ev.clientY;
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

    function onPointerUp(ev) {
      doc.removeEventListener('pointermove', onPointerMove);
      doc.removeEventListener('pointerup', onPointerUp);
      if (!drag) { return; }
      var node = drag.node;
      var origin = drag.origin;
      var px = ev.clientX, py = ev.clientY;
      drag = null;
      /* Ueber der Palette loslassen: aus der Palette passiert nichts, aus
       * dem Skripp nimmt es den Block endgueltig weg (= loeschen). */
      var palR = rectOf(els.pal);
      if (palR && px >= palR.left && px <= palR.left + palR.width && py >= palR.top && py <= palR.top + palR.height) {
        renderScripts();
        autosaveSoon();
        return;
      }
      var kind = BLOCKS[node.op].kind;

      if (kind === 'rep' || kind === 'bool') {
        /* Reporter: in den naechstgelegenen Steckplatz einstecken */
        var bestSlot = null, bestD = 56;
        var slots = els.script.querySelectorAll('.blk-slot, .blk-bslot');
        var i;
        for (i = 0; i < slots.length; i++) {
          var r = rectOf(slots[i]);
          if (!r) { continue; }
          var d = dist(px, py, r);
          if (d < bestD) { bestD = d; bestSlot = slots[i]; }
        }
        if (bestSlot) {
          var hostEl = walkUp(bestSlot, function (n) { return n.classList && n.classList.contains('blk') && n.getAttribute('data-id'); });
          if (hostEl) {
            var hostId = hostEl.getAttribute('data-id');
            var pos = findBlockPos(hostId);
            if (pos) {
              var fname = bestSlot.getAttribute('data-f');
              var want = BLOCKS[pos.node.op].inputs[fname];
              var fits = want && ((kind === 'bool' && want.type === 'bool') || (kind === 'rep' && want.type === 'slot'));
              if (fits) { pos.node.f[fname] = { b: node }; }
            }
          }
        }
        renderScripts();
        autosaveSoon();
        return;
      }

      /* Stapel/C-Block/Hut: andocken unter einen Block oder in eine Oeffnung */
      var best = null, bestScore = 64;
      if (kind !== 'hat') {
        var chains = els.script.querySelectorAll('.blk');
        var j;
        for (j = 0; j < chains.length; j++) {
          var be = chains[j];
          if (!be.getAttribute('data-id')) { continue; }
          var br = rectOf(be);
          if (!br) { continue; }
          var d2 = Math.sqrt((px - (br.left + 14)) * (px - (br.left + 14)) + (py - br.bottom) * (py - br.bottom));
          if (d2 < bestScore) { bestScore = d2; best = { type: 'below', id: be.getAttribute('data-id') }; }
        }
        var subs = els.script.querySelectorAll('.blk-sub');
        var k;
        for (k = 0; k < subs.length; k++) {
          var sr = rectOf(subs[k]);
          if (!sr) { continue; }
          var d3 = dist(px, py, sr);
          if (d3 < bestScore) { bestScore = d3; best = { type: 'sub', el: subs[k] }; }
        }
      }
      if (best) {
        if (best.type === 'below') {
          var tpos = findBlockPos(best.id);
          if (tpos) {
            var tail = node;
            while (tail.next) { tail = tail.next; }
            tail.next = tpos.node.next;
            tpos.node.next = node;
          }
        } else if (best.type === 'sub') {
          var subEl2 = best.el;
          var host = subEl2.parent || null;
          while (host && !(host.classList && host.classList.contains('blk') && host.getAttribute('data-id'))) { host = host.parent || null; }
          if (host) {
            var hpos = findBlockPos(host.getAttribute('data-id'));
            if (hpos) {
              var idx = parseInt(subEl2.getAttribute('data-sub'), 10) || 0;
              var tail2 = node;
              while (tail2.next) { tail2 = tail2.next; }
              tail2.next = hpos.node.sub[idx];
              hpos.node.sub[idx] = node;
            }
          }
        }
      } else {
        /* freie Flaeche: neues Skript anlegen */
        var wrapR = rectOf(els.script) || { left: 0, top: 0 };
        prog.scripts.push({
          id: uid('s'), x: clamp(Math.round(px - wrapR.left - 12), 0, 4000),
          y: clamp(Math.round(py - wrapR.top - 10), 0, 4000), top: node
        });
      }
      renderScripts();
      autosaveSoon();
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

    /* ---------- Buehne (#119) ---------- */
    function drawStage() {
      if (!els.ctrCanvas || typeof els.ctrCanvas.getContext !== 'function') { return; }
      var ctx = els.ctrCanvas.getContext('2d');
      if (!ctx) { return; }
      ctx.clearRect(0, 0, 480, 360);
      if (!rt || !rt.sprite.visible) { return; }
      var sp = rt.sprite;
      ctx.save();
      ctx.translate(240 + sp.x, 180 - sp.y);
      ctx.rotate(sp.dir * Math.PI / 180);
      ctx.font = Math.round(40 * sp.size / 100) + 'px system-ui, "Segoe UI Symbol", sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(COSTUME_EMOJI[sp.costume] || '🤖', 0, 0);
      ctx.restore();
      var bub = rt.bubble ? rt.bubble() : null;
      if (rt.bubble && bub) {
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
      var bub = rt.bubble();
      var busy = rt.running() || (bub && bub.until !== null && bub.until > ts);
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

    function inEditable(target) {
      return walkUp(target, function (n) {
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
      var target = ev.target;
      if (!target) { return; }
      /* nur wenn der Klick aus Palette oder Skriptflaeche kommt */
      var inArea = walkUp(target, function (n) { return n === els.pal || n === els.script; });
      if (!inArea) { return; }
      onPointerDown(ev);
    });
    if (els.saveForm) {
      els.saveForm.addEventListener('submit', function () {
        els.jsonTa.value = programJson();
      });
    }

    renderCats();
    renderPalette();
    renderHelp();
    renderScripts();
    /* Buehne einmal leer zeichnen, damit das Layout steht */
    drawStage();

    var ui = {
      program: prog,
      renderScripts: renderScripts,
      renderPalette: renderPalette,
      renderHelp: renderHelp,
      addVariable: addVariable,
      saveNow: saveNow,
      autosaveSoon: autosaveSoon,
      programJson: programJson,
      addBlock: function (op, x, y) {
        prog.scripts.push({ id: uid('s'), x: x || 24, y: y || 24, top: newBlock(op) });
        renderScripts();
        autosaveSoon();
        return prog.scripts[prog.scripts.length - 1];
      },
      deleteBlockTree: deleteBlockTree,
      isDragging: function () { return drag !== null; },
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
    newBlock: newBlock,
    emptyProgram: emptyProgram,
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
