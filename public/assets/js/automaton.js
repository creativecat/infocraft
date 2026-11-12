/**
 * InfoCraft - Automaten-Werkstatt (#163)
 *
 * Endliche Automaten zeichnen, Woerter pruefen, formale Beschreibung,
 * Trap-Zustand, Bild- und JSON-Export. Grundlage ist der vom
 * DigitalTutor bereitgestellte Automaten-Simulator; die Engine ist
 * DOM-frei gehalten, damit sie in den UI-Tests ohne Browser laeuft.
 *
 * Node-Tests: module.exports; Browser: window.IC_AUTOMAT.
 *
 * @version 0.16.0
 * @license MIT (InfoCraft)
 */
(function () {
  'use strict';

  var R = 26;

  /* ---------- Hilfsfunktionen (DOM-frei) ---------- */

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function parseSymbols(str) {
    var out = [];
    if (Array.isArray(str)) {
      var i;
      for (i = 0; i < str.length; i++) {
        if (str[i] !== '' && out.indexOf(String(str[i])) < 0) { out.push(String(str[i])); }
      }
      return out;
    }
    var toks = String(str).split(/[\s,;]+/).filter(Boolean), t, ch;
    for (t = 0; t < toks.length; t++) {
      var arr = Array.from(toks[t]);
      for (ch = 0; ch < arr.length; ch++) {
        if (out.indexOf(arr[ch]) < 0) { out.push(arr[ch]); }
      }
    }
    return out;
  }

  function sortSyms(arr) {
    return Array.from(arr).sort(function (a, b) {
      return String(a).localeCompare(String(b), 'de', { numeric: true });
    });
  }

  /* ---------- Datenmodell (DOM-frei) ---------- */

  function emptyAutomaton() {
    return { alphabet: [], states: [], transitions: [] };
  }

  function exampleAutomaton() {
    return {
      alphabet: ['a', 'b'],
      states: [
        { id: 's1', name: 'q0', x: 120, y: 200, start: true, accept: false, trap: false },
        { id: 's2', name: 'q1', x: 300, y: 200, start: false, accept: false, trap: false },
        { id: 's3', name: 'q2', x: 480, y: 200, start: false, accept: true, trap: false }
      ],
      transitions: [
        { id: 't1', from: 's1', to: 's2', symbols: ['a'] },
        { id: 't2', from: 's2', to: 's2', symbols: ['b'] },
        { id: 't3', from: 's2', to: 's3', symbols: ['a'] }
      ]
    };
  }

  function stateById(A, id) {
    var i;
    for (i = 0; i < A.states.length; i++) { if (A.states[i].id === id) { return A.states[i]; } }
    return null;
  }

  function transById(A, id) {
    var i;
    for (i = 0; i < A.transitions.length; i++) { if (A.transitions[i].id === id) { return A.transitions[i]; } }
    return null;
  }

  function normalizeAutomaton(d) {
    if (!d || !Array.isArray(d.states) || !Array.isArray(d.transitions)) { return null; }
    var states = d.states.filter(function (s) { return s && s.id != null; }).map(function (s, i) {
      return {
        id: String(s.id),
        name: String(s.name != null ? s.name : ('q' + i)),
        x: Number(s.x) || 100 + i * 120,
        y: Number(s.y) || 200,
        start: !!s.start,
        accept: !!(s.accept != null ? s.accept : (s.final != null ? s.final : s.end)),
        trap: !!s.trap
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
    var transitions = d.transitions.filter(function (t) {
      return t && ids.has(String(t.from)) && ids.has(String(t.to));
    }).map(function (t) {
      var syms;
      if (Array.isArray(t.symbols)) { syms = parseSymbols(t.symbols.join(',')); }
      else { syms = parseSymbols(t.symbols != null ? t.symbols : (t.label != null ? t.label : '')); }
      k += 1;
      return { id: String(t.id != null ? t.id : ('t' + k)), from: String(t.from), to: String(t.to), symbols: sortSyms(syms) };
    }).filter(function (t) { return t.symbols.length; });
    var alpha = [];
    if (Array.isArray(d.alphabet)) { alpha = parseSymbols(d.alphabet.join(',')); }
    transitions.forEach(function (t) {
      t.symbols.forEach(function (c) { if (alpha.indexOf(c) < 0) { alpha.push(c); } });
    });
    return { alphabet: sortSyms(alpha), states: states, transitions: transitions };
  }

  function serializeAutomaton(A) {
    var d = JSON.parse(JSON.stringify(A));
    d.format = 'endlicher-automat';
    d.version = 1;
    return d;
  }

  function effectiveAlphabet(A) {
    var set = new Set(A.alphabet);
    A.transitions.forEach(function (t) {
      t.symbols.forEach(function (c) { set.add(c); });
    });
    return sortSyms(Array.from(set));
  }

  /* ---------- Analyse (DOM-frei) ---------- */

  function analyseAutomaton(A) {
    var sigma = effectiveAlphabet(A);
    var missing = 0, nd = [], si, ci;
    for (si = 0; si < A.states.length; si++) {
      for (ci = 0; ci < sigma.length; ci++) {
        var c = sigma[ci];
        var targets = new Set(
          A.transitions.filter(function (t) { return t.from === A.states[si].id && t.symbols.indexOf(c) >= 0; })
            .map(function (t) { return t.to; })
        );
        if (targets.size === 0) { missing++; }
        if (targets.size > 1) { nd.push('(' + A.states[si].name + ', ' + c + ')'); }
      }
    }
    return { sigma: sigma, missing: missing, nd: nd };
  }

  function missingPairs(A, sigma) {
    var out = [], i, c;
    for (i = 0; i < A.states.length; i++) {
      var s = A.states[i];
      var used = new Set();
      A.transitions.filter(function (t) { return t.from === s.id; }).forEach(function (t) {
        t.symbols.forEach(function (ch) { used.add(ch); });
      });
      var m = [];
      for (c = 0; c < sigma.length; c++) { if (!used.has(sigma[c])) { m.push(sigma[c]); } }
      if (m.length) { out.push({ from: s.id, syms: m }); }
    }
    return out;
  }

  /**
   * Ergaenzt einen Trap-Zustand (reine Funktion): Der Klon B bekommt
   * zunaechst den Trap-Zustand, danach werden alle fehlenden Uebergaenge
   * berechnet. Dadurch erhaelt der Trap auch seine eigene Schleife und
   * der Automat ist danach vollstaendig (missing === 0).
   */
  function applyTrap(A) {
    var B = JSON.parse(JSON.stringify(A));
    var sigma = effectiveAlphabet(B);
    var trap = null, i;
    for (i = 0; i < B.states.length; i++) { if (B.states[i].trap) { trap = B.states[i]; } }
    var added = 0;
    if (trap) { return { automaton: B, added: 0, trapName: trap.name }; }
    if (!B.states.length || !sigma.length) { return { automaton: B, added: 0, trapName: null }; }
    var x0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    B.states.forEach(function (s) {
      x0 = Math.min(x0, s.x); x1 = Math.max(x1, s.x); y1 = Math.max(y1, s.y);
    });
    var name = 'trap', n2 = 2;
    while (B.states.some(function (s) { return s.name === name; })) { name = 'trap' + n2++; }
    trap = { id: 'trap' + Date.now().toString(36) + 'x', name: name, x: Math.round((x0 + x1) / 2), y: y1 + 150, start: false, accept: false, trap: true };
    B.states.push(trap);
    var pairs = missingPairs(B, sigma);
    pairs.forEach(function (m) {
      if (m.from !== trap.id) { added += m.syms.length; }
      var ex = null, j;
      for (j = 0; j < B.transitions.length; j++) {
        if (B.transitions[j].from === m.from && B.transitions[j].to === trap.id) { ex = B.transitions[j]; break; }
      }
      if (ex) { ex.symbols = sortSyms(Array.from(new Set(ex.symbols.concat(m.syms)))); }
      else { B.transitions.push({ id: 't' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), from: m.from, to: trap.id, symbols: sortSyms(m.syms) }); }
    });
    return { automaton: B, added: added, trapName: trap.name };
  }

  /* ---------- Simulation (DOM-frei) ---------- */

  function nameList(A, ids) {
    return ids.map(function (id) { var s = stateById(A, id); return s ? s.name : id; }).join(', ');
  }

  function simulateAutomaton(A, word) {
    var chars = Array.from(String(word == null ? '' : word).replace(/\s+/g, ''));
    var start = null, i;
    for (i = 0; i < A.states.length; i++) { if (A.states[i].start) { start = A.states[i]; break; } }
    if (!start) { return { chars: chars, steps: [], error: 'Es ist kein Startzustand festgelegt.' }; }
    var steps = [{ cur: [start.id], edges: [], ch: null }];
    var cur = [start.id], si, ti;
    for (i = 0; i < chars.length; i++) {
      var ch = chars[i];
      var next = new Set(), edges = [];
      for (si = 0; si < cur.length; si++) {
        for (ti = 0; ti < A.transitions.length; ti++) {
          var t = A.transitions[ti];
          if (t.from === cur[si] && t.symbols.indexOf(ch) >= 0) { next.add(t.to); edges.push(t.id); }
        }
      }
      if (!next.size) {
        var inSigma = effectiveAlphabet(A).indexOf(ch) >= 0;
        return {
          chars: chars, steps: steps, stuck: i, accepted: false,
          reason: inSigma
            ? 'In ' + nameList(A, cur) + ' gibt es keinen Uebergang fuer ' + ch + '. Der Automat bleibt stecken.'
            : ch + ' gehoert nicht zum Eingabealphabet. Der Automat bleibt in ' + nameList(A, cur) + ' stecken.'
        };
      }
      cur = Array.from(next);
      steps.push({ cur: cur, edges: edges, ch: ch });
    }
    var acc = cur.filter(function (id) { return stateById(A, id).accept; });
    return {
      chars: chars, steps: steps, accepted: acc.length > 0,
      reason: acc.length
        ? 'Das Wort endet im Endzustand ' + nameList(A, acc) + '.'
        : 'Das Wort endet in ' + nameList(A, cur) + '. ' + (cur.length > 1 ? 'Das sind' : 'Das ist') + ' kein Endzustand.'
    };
  }

  /* ---------- Geometrie und Zeichnung (DOM-frei) ---------- */

  var DEFAULT_PAL = {
    paper: '#ffffff', ink: '#1b2a41', muted: '#5b687c', grid: '#e2e7ef', accent: '#1f4fc9',
    accentSoft: '#e2e9fb', hl: '#ffd84a', hlInk: '#1b2a41', hlLine: '#c98a00', bad: '#bd2e24',
    font: 'Helvetica, Arial, sans-serif'
  };
  var EXPORT_PAL = {
    paper: '#ffffff', ink: '#1b2a41', muted: '#5b687c', grid: '#e2e7ef', accent: '#1b2a41',
    accentSoft: '#ffffff', hl: '#ffffff', hlInk: '#1b2a41', hlLine: '#1b2a41', bad: '#bd2e24',
    font: 'Helvetica, Arial, sans-serif'
  };

  function edgeGeom(A, t) {
    var a = stateById(A, t.from), b = stateById(A, t.to);
    if (!a || !b) { return null; }
    if (a === b) {
      var lx = a.x, ly = a.y, a1 = -125 * Math.PI / 180, a2 = -55 * Math.PI / 180;
      var sx = lx + R * Math.cos(a1), sy = ly + R * Math.sin(a1);
      var ex = lx + R * Math.cos(a2), ey = ly + R * Math.sin(a2);
      return { d: 'M' + sx + ' ' + sy + ' C' + (lx - 40) + ' ' + (ly - R - 64) + ' ' + (lx + 40) + ' ' + (ly - R - 64) + ' ' + ex + ' ' + ey, lx: lx, ly: ly - R - 58 };
    }
    var dx = b.x - a.x, dy = b.y - a.y, len = Math.hypot(dx, dy) || 1;
    var nx = -dy / len, ny = dx / len;
    var curved = A.transitions.some(function (o) { return o.from === t.to && o.to === t.from; });
    var off = curved ? 28 : 0;
    var cx = (a.x + b.x) / 2 + nx * off * 2, cy = (a.y + b.y) / 2 + ny * off * 2;
    var d1 = Math.hypot(cx - a.x, cy - a.y) || 1, d2 = Math.hypot(cx - b.x, cy - b.y) || 1;
    var s2x = a.x + (cx - a.x) / d1 * R, s2y = a.y + (cy - a.y) / d1 * R;
    var e2x = b.x + (cx - b.x) / d2 * R, e2y = b.y + (cy - b.y) / d2 * R;
    var mx = .25 * s2x + .5 * cx + .25 * e2x, my = .25 * s2y + .5 * cy + .25 * e2y;
    var lnx = nx, lny = ny;
    if (!curved) {
      if (lny > 0.01 || (Math.abs(lny) <= 0.01 && lnx < 0)) { lnx = -lnx; lny = -lny; }
    }
    var lo = 13 + Math.abs(lnx) * 8;
    return { d: 'M' + s2x + ' ' + s2y + ' Q' + cx + ' ' + cy + ' ' + e2x + ' ' + e2y, lx: mx + lnx * lo, ly: my + lny * lo };
  }

  function defsMarkup(P) {
    function m(id, c) {
      return '<marker id="' + id + '" viewBox="0 0 12 12" refX="11" refY="6" markerWidth="11" markerHeight="11" markerUnits="userSpaceOnUse" orient="auto"><path d="M0 1 L12 6 L0 11 Z" fill="' + c + '"/></marker>';
    }
    return m('aInk', P.ink) + m('aSel', P.accent) + m('aHl', P.hlLine);
  }

  /**
   * Zeichnet die Szene als SVG-Markup-String. opts:
   * { interactive, sel {kind,id}, pending, curStates Set, curEdges Set, P Palette }
   */
  function sceneMarkup(A, opts) {
    var P = (opts && opts.P) || DEFAULT_PAL;
    var interactive = opts && opts.interactive;
    var edges = '', hits = '', labels = '', nodes = '', halos = '';
    A.transitions.forEach(function (t) {
      var g = edgeGeom(A, t);
      if (!g) { return; }
      var isSel = opts.sel && opts.sel.kind === 'trans' && opts.sel.id === t.id;
      var isHl = opts.curEdges && opts.curEdges.has(t.id);
      var col = isHl ? P.hlLine : (isSel ? P.accent : P.ink);
      var mk = isHl ? 'aHl' : (isSel ? 'aSel' : 'aInk');
      edges += '<path d="' + g.d + '" fill="none" stroke="' + col + '" stroke-width="' + (isHl || isSel ? 2.8 : 1.7) + '" marker-end="url(#' + mk + ')"/>';
      var dk = interactive ? ' data-kind="trans" data-id="' + t.id + '"' : '';
      if (interactive) {
        hits += '<path d="' + g.d + '" fill="none" stroke="transparent" stroke-width="18"' + dk + ' style="cursor:pointer"/>';
      }
      labels += '<text x="' + g.lx + '" y="' + g.ly + '" text-anchor="middle" dominant-baseline="central" font-family="' + P.font + '" font-size="16" font-weight="' + (isSel || isHl ? 700 : 400) + '" fill="' + col + '" stroke="' + P.paper + '" stroke-width="6" stroke-linejoin="round" paint-order="stroke"' + dk + ' style="cursor:pointer">' + esc(t.symbols.join(', ')) + '</text>';
    });
    A.states.forEach(function (s) {
      var isSel = opts.sel && opts.sel.kind === 'state' && opts.sel.id === s.id;
      var isCur = opts.curStates && opts.curStates.has(s.id);
      var stroke = isSel ? P.accent : P.ink;
      var fs = s.name.length > 4 ? Math.max(10, 16 - (s.name.length - 4) * 1.6) : 16;
      var dk = interactive ? ' data-kind="state" data-id="' + s.id + '" style="cursor:pointer"' : '';
      var g = '<g' + dk + '>';
      if (isSel) { halos += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R + 8) + '" fill="' + P.accentSoft + '"/>'; }
      if (opts.pending === s.id) { g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R + 8) + '" fill="none" stroke="' + P.accent + '" stroke-width="2" stroke-dasharray="4 4"/>'; }
      if (s.start) { g += '<path d="M' + (s.x - R - 40) + ' ' + s.y + ' L' + (s.x - R - 1) + ' ' + s.y + '" stroke="' + P.ink + '" stroke-width="2" marker-end="url(#aInk)"/>'; }
      g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + R + '" fill="' + (isCur ? P.hl : P.paper) + '" stroke="' + stroke + '" stroke-width="' + (isSel ? 3 : 2) + '"' + (s.trap ? ' stroke-dasharray="6 4"' : '') + '/>';
      if (s.accept) { g += '<circle cx="' + s.x + '" cy="' + s.y + '" r="' + (R - 5) + '" fill="none" stroke="' + stroke + '" stroke-width="1.7"/>'; }
      g += '<text x="' + s.x + '" y="' + s.y + '" text-anchor="middle" dominant-baseline="central" font-family="' + P.font + '" font-size="' + fs + '" font-weight="700" fill="' + (isCur ? P.hlInk : P.ink) + '">' + esc(s.name) + '</text></g>';
      nodes += g;
    });
    return halos + hits + edges + nodes + labels;
  }

  function bbox(A) {
    if (!A.states.length) { return null; }
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    A.states.forEach(function (s) {
      x0 = Math.min(x0, s.x); y0 = Math.min(y0, s.y);
      x1 = Math.max(x1, s.x); y1 = Math.max(y1, s.y);
    });
    var loops = A.transitions.some(function (t) { return t.from === t.to; });
    return { x0: x0 - R - 52, y0: y0 - R - (loops ? 62 : 30), x1: x1 + R + 30, y1: y1 + R + 34 };
  }

  function exportSvgString(A) {
    var b = bbox(A), P = EXPORT_PAL, pad = 20;
    var x = b.x0 - pad, y = b.y0 - pad, w = b.x1 - b.x0 + 2 * pad, h = b.y1 - b.y0 + 2 * pad;
    return {
      w: w, h: h,
      str: '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="' + x + ' ' + y + ' ' + w + ' ' + h + '"><defs>' + defsMarkup(P) + '</defs><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="#ffffff"/>' + sceneMarkup(A, { interactive: false, P: P }) + '</svg>'
    };
  }

  /** Formale Beschreibung als HTML (5-Tupel). */
  function tupleText(A) {
    var sigma = effectiveAlphabet(A);
    var start = null;
    A.states.forEach(function (s) { if (s.start) { start = s; } });
    var names = A.states.map(function (s) { return esc(s.name); });
    return 'A = (Q, \u03a3, \u03b4, q<sub>0</sub>, F)<br>Q = {' + names.join(', ') + '}<br>'
      + '\u03a3 = {' + sigma.map(esc).join(', ') + '}<br>'
      + 'q<sub>0</sub> = ' + (start ? esc(start.name) : '\u2013') + '<br>'
      + 'F = {' + A.states.filter(function (s) { return s.accept; }).map(function (s) { return esc(s.name); }).join(', ') + '}';
  }

  /* ---------- Editor (attach) ---------- */

  var HINTS = {
    select: 'Zustand ziehen: verschieben. Antippen: bearbeiten. Freie Flaeche ziehen: Ansicht verschieben. Doppelklick auf freie Flaeche: neuer Zustand.',
    state: 'Auf eine freie Stelle tippen: neuer Zustand. Der erste Zustand wird automatisch Startzustand.',
    trans: 'Vom Ausgangs- zum Zielzustand ziehen oder beide nacheinander antippen. Denselben Zustand zweimal antippen ergibt eine Schleife.',
    delete: 'Zustand oder Uebergang antippen, um ihn zu loeschen. Mit Rueckgaengig machst du das ungeschehen.'
  };

  function attach(doc) {
    var root = doc.getElementById('amt-ide');
    if (!root) { return null; }
    var $id = function (id) { return doc.getElementById(id); };
    var svg = $id('amt-canvas'), stage = $id('amt-stage');
    var els = {
      canvas: svg, stage: stage, hint: $id('amt-hint'),
      pop: $id('amt-pop'), popLbl: $id('amt-pop-lbl'), popIn: $id('amt-pop-in'),
      popOk: $id('amt-pop-ok'), popHint: $id('amt-pop-hint'),
      selPanel: $id('amt-sel'), word: $id('amt-word'), run: $id('amt-run'),
      step: $id('amt-step'), play: $id('amt-play'), reset: $id('amt-reset'),
      out: $id('amt-out'), batchIn: $id('amt-batch-in'), batchBtn: $id('amt-batch-btn'),
      batchOut: $id('amt-batch-out'), sigma: $id('amt-sigma'), chips: $id('amt-chips'),
      tuple: $id('amt-tuple'), delta: $id('amt-delta'),
      undoBtn: $id('amt-undo'), redoBtn: $id('amt-redo'), trapBtn: $id('amt-trap'),
      newBtn: $id('amt-new'), pngBtn: $id('amt-png'), fitBtn: $id('amt-fit'),
      zoomIn: $id('amt-zoom-in'), zoomOut: $id('amt-zoom-out'),
      saveForm: $id('automaton-save-form'), jsonTa: $id('automaton-json'),
      saveInfo: $id('amt-saveinfo')
    };
    if (!svg || !stage || !els.saveForm) { return null; }

    /* --- Toast --- */
    var toastEl = doc.createElement('div');
    toastEl.className = 'amt-toast';
    toastEl.setAttribute('hidden', '');
    stage.appendChild(toastEl);
    var toastTimer = null;
    function toast(msg) {
      toastEl.textContent = msg;
      toastEl.removeAttribute('hidden');
      if (toastTimer) { clearTimeout(toastTimer); }
      toastTimer = setTimeout(function () { toastEl.setAttribute('hidden', ''); }, 4200);
    }

    /* --- Zustand --- */
    var A = null, view = { tx: 0, ty: 0, k: 1 };
    var mode = 'select', sel = null, pendingFrom = null;
    var drag = null, rubber = null, sim = null, playTimer = null, popCtx = null;
    var undoStack = [], redoStack = [];
    var saveTimer = null, dirty = false;

    var savedRaw = (els.jsonTa && els.jsonTa.value || '').trim();
    if (savedRaw) {
      try { A = normalizeAutomaton(JSON.parse(savedRaw)); } catch (eParse) { A = null; }
    }
    if (!A) { A = exampleAutomaton(); }

    var uid = function (p) { return p + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); };
    var st = function (id) { return stateById(A, id); };
    var tr = function (id) { return transById(A, id); };
    var effAlphabet = function () { return effectiveAlphabet(A); };
    var freeName = function (base) {
      var i;
      if (base) {
        var n = base, n2 = 2;
        while (A.states.some(function (s) { return s.name === n; })) { n = base + n2++; }
        return n;
      }
      i = 0;
      while (A.states.some(function (s) { return s.name === 'q' + i; })) { i++; }
      return 'q' + i;
    };

    /* --- Verlauf --- */
    function commit() {
      undoStack.push(JSON.stringify(A));
      if (undoStack.length > 150) { undoStack.shift(); }
      redoStack.length = 0;
    }
    function undo() {
      if (!undoStack.length) { return; }
      redoStack.push(JSON.stringify(A));
      A = JSON.parse(undoStack.pop());
      changed();
    }
    function redo() {
      if (!redoStack.length) { return; }
      undoStack.push(JSON.stringify(A));
      A = JSON.parse(redoStack.pop());
      changed();
    }

    /* --- Speichern --- */
    function queueSave() {
      dirty = true;
      if (saveTimer) { clearTimeout(saveTimer); }
      saveTimer = setTimeout(saveNow, 900);
    }
    function saveNow() {
      if (!dirty) { return; }
      dirty = false;
      if (!els.jsonTa || !els.saveForm) { return; }
      els.jsonTa.value = JSON.stringify(serializeAutomaton(A));
      var action = els.saveForm.getAttribute('action') || '';
      var token = '';
      var tokenInput = els.saveForm.querySelector('input[name="csrf"]');
      if (tokenInput) { token = tokenInput.value || ''; }
      var titleInput = els.saveForm.querySelector('input[name="title"]');
      var title = titleInput ? (titleInput.value || '') : '';
      if (!action || typeof fetch !== 'function') { return; }
      var body = 'csrf=' + encodeURIComponent(token)
        + '&title=' + encodeURIComponent(title)
        + '&content=' + encodeURIComponent(els.jsonTa.value);
      if (els.saveInfo) {
        els.saveInfo.removeAttribute('hidden');
        els.saveInfo.textContent = 'speichert …';
      }
      fetch(action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      }).then(function (r) {
        if (!r.ok) { throw new Error('save'); }
        if (els.saveInfo) { els.saveInfo.textContent = 'gespeichert'; }
      }).catch(function () {
        dirty = true;
        if (els.saveInfo) { els.saveInfo.textContent = 'Speichern fehlgeschlagen'; };
      });
    }

    function changed() {
      if (sel && !(sel.kind === 'state' ? st(sel.id) : tr(sel.id))) { sel = null; }
      if (pendingFrom && !st(pendingFrom)) { pendingFrom = null; }
      stopPlay(); sim = null;
      queueSave();
      render(); renderPanel(); renderDefinition(); renderSim();
    }

    /* --- Ansicht --- */
    function palette() {
      var cs = (typeof getComputedStyle === 'function' && doc.documentElement)
        ? getComputedStyle(doc.documentElement) : null;
      if (!cs || !cs.getPropertyValue) { return DEFAULT_PAL; }
      var g = function (n) { return (cs.getPropertyValue(n) || '').trim() || DEFAULT_PAL[n]; };
      return {
        paper: g('--paper'), ink: g('--ink'), muted: g('--muted'), grid: g('--grid'),
        accent: g('--accent'), accentSoft: g('--accent-soft'), hl: g('--hl'),
        hlInk: g('--hl-ink'), hlLine: g('--hl-line'), bad: g('--bad'),
        font: DEFAULT_PAL.font
      };
    }

    function toWorld(cx, cy) {
      var r = svg.getBoundingClientRect();
      return { x: (cx - r.left - view.tx) / view.k, y: (cy - r.top - view.ty) / view.k };
    }
    function toScreen(x, y) { return { x: x * view.k + view.tx, y: y * view.k + view.ty }; }
    function fitView() {
      var b = bbox(A), w = stage.clientWidth, h = stage.clientHeight;
      if (!b) { view = { tx: 0, ty: 0, k: 1 }; render(); return; }
      var bw = b.x1 - b.x0, bh = b.y1 - b.y0;
      var k = Math.max(.3, Math.min(1.5, (w - 60) / bw, (h - 90) / bh));
      view = { k: k, tx: (w - bw * k) / 2 - b.x0 * k, ty: (h - 40 - bh * k) / 2 - b.y0 * k };
      render();
    }
    function zoomAt(f, cx, cy) {
      var k = Math.max(.25, Math.min(3, view.k * f)); f = k / view.k;
      view.tx = cx - (cx - view.tx) * f; view.ty = cy - (cy - view.ty) * f; view.k = k;
      render();
    }

    function render() {
      var P = palette();
      var cur = sim ? sim.steps[sim.pos] : null;
      var o = {
        interactive: true, sel: sel, pending: pendingFrom,
        curStates: cur ? new Set(cur.cur) : null, curEdges: cur ? new Set(cur.edges) : null
      };
      var extra = '';
      if (rubber) {
        var ra = st(rubber.from);
        if (ra) {
          extra = '<path d="M' + ra.x + ' ' + ra.y + ' L' + rubber.x + ' ' + rubber.y + '" stroke="' + P.accent + '" stroke-width="2" stroke-dasharray="6 5" fill="none" marker-end="url(#aSel)" pointer-events="none"/>';
        }
      }
      svg.innerHTML =
        '<defs>' + defsMarkup(P)
        + '<pattern id="amt-grid" width="20" height="20" patternUnits="userSpaceOnUse" patternTransform="translate(' + view.tx + ' ' + view.ty + ') scale(' + view.k + ')"><path d="M20 0H0V20" fill="none" stroke="' + P.grid + '" stroke-width="1" vector-effect="non-scaling-stroke"/></pattern></defs>'
        + '<rect width="100%" height="100%" fill="url(#amt-grid)"/>'
        + '<g transform="translate(' + view.tx + ' ' + view.ty + ') scale(' + view.k + ')">' + sceneMarkup(A, { P: P, interactive: true, sel: sel, pending: pendingFrom, curStates: o.curStates, curEdges: o.curEdges }) + extra + '</g>'
        + (A.states.length ? '' : '<text x="50%" y="46%" text-anchor="middle" font-family="' + P.font + '" font-size="17" fill="' + P.muted + '">Noch kein Zustand. Waehle den Werkzeugknopf Zustand und tippe auf eine freie Stelle.</text>');
      if (els.undoBtn) { els.undoBtn.disabled = !undoStack.length; }
      if (els.redoBtn) { els.redoBtn.disabled = !redoStack.length; }
    }

    /* --- Bearbeiten --- */
    function addState(x, y) {
      commit();
      var s = {
        id: uid('s'), name: freeName(), x: Math.round(x), y: Math.round(y),
        start: !A.states.some(function (t) { return t.start; }), accept: false, trap: false
      };
      A.states.push(s);
      sel = { kind: 'state', id: s.id };
      changed();
    }
    function deleteState(id) {
      var s = st(id); if (!s) { return; }
      commit();
      A.states = A.states.filter(function (x) { return x.id !== id; });
      A.transitions = A.transitions.filter(function (t) { return t.from !== id && t.to !== id; });
      changed();
      toast('Zustand ' + s.name + ' geloescht.');
    }
    function deleteTrans(id) {
      var t = tr(id); if (!t) { return; }
      commit();
      A.transitions = A.transitions.filter(function (x) { return x.id !== id; });
      changed();
      toast('Uebergang ' + (st(t.from) ? st(t.from).name : '?') + ' nach ' + (st(t.to) ? st(t.to).name : '?') + ' geloescht.');
    }
    function setSymbols(from, to, syms, editId) {
      commit();
      syms.forEach(function (c) { if (A.alphabet.indexOf(c) < 0) { A.alphabet.push(c); } });
      A.alphabet = sortSyms(A.alphabet);
      if (editId) {
        var t = tr(editId);
        if (!syms.length) {
          A.transitions = A.transitions.filter(function (x) { return x.id !== editId; });
        } else {
          t.symbols = sortSyms(syms);
        }
      } else {
        var ex = null, i;
        for (i = 0; i < A.transitions.length; i++) {
          if (A.transitions[i].from === from && A.transitions[i].to === to) { ex = A.transitions[i]; break; }
        }
        if (ex) {
          ex.symbols = sortSyms(Array.from(new Set(ex.symbols.concat(syms))));
          sel = { kind: 'trans', id: ex.id };
        } else {
          var nt = { id: uid('t'), from: from, to: to, symbols: sortSyms(syms) };
          A.transitions.push(nt);
          sel = { kind: 'trans', id: nt.id };
        }
      }
      changed();
    }

    /* --- Popover --- */
    function openPop(from, to, editId) {
      var a = st(from), b = st(to); if (!a || !b) { return; }
      popCtx = { from: from, to: to, editId: editId };
      var t = editId ? tr(editId) : null;
      var g = editId ? edgeGeom(A, t) : (a === b ? { lx: a.x, ly: a.y - R - 58 } : { lx: (a.x + b.x) / 2, ly: (a.y + b.y) / 2 });
      var p = toScreen(g.lx, g.ly);
      if (els.popLbl) { els.popLbl.textContent = 'Uebergang ' + a.name + ' nach ' + b.name; }
      if (els.popIn) { els.popIn.value = t ? t.symbols.join(', ') : ''; }
      if (els.popHint) {
        els.popHint.textContent = t
          ? 'Feld leeren und OK: Uebergang loeschen'
          : 'Mehrere Zeichen auf einmal: a,b oder ab';
      }
      els.pop.removeAttribute('hidden');
      var w = els.pop.offsetWidth || 230, h = els.pop.offsetHeight || 140;
      els.pop.style.left = Math.max(8, Math.min(stage.clientWidth - w - 8, p.x - w / 2)) + 'px';
      els.pop.style.top = Math.max(8, Math.min(stage.clientHeight - h - 8, p.y + 18)) + 'px';
      if (!t && a !== b) { rubber = { from: from, x: b.x, y: b.y }; }
      render();
      setTimeout(function () { if (els.popIn && els.popIn.focus) { els.popIn.focus(); } }, 0);
    }
    function closePop(confirm) {
      if (!popCtx) { return; }
      var ctx = popCtx; popCtx = null;
      els.pop.setAttribute('hidden', ''); rubber = null;
      if (confirm) {
        var syms = parseSymbols(els.popIn.value);
        if (syms.length || ctx.editId) { setSymbols(ctx.from, ctx.to, syms, ctx.editId); return; }
      }
      render();
    }
    if (els.popOk) { els.popOk.addEventListener('click', function () { closePop(true); }); }
    if (els.popIn) {
      els.popIn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); closePop(true); }
        if (e.key === 'Escape') { e.preventDefault(); closePop(false); }
      });
    }

    /* --- Zeiger --- */
    svg.addEventListener('pointerdown', function (e) {
      if (popCtx) { closePop(true); }
      if (e.button > 0) { return; }
      var el = e.target.closest ? e.target.closest('[data-kind]') : null;
      var kind = (el && el.getAttribute('data-kind')) || null;
      var id = (el && el.getAttribute('data-id')) || null;
      var p = toWorld(e.clientX, e.clientY);
      try { svg.setPointerCapture(e.pointerId); } catch (eCap) {}
      drag = { pid: e.pointerId, sx: e.clientX, sy: e.clientY, moved: false, kind: kind, id: id, p0: p, v0: { tx: view.tx, ty: view.ty, k: view.k }, committed: false };
      if (kind === 'state') { var s = st(id); drag.o = { x: s.x, y: s.y }; }
      if (mode === 'trans' && kind === 'state') { drag.rubber = true; }
    });
    svg.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.pid) { return; }
      if (!drag.moved && Math.hypot(e.clientX - drag.sx, e.clientY - drag.sy) < 5) { return; }
      drag.moved = true;
      var p = toWorld(e.clientX, e.clientY);
      if (drag.rubber) { rubber = { from: drag.id, x: p.x, y: p.y }; pendingFrom = null; render(); return; }
      if (drag.kind === 'state' && mode !== 'delete') {
        if (!drag.committed) { commit(); drag.committed = true; }
        var s = st(drag.id);
        s.x = Math.round(drag.o.x + p.x - drag.p0.x);
        s.y = Math.round(drag.o.y + p.y - drag.p0.y);
        render(); return;
      }
      if (!drag.kind || drag.kind === 'trans') {
        view.tx = drag.v0.tx + e.clientX - drag.sx;
        view.ty = drag.v0.ty + e.clientY - drag.sy;
        render();
      }
    });
    function endDrag(e) {
      if (!drag || e.pointerId !== drag.pid) { return; }
      var d = drag; drag = null;
      if (d.moved) {
        if (d.rubber) {
          rubber = null;
          var under = doc.elementFromPoint(e.clientX, e.clientY);
          var uEl = under && under.closest ? under.closest('[data-kind="state"]') : null;
          if (uEl) { openPop(d.id, uEl.getAttribute('data-id')); } else { render(); }
        } else if (d.committed) {
          sim = null; queueSave(); renderPanel(); renderDefinition(); renderSim(); render();
        }
        return;
      }
      click(d.kind, d.id, d.p0);
    }
    svg.addEventListener('pointerup', endDrag);
    svg.addEventListener('pointercancel', function (e) {
      if (drag && e.pointerId === drag.pid) { drag = null; rubber = null; render(); }
    });

    function click(kind, id, p) {
      if (mode === 'select') {
        sel = kind ? { kind: kind === 'state' ? 'state' : 'trans', id: id } : null;
      } else if (mode === 'state') {
        if (!kind) { addState(p.x, p.y); return; }
        sel = { kind: kind === 'state' ? 'state' : 'trans', id: id };
      } else if (mode === 'trans') {
        if (kind === 'state') {
          if (pendingFrom) { var f = pendingFrom; pendingFrom = null; openPop(f, id); return; }
          pendingFrom = id;
        } else if (kind === 'trans') {
          var t = tr(id); openPop(t.from, t.to, id); return;
        } else { pendingFrom = null; }
      } else if (mode === 'delete') {
        if (kind === 'state') { deleteState(id); }
        else if (kind === 'trans') { deleteTrans(id); }
        return;
      }
      render(); renderPanel();
    }

    svg.addEventListener('dblclick', function (e) {
      var el = e.target.closest ? e.target.closest('[data-kind]') : null;
      if (mode === 'delete') { return; }
      if (!el) { var p = toWorld(e.clientX, e.clientY); addState(p.x, p.y); return; }
      if (el.getAttribute('data-kind') === 'trans') {
        var t = tr(el.getAttribute('data-id')); openPop(t.from, t.to, t.id);
      }
      if (el.getAttribute('data-kind') === 'state' && mode === 'select') {
        var i = doc.getElementById('amt-name');
        if (i && i.focus) { i.focus(); if (i.select) { i.select(); } }
      }
    });
    svg.addEventListener('wheel', function (e) {
      e.preventDefault();
      var r = svg.getBoundingClientRect();
      zoomAt(Math.exp(-e.deltaY * 0.0015), e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
    if (els.zoomIn) { els.zoomIn.addEventListener('click', function () { zoomAt(1.2, stage.clientWidth / 2, stage.clientHeight / 2); }); }
    if (els.zoomOut) { els.zoomOut.addEventListener('click', function () { zoomAt(1 / 1.2, stage.clientWidth / 2, stage.clientHeight / 2); }); }
    if (els.fitBtn) { els.fitBtn.addEventListener('click', fitView); }

    /* --- Modi --- */
    function setMode(m) {
      mode = m; pendingFrom = null;
      var btns = root.querySelectorAll('[data-amt-mode]'), i;
      for (i = 0; i < btns.length; i++) {
        btns[i].setAttribute('aria-pressed', String(btns[i].getAttribute('data-amt-mode') === m));
      }
      if (els.hint) { els.hint.textContent = HINTS[m]; }
      render();
    }
    (function () {
      var btns = root.querySelectorAll('[data-amt-mode]'), i;
      for (i = 0; i < btns.length; i++) {
        btns[i].addEventListener('click', function () { setMode(this.getAttribute('data-amt-mode')); });
      }
    })();

    /* --- Seitenleiste: Auswahl --- */
    function renderPanel() {
      var box = els.selPanel;
      if (!box) { return; }
      if (!sel) {
        box.innerHTML = '<h3>Auswahl</h3><p class="muted">Tippe einen Zustand oder einen Uebergang an, um Name, Start- und Endzustand oder Eingabezeichen zu aendern.</p>';
        return;
      }
      if (sel.kind === 'state') {
        var s = st(sel.id);
        box.innerHTML = '<h3>Zustand</h3>'
          + '<label class="field"><span>Name</span><input class="inp mono" id="amt-name" value="' + esc(s.name) + '" autocomplete="off" spellcheck="false"></label>'
          + '<div class="check-row">'
          + '<label class="check"><input type="checkbox" id="amt-start-cb"' + (s.start ? ' checked' : '') + '> Startzustand</label>'
          + '<label class="check"><input type="checkbox" id="amt-accept-cb"' + (s.accept ? ' checked' : '') + '> Endzustand</label>'
          + '</div>'
          + '<div class="btn-row"><button class="btn" id="amt-loop" type="button">Uebergang von hier</button>'
          + '<button class="btn btn-danger" id="amt-del-sel" type="button">Zustand loeschen</button></div>';
        var nameIn = doc.getElementById('amt-name');
        nameIn.addEventListener('change', function (e) {
          var v = e.target.value.trim();
          if (!v) { e.target.value = s.name; return; }
          if (A.states.some(function (x) { return x.id !== s.id && x.name === v; })) {
            toast('Den Namen ' + v + ' gibt es schon.');
            e.target.value = s.name; return;
          }
          commit(); st(sel.id).name = v; changed();
        });
        nameIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.target.blur(); } });
        doc.getElementById('amt-start-cb').addEventListener('change', function (e) {
          commit();
          A.states.forEach(function (x) { x.start = false; });
          st(sel.id).start = e.target.checked;
          changed();
        });
        doc.getElementById('amt-accept-cb').addEventListener('change', function (e) {
          commit(); st(sel.id).accept = e.target.checked; changed();
        });
        doc.getElementById('amt-del-sel').addEventListener('click', function () { deleteState(sel.id); });
        doc.getElementById('amt-loop').addEventListener('click', function () {
          var id = sel.id; setMode('trans'); pendingFrom = id; render();
          toast('Tippe jetzt den Zielzustand an.');
        });
      } else {
        var t = tr(sel.id), a = st(t.from), b = st(t.to);
        box.innerHTML = '<h3>Uebergang</h3>'
          + '<div class="selhead">' + esc(a.name) + ' nach ' + esc(b.name) + '</div>'
          + '<label class="field"><span>Eingabezeichen (z. B. a, b oder ab)</span><input class="inp mono" id="amt-sym" value="' + esc(t.symbols.join(', ')) + '" autocomplete="off" spellcheck="false"></label>'
          + '<div class="btn-row"><button class="btn btn-danger" id="amt-del-sel" type="button">Uebergang loeschen</button></div>';
        var symIn = doc.getElementById('amt-sym');
        symIn.addEventListener('change', function (e) {
          var syms = parseSymbols(e.target.value);
          if (!syms.length) { deleteTrans(t.id); return; }
          setSymbols(t.from, t.to, syms, t.id);
        });
        symIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.target.blur(); } });
        doc.getElementById('amt-del-sel').addEventListener('click', function () { deleteTrans(sel.id); });
      }
    }

    /* --- Formale Beschreibung --- */
    function renderDefinition() {
      var si = els.sigma;
      if (si && doc.activeElement !== si) { si.value = effAlphabet().join(', '); }
      var an = analyseAutomaton(A);
      var sigma = an.sigma, missing = an.missing, nd = an.nd;
      var start = null;
      A.states.forEach(function (s) { if (s.start) { start = s; } });
      var chips = [];
      chips.push(start
        ? '<span class="chip ok">Startzustand ' + esc(start.name) + '</span>'
        : '<span class="chip warn">kein Startzustand</span>');
      if (!A.states.some(function (s) { return s.accept; })) {
        chips.push('<span class="chip warn">kein Endzustand</span>');
      }
      chips.push(nd.length
        ? '<span class="chip warn" title="' + esc(nd.join(' ')) + '">nicht deterministisch: ' + esc(nd.join(', ')) + '</span>'
        : '<span class="chip ok">deterministisch</span>');
      if (A.states.length && sigma.length) {
        chips.push(missing
          ? '<span class="chip warn">unvollstaendig: ' + missing + ' Uebergang' + (missing > 1 ? 'e' : '') + ' fehl' + (missing > 1 ? 'en' : 't') + '</span>'
          : '<span class="chip ok">vollstaendig</span>');
      }
      if (els.chips) { els.chips.innerHTML = chips.join(''); }
      if (els.tuple) { els.tuple.innerHTML = tupleText(A); }
      if (!els.delta) { return; }
      if (!A.states.length || !sigma.length) { els.delta.innerHTML = ''; return; }
      var h = '<table class="delta"><caption class="muted" style="text-align:left;padding-bottom:4px">Uebergangsfunktion \u03b4 (Pfeil: Start, Stern: Ende)</caption><thead><tr><th>\u03b4</th>' + sigma.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
      A.states.forEach(function (s) {
        h += '<tr><th>' + (s.start ? '\u2192' : '') + (s.accept ? '*' : '') + esc(s.name) + '</th>';
        sigma.forEach(function (c) {
          var tg = Array.from(new Set(
            A.transitions.filter(function (t) { return t.from === s.id && t.symbols.indexOf(c) >= 0; })
              .map(function (t) { return st(t.to).name; })
          ));
          h += tg.length
            ? '<td>' + (tg.length > 1 ? '{' + tg.map(esc).join(', ') + '}' : esc(tg[0])) + '</td>'
            : '<td class="miss">\u2013</td>';
        });
        h += '</tr>';
      });
      els.delta.innerHTML = h + '</tbody></table>';
    }
    if (els.sigma) {
      els.sigma.addEventListener('change', function (e) {
        var syms = parseSymbols(e.target.value);
        var used = new Set();
        A.transitions.forEach(function (t) { t.symbols.forEach(function (c) { used.add(c); }); });
        var dropped = Array.from(used).filter(function (c) { return syms.indexOf(c) < 0; });
        commit();
        A.alphabet = sortSyms(Array.from(new Set(syms.concat(Array.from(used)))));
        changed();
        if (dropped.length) { toast(dropped.join(', ') + ' bleibt im Alphabet, weil es in Uebergaengen vorkommt.'); }
      });
      els.sigma.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.target.blur(); } });
    }

    /* --- Trap --- */
    function doTrap() {
      if (!A.states.length) { toast('Lege zuerst Zustaende an.'); return; }
      var sigma = effAlphabet();
      if (!sigma.length) { toast('Lege zuerst Uebergaenge oder das Eingabealphabet fest.'); return; }
      if (!missingPairs(A, sigma).length) { toast('Der Automat ist schon vollstaendig. Ein Trap-Zustand ist nicht noetig.'); return; }
      commit();
      var res = applyTrap(A);
      A = res.automaton;
      var trapState = null;
      A.states.forEach(function (s) { if (s.trap) { trapState = s; } });
      sel = trapState ? { kind: 'state', id: trapState.id } : null;
      changed();
      toast(res.added + ' fehlende Uebergaenge fuehren jetzt in den Trap-Zustand ' + res.trapName + '.');
    }
    if (els.trapBtn) { els.trapBtn.addEventListener('click', doTrap); }

    /* --- Simulation --- */
    function startSim() {
      var word = els.word.value;
      var r = simulateAutomaton(A, word);
      sim = { word: word, chars: r.chars, steps: r.steps, stuck: r.stuck, error: r.error, accepted: r.accepted, reason: r.reason, pos: 0 };
    }
    function simFinished() { return sim && !sim.error && sim.pos >= sim.steps.length - 1; }
    function renderSim() {
      var out = els.out;
      if (!sim) {
        out.innerHTML = '<p class="muted">Pruefen zeigt sofort das Ergebnis. Schritt geht das Wort Zeichen fuer Zeichen durch und markiert den aktuellen Zustand.</p>';
        render(); return;
      }
      if (sim.error) {
        out.innerHTML = '<div class="notice bad"><strong>Simulation nicht moeglich</strong>' + esc(sim.error) + '</div>';
        render(); return;
      }
      var done = sim.pos, fin = simFinished();
      var tape = sim.chars.length ? sim.chars.map(function (c, i) {
        var cl = 'cell';
        if (i < done) { cl += ' done'; }
        else if (fin && sim.stuck === i) { cl += ' fail'; }
        else if (i === done && !fin) { cl += ' next'; }
        return '<span class="' + cl + '">' + esc(c) + '</span>';
      }).join('') : '<span class="eps">\u03b5 (leeres Wort)</span>';
      var path = '';
      sim.steps.slice(0, done + 1).forEach(function (s, i) {
        var nm = s.cur.length > 1 ? '{' + nameList(A, s.cur) + '}' : nameList(A, s.cur);
        if (i > 0) { path += ' <span class="arr">-' + esc(s.ch) + '-></span> '; }
        path += i === done ? '<span class="cur">' + esc(nm) + '</span>' : esc(nm);
      });
      var res = '';
      if (fin) {
        res = sim.accepted
          ? '<div class="notice ok"><strong>akzeptiert</strong>' + esc(sim.reason) + '</div>'
          : '<div class="notice bad"><strong>nicht akzeptiert</strong>' + esc(sim.reason) + '</div>';
      } else {
        res = '<div class="notice info">Gelesen: ' + done + ' von ' + sim.chars.length + ' Zeichen</div>';
      }
      out.innerHTML = '<div class="tape" aria-label="Eingabeband">' + tape + '</div><div class="path">' + path + '</div>' + res;
      render();
    }
    function stopPlay() {
      if (playTimer) { clearInterval(playTimer); playTimer = null; }
      if (els.play) { els.play.textContent = '\u25b6 Abspielen'; }
    }
    if (els.run) {
      els.run.addEventListener('click', function () {
        stopPlay(); startSim();
        if (!sim.error) { sim.pos = sim.steps.length - 1; }
        renderSim();
      });
    }
    if (els.step) {
      els.step.addEventListener('click', function () {
        stopPlay();
        if (!sim || sim.word !== els.word.value || simFinished()) { startSim(); }
        else { sim.pos++; }
        renderSim();
      });
    }
    if (els.play) {
      els.play.addEventListener('click', function () {
        if (playTimer) { stopPlay(); return; }
        if (!sim || sim.word !== els.word.value || simFinished()) { startSim(); }
        renderSim();
        if (sim.error || simFinished()) { return; }
        els.play.textContent = 'Pause';
        playTimer = setInterval(function () {
          sim.pos++; renderSim();
          if (simFinished()) { stopPlay(); }
        }, 800);
      });
    }
    if (els.reset) {
      els.reset.addEventListener('click', function () { stopPlay(); sim = null; renderSim(); });
    }
    if (els.word) {
      els.word.addEventListener('keydown', function (e) { if (e.key === 'Enter') { els.run.click(); } });
    }
    if (els.batchBtn) {
      els.batchBtn.addEventListener('click', function () {
        var lines = els.batchIn.value.split('\n').map(function (l) { return l.trim(); }).filter(function (l) { return l.length; });
        if (!A.states.some(function (s) { return s.start; })) { toast('Es ist kein Startzustand festgelegt.'); return; }
        els.batchOut.innerHTML = lines.map(function (l) {
          var w = (l === '\u03b5' || l === 'eps') ? '' : l;
          var r = simulateAutomaton(A, w);
          return '<li><span class="' + (r.accepted ? 'y' : 'n') + '">' + (r.accepted ? '\u2713' : '\u2717') + '</span><span>' + (w ? esc(w) : '\u03b5') + '</span></li>';
        }).join('');
      });
    }

    /* --- Export --- */
    function download(blob, name) {
      var a = doc.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = name;
      doc.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
    }
    function fallbackSvg(str) {
      download(new Blob([str], { type: 'image/svg+xml' }), 'automat.svg');
      toast('PNG war in diesem Browser nicht moeglich. Das Bild wurde als automat.svg gespeichert.');
    }
    if (els.pngBtn) {
      els.pngBtn.addEventListener('click', function () {
        if (!A.states.length) { toast('Es gibt noch nichts zu exportieren.'); return; }
        var ex = exportSvgString(A);
        var str = ex.str, w = ex.w, h = ex.h;
        if (typeof Image === 'undefined' || typeof doc.createElement('canvas') === 'undefined') { fallbackSvg(str); return; }
        var img = new Image();
        img.onload = function () {
          var scale = 3, c = doc.createElement('canvas');
          c.width = Math.ceil(w * scale); c.height = Math.ceil(h * scale);
          var ctx = c.getContext('2d');
          ctx.scale(scale, scale); ctx.drawImage(img, 0, 0, w, h);
          try {
            c.toBlob(function (bl) {
              if (bl) { download(bl, 'automat.png'); toast('Bild automat.png gespeichert.'); }
              else { fallbackSvg(str); }
            }, 'image/png');
          } catch (err) { fallbackSvg(str); }
        };
        img.onerror = function () { fallbackSvg(str); };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(str);
      });
    }
    if (els.newBtn) {
      els.newBtn.addEventListener('click', function () {
        if (!A.states.length) { return; }
        commit(); A = emptyAutomaton(); sel = null;
        changed(); view = { tx: 0, ty: 0, k: 1 }; render();
        toast('Neuer, leerer Automat.');
      });
    }
    if (els.undoBtn) { els.undoBtn.addEventListener('click', undo); }
    if (els.redoBtn) { els.redoBtn.addEventListener('click', redo); }

    /* --- Tastatur --- */
    doc.addEventListener('keydown', function (e) {
      var active = doc.activeElement;
      var typing = active && /^(INPUT|TEXTAREA)$/.test(active.tagName);
      if ((e.ctrlKey || e.metaKey) && !typing) {
        var k = (e.key || '').toLowerCase();
        if (k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
        else if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
        return;
      }
      if (typing) { return; }
      if ((e.key === 'Delete' || e.key === 'Backspace') && sel) {
        e.preventDefault();
        if (sel.kind === 'state') { deleteState(sel.id); } else { deleteTrans(sel.id); }
      } else if (e.key === 'Escape') {
        pendingFrom = null; sel = null; render(); renderPanel();
      } else if (e.key === 'a') { setMode('select'); }
      else if (e.key === 'z') { setMode('state'); }
      else if (e.key === 'u') { setMode('trans'); }
      else if (e.key === 'l') { setMode('delete'); }
    });

    /* --- Start --- */
    setMode('select');
    renderPanel(); renderDefinition(); renderSim();
    if (stage.clientWidth) { fitView(); } else { render(); }

    return {
      setMode: setMode,
      getState: function () { return A; },
      fitView: fitView,
      saveNow: saveNow
    };
  }

  /* ---------- Exports ---------- */

  var api = {
    R: R,
    DEFAULT_PAL: DEFAULT_PAL,
    EXPORT_PAL: EXPORT_PAL,
    parseSymbols: parseSymbols,
    sortSyms: sortSyms,
    emptyAutomaton: emptyAutomaton,
    exampleAutomaton: exampleAutomaton,
    normalizeAutomaton: normalizeAutomaton,
    serializeAutomaton: serializeAutomaton,
    effectiveAlphabet: effectiveAlphabet,
    analyseAutomaton: analyseAutomaton,
    missingPairs: missingPairs,
    applyTrap: applyTrap,
    simulateAutomaton: simulateAutomaton,
    edgeGeom: edgeGeom,
    defsMarkup: defsMarkup,
    sceneMarkup: sceneMarkup,
    bbox: bbox,
    exportSvgString: exportSvgString,
    tupleText: tupleText,
    attach: attach
  };

  if (typeof window !== 'undefined') { window.IC_AUTOMAT = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
  if (typeof document !== 'undefined' && document.getElementById('amt-ide')) {
    attach(document);
  }
})();
