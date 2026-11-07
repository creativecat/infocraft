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
  var list = this.handlers[type] || [];
  for (var i = 0; i < list.length; i++) { list[i](ev); }
};
FakeEl.prototype.setSelectionRange = function (s, e) { this.selectionStart = s; this.selectionEnd = e; };

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
var tabRepl = consoleTab('repl', 'Objektkonsole', false);
var tabObjects = consoleTab('objects', 'Objekte', false);
var tabErrors = consoleTab('errors', 'Fehlerkonsole', false);
var tabApi = consoleTab('api', 'API-Hilfe', false);
var paneOut = consolePane('out', true);
var paneClasses = consolePane('classes', false);
var paneRepl = consolePane('repl', false);
var paneObjects = consolePane('objects', false);
var paneErrors = consolePane('errors', false);
var paneApi = consolePane('api', false);

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

    /* T15: Fehlerzeile wird in den Zeilennummern rot markiert. */
    ta.value = 'class C {' + NLt + 'void x() { y = ; }' + NLt + '}';
    ta.dispatch('input');
    runBtn.dispatch('click');
    var errCells = pane1.querySelector('.ln-gutter').querySelectorAll('.ln-err');
    ok(errCells.length >= 1, 'T15 Fehlerzeile in den Zeilennummern markiert');

    /* T16: API-Hilfe-Tab. */
    tabApi.dispatch('click');
    ok(!hidden(paneApi), 'T16a API-Hilfe-Fenster oeffnet');
    ok(hidden(paneOut), 'T16b Ausgabe-Fenster weicht');

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

    console.log('');
    if (failures.length === 0) { console.log('Alle ' + passed + ' UI-Tests bestanden.'); process.exit(0); }
    else { console.log(failures.length + ' UI-Tests fehlgeschlagen.'); process.exit(1); }
  }, 700);
}, 700);
