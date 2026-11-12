/**
 * InfoCraft - Schaltwerk-Werkstatt (#166)
 *
 * Digitale Schaltungen bauen: Schalter, Lampen, UND/ODER/NICHT/NAND/
 * XOR, Taktgeber und D-Flipflops. Die Engine wertet das Netz stabil
 * aus (Iterieren bis sich nichts mehr aendert) und laesst den Takt
 * laufen. DOM-frei, damit sie in den UI-Tests ohne Browser laeuft.
 * Lehrplanbezug: Schaltwerke quer durch die Informatik.
 *
 * Node-Tests: module.exports; Browser: window.IC_LOGIC.
 *
 * @version 0.17.0
 * @license MIT (InfoCraft)
 */
(function () {
  'use strict';

  var KINDS = ['switch', 'lamp', 'and', 'or', 'not', 'nand', 'xor', 'clock', 'dff'];
  var KIND_LABEL = {
    switch: 'Schalter', lamp: 'Lampe', and: 'UND', or: 'ODER', not: 'NICHT',
    nand: 'NAND', xor: 'XOR', clock: 'Takt', dff: 'D-FF'
  };
  var CW = 64, CH = 44, GAPX = 40, GAPY = 30;

  /* ---------- Datenmodell (DOM-frei) ---------- */

  function emptyCircuit() {
    return { comps: [], wires: [] };
  }

  function exampleCircuit() {
    /* Halbaddierer: A XOR B -> Summe, A UND B -> Uebertrag. */
    return {
      comps: [
        { id: 'swA', kind: 'switch', x: 60, y: 80, state: 0, label: 'A' },
        { id: 'swB', kind: 'switch', x: 60, y: 220, state: 0, label: 'B' },
        { id: 'xS', kind: 'xor', x: 260, y: 140, state: 0 },
        { id: 'aC', kind: 'and', x: 260, y: 260, state: 0 },
        { id: 'lS', kind: 'lamp', x: 460, y: 140, state: 0, label: 'S' },
        { id: 'lC', kind: 'lamp', x: 460, y: 260, state: 0, label: 'C' }
      ],
      wires: [
        { id: 'w1', from: 'swA', to: 'xS' },
        { id: 'w2', from: 'swB', to: 'xS' },
        { id: 'w3', from: 'swA', to: 'aC' },
        { id: 'w4', from: 'swB', to: 'aC' },
        { id: 'w5', from: 'xS', to: 'lS' },
        { id: 'w6', from: 'aC', to: 'lC' }
      ]
    };
  }

  function compById(C, id) {
    var i;
    for (i = 0; i < C.comps.length; i++) { if (C.comps[i].id === id) { return C.comps[i]; } }
    return null;
  }

  function normalizeCircuit(d) {
    if (!d || !Array.isArray(d.comps) || !Array.isArray(d.wires)) { return null; }
    var comps = d.comps.filter(function (c) { return c && c.id != null && KINDS.indexOf(c.kind) >= 0; }).map(function (c, i) {
      return {
        id: String(c.id), kind: c.kind,
        x: Number(c.x) || 80 + (i % 4) * (CW + GAPX),
        y: Number(c.y) || 80 + Math.floor(i / 4) * (CH + GAPY),
        state: c.state ? 1 : 0,
        label: String(c.label != null ? c.label : '').slice(0, 4)
      };
    });
    var ids = new Set(comps.map(function (c) { return c.id; }));
    var k = 0;
    var wires = d.wires.filter(function (w) {
      return w && ids.has(String(w.from)) && ids.has(String(w.to)) && String(w.from) !== String(w.to);
    }).map(function (w) {
      k += 1;
      return { id: String(w.id != null ? w.id : ('w' + k)), from: String(w.from), to: String(w.to) };
    });
    return { comps: comps, wires: wires };
  }

  function parseCircuit(text) {
    var data = null;
    try { data = JSON.parse(String(text)); } catch (e) { data = null; }
    return normalizeCircuit(data) || emptyCircuit();
  }

  function serializeCircuit(C) {
    var d = JSON.parse(JSON.stringify(C));
    d.format = 'schaltwerk';
    d.version = 1;
    return d;
  }

  /* ---------- Auswertung (DOM-frei, #166) ----------
   * Ein Draht fuehrt vom Ausgang eines Bausteins zu einem Eingang.
   * Die Auswertung iteriert, bis sich keine Ausgangsleitung mehr
   * aendert; Rueckkopplungen ueber Gatter werden so bis zur Grenze
   * aufgeloest, das D-Flipflop haelt seinen Zustand als Speicher. */

  function inputsOf(C, id, vals) {
    var out = [];
    var i;
    for (i = 0; i < C.wires.length; i++) {
      if (C.wires[i].to === id) { out.push(vals[C.wires[i].from] ? 1 : 0); }
    }
    return out;
  }

  function gateValue(kind, ins) {
    var a = ins[0] || 0;
    var b = ins.length > 1 ? ins[1] : a;
    switch (kind) {
      case 'switch': case 'clock': case 'dff': return -1; /* Ausgang kommt woanders her */
      case 'lamp': return a;
      case 'not': return a ? 0 : 1;
      case 'and': return (a && b) ? 1 : 0;
      case 'or': return (a || b) ? 1 : 0;
      case 'nand': return (a && b) ? 0 : 1;
      case 'xor': return (a === b) ? 0 : 1;
    }
    return 0;
  }

  function evalCircuit(C, opts) {
    var maxIter = (opts && opts.maxIter) || 200;
    var vals = {};
    var i;
    for (i = 0; i < C.comps.length; i++) {
      var c = C.comps[i];
      vals[c.id] = (c.kind === 'switch' || c.kind === 'clock' || c.kind === 'dff') ? (c.state ? 1 : 0) : 0;
    }
    var iter = 0;
    var stable = false;
    for (iter = 0; iter < maxIter; iter++) {
      var next = {};
      var changed = false;
      for (i = 0; i < C.comps.length; i++) {
        var comp = C.comps[i];
        var v;
        if (comp.kind === 'switch' || comp.kind === 'clock' || comp.kind === 'dff') {
          v = comp.state ? 1 : 0;
        } else {
          var g = gateValue(comp.kind, inputsOf(C, comp.id, vals));
          v = g < 0 ? 0 : g;
        }
        next[comp.id] = v;
        if (v !== vals[comp.id]) { changed = true; }
      }
      vals = next;
      if (!changed) { stable = true; break; }
    }
    /* Lampen speichern das Ergebnis zurueck in die Komponente, damit
     * Szene und Serialisierung den letzten Zustand zeigen. */
    for (i = 0; i < C.comps.length; i++) {
      if (C.comps[i].kind === 'lamp' || C.comps[i].kind === 'and' || C.comps[i].kind === 'or' ||
          C.comps[i].kind === 'not' || C.comps[i].kind === 'nand' || C.comps[i].kind === 'xor') {
        C.comps[i].state = vals[C.comps[i].id] ? 1 : 0;
      }
    }
    return { values: vals, stable: stable, iters: iter, lamps: lampsOf(C, vals) };
  }

  function lampsOf(C, vals) {
    var out = [];
    var i;
    for (i = 0; i < C.comps.length; i++) {
      if (C.comps[i].kind === 'lamp') { out.push({ id: C.comps[i].id, label: C.comps[i].label || KIND_LABEL.lamp, on: vals[C.comps[i].id] ? 1 : 0 }); }
    }
    return out;
  }

  /* Ein Takt: D-Flipflops uebernehmen ihren D-Eingang, Taktgeber
   * wechseln, danach wird das Netz neu ausgewertet. Reine Funktion. */
  function tickCircuit(C) {
    var ev = evalCircuit(JSON.parse(JSON.stringify(C)));
    var next = JSON.parse(JSON.stringify(C));
    var i;
    for (i = 0; i < next.comps.length; i++) {
      var c = next.comps[i];
      if (c.kind === 'clock') { c.state = c.state ? 0 : 1; }
      else if (c.kind === 'dff') {
        var ins = inputsOf(C, c.id, ev.values);
        c.state = ins.length > 0 ? (ins[0] ? 1 : 0) : 0;
      }
    }
    return next;
  }

  function runCircuit(C, n) {
    var states = [];
    var cur = JSON.parse(JSON.stringify(C));
    var i;
    for (i = 0; i < (n || 8); i++) {
      var ev = evalCircuit(cur);
      states.push({ values: ev.values, lamps: ev.lamps });
      cur = tickCircuit(cur);
    }
    return { states: states, final: cur };
  }

  function analyseCircuit(C) {
    var counts = {};
    var i;
    for (i = 0; i < KINDS.length; i++) { counts[KINDS[i]] = 0; }
    for (i = 0; i < C.comps.length; i++) { counts[C.comps[i].kind] += 1; }
    return { counts: counts, wires: C.wires.length };
  }

  /* ---------- Szene (SVG-Markup, DOM-frei) ---------- */

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function inPos(c) { return { x: c.x, y: c.y + CH / 2 - 6 }; }
  function outPos(c) { return { x: c.x + CW, y: c.y + 6 }; }

  function sceneMarkup(C, opts) {
    opts = opts || {};
    var interactive = !!opts.interactive;
    var vals = (opts.values) || null;
    var wires = '', hits = '', nodes = '';
    C.wires.forEach(function (w) {
      var a = compById(C, w.from), b = compById(C, w.to);
      if (!a || !b) { return; }
      var pa = outPos(a), pb = inPos(b);
      var on = vals ? vals[w.from] : null;
      var col = on === 1 ? '#16a34a' : (on === 0 ? '#94a3b8' : '#475569');
      var sw = on === 1 ? 3 : 1.8;
      wires += '<path d="M' + pa.x + ' ' + pa.y + ' C ' + (pa.x + 40) + ' ' + pa.y + ', ' + (pb.x - 40) + ' ' + pb.y + ', ' + pb.x + ' ' + pb.y + '" fill="none" stroke="' + col + '" stroke-width="' + sw + '"/>';
      var dk = interactive ? ' data-kind="wire" data-id="' + w.id + '"' : '';
      if (interactive) {
        hits += '<path d="M' + pa.x + ' ' + pa.y + ' C ' + (pa.x + 40) + ' ' + pa.y + ', ' + (pb.x - 40) + ' ' + pb.y + ', ' + pb.x + ' ' + pb.y + '" fill="none" stroke="transparent" stroke-width="16"' + dk + ' style="cursor:pointer"/>';
      }
    });
    C.comps.forEach(function (c) {
      var v = vals ? vals[c.id] : c.state;
      var isSel = opts.sel && opts.sel.kind === 'comp' && opts.sel.id === c.id;
      var stroke = isSel ? '#0f766e' : '#1e293b';
      var fill = '#ffffff';
      if (c.kind === 'switch') { fill = c.state ? '#fde68a' : '#ffffff'; }
      if (c.kind === 'clock') { fill = c.state ? '#fde68a' : '#ffffff'; }
      if (c.kind === 'lamp') { fill = v ? '#fde68a' : '#f1f5f9'; }
      var dk = interactive ? ' data-kind="comp" data-id="' + c.id + '" style="cursor:pointer"' : '';
      var g = '<g' + dk + '>';
      g += '<rect x="' + c.x + '" y="' + c.y + '" width="' + CW + '" height="' + CH + '" rx="8" fill="' + fill + '" stroke="' + stroke + '" stroke-width="' + (isSel ? 3 : 2) + '"/>';
      var lbl = (c.kind === 'switch' || c.kind === 'clock' || c.kind === 'lamp') && c.label ? c.label : KIND_LABEL[c.kind];
      g += '<text x="' + (c.x + CW / 2) + '" y="' + (c.y + CH / 2) + '" text-anchor="middle" dominant-baseline="central" font-family="system-ui, sans-serif" font-size="14" font-weight="700" fill="#1e293b">' + esc(lbl) + '</text>';
      if (c.kind === 'lamp' && v) { g += '<circle cx="' + (c.x + CW - 8) + '" cy="' + (c.y + 8) + '" r="4" fill="#f59e0b"/>'; }
      g += '</g>';
      nodes += g;
    });
    return hits + wires + nodes;
  }

  function bbox(C) {
    if (!C.comps.length) { return null; }
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    C.comps.forEach(function (c) {
      x0 = Math.min(x0, c.x); y0 = Math.min(y0, c.y);
      x1 = Math.max(x1, c.x + CW); y1 = Math.max(y1, c.y + CH);
    });
    return { x0: x0 - 40, y0: y0 - 40, x1: x1 + 40, y1: y1 + 40 };
  }

  function exportSvgString(C) {
    var ev = evalCircuit(JSON.parse(JSON.stringify(C)));
    var b = bbox(C) || { x0: 0, y0: 0, x1: 600, y1: 400 };
    var pad = 16;
    var x = b.x0 - pad, y = b.y0 - pad, w = b.x1 - b.x0 + 2 * pad, h = b.y1 - b.y0 + 2 * pad;
    return {
      w: w, h: h,
      str: '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="' + x + ' ' + y + ' ' + w + ' ' + h + '"><rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="#ffffff"/>' + sceneMarkup(C, { values: ev.values }) + '</svg>'
    };
  }

  /* ---------- Editor (attach) ---------- */

  function attach(doc) {
    var root = doc.getElementById('lc-ide');
    if (!root) { return null; }
    var els = {
      canvas: doc.getElementById('lc-canvas'),
      hint: doc.getElementById('lc-hint'),
      sel: doc.getElementById('lc-sel'),
      out: doc.getElementById('lc-out'),
      form: doc.getElementById('lc-save-form'),
      json: doc.getElementById('lc-json'),
      saveInfo: doc.getElementById('lc-saveinfo')
    };
    var C = parseCircuit(els.json ? els.json.value : '');
    var mode = 'select';
    var placeKind = null;
    var sel = null;
    var wireFrom = null;
    var seq = C.comps.length;
    var saveTimer = null;
    var tickTimer = null;

    function hint(text) { if (els.hint) { els.hint.textContent = text || ''; } }

    function render() {
      if (!els.canvas) { return; }
      var ev = evalCircuit(jsonCopy(C));
      els.canvas.innerHTML = sceneMarkup(C, { interactive: true, sel: sel, values: ev.values });
      return ev;
    }

    function jsonCopy(o) { return JSON.parse(JSON.stringify(o)); }

    function autosave() {
      if (!els.json) { return; }
      els.json.value = JSON.stringify(serializeCircuit(C));
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

    function addComp(kind, x, y) {
      seq += 1;
      var id = kind.charAt(0) + seq;
      var comp = { id: id, kind: kind, x: x || 60 + (C.comps.length % 5) * (CW + GAPX), y: y || 80 + Math.floor(C.comps.length / 5) * (CH + GAPY), state: 0, label: '' };
      C.comps.push(comp);
      sel = { kind: 'comp', id: id };
      render(); renderSel(); autosave();
      return comp;
    }

    function delComp(id) {
      C.comps = C.comps.filter(function (c) { return c.id !== id; });
      C.wires = C.wires.filter(function (w) { return w.from !== id && w.to !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    function delWire(id) {
      C.wires = C.wires.filter(function (w) { return w.id !== id; });
      sel = null;
      render(); renderSel(); autosave();
    }

    function addWire(fromId, toId) {
      var w = { id: 'w' + (C.wires.length + 1) + '_' + Date.now() % 100000, from: fromId, to: toId };
      C.wires.push(w);
      sel = { kind: 'wire', id: w.id };
      render(); renderSel(); autosave();
      return w;
    }

    function renderSel() {
      if (!els.sel) { return; }
      els.sel.textContent = '';
      if (!sel) { els.sel.textContent = 'Nichts ausgewählt. Baustein wählen, dann klicken zum Platzieren. Schalter anklicken schaltet um.'; return; }
      if (sel.kind === 'comp') {
        var c = compById(C, sel.id);
        if (!c) { sel = null; return; }
        var lab = doc.createElement('label');
        lab.className = 'field';
        var sp = doc.createElement('span');
        sp.textContent = 'Beschriftung';
        var inp = doc.createElement('input');
        inp.className = 'inp mono';
        inp.value = c.label;
        inp.addEventListener('change', function () { c.label = String(inp.value).slice(0, 4); render(); autosave(); });
        lab.appendChild(sp); lab.appendChild(inp);
        els.sel.appendChild(lab);
        var del = doc.createElement('button');
        del.className = 'btn'; del.type = 'button'; del.textContent = 'Baustein löschen';
        del.addEventListener('click', function () { delComp(c.id); });
        els.sel.appendChild(del);
      } else {
        var w = null, i;
        for (i = 0; i < C.wires.length; i++) { if (C.wires[i].id === sel.id) { w = C.wires[i]; break; } }
        if (!w) { sel = null; return; }
        var p = doc.createElement('p');
        p.textContent = 'Draht von ' + w.from + ' nach ' + w.to + '.';
        els.sel.appendChild(p);
        var delW = doc.createElement('button');
        delW.className = 'btn'; delW.type = 'button'; delW.textContent = 'Draht löschen';
        delW.addEventListener('click', function () { delWire(w.id); });
        els.sel.appendChild(delW);
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
      els.canvas.addEventListener('pointerdown', function (ev) {
        var hit = hitTarget(ev);
        if (mode === 'place' && placeKind) {
          addComp(placeKind, Number(ev.clientX) || null, Number(ev.clientY) || null);
          hint('Baustein platziert: ' + KIND_LABEL[placeKind]);
          return;
        }
        if (mode === 'wire') {
          if (hit && hit.kind === 'comp') {
            if (!wireFrom) { wireFrom = hit.id; hint('Zielbaustein anklicken.'); }
            else if (wireFrom !== hit.id) { addWire(wireFrom, hit.id); wireFrom = null; hint('Draht gelegt.'); }
          } else if (hit && hit.kind === 'wire') { sel = { kind: 'wire', id: hit.id }; renderSel(); }
          return;
        }
        if (mode === 'delete') {
          if (hit) { if (hit.kind === 'comp') { delComp(hit.id); } else { delWire(hit.id); } }
          return;
        }
        if (hit && hit.kind === 'comp') {
          var c = compById(C, hit.id);
          if (c && c.kind === 'switch') {
            c.state = c.state ? 0 : 1;
            render();
            autosave();
            return;
          }
          sel = { kind: 'comp', id: hit.id };
          renderSel(); render();
        } else if (hit && hit.kind === 'wire') {
          sel = { kind: 'wire', id: hit.id };
          renderSel(); render();
        }
      });
    }

    var toolBtns = root.querySelectorAll('[data-lc-mode]');
    Array.prototype.forEach.call(toolBtns, function (btn) {
      btn.addEventListener('click', function () {
        var m = btn.getAttribute('data-lc-mode') || 'select';
        if (m === 'place') {
          mode = 'place';
          placeKind = btn.getAttribute('data-lc-kind') || 'switch';
          wireFrom = null;
          hint(KIND_LABEL[placeKind] + ' platzieren: auf die Fläche klicken.');
        } else {
          mode = m;
          placeKind = null;
          wireFrom = null;
          hint(m === 'wire' ? 'Erst den Quell-, dann den Zielbaustein anklicken.'
            : m === 'delete' ? 'Baustein oder Draht anklicken, um ihn zu entfernen.'
            : 'Schalter anklicken zum Umschalten.');
        }
        Array.prototype.forEach.call(toolBtns, function (b2) {
          b2.setAttribute('aria-pressed', b2 === btn ? 'true' : 'false');
        });
        render();
      });
    });

    /* Takt laufen lassen: jede halbe Sekunde ein Tick. */
    var clockBtn = doc.getElementById('lc-clock');
    if (clockBtn) {
      clockBtn.addEventListener('click', function () {
        if (tickTimer) {
          clearInterval(tickTimer);
          tickTimer = null;
          clockBtn.textContent = '▶ Takt';
          return;
        }
        clockBtn.textContent = '⏹ Takt stoppen';
        tickTimer = setInterval(function () {
          C = tickCircuit(C);
          render();
          autosave();
        }, 500);
      });
    }

    function evaluate() {
      var ev = evalCircuit(jsonCopy(C));
      if (els.out) {
        var txt = ev.stable ? 'Netz stabil nach ' + ev.iters + ' Durchgängen.' : '⚠ Netz oszilliert (Rückkopplung ohne Speicher).';
        var i;
        for (i = 0; i < ev.lamps.length; i++) {
          txt += '\nLampe ' + (ev.lamps[i].label || '?') + ': ' + (ev.lamps[i].on ? 'an 💡' : 'aus');
        }
        els.out.textContent = txt;
      }
      render();
      return ev;
    }

    var evalBtn = doc.getElementById('lc-eval');
    if (evalBtn) { evalBtn.addEventListener('click', evaluate); }

    if (els.form) {
      els.form.addEventListener('submit', function () {
        if (els.json) { els.json.value = JSON.stringify(serializeCircuit(C)); }
      });
    }

    render();
    renderSel();
    evaluate();

    return {
      circuit: function () { return C; },
      evaluate: evaluate,
      addComp: addComp,
      addWire: addWire,
      delComp: delComp,
      delWire: delWire,
      render: render,
      serialize: function () { return JSON.stringify(serializeCircuit(C)); },
      exportSvg: function () { return exportSvgString(C); },
      setMode: function (m) { mode = m; }
    };
  }

  var api = {
    emptyCircuit: emptyCircuit,
    exampleCircuit: exampleCircuit,
    KINDS: KINDS,
    KIND_LABEL: KIND_LABEL,
    normalizeCircuit: normalizeCircuit,
    parseCircuit: parseCircuit,
    serializeCircuit: serializeCircuit,
    evalCircuit: evalCircuit,
    tickCircuit: tickCircuit,
    runCircuit: runCircuit,
    analyseCircuit: analyseCircuit,
    sceneMarkup: sceneMarkup,
    exportSvgString: exportSvgString,
    attach: attach
  };
  if (typeof window !== 'undefined') { window.IC_LOGIC = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
  if (typeof document !== 'undefined' && typeof document.getElementById === 'function' && document.getElementById('lc-ide')) {
    attach(document);
  }
}());
