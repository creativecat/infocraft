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

              console.log('');
              if (failures.length === 0) { console.log('Alle ' + passed + ' UI-Tests bestanden.'); process.exit(0); }
              else { console.log(failures.length + ' UI-Tests fehlgeschlagen.'); process.exit(1); }
            }, 700);
          }, 700);
        }, 700);
      }, 200);
    }, 900);
  }, 700);
}, 700);
