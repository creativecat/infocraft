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
  var list = this.handlers[type] || [];
  var i;
  for (i = 0; i < list.length; i++) { list[i](ev); }
  var node = this.parent;
  var sawBody = (global.document && global.document.body === this);
  while (!stopped && node) {
    if (global.document && global.document.body === node) { sawBody = true; }
    var l2 = (node.handlers && node.handlers[type]) || [];
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
  for (var i = 0; i < l.length; i++) { l[i](ev); }
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
                ok(ibFoldBtn61.textContent === 'Inhalt ◂' && !ibAside61.classList.contains('ibook-pagenav-off'), 'T61b Standard ist aufgeklappt');
                ibFoldBtn61.dispatch('click');
                ok(ibAside61.classList.contains('ibook-pagenav-off') === true && ibFoldBtn61.textContent === 'Inhalt ▸' && ibFoldBtn61.getAttribute('aria-expanded') === 'false', 'T61c Klick klappt die komplette Leiste ein');
                ok(ibStore61['ic-ibook-navfold'] === '1', 'T61d der Zustand wird im Browser gespeichert');
                ibFoldBtn61.dispatch('click');
                ok(ibAside61.classList.contains('ibook-pagenav-off') === false && ibFoldBtn61.getAttribute('aria-expanded') === 'true', 'T61e zweiter Klick klappt wieder auf');
                global.localStorage = { getItem: function () { return '1'; }, setItem: function () {} };
                var ibAside61b = new FakeEl('aside');
                var ibFoldBtn61b = new FakeEl('button');
                window.IC_IBOOK_NAVFOLD(ibAside61b, ibFoldBtn61b);
                ok(ibAside61b.classList.contains('ibook-pagenav-off') === true && ibFoldBtn61b.textContent === 'Inhalt ▸', 'T61f gespeicherter eingeklappter Zustand wird beim Laden wiederhergestellt');
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
                  /* T67 (#87): Autovervollstaendigung: Klassennamen beim Tippen,
                   * nach dem Punkt Attribute und Methoden, Tab/Enter fuegt ein. */
                  var ibAc67 = ibCodeBox65.querySelector('.icv-code-ac');
                  ibCodeTa65.value = 'Au';
                  ibCodeTa65.selectionStart = 2;
                  ibCodeTa65.selectionEnd = 2;
                  ibCodeTa65.dispatch('input');
                  ok(ibAc67.classList.contains('icv-code-ac-open') === true && ibAc67.children.length === 1 && ibAc67.children[0].textOf() === 'Auto', 'T67 tippen oeffnet die Vorschlaege mit dem Klassennamen');
                  ibCodeTa65.dispatch('keydown', { key: 'Tab' });
                  ok(ibCodeTa65.value === 'Auto', 'T67b Tab uebernimmt den Vorschlag ins Code-Feld');
                  ok(ibAc67.classList.contains('icv-code-ac-open') === false, 'T67c nach der Uebernahme schliessen die Vorschlaege');
                  ibCodeTa65.value = 'Auto.fa';
                  ibCodeTa65.selectionStart = 8;
                  ibCodeTa65.selectionEnd = 8;
                  ibCodeTa65.dispatch('input');
                  var acLabels67 = [];
                  for (var ai67 = 0; ai67 < ibAc67.children.length; ai67++) { acLabels67.push(ibAc67.children[ai67].textOf()); }
                  ok(ibAc67.classList.contains('icv-code-ac-open') === true && acLabels67.join('|').indexOf('fahren()') !== -1, 'T67d nach dem Punkt schlagen Attribute und Methoden vor');
                  ibCodeTa65.dispatch('keydown', { key: 'ArrowDown' });
                  ibCodeTa65.dispatch('keydown', { key: 'Enter' });
                  ok(ibCodeTa65.value === 'Auto.fahren(', 'T67e Enter fuegt die gewaehlte Methode ein');
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
                ok(String(foldBtn76.textContent).indexOf('Inhalt') !== -1, 'T76 der Ausklapp-Knopf heisst Inhalt');
                aside76.classList.add('ibook-pagenav-off');
                foldBtn76.dispatch('click');
                ok(!aside76.classList.contains('ibook-pagenav-off'), 'T76b ein Klick klappt die Leiste wieder aus');
                ok(String(foldBtn76.textContent).indexOf('Inhalt') !== -1, 'T76c die Beschriftung bleibt auch eingeklappt lesbar');
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
                     T87-T95 (#116-#121): Block-Werkstatt. Zunaechst headless
                     (Laufzeit ohne DOM), danach ein eigener FakeDoc fuer den
                     Editor (T86-Muster: eigenes document + frisches require,
                     weil window-Exponate auf die zuletzt initialisierte
                     Instanz zeigen). */
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
                  var blkFlagBtn = blkReg('blk-flag', 'button');
                  blkReg('blk-stop', 'button');
                  var blkRuninfoEl = blkReg('blk-runinfo');
                  var blkSaveinfoEl = blkReg('blk-saveinfo');
                  blkReg('blk-var-add', 'button');
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
                  ok(Object.keys(BLK.BLOCKS).length >= 40, 'T87b die Registry enthaelt alle Bloecke der acht Kategorien');
                  var blkParsed = BLK.parseProgram(blkJsonEl.value);
                  ok(blkParsed.scripts.length === 1 && blkParsed.scripts[0].top.op === 'flag' && blkParsed.scripts[0].top.next.op === 'say_for', 'T87c das gespeicherte JSON laedt als Skript mit Fahne und sage-Befehl');
                  ok(BLK.parseProgram('kein json').scripts.length === 0, 'T87d ungueltiges JSON laedt als leeres Programm statt zu abstuerzen');

                  /* T88: Bewegung, warte, wiederhole, fortlaufend, stoppe alles */
                  var rt88 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('move_steps', { steps: '10' }))));
                  rt88.flag(0);
                  rt88.frame(0);
                  ok(rt88.sprite.x === 10, 'T88 gehe 10 Schritte in Richtung 90 Grad bewegt die Figur nach rechts');
                  var rt88b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('point_dir', { deg: '0' }, blkRaw('move_steps', { steps: '10' })))));
                  rt88b.flag(0);
                  rt88b.frame(0);
                  ok(rt88b.sprite.y === 10, 'T88b Richtung 0 Grad heisst nach oben');
                  var rt88c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('wait', { secs: '1' }, blkRaw('change_y', { dy: '7' })))));
                  rt88c.flag(0);
                  rt88c.frame(999);
                  ok(rt88c.sprite.y === 0, 'T88c waehrend des Wartens steht das Skript still');
                  rt88c.frame(1000);
                  ok(rt88c.sprite.y === 0, 'T88d die Wartezeit zaehlt ab dem ersten Anlauf (hier: bis Frame 1999)');
                  rt88c.frame(2500);
                  ok(rt88c.sprite.y === 7, 'T88e nach Ablauf der Wartezeit laeuft es weiter');
                  var rt88e = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('repeat', { count: '3' }, null, [blkRaw('change_y', { dy: '5' })])) }] })));
                  rt88e.flag(0);
                  rt88e.frame(0);
                  rt88e.frame(0);
                  rt88e.frame(0);
                  ok(rt88e.sprite.y === 15, 'T88f wiederhole 3 mal lauft mit einem Durchlauf pro Bild');
                  rt88e.frame(0);
                  ok(rt88e.running() === false, 'T88g nach dem letzten Durchlauf ist der Thread beendet');
                  var rt88g = BLK.newRuntime(BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's1', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('forever', {}, null, [blkRaw('move_steps', { steps: '1' })])) }] })));
                  rt88g.flag(0);
                  var f88;
                  for (f88 = 0; f88 < 10; f88++) { rt88g.frame(f88); }
                  ok(rt88g.sprite.x === 10, 'T88h wiederhole fortlaufend bewegt Bild fuer Bild');
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
                  ok(rt90c.vars.t === 1, 'T90c die Stoppuhr zaehlt die Sekunden seit dem Fahnen-Start');

                  /* T91: Stift und Sprechblase */
                  var rt91 = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('pen_down', {}, blkRaw('move_steps', { steps: '100' })))));
                  rt91.flag(0);
                  rt91.frame(0);
                  ok(rt91.pen.segments.length === 1 && rt91.pen.segments[0].x2 === 100, 'T91 Stift ab zeichnet beim Bewegen eine Spur');
                  var rt91b = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('pen_down', {}, blkRaw('move_steps', { steps: '50' }, blkRaw('pen_clear'))))));
                  rt91b.flag(0);
                  rt91b.frame(0);
                  ok(rt91b.pen.segments.length === 0, 'T91b loesche alle Spuren leert die Spuren');
                  var rt91c = BLK.newRuntime(blkProgram(blkRaw('flag', {}, blkRaw('say_for', { msg: 'Hallo!', secs: '2' }))));
                  rt91c.flag(0);
                  rt91c.frame(0);
                  ok(rt91c.bubble() !== null && rt91c.bubble().text === 'Hallo!', 'T91c sage ... fuer n Sekunden zeigt die Sprechblase');
                  rt91c.frame(2500);
                  ok(rt91c.bubble() === null, 'T91d die Sprechblase verschwindet nach Ablauf der Zeit');

                  /* T92: Serialisierung und Validierung */
                  var blkSer = BLK.serializeProgram(blkParsed);
                  ok(blkSer.v === 1 && blkSer.scripts.length === 1 && blkSer.scripts[0].top.next.op === 'say_for', 'T92 das Programm serialisiert sich mit Skripten, Feldern und Ketten');
                  var blkRawMany = { v: 1, vars: [], sprite: {}, scripts: [] };
                  var i92;
                  for (i92 = 0; i92 < 100; i92++) { blkRawMany.scripts.push({ id: 's' + i92, x: 0, y: 0, top: blkRaw('flag') }); }
                  var blkMany = BLK.parseProgram(JSON.stringify(blkRawMany));
                  ok(blkMany.scripts.length === 60, 'T92b mehr als 60 Skripte werden auf das Limit begrenzt');
                  var blkEvil = BLK.parseProgram(JSON.stringify({ v: 1, vars: [], sprite: {}, scripts: [{ id: 's', x: 0, y: 0, top: blkRaw('flag', {}, blkRaw('hakemeister', {}, blkRaw('hackemeister_zwei'))) }] }));
                  ok(blkEvil.scripts.length === 1 && blkEvil.scripts[0].top.next === null, 'T92c unbekannte Bloecke im geladenen JSON entfallen kommentarlos');

                  /* T93: Editor-UI am eigenen FakeDoc */
                  var blkUi = BLK.ui;
                  ok(blkUi !== null && typeof blkUi === 'object', 'T93 der Editor initialisiert sich auf der Blöcke-Seite von selbst');
                  ok(blkCatsEl.children.length === 8, 'T93b die Palette listet alle acht Kategorien');
                  ok(blkPalEl.children.length === 2, 'T93c die Ereignis-Kategorie zeigt Fahne und Tast-Hut');
                  blkCatsEl.children[1].dispatch('click');
                  ok(blkPalEl.querySelector('.blk[data-pal-op="move_steps"]') !== null, 'T93d der Kategorie-Wechsel zeigt die Bewegungs-Bloecke');
                  ok(blkHelpEl.querySelectorAll('.blk-help-row').length === Object.keys(BLK.BLOCKS).length, 'T93e die Befehlsuebersicht erklaert jeden Block der Registry');
                  var blkBefore = blkUi.program.scripts.length;
                  blkUi.addBlock('flag', 200, 120);
                  ok(blkUi.program.scripts.length === blkBefore + 1 && blkScriptEl.querySelectorAll('.blk-chain').length === blkBefore + 1, 'T93f ein neuer Fahne-Block entsteht als Skript auf der Flaeche');
                  var blkDelId = blkUi.program.scripts[blkUi.program.scripts.length - 1].top.id;
                  blkUi.deleteBlockTree(blkDelId);
                  ok(blkUi.program.scripts.length === blkBefore, 'T93g der Loesch-Knopf entfernt den Block samt Skript');
                  ok(blkUi.addVariable('punkte') === true && blkUi.program.vars.indexOf('punkte') !== -1, 'T93h eine neue Variable landet in der Liste');
                  ok(blkUi.addVariable('punkte') === false, 'T93i doppelte Variablennamen lehnt der Editor ab');

                  /* T94: Autospeichern */
                  blkFetchCalls.length = 0;
                  blkUi.saveNow();
                  ok(blkFetchCalls.length === 1 && blkFetchCalls[0].url === '/werkstatt/code/5', 'T94 das Autospeichern geht als POST auf die Projekt-URL');
                  
                  var blkBodyJson = decodeURIComponent(blkFetchCalls[0].body.split('content=')[1] || '');
                  ok(blkBodyJson.indexOf('{"v":1') === 0 && blkBodyJson.indexOf('"op":"flag"') !== -1, 'T94c das komplette Skriptmodell wird als JSON gespeichert');
                  ok(blkJsonEl.value.indexOf('"op":"flag"') !== -1, 'T94d das JSON-Feld fuer den normalen Speichern-Knopf bleibt synchron');
                  ok(blkSaveinfoEl.textContent.indexOf('automatisch gespeichert') !== -1, 'T94e der Editor meldet den automatischen Speichervorgang im Statusfeld');

                  /* T95: Buehne und Lauf in der UI */
                  blkFlagBtn.dispatch('click');
                  ok(blkUi.runtime() !== null && blkRuninfoEl.textContent.indexOf('läuft') !== -1, 'T95 der Fahne-Knopf startet das Programm auf der Buehne');
                  ok(blkPenEl.__ctx.__calls.indexOf('clearRect') !== -1, 'T95b jeder Start loescht zunaechst alle Spuren von der Buehne');
                  blkUi.tick(0);
                  blkUi.tick(1500);
                  ok(blkCtrEl.__ctx.__calls.indexOf('fillText:🤖') !== -1, 'T95c die Figur zeichnet sich in der Mitte der Buehne');
                  blkUi.tick(2500);
                  ok(blkUi.runtime().sprite.x === 50, 'T95d nach der Sprechblase laeuft der weitere Block (hier: 50 Schritte)');

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
