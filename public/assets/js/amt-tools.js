/**
 * InfoCraft - Werkzeugkasten fuer Automaten-Werkstaetten (#173)
 *
 * Funktionen, die Kellerautomat und Turingmaschine beide brauchen, gibt es
 * hier GENAU EINMAL und beide Werkstaetten setzen sie modular ein:
 *   - makeHistory: Undo/Redo (Historie, Standard 150 Schritte)
 *   - makeView:    Zoom, Einpassen und Schieben ueber das SVG-viewBox
 *   - exportPng:   PNG-Export des Automatenbildes
 *   - makePlayer:  Abspielen einer Schrittfolge mit Takt
 *   - deltaTable:  Uebergangstabelle der formalen Beschreibung
 *
 * Die Werkstatt des endlichen Automaten hat diese Funktionen bereits fest
 * eingebaut und bleibt unangetastet; neue Werkstaetten teilen sich diesen
 * Kasten, damit gleiche Funktionen nicht doppelt entstehen.
 *
 * DOM-frei gehalten, wo es geht: makeHistory, makePlayer und deltaTable
 * laufen auch in den Node-Tests ohne Browser.
 *
 * @version 0.18.0
 * @license MIT (InfoCraft)
 */
(function () {
  'use strict';

  /* ---------- Undo/Redo: Historie ueber Serialisierungen ---------- */

  function makeHistory(opts) {
    var max = (opts && opts.max) || 150;
    var snapshot = opts.snapshot;
    var restore = opts.restore;
    var undoStack = [];
    var redoStack = [];
    return {
      /* Nach JEDER abgeschlossenen Aenderung aufrufen: Der neue Stand
       * landet auf dem Undo-Stapel, der Redo-Stapel laeuft leer. */
      commit: function () {
        undoStack.push(snapshot());
        if (undoStack.length > max) { undoStack.shift(); }
        redoStack.length = 0;
      },
      undo: function () {
        if (undoStack.length < 2) { return null; }
        redoStack.push(undoStack.pop());
        var s = undoStack[undoStack.length - 1];
        restore(s);
        return s;
      },
      redo: function () {
        if (redoStack.length === 0) { return null; }
        var s = redoStack.pop();
        undoStack.push(s);
        restore(s);
        return s;
      },
      canUndo: function () { return undoStack.length > 1; },
      canRedo: function () { return redoStack.length > 0; },
      size: function () { return undoStack.length; }
    };
  }

  /* ---------- Ansicht: Zoom, Einpassen, Schieben (viewBox) ---------- */

  function makeView(svg, initial) {
    var v = {
      x: (initial && initial.x) || 0,
      y: (initial && initial.y) || 0,
      w: (initial && initial.w) || 900,
      h: (initial && initial.h) || 560
    };
    function apply() {
      if (svg && svg.setAttribute) {
        svg.setAttribute('viewBox', v.x + ' ' + v.y + ' ' + v.w + ' ' + v.h);
      }
    }
    function fit(b, pad) {
      if (!b) { return; }
      pad = pad || 40;
      var w = (b.x1 - b.x0) + 2 * pad;
      var h = (b.y1 - b.y0) + 2 * pad;
      v = { x: b.x0 - pad, y: b.y0 - pad, w: w < 100 ? 100 : w, h: h < 100 ? 100 : h };
      apply();
    }
    function zoom(f) {
      var cx = v.x + v.w / 2;
      var cy = v.y + v.h / 2;
      v.w = Math.max(80, v.w / f);
      v.h = Math.max(80, v.h / f);
      v.x = cx - v.w / 2;
      v.y = cy - v.h / 2;
      apply();
    }
    function panBy(dx, dy) {
      v.x += dx;
      v.y += dy;
      apply();
    }
    /* Zeigerposition in Zeichenflaechen-Koordinaten umrechnen. Ohne
     * getBoundingClientRect (Node-Tests) bleiben die Rohwerte stehen. */
    function toWorld(clientX, clientY) {
      var r = (svg && svg.getBoundingClientRect) ? svg.getBoundingClientRect() : null;
      if (!r || !r.width || !r.height) { return { x: clientX, y: clientY }; }
      return {
        x: v.x + ((clientX - r.left) / r.width) * v.w,
        y: v.y + ((clientY - r.top) / r.height) * v.h
      };
    }
    function cur() { return { x: v.x, y: v.y, w: v.w, h: v.h }; }
    apply();
    return { fit: fit, zoom: zoom, panBy: panBy, toWorld: toWorld, cur: cur, apply: apply };
  }

  /* ---------- PNG-Export: SVG inline rasterisieren ---------- */

  function downloadBlob(doc, blob, name) {
    var a = doc.createElement('a');
    a.href = (typeof URL !== 'undefined' && URL.createObjectURL) ? URL.createObjectURL(blob) : '';
    a.download = name;
    if (a.click) { a.click(); } else if (doc.body && doc.body.appendChild) {
      doc.body.appendChild(a);
      a.click();
    }
  }

  function exportPng(doc, svgInfo, name) {
    /* svgInfo: { str: '<svg ...>', w: 600, h: 400 } aus exportSvgString.
     * Ohne Blob, URL oder Image (Node-Tests, sehr alte Browser) kehrt der
     * Export still mit false zurueck, die Werkstatt laeuft weiter. */
    if (!doc || !doc.createElement || typeof Blob === 'undefined' ||
        typeof URL === 'undefined' || !URL.createObjectURL ||
        typeof Image === 'undefined') { return false; }
    try {
      var blob = new Blob([svgInfo.str], { type: 'image/svg+xml;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        try {
          var cv = doc.createElement('canvas');
          cv.width = img.width || svgInfo.w;
          cv.height = img.height || svgInfo.h;
          var ctx = cv.getContext ? cv.getContext('2d') : null;
          if (!ctx) { return; }
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, cv.width, cv.height);
          ctx.drawImage(img, 0, 0);
          if (cv.toBlob) {
            cv.toBlob(function (png) { if (png) { downloadBlob(doc, png, name); } });
          }
        } catch (ePng) { /* Rastern gescheitert, SVG bleibt erspart */ }
      };
      img.src = url;
      return true;
    } catch (eExp) {
      return false;
    }
  }

  /* ---------- Abspielen: Schrittfolge mit festem Takt ---------- */

  function makePlayer(opts) {
    var steps = (opts && opts.steps) || [];
    var show = opts && opts.show;
    var done = opts && opts.done;
    var tick = (opts && opts.interval) || 700;
    var timer = null;
    function stop() {
      if (timer !== null && typeof clearInterval === 'function') {
        clearInterval(timer);
      }
      timer = null;
    }
    function play() {
      stop();
      if (steps.length === 0) {
        if (done) { done(); }
        return;
      }
      if (show) { show(0, steps[0]); }
      var idx = 0;
      timer = -1; /* besetzt, solange der Takt laeuft */
      var advance = function () {
        idx += 1;
        if (idx >= steps.length) {
          stop();
          if (done) { done(); }
          return;
        }
        if (show) { show(idx, steps[idx]); }
      };
      if (typeof setInterval === 'function') {
        timer = setInterval(advance, tick);
      } else {
        advance(); /* ohne Taktgeber: nur der erste Schritt */
      }
    }
    return {
      play: play,
      stop: stop,
      playing: function () { return timer !== null; },
      steps: function () { return steps; }
    };
  }

  /* ---------- Formale Beschreibung: Uebergangstabelle ---------- */

  /* deltaTable(doc, head, rows): Tabelle der Uebergangs-/Delta-Funktion.
   * head: Spaltentitel, rows: Array von Zellwert-Arrays (alles Strings). */
  function deltaTable(doc, head, rows) {
    var t = doc.createElement('table');
    t.className = 'delta'; /* nutzt das Tabellen-Styling der Automaten-Seiten */
    var thead = doc.createElement('thead');
    var htr = doc.createElement('tr');
    for (var i = 0; i < head.length; i++) {
      var th = doc.createElement('th');
      th.textContent = head[i];
      htr.appendChild(th);
    }
    thead.appendChild(htr);
    t.appendChild(thead);
    var tbody = doc.createElement('tbody');
    for (var r = 0; r < rows.length; r++) {
      var tr = doc.createElement('tr');
      for (var c = 0; c < rows[r].length; c++) {
        var td = doc.createElement('td');
        td.textContent = rows[r][c];
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
    t.appendChild(tbody);
    return t;
  }

  var api = {
    makeHistory: makeHistory,
    makeView: makeView,
    exportPng: exportPng,
    makePlayer: makePlayer,
    deltaTable: deltaTable
  };
  if (typeof window !== 'undefined') { window.IC_AMT_TOOLS = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
})();
