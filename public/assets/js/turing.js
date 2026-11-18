/**
 * InfoCraft - Turingmaschinen-Werkstatt (#165)
 *
 * Turingmaschinen zeichnen, Woerter auf dem Band pruefen, Schrittliste
 * mit Bandfenster und Lese-/Schreibkopf. Die Engine ist DOM-frei
 * gehalten, damit sie in den UI-Tests ohne Browser laeuft.
 * Lehrplanbezug Informatik 13: Berechenbarkeit.
 *
 * Uebergangsbeschriftung "a / b, R": lese a, schreibe b, gehe nach
 * rechts (R rechts, L links, N stehen). Das leere Feld ist das
 * Blanksymbol "_".
 *
 * Node-Tests: module.exports; Browser: window.IC_TM.
 *
 * Undo/Redo, Zoom, PNG-Export und Abspielen kommen aus dem gemeinsamen
 * Werkzeugkasten amt-tools.js (#173): gleiche Funktionen einmal umgesetzt,
 * modular in beiden Automaten-Werkstaetten eingesetzt.
 *
 * @version 0.18.0
 * @license MIT (InfoCraft)
 */
(function () {
  'use strict';

  var R = 26;
  var BLANK = '_';
  var DEFAULT_PAL = {
    paper: '#ffffff', ink: '#1e293b', accent: '#0f766e', accentSoft: '#ccfbf1',
    hl: '#fde68a', font: 'system-ui, sans-serif'
  };

  /* Gemeinsamer Werkzeugkasten (#173): im Browser vor turing.js geladen,
   * in den Node-Tests ueber require. Ohne Kasten laeuft die Werkstatt
   * einfach ohne die zusaetzlichen Knoepfe weiter. */
  var TOOLS = (typeof window !== 'undefined' && window.IC_AMT_TOOLS)
    ? window.IC_AMT_TOOLS
    : ((typeof require === 'function') ? require('./amt-tools.js') : null);

  /* ---------- Hilfsfunktionen (DOM-frei) ---------- */

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function wordChars(word) {
    var w = String(word == null ? '' : word).trim();
    if (w === '' || w === 'ε' || w === '&epsilon;') { return []; }
    return Array.from(w);
  }

  /* ---------- Datenmodell (DOM-frei) ---------- */

  function emptyTM() {
    return { alphabet: [], blank: BLANK, states: [], rules: [] };
  }

  function exampleTM() {
    /* Inkrement einer Binaerzahl: bis zum rechten Rand laufen, dann
     * von rechts die Einsen uebertragen (1011 -> 1100). */
    return {
      alphabet: ['0', '1'],
      blank: BLANK,
      states: [
        { id: 'q0', name: 'q0', x: 140, y: 200, start: true, accept: false },
        { id: 'q1', name: 'q1', x: 330, y: 200, start: false, accept: false },
        { id: 'qf', name: 'qf', x: 520, y: 200, start: false, accept: true }
      ],
      rules: [
        { id: 'r1', from: 'q0', to: 'q0', read: '0', write: '0', move: 'R' },
        { id: 'r2', from: 'q0', to: 'q0', read: '1', write: '1', move: 'R' },
        { id: 'r3', from: 'q0', to: 'q1', read: BLANK, write: BLANK, move: 'L' },
        { id: 'r4', from: 'q1', to: 'q1', read: '1', write: '0', move: 'L' },
        { id: 'r5', from: 'q1', to: 'qf', read: '0', write: '1', move: 'R' },
        { id: 'r6', from: 'q1', to: 'qf', read: BLANK, write: '1', move: 'R' }
      ]
    };
  }

  function stateById(T, id) {
    var i;
    for (i = 0; i < T.states.length; i++) { if (T.states[i].id === id) { return T.states[i]; } }
    return null;
  }

  function normalizeTM(d) {
    if (!d || !Array.isArray(d.states) || !Array.isArray(d.rules)) { return null; }
    var states = d.states.filter(function (s) { return s && s.id != null; }).map(function (s, i) {
      return {
        id: String(s.id),
        name: String(s.name != null ? s.name : ('q' + i)),
        x: Number(s.x) || 100 + i * 120,
        y: Number(s.y) || 200,
        start: !!s.start,
        accept: !!(s.accept != null ? s.accept : (s.final != null ? s.final : s.end))
      };
    });
    var ids = new Set(states.map(function (s) { return s.id; }));
    var firstStart = false;
    states.forEach(function (s) {
      if (s.start) {
        if (firstStart) { s.start = false; }
        firstStart = true;
      }
    });
    var k = 0;
    var rules = d.rules.filter(function (r) {
      return r && ids.has(String(r.from)) && ids.has(String(r.to));
    }).map(function (r) {
      k += 1;
      var mv = String(r.move != null ? r.move : 'R').toUpperCase();
      if (mv !== 'R' && mv !== 'L' && mv !== 'N') { mv = 'R'; }
      return {
        id: String(r.id != null ? r.id : ('r' + k)),
        from: String(r.from), to: String(r.to),
        read: String(r.read != null ? r.read : BLANK).slice(0, 1),
        write: String(r.write != null ? r.write : r.read).slice(0, 1),
        move: mv
      };
    });
    var alpha = [];
    if (Array.isArray(d.alphabet)) { d.alphabet.forEach(function (c) { if (alpha.indexOf(String(c)) < 0) { alpha.push(String(c)); } }); }
    rules.forEach(function (r) {
      if (r.read !== BLANK && alpha.indexOf(r.read) < 0) { alpha.push(r.read); }
      if (r.write !== BLANK && alpha.indexOf(r.write) < 0) { alpha.push(r.write); }
    });
    alpha.sort();
    return { alphabet: alpha, blank: BLANK, states: states, rules: rules };
  }

  function parseTM(text) {
    var data = null;
    try { data = JSON.parse(String(text)); } catch (e) { data = null; }
    return normalizeTM(data) || emptyTM();
  }

  function serializeTM(T) {
    var d = JSON.parse(JSON.stringify(T));
    d.format = 'turingmaschine';
    d.version = 1;
    return d;
  }

  /* ---------- Lauf (DOM-frei, #165) ----------
   * Das Band liegt als Map Position -> Zeichen, der Kopf ist eine
   * Position. Gefahren wird deterministisch: die erste passende Regel
   * gewinnt. Die Schrittliste zeigt je Schritt Zustand, Bandfenster
   * und Kopfposition. */

  function ruleFor(T, state, ch) {
    var i;
    for (i = 0; i < T.rules.length; i++) {
      var r = T.rules[i];
      if (r.from === state && r.read === ch) { return r; }
    }
    return null;
  }

  function tmRun(T, word, opts) {
    var blank = T.blank || BLANK;
    var maxSteps = (opts && opts.maxSteps) || 500;
    var winHalf = (opts && opts.window) || 8;
    var syms = wordChars(word);
    var tape = {};
    var i;
    for (i = 0; i < syms.length; i++) { tape[i] = syms[i]; }
    var startState = null;
    for (i = 0; i < T.states.length; i++) { if (T.states[i].start) { startState = T.states[i]; break; } }
    if (!startState && T.states.length) { startState = T.states[0]; }
    if (!startState) { return { accepted: false, halted: false, steps: [], reason: 'kein Zustand' }; }
    var st = startState.id;
    var head = syms.length > 0 ? 0 : 0;
    var steps = [];
    var accepted = false;
    var halted = false;
    var n = 0;
    while (n < maxSteps) {
      var ch = tape[head] != null ? tape[head] : blank;
      /* Bandfenster um den Kopf */
      var cells = [];
      var p;
      for (p = head - winHalf; p <= head + winHalf; p++) {
        cells.push({ pos: p, ch: tape[p] != null ? tape[p] : blank, head: p === head });
      }
      var stObj = stateById(T, st);
      steps.push({
        state: stObj ? stObj.name : st,
        cells: cells,
        window: cells.map(function (c) { return c.ch; }).join(''),
        headOff: winHalf
      });
      if (stObj && stObj.accept) { accepted = true; halted = true; break; }
      var r = ruleFor(T, st, ch);
      if (!r) { halted = true; break; }
      tape[head] = r.write;
      if (r.move === 'L') { head -= 1; }
      else if (r.move === 'R') { head += 1; }
      st = r.to;
      n += 1;
    }
    return {
      accepted: accepted,
      halted: halted,
      steps: steps,
      truncated: !halted,
      tape: tape,
      reason: accepted ? 'akzeptiert' : (halted ? 'abgelehnt (keine Regel)' : 'Schrittlimit erreicht')
    };
  }

  function analyseTM(T) {
    var sigma = [];
    var i;
    for (i = 0; i < T.alphabet.length; i++) { if (sigma.indexOf(T.alphabet[i]) < 0) { sigma.push(T.alphabet[i]); } }
    for (i = 0; i < T.rules.length; i++) {
      if (T.rules[i].read !== BLANK && sigma.indexOf(T.rules[i].read) < 0) { sigma.push(T.rules[i].read); }
      if (T.rules[i].write !== BLANK && sigma.indexOf(T.rules[i].write) < 0) { sigma.push(T.rules[i].write); }
    }
    sigma.sort();
    var finals = [];
    for (i = 0; i < T.states.length; i++) { if (T.states[i].accept) { finals.push(T.states[i].name); } }
    /* Determinismus: (Zustand, gelesen) darf nur eine Regel haben. */
    var seen = {};
    var nd = [];
    for (i = 0; i < T.rules.length; i++) {
      var key = T.rules[i].from + ',' + T.rules[i].read;
      if (seen[key]) { nd.push('(' + key + ')'); } else { seen[key] = true; }
    }
    return { sigma: sigma, finals: finals, blank: BLANK, nd: nd };
  }

  /* ---------- Szene (SVG-Markup, DOM-frei) ---------- */

  function ruleLabel(r) {
    return eps(r.read) + ' / ' + eps(r.write) + ', ' + r.move;
  }
  function eps(v) { return String(v || '') === '' ? 'ε' : String(v); }

  function edgeGeom(T, r) {
    var a = stateById(T, r.from), b = stateById(T, r.to);
    if (!a || !b) { return null; }
    var dx = b.x - a.x, dy = b.y - a.y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    var ux = dx / len, uy = dy / len;
    if (a === b) {
      return { d: 'M' + a.x + ' ' + (a.y - R - 2) + ' C ' + (a.x - 46) + ' ' + (a.y - R - 40) + ', ' + (a.x + 46) + ' ' + (a.y - R - 40) + ', ' + a.x + ' ' + (a.y - R - 2), lx: a.x, ly: a.y - R - 44 };
    }
    var mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
    var nx = -uy, ny = ux;
    return {
      d: 'M' + (a.x + ux * R) + ' ' + (a.y + uy * R) + ' L' + (b.x - ux * (R + 3)) + ' ' + (b.y - uy * (R + 3)),
      lx: mx + nx * 16, ly: my + ny * 16 - 8
    };
  }

  function sceneMarkup(T, opts) {
    opts = opts || {};
    var Pal = opts.P || DEFAULT_PAL;
    var interactive = !!opts.interactive;
    var edges = '', hits = '', labels = '', nodes = '';
    T.rules.forEach(function (r) {
      var g = edgeGeom(T, r);
      if (!g) { return; }
      var isSel = opts.sel && opts.sel.kind === 'rule' && opts.sel.id === r.id;
      var col = isSel ? Pal.accent : Pal.ink;
      edges += '<path d="' + g.d + '" fill="none" stroke="' + col + '" stroke-width="' + (isSel ? 2.8 : 1.7) + '" marker-end="url(#' + (isSel ? 'aSel' : 'aInk') + ')"/>';
      var dk = interactive ? ' data-kind="rule" data-id="' + r.id + '"' : '';
      if (interactive) {
        hits += '<path d="' + g.d + '" fill="none" stroke="transparent" stroke-width="18"' + dk + ' style="cursor:pointer"/>';
      }
      labels += '<text x="' + g.lx + '" y="' + g.ly + '" text-anchor="middle" dominant-baseline="central" font-family="' + Pal.font + '" font-size="14" font-weight="' + (isSel ? 700 : 400) + '" fill="' + col + '" stroke="' + Pal.paper + '" stroke-width="6" stroke-linejoin="round" paint-order="stroke"' + dk + ' style="cursor:pointer">' + esc(ruleLabel(r)) + '</text>';
    });
    T.states.forEach(function (s) {
      var isSel = opts.sel && opts.sel.kind === 'state' && opts.sel.id === s.id;
      var stroke = isSel ? Pal.accent : Pal.ink;
      var dk = interactive ? ' data-kind="state" data-id="' + s.id + '" style="cursor:pointer"' : '';
      var g = '<g' + dk + '>';
      if (isSel) { g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R + 8) + '" fill="' + Pal.accentSoft + '"/>'; }
      if (opts.pending === s.id) { g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R + 8) + '" fill="none" stroke="' + Pal.accent + '" stroke-width="2" stroke-dasharray="4 4"/>'; }
      if (s.start) { g += '<path d="M' + (s.x - R - 40) + ' ' + s.y + ' L' + (s.x - R - 1) + ' ' + s.y + '" stroke="' + Pal.ink + '" stroke-width="2" marker-end="url(#aInk)"/>'; }
      g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + R + '" fill="' + Pal.paper + '" stroke="' + stroke + '" stroke-width="' + (isSel ? 3 : 2) + '"/>';
      if (s.accept) { g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R - 5) + '" fill="none" stroke="' + stroke + '" stroke-width="1.7"/>'; }
      g += '<text x="' + s.x + '" y="' + s.y + '" text-anchor="middle" dominant-baseline="central" font-family="' + Pal.font + '" font-size="16" font-weight="700" fill="' + Pal.ink + '">' + esc(s.name) + '</text></g>';
      nodes += g;
    });
    return hits + edges + nodes + labels;
  }

  function defsMarkup(Pal) {
    return '<marker id="aInk" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="' + Pal.ink + '"/></marker>' +
      '<marker id="aSel" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="' + Pal.accent + '"/></marker>';
  }

  function bbox(T) {
    if (!T.states.length) { return null; }
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    T.states.forEach(function (s) {
      x0 = Math.min(x0, s.x); y0 = Math.min(y0, s.y);
      x1 = Math.max(x1, s.x); y1 = Math.max(y1, s.y);
    });
    var loops = T.rules.some(function (r) { return r.from === r.to; });
    return { x0: x0 - R - 52, y0: y0 - R - (loops ? 62 : 30), x1: x1 + R + 30, y1: y1 + R + 34 };
  }

  function exportSvgString(T) {
    var b = bbox(T) || { x0: 0, y0: 0, x1: 600, y1: 400 };
    var Pal = DEFAULT_PAL, pad = 20;
    var x = b.x0 - pad, y = b.y0 - pad, w = b.x1 - b.x0 + 2 * pad, h = b.y1 - b.y0 + 2 * pad;
    return {
      w: w, h: h,
      str: '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="' + x + ' ' + y + ' ' + w + ' ' + h + '"><defs>' + defsMarkup(Pal) + '</defs><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="#ffffff"/>' + sceneMarkup(T, { interactive: false }) + '</svg>'
    };
  }

  /* ---------- Editor (attach) ---------- */

  function attach(doc) {
    var root = doc.getElementById('tm-ide');
    if (!root) { return null; }
    var els = {
      canvas: doc.getElementById('tm-canvas'),
      hint: doc.getElementById('tm-hint'),
      pop: doc.getElementById('tm-pop'),
      popIn: doc.getElementById('tm-pop-in'),
      popOk: doc.getElementById('tm-pop-ok'),
      sel: doc.getElementById('tm-sel'),
      word: doc.getElementById('tm-word'),
      run: doc.getElementById('tm-run'),
      step: doc.getElementById('tm-step'),
      reset: doc.getElementById('tm-reset'),
      out: doc.getElementById('tm-out'),
      undo: doc.getElementById('tm-undo'),
      redo: doc.getElementById('tm-redo'),
      zoomIn: doc.getElementById('tm-zoom-in'),
      zoomOut: doc.getElementById('tm-zoom-out'),
      fit: doc.getElementById('tm-fit'),
      png: doc.getElementById('tm-png'),
      play: doc.getElementById('tm-play'),
      batchIn: doc.getElementById('tm-batch-in'),
      batchBtn: doc.getElementById('tm-batch-btn'),
      batchOut: doc.getElementById('tm-batch-out'),
      tuple: doc.getElementById('tm-tuple'),
      delta: doc.getElementById('tm-delta'),
      form: doc.getElementById('tm-save-form'),
      json: doc.getElementById('tm-json'),
      saveInfo: doc.getElementById('tm-saveinfo')
    };
    var T = parseTM(els.json ? els.json.value : '');
    var mode = 'select';
    var sel = null;
    var pending = null;
    var stateSeq = T.states.length;
    var saveTimer = null;
    var lastRun = null;
    var runPos = 0;
    /* Ansicht (Zoom/Einpassen/Schieben) und Historie (Undo/Redo) aus dem
     * gemeinsamen Werkzeugkasten (#173). */
    var view = (TOOLS && els.canvas) ? TOOLS.makeView(els.canvas, { x: 0, y: 0, w: 900, h: 560 }) : null;
    var hist = (TOOLS && els.json) ? TOOLS.makeHistory({
      snapshot: function () { return JSON.stringify(serializeTM(T)); },
      restore: function (snap) {
        T = parseTM(snap);
        sel = null;
        stateSeq = T.states.length;
        render();
        renderSel();
        if (els.json) { els.json.value = snap; }
        pushSave();
      }
    }) : null;
    var player = null;
    if (hist) { hist.commit(); }

    function hint(text) { if (els.hint) { els.hint.textContent = text || ''; } }

    function render() {
      if (!els.canvas) { return; }
      els.canvas.innerHTML = defsMarkup(DEFAULT_PAL) + sceneMarkup(T, {
        interactive: true, sel: sel,
        pending: pending && pending.kind === 'state' ? pending.id : null
      });
      renderDesc();
    }

    /* Formale Beschreibung (#173): 7-Tupel der Turingmaschine und die
     * Delta-Tabelle aus dem aktuellen Bandmodell. */
    function renderDesc() {
      if (!els.tuple && !els.delta) { return; }
      var an = analyseTM(T);
      var names = [];
      var i;
      for (i = 0; i < T.states.length; i++) { names.push(T.states[i].name); }
      var gamma = [];
      for (i = 0; i < an.sigma.length; i++) { if (gamma.indexOf(an.sigma[i]) < 0) { gamma.push(an.sigma[i]); } }
      if (gamma.indexOf(T.blank || BLANK) < 0) { gamma.push(T.blank || BLANK); }
      var start = '';
      for (i = 0; i < T.states.length; i++) { if (T.states[i].start) { start = T.states[i].name; break; } }
      if (els.tuple) {
        els.tuple.textContent = 'M = (Q, Σ, Γ, δ, q0, _, E) mit'
          + '  Q = {' + names.join(', ') + '}'
          + '  Σ = {' + an.sigma.join(', ') + '}'
          + '  Γ = {' + gamma.join(', ') + '}'
          + '  q0 = ' + (start || '–')
          + '  _ = ' + (T.blank || BLANK)
          + '  E = {' + an.finals.join(', ') + '}'
          + (an.nd.length ? '  Achtung: nicht deterministisch bei ' + an.nd.join(', ') : '  deterministisch');
      }
      if (els.delta) {
        while (els.delta.firstChild) { els.delta.removeChild(els.delta.firstChild); }
        if (TOOLS && TOOLS.deltaTable) {
          var rows = [];
          for (i = 0; i < T.rules.length; i++) {
            var r = T.rules[i];
            var fromName = stateById(T, r.from);
            var toName = stateById(T, r.to);
            rows.push([
              fromName ? fromName.name : r.from,
              r.read === (T.blank || BLANK) ? '_' : r.read,
              r.write === (T.blank || BLANK) ? '_' : r.write,
              r.move,
              toName ? toName.name : r.to
            ]);
          }
          els.delta.appendChild(TOOLS.deltaTable(doc, ['von', 'liest', 'schreibt', 'geht', 'nach'], rows));
        }
      }
    }

    /* Autospeichern: Jede Modell-Aenderung geht in die Historie (Undo/Redo,
     * #173) und nach kurzer Ruhe auf den Server. */
    function pushSave() {
      if (saveTimer) { clearTimeout(saveTimer); }
      saveTimer = setTimeout(function () {
        saveTimer = null;
        if (els.form && typeof fetch === 'function') {
          var action = els.form.getAttribute('action') || '';
          var body = 'content=' + encodeURIComponent(els.json.value);
          var csrf = els.form.querySelector('input[name="csrf"]');
          if (csrf) { body += '&csrf=' + encodeURIComponent(csrf.value); }
          var title = els.form.querySelector('input[name="title"]');
          if (title) { body += '&title=' + encodeURIComponent(title.value); }
          fetch(action, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, keepalive: true })
            .then(function () { if (els.saveInfo) { els.saveInfo.textContent = '✓ automatisch gespeichert'; els.saveInfo.hidden = false; } })
            .catch(function () { if (els.saveInfo) { els.saveInfo.textContent = '⚠ Speichern fehlgeschlagen'; els.saveInfo.hidden = false; } });
        }
      }, 900);
    }

    function autosave() {
      if (!els.json) { return; }
      if (hist) { hist.commit(); }
      els.json.value = JSON.stringify(serializeTM(T));
      pushSave();
    }

    function addState(x, y) {
      stateSeq += 1;
      var id = 'q' + stateSeq;
      var st = { id: id, name: id, x: x || 120 + T.states.length * 90, y: y || 200, start: T.states.length === 0, accept: false };
      T.states.push(st);
      sel = { kind: 'state', id: id };
      render(); renderSel(); autosave();
      return st;
    }

    function delState(id) {
      T.states = T.states.filter(function (s) { return s.id !== id; });
      T.rules = T.rules.filter(function (r) { return r.from !== id && r.to !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    function delRule(id) {
      T.rules = T.rules.filter(function (r) { return r.id !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    /* Beschriftung "a / b, R" oder "a,b,R". */
    function parseRuleLabel(label) {
      var raw = String(label || '').replace(/\s+/g, '');
      var m = /^(.)(?:\/(.))?,?([LRN])$/.exec(raw);
      if (!m) { return { read: 'a', write: 'a', move: 'R' }; }
      return {
        read: m[1] === 'ε' || m[1] === '' ? BLANK : m[1],
        write: m[2] === undefined ? m[1] : (m[2] === 'ε' || m[2] === '' ? BLANK : m[2]),
        move: m[3]
      };
    }

    function addRule(fromId, toId, label) {
      var p = parseRuleLabel(label);
      var r = { id: 'r' + (T.rules.length + 1) + '_' + Date.now() % 100000, from: fromId, to: toId, read: p.read, write: p.write, move: p.move };
      T.rules.push(r);
      sel = { kind: 'rule', id: r.id };
      render(); renderSel(); autosave();
      return r;
    }

    function renderSel() {
      if (!els.sel) { return; }
      els.sel.textContent = '';
      if (!sel) { els.sel.textContent = 'Nichts ausgewählt. Werkzeuge: Zustand anlegen, Regel anlegen (erst Quelle, dann Ziel), Löschen.'; return; }
      if (sel.kind === 'state') {
        var st = stateById(T, sel.id);
        if (!st) { sel = null; return; }
        var lab = doc.createElement('label');
        lab.className = 'field';
        var sp = doc.createElement('span');
        sp.textContent = 'Name';
        var inp = doc.createElement('input');
        inp.className = 'inp mono';
        inp.value = st.name;
        inp.addEventListener('change', function () { st.name = String(inp.value).slice(0, 8) || st.name; render(); autosave(); });
        lab.appendChild(sp); lab.appendChild(inp);
        els.sel.appendChild(lab);
        var chk = doc.createElement('label');
        chk.className = 'check';
        var cb = doc.createElement('input');
        cb.type = 'checkbox';
        cb.checked = st.accept;
        cb.addEventListener('change', function () { st.accept = cb.checked; render(); autosave(); });
        var sp2 = doc.createElement('span');
        sp2.textContent = 'Endzustand';
        chk.appendChild(cb); chk.appendChild(sp2);
        els.sel.appendChild(chk);
        var del = doc.createElement('button');
        del.className = 'btn'; del.type = 'button'; del.textContent = 'Zustand löschen';
        del.addEventListener('click', function () { delState(st.id); });
        els.sel.appendChild(del);
      } else {
        var r = null, i;
        for (i = 0; i < T.rules.length; i++) { if (T.rules[i].id === sel.id) { r = T.rules[i]; break; } }
        if (!r) { sel = null; return; }
        var lab2 = doc.createElement('label');
        lab2.className = 'field';
        var sp3 = doc.createElement('span');
        sp3.textContent = 'Regel (lese / schreibe, R|L|N)';
        var inp2 = doc.createElement('input');
        inp2.className = 'inp mono';
        inp2.value = r.read + ' / ' + r.write + ', ' + r.move;
        inp2.addEventListener('change', function () {
          var p = parseRuleLabel(inp2.value);
          r.read = p.read; r.write = p.write; r.move = p.move;
          render(); autosave();
        });
        lab2.appendChild(sp3); lab2.appendChild(inp2);
        els.sel.appendChild(lab2);
        var delR = doc.createElement('button');
        delR.className = 'btn'; delR.type = 'button'; delR.textContent = 'Regel löschen';
        delR.addEventListener('click', function () { delRule(r.id); });
        els.sel.appendChild(delR);
      }
    }

    function hitTarget(ev) {
      var node = ev.target;
      while (node && node !== els.canvas) {
        if (node.getAttribute) {
          var kind = node.getAttribute('data-kind');
          if (kind) { return { kind: kind, id: node.getAttribute('data-id') }; }
        }
        node = node.parentNode;
      }
      return null;
    }

    if (els.canvas) {
      /* Schieben der Flaeche im Auswahlmodus (#173). */
      var panFrom = null;
      els.canvas.addEventListener('pointerdown', function (ev) {
        var hit = hitTarget(ev);
        if (mode === 'state') {
          var pt = view ? view.toWorld(Number(ev.clientX) || 0, Number(ev.clientY) || 0) : { x: Number(ev.clientX) || null, y: Number(ev.clientY) || null };
          addState(pt.x, pt.y);
          hint('Zustand angelegt.');
          return;
        }
        if (mode === 'delete') { if (hit) { if (hit.kind === 'state') { delState(hit.id); } else { delRule(hit.id); } } return; }
        if (mode === 'rule') {
          if (hit && hit.kind === 'state') {
            if (!pending) { pending = { kind: 'state', id: hit.id }; hint('Zielzustand anklicken.'); render(); }
            else if (pending.id !== hit.id) {
              var fromId = pending.id;
              pending = null;
              if (els.pop && els.popIn) {
                els.pop.hidden = false;
                els.popIn.value = 'a / a, R';
                els.popOk.addEventListener('click', function () {
                  els.pop.hidden = true;
                  addRule(fromId, hit.id, els.popIn.value);
                }, { once: true });
              }
            }
          }
          return;
        }
        if (hit) { sel = { kind: hit.kind, id: hit.id }; renderSel(); render(); }
        else if (view) { panFrom = { x: Number(ev.clientX) || 0, y: Number(ev.clientY) || 0 }; }
      });
      if (view) {
        els.canvas.addEventListener('pointermove', function (ev) {
          if (!panFrom) { return; }
          var rect = els.canvas.getBoundingClientRect ? els.canvas.getBoundingClientRect() : null;
          var cur = view.cur();
          var dx = (Number(ev.clientX) - panFrom.x) * (rect && rect.width ? cur.w / rect.width : 1);
          var dy = (Number(ev.clientY) - panFrom.y) * (rect && rect.height ? cur.h / rect.height : 1);
          view.panBy(-dx, -dy);
          panFrom = { x: Number(ev.clientX) || 0, y: Number(ev.clientY) || 0 };
        });
        els.canvas.addEventListener('pointerup', function () { panFrom = null; });
        els.canvas.addEventListener('pointerleave', function () { panFrom = null; });
      }
    }

    /* Zoom, Einpassen, Undo/Redo, PNG (#173) aus dem Werkzeugkasten. */
    if (view) {
      if (els.zoomIn) { els.zoomIn.addEventListener('click', function () { view.zoom(1.25); }); }
      if (els.zoomOut) { els.zoomOut.addEventListener('click', function () { view.zoom(0.8); }); }
      if (els.fit) { els.fit.addEventListener('click', function () { view.fit(bbox(T), 50); }); }
    }
    if (hist) {
      if (els.undo) {
        els.undo.addEventListener('click', function () {
          hist.undo();
          hint('↶ zurück');
        });
      }
      if (els.redo) {
        els.redo.addEventListener('click', function () {
          hist.redo();
          hint('↷ wiederhergestellt');
        });
      }
    }
    if (TOOLS && els.png) {
      els.png.addEventListener('click', function () {
        TOOLS.exportPng(doc, exportSvgString(T), 'turingmaschine.png');
        hint('Bild wird als PNG geladen.');
      });
    }

    var toolBtns = root.querySelectorAll('[data-tm-mode]');
    Array.prototype.forEach.call(toolBtns, function (btn) {
      btn.addEventListener('click', function () {
        mode = btn.getAttribute('data-tm-mode') || 'select';
        pending = null;
        Array.prototype.forEach.call(toolBtns, function (b2) {
          b2.setAttribute('aria-pressed', b2 === btn ? 'true' : 'false');
        });
        hint(mode === 'state' ? 'Klicke auf die Fläche, um einen Zustand anzulegen.'
          : mode === 'rule' ? 'Erst den Quellzustand, dann den Zielzustand anklicken.'
          : mode === 'delete' ? 'Zustand oder Regel anklicken, um ihn zu entfernen.' : '');
        render();
      });
    });

    function showStep(idx) {
      if (!lastRun || !els.out) { return; }
      var s = lastRun.steps[idx];
      if (!s) { return; }
      var cells = '';
      var i;
      for (i = 0; i < s.cells.length; i++) {
        cells += s.cells[i].ch + (s.cells[i].head ? '‣' : '') + ' ';
      }
      els.out.textContent = 'Schritt ' + idx + '  Zustand: ' + s.state + '\nBand: ' + cells + '\n' + (lastRun.accepted && idx === lastRun.steps.length - 1 ? '✓ akzeptiert' : '');
    }

    function runAll() {
      stopPlay();
      lastRun = tmRun(T, els.word ? els.word.value : '');
      runPos = 0;
      if (els.out) {
        els.out.textContent = (lastRun.accepted ? '✓ akzeptiert' : '✗ ' + lastRun.reason) +
          '\nSchritte: ' + lastRun.steps.length;
      }
      return lastRun;
    }

    /* Zustands-ID zum Namen suchen: fuer das Abspielen wird der laufende
     * Zustand in der Zeichnung hervorgehoben. */
    function stateIdByName(name) {
      var i;
      for (i = 0; i < T.states.length; i++) {
        if (T.states[i].name === name) { return T.states[i].id; }
      }
      return null;
    }

    /* Abspielen (#173): Die Schrittfolge laeuft mit festem Takt, Band und
     * Zustand stehen je Schritt im Ausgabefeld, der Zustand ist markiert. */
    function stopPlay() {
      if (player) { player.stop(); player = null; }
    }

    function playWord() {
      stopPlay();
      if (!TOOLS || !TOOLS.makePlayer) { return; }
      lastRun = tmRun(T, els.word ? els.word.value : '');
      runPos = 0;
      player = TOOLS.makePlayer({
        steps: lastRun.steps,
        show: function (idx, s) {
          var cells = '';
          var i;
          for (i = 0; i < s.cells.length; i++) {
            cells += s.cells[i].ch + (s.cells[i].head ? '‣' : '') + ' ';
          }
          sel = { kind: 'state', id: stateIdByName(s.state) };
          render();
          if (els.out) { els.out.textContent = '▶ Schritt ' + idx + '  Zustand: ' + s.state + '\nBand: ' + cells; }
        },
        done: function () {
          if (els.out) {
            els.out.textContent = (lastRun.accepted ? '✓ akzeptiert' : '✗ ' + lastRun.reason) +
              '\nSchritte: ' + lastRun.steps.length;
          }
          player = null;
        }
      });
      player.play();
    }

    /* Mehrere Wörter testen (#173): ein Wort pro Zeile, je Zeile ein Ergebnis. */
    function runBatch() {
      if (!els.batchOut) { return; }
      els.batchOut.textContent = '';
      var words = String(els.batchIn ? els.batchIn.value : '').split(/\n+/).map(function (w) { return w.trim(); }).filter(Boolean);
      words.forEach(function (w) {
        var res = tmRun(T, w);
        var li = doc.createElement('li');
        li.textContent = w + ': ' + (res.accepted ? '✓' : '✗') + (res.accepted ? '' : ' (' + (res.reason || 'abgelehnt') + ')');
        els.batchOut.appendChild(li);
      });
    }

    if (els.run) { els.run.addEventListener('click', runAll); }
    if (els.play) { els.play.addEventListener('click', playWord); }
    if (els.batchBtn) { els.batchBtn.addEventListener('click', runBatch); }
    if (els.step) {
      els.step.addEventListener('click', function () {
        if (!lastRun) { runAll(); }
        stopPlay();
        showStep(runPos);
        runPos += 1;
        if (runPos >= lastRun.steps.length) { runPos = 0; }
      });
    }
    if (els.reset) { els.reset.addEventListener('click', function () { stopPlay(); lastRun = null; runPos = 0; if (els.out) { els.out.textContent = ''; } }); }
    if (els.form) {
      els.form.addEventListener('submit', function () {
        if (els.json) { els.json.value = JSON.stringify(serializeTM(T)); }
      });
    }

    render();
    renderSel();

    return {
      tm: function () { return T; },
      run: runAll,
      addState: addState,
      addRule: addRule,
      delState: delState,
      delRule: delRule,
      render: render,
      serialize: function () { return JSON.stringify(serializeTM(T)); },
      exportSvg: function () { return exportSvgString(T); },
      setMode: function (m) { mode = m; },
      /* Werkzeugkasten-Zugriff fuer die Tests (#173) */
      undo: function () { return hist ? hist.undo() : null; },
      redo: function () { return hist ? hist.redo() : null; },
      canUndo: function () { return hist ? hist.canUndo() : false; },
      fitView: function () { if (view) { view.fit(bbox(T), 50); } },
      zoomBy: function (f) { if (view) { view.zoom(f); } },
      viewBox: function () { return view ? view.cur() : null; },
      exportPng: function () { return TOOLS ? TOOLS.exportPng(doc, exportSvgString(T), 'turingmaschine.png') : false; },
      playWord: playWord,
      runBatch: runBatch
    };
  }

  var api = {
    emptyTM: emptyTM,
    exampleTM: exampleTM,
    normalizeTM: normalizeTM,
    parseTM: parseTM,
    serializeTM: serializeTM,
    tmRun: tmRun,
    analyseTM: analyseTM,
    sceneMarkup: sceneMarkup,
    exportSvgString: exportSvgString,
    attach: attach
  };
  if (typeof window !== 'undefined') { window.IC_TM = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
  if (typeof document !== 'undefined' && typeof document.getElementById === 'function' && document.getElementById('tm-ide')) {
    attach(document);
  }
}());
