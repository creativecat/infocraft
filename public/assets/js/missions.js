/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Modulare Missionen/Checklisten für alle Werkstätten (#265)
 *
 * Eine Komponente, überall gleich: eine ausklappbare, über der Seite
 * schwebende Box (Runde 39). Jede Werkstatt definiert ihre Missionen
 * und den Zustand (flags), diese Datei rendert die Liste einheitlich,
 * hakt automatisch ab und zählt den Fortschritt im Kopf der Box.
 *
 * Nutzung: network.js delegiert an IC_MISSIONS.render(), die Code-Werkstatt
 * legt eine Box mit data-missions-Flags ins Template, init() füllt sie beim
 * Laden selbst. Node-Tests: module.exports; Browser: window.IC_MISSIONS.
 *
 * @version 0.39.0
 * @license CC BY-NC-SA 4.0 (InfoCraft)
 */
(function (global) {
  'use strict';

  var GRADS = { 1: 'leicht', 2: 'mittel', 3: 'anspruchsvoll' };

  /* Missionen der Code-Werkstatt: automatisch abgehakt, sobald die
   * Flags aus dem Projektbestand (CodeController::index) vorliegen. */
  var CODE_MISSIONS = [
    { id: 'c1', grad: 1, title: 'Eigenes Projekt', text: 'Lege dein erstes Projekt an.', tip: 'Alles, was du speicherst, bleibt automatisch in deinem Konto.', flag: 'has-project' },
    { id: 'c2', grad: 1, title: 'Dreier-Pack', text: 'Lege drei Projekte an, am besten in verschiedenen Ordnern.', tip: 'Ordner halten die Werkstatt aufgeräumt.', flag: 'three-projects' },
    { id: 'c3', grad: 2, title: 'Java', text: 'Speichere ein eigenes Java-Projekt.', tip: 'Java ist die Sprache des Gymnasiums: Klassen, Objekte, Methoden.', flag: 'has-java' },
    { id: 'c4', grad: 2, title: 'Python', text: 'Speichere ein eigenes Python-Projekt.', tip: 'Python kommt mit wenig Tipparbeit ans Ziel.', flag: 'has-python' },
    { id: 'c5', grad: 3, title: 'Blöcke', text: 'Baue ein Programm in der Block-Werkstatt.', tip: 'Blöcke zeigen die Struktur, der Generator übersetzt sie nach Java und Python.', flag: 'has-blocks' },
    { id: 'c6', grad: 3, title: 'Automat oder Assembler', text: 'Probiere die Projektarten Automaten oder Assembler aus.', tip: 'Automaten prüfen Wörter, Assembler arbeitet direkt auf der CPU.', check: function (flags) { return flags.indexOf('has-amt') >= 0 || flags.indexOf('has-asm') >= 0; } }
  ];

  function esc(s) {
    return String(s).replace(/[&<>"]/g, '');
  }

  /** Zustand aus Missionsdefinitionen und erledigt-Flags (DOM-frei testbar). */
  function stateFor(defs, flags) {
    var out = [];
    for (var i = 0; i < defs.length; i++) {
      var m = defs[i];
      var done = m.flag ? flags.indexOf(m.flag) >= 0 : (m.check ? !!m.check(flags) : false);
      out.push({ id: m.id, title: m.title, text: m.text, grad: m.grad || 2, gradLabel: GRADS[m.grad || 2] || 'mittel', tip: m.tip || '', done: done });
    }
    out.sort(function (a, b) { return a.grad - b.grad; });
    return out;
  }

  /**
   * Liste in ein UL-Element rendern, immer gleiches Aussehen:
   * Grad-Badge, Titel, Aufgabe, Fachwort-Tipp, Häkchen bei erledigt.
   */
  function render(doc, listEl, state) {
    if (!listEl || !doc.createElement) { return 0; }
    while (listEl.firstChild) { listEl.removeChild(listEl.firstChild); }
    var done = 0;
    for (var i = 0; i < state.length; i++) {
      var st = state[i];
      var li = doc.createElement('li');
      li.setAttribute('class', st.done ? 'ic-mission ic-mission-done' : 'ic-mission');
      var badge = doc.createElement('span');
      badge.setAttribute('class', 'ic-mission-badge ic-mission-grad-' + (st.grad || 2));
      badge.textContent = st.gradLabel;
      li.appendChild(badge);
      var s = doc.createElement('span');
      s.innerHTML = (st.done ? '<span class="icn ic-checkmark" aria-hidden="true"></span> ' : '<span class="icn ic-checkbox-unchecked" aria-hidden="true"></span> ')
        + esc(st.title) + ': ' + esc(st.text);
      li.appendChild(s);
      if (st.tip) {
        var tip = doc.createElement('div');
        tip.setAttribute('class', 'ic-mission-tip');
        tip.textContent = 'Fachwort: ' + st.tip;
        li.appendChild(tip);
      }
      listEl.appendChild(li);
      if (st.done) { done++; }
    }
    return done;
  }

  /** Fortschritt („3/6“) in die Kopfzeile der Box schreiben, falls vorhanden. */
  function setCount(doc, boxId, done, total) {
    var box = doc.getElementById ? doc.getElementById(boxId) : null;
    var head = box ? boxGetCount(doc, box) : null;
    if (head) { head.textContent = done + '/' + total; }
  }

  function boxGetCount(doc, box) {
    for (var i = 0; i < box.children.length; i++) {
      var c = box.children[i];
      if (String(c.getAttribute && c.getAttribute('class') || '').indexOf('ic-missions-head') >= 0) {
        for (var j = 0; j < c.children.length; j++) {
          var cc = c.children[j];
          if (String(cc.getAttribute && cc.getAttribute('class') || '').indexOf('ic-missions-count') >= 0) { return cc; }
        }
      }
    }
    return null;
  }

  /** Code-Werkstatt: Box mit data-missions beim Laden selbst füllen. */
  function init(doc) {
    var boxes = doc.querySelectorAll ? doc.querySelectorAll('.ic-missions-box[data-missions]') : [];
    for (var i = 0; i < boxes.length; i++) {
      var box = boxes[i];
      var flags = [];
      try { flags = JSON.parse(box.getAttribute('data-missions') || '[]') || []; } catch (e) { flags = []; }
      var list = null;
      for (var j = 0; j < box.children.length; j++) {
        if (box.children[j].tagName === 'UL') { list = box.children[j]; }
      }
      if (!list) { continue; }
      var state = stateFor(CODE_MISSIONS, flags);
      var done = render(doc, list, state);
      var head = boxGetCount(doc, box);
      if (head) { head.textContent = done + '/' + state.length; }
    }
  }

  var api = {
    version: '0.39.0',
    GRADS: GRADS,
    CODE_MISSIONS: CODE_MISSIONS,
    stateFor: stateFor,
    render: render,
    setCount: setCount,
    init: init
  };
  global.IC_MISSIONS = api;
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }

  /* Selbststart: Templates binden nur diese Datei, die Box füllt sich selbst. */
  if (typeof window !== 'undefined' && window.document && window.document.querySelectorAll) {
    init(window.document);
  }
})(typeof window !== 'undefined' ? window : global);
