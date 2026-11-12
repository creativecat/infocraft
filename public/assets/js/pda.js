/**
 * InfoCraft - Kellerautomat-Werkstatt (#164)
 *
 * Kellerautomaten (Pushdown-Automaten) zeichnen, Woerter mit Keller
 * pruefen, Akzeptanz per Endzustand oder leerem Keller. Die Engine ist
 * DOM-frei gehalten, damit sie in den UI-Tests ohne Browser laeuft.
 * Lehrplanbezug Informatik 11: formale Sprachen und Automaten.
 *
 * Uebergangsbeschriftung: lesbar wie in der Tafelschrift "a, A -> BC":
 * lese a, hole A vom Keller, lege B dann C ab (C liegt oben). Leeres
 * Zeichen heisst epsilon: nichts lesen oder nichts holen.
 *
 * Node-Tests: module.exports; Browser: window.IC_PDA.
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
  var DEFAULT_PAL = {
    paper: '#ffffff', ink: '#1e293b', accent: '#0f766e', accentSoft: '#ccfbf1',
    hl: '#fde68a', hlInk: '#7c2d12', hlLine: '#b45309', font: 'system-ui, sans-serif'
  };

  /* Gemeinsamer Werkzeugkasten (#173): im Browser vor pda.js geladen,
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

  function eps(v) { return String(v || '') === '' ? 'ε' : String(v); }

  function wordChars(word) {
    var w = String(word == null ? '' : word).trim();
    if (w === '' || w === 'ε' || w === '&epsilon;') { return []; }
    return Array.from(w);
  }

  /* ---------- Datenmodell (DOM-frei) ---------- */

  function emptyPDA() {
    return { alphabet: [], stack0: 'Z', accept: 'state', states: [], transitions: [] };
  }

  function examplePDA() {
    /* a^n b^n: beim Lesen von a liegt je ein A auf dem Stapel, jedes b
     * holt eines zurueck, am Ende fuehrt ein epsilon-Uebergang in den
     * Endzustand, sobald nur noch das Bodenzeichen liegt. */
    return {
      alphabet: ['a', 'b'], stack0: 'Z', accept: 'state',
      states: [
        { id: 'q0', name: 'q0', x: 140, y: 200, start: true, accept: false },
        { id: 'q1', name: 'q1', x: 330, y: 200, start: false, accept: false },
        { id: 'q2', name: 'q2', x: 520, y: 200, start: false, accept: true }
      ],
      transitions: [
        { id: 't1', from: 'q0', to: 'q0', read: 'a', pop: '', push: 'A' },
        { id: 't2', from: 'q0', to: 'q1', read: 'b', pop: 'A', push: '' },
        { id: 't3', from: 'q1', to: 'q1', read: 'b', pop: 'A', push: '' },
        { id: 't4', from: 'q1', to: 'q2', read: '', pop: 'Z', push: '' },
        { id: 't5', from: 'q0', to: 'q2', read: '', pop: 'Z', push: '' }
      ]
    };
  }

  function stateById(P, id) {
    var i;
    for (i = 0; i < P.states.length; i++) { if (P.states[i].id === id) { return P.states[i]; } }
    return null;
  }

  function normalizePDA(d) {
    if (!d || !Array.isArray(d.states) || !Array.isArray(d.transitions)) { return null; }
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
    var stack0 = String(d.stack0 != null && d.stack0 !== '' ? d.stack0 : 'Z').slice(0, 1);
    var accept = d.accept === 'stack' ? 'stack' : 'state';
    var k = 0;
    var transitions = d.transitions.filter(function (t) {
      return t && ids.has(String(t.from)) && ids.has(String(t.to));
    }).map(function (t) {
      k += 1;
      return {
        id: String(t.id != null ? t.id : ('t' + k)),
        from: String(t.from), to: String(t.to),
        read: String(t.read != null ? t.read : '').slice(0, 1),
        pop: String(t.pop != null ? t.pop : '').slice(0, 1),
        push: String(t.push != null ? t.push : '').slice(0, 4)
      };
    });
    var alpha = [];
    if (Array.isArray(d.alphabet)) { d.alphabet.forEach(function (c) { if (alpha.indexOf(String(c)) < 0) { alpha.push(String(c)); } }); }
    transitions.forEach(function (t) { if (t.read !== '' && alpha.indexOf(t.read) < 0) { alpha.push(t.read); } });
    alpha.sort();
    return { alphabet: alpha, stack0: stack0, accept: accept, states: states, transitions: transitions };
  }

  function parsePDA(text) {
    var data = null;
    try { data = JSON.parse(String(text)); } catch (e) { data = null; }
    return normalizePDA(data) || emptyPDA();
  }

  function serializePDA(P) {
    var d = JSON.parse(JSON.stringify(P));
    d.format = 'keller-automat';
    d.version = 1;
    return d;
  }

  /* ---------- Woerter pruefen (DOM-frei, #164) ----------
   * Der Kellerautomat ist nichtdeterministisch: epsilon-Uebergaenge und
   * mehrere Kandidaten pro Schritt sind erlaubt. Die Breitensuche haelt
   * alle moeglichen Konfigurationen (Zustand, Position, Keller) offen,
   * bis eine akzeptiert. Der Keller liegt als Array vor, das oberste
   * Zeichen steht hinten. */

  function stackFits(stack, pop) {
    if (pop === '') { return true; }
    return stack.length > 0 && stack[stack.length - 1] === pop;
  }

  function afterMove(stack, pop, push) {
    var s = stack.slice(0);
    if (pop !== '') { s.pop(); }
    for (var i = 0; i < push.length; i++) { s.push(push.charAt(i)); }
    return s;
  }

  function pdaRun(P, word, opts) {
    var syms = wordChars(word);
    var maxVisit = (opts && opts.maxVisit) || 30000;
    var startState = null;
    var i;
    for (i = 0; i < P.states.length; i++) { if (P.states[i].start) { startState = P.states[i]; break; } }
    if (!startState && P.states.length) { startState = P.states[0]; }
    if (!startState) { return { accepted: false, steps: [], truncated: false, reason: 'kein Zustand' }; }
    var start = { state: startState.id, pos: 0, stack: [P.stack0], path: [] };
    var queue = [start];
    var seen = {};
    var visits = 0;
    var win = null;
    var truncated = false;
    while (queue.length > 0) {
      var cfg = queue.shift();
      var key = cfg.state + '|' + cfg.pos + '|' + cfg.stack.join('');
      if (seen[key]) { continue; }
      seen[key] = true;
      if (++visits > maxVisit) { truncated = true; break; }
      var st = stateById(P, cfg.state);
      var atEnd = cfg.pos === syms.length;
      var stackEmpty = cfg.stack.length === 0;
      var okState = P.accept !== 'stack' && st && st.accept && atEnd;
      var okStack = P.accept === 'stack' && atEnd && stackEmpty;
      if (okState || okStack) { win = cfg; break; }
      /* Nachfolger: lesende Uebergaenge, dann epsilon-Uebergaenge. */
      var t, nc;
      for (t = 0; t < P.transitions.length; t++) {
        var tr = P.transitions[t];
        if (tr.from !== cfg.state) { continue; }
        var consume = tr.read !== '';
        if (consume) {
          if (!atEnd && tr.read === syms[cfg.pos] && stackFits(cfg.stack, tr.pop)) {
            nc = {
              state: tr.to, pos: cfg.pos + 1,
              stack: afterMove(cfg.stack, tr.pop, tr.push),
              path: cfg.path.concat([{ via: tr.id, read: tr.read, eps: false }])
            };
            queue.push(nc);
          }
        } else if (stackFits(cfg.stack, tr.pop)) {
          nc = {
            state: tr.to, pos: cfg.pos,
            stack: afterMove(cfg.stack, tr.pop, tr.push),
            path: cfg.path.concat([{ via: tr.id, read: '', eps: true }])
          };
          queue.push(nc);
        }
      }
    }
    if (!win) { return { accepted: false, steps: traceSteps(P, startState, syms, null, [P.stack0]), truncated: truncated, reason: truncated ? 'abgebrochen' : 'abgelehnt' }; }
    return { accepted: true, steps: traceSteps(P, startState, syms, win, [P.stack0]), truncated: false, reason: 'akzeptiert' };
  }

  /* Rekonstruiert die Schrittliste einer akzeptierten Konfiguration:
   * je Schritt Zustand, gelesenes Zeichen und Keller danach. */
  function traceSteps(P, startState, syms, win, initStack) {
    var steps = [{ state: startState.name, read: '', stack: initStack.join('') || '—', eps: false }];
    if (!win) { return steps; }
    var cfg = { state: startState.id, pos: 0, stack: initStack.slice(0) };
    var i, tr;
    for (i = 0; i < win.path.length; i++) {
      var mv = win.path[i];
      tr = null;
      var t;
      for (t = 0; t < P.transitions.length; t++) {
        if (P.transitions[t].id === mv.via) { tr = P.transitions[t]; break; }
      }
      if (!tr) { continue; }
      if (!mv.eps) { cfg.pos += 1; }
      cfg.stack = afterMove(cfg.stack, tr.pop, tr.push);
      cfg.state = tr.to;
      steps.push({
        state: stateById(P, cfg.to || tr.to) ? stateById(P, tr.to).name : tr.to,
        read: mv.eps ? 'ε' : mv.read,
        stack: cfg.stack.join('') || '—',
        eps: mv.eps
      });
    }
    return steps;
  }

  function analysePDA(P) {
    var sigma = [];
    var i, t;
    for (i = 0; i < P.alphabet.length; i++) { if (sigma.indexOf(P.alphabet[i]) < 0) { sigma.push(P.alphabet[i]); } }
    for (i = 0; i < P.transitions.length; i++) {
      t = P.transitions[i];
      if (t.read !== '' && sigma.indexOf(t.read) < 0) { sigma.push(t.read); }
    }
    sigma.sort();
    var finals = [];
    for (i = 0; i < P.states.length; i++) { if (P.states[i].accept) { finals.push(P.states[i].name); } }
    return {
      sigma: sigma,
      finals: finals,
      acceptMode: P.accept,
      stack0: P.stack0,
      epsMoves: P.transitions.filter(function (tr) { return tr.read === ''; }).length
    };
  }

  /* ---------- Szene (SVG-Markup, DOM-frei) ---------- */

  function transLabel(t) {
    return eps(t.read) + ', ' + eps(t.pop) + ' → ' + eps(t.push);
  }

  function edgeGeom(P, t) {
    var a = stateById(P, t.from), b = stateById(P, t.to);
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

  function sceneMarkup(P, opts) {
    opts = opts || {};
    var Pal = opts.P || DEFAULT_PAL;
    var interactive = !!opts.interactive;
    var edges = '', hits = '', labels = '', nodes = '';
    P.transitions.forEach(function (t) {
      var g = edgeGeom(P, t);
      if (!g) { return; }
      var isSel = opts.sel && opts.sel.kind === 'trans' && opts.sel.id === t.id;
      var col = isSel ? Pal.accent : Pal.ink;
      var mk = isSel ? 'aSel' : 'aInk';
      edges += '<path d="' + g.d + '" fill="none" stroke="' + col + '" stroke-width="' + (isSel ? 2.8 : 1.7) + '" marker-end="url(#' + mk + ')"/>';
      var dk = interactive ? ' data-kind="trans" data-id="' + t.id + '"' : '';
      if (interactive) {
        hits += '<path d="' + g.d + '" fill="none" stroke="transparent" stroke-width="18"' + dk + ' style="cursor:pointer"/>';
      }
      labels += '<text x="' + g.lx + '" y="' + g.ly + '" text-anchor="middle" dominant-baseline="central" font-family="' + Pal.font + '" font-size="14" font-weight="' + (isSel ? 700 : 400) + '" fill="' + col + '" stroke="' + Pal.paper + '" stroke-width="6" stroke-linejoin="round" paint-order="stroke"' + dk + ' style="cursor:pointer">' + esc(transLabel(t)) + '</text>';
    });
    P.states.forEach(function (s) {
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

  function bbox(P) {
    if (!P.states.length) { return null; }
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    P.states.forEach(function (s) {
      x0 = Math.min(x0, s.x); y0 = Math.min(y0, s.y);
      x1 = Math.max(x1, s.x); y1 = Math.max(y1, s.y);
    });
    var loops = P.transitions.some(function (t) { return t.from === t.to; });
    return { x0: x0 - R - 52, y0: y0 - R - (loops ? 62 : 30), x1: x1 + R + 30, y1: y1 + R + 34 };
  }

  function exportSvgString(P) {
    var b = bbox(P) || { x0: 0, y0: 0, x1: 600, y1: 400 };
    var Pal = DEFAULT_PAL, pad = 20;
    var x = b.x0 - pad, y = b.y0 - pad, w = b.x1 - b.x0 + 2 * pad, h = b.y1 - b.y0 + 2 * pad;
    return {
      w: w, h: h,
      str: '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="' + x + ' ' + y + ' ' + w + ' ' + h + '"><defs>' + defsMarkup(Pal) + '</defs><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="#ffffff"/>' + sceneMarkup(P, { interactive: false }) + '</svg>'
    };
  }

  /* ---------- Editor (attach) ---------- */

  function attach(doc) {
    var root = doc.getElementById('pda-ide');
    if (!root) { return null; }
    var els = {
      canvas: doc.getElementById('pda-canvas'),
      hint: doc.getElementById('pda-hint'),
      pop: doc.getElementById('pda-pop'),
      popLbl: doc.getElementById('pda-pop-lbl'),
      popIn: doc.getElementById('pda-pop-in'),
      popOk: doc.getElementById('pda-pop-ok'),
      sel: doc.getElementById('pda-sel'),
      word: doc.getElementById('pda-word'),
      run: doc.getElementById('pda-run'),
      reset: doc.getElementById('pda-reset'),
      out: doc.getElementById('pda-out'),
      stack0: doc.getElementById('pda-stack0'),
      batchIn: doc.getElementById('pda-batch-in'),
      batchBtn: doc.getElementById('pda-batch-btn'),
      batchOut: doc.getElementById('pda-batch-out'),
      undo: doc.getElementById('pda-undo'),
      redo: doc.getElementById('pda-redo'),
      zoomIn: doc.getElementById('pda-zoom-in'),
      zoomOut: doc.getElementById('pda-zoom-out'),
      fit: doc.getElementById('pda-fit'),
      png: doc.getElementById('pda-png'),
      play: doc.getElementById('pda-play'),
      tuple: doc.getElementById('pda-tuple'),
      delta: doc.getElementById('pda-delta'),
      form: doc.getElementById('pda-save-form'),
      json: doc.getElementById('pda-json'),
      saveInfo: doc.getElementById('pda-saveinfo')
    };
    var P = parsePDA(els.json ? els.json.value : '');
    var mode = 'select';
    var sel = null;
    var pending = null;
    var stateSeq = P.states.length;
    var saveTimer = null;
    /* Ansicht (Zoom/Einpassen/Schieben) und Historie (Undo/Redo) aus dem
     * gemeinsamen Werkzeugkasten (#173). */
    var view = (TOOLS && els.canvas) ? TOOLS.makeView(els.canvas, { x: 0, y: 0, w: 900, h: 560 }) : null;
    var hist = (TOOLS && els.json) ? TOOLS.makeHistory({
      snapshot: function () { return JSON.stringify(serializePDA(currentPDA())); },
      restore: function (snap) {
        P = parsePDA(snap);
        sel = null;
        stateSeq = P.states.length;
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
      els.canvas.innerHTML = defsMarkup(DEFAULT_PAL) + sceneMarkup(P, {
        interactive: true, sel: sel, pending: pending && pending.kind === 'state' ? pending.id : null
      });
      renderDesc();
    }

    /* Formale Beschreibung (#173): 7-Tupel und Uebergangstabelle aus dem
     * aktuellen Automaten, ohne eigene Eingabefelder. */
    function renderDesc() {
      if (!els.tuple && !els.delta) { return; }
      var an = analysePDA(P);
      var names = [];
      var i;
      for (i = 0; i < P.states.length; i++) { names.push(P.states[i].name); }
      var gamma = [P.stack0 || 'Z'];
      var pushChars = [];
      for (i = 0; i < P.transitions.length; i++) {
        var pushes = String(P.transitions[i].push || '');
        for (var j = 0; j < pushes.length; j++) { pushChars.push(pushes[j]); }
      }
      for (i = 0; i < pushChars.length; i++) {
        if (pushChars[i] !== '' && gamma.indexOf(pushChars[i]) < 0) { gamma.push(pushChars[i]); }
      }
      var start = '';
      for (i = 0; i < P.states.length; i++) { if (P.states[i].start) { start = P.states[i].name; break; } }
      if (els.tuple) {
        els.tuple.textContent = 'P = (Q, Σ, Γ, Δ, q0, Z0, E) mit'
          + '  Q = {' + names.join(', ') + '}'
          + '  Σ = {' + an.sigma.join(', ') + '}'
          + '  Γ = {' + gamma.join(', ') + '}'
          + '  q0 = ' + (start || '–')
          + '  Z0 = ' + (P.stack0 || 'Z')
          + '  E = {' + an.finals.join(', ') + '}'
          + (an.acceptMode === 'stack' ? '  Akzeptanz: leerer Keller' : '  Akzeptanz: Endzustand');
      }
      if (els.delta) {
        while (els.delta.firstChild) { els.delta.removeChild(els.delta.firstChild); }
        if (TOOLS && TOOLS.deltaTable) {
          var rows = [];
          for (i = 0; i < P.transitions.length; i++) {
            var tr = P.transitions[i];
            var fromName = stateById(P, tr.from);
            var toName = stateById(P, tr.to);
            rows.push([
              fromName ? fromName.name : tr.from,
              eps(tr.read),
              eps(tr.pop),
              eps(tr.push),
              toName ? toName.name : tr.to
            ]);
          }
          els.delta.appendChild(TOOLS.deltaTable(doc, ['von', 'liest', 'Keller', 'legt ab', 'nach'], rows));
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
      els.json.value = JSON.stringify(serializePDA(currentPDA()));
      pushSave();
    }

    function addState(x, y) {
      stateSeq += 1;
      var id = 'q' + stateSeq;
      var st = { id: id, name: id, x: x || 120 + P.states.length * 90, y: y || 200, start: P.states.length === 0, accept: false };
      P.states.push(st);
      sel = { kind: 'state', id: id };
      render(); renderSel(); autosave();
      return st;
    }

    function delState(id) {
      P.states = P.states.filter(function (s) { return s.id !== id; });
      P.transitions = P.transitions.filter(function (t) { return t.from !== id && t.to !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    function delTrans(id) {
      P.transitions = P.transitions.filter(function (t) { return t.id !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    function addTrans(fromId, toId, label) {
      /* Beschriftung "a, A, BC" oder "a,A->BC": lesen, holen, ablegen. */
      var parts = String(label || '').split(/[,\s]+/).filter(Boolean);
      var read = parts[0] === 'ε' || parts[0] === undefined ? '' : String(parts[0] || '').slice(0, 1);
      var pop = parts[1] === 'ε' || parts[1] === undefined ? '' : String(parts[1] || '').slice(0, 1);
      var push = parts[2] === 'ε' || parts[2] === undefined ? '' : String(parts[2] || '').slice(0, 4);
      if (!parts.length) { read = ''; pop = ''; push = ''; }
      var tr = { id: 't' + (P.transitions.length + 1) + '_' + Date.now() % 100000, from: fromId, to: toId, read: read, pop: pop, push: push };
      P.transitions.push(tr);
      sel = { kind: 'trans', id: tr.id };
      render(); renderSel(); autosave();
      return tr;
    }

    function renderSel() {
      if (!els.sel) { return; }
      els.sel.textContent = '';
      if (!sel) { els.sel.textContent = 'Nichts ausgewählt. Werkzeuge: Zustand anlegen, Übergang anlegen (zuerst Quelle, dann Ziel), Löschen.'; return; }
      if (sel.kind === 'state') {
        var st = stateById(P, sel.id);
        if (!st) { sel = null; return; }
        els.sel.appendChild(selRow('Name', st.name, function (v) { st.name = String(v).slice(0, 8) || st.name; render(); autosave(); }));
        appendCheck(els.sel, 'Startzustand', st.start, function (v) {
          P.states.forEach(function (s2) { s2.start = false; });
          st.start = !!v; render(); autosave();
        });
        appendCheck(els.sel, 'Endzustand', st.accept, function (v) { st.accept = !!v; render(); autosave(); });
        var del = doc.createElement('button');
        del.className = 'btn'; del.type = 'button'; del.textContent = 'Zustand löschen';
        del.addEventListener('click', function () { delState(st.id); });
        els.sel.appendChild(del);
      } else {
        var t = null, i;
        for (i = 0; i < P.transitions.length; i++) { if (P.transitions[i].id === sel.id) { t = P.transitions[i]; break; } }
        if (!t) { sel = null; return; }
        els.sel.appendChild(selRow('Beschriftung', t.read + ', ' + t.pop + ' → ' + t.push, function (v) {
          var p2 = String(v).split(/[,\s]+/).filter(Boolean);
          t.read = p2[0] === 'ε' ? '' : String(p2[0] || '').slice(0, 1);
          t.pop = p2[1] === 'ε' ? '' : String(p2[1] || '').slice(0, 1);
          t.push = p2[2] === 'ε' ? '' : String(p2[2] || '').slice(0, 4);
          render(); autosave();
        }));
        var delT = doc.createElement('button');
        delT.className = 'btn'; delT.type = 'button'; delT.textContent = 'Übergang löschen';
        delT.addEventListener('click', function () { delTrans(t.id); });
        els.sel.appendChild(delT);
      }
    }

    function selRow(label, value, onChange) {
      var wrap = doc.createElement('label');
      wrap.className = 'field';
      var span = doc.createElement('span');
      span.textContent = label;
      var inp = doc.createElement('input');
      inp.className = 'inp mono';
      inp.value = value == null ? '' : String(value);
      inp.setAttribute('autocomplete', 'off');
      inp.addEventListener('change', function () { onChange(inp.value); });
      wrap.appendChild(span);
      wrap.appendChild(inp);
      return wrap;
    }

    function appendCheck(parentEl, label, value, onChange) {
      var lab = doc.createElement('label');
      lab.className = 'check';
      var cb = doc.createElement('input');
      cb.type = 'checkbox';
      cb.checked = !!value;
      cb.addEventListener('change', function () { onChange(cb.checked); });
      var span = doc.createElement('span');
      span.textContent = label;
      lab.appendChild(cb);
      lab.appendChild(span);
      parentEl.appendChild(lab);
    }

    /* Klicks: Zustand anlegen, Uebergang in zwei Schritten, Loeschen. */
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
      /* Schieben der Flaeche im Auswahlmodus (#173): Leere Stelle anfassen
       * und ziehen; beim Loslassen ist nichts ausgewaehlt. */
      var panFrom = null;
      els.canvas.addEventListener('pointerdown', function (ev) {
        var hit = hitTarget(ev);
        if (mode === 'state') {
          var pt = view ? view.toWorld(Number(ev.clientX) || 0, Number(ev.clientY) || 0) : { x: Number(ev.clientX) || null, y: Number(ev.clientY) || null };
          addState(pt.x, pt.y);
          hint('Zustand angelegt. Klicke es an, um Start oder Ende zu setzen.');
          return;
        }
        if (mode === 'delete') {
          if (hit) { if (hit.kind === 'state') { delState(hit.id); } else { delTrans(hit.id); } }
          return;
        }
        if (mode === 'trans') {
          if (hit && hit.kind === 'state') {
            if (!pending) { pending = { kind: 'state', id: hit.id }; hint('Ziel anklicken.'); render(); }
            else if (pending.id !== hit.id) {
              pending = null;
              if (els.pop && els.popIn) {
                els.pop.hidden = false;
                els.popIn.value = 'a, ε, ε';
                try { els.popIn.focus(); } catch (e) { /* egal */ }
                els.popOk.addEventListener('click', function () {
                  els.pop.hidden = true;
                  addTrans(sel && sel.kind === 'state' ? sel.id : hit.id, hit.id, els.popIn.value);
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
      if (els.fit) { els.fit.addEventListener('click', function () { view.fit(bbox(P), 50); }); }
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
        TOOLS.exportPng(doc, exportSvgString(currentPDA()), 'kellerautomat.png');
        hint('Bild wird als PNG geladen.');
      });
    }

    var toolBtns = root.querySelectorAll('[data-pda-mode]');
    Array.prototype.forEach.call(toolBtns, function (btn) {
      btn.addEventListener('click', function () {
        mode = btn.getAttribute('data-pda-mode') || 'select';
        pending = null;
        Array.prototype.forEach.call(toolBtns, function (b2) {
          b2.setAttribute('aria-pressed', b2 === btn ? 'true' : 'false');
        });
        hint(mode === 'state' ? 'Klicke auf die Fläche, um einen Zustand anzulegen.'
          : mode === 'trans' ? 'Erst den Quellzustand, dann den Zielzustand anklicken.'
          : mode === 'delete' ? 'Zustand oder Übergang anklicken, um ihn zu entfernen.'
          : '');
        render();
      });
    });

    function acceptMode() {
      var radios = root.querySelectorAll('input[name="pda-accept"]');
      var i;
      for (i = 0; i < radios.length; i++) {
        if (radios[i].checked) { return radios[i].value === 'stack' ? 'stack' : 'state'; }
      }
      return P.accept;
    }

    function currentPDA() {
      P.accept = acceptMode();
      if (els.stack0 && els.stack0.value) { P.stack0 = String(els.stack0.value).slice(0, 1); }
      return P;
    }

    function runWord(word, outEl) {
      var res = pdaRun(currentPDA(), word);
      var lines = [];
      var i;
      lines.push(res.accepted ? '✓ akzeptiert' : '✗ ' + (res.reason || 'abgelehnt'));
      for (i = 0; i < res.steps.length; i++) {
        var s = res.steps[i];
        lines.push((i === 0 ? 'Start' : 'Schritt ' + i) + ': ' + s.state + (s.read ? ' (liest ' + s.read + ')' : '') + ' Keller: ' + (s.stack === '—' ? 'leer' : s.stack.split('').join(' ')));
      }
      if (outEl) { outEl.textContent = lines.join('\n'); }
      return res;
    }

    /* Zustands-ID zum Namen suchen: fuer das Abspielen wird der laufende
     * Zustand in der Zeichnung hervorgehoben. */
    function stateIdByName(name) {
      var i;
      for (i = 0; i < P.states.length; i++) {
        if (P.states[i].name === name) { return P.states[i].id; }
      }
      return null;
    }

    /* Abspielen (#173): Die Schrittfolge laeuft mit festem Takt durch,
     * der jeweilige Zustand ist in der Zeichnung markiert. */
    function stopPlay() {
      if (player) { player.stop(); player = null; }
    }

    function playWord() {
      stopPlay();
      if (!TOOLS || !TOOLS.makePlayer) { return; }
      var res = pdaRun(currentPDA(), els.word ? els.word.value : '');
      var lines = [];
      player = TOOLS.makePlayer({
        steps: res.steps,
        show: function (idx, s) {
          sel = { kind: 'state', id: stateIdByName(s.state) };
          render();
          lines.push((idx === 0 ? 'Start' : 'Schritt ' + idx) + ': ' + s.state + (s.read ? ' (liest ' + s.read + ')' : '') + ' Keller: ' + (s.stack === '—' ? 'leer' : s.stack.split('').join(' ')));
          if (els.out) { els.out.textContent = lines.join('\n'); }
        },
        done: function () {
          if (els.out) { els.out.textContent = (res.accepted ? '✓ akzeptiert' : '✗ ' + (res.reason || 'abgelehnt')) + '\n' + lines.join('\n'); }
          player = null;
        }
      });
      player.play();
    }

    if (els.run) { els.run.addEventListener('click', function () { stopPlay(); sel = null; runWord(els.word ? els.word.value : '', els.out); render(); }); }
    if (els.play) { els.play.addEventListener('click', playWord); }
    if (els.reset) { els.reset.addEventListener('click', function () { stopPlay(); if (els.out) { els.out.textContent = ''; } }); }
    if (els.batchBtn) {
      els.batchBtn.addEventListener('click', function () {
        if (!els.batchOut) { return; }
        els.batchOut.textContent = '';
        var words = String(els.batchIn ? els.batchIn.value : '').split(/\n+/).map(function (w) { return w.trim(); }).filter(Boolean);
        words.forEach(function (w) {
          var res = pdaRun(currentPDA(), w);
          var li = doc.createElement('li');
          li.textContent = w + ': ' + (res.accepted ? '✓' : '✗');
          els.batchOut.appendChild(li);
        });
      });
    }
    if (els.stack0) { els.stack0.addEventListener('change', function () { P.stack0 = String(els.stack0.value).slice(0, 1) || 'Z'; autosave(); }); }
    var acceptBtns = root.querySelectorAll('input[name="pda-accept"]');
    Array.prototype.forEach.call(acceptBtns, function (rb) {
      rb.addEventListener('change', function () { P.accept = acceptMode(); autosave(); });
    });

    if (els.form) {
      els.form.addEventListener('submit', function () {
        if (els.json) { els.json.value = JSON.stringify(serializePDA(currentPDA())); }
      });
    }

    if (els.stack0) { els.stack0.value = P.stack0; }
    var initRbs = root.querySelectorAll('input[name="pda-accept"]');
    Array.prototype.forEach.call(initRbs, function (rb) {
      rb.checked = (rb.value === 'stack') === (P.accept === 'stack');
    });
    render();
    renderSel();

    return {
      pda: function () { return currentPDA(); },
      runWord: function (w) { return runWord(w, null); },
      addState: addState,
      addTrans: addTrans,
      delState: delState,
      delTrans: delTrans,
      render: render,
      serialize: function () { return JSON.stringify(serializePDA(currentPDA())); },
      exportSvg: function () { return exportSvgString(currentPDA()); },
      setMode: function (m) { mode = m; },
      /* Werkzeugkasten-Zugriff fuer die Tests (#173) */
      undo: function () { return hist ? hist.undo() : null; },
      redo: function () { return hist ? hist.redo() : null; },
      canUndo: function () { return hist ? hist.canUndo() : false; },
      fitView: function () { if (view) { view.fit(bbox(P), 50); } },
      zoomBy: function (f) { if (view) { view.zoom(f); } },
      viewBox: function () { return view ? view.cur() : null; },
      exportPng: function () { return TOOLS ? TOOLS.exportPng(doc, exportSvgString(currentPDA()), 'kellerautomat.png') : false; },
      playWord: playWord
    };
  }

  var api = {
    emptyPDA: emptyPDA,
    examplePDA: examplePDA,
    normalizePDA: normalizePDA,
    parsePDA: parsePDA,
    serializePDA: serializePDA,
    pdaRun: pdaRun,
    analysePDA: analysePDA,
    sceneMarkup: sceneMarkup,
    exportSvgString: exportSvgString,
    attach: attach
  };
  if (typeof window !== 'undefined') { window.IC_PDA = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
  if (typeof document !== 'undefined' && typeof document.getElementById === 'function' && document.getElementById('pda-ide')) {
    attach(document);
  }
}());
