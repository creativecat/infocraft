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
    key: '',
    preventDefault: function () {},
  };
  if (opts) { for (var k in opts) { ev[k] = opts[k]; } }
  var list = this.handlers[type] || [];
  for (var i = 0; i < list.length; i++) { list[i](ev); }
};

function FakeDoc() {
  this.body = new FakeEl('body');
  this._byId = {};
}
FakeDoc.prototype.createElement = function (tag) { return new FakeEl(tag); };
FakeDoc.prototype.createTextNode = function (text) {
  var el = new FakeEl('#text');
  el.textContent = String(text);
  return el;
};
FakeDoc.prototype.getElementById = function (id) { return this._byId[id] || null; };
FakeDoc.prototype.querySelectorAll = function (sel) { return this.body.querySelectorAll(sel); };
FakeDoc.prototype.querySelector = function (sel) {
  var all = this.body.querySelectorAll(sel);
  return all.length > 0 ? all[0] : null;
};
FakeDoc.prototype.register = function (el, id) { el.id = id; this._byId[id] = el; this.body.appendChild(el); };

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
  var ta = new FakeEl('textarea');
  ta.value = code;
  p.appendChild(ta);
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
var tabRepl = consoleTab('repl', 'Objektkonsole', false);
var tabObjects = consoleTab('objects', 'Objekte', false);
var tabErrors = consoleTab('errors', 'Fehlerkonsole', false);
var paneOut = consolePane('out', true);
var paneClasses = consolePane('classes', false);
var paneRepl = consolePane('repl', false);
var paneObjects = consolePane('objects', false);
var paneErrors = consolePane('errors', false);

var runBtn = new FakeEl('button');
doc.register(runBtn, 'run-java');
var consoleEl = new FakeEl('pre');
doc.register(consoleEl, 'java-console');
var errBox = new FakeEl('div');
doc.register(errBox, 'java-errors');
var replLog = new FakeEl('div');
doc.register(replLog, 'java-repl-log');
var replIn = new FakeEl('input');
doc.register(replIn, 'java-repl-in');
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

/* ---------- java-vm.js laden: wireUi() laeuft sofort ---------- */
global.document = doc;
global.window = undefined;
var vm = require('../public/assets/js/java-vm.js');

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

/* T2: Konsolen-Tabs schalten die Panes (Klassendiagramm-Tab inklusive). */
tabClasses.dispatch('click');
ok(!hidden(paneClasses), 'T2a Tab Klassen zeigt das Klassendiagramm-Fenster');
ok(hidden(paneOut), 'T2b Ausgabe-Fenster versteckt');
tabRepl.dispatch('click');
ok(!hidden(paneRepl) && hidden(paneClasses), 'T2c Objektkonsole erreicht');
tabOut.dispatch('click');
ok(!hidden(paneOut) && hidden(paneRepl), 'T2d zurueck zur Ausgabe');

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

/* T6: Objektkonsole: Objekt erzeugen per Enter. */
replIn.value = 'Auto b = new Auto("Audi");';
replIn.dispatch('keydown', { key: 'Enter' });
ok(replLog.children.length > 0, 'T6a Objektkonsole protokolliert die Eingabe');
replIn.value = 'b.vorstellen();';
replIn.dispatch('keydown', { key: 'Enter' });
var last = replLog.children[replLog.children.length - 1];
ok(last.textOf().indexOf('Audi') !== -1, 'T6b Methodenaufruf liefert Ergebnis');

/* T7: Live-Klassendiagramm beim Tippen (ohne Ausfuehren). */
var ta = pane1.querySelector('textarea');
ta.value = ta.value + '\npublic class Fahrrad { public void klingeln() { } }\n';
ta.dispatch('input');
setTimeout(function () {
  ok(classBox.innerHTML.indexOf('Fahrrad') !== -1, 'T7 neue Klasse erscheint im Diagramm, ohne Ausfuehren');

  /* T8: Objekte-Tab und Leeren-Button. */
  tabObjects.dispatch('click');
  ok(!hidden(paneObjects), 'T8a Objekte-Fenster oeffnet');
  clearBtn.dispatch('click');
  ok(consoleEl.textContent === '' && errBox.innerHTML === '', 'T8b Leeren rueumt Konsole und Fehler auf');

  /* T9: Fehlerfall: kaputter Code zerstoert das Diagramm nicht. */
  ta.value = 'public class ??? { syntax killer !!!';
  ta.dispatch('input');
  setTimeout(function () {
    ok(classBox.innerHTML.length > 0, 'T9 bei Syntaxfehlern bleibt eine Diagramm-Anschnitt stehen');
    console.log('');
    if (failures.length === 0) { console.log('Alle ' + passed + ' UI-Tests bestanden.'); process.exit(0); }
    else { console.log(failures.length + ' UI-Tests fehlgeschlagen.'); process.exit(1); }
  }, 700);
}, 700);
