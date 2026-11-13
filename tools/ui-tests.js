/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: UI-Tests mit Fake-DOM
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.22.0
 */
/* InfoCraft UI-Tests: Sind alle Buttons der Java-IDE klickbar und funktionieren sie?
 *
 * java-vm.js startet beim Laden wireUi(), sobald ein document existiert. Diese
 * Tests bauen ein kleines Fake-DOM (so wie die Projektseite es rendert) und
 * klicken alle Buttons durch: Datei-Tabs, Konsolen-Tabs (inkl. Klassendiagramm),
 * Ausfuehren, Strg+Enter, Objektkonsole. Laeuft komplett offline:
 *   node tools/ui-tests.js
 */

'use strict';

/* ---------- Mini-Fake-DOM ---------- */
function matchesSegments(el, sel) {
  var segs = sel.match(/#[\w-]+|\.[\w-]+|\[[^\]]+\]|[^#\.\[]+|^$/g) || [];
  var i;
  for (i = 0; i < segs.length; i++) {
    var s = segs[i];
    if (s.charAt(0) === '#') { if (el.id !== s.slice(1)) { return false; } continue; }
    if (s.charAt(0) === '.') { if (!el.classList.contains(s.slice(1))) { return false; } continue; }
    var mAttr = /^\[([A-Za-z-]+)(?:="([^"]*)"|=(.*))?\]$/.exec(s);
    if (mAttr) {
      var val = mAttr[2] !== undefined ? mAttr[2] : (mAttr[3] !== undefined ? mAttr[3] : null);
      var actual = el.getAttribute(mAttr[1]);
      if (val === null) { if (!el.hasAttribute(mAttr[1])) { return false; } }
      else if (actual !== val) { return false; }
      continue;
    }
    if (el.tagName !== s.toUpperCase()) { return false; }
  }
  return true;
}

function matchesChain(el, parts) {
  if (!matchesSegments(el, parts[parts.length - 1])) { return false; }
  var idx = parts.length - 2;
  var node = el.parent;
  while (idx >= 0 && node) {
    if (matchesSegments(node, parts[idx])) { idx--; }
    node = node.parent;
  }
  return idx < 0;
}

function FakeEl(tag) {
  this.tagName = String(tag).toUpperCase();
  this.id = '';
  this.className = '';
  this.children = [];
  this.attributes = {};
  this.handlers = {};
  this.value = '';
  this.style = {};
  this.parent = null;
  this.selectionStart = 0;
  this.selectionEnd = 0;
  var self = this;
  this.classList = {
    _set: new Set(),
    add: function (c) { self.classList._set.add(c); },
    remove: function (c) { self.classList._set.delete(c); },
    contains: function (c) { return self.classList._set.has(c); },
    toggle: function (c, on) {
      if (on === undefined) { on = !self.classList._set.has(c); }
      if (on) { self.classList._set.add(c); } else { self.classList._set.delete(c); }
    }
  };
  Object.defineProperty(this, 'className', {
    get: function () { return Array.from(self.classList._set).join(' '); },
    set: function (v) {
      self.classList._set = new Set(String(v).trim().split(/\s+/).filter(Boolean));
    }
  });
}
FakeEl.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attributes, n); };
Object.defineProperty(FakeEl.prototype, 'parentNode', {
  get: function () { return this.parent; },
  configurable: true
});
Object.defineProperty(FakeEl.prototype, 'innerHTML', {
  get: function () { return this._html === undefined ? '' : this._html; },
  set: function (v) {
    this._html = String(v);
    this.children = [];
  },
  configurable: true
});
/* Wie im echten DOM (#64): Setzen von textContent ersetzt die Kinder.
 * Der getter liefert weiterhin den zuletzt gesetzten Text als String. */
Object.defineProperty(FakeEl.prototype, 'textContent', {
  get: function () { return this._text === undefined ? '' : this._text; },
  set: function (v) {
    this._text = String(v);
    this.children = [];
  },
  configurable: true
});
FakeEl.prototype.getAttribute = function (n) {
  var v = this.attributes[n];
  return v === undefined ? null : v;
};
FakeEl.prototype.setAttribute = function (n, v) { this.attributes[n] = String(v); };
FakeEl.prototype.removeAttribute = function (n) { delete this.attributes[n]; };
FakeEl.prototype.addEventListener = function (type, fn) {
  if (!this.handlers[type]) { this.handlers[type] = []; }
  this.handlers[type].push(fn);
};
FakeEl.prototype.removeEventListener = function (type, fn) {
  var l = this.handlers && this.handlers[type];
  if (!l) { return; }
  var i = l.indexOf(fn);
  if (i !== -1) { l.splice(i, 1); }
};
FakeEl.prototype.focus = function () { this._focused = true; };
FakeEl.prototype.appendChild = function (child) {
  child.parent = this;
  this.children.push(child);
  return child;
};
FakeEl.prototype.removeChild = function (child) {
  var i = this.children.indexOf(child);
  if (i !== -1) { this.children.splice(i, 1); }
};
Object.defineProperty(FakeEl.prototype, 'firstChild', {
  get: function () { return this.children.length > 0 ? this.children[0] : null; },
  configurable: true
});
/* Der HTML-Renderer (#174) baut sein iframe ueber box.ownerDocument. */
FakeEl.prototype.ownerDocument = {
  createElement: function (tag) { return new FakeEl(tag); },
  createTextNode: function (text) { return FakeDoc.prototype.createTextNode(text); }
};
FakeEl.prototype.insertBefore = function (el, ref) {
  if (ref) {
    var i = this.children.indexOf(ref);
    if (i !== -1) {
      if (el.parent && el.parent.removeChild) { el.parent.removeChild(el); }
      this.children.splice(i, 0, el);
      el.parent = this;
      return el;
    }
  }
  return this.appendChild(el);
};
FakeEl.prototype.querySelectorAll = function (sel) {
  var parts = sel.split(' ').filter(Boolean);
  var out = [];
  function walk(node) {
    for (var i = 0; i < node.children.length; i++) {
      var c = node.children[i];
      if (matchesChain(c, parts)) { out.push(c); }
      walk(c);
    }
  }
  walk(this);
  return out;
};
FakeEl.prototype.querySelector = function (sel) {
  var all = this.querySelectorAll(sel);
  return all.length > 0 ? all[0] : null;
};
FakeEl.prototype.textOf = function () {
  var out = String(this.textContent || '');
  for (var i = 0; i < this.children.length; i++) { out += this.children[i].textOf(); }
  return out;
};
FakeEl.prototype.dispatch = function (type, opts) {
  var ev = {
    type: type,
    currentTarget: this,
    target: this,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    key: '',
    preventDefault: function () {},
  };
  if (opts) { for (var k in opts) { ev[k] = opts[k]; } }
  /* Bubbling wie im Browser: erst die Listener am Ziel, dann der Weg
   * nach oben ueber alle Eltern bis zum Dokument (#60/#62): Der echte
   * Browser greift den Button-Klick per Delegation am document ab, der
   * Test-Harness bildet das jetzt genauso ab. */
  var stopped = false;
  ev.stopPropagation = function () { stopped = true; };
  /* Snapshot der Listener-Liste wie im echten DOM (#153). */
  var list = (this.handlers[type] || []).slice();
  var i;
  for (i = 0; i < list.length; i++) { list[i](ev); }
  var node = this.parent;
  var sawBody = (global.document && global.document.body === this);
  while (!stopped && node) {
    if (global.document && global.document.body === node) { sawBody = true; }
    var l2 = ((node.handlers && node.handlers[type]) || []).slice();
    ev.currentTarget = node;
    for (var j = 0; j < l2.length; j++) { l2[j](ev); }
    node = node.parent;
  }
  if (!stopped && sawBody && global.document && typeof global.document._fire === 'function') {
    ev.currentTarget = global.document;
    global.document._fire(type, ev);
  }
};
FakeEl.prototype.setSelectionRange = function (s, e) { this.selectionStart = s; this.selectionEnd = e; };
FakeEl.prototype.getBoundingClientRect = function () { return { left: 8, top: 8, width: 420, height: 20 }; };

/* Canvas-Mock: gfxRedraw zeichnet ins 2d-Objekt, hier nur Aufzeichnung */
function fakeCtx() {
  return {
    __calls: [],
    fillStyle: '', strokeStyle: '', lineWidth: 1, font: '', textAlign: '', textBaseline: '',
    globalAlpha: 1,
    save: function () { this.__calls.push('save'); },
    restore: function () { this.__calls.push('restore'); },
    beginPath: function () { this.__calls.push('beginPath'); },
    closePath: function () { this.__calls.push('closePath'); },
    moveTo: function (x, y) { this.__calls.push('moveTo:' + Math.round(x) + ',' + Math.round(y)); },
    lineTo: function (x, y) { this.__calls.push('lineTo:' + Math.round(x) + ',' + Math.round(y)); },
    arc: function (x, y, r) { this.__calls.push('arc:' + Math.round(x) + ',' + Math.round(y) + ',' + Math.round(r)); },
    ellipse: function (x, y, rx, ry) { this.__calls.push('ellipse:' + Math.round(rx)); },
    rect: function (x, y, w, h) { this.__calls.push('rect:' + Math.round(w) + 'x' + Math.round(h)); },
    quadraticCurveTo: function () { this.__calls.push('quad'); },
    fill: function () { this.__calls.push('fill'); },
    stroke: function () { this.__calls.push('stroke'); },
    fillText: function (t) { this.__calls.push('fillText:' + t); },
    fillRect: function (x, y, w, h) { this.__calls.push('fillRect'); },
    clearRect: function () { this.__calls.push('clearRect'); },
    translate: function (x, y) { this.__calls.push('translate:' + Math.round(x) + ',' + Math.round(y)); },
    rotate: function (a) { this.__calls.push('rotate:' + Math.round(a * 100) / 100); },
    scale: function (x, y) { this.__calls.push('scale:' + x + ',' + y); }
  };
}

function FakeDoc() {
  this.body = new FakeEl('body');
  this._byId = {};
}
FakeDoc.prototype.createElement = function (tag) {
  var el = new FakeEl(tag);
  if (String(tag).toLowerCase() === 'canvas') {
    el.width = 0;
    el.height = 0;
    el.__ctx = fakeCtx();
    el.getContext = function () { return el.__ctx; };
    /* Freie-Flaeche-Editor (#63): Freihand wird als PNG gespeichert */
    el.toDataURL = function () { return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='; };
  }
  return el;
};
FakeDoc.prototype.createTextNode = function (text) {
  var el = new FakeEl('#text');
  el.textContent = String(text);
  return el;
};
FakeDoc.prototype.getElementById = function (id) { return this._byId[id] || this.body.querySelector('#' + id) || null; };
FakeDoc.prototype.querySelectorAll = function (sel) { return this.body.querySelectorAll(sel); };
FakeDoc.prototype.querySelector = function (sel) {
  var all = this.body.querySelectorAll(sel);
  return all.length > 0 ? all[0] : null;
};
FakeDoc.prototype.register = function (el, id) { el.id = id; this._byId[id] = el; this.body.appendChild(el); };
/* document-Listener (Delegation) wie im Browser (#60): Klick-Delegation von
 * java-vm.js haengt am document und greift den Button auch dann ab, wenn der
 * Klick auf ein inneres Element geht. */
FakeDoc.prototype.addEventListener = function (type, fn) {
  if (!this._handlers) { this._handlers = {}; }
  (this._handlers[type] = this._handlers[type] || []).push(fn);
};
FakeDoc.prototype.removeEventListener = function (type, fn) {
  var l = this._handlers && this._handlers[type];
  if (!l) { return; }
  var i = l.indexOf(fn);
  if (i !== -1) { l.splice(i, 1); }
};
FakeDoc.prototype._fire = function (type, ev) {
  var l = this._handlers && this._handlers[type];
  if (!l) { return; }
  /* DOM-Spezifikation: die Liste der Listener wird vor dem Feuern
   * kopiert; ein Handler, der einen anderen abmeldet, darf den noch
   * ausstehenden Dispatch nicht zum Verschwinden bringen (#153). */
  var copy = l.slice();
  for (var i = 0; i < copy.length; i++) { copy[i](ev); }
};

/* ---------- IDE-Seite nachbauen (wie templates/code_project.php) ---------- */
var AUTO_CODE = 'public class Auto {\n' +
  '    private String marke;\n' +
  '    private int km;\n' +
  '    public Auto(String marke) { this.marke = marke; }\n' +
  '    public void fahren(int km) { this.km = this.km + km; }\n' +
  '    public String vorstellen() { return this.marke + " ist " + this.km + " km gefahren."; }\n' +
  '}\n' +
  'Auto a = new Auto("BMW");\n' +
  'System.out.println(a.vorstellen());\n';

var GARAGE_CODE = 'public class Garage {\n' +
  '    private String ort;\n' +
  '    public void abstellen(String ort) { this.ort = ort; }\n' +
  '}\n';

var doc = new FakeDoc();

function pane(fileId, name, code) {
  var p = new FakeEl('div');
  p.className = 'file-pane';
  p.setAttribute('data-file-pane', String(fileId));
  p.setAttribute('data-file-name', name);
  var hl = new FakeEl('div');
  hl.className = 'hlwrap ide-hlwrap';
  hl.setAttribute('data-hl', 'java');
  var ta = new FakeEl('textarea');
  ta.value = code;
  hl.appendChild(ta);
  p.appendChild(hl);
  doc.body.appendChild(p);
  return p;
}
function fileRow(fileId, name, active) {
  var row = new FakeEl('div');
  row.className = 'ide-file' + (active ? ' active' : '');
  row.setAttribute('data-file-row', String(fileId));
  var btn = new FakeEl('button');
  btn.setAttribute('data-file-tab', String(fileId));
  btn.textContent = name;
  if (active) { btn.classList.add('active'); }
  row.appendChild(btn);
  doc.body.appendChild(row);
  return { row: row, btn: btn };
}
function consoleTab(key, label, active) {
  var b = new FakeEl('button');
  b.setAttribute('data-ctab', key);
  b.textContent = label;
  if (active) { b.classList.add('active'); }
  doc.body.appendChild(b);
  return b;
}
function consolePane(key, active) {
  var p = new FakeEl('div');
  p.setAttribute('data-cpane', key);
  if (!active) { p.setAttribute('hidden', 'hidden'); }
  doc.body.appendChild(p);
  return p;
}

var f1 = fileRow(11, 'Auto.java', true);
var f2 = fileRow(12, 'Garage.java', false);
var pane1 = pane(11, 'Auto.java', AUTO_CODE);
var pane2 = pane(12, 'Garage.java', GARAGE_CODE);
pane2.setAttribute('hidden', 'hidden');

var tabOut = consoleTab('out', 'Ausgabe', true);
var tabClasses = consoleTab('classes', 'Klassen', false);
var tabObjects = consoleTab('objects', 'Objekte', false);
var tabDb = consoleTab('db', 'Datenbank', false);
/* API-Hilfe als eigener Knopf (java-api-open) + versteckte Quelle, Stopp-Knopf,
 * Dock-Zeile unterhalb der Konsole (wie templates/code_project.php) */
var apiBtn = new FakeEl('button');
doc.register(apiBtn, 'java-api-open');
var apiSrc = new FakeEl('div');
doc.register(apiSrc, 'java-api-src');
apiSrc.innerHTML = '<p>Mini-Datenbank: <code>Database.execute(...)</code> und <code>Input.readString</code></p>';
var stopBtn = new FakeEl('button');
doc.register(stopBtn, 'java-stop');
var gfxOpenBtn = new FakeEl('button');
doc.register(gfxOpenBtn, 'java-gfx-open');
var gfxDock = new FakeEl('div');
doc.register(gfxDock, 'gfx-dock');
gfxDock.setAttribute('hidden', 'hidden');
doc.body.appendChild(gfxDock);
/* Hinweis unter dem Grafik-Fenster (wie templates/code_project.php):
 * eine Leiste mit Schliessen/Anzeigen-Knoepfen, das Fenster landet davor. */
var gfxDockBar = new FakeEl('div');
gfxDockBar.className = 'gfx-dock-bar';
gfxDock.appendChild(gfxDockBar);
var gfxHideBtn = new FakeEl('button');
doc.register(gfxHideBtn, 'java-gfx-hide');
var paneOut = consolePane('out', true);
var paneClasses = consolePane('classes', false);
var paneObjects = consolePane('objects', false);
var tabSprites = consoleTab('sprites', 'Sprites', false);
var paneSprites = consolePane('sprites', false);
var paneDb = consolePane('db', false);
/* Datenbank-Tab: Schema der vorhandenen Tabellen + Abfrage-Konsole */
var dbSchemaBox = new FakeEl('div');
doc.register(dbSchemaBox, 'java-dbschema');
var dbIn = new FakeEl('textarea');
doc.register(dbIn, 'java-db-in');
var dbOut = new FakeEl('pre');
doc.register(dbOut, 'java-db-out');
var dbRunBtn = new FakeEl('button');
doc.register(dbRunBtn, 'java-db-run');

var runBtn = new FakeEl('button');
doc.register(runBtn, 'run-java');
var stepBtn = new FakeEl('button');
doc.register(stepBtn, 'java-step');
var restBtn = new FakeEl('button');
doc.register(restBtn, 'java-run-rest');
var stepInfo = new FakeEl('div');
doc.register(stepInfo, 'java-step-info');
var fmtBtn = new FakeEl('button');
doc.register(fmtBtn, 'java-format');
var consoleEl = new FakeEl('pre');
doc.register(consoleEl, 'java-console');
var errBox = new FakeEl('div');
doc.register(errBox, 'java-errors');
var replLog = new FakeEl('div');
doc.register(replLog, 'java-repl-log');
var replIn = new FakeEl('textarea');
doc.register(replIn, 'java-repl-in');
var replGut = new FakeEl('div');
doc.register(replGut, 'java-repl-gutter');
var replRunBtn = new FakeEl('button');
doc.register(replRunBtn, 'java-repl-run');
doc.register(new FakeEl('p'), 'java-repl-hint');
var acBox = new FakeEl('div');
doc.register(acBox, 'java-repl-ac');
acBox.setAttribute('hidden', 'hidden');
var objBox = new FakeEl('div');
doc.register(objBox, 'java-objects');
var objBadge = new FakeEl('span');
doc.register(objBadge, 'java-obj-badge');
objBadge.textContent = '0';
var errBadge = new FakeEl('span');
doc.register(errBadge, 'java-error-badge');
errBadge.textContent = '0';
var classBox = new FakeEl('div');
doc.register(classBox, 'java-classes');
classBox.className = 'umldiag';
var clearBtn = new FakeEl('button');
doc.register(clearBtn, 'java-clear');

/* ---------- Savebar und Autosave (wie templates/code_project.php) ---------- */
var saveForm = new FakeEl('form');
saveForm.className = 'ide-savebar';
saveForm.setAttribute('action', '/werkstatt/code/7');
var csrfInput = new FakeEl('input');
csrfInput.setAttribute('name', 'csrf');
csrfInput.value = 'token123';
saveForm.appendChild(csrfInput);
doc.register(saveForm, 'code-save-form');
var saveInfo = new FakeEl('span');
doc.register(saveInfo, 'java-save-info');
var newFileForm = new FakeEl('form');
newFileForm.className = 'ide-side-new';
newFileForm.setAttribute('action', '/werkstatt/code/7/datei');
newFileForm._submitted = 0;
newFileForm.submit = function () { newFileForm._submitted++; };
doc.body.appendChild(newFileForm);
var delFileForm = new FakeEl('form');
delFileForm.className = 'ide-file-del';
delFileForm.setAttribute('action', '/werkstatt/code/7/datei/12/loeschen');
doc.body.appendChild(delFileForm);
var fetchCalls = [];
global.fetch = function (url, opts) {
  fetchCalls.push({ url: url, opts: opts });
  return Promise.resolve({ ok: true });
};

/* ---------- Gespeicherte Beispieldatenbank (hidden field, wie code_project.php) ---------- */
var dbSeed = new FakeEl('input');
dbSeed.value = 'CREATE TABLE produkte (name TEXT, preis INT);' + String.fromCharCode(10) +
  "INSERT INTO produkte VALUES ('Heft', 2);" + String.fromCharCode(10) +
  "INSERT INTO produkte VALUES ('Radiergummi', 1);";
doc.register(dbSeed, 'java-db-seed');

/* ---------- java-vm.js laden: wireUi() laeuft sofort ---------- */
global.document = doc;
global.window = undefined;
var vm = require('../public/assets/js/java-vm.js');
/* Fenster-Attrappe erst NACH dem Laden: java-vm.js prueft beim Boot noch
 * window === undefined (keine globalen Listener im Fake-DOM), aber die
 * Autovervollstaendigung rechnet die Box-Position ueber getComputedStyle
 * aus (T38). Ohne Attrappe liefert edAcCaretPos null und die Box bliebe zu. */
global.window = {
  getComputedStyle: function () { return {}; },
  pageXOffset: 0,
  pageYOffset: 0,
  setTimeout: function () { return 0; }
};

/* ---------- Tests ---------- */
var failures = [];
var passed = 0;
function ok(cond, name) {
  if (cond) { console.log('ok   ' + name); passed++; }
  else { console.log('FAIL ' + name); failures.push(name); }
}
function hidden(el) { return el.hasAttribute('hidden'); }

/* T1: Datei im Seitenbaum anklicken aktualisiert das Editor-Fenster (der alte Bug). */
f2.btn.dispatch('click');
ok(!hidden(pane2), 'T1a Klick auf Garage.java: Editor-Fenster zeigt Garage');
ok(hidden(pane1), 'T1b Auto-Pane ist versteckt');
ok(f2.btn.classList.contains('active') && f2.row.classList.contains('active'), 'T1c Tab und Zeile markiert');
ok(!f1.btn.classList.contains('active') && !f1.row.classList.contains('active'), 'T1d alte Markierung entfernt');
ok(pane2.querySelector('textarea')._focused === true, 'T1e Cursor springt in den Editor');

f1.btn.dispatch('click');
ok(!hidden(pane1) && hidden(pane2), 'T1f zurueck zu Auto.java schaltet ebenfalls');

/* T37: DB-Konsole ohne Programmlauf (#60): Der gemeldete Ablauf - Tabelle ist
 * geladen (gespeicherter Seed), SELECT eintippen, Button klicken, ohne dass
 * das Programm je lief - muss Zeilen in die Ausgabe-Box schreiben. */
ok(dbSchemaBox.textOf().indexOf('produkte') !== -1, 'T37a Seed-Tabelle erscheint im Schema, bevor das Programm je lief');
dbIn.value = 'SELECT * FROM produkte';
dbOut.textContent = '';
dbRunBtn.dispatch('click');
ok(dbOut.textContent.indexOf('Heft') !== -1 && dbOut.textContent.indexOf('Radiergummi') !== -1, 'T37b SELECT ueber den Button zeigt die Zeilen (auch ohne vorheriges ▶)');
ok(dbOut.textContent.length > 0, 'T37c die Ausgabe-Box bleibt nach dem Klick nie leer');
dbIn.value = '';
dbOut.textContent = '';
dbRunBtn.dispatch('click');
ok(dbOut.textContent.length > 0, 'T37d leere Abfrage meldet sich sichtbar statt still');

/* T38: Autovervollstaendigung trotz kaputtem Zwischenstand (#59): Die Klasse
 * in der zweiten Datei muss auch dann vorgeschlagen werden, wenn der Quelltext
 * gerade syntaktisch ungueltig ist - die Symbole kommen per Muster aus dem
 * Quelltext, nicht nur aus dem Compiler. */
var NLt38 = String.fromCharCode(10);
var ta38 = pane1.querySelector('textarea');
var ta2 = pane2.querySelector('textarea');
var keep1 = ta38.value;
var keep2 = ta2.value;
ta38.value = 'class Auto {' + NLt38 + '  String marke' + NLt38 + '  // (mitten im Tippen: kein Semikolon, kein Ende';
ta38.setSelectionRange(ta38.value.length, ta38.value.length);
ta38.dispatch('input');
ta2.value = 'class Garage {' + NLt38 + '    String ort;' + NLt38 + '    void abstellen(String ortNeu) { ort = ortNeu; }' + NLt38 + '}' + NLt38;
ta2.setSelectionRange(ta2.value.length, ta2.value.length);
ta2.dispatch('input');
ta2.setSelectionRange(ta2.value.length, ta2.value.length);
ta2.dispatch('keydown', { key: ' ', ctrlKey: true });
(function () {
  var boxes = doc.body.querySelectorAll('.ac-box');
  var visibleBox = null;
  var bi;
  for (bi = 0; bi < boxes.length; bi++) {
    if (!boxes[bi].hasAttribute('hidden')) { visibleBox = boxes[bi]; }
  }
  ok(visibleBox !== null, 'T38a Vorschlagsbox oeffnet sich trotz kaputtem Quelltext');
  if (visibleBox) {
    var txt = visibleBox.textOf();
    ok(txt.indexOf('Garage') !== -1, 'T38b Klasse aus der anderen Projektdatei wird vorgeschlagen');
    ok(txt.indexOf('abstellen') !== -1, 'T38c Methode aus der anderen Projektdatei wird vorgeschlagen');
  }
})();
/* Projekt-Dateien fuer alle nachfolgenden Tests wiederherstellen */
ta2.dispatch('keydown', { key: 'Escape' });
ta38.value = keep1;
ta38.setSelectionRange(0, 0);
ta38.dispatch('input');
ta2.value = keep2;
ta2.setSelectionRange(0, 0);
ta2.dispatch('input');

/* T39: DB-Konsole ueber die echte Delegation (#60 Nacharbeit): Im Browser
 * greift java-vm.js den Button-Klick per document-Delegation ab; der direkte
 * Listener am Button und die Delegation duerfen die Abfrage nie doppelt
 * ausfuehren (__icDbRunDone-Guard). */
dbIn.value = "INSERT INTO produkte VALUES ('Stift', 3)";
dbOut.textContent = '';
dbRunBtn.dispatch('click');
ok(dbOut.textContent.indexOf('Befehl(e)') !== -1, 'T39a INSERT ueber den Button meldet sich sichtbar');
dbIn.value = 'SELECT * FROM produkte';
dbOut.textContent = '';
dbRunBtn.dispatch('click');
var t39treffer = (dbOut.textContent.match(/Stift/g) || []).length;
ok(t39treffer === 1, 'T39b Guard: Der Button-Klick fuehrt die Abfrage genau einmal aus (Zeile genau einmal da)');
/* Klick auf ein INNERES Element des Buttons: Der direkte Listener am Button
 * fehlt dafuer, die Delegation muss den Button auf dem Weg nach oben finden. */
var btnText39 = new FakeEl('span');
btnText39.textContent = 'Abfrage ausfuehren';
dbRunBtn.appendChild(btnText39);
dbIn.value = 'SELECT * FROM produkte';
dbOut.textContent = '';
btnText39.dispatch('click');
var t39treffer2 = (dbOut.textContent.match(/Stift/g) || []).length;
ok(dbOut.textContent.indexOf('Stift') !== -1, 'T39c Klick auf den Button-Text (inneres Element) startet die Abfrage');
ok(t39treffer2 === 1, 'T39d Delegation + Guard: Auch der indirekte Klick laeuft genau einmal');
dbRunBtn.removeChild(btnText39);
dbIn.value = '';
dbOut.textContent = '';

/* T2: Konsolen-Tabs schalten die Panes (Klassendiagramm-Tab inklusive). */
tabClasses.dispatch('click');
ok(!hidden(paneClasses), 'T2a Tab Klassen zeigt das Klassendiagramm-Fenster');
ok(hidden(paneOut), 'T2b Ausgabe-Fenster versteckt');
tabObjects.dispatch('click');
ok(!hidden(paneObjects) && hidden(paneClasses), 'T2c Objekte-Tab zeigt das Objektdiagramm als eigenen Tab');
tabDb.dispatch('click');
ok(!hidden(paneDb) && hidden(paneObjects), 'T2d Datenbank-Tab erreicht');
tabOut.dispatch('click');
ok(!hidden(paneOut) && hidden(paneDb), 'T2d2 zurueck zur Ausgabe');

/* T3: Klassendiagramm steht sofort (nur Parsen, ohne Ausfuehren). */
var html0 = classBox.innerHTML;
ok(html0.indexOf('uml-class') !== -1, 'T3a UML-Boxen gerendert');
ok(html0.indexOf('Auto') !== -1 && html0.indexOf('Garage') !== -1, 'T3b beide Klassen sichtbar');
ok(html0.indexOf('- marke : String') !== -1, 'T3c privates Attribut mit Minus');
ok(html0.indexOf('+ fahren(int km)') !== -1, 'T3d Methode mit Parametern');
ok(html0.indexOf('Auto(String marke)') !== -1, 'T3e Konstruktor sichtbar');

/* T4: Ausfuehren-Button arbeitet die sichtbare Datei ab. */
runBtn.dispatch('click');
ok(consoleEl.textContent.indexOf('BMW') !== -1, 'T4a Ausfuehren zeigt Programm-Ausgabe');

/* T5: Strg + Enter fuehrt ebenfalls aus. */
consoleEl.textContent = '';
pane1.querySelector('textarea').dispatch('keydown', { ctrlKey: true, key: 'Enter' });
ok(consoleEl.textContent.indexOf('BMW') !== -1, 'T5 Strg+Enter fuehrt aus');

/* T6: Objektkonsole: Anweisungsfeld mit Zeilennummern, Ausfuehren per Knopf. */
replIn.value = 'Auto b = new Auto("Audi");';
replIn.dispatch('input');
ok(replGut.textContent === '1\n', 'T6 Zeilennummern des Anweisungsfelds folgen der Eingabe');
replIn.value = 'Auto b = new Auto("Audi");\nb.vorstellen();';
replIn.dispatch('input');
ok(replGut.textContent === '1\n2\n', 'T6b mehrzeilige Eingabe zaehlt die Zeilennummern mit');
replRunBtn.dispatch('click');
ok(replLog.children.length > 0, 'T6c Ausfuehren-Knopf der Objektkonsole protokolliert die Eingabe');
var last = replLog.children[replLog.children.length - 1];
ok(last.textOf().indexOf('Audi') !== -1, 'T6d Methodenaufruf liefert Ergebnis');
/* Strg + Enter fuehrt ebenfalls aus, Eingabe bleibt stehen */
replIn.value = 'b.vorstellen();';
replIn.dispatch('keydown', { key: 'Enter', ctrlKey: true });
last = replLog.children[replLog.children.length - 1];
ok(last.textOf().indexOf('Audi') !== -1, 'T6e Strg+Enter im Anweisungsfeld fuehrt aus');
ok(replIn.value === 'b.vorstellen();', 'T6f die Anweisungen bleiben nach dem Ausfuehren stehen');

/* T82 (#106): ▶ Anweisungen ausführen startet das Programm selbst, wenn
 * noch kein Lauf passiert ist - vorher musste oben ▶ Ausführen geklickt
 * werden. Und (#107): erneutes Erzeugen ueberschreibt das Objekt, (#109)
 * Ausgaben erscheinen auch im Konsolenfenster, (#108) Tab rueckt ein. */
var pane1ta82 = pane1.querySelector('textarea');
var savedCode82 = pane1ta82.value;
pane1ta82.value = 'class Boom {'; /* kaputt: danach steht keine Session */
runBtn.dispatch('click');
pane1ta82.value = savedCode82; /* reparieren, NICHT neu ausfuehren */
replIn.value = 'Auto x = new Auto("VW");';
var nBefore82 = replLog.children.length;
replRunBtn.dispatch('click');
ok(replLog.children.length === nBefore82 + 1, 'T82 ▶ Anweisungen ausführen startet das Programm automatisch (ohne vorheriges ▶ Ausführen)');
var badge82 = objBadge.textContent;
replIn.value = 'Auto x = new Auto("VW2");';
replRunBtn.dispatch('click');
ok(objBadge.textContent === badge82, 'T82b erneutes Erzeugen mit gleichem Namen ueberschreibt das Objekt statt es zu stapeln');
replIn.value = 'System.out.println("REPLAUSGABE");';
replRunBtn.dispatch('click');
ok(consoleEl.textContent.indexOf('REPLAUSGABE') !== -1, 'T82c Ausgaben der Objektkonsole erscheinen auch im Konsolenfenster darueber');
ok(consoleEl.textContent.indexOf('Anweisungen') !== -1, 'T82d die Spiegelung ist im Konsolenfenster klar gekennzeichnet');
var savedIET82 = global.window.IC_EDIT_TOOLS;
global.window.IC_EDIT_TOOLS = { tabIndent: function (ta) { ta.__tabbed = true; } };
replIn.value = 'x';
replIn.dispatch('keydown', { key: 'Tab' });
ok(replIn.__tabbed === true, 'T82e Tab ohne Vorschlag rueckt im Anweisungsfeld ein statt den Fokus zu verlieren');
global.window.IC_EDIT_TOOLS = savedIET82;

/* T7: Live-Klassendiagramm beim Tippen (ohne Ausfuehren). */
var ta = pane1.querySelector('textarea');
ta.value = ta.value + '\npublic class Fahrrad { public void klingeln() { } }\n';
ta.dispatch('input');
setTimeout(function () {
  ok(classBox.innerHTML.indexOf('Fahrrad') !== -1, 'T7 neue Klasse erscheint im Diagramm, ohne Ausfuehren');

  /* T8: Objekte-Tab und Leeren-Button. */
  tabClasses.dispatch('click');
  ok(!hidden(paneClasses), 'T8a Klassen-Fenster mit Klassen- und Objektdiagramm oeffnet');
  clearBtn.dispatch('click');
  ok(consoleEl.textContent === '' && errBox.innerHTML === '', 'T8b Leeren rueumt Konsole und Fehler auf');

  /* T9: Fehlerfall: kaputter Code zerstoert das Diagramm nicht. */
  ta.value = 'public class ??? { syntax killer !!!';
  ta.dispatch('input');
  setTimeout(function () {
    ok(classBox.innerHTML.length > 0, 'T9 bei Syntaxfehlern bleibt eine Diagramm-Anschnitt stehen');

    /* T10: Zeilennummern (Gutter) neben dem Editor. */
    var NLt = String.fromCharCode(10);
    ta.value = 'class A {' + NLt + '  void x() {}' + NLt + '}' + NLt + 'A a = new A();';
    ta.dispatch('input');
    var hlwrap = pane1.querySelector('.hlwrap');
    ok(hlwrap.classList.contains('has-gutter'), 'T10a Editor bekommt den Gutter (Klasse has-gutter)');
    var gutter = hlwrap.querySelector('.ln-gutter');
    ok(gutter !== null, 'T10b Zeilennummern-Feld existiert');
    var cells = gutter.querySelectorAll('.ln-cell');
    ok(cells.length === 4, 'T10c vier Zeilennummern fuer vier Zeilen (hat ' + cells.length + ')');
    ok(cells[0].textContent === '1' && cells[3].textContent === '4', 'T10d Nummern 1 bis 4');

    /* T11: Tab-Taste rueckt ein (4 Spaces), Shift+Tab rueckt aus. */
    ta.value = 'System.out.println("hi");';
    ta.dispatch('input');
    ta.setSelectionRange(0, 0);
    ta.dispatch('keydown', { key: 'Tab' });
    ok(ta.value === '    System.out.println("hi");', 'T11a Tab fuegt 4 Spaces am Zeilenanfang ein');
    ta.setSelectionRange(0, 0);
    ta.dispatch('keydown', { key: 'Tab', shiftKey: true });
    ok(ta.value === 'System.out.println("hi");', 'T11b Shift+Tab rueckt wieder aus');

    /* T12: Enter rueckt automatisch ein (nach { eine Ebene tiefer). */
    ta.value = 'class A {';
    ta.dispatch('input');
    ta.setSelectionRange(9, 9);
    ta.dispatch('keydown', { key: 'Enter' });
    ok(ta.value === 'class A {' + NLt + '    ' && ta.selectionStart === 14, 'T12a Enter nach { rueckt 4 Spaces ein');
    ta.dispatch('keydown', { key: '}' });
    ok(ta.value === 'class A {' + NLt + '}', 'T12b schliessende Klammer rueckt automatisch aus');

    /* T13: Formatieren-Button rueckt chaotischen Code sauber ein. */
    ta.value = 'class B {' + NLt + 'int n;' + NLt + 'void go(){' + NLt + 'n = 1;' + NLt + '}' + NLt + '}';
    ta.dispatch('input');
    fmtBtn.dispatch('click');
    ok(ta.value.indexOf(NLt + '    int n;') !== -1, 'T13a Attribute werden eingerueckt');
    ok(ta.value.indexOf(NLt + '        n = 1;') !== -1, 'T13b Methodenrumpf eine Ebene tiefer');
    ok(ta.value.indexOf(NLt + '    }') !== -1, 'T13c schliessende Klammer richtig ausgerueckt');
    ok(stepInfo.textContent.indexOf('formatiert') !== -1, 'T13d Infozeile meldet das Formatieren');

    /* T14: Einzelschritt: ein Klick, eine Anweisung. */
    ta.value = 'System.out.println("eins");' + NLt + 'System.out.println("zwei");';
    ta.dispatch('input');
    runBtn.dispatch('click');
    ok(consoleEl.textContent.indexOf('zwei') !== -1, 'T14a normaler Lauf zeigt alles');
    stepBtn.dispatch('click');
    ok(consoleEl.textContent === 'eins' + NLt, 'T14b erster Schritt zeigt nur die erste Ausgabe');
    ok(stepInfo.textContent.indexOf('Schritt 1') !== -1 && stepInfo.textContent.indexOf('Zeile 2') !== -1, 'T14c Info nennt Schritt 1 und naechste Zeile 2');
    var gutter2 = pane1.querySelector('.ln-gutter');
    var curCells = gutter2.querySelectorAll('.ln-cur');
    ok(curCells.length === 1, 'T14d naechste Zeile ist in den Zeilennummern markiert');
    stepBtn.dispatch('click');
    ok(consoleEl.textContent === 'eins' + NLt + 'zwei' + NLt, 'T14e zweiter Schritt zeigt beide Ausgaben');
    ok(stepInfo.textContent.indexOf('durchgelaufen') !== -1, 'T14f Info meldet das Ende');

    /* T14g: Stopp-Knopf haelt an und bricht einen Einzelschritt ab: Der
     *      naechste Einzelschritt beginnt wieder bei Schritt 1. */
    stopBtn.dispatch('click');
    ok(stepInfo.textContent.indexOf('gestoppt') !== -1, 'T14g Stopp meldet das Anhalten');
    stepBtn.dispatch('click');
    ok(stepInfo.textContent.indexOf('Schritt 1') !== -1, 'T14h nach Stopp beginnt der Einzelschritt wieder bei Schritt 1');

    /* T15: Fehlerzeile wird in den Zeilennummern rot markiert. */
    ta.value = 'class C {' + NLt + 'void x() { y = ; }' + NLt + '}';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var errCells = pane1.querySelector('.ln-gutter').querySelectorAll('.ln-err');
    ok(errCells.length >= 1, 'T15 Fehlerzeile in den Zeilennummern markiert');

    /* T16: API-Hilfe oeffnet in einem eigenen Fenster. Ohne window.open (wie
     *     hier im Fake-DOM) erscheint sie als schwebendes Fenster in der Seite. */
    apiBtn.dispatch('click');
    var apiWin = doc.querySelector('.api-window');
    ok(apiWin !== null, 'T16a API-Hilfe-Fenster oeffnet');
    var apiBody = apiWin ? apiWin.querySelector('.api-window-body') : null;
    ok(apiBody !== null && apiBody.innerHTML.indexOf('Mini-Datenbank') !== -1, 'T16b API-Hilfe-Fenster enthaelt die Doku');
    if (apiWin) {
      var apiClose = apiWin.querySelector('.gfx-close');
      if (apiClose) {
        apiClose.dispatch('click');
        ok(apiWin.style.display === 'none', 'T16c Schliessen-Knopf versteckt das API-Hilfe-Fenster');
        apiBtn.dispatch('click');
        ok(apiWin.style.display === '', 'T16d Erneuter Klick holt die API-Hilfe zurueck');
      }
    }

    /* T17: Grafik-Fenster oeffnet sich bei World/Figuren. */
    ta.value = 'class Auto extends Circle {' + NLt +
      '  Auto() { super(50, 60, 10); setFillColor(Color.red); }' + NLt +
      '  void act() { move(3, 0); }' + NLt +
      '}' + NLt +
      'World w = new World(400, 300);' + NLt +
      'Auto b = new Auto();';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var win = doc.querySelector('#gfx-window');
    ok(win !== null, 'T17a Grafik-Fenster ist geoeffnet');
    ok(win.parent === gfxDock, 'T17a2 Grafik-Fenster liegt in der eigenen Zeile unterhalb der Konsole');
    ok(!hidden(gfxDock), 'T17a3 Die Grafik-Zeile unterhalb der Konsole ist sichtbar');
    var shapes = vm.gfxSnapshot();
    ok(shapes.length === 1 && shapes[0].kind === 'circle' && shapes[0].fill === 'rgb(255, 0, 0)', 'T17b Ball liegt als roter Kreis im Fenster');
    ok(shapes[0].r === 10, 'T17c Kreis-Radius stimmt');

    /* T18: act()-Animation bewegt den Ball. */
    var x0 = shapes[0].x;
    vm.gfxTick();
    vm.gfxTick();
    shapes = vm.gfxSnapshot();
    ok(Math.round(shapes[0].x - x0) === 6, 'T18 act() bewegt den Ball um 6 Pixel (2 Ticks)');

    /* T19: Tastatur-Ereignis onKeyDown. */
    ta.value = 'class Auto extends Rectangle {' + NLt +
      '  Auto() { super(10, 10, 20, 20); }' + NLt +
      '  void onKeyDown(String taste) { move(0, 4); }' + NLt +
      '}' + NLt +
      'Auto h = new Auto();';
    ta.dispatch('input');
    runBtn.dispatch('click');
    vm.gfxDispatchKey('ArrowDown', true);
    shapes = vm.gfxSnapshot();
    ok(shapes.length === 1 && Math.round(shapes[0].y) === 14, 'T19 onKeyDown bewegt den Held nach unten');

    /* T20: GUI-Button mit onChange. */
    ta.value = 'class Auto extends Button {' + NLt +
      '  Auto() { super(20, 20, 100, 30, "Klick"); }' + NLt +
      '  void onChange(String wert) { setText("geklickt"); }' + NLt +
      '}' + NLt +
      'Auto k = new Auto();';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var guiBtn = null;
    var win2 = doc.querySelector('#gfx-window');
    if (win2) {
      var guiLayer = win2.querySelector('.gfx-gui');
      var els = guiLayer.querySelectorAll('button');
      guiBtn = els.length > 0 ? els[0] : null;
    }
    ok(guiBtn !== null, 'T20a Knopf liegt als echtes Element im Grafik-Fenster');
    if (guiBtn) {
      guiBtn.dispatch('click');
      shapes = vm.gfxSnapshot();
      ok(shapes.length === 1 && shapes[0].text === 'geklickt', 'T20b Klick loest onChange aus (Text geaendert)');
    }

    /* T20c: Grafik-Fenster mit X schliessen und ueber den 🎨-Knopf wieder anzeigen. */
    var win2b = doc.querySelector('#gfx-window');
    var gfxClose = win2b ? win2b.querySelector('.gfx-close') : null;
    ok(gfxClose !== null, 'T20c Grafik-Fenster hat einen Schliessen-Knopf');
    if (gfxClose) {
      gfxClose.dispatch('click');
      ok(win2b.style.display === 'none', 'T20c2 X schliesst das Grafik-Fenster');
      ok(!hidden(gfxDock), 'T20c3 die Schliessen/Anzeigen-Leiste bleibt unter dem Fenster sichtbar');
      gfxOpenBtn.dispatch('click');
      ok(win2b.style.display === '', 'T20c4 Anzeigen-Knopf blendet das Grafik-Fenster wieder ein');
      gfxHideBtn.dispatch('click');
      ok(win2b.style.display === 'none', 'T20c5 Schliessen-Knopf in der Leiste schliesst das Fenster');
      gfxOpenBtn.dispatch('click');
      ok(win2b.style.display === '', 'T20c6 Anzeigen-Knopf in der Leiste oeffnet es erneut');
    }

    /* T20d: new World(b, h) passt das Grafik-Fenster in der Groesse des Codes an. */
    var win2c = doc.querySelector('#gfx-window');
    ok(win2c !== null, 'T20d Grafik-Fenster ist offen');
    if (win2c) {
      var cnv = win2c.querySelector('canvas');
      var hintIdx = gfxDock.children.indexOf(gfxDockBar);
      var winIdx = gfxDock.children.indexOf(win2c);
      ok(winIdx === 0 && hintIdx === 1, 'T20d2 Schliessen/Anzeigen-Leiste steht direkt unter dem Grafik-Fenster');
      ta.value = 'World w = new World(500, 400);';
      ta.dispatch('input');
      runBtn.dispatch('click');
      ok(win2c.style.width === '500px', 'T20d3 new World(500, 400) setzt die Fensterbreite auf 500px');
      ok(cnv.width === 500 && cnv.height === 400, 'T20d4 Canvas folgt der World-Groesse (500x400)');
      ta.value = 'World w = new World(2000, 1500);';
      ta.dispatch('input');
      runBtn.dispatch('click');
      ok(cnv.width === 960 && cnv.height === 720, 'T20d5 riesige World wird auf 960x720 begrenzt');
    }

    /* T20e: Zum Start ist das Grafik-Fenster immer geleert und versteckt. */
    ta.value = 'System.out.println("kein Grafik-Fenster");';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var win2e = doc.querySelector('#gfx-window');
    ok(win2e.style.display === 'none', 'T20e Start ohne Grafik versteckt das Fenster');
    ok(!hidden(gfxDock), 'T20e2 die Leiste am Grafik-Fenster bleibt fuer ein erneutes Anzeigen stehen');
    if (win2e) {
      var cnv2 = win2e.querySelector('canvas');
      ok(cnv2.width === 480 && cnv2.height === 360, 'T20e3 Canvas ist auf die Standardgroesse zurueckgesetzt');
      ok(cnv2.__ctx.__calls.indexOf('clearRect') !== -1, 'T20e4 die alte Zeichnung ist weggeleert');
      var guiLeft = win2e.querySelector('.gfx-gui');
      ok(guiLeft.children.length === 0, 'T20e5 alte Knoepfe und Eingabefelder sind entfernt');
    }
    /* und ein neues Grafik-Programm oeffnet das geleerte Fenster wieder */
    ta.value = 'class Auto extends Circle {' + NLt +
      '  Auto() { super(50, 60, 10); }' + NLt +
      '}' + NLt +
      'Auto b = new Auto();';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var win2f = doc.querySelector('#gfx-window');
    ok(win2f.style.display === '' && !hidden(gfxDock), 'T20e6 ein neues Grafik-Programm oeffnet das Fenster wieder');

    /* T21: Klammer-Automatik: ( schliesst sofort ), Cursor steht dazwischen. */
    var taE = pane1.querySelector('textarea');
    taE.value = 'System.out.println';
    taE.dispatch('input');
    taE.setSelectionRange(18, 18);
    taE.dispatch('keydown', { key: '(' });
    ok(taE.value === 'System.out.println()', 'T21a runde Klammer schliesst sich sofort');
    ok(taE.selectionStart === 19 && taE.selectionEnd === 19, 'T21b Cursor steht zwischen den Klammern');

    /* T22: { am Zeilenende oeffnet den Block: Cursor in neue Zeile mit Einzug,
     * schliessende Klammer steht gleich mit darunter. */
    taE.value = '    public void begruessen()';
    taE.dispatch('input');
    taE.setSelectionRange(28, 28);
    taE.dispatch('keydown', { key: '{' });
    var expBody = '    public void begruessen() {' + NLt + '        ' + NLt + '    }';
    ok(taE.value === expBody, 'T22a Block wird auf drei Zeilen geoeffnet (ist: ' + JSON.stringify(taE.value) + ')');
    ok(taE.selectionStart === 30 + NLt.length + 8, 'T22b Cursor steht in der neuen Zeile hinter dem Einzug');
    taE.value = 'int x = 1';
    taE.dispatch('input');
    taE.setSelectionRange(9, 9);
    taE.dispatch('keydown', { key: '[' });
    ok(taE.value === 'int x = 1[]', 'T22c eckige Klammer schliesst sich sofort');

    /* T23: Tippt man die schliessende Klammer, wird die vorhandene uebersprungen. */
    taE.value = 'System.out.println()';
    taE.dispatch('input');
    taE.setSelectionRange(19, 19);
    taE.dispatch('keydown', { key: ')' });
    ok(taE.value === 'System.out.println()', 'T23a schliessende Klammer wird nicht verdoppelt');
    ok(taE.selectionStart === 20, 'T23b Cursor springt hinter die vorhandene Klammer');

    /* T24/T25: Automatisches Speichern bei jeder Aenderung. */
    fetchCalls.length = 0;
    var before = fetchCalls.length;
    taE.value = 'class A {}';
    taE.dispatch('input');
    setTimeout(function () {
      ok(fetchCalls.length === before + 1, 'T24a nach Tippen wird automatisch gespeichert (ein fetch)');
      if (fetchCalls.length > 0) {
        ok(fetchCalls[fetchCalls.length - 1].url === '/werkstatt/code/7', 'T24b speichert an die Projekt-Adresse');
        var body = String(fetchCalls[fetchCalls.length - 1].opts.body);
        ok(body.indexOf('files%5B11%5D') !== -1 && body.indexOf('csrf=token123') !== -1, 'T24c Body enthaelt Dateien und CSRF-Token');
        ok(body.indexOf('title=') === -1, 'T24d kein Titel-Feld mehr im Speicher-Body (Titel steht im Kopf)');
      } else {
        ok(false, 'T24b speichert an die Projekt-Adresse');
        ok(false, 'T24c Body enthaelt Dateien und CSRF-Token');
        ok(false, 'T24d kein Titel-Feld mehr im Speicher-Body (Titel steht im Kopf)');
      }
      ok(saveInfo.textContent.indexOf('gespeichert') !== -1, 'T24e Status meldet das automatische Speichern');
      ok(saveInfo.textContent.indexOf('✓') !== -1, 'T24f Status zeigt dezent mit Haekchen und Uhrzeit');
      ok(saveInfo.className.indexOf('save-err') === -1, 'T24g Erfolg ohne Fehler-Optik');

      /* T25: Neue Datei anlegen speichert zuerst - nichts geht verloren. */
      newFileForm._submitted = 0;
      taE.value = taE.value + NLt + 'int nochEineZeile = 2;';
      taE.dispatch('input');
      var prevented = false;
      newFileForm.dispatch('submit', { preventDefault: function () { prevented = true; } });
      ok(prevented, 'T25a Absenden wird kurz aufgehalten, bis gespeichert ist');
      setTimeout(function () {
        ok(newFileForm._submitted === 1, 'T25b nach dem Speichern wird die neue Datei angelegt');
        ok(fetchCalls.length >= 2, 'T25c vorher wurde der aktuelle Code gesichert');

        /* T26: Vererbung im Klassendiagramm als Pfeil (Kind unter dem Elter). */
        taE.value = 'class Fahrzeug {' + NLt +
          '  protected int ps;' + NLt +
          '}' + NLt +
          'class Auto extends Fahrzeug {' + NLt +
          '  public void hupen() { System.out.println("tut"); }' + NLt +
          '}';
        taE.dispatch('input');
        setTimeout(function () {
          var umlHtml = classBox.innerHTML;
          ok(umlHtml.indexOf('uml-arrow') !== -1, 'T26a Vererbung wird als Pfeil gezeichnet');
          ok(umlHtml.indexOf('uml-children') !== -1, 'T26b Unterklasse haengt unter dem Elter');
          ok(umlHtml.indexOf('uml-parent') === -1, 'T26c alte Text-Notiz entfaellt bei Projekt-Klassen');

          /* T27: AltGr (Strg+Alt) bremst die Klammer-Automatik nicht aus. */
          taE.value = 'int x = 1';
          taE.dispatch('input');
          taE.setSelectionRange(9, 9);
          taE.dispatch('keydown', { key: '[', ctrlKey: true, altKey: true });
          ok(taE.value === 'int x = 1[]', 'T27a eckige Klammer schliesst sofort trotz AltGr');
          taE.value = '    public void test()';
          taE.dispatch('input');
          taE.setSelectionRange(22, 22);
          taE.dispatch('keydown', { key: '{', ctrlKey: true, altKey: true });
          ok(taE.value === '    public void test() {' + NLt + '        ' + NLt + '    }', 'T27b Block-Oeffnung funktioniert trotz AltGr');

          /* T27c/d: Firefox und macOS melden AltGr/Option nur als altKey. */
          taE.value = 'int y = 2';
          taE.dispatch('input');
          taE.setSelectionRange(9, 9);
          taE.dispatch('keydown', { key: '[', altKey: true });
          ok(taE.value === 'int y = 2[]', 'T27c eckige Klammer schliesst sich auch bei reinem Alt (Firefox/macOS)');
          taE.value = 'int z = 3';
          taE.dispatch('input');
          taE.setSelectionRange(9, 9);
          taE.dispatch('keydown', { key: '{', altKey: true });
          ok(taE.value === 'int z = 3 {' + NLt + '    ' + NLt + '}', 'T27d geschweifte Klammer oeffnet den Block auch bei reinem Alt');

          /* T27e/f: beforeinput-Faenger fuer Faelle, in denen keydown das
           * Zeichen nicht meldet (Treiber, Mobile, ungewoehnliche Layouts). */
          taE.value = 'int w = 4';
          taE.dispatch('input');
          taE.setSelectionRange(9, 9);
          taE.dispatch('beforeinput', { inputType: 'insertText', data: '[' });
          ok(taE.value === 'int w = 4[]', 'T27e beforeinput-Faenger schliesst eckige Klammer');
          taE.value = 'System.out.println()';
          taE.dispatch('input');
          taE.setSelectionRange(19, 19);
          taE.dispatch('beforeinput', { inputType: 'insertText', data: ')' });
          ok(taE.value === 'System.out.println()' && taE.selectionStart === 20, 'T27f beforeinput-Faenger ueberspringt schliessende Klammer');

          /* T28: Objekte aus dem Java-Programm erscheinen mit Namen im Objekte-Fenster. */
          taE.value = 'class Auto {' + NLt +
            '  public String marke;' + NLt +
            '  public Auto(String m) { marke = m; }' + NLt +
            '}' + NLt +
            'Auto a = new Auto("BMW");';
          taE.dispatch('input');
          runBtn.dispatch('click');
          var objsTxt = objBox.textOf();
          ok(objsTxt.indexOf('a : Auto') !== -1, 'T28a Objekt aus dem Programm steht mit Namen im Objekte-Fenster');
          ok(objsTxt.indexOf(': Auto') === -1 || objsTxt.indexOf('a : Auto') !== -1, 'T28b kein kaputter Doppelpunkt-Name mehr');

          /* T29: Meldungen der IDE nutzen echte Umlaute statt ae/oe/ue. */
          taE.value = 'class Auto {' + NLt +
            '  public String marke;' + NLt +
            '}' + NLt +
            'Auto a = new Auto();' + NLt +
            'a.tueren;';
          taE.dispatch('input');
          runBtn.dispatch('click');
          var errTxt = errBox.textOf();
          ok(errTxt.indexOf('gehört') !== -1, 'T29a Fehlermeldung schreibt gehört mit echtem ö');
          ok(errTxt.indexOf('gehoert') === -1, 'T29b keine Ersatz-Schreibung mehr in der Meldung');

          /* T30: Ein erfolgreicher Lauf ersetzt alte Fehler durch den Erfolg. */
          taE.value = 'class Auto {' + NLt +
            '  public String marke;' + NLt +
            '}' + NLt +
            'Auto a = new Auto();';
          taE.dispatch('input');
          runBtn.dispatch('click');
          var okHtml = errBox.innerHTML;
          ok(okHtml.indexOf('fehlerfrei') !== -1 && okHtml.indexOf('gehört') === -1, 'T30a erfolgreicher Lauf ersetzt die alten Fehler');
          ok(okHtml.indexOf('Alles fehlerfrei durchgelaufen') !== -1, 'T30b Erfolg der Ausfuehrung bleibt deutlich sichtbar');

          /* T31: Klick auf die Fehlerkarte springt zur Datei und Zeile. */
          var gta = pane2.querySelector('textarea');
          gta.value = 'class Garage {' + NLt +
            '  public int plaetze' + NLt +
            '}' + NLt +
            'Garage g = new Garage();';
          gta.dispatch('input');
          runBtn.dispatch('click');
          var gCard = errBox.children[0];
          ok(gCard && gCard.classList.contains('err-jump'), 'T31a Fehlerkarte aus einer anderen Datei ist klickbar');
          ok(hidden(pane2), 'T31b vorher ist Garage.java noch nicht offen');
          gCard.dispatch('click');
          ok(!hidden(pane2) && hidden(pane1), 'T31c Klick oeffnet Garage.java im Editor');
          var errLine = 0;
          var gCells = pane2.querySelector('.ln-gutter').querySelectorAll('.ln-cell');
          var gi;
          for (gi = 0; gi < gCells.length; gi++) {
            if (gCells[gi].className.indexOf('ln-err') !== -1) { errLine = gi + 1; break; }
          }
          ok(errLine > 0, 'T31d Fehlerzeile in Garage.java ist rot markiert');
          var wantOff = 0;
          var gLines = String(gta.value).split(NLt);
          var gl;
          for (gl = 0; gl < errLine - 1; gl++) { wantOff += gLines[gl].length + 1; }
          ok(gta.selectionStart === wantOff && gta.selectionEnd === wantOff, 'T31e Cursor steht am Anfang der Fehlerzeile');

          /* T32: Live-Fehlerkonsole - Fehler erscheinen beim Tippen, ohne Ausfuehren. */
          gta.value = 'class Garage {' + NLt +
            '  public int plaetze;' + NLt +
            '}';
          gta.dispatch('input');
          taE.value = 'class Auto {' + NLt +
            '  public String marke' + NLt +
            '}';
          taE.dispatch('input');
          setTimeout(function () {
            ok(errBox.textOf().indexOf('Syntaxfehler') !== -1, 'T32a Fehler erscheint beim Tippen ohne Ausfuehren');
            taE.value = 'class Auto {' + NLt +
              '  public String marke;' + NLt +
              '}';
            taE.dispatch('input');
            setTimeout(function () {
              ok(errBox.innerHTML.indexOf('Keine Fehler im Code gefunden') !== -1, 'T32b behobener Fehler verschwindet ohne neuen Lauf');
              var gCells2 = pane2.querySelector('.ln-gutter').querySelectorAll('.ln-cell');
              var noErr = true;
              var gi2;
              for (gi2 = 0; gi2 < gCells2.length; gi2++) {
                if (gCells2[gi2].className.indexOf('ln-err') !== -1) { noErr = false; }
              }
              ok(noErr, 'T32c rote Zeilenmarkierungen werden mit behobenem Fehler geloest');

              /* T33: Einzelschritt springt automatisch zur Datei und Zeile. */
              taE.value = 'class Auto {' + NLt +
                '  public String marke;' + NLt +
                '}' + NLt +
                'Auto a = new Auto();';
              taE.dispatch('input');
              gta.value = 'class Garage {' + NLt +
                '  public int plaetze;' + NLt +
                '}' + NLt +
                'Garage g = new Garage();';
              gta.dispatch('input');
              stepBtn.dispatch('click');
              ok(!hidden(pane2) && hidden(pane1), 'T33a Einzelschritt oeffnet automatisch die Datei des naechsten Schritts');
              ok(f2.row.classList.contains('has-step') && !f1.row.classList.contains('has-step'), 'T33b Datei des Schritts ist in der Dateiliste gelb unterlegt');
              var stepCells = pane2.querySelector('.ln-gutter').querySelectorAll('.ln-cur');
              ok(stepCells.length === 1, 'T33c die pausierte Zeile ist in den Zeilennummern gelb markiert');
              var stepOff = 0;
              var stepLines = String(gta.value).split(NLt);
              var sl;
              for (sl = 0; sl < 3; sl++) { stepOff += stepLines[sl].length + 1; }
              ok(gta.selectionStart === stepOff && gta.selectionEnd === stepOff, 'T33d Cursor springt an den Anfang der pausierten Zeile');
              stepBtn.dispatch('click');
              ok(!f2.row.classList.contains('has-step') && !f1.row.classList.contains('has-step'), 'T33e gelbe Datei-Markierung loest sich, wenn das Programm durchlaeuft');
              ok(stepInfo.textContent.indexOf('durchgelaufen') !== -1, 'T33f Info meldet das Ende des Programms');

              /* T34: Dateien mit Fehlern sind in der Dateiliste rot unterlegt. */
              gta.value = 'class Garage {' + NLt +
                '  public int plaetze' + NLt +
                '}';
              gta.dispatch('input');
              runBtn.dispatch('click');
              ok(f2.row.classList.contains('has-err'), 'T34a fehlerhafte Datei ist in der Dateiliste rot unterlegt');
              ok(!f1.row.classList.contains('has-err'), 'T34b fehlerfreie Datei bleibt ohne rote Unterlage');
              gta.value = 'class Garage {' + NLt +
                '  public int plaetze;' + NLt +
                '}';
              gta.dispatch('input');
              runBtn.dispatch('click');
              ok(!f2.row.classList.contains('has-err'), 'T34c rote Unterlage verschwindet, sobald der Fehler behoben ist');

              /* T35: Objekte-Tab: Methoden im Objektdiagramm anklickbar. */
              ta.value = AUTO_CODE;
              ta.dispatch('input');
              runBtn.dispatch('click');
              tabObjects.dispatch('click');
              ok(!hidden(paneObjects) && hidden(paneOut), 'T35a Objekte-Tab oeffnet das Objektdiagramm');
              var chips = objBox.querySelectorAll('button');
              var chipVor = null;
              var chipFahr = null;
              var ci3;
              for (ci3 = 0; ci3 < chips.length; ci3++) {
                if (chips[ci3].textContent === 'vorstellen()') { chipVor = chips[ci3]; }
                if (chips[ci3].textContent === 'fahren(km)') { chipFahr = chips[ci3]; }
              }
              ok(chipVor !== null && chipFahr !== null, 'T35b Methoden der Klasse erscheinen als Chips am Objekt');
              if (chipVor) {
                chipVor.dispatch('click');
                var lastM = replLog.children[replLog.children.length - 1];
                ok(lastM.textOf().indexOf('BMW ist 0 km gefahren.') !== -1, 'T35c Klick auf Methode ohne Parameter ruft sie auf und protokolliert den Rueckgabewert');
                ok(hidden(paneOut), 'T35d der Klick springt nicht mehr in den ▶-Tab (Ausgabe-Tab bleibt zu)');
                var bubbles = objBox.querySelectorAll('.obj-bubble');
                ok(bubbles.length === 1, 'T35g das Ergebnis erscheint als Blase direkt am Objekt');
                if (bubbles.length === 1) {
                  ok(bubbles[0].textOf().indexOf('BMW ist 0 km gefahren.') !== -1, 'T35h die Blase zeigt Rueckgabe und Ausgabe des Aufrufs');
                }
              }
              if (chipFahr) {
                replIn.value = '';
                chipFahr.dispatch('click');
                ok(replIn.value === 'a.fahren(km)', 'T35e Methode mit Parametern setzt den Aufruf ins Anweisungsfeld');
                ok(hidden(paneOut), 'T35f auch hier bleibt die Ansicht im Objekte-Tab stehen');
              }

              /* T36: Datenbank-Tab: Tabellen-Schema und DB-Konsole. */
              ta.value = 'class Auto { }' + NLt +
                'Database.execute("CREATE TABLE schueler (name TEXT, punkte INT)");' + NLt +
                'Database.execute("INSERT INTO schueler VALUES (\'Anna\', 12)");' + NLt;
              ta.dispatch('input');
              runBtn.dispatch('click');
              tabDb.dispatch('click');
              ok(!hidden(paneDb) && hidden(paneObjects), 'T36a Datenbank-Tab oeffnet');
              var schemaTxt = dbSchemaBox.textOf();
              ok(schemaTxt.indexOf('schueler') !== -1, 'T36b angelegte Tabelle erscheint als Schema-Kachel');
              ok(schemaTxt.indexOf('name') !== -1 && schemaTxt.indexOf('TEXT') !== -1 && schemaTxt.indexOf('punkte') !== -1 && schemaTxt.indexOf('INT') !== -1, 'T36c Spalten der Tabelle stehen mit Typ im Schema');
                  var schemaTbl = dbSchemaBox.querySelector('table.db-schema-table');
                  ok(schemaTbl !== null, 'T36f das Schema ist eine echte Tabelle (kein Diagramm)');
                  if (schemaTbl) {
                    var schRows = schemaTbl.querySelectorAll('tbody tr');
                    ok(schRows.length === 2 && schRows[0].children.length === 2 && schRows[0].children[0].textContent === 'name' && schRows[0].children[1].textContent === 'TEXT', 'T36g Tabellenzeilen zeigen Spalte und Typ getrennt');
                  }
              dbIn.value = 'SELECT * FROM schueler';
              dbRunBtn.dispatch('click');
              ok(dbOut.textContent.indexOf('Anna') !== -1 && dbOut.textContent.indexOf('12') !== -1, 'T36d SELECT in der DB-Konsole zeigt die Zeilen');
              dbIn.value = 'SELECT * FROM fehlt';
              dbRunBtn.dispatch('click');
              ok(dbOut.textContent.indexOf('gibt es noch nicht') !== -1, 'T36e SQL-Fehler erscheinen mit Tipp in der DB-Konsole');

              /* ================= T40: InfoBook-Freie Flaeche (#64) =================
               * Eine eigene Fake-Seite wie templates/ibook_page.php: Die Seite ist
               * EINE freie Flaeche, der Editor baut sich beim Laden automatisch auf
               * (keine Baustein-Leiste mehr). Geprueft wird: Toolbar mit Werkzeugen,
               * Bild per Drag&Drop an der Ablege-Position, Text per Doppeltipp mit
               * Hintergrund/Ausrichtung, Stift-Striche als eigene Objekte (Farbe und
               * Staerke nachtraeglich aenderbar, verschiebbar), Radierer entfernt
               * Striche, Einbettung mit Adress-Eingabe, Serialisierung nach collect(). */
              global.localStorage = { getItem: function () { return null; }, setItem: function () {} };
              var ibDoc = new FakeDoc();
              ibDoc.documentElement = new FakeEl('html');
              ibDoc.body.setAttribute('data-csrf', 'csrf-t40');
              var ibEditor = new FakeEl('div');
              ibEditor.setAttribute('data-book-id', '77');
              ibEditor.setAttribute('data-page-id', '42');
              ibEditor.setAttribute('data-nav', JSON.stringify([{ id: 42, t: 'Aktuelle Seite', s: 'Abschnitt A' }, { id: 43, t: 'Zielseite', s: 'Abschnitt A' }]));
              ibEditor.setAttribute('data-diagrams', JSON.stringify([{ id: 5, t: 'Klassendiagramm Auto', kind: 'java', src: 'classDiagram\nAuto <|-- BMW' }]));
              ibEditor.setAttribute('data-dbtemplates', JSON.stringify([{ slug: 'shop', t: 'Kaufhaus Laden', sql: "CREATE TABLE artikel (name TEXT, preis INT); INSERT INTO artikel VALUES ('Kaugummi', 1)" }]));
              ibEditor.setAttribute('data-codeclasses', JSON.stringify([{ project: 'Fahrzeuge', name: 'Auto.java', code: 'class Auto {\n  String name;\n  void fahren() {}\n}\n' }]));
              ibEditor.setAttribute('data-blocks', '[]');
              ibDoc.register(ibEditor, 'ibook-editor');
              ibDoc.register(new FakeEl('input'), 'ibook-blocks-json');
              var ibSaveForm = new FakeEl('form');
              ibSaveForm.setAttribute('action', '/infobuecher/77/seite/42');
              ibDoc.register(ibSaveForm, 'ibook-saveform');
              var ibookUrls = [];
              global.fetch = function (url) {
                ibookUrls.push(url);
                return Promise.resolve({
                  ok: true,
                  json: function () { return Promise.resolve({ ok: true, id: 4242, name: 'foto.png' }); }
                });
              };
              global.document = ibDoc;
              /* app.js im eigenen Fake-DOM laden: alle IIFEs ohne ihre Elemente
               * steigen aus, nur der InfoBook-Editor (#ibook-editor) bindet sich. */
              require('../public/assets/js/app.js');

              var ibBar = ibDoc.body.querySelector('.icv-toolbar');
              ok(ibBar !== null, 'T40a GoodNotes-artige Symbolleiste ist da, ohne Baustein-Leiste');
              var ibStage = ibDoc.body.querySelector('.icv-stage');
              ok(ibStage !== null && ibStage.getAttribute('style').indexOf('1600/1000') !== -1, 'T40b die Seite ist automatisch die freie Flaeche (1600x1000)');
              var ibToolBtns = ibDoc.body.querySelectorAll('[data-icv-tool]');
              ok(ibToolBtns.length === 3, 'T40c drei Werkzeuge: Auswahl, Stift, Radierer');
              var ibInk = ibStage.querySelector('canvas.icv-inkcanvas');
              ok(ibInk !== null && ibInk.width === 1600 && ibInk.height === 1000, 'T40d transparente Zeichenebene ueber allem (1600x1000)');
              ok(ibDoc.getElementById('ibook-addrow') === null && ibDoc.body.querySelectorAll('[data-ib-add]').length === 0, 'T40e keine Baustein-Buttons mehr');
              var ibCollect = function () { return JSON.parse(ibDoc.getElementById('ibook-blocks-json').value)[0]; };
              var cv0 = ibCollect();
              ok(cv0 && cv0.type === 'canvas' && cv0.w === 1600 && cv0.h === 1000 && cv0.items.length === 0, 'T40f leere Seite serialisiert genau einen Canvas-Block');

              /* rect 800x500: clientX 400 -> logisch x=800, minus Haelfte -> 640 = 40%. */
              ibStage.getBoundingClientRect = function () { return { left: 0, top: 0, width: 800, height: 500 }; };
              var ibBlob = new Blob(['x'], { type: 'image/png' });
              ibBlob.name = 'foto.png';
              ibStage.dispatch('drop', { clientX: 400, clientY: 250, dataTransfer: { files: [ibBlob] } });
              ok(ibookUrls.indexOf('/infobuecher/77/medium') !== -1, 'T40g abgelegtes Bild wird sofort hochgeladen');

              /* Text per Doppeltipp auf freie Stelle, Hintergrund und Ausrichtung */
              ibStage.dispatch('dblclick', { clientX: 100, clientY: 60, target: ibStage });
              var ibTa = ibStage.querySelector('.icv-text textarea');
              ok(ibTa !== null, 'T40h Doppeltipp legt ein Textfeld genau an der Stelle an');
              if (ibTa) {
                ibTa.value = ':::merksatz Beschriftung';
                ibTa.dispatch('input');
              }
              var ibTextDiv = ibStage.querySelector('.icv-text');
              ibTextDiv.dispatch('pointerdown', { target: ibTextDiv });
              ok(ibTextDiv.classList.contains('icv-selected'), 'T40i Textfeld ist per Klick auswaehlbar');
              var ibCtx = ibDoc.body.querySelector('.icv-ctx');
              ok(ibCtx.classList.contains('icv-toolbar-show'), 'T40j Kontextleiste erscheint bei Auswahl');
              var ibBgBtn = ibDoc.body.querySelector('[data-icv-bg="#eff6ff"]');
              ibBgBtn.dispatch('click');
              var ibAlignBtn = ibDoc.body.querySelector('[data-icv-align="right"]');
              ibAlignBtn.dispatch('click');
              var cvText = ibCollect();
              var txtSel = cvText && cvText.items ? cvText.items.filter(function (it) { return it.kind === 'text'; })[0] : null;
              ok(txtSel && txtSel.body === ':::merksatz Beschriftung' && txtSel.bg === '#eff6ff' && txtSel.align === 'right', 'T40k Textfeld mit Hintergrund und Rechts-Ausrichtung serialisiert');

              /* Stift: Strich als eigenes Objekt zeichnen (standardfarbe wechselt vorab) */
              ibDoc.body.querySelector('[data-icv-color="#ef4444"]').dispatch('click');
              ibDoc.body.querySelector('[data-icv-tool="draw"]').dispatch('click');
              ok(ibStage.classList.contains('icv-mode-draw'), 'T40l Stift-Modus schaltet die Flaeche um');
              ibInk.dispatch('pointerdown', { clientX: 10, clientY: 10, pointerId: 3, pressure: 0.5 });
              ibInk.dispatch('pointermove', { clientX: 50, clientY: 30, pointerId: 3, pressure: 0.5 });
              ibInk.dispatch('pointerup', { pointerId: 3 });
              var inkCalls = ibInk.__ctx.__calls;
              ok(inkCalls.indexOf('beginPath') !== -1 && inkCalls.indexOf('stroke') !== -1, 'T40m der Strich zeichnet sich live auf die Ebene ueber allem');
              var cvInk = ibCollect();
              var inkA = cvInk && cvInk.items ? cvInk.items.filter(function (it) { return it.kind === 'ink'; })[0] : null;
              ok(inkA && inkA.c === '#ef4444' && inkA.sw === 4 && inkA.x === 20 && inkA.y === 20 && inkA.w === 80 && inkA.h === 40, 'T40n Stift-Strich landet als Objekt (Farbe, Staerke, Bounding-Box) in collect()');
              ok(inkA && inkA.p.length === 4 && inkA.p[0] === 0 && inkA.p[1] === 0 && inkA.p[2] === 80 && inkA.p[3] === 40, 'T40o Strichpunkte relativ zum Ursprung, direkt mit der Seite speicherbar');

              /* Auswahl: Strich umfaerben, Staerke aendern, verschieben */
              ibDoc.body.querySelector('[data-icv-tool="select"]').dispatch('click');
              var ibStrokeDiv = ibStage.querySelector('.icv-stroke');
              ok(ibStrokeDiv !== null && ibStrokeDiv.querySelector('path') !== null, 'T40p gezeichneter Strich ist als SVG-Objekt auf der Flaeche');
              ibStrokeDiv.dispatch('pointerdown', { target: ibStrokeDiv });
              ibDoc.body.querySelector('[data-icv-color="#3b82f6"]').dispatch('click');
              ibDoc.body.querySelector('[data-icv-sw="8"]').dispatch('click');
              var ibStrokePath = ibStage.querySelector('.icv-stroke path');
              ok(ibStrokePath.getAttribute('stroke') === '#3b82f6' && ibStrokePath.getAttribute('stroke-width') === '8', 'T40q ausgewaehlter Strich laesst Farbe und Staerke nachtraeglich aendern');
              var ibStrokeMove = ibStage.querySelector('.icv-stroke .icv-move');
              ibStrokeMove.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 9 });
              ibStrokeMove.dispatch('pointermove', { clientX: 160, clientY: 50, pointerId: 9 });
              ibStrokeMove.dispatch('pointerup', { pointerId: 9 });
              var ibStrokeDiv2 = ibStage.querySelector('.icv-stroke');
              ok(ibStrokeDiv2.style.left === '21.25%', 'T40r Strich laesst sich wie ein Bild verschieben (21.25%)');

              /* Radierer: Striche wegwischen, Bilder und Textfelder bleiben */
              ibDoc.body.querySelector('[data-icv-tool="eraser"]').dispatch('click');
              ok(ibStage.classList.contains('icv-mode-eraser'), 'T40s Radierer-Modus schaltet die Flaeche um');
              ibInk.dispatch('pointerdown', { clientX: 200, clientY: 70, pointerId: 4 });
              ibInk.dispatch('pointerup', { pointerId: 4 });
              ok(ibStage.querySelector('.icv-stroke') === null, 'T40t Radierer entfernt den getroffenen Strich');
              var cvErased = ibCollect();
              var inkCount = cvErased && cvErased.items ? cvErased.items.filter(function (it) { return it.kind === 'ink'; }).length : -1;
              ok(inkCount === 0, 'T40u nur der Strich ist weg, serialisiert ist kein Ink-Objekt mehr');

              /* Einbettung: Platzhalter im Editor, Adresse im Kontextbereich */
              var ibEmbBtn = null;
              var ibBarBtns = ibBar.querySelectorAll('button');
              var ibi;
              for (ibi = 0; ibi < ibBarBtns.length; ibi++) {
                /* Icons only (#68): Der Einbettungs-Knopf zeigt nur sein Icon,
                 * gefunden wird er ueber seine Hover-Beschreibung. */
                var ibTitle68 = String(ibBarBtns[ibi].title || ibBarBtns[ibi].getAttribute('title') || '');
                if (ibTitle68.indexOf('einbetten') !== -1 || ibTitle68.indexOf('Einbettung') !== -1) { ibEmbBtn = ibBarBtns[ibi]; }
              }
              ibEmbBtn.dispatch('click');
              var ibEmbDiv = ibStage.querySelector('.icv-embed');
              ok(ibEmbDiv !== null, 'T40v Einbettung liegt als Platzhalter auf der Flaeche');
              var ibUrlIn = ibBar.querySelector('.icv-ctx-input');
              ibUrlIn.value = 'https://learningapps.org/watch?v=abc123';
              ibUrlIn.dispatch('input');
              var cvEmb = ibCollect();
              var embSel = cvEmb && cvEmb.items ? cvEmb.items.filter(function (it) { return it.kind === 'embed'; })[0] : null;
              ok(embSel && embSel.url === 'https://learningapps.org/watch?v=abc123', 'T40w Einbettungs-Adresse aus dem Kontextbereich wird serialisiert');

              /* Radierer soll Textfeld und Bild unberuehrt lassen */
              ok(ibStage.querySelector('.icv-text textarea') !== null, 'T40x Textfeld steht noch nach dem Radieren');
              ok(inkCount === 0 && ibookUrls.indexOf('/infobuecher/77/zeichnung') === -1, 'T40y Striche brauchen keinen PNG-Upload mehr (Speicher-Bug behoben)');

              setTimeout(function () {
                var ibImg = ibStage.querySelector('.icv-image img');
                ok(ibImg !== null && ibImg.src === '/infobuecher/medium/4242', 'T40z hochgeladenes Bild liegt nach dem Upload auf der Flaeche');
                var ibImgDiv = ibStage.querySelector('.icv-image');
                ok(ibImgDiv.style.left === '40%', 'T40za Bild liegt an der Ablege-Position (40%)');
                ok(ibookUrls.indexOf('/infobuecher/77/medium') === ibookUrls.lastIndexOf('/infobuecher/77/medium'), 'T40zb genau ein Upload pro abgelegtem Bild');
                var collected = JSON.parse(ibDoc.getElementById('ibook-blocks-json').value);
                var cv = collected[0];
                ok(cv && cv.type === 'canvas' && cv.w === 1600 && cv.h === 1000, 'T40zc collect() schreibt genau den Canvas-Block');
                var imgItem = cv && cv.items ? cv.items.filter(function (it) { return it.kind === 'image'; })[0] : null;
                ok(imgItem && imgItem.media === 4242 && imgItem.x === 640 && imgItem.y === 380, 'T40zd Bild mit Medien-ID und Ablege-Position wird serialisiert');

                /* T43 (#66): Loeschen-Knopf direkt am Element: Einbettung
                 * auswaehlen, auf das rote X am Objekt klicken, Element ist weg. */
                var ibEmbDiv66 = ibStage.querySelector('.icv-embed');
                ok(ibEmbDiv66 !== null, 'T43a Einbettung liegt noch vor dem Loesch-Test auf der Flaeche');
                if (ibEmbDiv66) { ibEmbDiv66.dispatch('pointerdown', { target: ibEmbDiv66 }); }
                var ibDelBtn66 = ibStage.querySelector('.icv-embed .icv-del');
                ok(ibDelBtn66 !== null, 'T43b am Element sitzt ein Loeschen-Knopf (icv-del)');
                if (ibDelBtn66) { ibDelBtn66.dispatch('click', { target: ibDelBtn66 }); }
                var cvDel66 = ibCollect();
                var embCount66 = cvDel66 && cvDel66.items ? cvDel66.items.filter(function (it) { return it.kind === 'embed'; }).length : -1;
                ok(ibStage.querySelector('.icv-embed') === null && embCount66 === 0, 'T43c X am Element entfernt es, serialisiert ist es auch weg');

                /* T44 (#66): Entf-Taste loescht die Auswahl (nicht waehrend der Texteingabe). */
                ibDoc.body.querySelector('[data-icv-tool="select"]').dispatch('click');
                ibStage.dispatch('dblclick', { clientX: 300, clientY: 60, target: ibStage });
                var textCount66 = ibStage.querySelectorAll('.icv-text').length;
                ok(textCount66 === 2, 'T44a zweites Textfeld steht vor der Entf-Taste auf der Flaeche');
                ibDoc._fire('keydown', { key: 'Delete', target: ibStage });
                ok(ibStage.querySelectorAll('.icv-text').length === textCount66 - 1, 'T44b Entf-Taste entfernt das ausgewaehlte Element');
                var growTa66 = ibStage.querySelector('.icv-text textarea');
                ok(growTa66 !== null && growTa66.style.height === 'auto', 'T45 Textfeld-Hoehe folgt dem Text (Auto-Grow statt manuellem Ziehen)');

                /* T46 (#66): Strich auf der GANZEN Flaeche greifen und verschieben. */
                ibDoc.body.querySelector('[data-icv-tool="draw"]').dispatch('click');
                ibInk.dispatch('pointerdown', { clientX: 10, clientY: 10, pointerId: 66, pressure: 0.5 });
                ibInk.dispatch('pointermove', { clientX: 50, clientY: 30, pointerId: 66, pressure: 0.5 });
                ibInk.dispatch('pointerup', { pointerId: 66 });
                ibDoc.body.querySelector('[data-icv-tool="select"]').dispatch('click');
                var ibStroke66 = ibStage.querySelector('.icv-stroke');
                ok(ibStroke66 !== null && ibStroke66.style.left === '1.25%', 'T46a neuer Strich liegt bei 1.25% (20 von 1600)');
                if (ibStroke66) {
                  ibStroke66.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 67 });
                  ibStroke66.dispatch('pointermove', { clientX: 160, clientY: 50, pointerId: 67 });
                  ibStroke66.dispatch('pointerup', { pointerId: 67 });
                }
                var ibStroke66b = ibStage.querySelector('.icv-stroke');
                ok(ibStroke66b !== null && ibStroke66b.style.left === '21.25%', 'T46b Strich laesst sich an jeder Stelle seiner Flaeche anfassen (21.25%)');

                /* T47 (#67): Waehrend eines Drags duerfen Klicks auf andere
                 * Objekte die Auswahl NICHT wechseln - erst wenn das Objekt
                 * wieder liegt, ist die Flaeche frei. */
                var ibImgDiv67 = ibStage.querySelector('.icv-image');
                ok(ibImgDiv67 !== null, 'T47a Bild liegt vor dem Drag-Guard-Test auf der Flaeche');
                if (ibStroke66b) { ibStroke66b.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 70 }); }
                if (ibImgDiv67) { ibImgDiv67.dispatch('pointerdown', { target: ibImgDiv67 }); }
                ok(ibImgDiv67 !== null && ibStroke66b.classList.contains('icv-selected') && !ibImgDiv67.classList.contains('icv-selected'), 'T47 waehrend des Drags bleibt die Auswahl beim gezogenen Objekt (Klick aufs Bild waehlt nicht um)');
                if (ibStroke66b) { ibStroke66b.dispatch('pointerup', { pointerId: 70 }); }
                ok(ibImgDiv67 !== null && !ibImgDiv67.classList.contains('icv-selected'), 'T47b nach dem Ablegen ist immer noch nichts anderes ausgewaehlt');

                /* T48 (#67): Vier Eck-Handles statt randloser Skalier-Bereich,
                 * proportionales Skalieren von allen Ecken mit fester Anker-Ecke. */
                if (ibImgDiv67) { ibImgDiv67.dispatch('pointerdown', { target: ibImgDiv67 }); }
                var rsCount67 = ibImgDiv67 ? ibImgDiv67.querySelectorAll('.icv-rs').length : -1;
                ok(rsCount67 === 4, 'T48a das ausgewaehlte Bild hat genau vier Eck-Handles (nur bei Auswahl sichtbar, CSS)');
                var rsSe67 = ibImgDiv67 ? ibImgDiv67.querySelector('.icv-rs-se') : null;
                var rsNw67 = ibImgDiv67 ? ibImgDiv67.querySelector('.icv-rs-nw') : null;
                ok(rsSe67 !== null && rsNw67 !== null, 'T48b alle Ecken sind als eigene Dragzonen da (SE und NW)');
                if (rsSe67) {
                  rsSe67.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 71 });
                  rsSe67.dispatch('pointermove', { clientX: 100, clientY: 75, pointerId: 71 });
                  rsSe67.dispatch('pointerup', { pointerId: 71 });
                }
                ok(ibImgDiv67 !== null && ibImgDiv67.style.width === '32.5%' && ibImgDiv67.style.height === '39%', 'T48c SE-Ecke vergroessert proportional (320x240 wird 520x390)');
                if (rsNw67) {
                  rsNw67.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 72 });
                  rsNw67.dispatch('pointermove', { clientX: -100, clientY: -75, pointerId: 72 });
                  rsNw67.dispatch('pointerup', { pointerId: 72 });
                }
                ok(ibImgDiv67 !== null && ibImgDiv67.style.left === '27.5%' && ibImgDiv67.style.top === '23%' && ibImgDiv67.style.width === '45%', 'T48d NW-Ecke skaliert mit fester Anker-Ecke unten rechts (Position laeuft mit)');
                var cv48 = ibCollect();
                var img48 = cv48 && cv48.items ? cv48.items.filter(function (it) { return it.kind === 'image'; })[0] : null;
                ok(img48 && img48.w === 720 && img48.h === 540 && img48.x === 440 && img48.y === 230, 'T48e die skalierte Groesse und Position werden mit der Seite serialisiert');

                /* T51 (#67): Die Flaeche wächst automatisch mit, sobald ein
                 * Objekt nach unten gezogen wird, und laesst sich manuell
                 * vergroessern bzw. bis knapp unter das unterste Element
                 * verkleinern. */
                var stroke51 = ibStage.querySelector('.icv-stroke');
                ok(stroke51 !== null, 'T51a Strich liegt vor dem Flaechen-Test auf der Flaeche');
                if (stroke51) {
                  stroke51.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 73 });
                  stroke51.dispatch('pointermove', { clientX: 160, clientY: 900, pointerId: 73 });
                  stroke51.dispatch('pointerup', { pointerId: 73 });
                }
                ok(ibStage.getAttribute('style').indexOf('1600/2040') !== -1, 'T51 ein Objekt unten angefasst laesst die Flaeche automatisch mitwachsen (1000 wird 2040)');
                ibDoc.body.querySelector('[data-icv-shrink]').dispatch('click');
                ok(ibStage.getAttribute('style').indexOf('1600/2020') !== -1, 'T51b verkleinern stoppt knapp unter dem untersten Element (Minimum 2020)');
                ibDoc.body.querySelector('[data-icv-grow]').dispatch('click');
                ok(ibStage.getAttribute('style').indexOf('1600/2320') !== -1 && ibInk.height === 2320, 'T51c vergroessern schlaegt 300 dazu, die Zeichenebene wächst mit (2320)');

                /* T50 (#67): Vorschau fuer eingebettete Videos (erster Frame)
                 * und YouTube-Einbettungen (Thumbnail aus der URL). */
                var appPath50 = require.resolve('../public/assets/js/app.js');
                delete require.cache[appPath50];
                var ibDoc2 = new FakeDoc();
                ibDoc2.documentElement = new FakeEl('html');
                ibDoc2.body.setAttribute('data-csrf', 'csrf-t50');
                var ibEditor2 = new FakeEl('div');
                ibEditor2.setAttribute('data-book-id', '77');
                ibEditor2.setAttribute('data-page-id', '42');
                ibEditor2.setAttribute('data-blocks', JSON.stringify([{ type: 'canvas', w: 1600, h: 1000, items: [
                  { kind: 'video', media: 11, name: 'clip.mp4', x: 100, y: 100, w: 480, h: 270 },
                  { kind: 'embed', url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', title: 'Demo', x: 100, y: 500, w: 480 }
                ] }]));
                ibDoc2.register(ibEditor2, 'ibook-editor');
                ibDoc2.register(new FakeEl('input'), 'ibook-blocks-json');
                var ibForm2 = new FakeEl('form');
                ibForm2.setAttribute('action', '/infobuecher/77/seite/42');
                ibDoc2.register(ibForm2, 'ibook-saveform');
                var savedDoc50 = global.document;
                global.document = ibDoc2;
                require(appPath50);
                var vid50 = ibDoc2.body.querySelector('video.icv-video-preview');
                ok(vid50 !== null && vid50.getAttribute('src') === '/infobuecher/medium/11', 'T50a eingebettetes Video zeigt seinen ersten Frame als Vorschau (preload=metadata)');
                var yt50 = ibDoc2.body.querySelector('img.icv-embed-thumb');
                ok(yt50 !== null && yt50.getAttribute('src') === 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', 'T50b YouTube-Einbettung zeigt das Vorschaubild direkt aus der Video-Nummer der URL');
                global.document = savedDoc50;

                /* T53 (#68): Der Hintergrund faerbt den GESAMTEN Zettel,
                 * 'Ohne' nimmt ihm Hintergrund, gelbes Post-It und Schatten. */
                var ibText53 = ibStage.querySelector('.icv-text');
                ok(ibText53 !== null, 'T53a Textfeld liegt vor dem Post-It-Test auf der Flaeche');
                if (ibText53) { ibText53.dispatch('pointerdown', { target: ibText53 }); }
                ibDoc.body.querySelector('[data-icv-bg="none"]').dispatch('click');
                ok(ibText53 !== null && ibText53.style.background === 'transparent' && ibText53.style.borderColor === 'transparent' && ibText53.style.boxShadow === 'none', 'T53 Ohne Hintergrund meint den ganzen Zettel: transparent, ohne Rand und Schatten');
                var cv53 = ibCollect();
                var txt53 = cv53 && cv53.items ? cv53.items.filter(function (it) { return it.kind === 'text'; })[0] : null;
                ok(txt53 && txt53.bg === 'none', 'T53b der ganze Zettel ohne Hintergrund wird mit der Seite serialisiert (bg none)');
                ibDoc.body.querySelector('[data-icv-bg="#eff6ff"]').dispatch('click');
                ok(ibText53 !== null && ibText53.style.background === '#eff6ff', 'T53c eine Hintergrundfarbe faerbt ebenfalls den ganzen Zettel, nicht nur die Schreibfläche');

                /* T54 (#68): Lasso-Auswahl mit gedrueckter Maustaste und
                 * Gruppieren. Die Flaeche ist 1600x2320, das Harness-Stadium
                 * 800x500: clientX halbiert, clientY mit 4,64 multipliziert. */
                ibStage.dispatch('pointerdown', { clientX: 50, clientY: 45, pointerId: 81, target: ibStage });
                ibStage.dispatch('pointermove', { clientX: 450, clientY: 45, pointerId: 81 });
                ibStage.dispatch('pointermove', { clientX: 450, clientY: 430, pointerId: 81 });
                ibStage.dispatch('pointermove', { clientX: 50, clientY: 430, pointerId: 81 });
                ibStage.dispatch('pointerup', { pointerId: 81 });
                var ibImg54 = ibStage.querySelector('.icv-image');
                var ibStroke54 = ibStage.querySelector('.icv-stroke');
                var ibText54 = ibStage.querySelector('.icv-text');
                ok(ibImg54 !== null && ibStroke54 !== null && ibImg54.classList.contains('icv-selected') && ibStroke54.classList.contains('icv-selected'), 'T54 Lasso waehlt alles, was es beruehrt (Bild und Strich markiert)');
                ok(ibText54 !== null && !ibText54.classList.contains('icv-selected'), 'T54b das Textfeld ausserhalb des Lassos bleibt unmarkiert');
                ok(ibDoc.body.querySelector('.icv-ctx').textOf().indexOf('2 Objekte') !== -1, 'T54c Kontextleiste meldet zwei ausgewaehlte Objekte');
                ok(ibStage.querySelector('polyline') !== null, 'T54d das Lasso-Overlay liegt mit auf der Flaeche');
                ibDoc.body.querySelector('[data-icv-group]').dispatch('click');
                var cv54 = ibCollect();
                var g54img = cv54 && cv54.items ? cv54.items.filter(function (it) { return it.kind === 'image'; })[0] : null;
                var g54stroke = cv54 && cv54.items ? cv54.items.filter(function (it) { return it.kind === 'ink'; })[0] : null;
                ok(g54img && g54stroke && g54img.g > 0 && g54img.g === g54stroke.g, 'T54e Gruppieren gibt allen Lasso-Objekten dieselbe Gruppen-ID');
                ibImg54.dispatch('pointerdown', { clientX: 0, clientY: 0, pointerId: 82 });
                ibImg54.dispatch('pointermove', { clientX: 100, clientY: 0, pointerId: 82 });
                ibImg54.dispatch('pointerup', { pointerId: 82 });
                var ibStroke54b = ibStage.querySelector('.icv-stroke');
                ok(ibStroke54b !== null && ibStroke54b.style.left === '53.75%', 'T54f Gruppenmitglieder bewegen sich gemeinsam (Strich laeuft mit auf 53.75%)');
                ibDoc.body.querySelector('[data-icv-ungroup]').dispatch('click');
                var cv54b = ibCollect();
                var g54bimg = cv54b && cv54b.items ? cv54b.items.filter(function (it) { return it.kind === 'image'; })[0] : null;
                var g54bstroke = cv54b && cv54b.items ? cv54b.items.filter(function (it) { return it.kind === 'ink'; })[0] : null;
                ok(g54bimg && g54bstroke && g54bimg.g === undefined && g54bstroke.g === undefined, 'T54g Gruppe loesen entfernt die Gruppen-ID von allen Mitgliedern');

                /* T55 (#68): Icons only in der Leiste, Flaechen-Knoepfe unten
                 * rechts in der Flaeche, Auswahl-Sachen im Kontextbereich. */
                var ibSelBtn55 = ibDoc.body.querySelector('[data-icv-tool="select"]');
                ok(ibSelBtn55 !== null && ibSelBtn55.textOf() === '🖱️' && String(ibSelBtn55.title || '').indexOf('Auswahl') !== -1, 'T55 Werkzeug-Knoepfe zeigen nur das Icon, die Beschreibung steht als Hover (title)');
                ok(ibBar.querySelector('[data-icv-grow]') === null && ibBar.querySelector('[data-icv-shrink]') === null && ibStage.querySelector('[data-icv-grow]') !== null, 'T55b die Flaechen-Knoepfe sitzen unten rechts in der Flaeche, nicht mehr in der Leiste');
                var ibDel55 = ibDoc.body.querySelector('.icv-ctx .icv-delbtn');
                ok(ibDel55 !== null && ibDel55.parent && String(ibDel55.parent.className).indexOf('icv-ctx') !== -1, 'T55c Loeschen liegt im Auswahl-Kontext (Kontextleiste erscheint nur mit Auswahl)');
                ok(ibDoc.body.querySelector('.icv-ctx [data-icv-group]') !== null, 'T55d Gruppieren liegt ebenfalls im Auswahl-Kontext');
                var ibSwatches55 = ibBar.querySelector('.icv-swatches');
                ok(ibSwatches55 !== null && ibSwatches55.style.display === 'none', 'T55e ohne Stift-Modus und ohne Strich-Auswahl bleiben Stift-Farben und Staerken aus der Leiste');

                /* T56 (#73): Objekt mit einer anderen Seite des Buchs
                 * verlinken: Auswahl im Kontextbereich, Badge am Element,
                 * Serialisierung, und wieder weg. */
                var ibTxt56 = ibStage.querySelector('.icv-text');
                if (ibTxt56) { ibTxt56.dispatch('pointerdown', { target: ibTxt56 }); }
                var ibSel56 = ibDoc.body.querySelector('.icv-ctx .icv-ctx-select');
                ok(ibSel56 !== null, 'T56 Kontextbereich hat die Seiten-Auswahl zum Verlinken');
                if (ibSel56) {
                  ibSel56.value = '43';
                  ibSel56.dispatch('change');
                  ok(ibTxt56 !== null && ibTxt56.querySelector('.icv-linkbadge') !== null, 'T56b verlinktes Objekt traegt das Kettenglied-Badge');
                  var cv56 = ibCollect();
                  var t56 = cv56 && cv56.items ? cv56.items.filter(function (it) { return it.kind === 'text'; })[0] : null;
                  ok(t56 && t56.link === 43, 'T56c der Seiten-Link wird mit serialisiert (link: 43)');
                  ibSel56.value = '';
                  ibSel56.dispatch('change');
                  var cv56b = ibCollect();
                  var t56b = cv56b && cv56b.items ? cv56b.items.filter(function (it) { return it.kind === 'text'; })[0] : null;
                  ok(t56b && t56b.link === undefined, 'T56d ohne Link-Auswahl verschwindet der Link wieder');
                  ok(ibTxt56 !== null && ibTxt56.querySelector('.icv-linkbadge') === null, 'T56e auch das Badge verschwindet wieder');
                }

                /* T57 (#75): Code-Fenster mit Konsole. Einfuegen ueber den
                 * Laptop-Knopf in der Leiste, Quelltext tippen, Ausfuehren
                 * laeuft ueber die globale Delegation am document. */
                var ibCodeBtn57 = null;
                var ibBarBtns57 = ibBar.querySelectorAll('button');
                for (var ci57 = 0; ci57 < ibBarBtns57.length; ci57++) {
                  if (String(ibBarBtns57[ci57].title || '').indexOf('Code-Fenster') !== -1) { ibCodeBtn57 = ibBarBtns57[ci57]; }
                }
                ok(ibCodeBtn57 !== null && ibCodeBtn57.textOf() === '💻', 'T57 Code-Fenster-Knopf in der Leiste zeigt nur das Icon');
                if (ibCodeBtn57) {
                  ibCodeBtn57.dispatch('click');
                  var ibCodeBox57 = ibStage.querySelector('.icv-code');
                  ok(ibCodeBox57 !== null && ibCodeBox57.querySelector('.icv-code-ta') !== null && ibCodeBox57.querySelector('.icv-code-out') !== null, 'T57b das Code-Fenster hat Editor, Ausfuehren-Knopf und Konsole');
                  if (ibCodeBox57) {
                    var ibLang57 = ibCodeBox57.querySelector('.icv-code-langsel');
                    ok(ibLang57 !== null && ibLang57.value === 'java', 'T57b2 Sprachauswahl ist da, Standard Java');
                    if (ibLang57) {
                      ibLang57.value = 'javascript';
                      ibLang57.dispatch('change');
                      ok(ibCodeBox57.querySelector('.icv-code-run').getAttribute('data-lang') === 'javascript', 'T57b3 die Sprache wandert an den Ausfuehren-Knopf');
                    }
                    var ibCodeTa57 = ibCodeBox57.querySelector('.icv-code-ta');
                    ibCodeTa57.value = "console.log('Hallo InfoBook'); 6 * 7;";
                    ibCodeTa57.dispatch('input');
                    var cv57 = ibCollect();
                    var t57 = cv57 && cv57.items ? cv57.items.filter(function (it) { return it.kind === 'code'; })[0] : null;
                    ok(t57 && t57.code === "console.log('Hallo InfoBook'); 6 * 7;" && t57.lang === 'javascript', 'T57c der Quelltext wird mit Sprache serialisiert');
                    ibCodeBox57.querySelector('.icv-code-run').dispatch('click');
                    var ibCodeOut57 = ibCodeBox57.querySelector('.icv-code-out');
                    ok(ibCodeOut57 !== null && ibCodeOut57.textContent.indexOf('Hallo InfoBook') !== -1 && ibCodeOut57.textContent.indexOf('42') !== -1, 'T57d Ausfuehren schreibt console-Ausgaben und Ergebnis in die Konsole');
                  }
                }

                /* T62 (#81): Java laeuft ueber die Java-VM (kein "System is
                 * not defined" mehr), Compiler-Fehler zeigen Zeile und Datei,
                 * ohne VM gibt es einen klaren Hinweis. */
                if (ibCodeBox57 && ibLang57) {
                  global.window.InfoCraftJavaVM = {
                    run: function (files) {
                      ok(files && typeof files === 'object' && !Array.isArray(files) && typeof files['Main.java'] === 'string' && files['Main.java'].indexOf('System.out.println') !== -1, 'T62 Java-Quelltext geht als Objekt mit Main.java-String an die Java-VM (kein Array mehr)');
                      return { output: 'Hallo aus Java', errors: [], ok: true };
                    }
                  };
                  ibLang57.value = 'java';
                  ibLang57.dispatch('change');
                  ibCodeBox57.querySelector('.icv-code-ta').value = 'System.out.println("Hallo Java");';
                  ibCodeBox57.querySelector('.icv-code-ta').dispatch('input');
                  ibCodeBox57.querySelector('.icv-code-run').dispatch('click');
                  ok(ibCodeOut57.textContent.indexOf('Hallo aus Java') !== -1, 'T62b Sprache Java: die Ausgabe der Java-VM landet in der Konsole');
                  global.window.InfoCraftJavaVM = {
                    run: function () { return { output: '', errors: [{ kind: 'error', message: 'Semikolon fehlt', line: 3, file: 'Main.java' }], ok: false }; }
                  };
                  ibCodeBox57.querySelector('.icv-code-run').dispatch('click');
                  ok(ibCodeOut57.textContent.indexOf('✖ Semikolon fehlt') !== -1 && ibCodeOut57.textContent.indexOf('Zeile 3') !== -1, 'T62c Compiler-Fehler kommen mit Zeilennummer zurueck');
                  delete global.window.InfoCraftJavaVM;
                  ibCodeBox57.querySelector('.icv-code-run').dispatch('click');
                  ok(ibCodeOut57.textContent.indexOf('nicht geladen') !== -1, 'T62d ohne Java-VM gibt es einen klaren Hinweis statt "System is not defined"');
                }

                /* T58 (#76): Datenbank-Element mit Schema und SQL-Abfrage.
                 * Seed baut die Beispieldatenbank im Browser auf, die kleine
                 * Engine versteht WHERE, LIKE, ORDER BY und LIMIT. */
                var ibDbBtn58 = null;
                var ibBarBtns58 = ibBar.querySelectorAll('button');
                for (var ci58 = 0; ci58 < ibBarBtns58.length; ci58++) {
                  if (String(ibBarBtns58[ci58].title || '').indexOf('Datenbank') !== -1) { ibDbBtn58 = ibBarBtns58[ci58]; }
                }
                ok(ibDbBtn58 !== null && ibDbBtn58.textOf() === '🗄️', 'T58 Datenbank-Knopf in der Leiste zeigt nur das Icon');
                if (ibDbBtn58) {
                  ibDbBtn58.dispatch('click');
                  var ibDbBox58 = ibStage.querySelector('.icv-db');
                  ok(ibDbBox58 !== null && ibDbBox58.querySelector('.icv-db-seed-ta') !== null && ibDbBox58.querySelector('.icv-db-in') !== null && ibDbBox58.querySelector('.icv-db-run') !== null && ibDbBox58.querySelector('.icv-db-out') !== null, 'T58b das Datenbank-Element hat Seed-Feld, Abfrage, Ausfuehren und Ausgabe');
                  if (ibDbBox58) {
                    var ibDbSeed58 = ibDbBox58.querySelector('.icv-db-seed-ta');
                    ibDbSeed58.value = "CREATE TABLE schueler (name TEXT, punkte INT); INSERT INTO schueler VALUES ('Anna', 12); INSERT INTO schueler VALUES ('Ben', 9)";
                    ibDbSeed58.dispatch('input');
                    ok(ibDbBox58.querySelector('.icv-db-schema').textContent.indexOf('schueler') !== -1 && ibDbBox58.querySelector('.icv-db-schema').textContent.indexOf('punkte INT') !== -1, 'T58c das Schema aktualisiert sich live aus den CREATE-TABLE-Zeilen');
                    var ibDbQ58 = ibDbBox58.querySelector('.icv-db-in');
                    /* T83 (#113): Die Abfrage des DB-Elements hat dieselbe SQL-
                     * Autovervollstaendigung wie der SQL-Arbeitsbereich -
                     * Schluesselwoerter plus Tabellen aus dem eigenen Schema,
                     * Tab uebernimmt. */
                    ok(ibDbQ58.__icAc === true, 'T83 die SQL-Abfrage des DB-Elements ist an die Autovervollstaendigung angeschlossen (Editor)');
                    ok(String(ibDbQ58.getAttribute('data-tables') || '').indexOf('schueler') !== -1, 'T83b die Tabellen des Schemas stehen als Vorschlagsquelle am Feld');
                    ibDbQ58.value = 'SEL';
                    ibDbQ58.selectionStart = 3;
                    ibDbQ58.selectionEnd = 3;
                    ibDbQ58.dispatch('input');
                    var ac83 = ibDbBox58.querySelector('.ac-item');
                    ok(ac83 !== null && ac83.textOf().indexOf('SELECT') !== -1, 'T83c SQL-Schluesselwoerter erscheinen als Vorschlaege');
                    ibDbQ58.dispatch('keydown', { key: 'Tab' });
                    ok(String(ibDbQ58.value).indexOf('SELECT') === 0, 'T83d Tab uebernimmt den SQL-Vorschlag');
                    ibDbQ58.value = 'SELECT name, punkte FROM schueler WHERE punkte > 10';
                    ibDbQ58.dispatch('input');
                    ibDbBox58.querySelector('.icv-db-run').dispatch('click');
                    var ibDbOut58 = ibDbBox58.querySelector('.icv-db-out');
                    ok(ibDbOut58.innerHTML.indexOf('<table class="icv-db-table">') !== -1 && ibDbOut58.innerHTML.indexOf('<th>name</th>') !== -1 && ibDbOut58.innerHTML.indexOf('<th>punkte</th>') !== -1, 'T58d DB-Ausgabe ist eine echte HTML-Tabelle mit Kopfzeile');
                    ok(ibDbOut58.innerHTML.indexOf('Anna') !== -1 && ibDbOut58.innerHTML.indexOf('Ben') === -1 && ibDbOut58.innerHTML.indexOf('1 Zeile') !== -1, 'T58d2 WHERE punkte > 10 liefert nur Anna (1 Zeile)');
                    ibDbQ58.value = 'SELECT * FROM schueler ORDER BY punkte ASC';
                    ibDbQ58.dispatch('input');
                    ibDbBox58.querySelector('.icv-db-run').dispatch('click');
                    ok(ibDbOut58.innerHTML.indexOf('Ben') !== -1 && ibDbOut58.innerHTML.indexOf('Ben') < ibDbOut58.innerHTML.indexOf('Anna'), 'T58e ORDER BY punkte ASC sortiert Ben (9) vor Anna (12)');
                    ibDbQ58.value = "SELECT name FROM schueler WHERE name LIKE 'A%'";
                    ibDbQ58.dispatch('input');
                    ibDbBox58.querySelector('.icv-db-run').dispatch('click');
                    ok(ibDbOut58.innerHTML.indexOf('Anna') !== -1 && ibDbOut58.innerHTML.indexOf('Ben') === -1, 'T58f LIKE A% findet Anna');
                    var cv58 = ibCollect();
                    var t58 = cv58 && cv58.items ? cv58.items.filter(function (it) { return it.kind === 'db'; })[0] : null;
                    ok(t58 && t58.dbseed.indexOf('CREATE TABLE schueler') !== -1 && t58.dbq.indexOf('LIKE') !== -1, 'T58g Seed und Abfrage werden serialisiert');
                    /* Mehrzeilige VALUES (#88): jede Klammergruppe ist eine
                     * Zeile, ohne Klammerreste in der Ausgabe. */
                    ibDbSeed58.value = "CREATE TABLE schueler (name TEXT, punkte INT); INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 9)";
                    ibDbSeed58.dispatch('input');
                    ibDbQ58.value = 'SELECT * FROM schueler';
                    ibDbQ58.dispatch('input');
                    ibDbBox58.querySelector('.icv-db-run').dispatch('click');
                    ok(ibDbOut58.innerHTML.indexOf('Anna') !== -1 && ibDbOut58.innerHTML.indexOf('Ben') !== -1 && ibDbOut58.innerHTML.indexOf(')') === -1, 'T58h mehrzeilige VALUES liefern saubere Zeilen ohne Klammerreste');
                    ok(ibDbOut58.innerHTML.indexOf('2 Zeilen') !== -1, 'T58i beide Zeilen der mehrzeiligen VALUES kommen an');
                  }
                }

                /* T59 (#77): Diagramm-Element (Mermaid): Code links, Diagramm
                 * rechts, live beim Tippen, Doppelklick auf die Vorschau
                 * springt zurueck in den Code. */
                global.window.mermaid = { render: function (id, src) { return { svg: '<svg class="icv-dg-test">' + id + '</svg>' }; } };
                var ibDiagBtn59 = null;
                var ibBarBtns59 = ibBar.querySelectorAll('button');
                for (var ci59 = 0; ci59 < ibBarBtns59.length; ci59++) {
                  if (String(ibBarBtns59[ci59].title || '').indexOf('Diagramm') !== -1) { ibDiagBtn59 = ibBarBtns59[ci59]; }
                }
                ok(ibDiagBtn59 !== null && ibDiagBtn59.textOf() === '📊', 'T59 Diagramm-Knopf in der Leiste zeigt nur das Icon');
                if (ibDiagBtn59) {
                  ibDiagBtn59.dispatch('click');
                  var ibDiagBox59 = ibStage.querySelector('.icv-diagram');
                  ok(ibDiagBox59 !== null && ibDiagBox59.querySelector('.icv-diagram-src') !== null && ibDiagBox59.querySelector('.icv-diagram-prev') !== null, 'T59b das Diagramm-Element hat Code links und Vorschau rechts');
                  if (ibDiagBox59) {
                    var ibDiagTa59 = ibDiagBox59.querySelector('.icv-diagram-src');
                    ok(String(ibDiagTa59.value).indexOf('graph TD') !== -1, 'T59c neuer Code startet mit einer Mermaid-Vorlage');
                    /* #79: pur ist Standard (im Editor keine Vorschau), fuer den
                     * Live-Test wird auf geteilt gestellt. */
                    var ibDiagMode59 = ibDiagBox59.querySelector('.icv-diagram-mode');
                    ok(ibDiagMode59 !== null && ibDiagMode59.value === 'pur', 'T59b2 Varianten-Auswahl ist da, Standard pur (im Editor keine Vorschau)');
                    if (ibDiagMode59) {
                      ibDiagMode59.value = 'split';
                      ibDiagMode59.dispatch('change');
                    }
                    ibDiagTa59.value = 'graph LR\nCI_A --> CI_B';
                    ibDiagTa59.dispatch('input');
                    ok(String(ibDiagBox59.querySelector('.icv-diagram-prev').innerHTML).indexOf('<svg class="icv-dg-test">') === 0, 'T59d die Vorschau rendert live mit');
                    ibDiagBox59.querySelector('.icv-diagram-prev').dispatch('dblclick');
                    ok(ibDiagTa59._focused === true, 'T59e Doppelklick auf die Vorschau fokussiert den Code');
                    var cv59 = ibCollect();
                    var t59 = cv59 && cv59.items ? cv59.items.filter(function (it) { return it.kind === 'diagram'; })[0] : null;
                    ok(t59 && t59.src.indexOf('CI_A --> CI_B') !== -1, 'T59f der Diagramm-Code wird serialisiert');
                    ok(t59 && t59.dmode === 'split', 'T59f2 die Variante geteilt wird serialisiert');
                    /* Lesemodus: div.icv-diagram-ro[data-src] zeichnet einmalig
                     * beim DOMContentLoaded-Lauf. */
                    var ibRoBox59 = new FakeEl('div');
                    ibRoBox59.className = 'icv-item icv-diagram icv-diagram-ro';
                    ibRoBox59.setAttribute('data-src', 'graph TD\nRO_A[RO_ZIEL]');
                    var ibRoPrev59 = new FakeEl('div');
                    ibRoPrev59.className = 'icv-diagram-prev';
                    ibRoBox59.appendChild(ibRoPrev59);
                    ibDoc.body.appendChild(ibRoBox59);
                    ibDoc._fire('DOMContentLoaded', { type: 'DOMContentLoaded' });
                    ok(String(ibRoPrev59.innerHTML).indexOf('<svg class="icv-dg-test">') === 0, 'T59g im Lesemodus zeichnet die fertige Diagramm-Box ohne Editor');
                    delete global.window.mermaid;
                    var ibOffPrev59 = new FakeEl('div');
                    window.IC_RENDER_DIAGRAM(ibOffPrev59, 'graph TD\nX --> Y');
                    ok(ibOffPrev59.textContent.indexOf('graph TD') !== -1 && ibOffPrev59.getAttribute('data-mermaid-missing') === '1', 'T59h ohne Mermaid bleibt der Quelltext als Fallback lesbar');
                  }
                }

                /* T60 (#78): Die Quelltext-Fenster von Diagramm, Code und DB
                 * sind frei klickbar: Die Elementflaeche ist kein Verschiebe-
                 * Griff mehr, preventDefault (blockierte den Textarea-Fokus)
                 * wird nicht mehr gerufen. Verschieben geht ueber die Leiste. */
                var pd60 = false;
                ibDiagBox59.dispatch('pointerdown', { clientX: 5, clientY: 5, preventDefault: function () { pd60 = true; } });
                ok(pd60 === false, 'T60 Diagramm-Element ruft preventDefault nicht mehr: Quelltext laesst sich anklicken');
                var ibCodeBox60 = ibStage.querySelector('.icv-code');
                var pdCode60 = false;
                if (ibCodeBox60) { ibCodeBox60.dispatch('pointerdown', { clientX: 5, clientY: 5, preventDefault: function () { pdCode60 = true; } }); }
                ok(pdCode60 === false, 'T60b Code-Fenster: Flaeche blockiert das Anklicken der Textarea nicht mehr');
                var ibDbBox60 = ibStage.querySelector('.icv-db');
                var pdDb60 = false;
                if (ibDbBox60) { ibDbBox60.dispatch('pointerdown', { clientX: 5, clientY: 5, preventDefault: function () { pdDb60 = true; } }); }
                ok(pdDb60 === false, 'T60c DB-Element: Schema-Feld bleibt frei klickbar');
                ok(ibDiagBox59.querySelector('.icv-move') !== null, 'T60d Verschieben bleibt: Jedes Element hat seine Leiste oben');

                /* T61 (#82): Seitenleiste komplett einklappbar: Der Knopf
                 * schaltet die Leiste, der Zustand bleibt gespeichert. */
                ok(typeof window.IC_IBOOK_NAVFOLD === 'function', 'T61 app.js stellt die Einklapp-Logik bereit');
                var ibStore61 = {};
                global.localStorage = {
                  getItem: function (k) { return k in ibStore61 ? ibStore61[k] : null; },
                  setItem: function (k, v) { ibStore61[k] = v; }
                };
                var ibAside61 = new FakeEl('aside');
                ibAside61.className = 'ibook-pagenav';
                var ibFoldBtn61 = new FakeEl('button');
                window.IC_IBOOK_NAVFOLD(ibAside61, ibFoldBtn61);
                ok(!ibFoldBtn61.classList.contains('ibook-navtoggle-off') && !ibAside61.classList.contains('ibook-pagenav-off'), 'T61b Standard ist aufgeklappt');
                ibFoldBtn61.dispatch('click');
                ok(ibAside61.classList.contains('ibook-pagenav-off') === true && ibFoldBtn61.classList.contains('ibook-navtoggle-off') === true && ibFoldBtn61.getAttribute('aria-expanded') === 'false', 'T61c Klick klappt die komplette Leiste ein');
                ok(ibStore61['ic-ibook-navfold'] === '1', 'T61d der Zustand wird im Browser gespeichert');
                ibFoldBtn61.dispatch('click');
                ok(ibAside61.classList.contains('ibook-pagenav-off') === false && ibFoldBtn61.getAttribute('aria-expanded') === 'true', 'T61e zweiter Klick klappt wieder auf');
                global.localStorage = { getItem: function () { return '1'; }, setItem: function () {} };
                var ibAside61b = new FakeEl('aside');
                var ibFoldBtn61b = new FakeEl('button');
                window.IC_IBOOK_NAVFOLD(ibAside61b, ibFoldBtn61b);
                ok(ibAside61b.classList.contains('ibook-pagenav-off') === true && ibFoldBtn61b.classList.contains('ibook-navtoggle-off') === true, 'T61f gespeicherter eingeklappter Zustand wird beim Laden wiederhergestellt');
                global.localStorage = { getItem: function () { return null; }, setItem: function () {} };

                /* T63 (#79): Bibliothek am Diagramm-Element: Auswahl uebernimmt
                 * den Mermaid-Code, pur bleibt pur (kein Rendern im Editor). */
                global.window.mermaid = { render: function (id) { return { svg: '<svg class="icv-dg-t63">' + id + '</svg>' }; } };
                ibDiagBtn59.dispatch('click');
                var ibDiagBoxes63 = ibStage.querySelectorAll('.icv-diagram');
                var ibDiagBox63 = ibDiagBoxes63[ibDiagBoxes63.length - 1];
                ok(ibDiagBox63 !== null && ibDiagBox63.querySelector('.icv-diagram-lib') !== null, 'T63 neues Diagramm-Element bietet die eigene Bibliothek an');
                if (ibDiagBox63) {
                  var ibDiagWrap63 = ibDiagBox63.querySelector('.icv-diagram-wrap');
                  ok(ibDiagWrap63.classList.contains('icv-diagram-noprev') === true, 'T63b pur (Standard): im Editor steht nur der Quelltext, keine Vorschau');
                  var ibDiagLib63 = ibDiagBox63.querySelector('.icv-diagram-lib');
                  ibDiagLib63.value = '5';
                  ibDiagLib63.dispatch('change');
                  ok(String(ibDiagBox63.querySelector('.icv-diagram-src').value).indexOf('classDiagram') !== -1, 'T63c die Bibliotheks-Auswahl uebernimmt den Mermaid-Code');
                  ok(ibDiagLib63.value === '', 'T63d das Bibliotheks-Dropdown springt zurueck auf die neutrale Auswahl');
                  var cv63 = ibCollect();
                  var t63 = cv63 && cv63.items ? cv63.items.filter(function (it) { return it.kind === 'diagram'; })[cv63.items.filter(function (it) { return it.kind === 'diagram'; }).length - 1] : null;
                  ok(t63 && String(t63.src).indexOf('Auto <|-- BMW') !== -1 && t63.dmode === undefined, 'T63e der uebernommene Code wird serialisiert, Variante bleibt pur');
                  /* Lesemodus geteilt (#79): die Split-Box zeichnet auch, der
                   * Balken zwischen Code und Diagramm laesst sich ziehen und
                   * begrenzt beide Fenster (nichts wird unbedienbar). */
                  var ibRoSplit63 = new FakeEl('div');
                  ibRoSplit63.className = 'icv-item icv-diagram icv-diagram-split-ro';
                  ibRoSplit63.setAttribute('data-src', 'graph TD\nS63[SPLIT]');
                  var ibRoSplitPrev63 = new FakeEl('div');
                  ibRoSplitPrev63.className = 'icv-diagram-prev';
                  ibRoSplit63.appendChild(ibRoSplitPrev63);
                  ibDoc.body.appendChild(ibRoSplit63);
                  ibDoc._fire('DOMContentLoaded', { type: 'DOMContentLoaded' });
                  ok(String(ibRoSplitPrev63.innerHTML).indexOf('<svg class="icv-dg-t63">') === 0, 'T63g die geteilte Lesemodus-Box zeichnet ihr Diagramm');
                  var ibSplitWrap63 = new FakeEl('div');
                  ibSplitWrap63.className = 'icv-diagram-split';
                  var ibSplitCode63 = new FakeEl('textarea');
                  ibSplitCode63.className = 'icv-diagram-code';
                  ibSplitCode63.value = 'graph TD\nS63[SPLIT]';
                  var ibSplitBar63 = new FakeEl('div');
                  ibSplitBar63.className = 'icv-diagram-split-bar';
                  var ibSplitPrev63 = new FakeEl('div');
                  ibSplitPrev63.className = 'icv-diagram-prev';
                  ibSplitWrap63.appendChild(ibSplitCode63);
                  ibSplitWrap63.appendChild(ibSplitBar63);
                  ibSplitWrap63.appendChild(ibSplitPrev63);
                  ibDoc.body.appendChild(ibSplitWrap63);
                  ibSplitBar63.dispatch('pointerdown', { clientX: 8, clientY: 8 });
                  ibDoc._fire('pointermove', { clientX: 218, clientY: 8 });
                  ok(String(ibSplitCode63.style.width) === '50%', 'T63h der Balken teilt Code und Diagramm nach Zug');
                  ibDoc._fire('pointermove', { clientX: 5000, clientY: 8 });
                  ok(String(ibSplitCode63.style.width) === '85%', 'T63i der Balken begrenzt: Code hoechstens 85%, das Diagramm bleibt bedienbar');
                  ibDoc._fire('pointermove', { clientX: -5000, clientY: 8 });
                  ok(String(ibSplitCode63.style.width) === '15%', 'T63j nach unten bleibt dem Code mindestens 15% - nichts geht verloren');
                  ibDoc._fire('pointerup', {});
                  /* #85: Der Quelltext bleibt im Lesemodus bearbeitbar,
                   * jede Aenderung zeichnet das Diagramm sofort neu. */
                  global.window.mermaid = { render: function (id, src) { return { svg: '<svg class="icv-dg-t63">' + id + '|' + String(src) + '</svg>' }; } };
                  ibSplitCode63.value = 'graph TD\nS63[NEU]';
                  ibSplitCode63.dispatch('input');
                  ibSplitCode63.value = 'graph TD\nS63[NOCHNEU]';
                  ibSplitCode63.dispatch('input');
                  ok(String(ibSplitPrev63.innerHTML).indexOf('S63[NOCHNEU]') !== -1, 'T63k geteilt im Lesemodus: Tippen am Quelltext zeichnet das Diagramm live neu');
                  ibDoc._fire('pointerup', {});
                  delete global.window.mermaid;
                }

                /* T64 (#80): Vorhandene Datenbanken der SQL-Werkstatt fuellen
                 * das DB-Element: Schema und Beispieldaten landen im Seed-Feld,
                 * Abfragen laufen damit auf den echten Vorlagen-Tabellen. */
                ibDbBtn58.dispatch('click');
                var ibDbBoxes64 = ibStage.querySelectorAll('.icv-db');
                var ibDbBox64 = ibDbBoxes64[ibDbBoxes64.length - 1];
                ok(ibDbBox64 !== null && ibDbBox64.querySelector('.icv-db-libsel') !== null, 'T64 neues DB-Element bietet vorhandene Datenbanken an');
                if (ibDbBox64) {
                  var ibDbLib64 = ibDbBox64.querySelector('.icv-db-libsel');
                  ibDbLib64.value = 'shop';
                  ibDbLib64.dispatch('change');
                  var ibDbSeedTa64 = ibDbBox64.querySelector('.icv-db-seed-ta');
                  ok(String(ibDbSeedTa64.value).indexOf('CREATE TABLE artikel') !== -1 && ibDbSeedTa64.value.indexOf('Kaugummi') !== -1, 'T64b die Vorlage laedt Schema und Beispieldaten ins Feld');
                  ok(ibDbBox64.querySelector('.icv-db-schema').textContent.indexOf('artikel') !== -1, 'T64c die Schema-Anzeige aktualisiert sich aus der Vorlage');
                  ok(ibDbLib64.value === '', 'T64d das Vorlagen-Dropdown springt zurueck');
                  var ibDbQ64 = ibDbBox64.querySelector('.icv-db-in');
                  ibDbQ64.value = 'SELECT name FROM artikel WHERE preis < 5';
                  ibDbQ64.dispatch('input');
                  ibDbBox64.querySelector('.icv-db-run').dispatch('click');
                  var ibDbOut64 = ibDbBox64.querySelector('.icv-db-out');
                  ok(ibDbOut64.innerHTML.indexOf('<table class="icv-db-table">') !== -1 && ibDbOut64.innerHTML.indexOf('<th>name</th>') !== -1 && ibDbOut64.innerHTML.indexOf('Kaugummi') !== -1, 'T64e Abfragen laufen gegen die Tabellen der Vorlage (als echte Tabelle)');
                  var cv64 = ibCollect();
                  var t64 = cv64 && cv64.items ? cv64.items.filter(function (it) { return it.kind === 'db'; })[cv64.items.filter(function (it) { return it.kind === 'db'; }).length - 1] : null;
                  ok(t64 && t64.dbseed.indexOf('Kaugummi') !== -1, 'T64f der Vorlagen-Seed wird serialisiert');
                }

                /* T65 (#87): Klassen aus Projekten erscheinen als Karte ueber
                 * dem Code, nicht im Quelltext. Karte klappt Attribute und
                 * Methoden auf, das x nimmt sie wieder mit. */
                ibCodeBtn57.dispatch('click');
                var ibCodeBoxes65 = ibStage.querySelectorAll('.icv-code');
                var ibCodeBox65 = ibCodeBoxes65[ibCodeBoxes65.length - 1];
                var ibProj65 = ibCodeBox65 ? ibCodeBox65.querySelector('.icv-code-projsel') : null;
                ok(ibProj65 !== null, 'T65 das Code-Fenster bietet Klassen aus Java-Projekten an');
                if (ibProj65) {
                  var ibCodeTa65 = ibCodeBox65.querySelector('.icv-code-ta');
                  ibCodeTa65.value = 'System.out.println("Los");';
                  ibCodeTa65.selectionStart = 23;
                  ibCodeTa65.dispatch('input');
                  ibProj65.value = '0';
                  ibProj65.dispatch('change');
                  var ibCard65 = ibCodeBox65.querySelector('.icv-code-card');
                  ok(ibCard65 !== null, 'T65b die Klassen-Auswahl legt eine Karte ans Fenster');
                  ok(String(ibCodeTa65.value).indexOf('class Auto') === -1 && ibCodeTa65.value.indexOf('System.out.println("Los");') !== -1, 'T65c der Klassen-Quelltext bleibt draussen, der eigene Code unangetastet');
                  ok(ibCard65 && ibCodeBox65.querySelector('.icv-code-card-name') && ibCodeBox65.querySelector('.icv-code-card-name').textOf() === 'Auto', 'T65d die Karte zeigt nur den Klassennamen');
                  ok(ibProj65.value === '', 'T65e das Projekt-Dropdown springt zurueck auf die neutrale Auswahl');
                  var ibCardName65 = ibCodeBox65.querySelector('.icv-code-card-name');
                  ok(ibCard65.classList.contains('open') === false, 'T65f die Karte startet zugeklappt');
                  ibCardName65.dispatch('click');
                  ok(ibCard65.classList.contains('open') === true, 'T65g Klick auf den Namen klappt Attribute und Methoden auf');
                  ok(ibCard65.querySelector('.icv-code-card-field') !== null && ibCard65.querySelector('.icv-code-card-field').textOf().indexOf('name : String') !== -1, 'T65h das Attribut steht in den Details');
                  ok(ibCard65.querySelector('.icv-code-card-method') !== null && ibCard65.querySelector('.icv-code-card-method').textOf().indexOf('fahren()') !== -1, 'T65i die Methode steht in den Details');
                  ibCardName65.dispatch('click');
                  ok(ibCard65.classList.contains('open') === false, 'T65j der zweite Klappt die Karte wieder zu');
                  var cv65 = ibCollect();
                  var t65 = cv65 && cv65.items ? cv65.items.filter(function (it) { return it.kind === 'code'; })[cv65.items.filter(function (it) { return it.kind === 'code'; }).length - 1] : null;
                  ok(t65 && t65.cls && t65.cls.length === 1 && t65.cls[0].name === 'Auto.java' && t65.cls[0].project === 'Fahrzeuge' && String(t65.cls[0].code).indexOf('class Auto') !== -1, 'T65k die Karte wird mit Name, Projekt und Quelltext serialisiert');
                  ibCard65.querySelector('.icv-code-card-x').dispatch('click');
                  ok(ibCodeBox65.querySelector('.icv-code-card') === null, 'T65l das x entfernt die Karte wieder');
                  var cv65b = ibCollect();
                  var t65b = cv65b && cv65b.items ? cv65b.items.filter(function (it) { return it.kind === 'code'; })[cv65b.items.filter(function (it) { return it.kind === 'code'; }).length - 1] : null;
                  ok(t65b && t65b.cls === undefined, 'T65m ohne Karte verschwindet die Klasse aus der Serialisierung');
                  /* T66 (#87): Beim Ausfuehren reist die importierte Klasse als
                   * eigene Datei mit zur Java-VM, der Hauptcode bleibt Main.java. */
                  ibProj65.value = '0';
                  ibProj65.dispatch('change');
                  global.window.InfoCraftJavaVM = {
                    run: function (files) {
                      ok(files && typeof files === 'object' && typeof files['Main.java'] === 'string' && files['Main.java'].indexOf('System.out.println("Los");') !== -1, 'T66 der Hauptcode geht als Main.java an die Java-VM');
                      ok(typeof files['Auto.java'] === 'string' && files['Auto.java'].indexOf('class Auto') !== -1 && files['Auto.java'].indexOf('void fahren') !== -1, 'T66b die importierte Klasse reist als eigene Datei Auto.java mit');
                      return { output: 'Auto faehrt los', errors: [], ok: true };
                    }
                  };
                  ibCodeBox65.querySelector('.icv-code-run').dispatch('click');
                  ok(ibCodeBox65.querySelector('.icv-code-out').textContent.indexOf('Auto faehrt los') !== -1, 'T66c die Ausgabe der Java-VM landet in der Konsole');
                  delete global.window.InfoCraftJavaVM;
                  /* T67 (#87/#95): Autovervollstaendigung an der GEMEINSAMEN
                   * Vorschlags-Engine wie im Lesemodus: Klassennamen beim
                   * Tippen, nach dem Punkt Attribute und Methoden, Tab
                   * uebernimmt, Klick fuegt ein. */
                  var ibAc67 = ibCodeBox65.querySelector('.ac-drop');
                  ibCodeTa65.value = 'Au';
                  ibCodeTa65.selectionStart = 2;
                  ibCodeTa65.selectionEnd = 2;
                  ibCodeTa65.dispatch('input');
                  var acOpen67 = ibAc67 && ibAc67.style.display !== 'none';
                  var acText67 = '';
                  if (ibAc67) {
                    for (var ai0 = 0; ai0 < ibAc67.children.length; ai0++) { acText67 += ibAc67.children[ai0].textOf() + '|'; }
                  }
                  ok(acOpen67 === true && acText67.indexOf('Auto') !== -1, 'T67 tippen oeffnet die Vorschlaege mit dem Klassennamen');
                  ibCodeTa65.dispatch('keydown', { key: 'Tab' });
                  ok(ibCodeTa65.value === 'Auto', 'T67b Tab uebernimmt den Vorschlag ins Code-Feld');
                  ok(ibAc67.style.display === 'none', 'T67c nach der Uebernahme schliessen die Vorschlaege');
                  ibCodeTa65.value = 'Auto.fa';
                  ibCodeTa65.selectionStart = 7;
                  ibCodeTa65.selectionEnd = 7;
                  ibCodeTa65.dispatch('input');
                  var acLabels67 = [];
                  for (var ai67 = 0; ai67 < ibAc67.children.length; ai67++) { acLabels67.push(ibAc67.children[ai67].textOf()); }
                  ok(acLabels67.join('|').indexOf('fahren()') !== -1, 'T67d nach dem Punkt schlagen Attribute und Methoden vor');
                  var acPick67 = null;
                  for (var ai2 = 0; ai2 < ibAc67.children.length; ai2++) {
                    if (ibAc67.children[ai2].textOf() === 'fahren()') { acPick67 = ibAc67.children[ai2]; }
                  }
                  acPick67.dispatch('click');
                  ok(ibCodeTa65.value === 'Auto.fahren(', 'T67e ein Klick auf den Vorschlag fuegt die Methode ein');
                  var cv67 = ibCollect();
                  var t67 = cv67 && cv67.items ? cv67.items.filter(function (it) { return it.kind === 'code'; })[cv67.items.filter(function (it) { return it.kind === 'code'; }).length - 1] : null;
                  ok(t67 && t67.code === 'Auto.fahren(', 'T67f der ergaenzte Quelltext wird serialisiert');
                }

                /* T69 (#93): Editor-Paritaet am Code-Fenster - Zeilennummern
                 * und Klammer-Automatik wie in der Werkstatt. */
                var NLt = String.fromCharCode(10);
                var ibEdWrap69 = ibCodeBox65 ? ibCodeBox65.querySelector('.icv-edwrap') : null;
                ok(ibEdWrap69 !== null, 'T69 das Code-Fenster im Editor traegt den Zeilennummern-Gutter (icv-edwrap)');
                if (ibEdWrap69) {
                  var ibGut69 = ibEdWrap69.querySelector('.icv-gutter');
                  ok(ibGut69 !== null && ibEdWrap69.querySelector('textarea.icv-code-ta') === ibCodeTa65, 'T69b der Gutter haelt die Zeilennummern-Spalte, das Code-Feld steckt im Wrap');
                  ibCodeTa65.value = 'int a = 1;' + NLt + 'int b = 2;' + NLt + 'int c = 3;';
                  ibCodeTa65.selectionStart = 32;
                  ibCodeTa65.selectionEnd = 32;
                  ibCodeTa65.dispatch('input');
                  var ibGutCells69 = ibGut69 && ibGut69.querySelector('.icv-gutter-inner') ? String(ibGut69.querySelector('.icv-gutter-inner').innerHTML).split('icv-gutter-cell').length - 1 : 0;
                  ok(ibGutCells69 === 3, 'T69c drei Zeilen Quelltext geben drei Zeilennummern');
                  ibCodeTa65.value = '';
                  ibCodeTa65.selectionStart = 0;
                  ibCodeTa65.selectionEnd = 0;
                  ibCodeTa65.dispatch('keydown', { key: '{' });
                  ok(ibCodeTa65.value === '{}' && ibCodeTa65.selectionStart === 1, 'T69d die oeffnende Klammer schliesst sofort mit }');
                  ibCodeTa65.value = 'System.out.println("Hi"';
                  ibCodeTa65.selectionStart = 23;
                  ibCodeTa65.selectionEnd = 23;
                  ibCodeTa65.dispatch('keydown', { key: '(' });
                  ok(ibCodeTa65.value === 'System.out.println("Hi"()', 'T69e auch ( schliesst sofort mit dem Gegenstueck');
                  ibCodeTa65.value = 'Auto.fahren()';
                  ibCodeTa65.selectionStart = 12;
                  ibCodeTa65.selectionEnd = 12;
                  ibCodeTa65.dispatch('keydown', { key: ')' });
                  ok(ibCodeTa65.value === 'Auto.fahren()' && ibCodeTa65.selectionStart === 13, 'T69f ein getipptes ) ueberspringt das vorhandene statt es zu verdoppeln');
                  /* #94: Enter direkt nach einer oeffnenden Klammer legt
                   * zwei eingerueckte Zeilen an, darunter die schliessende. */
                  ibCodeTa65.value = '{}';
                  ibCodeTa65.selectionStart = 1;
                  ibCodeTa65.selectionEnd = 1;
                  ibCodeTa65.dispatch('keydown', { key: 'Enter' });
                  ok(ibCodeTa65.value === '{' + NLt + '  ' + NLt + '}' && ibCodeTa65.selectionStart === 4, 'T115 Enter nach einer oeffnenden Klammer legt zwei eingerueckte Zeilen an (#94)');
                }

                /* T71 (#93): Mermaid-Vorschlaege am Diagramm-Quelltext im
                 * Editor, dazu Gutter und Klammer-Automatik. */
                ibDiagBtn59.dispatch('click');
                var ibDiagBoxes71 = ibStage.querySelectorAll('.icv-diagram');
                var ibDiagBox71 = ibDiagBoxes71[ibDiagBoxes71.length - 1];
                var ibDiagTa71 = ibDiagBox71 ? ibDiagBox71.querySelector('textarea.icv-diagram-src') : null;
                var ibDiagWrap71 = (ibDiagTa71 && ibDiagTa71.parentNode && ibDiagTa71.parentNode.classList && ibDiagTa71.parentNode.classList.contains('icv-edwrap')) ? ibDiagTa71.parentNode : null;
                ok(ibDiagWrap71 !== null && ibDiagWrap71.querySelector('.ac-drop') !== null, 'T71 der Diagramm-Quelltext im Editor traegt den Gutter-Wrap mit Vorschlagsbox darunter');
                if (ibDiagWrap71) {
                  var ibAcDrop71 = ibDiagWrap71.querySelector('.ac-drop');
                  ibDiagTa71.value = 'gr';
                  ibDiagTa71.selectionStart = 2;
                  ibDiagTa71.selectionEnd = 2;
                  ibDiagTa71.dispatch('input');
                  var ibAc71Labels = [];
                  for (var ai71 = 0; ai71 < ibAcDrop71.children.length; ai71++) { ibAc71Labels.push(ibAcDrop71.children[ai71].textOf()); }
                  ok(ibAc71Labels.join('|').indexOf('graph TD') !== -1, 'T71b tippen schlaegt Mermaid-Schluesselwoerter vor (graph TD)');
                  ibDiagTa71.dispatch('keydown', { key: 'Tab' });
                  ok(ibDiagTa71.value === 'graph TD', 'T71c Tab uebernimmt den Vorschlag in den Quelltext');
                  ibDiagTa71.value = 'graph TD' + NLt + 'A --';
                  ibDiagTa71.selectionStart = 13;
                  ibDiagTa71.selectionEnd = 13;
                  ibDiagTa71.dispatch('input');
                  var ibAc71LabelsB = [];
                  for (var bi71 = 0; bi71 < ibAcDrop71.children.length; bi71++) { ibAc71LabelsB.push(ibAcDrop71.children[bi71].textOf()); }
                  ok(ibAc71LabelsB.join('|').indexOf('-->') !== -1, 'T71d nach dem Minus schlagen die Pfeile vor (-->)');
                  ibDiagTa71.dispatch('keydown', { key: 'Enter' });
                  ok(ibDiagTa71.value === 'graph TD' + NLt + 'A -->', 'T71e Enter fuegt den Pfeil ein');
                  ibDiagTa71.value = 'graph TD' + NLt + 'A --> B';
                  ibDiagTa71.selectionStart = 16;
                  ibDiagTa71.selectionEnd = 16;
                  ibDiagTa71.dispatch('keydown', { key: '{' });
                  ok(ibDiagTa71.value === 'graph TD' + NLt + 'A --> B{}', 'T71f die Klammer-Automatik schliesst { auch am Diagramm-Quelltext');
                  /* #96: ganze Mermaid-Snippets stehen in der Vervoll-
                   * staendigung, hier subgraph aus dem erDiagram. */
                  ibDiagTa71.value = 'sub';
                  ibDiagTa71.selectionStart = 3;
                  ibDiagTa71.selectionEnd = 3;
                  ibDiagTa71.dispatch('input');
                  var ibAc96Labels = [];
                  for (var ai96 = 0; ai96 < ibAcDrop71.children.length; ai96++) { ibAc96Labels.push(ibAcDrop71.children[ai96].textOf()); }
                  ok(ibAc96Labels.join('|').indexOf('subgraph Titel') !== -1, 'T115b die Mermaid-Vervollstaendigung schlaegt ganze Snippets vor (#96)');
                }

                /* T72/T73 (#92/#93): Lesemodus-Paritaet. Der Server rendert das
                 * Code-Fenster als editierbares Feld (#92), app.js legt beim
                 * Laden Gutter, Klammer-Automatik und Vorschlaege darauf; das
                 * geteilte Diagramm rendert live weiter. */
                var roPath72 = require.resolve('../public/assets/js/app.js');
                delete require.cache[roPath72];
                var roDoc = new FakeDoc();
                roDoc.documentElement = new FakeEl('html');
                roDoc.body.setAttribute('data-csrf', 'csrf-t72');
                var roCodeBox = new FakeEl('div');
                roCodeBox.className = 'icv-item icv-code';
                var roCodeTa = new FakeEl('textarea');
                roCodeTa.className = 'mono icv-code-src';
                roCodeTa.value = 'class Main {' + NLt + '  public static void main(String[] args) {' + NLt + '    Auto a = new Auto();' + NLt + '  }' + NLt + '}';
                var roRunBtn = new FakeEl('button');
                roRunBtn.className = 'icv-code-run';
                roRunBtn.setAttribute('data-lang', 'java');
                roRunBtn.setAttribute('data-clsfiles', JSON.stringify([{ name: 'Auto.java', code: 'class Auto {' + NLt + '  String name;' + NLt + '  void fahren() {}' + NLt + '}' + NLt }]));
                /* #99: Klassen-Chip im Lesemodus, app.js baut die Karte dazu. */
                var roChip = new FakeEl('div');
                roChip.className = 'icv-code-card icv-code-card-ro';
                roChip.setAttribute('data-clsname', 'Auto');
                roChip.setAttribute('title', 'Auto.java');
                roChip.appendChild(new FakeEl('text'));
                roChip.children[0].textContent = 'Auto';
                roCodeBox.appendChild(roChip);
                var roOut = new FakeEl('pre');
                roOut.className = 'icv-code-out';
                roCodeBox.appendChild(roCodeTa);
                roCodeBox.appendChild(roRunBtn);
                roCodeBox.appendChild(roOut);
                roDoc.body.appendChild(roCodeBox);
                var roSplitRo = new FakeEl('div');
                roSplitRo.className = 'icv-item icv-diagram icv-diagram-split-ro';
                roSplitRo.setAttribute('data-src', 'graph TD' + NLt + 'A --> B');
                var roSplitWrap = new FakeEl('div');
                roSplitWrap.className = 'icv-diagram-split';
                var roSplitTa = new FakeEl('textarea');
                roSplitTa.className = 'mono icv-diagram-code';
                roSplitTa.value = 'graph TD' + NLt + 'A --> B';
                var roSplitBar = new FakeEl('div');
                roSplitBar.className = 'icv-diagram-split-bar';
                var roSplitPrev = new FakeEl('div');
                roSplitPrev.className = 'icv-diagram-prev';
                roSplitWrap.appendChild(roSplitTa);
                roSplitWrap.appendChild(roSplitBar);
                roSplitWrap.appendChild(roSplitPrev);
                roSplitRo.appendChild(roSplitWrap);
                roDoc.body.appendChild(roSplitRo);
                var savedDocR = global.document;
                global.document = roDoc;
                require(roPath72);
                var roWrap72 = (roCodeTa.parentNode && roCodeTa.parentNode.classList && roCodeTa.parentNode.classList.contains('icv-edwrap')) ? roCodeTa.parentNode : null;
                ok(roWrap72 !== null && roWrap72.querySelector('.icv-gutter') !== null, 'T72 das Lesemodus-Code-Fenster (#92) traegt den Zeilennummern-Gutter');
                if (roWrap72) {
                  var roGutCells72 = String(roWrap72.querySelector('.icv-gutter').querySelector('.icv-gutter-inner').innerHTML).split('icv-gutter-cell').length - 1;
                  ok(roGutCells72 === 5, 'T72b fuenf Zeilen Quelltext geben fuenf Zeilennummern');
                  roCodeTa.value = '';
                  roCodeTa.selectionStart = 0;
                  roCodeTa.selectionEnd = 0;
                  roCodeTa.dispatch('keydown', { key: '{' });
                  ok(roCodeTa.value === '{}', 'T72c die Klammer-Automatik schliesst { auch im Lesemodus');
                  var roDrop72 = roWrap72.querySelector('.ac-drop');
                  roCodeTa.value = 'Aut';
                  roCodeTa.selectionStart = 3;
                  roCodeTa.selectionEnd = 3;
                  roCodeTa.dispatch('input');
                  var roLabels72 = [];
                  for (var ri72 = 0; roDrop72 && ri72 < roDrop72.children.length; ri72++) { roLabels72.push(roDrop72.children[ri72].textOf()); }
                  ok(roLabels72.join('|').indexOf('Auto') !== -1, 'T72d tippen schlaegt die importierte Klasse Auto vor');
                  roCodeTa.dispatch('keydown', { key: 'Tab' });
                  ok(roCodeTa.value === 'Auto', 'T72e Tab uebernimmt den Klassennamen');
                  roCodeTa.value = 'Auto.fa';
                  roCodeTa.selectionStart = 7;
                  roCodeTa.selectionEnd = 7;
                  roCodeTa.dispatch('input');
                  var roLabels72b = [];
                  for (var rj72 = 0; roDrop72 && rj72 < roDrop72.children.length; rj72++) { roLabels72b.push(roDrop72.children[rj72].textOf()); }
                  ok(roLabels72b.join('|').indexOf('fahren()') !== -1, 'T72f nach dem Punkt schlagen Attribute und Methoden der importierten Klasse vor');
                  roCodeTa.dispatch('keydown', { key: 'Enter' });
                  ok(roCodeTa.value === 'Auto.fahren(', 'T72g Enter fuegt die Methode ein');
                  global.window.InfoCraftJavaVM = {
                    run: function (files) {
                      ok(files && typeof files === 'object' && typeof files['Main.java'] === 'string' && files['Main.java'].indexOf('Auto.fahren(') !== -1, 'T72h der im Lesemodus getippte Quelltext geht als Main.java an die Java-VM');
                      ok(typeof files['Auto.java'] === 'string' && files['Auto.java'].indexOf('class Auto') !== -1, 'T72i die importierte Klasse reist als Auto.java mit');
                      return { output: 'Auto faehrt', errors: [], ok: true };
                    }
                  };
                  roRunBtn.dispatch('click');
                  ok(roOut.textContent.indexOf('Auto faehrt') !== -1, 'T72j die Ausgabe der Java-VM landet in der Konsole des Lesemodus-Fensters');
                  delete global.window.InfoCraftJavaVM;
                }
                var roWrap73 = (roSplitTa.parentNode && roSplitTa.parentNode.classList && roSplitTa.parentNode.classList.contains('icv-edwrap')) ? roSplitTa.parentNode : null;
                ok(roWrap73 !== null && roWrap73.querySelector('.ac-drop') !== null, 'T73 der Quelltext im geteilten Lesemodus traegt Gutter und Mermaid-Vorschlagsbox');
                if (roWrap73) {
                  roSplitTa.value = 'gr';
                  roSplitTa.selectionStart = 2;
                  roSplitTa.selectionEnd = 2;
                  roSplitTa.dispatch('input');
                  roSplitTa.dispatch('keydown', { key: 'Tab' });
                  ok(roSplitTa.value === 'graph TD', 'T73b Tab uebernimmt den Mermaid-Vorschlag in den geteilten Quelltext');
                  global.window.mermaid = { render: function (id, src) { return { svg: '<svg class="icv-dg-t73">' + String(src) + '</svg>' }; } };
                  roSplitTa.value = 'graph TD' + NLt + 'A --> C';
                  roSplitTa.selectionStart = 16;
                  roSplitTa.selectionEnd = 16;
                  roSplitTa.dispatch('input');
                  ok(String(roSplitPrev.innerHTML).indexOf('A --> C') !== -1, 'T73c Tippen am Gutter-Feld zeichnet das Diagramm live neu');
                  delete global.window.mermaid;
                }
                global.document = savedDocR;

                /* T74 (#98): AltGr-Klammern. Auf deutschen Tastaturen melden
                 * { und [ sich als ctrlKey+altKey; die Klammer-Automatik
                 * darf sie nicht mehr wegfiltern, echte Strg-Kuerzel schon. */
                roCodeTa.value = '';
                roCodeTa.selectionStart = 0;
                roCodeTa.selectionEnd = 0;
                roCodeTa.dispatch('keydown', { key: '{', ctrlKey: true, altKey: true });
                ok(roCodeTa.value === '{}', 'T74 AltGr-{ (ctrlKey+altKey) schliesst die geschweifte Klammer mit');
                roCodeTa.value = '';
                roCodeTa.selectionStart = 0;
                roCodeTa.selectionEnd = 0;
                roCodeTa.dispatch('keydown', { key: '[', ctrlKey: true, altKey: true });
                ok(roCodeTa.value === '[]', 'T74b AltGr-[ schliesst die eckige Klammer mit');
                roCodeTa.value = '';
                roCodeTa.selectionStart = 0;
                roCodeTa.selectionEnd = 0;
                roCodeTa.dispatch('keydown', { key: '(', ctrlKey: true });
                ok(roCodeTa.value === '', 'T74c ein echtes Strg-Kuerzel bleibt unangetastet');

                /* T75 (#99): Klassenkarte im Lesemodus. Der Chip klappt auf
                 * und zeigt Attribute und Methoden der importierten Klasse. */
                var roDet75 = roChip.querySelector('.icv-code-card-detail');
                ok(roDet75 !== null, 'T75 der Lesemodus-Chip traegt die aufklappbare Klassenkarte');
                if (roDet75) {
                  var roTxt75 = roChip.textOf();
                  ok(roTxt75.indexOf('name : String') !== -1, 'T75b die Karte nennt das Attribut der importierten Klasse');
                  ok(roTxt75.indexOf('fahren()') !== -1, 'T75c die Karte nennt die Methode der importierten Klasse');
                  roChip.dispatch('click');
                  ok(roChip.classList.contains('open'), 'T75d ein Klick klappt die Karte im Lesemodus auf');
                }

                /* T76 (#100): Inhalt-Knopf in der Kopfzeile. Der Knopf sitzt
                 * neben dem Zurueck-Button und traegt die Beschriftung Inhalt,
                 * die Leiste verschwindet eingeklappt komplett. */
                var p76 = require.resolve('../public/assets/js/app.js');
                delete require.cache[p76];
                var doc76 = new FakeDoc();
                doc76.documentElement = new FakeEl('html');
                var aside76 = new FakeEl('aside');
                doc76.register(aside76, 'ibook-pagenav');
                var foldBtn76 = new FakeEl('button');
                foldBtn76.className = 'btn btn-ghost ibook-pagenav-fold';
                doc76.register(foldBtn76, 'ibook-pagenav-fold');
                doc76.body.appendChild(aside76);
                doc76.body.appendChild(foldBtn76);
                var savedDoc76 = global.document;
                global.document = doc76;
                require(p76);
                var tpl76 = require('fs').readFileSync(require.resolve('../templates/ibook_page.php'), 'utf8');
                ok(tpl76.indexOf('ibook-navtoggle') !== -1 && tpl76.indexOf('aria-label="Inhaltsnavigation') !== -1, 'T76 der Ausklapp-Knopf ist ein Icon-Knopf mit Beschreibung (#185)');
                aside76.classList.add('ibook-pagenav-off');
                foldBtn76.dispatch('click');
                ok(!aside76.classList.contains('ibook-pagenav-off'), 'T76b ein Klick klappt die Leiste wieder aus');
                ok(foldBtn76.classList.contains('ibook-navtoggle-off') === false && foldBtn76.getAttribute('aria-expanded') === 'true', 'T76c der Knopf-Zustand bleibt konsistent (Klasse statt Textwechsel, #185)');
                aside76.classList.add('ibook-pagenav-off');
                foldBtn76.dispatch('click');
                global.document = savedDoc76;

                /* T77 (#101): Seiten per Drag&Drop sortieren. Ziehen im selben
                 * Abschnitt sortiert das DOM und schickt die neue Reihenfolge
                 * an den Endpunkt seiten-reihenfolge. */
                var p77 = require.resolve('../public/assets/js/app.js');
                delete require.cache[p77];
                var doc77 = new FakeDoc();
                doc77.documentElement = new FakeEl('html');
                var aside77 = new FakeEl('aside');
                doc77.register(aside77, 'ibook-pagenav');
                var sec77 = new FakeEl('a');
                sec77.className = 'ibook-pagenav-section';
                var pgA = new FakeEl('a');
                pgA.className = 'ibook-pagenav-page ibook-pagenav-drag';
                pgA.setAttribute('draggable', 'true');
                pgA.setAttribute('data-page-id', '1');
                pgA.setAttribute('href', '/infobuecher/12/seite/1');
                var pgB = new FakeEl('a');
                pgB.className = 'ibook-pagenav-page ibook-pagenav-drag';
                pgB.setAttribute('draggable', 'true');
                pgB.setAttribute('data-page-id', '2');
                pgB.setAttribute('href', '/infobuecher/12/seite/2');
                var pgC = new FakeEl('a');
                pgC.className = 'ibook-pagenav-page ibook-pagenav-drag';
                pgC.setAttribute('draggable', 'true');
                pgC.setAttribute('data-page-id', '3');
                pgC.setAttribute('href', '/infobuecher/12/seite/3');
                aside77.appendChild(sec77);
                aside77.appendChild(pgA);
                aside77.appendChild(pgB);
                aside77.appendChild(pgC);
                doc77.body.appendChild(aside77);
                var dndUrl = null;
                var dndBody = null;
                var savedFetch = global.fetch;
                global.fetch = function (u, o) { dndUrl = u; dndBody = o && o.body ? String(o.body) : ''; return { catch: function () {} }; };
                var savedDoc77 = global.document;
                global.document = doc77;
                require(p77);
                pgA.dispatch('dragstart', { dataTransfer: { setData: function () {}, effectAllowed: '' } });
                ok(pgA.classList.contains('ibook-pagenav-dragging'), 'T77 die gezogene Seite markiert sich');
                pgC.dispatch('dragover', { dataTransfer: { dropEffect: '' } });
                ok(pgC.classList.contains('ibook-pagenav-dragover'), 'T77b die Zielzeile hebt sich hervor');
                pgC.dispatch('drop', { dataTransfer: { dropEffect: '' } });
                ok(aside77.children[3] === pgA, 'T77c die gezogene Seite landet hinter dem Ziel (DOM-Reihenfolge B, C, A)');
                ok(dndUrl === '/infobuecher/12/seiten-reihenfolge', 'T77d die neue Reihenfolge geht an den Sortier-Endpunkt');
                ok(dndBody !== null && dndBody.indexOf('page_ids=2%2C3%2C1') !== -1, 'T77e die Reihenfolge wandert als page_ids an den Server');
                global.fetch = savedFetch;
                global.document = savedDoc77;

                /* T78 (#102): Editor-Bausteine sind Module: clsMembers liegt
                 * genau einmal vor (IC_EDIT_TOOLS) und dient Editor, Lesemodus
                 * und Klassenkarte. */
                ok(global.window.IC_EDIT_TOOLS && typeof global.window.IC_EDIT_TOOLS.clsMembers === 'function', 'T78 IC_EDIT_TOOLS exportiert die Member-Erkennung als Baustein');
                var mem78 = global.window.IC_EDIT_TOOLS ? global.window.IC_EDIT_TOOLS.clsMembers('class Auto {' + NLt + '  String name;' + NLt + '  void fahren() {}' + NLt + '}') : null;
                ok(mem78 !== null && mem78.fields.length === 1 && mem78.fields[0].name === 'name', 'T78b der Baustein erkennt Attribute einer Klasse');
                ok(mem78 !== null && mem78.methods.length === 1 && mem78.methods[0].name === 'fahren', 'T78c der Baustein erkennt Methoden einer Klasse');

                /* T79 (#103): Tab-Verhalten vereinheitlicht. Ohne offenen
                 * Vorschlag rueckt Tab um zwei Leerzeichen ein, statt den
                 * Fokus zu verlieren; mit Vorschlag uebernimmt Tab wie bisher. */
                roCodeTa.value = 'qq';
                roCodeTa.selectionStart = 2;
                roCodeTa.selectionEnd = 2;
                roCodeTa.dispatch('input');
                roCodeTa.dispatch('keydown', { key: 'Tab' });
                ok(roCodeTa.value === 'qq  ', 'T79 Tab ohne Vorschlag fuegt im Lesemodus zwei Leerzeichen ein');
                ok(roCodeTa.selectionStart === 4 && roCodeTa.selectionEnd === 4, 'T79b der Cursor steht nach dem Vorschub');
                roCodeTa.value = 'Sys';
                roCodeTa.selectionStart = 3;
                roCodeTa.selectionEnd = 3;
                roCodeTa.dispatch('input');
                roCodeTa.dispatch('keydown', { key: 'Tab' });
                ok(roCodeTa.value === 'System.out.println(', 'T79c Tab mit offenem Vorschlag uebernimmt weiterhin den Vorschlag');
                /* Diagramm-Quelltext im geteilten Lesemodus: gleiche Regel. */
                roSplitTa.value = 'zz';
                roSplitTa.selectionStart = 2;
                roSplitTa.selectionEnd = 2;
                roSplitTa.dispatch('input');
                roSplitTa.dispatch('keydown', { key: 'Tab' });
                ok(roSplitTa.value === 'zz  ', 'T79d Tab ohne Vorschlag fuegt auch im Diagramm-Quelltext zwei Leerzeichen ein');
                /* Auswahl ueber mehrere Zeilen: jede Zeile rueckt ein. */
                var ta79 = new FakeEl('textarea');
                ta79.value = 'a' + NLt + 'b';
                ta79.selectionStart = 0;
                ta79.selectionEnd = 3;
                global.window.IC_EDIT_TOOLS.tabIndent(ta79);
                ok(ta79.value === '  a' + NLt + '  b', 'T79e Auswahl ueber zwei Zeilen rueckt jede Zeile ein');

                /* T80 (#105): Syntax-Highlighting als Baustein. highlight()
                 * haengt das Overlay hinter das Feld, feuerert bei input neu
                 * und schaltet die Sprache um; ohne hljs bleibt alles weg. */
                global.window.hljs = {
                  getLanguage: function (l) { return l === 'java' || l === 'javascript' || l === 'mermaid'; },
                  highlight: function (src, o) { return { value: '<b class="hl-mock">' + o.language + '</b>' }; }
                };
                var wrap80 = new FakeEl('div');
                var ta80 = new FakeEl('textarea');
                wrap80.appendChild(ta80);
                var ctl80 = global.window.IC_EDIT_TOOLS.highlight(ta80, 'java');
                ok(ctl80 !== null && wrap80.classList.contains('hl-on'), 'T80 das Overlay aktiviert sich (hl-on) nur, wenn es wirklich da ist');
                var pre80 = wrap80.querySelector('.icv-hl-pre');
                ok(pre80 !== null, 'T80b das farbige pre-Overlay liegt hinter dem Feld');
                var code80 = pre80 ? pre80.children[0] : null;
                ok(code80 !== null && String(code80.className).indexOf('language-java') !== -1, 'T80c das Overlay traegt die Sprache Java');
                ok(code80 !== null && String(code80.innerHTML).indexOf('hl-mock') !== -1, 'T80d der Quelltext wird durch highlight.js gefaerbt');
                ta80.value = 'class A {}';
                ta80.dispatch('input');
                ok(code80 !== null && String(code80.innerHTML).indexOf('hl-mock') !== -1, 'T80e Tippen faerbt das Overlay neu');
                ctl80.setLang('javascript');
                ok(code80 !== null && String(code80.className).indexOf('language-javascript') !== -1, 'T80f der Sprachwechsel schaltet das Overlay um');
                /* Ohne hljs: Rueckgabe null, Feld unangetastet. */
                delete global.window.hljs;
                var wrap80b = new FakeEl('div');
                var ta80b = new FakeEl('textarea');
                wrap80b.appendChild(ta80b);
                ok(global.window.IC_EDIT_TOOLS.highlight(ta80b, 'java') === null && !wrap80b.classList.contains('hl-on'), 'T80g ohne highlight.js bleibt das Feld unangetastet');

                /* T81 (#105): boostRead verdrahtet das Highlighting im
                 * Lesemodus mit - gleicher Baustein, nur angebunden. */
                var p81 = require.resolve('../public/assets/js/app.js');
                delete require.cache[p81];
                var doc81 = new FakeDoc();
                doc81.documentElement = new FakeEl('html');
                var box81 = new FakeEl('div');
                box81.className = 'icv-item icv-code';
                var ta81 = new FakeEl('textarea');
                ta81.className = 'mono icv-code-src';
                ta81.value = 'class Main {}';
                var btn81 = new FakeEl('button');
                btn81.className = 'icv-code-run';
                btn81.setAttribute('data-lang', 'java');
                btn81.setAttribute('data-clsfiles', '[]');
                box81.appendChild(ta81);
                box81.appendChild(btn81);
                doc81.body.appendChild(box81);
                var savedDoc81 = global.document;
                global.document = doc81;
                global.window.hljs = {
                  getLanguage: function () { return true; },
                  highlight: function (src, o) { return { value: '<b class="hl-mock81">' + o.language + '</b>' }; }
                };
                require(p81);
                var wrap81 = (ta81.parentNode && ta81.parentNode.classList.contains('icv-edwrap')) ? ta81.parentNode : null;
                ok(wrap81 !== null && wrap81.classList.contains('hl-on') && wrap81.querySelector('.icv-hl-pre') !== null, 'T81 der Lesemodus holt sich das Syntax-Highlighting aus demselben Baustein');
                var code81 = wrap81 && wrap81.querySelector('.icv-hl-pre') ? wrap81.querySelector('.icv-hl-pre').children[0] : null;
                ok(code81 !== null && String(code81.innerHTML).indexOf('hl-mock81') !== -1, 'T81b der Java-Quelltext der Seite ist gefaerbt');
                delete global.window.hljs;
                global.document = savedDoc81;

                /* T41 (#65): Das Anweisungsfeld wächst mit dem Text: rows folgt der Zeilenzahl. */
                var NL41 = String.fromCharCode(10);
                replIn.value = ['a', 'b', 'c', 'd', 'e', 'f'].join(NL41);
                replIn.dispatch('input');
                ok(String(replIn.getAttribute('rows')) === '6', 'T41 Anweisungsfeld: 6 Zeilen Text geben rows 6');
                replIn.value = 'eins';
                replIn.dispatch('input');
                ok(String(replIn.getAttribute('rows')) === '4', 'T41b kuerzere Eingabe behaelt mindestens rows 4');
                var t41long = '';
                var t41i;
                for (t41i = 0; t41i < 20; t41i++) { t41long += 'z' + NL41; }
                replIn.value = t41long;
                replIn.dispatch('input');
                ok(String(replIn.getAttribute('rows')) === '14', 'T41c viele Zeilen stoppen bei rows 14 (dann scrollt das Feld)');

                /* T42 (#65): Der Boot wartet auf DOMContentLoaded. java-vm.js laeuft
                 * im Browser mitten im Body (Script-Tag vor dem DB-Panel), die
                 * DB-Konsole steht erst danach im HTML. Bisher lief die Verdrahtung
                 * ins Leere und 'Abfrage ausfuehren' tat nichts. */
                var vmPath42 = require.resolve('../public/assets/js/java-vm.js');
                delete require.cache[vmPath42];
                var doc42 = new FakeDoc();
                doc42.readyState = 'loading';
                var dbSeed42 = new FakeEl('input');
                dbSeed42.value = 'CREATE TABLE produkte (name TEXT);' + NL41 + "INSERT INTO produkte VALUES ('Heft');";
                doc42.register(dbSeed42, 'java-db-seed');
                var dbIn42 = new FakeEl('textarea');
                doc42.register(dbIn42, 'java-db-in');
                var dbOut42 = new FakeEl('pre');
                doc42.register(dbOut42, 'java-db-out');
                var dbRunBtn42 = new FakeEl('button');
                doc42.register(dbRunBtn42, 'java-db-run');
                var savedDoc42 = global.document;
                global.document = doc42;
                require(vmPath42);
                ok(dbOut42.textContent === '', 'T42 solange das Dokument laedt, laeuft der Boot noch nicht');
                doc42._fire('DOMContentLoaded', { type: 'DOMContentLoaded' });
                ok(dbOut42.textContent.indexOf('Noch keine Abfrage') !== -1, 'T42b nach DOMContentLoaded ist die DB-Konsole verdrahtet (Platzhalter steht)');
                dbIn42.value = 'SELECT * FROM produkte';
                dbOut42.textContent = '';
                dbRunBtn42.dispatch('click');
                ok(dbOut42.textContent.indexOf('Heft') !== -1, 'T42c Abfrage ausfuehren liefert nach dem Boot die Zeilen (Seed geladen)');
                global.document = savedDoc42;

                /* T84 (#113): Lesemodus - boostRead verdrahtet auch das
                 * SQL-Abfrage-Feld des DB-Elements mit der Werkstatt-
                 * Autovervollstaendigung, Tabellen aus dem Seed-pre. */
                var p84 = require.resolve('../public/assets/js/app.js');
                delete require.cache[p84];
                var doc84 = new FakeDoc();
                doc84.documentElement = new FakeEl('html');
                var box84 = new FakeEl('div');
                box84.className = 'icv-item icv-db';
                var seed84 = new FakeEl('pre');
                seed84.className = 'icv-db-seed-src';
                seed84.textContent = "CREATE TABLE klassen (name TEXT); INSERT INTO klassen VALUES ('7a');";
                var qta84 = new FakeEl('textarea');
                qta84.className = 'mono icv-db-in';
                box84.appendChild(seed84);
                box84.appendChild(qta84);
                doc84.body.appendChild(box84);
                var savedDoc84 = global.document;
                global.document = doc84;
                require(p84);
                ok(qta84.__icAc === true, 'T84 das SQL-Abfrage-Feld des Lesemodus ist an die Autovervollstaendigung angeschlossen');
                ok(String(qta84.getAttribute('data-tables') || '') === 'klassen', 'T84b die Tabellen aus dem Schema stehen als data-tables am Feld');
                qta84.value = 'SEL';
                qta84.selectionStart = 3;
                qta84.selectionEnd = 3;
                qta84.dispatch('input');
                var ac84 = box84.querySelector('.ac-item');
                ok(ac84 !== null && ac84.textOf().indexOf('SELECT') !== -1, 'T84c SQL-Vorschlaege erscheinen im Lesemodus');
                qta84.dispatch('keydown', { key: 'Tab' });
                ok(String(qta84.value).indexOf('SELECT') === 0, 'T84d Tab uebernimmt den Vorschlag im Lesemodus');
                global.document = savedDoc84;

                /* T85 (#111/#112): CSS-Marker. Die Dock-Regel neutralisiert
                 * die Offsets der Overlay-Basisregel; die Objektkonsole wird
                 * per ID-Regel transparent, sobald das Overlay aktiv ist. */
                var css85 = require('fs').readFileSync(require.resolve('../public/assets/css/style.css'), 'utf8');
                var dock85 = css85.slice(css85.indexOf('.gfx-dock .gfx-window'), css85.indexOf('.gfx-dock .gfx-window') + 500);
                ok(/top:\s*auto;\s*right:\s*auto;/.test(dock85), 'T85 die Dock-Regel neutralisiert top/right der Overlay-Basisregel');
                ok(css85.indexOf('.hlwrap.hl-active > #java-repl-in { color: transparent; }') !== -1, 'T85b die ID-Regel macht den Originaltext der Objektkonsole transparent');

                /* T86 (#115): Textfeld-Hoehe nach Laden/Import. fitTa lief in
                 * buildItem, bevor das Feld im Dokument hing (scrollHeight 0),
                 * geladene/importierte Felder blieben auf Mindesthoehe.
                 * renderStage zieht die Hoehe jetzt nach dem Einfuegen fuer
                 * ALLE Felder nach (fitStageTas, als IC_IBOOK_FITTA exponiert). */
                var p86 = require.resolve('../public/assets/js/app.js');
                delete require.cache[p86];
                var doc86 = new FakeDoc();
                doc86.documentElement = new FakeEl('html');
                doc86.body.setAttribute('data-csrf', 'csrf-t86');
                var ed86 = new FakeEl('div');
                ed86.setAttribute('data-book-id', '77');
                ed86.setAttribute('data-page-id', '42');
                ed86.setAttribute('data-blocks', JSON.stringify([{ type: 'canvas', w: 1600, h: 1000, items: [
                  { kind: 'text', body: 'Importierter Zettel', x: 40, y: 40, w: 320 }
                ] }]));
                doc86.register(ed86, 'ibook-editor');
                doc86.register(new FakeEl('input'), 'ibook-blocks-json');
                var form86 = new FakeEl('form');
                form86.setAttribute('action', '/infobuecher/77/seite/42');
                doc86.register(form86, 'ibook-saveform');
                var savedDoc86 = global.document;
                global.document = doc86;
                require(p86);
                global.document = savedDoc86;
                ok(typeof global.window.IC_IBOOK_FITTA === 'function', 'T86 der Nachzieh-Lauf ist als Baustein exponiert (renderStage ruft ihn nach dem Einfuegen)');
                var ta86 = ed86.querySelector('.icv-ta');
                ok(ta86 !== null, 'T86b das importierte Textfeld steht auf der Flaeche');
                if (ta86 && global.window.IC_IBOOK_FITTA) {
                  ta86.style.height = ''; /* Mindesthoehe, wie direkt nach dem Import */
                  ta86.scrollHeight = 234; /* jetzt liegt das Feld im Layout */
                  global.window.IC_IBOOK_FITTA();
                  ok(ta86.style.height === '234px', 'T86c nach dem Laden/Import passt sich die Textfeld-Hoehe an den Inhalt an');
                }

                /* T52 (#67): Autospeichern. Der 900ms-Timer des Editors feuert
                 * erst nach diesem Callback, deshalb sitzt die Pruefung (und
                 * die Zusammenfassung) eine setTimeout-Ebene tiefer. */
                setTimeout(function () {
                  ok(ibookUrls.indexOf('/infobuecher/77/seite/42') !== -1, 'T52 jede Aenderung speichert sich von selbst (POST auf die Seite, ohne Speichern-Knopf)');
                  var ibStatus52 = ibDoc.body.querySelector('.ibook-status');
                  ok(ibStatus52 !== null && ibStatus52.textContent.indexOf('Automatisch gespeichert') !== -1, 'T52b der Editor meldet den automatischen Speichervorgang im Statusfeld');

                                    /* ============================================================
                     T87-T99 (#116-#128): Block-Werkstatt. Zunaechst headless
                     (Laufzeit ohne DOM), danach ein eigener FakeDoc fuer den
                     Editor (T86-Muster: eigenes document + frisches require,
                     weil window-Exponate auf die zuletzt initialisierte
                     Instanz zeigen). Seit #125/#126: Figuren-Liste, die
                     Buehne als eigenes Ziel, Migration von Version 1. */
                  var blkDocSaved = global.document;
                  var blkFetchSaved = global.fetch;
                  var blkFetchCalls = [];
                  global.fetch = function (url, opts) {
                    blkFetchCalls.push({ url: String(url), body: opts ? String(opts.body) : '' });
                    return { then: function (fn) { fn({ ok: true }); return { catch: function () {} }; } };
                  };

                  var blkDoc = new FakeDoc();
                  function blkReg(id, tag) {
                    var e = blkDoc.createElement(tag || 'div');
                    blkDoc.register(e, id);
                    return e;
                  }
                  blkReg('blk-ide');
                  blkReg('blk-scriptwrap');
                  var blkCatsEl = blkReg('blk-cats');
                  var blkPalEl = blkReg('blk-pal');
                  var blkHelpEl = blkReg('blk-help');
                  var blkScriptEl = blkReg('blk-script');
                  var blkSpritesEl = blkReg('blk-sprites');
                  var blkFlagBtn = blkReg('blk-flag', 'button');
                  blkReg('blk-stop', 'button');
                  var blkRuninfoEl = blkReg('blk-runinfo');
                  var blkSaveinfoEl = blkReg('blk-saveinfo');
                  var blkVarAddEl = blkReg('blk-var-add', 'button');
                  var blkListAddEl = blkReg('blk-list-add', 'button');
                  var blkProcAddEl = blkReg('blk-proc-add', 'button');
                  var blkTemplatesBtn = blkReg('blk-templates', 'button');
                  var blkFullBtn = blkReg('blk-full', 'button');
                  var blkStageEl = blkReg('blk-stage');
                  var blkBgEl = blkDoc.createElement('canvas');
                  blkDoc.register(blkBgEl, 'blk-bg');
                  var blkModeEl = blkReg('blk-mode', 'div');
                  var blkModeLearnEl = blkReg('blk-mode-learn', 'button');
                  var blkModeExpertEl = blkReg('blk-mode-expert', 'button');
                  /* #161: Modus am Werkstatt-Geruest (Konto-Vorgabe) und
                   * Speicher-Route fuer die Moduswahl. */
                  var blkIdeEl = blkDoc.getElementById('blk-ide');
                  blkIdeEl.setAttribute('data-blk-mode', 'learn');
                  blkIdeEl.setAttribute('data-mode-url', '/einstellungen/werkstattmodus');
                  var blkCodeEl = blkReg('blk-code', 'button');
                  blkCodeEl.setAttribute('data-port', '/werkstatt/code/blocks/port');
                  var blkConsoleBox = blkReg('blk-console');
                  var blkConsoleLogEl = blkReg('blk-console-log');
                  blkReg('blk-console-in', 'input');
                  blkReg('blk-console-send', 'button');
                  var blkPenEl = blkDoc.createElement('canvas');
                  blkDoc.register(blkPenEl, 'blk-pen');
                  var blkCtrEl = blkDoc.createElement('canvas');
                  blkDoc.register(blkCtrEl, 'blk-ctr');
                  var blkFormEl = blkReg('blocks-save-form', 'form');
                  blkFormEl.setAttribute('action', '/werkstatt/code/5');
                  var blkCsrfEl = new FakeEl('input');
                  blkCsrfEl.setAttribute('name', 'csrf');
                  blkCsrfEl.value = 'tok';
                  blkFormEl.appendChild(blkCsrfEl);
                  var blkTitleEl = new FakeEl('input');
                  blkTitleEl.setAttribute('name', 'title');
                  blkTitleEl.value = 'Block Demo';
                  blkFormEl.appendChild(blkTitleEl);
                  var blkJsonEl = new FakeEl('textarea');
                  blkDoc.register(blkJsonEl, 'blocks-json');
                  /* Absichtlich noch Version 1 (eine Figur, flache Skriptliste):
                   * T87 und T97 pruefen die Migration auf Version 2. */
                  blkJsonEl.value = JSON.stringify({
                    v: 1, vars: [],
                    sprite: { x: 0, y: 0, dir: 90, costume: 'robot', size: 100, visible: true },
                    scripts: [{
                      id: 's1', x: 24, y: 16,
                      top: { op: 'flag', id: 'b1', f: {}, sub: [],
                        next: { op: 'say_for', id: 'b2', f: { msg: { v: 'Hallo!' }, secs: { v: '2' } }, sub: [],
                          next: { op: 'move_steps', id: 'b3', f: { steps: { v: '50' } }, sub: [], next: null } } }
                    }]
                  });

                  global.document = blkDoc;
                  delete require.cache[require.resolve('../public/assets/js/block-editor.js')];
                  var BLK = require('../public/assets/js/block-editor.js');

                  function blkRaw(op, fields, next, subs) {
                    var f = {};
                    var k;
                    for (k in (fields || {})) {
                      var fv = fields[k];
                      f[k] = (fv && typeof fv === 'object' && fv.b) ? { b: fv.b } : { v: fv };
                    }
                    return { op: op, f: f, sub: subs || [], next: next || null };
                  }
                  function blkProgram(top) {
                    return BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: top }] }));
                  }
                  function blkCond(raw) { return { cond: { b: raw } }; }

                  ok(BLK && BLK.BLOCKS && BLK.BLOCKS.flag && BLK.newRuntime, 'T87 der Block-Editor meldet Registry und Laufzeit als Exponat');
                  ok(Object.keys(BLK.BLOCKS).length >= 40, 'T87b die Registry enthaelt alle Bloecke der jetzt neun Kategorien');
                  var blkParsed = BLK.parseProgram(blkJsonEl.value);
                  ok(blkParsed.v === 3 && blkParsed.sprites.length === 1 && blkParsed.sprites[0].scripts.length === 1 && blkParsed.sprites[0].scripts[0].top.op === 'flag' && blkParsed.sprites[0].scripts[0].top.next.op === 'say_for', 'T87c das gespeicherte JSON laedt als Skript (Version 3, Ursprung oben links) mit Start-Hut und sage-Befehl');
                  var blkEmpty = BLK.parseProgram('kein json');
                  ok(blkEmpty.sprites.length === 1 && blkEmpty.sprites[0].scripts.length === 0, 'T87d ungueltiges JSON laedt als leeres Programm statt zu abstuerzen');

                  /* T88: Bewegung, warte, wiederhole, fortlaufend, stoppe alles */
                  var rt88 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('move_steps', { steps: '10' }))));
                  rt88.flag(0);
                  rt88.frame(0);
                  ok(rt88.sprites[0].x === 250, 'T88 gehe 10 Schritte in Richtung 90 Grad bewegt die Figur nach rechts (Start in der Buehnenmitte 240/180)');
                  var rt88b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('point_dir', { deg: '0' }, blkRaw('move_steps', { steps: '10' })))));
                  rt88b.flag(0);
                  rt88b.frame(0);
                  ok(rt88b.sprites[0].y === 170, 'T88b Richtung 0 Grad heisst nach oben (y wird kleiner)');
                  var rt88c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('wait', { secs: '1' }, blkRaw('change_y', { dy: '7' })))));
                  rt88c.flag(0);
                  rt88c.frame(999);
                  ok(rt88c.sprites[0].y === 180, 'T88c waehrend des Wartens steht das Skript still');
                  rt88c.frame(1000);
                  ok(rt88c.sprites[0].y === 180, 'T88d die Wartezeit zaehlt ab dem ersten Anlauf (hier: bis Frame 1999)');
                  rt88c.frame(2500);
                  ok(rt88c.sprites[0].y === 187, 'T88e nach Ablauf der Wartezeit laeuft es weiter (y nach unten)');
                  var rt88e = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('repeat', { count: '3' }, null, [blkRaw('change_y', { dy: '5' })])) }] })));
                  rt88e.flag(0);
                  rt88e.frame(0);
                  rt88e.frame(0);
                  rt88e.frame(0);
                  ok(rt88e.sprites[0].y === 195, 'T88f wiederhole 3 mal lauft mit einem Durchlauf pro Bild');
                  rt88e.frame(0);
                  ok(rt88e.running() === false, 'T88g nach dem letzten Durchlauf ist der Thread beendet');
                  var rt88g = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('forever', {}, null, [blkRaw('move_steps', { steps: '1' })])) }] })));
                  rt88g.flag(0);
                  var f88;
                  for (f88 = 0; f88 < 10; f88++) { rt88g.frame(f88); }
                  ok(rt88g.sprites[0].x === 250, 'T88h wiederhole fortlaufend bewegt Bild fuer Bild');
                  var rt88h = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('forever', {}, null, [blkRaw('stop_all')])) }] })));
                  rt88h.flag(0);
                  rt88h.frame(0);
                  ok(rt88h.running() === false, 'T88i stoppe alles beendet alle laufenden Skripte sofort');

                  /* T89: wenn/sonst, Operatoren, Division durch 0, Zufall */
                  function blkVarProgram(condRaw, name) {
                    return BLK.parseProgram(JSON.stringify({
                      v: 1, vars: ['punkte'], sprite: {},
                      scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('if_then', blkCond(condRaw), null, [blkRaw('var_set', { name: name, value: '5' })])) }]
                    }));
                  }
                  var rt89 = BLK.newRuntime(blkVarProgram(blkRaw('gt', { a: '3', b: '2' }), 'punkte'));
                  rt89.flag(0);
                  rt89.frame(0);
                  ok(rt89.vars.punkte === 5, 'T89 wenn 3 > 2 setzt den dann-Zweig um');
                  var rt89b = BLK.newRuntime(blkVarProgram(blkRaw('lt', { a: '3', b: '2' }), 'punkte'));
                  rt89b.flag(0);
                  rt89b.frame(0);
                  ok(rt89b.vars.punkte === 0, 'T89b eine falsche Bedingung ueberspringt den dann-Zweig');
                  var rt89c = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: ['a'], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('if_else', blkCond(blkRaw('eq', { a: 'hi', b: 'hi' })), null, [blkRaw('var_set', { name: 'a', value: '1' })], [blkRaw('var_set', { name: 'a', value: '2' })])) }] })));
                  rt89c.flag(0);
                  rt89c.frame(0);
                  ok(rt89c.vars.a === 1, 'T89c wenn ... sonst nimmt bei wahrer Bedingung den dann-Zweig');
                  var rt89d = BLK.newRuntime(blkVarProgram(blkRaw('div', { a: '4', b: '0' }), 'punkte'));
                  rt89d.flag(0);
                  rt89d.frame(0);
                  ok(rt89d.errors.length === 1 && rt89d.vars.punkte === 0, 'T89d Division durch 0 erzeugt einen Hinweis statt eines Absturzes');
                  var rt89e = BLK.newRuntime(blkVarProgram(blkRaw('random', { from: '1', to: '10' }), 'punkte'));
                  rt89e.flag(0);
                  rt89e.frame(0);
                  ok(rt89e.errors.length === 0, 'T89e die ganzzahlige Zufallszahl laeuft ohne Fehler durch');

                  /* T90: Wahrnehmung (Rand, Taste, Stoppuhr) */
                  var rt90 = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: ['r'], sprite: { x: 238, y: 0 }, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('if_then', blkCond(blkRaw('touching_edge')), null, [blkRaw('var_set', { name: 'r', value: '1' })])) }] })));
                  rt90.flag(0);
                  rt90.frame(0);
                  ok(rt90.vars.r === 1, 'T90 am Rand berueht die Wahrnehmung den Rand');
                  var rt90b = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: ['k'], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('forever', {}, null, [blkRaw('if_then', blkCond(blkRaw('key_pressed', { key: 'leertaste' })), null, [blkRaw('var_set', { name: 'k', value: '1' })])])) }] })));
                  rt90b.flag(0);
                  rt90b.keys.leertaste = true;
                  rt90b.frame(0);
                  rt90b.frame(0);
                  ok(rt90b.vars.k === 1, 'T90b Taste gedrueckt? liest den Tastaturstatus ohne Fokus im Fenster');
                  var rt90c = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: ['t'], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('if_then', blkCond(blkRaw('gt', { a: { b: blkRaw('timer') }, b: '0.5' })), null, [blkRaw('var_set', { name: 't', value: '1' })])) }] })));
                  rt90c.flag(5000);
                  rt90c.frame(6000);
                  ok(rt90c.vars.t === 1, 'T90c die Stoppuhr zaehlt die Sekunden seit dem Start');

                  /* T91: Stift und Sprechblase */
                  var rt91 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('pen_down', {}, blkRaw('move_steps', { steps: '100' })))));
                  rt91.flag(0);
                  rt91.frame(0);
                  ok(rt91.sprites[0].pen.segments.length === 1 && rt91.sprites[0].pen.segments[0].x2 === 340, 'T91 Stift ab zeichnet beim Bewegen eine Spur (240 nach 340)');
                  var rt91b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('pen_down', {}, blkRaw('move_steps', { steps: '50' }, blkRaw('pen_clear'))))));
                  rt91b.flag(0);
                  rt91b.frame(0);
                  ok(rt91b.sprites[0].pen.segments.length === 0, 'T91b loesche alle Spuren leert die Spuren');
                  var rt91c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('say_for', { msg: 'Hallo!', secs: '2' }))));
                  rt91c.flag(0);
                  rt91c.frame(0);
                  ok(rt91c.sprites[0].bubble !== null && rt91c.sprites[0].bubble.text === 'Hallo!', 'T91c sage ... fuer n Sekunden zeigt die Sprechblase');
                  rt91c.frame(2500);
                  ok(rt91c.sprites[0].bubble === null, 'T91d die Sprechblase verschwindet nach Ablauf der Zeit');

                  /* T92: Serialisierung und Validierung */
                  var blkSer = BLK.serializeProgram(blkParsed);
                  ok(blkSer.v === 3 && blkSer.sprites.length === 1 && blkSer.sprites[0].scripts.length === 1 && blkSer.sprites[0].scripts[0].top.next.op === 'say_for' && blkSer.stage.scripts.length === 0, 'T92 das Programm serialisiert sich (Version 3) mit Figuren, Skripten und Buehne');
                  var blkRawMany = { v: 1, vars: [], sprite: {}, scripts: [] };
                  var i92;
                  for (i92 = 0; i92 < 100; i92++) { blkRawMany.scripts.push({ id: 's' + i92, x: 0, y: 0, top: blkRaw('flag') }); }
                  var blkMany = BLK.parseProgram(JSON.stringify(blkRawMany));
                  ok(blkMany.sprites[0].scripts.length === 60, 'T92b mehr als 60 Skripte werden auf das Limit begrenzt');
                  var blkEvil = BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('hakemeister', {}, blkRaw('hackemeister_zwei'))) }] }));
                  ok(blkEvil.sprites[0].scripts.length === 1 && blkEvil.sprites[0].scripts[0].top.next === null, 'T92c unbekannte Bloecke im geladenen JSON entfallen kommentarlos');

                  /* T93: Editor-UI am eigenen FakeDoc */
                  var blkUi = BLK.ui;
                  ok(blkUi !== null && typeof blkUi === 'object', 'T93 der Editor initialisiert sich auf der Blöcke-Seite von selbst');
                  ok(blkCatsEl.children.length === 9, 'T93b die Palette listet im Lernmodus neun Kategorien (inklusive Meine Bloecke), die Variablen-Kategorie bleibt dem Expertenmodus vorbehalten (#162, #138)');
                  ok(blkPalEl.children.length === 4, 'T93c die Ereignis-Kategorie zeigt im Lernmodus die vier Basis-Bloecke inklusive Klon-Start (#162, #132)');
                  blkCatsEl.children[1].dispatch('click');
                  ok(blkPalEl.querySelector('.blk[data-pal-op="move_steps"]') !== null, 'T93d der Kategorie-Wechsel zeigt die Bewegungs-Bloecke');
                  ok(blkHelpEl.querySelectorAll('.blk-help-row').length === Object.keys(BLK.BLOCKS).length, 'T93e die Befehlsuebersicht erklaert jeden Block der Registry');
                  var blkBefore = blkUi.program.sprites[0].scripts.length;
                  blkUi.addBlock('flag', 200, 120);
                  ok(blkUi.program.sprites[0].scripts.length === blkBefore + 1 && blkScriptEl.querySelectorAll('.blk-chain').length === blkBefore + 1, 'T93f ein neuer Start-Block entsteht als Skript auf der Flaeche');
                  var blkDelId = blkUi.program.sprites[0].scripts[blkUi.program.sprites[0].scripts.length - 1].top.id;
                  blkUi.deleteBlockTree(blkDelId);
                  ok(blkUi.program.sprites[0].scripts.length === blkBefore, 'T93g der Loesch-Knopf entfernt den Block samt Skript');
                  ok(blkUi.addVariable('punkte') === true && blkUi.program.vars.indexOf('punkte') !== -1, 'T93h eine neue Variable landet in der Liste');
                  ok(blkUi.addVariable('punkte') === false, 'T93i doppelte Variablennamen lehnt der Editor ab');

                  /* T94: Autospeichern */
                  blkFetchCalls.length = 0;
                  blkUi.saveNow();
                  ok(blkFetchCalls.length === 1 && blkFetchCalls[0].url === '/werkstatt/code/5', 'T94 das Autospeichern geht als POST auf die Projekt-URL');

                  var blkBodyJson = decodeURIComponent(blkFetchCalls[0].body.split('content=')[1] || '');
                  ok(blkBodyJson.indexOf('{"v":3') === 0 && blkBodyJson.indexOf('"op":"flag"') !== -1 && blkBodyJson.indexOf('"sprites"') !== -1 && blkBodyJson.indexOf('"stage"') !== -1, 'T94c das komplette Skriptmodell wird als JSON (Version 3) mit Figuren und Buehne gespeichert');
                  ok(blkJsonEl.value.indexOf('"op":"flag"') !== -1, 'T94d das JSON-Feld im Formular bleibt synchron');
                  ok(blkSaveinfoEl.textContent.indexOf('automatisch gespeichert') !== -1, 'T94e der Editor meldet den automatischen Speichervorgang im Statusfeld');

                  /* T95: Buehne und Lauf in der UI */
                  blkFlagBtn.dispatch('click');
                  ok(blkUi.runtime() !== null && blkRuninfoEl.textContent.indexOf('läuft') !== -1, 'T95 der Start-Knopf startet das Programm auf der Buehne');
                  ok(blkPenEl.__ctx.__calls.indexOf('clearRect') !== -1, 'T95b jeder Start loescht zunaechst alle Spuren von der Buehne');
                  blkUi.tick(0);
                  blkUi.tick(1500);
                  ok(blkCtrEl.__ctx.__calls.indexOf('fillText:🤖') !== -1, 'T95c die Figur zeichnet sich in der Mitte der Buehne');
                  blkUi.tick(2500);
                  ok(blkUi.runtime().sprites[0].x === 290, 'T95d nach der Sprechblase laeuft der weitere Block (hier: 50 Schritte nach rechts)');

                  /* T96: Formen (#127): Registry traegt die Form je Block,
                   * der Start-Hut nennt den Play-Knopf (#128). */
                  var blkKinds = { hat: 0, stack: 0, c: 0, c2: 0, rep: 0, bool: 0 };
                  var blkAllForms = true;
                  var op96;
                  for (op96 in BLK.BLOCKS) {
                    if (Object.prototype.hasOwnProperty.call(BLK.BLOCKS, op96)) {
                      if (blkKinds[BLK.BLOCKS[op96].kind] === undefined) { blkAllForms = false; }
                      else { blkKinds[BLK.BLOCKS[op96].kind]++; }
                    }
                  }
                  ok(blkAllForms && blkKinds.hat >= 2 && blkKinds.stack >= 20 && blkKinds.rep >= 6 && blkKinds.bool >= 8, 'T96 jeder Block traegt seine Form (Hut, Stapel, C, Pille, Sechseck)');
                  ok(BLK.BLOCKS.flag.parts[0] === 'Wenn ▶ angeklickt wird', 'T96b der Start-Hut nennt den Play-Knopf statt der Fahne');

                  /* T97: Migration von Version 1 auf Version 2 (#125) */
                  ok(blkParsed.sprites[0].name === 'Figur 1' && blkParsed.sprites[0].id && blkParsed.stage.scripts.length === 0, 'T97 ein altes Programm der Version 1 laedt als Version 3 (Koordinaten umgerechnet) mit einer Figur und einer Buehne');
                  var blkRound = BLK.parseProgram(JSON.stringify(BLK.serializeProgram(blkParsed)));
                  ok(blkRound.v === 3 && blkRound.sprites.length === blkParsed.sprites.length && blkRound.sprites[0].scripts.length === blkParsed.sprites[0].scripts.length && blkRound.stage.scripts.length === 0, 'T97b speichern und laden ergibt denselben Stand (Round-trip, Version 3)');

                  /* T98: mehrere Figuren, jede mit eigenen Skripten (#125) */
                  var blkTwo = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('move_steps', { steps: '10' })) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: -60, y: 30, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's2', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('move_steps', { steps: '20' })) }] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt98 = BLK.newRuntime(blkTwo);
                  rt98.flag(0);
                  rt98.frame(0);
                  ok(rt98.sprites[0].x === 250 && rt98.sprites[1].x === 200, 'T98b zwei Figuren laufen unabhaengig voneinander (hier: 10 und 20 Schritte, Startpositionen aus Version 2 migriert)');
                  var rt98c = BLK.newRuntime(blkTwo);
                  rt98c.flag(0);
                  rt98c.stopAll();
                  ok(rt98c.running() === false && rt98c.sprites[0].x === 240 && rt98c.sprites[1].x === 180, 'T98c stoppe alles haelt beide Figuren an (migrierte Positionen)');

                  /* T99: die Buehne als eigenes Ziel (#126) */
                  var blkStageProg = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: ['buehne'],
                    sprites: [],
                    stage: { scripts: [{ id: 'st1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('var_set', { name: 'buehne', value: '1' }, blkRaw('move_steps', { steps: '30' }))) }] }
                  }));
                  var rt99 = BLK.newRuntime(blkStageProg);
                  rt99.flag(0);
                  rt99.frame(0);
                  ok(rt99.vars.buehne === 1 && rt99.errors.length === 0 && rt99.sprites.length === 0, 'T99 das Buehnen-Skript laeuft mit und Bewegung stoert die Buehne nicht');
                  /* Figurenliste + Zielwechsel in der UI */
                  ok(blkSpritesEl.children.length === 3, 'T98 die Figurenliste zeigt die Buehne, eine Figur und den Figuren-Knopf');
                  var blkSpr2 = blkUi.addSprite('fish');
                  ok(blkSpr2 !== null && blkUi.program.sprites.length === 2 && blkSpritesEl.children.length === 4, 'T98d eine neue Figur landet in der Liste und wird zum Ziel');
                  blkUi.selectTarget(null);
                  ok(blkUi.isStage() === true && blkCatsEl.children.length === 7, 'T99b die Buehne zeigt nur passende Kategorien (sieben statt neun im Lernmodus, inklusive Buehne und Meine Bloecke)');
                  blkUi.addBlock('flag', 10, 10);
                  ok(blkUi.program.stage.scripts.length === 1 && blkUi.program.sprites[0].scripts.length === blkUi.program.sprites[0].scripts.length, 'T99c ein Block auf der Buehne landet im Buehnen-Skript');
                  blkUi.selectTarget(blkUi.program.sprites[0]);
                  ok(blkUi.isStage() === false && blkCatsEl.children.length === 9, 'T99d zurueck auf der Figur sind im Lernmodus alle neun zulaessigen Kategorien da, die Buehne-Kategorie entfaellt (#139)');
                  blkUi.deleteSprite(blkUi.program.sprites[1]);
                  ok(blkUi.program.sprites.length === 1 && blkUi.target() === blkUi.program.sprites[0], 'T99e das Loeschen der zweiten Figur waehlt die erste zurueck');
                  ok(blkUi.program.stage.scripts.length === 1, 'T99f das Buehnen-Skript bleibt beim Figuren-Wechsel erhalten');

                  /* T100-T100e (#129): Block aus der Mitte/Unten eines Stapels
                   * wegnehmen darf den Rest des Stapels nie verlieren. Vorher
                   * meldete findBlockPos jeden Block der next-Kette mit
                   * parentKind 'top' und der Editor raeumte das GESAMTE Skript
                   * weg. Eigener FakeDoc mit vollstaendigen Rechtecken (auch
                   * right/bottom, sonst ist dist() NaN) und Pointer-Events
                   * wie im Browser; der Editor wird je Szenario frisch
                   * geladen, damit keine Handler aelterer Instanzen am
                   * selben Dokument haengen. */
                  var blkDragDoc = new FakeDoc();
                  (function () {
                    var d = blkDragDoc;
                    function reg2(id, tag) { var e = d.createElement(tag || 'div'); d.register(e, id); return e; }
                    var pal2 = reg2('blk-pal');
                    var wrap2 = reg2('blk-scriptwrap');
                    var scr2 = reg2('blk-script');
                    reg2('blk-ide'); reg2('blk-cats'); reg2('blk-help'); reg2('blk-sprites');
                    reg2('blk-flag', 'button'); reg2('blk-stop', 'button'); reg2('blk-runinfo');
                    reg2('blk-saveinfo'); reg2('blk-var-add', 'button');
                    reg2('blk-pen', 'canvas'); reg2('blk-ctr', 'canvas');
                    var f2 = reg2('blocks-save-form', 'form');
                    f2.setAttribute('action', '/x');
                    var cs2 = new FakeEl('input'); cs2.setAttribute('name', 'csrf'); cs2.value = 'tok'; f2.appendChild(cs2);
                    var ti2 = new FakeEl('input'); ti2.setAttribute('name', 'title'); ti2.value = 'B'; f2.appendChild(ti2);
                    var jt2 = new FakeEl('textarea'); d.register(jt2, 'blocks-json');
                    pal2.getBoundingClientRect = function () { return { left: 8, top: 100, width: 160, height: 500, right: 168, bottom: 600 }; };
                    wrap2.getBoundingClientRect = function () { return { left: 200, top: 100, width: 600, height: 500, right: 800, bottom: 600 }; };
                    scr2.getBoundingClientRect = function () { return { left: 200, top: 100, width: 600, height: 500, right: 800, bottom: 600 }; };
                    blkDragDoc.scriptEl = scr2;
                    blkDragDoc.jsonEl = jt2;
                  })();
                  function blkDragFresh(jsonText) {
                    blkDragDoc.jsonEl.value = jsonText || JSON.stringify({
                      v: 2, vars: [], sprites: [{ id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true, scripts: [{
                        id: 's1', x: 24, y: 16,
                        top: { op: 'flag', id: 'b1', f: {}, sub: [],
                          next: { op: 'say_for', id: 'b2', f: { msg: { v: 'Hallo!' }, secs: { v: '2' } }, sub: [],
                            next: { op: 'move_steps', id: 'b3', f: { steps: { v: '50' } }, sub: [], next: null } } }
                      }] }], stage: { scripts: [] }
                    });
                    var dSaved = global.document;
                    /* Nur die frische Instanz darf am Dokument lauschen: Ohne
                     * das Leeren wuerden Delegations-Handler aelterer
                     * Instanzen (gleiche Element-IDs, getrennte Programme)
                     * parallel mitziehen und dabei das gemeinsame DOM neu
                     * rendern - der Browser kennt das nicht, er hat nur
                     * eine Editor-Instanz pro Seite. */
                    blkDragDoc._handlers = {};
                    global.document = blkDragDoc;
                    delete require.cache[require.resolve('../public/assets/js/block-editor.js')];
                    var B2 = require('../public/assets/js/block-editor.js');
                    blkDragDoc.setRects = function () {
                      var y = 116;
                      for (var c = 0; c < blkDragDoc.scriptEl.children.length; c++) {
                        var kids = blkDragDoc.scriptEl.children[c].children;
                        for (var k = 0; k < kids.length; k++) {
                          (function (bb, yy) { bb.getBoundingClientRect = function () { return { left: 224, top: yy, width: 180, height: 34, right: 404, bottom: yy + 34 }; }; })(kids[k], y);
                          y += 34;
                        }
                        y += 20;
                      }
                    };
                    /* Wie setRects, aber inklusive der Bloecke in C-Block-Koerpern
                     * und der Koerper-Container selbst (#148). */
                    blkDragDoc.setRectsDeep = function () {
                      var all = blkDragDoc.scriptEl.querySelectorAll('.blk');
                      var y = 116;
                      for (var i = 0; i < all.length; i++) {
                        (function (bb, yy) { bb.getBoundingClientRect = function () { return { left: 224, top: yy, width: 180, height: 34, right: 404, bottom: yy + 34 }; }; })(all[i], y);
                        y += 34;
                      }
                      var subs = blkDragDoc.scriptEl.querySelectorAll('.blk-sub');
                      for (var s = 0; s < subs.length; s++) {
                        (function (bb) { bb.getBoundingClientRect = function () { return { left: 224, top: 170, width: 180, height: 96, right: 404, bottom: 266 }; }; })(subs[s]);
                      }
                    };
                    blkDragDoc.restoreDoc = function () { global.document = dSaved; };
                    return B2.ui; /* Autoboot hat an blkDrag attached */
                  }
                  function blkDragOps(ui) {
                    var out = [];
                    var spr = ui.program.sprites[0];
                    for (var i = 0; i < spr.scripts.length; i++) {
                      var s = [], n = spr.scripts[i].top;
                      while (n) { s.push(n.op); n = n.next; }
                      out.push(s.join(','));
                    }
                    return out;
                  }
                  function blkDragPull(elIdx, sx, sy, moves, ex, ey) {
                    var el = blkDragDoc.scriptEl.children[0].children[elIdx];
                    el.dispatch('pointerdown', { clientX: sx, clientY: sy, pointerId: 1 });
                    for (var i = 0; i < moves.length; i++) {
                      blkDragDoc._fire('pointermove', { clientX: moves[i][0], clientY: moves[i][1], pointerId: 1 });
                      blkDragDoc.setRects();
                    }
                    blkDragDoc._fire('pointerup', { clientX: ex, clientY: ey, pointerId: 1 });
                  }

                  var ui2 = blkDragFresh();
                  blkDragPull(2, 250, 200, [[250, 212], [260, 260], [500, 400]], 500, 400);
                  var opsA = blkDragOps(ui2);
                  blkDragDoc.restoreDoc();
                  ok(opsA.length === 2 && opsA[0] === 'flag,say_for' && opsA[1] === 'move_steps', 'T100 der unterste Block loest nur sich, der Rest des Stapels bleibt');

                  var ui2 = blkDragFresh();
                  blkDragPull(2, 250, 200, [[250, 212], [150, 300]], 80, 320);
                  var opsB = blkDragOps(ui2);
                  blkDragDoc.restoreDoc();
                  ok(opsB.length === 1 && opsB[0] === 'flag,say_for', 'T100b Loeschen per Palette trifft nur den gezogenen Block, nicht den Stapel');

                  var ui2 = blkDragFresh();
                  var elC = blkDragDoc.scriptEl.children[0].children[2];
                  elC.dispatch('pointerdown', { clientX: 250, clientY: 200, pointerId: 1 });
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 212, pointerId: 1 });
                  blkDragDoc.setRects();
                  blkDragDoc._fire('pointercancel', { clientX: 250, clientY: 212, pointerId: 1 });
                  var opsC = blkDragOps(ui2);
                  blkDragDoc.restoreDoc();
                  ok(opsC.length === 1 && opsC[0] === 'flag,say_for,move_steps', 'T100c pointercancel nach dem Loesen stellt den Block an seinen alten Platz');

                  var ui2 = blkDragFresh();
                  var midRow = blkDragDoc.scriptEl.children[0].children[1].children[0];
                  var midX = null;
                  for (var qx = 0; qx < midRow.children.length; qx++) { if (midRow.children[qx].tagName === 'BUTTON') { midX = midRow.children[qx]; } }
                  if (midX) { midX.dispatch('click', {}); }
                  var opsD = blkDragOps(ui2);
                  blkDragDoc.restoreDoc();
                  ok(midX && opsD.length === 1 && opsD[0] === 'flag', 'T100d ✕ auf einen Mittelblock loescht nur ihn und seinen Teilbaum');

                  var ui2 = blkDragFresh();
                  blkDragPull(1, 250, 160, [[250, 172], [500, 400]], 500, 400);
                  var opsE = blkDragOps(ui2);
                  blkDragDoc.restoreDoc();
                  ok(opsE.length === 2 && opsE[0] === 'flag' && opsE[1] === 'say_for,move_steps', 'T100e der Mittelblock nimmt nur seinen eigenen Teilbaum mit');

                  /* T101-T108 (#130-#136, #140, #141): Klang, Nachrichten,
                     Sensoren und Zeiger, Bewegung-Plus, Aussehen-Plus,
                     Rechnen-Plus, Code-Ansicht Java/Python und Portierung. */

                  /* Klang (#130) */
                  ok(BLK.BLOCKS.play_note && BLK.BLOCKS.start_note && BLK.BLOCKS.stop_sounds && BLK.BLOCKS.set_volume && BLK.BLOCKS.volume.cat === 'sound', 'T101 die Klang-Kategorie hat spielen, starten, stoppen und Lautstaerke (#130)');
                  var rt101 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('play_note', { tone: 'a4', secs: '0.5' }, blkRaw('change_y', { dy: '7' })))));
                  rt101.flag(0);
                  rt101.frame(0);
                  ok(rt101.sounds.length === 1, 'T101b spiele Ton legt einen (im Test stummen) Ton an und haelt das Skript an (#130)');
                  rt101.frame(600);
                  ok(rt101.sounds.length === 0 && rt101.sprites[0].y === 187, 'T101c nach der Tonzeit laeuft das Skript weiter (#130)');
                  var rt101b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('start_note', { tone: 'c4' }, blkRaw('stop_sounds', {})))));
                  rt101b.flag(0);
                  rt101b.frame(0);
                  ok(rt101b.sounds.length === 0 && rt101b.sprites[0].y === 180, 'T101d starte Ton wartet nicht, stoppe alle Toene raeumt ab (#130)');
                  var rt101c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('set_volume', { v: '40' }, blkRaw('var_set', { name: 'l', value: { b: blkRaw('volume') } })))));
                  rt101c.flag(0);
                  rt101c.frame(0);
                  ok(rt101c.volume() === 40 && rt101c.vars.l === 40, 'T101e setze Lautstaerke und der Lautstaerke-Reporter folgt (#130)');

                  /* Nachrichten (#131) */
                  var progMsg = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('when_msg', { msg: 'nachricht1' }, blkRaw('var_set', { name: 'm', value: '1' })) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's2', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('broadcast', { msg: 'nachricht1' })) }] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt102 = BLK.newRuntime(progMsg);
                  rt102.flag(0);
                  rt102.frame(0);
                  ok(rt102.vars.m === 1, 'T102 senden startet das Empfaenger-Skript der anderen Figur (#131)');
                  var progWait = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('when_msg', { msg: 'nachricht2' }, blkRaw('wait', { secs: '1' }, blkRaw('var_set', { name: 'done', value: '1' }))) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's2', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('broadcast_wait', { msg: 'nachricht2' }, blkRaw('var_set', { name: 'nach', value: '1' }))) }] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt102b = BLK.newRuntime(progWait);
                  rt102b.flag(0);
                  rt102b.frame(0);
                  ok(rt102b.vars.nach === undefined && rt102b.vars.done === undefined, 'T102b sende-und-warte blockiert, solange der Empfaenger laeuft (#131)');
                  rt102b.frame(1100);
                  ok(rt102b.vars.done === 1 && rt102b.vars.nach === undefined, 'T102c der Empfaenger wird erst fertig, dann kommt der Sender dran (#131)');
                  rt102b.frame(1101);
                  ok(rt102b.vars.nach === 1, 'T102d danach laeuft der Sender weiter (#131)');

                  /* Sensoren und Zeiger (#133) */
                  var rt103 = BLK.newRuntime(blkProgram(blkRaw('flag', {},
                    blkRaw('var_set', { name: 'mx', value: { b: blkRaw('mouse_x') } },
                      blkRaw('var_set', { name: 'my', value: { b: blkRaw('mouse_y') } },
                        blkRaw('var_set', { name: 'md', value: { b: blkRaw('mouse_down') } }))))));
                  rt103.setMouse(30, 20, true);
                  rt103.flag(0);
                  rt103.frame(0);
                  ok(rt103.vars.mx === 30 && rt103.vars.my === 20 && rt103.vars.md === true, 'T103 Maus-x, Maus-y und gedrueckt folgen dem Zeiger (#133)');
                  var progTouch = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {},
                          blkRaw('var_set', { name: 'r', value: { b: blkRaw('touching_sprite', { name: 'sp2' }) } },
                            blkRaw('var_set', { name: 'd', value: { b: blkRaw('distance_to', { name: 'sp2' }) } }))) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 20, y: 0, dir: 90, size: 100, visible: true, scripts: [] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt103b = BLK.newRuntime(progTouch);
                  rt103b.flag(0);
                  rt103b.frame(0);
                  ok(rt103b.vars.r === true && rt103b.vars.d === 20, 'T103b beruehren und Abstand zwischen zwei Figuren stimmen (#133)');
                  var progClick = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('when_clicked', {}, blkRaw('var_set', { name: 'c', value: '1' })) }] }
                    ],
                    stage: { scripts: [{ id: 'st', x: 0, y: 0, top: blkRaw('when_clicked', {}, blkRaw('var_set', { name: 'b', value: '1' })) }] }
                  }));
                  var rt103c = BLK.newRuntime(progClick);
                  rt103c.setMouse(240, 180, true);
                  rt103c.click(0);
                  rt103c.frame(0);
                  ok(rt103c.vars.c === 1 && rt103c.vars.b === undefined, 'T103c Klick auf die Figur startet nur das Figuren-Skript (#133)');
                  var rt103d = BLK.newRuntime(progClick);
                  rt103d.setMouse(300, 180, true);
                  rt103d.click(0);
                  rt103d.frame(0);
                  ok(rt103d.vars.b === 1 && rt103d.vars.c === undefined, 'T103d Klick neben die Figur startet das Buehnen-Skript (#133)');

                  /* Bewegung-Plus (#134) */
                  var rt104 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('glide', { secs: '1', x: '100', y: '0' }))));
                  rt104.flag(0);
                  rt104.frame(0);
                  ok(rt104.sprites[0].x === 240, 'T104 gleiten startet an der alten Position (#134)');
                  rt104.frame(500);
                  ok(Math.round(rt104.sprites[0].x) === 170, 'T104b halbwegs auf der Strecke liegt die Figur in der Mitte (#134)');
                  rt104.frame(1000);
                  ok(rt104.sprites[0].x === 100, 'T104c am Ende steht die Figur am Ziel (#134)');
                  var prog104 = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 240, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('bounce', {})) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 100, y: 0, dir: 90, size: 100, visible: true, scripts: [] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt104b = BLK.newRuntime(prog104);
                  rt104b.flag(0);
                  rt104b.frame(0);
                  ok(rt104b.sprites[0].dir === 270 && rt104b.sprites[0].x === 456, 'T104d am Rand prallt die Figur ab und rueckt ein (#144: rechter Rand bei x 480)');
                  var prog104b = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 0, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('point_towards', { name: 'sp2' })) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 100, y: 0, dir: 90, size: 100, visible: true, scripts: [] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt104d = BLK.newRuntime(prog104b);
                  rt104d.flag(0);
                  rt104d.frame(0);
                  ok(Math.round(rt104d.sprites[0].dir) === 90, 'T104e drehe dich zu richtet die Figur nach rechts aus (#134)');

                  /* Aussehen-Plus (#135) */
                  var rt105 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('think', { msg: 'Hmm ...' }))));
                  rt105.flag(0);
                  rt105.frame(0);
                  ok(rt105.sprites[0].bubble && rt105.sprites[0].bubble.think === true && rt105.sprites[0].bubble.text === 'Hmm ...', 'T105 denken zeigt eine Denkblase (#135)');
                  var rt105b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('next_costume', {}, blkRaw('change_effect', { d: '25' })))));
                  rt105b.flag(0);
                  rt105b.frame(0);
                  ok(rt105b.sprites[0].costume === 'rocket' && rt105b.sprites[0].effect === 25, 'T105b naechstes Kostuem und Farbeffekt aendern die Figur (#135)');
                  var rt105c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('set_effect', { v: '100' }, blkRaw('clear_effects', {})))));
                  rt105c.flag(0);
                  rt105c.frame(0);
                  ok(rt105c.sprites[0].effect === 0, 'T105c Farbeffekte aus stellt die Originalfarbe wieder her (#135)');
                  var progLayer = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('go_front', {})) }] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 0, y: 0, dir: 90, size: 100, visible: true, scripts: [] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt105d = BLK.newRuntime(progLayer);
                  rt105d.flag(0);
                  rt105d.frame(0);
                  ok(rt105d.order[0] === 'sp2' && rt105d.order[1] === 'sp1', 'T105d zu vorderster Ebene schiebt die Figur nach vorn (#135)');
                  var progLayerBack = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: [],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true, scripts: [] },
                      { id: 'sp2', name: 'Figur 2', costume: 'fish', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's2', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('go_back', { n: '1' })) }] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var rt105e = BLK.newRuntime(progLayerBack);
                  rt105e.flag(0);
                  rt105e.frame(0);
                  ok(rt105e.order[0] === 'sp2' && rt105e.order[1] === 'sp1', 'T105e n Ebenen nach hinten tauscht entsprechend (#135)');

                  /* Rechnen-Plus (#136) */
                  function blkCalc(raw) {
                    return BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('var_set', { name: 'r', value: { b: raw } }))));
                  }
                  var rc1 = blkCalc(blkRaw('mod', { a: '7', b: '3' })); rc1.flag(0); rc1.frame(0);
                  ok(rc1.vars.r === 1, 'T106 Rest von 7 durch 3 ist 1 (#136)');
                  var rc2 = blkCalc(blkRaw('round', { a: '2.6' })); rc2.flag(0); rc2.frame(0);
                  ok(rc2.vars.r === 3, 'T106b runde 2,6 ergibt 3 (#136)');
                  var rc3 = blkCalc(blkRaw('sqrt', { a: '25' })); rc3.flag(0); rc3.frame(0);
                  ok(rc3.vars.r === 5, 'T106c Wurzel aus 25 ist 5 (#136)');
                  var rc4 = blkCalc(blkRaw('abs', { a: '-7' })); rc4.flag(0); rc4.frame(0);
                  ok(rc4.vars.r === 7, 'T106d Betrag von -7 ist 7 (#136)');
                  var rc5 = blkCalc(blkRaw('join', { a: 'Hallo', b: '!' })); rc5.flag(0); rc5.frame(0);
                  ok(rc5.vars.r === 'Hallo!', 'T106e verbinde haengt Texte aneinander (#136)');
                  var rc6 = blkCalc(blkRaw('letter_of', { n: '2', txt: 'Hallo' })); rc6.flag(0); rc6.frame(0);
                  ok(rc6.vars.r === 'a', 'T106f Buchstabe 2 von Hallo ist a (#136)');
                  var rc7 = blkCalc(blkRaw('length_of', { txt: 'Hallo' })); rc7.flag(0); rc7.frame(0);
                  ok(rc7.vars.r === 5, 'T106g Laenge von Hallo ist 5 (#136)');

                  /* Code-Ansicht (#140) */
                  var genProg = BLK.parseProgram(JSON.stringify({
                    v: 2, vars: ['punkte'],
                    sprites: [
                      { id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true,
                        scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {},
                          blkRaw('say_for', { msg: 'Hallo!', secs: '2' },
                            blkRaw('repeat', { count: '3' }, null, [blkRaw('turn_right', { deg: '15' })]))) }] }
                    ],
                    stage: { scripts: [] }
                  }));
                  var jOut = BLK.programToJava(genProg, 'Block Demo');
                  ok(jOut.indexOf('public class BlockDemo') !== -1 && jOut.indexOf('static class Figur1 extends Figur') !== -1 && jOut.indexOf('void wenn_flag_angeklickt()') !== -1 && jOut.indexOf('sage_fuer(\"Hallo!\", 2)') !== -1 && jOut.indexOf('for (int zaehler1') !== -1 && jOut.indexOf('void sage_fuer(Object p1, Object p2) { }') !== -1 && jOut.indexOf('new Thread(() -> figur1.wenn_flag_angeklickt()).start();') !== -1, 'T107 Java-Ansicht: Klasse, Vererbung, Methode, Aufruf, Schleife und Platzhalter (#140, #152)');
                  var pOut = BLK.programToPython(genProg, 'Block Demo');
                  ok(pOut.indexOf('class Figur1(Figur):') !== -1 && pOut.indexOf('def wenn_flag_angeklickt(self):') !== -1 && pOut.indexOf('self.sage_fuer(\'Hallo!\', 2)') !== -1 && pOut.indexOf('for zaehler1 in range(int(3)):') !== -1 && pOut.indexOf('# sage Hallo! für 2 Sekunden') !== -1 && pOut.indexOf('threading.Thread(target=figur1.wenn_flag_angeklickt).start()') !== -1, 'T107b Python-Ansicht: Klasse, Methode, Schleife und Blocktext-Kommentar (#140, #152)');
                  genProg.sprites[0].scripts[0].top.next = { op: 'gibts_nicht', id: 'bx', f: {}, sub: [], next: null };
                  var jOut2 = BLK.programToJava(genProg, 'Block Demo');
                  ok(jOut2.indexOf('Block ohne Textfassung') !== -1, 'T107c ein Block ohne Uebersetzung bleibt als Kommentar sichtbar (#140)');

                  /* Code-Dialog und Portierung (#140, #141) */
                  var blkUiCode = blkUi;
                  blkUiCode.openCodeDialog();
                  var rootIde = blkDoc.getElementById('blk-ide');
                  var dlgEl = null;
                  var di;
                  for (di = 0; di < rootIde.children.length; di++) {
                    if (rootIde.children[di].getAttribute('id') === 'blk-code-dialog') { dlgEl = rootIde.children[di]; }
                  }
                  ok(dlgEl && !dlgEl.hasAttribute('hidden'), 'T108 der Code-Dialog oeffnet sich ueber der Werkstatt (#140)');
                  var preEl = null;
                  for (di = 0; di < dlgEl.children.length; di++) {
                    if (dlgEl.children[di].getAttribute('id') === 'blk-code-view') { preEl = dlgEl.children[di]; }
                  }
                  ok(preEl && preEl.textContent.indexOf('public class') !== -1, 'T108b die Java-Ansicht zeigt das Programm als Klasse (#140)');
                  var pyTab = null;
                  var tabWrap = dlgEl.children[0].children[0];
                  for (di = 0; di < tabWrap.children.length; di++) {
                    if (tabWrap.children[di].textContent === 'Python') { pyTab = tabWrap.children[di]; }
                  }
                  if (pyTab) { pyTab.dispatch('click', {}); }
                  ok(pyTab && preEl.textContent.indexOf('def wenn_flag_angeklickt(self):') !== -1, 'T108c der Python-Reiter zeigt dieselben Skripte als Funktionen (#140)');
                  var portBtn = null;
                  var actRow = dlgEl.children[2];
                  for (di = 0; di < actRow.children.length; di++) {
                    if (actRow.children[di].textContent === 'Als neues Projekt übernehmen') { portBtn = actRow.children[di]; }
                  }
                  if (portBtn) { portBtn.dispatch('click', {}); }
                  var portForm = null;
                  for (di = 0; di < dlgEl.children.length; di++) {
                    if (dlgEl.children[di].tagName === 'FORM') { portForm = dlgEl.children[di]; }
                  }
                  ok(portForm && String(portForm.getAttribute('action')).indexOf('/werkstatt/code/blocks/port') !== -1, 'T108d die Portierung schickt an die Port-Route (#141)');
                  var pf = {};
                  if (portForm) {
                    for (di = 0; di < portForm.children.length; di++) {
                      pf[portForm.children[di].getAttribute('name')] = portForm.children[di].getAttribute('value');
                    }
                  }
                  ok(pf.language === 'python' && String(pf.title).indexOf('(Python)') !== -1 && String(pf.code).indexOf('class Figur1(Figur):') !== -1 && pf.csrf === 'tok', 'T108e das Port-Formular traegt Sprache, Titel, Code und Sicherheitsfeld (#141)');

                  /* T109-T112 (#144, #148, #150-#153): Lern-/Expertenmodus,
                     bunte Kategorie-Knoepfe, Konsole, OOP-Klassenmodell im
                     Code-Generator und sichtbares Ziehen mit Einfuege-Linie. */
                  ok(blkUi.isExpert() === false, 'T109 der Editor startet im Lernmodus (#151)');
                  ok(blkModeEl.getAttribute('data-active') === 'learn' && blkModeLearnEl.getAttribute('aria-pressed') === 'true' && blkModeLearnEl.classList.contains('active') === true && blkModeExpertEl.getAttribute('aria-pressed') === 'false' && blkModeExpertEl.classList.contains('active') === false, 'T109b der Schalter zeigt den Lernmodus als aktive Haelfte an (#161)');
                  ok(blkCodeEl.hasAttribute('hidden') && blkConsoleBox.hasAttribute('hidden') && blkVarAddEl.hasAttribute('hidden'), 'T109d im Lernmodus bleiben Code-Knopf, Konsole und Variablen-Knopf verborgen (#151, #162)');
                  var catColored = 0;
                  var c9;
                  for (c9 = 0; c9 < blkCatsEl.children.length; c9++) {
                    if (blkCatsEl.children[c9].style && blkCatsEl.children[c9].style.background) { catColored++; }
                  }
                  ok(catColored === blkCatsEl.children.length && blkCatsEl.children.length === 9, 'T109c jeder Kategorie-Knopf traegt seine Kategoriefarbe, im Lernmodus neun Stueck (#150, #162)');
                  var catLabels109 = [], btn109;
                  for (btn109 = 0; btn109 < blkCatsEl.children.length; btn109++) { catLabels109.push(blkCatsEl.children[btn109].textContent); }
                  ok(catLabels109.indexOf('Variablen') === -1, 'T109e im Lernmodus fehlt die Variablen-Kategorie (#162)');
                  ok(blkPalEl.children.length === 4 && blkPalEl.querySelector('.blk[data-pal-op="broadcast_wait"]') === null && blkPalEl.querySelector('.blk[data-pal-op="when_msg"]') === null && blkPalEl.querySelector('.blk[data-pal-op="broadcast"]') === null, 'T109f die Ereignis-Kategorie zeigt im Lernmodus nur die vier Basis-Bloecke, Nachrichten bleiben Experten-Sache (#151, #162, #132)');
                  blkFetchCalls.length = 0;
                  blkModeExpertEl.dispatch('click');
                  var blkModeCalls = blkFetchCalls.filter(function (mc) { return String(mc.url).indexOf('/einstellungen/werkstattmodus') !== -1; });
                  ok(blkUi.isExpert() === true && !blkCodeEl.hasAttribute('hidden') && !blkConsoleBox.hasAttribute('hidden') && !blkVarAddEl.hasAttribute('hidden'), 'T109g ein Klick auf die Experten-Haelfte schaltet um und blendet Code, Konsole und Variablen ein (#161)');
                  ok(blkModeCalls.length === 1 && blkModeCalls[0].body === 'csrf=tok&mode=expert', 'T109h der Wechsel in den Expertenmodus speichert die Wahl im Konto (POST mit mode=expert)');
                  ok(blkCatsEl.children.length === 10 && blkPalEl.children.length === 7 && blkPalEl.querySelector('.blk[data-pal-op="broadcast_wait"]') !== null, 'T109i im Expertenmodus sind alle zehn Kategorien und alle sieben Ereignis-Bloecke da (#151, #162, #138)');
                  blkModeExpertEl.dispatch('click');
                  ok(blkFetchCalls.length === 1, 'T109j ein Klick auf die bereits aktive Haelfte loest keinen neuen Speichervorgang aus (#161)');
                  blkUi.setMode(false);
                  ok(blkUi.isExpert() === false && blkFetchCalls.length === 1 && blkCatsEl.children.length === 9, 'T109k die programmatische Moduswahl wirkt sofort, ohne die Konto-Route aufzurufen (#161)');
                  blkUi.setMode(true);
                  blkCatsEl.children[7].dispatch('click');
                  ok(blkPalEl.querySelector('.blk[data-pal-op="var_set"]') !== null, 'T109l die Variablen-Kategorie zeigt im Expertenmodus ihre Bloecke (#162)');
                  blkFetchCalls.length = 0;
                  blkModeLearnEl.dispatch('click');
                  blkModeCalls = blkFetchCalls.filter(function (mc2) { return String(mc2.url).indexOf('/einstellungen/werkstattmodus') !== -1; });
                  ok(blkModeCalls.length === 1 && blkModeCalls[0].body === 'csrf=tok&mode=learn', 'T109m die Moduswahl wird je Konto gespeichert: der Ruecksprung in den Lernmodus geht als POST mit mode=learn (#161)');
                  ok(blkUi.isExpert() === false && blkPalEl.children.length === 4 && blkPalEl.querySelector('.blk[data-pal-op="var_set"]') === null, 'T109n beim Rueckschalten springt die Palette von der Experten-Kategorie auf die Ereignisse zurueck (#162)');
                  ok(blkModeLearnEl.getAttribute('aria-pressed') === 'true' && blkModeLearnEl.classList.contains('active') === true && blkModeExpertEl.getAttribute('aria-pressed') === 'false' && blkModeExpertEl.classList.contains('active') === false && blkModeEl.getAttribute('data-active') === 'learn', 'T109o der Schalter spiegelt den Lernmodus konsistent in aria-pressed und Aktvierung (#161)');
                  blkModeLearnEl.dispatch('click');
                  ok(blkFetchCalls.length === 1, 'T109p auch im Lernmodus bleibt ein Klick auf die aktive Haelfte ohne Speichervorgang (#161)');

                  function blkLogText() {
                    var t = '';
                    var ch = blkConsoleLogEl.children;
                    for (var li = 0; li < ch.length; li++) { t += ch[li].textContent + '\n'; }
                    return t;
                  }
                  blkUi.setMode(true);
                  blkUi.consoleRun('hilfe');
                  ok(blkLogText().indexOf('Befehle:') !== -1, 'T110 hilfe listet die Konsolen-Befehle im Protokoll (#152)');
                  blkUi.consoleRun('x 240');
                  blkUi.consoleRun('gehe 50');
                  blkUi.consoleRun('info');
                  ok(blkLogText().indexOf('geht 50 Schritte') !== -1 && blkLogText().indexOf('x 290') !== -1, 'T110b gehe 50 bewegt die ausgewaehlte Figur bis x 290 (#144, #152)');
                  blkUi.consoleRun('Figur 1.gehe 10');
                  blkUi.consoleRun('info');
                  ok(blkLogText().indexOf('x 300') !== -1, 'T110c mit dem Figurnamen steuert die Konsole gezielt diese Figur (#152)');
                  var errBefore = blkConsoleLogEl.querySelectorAll('.blk-console-err').length;
                  blkUi.consoleRun('huepfe 5');
                  ok(blkConsoleLogEl.querySelectorAll('.blk-console-err').length === errBefore + 1, 'T110d ein unbekannter Befehl landet als Fehlerzeile im Protokoll (#152)');
                  blkUi.consoleRun('x 100');
                  blkUi.consoleRun('info');
                  ok(blkLogText().indexOf('x 100') !== -1, 'T110e x 100 setzt die x-Position direkt (Ursprung oben links) (#144, #152)');

                  /* Sichtbares Ziehen (#153): der Block bleibt beim Ziehen im DOM */
                  var ui3 = blkDragFresh();
                  var dragEl3 = blkDragDoc.scriptEl.children[0].children[2];
                  dragEl3.dispatch('pointerdown', { clientX: 250, clientY: 200, pointerId: 1 });
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 212, pointerId: 1 });
                  blkDragDoc.setRects();
                  var midOps3 = blkDragOps(ui3);
                  var midDrag3 = blkDragDoc.scriptEl.querySelectorAll('.blk-dragging').length;
                  var midRows3 = blkDragDoc.scriptEl.children[0].children.length;
                  blkDragDoc._fire('pointerup', { clientX: 500, clientY: 400, pointerId: 1 });
                  blkDragDoc.restoreDoc();
                  ok(midOps3.length === 1 && midOps3[0] === 'flag,say_for,move_steps' && midDrag3 === 1 && midRows3 === 3, 'T111 beim Ziehen bleiben alle Bloecke sichtbar, nur der gezogene ist markiert (#153)');

                  /* Einfuege-Linie im Loop-Koerper (#148) */
                  var loopJson = JSON.stringify({
                    v: 2, vars: [], sprites: [{ id: 'sp1', name: 'Figur 1', costume: 'robot', x: 0, y: 0, dir: 90, size: 100, visible: true, scripts: [{
                      id: 's1', x: 24, y: 16,
                      top: { op: 'flag', id: 'b1', f: {}, sub: [],
                        next: { op: 'repeat', id: 'b2', f: { count: { v: '3' } }, sub: [
                          { op: 'move_steps', id: 'b3', f: { steps: { v: '10' } }, sub: [], next: { op: 'say_for', id: 'b4', f: { msg: { v: 'Hi' }, secs: { v: '1' } }, sub: [], next: null } }
                        ], next: null } }
                    }] }], stage: { scripts: [] }
                  });
                  var ui4 = blkDragFresh(loopJson);
                  var all4 = blkDragDoc.scriptEl.querySelectorAll('.blk');
                  blkDragDoc.setRectsDeep();
                  all4[3].dispatch('pointerdown', { clientX: 250, clientY: 234, pointerId: 1 });
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 190, pointerId: 1 });
                  blkDragDoc.setRectsDeep();
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 190, pointerId: 1 });
                  var hints4 = blkDragDoc.getElementById('blk-scriptwrap').querySelectorAll('.blk-drop-hint');
                  var hint4 = hints4.length > 0 ? hints4[hints4.length - 1] : null;
                  var hintCls4 = hint4 ? hint4.className : '';
                  var hintH4 = hint4 ? hint4.style.height : '';
                  blkDragDoc._fire('pointerup', { clientX: 250, clientY: 190, pointerId: 1 });
                  var sub4 = ui4.program.sprites[0].scripts[0].top.next.sub[0];
                  blkDragDoc.restoreDoc();
                  ok(hint4 !== null && hintCls4.indexOf('sub') !== -1 && hintH4 === '6px' && sub4.op === 'say_for', 'T111b die Einfuege-Linie zeigt im Koerper die genaue Stelle, hier den Anfang (#148)');

                  var ui5 = blkDragFresh(loopJson);
                  var all5 = blkDragDoc.scriptEl.querySelectorAll('.blk');
                  blkDragDoc.setRectsDeep();
                  all5[3].dispatch('pointerdown', { clientX: 250, clientY: 234, pointerId: 1 });
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 210, pointerId: 1 });
                  blkDragDoc.setRectsDeep();
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 210, pointerId: 1 });
                  blkDragDoc._fire('pointerup', { clientX: 250, clientY: 210, pointerId: 1 });
                  var sub5 = ui5.program.sprites[0].scripts[0].top.next.sub[0];
                  blkDragDoc.restoreDoc();
                  ok(sub5.op === 'move_steps' && sub5.next && sub5.next.op === 'say_for', 'T111c ueber der unteren Blockhaelfte fuegt der Editor dahinter statt davor ein (#148)');

                  /* OOP-Generator mit Buehne (#152) */
                  var stageProg = BLK.parseProgram(JSON.stringify({
                    v: 3, vars: [],
                    sprites: [{ name: 'Robo', costume: 'robot', x: 240, y: 180, dir: 90, scripts: [{ top: blkRaw('flag', {}, blkRaw('broadcast', { msg: 'los' })) }] }],
                    stage: { scripts: [{ top: blkRaw('flag', {}, blkRaw('broadcast', { msg: 'start' })) }] }
                  }));
                  var stageJava = BLK.programToJava(stageProg, 'Buehnen Demo');
                  ok(stageJava.indexOf('static class Buehne extends Figur') !== -1 && stageJava.indexOf('Buehne buehne = new Buehne();') !== -1 && stageJava.indexOf('new Thread(() -> buehne.wenn_flag_angeklickt()).start();') !== -1, 'T112 die Buehne wird im Java-Code zur eigenen Klasse mit eigenem Objekt (#152)');
                  var stagePy = BLK.programToPython(stageProg, 'Buehnen Demo');
                  ok(stagePy.indexOf('class Buehne(Figur):') !== -1 && stagePy.indexOf('buehne = Buehne()') !== -1 && stagePy.indexOf('threading.Thread(target=buehne.wenn_flag_angeklickt).start()') !== -1, 'T112b die Buehne wird im Python-Code zur eigenen Klasse mit eigenem Objekt (#152)');
                  var plainProg = BLK.parseProgram(JSON.stringify({
                    v: 3, vars: [], sprites: [{ name: 'Robo', costume: 'robot', x: 240, y: 180, dir: 90, scripts: [] }], stage: { scripts: [] }
                  }));
                  var plainJava = BLK.programToJava(plainProg, 'Leer');
                  var plainPy = BLK.programToPython(plainProg, 'Leer');
                  ok(plainJava.indexOf('static class Robo extends Figur') !== -1 && plainJava.indexOf('Robo robo = new Robo();') !== -1 && plainPy.indexOf('class Robo(Figur):') !== -1 && plainPy.indexOf('robo = Robo()') !== -1 && plainJava.indexOf('class Buehne') === -1 && plainPy.indexOf('class Buehne') === -1, 'T112c jede Figur wird zum Objekt, auch ohne Skripte; ohne Buehnenskript entsteht keine Buehnen-Klasse (#152)');

                  /* T113-T113c (Optimierungen): Zieh-Geist per transform,
                     gedeckeltes Konsolenprotokoll, Blasenbreiten-Cache. */
                  var uiO = blkDragFresh();
                  var dragElO = blkDragDoc.scriptEl.children[0].children[2];
                  dragElO.dispatch('pointerdown', { clientX: 250, clientY: 200, pointerId: 1 });
                  blkDragDoc._fire('pointermove', { clientX: 250, clientY: 212, pointerId: 1 });
                  blkDragDoc.setRects();
                  blkDragDoc._fire('pointermove', { clientX: 300, clientY: 240, pointerId: 1 });
                  blkDragDoc.setRects();
                  var ghostO = blkDragDoc.body.querySelector('.blk-ghost');
                  var ghostTransform = ghostO && ghostO.style && String(ghostO.style.transform || '');
                  blkDragDoc._fire('pointerup', { clientX: 500, clientY: 400, pointerId: 1 });
                  blkDragDoc.restoreDoc();
                  ok(ghostO !== null && ghostTransform.indexOf('translate(') === 0 && ghostTransform.indexOf('px') !== -1 && !ghostO.style.left && !ghostO.style.top, 'T113 der gezogene Block folgt dem Zeiger per transform statt left/top, ohne Bildaufbau (Optimierung)');

                  blkUi.setMode(true);
                  var iLog;
                  for (iLog = 0; iLog < 260; iLog++) { blkUi.consoleRun('x ' + (10 + iLog)); }
                  ok(blkConsoleLogEl.children.length === 200, 'T113b das Konsolenprotokoll bleibt bei 200 Zeilen gedeckelt (Optimierung)');

                  var mtCalls = 0;
                  blkCtrEl.__ctx.measureText = function () { mtCalls++; return { width: 44 }; };
                  blkUi.consoleRun('sage Messtext A!');
                  var mtA = mtCalls;
                  blkUi.consoleRun('sage Messtext A!');
                  blkUi.consoleRun('info');
                  var mtB = mtCalls;
                  blkUi.consoleRun('sage Messtext B!');
                  var mtC = mtCalls;
                  ok(mtA >= 1 && mtB === mtA && mtC === mtA + 1, 'T113c die Blasenbreite wird pro Blasentext nur einmal gemessen (Optimierung)');

                  /* T114-T114r (#163): Automaten-Werkstatt. Die Engine ist
                   * DOM-frei, deshalb laesst sie sich direkt am Modul
                   * pruefen. */
                  var AMT = require('../public/assets/js/automaton.js');
                  var amtEx = AMT.exampleAutomaton();
                  ok(AMT !== null && amtEx.states.length === 3 && amtEx.transitions.length === 3, 'T114 die Automaten-Engine liefert den Beispiel-DEA');
                  ok(AMT.simulateAutomaton(amtEx, 'aa').accepted === true, 'T114b das Wort aa ist im Beispiel-DEA akzeptiert');
                  ok(AMT.simulateAutomaton(amtEx, 'abbba').accepted === true, 'T114c das Wort abbba ist im Beispiel-DEA akzeptiert');
                  ok(AMT.simulateAutomaton(amtEx, '').accepted === false, 'T114d das leere Wort ist im Beispiel-DEA nicht akzeptiert');
                  ok(AMT.simulateAutomaton(amtEx, 'ab').accepted === false, 'T114e das Wort ab endet nicht im Endzustand');
                  var amtBa = AMT.simulateAutomaton(amtEx, 'ba');
                  ok(amtBa.accepted === false && amtBa.stuck === 0 && amtBa.reason.indexOf('keinen Uebergang') !== -1, 'T114f bei ba bleibt der Automat schon vor dem ersten Zeichen stecken');
                  var amtC = AMT.simulateAutomaton(amtEx, 'c');
                  ok(amtC.accepted === false && amtC.reason.indexOf('nicht zum Eingabealphabet') !== -1, 'T114g ein Zeichen ausserhalb des Alphabets bleibt ohne Uebergang');
                  var amtAn = AMT.analyseAutomaton(amtEx);
                  ok(amtAn.missing === 3 && amtAn.nd.length === 0 && amtAn.sigma.join('') === 'ab', 'T114h die Analyse zaehlt drei fehlende Uebergaenge und erkennt Determinismus');
                  var amtNorm = AMT.normalizeAutomaton(JSON.parse(JSON.stringify(amtEx)));
                  ok(amtNorm !== null && amtNorm.states.length === 3 && amtNorm.transitions.length === 3 && AMT.normalizeAutomaton({ kaputt: 1 }) === null, 'T114i normalizeAutomaton laedt gueltige Automaten und lehnt ungueltige ab');
                  var amtTrap = AMT.applyTrap(amtEx);
                  ok(amtTrap.added === 3 && amtTrap.automaton.states.length === 4, 'T114j der Trap-Zustand ergaenzt die drei fehlenden Uebergaenge');
                  var amtTrapState = amtTrap.automaton.states.filter(function (ts) { return ts.trap; })[0];
                  var amtTrapLoop = false, at2;
                  for (at2 = 0; at2 < amtTrap.automaton.transitions.length; at2++) {
                    var tt2 = amtTrap.automaton.transitions[at2];
                    if (tt2.from === amtTrapState.id && tt2.to === amtTrapState.id && tt2.symbols.length === 2) { amtTrapLoop = true; }
                  }
                  ok(AMT.analyseAutomaton(amtTrap.automaton).missing === 0 && amtTrapLoop, 'T114k nach dem Trap ist der Automat vollstaendig, mit eigener Schleife am Trap');
                  var amtSer = AMT.serializeAutomaton(amtEx);
                  ok(amtSer.format === 'endlicher-automat' && amtSer.version === 1, 'T114l das Speicherformat nennt Format und Version');
                  var amtScene = AMT.sceneMarkup(amtEx, { interactive: true, P: AMT.EXPORT_PAL });
                  ok(amtScene.indexOf('data-kind="state"') !== -1 && amtScene.indexOf('data-kind="trans"') !== -1, 'T114m die Zeichnung markiert Zustaende und Uebergaenge als Klickziele');
                  ok((amtScene.match(/cx="480"/g) || []).length === 2, 'T114n der Endzustand erhaelt seinen Doppelkreis');
                  var amtSvg = AMT.exportSvgString(amtEx);
                  ok(amtSvg.str.indexOf('<svg xmlns') === 0 && amtSvg.w > 0 && amtSvg.h > 0, 'T114o der Bild-Export liefert ein fertiges SVG');
                  ok(AMT.tupleText(amtEx).indexOf('F = {q2}') !== -1, 'T114p die formale Beschreibung nennt die Endzustandsmenge');
                  ok(AMT.effectiveAlphabet(amtEx).join('') === 'ab' && AMT.missingPairs(amtEx, ['a', 'b']).length === 2, 'T114q effektives Alphabet und fehlende Uebergaenge stimmen ueberein');
                  var amtLoop = AMT.edgeGeom(amtEx, { id: 'x', from: 's2', to: 's2', symbols: ['b'] });
                  ok(amtLoop !== null && amtLoop.d.indexOf('C') !== -1, 'T114r die Schleifen-Geometrie zeichnet eine Kurve');

                  /* T116-T116s (#132/#137/#138/#139/#142/#143): Klone,
                   * Listen, eigene Baeusteine, Hintergruende, Vorlagen
                   * und Vollbild. Erst Registry und Editor-Oberflaeche,
                   * dann die DOM-freie Laufzeit. */
                  ok(BLK.BLOCKS.when_clone && BLK.BLOCKS.clone_create && BLK.BLOCKS.clone_delete &&
                     BLK.BLOCKS.list_add && BLK.BLOCKS.list_del && BLK.BLOCKS.list_set &&
                     BLK.BLOCKS.list_get && BLK.BLOCKS.list_len && BLK.BLOCKS.list_has &&
                     BLK.BLOCKS.proc_def && BLK.BLOCKS.proc_call &&
                     BLK.BLOCKS.backdrop_switch && BLK.BLOCKS.backdrop_next, 'T116 die Registry kennt Klone, Listen, eigene Baeusteine und Hintergruende (#132, #137, #138, #139)');
                  blkUi.setMode(false);
                  ok(hidden(blkListAddEl) && !hidden(blkProcAddEl), 'T116b der Listen-Knopf fehlt im Lernmodus, der Baustein-Knopf bleibt (#137, #138)');
                  ok(blkUi.addList('einkauf') === true && blkUi.program.lists.indexOf('einkauf') !== -1, 'T116c eine Liste laesst sich anlegen (#137)');
                  ok(blkUi.addList('einkauf') === false && blkUi.addList('') === false, 'T116d doppelte und leere Namen bleiben draussen (#137)');
                  ok(blkUi.addProc('Springen') === true && blkUi.target().procs.indexOf('Springen') !== -1, 'T116e ein Baustein laesst sich an der Figur anlegen (#138)');
                  ok(blkUi.templateCount() === 4, 'T116f der Editor haelt vier Beispielprogramme bereit (#142)');
                  function simFindById(rootEl, wantId) {
                    if (!rootEl) { return null; }
                    if (rootEl.getAttribute && rootEl.getAttribute('id') === wantId) { return rootEl; }
                    for (var sf = 0; sf < rootEl.children.length; sf++) {
                      var hitSf = simFindById(rootEl.children[sf], wantId);
                      if (hitSf) { return hitSf; }
                    }
                    return null;
                  }
                  blkTemplatesBtn.dispatch('click');
                  var blkTplBoxEl = simFindById(blkIdeEl, 'blk-templates-box');
                  ok(blkTplBoxEl !== null && !blkTplBoxEl.hasAttribute('hidden'), 'T116g der Vorlagen-Knopf oeffnet die Auswahl (#142)');
                  ok(blkTplBoxEl.querySelectorAll('.blk-templates-row').length === 4, 'T116h die Auswahl zeigt vier Vorlagen mit Laden-Knopf (#142)');
                  var blkTplCloseEl = blkTplBoxEl.querySelector('.blk-x');
                  if (blkTplCloseEl) { blkTplCloseEl.dispatch('click'); }
                  ok(blkTplBoxEl.hasAttribute('hidden'), 'T116i der Schliessen-Knopf versteckt die Vorlagen (#142)');
                  ok(blkUi.isFullscreen() === false, 'T116j die Werkstatt startet ohne Vollbild (#143)');
                  ok(blkUi.fullscreenOn() === true && blkUi.isFullscreen() === true && simFindById(blkIdeEl, 'blk-fullbox') !== null, 'T116k der Vollbild-Knopf holt die Buehne in ein eigenes Lager (#143)');
                  ok(blkUi.fullscreenOff() === true && blkUi.isFullscreen() === false && simFindById(blkIdeEl, 'blk-fullbox') === null, 'T116l Beenden loest das Vollbild wieder auf (#143)');

                  /* Laufzeit der neuen Bloecke (DOM-frei, wie die Engine). */
                  var uidB = 1000;
                  function rawB(op, fields, next, subs) {
                    return { op: op, id: op + '_' + (++uidB), f: fields || {}, sub: subs || [], next: next || null };
                  }
                  function chainB(items) {
                    var node = null;
                    for (var ibC = items.length - 1; ibC >= 0; ibC--) { node = rawB(items[ibC][0], items[ibC][1], node, items[ibC][2] || []); }
                    return node;
                  }
                  function progB(scripts, extra) {
                    extra = extra || {};
                    return BLK.parseProgram(JSON.stringify({
                      v: 3, vars: extra.vars || [], lists: extra.lists || [],
                      sprites: extra.sprites || [{ id: 'sp1', name: 'F1', costume: 'robot', scripts: scripts, procs: extra.procs || [] }],
                      stage: { scripts: extra.stageScripts || [], procs: extra.stageProcs || [], backdrop: extra.backdrop || 'paper' }
                    }));
                  }
                  function scrB(top) { return { id: 'sc' + (++uidB), x: 0, y: 0, top: top }; }
                  function frB(rt, n) { for (var fb = 0; fb < n; fb++) { rt.frame(100 * fb + 100); } }

                  var rtL = BLK.newRuntime(progB([scrB(chainB([
                    ['flag'], ['list_add', { list: { v: 'einkauf' }, value: { v: 'Milch' } }],
                    ['list_add', { list: { v: 'einkauf' }, value: { v: 'Brot' } }],
                    ['var_set', { name: { v: 'ergebnis' }, value: { b: rawB('list_get', { list: { v: 'einkauf' }, n: { v: '2' } }, null, []) } }]
                  ]))], { vars: ['ergebnis'], lists: ['einkauf'] }));
                  rtL.flag(100); frB(rtL, 3);
                  ok(rtL.vars.ergebnis === 'Brot' && rtL.lists.einkauf.length === 2 && rtL.errors.length === 0, 'T116m Listen fuellen, lesen und zaehlen ohne Fehler (#137)');
                  var rtL2 = BLK.newRuntime(progB([scrB(chainB([
                    ['flag'], ['list_add', { list: { v: 'L' }, value: { v: 'x' } }],
                    ['list_add', { list: { v: 'L' }, value: { v: 'y' } }],
                    ['list_set', { list: { v: 'L' }, n: { v: '2' }, value: { v: 'z' } }],
                    ['list_del', { list: { v: 'L' }, n: { v: '1' } }],
                    ['var_set', { name: { v: 'laenge' }, value: { b: rawB('list_len', { list: { v: 'L' } }, null, []) } }],
                    ['var_set', { name: { v: 'drin' }, value: { b: rawB('list_has', { list: { v: 'L' }, value: { v: 'z' } }, null, []) } }]
                  ]))], { vars: ['laenge', 'drin'], lists: ['L'] }));
                  rtL2.flag(100); frB(rtL2, 3);
                  ok(JSON.stringify(rtL2.lists.L) === '["z"]' && rtL2.vars.laenge === 1 && rtL2.vars.drin === true, 'T116n Eintraege setzen, loeschen, laengen und suchen (#137)');
                  var serB = BLK.serializeProgram(progB([], { lists: ['A'], procs: ['Springen'], stageProcs: ['Intro'], backdrop: 'sea' }));
                  var parB = BLK.parseProgram(JSON.stringify(serB));
                  ok(serB.lists.length === 2 || (serB.lists.length === 1 && serB.lists[0] === 'A'), 'T116o das Speicherformat nimmt Listen und Bausteine auf (#137, #138)');
                  ok(parB.lists.indexOf('A') !== -1 && parB.sprites[0].procs.indexOf('Springen') !== -1 && parB.stage.procs.indexOf('Intro') !== -1 && parB.stage.backdrop === 'sea', 'T116p Laden ergibt Listen, Figuren- und Buehnen-Bausteine und Hintergrund zurueck (#137, #138, #139)');
                  var rtP = BLK.newRuntime(progB([
                    scrB(chainB([['flag'], ['proc_call', { name: { v: 'hoch' } }], ['proc_call', { name: { v: 'hoch' } }]])),
                    scrB(chainB([['proc_def', { name: { v: 'hoch' } }], ['var_change', { name: { v: 'zaehler' }, d: { v: '5' } }]]))
                  ], { vars: ['zaehler'] }));
                  rtP.flag(100); frB(rtP, 3);
                  ok(rtP.vars.zaehler === 10 && rtP.errors.length === 0, 'T116q ein Baustein springt zweimal zum Rueckpunkt zurueck (#138)');
                  var rtP2 = BLK.newRuntime(progB([
                    scrB(chainB([['flag'], ['repeat', { count: { v: '3' } }, [chainB([['proc_call', { name: { v: 'hoch' } }]])]]])),
                    scrB(chainB([['proc_def', { name: { v: 'hoch' } }], ['var_change', { name: { v: 'zaehler' }, d: { v: '5' } }]]))
                  ], { vars: ['zaehler'] }));
                  rtP2.flag(100); frB(rtP2, 6);
                  ok(rtP2.vars.zaehler === 15 && rtP2.errors.length === 0, 'T116r der Baustein-Aufruf in einer Wiederholung springt jedes Mal zurueck (#138)');
                  var rtP3 = BLK.newRuntime(progB([
                    scrB(chainB([['flag'], ['proc_call', { name: { v: 'A' } }]])),
                    scrB(chainB([['proc_def', { name: { v: 'A' } }], ['proc_call', { name: { v: 'B' } }], ['var_change', { name: { v: 'z' }, d: { v: '1' } }]])),
                    scrB(chainB([['proc_def', { name: { v: 'B' } }], ['var_change', { name: { v: 'z' }, d: { v: '10' } }]]))
                  ], { vars: ['z'] }));
                  rtP3.flag(100); frB(rtP3, 4);
                  ok(rtP3.vars.z === 11 && rtP3.errors.length === 0, 'T116s verschachtelte Bausteine kehren auf allen Ebenen zurueck (#138)');

                  /* T116t-T116z (#132/#139): Klone und Hintergruende. */
                  var rtK = BLK.newRuntime(progB([
                    scrB(chainB([['flag'], ['clone_create', { name: { v: '' } }]])),
                    scrB(chainB([['when_clone'], ['clone_delete']]))
                  ]));
                  rtK.flag(100); frB(rtK, 3);
                  ok(rtK.sprites.length === 1 && rtK.errors.length === 0, 'T116t ein Klon startet sein Skript und loescht sich selbst (#132)');
                  var rtK2 = BLK.newRuntime(progB([], { sprites: [
                    { id: 'sp1', name: 'Robot', costume: 'robot', scripts: [scrB(chainB([['flag'], ['clone_create', { name: { v: 'sp2' } }]]))] },
                    { id: 'sp2', name: 'Fisch', costume: 'fish', scripts: [scrB(chainB([['when_clone'], ['change_x', { dx: { v: '40' } }]]))] }
                  ] }));
                  rtK2.flag(100); frB(rtK2, 3);
                  ok(rtK2.sprites.length === 3 && rtK2.sprites[2].isClone === true && rtK2.sprites[2].x === 280 && rtK2.sprites[1].x === 240, 'T116u der Klon einer anderen Figur laeuft deren Skript, das Original bleibt unberuehrt (#132)');
                  var rtK3 = BLK.newRuntime(progB([scrB(chainB([['flag'], ['repeat', { count: { v: '350' } }, [chainB([['clone_create', { name: { v: '' } }]])]]]))]));
                  rtK3.flag(100); frB(rtK3, 400);
                  ok(rtK3.sprites.length === 301 && rtK3.errors.length === 1 && /Klon-Grenze/.test(rtK3.errors[0]), 'T116v bei 300 Klonen stoppt die Werkstatt mit einer klaren Meldung (#132)');
                  var rtD = BLK.newRuntime(progB([scrB(chainB([['flag'], ['backdrop_switch', { name: { v: 'sea' } }], ['backdrop_next']]))]));
                  ok(rtD.backdrop() === 'paper', 'T116w die Buehne startet auf Papier (#139)');
                  rtD.flag(100); frB(rtD, 2);
                  ok(rtD.backdrop() === 'meadow', 'T116x Hintergrund wechseln und weiterblättern (#139)');
                  var codeB = progB([scrB(chainB([['flag'], ['proc_call', { name: { v: 'Los' } }], ['clone_create', { name: { v: '' } }], ['list_add', { list: { v: 'L' }, value: { v: '1' } }]]))], { lists: ['L'] });
                  ok(/rufe_baustein/.test(BLK.programToJava(codeB)) && /erzeuge_klon_von/.test(BLK.programToJava(codeB)) && /fuege_hinzu/.test(BLK.programToJava(codeB)), 'T116y die Java-Ansicht uebersetzt Baustein, Klon und Liste (#132, #137, #138)');
                  ok(/rufe_baustein/.test(BLK.programToPython(codeB)), 'T116z die Python-Ansicht uebersetzt den Baustein-Aufruf (#138)');

                  /* T117-T117o (#164): Kellerautomat. Engine ist DOM-frei. */
                  var IC_PDA = require('../public/assets/js/pda.js');
                  var pdaEx = IC_PDA.examplePDA();
                  ok(pdaEx.alphabet.length === 2 && pdaEx.stack0 === 'Z' && pdaEx.accept === 'state' && pdaEx.states.length === 3, 'T117 der Kellerautomat liefert das Beispiel a hoch n b hoch n (#164)');
                  ok(IC_PDA.pdaRun(pdaEx, 'aabb').accepted === true, 'T117b aabb ist mit Keller akzeptiert (#164)');
                  ok(IC_PDA.pdaRun(pdaEx, 'aaabbb').accepted === true, 'T117c aaabbb ist mit Keller akzeptiert (#164)');
                  ok(IC_PDA.pdaRun(pdaEx, 'aab').accepted === false && IC_PDA.pdaRun(pdaEx, 'abba').accepted === false && IC_PDA.pdaRun(pdaEx, 'aabbbb').accepted === false, 'T117d zu kurze, falsche und zu lange Woerter werden abgelehnt (#164)');
                  ok(IC_PDA.pdaRun(pdaEx, 'ε').accepted === true, 'T117e das leere Wort fuehrt per epsilon in den Endzustand (#164)');
                  var pdaSteps = IC_PDA.pdaRun(pdaEx, 'aabb').steps;
                  ok(pdaSteps.length === 6 && pdaSteps[0].stack === 'Z' && pdaSteps[5].state === 'q2', 'T117f die Schritteliste zeigt Zustand und Keller (#164)');
                  var pdaAn = IC_PDA.analysePDA(pdaEx);
                  ok(pdaAn.sigma.join('') === 'ab' && pdaAn.finals.length === 1 && pdaAn.epsMoves === 2, 'T117g die Analyse nennt Alphabet, Endzustaende und epsilon-Uebergaenge (#164)');
                  ok(IC_PDA.normalizePDA({ states: pdaEx.states, transitions: pdaEx.transitions, stack0: 'Z', accept: 'stack' }).accept === 'stack', 'T117h die Akzeptanz laesst sich auf leeren Keller umstellen (#164)');
                  ok(IC_PDA.normalizePDA({ states: pdaEx.states, transitions: [{ from: 'qX', to: 'q0', read: 'a', push: 'A' }].concat(pdaEx.transitions), stack0: 'Z' }).transitions.length === pdaEx.transitions.length, 'T117i kaputte Referenzen fallen beim Normalisieren raus (#164)');
                  var pdaSvg = IC_PDA.exportSvgString(pdaEx);
                  ok(pdaSvg.str.indexOf('<svg') === 0 && pdaSvg.str.indexOf('a, ε → A') !== -1, 'T117j der Bild-Export zeichnet die Keller-Beschriftung (#164)');
                  var pdaParsed = IC_PDA.parsePDA(JSON.stringify(IC_PDA.serializePDA(pdaEx)));
                  ok(pdaParsed.accept === 'state' && pdaParsed.transitions.length === 5, 'T117k Speichern und Laden ergibt denselben Automaten (#164)');

                  /* T117l-T117o: Boot am Fake-Geruest wie auf der Seite. */
                  var pdaDoc = new FakeDoc();
                  function simReg(doc, id, tag) {
                    var e = doc.createElement(tag || 'div');
                    doc.register(e, id);
                    return e;
                  }
                  var pdaRootEl = simReg(pdaDoc, 'pda-ide');
                  var pdaCanvasEl = simReg(pdaDoc, 'pda-canvas');
                  var pdaHintEl = simReg(pdaDoc, 'pda-hint');
                  var pdaSelEl = simReg(pdaDoc, 'pda-sel');
                  var pdaOutEl = simReg(pdaDoc, 'pda-out');
                  var pdaWordEl = simReg(pdaDoc, 'pda-word', 'input');
                  var pdaRunBtn = simReg(pdaDoc, 'pda-run', 'button');
                  var pdaStack0El = simReg(pdaDoc, 'pda-stack0', 'input');
                  var pdaJsonEl = simReg(pdaDoc, 'pda-json', 'textarea');
                  pdaJsonEl.value = JSON.stringify(IC_PDA.serializePDA(pdaEx));
                  var pdaUi = IC_PDA.attach(pdaDoc);
                  ok(pdaUi !== null && String(pdaCanvasEl.innerHTML).indexOf('q0') !== -1, 'T117l die Werkstatt zeichnet den geladenen Automaten (#164)');
                  ok(pdaStack0El.value === 'Z', 'T117m das Bodenzeichen steht im Feld (#164)');
                  pdaWordEl.value = 'aabb';
                  pdaRunBtn.dispatch('click');
                  ok(String(pdaOutEl.textContent).indexOf('akzeptiert') !== -1 && String(pdaOutEl.textContent).indexOf('Keller') !== -1, 'T117n der Pruef-Knopf zeigt Urteil und Kellerschritte (#164)');
                  pdaWordEl.value = 'aab';
                  pdaRunBtn.dispatch('click');
                  ok(String(pdaOutEl.textContent).indexOf('✗') !== -1, 'T117o ein abgelehntes Wort zeigt das Kreuz (#164)');

                  /* T118-T118l (#165): Turingmaschine. */
                  var IC_TM = require('../public/assets/js/turing.js');
                  var tmEx = IC_TM.exampleTM();
                  ok(tmEx.rules.length === 6 && tmEx.states.length === 3 && tmEx.blank === '_', 'T118 die Turingmaschine liefert das Inkrement-Beispiel (#165)');
                  var tmR1 = IC_TM.tmRun(tmEx, '1011');
                  var tmTape1 = '';
                  var tmPos1, tmMax1 = -99, tmMin1 = 99;
                  for (tmPos1 in tmR1.tape) { if (tmR1.tape[tmPos1] !== '_') { tmMax1 = Math.max(tmMax1, parseInt(tmPos1, 10)); tmMin1 = Math.min(tmMin1, parseInt(tmPos1, 10)); } }
                  for (tmPos1 = tmMin1; tmPos1 <= tmMax1; tmPos1++) { tmTape1 += (tmR1.tape[tmPos1] != null ? tmR1.tape[tmPos1] : '_'); }
                  ok(tmR1.accepted === true && tmTape1 === '1100', 'T118b 1011 wird zu 1100 inkrementiert (#165)');
                  var tmR2 = IC_TM.tmRun(tmEx, '111');
                  var tmTape2 = '';
                  var tmPos2, tmMax2 = -99, tmMin2 = 99;
                  for (tmPos2 in tmR2.tape) { if (tmR2.tape[tmPos2] !== '_') { tmMax2 = Math.max(tmMax2, parseInt(tmPos2, 10)); tmMin2 = Math.min(tmMin2, parseInt(tmPos2, 10)); } }
                  for (tmPos2 = tmMin2; tmPos2 <= tmMax2; tmPos2++) { tmTape2 += (tmR2.tape[tmPos2] != null ? tmR2.tape[tmPos2] : '_'); }
                  ok(tmR2.accepted === true && tmTape2 === '1000', 'T118c der Uebertrag laeuft ins neue Feld: 111 wird 1000 (#165)');
                  ok(IC_TM.tmRun(tmEx, 'ε').accepted === true && IC_TM.tmRun(tmEx, '1011').steps.length === 9, 'T118d das leere Wort laeuft mit, die Schrittliste zaehlt neun (#165)');
                  var tmAn = IC_TM.analyseTM(tmEx);
                  ok(tmAn.finals[0] === 'qf' && tmAn.nd.length === 0, 'T118e die Analyse erkennt den Endzustand und Determinismus (#165)');
                  var tmNd = IC_TM.normalizeTM({ states: tmEx.states, rules: tmEx.rules.concat([{ from: 'q0', to: 'qf', read: '0', write: '0', move: 'R' }]) });
                  ok(IC_TM.analyseTM(tmNd).nd.length === 1, 'T118f zwei Regeln fuer dasselbe Zeichen werden als Konflikt gemeldet (#165)');
                  var tmSvg = IC_TM.exportSvgString(tmEx);
                  ok(tmSvg.str.indexOf('<svg') === 0 && tmSvg.str.indexOf('0 / 0, R') !== -1, 'T118g der Bild-Export zeichnet die Regel-Beschriftung (#165)');
                  var tmParsed = IC_TM.parseTM(JSON.stringify(IC_TM.serializeTM(tmEx)));
                  ok(tmParsed.rules.length === 6 && tmParsed.blank === '_', 'T118h Speichern und Laden ergibt dieselbe Maschine (#165)');
                  var tmDoc = new FakeDoc();
                  var tmRootEl2 = simReg(tmDoc, 'tm-ide');
                  var tmCanvasEl = simReg(tmDoc, 'tm-canvas');
                  var tmOutEl = simReg(tmDoc, 'tm-out');
                  var tmWordEl = simReg(tmDoc, 'tm-word', 'input');
                  var tmRunBtn = simReg(tmDoc, 'tm-run', 'button');
                  var tmStepBtn = simReg(tmDoc, 'tm-step', 'button');
                  var tmJsonEl = simReg(tmDoc, 'tm-json', 'textarea');
                  tmJsonEl.value = JSON.stringify(IC_TM.serializeTM(tmEx));
                  var tmUi = IC_TM.attach(tmDoc);
                  ok(tmUi !== null && String(tmCanvasEl.innerHTML).indexOf('q0') !== -1, 'T118i die Werkstatt zeichnet die geladene Maschine (#165)');
                  tmWordEl.value = '1011';
                  tmRunBtn.dispatch('click');
                  ok(String(tmOutEl.textContent).indexOf('akzeptiert') !== -1 && String(tmOutEl.textContent).indexOf('9') !== -1, 'T118j der Lauf-Knopf zeigt Urteil und Schrittzahl (#165)');
                  tmStepBtn.dispatch('click');
                  ok(String(tmOutEl.textContent).indexOf('Zustand') !== -1 && String(tmOutEl.textContent).indexOf('‣') !== -1, 'T118k der Schritt-Knopf zeigt Bandfenster und Kopf (#165)');

                  /* T119-T119n (#166): Schaltwerk. */
                  var IC_LOGIC = require('../public/assets/js/logic.js');
                  var lcEx = IC_LOGIC.exampleCircuit();
                  var lcEv0 = IC_LOGIC.evalCircuit(JSON.parse(JSON.stringify(lcEx)));
                  ok(lcEv0.stable === true && lcEv0.lamps.length === 2 && lcEv0.values.lS === 0 && lcEv0.values.lC === 0, 'T119 der Halbaddierer bewertet 0 plus 0 zu S=0, C=0 (#166)');
                  function lcWith(a, b) {
                    var c = JSON.parse(JSON.stringify(lcEx));
                    c.comps[0].state = a; c.comps[1].state = b;
                    return IC_LOGIC.evalCircuit(c);
                  }
                  ok(lcWith(1, 0).values.lS === 1 && lcWith(1, 0).values.lC === 0, 'T119b 1 plus 0 ergibt Summe ohne Uebertrag (#166)');
                  ok(lcWith(1, 1).values.lS === 0 && lcWith(1, 1).values.lC === 1, 'T119c 1 plus 1 ergibt den Uebertrag (#166)');
                  ok(lcWith(0, 1).values.lS === 1, 'T119d 0 plus 1 ergibt die Summe (#166)');
                  var lcNand = IC_LOGIC.parseCircuit(JSON.stringify({
                    comps: [
                      { id: 'a', kind: 'switch', x: 60, y: 60, state: 1 },
                      { id: 'n', kind: 'not', x: 200, y: 60 },
                      { id: 'b', kind: 'switch', x: 60, y: 160, state: 0 },
                      { id: 'd', kind: 'nand', x: 340, y: 60 },
                      { id: 'l', kind: 'lamp', x: 480, y: 60, label: 'X' }
                    ],
                    wires: [
                      { id: 'w1', from: 'a', to: 'n' }, { id: 'w2', from: 'n', to: 'd' },
                      { id: 'w3', from: 'b', to: 'd' }, { id: 'w4', from: 'd', to: 'l' }
                    ]
                  }));
                  var lcNandEv = IC_LOGIC.evalCircuit(lcNand);
                  ok(lcNandEv.values.n === 0 && lcNandEv.values.d === 1 && lcNandEv.values.l === 1, 'T119e NICHT und NAND verketten ueber Drähte (#166)');
                  var lcDff = IC_LOGIC.parseCircuit(JSON.stringify({
                    comps: [
                      { id: 'd', kind: 'switch', x: 60, y: 60, state: 1 },
                      { id: 'f', kind: 'dff', x: 220, y: 60 },
                      { id: 'l', kind: 'lamp', x: 400, y: 60 }
                    ],
                    wires: [ { id: 'w1', from: 'd', to: 'f' }, { id: 'w2', from: 'f', to: 'l' } ]
                  }));
                  var lcTick1 = IC_LOGIC.tickCircuit(lcDff);
                  ok(IC_LOGIC.evalCircuit(lcTick1).values.f === 1 && IC_LOGIC.evalCircuit(lcTick1).values.l === 1, 'T119f das D-Flipflop uebernimmt D beim Takt (#166)');
                  lcTick1.comps[0].state = 0;
                  ok(IC_LOGIC.evalCircuit(lcTick1).values.f === 1, 'T119g zwischen den Takten haelt das Flipflop seinen Zustand (#166)');
                  var lcTick2 = IC_LOGIC.tickCircuit(lcTick1);
                  ok(IC_LOGIC.evalCircuit(lcTick2).values.f === 0, 'T119h beim naechsten Takt folgt das Flipflop dem neuen D (#166)');
                  var lcClk = IC_LOGIC.parseCircuit(JSON.stringify({
                    comps: [ { id: 'c', kind: 'clock', state: 0 }, { id: 'l', kind: 'lamp' } ],
                    wires: [ { id: 'w', from: 'c', to: 'l' } ]
                  }));
                  var lcRun = IC_LOGIC.runCircuit(lcClk, 4);
                  ok(lcRun.states.length === 4 && lcRun.states[0].values.l === 0 && lcRun.states[1].values.l === 1 && lcRun.states[2].values.l === 0, 'T119i der Taktgeber blinkt die Lampe (#166)');
                  var lcAn = IC_LOGIC.analyseCircuit(lcEx);
                  ok(lcAn.counts.switch === 2 && lcAn.wires === 6, 'T119j die Analyse zaehlt Bausteine und Drähte (#166)');
                  var lcSvg = IC_LOGIC.exportSvgString(lcEx);
                  ok(lcSvg.str.indexOf('<svg') === 0 && lcSvg.str.indexOf('XOR') !== -1, 'T119k der Bild-Export zeichnet die Gatter (#166)');
                  var lcParsed = IC_LOGIC.parseCircuit(JSON.stringify(IC_LOGIC.serializeCircuit(lcEx)));
                  ok(lcParsed.comps.length === 6 && lcParsed.wires.length === 6, 'T119l Speichern und Laden ergibt dieselbe Schaltung (#166)');
                  ok(IC_LOGIC.normalizeCircuit({ comps: [{ id: 'x', kind: 'quantum', x: 0, y: 0 }], wires: [] }).comps.length === 0, 'T119m unbekannte Baustein-Arten fallen beim Normalisieren raus (#166)');
                  var lcDoc = new FakeDoc();
                  var lcRootEl = simReg(lcDoc, 'lc-ide');
                  var lcCanvasEl = simReg(lcDoc, 'lc-canvas');
                  var lcSelEl = simReg(lcDoc, 'lc-sel');
                  var lcOutEl = simReg(lcDoc, 'lc-out');
                  var lcEvalBtn = simReg(lcDoc, 'lc-eval', 'button');
                  var lcJsonEl = simReg(lcDoc, 'lc-json', 'textarea');
                  lcJsonEl.value = JSON.stringify(IC_LOGIC.serializeCircuit(lcEx));
                  var lcClkBtn = simReg(lcDoc, 'lc-clock', 'button');
                  var lcUi = IC_LOGIC.attach(lcDoc);
                  ok(lcUi !== null && String(lcCanvasEl.innerHTML).indexOf('A') !== -1, 'T119n die Werkstatt zeichnet die geladene Schaltung und wertet sie aus (#166)');
                  ok(String(lcOutEl.textContent).indexOf('Netz stabil') !== -1, 'T119o die Auswertung meldet das stabile Netz (#166)');
                  lcClkBtn.dispatch('click');
                  lcClkBtn.dispatch('click');
                  ok(lcClkBtn.textContent === '▶ Takt', 'T119p der Takt-Knopf startet und stoppt den Takt (#166)');

                  /* T120 (#173): Gemeinsamer Werkzeugkasten fuer die
                   * Automaten-Werkstaetten: Historie, Ansicht, PNG, Takt,
                   * Tabelle - einmal umgesetzt, modular eingesetzt. */
                  var IC_TOOLS = require('../public/assets/js/amt-tools.js');
                  ok(typeof IC_TOOLS.makeHistory === 'function' && typeof IC_TOOLS.makeView === 'function' && typeof IC_TOOLS.exportPng === 'function' && typeof IC_TOOLS.makePlayer === 'function' && typeof IC_TOOLS.deltaTable === 'function', 'T120 der Werkzeugkasten stellt Historie, Ansicht, PNG, Takt und Tabelle bereit (#173)');
                  var histCur = { n: 1 };
                  var histLog = [];
                  var hist120 = IC_TOOLS.makeHistory({
                    snapshot: function () { return JSON.stringify(histCur); },
                    restore: function (snap) { histCur = JSON.parse(snap); histLog.push('r' + histCur.n); }
                  });
                  ok(hist120.canUndo() === false, 'T120a ohne Aenderung gibt es nichts Zurueckzunehmen (#173)');
                  hist120.commit();
                  histCur.n = 2;
                  hist120.commit();
                  histCur.n = 3;
                  hist120.commit();
                  ok(hist120.canUndo() === true, 'T120b Aenderungen landen auf der Historie (#173)');
                  hist120.undo();
                  ok(histCur.n === 2, 'T120c Undo stellt den vorherigen Stand wieder her (#173)');
                  hist120.undo();
                  ok(histCur.n === 1 && hist120.canUndo() === false, 'T120d Undo stoppt am Ausgangszustand (#173)');
                  hist120.redo();
                  ok(histCur.n === 2, 'T120e Redo nimmt die Aenderung wieder auf (#173)');
                  var svg120 = new FakeEl('svg');
                  var view120 = IC_TOOLS.makeView(svg120, { x: 0, y: 0, w: 900, h: 560 });
                  ok(svg120.getAttribute('viewBox') === '0 0 900 560', 'T120f die Ansicht schreibt den Anfangsrahmen in die Zeichenflaeche (#173)');
                  view120.zoom(1.25);
                  ok(svg120.getAttribute('viewBox').indexOf('720') !== -1, 'T120g Zoom vergroessert den Rahmen (720 statt 900) (#173)');
                  view120.fit({ x0: 100, y0: 80, x1: 500, y1: 380 }, 50);
                  var vb120 = svg120.getAttribute('viewBox').split(' ');
                  ok(parseFloat(vb120[0]) === 50 && parseFloat(vb120[2]) === 500, 'T120h Einpassen legt den Rahmen um die Zeichnung (#173)');
                  view120.panBy(10, 20);
                  ok(svg120.getAttribute('viewBox').split(' ')[0] === '60', 'T120i Schieben verschiebt den Rahmen (#173)');
                  var tbl120 = IC_TOOLS.deltaTable(pdaDoc, ['von', 'nach'], [['q0', 'q1']]);
                  ok(tbl120.children[0].children[0].children.length === 2 && tbl120.children[1].children[0].children[1].textContent === 'q1', 'T120j die Tabelle baut Kopf und Zeilen der Uebergangsfunktion (#173)');
                  IC_TOOLS.exportPng(new FakeDoc(), { str: '<svg></svg>', w: 10, h: 10 }, 'x.png');
                  ok(true, 'T120k der PNG-Export verlaesst sich still auf den Browser und wirft nie (#173)');

                  /* T120l-T120r (#173): Funktionsparitaet des Kellerautomaten:
                   * Undo/Redo, Zoom/Einpassen, Abspielen, formale Beschreibung. */
                  var pdaDoc2 = new FakeDoc();
                  var pdaReg2 = function (id, tag) { var e = pdaDoc2.createElement(tag || 'div'); pdaDoc2.register(e, id); return e; };
                  var pdaRoot2 = pdaReg2('pda-ide');
                  var pdaCanvas2 = pdaReg2('pda-canvas', 'svg');
                  var pdaHint2 = pdaReg2('pda-hint');
                  var pdaSel2 = pdaReg2('pda-sel');
                  var pdaOut2 = pdaReg2('pda-out');
                  var pdaWord2 = pdaReg2('pda-word', 'input');
                  var pdaRun2 = pdaReg2('pda-run', 'button');
                  var pdaPlay2 = pdaReg2('pda-play', 'button');
                  var pdaUndo2 = pdaReg2('pda-undo', 'button');
                  var pdaRedo2 = pdaReg2('pda-redo', 'button');
                  var pdaZoomIn2 = pdaReg2('pda-zoom-in', 'button');
                  var pdaZoomOut2 = pdaReg2('pda-zoom-out', 'button');
                  var pdaFit2 = pdaReg2('pda-fit', 'button');
                  var pdaPng2 = pdaReg2('pda-png', 'button');
                  var pdaTuple2 = pdaReg2('pda-tuple');
                  var pdaDelta2 = pdaReg2('pda-delta');
                  var pdaJson2 = pdaReg2('pda-json', 'textarea');
                  pdaJson2.value = JSON.stringify(IC_PDA.serializePDA(pdaEx));
                  var pdaUi2 = IC_PDA.attach(pdaDoc2);
                  ok(String(pdaTuple2.textContent).indexOf('P = (Q') !== -1 && String(pdaTuple2.textContent).indexOf('q0') !== -1, 'T120l die formale Beschreibung nennt das 7-Tupel des Kellerautomaten (#173)');
                  ok(pdaDelta2.children.length === 1 && pdaDelta2.children[0].children[1].children.length === pdaEx.transitions.length, 'T120m die Uebergangstabelle zaehlt alle Uebergaenge (#173)');
                  ok(pdaUi2.addState(300, 200) !== null && pdaUi2.pda().states.length === 4, 'T120n ein zugefuegter Zustand zaehlt mit (#173)');
                  pdaUndo2.dispatch('click');
                  ok(pdaUi2.pda().states.length === 3, 'T120o der Zurueck-Knopf nimmt den Zustand zurueck (#173)');
                  pdaRedo2.dispatch('click');
                  ok(pdaUi2.pda().states.length === 4, 'T120p der Wiederherstellen-Knopf legt ihn erneut an (#173)');
                  pdaFit2.dispatch('click');
                  pdaZoomIn2.dispatch('click');
                  ok(pdaCanvas2.getAttribute('viewBox') !== null && parseFloat(pdaCanvas2.getAttribute('viewBox').split(' ')[2]) < 900, 'T120q Einpassen und Zoom aendern den Rahmen der Kellerautomat-Ansicht (#173)');
                  pdaWord2.value = 'aabb';
                  pdaPlay2.dispatch('click');
                  ok(String(pdaOut2.textContent).indexOf('Start') !== -1, 'T120r der Abspiel-Knopf zeigt den ersten Schritt sofort (#173)');
                  pdaPng2.dispatch('click');
                  ok(String(pdaHint2.textContent).indexOf('PNG') !== -1, 'T120s der Bild-Knopf meldet den PNG-Download (#173)');

                  /* T120t-T120z (#173): Funktionsparitaet der Turingmaschine:
                   * Undo/Redo, Zoom, mehrere Woerter, Abspielen, 7-Tupel. */
                  var tmDoc2 = new FakeDoc();
                  var tmReg2 = function (id, tag) { var e = tmDoc2.createElement(tag || 'div'); tmDoc2.register(e, id); return e; };
                  var tmRoot2 = tmReg2('tm-ide');
                  var tmCanvas2 = tmReg2('tm-canvas', 'svg');
                  var tmHint2 = tmReg2('tm-hint');
                  var tmOut2 = tmReg2('tm-out');
                  var tmWord2 = tmReg2('tm-word', 'input');
                  var tmRun2 = tmReg2('tm-run', 'button');
                  var tmPlay2 = tmReg2('tm-play', 'button');
                  var tmUndo2 = tmReg2('tm-undo', 'button');
                  var tmRedo2 = tmReg2('tm-redo', 'button');
                  var tmFit2 = tmReg2('tm-fit', 'button');
                  var tmTuple2 = tmReg2('tm-tuple');
                  var tmDelta2 = tmReg2('tm-delta');
                  var tmBatchIn2 = tmReg2('tm-batch-in', 'textarea');
                  var tmBatchBtn2 = tmReg2('tm-batch-btn', 'button');
                  var tmBatchOut2 = tmReg2('tm-batch-out');
                  var tmJson2 = tmReg2('tm-json', 'textarea');
                  tmJson2.value = JSON.stringify(IC_TM.serializeTM(tmEx));
                  var tmUi2 = IC_TM.attach(tmDoc2);
                  ok(String(tmTuple2.textContent).indexOf('M = (Q') !== -1 && String(tmTuple2.textContent).indexOf('deterministisch') !== -1, 'T120t die formale Beschreibung nennt das 7-Tupel der Turingmaschine (#173)');
                  ok(tmDelta2.children.length === 1 && tmDelta2.children[0].children[1].children.length === tmEx.rules.length, 'T120u die Delta-Tabelle listet alle Regeln (#173)');
                  tmUi2.addState(300, 200);
                  tmUndo2.dispatch('click');
                  ok(tmUi2.tm().states.length === 3, 'T120v der Zurueck-Knopf nimmt den Zustand zurueck (#173)');
                  tmRedo2.dispatch('click');
                  ok(tmUi2.tm().states.length === 4, 'T120w der Wiederherstellen-Knopf legt ihn erneut an (#173)');
                  tmBatchIn2.value = '1011' + NL41 + '111';
                  tmBatchBtn2.dispatch('click');
                  ok(tmBatchOut2.children.length === 2 && tmBatchOut2.children[0].textContent.indexOf('✓') !== -1, 'T120x der Alle-testen-Knopf prueft mehrere Woerter auf einmal (#173)');
                  tmWord2.value = '1011';
                  tmPlay2.dispatch('click');
                  ok(String(tmOut2.textContent).indexOf('▶ Schritt') !== -1, 'T120y der Abspiel-Knopf zeigt Bandfenster und Kopf sofort (#173)');
                  tmFit2.dispatch('click');
                  ok(tmCanvas2.getAttribute('viewBox') !== null, 'T120z der Einpassen-Knopf setzt den Ansichtsrahmen (#173)');

                  /* T121 (#174): HTML-Baustein im InfoBook: dritter Kasten
                   * neben Java- und SQL-Fenster, Quelltext laeuft nur in
                   * einer abgeschotteten Sandbox ohne same-origin. */
                  var appPath121 = require.resolve('../public/assets/js/app.js');
                  delete require.cache[appPath121];
                  global.window = {};
                  var ibDoc121 = new FakeDoc();
                  var ibEditor121 = new FakeEl('div');
                  ibEditor121.setAttribute('data-codeclasses', JSON.stringify([]));
                  ibEditor121.setAttribute('data-blocks', '[]');
                  ibDoc121.register(ibEditor121, 'ibook-editor');
                  ibDoc121.register(new FakeEl('input'), 'ibook-blocks-json');
                  var ibSaveForm121 = new FakeEl('form');
                  ibSaveForm121.setAttribute('action', '/infobuecher/7/seite/9');
                  ibDoc121.register(ibSaveForm121, 'ibook-saveform');
                  global.document = ibDoc121;
                  global.fetch = function () { return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ ok: true, id: 5, name: 'x.png' }); } }); };
                  require('../public/assets/js/app.js');
                  var ibBar121 = ibDoc121.body.querySelector('.icv-toolbar');
                  var htmlBtn121 = null;
                  var ibBidx;
                  for (ibBidx = 0; ibBar121 && ibBidx < ibBar121.children.length; ibBidx++) {
                    if (ibBar121.children[ibBidx].textContent === '🌐') { htmlBtn121 = ibBar121.children[ibBidx]; break; }
                  }
                  ok(htmlBtn121 !== null, 'T121 der InfoBook-Editor hat einen Knopf fuer den HTML-Baustein (#174)');
                  htmlBtn121.dispatch('click');
                  var ibCollect121 = function () { return JSON.parse(ibDoc121.getElementById('ibook-blocks-json').value)[0].items || []; };
                  ok(ibCollect121().length === 1 && ibCollect121()[0].kind === 'html', 'T121b der HTML-Baustein serialisiert als eigener Kasten (#174)');
                  var hTa121 = ibDoc121.body.querySelector('.icv-html-ta');
                  var hPrev121 = ibDoc121.body.querySelector('.icv-html-prev');
                  ok(hTa121 !== null && hPrev121 !== null, 'T121c der Baustein zeigt Quelltextfeld und Vorschau (#174)');
                  hTa121.value = '<h1>Anmeldung</h1>';
                  hTa121.dispatch('input');
                  ok(ibCollect121()[0].html === '<h1>Anmeldung</h1>', 'T121d der getippte Quelltext wird mit der Seite gesichert (#174)');
                  ok(hPrev121.children.length === 1 && hPrev121.children[0].tagName === 'IFRAME', 'T121e die Vorschau laeuft in einem eigenen Fenster (#174)');
                  ok(hPrev121.children[0].getAttribute('sandbox') === 'allow-scripts allow-forms', 'T121f die Sandbox erlaubt Skripte und Formulare, aber keinen Zugriff auf die Plattform (#174)');
                  var roBox121 = new FakeEl('div');
                  global.window.IC_HTML_RENDER(roBox121, '<p>Lesemodus</p>');
                  ok(roBox121.children.length === 1 && roBox121.children[0].getAttribute('srcdoc') === '<p>Lesemodus</p>', 'T121g der Lesemodus rendert den gespeicherten Quelltext im abgeschotteten Fenster (#174)');
                  /* Boot im Lesemodus: fertige Rahmen einmalig bestuecken. */
                  var appPath121b = require.resolve('../public/assets/js/app.js');
                  delete require.cache[appPath121b];
                  var bootDoc121 = new FakeDoc();
                  var bootFrame121 = new FakeEl('div');
                  bootFrame121.className = 'icv-html-frame';
                  var bootItemWrap121 = new FakeEl('div');
                  bootItemWrap121.appendChild(bootFrame121);
                  var bootSrc121 = new FakeEl('pre');
                  bootSrc121.className = 'icv-html-src';
                  bootSrc121.textContent = '<b>Maskenfeld</b>';
                  bootItemWrap121.appendChild(bootSrc121);
                  bootDoc121.register(bootItemWrap121, 'boot-wrap-121');
                  global.document = bootDoc121;
                  require('../public/assets/js/app.js');
                  ok(bootFrame121.children.length === 1 && bootFrame121.children[0].getAttribute('srcdoc') === '<b>Maskenfeld</b>', 'T121h der Boot im Lesemodus liest den versteckten Quelltext und baut das Fenster (#174)');
                  delete global.window;

                  /* T122 (#174): Die Automaten-Werkstatt liest ihre Farben
                   * aus CSS-Variablen. Frueher fiel die Ersatzsuche auf
                   * falsche Schluessel und ergab undefined: Der Browser
                   * zeichnete Zustandskreise dann solide schwarz. Diese
                   * Tests simulieren den Browser mit getComputedStyle. */
                  var fs122 = require('fs');
                  ok(fs122.readFileSync(require.resolve('../public/assets/css/style.css'), 'utf8').indexOf('--paper:') !== -1,
                    'T122 das Stylesheet definiert die Farbwerte der Werkstatt zentral (#174)');
                  var autoPath122 = require.resolve('../public/assets/js/automaton.js');
                  var stubVals122 = {};
                  global.getComputedStyle = function () {
                    return { getPropertyValue: function (n) { return stubVals122[n] || ''; } };
                  };
                  var amtBoot122 = function () {
                    delete require.cache[autoPath122];
                    var doc122 = new FakeDoc();
                    doc122.documentElement = new FakeEl('html');
                    var svg122 = new FakeEl('svg');
                    doc122.register(new FakeEl('div'), 'amt-ide');
                    doc122.register(svg122, 'amt-canvas');
                    doc122.register(new FakeEl('section'), 'amt-stage');
                    doc122.register(new FakeEl('form'), 'automaton-save-form');
                    ['amt-sel', 'amt-word', 'amt-run', 'amt-step', 'amt-play', 'amt-reset',
                     'amt-out', 'amt-sigma', 'amt-chips', 'amt-tuple', 'amt-delta',
                     'amt-hint', 'amt-undo', 'amt-redo', 'amt-trap', 'amt-new',
                     'amt-zoom-in', 'amt-zoom-out', 'amt-fit', 'amt-png',
                     'amt-pop', 'amt-pop-in', 'amt-pop-ok', 'amt-pop-lbl', 'amt-pop-hint',
                     'amt-batch-in', 'amt-batch-btn', 'amt-batch-out', 'amt-saveinfo'
                    ].forEach(function (id122) { doc122.register(new FakeEl('div'), id122); });
                    global.document = doc122;
                    require('../public/assets/js/automaton.js');
                    return svg122;
                  };
                  var svg122a = amtBoot122();
                  ok(svg122a.innerHTML.indexOf('fill="#ffffff"') !== -1,
                    'T122b ohne Farbangaben behalten die Zustandskreise ihre weisse Fuellung (#174)');
                  ok(svg122a.innerHTML.indexOf('undefined') === -1,
                    'T122c die Zeichnung enthaelt keine ungueltigen Farbwerte mehr (#174)');
                  stubVals122['--paper'] = '#fafaff';
                  var svg122b = amtBoot122();
                  ok(svg122b.innerHTML.indexOf('fill="#fafaff"') !== -1,
                    'T122d eigene Farbwerte aus dem Stylesheet fließen in die Zeichnung ein (#174)');
                  delete global.getComputedStyle;

                  /* T123 (#177/#188): Präsentationsmodus im InfoBook.
                   * Folien-Navigation und Elemente-Einblendung per Klick
                   * laufen als IC_PRESENT-Modul in app.js; Autoplay mit
                   * Zeitsteuerung, Fortschrittsbalken und Zeitauswahl sind
                   * mit #188 entfallen. */
                  global.window = {};
                  var presPath123 = require.resolve('../public/assets/js/app.js');
                  delete require.cache[presPath123];
                  global.document = new FakeDoc();
                  require('../public/assets/js/app.js');
                  ok(global.window.IC_PRESENT && typeof global.window.IC_PRESENT.nextIndex === 'function'
                    && typeof global.window.IC_PRESENT.stepsOf === 'function'
                    && typeof global.window.IC_PRESENT.stepInfoText === 'function',
                    'T123 der Präsentationsmodus stellt seine Helfer bereit (#177, #188)');
                  ok(global.window.IC_PRESENT.nextIndex(1, 3, false) === 2
                    && global.window.IC_PRESENT.nextIndex(2, 3, false) === 2
                    && global.window.IC_PRESENT.nextIndex(2, 3, true) === 0,
                    'T123b der Folienzeiger bleibt am Ende stehen, nur im Kreislauf springt er um (#188)');
                  var stepSlide123 = new FakeEl('section');
                  var stepBox123 = new FakeEl('div');
                  stepBox123.className = 'ibook-slide-blocks';
                  var stepPill123 = new FakeEl('p');
                  stepPill123.className = 'pill ibook-slide-section';
                  var stepA123 = new FakeEl('p');
                  var stepB123 = new FakeEl('p');
                  stepBox123.appendChild(stepPill123);
                  stepBox123.appendChild(stepA123);
                  stepBox123.appendChild(stepB123);
                  stepSlide123.appendChild(stepBox123);
                  var steps123 = global.window.IC_PRESENT.stepsOf(stepSlide123);
                  ok(steps123.length === 2 && steps123[0].els.length === 1 && steps123[0].els[0] === stepA123
                    && steps123[1].els[0] === stepB123,
                    'T123c ohne Schrittnummern ist jedes Element der Folie ein Schritt, ohne die Bereichs-Pill (#188, #191)');
                  ok(global.window.IC_PRESENT.stepInfoText(0, 2) === 'Schritt 1 / 2'
                    && global.window.IC_PRESENT.stepInfoText(1, 2) === 'Schritt 2 / 2'
                    && global.window.IC_PRESENT.stepInfoText(2, 2) === ''
                    && global.window.IC_PRESENT.stepInfoText(0, 1) === '',
                    'T123d die Schritt-Anzeige nennt den nächsten Schritt und schweigt, wenn alles sichtbar ist (#188, #191)');
                  /* Folgeseite mit zwei Folien nachbauen, jede mit zwei
                   * Elementen unter der Bereichs-Pill. Boot laeuft beim
                   * Einbinden von app.js, weil das Fake-DOM nie "loading" ist. */
                  delete require.cache[presPath123];
                  var presDoc123 = new FakeDoc();
                  var stage123 = new FakeEl('div');
                  stage123.setAttribute('data-ibook', '41');
                  function buildSlide123(name123) {
                    var sl = new FakeEl('section');
                    sl.className = 'slide';
                    var box = new FakeEl('div');
                    box.className = 'ibook-slide-blocks';
                    var pill = new FakeEl('p');
                    pill.className = 'pill ibook-slide-section';
                    var b1 = new FakeEl('p');
                    b1.textContent = name123 + ' A';
                    var b2 = new FakeEl('p');
                    b2.textContent = name123 + ' B';
                    box.appendChild(pill);
                    box.appendChild(b1);
                    box.appendChild(b2);
                    sl.appendChild(box);
                    return { slide: sl, b1: b1, b2: b2 };
                  }
                  var sl1_123 = buildSlide123('Alpha');
                  var sl2_123 = buildSlide123('Beta');
                  stage123.appendChild(sl1_123.slide);
                  stage123.appendChild(sl2_123.slide);
                  presDoc123.register(stage123, 'slide-stage');
                  var pCounter123 = new FakeEl('span'); presDoc123.register(pCounter123, 'slide-counter');
                  var pPrev123 = new FakeEl('button'); presDoc123.register(pPrev123, 'slide-prev');
                  var pNext123 = new FakeEl('button'); presDoc123.register(pNext123, 'slide-next');
                  var pFull123 = new FakeEl('button'); presDoc123.register(pFull123, 'slide-full');
                  var pStep123 = new FakeEl('span'); presDoc123.register(pStep123, 'slide-stepinfo');
                  global.document = presDoc123;
                  require('../public/assets/js/app.js');
                  ok(sl1_123.slide.classList.contains('slide-active') && pCounter123.textContent === '1 / 2'
                    && sl1_123.b1.classList.contains('slide-step-hidden') && sl1_123.b2.classList.contains('slide-step-hidden')
                    && pStep123.textContent === 'Schritt 1 / 2',
                    'T123e der Boot zeigt die erste Folie und haelt ihre Elemente zurueck (#188)');
                  pNext123.dispatch('click');
                  ok(sl1_123.b1.classList.contains('slide-step-in') && !sl1_123.b1.classList.contains('slide-step-hidden')
                    && sl1_123.b2.classList.contains('slide-step-hidden') && pStep123.textContent === 'Schritt 2 / 2',
                    'T123f der Weiter-Knopf blendet das erste Element einzeln per Klick ein (#188)');
                  pNext123.dispatch('click');
                  ok(!sl1_123.b2.classList.contains('slide-step-hidden') && pStep123.textContent === '',
                    'T123g ist die Folie komplett aufgedeckt, bleibt die Schritt-Anzeige leer (#188)');
                  pNext123.dispatch('click');
                  ok(sl2_123.slide.classList.contains('slide-active') && pCounter123.textContent === '2 / 2'
                    && sl2_123.b1.classList.contains('slide-step-hidden') && sl2_123.b2.classList.contains('slide-step-hidden'),
                    'T123h die naechste Folie startet wieder mit verborgenen Elementen (#188)');
                  pPrev123.dispatch('click');
                  ok(sl1_123.slide.classList.contains('slide-active') && !sl1_123.b1.classList.contains('slide-step-hidden')
                    && !sl1_123.b2.classList.contains('slide-step-hidden'),
                    'T123i der Rueck-Knopf zeigt die vorige Folie komplett aufgedeckt (#188)');
                  pPrev123.dispatch('click');
                  ok(sl1_123.b2.classList.contains('slide-step-hidden') && !sl1_123.b1.classList.contains('slide-step-hidden')
                    && pStep123.textContent === 'Schritt 2 / 2',
                    'T123j auf der Folie blendet der Rueck-Knopf das zuletzt eingeblendete Element wieder aus (#188)');
                  presDoc123._fire('keydown', { key: 'ArrowRight', preventDefault: function () {} });
                  ok(!sl1_123.b2.classList.contains('slide-step-hidden') && pStep123.textContent === '',
                    'T123k die Pfeiltaste rechts blendet das nächste Element ein (#188)');
                  presDoc123._fire('keydown', { key: 'p', preventDefault: function () {} });
                  ok(sl1_123.slide.classList.contains('slide-active') && pStep123.textContent === ''
                    && !sl1_123.b1.classList.contains('slide-step-hidden') && !sl1_123.b2.classList.contains('slide-step-hidden'),
                    'T123l die Taste P startet keinen Automatikmodus mehr (#188)');
                  var presTpl123 = require('fs').readFileSync(require.resolve('../templates/ibook_present.php'), 'utf8');
                  var presJs123 = require('fs').readFileSync(presPath123, 'utf8');
                  var presCss123 = require('fs').readFileSync(require.resolve('../public/assets/css/style.css'), 'utf8');
                  ok(presTpl123.indexOf('slide-stepinfo') !== -1 && presTpl123.indexOf('slide-interval') === -1
                    && presTpl123.indexOf('slide-play') === -1 && presTpl123.indexOf('slide-progress') === -1
                    && presTpl123.indexOf('IC_PRESENT') !== -1,
                    'T123m die Folgenseite liefert die Schritt-Anzeige statt Balken, Startknopf und Zeitauswahl (#188)');
                  ok(presJs123.indexOf('slide-step-hidden') !== -1 && presCss123.indexOf('.slide-step-hidden') !== -1
                    && presCss123.indexOf('.slide-step-in') !== -1,
                    'T123n app.js und style.css tragen die Einblend-Klassen (#188)');

                  /* T124 (#184): Die linke Seitennavigation zeigt nur die
                   * Seiten des aktiven Abschnitts; genau ein "Neue Seite"-
                   * Feld statt einem je Abschnitt, das Abschnitt-Formular
                   * klappt auf. Alles reine Datei-Pruefung wie bei T123l. */
                  var navTpl124 = require('fs').readFileSync(require.resolve('../templates/ibook_nav.php'), 'utf8');
                  var navCss124 = require('fs').readFileSync(require.resolve('../public/assets/css/style.css'), 'utf8');
                  var navPhp124 = require('fs').readFileSync(require.resolve('../src/Controllers/InfoBookController.php'), 'utf8');
                  ok(navTpl124.indexOf('ibook-pagenav-pages') !== -1 && navCss124.indexOf('.ibook-pagenav-pages') !== -1,
                    'T124 die Navigation fasst die Seiten des aktiven Abschnitts in einen eigenen Block (#184)');
                  ok(navTpl124.indexOf('ibook-pagenav-section-active') !== -1 && navCss124.indexOf('.ibook-pagenav-section-active') !== -1,
                    'T124b der aktive Abschnitt ist ein Label statt eines Links auf sich selbst (#184)');
                  ok(navTpl124.indexOf('ibook-pagenav-pages') < navTpl124.indexOf('ibook-navnew'),
                    'T124c das einzige Neue-Seite-Feld steht unter den Seiten des aktiven Abschnitts (#184)');
                  ok(navTpl124.indexOf('ibook-navnewsec-details') !== -1 && navCss124.indexOf('.ibook-navnewsec-details') !== -1,
                    'T124d Neuer Abschnitt klappt auf, statt als Dauerformular Platz zu verbrauchen (#184)');
                  ok(navPhp124.indexOf('navSectionsFor') !== -1 && navPhp124.indexOf('visibleSectionForStudent') !== -1,
                    'T124e Schuelerinnen und Schueler sehen nur die fuer sie sichtbaren Abschnitte (#184)');
                  ok(navPhp124.split('self::navSectionsFor').length - 1 === 3,
                    'T124f Buchansicht, Seitennavigation und Praesentation laden die Abschnitte gemeinsam (#184)');
                  ok(navTpl124.indexOf('navLesen') !== -1 && navTpl124.indexOf('?lesen=1') !== -1,
                    'T124g im Lesemodus behalten alle Seiten-Links das lesen-Suffix (#184)');

                  /* T125 (#185): Buch- und Seitenansicht teilen sich
                   * Navigation und Kopfzeile: gemeinsames Partial,
                   * Breadcrumb als Standort-Anzeige, Icon-Knopf, Start auf
                   * der zuletzt besuchten Seite, Abschnittsansicht mit
                   * Karten. Alles reine Datei-Pruefung. */
                  var navPage125 = require('fs').readFileSync(require.resolve('../templates/ibook_page.php'), 'utf8');
                  var navBook125 = require('fs').readFileSync(require.resolve('../templates/ibook_book.php'), 'utf8');
                  ok(navPage125.indexOf('ibook_nav.php') !== -1 && navBook125.indexOf('ibook_nav.php') !== -1,
                    'T125 Buch- und Seitenansicht zeichnen die Navigation aus demselben Partial (#185)');
                  ok(navPage125.indexOf('ibook-crumbs') !== -1 && navBook125.indexOf('ibook-crumbs') !== -1 && navCss124.indexOf('.ibook-crumbs') !== -1,
                    'T125b beide Ansichten zeigen einen Breadcrumb Buch-Abschnitt-Seite (#185)');
                  ok(navPhp124.indexOf('ic_ibook_last') !== -1 && navPhp124.indexOf('lastVisitedPage') !== -1,
                    'T125c der Buch-Start oeffnet die zuletzt besuchte Seite (Cookie je Buch, #185)');
                  ok(navPhp124.indexOf('lastVisitedPage($book, $sections)') !== -1 && navPhp124.indexOf('\'ic_ibook_last[\'') !== -1,
                    'T125d die Start-Weiterleitung prueft Sichtbarkeit und Existenz der gemerkten Seite (#185)');
                  ok(navBook125.indexOf('ibook-secview') !== -1 && navCss124.indexOf('.ibook-secview') !== -1,
                    'T125e die Abschnittsansicht zeigt die Seiten als Karten im einheitlichen Gewand (#185)');
                  ok(navPage125.indexOf('ibook-navtoggle') !== -1 && navBook125.indexOf('ibook-navtoggle') !== -1 && navCss124.indexOf('.ibook-navtoggle') !== -1,
                    'T125f der Aufklapp-Knopf ist ein Icon-Knopf in beiden Ansichten (#185)');
                  var navModel125 = require('fs').readFileSync(require.resolve('../src/Models/InfoBook.php'), 'utf8');
                  ok(navModel125.indexOf('first_section_id') !== -1 && navPhp124.indexOf('first_section_id') !== -1,
                    'T125g nach dem Buchpaket-Import landet man direkt in der neuen Abschnittsansicht (#185)');


                  /* T126 (#186-#190): Runde 22. Abschnitte in der Navigation
                   * aufklappbar, Aufklappzustand je Abschnitt im Browser
                   * gespeichert, genau ein Neue-Seite-Feld, Buchpaket-Import
                   * unten in der Navigation, leere Abschnitte als Karten-
                   * Raster, Vorschau im gleichen Fenster, Klassenkarte als
                   * echter UML-Klassenkasten. */
                  var navTpl126 = navTpl124;
                  var navJs126 = require('fs').readFileSync(presPath123, 'utf8');
                  ok(navTpl126.indexOf('ibook-pagenav-secdetails') !== -1 && navTpl126.indexOf('data-secid') !== -1
                    && navCss124.indexOf('.ibook-pagenav-secdetails') !== -1,
                    'T126 die Abschnitte der Navigation sind aufklappbare Eintraege mit Zustands-Pfeil (#186)');
                  var navStore126 = {};
                  var savedLs126 = global.localStorage;
                  global.localStorage = {
                    getItem: function (k) { return Object.prototype.hasOwnProperty.call(navStore126, k) ? navStore126[k] : null; },
                    setItem: function (k, v) { navStore126[k] = String(v); }
                  };
                  var aside126 = new FakeEl('aside');
                  var secA126 = new FakeEl('details');
                  secA126.className = 'ibook-pagenav-secdetails';
                  secA126.setAttribute('data-secid', '5');
                  var secB126 = new FakeEl('details');
                  secB126.className = 'ibook-pagenav-secdetails';
                  secB126.setAttribute('data-secid', '7');
                  aside126.appendChild(secA126);
                  aside126.appendChild(secB126);
                  ok(typeof global.window.IC_IBOOK_NAVSEC === 'function'
                    && global.window.IC_IBOOK_NAVSEC(aside126) === true,
                    'T126b die Aufklapp-Logik der Abschnitte ist als Modul bereit (#186)');
                  ok(secA126.hasAttribute('open') === false && secB126.hasAttribute('open') === false,
                    'T126c ohne gespeicherten Zustand bleibt der vom Server gelieferte Zustand stehen (#186)');
                  secA126.setAttribute('open', 'open');
                  secA126.dispatch('toggle');
                  ok(navStore126['ic-ibook-navsec'] === '{"5":1}',
                    'T126d das Auf- und Zuklappen wird je Abschnitt im Browser gespeichert (#186)');
                  secB126.setAttribute('open', 'open');
                  secB126.dispatch('toggle');
                  var st126e = JSON.parse(navStore126['ic-ibook-navsec']);
                  ok(st126e['5'] === 1 && st126e['7'] === 1,
                    'T126e mehrere Abschnitte bleiben gleichzeitig offen (#186)');
                  secB126.removeAttribute('open');
                  secB126.dispatch('toggle');
                  var secC126 = new FakeEl('details');
                  secC126.className = 'ibook-pagenav-secdetails';
                  secC126.setAttribute('data-secid', '7');
                  secC126.setAttribute('open', 'open');
                  var aside126b = new FakeEl('aside');
                  aside126b.appendChild(secC126);
                  global.window.IC_IBOOK_NAVSEC(aside126b);
                  ok(secC126.hasAttribute('open') === false,
                    'T126f der gespeicherte Aufklappzustand ueberschreibt den Server-Standard (#186)');
                  global.localStorage = savedLs126;
                  ok(navTpl126.split('ibook-navnew"').length - 1 === 1
                    && navTpl126.indexOf('navIsActive && $canWrite') !== -1,
                    'T126g das einzige Neue-Seite-Feld bleibt allein am aktiven Abschnitt (#186)');
                  ok(navTpl126.indexOf('ibook-navimport-details') !== -1
                    && navCss124.indexOf('.ibook-navimport-details') !== -1
                    && navBook125.indexOf('name="paket"') === -1,
                    'T126h der Buchpaket-Import sitzt unten in der Navigation, nicht im Hauptbereich (#187)');
                  ok(navBook125.indexOf('ibook-page-card-empty') !== -1
                    && navCss124.indexOf('.ibook-page-card-empty') !== -1,
                    'T126i ein leerer Abschnitt zeigt dasselbe Karten-Raster mit Platzhalter-Karte (#187)');
                  ok(navPage125.indexOf('?lesen=1') !== -1 && navPage125.indexOf('target=') === -1,
                    'T126j die Vorschau oeffnet im gleichen Fenster (#189)');
                  ok(navJs126.indexOf('icv-clsbox') !== -1 && navCss124.indexOf('.icv-clsbox') !== -1,
                    'T126k die Klassenkarte ist ein echter UML-Klassenkasten (#190)');
                  ok(navJs126.indexOf('clsCard: clsCardDetail') !== -1
                    && navJs126.indexOf('IC_EDIT_TOOLS.clsCard') !== -1
                    && navJs126.indexOf('clsCardDetail(name, code)') !== -1,
                    'T126l Editor und Lesemodus zeichnen die Klassenkarte aus demselben Baustein (#190)');

                  /* T127 (#191-#194): Runde 23. Praesentations-Schritte am
                   * Element: data-step gruppiert und sortiert, gleiche
                   * Nummern erscheinen zusammen; der Editor setzt Schritt
                   * und Animation im Kontextbereich, Badge am Element. */
                  var grpSlide127 = new FakeEl('section');
                  var grpBox127 = new FakeEl('div');
                  grpBox127.className = 'ibook-slide-blocks';
                  var grpPill127 = new FakeEl('p');
                  grpPill127.className = 'pill ibook-slide-section';
                  grpBox127.appendChild(grpPill127);
                  var grpEls127 = [];
                  [[2, 'a'], [1, 'b'], [1, 'c'], [3, 'd']].forEach(function (pair) {
                    var e = new FakeEl('p');
                    e.setAttribute('data-step', String(pair[0]));
                    e.textContent = 'El ' + pair[1];
                    grpBox127.appendChild(e);
                    grpEls127.push(e);
                  });
                  grpSlide127.appendChild(grpBox127);
                  var grp127 = global.window.IC_PRESENT.stepsOf(grpSlide127);
                  ok(grp127.length === 3 && grp127[0].n === 1 && grp127[0].els.length === 2
                    && grp127[0].els[0] === grpEls127[1] && grp127[0].els[1] === grpEls127[2]
                    && grp127[1].els[0] === grpEls127[0] && grp127[2].els[0] === grpEls127[3],
                    'T127 data-step sortiert die Schritte aufsteigend, gleiche Nummern bilden eine Gruppe (#191)');

                  /* Folgenseite mit einer Schritt-Folie und einer Fallback-
                   * Folie: Boot verhaelt beide wie erwartet. */
                  delete require.cache[presPath123];
                  var presDoc127 = new FakeDoc();
                  var stage127 = new FakeEl('div');
                  stage127.setAttribute('data-ibook', '47');
                  var slA127 = new FakeEl('section');
                  slA127.className = 'slide';
                  var boxA127 = new FakeEl('div');
                  boxA127.className = 'ibook-slide-blocks';
                  var pillA127 = new FakeEl('p');
                  pillA127.className = 'pill ibook-slide-section';
                  boxA127.appendChild(pillA127);
                  var s1a127 = new FakeEl('p');
                  s1a127.setAttribute('data-step', '1');
                  var s1b127 = new FakeEl('p');
                  s1b127.setAttribute('data-step', '1');
                  var s1c127 = new FakeEl('p');
                  s1c127.setAttribute('data-step', '2');
                  boxA127.appendChild(s1a127);
                  boxA127.appendChild(s1b127);
                  boxA127.appendChild(s1c127);
                  slA127.appendChild(boxA127);
                  var slB127 = new FakeEl('section');
                  slB127.className = 'slide';
                  var boxB127 = new FakeEl('div');
                  boxB127.className = 'ibook-slide-blocks';
                  var bx127 = new FakeEl('p');
                  var by127 = new FakeEl('p');
                  boxB127.appendChild(bx127);
                  boxB127.appendChild(by127);
                  slB127.appendChild(boxB127);
                  stage127.appendChild(slA127);
                  stage127.appendChild(slB127);
                  presDoc127.register(stage127, 'slide-stage');
                  var c127 = new FakeEl('span'); presDoc127.register(c127, 'slide-counter');
                  var pr127 = new FakeEl('button'); presDoc127.register(pr127, 'slide-prev');
                  var nx127 = new FakeEl('button'); presDoc127.register(nx127, 'slide-next');
                  var si127 = new FakeEl('span'); presDoc127.register(si127, 'slide-stepinfo');
                  global.document = presDoc127;
                  require('../public/assets/js/app.js');
                  ok(slA127.classList.contains('slide-active')
                    && s1a127.classList.contains('slide-step-hidden') && s1b127.classList.contains('slide-step-hidden')
                    && s1c127.classList.contains('slide-step-hidden') && si127.textContent === 'Schritt 1 / 2',
                    'T127b der Boot haelt alle Schritte der ersten Folie zurueck (#191)');
                  nx127.dispatch('click');
                  ok(!s1a127.classList.contains('slide-step-hidden') && s1a127.classList.contains('slide-step-in')
                    && !s1b127.classList.contains('slide-step-hidden') && s1b127.classList.contains('slide-step-in')
                    && s1c127.classList.contains('slide-step-hidden') && si127.textContent === 'Schritt 2 / 2',
                    'T127c ein Klick blendet eine ganze Schritt-Gruppe gemeinsam ein (#191)');
                  nx127.dispatch('click');
                  ok(!s1c127.classList.contains('slide-step-hidden') && si127.textContent === '',
                    'T127d der zweite Schritt folgt, danach schweigt die Anzeige (#191)');
                  nx127.dispatch('click');
                  ok(slB127.classList.contains('slide-active') && bx127.classList.contains('slide-step-hidden')
                    && c127.textContent === '2 / 2',
                    'T127e ohne Schrittnummern faellt die Folie auf Block-Ebene zurueck (#188, #191)');
                  pr127.dispatch('click');
                  ok(slA127.classList.contains('slide-active') && !s1a127.classList.contains('slide-step-hidden')
                    && !s1b127.classList.contains('slide-step-hidden') && !s1c127.classList.contains('slide-step-hidden'),
                    'T127f der Rueckweg zeigt die Folie komplett aufgedeckt (#191)');
                  pr127.dispatch('click');
                  ok(s1c127.classList.contains('slide-step-hidden') && !s1b127.classList.contains('slide-step-hidden')
                    && si127.textContent === 'Schritt 2 / 2',
                    'T127g der Rueck-Knopf blendet den letzten Schritt wieder aus (#191)');

                  var php127 = require('fs').readFileSync(require.resolve('../src/Models/InfoBook.php'), 'utf8');
                  ok(php127.indexOf("$it['step'] = $step") !== -1 && php127.indexOf("$it['anim'] = $anim") !== -1
                    && php127.indexOf("'fade', 'up', 'left', 'zoom'") !== -1 && php127.indexOf('$step > 99') !== -1,
                    'T127h die Bereinigung behaelt Schritt und Animation je Element, mit Obergrenze (#191)');
                  var ctrl127 = require('fs').readFileSync(require.resolve('../src/Controllers/InfoBookController.php'), 'utf8');
                  ok(ctrl127.indexOf('data-step') !== -1 && ctrl127.indexOf('data-anim') !== -1
                    && ctrl127.indexOf("renderBlock($block, (int) $book->id, $slideLinks, 'present')") !== -1
                    && ctrl127.indexOf('66vh') !== -1,
                    'T127i der Präsentationsmodus rendert Schrittnummer, Animation und Flaechen-Anpassung (#191, #203: Folien waehlen ihre Link-Ziele je Bereich)');
                  var js127 = require('fs').readFileSync(presPath123, 'utf8');
                  ok(js127.indexOf('icv-ctx-step') !== -1 && js127.indexOf('refreshStepBadge') !== -1
                    && js127.indexOf('icv-stepbadge') !== -1 && js127.indexOf('e.step = item.step') !== -1
                    && js127.indexOf('e.anim = item.anim') !== -1,
                    'T127j der Editor setzt Schritt und Animation im Kontextbereich, Badge und Speicherung folgen (#191)');
                  var css127 = require('fs').readFileSync(require.resolve('../public/assets/css/style.css'), 'utf8');
                  ok(css127.indexOf('slide-in-up') !== -1 && css127.indexOf('slide-in-left') !== -1
                    && css127.indexOf('slide-in-zoom') !== -1 && css127.indexOf('.icv-stepbadge') !== -1,
                    'T127k die Einblend-Animationen und das Schritt-Badge liegen im Stylesheet (#191)');
                  ok(css127.indexOf("content: '«'") !== -1 && css127.indexOf("content: '»'") !== -1
                    && css127.indexOf("content: '☰'") === -1,
                    'T127l das Ein-/Ausklapp-Icon der Navigation ist kein Hamburger mehr (#192)');
                  var pageTpl127 = require('fs').readFileSync(require.resolve('../templates/ibook_page.php'), 'utf8');
                  ok(pageTpl127.indexOf('Freie Fläche') === -1,
                    'T127m die Ueberschrift "Freie Flaeche" ist aus dem Editor entfernt (#193)');
                  var pkgIf127 = require('fs').readFileSync(require.resolve('../docs/beispielpakete/if-anweisungen-java.json'), 'utf8');
                  var pkgVb127 = require('fs').readFileSync(require.resolve('../docs/beispielpakete/vererbung-java.json'), 'utf8');
                  ok(pkgIf127.indexOf('sections') !== -1 && pkgIf127.indexOf('"step"') !== -1
                    && pkgVb127.indexOf('sections') !== -1 && pkgVb127.indexOf('"step"') !== -1
                    && pkgIf127.indexOf('"anim"') !== -1 && pkgVb127.indexOf('"anim"') !== -1,
                    'T127n die Beispiel-Buchpakete liegen im Import-Format mit Schritten und Animationen (#194)');

                  /* T128 (#195, #196): Runde 24. SQL-Abenteuer: Quest-Übersicht
                   * mit Szenenbild-Karten, Kapitel-Ansicht mit Lösungssperre
                   * und drei neue Abenteuer mit Seed-Datenbanken. */
                  var advFs128 = require('fs');
                  var advRead128 = function (p) {
                    return advFs128.readFileSync(require.resolve(p), 'utf8');
                  };
                  var advMeta128 = advRead128('../src/SqlAdventure.php');
                  var advHub128 = advRead128('../templates/sql_adventures.php');
                  var advStory128 = advRead128('../templates/sql_adventure.php');
                  var advCtrl128 = advRead128('../src/Controllers/SqlWorkshopController.php');
                  var advRoutes128 = advRead128('../public/index.php');
                  var advOverview128 = advRead128('../templates/sql_overview.php');
                  var advMig128 = advRead128('../src/Migrator.php');
                  var advCss128 = advRead128('../public/assets/css/style.css');
                  ok(advMeta128.indexOf("'adventure' =>") !== -1
                    && advMeta128.indexOf("'raumstation' =>") !== -1
                    && advMeta128.indexOf("'museum' =>") !== -1
                    && advMeta128.indexOf("'festival' =>") !== -1
                    && advMeta128.split('<svg').length - 1 >= 5
                    && advMeta128.indexOf('teaser') !== -1 && advMeta128.indexOf('finale') !== -1,
                    'T128 jedes Abenteuer hat Teaser, Intro, Finale und ein eigenes Szenenbild (#195, #196)');
                  ok(advHub128.indexOf('SQL-Abenteuer') !== -1
                    && advHub128.indexOf('adv-card') !== -1
                    && advHub128.indexOf('adv-progress-fill') !== -1
                    && advHub128.indexOf('Noch nicht zugeteilt') !== -1
                    && advHub128.indexOf("url('werkstatt/sql/abenteuer')") === -1,
                    'T128b die Quest-Übersicht zeigt Karten mit Szenenbild, Fortschritt und Sperre (#195)');
                  ok(advStory128.indexOf('adv-hero') !== -1
                    && advStory128.indexOf('adv-chapter') !== -1
                    && advStory128.indexOf('adv-current') !== -1
                    && advStory128.indexOf('adv-locked') !== -1
                    && advStory128.indexOf('Noch verschlossen') !== -1
                    && advStory128.indexOf('adv-finale') !== -1
                    && advStory128.indexOf('id="aufgaben"') !== -1,
                    'T128c die Kapitel-Ansicht kennt gelöst, aktuell, verschlossen und das Finale (#195)');
                  ok(advCtrl128.indexOf('public static function adventures()') !== -1
                    && advCtrl128.indexOf("'sql_adventure'") !== -1
                    && advCtrl128.indexOf("'sql_workspace'") !== -1
                    && advCtrl128.indexOf('SqlAdventure::meta(') !== -1,
                    'T128d der Controller rendert Abenteuer in der Kapitel-Ansicht mit Metadaten (#195)');
                  ok(advRoutes128.indexOf("'/werkstatt/sql/abenteuer'") !== -1
                    && advRoutes128.indexOf("'/werkstatt/sql/abenteuer'") < advRoutes128.indexOf("'/werkstatt/sql/{slug}'"),
                    'T128e die Abenteuer-Route liegt vor dem Platzhalter {slug} (#195)');
                  ok(advOverview128.indexOf('werkstatt/sql/abenteuer') !== -1
                    && advOverview128.indexOf('Zu den Abenteuern') !== -1,
                    'T128f die Werkstatt-Übersicht verlinkt auf die Abenteuer-Übersicht (#195)');
                  ok(advMig128.indexOf("'raumstation', 'Raumstation Alpha'") !== -1
                    && advMig128.indexOf("'museum', 'Das Museum des verschwundenen Meisterwerks'") !== -1
                    && advMig128.indexOf("'festival', 'Festival der jungen Bühnen'") !== -1,
                    'T128g der Migrator sät drei neue Abenteuer-Vorlagen (#196)');
                  var advCount128 = function (seg, needle) { return seg.split(needle).length - 1; };
                  var advSeg128 = function (slug) {
                    var start = advMig128.indexOf("if ($slug === '" + slug + "')");
                    var rest = advMig128.slice(start + 10);
                    var nextIf = rest.indexOf("if ($slug ===");
                    var stop = advMig128.indexOf('// adventure (Dateninsel)', start);
                    var end = start + 10 + (nextIf !== -1 && start + 10 + nextIf < stop ? nextIf : stop - start - 10);
                    return advMig128.slice(start, end);
                  };
                  ['raumstation', 'museum', 'festival'].forEach(function (slug) {
                    ok(advCount128(advSeg128(slug), "'position' =>") === 10
                      && advCount128(advSeg128(slug), "'kind' => 'action'") >= 1,
                      'T128h ' + slug + ' hat zehn Kapitel-Missionen inklusive Schreibaufgaben (#196)');
                  });
                  var advRaumSql128 = advRead128('../sql/templates/raumstation.sql');
                  var advMusSql128 = advRead128('../sql/templates/museum.sql');
                  var advFesSql128 = advRead128('../sql/templates/festival.sql');
                  ok(advCount128(advRaumSql128, 'CREATE TABLE') === 4 && advCount128(advRaumSql128, 'INSERT INTO') === 4
                    && advCount128(advMusSql128, 'CREATE TABLE') === 4 && advCount128(advMusSql128, 'INSERT INTO') === 4
                    && advCount128(advFesSql128, 'CREATE TABLE') === 4 && advCount128(advFesSql128, 'INSERT INTO') === 4,
                    'T128i jede Seed-Datenbank bringt vier Tabellen mit Daten (#196)');
                  ok(advRaumSql128.indexOf('defekt') !== -1 && advRaumSql128.indexOf('Meteorit') !== -1
                    && advMusSql128.indexOf('verliehen_an') !== -1 && advMusSql128.indexOf('Der Morgen') !== -1
                    && advFesSql128.indexOf('unklar') !== -1 && advFesSql128.indexOf('Waldbühne') !== -1,
                    'T128j die Seed-Datenbanken tragen die Story-Details ihrer Abenteuer (#196)');
                  ok(advMig128.indexOf("['raumstation', 'museum', 'festival']") !== -1
                    && advMig128.indexOf('$abenteuer') !== -1,
                    'T128k die Beispielklasse erhält die neuen Abenteuer mit Schreibrechten (#196)');
                  ok(advCss128.indexOf('.adv-progress-fill') !== -1
                    && advCss128.indexOf('.adv-chapter-badge') !== -1
                    && advCss128.indexOf('.adv-current') !== -1
                    && advCss128.indexOf('.adv-finale') !== -1,
                    'T128l Quest-Karten, Fortschritt und Kapitel liegen im Stylesheet (#195)');
                  ok(advStory128.indexOf('adv-tools') !== -1
                    && advStory128.indexOf('Tabellen der Datenbank') !== -1
                    && advStory128.indexOf('ER-Diagramm') !== -1
                    && advStory128.indexOf('data-hl="sql"') !== -1,
                    'T128m Konsole, Tabellen und ER-Diagramm bleiben in der Kapitel-Ansicht erreichbar (#195)');
                  ok(advMeta128.indexOf('queryquest') === -1 && advMeta128.indexOf('knightlab') === -1
                    && advMeta128.indexOf('sql-island') === -1 && advMig128.indexOf('queryquest') === -1
                    && advHub128.indexOf('queryquest') === -1 && advStory128.indexOf('queryquest') === -1,
                    'T128n die Abenteuer nennen keine Vorbild-Projekte (#195, #196)');

                  /* T129 (#197-#199): Runde 25. Cursor deckungsgleich zum
                   * Overlay, echte Tipps statt Loesungen, erhaltene Eingaben
                   * mit gezielter Rueckmeldung, Kapitel als Weg mit Bildern. */
                  var appJs129 = advRead128('../public/assets/js/app.js');
                  ok(appJs129.indexOf('window.getComputedStyle(area)') !== -1
                    && appJs129.indexOf("'fontFamily', 'fontSize'") !== -1
                    && appJs129.indexOf('pre.style[prop]') !== -1,
                    'T129 das Highlight-Overlay uebernimmt die Schrift-Metrik der Textarea (#197)');
                  var cssMerged129 = advCss128.split('\n').some(function (line) {
                    return line.trim() === '.hlwrap.sql > textarea, .hlwrap.sql > pre { font-size: 0.95rem; }';
                  });
                  var cssOldAlone129 = advCss128.split('\n').some(function (line) {
                    return line.trim() === '.hlwrap.sql > pre { font-size: 0.95rem; }';
                  });
                  ok(cssMerged129 && !cssOldAlone129,
                    'T129b die SQL-Schriftgroesse gilt fuer Feld und Overlay in einer gemeinsamen Regel (#197)');
                  var schemaLite129 = advRead128('../sql/schema.sqlite.sql');
                  var schemaMy129 = advRead128('../sql/schema.mysql.sql');
                  ok(schemaLite129.indexOf('task TEXT NULL') !== -1 && schemaLite129.indexOf('hint_variants TEXT NULL') !== -1
                    && schemaMy129.indexOf('task TEXT NULL') !== -1 && schemaMy129.indexOf('hint_variants TEXT NULL') !== -1
                    && advMig128.indexOf('private static function ensureMissionTextColumns') !== -1
                    && advMig128.indexOf('private static function refreshSeedMissionTexts') !== -1,
                    'T129c Missionen bekommen Aufgabe und Tipp-Varianten als Spalten, Bestandsinstallationen werden nachgezogen (#198)');
                  ok(advMig128.indexOf('(template_id, position, kind, title, story, task, hint, hint_variants, solution_sql, check_sql, check_expected)') !== -1,
                    'T129d das INSERT der Missionen schreibt Aufgabe und Tipp-Varianten mit (#198)');
                  var advTaskRows129 = advMig128.split('\n').filter(function (line) {
                    return line.indexOf("'task' =>") !== -1 && line.indexOf("'solution_sql' =>") !== -1;
                  });
                  var advVal129 = function (line, key) {
                    var i = line.indexOf("'" + key + "' => ");
                    if (i === -1) { return null; }
                    var rest = line.slice(i + key.length + 6);
                    var q = rest.charAt(0);
                    if (q !== "'" && q !== '"') { return null; }
                    var out = '';
                    for (var k = 1; k < rest.length; k++) {
                      if (rest.charAt(k) === '\\') { out += rest.charAt(k + 1); k++; continue; }
                      if (rest.charAt(k) === q) { break; }
                      out += rest.charAt(k);
                    }
                    return out;
                  };
                  ok(advTaskRows129.length === 42,
                    'T129e alle 42 Abenteuer-Kapitel haben eine konkrete Aufgabe (#198, #199)');
                  var advHintsOk129 = true;
                  advTaskRows129.forEach(function (line) {
                    var hint = advVal129(line, 'hint');
                    var solution = advVal129(line, 'solution_sql');
                    if (hint === null || solution === null || hint === solution) { advHintsOk129 = false; }
                    if (line.indexOf("'hints' =>") === -1) { advHintsOk129 = false; }
                  });
                  ok(advHintsOk129,
                    'T129f kein Tipp verrraet die Loesung, jede Mission hat Tipp-Varianten fuer Fehlversuche (#198)');
                  var wsSrc129 = advRead128('../src/SqlWorkshop.php');
                  ok(wsSrc129.indexOf('public static function attemptFeedback') !== -1
                    && wsSrc129.indexOf("'groupby'") !== -1 && wsSrc129.indexOf("'having'") !== -1
                    && wsSrc129.indexOf("'subquery'") !== -1 && wsSrc129.indexOf("'scope'") !== -1
                    && wsSrc129.indexOf("'value'") !== -1 && wsSrc129.indexOf("'rows'") !== -1,
                    'T129g die Versuchs-Analyse erkennt typische Fehler und waehlt passende Hinweise (#198)');
                  ok(advCtrl128.indexOf("$_SESSION['mission_attempt']") !== -1
                    && advCtrl128.indexOf('SqlWorkshop::attemptFeedback(') !== -1
                    && advCtrl128.indexOf("'attempt' => $attempt") !== -1,
                    'T129h Fehlversuche bleiben mit gezieltem Hinweis in der Session und im View erhalten (#198)');
                  ok(advStory128.indexOf('adv-path') !== -1
                    && advStory128.indexOf('adv-station') !== -1
                    && advStory128.indexOf('adv-dot') !== -1
                    && advStory128.indexOf('adv-task') !== -1
                    && advStory128.indexOf('adv-feedback') !== -1
                    && advStory128.indexOf('chapterScene(') !== -1
                    && advStory128.indexOf("$attempt['sql']") !== -1,
                    'T129i die Kapitel liegen als Weg mit Stationen, Kapitelbild, Aufgaben-Kasten und erhaltenem Versuch (#199)');
                  var wsTpl129 = advRead128('../templates/sql_workspace.php');
                  ok(wsTpl129.indexOf('adv-feedback') !== -1
                    && wsTpl129.indexOf("$attempt['sql']") !== -1,
                    'T129j auch die klassischen Aufgaben erhalten die Eingabe und den Hinweis (#198)');
                  ok(advMeta128.indexOf('public static function chapterScene') !== -1
                    && advMeta128.split('<svg').length - 1 >= 47,
                    'T129k jedes Kapitel hat ein eigenes Szenenbild in allen vier Abenteuern (#199)');
                  ok(advCss128.indexOf('.adv-path { display: grid') !== -1
                    && advCss128.indexOf('.adv-dot-now') !== -1
                    && advCss128.indexOf('.adv-task {') !== -1
                    && advCss128.indexOf('.adv-feedback {') !== -1
                    && advCss128.indexOf('min-width: 900px') !== -1,
                    'T129l Weg-Layout, Stationen, Aufgaben- und Hinweis-Kasten liegen im Stylesheet (#199)');
                  var support129 = advRead128('../src/Support.php');
                  var changelog129 = advRead128('../CHANGELOG.md');
                  ok(changelog129.indexOf('## [0.25.0]') !== -1
                    && changelog129.indexOf('## [0.26.0]') !== -1,
                    'T129m der Changelog haelt die Eintraege zu 0.25.0 und 0.26.0 fest (Versionsstand selbst prueft T130r)');
                  ok(advMeta128.indexOf('—') === -1 && wsSrc129.indexOf('—') === -1
                    && advMig128.indexOf('—') === -1 && advCss128.indexOf('—') === -1
                    && advStory128.indexOf('—') === -1 && wsTpl129.indexOf('—') === -1
                    && wsSrc129.indexOf('queryquest') === -1 && wsTpl129.indexOf('queryquest') === -1,
                    'T129n neue Inhalte kommen ohne lange Gedankenstriche und ohne Vorbild-Namen aus (#198, #199)');

                  /* T130 (#200-#204): Runde 26. Cursor deckungsgleich auch bei
                   * langem SQL (kein weicher Umbruch mehr), Sprung zu den
                   * Aufgaben oben, InfoBook-Verteilen mit Ziel-Abschnitt-
                   * Auswahl, Schueler-Knopf repariert, ehrlicher Lese-Hinweis
                   * und Links nur auf gemeinschaftlich lesbare Seiten. */
                  var advRead130 = function (p) {
                    return advFs128.readFileSync(require.resolve(p), 'utf8');
                  };
                  var advCount130 = function (hay, needle) {
                    return hay.split(needle).length - 1;
                  };
                  var tplAdv130 = advRead130('../templates/sql_adventure.php');
                  var tplWs130 = advRead130('../templates/sql_workspace.php');
                  var app130 = advRead130('../public/assets/js/app.js');
                  var css130 = advRead130('../public/assets/css/style.css');
                  ok(advCount130(tplAdv130, 'wrap="off"') === 2 && advCount130(tplWs130, 'wrap="off"') === 2,
                    'T130 SQL-Eingabefelder brechen nicht mehr weich um, Cursor und Overlay laufen nicht auseinander (#200)');
                  ok(css130.indexOf('.hlwrap.sql > pre { white-space: pre; overflow-wrap: normal; }') !== -1,
                    'T130b das Overlay der SQL-Felder bricht genauso wenig um wie die Felder (#200)');
                  ok(app130.indexOf('fontKerning') !== -1 && app130.indexOf('fontVariantLigatures') !== -1
                    && app130.indexOf('textRendering') !== -1 && app130.indexOf('wordSpacing') !== -1,
                    'T130c das Overlay uebernimmt Kerning-, Ligaturen- und Breiten-Eigenschaften (#200)');
                  ok(tplAdv130.indexOf('href="#aufgaben"') !== -1 && tplAdv130.indexOf('🗺️ Abenteuer-Übersicht') !== -1
                    && tplAdv130.indexOf('📖 Zu den Kapiteln') !== -1,
                    'T130d die Abenteuer-Seite springt oben direkt zu den Kapiteln und zur Übersicht (#200)');
                  ok(tplWs130.indexOf('🎯 Zu den Aufgaben') !== -1 && tplWs130.indexOf('href="#aufgaben"') !== -1,
                    'T130e die klassische Werkstatt hat denselben Sprung zu ihren Aufgaben oben (#200)');
                  var tplBook130 = advRead130('../templates/ibook_book.php');
                  var tplPage130 = advRead130('../templates/ibook_page.php');
                  ok(tplBook130.indexOf('← Buch-Start') !== -1 && tplPage130.indexOf('← Buch-Start') !== -1
                    && tplBook130.indexOf('Auth::isTeacher()') !== -1 && tplPage130.indexOf('Auth::isTeacher()') !== -1,
                    'T130f der InfoBooks-Knopf fuehrt Schuelerinnen und Schueler zum Buch-Start statt ins Leere (#201)');
                  var ctrl130 = advRead130('../src/Controllers/InfoBookController.php');
                  ok(ctrl130.indexOf('resolveDistributeTarget') !== -1
                    && ctrl130.indexOf('function distributeSection') !== -1
                    && ctrl130.indexOf('function distributeBook') !== -1,
                    'T130g Verteilen loest das Ziel auf: Abschnitt waehlbar oder neu, ganzes Buch verteilt mit (#202)');
                  var routes130 = advRead130('../public/index.php');
                  ok(routes130.indexOf('/infobuecher/{id}/abschnitt/{sid}/verteilen') !== -1
                    && routes130.indexOf('/infobuecher/{id}/buch/verteilen') !== -1,
                    'T130h die Routen fuer Abschnitt- und Buch-Verteilen stehen (#202)');
                  var panel130 = advRead130('../templates/ibook_distribute_panel.php');
                  ok(panel130.indexOf('name="section_id"') !== -1
                    && panel130.indexOf('value="-1"') !== -1
                    && panel130.indexOf('name="new_section_title"') !== -1,
                    'T130i das Verteilen-Panel waehlt den Ziel-Abschnitt oder legt einen neuen mit Titel an (#202)');
                  ok(tplBook130.indexOf('Abschnitt an Klasse verteilen') !== -1
                    && tplBook130.indexOf('Ganzes Buch an Klasse verteilen') !== -1
                    && tplBook130.indexOf('ibook_distribute_panel.php') !== -1,
                    'T130j Abschnittsansicht verteilt Abschnitte und ganze Buecher (#202)');
                  var model130 = advRead130('../src/Models/InfoBook.php');
                  ok(model130.indexOf('savePage(int $pageId, string $title, array $blocks, bool $sharedContext = false)') !== -1
                    && model130.indexOf("in_array((string) $linkSection['area'], ['lernbereich', 'gemeinsam'], true)") !== -1,
                    'T130k Zusammenarbeit-Seiten speichern nur Links auf gemeinschaftlich lesbare Seiten (#203)');
                  ok(ctrl130.indexOf("(string) $section['area'] === 'gemeinsam'") !== -1,
                    'T130l der Controller uebergibt den Zusammenarbeit-Kontext ans Speichern (#203)');
                  ok(model130.indexOf('$blocks[$i][\'items\'][$j][\'link\'] = 0;') !== -1,
                    'T130m beim Buchuebergang verlieren Kopien ihre alten Seiten-Links (#202, #203)');
                  ok(ctrl130.indexOf('$sharedContext = (string) $section[\'area\'] === \'gemeinsam\';') !== -1
                    && ctrl130.indexOf('linkPagesShared') !== -1,
                    'T130n Editor-Link-Ziele und Präsentationsfolien filtern private Seiten im Zusammenarbeit-Bereich (#203)');
                  ok(tplPage130.indexOf('Vorschau deiner Seite') !== -1
                    && tplPage130.indexOf('Bereich Zusammenarbeit') !== -1,
                    'T130o der Lese-Hinweis unterscheidet Vorschau, Zusammenarbeit und Lernbereich (#203)');
                  ok(tplBook130.indexOf('Wer sieht was?') !== -1
                    && tplBook130.indexOf('ausschließlich Abschnitte dieses einen Buchs') !== -1,
                    'T130p die Abschnittsansicht erklaert der Lehrkraft, wer was sieht (#204)');
                  ok(ctrl130.indexOf('navSectionsFor') !== -1
                    && ctrl130.indexOf('visibleSectionForStudent') !== -1
                    && ctrl130.indexOf('InfoBook::sections($book->id)') !== -1,
                    'T130q die Sichtbarkeitsfilter bleiben verdrahtet: Abschnitte nur des eigenen Buchs, Schueler-Filter (#204)');
                  var support130 = advRead130('../src/Support.php');
                  var changelog130 = advRead130('../CHANGELOG.md');
                  ok(support130.indexOf("APP_VERSION = '0.26.0'") !== -1
                    && support130.indexOf("ASSET_VERSION = '82'") !== -1
                    && changelog130.indexOf('## [0.26.0]') !== -1,
                    'T130r Version 0.26.0 mit Asset-Stand 82 und Changelog-Eintrag');
                  ok(tplAdv130.indexOf('—') === -1 && tplWs130.indexOf('—') === -1
                    && tplBook130.indexOf('—') === -1 && tplPage130.indexOf('—') === -1
                    && panel130.indexOf('—') === -1 && ctrl130.indexOf('—') === -1 && model130.indexOf('—') === -1,
                    'T130s neue Inhalte kommen ohne lange Gedankenstriche aus (#200-#204)');

                  global.document = blkDocSaved;
                  global.fetch = blkFetchSaved;

                  console.log('');
                  if (failures.length === 0) { console.log('Alle ' + passed + ' UI-Tests bestanden.'); process.exit(0); }
                  else { console.log(failures.length + ' UI-Tests fehlgeschlagen.'); process.exit(1); }
                }, 1200);
              }, 700);

            }, 700);
          }, 700);
        }, 700);
      }, 200);
    }, 900);
  }, 700);
}, 700);
