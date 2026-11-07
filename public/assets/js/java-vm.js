/**
 * InfoCraft Java-Simulation: LEXER + PARSER (Teil 1 von 2)
 * Eigener Java-Subset-Interpreter, läuft komplett im Browser (keine Server-
 * Kompilierung). Ein Dateipaar pro Klasse, mehrere Dateien pro Projekt.
 *
 * Fehlerkultur: Jede Meldung (kind syntax/compile/runtime/stderr) trägt
 * Datei + Zeile und eine deutsche Hilfestellung (hint) für Schülerinnen
 * und Schüler. Es5-Styles, damit ältere Schulgerte mitkommen.
 */
(function (global) {
  'use strict';

  var NL = String.fromCharCode(10);
  var KEYWORDS = Object.create(null);
  var KW = ['class', 'interface', 'enum', 'record', 'extends', 'implements',
    'public', 'private', 'protected', 'static', 'final', 'abstract',
    'void', 'new', 'return', 'if', 'else', 'while', 'for', 'do',
    'break', 'continue', 'this', 'super', 'null', 'true', 'false',
    'int', 'long', 'short', 'byte', 'double', 'float', 'boolean', 'char'];
  var ki;
  for (ki = 0; ki < KW.length; ki++) { KEYWORDS[KW[ki]] = 1; }
  var PRIMS = Object.create(null);
  PRIMS.int = 'int'; PRIMS.long = 'int'; PRIMS.short = 'int'; PRIMS.byte = 'int';
  PRIMS.double = 'double'; PRIMS.float = 'double'; PRIMS.boolean = 'boolean'; PRIMS.char = 'char';

  /* Operatoren: längere zuerst, damit <= vor < gelesen wird. */
  var OPS = ['<=', '>=', '==', '!=', '&&', '||', '++', '--',
    '+=', '-=', '*=', '/=', '%=', '<', '>', '+', '-', '*', '/', '%', '=', '!', '?', ':'];
  var PUNCT = Object.create(null);
  var PS = ['{', '}', '(', ')', '[', ']', '.', ',', ';'];
  var pi;
  for (pi = 0; pi < PS.length; pi++) { PUNCT[PS[pi]] = 1; }

  function JError(kind, file, line, message, hint) {
    return { kind: kind, file: file, line: line, message: message, hint: hint || '' };
  }

  /* ================= Lexer ================= */
  function lex(file, src, errs) {
    var toks = [];
    var i = 0;
    var line = 1;
    var n = src.length;
    function fail(msg, hint, l) {
      errs.push(JError('syntax', file, l || line, msg, hint));
    }
    while (i < n) {
      var c = src.charAt(i);
      if (c === NL) { line++; i++; continue; }
      if (c === ' ' || c === String.fromCharCode(9) || c === String.fromCharCode(13)) { i++; continue; }
      /* Kommentare */
      if (c === '/' && src.charAt(i + 1) === '/') {
        while (i < n && src.charAt(i) !== NL) { i++; }
        continue;
      }
      if (c === '/' && src.charAt(i + 1) === '*') {
        var cl = line;
        i += 2;
        var closed = false;
        while (i < n) {
          if (src.charAt(i) === NL) { line++; }
          if (src.charAt(i) === '*' && src.charAt(i + 1) === '/') { i += 2; closed = true; break; }
          i++;
        }
        if (!closed) { fail('Der Kommentar ab Zeile ' + cl + ' wird nie geschlossen (fehlendes Stern-Schrägstrich).', 'Schreibe am Ende des Kommentars Stern-Schrägstrich: ' + '*/' + ' statt nur *.'); return null; }
        continue;
      }
      /* Annotationen (@Override) überspringen */
      if (c === '@') {
        i++;
        while (i < n && /[A-Za-z0-9_]/.test(src.charAt(i))) { i++; }
        continue;
      }
      /* Bezeichner und Schlüsselwörter */
      if (/[A-Za-z_]/.test(c)) {
        var id = '';
        while (i < n && /[A-Za-z0-9_]/.test(src.charAt(i))) { id += src.charAt(i); i++; }
        toks.push(KEYWORDS[id] ? { k: 'kw', v: id, line: line } : { k: 'id', v: id, line: line });
        continue;
      }
      /* Zahlen: 42 oder 3.14 (keine Exponenten nötig), Hex 0xff8000 */
      if (/[0-9]/.test(c)) {
        var num = '';
        var isDbl = false;
        if (c === '0' && (src.charAt(i + 1) === 'x' || src.charAt(i + 1) === 'X')) {
          i += 2;
          var hx = '';
          while (i < n && /[0-9a-fA-F]/.test(src.charAt(i))) { hx += src.charAt(i); i++; }
          if (hx === '') { fail('Nach 0x kommen Hex-Ziffern.', 'z. B. 0xff8000'); return null; }
          toks.push({ k: 'int', v: parseInt(hx, 16), line: line });
          continue;
        }
        while (i < n && /[0-9]/.test(src.charAt(i))) { num += src.charAt(i); i++; }
        if (src.charAt(i) === '.' && /[0-9]/.test(src.charAt(i + 1) || '')) {
          isDbl = true;
          num += '.';
          i++;
          while (i < n && /[0-9]/.test(src.charAt(i))) { num += src.charAt(i); i++; }
        }
        toks.push({ k: isDbl ? 'dbl' : 'int', v: isDbl ? parseFloat(num) : parseInt(num, 10), line: line });
        continue;
      }
      /* Zeichen: 'a', Newline, Backslash */
      if (c === "'" ) {
        var cl2 = line;
        i++;
        if (i >= n || src.charAt(i) === NL) { fail('Hochkomma ohne Zeichen.', "Ein char steht in einzelnen Hochkommata: 'a'"); return null; }
        var code = src.charCodeAt(i);
        if (src.charAt(i) === String.fromCharCode(92)) {
          i++;
          var esc = src.charAt(i);
          code = esc === 'n' ? 10 : (esc === 't' ? 9 : (esc === 'r' ? 13 : (esc === '0' ? 0 : (esc === String.fromCharCode(92) ? 92 : (esc === "'" ? 39 : (esc === '"' ? 34 : esc.charCodeAt(0)))))));
        }
        i++;
        if (src.charAt(i) !== "'") { fail('Ein char besteht aus genau einem Zeichen.', "Beispiel: 'a' (mit einfachen Hochkommata). Für Text nutze doppelte: \"abc\""); return null; }
        i++;
        toks.push({ k: 'char', v: code, line: cl2 });
        continue;
      }
      /* Strings */
      if (c === '"') {
        var sl = line;
        i++;
        var s = '';
        var closed2 = false;
        while (i < n && src.charAt(i) !== NL) {
          var d = src.charAt(i);
          if (d === '"') { closed2 = true; i++; break; }
          if (d === String.fromCharCode(92)) {
            i++;
            var e = src.charAt(i);
            s += e === 'n' ? NL : (e === 't' ? String.fromCharCode(9) : (e === 'r' ? String.fromCharCode(13) : (e === String.fromCharCode(92) ? String.fromCharCode(92) : (e === '"' ? '"' : e))));
            i++;
            continue;
          }
          s += d;
          i++;
        }
        if (!closed2) { fail('Der String ab Zeile ' + sl + ' wird nie geschlossen (fehlendes Anführungszeichen).', 'Jeder String beginnt und endet mit doppelten Anführungszeichen.'); return null; }
        toks.push({ k: 'str', v: s, line: sl });
        continue;
      }
      /* Punkte und Klammern */
      if (PUNCT[c]) { toks.push({ k: 'p', v: c, line: line }); i++; continue; }
      /* Operatoren */
      var found = null;
      var oi;
      for (oi = 0; oi < OPS.length; oi++) {
        if (src.substr(i, OPS[oi].length) === OPS[oi]) { found = OPS[oi]; break; }
      }
      if (found) { toks.push({ k: 'op', v: found, line: line }); i += found.length; continue; }
      fail('Unerwartetes Zeichen: ' + c, 'Dieses Zeichen gehört nicht zu Java. Prüfe Tastatur-Layout und Sonderzeichen.');
      return null;
    }
    toks.push({ k: 'eof', v: '', line: line });
    return toks;
  }

  /* ================= Parser ================= */
  function Parser(file, toks, errs) {
    var pos = 0;
    var srcFile = file;

    function peek(off) {
      var p = pos + (off || 0);
      return toks[Math.min(p, toks.length - 1)];
    }
    function next() {
      var t = toks[pos];
      if (t.k !== 'eof') { pos++; }
      return t;
    }
    function at(k, v) {
      var t = peek();
      return t.k === k && (v === undefined || t.v === v);
    }
    function err(msg, hint, line) {
      var e = JError('syntax', srcFile, line || peek().line, msg, hint || '');
      errs.push(e);
      throw { stop: true, jerr: e };
    }
    function describe(t) {
      if (t.k === 'eof') { return 'Dateiende'; }
      if (t.k === 'id' || t.k === 'kw') { return t.v; }
      if (t.k === 'int' || t.k === 'dbl') { return 'Zahl'; }
      if (t.k === 'str') { return 'Text'; }
      if (t.k === 'char') { return 'Zeichen'; }
      return "'" + t.v + "'";
    }
    function expectP(v, what, hint) {
      var t = peek();
      if (!at('p', v)) { err('Erwartet: ' + (what || v) + ', gefunden: ' + describe(t), hint); }
      return next();
    }
    function skipSemi() {
      while (at('p', ';')) { next(); }
    }

    /* package/import-Zeilen überspringen (sinnt leer, aber harmlos) */
    function skipImports() {
      while (at('id', 'package') || at('id', 'import')) {
        next();
        while (!at('p', ';') && !at('eof')) { next(); }
        skipSemi();
      }
    }

    function startsExpression(t) {
      if (!t) { return false; }
      if (t.k === 'int' || t.k === 'dbl' || t.k === 'str' || t.k === 'char') { return true; }
      if (t.k === 'id') { return true; }
      if (t.k === 'p' && t.v === '(') { return true; }
      if (t.k === 'kw') { return t.v === 'new' || t.v === 'this' || t.v === 'super' || t.v === 'null' || t.v === 'true' || t.v === 'false'; }
      return false;
    }

    function parseUnit() {
      skipImports();
      var classes = [];
      var stmts = [];
      skipSemi();
      while (!at('eof')) {
        if (at('kw', 'interface')) { err('interface wird noch nicht unterstützt.', 'Nutze class (die Simulation kennt erst Klassen ohne interfaces).'); }
        if (at('kw', 'enum')) { err('enum wird noch nicht unterstützt.', 'Nutze eine class mit konstanten Werten als statische Felder.'); }
        if (at('kw', 'record')) { err('record wird noch nicht unterstützt.', 'Nutze eine normale class mit Konstruktor und Attributen.'); }
        if (at('kw', 'class') || at('kw', 'public') || at('kw', 'private') || at('kw', 'protected') || at('kw', 'static') || at('kw', 'final') || at('kw', 'abstract')) {
          classes.push(parseClass());
        } else {
          /* Anweisungen direkt in der Datei: Objekte erzeugen, ausgeben, rechnen ... */
          stmts.push(parseStatement());
        }
        skipSemi();
      }
      return { classes: classes, stmts: stmts };
    }

    function parseClass() {
      var mods = parseModifiers(); /* Sichtbarkeit merken, Rest ist für die Simulation irrelevant */
      if (!at('kw', 'class')) { err('Erwartet: class, gefunden: ' + describe(peek()), 'In einer Datei dürfen mehrere Klassen stehen (public class Name { ... }), und auch Anweisungen direkt ohne Klasse, z. B.: Test t = new Test();'); }
      next(); /* class */
      if (!at('id')) { err('Nach class folgt der Name der Klasse.', 'z. B. public class Auto { ... }'); }
      var nameTok = next();
      var cls = { name: nameTok.v, parent: null, isPublic: mods.visibility === 'public', fields: [], ctors: [], methods: [], line: nameTok.line, srcFile: srcFile };
      if (at('kw', 'extends')) {
        next();
        if (!at('id')) { err('Nach extends folgt der Name der Oberklasse.', 'z. B. class Hund extends Tier'); }
        cls.parent = next().v;
      }
      if (at('kw', 'implements')) { err('implements wird noch nicht unterstützt.', 'Die Simulation kennt erst Vererbung mit extends.'); }
      expectP('{', '{', 'Nach dem Klassennamen kommt die öffnende geschweifte Klammer.');
      while (!at('p', '}') && !at('eof')) {
        parseMember(cls);
      }
      expectP('}', '}', 'Die Klasse braucht eine schließende geschweifte Klammer am Ende.');
      return cls;
    }

    function parseModifiers() {
      var mods = { isStatic: false, visibility: null };
      for (;;) {
        if (at('kw', 'public')) { mods.visibility = 'public'; next(); continue; }
        if (at('kw', 'private')) { mods.visibility = 'private'; next(); continue; }
        if (at('kw', 'protected')) { mods.visibility = 'protected'; next(); continue; }
        if (at('kw', 'static')) { mods.isStatic = true; next(); continue; }
        if (at('kw', 'final') || at('kw', 'abstract')) { next(); continue; }
        break;
      }
      return mods;
    }

    function parseMember(cls) {
      var mods = parseModifiers();
      var t = peek();
      /* void-Methoden */
      if (t.k === 'kw' && t.v === 'void') {
        next();
        if (!at('id')) { err('Nach void folgt der Methodenname.', 'z. B. void drucken() { ... }'); }
        var vname = next().v;
        cls.methods.push({ name: vname, ret: { name: 'void', arr: false, void: true }, isStatic: mods.isStatic, visibility: mods.visibility, params: parseParams(), body: parseBlock(), line: t.line });
        return;
      }
      /* Konstruktor: Name == Klassenname */
      if (t.k === 'id' && t.v === cls.name && peek(1).k === 'p' && peek(1).v === '(') {
        next();
        cls.ctors.push({ params: parseParams(), visibility: mods.visibility, body: parseBlock(), line: t.line });
        return;
      }
      /* Attribute und Methoden mit Rückgabetyp */
      if (t.k === 'kw' && PRIMS[t.v]) {
        addTyped(cls, parseType(true), mods, t);
        return;
      }
      if (t.k === 'id') {
        var ty = parseType(true);
        if (!ty) { err('Unverständliches Klassenmitglied.', 'Erwartet: Attribut (Typ name;), Konstruktor (Name(...)) oder Methode (Typ name(...)).'); }
        if (!at('id')) { err('Nach dem Typ ' + ty.name + ' folgt ein Name.', 'z. B. ' + ty.name + ' attribut; oder ' + ty.name + ' methode() { ... }'); }
        addTyped(cls, ty, mods, t);
        return;
      }
      err('Unverständliches Klassenmitglied.', 'Erwartet: Attribut (Typ name;), Konstruktor (Name(...)) oder Methode (Typ name(...)).');
    }

    function addTyped(cls, ty, mods, t0) {
      if (!at('id')) { err('Hier fehlt ein Name.', 'z. B. ' + ty.name + ' wert;'); }
      var nameTok = next();
      if (at('p', '(')) {
        cls.methods.push({ name: nameTok.v, ret: ty, isStatic: mods.isStatic, visibility: mods.visibility, params: parseParams(), body: parseBlock(), line: nameTok.line });
        return;
      }
      /* Attribut(e) */
      for (;;) {
        var init = null;
        if (at('op', '=')) {
          next();
          init = at('p', '{') ? parseArrayLiteral(ty.name, ty.arr) : parseExpr();
        }
        cls.fields.push({ name: nameTok.v, type: ty, isStatic: mods.isStatic, visibility: mods.visibility, init: init, line: nameTok.line });
        if (at('p', ',')) { next(); if (!at('id')) { err('Nach dem Komma folgt ein weiterer Name.', 'z. B. int a, b;'); } nameTok = next(); continue; }
        break;
      }
      expectP(';', ';', 'Jede Attribut-Deklaration endet mit Semikolon.');
    }

    function parseType(allowArray) {
      var t = peek();
      var name;
      if (t.k === 'kw' && PRIMS[t.v]) { name = PRIMS[t.v]; next(); }
      else if (t.k === 'id') { name = t.v; next(); }
      else { return null; }
      var dims = 0;
      if (allowArray !== false) {
        while (at('p', '[')) {
          next();
          expectP(']', ']', 'Array-Typ: hinter dem Namen der Klasse kommt [] wie in String[].');
          dims++;
        }
        if (dims > 1) { err('Mehrdimensionale Arrays werden noch nicht unterstützt.', 'Nutze eindimensionale Arrays wie int[].'); }
      }
      return { name: name, arr: dims > 0 };
    }

    function parseParams() {
      expectP('(', '(', 'Hinter dem Methodennamen kommen runde Klammern mit den Parametern.');
      var params = [];
      if (at('p', ')')) { next(); return params; }
      for (;;) {
        var ty = parseType(true);
        if (!ty) { err('In den Parametern fehlt ein Typ.', 'z. B. (String name) oder (int zahl)'); }
        if (!at('id')) { err('Hinter dem Parametertyp ' + ty.name + ' fehlt der Name.', 'z. B. ' + ty.name + ' wert'); }
        var pTok = next();
        params.push({ name: pTok.v, type: ty, line: pTok.line });
        if (at('p', ',')) { next(); continue; }
        break;
      }
      expectP(')', ')', 'Die Parameterliste endet mit einer schließenden Klammer.');
      return params;
    }

    function parseBlock() {
      expectP('{', '{', 'Der Rumpf steht in geschweiften Klammern { ... }.');
      var body = [];
      while (!at('p', '}') && !at('eof')) {
        body.push(parseStatement());
      }
      expectP('}', '}', 'Der Rumpf endet mit einer schließenden geschweiften Klammer.');
      return body;
    }

    function isTypeStart(t, t2, t3) {
      if (t.k === 'kw' && PRIMS[t.v]) { return true; }
      if (t.k === 'id' && t2 && t2.k === 'id') { return true; }
      if (t.k === 'id' && t2 && t2.k === 'p' && t2.v === '[' && t3 && t3.k === 'p' && t3.v === ']') { return true; }
      return false;
    }

    function parseStatement() {
      var t = peek();
      if (at('p', '{')) { return { type: 'block', body: parseBlock(), line: t.line }; }
      if (at('p', ';')) { next(); return { type: 'empty', line: t.line }; }
      if (at('kw', 'if')) { return parseIf(); }
      if (at('kw', 'while')) { return parseWhile(); }
      if (at('kw', 'do')) { return parseDoWhile(); }
      if (at('kw', 'for')) { return parseFor(); }
      if (at('kw', 'return')) {
        next();
        var val = null;
        if (!at('p', ';')) { val = parseExpr(); }
        expectP(';', ';', 'Nach return kommt ein Semikolon.');
        return { type: 'return', val: val, line: t.line };
      }
      if (at('kw', 'break')) { next(); expectP(';', ';', 'Nach break kommt ein Semikolon.'); return { type: 'break', line: t.line }; }
      if (at('kw', 'continue')) { next(); expectP(';', ';', 'Nach continue kommt ein Semikolon.'); return { type: 'continue', line: t.line }; }
      if (at('kw', 'switch')) { err('switch wird noch nicht unterstützt.', 'Nutze if / else if / else statt switch.'); }
      if (at('kw', 'try') || at('kw', 'throw')) { err('try/catch und throw werden noch nicht unterstützt.', 'Prüfe Werte vorher mit if, statt Ausnahmen zu werfen.'); }
      /* Deklaration? */
      if (isTypeStart(peek(), peek(1), peek(2))) {
        var ty = parseType(true);
        var decls = [];
        for (;;) {
          if (!at('id')) { err('Nach dem Typ ' + ty.name + ' fehlt der Variablenname.', 'z. B. ' + ty.name + ' wert;'); }
          var nTok = next();
          var init = null;
          if (at('op', '=')) {
            next();
            init = at('p', '{') ? parseArrayLiteral(ty.name, ty.arr) : parseExpr();
          }
          decls.push({ name: nTok.v, type: ty, init: init, line: nTok.line });
          if (at('p', ',')) { next(); continue; }
          break;
        }
        expectP(';', ';', 'Jede Deklaration endet mit einem Semikolon.');
        return { type: 'decl', decls: decls, line: t.line };
      }
      /* Ausdrucksanweisung */
      var expr = parseExpr();
      expectP(';', ';', 'Jede Anweisung endet mit einem Semikolon.');
      return { type: 'exprstat', expr: expr, line: t.line };
    }

    function parseIf() {
      var t = next();
      expectP('(', '(', 'Hinter if kommt die Bedingung in runden Klammern: if (...) { ... }');
      var cond = parseExpr();
      expectP(')', ')', 'Die if-Bedingung endet mit einer schließenden Klammer.');
      var then = parseStatement();
      var els = null;
      if (at('kw', 'else')) { next(); els = parseStatement(); }
      return { type: 'if', cond: cond, then: then, els: els, line: t.line };
    }

    function parseWhile() {
      var t = next();
      expectP('(', '(', 'Hinter while kommt die Bedingung in runden Klammern.');
      var cond = parseExpr();
      expectP(')', ')', 'Die while-Bedingung endet mit einer schließenden Klammer.');
      var body = parseStatement();
      return { type: 'while', cond: cond, body: body, line: t.line };
    }

    function parseDoWhile() {
      var t = next();
      var body = parseStatement();
      if (!at('kw', 'while')) { err('Nach do ... kommt while mit der Bedingung.', 'z. B. do { ... } while (bedingung);'); }
      next();
      expectP('(', '(', 'Hinter while kommt die Bedingung in runden Klammern.');
      var cond = parseExpr();
      expectP(')', ')', 'Die Bedingung endet mit einer schließenden Klammer.');
      expectP(';', ';', 'do-while endet mit einem Semikolon.');
      return { type: 'dowhile', body: body, cond: cond, line: t.line };
    }

    function parseFor() {
      var t = next();
      expectP('(', '(', 'Hinter for kommt (start; bedingung; weiter) { ... }');
      var init = null;
      if (!at('p', ';')) {
        if (isTypeStart(peek(), peek(1), peek(2))) {
          var ty = parseType(true);
          var decls = [];
          for (;;) {
            if (!at('id')) { err('Nach dem Typ ' + ty.name + ' fehlt der Name.', 'z. B. for (int i = 0; ...)'); }
            var nTok = next();
            var init2 = null;
            if (at('op', '=')) { next(); init2 = parseExpr(); }
            decls.push({ name: nTok.v, type: ty, init: init2, line: nTok.line });
            if (at('p', ',')) { next(); continue; }
            break;
          }
          init = { type: 'decl', decls: decls, line: t.line };
        } else {
          init = { type: 'exprstat', expr: parseExpr(), line: t.line };
        }
      }
      expectP(';', ';', 'Im for-Kopf stehen Semikolons: for (start; bedingung; weiter).');
      var cond = null;
      if (!at('p', ';')) { cond = parseExpr(); }
      expectP(';', ';', 'Im for-Kopf stehen Semikolons: for (start; bedingung; weiter).');
      var upd = null;
      if (!at('p', ')')) { upd = parseExpr(); }
      expectP(')', ')', 'Der for-Kopf endet mit einer schließenden Klammer.');
      var body = parseStatement();
      return { type: 'for', init: init, cond: cond, upd: upd, body: body, line: t.line };
    }

    /* ---------- Ausdrücke ---------- */
    function parseExpr() { return parseAssign(); }

    function parseAssign() {
      var t = peek();
      var left = parseTernary();
      if (at('op', '=') || at('op', '+=') || at('op', '-=') || at('op', '*=') || at('op', '/=') || at('op', '%=')) {
        var op = next().v;
        var right = parseAssign();
        if (left.type !== 'name' && left.type !== 'field' && left.type !== 'index') {
          err('Links von ' + op + ' muss eine Variable, ein Attribut oder ein Array-Platz stehen.', 'z. B. zahl = 1; oder objekt.attribut = 2; oder array[0] = 3;');
        }
        return { type: 'assign', op: op, target: left, val: right, line: t.line };
      }
      return left;
    }

    function parseTernary() {
      var t = peek();
      var c = parseOr();
      if (at('op', '?')) {
        next();
        var a = parseAssign();
        if (!at('op', ':')) {
          err('Im Bedingungsoperator fehlt der Doppelpunkt :', 'Der Bedingungsoperator hat die Form: bedingung ? wert1 : wert2');
        }
        next();
        var b = parseAssign();
        return { type: 'cond', cond: c, a: a, b: b, line: t.line };
      }
      return c;
    }

    function parseOr() {
      var l = parseAnd();
      while (at('op', '||')) {
        var t = next();
        l = { type: 'bin', op: '||', l: l, r: parseAnd(), line: t.line };
      }
      return l;
    }
    function parseAnd() {
      var l = parseEq();
      while (at('op', '&&')) {
        var t = next();
        l = { type: 'bin', op: '&&', l: l, r: parseEq(), line: t.line };
      }
      return l;
    }
    function parseEq() {
      var l = parseRel();
      while (at('op', '==') || at('op', '!=')) {
        var t = next();
        l = { type: 'bin', op: t.v, l: l, r: parseRel(), line: t.line };
      }
      return l;
    }
    function parseRel() {
      var l = parseAdd();
      while (at('op', '<') || at('op', '>') || at('op', '<=') || at('op', '>=')) {
        var t = next();
        l = { type: 'bin', op: t.v, l: l, r: parseAdd(), line: t.line };
      }
      return l;
    }
    function parseAdd() {
      var l = parseMul();
      while (at('op', '+') || at('op', '-')) {
        var t = next();
        l = { type: 'bin', op: t.v, l: l, r: parseMul(), line: t.line };
      }
      return l;
    }
    function parseMul() {
      var l = parseUnary();
      while (at('op', '*') || at('op', '/') || at('op', '%')) {
        var t = next();
        l = { type: 'bin', op: t.v, l: l, r: parseUnary(), line: t.line };
      }
      return l;
    }

    function tryCastType() {
      var t = peek();
      if (t.k !== 'p' || t.v !== '(') { return null; }
      var t2 = peek(1);
      var name = null;
      if (t2.k === 'kw' && PRIMS[t2.v]) { name = PRIMS[t2.v]; }
      else if (t2.k === 'id') { name = t2.v; }
      else { return null; }
      var t3 = peek(2);
      if (!t3 || t3.k !== 'p' || t3.v !== ')') { return null; }
      if (!startsExpression(peek(3))) { return null; }
      return name;
    }

    function parseUnary() {
      var t = peek();
      if (at('op', '-')) { next(); return { type: 'un', op: '-', e: parseUnary(), line: t.line }; }
      if (at('op', '+')) { next(); return { type: 'un', op: '+', e: parseUnary(), line: t.line }; }
      if (at('op', '!')) { next(); return { type: 'un', op: '!', e: parseUnary(), line: t.line }; }
      if (at('op', '++') || at('op', '--')) {
        var op = next().v;
        return { type: 'pre', op: op, e: parseUnary(), line: t.line };
      }
      var castTy = tryCastType();
      if (castTy) {
        next(); next(); next();
        return { type: 'cast', ty: castTy, e: parseUnary(), line: t.line };
      }
      return parsePostfix();
    }

    function parsePostfix() {
      var e = parsePrimary();
      for (;;) {
        var t = peek();
        if (at('p', '.')) {
          next();
          if (!at('id')) { err('Nach dem Punkt folgt ein Name.', 'z. B. objekt.methode() oder objekt.attribut'); }
          var mTok = next();
          if (at('p', '(')) {
            e = { type: 'call', target: e, name: mTok.v, args: parseCallArgs(), line: mTok.line };
          } else {
            e = { type: 'field', target: e, name: mTok.v, line: mTok.line };
          }
          continue;
        }
        if (at('p', '[')) {
          next();
          var idx = parseExpr();
          expectP(']', ']', 'Hinter dem Index kommt eine schließende eckige Klammer.');
          e = { type: 'index', target: e, index: idx, line: t.line };
          continue;
        }
        if (at('op', '++') || at('op', '--')) {
          var po = next().v;
          e = { type: 'post', op: po, e: e, line: t.line };
          continue;
        }
        break;
      }
      return e;
    }

    function parseCallArgs() {
      expectP('(', '(', 'Methodenaufrufe haben runde Klammern: methode(...)');
      var args = [];
      if (at('p', ')')) { next(); return args; }
      for (;;) {
        args.push(parseExpr());
        if (at('p', ',')) { next(); continue; }
        break;
      }
      expectP(')', ')', 'Der Methodenaufruf endet mit einer schließenden Klammer.');
      return args;
    }

    function parseArrayLiteral(elem, isArr) {
      var t = peek();
      expectP('{', '{', 'Ein Array-Kurzformat beginnt mit {, z. B. int[] z = {1, 2, 3};');
      var items = [];
      if (!at('p', '}')) {
        for (;;) {
          items.push(parseExpr());
          if (at('p', ',')) { next(); continue; }
          break;
        }
      }
      expectP('}', '}', 'Das Array-Kurzformat endet mit }.');
      return { type: 'arrlit', elem: isArr ? elem : 'ref', items: items, line: t.line };
    }

    function parsePrimary() {
      var t = peek();
      if (t.k === 'int') { next(); return { type: 'int', v: t.v, line: t.line }; }
      if (t.k === 'dbl') { next(); return { type: 'double', v: t.v, line: t.line }; }
      if (t.k === 'str') { next(); return { type: 'str', v: t.v, line: t.line }; }
      if (t.k === 'char') { next(); return { type: 'char', v: t.v, line: t.line }; }
      if (at('kw', 'true')) { next(); return { type: 'bool', v: true, line: t.line }; }
      if (at('kw', 'false')) { next(); return { type: 'bool', v: false, line: t.line }; }
      if (at('kw', 'null')) { next(); return { type: 'null', line: t.line }; }
      if (at('kw', 'this')) {
        next();
        if (at('p', '(')) {
          return { type: 'call', target: { type: 'this', line: t.line }, name: null, args: parseCallArgs(), line: t.line };
        }
        return { type: 'this', line: t.line };
      }
      if (at('kw', 'super')) {
        next();
        if (at('p', '(')) {
          return { type: 'call', target: { type: 'super', line: t.line }, name: null, args: parseCallArgs(), line: t.line };
        }
        return { type: 'super', line: t.line };
      }
      if (at('p', '(')) {
        next();
        var inner = parseExpr();
        expectP(')', ')', 'Der geklammerte Ausdruck endet mit einer schließenden Klammer.');
        return inner;
      }
      if (at('kw', 'new')) {
        next();
        var ty = parseType(false);
        if (!ty) { err('Nach new folgt ein Typ.', 'z. B. new Auto() oder new int[10].'); }
        if (at('p', '[')) {
          next();
          if (at('p', ']')) {
            next();
            var items2 = [];
            expectP('{', '{', 'new int[] {1, 2} startet mit einer öffnenden geschweiften Klammer.');
            if (!at('p', '}')) {
              for (;;) {
                items2.push(parseExpr());
                if (at('p', ',')) { next(); continue; }
                break;
              }
            }
            expectP('}', '}', 'Das Array-Literal endet mit }.');
            return { type: 'arrlit', elem: ty.name, items: items2, line: t.line };
          }
          var size = parseExpr();
          expectP(']', ']', 'Hinter der Array-Größe kommt eine schließende eckige Klammer.');
          return { type: 'newarr', elem: ty.name, size: size, line: t.line };
        }
        expectP('(', '(', 'Hinter new kommt der Aufruf des Konstruktors: new Klassenname(...)');
        var args2 = [];
        if (at('p', ')')) { next(); }
        else {
          for (;;) {
            args2.push(parseExpr());
            if (at('p', ',')) { next(); continue; }
            break;
          }
          expectP(')', ')', 'Der Konstruktor-Aufruf endet mit einer schließenden Klammer.');
        }
        return { type: 'new', cls: ty.name, args: args2, line: t.line };
      }
      if (t.k === 'id') {
        next();
        if (at('p', '(')) {
          return { type: 'call', target: null, name: t.v, args: parseCallArgs(), line: t.line };
        }
        return { type: 'name', v: t.v, line: t.line };
      }
      err('Unerwartet: ' + describe(t), 'Hier steht etwas, das nicht als Ausdruck lesbar ist. Prüfe Klammern und Semikolons in den Zeilen davor.');
    }

    /* Objektkonsole: Anweisungen ohne umgebende Klasse lesen (kein main nötig). */
    function parseReplBody() {
      skipImports();
      skipSemi();
      var body = [];
      while (!at('eof')) {
        body.push(parseStatement());
        skipSemi();
      }
      return body;
    }

    return { parseUnit: parseUnit, parseReplBody: parseReplBody };
  }

  /* ================= Interpreter (Teil 2) ================= */
  var MAX_STEPS = 5000000;
  var MAX_DEPTH = 400;
  var MAX_OUTPUT = 100000;

  function wrap32(x) { return Math.trunc(x) | 0; }
  function mkInt(x) { return { t: 'int', v: wrap32(x) }; }
  function mkDbl(x) { return { t: 'double', v: x }; }
  function mkBool(b) { return { t: 'boolean', v: !!b }; }
  function mkStr(s) { return { t: 'String', v: s }; }
  function mkChar(c) { return { t: 'char', v: c }; }
  var NULLV = { t: 'null' };
  var VOIDV = { t: 'void', v: null };
  var TRUE = mkBool(true);
  var FALSE = mkBool(false);

  function fmtDouble(x) {
    if (x !== x || x === Infinity || x === -Infinity) { return String(x); }
    if (Math.floor(x) === x && Math.abs(x) < 1e21) { return String(x) + '.0'; }
    return String(x);
  }

  function isPrimType(name) {
    return name === 'int' || name === 'double' || name === 'boolean' || name === 'char';
  }

  function defaultVal(type) {
    if (type.arr) { return NULLV; }
    if (type.name === 'int') { return mkInt(0); }
    if (type.name === 'double') { return mkDbl(0); }
    if (type.name === 'boolean') { return FALSE; }
    if (type.name === 'char') { return mkChar(0); }
    return NULLV;
  }

  /* Laufzeitkontext aller laufenden Programme (pro run-Aufruf neu). */
  var RUN_CLASS_MAP = Object.create(null);
  var RUN_VM = null;
  var RUN_DB = null;
  var files0 = Object.create(null);

  /* ================= run(files) ================= */
  /* Gemeinsame Vorarbeit für run() und openSession(): alle Dateien lexen,
   * parsen und übersetzen. Liefert {ok, classes, classMap, errors, fileNames}. */
  function compileAll(files) {
    var errors = [];
    var classes = [];
    var classMap = Object.create(null);
    var fileNames = [];
    var toplevel = [];
    var k;
    for (k in files) { if (Object.prototype.hasOwnProperty.call(files, k)) { fileNames.push(k); } }

    var pre = precheck(files);
    if (pre) { errors.push(pre); return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel }; }

    for (var fi = 0; fi < fileNames.length; fi++) {
      var fname = fileNames[fi];
      var perrs = [];
      var toks = lex(fname, files[fname], perrs);
      if (!toks || perrs.length) {
        if (perrs.length) { errors.push(perrs[0]); }
        return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
      }
      try {
        var parsed = new Parser(fname, toks, perrs).parseUnit();
        for (var pj = 0; pj < parsed.classes.length; pj++) {
          if (classMap[parsed.classes[pj].name]) {
            errors.push(JError('compile', fname, parsed.classes[pj].line, 'Die Klasse ' + parsed.classes[pj].name + ' existiert doppelt.', 'Jede Klasse darf nur einmal definiert werden (auch über mehrere Dateien hinweg).'));
            return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
          }
          parsed.classes[pj].srcFile = fname;
          classes.push(parsed.classes[pj]);
          classMap[parsed.classes[pj].name] = parsed.classes[pj];
        }
        /* Anweisungen direkt in der Datei (Reihenfolge über Dateien wahren) */
        for (var sj = 0; sj < parsed.stmts.length; sj++) {
          parsed.stmts[sj].srcFile = fname;
          toplevel.push(parsed.stmts[sj]);
        }
      } catch (e) {
        if (perrs.length) { errors.push(perrs[0]); }
        else { throw e; }
        return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
      }
      if (perrs.length) { errors.push(perrs[0]); return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel }; }
    }

    if (!compileChecks(classes, classMap, errors, files)) {
      return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
    }
    return { ok: true, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
  }

  function run(files, opts) {
    files0 = files;
    var errors = [];
    var out = [];
    var errCards = [];
    var comp = compileAll(files);
    errors = comp.errors;
    if (!comp.ok) { return finish(null, false); }

    RUN_CLASS_MAP = comp.classMap;
    RUN_DB = Object.create(null);
    var vm = {
      out: out,
      errCards: errCards,
      statics: Object.create(null),
      steps: 0,
      depth: 0,
      objects: [],
      gfx: gfxNewState(),
      stepLimit: (opts && typeof opts.stepLimit === 'number') ? opts.stepLimit : null,
      leafSteps: 0,
      paused: false,
      pauseFile: '',
      pauseLine: 0
    };
    RUN_VM = vm;
    gfxResetRun();
    GFX_ONERROR = null;

    var sessionEnv = newEnv(null);
    var session = makeSession(comp, vm, sessionEnv);

    var mainFound = null;
    try {
      var ci;
      for (ci = 0; ci < comp.classes.length; ci++) { initStatics(comp.classes[ci], vm); }
      /* Anweisungen direkt in der Datei laufen zuerst (Objekte erzeugen u. a.),
       * danach – falls vorhanden – die main-Methode. Beides sieht die Objektkonsole. */
      runToplevel(comp, vm, sessionEnv, errors);
      if (!vm.paused) {
        for (ci = 0; ci < comp.classes.length; ci++) {
          var mm = findMethod(comp.classes[ci], 'main', 1);
          if (mm && mm.m.isStatic) { mainFound = mm; break; }
        }
        if (mainFound) {
          invokeMethod(mainFound.cls, mainFound.m, null, [{ t: 'array', elem: 'String', arr: [] }], vm);
        }
      }
      /* Ohne main-Methode kein Fehler mehr: Das Projekt lässt sich dann
       * interaktiv über die Objektkonsole bedienen (Objekte erzeugen,
       * Methoden aufrufen, Objektdiagramm). */
    } catch (e) {
      if (e && e.pause) { vm.paused = true; vm.pauseFile = e.file || ''; vm.pauseLine = e.line || 0; }
      else if (e && e.jerr) { errors.push(e.jerr); }
      else if (e && e.ctrl) { }
      else { throw e; }
    }

    return finish(session, !!mainFound);

    function finish(session, ranMain) {
      var all = [];
      var ei;
      for (ei = 0; ei < errors.length; ei++) { all.push(errors[ei]); }
      for (ei = 0; ei < errCards.length; ei++) { all.push(errCards[ei]); }
      var ok = true;
      for (ei = 0; ei < all.length; ei++) { if (all[ei].kind !== 'stderr') { ok = false; } }
      var paused = !!(vm && vm.paused);
      var objs = [];
      if (paused || (vm && vm.gfx && vm.gfx.shapes.length > 0)) {
        try { objs = objectsSnapshotOf(comp, vm, sessionEnv); } catch (e2) { objs = []; }
      }
      return {
        output: out.join(''), errors: all, ok: ok, session: session, ranMain: !!ranMain,
        paused: paused,
        pauseFile: paused ? vm.pauseFile : '',
        pauseLine: paused ? vm.pauseLine : 0,
        leafSteps: vm ? vm.leafSteps : 0,
        objects: objs,
        hasGfx: !!(vm && vm.gfx && (vm.gfx.shapes.length > 0 || vm.gfx.world))
      };
    }
  }

  /* ================= Objektkonsole (interaktive Sitzung) =================
   * Nach dem Ausführen bleibt das Programm "lebendig": In der Objektkonsole
   * lassen sich Objekte erzeugen, Methoden aufrufen und Ausdrücke auswerten.
   * Alle erzeugten Objekte zeigt das Objektdiagramm. Eine main-Methode ist
   * dafür nicht nötig (und wird in den Startpaketen bewusst nicht benutzt).
   */
  /* Anweisungen direkt in der Datei ausfuehren (ohne umgebende Klasse),
   * z. B. Objekte erzeugen: Test t = new Test();  Fehler landen in errors. */
  function runToplevel(comp, vm, env, errors) {
    var i2;
    for (i2 = 0; i2 < comp.toplevel.length; i2++) {
      var st = comp.toplevel[i2];
      var ctx2 = { file: st.srcFile || '', line: st.line || 1, cls: null, thisVal: null, vm: vm, captureExpr: true };
      try {
        execBlock([st], env, ctx2);
        /* Rückgabewerte landen nicht im Nirvana: Ein blosser Methodenaufruf
         * am Datei-Ende zeigt sein Ergebnis in der Ausgabe (wie die Objektkonsole). */
        if (st.type === 'exprstat' && ctx2.lastExprVal && ctx2.lastExprVal.t !== 'void') {
          var tv = ctx2.lastExprVal;
          var tname = tv.t === 'ref' ? tv.cls : (tv.t === 'array' ? (tv.elem + '[]') : tv.t);
          output(ctx2, 'Ergebnis: ' + javaStr(tv, ctx2, st.line || 1) + '   (' + tname + ')' + NL);
        }
      } catch (e) {
        if (e && e.jerr) { errors.push(e.jerr); return; }
        if (e && (e.ctrl === 'return' || e.ctrl === 'break' || e.ctrl === 'continue')) {
          errors.push(JError('syntax', ctx2.file, st.line || 1, 'return, break und continue gehören in Methoden und Schleifen deiner Klasse.', 'Direkt in der Datei stehen normale Anweisungen, z. B.: Test t = new Test();'));
          return;
        }
        throw e;
      }
    }
  }

  function openSession(files) {
    var comp = compileAll(files);
    if (!comp.ok) { return { ok: false, errors: comp.errors, session: null }; }
    var out = [];
    var errCards = [];
    RUN_CLASS_MAP = comp.classMap;
    RUN_DB = Object.create(null);
    var vm = { out: out, errCards: errCards, statics: Object.create(null), steps: 0, depth: 0, objects: [], gfx: gfxNewState(), stepLimit: null, leafSteps: 0 };
    RUN_VM = vm;
    gfxResetRun();
    GFX_ONERROR = null;
    var env = newEnv(null);
    try {
      for (var ci = 0; ci < comp.classes.length; ci++) { initStatics(comp.classes[ci], vm); }
      var terrs = [];
      runToplevel(comp, vm, env, terrs);
      if (terrs.length > 0) {
        var tall = [];
        var tei;
        for (tei = 0; tei < errCards.length; tei++) { tall.push(errCards[tei]); }
        for (tei = 0; tei < terrs.length; tei++) { tall.push(terrs[tei]); }
        return { ok: false, errors: tall, session: null };
      }
    } catch (e) {
      if (e && e.jerr) { return { ok: false, errors: [e.jerr], session: null }; }
      throw e;
    }
    var initErrs = [];
    for (var ei = 0; ei < errCards.length; ei++) { initErrs.push(errCards[ei]); }
    return { ok: true, errors: initErrs, session: makeSession(comp, vm, env) };
  }

  function sessionMainClassName(comp) {
    for (var ci = 0; ci < comp.classes.length; ci++) {
      var mm = findMethod(comp.classes[ci], 'main', 1);
      if (mm && mm.m.isStatic) { return comp.classes[ci].name; }
    }
    return null;
  }

  function typeName(ty) {
    if (!ty) { return '?'; }
    return ty.name + (ty.arr ? '[]' : '');
  }

  function isIdentChar(c) {
    return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c === '_';
  }

  /* Variablen der Sitzung mit Objekt-Referenzen (für Namenszuordnung im Diagramm). */
  function varPairsOf(env) {
    var pairs = [];
    var name;
    for (name in env.vars) {
      if (Object.prototype.hasOwnProperty.call(env.vars, name)) {
        var slot = env.vars[name];
        if (slot.val && slot.val.t === 'ref') { pairs.push({ obj: slot.val.obj, name: name, type: slot.val.cls }); }
      }
    }
    return pairs;
  }

  /* Wert für das Objektdiagramm beschreiben (ruft keinen Schülercode auf). */
  function reprVal(v, pairs, regs) {
    if (!v || v.t === 'null') { return 'null'; }
    if (v.t === 'String') { return '"' + v.v + '"'; }
    if (v.t === 'int' || v.t === 'double') { return String(v.v); }
    if (v.t === 'boolean') { return v.v ? 'true' : 'false'; }
    if (v.t === 'char') { return "'" + String.fromCharCode(v.v) + "'"; }
    if (v.t === 'array') {
      var parts = [];
      var i;
      for (i = 0; i < v.arr.length && i < 4; i++) { parts.push(reprVal(v.arr[i], pairs, regs)); }
      if (v.arr.length > 4) { parts.push('...'); }
      return v.elem + '[] [' + parts.join(', ') + ']';
    }
    if (v.t === 'ref') {
      var nm = null;
      var id = null;
      var j;
      for (j = 0; j < pairs.length; j++) { if (pairs[j].obj === v.obj) { nm = pairs[j].name; } }
      for (j = 0; j < regs.length; j++) { if (regs[j].obj === v.obj) { id = regs[j].id; } }
      return (nm ? nm : ('Objekt ' + id)) + ' : ' + v.cls;
    }
    return String(v.v);
  }

  function objectsSnapshotOf(comp, vm, env) {
    var pairs = varPairsOf(env);
    var list = [];
    var i, j;
    for (i = 0; i < vm.objects.length; i++) {
      var reg = vm.objects[i];
      var varName = reg.name || null;
      for (j = 0; j < pairs.length; j++) { if (pairs[j].obj === reg.obj) { varName = pairs[j].name; } }
      var cls = comp.classMap[reg.cls];
      var fields = [];
      var flds = cls ? instanceFieldsOf(cls) : [];
      for (j = 0; j < flds.length; j++) {
        var fname = flds[j].name;
        if (Object.prototype.hasOwnProperty.call(reg.obj.f, fname)) {
          fields.push({ name: fname, text: reprVal(reg.obj.f[fname], pairs, vm.objects) });
        }
      }
      list.push({ id: reg.id, cls: reg.cls, name: varName, fields: fields });
    }
    return list;
  }

  function classChainOf(cls, classMap) {
    var chain = [];
    var c = cls;
    var guard = 0;
    while (c && guard < 30) { chain.unshift(c); c = c.parent ? classMap[c.parent] : null; guard++; }
    return chain;
  }

  function methodItem(m) {
    var ps = [];
    var i;
    for (i = 0; i < m.params.length; i++) { ps.push(typeName(m.params[i].type) + ' ' + m.params[i].name); }
    return {
      label: m.name + '(',
      insert: m.name + '(',
      hint: 'Methode (' + ps.join(', ') + ') zurück: ' + (m.ret && m.ret.void ? 'void' : typeName(m.ret))
    };
  }

  var STRING_METHODS = ['length()', 'charAt(', 'substring(', 'indexOf(', 'equals(', 'equalsIgnoreCase(', 'contains(', 'startsWith(', 'toUpperCase()', 'toLowerCase()', 'trim()', 'replace(', 'isEmpty()'];
  var REPL_KEYWORDS = ['new', 'int', 'double', 'boolean', 'char', 'if', 'while', 'for', 'return', 'true', 'false', 'null'];
  var BUILTIN_CLASSES = ['System', 'Math', 'Integer', 'Double', 'String', 'Datenbank'];

  /* Empfänger vor einem abschließenden Punkt ermitteln (auch System.out.). */
  function dotReceiver(t, dotIdx) {
    var r = dotIdx - 1;
    while (r >= 0 && isIdentChar(t.charAt(r))) { r--; }
    var ident = t.substring(r + 1, dotIdx);
    if ((ident === 'out' || ident === 'err') && r >= 1 && t.charAt(r) === '.') {
      var r2 = r - 1;
      while (r2 >= 0 && isIdentChar(t.charAt(r2))) { r2--; }
      if (t.substring(r2 + 1, r) === 'System') { return 'System.' + ident; }
    }
    return ident;
  }

  function makeSession(comp, vm, envIn) {
    var env = envIn || newEnv(null);
    var classMap = comp.classMap;
    var s = { vm: vm, hasMain: sessionMainClassName(comp) !== null };

    s.classNames = function () {
      var names = [];
      for (var i = 0; i < comp.classes.length; i++) { names.push(comp.classes[i].name); }
      return names;
    };

    s.objects = function () { return objectsSnapshotOf(comp, vm, env); };

    s.evalLine = function (input) {
      var line = String(input == null ? '' : input);
      line = line.trim();
      var res = { output: '', errors: [], result: null, resultType: null, objects: [] };
      if (line === '') { res.objects = s.objects(); return res; }
      var lastCh = line.charAt(line.length - 1);
      if (lastCh !== ';' && lastCh !== '{' && lastCh !== '}') { line += ';'; }
      var perrs = [];
      var toks = lex('Objektkonsole', line, perrs);
      if (!toks || perrs.length) {
        if (perrs.length) { res.errors.push(perrs[0]); }
        res.objects = s.objects();
        return res;
      }
      var stmts = [];
      try { stmts = new Parser('Objektkonsole', toks, perrs).parseReplBody(); }
      catch (e) {
        if (e && e.stop && e.jerr) { res.errors.push(e.jerr); res.objects = s.objects(); return res; }
        throw e;
      }
      if (stmts.length === 0) { res.objects = s.objects(); return res; }
      RUN_CLASS_MAP = classMap;
      RUN_VM = vm;
      var ctx = { file: 'Objektkonsole', line: 1, cls: null, thisVal: null, vm: vm, captureExpr: true };
      var outStart = vm.out.length;
      var errStart = vm.errCards.length;
      try {
        execBlock(stmts, env, ctx);
      } catch (e) {
        if (e && e.jerr) { res.errors.push(e.jerr); }
        else if (e && (e.ctrl === 'return' || e.ctrl === 'break' || e.ctrl === 'continue')) {
          res.errors.push(JError('syntax', 'Objektkonsole', ctx.line, 'return, break und continue gehören in Methoden und Schleifen deiner Klasse.', 'In der Objektkonsole schreibst du Anweisungen direkt auf, z. B. auto.fahren();'));
        }
        else { throw e; }
        res.output = vm.out.slice(outStart).join('');
        res.objects = s.objects();
        return res;
      }
      var last = stmts[stmts.length - 1];
      if (last && last.type === 'exprstat' && ctx.lastExprVal) {
        var v = ctx.lastExprVal;
        if (v.t !== 'void') {
          try {
            res.result = javaStr(v, ctx, ctx.line);
            res.resultType = v.t === 'ref' ? v.cls : (v.t === 'array' ? (v.elem + '[]') : v.t);
          } catch (e2) {
            if (e2 && e2.jerr) { res.errors.push(e2.jerr); }
            else { throw e2; }
          }
        }
      }
      res.output = vm.out.slice(outStart).join('');
      var newErrs = vm.errCards.slice(errStart);
      for (var ei = 0; ei < newErrs.length; ei++) { res.errors.push(newErrs[ei]); }
      res.objects = s.objects();
      return res;
    };

    /* Autovervollständigung: {from: Einfügeposition, items: [{label, insert, hint}]} */
    s.complete = function (text) {
      var t = String(text == null ? '' : text);
      var end = t.length;
      var i, j;
      var lastNonWs = -1;
      for (i = end - 1; i >= 0; i--) {
        var ch = t.charAt(i);
        if (ch !== ' ' && ch !== String.fromCharCode(9) && ch !== NL) { lastNonWs = i; break; }
      }
      if (lastNonWs >= 0 && t.charAt(lastNonWs) === '.') {
        return { from: end, items: memberItems(dotReceiver(t, lastNonWs)) };
      }
      var w = end - 1;
      while (w >= 0 && isIdentChar(t.charAt(w))) { w--; }
      var word = t.substring(w + 1, end);
      /* Punkt vor dem Wort: Mitglieder des Empfängers anbieten (auch mitten im Wort) */
      if (w >= 0 && t.charAt(w) === '.') {
        var recv = dotReceiver(t, w);
        if (recv !== '') {
          var mems = memberItems(recv);
          var mout = [];
          var mi;
          for (mi = 0; mi < mems.length; mi++) {
            if (word === '' || mems[mi].label.indexOf(word) === 0) { mout.push(mems[mi]); }
          }
          return { from: w + 1, items: mout.slice(0, 40) };
        }
      }
      var items = [];
      var name;
      for (name in env.vars) {
        if (Object.prototype.hasOwnProperty.call(env.vars, name)) {
          items.push({ label: name, insert: name, hint: 'Variable: ' + typeName(env.vars[name].type) });
        }
      }
      for (i = 0; i < comp.classes.length; i++) {
        items.push({ label: comp.classes[i].name, insert: comp.classes[i].name, hint: 'Klasse' });
      }
      for (i = 0; i < BUILTIN_CLASSES.length; i++) {
        items.push({ label: BUILTIN_CLASSES[i], insert: BUILTIN_CLASSES[i], hint: 'eingebaute Klasse' });
      }
      for (i = 0; i < REPL_KEYWORDS.length; i++) {
        items.push({ label: REPL_KEYWORDS[i], insert: REPL_KEYWORDS[i], hint: 'Schlüsselwort' });
      }
      var out = [];
      for (i = 0; i < items.length; i++) {
        if (word === '' || items[i].label.indexOf(word) === 0) { out.push(items[i]); }
      }
      return { from: w + 1, items: out.slice(0, 40) };
    };

    function memberItems(recv) {
      var out = [];
      var i, j;
      if (recv === '') { return out; }
      var slot = envFind(env, recv);
      if (slot && slot.val) {
        var v = slot.val;
        if (v.t === 'ref') {
          var chain = classChainOf(classMap[v.cls], classMap);
          for (i = 0; i < chain.length; i++) {
            var cls = chain[i];
            for (j = 0; j < cls.fields.length; j++) {
              if (!cls.fields[j].isStatic) { out.push({ label: cls.fields[j].name, insert: cls.fields[j].name, hint: 'Attribut: ' + typeName(cls.fields[j].type) }); }
            }
            for (j = 0; j < cls.methods.length; j++) {
              if (!cls.methods[j].isStatic) { out.push(methodItem(cls.methods[j])); }
            }
          }
          return out;
        }
        if (v.t === 'String') {
          for (i = 0; i < STRING_METHODS.length; i++) { out.push({ label: STRING_METHODS[i], insert: STRING_METHODS[i], hint: 'String-Methode' }); }
          return out;
        }
        if (v.t === 'array') {
          out.push({ label: 'length', insert: 'length', hint: 'Länge des Arrays (ohne Klammern)' });
          return out;
        }
        return out;
      }
      if (classMap[recv]) {
        var cls2 = classMap[recv];
        var chain2 = classChainOf(cls2, classMap);
        for (i = 0; i < chain2.length; i++) {
          for (j = 0; j < chain2[i].fields.length; j++) {
            if (chain2[i].fields[j].isStatic) { out.push({ label: chain2[i].fields[j].name, insert: chain2[i].fields[j].name, hint: 'Klassen-Attribut: ' + typeName(chain2[i].fields[j].type) }); }
          }
          for (j = 0; j < chain2[i].methods.length; j++) {
            if (chain2[i].methods[j].isStatic) { out.push(methodItem(chain2[i].methods[j])); }
          }
        }
        for (i = 0; i < cls2.ctors.length; i++) {
          var ps = [];
          for (j = 0; j < cls2.ctors[i].params.length; j++) { ps.push(typeName(cls2.ctors[i].params[j].type)); }
          out.push({ label: 'new ' + recv + '(', insert: 'new ' + recv + '(', hint: 'Konstruktor (' + ps.join(', ') + ')' });
        }
        return out;
      }
      if (recv === 'System.out' || recv === 'System.err') {
        out.push({ label: 'println(', insert: 'println(', hint: 'Ausgabe mit Zeilenumbruch' });
        out.push({ label: 'print(', insert: 'print(', hint: 'Ausgabe ohne Zeilenumbruch' });
        return out;
      }
      if (recv === 'System') {
        out.push({ label: 'out.println(', insert: 'out.println(', hint: 'Normale Ausgabe' });
        out.push({ label: 'err.println(', insert: 'err.println(', hint: 'Ausgabe in die Fehlerkonsole' });
        return out;
      }
      if (recv === 'Math') {
        var ms = ['abs(', 'max(', 'min(', 'pow(', 'sqrt(', 'floor(', 'ceil(', 'round(', 'random()', 'PI', 'E'];
        for (i = 0; i < ms.length; i++) { out.push({ label: ms[i], insert: ms[i], hint: 'Math' }); }
        return out;
      }
      if (recv === 'Integer') {
        var is = ['parseInt(', 'toString(', 'MAX_VALUE', 'MIN_VALUE'];
        for (i = 0; i < is.length; i++) { out.push({ label: is[i], insert: is[i], hint: 'Integer' }); }
        return out;
      }
      if (recv === 'Double') {
        var ds = ['parseDouble(', 'toString('];
        for (i = 0; i < ds.length; i++) { out.push({ label: ds[i], insert: ds[i], hint: 'Double' }); }
        return out;
      }
      if (recv === 'String') {
        out.push({ label: 'valueOf(', insert: 'valueOf(', hint: 'String' });
        return out;
      }
      if (recv === 'Datenbank') {
        var dbs = ['ausfuehren(', 'abfrage(', 'anzahl(', 'wert(', 'tabellen()'];
        for (i = 0; i < dbs.length; i++) { out.push({ label: dbs[i], insert: dbs[i], hint: 'Datenbank (SQL direkt in Java)' }); }
        return out;
      }
      return out;
    }

    return s;
  }

  /* Vorab-Prüfung nicht unterstützter Sprachelemente mit klarer Meldung. */
  function precheck(files) {
    var checks = [
      [/Scanner/, 'Scanner (Tastatur-Eingaben) wird noch nicht unterstützt.', 'Die Simulation unterstützt noch keine Eingaben. Arbeite mit festen Werten im Programm.'],
      [/\bArrayList\b|\bList\b/, 'ArrayList und List werden noch nicht unterstützt.', 'Nutze Arrays, z. B. int[] zahlen = new int[5];'],
      [/\bHashMap\b|\bMap\b|\bHashSet\b|\bSet\b/, 'HashMap, Map, HashSet und Set werden noch nicht unterstützt.', 'Nutze Arrays, um mehrere Werte zu speichern.'],
      [/\bswitch\b/, 'switch wird noch nicht unterstützt.', 'Nutze if / else if / else statt switch.'],
      [/\btry\b|\bcatch\b|\bfinally\b|\bthrow\b|\bthrows\b/, 'try/catch/throw (Ausnahmebehandlung) wird noch nicht unterstützt.', 'Prüfe Werte vorher mit if, statt Ausnahmen zu werfen.']
    ];
    var names = [];
    var n;
    for (n in files) { if (Object.prototype.hasOwnProperty.call(files, n)) { names.push(n); } }
    for (var i = 0; i < names.length; i++) {
      var name = names[i];
      var lines = files[name].split(NL);
      for (var c = 0; c < checks.length; c++) {
        for (var li = 0; li < lines.length; li++) {
          if (checks[c][0].test(lines[li])) {
            return JError('compile', name, li + 1, 'Zeile ' + (li + 1) + ': ' + checks[c][1], checks[c][2]);
          }
        }
      }
    }
    return null;
  }

  function compileChecks(classes, classMap, errors, files) {
    /* Alle new-Vorkommen: die Klasse muss existieren. */
    var names = [];
    var n;
    for (n in files) { if (Object.prototype.hasOwnProperty.call(files, n)) { names.push(n); } }
    var re = /new\s+([A-Z][A-Za-z0-9_]*)\s*\(/g;
    for (var ni = 0; ni < names.length; ni++) {
      var lines = files[names[ni]].split(NL);
      for (var li = 0; li < lines.length; li++) {
        re.lastIndex = 0;
        var m = re.exec(lines[li]);
        while (m) {
          if (!classMap[m[1]] && !GFX_CLASSES[m[1]]) {
            errors.push(JError('compile', names[ni], li + 1, 'Zeile ' + (li + 1) + ': Unbekannte Klasse ' + m[1] + ' bei new.', 'Lege eine Datei ' + m[1] + '.java mit der Klasse ' + m[1] + ' an (oder prüfe die Schreibweise).'));
            return false;
          }
          m = re.exec(lines[li]);
        }
      }
    }
    /* Klassenname und Dateiname: Bei einer Klasse pro Datei muss der Name
     * passen (Schul-Konvention). Bei mehreren Klassen in einer Datei gilt
     * die Java-Regel: Nur die public-Klasse muss wie die Datei heissen. */
    var perFile = Object.create(null);
    var ci2;
    for (ci2 = 0; ci2 < classes.length; ci2++) {
      perFile[classes[ci2].srcFile] = (perFile[classes[ci2].srcFile] || 0) + 1;
    }
    for (var i = 0; i < classes.length; i++) {
      var cls = classes[i];
      var base = cls.srcFile;
      if (base.length > 5 && base.slice(base.length - 5) === '.java') { base = base.slice(0, base.length - 5); }
      if (cls.name !== base) {
        /* Bei mehreren Klassen in einer Datei ist der Name frei wählbar
         * (z. B. Ober- und Unterklasse in einer Datei); nur bei einer einzigen
         * Klasse pro Datei gilt die Schul-Konvention Name == Dateiname. */
        if (perFile[cls.srcFile] === 1) {
          errors.push(JError('compile', cls.srcFile, cls.line, 'Die Klasse ' + cls.name + ' heißt anders als die Datei ' + cls.srcFile + '.',
            'In Java muss der Name der (einzigen) Klasse genau dem Dateinamen entsprechen: ' + cls.name + '.java'));
          return false;
        }
      }
      if (cls.parent && !classMap[cls.parent] && !GFX_CLASSES[cls.parent]) {
        errors.push(JError('compile', cls.srcFile, cls.line, 'Die Oberklasse ' + cls.parent + ' von ' + cls.name + ' fehlt.', 'Lege eine Datei ' + cls.parent + '.java an (oder korrigiere den Namen hinter extends).'));
        return false;
      }
      for (var mm = 0; mm < cls.methods.length; mm++) {
        var meth = cls.methods[mm];
        for (var p = 0; p < meth.params.length; p++) {
          if (!meth.params[p].type.arr && !isPrimType(meth.params[p].type.name) && meth.params[p].type.name !== 'String' && !classMap[meth.params[p].type.name] && !GFX_CLASSES[meth.params[p].type.name]) {
            errors.push(JError('compile', cls.srcFile, meth.line, 'Unbekannter Typ ' + meth.params[p].type.name + ' in ' + cls.name + '.' + meth.name + '.', 'Der Typ ' + meth.params[p].type.name + ' ist weder eine deiner Klassen noch ein Standard-Typ (int, double, boolean, char, String).'));
            return false;
          }
        }
      }
    }
    return true;
  }

  /* ===== Laufzeit-Hilfen ===== */
  function rt(ctx, line, msg, hint) {
    return { jerr: JError('runtime', ctx ? ctx.file : '', line || (ctx ? ctx.line : 0), msg, hint || '') };
  }

  function requireBool(ctx, v, line, what) {
    if (v.t !== 'boolean') {
      throw rt(ctx, line, 'Die Bedingung (' + what + ') muss boolean sein, ist aber ' + v.t + '.', 'Vergleiche mit ==, <, > usw. ergeben true oder false, z. B. if (zahl > 0).');
    }
    return v.v;
  }

  function findMethod(cls, name, argc) {
    var c = cls;
    while (c) {
      for (var i = 0; i < c.methods.length; i++) {
        if (c.methods[i].name === name && c.methods[i].params.length === argc) { return { cls: c, m: c.methods[i] }; }
      }
      c = c.parent ? RUN_CLASS_MAP[c.parent] : null;
    }
    return null;
  }

  function initStatics(cls, vm) {
    if (vm.statics[cls.name]) { return; }
    vm.statics[cls.name] = Object.create(null);
    if (cls.parent && RUN_CLASS_MAP[cls.parent]) { initStatics(RUN_CLASS_MAP[cls.parent], vm); }
    var ctx = { file: cls.srcFile, line: cls.line, cls: cls, thisVal: null, vm: vm };
    for (var i = 0; i < cls.fields.length; i++) {
      var f = cls.fields[i];
      if (f.isStatic) { continue; }
    }
    for (var j = 0; j < cls.fields.length; j++) {
      var sf = cls.fields[j];
      if (!sf.isStatic) { continue; }
      var sv = sf.init ? evalExpr(sf.init, newEnv(null), ctx) : defaultVal(sf.type);
      if (sf.init) { checkAssign(ctx, { type: sf.type }, sv, sf.line || ctx.line, sf.name); }
      vm.statics[cls.name][sf.name] = sv;
    }
  }

  function newEnv(parent) { return { vars: {}, parent: parent }; }

  /* Objektnamen merken: Wird ein Objekt einer Variablen zugewiesen (Deklaration,
   * Parameter oder Zuweisung), landet der Name im Objekt-Diagramm - auch für
   * Objekte, die in main oder Methoden erzeugt wurden. */
  function recordObjName(vm, val, name) {
    if (!vm || !vm.objects || !val || val.t !== 'ref' || !val.obj) { return; }
    var i;
    for (i = 0; i < vm.objects.length; i++) {
      if (vm.objects[i].obj === val.obj) { vm.objects[i].name = name; return; }
    }
  }

  function envDeclare(env, name, type, val) {
    env.vars[name] = { type: type, val: val };
    recordObjName(RUN_VM, val, name);
  }

  function envFind(env, name) {
    var e = env;
    while (e) {
      if (Object.prototype.hasOwnProperty.call(e.vars, name)) { return e.vars[name]; }
      e = e.parent;
    }
    return null;
  }

  /* Typprüfung bei Zuweisung (deutsche Hilfestellung statt Compiler-Kauderwelsch). */
  /* Ist val.cls eine Unterklasse von target? (Vererbung: Hund ist ein Tier) */
  function classIsA(clsName, targetName) {
    var cc = RUN_CLASS_MAP[clsName] || (GFX_CLASSES[clsName] ? { name: clsName, parent: null } : null);
    var hop = 0;
    while (cc && hop < 32) {
      if (cc.name === targetName) { return true; }
      cc = cc.parent ? (RUN_CLASS_MAP[cc.parent] || (GFX_CLASSES[cc.parent] ? { name: cc.parent, parent: null } : null)) : null;
      hop++;
    }
    return false;
  }
  function checkAssign(ctx, slot, val, line, targetName) {
    var t = slot.type ? slot.type.name : null;
    var isArr = slot.type ? slot.type.arr : false;
    if (isArr) {
      if (val.t === 'array' || val.t === 'null') { return; }
      throw rt(ctx, line, 'Ein Array passt hier nicht zu ' + (t || 'Referenz') + '.', 'Der Wert muss ein Array (oder null) sein: new ' + (t || 'Typ') + '[...] bzw. { ... }.');
    }
    if (t === 'int') {
      if (val.t === 'int') { return; }
      if (val.t === 'double') { throw rt(ctx, line, targetName + ' ist int, der Wert hat Nachkommastellen (double).', 'Java verwirft Nachkommastellen nicht automatisch: schreibe ' + targetName + ' = (int) wert; wenn du abschneiden willst, oder nutze double.'); }
      throw rt(ctx, line, 'int passt nicht zu ' + val.t + '.', 'Der Wert muss int sein (oder mit (int) umgewandelt werden).');
    }
    if (t === 'double') {
      if (val.t === 'double' || val.t === 'int') { return; }
      throw rt(ctx, line, 'double passt nicht zu ' + val.t + '.', 'Der Wert muss eine Zahl sein (int oder double).');
    }
    if (t === 'boolean') {
      if (val.t === 'boolean') { return; }
      throw rt(ctx, line, 'boolean passt nicht zu ' + val.t + '.', 'boolean speichert nur true oder false.');
    }
    if (t === 'char') {
      if (val.t === 'char' || val.t === 'int') { return; }
      throw rt(ctx, line, 'char passt nicht zu ' + val.t + '.', 'Der Wert muss ein einzelnes Zeichen oder ein Zeichencode (int) sein.');
    }
    if (t === 'String') {
      if (val.t === 'String' || val.t === 'null') { return; }
      throw rt(ctx, line, 'String passt nicht zu ' + val.t + '.', targetName + ' ist als String deklariert: Der Wert muss Text in Anführungszeichen sein (oder null).');
    }
    if (t && !isPrimType(t)) {
      if (val.t === 'null') { return; }
      if (val.t === 'ref' && classIsA(val.cls, t)) { return; }
      throw rt(ctx, line, (t || 'Referenz') + ' passt nicht zu ' + (val.t === 'ref' ? val.cls : val.t) + '.', 'Der Wert muss ein ' + t + '-Objekt sein (oder eine Unterklasse davon bzw. null).');
    }
  }

  /* Attribut-Deklaration in der Klassenhierarchie suchen (für die Typprüfung
   * bei Attribut-Zuweisungen: nur Attribute der Klasse, keine GFX-internen Werte). */
  function findFieldDef(cls, name) {
    var c = cls;
    var hop = 0;
    while (c && hop < 32) {
      var i;
      for (i = 0; i < c.fields.length; i++) { if (c.fields[i].name === name) { return c.fields[i]; } }
      c = c.parent ? (RUN_CLASS_MAP[c.parent] || null) : null;
      hop++;
    }
    return null;
  }

  function instanceFieldsOf(cls) {
    var chain = [];
    var c = cls;
    while (c) { chain.unshift(c); c = c.parent ? RUN_CLASS_MAP[c.parent] : null; }
    var fields = [];
    for (var i = 0; i < chain.length; i++) {
      for (var j = 0; j < chain[i].fields.length; j++) {
        if (!chain[i].fields[j].isStatic) { fields.push(chain[i].fields[j]); }
      }
    }
    return fields;
  }

  function initFieldsOf(cls, obj, env, ctx) {
    for (var i = 0; i < cls.fields.length; i++) {
      var f = cls.fields[i];
      if (f.isStatic) { continue; }
      var fv = f.init ? evalExpr(f.init, env, ctx) : defaultVal(f.type);
      if (f.init) { checkAssign(ctx, { type: f.type }, fv, f.line || ctx.line, f.name); }
      obj.f[f.name] = fv;
    }
  }

  function instantiate(cls, args, vm) {
    var ctx = { file: cls.srcFile, line: cls.line, cls: cls, thisVal: null, vm: vm };
    var obj = { cls: cls.name, f: {} };
    /* Objekt-Registry: alle erzeugten Objekte landen im Objektdiagramm. */
    if (vm && vm.objects) { vm.objects.push({ id: vm.objects.length + 1, cls: cls.name, obj: obj }); }
    var allFields = instanceFieldsOf(cls);
    var af;
    for (af = 0; af < allFields.length; af++) { obj.f[allFields[af].name] = defaultVal(allFields[af].type); }
    var ctor = null;
    for (var i = 0; i < cls.ctors.length; i++) {
      if (cls.ctors[i].params.length === args.length) { ctor = cls.ctors[i]; break; }
    }
    if (!ctor && args.length > 0) {
      throw rt(ctx, cls.line, 'Kein Konstruktor von ' + cls.name + ' nimmt ' + args.length + ' Argument(e).', 'Es gibt keinen Konstruktor mit dieser Parameterzahl. Ergänze einen Konstruktor ' + cls.name + '(...) oder passe den new-Aufruf an.');
    }
    if (ctor) { execCtor(cls, ctor, obj, args, newEnv(null)); }
    else { initFieldsOf(cls, obj, newEnv(null), ctx); }
    /* Grafik-Basisklasse (z. B. extends Rectangle) ohne super(...)-Aufruf:
     * Figur mit Standardwerten anlegen. */
    if (!obj.g) {
      var gbase = gfxBaseOf(cls);
      if (gbase) { gfxAttach(obj, gbase, [], vm, ctx, cls.line); }
    }
    return { t: 'ref', cls: cls.name, obj: obj };
  }

  function execCtor(cls, ctor, obj, args, env) {
    var ctx = { file: cls.srcFile, line: ctor.line, cls: cls, thisVal: { t: 'ref', cls: obj.cls, obj: obj }, vm: RUN_VM };
    var i;
    for (i = 0; i < ctor.params.length; i++) {
      checkAssign(ctx, { type: ctor.params[i].type }, args[i], ctor.line, 'Der Parameter ' + ctor.params[i].name);
      envDeclare(env, ctor.params[i].name, ctor.params[i].type, args[i]);
    }
    /* implizites super(), ausser der Rumpf startet mit super(...) oder this(...) */
    var first = ctor.body.length > 0 ? ctor.body[0] : null;
    var explicit = first && first.type === 'exprstat' && first.expr.type === 'call' && first.expr.target &&
      (first.expr.target.type === 'super' || first.expr.target.type === 'this') && first.expr.name === null;
    if (!explicit && cls.parent && RUN_CLASS_MAP[cls.parent]) {
      var parent = RUN_CLASS_MAP[cls.parent];
      var pc = null;
      for (var j = 0; j < parent.ctors.length; j++) { if (parent.ctors[j].params.length === 0) { pc = parent.ctors[j]; break; } }
      if (pc) { execCtor(parent, pc, obj, [], newEnv(null)); }
      else { initFieldsOf(parent, obj, newEnv(null), ctx); }
    }
    initFieldsOf(cls, obj, env, ctx);
    execBlock(ctor.body, env, ctx);
  }

  function invokeMethod(mcls, m, thisVal, args, vm) {
    vm.depth++;
    if (vm.depth > MAX_DEPTH) {
      vm.depth--;
      throw rt({ file: mcls.srcFile, line: m.line }, m.line, 'StackOverflow: zu viele verschachtelte Aufrufe.', 'Vermutlich fehlt die Abbruchbedingung der Rekursion: prüfe, wann die Methode aufhören muss (z. B. if (n <= 0) return ...).');
    }
    var ctx = { file: mcls.srcFile, line: m.line, cls: mcls, thisVal: thisVal, vm: vm };
    var env = newEnv(null);
    var i;
    try {
      for (i = 0; i < m.params.length; i++) {
        checkAssign(ctx, { type: m.params[i].type }, args[i], m.line, 'Der Parameter ' + m.params[i].name);
        envDeclare(env, m.params[i].name, m.params[i].type, args[i]);
      }
    } catch (e2) {
      vm.depth--;
      throw e2;
    }
    var result = VOIDV;
    try {
      execBlock(m.body, env, ctx);
    } catch (e) {
      if (e && e.ctrl === 'return') {
        if (!m.ret.void) {
          if (!e.v || e.v.t === 'void') {
            vm.depth--;
            throw rt(ctx, m.line, 'Die Methode ' + m.name + ' muss einen Wert vom Typ ' + typeName(m.ret) + ' zurückgeben.', 'Jeder Weg durch die Methode braucht ein return wert; mit einem passenden Wert.');
          }
          checkAssign(ctx, { type: m.ret }, e.v, m.line, 'Der Rückgabewert von ' + m.name);
          result = e.v;
          vm.depth--;
          return result;
        }
        if (e.v && e.v.t !== 'void') {
          vm.depth--;
          throw rt(ctx, m.line, 'Die Methode ' + m.name + ' ist void und darf keinen Wert zurückgeben.', 'Bei void-Methoden steht nur return; ohne Wert (oder gar kein return).');
        }
        vm.depth--;
        return result;
      }
      vm.depth--;
      throw e;
    }
    vm.depth--;
    if (!m.ret.void) {
      throw rt(ctx, m.line, 'Die Methode ' + m.name + ' erreicht ihr Ende ohne return.', 'Jeder Weg durch eine Methode mit Rückgabetyp braucht ein return wert; – auch am Ende der Methode oder im else-Zweig.');
    }
    return result;
  }

  /* ===== Anweisungen ===== */
  /* Einzelschritt: Zählt ausgeführte "Blatt"-Anweisungen (Deklarationen und
   * Ausdrucks-Anweisungen). Ist ein stepLimit gesetzt, stoppt das Programm
   * davor und meldet die Zeile, die als Nächstes dran wäre. */
  function stepGate(ctx, st) {
    var vm = ctx && ctx.vm ? ctx.vm : null;
    if (!vm || vm.stepLimit === null || vm.stepLimit === undefined) { return; }
    if (st.type !== 'decl' && st.type !== 'exprstat') { return; }
    if (vm.leafSteps >= vm.stepLimit) {
      throw { pause: true, file: ctx.file, line: st.line };
    }
    vm.leafSteps++;
  }

  function step(ctx) {
    ctx.vm.steps++;
    if (ctx.vm.steps > MAX_STEPS) {
      throw rt(ctx, ctx.line, 'Das Programm macht sehr viele Schritte (Endlosschleife?).', 'Prüfe, ob deine Schleifen-Variable im Rumpf wirklich geändert wird und die Bedingung irgendwann false wird.');
    }
  }

  function output(ctx, text) {
    ctx.vm.out.push(text);
    var len = 0;
    var i;
    for (i = 0; i < ctx.vm.out.length; i++) { len += ctx.vm.out[i].length; }
    if (len > MAX_OUTPUT) {
      throw rt(ctx, ctx.line, 'Das Programm produziert zu viel Ausgabe.', 'Vermutlich läuft eine Schleife zu oft oder ohne Ende. Begrenze die Ausgaben.');
    }
  }

  function stderrOut(ctx, line, text) {
    ctx.vm.errCards.push({ kind: 'stderr', file: ctx.file, line: line, message: text, hint: 'System.err schreibt in die Fehlerkonsole. Für normale Ausgaben nutze System.out.println(...).' });
  }

  function execBlock(body, env, ctx) {
    for (var i = 0; i < body.length; i++) { execStmt(body[i], env, ctx); }
  }

  function execStmt(st, env, ctx) {
    step(ctx);
    if (!st || st.type === 'empty') { return; }
    ctx.line = st.line;
    stepGate(ctx, st);
    if (st.type === 'block') {
      execBlock(st.body, newEnv(env), ctx);
      return;
    }
    if (st.type === 'decl') {
      for (var i = 0; i < st.decls.length; i++) {
        var d = st.decls[i];
        var val = d.init ? evalExpr(d.init, env, ctx) : defaultVal(d.type);
        if (d.init) { checkAssign(ctx, { type: d.type }, val, st.line, d.name); }
        envDeclare(env, d.name, d.type, val);
      }
      return;
    }
    if (st.type === 'exprstat') {
      var ev = evalExpr(st.expr, env, ctx);
      if (ctx.captureExpr) { ctx.lastExprVal = ev; }
      return;
    }
    if (st.type === 'if') {
      var c = evalExpr(st.cond, env, ctx);
      if (requireBool(ctx, c, st.line, 'if')) { execStmt(st.then, env, ctx); }
      else if (st.els) { execStmt(st.els, env, ctx); }
      return;
    }
    if (st.type === 'while') {
      for (;;) {
        step(ctx);
        var wc = evalExpr(st.cond, env, ctx);
        if (!requireBool(ctx, wc, st.line, 'while')) { break; }
        try { execStmt(st.body, env, ctx); }
        catch (e) { if (e && e.ctrl === 'break') { return; } if (e && e.ctrl === 'continue') { continue; } throw e; }
      }
      return;
    }
    if (st.type === 'dowhile') {
      for (;;) {
        step(ctx);
        try { execStmt(st.body, env, ctx); }
        catch (e) { if (e && e.ctrl === 'break') { return; } if (e && !(e && e.ctrl === 'continue')) { throw e; } }
        var dc = evalExpr(st.cond, env, ctx);
        if (!requireBool(ctx, dc, st.line, 'do-while')) { return; }
      }
    }
    if (st.type === 'for') {
      var fenv = newEnv(env);
      if (st.init) { execStmt(st.init, fenv, ctx); }
      for (;;) {
        step(ctx);
        if (st.cond) {
          var fc = evalExpr(st.cond, fenv, ctx);
          if (!requireBool(ctx, fc, st.line, 'for')) { break; }
        }
        try { execStmt(st.body, fenv, ctx); }
        catch (e) { if (e && e.ctrl === 'break') { return; } if (!(e && e.ctrl === 'continue')) { throw e; } }
        if (st.upd) { evalExpr(st.upd, fenv, ctx); }
      }
      return;
    }
    if (st.type === 'return') {
      var rv = st.val ? evalExpr(st.val, env, ctx) : VOIDV;
      throw { ctrl: 'return', v: rv };
    }
    if (st.type === 'break') { throw { ctrl: 'break' }; }
    if (st.type === 'continue') { throw { ctrl: 'continue' }; }
    throw rt(ctx, st.line, 'Interne Anweisung nicht unterstützt: ' + st.type, '');
  }

  /* ===== javaStr: Wert in Text umwandeln (println und String-Plus) ===== */
  function javaStr(v, ctx, line) {
    if (v.t === 'String') { return v.v; }
    if (v.t === 'int') { return String(v.v); }
    if (v.t === 'double') { return fmtDouble(v.v); }
    if (v.t === 'char') { return String.fromCharCode(v.v); }
    if (v.t === 'boolean') { return v.v ? 'true' : 'false'; }
    if (v.t === 'null') { return 'null'; }
    if (v.t === 'array') {
      var parts = [];
      var i;
      for (i = 0; i < v.arr.length; i++) { parts.push(javaStr(v.arr[i], ctx, line)); }
      return '[' + parts.join(', ') + ']';
    }
    if (v.t === 'ref') {
      var cls = RUN_CLASS_MAP[v.cls];
      if (cls) {
        var tm = findMethod(cls, 'toString', 0);
        if (tm && !tm.m.isStatic) {
          var res = invokeMethod(tm.cls, tm.m, v, [], ctx.vm);
          if (res.t === 'String') { return res.v; }
        }
      }
      return v.cls + '@InfoCraft';
    }
    if (v.t === 'void') {
      throw rt(ctx, line, 'Die Methode gibt nichts (void) zurück und kann nicht als Wert verwendet werden.', 'Rufe die void-Methode allein auf oder nutze eine Methode mit Rückgabetyp.');
    }
    return String(v.v);
  }

  /* ===== Ausdrücke ===== */
  function evalExpr(e, env, ctx) {
    step(ctx);
    if (!e) { throw rt(ctx, ctx.line, 'Interner Fehler: leerer Ausdruck.', ''); }
    if (e.line) { ctx.line = e.line; }
    switch (e.type) {
      case 'int': return mkInt(e.v);
      case 'double': return mkDbl(e.v);
      case 'str': return mkStr(e.v);
      case 'char': return mkChar(e.v);
      case 'bool': return e.v ? TRUE : FALSE;
      case 'null': return NULLV;
      case 'void': return VOIDV;
      case 'this':
        if (!ctx.thisVal || ctx.thisVal.t !== 'ref') { throw rt(ctx, e.line, 'this steht hier nicht zur Verfügung.', 'this nutzt man in Objektmethoden und Konstruktoren.'); }
        return ctx.thisVal;
      case 'name': return lookupName(e, env, ctx);
      case 'cond': {
        var c = evalExpr(e.cond, env, ctx);
        if (!requireBool(ctx, c, e.line, 'Bedingungsoperator ?')) { throw rt(ctx, e.line, 'Vor dem ? muss ein boolean stehen.', 'Der Bedingungsoperator hat die Form: bedingung ? wert1 : wert2'); }
        return c.v ? evalExpr(e.a, env, ctx) : evalExpr(e.b, env, ctx);
      }
      case 'un': {
        var v = evalExpr(e.e, env, ctx);
        if (e.op === '!') {
          if (v.t !== 'boolean') { throw rt(ctx, e.line, '! braucht einen boolean-Wert.', 'z. B. !istFertig'); }
          return v.v ? FALSE : TRUE;
        }
        if (v.t !== 'int' && v.t !== 'double' && v.t !== 'char') { throw rt(ctx, e.line, 'Unäres ' + e.op + ' braucht eine Zahl.', ''); }
        if (e.op === '+') { return v.t === 'int' || v.t === 'char' ? mkInt(v.v) : mkDbl(v.v); }
        return v.t === 'int' || v.t === 'char' ? mkInt(-v.v) : mkDbl(-v.v);
      }
      case 'bin': return evalBin(e, env, ctx);
      case 'assign': return evalAssign(e, env, ctx);
      case 'pre': case 'post': {
        var ref = resolveLValue(e.e, env, ctx);
        var cur = ref.get();
        if (cur.t !== 'int' && cur.t !== 'double') { throw rt(ctx, e.line, e.op + ' funktioniert nur mit Zahlen.', 'z. B. zaehler++'); }
        var nv = cur.t === 'int' ? mkInt(cur.v + (e.op === '++' ? 1 : -1)) : mkDbl(cur.v + (e.op === '++' ? 1 : -1));
        ref.set(nv);
        return e.type === 'pre' ? nv : cur;
      }
      case 'new': {
        var cls = RUN_CLASS_MAP[e.cls];
        if (!cls) {
          if (GFX_CLASSES[e.cls]) {
            var ga = [];
            var gi2;
            for (gi2 = 0; gi2 < e.args.length; gi2++) { ga.push(evalExpr(e.args[gi2], env, ctx)); }
            return gfxNew(e.cls, ga, ctx, e.line);
          }
          throw rt(ctx, e.line, 'Unbekannte Klasse ' + e.cls + '.', 'Es gibt keine Klasse ' + e.cls + '. Prüfe die Schreibweise oder lege die Klasse an.');
        }
        var aargs = [];
        var ai;
        for (ai = 0; ai < e.args.length; ai++) { aargs.push(evalExpr(e.args[ai], env, ctx)); }
        return instantiate(cls, aargs, ctx.vm);
      }
      case 'newarr': {
        var sz = evalExpr(e.size, env, ctx);
        if (sz.t !== 'int') { throw rt(ctx, e.line, 'Die Array-Größe muss int sein.', 'z. B. new int[10]'); }
        if (sz.v < 0) { throw rt(ctx, e.line, 'NegativeArraySizeException: Das Array soll Größe ' + sz.v + ' haben.', 'Die Größe eines Arrays darf nicht negativ sein.'); }
        var arr = [];
        var dv = e.elem === 'int' ? mkInt(0) : (e.elem === 'double' ? mkDbl(0) : (e.elem === 'boolean' ? FALSE : (e.elem === 'char' ? mkChar(0) : NULLV)));
        for (ai = 0; ai < sz.v; ai++) { arr.push(dv); }
        return { t: 'array', elem: e.elem, arr: arr };
      }
      case 'arrlit': {
        var items = [];
        var ii;
        for (ii = 0; ii < e.items.length; ii++) {
          var iv = evalExpr(e.items[ii], env, ctx);
          if (e.elem === 'double' && iv.t === 'int') { items.push(mkDbl(iv.v)); }
          else { items.push(iv); }
        }
        return { t: 'array', elem: e.elem, arr: items };
      }
      case 'index': return resolveLValue(e, env, ctx).get();
      case 'field': {
        /* Statische Konstanten der Grafik-API: Color.red, Alignment.center */
        if (e.target && e.target.type === 'name' && (e.target.v === 'Color' || e.target.v === 'Alignment') && !envFind(env, e.target.v)) {
          return gfxStaticGet(e.target.v, e.name, ctx, e.line);
        }
        return resolveField(e, evalExpr(e.target, env, ctx), ctx);
      }
      case 'cast': return evalCast(e, env, ctx);
      case 'call': return evalCall(e, env, ctx);
      case 'super': throw rt(ctx, e.line, 'super ist nur für super(...) oder super.methode(...) gedacht.', 'z. B. super.getFarbe() oder im Konstruktor: super(name).');
    }
    throw rt(ctx, e.line, 'Interner Fehler im Ausdruck: ' + e.type, '');
  }

  function lookupName(e, env, ctx) {
    var slot = envFind(env, e.v);
    if (slot) { return slot.val; }
    if (ctx.thisVal && ctx.thisVal.t === 'ref' && Object.prototype.hasOwnProperty.call(ctx.thisVal.obj.f, e.v)) {
      return ctx.thisVal.obj.f[e.v];
    }
    var c = ctx.cls;
    while (c) {
      if (ctx.vm.statics[c.name] && Object.prototype.hasOwnProperty.call(ctx.vm.statics[c.name], e.v)) {
        return ctx.vm.statics[c.name][e.v];
      }
      c = c.parent ? RUN_CLASS_MAP[c.parent] : null;
    }
    throw rt(ctx, e.line, 'Die Variable ' + e.v + ' ist nicht bekannt.', 'Prüfe die Schreibweise (Java unterscheidet Groß-/Kleinschreibung) und ob ' + e.v + ' deklariert ist (int ' + e.v + ' = ...).');
  }

  function resolveLValue(e, env, ctx) {
    if (e.type === 'name') {
      var slot = envFind(env, e.v);
      if (slot) {
        return {
          get: function () { return slot.val; },
          set: function (nv) {
            checkAssign(ctx, slot, nv, e.line, e.v);
            recordObjName(ctx.vm, nv, e.v);
            slot.val = nv;
          }
        };
      }
      if (ctx.thisVal && ctx.thisVal.t === 'ref' && Object.prototype.hasOwnProperty.call(ctx.thisVal.obj.f, e.v)) {
        var obj = ctx.thisVal.obj;
        var selfDef = RUN_CLASS_MAP[ctx.thisVal.cls] ? findFieldDef(RUN_CLASS_MAP[ctx.thisVal.cls], e.v) : null;
        return {
          get: function () { return obj.f[e.v]; },
          set: function (nv) {
            if (selfDef) { checkAssign(ctx, { type: selfDef.type }, nv, e.line, e.v); }
            obj.f[e.v] = nv;
          }
        };
      }
      var c = ctx.cls;
      while (c) {
        if (ctx.vm.statics[c.name] && Object.prototype.hasOwnProperty.call(ctx.vm.statics[c.name], e.v)) {
          var st = ctx.vm.statics[c.name];
          var stDef = findFieldDef(c, e.v);
          return {
            get: function () { return st[e.v]; },
            set: function (nv) {
              if (stDef) { checkAssign(ctx, { type: stDef.type }, nv, e.line, e.v); }
              st[e.v] = nv;
            }
          };
        }
        c = c.parent ? RUN_CLASS_MAP[c.parent] : null;
      }
      throw rt(ctx, e.line, 'Die Variable ' + e.v + ' ist nicht bekannt.', 'Prüfe die Schreibweise (Java unterscheidet Groß-/Kleinschreibung) und ob ' + e.v + ' deklariert ist.');
    }
    if (e.type === 'field') {
      var target = evalExpr(e.target, env, ctx);
      if (target.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Objekt für ' + '.' + e.name + ' ist null.', 'Das Objekt wurde vermutlich noch nicht mit new erzeugt oder eine Methode hat null zurückgegeben.'); }
      if (target.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + e.name + ' steht kein Objekt.', 'Attribute erreicht man über ein Objekt, z. B. auto.farbe'); }
      var o2 = target.obj;
      if (!Object.prototype.hasOwnProperty.call(o2.f, e.name)) {
        throw rt(ctx, e.line, 'Das Attribut ' + e.name + ' gehört nicht zur Klasse ' + target.cls + '.', 'Prüfe Schreibweise und Groß-/Kleinschreibung und ob die Klasse ein Attribut ' + e.name + ' hat.');
      }
      var fDef = RUN_CLASS_MAP[target.cls] ? findFieldDef(RUN_CLASS_MAP[target.cls], e.name) : null;
      return {
        get: function () { return o2.f[e.name]; },
        set: function (nv) {
          if (fDef) { checkAssign(ctx, { type: fDef.type }, nv, e.line, target.cls + '.' + e.name); }
          o2.f[e.name] = nv;
        }
      };
    }
    if (e.type === 'index') {
      var targetArr = evalExpr(e.target, env, ctx);
      if (targetArr.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Array ist null.', 'Erzeuge das Array zuerst: new int[...] oder new int[]{...}'); }
      if (targetArr.t !== 'array') { throw rt(ctx, e.line, 'In eckigen Klammern [] kann man nur auf Arrays zugreifen.', 'z. B. zahlen[0]'); }
      var idx = evalExpr(e.index, env, ctx);
      if (idx.t !== 'int') { throw rt(ctx, e.line, 'Der Array-Index muss int sein.', 'z. B. zahlen[0] oder zahlen[i]'); }
      if (idx.v < 0 || idx.v >= targetArr.arr.length) {
        throw rt(ctx, e.line, 'ArrayIndexOutOfBoundsException: Index ' + idx.v + ', das Array hat ' + targetArr.arr.length + ' Element(e).', 'Gültige Indizes gehen von 0 bis ' + (targetArr.arr.length - 1) + '. Prüfe die Schleifen-Grenzen (i < länge, nicht <=).');
      }
      var theArr = targetArr.arr;
      var theIdx = idx.v;
      return {
        get: function () { return theArr[theIdx]; },
        set: function (nv) { theArr[theIdx] = nv; }
      };
    }
    throw rt(ctx, e.line, 'Hier kann kein Wert zugewiesen werden.', 'Links von = muss eine Variable, ein Attribut oder ein Array-Platz stehen.');
  }

  function resolveField(e, target, ctx) {
    if (e.name === 'length' && target.t === 'array') { return mkInt(target.arr.length); }
    if (target.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Objekt für .' + e.name + ' ist null.', 'Erzeuge das Objekt mit new, bevor du darauf zugreifst.'); }
    if (target.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + e.name + ' steht kein Objekt.', ''); }
    if (!Object.prototype.hasOwnProperty.call(target.obj.f, e.name)) {
      throw rt(ctx, e.line, 'Das Attribut ' + e.name + ' gehört nicht zur Klasse ' + target.cls + '.', 'Prüfe Schreibweise und Groß-/Kleinschreibung und ob die Klasse ein Attribut ' + e.name + ' hat.');
    }
    return target.obj.f[e.name];
  }

  function evalAssign(e, env, ctx) {
    var ref = resolveLValue(e.target, env, ctx);
    if (e.op === '=') {
      var val = evalExpr(e.val, env, ctx);
      ref.set(val);
      return val;
    }
    var cur = ref.get();
    var rhs = evalExpr(e.val, env, ctx);
    var combined = binop(e.op.charAt(0), cur, rhs, ctx, e.line);
    ref.set(combined);
    return combined;
  }

  function evalCast(e, env, ctx) {
    var v = evalExpr(e.e, env, ctx);
    if (e.ty === 'int') {
      if (v.t === 'int' || v.t === 'char') { return mkInt(v.v); }
      if (v.t === 'double') { return mkInt(Math.trunc(v.v)); }
      throw rt(ctx, e.line, '(int) funktioniert nur mit Zahlen.', 'z. B. (int) 3.9 ergibt 3.');
    }
    if (e.ty === 'double') {
      if (v.t === 'int' || v.t === 'double' || v.t === 'char') { return mkDbl(v.v); }
      throw rt(ctx, e.line, '(double) funktioniert nur mit Zahlen.', '');
    }
    if (e.ty === 'char') {
      if (v.t === 'char') { return v; }
      if (v.t === 'int') { return mkChar(v.v); }
      throw rt(ctx, e.line, '(char) funktioniert nur mit Zahlen oder Zeichen.', 'z. B. (char) 65 ergibt das Zeichen A.');
    }
    if (e.ty === 'boolean') {
      if (v.t === 'boolean') { return v; }
      throw rt(ctx, e.line, '(boolean) funktioniert nur mit boolean-Werten.', '');
    }
    if (v.t === 'null' || v.t === 'ref') { return v; }
    throw rt(ctx, e.line, 'Dieser Cast wird nicht unterstützt.', '');
  }

  function evalBin(e, env, ctx) {
    var op = e.op;
    if (op === '&&' || op === '||') {
      var l = evalExpr(e.l, env, ctx);
      if (!requireBool(ctx, l, e.line, op)) { throw rt(ctx, e.line, 'Der linke Teil von ' + op + ' muss boolean sein.', 'Mit ' + op + ' verknüpfst du Bedingungen, z. B. if (x > 0 && x < 10).'); }
      if (op === '&&' && !l.v) { return FALSE; }
      if (op === '||' && l.v) { return TRUE; }
      var r = evalExpr(e.r, env, ctx);
      if (!requireBool(ctx, r, e.line, op)) { throw rt(ctx, e.line, 'Der rechte Teil von ' + op + ' muss boolean sein.', 'Mit ' + op + ' verknüpfst du Bedingungen, z. B. if (x > 0 && x < 10).'); }
      return r.v ? TRUE : FALSE;
    }
    var lv = evalExpr(e.l, env, ctx);
    var rv = evalExpr(e.r, env, ctx);
    return binop(op, lv, rv, ctx, e.line);
  }

  function binop(op, lv, rv, ctx, line) {
    if (op === '==' || op === '!=') {
      var eq = primEq(lv, rv, ctx, line, op);
      return mkBool(op === '==' ? eq : !eq);
    }
    var isStr = lv.t === 'String' || rv.t === 'String';
    if (op === '+' && isStr) {
      return mkStr(javaStr(lv, ctx, line) + javaStr(rv, ctx, line));
    }
    if (op === '+' || op === '-' || op === '*' || op === '/' || op === '%') {
      if (lv.t === 'null' || rv.t === 'null') {
        throw rt(ctx, line, 'NullPointerException: Ein Operand von ' + op + ' ist null (kein Objekt).', 'Objekte müssen mit new erzeugt werden, bevor man mit ihnen rechnet.');
      }
      if (lv.t !== 'int' && lv.t !== 'double' && lv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' kann man nicht auf ' + lv.t + ' rechnen.', 'Links und rechts müssen Zahlen (oder Strings für +) stehen.'); }
      if (rv.t !== 'int' && rv.t !== 'double' && rv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' kann man nicht auf ' + rv.t + ' rechnen.', 'Links und rechts müssen Zahlen (oder Strings für +) stehen.'); }
      var bothInt = (lv.t === 'int' || lv.t === 'char') && (rv.t === 'int' || rv.t === 'char');
      var a = lv.v;
      var b = rv.v;
      if (op === '+') { return bothInt ? mkInt(a + b) : mkDbl(a + b); }
      if (op === '-') { return bothInt ? mkInt(a - b) : mkDbl(a - b); }
      if (op === '*') { return bothInt ? mkInt(a * b) : mkDbl(a * b); }
      if (op === '/') {
        if (bothInt) {
          if (b === 0) { throw rt(ctx, line, 'ArithmeticException: Division durch 0 (int).', 'Eine Ganzzahl-Division durch 0 ist in Java nicht erlaubt. Prüfe den Teiler.'); }
          return mkInt(Math.trunc(a / b));
        }
        return mkDbl(a / b);
      }
      if (op === '%') {
        if (bothInt) {
          if (b === 0) { throw rt(ctx, line, 'ArithmeticException: Division durch 0 (int, Rest %).', 'Der Teiler hinter % darf nicht 0 sein.'); }
          return mkInt(a - Math.trunc(a / b) * b);
        }
        return mkDbl(a % b);
      }
    }
    if (op === '<' || op === '>' || op === '<=' || op === '>=') {
      if (lv.t === 'null' || rv.t === 'null') { throw rt(ctx, line, 'Mit ' + op + ' kann man null nicht vergleichen.', ''); }
      if (lv.t !== 'int' && lv.t !== 'double' && lv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' vergleicht man Zahlen.', 'z. B. if (punkte > 10)'); }
      if (rv.t !== 'int' && rv.t !== 'double' && rv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' vergleicht man Zahlen.', 'z. B. if (punkte > 10)'); }
      var x = lv.v;
      var y = rv.v;
      if (op === '<') { return mkBool(x < y); }
      if (op === '>') { return mkBool(x > y); }
      if (op === '<=') { return mkBool(x <= y); }
      return mkBool(x >= y);
    }
    throw rt(ctx, line, 'Operation ' + op + ' wird nicht unterstützt.', '');
  }

  function primEq(lv, rv, ctx, line, op) {
    if (lv.t === 'String' && rv.t === 'String') {
      throw rt(ctx, line, 'Strings vergleicht man nicht mit ' + op + ', sondern mit equals.', 'z. B. if (name.equals("Anna")) { ... }. ' + op + ' vergleicht Objekte, nicht deren Inhalt.');
    }
    if ((lv.t === 'String') !== (rv.t === 'String')) {
      throw rt(ctx, line, 'Einen String vergleicht man mit equals, nicht mit ' + op + '.', 'z. B. if (name.equals("Anna")) { ... }');
    }
    if (lv.t === rv.t) { return lv.v === rv.v || (lv.t === 'ref' && rv.t === 'ref' && lv.obj === rv.obj); }
    if ((lv.t === 'int' || lv.t === 'char' || lv.t === 'double') && (rv.t === 'int' || rv.t === 'char' || rv.t === 'double')) { return lv.v === rv.v; }
    if (lv.t === 'null' || rv.t === 'null') { return lv.t === rv.t; }
    return false;
  }

  /* ===== Methodenaufrufe ===== */
  function evalCall(e, env, ctx) {
    var name = e.name;
    var args = [];
    var i;
    for (i = 0; i < e.args.length; i++) { args.push(evalExpr(e.args[i], env, ctx)); }

    /* super(...) im Konstruktor */
    if (e.target && e.target.type === 'super' && name === null) {
      if (ctx.cls && ctx.cls.parent && GFX_CLASSES[ctx.cls.parent] && !RUN_CLASS_MAP[ctx.cls.parent]) {
        if (!ctx.thisVal || ctx.thisVal.t !== 'ref') { throw rt(ctx, e.line, 'super(...) braucht ein Objekt.', ''); }
        gfxAttach(ctx.thisVal.obj, ctx.cls.parent, args, ctx.vm, ctx, e.line);
        return VOIDV;
      }
      if (!ctx.cls || !ctx.cls.parent || !RUN_CLASS_MAP[ctx.cls.parent]) { throw rt(ctx, e.line, 'super(...) geht nur, wenn die Klasse extends hat.', 'Schreibe hinter den Klassennamen: extends Oberklasse.'); }
      var pcls = RUN_CLASS_MAP[ctx.cls.parent];
      var pc = null;
      for (var j = 0; j < pcls.ctors.length; j++) { if (pcls.ctors[j].params.length === args.length) { pc = pcls.ctors[j]; break; } }
      if (!pc) { throw rt(ctx, e.line, 'Kein Konstruktor von ' + pcls.name + ' nimmt ' + args.length + ' Argument(e).', 'Legge den passenden Konstruktor in ' + pcls.name + ' an.'); }
      execCtor(pcls, pc, ctx.thisVal.obj, args, newEnv(null));
      return VOIDV;
    }
    /* this(...) im Konstruktor */
    if (e.target && e.target.type === 'this' && name === null) {
      var tcls = ctx.cls;
      var tc = null;
      for (var j2 = 0; j2 < tcls.ctors.length; j2++) { if (tcls.ctors[j2].params.length === args.length) { tc = tcls.ctors[j2]; break; } }
      if (!tc) { throw rt(ctx, e.line, 'Kein Konstruktor von ' + tcls.name + ' nimmt ' + args.length + ' Argument(e).', 'Legge den passenden Konstruktor an.'); }
      execCtor(tcls, tc, ctx.thisVal.obj, args, newEnv(null));
      return VOIDV;
    }

    /* unqualifizierter Aufruf: Methode der eigenen Klasse (inkl. Vererbung) */
    if (!e.target) {
      var fm = findMethod(ctx.cls, name, args.length);
      if (fm) {
        if (fm.m.isStatic) { return invokeMethod(fm.cls, fm.m, null, args, ctx.vm); }
        if (!ctx.thisVal || ctx.thisVal.t !== 'ref') { throw rt(ctx, e.line, 'Die Methode ' + name + ' braucht ein Objekt.', 'Die Methode ist nicht static; rufe sie über ein Objekt auf oder schreibe static davor.'); }
        return invokeMethod(fm.cls, fm.m, ctx.thisVal, args, ctx.vm);
      }
      /* Eigene Klasse erbt von einer Grafik-Klasse: move, act, isKeyDown u. a. */
      if (ctx.thisVal && ctx.thisVal.t === 'ref' && ctx.thisVal.obj && ctx.thisVal.obj.g) {
        return gfxMethodCall(ctx.thisVal, name, args, ctx, e.line);
      }
      throw rt(ctx, e.line, 'Die Methode ' + name + ' mit ' + args.length + ' Parameter(n) ist nicht bekannt.', 'Prüfe Schreibweise und Groß-/Kleinschreibung, die Parameterzahl und ob die Methode überhaupt existiert.');
    }

    var target = e.target;

    /* System.out / System.err */
    if (target.type === 'field' && target.target && target.target.type === 'name' && target.target.v === 'System' && (target.name === 'out' || target.name === 'err')) {
      return systemCall(target.name, name, args, ctx, e.line);
    }

    /* Klassennamen als Ziel (statische Aufrufe) */
    if (target.type === 'name' && RUN_CLASS_MAP[target.v]) {
      var scls = RUN_CLASS_MAP[target.v];
      var sm = findMethod(scls, name, args.length);
      if (sm) {
        if (sm.m.isStatic) { return invokeMethod(sm.cls, sm.m, null, args, ctx.vm); }
        throw rt(ctx, e.line, 'Die Methode ' + name + ' ist nicht static und braucht ein Objekt von ' + scls.name + '.', 'Erzeuge zuerst ein Objekt: ' + scls.name + ' obj = new ' + scls.name + '(...);');
      }
      throw rt(ctx, e.line, 'Die Klasse ' + target.v + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Prüfe Schreibweise und Parameterzahl.');
    }

    /* Builtins */
    if (target.type === 'name' && target.v === 'Math') { return mathCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Integer') { return integerCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Double') { return doubleCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'String') { return stringStaticCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Datenbank') { return dbCall(name, args, ctx, e.line); }
    /* Statische Standardwerte der Grafik-API */
    if (target.type === 'name' && (target.v === 'Shape' || target.v === 'FilledShape')) { return gfxStaticCall(target.v, name, args, ctx, e.line); }

    /* super.methode(): Suche ab der Oberklasse */
    if (target.type === 'super') {
      if (!ctx.cls || !ctx.cls.parent || !RUN_CLASS_MAP[ctx.cls.parent]) { throw rt(ctx, e.line, 'super.methode() geht nur, wenn die Klasse extends hat.', ''); }
      var supCls = RUN_CLASS_MAP[ctx.cls.parent];
      var supm = findMethod(supCls, name, args.length);
      if (!supm) { throw rt(ctx, e.line, 'Die Oberklasse ' + supCls.name + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Prüfe Schreibweise und Parameterzahl.'); }
      return invokeMethod(supm.cls, supm.m, ctx.thisVal, args, ctx.vm);
    }

    var recv = evalExpr(target, env, ctx);

    if (recv.t === 'String') { return stringCall(recv, name, args, ctx, e.line); }
    if (recv.t === 'array') {
      if (name === 'length' && args.length === 0) { return mkInt(recv.arr.length); }
      throw rt(ctx, e.line, 'Arrays haben keine Methode ' + name + '.', 'Die Länge bekommt man mit array.length (ohne Klammern).');
    }
    if (recv.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Der Aufruf ' + name + '(...) läuft auf null.', 'Das Objekt wurde vermutlich noch nicht mit new erzeugt. Prüfe auch Rückgaben anderer Methoden.'); }
    if (recv.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + name + '(...) steht kein Objekt.', ''); }
    /* Grafik-Objekte (auch eigene Unterklassen): eigene Überschreibungen
     * gewinnen, danach kommt die eingebaute Grafik-API. */
    if (recv.obj && recv.obj.g) {
      var gUser = RUN_CLASS_MAP[recv.cls];
      var gum = gUser ? findMethod(gUser, name, args.length) : null;
      if (gum && !gum.m.isStatic) { return invokeMethod(gum.cls, gum.m, recv, args, ctx.vm); }
      return gfxMethodCall(recv, name, args, ctx, e.line);
    }
    var objCls = RUN_CLASS_MAP[recv.cls];
    var vm0 = findMethod(objCls, name, args.length);
    if (!vm0) {
      throw rt(ctx, e.line, 'Die Klasse ' + recv.cls + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Prüfe Schreibweise und Groß-/Kleinschreibung, die Parameterzahl und ob die Methode existiert.');
    }
    return invokeMethod(vm0.cls, vm0.m, recv, args, ctx.vm);
  }

  /* ===== System / Math / Integer / Double / String ===== */
  function systemCall(stream, fname, args, ctx, line) {
    if (fname !== 'println' && fname !== 'print') {
      throw rt(ctx, line, 'System.' + stream + '.' + fname + ' ist nicht unterstützt.', 'Nutze System.out.println(...), System.out.print(...), System.err.println(...) oder System.err.print(...).');
    }
    var text = '';
    if (args.length === 1) { text = javaStr(args[0], ctx, line); }
    else if (args.length > 1) {
      var parts = [];
      var i;
      for (i = 0; i < args.length; i++) { parts.push(javaStr(args[i], ctx, line)); }
      text = parts.join(' ');
    }
    var tail = fname === 'println' ? NL : '';
    if (stream === 'err') { stderrOut(ctx, line, text + tail); }
    else { output(ctx, text + tail); }
    return VOIDV;
  }

  function mathCall(name, args, ctx, line) {
    if (name === 'PI') { return mkDbl(Math.PI); }
    if (name === 'E') { return mkDbl(Math.E); }
    var vals = [];
    var i;
    for (i = 0; i < args.length; i++) {
      var a = args[i];
      if (a.t !== 'int' && a.t !== 'double' && a.t !== 'char') { throw rt(ctx, line, 'Math.' + name + ' braucht Zahl-Argumente.', ''); }
      vals.push(a.v);
    }
    if (name === 'abs' && vals.length === 1) { return args[0].t === 'int' ? mkInt(Math.abs(vals[0])) : mkDbl(Math.abs(vals[0])); }
    if (name === 'max' && vals.length === 2) { return args[0].t === 'int' ? mkInt(Math.max(vals[0], vals[1])) : mkDbl(Math.max(vals[0], vals[1])); }
    if (name === 'min' && vals.length === 2) { return args[0].t === 'int' ? mkInt(Math.min(vals[0], vals[1])) : mkDbl(Math.min(vals[0], vals[1])); }
    if (name === 'pow' && vals.length === 2) { return mkDbl(Math.pow(vals[0], vals[1])); }
    if (name === 'sqrt' && vals.length === 1) { return mkDbl(Math.sqrt(vals[0])); }
    if (name === 'floor' && vals.length === 1) { return mkDbl(Math.floor(vals[0])); }
    if (name === 'ceil' && vals.length === 1) { return mkDbl(Math.ceil(vals[0])); }
    if (name === 'round' && vals.length === 1) { return mkInt(Math.round(vals[0])); }
    if (name === 'random' && vals.length === 0) { return mkDbl(Math.random()); }
    throw rt(ctx, line, 'Math.' + name + ' wird nicht unterstützt (oder falsche Parameterzahl).', 'Verfügbar: abs, max, min, pow, sqrt, floor, ceil, round, random, PI, E.');
  }

  function integerCall(name, args, ctx, line) {
    if (name === 'parseInt') {
      if (args.length !== 1 || args[0].t !== 'String') { throw rt(ctx, line, 'Integer.parseInt braucht genau einen String.', 'z. B. Integer.parseInt("42")'); }
      var digits = args[0].v.trim();
      var digitsMatch = digits.match(/^-?[0-9]+/);
      if (!digitsMatch || digitsMatch[0] !== digits) {
        throw rt(ctx, line, 'NumberFormatException: ' + JSON.stringify(args[0].v) + ' ist keine ganze Zahl.', 'Integer.parseInt versteht nur Ziffern (optional mit - davor), z. B. Integer.parseInt("42").');
      }
      return mkInt(parseInt(args[0].v.trim(), 10));
    }
    if (name === 'toString') {
      if (args.length !== 1 || args[0].t !== 'int') { throw rt(ctx, line, 'Integer.toString braucht genau ein int.', ''); }
      return mkStr(String(args[0].v));
    }
    if (name === 'MAX_VALUE') { return mkInt(2147483647); }
    if (name === 'MIN_VALUE') { return mkInt(-2147483648); }
    throw rt(ctx, line, 'Integer.' + name + ' wird nicht unterstützt.', 'Verfügbar: parseInt, toString, MAX_VALUE, MIN_VALUE.');
  }

  function doubleCall(name, args, ctx, line) {
    if (name === 'parseDouble') {
      if (args.length !== 1 || args[0].t !== 'String') { throw rt(ctx, line, 'Double.parseDouble braucht genau einen String.', ''); }
      var numTxt = args[0].v.trim();
      var numMatch = numTxt.match(/^-?[0-9]+([.][0-9]+)?/);
      if (!numMatch || numMatch[0] !== numTxt) {
        throw rt(ctx, line, 'NumberFormatException: ' + JSON.stringify(args[0].v) + ' ist keine Zahl.', 'z. B. Double.parseDouble("3.5")');
      }
      return mkDbl(parseFloat(args[0].v));
    }
    throw rt(ctx, line, 'Double.' + name + ' wird nicht unterstützt.', 'Verfügbar: parseDouble.');
  }

  function stringStaticCall(name, args, ctx, line) {
    if (name === 'valueOf' && args.length === 1) { return mkStr(javaStr(args[0], ctx, line)); }
    throw rt(ctx, line, 'String.' + name + ' wird nicht unterstützt.', 'Verfügbar: String.valueOf(...).');
  }

  /* ===== Datenbank: eingebaute Mini-SQL-Datenbank (im Speicher, pro Lauf neu) =====
   * Für Schülerinnen und Schüler: Tabellen anlegen, befüllen und abfragen,
   * ganz ohne Installation. SQL-Kenntnisse aus der SQL-Werkstatt lassen sich
   * so direkt im Java-Programm nutzen.
   */
  function dbTables() {
    if (!RUN_DB) { RUN_DB = Object.create(null); }
    return RUN_DB;
  }

  function dbText(v) {
    if (v.t === 'String') { return v.v; }
    if (v.t === 'int') { return String(v.v); }
    if (v.t === 'double') { return fmtDouble(v.v); }
    if (v.t === 'boolean') { return v.v ? 'true' : 'false'; }
    if (v.t === 'char') { return String.fromCharCode(v.v); }
    return 'null';
  }

  function dbPad(s, w) {
    var r = String(s);
    while (r.length < w) { r += ' '; }
    return r;
  }

  function dbDashes(w) {
    var r = '';
    while (r.length < w) { r += '-'; }
    return r;
  }

  function dbStrip(s) {
    var r = String(s);
    while (r.length > 0) {
      var last = r.charAt(r.length - 1);
      if (last === ';' || last === ' ' || last === String.fromCharCode(9) || last === String.fromCharCode(13)) { r = r.slice(0, r.length - 1); }
      else { break; }
    }
    return r;
  }

  /* Regex-Treffer muss die komplette Zeichenkette abdecken (statt Anker am Ende). */
  function dbFull(re, s) {
    var m = re.exec(s);
    if (!m) { return null; }
    if (m.index + m[0].length !== s.length) { return null; }
    return m;
  }

  function dbFindTable(name, ctx, line) {
    var t = dbTables()[String(name).toLowerCase()];
    if (!t) {
      throw rt(ctx, line, 'Die Tabelle ' + name + ' gibt es noch nicht.', 'Lege sie zuerst an: Datenbank.ausfuehren("CREATE TABLE ' + name + ' (spalte TYP, ...)")');
    }
    return t;
  }

  function dbColIdx(table, name, ctx, line) {
    var low = String(name).toLowerCase();
    var i;
    for (i = 0; i < table.cols.length; i++) {
      if (table.cols[i].name.toLowerCase() === low) { return i; }
    }
    var alle = [];
    for (i = 0; i < table.cols.length; i++) { alle.push(table.cols[i].name); }
    throw rt(ctx, line, 'Die Spalte ' + name + ' gibt es in der Tabelle ' + table.name + ' nicht.', 'Verfügbare Spalten: ' + alle.join(', '));
  }

  /* Teilt an Kommas auf oberster Ebene, ignoriert Kommas in 'Texten' und Klammern. */
  function dbSplitTop(s) {
    var parts = [];
    var cur = '';
    var depth = 0;
    var inQ = false;
    var i;
    var c;
    for (i = 0; i < s.length; i++) {
      c = s.charAt(i);
      if (inQ) {
        cur += c;
        if (c === "'") {
          if (s.charAt(i + 1) === "'") { cur += s.charAt(i + 1); i++; }
          else { inQ = false; }
        }
        continue;
      }
      if (c === "'") { inQ = true; cur += c; continue; }
      if (c === '(') { depth++; cur += c; continue; }
      if (c === ')') { depth--; cur += c; continue; }
      if (c === ',' && depth === 0) { parts.push(cur); cur = ''; continue; }
      cur += c;
    }
    parts.push(cur);
    return parts;
  }

  /* Sucht ein Schlüsselwort (WHERE, ORDER BY, LIMIT) ausserhalb von 'Texten'
   * und liefert [davor, danach] oder null. */
  function dbCutWord(s, word) {
    var w = word.toLowerCase();
    var i;
    var c;
    var inQ = false;
    for (i = 0; i < s.length; i++) {
      c = s.charAt(i);
      if (inQ) {
        if (c === "'") {
          if (s.charAt(i + 1) === "'") { i++; continue; }
          inQ = false;
        }
        continue;
      }
      if (c === "'") { inQ = true; continue; }
      if (c !== ' ' && c !== String.fromCharCode(9)) { continue; }
      if (s.substr(i + 1, w.length).toLowerCase() !== w) { continue; }
      var after = s.charAt(i + 1 + w.length);
      if (after !== ' ' && after !== String.fromCharCode(9) && after !== '') { continue; }
      return [s.slice(0, i), s.slice(i + 1 + w.length)];
    }
    return null;
  }

  /* String-Literal: '...' mit verdoppeltem Anführungszeichen '' als Escape. */
  function dbParseString(raw) {
    var out = '';
    var i;
    for (i = 1; i < raw.length - 1; i++) {
      if (raw.charAt(i) === "'" && raw.charAt(i + 1) === "'") { out += "'"; i++; continue; }
      out += raw.charAt(i);
    }
    return out;
  }

  function dbParseLiteral(raw, ctx, line) {
    var s = String(raw).replace(/^\s+/, '');
    s = dbStrip(s).replace(/^\s+/, '');
    if (s.length >= 2 && s.charAt(0) === "'" && s.charAt(s.length - 1) === "'") {
      return { t: 'String', v: dbParseString(s) };
    }
    if (s === 'true') { return { t: 'boolean', v: true }; }
    if (s === 'false') { return { t: 'boolean', v: false }; }
    if (s === 'null') { return { t: 'null', v: null }; }
    var im = /^-?[0-9]+/.exec(s);
    if (im && im[0] === s) { return { t: 'int', v: parseInt(s, 10) }; }
    var dm = /^-?[0-9]+\.[0-9]+/.exec(s);
    if (dm && dm[0] === s) { return { t: 'double', v: parseFloat(s) }; }
    throw rt(ctx, line, 'Der Wert ' + dbStrip(raw) + ' ist kein gültiger SQL-Wert.', 'Text gehört in einfache Anführungszeichen: ' + "'Anna'" + '. Zahlen schreibt man ohne: 12 oder 1.5');
  }

  var DB_WHERE_TOK = /'(?:[^']|'')*'|<=|>=|<>|!=|[A-Za-z_][A-Za-z0-9_]*|[0-9]+(?:\.[0-9]+)?|[=<>]/g;

  /* WHERE in Gruppen zerlegen: OR trennt Gruppen, AND verknüpft innerhalb. */
  function dbParseWhere(cond, table, ctx, line) {
    var toks = [];
    var m;
    DB_WHERE_TOK.lastIndex = 0;
    while ((m = DB_WHERE_TOK.exec(cond))) { toks.push(m[0]); }
    if (toks.length === 0) {
      throw rt(ctx, line, 'Die WHERE-Bedingung ist unvollständig.', 'z. B. WHERE name = ' + "'Anna'" + ' oder WHERE punkte > 10');
    }
    var groups = [[]];
    var i = 0;
    var w;
    while (i < toks.length) {
      w = toks[i].toUpperCase();
      if (w === 'OR') {
        if (groups[groups.length - 1].length === 0) { throw rt(ctx, line, 'Bei WHERE fehlt vor OR ein Vergleich.', 'z. B. WHERE a = 1 OR b = 2'); }
        groups.push([]);
        i++;
        continue;
      }
      if (w === 'AND') {
        if (groups[groups.length - 1].length === 0) { throw rt(ctx, line, 'Bei WHERE fehlt vor AND ein Vergleich.', 'z. B. WHERE a = 1 AND b = 2'); }
        i++;
        continue;
      }
      if (i + 2 >= toks.length) {
        throw rt(ctx, line, 'Der Vergleich bei WHERE ist unvollständig.', 'Jeder Vergleich hat die Form spalte OP wert, z. B. punkte > 10.');
      }
      var op = toks[i + 1].toUpperCase();
      if (op !== '=' && op !== '!=' && op !== '<>' && op !== '<' && op !== '>' && op !== '<=' && op !== '>=' && op !== 'LIKE') {
        throw rt(ctx, line, 'Den Vergleich ' + toks[i + 1] + ' kenne ich nicht.', 'Erlaubt: = != < > <= >= und LIKE (mit % als Platzhalter).');
      }
      if (op === '<>') { op = '!='; }
      groups[groups.length - 1].push({
        idx: dbColIdx(table, toks[i], ctx, line),
        op: op,
        val: dbParseLiteral(toks[i + 2], ctx, line)
      });
      i += 3;
    }
    if (groups[groups.length - 1].length === 0) {
      throw rt(ctx, line, 'Der WHERE-Teil ist unvollständig.', 'z. B. WHERE name = ' + "'Anna'");
    }
    return groups;
  }

  function dbLikeToRegex(pat) {
    var rx = '';
    var i;
    var c;
    for (i = 0; i < pat.length; i++) {
      c = pat.charAt(i);
      if (c === '%') { rx += '.*'; continue; }
      if (c === '_') { rx += '.'; continue; }
      if ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c === ' ') { rx += c; continue; }
      rx += '\\' + c;
    }
    return rx;
  }

  function dbCmp(a, op, b) {
    if (a.t === 'null' || b.t === 'null') {
      if (op === '=') { return a.t === 'null' && b.t === 'null'; }
      if (op === '!=') { return !(a.t === 'null' && b.t === 'null'); }
      return false;
    }
    if (op === 'LIKE') {
      var pat = dbLikeToRegex(String(b.t === 'String' ? b.v : dbText(b)));
      return new RegExp('^' + pat, 'i').test(String(a.t === 'String' ? a.v : dbText(a)));
    }
    var an = (a.t === 'int' || a.t === 'double') ? a.v : null;
    var bn = (b.t === 'int' || b.t === 'double') ? b.v : null;
    var av;
    var bv;
    if ((an === null) !== (bn === null)) {
      av = dbText(a);
      bv = dbText(b);
    } else if (an !== null) {
      av = an;
      bv = bn;
    } else {
      av = dbText(a);
      bv = dbText(b);
    }
    if (op === '=') { return av === bv; }
    if (op === '!=') { return av !== bv; }
    if (op === '<') { return av < bv; }
    if (op === '>') { return av > bv; }
    if (op === '<=') { return av <= bv; }
    if (op === '>=') { return av >= bv; }
    return false;
  }

  function dbEvalWhere(groups, row) {
    var g;
    var c;
    for (g = 0; g < groups.length; g++) {
      var ok = true;
      for (c = 0; c < groups[g].length; c++) {
        if (!dbCmp(row[groups[g][c].idx], groups[g][c].op, groups[g][c].val)) { ok = false; break; }
      }
      if (ok) { return true; }
    }
    return false;
  }

  /* Ausführen von CREATE TABLE / INSERT / UPDATE / DELETE / DROP TABLE. */
  function dbExec(sqlText, ctx, line) {
    var sql = dbStrip(String(sqlText).replace(/^\s+/, ''));
    var km = /^\s*([A-Za-z]+)/.exec(sql);
    var kw = km ? km[1].toUpperCase() : '';
    if (kw === '') {
      throw rt(ctx, line, 'Der SQL-Befehl ist leer.', 'z. B. Datenbank.ausfuehren("CREATE TABLE tabelle (spalte TEXT)")');
    }
    var m;
    var table;
    var i;
    if (kw === 'CREATE') {
      m = dbFull(/^\s*CREATE\s+TABLE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(([\s\S]*)\)/i, sql);
      if (!m) {
        throw rt(ctx, line, 'CREATE TABLE verstehe ich nicht.', 'Form: CREATE TABLE name (spalte TYP, ...) – z. B. CREATE TABLE schueler (name TEXT, punkte INT)');
      }
      if (dbTables()[m[1].toLowerCase()]) {
        throw rt(ctx, line, 'Die Tabelle ' + m[1] + ' existiert bereits.', 'Wähle einen anderen Namen oder lösche sie vorher: DROP TABLE ' + m[1]);
      }
      var colParts = dbSplitTop(m[2]);
      var cols = [];
      for (i = 0; i < colParts.length; i++) {
        var cm = /^\s*([A-Za-z_][A-Za-z0-9_]*)\s+(TEXT|STRING|VARCHAR|CHAR|INT|INTEGER|DOUBLE|REAL|FLOAT|DECIMAL)\s*/i.exec(colParts[i]);
        if (!cm || cm[0] !== colParts[i]) {
          throw rt(ctx, line, 'Die Spaltenangabe ' + colParts[i].trim() + ' verstehe ich nicht.', 'Form: spalte TYP mit TYP = TEXT, INT oder DOUBLE, z. B. name TEXT');
        }
        var ty = cm[2].toUpperCase();
        var kind = (ty === 'TEXT' || ty === 'STRING' || ty === 'VARCHAR' || ty === 'CHAR') ? 'text' : ((ty === 'INT' || ty === 'INTEGER') ? 'int' : 'double');
        cols.push({ name: cm[1], kind: kind });
      }
      if (cols.length === 0) {
        throw rt(ctx, line, 'Eine Tabelle braucht mindestens eine Spalte.', 'z. B. CREATE TABLE schueler (name TEXT)');
      }
      dbTables()[m[1].toLowerCase()] = { name: m[1], cols: cols, rows: [] };
      return;
    }
    if (kw === 'DROP') {
      m = dbFull(/^\s*DROP\s+TABLE\s+([A-Za-z_][A-Za-z0-9_]*)/i, sql);
      if (!m) {
        throw rt(ctx, line, 'DROP TABLE verstehe ich nicht.', 'Form: DROP TABLE name');
      }
      table = dbFindTable(m[1], ctx, line);
      delete dbTables()[m[1].toLowerCase()];
      return;
    }
    if (kw === 'INSERT') {
      m = dbFull(/^\s*INSERT\s+INTO\s+([A-Za-z_][A-Za-z0-9_]*)\s*(\([^)]*\)\s*)?VALUES\s+([\s\S]*)/i, sql);
      if (!m) {
        throw rt(ctx, line, 'INSERT verstehe ich nicht.', 'Form: INSERT INTO tabelle VALUES (wert, ...) – Text in einfachen Anführungszeichen.');
      }
      table = dbFindTable(m[1], ctx, line);
      var targetCols = null;
      if (m[2]) {
        var inner = m[2].slice(1, m[2].indexOf(')'));
        targetCols = [];
        var colNames = dbSplitTop(inner);
        for (i = 0; i < colNames.length; i++) { targetCols.push(dbColIdx(table, colNames[i].trim(), ctx, line)); }
      }
      var tupParts = dbSplitTop(m[3]);
      var ti;
      for (ti = 0; ti < tupParts.length; ti++) {
        var tp = tupParts[ti].trim();
        var tm = /^\(([\s\S]*)\)/.exec(tp);
        if (!tm) {
          throw rt(ctx, line, 'Werte bei INSERT müssen in Klammern stehen.', 'z. B. INSERT INTO ' + table.name + ' VALUES (' + "'Anna'" + ', 12)');
        }
        var lits = dbSplitTop(tm[1]);
        var useCols = targetCols;
        if (!useCols) {
          if (lits.length !== table.cols.length) {
            throw rt(ctx, line, 'INSERT hat ' + lits.length + ' Wert(e), die Tabelle ' + table.name + ' hat aber ' + table.cols.length + ' Spalte(n).', 'Gib alle Werte an oder benenne die Spalten: INSERT INTO tabelle (spalte) VALUES (...).');
          }
          useCols = [];
          for (i = 0; i < table.cols.length; i++) { useCols.push(i); }
        }
        if (lits.length !== useCols.length) {
          throw rt(ctx, line, 'INSERT hat ' + lits.length + ' Wert(e), erwartet werden aber ' + useCols.length + '.', 'Anzahl der Werte und Spalten muss übereinstimmen.');
        }
        var row = [];
        for (i = 0; i < table.cols.length; i++) { row.push({ t: 'null', v: null }); }
        var wi;
        for (wi = 0; wi < lits.length; wi++) {
          var val = dbParseLiteral(lits[wi], ctx, line);
          var colIdx = useCols[wi];
          var colName = table.cols[colIdx].name;
          var kind = table.cols[colIdx].kind;
          if (kind === 'int') {
            if (val.t === 'double') { val = { t: 'int', v: Math.trunc(val.v) }; }
            else if (val.t === 'String') {
              throw rt(ctx, line, 'In die Spalte ' + colName + ' (INT) gehört eine Zahl ohne Anführungszeichen.', 'z. B. 12 statt ' + "'" + val.v + "'");
            }
          } else if (kind === 'text') {
            if (val.t === 'int' || val.t === 'double') {
              throw rt(ctx, line, 'In die Spalte ' + colName + ' (TEXT) gehört ein Text in einfachen Anführungszeichen.', "z. B. 'Anna' statt " + val.v);
            }
          }
          row[colIdx] = val;
        }
        table.rows.push(row);
      }
      return;
    }
    if (kw === 'UPDATE') {
      m = dbFull(/^\s*UPDATE\s+([A-Za-z_][A-Za-z0-9_]*)\s+SET\s+([\s\S]*)/i, sql);
      if (!m) {
        throw rt(ctx, line, 'UPDATE verstehe ich nicht.', 'Form: UPDATE tabelle SET spalte = wert WHERE ...');
      }
      table = dbFindTable(m[1], ctx, line);
      var setPart = m[2];
      var whereSql = null;
      var wcut = dbCutWord(m[2], 'WHERE');
      if (wcut) { setPart = wcut[0]; whereSql = wcut[1]; }
      var assigns = dbSplitTop(setPart);
      if (assigns.length === 0 || dbStrip(assigns[0]) === '') {
        throw rt(ctx, line, 'UPDATE braucht mindestens ein SET spalte = wert.', 'z. B. UPDATE schueler SET punkte = 10 WHERE name = ' + "'Ben'");
      }
      var sets = [];
      for (i = 0; i < assigns.length; i++) {
        var am = /^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([\s\S]*)/.exec(assigns[i]);
        if (!am) {
          throw rt(ctx, line, 'Die Zuweisung ' + assigns[i].trim() + ' verstehe ich nicht.', 'Form: spalte = wert, z. B. punkte = 10');
        }
        sets.push({ idx: dbColIdx(table, am[1], ctx, line), val: dbParseLiteral(am[2], ctx, line) });
      }
      var groups = whereSql ? dbParseWhere(whereSql, table, ctx, line) : null;
      for (i = 0; i < table.rows.length; i++) {
        if (!groups || dbEvalWhere(groups, table.rows[i])) {
          var si;
          for (si = 0; si < sets.length; si++) { table.rows[i][sets[si].idx] = sets[si].val; }
        }
      }
      return;
    }
    if (kw === 'DELETE') {
      m = dbFull(/^\s*DELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)([\s\S]*)/i, sql);
      if (!m) {
        throw rt(ctx, line, 'DELETE verstehe ich nicht.', 'Form: DELETE FROM tabelle WHERE ...');
      }
      table = dbFindTable(m[1], ctx, line);
      var groups2 = null;
      var rest2 = m[2];
      if (dbStrip(rest2) !== '') {
        var wcut2 = dbCutWord(rest2, 'WHERE');
        if (!wcut2 || dbStrip(wcut2[0]) !== '') {
          throw rt(ctx, line, 'Bei DELETE ist nur WHERE erlaubt.', 'z. B. DELETE FROM tabelle WHERE punkte < 5');
        }
        groups2 = dbParseWhere(wcut2[1], table, ctx, line);
      }
      var kept = [];
      for (i = 0; i < table.rows.length; i++) {
        if (groups2 && dbEvalWhere(groups2, table.rows[i])) { continue; }
        kept.push(table.rows[i]);
      }
      table.rows = kept;
      return;
    }
    if (kw === 'SELECT') {
      throw rt(ctx, line, 'SELECT gehört in Datenbank.abfrage(...), nicht in ausfuehren(...).', 'z. B. System.out.println(Datenbank.abfrage("SELECT * FROM tabelle"));');
    }
    throw rt(ctx, line, 'Den SQL-Befehl ' + kw + ' unterstützt die Mini-Datenbank nicht.', 'Verfügbar: CREATE TABLE, INSERT INTO, UPDATE, DELETE FROM, DROP TABLE, SELECT.');
  }

  /* SELECT ausfuehren: Spalten, WHERE, ORDER BY, LIMIT, COUNT(*). */
  function dbSelect(sqlText, ctx, line) {
    var sql = dbStrip(String(sqlText).replace(/^\s+/, ''));
    var m = dbFull(/^\s*SELECT\s+([\s\S]*?)\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)([\s\S]*)/i, sql);
    if (!m) {
      throw rt(ctx, line, 'SELECT verstehe ich nicht.', 'Form: SELECT spalten FROM tabelle [WHERE ...] [ORDER BY ...] – oder SELECT * FROM tabelle.');
    }
    var table = dbFindTable(m[2], ctx, line);
    var colSpec = m[1].trim();
    var rest = m[3];
    var whereSql = null;
    var orderCol = null;
    var orderDir = 'ASC';
    var limit = -1;
    var cut = dbCutWord(rest, 'LIMIT');
    if (cut) {
      var limVal = dbStrip(cut[1]).replace(/^\s+/, '');
      var lmv = /^[0-9]+/.exec(limVal);
      if (!lmv || lmv[0] !== limVal) {
        throw rt(ctx, line, 'LIMIT braucht eine ganze Zahl.', 'z. B. LIMIT 5');
      }
      limit = parseInt(limVal, 10);
      rest = cut[0];
    }
    cut = dbCutWord(rest, 'ORDER BY');
    if (cut) {
      var om = /^\s*([A-Za-z_][A-Za-z0-9_]*)\s*(ASC|DESC)?\s*/i.exec(cut[1]);
      if (!om) {
        throw rt(ctx, line, 'ORDER BY braucht eine Spalte.', 'z. B. ORDER BY punkte DESC');
      }
      orderCol = om[1];
      orderDir = om[2] ? om[2].toUpperCase() : 'ASC';
      var afterOrd = dbStrip(cut[1].slice(om[0].length));
      if (afterOrd !== '') {
        throw rt(ctx, line, 'Nach ORDER BY ' + om[1] + ' steht etwas Unerwartetes.', 'Erlaubt: ORDER BY spalte ASC oder ORDER BY spalte DESC.');
      }
      rest = cut[0];
    }
    cut = dbCutWord(rest, 'WHERE');
    if (cut) { whereSql = cut[1]; rest = cut[0]; }
    if (dbStrip(rest) !== '') {
      throw rt(ctx, line, 'Den Zusatz ' + dbStrip(rest) + ' verstehe ich nicht.', 'Erlaubt nach FROM tabelle: WHERE ..., ORDER BY spalte [ASC|DESC], LIMIT zahl.');
    }
    /* Spalten */
    var selCols = [];
    var i;
    var j;
    if (colSpec === '*') {
      for (i = 0; i < table.cols.length; i++) { selCols.push({ name: table.cols[i].name, idx: i }); }
      if (table.cols.length === 0) {
        throw rt(ctx, line, 'Die Tabelle ' + table.name + ' hat keine Spalten.', 'Prüfe das CREATE TABLE.');
      }
    } else {
      var namesList = dbSplitTop(colSpec);
      for (i = 0; i < namesList.length; i++) {
        var nm = namesList[i].trim();
        var cntM = /^COUNT\s*\(\s*\*\s*\)/i.exec(nm);
        if (cntM && cntM[0] === nm) { selCols.push({ name: 'COUNT(*)', count: true }); continue; }
        selCols.push({ name: nm, idx: dbColIdx(table, nm, ctx, line) });
      }
      if (selCols.length === 0) {
        throw rt(ctx, line, 'SELECT braucht mindestens eine Spalte.', 'z. B. SELECT * FROM tabelle oder SELECT name FROM tabelle');
      }
    }
    /* Zeilen filtern */
    var groups = whereSql ? dbParseWhere(whereSql, table, ctx, line) : null;
    var rows = [];
    for (i = 0; i < table.rows.length; i++) {
      if (!groups || dbEvalWhere(groups, table.rows[i])) { rows.push(table.rows[i]); }
    }
    /* Sortieren */
    if (orderCol) {
      var oIdx = dbColIdx(table, orderCol, ctx, line);
      var desc = orderDir === 'DESC';
      rows.sort(function (a, b) {
        var av = a[oIdx];
        var bv = b[oIdx];
        var an2 = (av.t === 'int' || av.t === 'double') ? av.v : null;
        var bn2 = (bv.t === 'int' || bv.t === 'double') ? bv.v : null;
        var cmp;
        if (an2 !== null && bn2 !== null) { cmp = an2 < bn2 ? -1 : (an2 > bn2 ? 1 : 0); }
        else {
          var as2 = dbText(av);
          var bs2 = dbText(bv);
          cmp = as2 < bs2 ? -1 : (as2 > bs2 ? 1 : 0);
        }
        return desc ? -cmp : cmp;
      });
    }
    if (limit >= 0 && rows.length > limit) { rows = rows.slice(0, limit); }
    /* Ergebnis aufbauen */
    var resCols = [];
    var resRows = [];
    var hasCount = false;
    for (i = 0; i < selCols.length; i++) { resCols.push(selCols[i].name); if (selCols[i].count) { hasCount = true; } }
    if (hasCount) {
      var cntRow = [];
      for (i = 0; i < selCols.length; i++) { cntRow.push(selCols[i].count ? { t: 'int', v: rows.length } : rows.length > 0 ? rows[0][selCols[i].idx] : { t: 'null', v: null }); }
      resRows.push(cntRow);
    } else {
      for (i = 0; i < rows.length; i++) {
        var outRow = [];
        for (j = 0; j < selCols.length; j++) { outRow.push(rows[i][selCols[j].idx]); }
        resRows.push(outRow);
      }
    }
    return { cols: resCols, rows: resRows };
  }

  /* Ergebnis als ausgerichtete Texttabelle. */
  function dbFormat(res) {
    if (res.rows.length === 0) { return '(keine Zeilen)'; }
    var w = [];
    var i;
    var j;
    for (i = 0; i < res.cols.length; i++) {
      w[i] = res.cols[i].length;
      for (j = 0; j < res.rows.length; j++) {
        var t = dbText(res.rows[j][i]);
        if (t.length > w[i]) { w[i] = t.length; }
      }
    }
    var lines = [];
    var head = [];
    for (i = 0; i < res.cols.length; i++) { head.push(dbPad(res.cols[i], w[i])); }
    lines.push(head.join(' | '));
    var sep = [];
    for (i = 0; i < res.cols.length; i++) { sep.push(dbDashes(w[i])); }
    lines.push(sep.join('-+-'));
    for (j = 0; j < res.rows.length; j++) {
      var cells = [];
      for (i = 0; i < res.cols.length; i++) { cells.push(dbPad(dbText(res.rows[j][i]), w[i])); }
      lines.push(cells.join(' | '));
    }
    return lines.join(NL);
  }

  function dbCall(name, args, ctx, line) {
    function wantSql(method) {
      if (args.length !== 1 || args[0].t !== 'String') {
        throw rt(ctx, line, 'Datenbank.' + method + ' braucht genau 1 Parameter: den SQL-Befehl als Text.', 'z. B. Datenbank.' + method + '("SELECT * FROM tabelle")');
      }
      return args[0].v;
    }
    if (name === 'ausfuehren') {
      dbExec(wantSql('ausfuehren'), ctx, line);
      return VOIDV;
    }
    if (name === 'abfrage') {
      return mkStr(dbFormat(dbSelect(wantSql('abfrage'), ctx, line)));
    }
    if (name === 'anzahl') {
      return mkInt(dbSelect(wantSql('anzahl'), ctx, line).rows.length);
    }
    if (name === 'wert') {
      var res = dbSelect(wantSql('wert'), ctx, line);
      if (res.rows.length === 0 || res.rows[0].length === 0) { return mkStr(''); }
      return mkStr(dbText(res.rows[0][0]));
    }
    if (name === 'tabellen') {
      if (args.length !== 0) {
        throw rt(ctx, line, 'Datenbank.tabellen braucht keine Parameter.', 'z. B. System.out.println(Datenbank.tabellen());');
      }
      var alle = [];
      var k;
      for (k in dbTables()) {
        if (Object.prototype.hasOwnProperty.call(dbTables(), k)) { alle.push(dbTables()[k].name); }
      }
      return mkStr(alle.length === 0 ? '(noch keine Tabellen)' : alle.join(', '));
    }
    throw rt(ctx, line, 'Datenbank.' + name + ' wird nicht unterstützt.', 'Verfügbar: ausfuehren(sql), abfrage(sql), anzahl(sql), wert(sql), tabellen().');
  }

  function stringCall(recv, name, args, ctx, line) {
    var s = recv.v;
    function wantStr(i, what) {
      if (!args[i] || args[i].t !== 'String') { throw rt(ctx, line, what + ' braucht ein String-Argument.', 'z. B. text' + '.' + what + '("...")'); }
      return args[i].v;
    }
    function wantIdx(i, what) {
      if (!args[i] || (args[i].t !== 'int' && args[i].t !== 'char')) { throw rt(ctx, line, what + ' braucht ein int-Argument.', ''); }
      return args[i].v;
    }
    if (name === 'length' && args.length === 0) { return mkInt(s.length); }
    if (name === 'charAt' && args.length === 1) {
      var idx = wantIdx(0, 'charAt');
      if (idx < 0 || idx >= s.length) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: charAt(' + idx + '), aber der String hat ' + s.length + ' Zeichen.', 'Gültige Indizes: 0 bis ' + (s.length - 1) + '.'); }
      return mkChar(s.charCodeAt(idx));
    }
    if (name === 'substring' && args.length === 1) {
      var b = wantIdx(0, 'substring');
      if (b < 0 || b > s.length) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: substring(' + b + ') bei Länge ' + s.length + '.', 'Der Start-Index muss zwischen 0 und ' + s.length + ' liegen.'); }
      return mkStr(s.substring(b));
    }
    if (name === 'substring' && args.length === 2) {
      var b2 = wantIdx(0, 'substring');
      var e2 = wantIdx(1, 'substring');
      if (b2 < 0 || e2 > s.length || b2 > e2) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: substring(' + b2 + ', ' + e2 + ') bei Länge ' + s.length + '.', 'Es gilt 0 <= Anfang <= Ende <= ' + s.length + '.'); }
      return mkStr(s.substring(b2, e2));
    }
    if (name === 'indexOf' && args.length >= 1) {
      var needle = args[0].t === 'String' ? args[0].v : (args[0].t === 'char' ? String.fromCharCode(args[0].v) : null);
      if (needle === null) { throw rt(ctx, line, 'indexOf braucht ein String- oder char-Argument.', 'z. B. text.indexOf("Wort")'); }
      return mkInt(s.indexOf(needle));
    }
    if (name === 'equals' && args.length === 1) { return mkBool(s === wantStr(0, 'equals')); }
    if (name === 'equalsIgnoreCase' && args.length === 1) { return mkBool(s.toLowerCase() === wantStr(0, 'equalsIgnoreCase').toLowerCase()); }
    if (name === 'contains' && args.length === 1) { return mkBool(s.indexOf(wantStr(0, 'contains')) >= 0); }
    if (name === 'startsWith' && args.length === 1) { return mkBool(s.indexOf(wantStr(0, 'startsWith')) === 0); }
    if (name === 'endsWith' && args.length === 1) {
      var suf = wantStr(0, 'endsWith');
      return mkBool(s.length >= suf.length && s.substring(s.length - suf.length) === suf);
    }
    if (name === 'toUpperCase' && args.length === 0) { return mkStr(s.toUpperCase()); }
    if (name === 'toLowerCase' && args.length === 0) { return mkStr(s.toLowerCase()); }
    if (name === 'trim' && args.length === 0) { return mkStr(s.trim()); }
    if (name === 'replace' && args.length === 2) {
      var from = args[0].t === 'String' ? args[0].v : String.fromCharCode(args[0].v);
      var to = args[1].t === 'String' ? args[1].v : String.fromCharCode(args[1].v);
      return mkStr(s.split(from).join(to));
    }
    if (name === 'isEmpty' && args.length === 0) { return mkBool(s.length === 0); }
    if (name === 'compareTo' && args.length === 1) {
      var o = wantStr(0, 'compareTo');
      if (s === o) { return mkInt(0); }
      return mkInt(s < o ? -1 : 1);
    }
    throw rt(ctx, line, 'Die String-Methode ' + name + ' (mit ' + args.length + ' Parametern) wird nicht unterstützt.', 'Verfügbar: length, charAt, substring, indexOf, equals, equalsIgnoreCase, contains, startsWith, endsWith, toUpperCase, toLowerCase, trim, replace, isEmpty, compareTo.');
  }

  /* ================= Grafik-Fenster (API angelehnt an learnj / OnlineIDE) =================
   * Ein eigenes Fenster mit XY-Feld, in dem Schülerinnen und Schüler Figuren
   * platzieren, bewegen, drehen, animieren und auf Maus und Tastatur reagieren
   * lassen können. Alles läuft im Browser auf einem Canvas - kein Server.
   * Klassen: World, Color, Alignment, Circle, Ellipse, Rectangle, RoundedRectangle,
   * Text, Triangle, Line, Polygon, Bitmap, Turtle, Group, Actor sowie die
   * GUI-Elemente Button, Textfield, Checkbox, Radiobutton.
   */
  var GFX_CLASSES = Object.create(null);
  var GFCL = ['World', 'Color', 'Alignment', 'Circle', 'Ellipse', 'Rectangle', 'RoundedRectangle', 'Text', 'Triangle', 'Line', 'Polygon', 'Bitmap', 'Turtle', 'Group', 'Actor', 'Shape', 'FilledShape', 'Button', 'Textfield', 'Checkbox', 'Radiobutton'];
  var gfci;
  for (gfci = 0; gfci < GFCL.length; gfci++) { GFX_CLASSES[GFCL[gfci]] = 1; }

  var GFX_KIND_OF = Object.create(null);
  GFX_KIND_OF.World = 'world';
  GFX_KIND_OF.Color = 'color';
  GFX_KIND_OF.Circle = 'circle';
  GFX_KIND_OF.Ellipse = 'ellipse';
  GFX_KIND_OF.Rectangle = 'rectangle';
  GFX_KIND_OF.RoundedRectangle = 'rounded';
  GFX_KIND_OF.Text = 'text';
  GFX_KIND_OF.Triangle = 'triangle';
  GFX_KIND_OF.Line = 'line';
  GFX_KIND_OF.Polygon = 'polygon';
  GFX_KIND_OF.Bitmap = 'bitmap';
  GFX_KIND_OF.Turtle = 'turtle';
  GFX_KIND_OF.Group = 'group';
  GFX_KIND_OF.Actor = 'actor';
  GFX_KIND_OF.Button = 'button';
  GFX_KIND_OF.Textfield = 'textfield';
  GFX_KIND_OF.Checkbox = 'checkbox';
  GFX_KIND_OF.Radiobutton = 'radiobutton';

  var GFX_SHAPE_KINDS = Object.create(null);
  var GFX_FILLED_KINDS = Object.create(null);
  var GS = ['circle', 'ellipse', 'rectangle', 'rounded', 'text', 'triangle', 'line', 'polygon', 'bitmap', 'turtle', 'button', 'textfield', 'checkbox', 'radiobutton'];
  var GFILLED = ['circle', 'ellipse', 'rectangle', 'rounded', 'text', 'triangle', 'polygon', 'bitmap'];
  var gsi;
  for (gsi = 0; gsi < GS.length; gsi++) { GFX_SHAPE_KINDS[GS[gsi]] = 1; }
  for (gsi = 0; gsi < GFILLED.length; gsi++) { GFX_FILLED_KINDS[GFILLED[gsi]] = 1; }

  /* Voreinstellungen (per Shape.setDefaultVisibility / FilledShape.setDefault... änderbar) */
  var GFX_DEFAULTS = { visible: true, fill: '#0000ff', border: null, borderWidth: 1 };
  var GFX_KEYS = Object.create(null);
  var GFX_ONERROR = null;
  var GFX_TIMER = null;
  var GFX_TEST_MODE = false;
  try {
    if (typeof global !== 'undefined' && global && global.__INFOCRAFT_TEST__) { GFX_TEST_MODE = true; }
  } catch (igfx) { }

  function gfxNewState() { return { shapes: [], world: null, worldRef: null, windowState: null }; }

  function gfxNum(args, i, what, ctx, line) {
    var v = args[i];
    if (!v || (v.t !== 'int' && v.t !== 'double' && v.t !== 'char')) {
      throw rt(ctx, line, what + ' braucht eine Zahl an Position ' + (i + 1) + '.', 'z. B. ' + what + '(400, 300, ...)');
    }
    return v.v;
  }
  function gfxStr(args, i, what, ctx, line) {
    var v = args[i];
    if (!v || v.t !== 'String') {
      throw rt(ctx, line, what + ' braucht einen String an Position ' + (i + 1) + '.', 'Setze den Text in Anführungszeichen, z. B. ' + what + '("Hallo").');
    }
    return v.v;
  }
  function gfxBool(args, i, what, ctx, line) {
    var v = args[i];
    if (!v || v.t !== 'boolean') {
      throw rt(ctx, line, what + ' braucht true oder false an Position ' + (i + 1) + '.', '');
    }
    return v.v;
  }

  var COLOR_TABLE = Object.create(null);
  COLOR_TABLE.red = '#ff0000'; COLOR_TABLE.green = '#008000'; COLOR_TABLE.blue = '#0000ff';
  COLOR_TABLE.yellow = '#ffff00'; COLOR_TABLE.orange = '#ffa500'; COLOR_TABLE.pink = '#ffc0cb';
  COLOR_TABLE.magenta = '#ff00ff'; COLOR_TABLE.cyan = '#00ffff'; COLOR_TABLE.white = '#ffffff';
  COLOR_TABLE.black = '#000000'; COLOR_TABLE.gray = '#808080'; COLOR_TABLE.grey = '#808080';
  COLOR_TABLE.lightGray = '#d3d3d3'; COLOR_TABLE.darkGray = '#404040';
  COLOR_TABLE.brown = '#a52a2a'; COLOR_TABLE.purple = '#800080';
  /* Kleingeschriebene Alias-Keys: Lookups laufen über den klein geschriebenen Namen */
  COLOR_TABLE.lightgray = '#d3d3d3'; COLOR_TABLE.darkgray = '#404040';

  function colorToRgb(v, ctx, line) {
    /* Liefert [r, g, b, a] oder null (bei null-Übergabe: nichts füllen). */
    if (!v || v.t === 'null') { return null; }
    if (v.t === 'String') {
      var s = v.v;
      if (s.charAt(0) === '#') {
        var hx = s.slice(1);
        if (hx.length === 3) {
          return [parseInt(hx.charAt(0) + hx.charAt(0), 16), parseInt(hx.charAt(1) + hx.charAt(1), 16), parseInt(hx.charAt(2) + hx.charAt(2), 16), 1];
        }
        if (hx.length === 6) {
          return [parseInt(hx.slice(0, 2), 16), parseInt(hx.slice(2, 4), 16), parseInt(hx.slice(4, 6), 16), 1];
        }
        throw rt(ctx, line, 'Die Farbe ' + s + ' ist keine gültige Hex-Zahl.', 'Schreibe sie als #rrggbb, z. B. "#ff8000".');
      }
      var low = s.toLowerCase();
      if (low.slice(0, 4) === 'rgb(' && s.slice(-1) === ')') {
        var parts = s.slice(4, s.length - 1).split(',');
        return [parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2], 10), 1];
      }
      if (low.slice(0, 5) === 'rgba(' && s.slice(-1) === ')') {
        var parts2 = s.slice(5, s.length - 1).split(',');
        return [parseInt(parts2[0], 10), parseInt(parts2[1], 10), parseInt(parts2[2], 10), parseFloat(parts2[3])];
      }
      if (COLOR_TABLE[low]) { return colorToRgb(mkStr(COLOR_TABLE[low]), ctx, line); }
      throw rt(ctx, line, 'Die Farbe ' + s + ' ist nicht bekannt.', 'Nutze eine bekannte Farbe ("red"), eine Hex-Zahl ("#ff8000") oder rgb(255, 128, 0).');
    }
    if (v.t === 'int') {
      return [(v.v >> 16) & 255, (v.v >> 8) & 255, v.v & 255, 1];
    }
    if (v.t === 'ref' && v.obj && v.obj.g && v.obj.g.kind === 'color') {
      return [v.obj.g.r, v.obj.g.g2, v.obj.g.b, v.obj.g.a];
    }
    throw rt(ctx, line, 'Diese Farbe wird nicht unterstützt.', 'Erlaubt sind Color-Konstanten (Color.red), "#ff8000", rgb(255,128,0), rgba(...,0.3) oder new Color(255, 128, 0).');
  }
  function rgbCss(rgbv) {
    if (!rgbv) { return null; }
    if (rgbv[3] !== undefined && rgbv[3] !== 1) { return 'rgba(' + rgbv[0] + ', ' + rgbv[1] + ', ' + rgbv[2] + ', ' + rgbv[3] + ')'; }
    return 'rgb(' + rgbv[0] + ', ' + rgbv[1] + ', ' + rgbv[2] + ')';
  }
  function mkColorRef(rgbv) {
    var obj = { cls: 'Color', f: {}, g: { kind: 'color', r: rgbv[0], g2: rgbv[1], b: rgbv[2], a: rgbv[3] === undefined ? 1 : rgbv[3], css: rgbCss(rgbv) } };
    return { t: 'ref', cls: 'Color', obj: obj };
  }

  function gfxStaticGet(clsName, name, ctx, line) {
    if (clsName === 'Color') {
      var low = String(name).toLowerCase();
      if (COLOR_TABLE[low]) { return mkColorRef(colorToRgb(mkStr(COLOR_TABLE[low]), ctx, line)); }
      throw rt(ctx, line, 'Color.' + name + ' gibt es nicht.', 'Verfügbare Farben: red, green, blue, yellow, orange, pink, magenta, cyan, white, black, gray, lightGray, darkGray, brown, purple.');
    }
    if (clsName === 'Alignment') {
      if (name === 'left' || name === 'center' || name === 'right') { return mkStr(name); }
      throw rt(ctx, line, 'Alignment.' + name + ' gibt es nicht.', 'Verfügbare Werte: Alignment.left, Alignment.center, Alignment.right.');
    }
    return null;
  }

  /* Statische Voreinstellungen: Shape.setDefaultVisibility, FilledShape.setDefault... */
  function gfxStaticCall(clsName, name, args, ctx, line) {
    if (clsName === 'Shape' && name === 'setDefaultVisibility' && args.length === 1) {
      GFX_DEFAULTS.visible = gfxBool(args, 0, 'setDefaultVisibility', ctx, line);
      return VOIDV;
    }
    if (clsName === 'FilledShape' && name === 'setDefaultFillColor' && args.length >= 1) {
      var fc = colorToRgb(args[0], ctx, line);
      GFX_DEFAULTS.fill = fc ? rgbCss(fc) : null;
      return VOIDV;
    }
    if (clsName === 'FilledShape' && name === 'setDefaultBorderColor' && args.length >= 1) {
      var bc = colorToRgb(args[0], ctx, line);
      GFX_DEFAULTS.border = bc ? rgbCss(bc) : null;
      return VOIDV;
    }
    if (clsName === 'FilledShape' && name === 'setDefaultBorderWidth' && args.length === 1) {
      GFX_DEFAULTS.borderWidth = gfxNum(args, 0, 'setDefaultBorderWidth', ctx, line);
      return VOIDV;
    }
    throw rt(ctx, line, clsName + '.' + name + ' gibt es nicht.', 'Verfügbar: Shape.setDefaultVisibility(...), FilledShape.setDefaultFillColor(...), FilledShape.setDefaultBorderColor(...), FilledShape.setDefaultBorderWidth(...).');
  }

  /* ---------- Fenster (DOM), einmalig angelegt, wiederverwendet ---------- */
  function gfxWindowFind() {
    if (typeof document === 'undefined' || !document.getElementById) { return null; }
    var el = document.getElementById('gfx-window');
    if (el && el.__gfxState) { return el.__gfxState; }
    return null;
  }
  function gfxEnsureWindow(vm) {
    var st = gfxWindowFind();
    if (st) { vm.gfx.windowState = st; return st; }
    if (typeof document === 'undefined' || !document.createElement || !document.body) { return null; }
    var el = document.createElement('div');
    el.id = 'gfx-window';
    el.className = 'gfx-window';
    el.setAttribute('tabindex', '0');
    var title = document.createElement('div');
    title.className = 'gfx-title';
    var label = document.createElement('span');
    label.className = 'gfx-label';
    label.textContent = 'Grafik-Fenster';
    var close = document.createElement('button');
    close.className = 'gfx-close';
    close.type = 'button';
    close.setAttribute('title', 'Grafik-Fenster schließen');
    close.textContent = String.fromCharCode(215);
    title.appendChild(label);
    title.appendChild(close);
    var canvas = document.createElement('canvas');
    canvas.className = 'gfx-canvas';
    canvas.width = 480;
    canvas.height = 360;
    var guiLayer = document.createElement('div');
    guiLayer.className = 'gfx-gui';
    el.appendChild(title);
    el.appendChild(canvas);
    el.appendChild(guiLayer);
    document.body.appendChild(el);
    st = { el: el, canvas: canvas, guiLayer: guiLayer, label: label, hidden: false };
    el.__gfxState = st;
    /* Ereignisse: Tastatur und Maus gehen an die Objekte des laufenden Programms */
    el.addEventListener('keydown', function (ev) { gfxDispatchKey(ev.key, true); if (ev.preventDefault && ev.key && ev.key.slice(0, 5) === 'Arrow') { ev.preventDefault(); } });
    el.addEventListener('keyup', function (ev) { gfxDispatchKey(ev.key, false); });
    canvas.addEventListener('mousedown', function (ev) { gfxMouse(ev, 'down'); });
    canvas.addEventListener('mouseup', function (ev) { gfxMouse(ev, 'up'); });
    canvas.addEventListener('mousemove', function (ev) { gfxMouse(ev, 'move'); });
    close.addEventListener('click', function () { gfxHideWindow(); });
    vm.gfx.windowState = st;
    return st;
  }
  function gfxHideWindow() {
    var st = gfxWindowFind();
    if (st) { st.hidden = true; if (st.el.style) { st.el.style.display = 'none'; } }
  }
  function gfxShowWindow(vm) {
    var st = vm.gfx.windowState || gfxEnsureWindow(vm);
    if (st) { st.hidden = false; if (st.el.style) { st.el.style.display = ''; } }
  }

  function gfxEnsureWorld(vm) {
    if (!vm.gfx.world) {
      vm.gfx.world = { kind: 'world', w: 800, h: 600, bg: '#ffffff', cursor: 'default' };
      vm.gfx.worldRef = { t: 'ref', cls: 'World', obj: { cls: 'World', f: {}, g: vm.gfx.world } };
    }
    gfxShowWindow(vm);
    return vm.gfx.world;
  }

  /* ---------- Figuren anlegen ---------- */
  function makeShape(kind, args, ctx, line) {
    var g = {
      kind: kind, x: 0, y: 0, visible: GFX_DEFAULTS.visible,
      fill: GFX_FILLED_KINDS[kind] ? GFX_DEFAULTS.fill : null,
      border: kind === 'line' || kind === 'turtle' ? '#000000' : GFX_DEFAULTS.border,
      borderWidth: kind === 'turtle' ? 2 : GFX_DEFAULTS.borderWidth,
      alpha: 1, rot: 0, scaleF: 1, mirrorX: false, mirrorY: false,
      dead: false, dom: null, ref: null
    };
    var i;
    if (kind === 'circle') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Circle', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Circle', ctx, line) : 0;
      g.r = args.length > 2 ? gfxNum(args, 2, 'Circle', ctx, line) : 10;
      if (g.r < 0) { throw rt(ctx, line, 'Der Radius von Circle darf nicht negativ sein.', 'new Circle(400, 300, 100) zeichnet einen Kreis mit Mittelpunkt (400, 300) und Radius 100.'); }
    } else if (kind === 'ellipse') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Ellipse', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Ellipse', ctx, line) : 0;
      g.rx = args.length > 2 ? gfxNum(args, 2, 'Ellipse', ctx, line) : 10;
      g.ry = args.length > 3 ? gfxNum(args, 3, 'Ellipse', ctx, line) : 10;
    } else if (kind === 'rectangle' || kind === 'rounded') {
      var rn = kind === 'rounded' ? 'RoundedRectangle' : 'Rectangle';
      g.x = args.length > 0 ? gfxNum(args, 0, rn, ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, rn, ctx, line) : 0;
      g.w = args.length > 2 ? gfxNum(args, 2, rn, ctx, line) : 100;
      g.h = args.length > 3 ? gfxNum(args, 3, rn, ctx, line) : 100;
      if (kind === 'rounded') { g.rad = args.length > 4 ? gfxNum(args, 4, rn, ctx, line) : 10; }
    } else if (kind === 'bitmap') {
      g.cols = Math.max(1, Math.floor(args.length > 0 ? gfxNum(args, 0, 'Bitmap', ctx, line) : 10));
      g.rows = Math.max(1, Math.floor(args.length > 1 ? gfxNum(args, 1, 'Bitmap', ctx, line) : 10));
      g.x = args.length > 2 ? gfxNum(args, 2, 'Bitmap', ctx, line) : 0;
      g.y = args.length > 3 ? gfxNum(args, 3, 'Bitmap', ctx, line) : 0;
      g.w = args.length > 4 ? gfxNum(args, 4, 'Bitmap', ctx, line) : 200;
      g.h = args.length > 5 ? gfxNum(args, 5, 'Bitmap', ctx, line) : 100;
      g.cells = [];
      for (i = 0; i < g.cols * g.rows; i++) { g.cells.push(null); }
      g.fill = null;
    } else if (kind === 'text') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Text', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Text', ctx, line) : 0;
      g.size = args.length > 2 ? gfxNum(args, 2, 'Text', ctx, line) : 20;
      g.text = args.length > 3 ? javaStr(args[3], ctx, line) : '';
      g.font = args.length > 4 ? gfxStr(args, 4, 'Text', ctx, line) : 'sans-serif';
      g.align = 'left';
    } else if (kind === 'triangle') {
      g.p1x = args.length > 0 ? gfxNum(args, 0, 'Triangle', ctx, line) : 0;
      g.p1y = args.length > 1 ? gfxNum(args, 1, 'Triangle', ctx, line) : 0;
      g.p2x = args.length > 2 ? gfxNum(args, 2, 'Triangle', ctx, line) : 0;
      g.p2y = args.length > 3 ? gfxNum(args, 3, 'Triangle', ctx, line) : 0;
      g.p3x = args.length > 4 ? gfxNum(args, 4, 'Triangle', ctx, line) : 0;
      g.p3y = args.length > 5 ? gfxNum(args, 5, 'Triangle', ctx, line) : 0;
    } else if (kind === 'line') {
      g.x1 = args.length > 0 ? gfxNum(args, 0, 'Line', ctx, line) : 0;
      g.y1 = args.length > 1 ? gfxNum(args, 1, 'Line', ctx, line) : 0;
      g.x2 = args.length > 2 ? gfxNum(args, 2, 'Line', ctx, line) : 100;
      g.y2 = args.length > 3 ? gfxNum(args, 3, 'Line', ctx, line) : 100;
      g.fill = null;
    } else if (kind === 'polygon') {
      g.closeAndFill = args.length > 0 ? gfxBool(args, 0, 'Polygon', ctx, line) : true;
      g.points = [];
      /* Punkte paarweise als x,y lesen; alternativ ein Array mit x/y-Werten */
      if (args.length === 2 && args[1].t === 'array') {
        var arr = args[1].arr;
        for (i = 0; i + 1 < arr.length; i += 2) {
          g.points.push([
            (arr[i].t === 'int' || arr[i].t === 'double') ? arr[i].v : 0,
            (arr[i + 1].t === 'int' || arr[i + 1].t === 'double') ? arr[i + 1].v : 0
          ]);
        }
      } else {
        for (i = 1; i + 1 < args.length; i += 2) {
          g.points.push([gfxNum(args, i, 'Polygon', ctx, line), gfxNum(args, i + 1, 'Polygon', ctx, line)]);
        }
      }
    } else if (kind === 'turtle') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Turtle', ctx, line) : 400;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Turtle', ctx, line) : 300;
      g.heading = 0;
      g.pen = true;
      g.path = [[g.x, g.y]];
      g.fill = null;
    } else if (kind === 'button') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Button', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Button', ctx, line) : 0;
      g.w = args.length > 2 ? gfxNum(args, 2, 'Button', ctx, line) : 120;
      g.h = args.length > 3 ? gfxNum(args, 3, 'Button', ctx, line) : 32;
      g.text = args.length > 4 ? javaStr(args[4], ctx, line) : 'Knopf';
    } else if (kind === 'textfield') {
      g.x = args.length > 0 ? gfxNum(args, 0, 'Textfield', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, 'Textfield', ctx, line) : 0;
      g.w = args.length > 2 ? gfxNum(args, 2, 'Textfield', ctx, line) : 140;
      g.h = args.length > 3 ? gfxNum(args, 3, 'Textfield', ctx, line) : 28;
      g.text = args.length > 4 ? javaStr(args[4], ctx, line) : '';
    } else if (kind === 'checkbox' || kind === 'radiobutton') {
      g.x = args.length > 0 ? gfxNum(args, 0, kind === 'checkbox' ? 'Checkbox' : 'Radiobutton', ctx, line) : 0;
      g.y = args.length > 1 ? gfxNum(args, 1, kind === 'checkbox' ? 'Checkbox' : 'Radiobutton', ctx, line) : 0;
      g.w = args.length > 2 ? gfxNum(args, 2, kind === 'checkbox' ? 'Checkbox' : 'Radiobutton', ctx, line) : 150;
      g.h = args.length > 3 ? gfxNum(args, 3, kind === 'checkbox' ? 'Checkbox' : 'Radiobutton', ctx, line) : 24;
      g.text = args.length > 4 ? javaStr(args[4], ctx, line) : '';
      g.checked = args.length > 5 ? gfxBool(args, 5, kind === 'checkbox' ? 'Checkbox' : 'Radiobutton', ctx, line) : false;
    } else if (kind === 'world') {
      g.w = args.length > 0 ? gfxNum(args, 0, 'World', ctx, line) : 800;
      g.h = args.length > 1 ? gfxNum(args, 1, 'World', ctx, line) : 600;
      if (g.w <= 0 || g.h <= 0) { throw rt(ctx, line, 'Die Größe des Grafik-Fensters muss positiv sein.', 'z. B. new World(800, 600)'); }
      g.bg = '#ffffff';
      g.cursor = 'default';
    }
    return g;
  }

  function gfxNew(clsName, args, ctx, line) {
    var vm = ctx.vm;
    var kind = GFX_KIND_OF[clsName];
    if (clsName === 'World') {
      var wst = makeShape('world', args, ctx, line);
      vm.gfx.world = wst;
      vm.gfx.worldRef = { t: 'ref', cls: 'World', obj: { cls: 'World', f: {}, g: wst } };
      gfxShowWindow(vm);
      gfxRedraw(vm);
      return vm.gfx.worldRef;
    }
    if (clsName === 'Color') {
      var r = args.length > 0 ? gfxNum(args, 0, 'new Color', ctx, line) : 0;
      var g2 = args.length > 1 ? gfxNum(args, 1, 'new Color', ctx, line) : 0;
      var b2 = args.length > 2 ? gfxNum(args, 2, 'new Color', ctx, line) : 0;
      var a2 = args.length > 3 ? gfxNum(args, 3, 'new Color', ctx, line) : 1;
      return mkColorRef([Math.max(0, Math.min(255, Math.floor(r))), Math.max(0, Math.min(255, Math.floor(g2))), Math.max(0, Math.min(255, Math.floor(b2))), Math.max(0, Math.min(1, a2))]);
    }
    if (clsName === 'Group') {
      var gobj = { cls: 'Group', f: {}, g: { kind: 'group', members: [], dead: false, ref: null } };
      vm.objects.push({ id: vm.objects.length + 1, cls: 'Group', obj: gobj });
      var gref = { t: 'ref', cls: 'Group', obj: gobj };
      gobj.g.ref = gobj;
      for (var gi = 0; gi < args.length; gi++) { gfxGroupAdd(gobj.g, args[gi], ctx, line); }
      return gref;
    }
    if (clsName === 'Actor') {
      var aobj = { cls: 'Actor', f: {}, g: { kind: 'actor', dead: false, ref: null } };
      vm.objects.push({ id: vm.objects.length + 1, cls: 'Actor', obj: aobj });
      aobj.g.ref = aobj;
      return { t: 'ref', cls: 'Actor', obj: aobj };
    }
    var obj = { cls: clsName, f: {}, g: makeShape(kind, args, ctx, line) };
    vm.objects.push({ id: vm.objects.length + 1, cls: clsName, obj: obj });
    obj.g.ref = obj;
    gfxEnsureWorld(vm);
    vm.gfx.shapes.push(obj.g);
    if (GFX_SHAPE_KINDS[kind] && (kind === 'button' || kind === 'textfield' || kind === 'checkbox' || kind === 'radiobutton')) { gfxMakeDom(obj.g, vm); }
    gfxRedraw(vm);
    return { t: 'ref', cls: clsName, obj: obj };
  }

  /* Grafik-Unterklasse (z. B. class Auto extends Rectangle): Figur anlegen */
  function gfxAttach(obj, baseName, args, vm, ctx, line) {
    var kind = GFX_KIND_OF[baseName];
    if (!kind || kind === 'world' || kind === 'color') {
      throw rt(ctx, line, 'Von ' + baseName + ' kann nicht geerbt werden.', 'Erlaubte Basisklassen: Actor, Circle, Ellipse, Rectangle, RoundedRectangle, Text, Triangle, Line, Polygon, Bitmap, Turtle, Group.');
    }
    if (kind === 'group') {
      obj.g = { kind: 'group', members: [], dead: false, ref: obj };
      return;
    }
    if (kind === 'actor') {
      obj.g = { kind: 'actor', dead: false, ref: obj };
      return;
    }
    obj.g = makeShape(kind, args, ctx, line);
    obj.g.ref = obj;
    gfxEnsureWorld(vm);
    vm.gfx.shapes.push(obj.g);
    if (kind === 'button' || kind === 'textfield' || kind === 'checkbox' || kind === 'radiobutton') { gfxMakeDom(obj.g, vm); }
    gfxRedraw(vm);
  }
  function gfxBaseOf(cls) {
    var c = cls;
    var guard = 0;
    while (c && guard < 30) {
      if (c.parent && GFX_CLASSES[c.parent] && RUN_CLASS_MAP[c.parent] === undefined) { return c.parent; }
      c = c.parent ? RUN_CLASS_MAP[c.parent] : null;
      guard++;
    }
    return null;
  }

  function gfxGroupAdd(g, val, ctx, line) {
    if (!val || val.t !== 'ref' || !val.obj || !val.obj.g || !GFX_SHAPE_KINDS[val.obj.g.kind]) {
      throw rt(ctx, line, 'add(...) braucht eine Figur (Circle, Rectangle usw.).', 'z. B. gruppe.add(rect);');
    }
    for (var i = 0; i < g.members.length; i++) { if (g.members[i] === val.obj.g) { return; } }
    g.members.push(val.obj.g);
  }

  /* ---------- GUI-Elemente als echte Knopf-/Eingabe-Elemente ---------- */
  function gfxMakeDom(g, vm) {
    if (typeof document === 'undefined' || !document.createElement) { return; }
    var st = vm.gfx.windowState || gfxEnsureWindow(vm);
    if (!st) { return; }
    var el = null;
    var selfRef = function () { return { t: 'ref', cls: g.ref ? g.ref.cls : 'Button', obj: g.ref }; };
    if (g.kind === 'button') {
      el = document.createElement('button');
      el.type = 'button';
      el.className = 'gfx-el gfx-btn-el';
      el.textContent = g.text;
      el.addEventListener('click', function () { gfxDispatch(g.ref, 'onChange', [mkStr(g.text)]); });
    } else if (g.kind === 'textfield') {
      el = document.createElement('input');
      el.type = 'text';
      el.className = 'gfx-el gfx-input-el';
      el.value = g.text;
      el.addEventListener('change', function () { g.text = el.value; gfxDispatch(g.ref, 'onChange', [mkStr(el.value)]); });
    } else if (g.kind === 'checkbox' || g.kind === 'radiobutton') {
      el = document.createElement('input');
      el.type = g.kind === 'checkbox' ? 'checkbox' : 'radio';
      el.className = 'gfx-el gfx-input-el';
      if (g.checked) { el.checked = true; }
      el.addEventListener('change', function () { g.checked = !!el.checked; gfxDispatch(g.ref, 'onChange', [mkStr(g.checked ? 'true' : 'false')]); });
    }
    if (!el) { return; }
    g.dom = el;
    st.guiLayer.appendChild(el);
  }

  /* ---------- Zeichnen ---------- */
  function gfxTransformOf(vm) {
    var w = vm.gfx.world;
    var st = vm.gfx.windowState;
    var cw = st && st.canvas ? st.canvas.width : 480;
    var ch = st && st.canvas ? st.canvas.height : 360;
    var sx = Math.min(cw / w.w, ch / w.h);
    return { s: sx, ox: (cw - w.w * sx) / 2, oy: (ch - w.h * sx) / 2 };
  }
  function gfxScaleBBox(bb, f) {
    var cx = bb.x + bb.w / 2;
    var cy = bb.y + bb.h / 2;
    return { x: cx - (bb.w / 2) * f, y: cy - (bb.h / 2) * f, w: bb.w * f, h: bb.h * f };
  }
  function shapeBBox(g) {
    var f = g.scaleF || 1;
    var bb;
    if (g.kind === 'circle') { bb = { x: g.x - g.r, y: g.y - g.r, w: 2 * g.r, h: 2 * g.r }; }
    else if (g.kind === 'ellipse') { bb = { x: g.x - g.rx, y: g.y - g.ry, w: 2 * g.rx, h: 2 * g.ry }; }
    else if (g.kind === 'rectangle' || g.kind === 'rounded' || g.kind === 'bitmap' || g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') { bb = { x: g.x, y: g.y, w: g.w || 10, h: g.h || 10 }; }
    else if (g.kind === 'text') { bb = { x: g.x, y: g.y, w: (g.size || 20) * 0.6 * (g.text ? g.text.length : 0), h: g.size || 20 }; }
    else if (g.kind === 'triangle') {
      bb = { x: Math.min(g.p1x, g.p2x, g.p3x), y: Math.min(g.p1y, g.p2y, g.p3y), w: Math.abs(Math.max(g.p1x, g.p2x, g.p3x) - Math.min(g.p1x, g.p2x, g.p3x)), h: Math.abs(Math.max(g.p1y, g.p2y, g.p3y) - Math.min(g.p1y, g.p2y, g.p3y)) };
    }
    else if (g.kind === 'line') { bb = { x: Math.min(g.x1, g.x2), y: Math.min(g.y1, g.y2), w: Math.abs(g.x2 - g.x1), h: Math.abs(g.y2 - g.y1) }; }
    else if (g.kind === 'polygon' && g.points) {
      var xs = [], ys = [], i;
      for (i = 0; i < g.points.length; i++) { xs.push(g.points[i][0]); ys.push(g.points[i][1]); }
      bb = { x: Math.min.apply(null, xs), y: Math.min.apply(null, ys), w: Math.max.apply(null, xs) - Math.min.apply(null, xs), h: Math.max.apply(null, ys) - Math.min.apply(null, ys) };
    }
    else if (g.kind === 'turtle' && g.path) {
      var xs2 = [], ys2 = [], j;
      for (j = 0; j < g.path.length; j++) { xs2.push(g.path[j][0]); ys2.push(g.path[j][1]); }
      bb = { x: Math.min.apply(null, xs2) - 10, y: Math.min.apply(null, ys2) - 10, w: Math.max.apply(null, xs2) - Math.min.apply(null, xs2) + 20, h: Math.max.apply(null, ys2) - Math.min.apply(null, ys2) + 20 };
    }
    else { bb = { x: g.x, y: g.y, w: 0, h: 0 }; }
    if (f !== 1 && bb.w + bb.h > 0) { return gfxScaleBBox(bb, f); }
    return bb;
  }
  function gfxRedraw(vm) {
    if (!vm || !vm.gfx || !vm.gfx.world) { return; }
    var st = vm.gfx.windowState || gfxEnsureWindow(vm);
    if (!st || !st.canvas || !st.canvas.getContext) { return; }
    var ctx2d = st.canvas.getContext('2d');
    if (!ctx2d || !ctx2d.fillRect) { return; }
    var w = vm.gfx.world;
    var tr = gfxTransformOf(vm);
    ctx2d.clearRect(0, 0, st.canvas.width, st.canvas.height);
    ctx2d.fillStyle = '#eef1f6';
    ctx2d.fillRect(0, 0, st.canvas.width, st.canvas.height);
    ctx2d.fillStyle = w.bg;
    ctx2d.fillRect(tr.ox, tr.oy, w.w * tr.s, w.h * tr.s);
    /* dezentes Raster, damit das XY-Feld sichtbar ist */
    ctx2d.strokeStyle = '#e2e8f0';
    ctx2d.lineWidth = 1;
    var gx;
    for (gx = 50; gx < w.w; gx += 50) {
      ctx2d.beginPath();
      ctx2d.moveTo(tr.ox + gx * tr.s, tr.oy);
      ctx2d.lineTo(tr.ox + gx * tr.s, tr.oy + w.h * tr.s);
      ctx2d.stroke();
    }
    var gy;
    for (gy = 50; gy < w.h; gy += 50) {
      ctx2d.beginPath();
      ctx2d.moveTo(tr.ox, tr.oy + gy * tr.s);
      ctx2d.lineTo(tr.ox + w.w * tr.s, tr.oy + gy * tr.s);
      ctx2d.stroke();
    }
    var i;
    for (i = 0; i < vm.gfx.shapes.length; i++) { gfxDrawShape(ctx2d, vm.gfx.shapes[i], tr); }
    /* GUI-Elemente positionieren */
    for (i = 0; i < vm.gfx.shapes.length; i++) {
      var sh = vm.gfx.shapes[i];
      if (sh.dom && sh.dom.style) {
        sh.dom.style.left = (tr.ox + sh.x * tr.s) + 'px';
        sh.dom.style.top = (tr.oy + sh.y * tr.s) + 'px';
        sh.dom.style.width = (sh.w * tr.s) + 'px';
        sh.dom.style.height = (sh.h * tr.s) + 'px';
      }
    }
  }
  function gfxDrawShape(ctx2d, g, tr) {
    if (!g.visible || g.dead) { return; }
    if (g.dom) { return; }
    if (!GFX_SHAPE_KINDS[g.kind]) { return; }
    var f = g.scaleF || 1;
    ctx2d.save();
    ctx2d.globalAlpha = g.alpha === undefined ? 1 : g.alpha;
    var mx = g.mirrorX ? -1 : 1;
    var my = g.mirrorY ? -1 : 1;
    var cx, cy;
    if (g.kind === 'circle' || g.kind === 'ellipse') { cx = g.x; cy = g.y; }
    else if (g.kind === 'rectangle' || g.kind === 'rounded' || g.kind === 'bitmap' || g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') { cx = g.x + g.w / 2; cy = g.y + g.h / 2; }
    else if (g.kind === 'text') { cx = g.x; cy = g.y; }
    else if (g.kind === 'triangle') { cx = (g.p1x + g.p2x + g.p3x) / 3; cy = (g.p1y + g.p2y + g.p3y) / 3; }
    else if (g.kind === 'line') { cx = (g.x1 + g.x2) / 2; cy = (g.y1 + g.y2) / 2; }
    else { var bb = shapeBBox(g); cx = bb.x + bb.w / 2; cy = bb.y + bb.h / 2; }
    var px = tr.ox + cx * tr.s;
    var py = tr.oy + cy * tr.s;
    ctx2d.translate(px, py);
    if (g.rot) { ctx2d.rotate(g.rot * Math.PI / 180); }
    ctx2d.scale(tr.s * f * mx, tr.s * f * my);
    ctx2d.beginPath();
    var doFill = g.fill !== null && g.fill !== undefined;
    var doStroke = g.border !== null && g.border !== undefined;
    if (g.kind === 'circle' || g.kind === 'ellipse') {
      var rx = g.kind === 'circle' ? g.r : g.rx;
      var ry = g.kind === 'circle' ? g.r : g.ry;
      if (ctx2d.ellipse) { ctx2d.ellipse(0, 0, rx, ry, 0, 0, Math.PI * 2); }
      else { ctx2d.arc(0, 0, rx, 0, Math.PI * 2); }
    } else if (g.kind === 'rectangle' || g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') {
      ctx2d.rect(-g.w / 2, -g.h / 2, g.w, g.h);
    } else if (g.kind === 'rounded') {
      var rw = g.w / 2, rh = g.h / 2, rr = Math.min(g.rad || 0, rw, rh);
      ctx2d.moveTo(-rw + rr, -rh);
      ctx2d.lineTo(rw - rr, -rh);
      ctx2d.quadraticCurveTo(rw, -rh, rw, -rh + rr);
      ctx2d.lineTo(rw, rh - rr);
      ctx2d.quadraticCurveTo(rw, rh, rw - rr, rh);
      ctx2d.lineTo(-rw + rr, rh);
      ctx2d.quadraticCurveTo(-rw, rh, -rw, rh - rr);
      ctx2d.lineTo(-rw, -rh + rr);
      ctx2d.quadraticCurveTo(-rw, -rh, -rw + rr, -rh);
    } else if (g.kind === 'text') {
      ctx2d.font = (g.size) + 'px ' + g.font;
      ctx2d.textAlign = g.align || 'left';
      ctx2d.textBaseline = 'top';
      doFill = true;
      ctx2d.fillStyle = g.fill || '#000000';
      ctx2d.fillText(g.text, 0, 0);
      ctx2d.restore();
      return;
    } else if (g.kind === 'triangle') {
      ctx2d.moveTo((g.p1x - cx), (g.p1y - cy));
      ctx2d.lineTo((g.p2x - cx), (g.p2y - cy));
      ctx2d.lineTo((g.p3x - cx), (g.p3y - cy));
      ctx2d.closePath();
    } else if (g.kind === 'line') {
      ctx2d.moveTo((g.x1 - cx), (g.y1 - cy));
      ctx2d.lineTo((g.x2 - cx), (g.y2 - cy));
      doFill = false;
    } else if (g.kind === 'polygon' && g.points && g.points.length > 0) {
      var pi;
      ctx2d.moveTo((g.points[0][0] - cx), (g.points[0][1] - cy));
      for (pi = 1; pi < g.points.length; pi++) { ctx2d.lineTo((g.points[pi][0] - cx), (g.points[pi][1] - cy)); }
      if (g.closeAndFill) { ctx2d.closePath(); } else { doFill = false; }
    } else if (g.kind === 'bitmap') {
      var cw2 = g.w / g.cols, ch2 = g.h / g.rows;
      var bi, bj;
      for (bi = 0; bi < g.cols; bi++) {
        for (bj = 0; bj < g.rows; bj++) {
          var col = g.cells[bj * g.cols + bi];
          if (col) { ctx2d.fillStyle = col; ctx2d.fillRect(-g.w / 2 + bi * cw2, -g.h / 2 + bj * ch2, cw2 + 0.5, ch2 + 0.5); }
        }
      }
      ctx2d.restore();
      return;
    } else if (g.kind === 'turtle') {
      if (g.path.length > 1) {
        ctx2d.strokeStyle = g.border || '#000000';
        ctx2d.lineWidth = g.borderWidth || 2;
        ctx2d.beginPath();
        ctx2d.moveTo((g.path[0][0] - cx), (g.path[0][1] - cy));
        for (pi = 1; pi < g.path.length; pi++) { ctx2d.lineTo((g.path[pi][0] - cx), (g.path[pi][1] - cy)); }
        ctx2d.stroke();
      }
      var hr = g.heading * Math.PI / 180;
      ctx2d.translate((g.x - cx), (g.y - cy));
      ctx2d.rotate(hr);
      ctx2d.beginPath();
      ctx2d.moveTo(10, 0);
      ctx2d.lineTo(-6, 6);
      ctx2d.lineTo(-6, -6);
      ctx2d.closePath();
      ctx2d.fillStyle = g.border || '#000000';
      ctx2d.fill();
      ctx2d.restore();
      return;
    }
    if (doFill) { ctx2d.fillStyle = g.fill; ctx2d.fill(); }
    if (doStroke) {
      ctx2d.strokeStyle = g.border;
      ctx2d.lineWidth = g.borderWidth || 1;
      ctx2d.stroke();
    }
    ctx2d.restore();
  }

  /* ---------- Ereignisse an die Objekte des laufenden Programms ---------- */
  function gfxDispatch(obj, name, args) {
    if (!obj || obj.g === undefined || obj.g === null) { return; }
    var cls = RUN_CLASS_MAP[obj.cls];
    if (!cls) { return; }
    var found = findMethod(cls, name, args.length);
    if (!found || found.m.isStatic) { return; }
    try {
      invokeMethod(found.cls, found.m, { t: 'ref', cls: obj.cls, obj: obj }, args, RUN_VM);
    } catch (e) {
      if (e && e.jerr) {
        if (GFX_ONERROR) { GFX_ONERROR(e.jerr); }
        gfxStopLoop();
      } else if (!(e && e.ctrl)) { throw e; }
    }
  }
  function gfxActors() {
    var out = [];
    if (!RUN_VM || !RUN_VM.objects) { return out; }
    var i;
    for (i = 0; i < RUN_VM.objects.length; i++) {
      var o = RUN_VM.objects[i];
      if (o.obj && o.obj.g && !o.obj.g.dead && o.obj.g.kind !== 'world' && o.obj.g.kind !== 'color' && RUN_CLASS_MAP[o.cls]) { out.push(o.obj); }
    }
    return out;
  }
  function gfxTick() {
    var actors = gfxActors();
    var i;
    for (i = 0; i < actors.length; i++) { gfxDispatch(actors[i], 'act', []); }
    if (RUN_VM) { gfxRedraw(RUN_VM); }
  }
  function gfxStartLoop() {
    if (GFX_TIMER || GFX_TEST_MODE) { return; }
    if (typeof setInterval !== 'function') { return; }
    GFX_TIMER = setInterval(gfxTick, 33);
  }
  function gfxStopLoop() {
    if (GFX_TIMER && typeof clearInterval === 'function') { clearInterval(GFX_TIMER); }
    GFX_TIMER = null;
  }
  var GFX_KEY_ALIAS = Object.create(null);
  GFX_KEY_ALIAS.links = 'ArrowLeft'; GFX_KEY_ALIAS.rechts = 'ArrowRight';
  GFX_KEY_ALIAS.oben = 'ArrowUp'; GFX_KEY_ALIAS.unten = 'ArrowDown';
  GFX_KEY_ALIAS.left = 'ArrowLeft'; GFX_KEY_ALIAS.right = 'ArrowRight';
  GFX_KEY_ALIAS.up = 'ArrowUp'; GFX_KEY_ALIAS.down = 'ArrowDown';
  function gfxKeyName(key) {
    var k = String(key);
    if (GFX_KEY_ALIAS[k]) { return GFX_KEY_ALIAS[k]; }
    return k;
  }
  function gfxDispatchKey(key, isDown) {
    var k = gfxKeyName(key);
    GFX_KEYS[k] = isDown === true;
    var actors = gfxActors();
    var i;
    if (isDown) {
      for (i = 0; i < actors.length; i++) { gfxDispatch(actors[i], 'onKeyDown', [mkStr(k)]); }
    } else {
      for (i = 0; i < actors.length; i++) { gfxDispatch(actors[i], 'onKeyUp', [mkStr(k)]); }
      if (k.length === 1) {
        for (i = 0; i < actors.length; i++) { gfxDispatch(actors[i], 'onKeyTyped', [mkStr(k)]); }
      }
    }
    if (RUN_VM) { gfxRedraw(RUN_VM); }
  }
  function gfxMouse(ev, phase) {
    if (!RUN_VM || !RUN_VM.gfx || !RUN_VM.gfx.world) { return; }
    var st = RUN_VM.gfx.windowState;
    if (!st || !st.canvas) { return; }
    var rect = st.canvas.getBoundingClientRect ? st.canvas.getBoundingClientRect() : null;
    if (!rect) { return; }
    var tr = gfxTransformOf(RUN_VM);
    var wx = ((ev.clientX - rect.left) - tr.ox) / tr.s;
    var wy = ((ev.clientY - rect.top) - tr.oy) / tr.s;
    var hit = null;
    var i;
    for (i = RUN_VM.gfx.shapes.length - 1; i >= 0; i--) {
      var g = RUN_VM.gfx.shapes[i];
      if (!g.visible || g.dead || !GFX_SHAPE_KINDS[g.kind]) { continue; }
      if (g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') { continue; }
      var bb = shapeBBox(g);
      if (wx >= bb.x && wx <= bb.x + bb.w && wy >= bb.y && wy <= bb.y + bb.h) { hit = g; break; }
    }
    var button = ev.button === 2 ? 2 : 0;
    if (phase === 'down') {
      if (hit && hit.ref) { gfxDispatch(hit.ref, 'onMouseDown', [mkInt(button)]); }
      if (!RUN_VM.gfx.hover && hit) { RUN_VM.gfx.hover = hit; }
    } else if (phase === 'up') {
      if (hit && hit.ref) { gfxDispatch(hit.ref, 'onMouseUp', [mkInt(button)]); }
    } else if (phase === 'move') {
      var prev = RUN_VM.gfx.hover || null;
      if (hit !== prev) {
        if (prev && prev.ref) { gfxDispatch(prev.ref, 'onMouseLeave', []); }
        if (hit && hit.ref) { gfxDispatch(hit.ref, 'onMouseEnter', []); }
        RUN_VM.gfx.hover = hit;
      }
    }
  }
  function gfxDestroy(g, vm) {
    g.dead = true;
    var i;
    for (i = vm.gfx.shapes.length - 1; i >= 0; i--) {
      if (vm.gfx.shapes[i] === g) { vm.gfx.shapes.splice(i, 1); }
    }
    for (i = 0; i < vm.gfx.shapes.length; i++) {
      var sh = vm.gfx.shapes[i];
      if (sh.kind === 'group' && sh.members) {
        for (var j = sh.members.length - 1; j >= 0; j--) { if (sh.members[j] === g) { sh.members.splice(j, 1); } }
      }
    }
    if (g.dom && g.dom.parentNode) { g.dom.parentNode.removeChild(g.dom); }
  }
  function gfxBoxesOverlap(a, b) {
    return a.x <= b.x + b.w && b.x <= a.x + a.w && a.y <= b.y + b.h && b.y <= a.y + a.h;
  }

  /* ---------- Methoden der Grafikobjekte ---------- */
  function gfxMethodCall(recv, name, args, ctx, line) {
    var g = recv.obj.g;
    var vm = ctx.vm;
    if (!g) { throw rt(ctx, line, 'Die Klasse ' + recv.cls + ' hat keine Methode ' + name + '.', ''); }
    if (g.dead) { throw rt(ctx, line, 'Dieses Objekt wurde schon mit destroy() zerstört.', 'Lege ein neues Objekt an, z. B. mit new.'); }

    if (g.kind === 'world') {
      if (name === 'getWidth' && args.length === 0) { return mkDbl(g.w); }
      if (name === 'getHeight' && args.length === 0) { return mkDbl(g.h); }
      if (name === 'setBackgroundColor') {
        var bg = colorToRgb(args[0], ctx, line);
        g.bg = rgbCss(bg) || '#ffffff';
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'setCursor') {
        g.cursor = gfxStr(args, 0, 'setCursor', ctx, line);
        var st = vm.gfx.windowState;
        if (st && st.canvas && st.canvas.style) { st.canvas.style.cursor = g.cursor; }
        return VOIDV;
      }
      throw rt(ctx, line, 'World hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Verfügbar: getWidth, getHeight, setBackgroundColor, setCursor.');
    }
    if (g.kind === 'group') {
      if (name === 'add' && args.length === 1) { gfxGroupAdd(g, args[0], ctx, line); gfxRedraw(vm); return VOIDV; }
      if (name === 'move' && args.length === 2) {
        var dx = gfxNum(args, 0, 'move', ctx, line), dy = gfxNum(args, 1, 'move', ctx, line);
        for (var mi = 0; mi < g.members.length; mi++) { gfxMoveShape(g.members[mi], dx, dy); }
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'rotate' && args.length >= 1) {
        var ang = gfxNum(args, 0, 'rotate', ctx, line);
        var pivot = g.members.length > 0 ? gfxGroupCenter(g) : { x: 0, y: 0 };
        if (args.length >= 3) { pivot = { x: gfxNum(args, 1, 'rotate', ctx, line), y: gfxNum(args, 2, 'rotate', ctx, line) }; }
        for (var ri = 0; ri < g.members.length; ri++) { gfxRotateShape(g.members[ri], ang, pivot); }
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'scale' && args.length >= 1) {
        var fac = gfxNum(args, 0, 'scale', ctx, line);
        var c2 = g.members.length > 0 ? gfxGroupCenter(g) : { x: 0, y: 0 };
        if (args.length >= 3) { c2 = { x: gfxNum(args, 1, 'scale', ctx, line), y: gfxNum(args, 2, 'scale', ctx, line) }; }
        for (var si = 0; si < g.members.length; si++) { gfxScaleShape(g.members[si], fac, c2); }
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'getCollidingShapes' && args.length === 1) {
        var other = args[0];
        if (!other || other.t !== 'ref' || !other.obj || !other.obj.g) { throw rt(ctx, line, 'getCollidingShapes braucht eine Figur.', 'z. B. gruppe.getCollidingShapes(ball);'); }
        var ob = shapeBBox(other.obj.g);
        var hits = [];
        for (var hi = 0; hi < g.members.length; hi++) {
          if (gfxBoxesOverlap(shapeBBox(g.members[hi]), ob) && g.members[hi].ref) {
            hits.push({ t: 'ref', cls: g.members[hi].ref.cls, obj: g.members[hi].ref });
          }
        }
        return { t: 'array', elem: 'Shape', arr: hits };
      }
      if (name === 'getCollisionPairs' && args.length >= 1) {
        var og = args[0];
        if (!og || og.t !== 'ref' || !og.obj || !og.obj.g || og.obj.g.kind !== 'group') { throw rt(ctx, line, 'getCollisionPairs braucht eine andere Gruppe.', 'z. B. schuesse.getCollisionPairs(asteroiden);'); }
        var pairs = [];
        var a, b;
        for (a = 0; a < g.members.length; a++) {
          for (b = 0; b < og.obj.g.members.length; b++) {
            if (gfxBoxesOverlap(shapeBBox(g.members[a]), shapeBBox(og.obj.g.members[b]))) {
              pairs.push({ t: 'array', elem: 'Shape', arr: [
                { t: 'ref', cls: g.members[a].ref.cls, obj: g.members[a].ref },
                { t: 'ref', cls: og.obj.g.members[b].ref.cls, obj: og.obj.g.members[b].ref }
              ] });
              if (args.length > 1 && gfxBool(args, 1, 'getCollisionPairs', ctx, line)) { a = g.members.length; break; }
            }
          }
        }
        return { t: 'array', elem: 'Shape[]', arr: pairs };
      }
      if (name === 'destroy' && args.length === 0) {
        for (var di = 0; di < g.members.length; di++) { gfxDestroy(g.members[di], vm); }
        g.members = [];
        g.dead = true;
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'getWorld' && args.length === 0) { return vm.gfx.worldRef; }
      if (name === 'setVisible' && args.length === 1) { g.visible = gfxBool(args, 0, 'setVisible', ctx, line); gfxRedraw(vm); return VOIDV; }
      if (name === 'setAlpha' && args.length === 1) { g.alpha = gfxNum(args, 0, 'setAlpha', ctx, line); gfxRedraw(vm); return VOIDV; }
      throw rt(ctx, line, 'Group hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Verfügbar: add, move, rotate, scale, setVisible, setAlpha, getCollidingShapes, getCollisionPairs, destroy, getWorld.');
    }

    if (GFX_SHAPE_KINDS[g.kind]) {
      if (name === 'move' && args.length === 2) {
        gfxMoveShape(g, gfxNum(args, 0, 'move', ctx, line), gfxNum(args, 1, 'move', ctx, line));
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'rotate' && args.length >= 1) {
        var ang2 = gfxNum(args, 0, 'rotate', ctx, line);
        var pv = args.length >= 3 ? { x: gfxNum(args, 1, 'rotate', ctx, line), y: gfxNum(args, 2, 'rotate', ctx, line) } : null;
        gfxRotateShape(g, ang2, pv);
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'scale' && args.length >= 1) {
        var fac2 = gfxNum(args, 0, 'scale', ctx, line);
        var c3 = args.length >= 3 ? { x: gfxNum(args, 1, 'scale', ctx, line), y: gfxNum(args, 2, 'scale', ctx, line) } : null;
        gfxScaleShape(g, fac2, c3);
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'mirrorX' && args.length === 0) { g.mirrorX = !g.mirrorX; gfxRedraw(vm); return VOIDV; }
      if (name === 'mirrorY' && args.length === 0) { g.mirrorY = !g.mirrorY; gfxRedraw(vm); return VOIDV; }
      if (name === 'setVisible' && args.length === 1) {
        g.visible = gfxBool(args, 0, 'setVisible', ctx, line);
        if (g.dom && g.dom.style) { g.dom.style.visibility = g.visible ? 'visible' : 'hidden'; }
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'setAlpha' && args.length === 1) { g.alpha = gfxNum(args, 0, 'setAlpha', ctx, line); gfxRedraw(vm); return VOIDV; }
      if (name === 'bringToFront' && args.length === 0) {
        for (var bfi = 0; bfi < vm.gfx.shapes.length; bfi++) { if (vm.gfx.shapes[bfi] === g) { vm.gfx.shapes.splice(bfi, 1); break; } }
        vm.gfx.shapes.push(g);
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'sendToBack' && args.length === 0) {
        for (var sbi = 0; sbi < vm.gfx.shapes.length; sbi++) { if (vm.gfx.shapes[sbi] === g) { vm.gfx.shapes.splice(sbi, 1); break; } }
        vm.gfx.shapes.unshift(g);
        gfxRedraw(vm);
        return VOIDV;
      }
      if (name === 'destroy' && args.length === 0) { gfxDestroy(g, vm); gfxRedraw(vm); return VOIDV; }
      if (name === 'getWorld' && args.length === 0) { return vm.gfx.worldRef; }
      if (GFX_FILLED_KINDS[g.kind] || g.kind === 'line' || g.kind === 'turtle' || g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') {
        if (name === 'setFillColor') {
          if (args.length === 0 || args.length > 2) { throw rt(ctx, line, 'setFillColor braucht eine Farbe (und optional einen Alpha-Wert).', 'z. B. c.setFillColor(Color.red); oder c.setFillColor("#ff8000", 0.5);'); }
          var rgbv = colorToRgb(args[0], ctx, line);
          if (rgbv && args.length === 2) { rgbv[3] = gfxNum(args, 1, 'setFillColor', ctx, line); }
          g.fill = rgbCss(rgbv);
          if (g.dom && g.dom.style) { g.dom.style.background = g.fill || 'transparent'; }
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'setBorderColor') {
          var brgb = colorToRgb(args[0], ctx, line);
          g.border = rgbCss(brgb);
          if (g.dom && g.dom.style) { g.dom.style.borderColor = g.border || 'transparent'; g.dom.style.borderStyle = 'solid'; }
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'setBorderWidth' && args.length === 1) {
          g.borderWidth = gfxNum(args, 0, 'setBorderWidth', ctx, line);
          if (g.borderWidth < 0) { throw rt(ctx, line, 'Die Randbreite darf nicht negativ sein.', ''); }
          if (g.dom && g.dom.style) { g.dom.style.borderWidth = g.borderWidth + 'px'; g.dom.style.borderStyle = 'solid'; }
          gfxRedraw(vm);
          return VOIDV;
        }
      }
      /* Besonderheiten je Figur */
      if (g.kind === 'text') {
        if (name === 'setText' && args.length === 1) { g.text = javaStr(args[0], ctx, line); gfxRedraw(vm); return VOIDV; }
        if (name === 'setAlignment' && args.length === 1) {
          var al = args[0].t === 'String' ? args[0].v : '';
          al = al.toLowerCase();
          if (al !== 'left' && al !== 'center' && al !== 'right') { throw rt(ctx, line, 'setAlignment kennt nur left, center und right.', 'z. B. t.setAlignment(Alignment.center);'); }
          g.align = al;
          gfxRedraw(vm);
          return VOIDV;
        }
      }
      if (g.kind === 'line') {
        if (name === 'setPoint' && args.length === 3) {
          var idx = gfxNum(args, 2, 'setPoint', ctx, line);
          if (idx !== 0 && idx !== 1) { throw rt(ctx, line, 'setPoint kennt nur Index 0 oder 1.', '0 ist der erste, 1 der zweite Endpunkt.'); }
          if (idx === 0) { g.x1 = gfxNum(args, 0, 'setPoint', ctx, line); g.y1 = gfxNum(args, 1, 'setPoint', ctx, line); }
          else { g.x2 = gfxNum(args, 0, 'setPoint', ctx, line); g.y2 = gfxNum(args, 1, 'setPoint', ctx, line); }
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'setPoints' && args.length === 4) {
          g.x1 = gfxNum(args, 0, 'setPoints', ctx, line); g.y1 = gfxNum(args, 1, 'setPoints', ctx, line);
          g.x2 = gfxNum(args, 2, 'setPoints', ctx, line); g.y2 = gfxNum(args, 3, 'setPoints', ctx, line);
          gfxRedraw(vm);
          return VOIDV;
        }
      }
      if (g.kind === 'polygon') {
        if (name === 'addPoint' && args.length === 2) {
          g.points.push([gfxNum(args, 0, 'addPoint', ctx, line), gfxNum(args, 1, 'addPoint', ctx, line)]);
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'addPoints' && args.length === 1) {
          if (!args[0] || args[0].t !== 'array') { throw rt(ctx, line, 'addPoints braucht ein Array mit x/y-Werten.', 'z. B. p.addPoints(new double[]{ ... });'); }
          var pa = args[0].arr;
          for (var pai = 0; pai + 1 < pa.length; pai += 2) {
            g.points.push([(pa[pai].t === 'int' || pa[pai].t === 'double') ? pa[pai].v : 0, (pa[pai + 1].t === 'int' || pa[pai + 1].t === 'double') ? pa[pai + 1].v : 0]);
          }
          gfxRedraw(vm);
          return VOIDV;
        }
      }
      /* GUI-Elemente: Knopf, Eingabefeld, Ankreuzfeld, Auswahlknopf */
      if (g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') {
        if (name === 'setText' && args.length === 1) {
          g.text = javaStr(args[0], ctx, line);
          if (g.dom) {
            if (g.kind === 'button') { g.dom.textContent = g.text; }
            else { g.dom.value = g.text; }
          }
          return VOIDV;
        }
        if (name === 'getText' && args.length === 0) { return mkStr(g.text || ''); }
        if (name === 'setFontSize' && args.length === 1) {
          g.size = gfxNum(args, 0, 'setFontSize', ctx, line);
          if (g.dom && g.dom.style) { g.dom.style.fontSize = g.size + 'px'; }
          return VOIDV;
        }
        if (g.kind === 'checkbox' && name === 'setChecked' && args.length === 1) {
          g.checked = gfxBool(args, 0, 'setChecked', ctx, line);
          if (g.dom) { g.dom.checked = g.checked; }
          return VOIDV;
        }
        if (g.kind === 'checkbox' && name === 'isChecked' && args.length === 0) { return mkBool(g.checked === true); }
        if (g.kind === 'radiobutton' && name === 'setSelected' && args.length === 1) {
          g.checked = gfxBool(args, 0, 'setSelected', ctx, line);
          if (g.dom) { g.dom.checked = g.checked; }
          return VOIDV;
        }
        if (g.kind === 'radiobutton' && name === 'isSelected' && args.length === 0) { return mkBool(g.checked === true); }
      }
      if (g.kind === 'bitmap') {
        if (name === 'setColor' && args.length === 3) {
          var bx = Math.floor(gfxNum(args, 0, 'setColor', ctx, line));
          var by = Math.floor(gfxNum(args, 1, 'setColor', ctx, line));
          if (bx < 0 || by < 0 || bx >= g.cols || by >= g.rows) { throw rt(ctx, line, 'setColor: Der Punkt (' + bx + ', ' + by + ') liegt ausserhalb der Bitmap (' + g.cols + ' x ' + g.rows + ').', 'Gültig sind x: 0..' + (g.cols - 1) + ' und y: 0..' + (g.rows - 1) + '.'); }
          g.cells[by * g.cols + bx] = rgbCss(colorToRgb(args[2], ctx, line));
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'getColorAsInt' && args.length === 2) {
          var qx = Math.floor(gfxNum(args, 0, 'getColorAsInt', ctx, line));
          var qy = Math.floor(gfxNum(args, 1, 'getColorAsInt', ctx, line));
          if (qx < 0 || qy < 0 || qx >= g.cols || qy >= g.rows) { throw rt(ctx, line, 'getColorAsInt: Der Punkt liegt ausserhalb der Bitmap.', ''); }
          var css = g.cells[qy * g.cols + qx];
          if (!css) { return mkInt(0xffffff); }
          var mm = /rgb\(([0-9]+), ([0-9]+), ([0-9]+)\)/.exec(css);
          if (!mm) { return mkInt(0xffffff); }
          return mkInt((parseInt(mm[1], 10) << 16) + (parseInt(mm[2], 10) << 8) + parseInt(mm[3], 10));
        }
      }
      if (g.kind === 'turtle') {
        if (name === 'forward' && args.length === 1) {
          var len = gfxNum(args, 0, 'forward', ctx, line);
          var rad = g.heading * Math.PI / 180;
          g.x = g.x + Math.cos(rad) * len;
          g.y = g.y + Math.sin(rad) * len;
          if (g.pen) { g.path.push([g.x, g.y]); }
          gfxRedraw(vm);
          return VOIDV;
        }
        if (name === 'turn' && args.length === 1) { g.heading = g.heading + gfxNum(args, 0, 'turn', ctx, line); gfxRedraw(vm); return VOIDV; }
        if (name === 'penUp' && args.length === 0) { g.pen = false; return VOIDV; }
        if (name === 'penDown' && args.length === 0) {
          if (!g.pen) { g.pen = true; g.path.push([g.x, g.y]); }
          return VOIDV;
        }
        if (name === 'getX' && args.length === 0) { return mkDbl(g.x); }
        if (name === 'getY' && args.length === 0) { return mkDbl(g.y); }
      }
      /* Positionen abfragen (praaktisch für Spiele) */
      if (name === 'getX' && args.length === 0) { return mkDbl(g.x); }
      if (name === 'getY' && args.length === 0) { return mkDbl(g.y); }
    }

    /* Actor-Grundausstattung: act und Ereignis-Platzhalter sind immer da */
    if (name === 'act' || name === 'onMouseDown' || name === 'onMouseUp' || name === 'onMouseEnter' || name === 'onMouseLeave' || name === 'onKeyDown' || name === 'onKeyUp' || name === 'onKeyTyped') { return VOIDV; }
    if (name === 'isKeyDown' && args.length === 1) { return mkBool(GFX_KEYS[gfxKeyName(gfxStr(args, 0, 'isKeyDown', ctx, line))] === true); }
    if (name === 'isKeyUp' && args.length === 1) { return mkBool(GFX_KEYS[gfxKeyName(gfxStr(args, 0, 'isKeyUp', ctx, line))] !== true); }
    throw rt(ctx, line, 'Die Klasse ' + recv.cls + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Schau in die API-Hilfe (Tab neben der Ausgabe): Dort stehen alle Methoden der Grafik-Klassen.');
  }

  function gfxShapeCenter(g) {
    var bb = shapeBBox(g);
    return { x: bb.x + bb.w / 2, y: bb.y + bb.h / 2 };
  }
  function gfxGroupCenter(g) {
    var i;
    var minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (i = 0; i < g.members.length; i++) {
      var bb = shapeBBox(g.members[i]);
      if (bb.x < minx) { minx = bb.x; }
      if (bb.y < miny) { miny = bb.y; }
      if (bb.x + bb.w > maxx) { maxx = bb.x + bb.w; }
      if (bb.y + bb.h > maxy) { maxy = bb.y + bb.h; }
    }
    if (minx === Infinity) { return { x: 0, y: 0 }; }
    return { x: (minx + maxx) / 2, y: (miny + maxy) / 2 };
  }
  function gfxMoveShape(g, dx, dy) {
    if (g.kind === 'circle' || g.kind === 'ellipse' || g.kind === 'rectangle' || g.kind === 'rounded' || g.kind === 'bitmap' || g.kind === 'text' || g.kind === 'turtle' || g.kind === 'button' || g.kind === 'textfield' || g.kind === 'checkbox' || g.kind === 'radiobutton') {
      g.x = (g.x || 0) + dx;
      g.y = (g.y || 0) + dy;
      if (g.kind === 'turtle' && g.pen) { g.path.push([g.x, g.y]); }
      return;
    }
    if (g.kind === 'triangle') {
      g.p1x += dx; g.p1y += dy; g.p2x += dx; g.p2y += dy; g.p3x += dx; g.p3y += dy;
      return;
    }
    if (g.kind === 'line') { g.x1 += dx; g.y1 += dy; g.x2 += dx; g.y2 += dy; return; }
    if (g.kind === 'polygon' && g.points) {
      for (var i = 0; i < g.points.length; i++) { g.points[i][0] += dx; g.points[i][1] += dy; }
    }
  }
  function gfxRotateShape(g, ang, pivot) {
    g.rot = (g.rot || 0) + ang;
    if (pivot) {
      var c = gfxShapeCenter(g);
      var rad = ang * Math.PI / 180;
      var nx = pivot.x + (c.x - pivot.x) * Math.cos(rad) - (c.y - pivot.y) * Math.sin(rad);
      var ny = pivot.y + (c.x - pivot.x) * Math.sin(rad) + (c.y - pivot.y) * Math.cos(rad);
      gfxMoveShape(g, nx - c.x, ny - c.y);
    }
  }
  function gfxScaleShape(g, fac, pivot) {
    if (fac <= 0) { throw rt(null, 0, 'Der Streckungsfaktor muss größer als 0 sein.', ''); }
    g.scaleF = (g.scaleF || 1) * fac;
    if (pivot) {
      var c = gfxShapeCenter(g);
      var nx = pivot.x + (c.x - pivot.x) * fac;
      var ny = pivot.y + (c.y - pivot.y) * fac;
      gfxMoveShape(g, nx - c.x, ny - c.y);
    }
  }

  /* Aufräumen zwischen zwei Läufen */
  function gfxResetRun() {
    gfxStopLoop();
    if (RUN_VM && RUN_VM.gfx) {
      var i;
      for (i = 0; i < RUN_VM.gfx.shapes.length; i++) {
        RUN_VM.gfx.shapes[i].dead = true;
        if (RUN_VM.gfx.shapes[i].dom && RUN_VM.gfx.shapes[i].dom.parentNode) { RUN_VM.gfx.shapes[i].dom.parentNode.removeChild(RUN_VM.gfx.shapes[i].dom); }
      }
      RUN_VM.gfx.shapes = [];
      if (RUN_VM.gfx.world || RUN_VM.gfx.windowState) { gfxHideWindow(); }
      RUN_VM.gfx.world = null;
      RUN_VM.gfx.worldRef = null;
      RUN_VM.gfx.hover = null;
    }
  }
  function gfxShapeSummary(g) {
    var o = { kind: g.kind, dead: !!g.dead, visible: g.visible };
    var k;
    for (k in g) {
      if (Object.prototype.hasOwnProperty.call(g, k)) {
        var t = typeof g[k];
        if (t === 'number' || t === 'boolean' || t === 'string') { o[k] = g[k]; }
        else if (g[k] === null && (k === 'fill' || k === 'border')) { o[k] = null; }
      }
    }
    if (g.points) { o.pointsCount = g.points.length; }
    if (g.path) { o.pathCount = g.path.length; }
    if (g.cells) { o.cellsCount = g.cells.length; }
    return o;
  }
  /* Momentaufnahme aller Figuren (für Tests und Einzelschritt-Anzeige) */
  function gfxSnapshot() {
    var out = [];
    if (!RUN_VM || !RUN_VM.gfx) { return out; }
    var i;
    for (i = 0; i < RUN_VM.gfx.shapes.length; i++) { out.push(gfxShapeSummary(RUN_VM.gfx.shapes[i])); }
    return out;
  }

  function trimRightWs(s) {
    var n = s.length;
    while (n > 0 && (s.charAt(n - 1) === ' ' || s.charAt(n - 1) === String.fromCharCode(9))) { n--; }
    return s.slice(0, n);
  }
  /* ---------- Automatisches Formatieren (Einrücken/Ausrücken) ----------
   * Zustandsmaschine über Strings, Zeichen und Kommentare: Innerhalb
   * von "..." und '...' sowie hinter // und in /* ... * / Klammern zählen nicht. */
  function indentOf(n) {
    var s = '';
    var i2;
    for (i2 = 0; i2 < n; i2++) { s = s + '    '; }
    return s;
  }
  function formatJavaSource(src) {
    var lines = String(src).split(NL);
    var out = [];
    var depth = 0;
    var inBlock = false;
    var i2;
    for (i2 = 0; i2 < lines.length; i2++) {
      var line = trimRightWs(lines[i2]);
      if (line === '') { out.push(''); continue; }
      if (inBlock) {
        out.push(indentOf(depth) + line);
        if (line.indexOf('*/') !== -1) { inBlock = false; }
        continue;
      }
      var t = line.replace(/^[ \t]+/, '');
      /* führende schließende Klammern gehören eine Ebene tiefer */
      var leadClose = 0;
      var k = 0;
      while (k < t.length && (t.charAt(k) === '}' || t.charAt(k) === ')')) {
        if (t.charAt(k) === '}') { leadClose++; }
        k++;
      }
      out.push(indentOf(Math.max(0, depth - leadClose)) + t);
      /* Netto-Klammern zählen, Strings/Zeichen/Kommentare auslassen */
      var inStr = false, inChr = false, inLineC = false, inBlk = false;
      var j2;
      for (j2 = 0; j2 < t.length; j2++) {
        var c = t.charAt(j2);
        if (inLineC) { break; }
        if (inBlk) {
          if (c === '*' && t.charAt(j2 + 1) === '/') { inBlk = false; j2++; }
          continue;
        }
        if (inStr) {
          if (c === String.fromCharCode(92)) { j2++; }
          else if (c === '"') { inStr = false; }
          continue;
        }
        if (inChr) {
          if (c === String.fromCharCode(92)) { j2++; }
          else if (c === "'") { inChr = false; }
          continue;
        }
        if (c === '"') { inStr = true; continue; }
        if (c === "'") { inChr = true; continue; }
        if (c === '/' && t.charAt(j2 + 1) === '/') { inLineC = true; break; }
        if (c === '/' && t.charAt(j2 + 1) === '*') { inBlk = true; j2++; continue; }
        if (c === '{') { depth++; }
        if (c === '}') { depth--; if (depth < 0) { depth = 0; } }
      }
      if (inBlk) { inBlock = true; }
    }
    return out.join(NL);
  }

  /* ================= UI-Anbindung (nur im Browser aktiv) ================= */
  function wireUi() {
    if (typeof document === 'undefined') { return; }
    var tabs = document.querySelectorAll('[data-file-tab]');
    var panes = document.querySelectorAll('[data-file-pane]');
    var fileRows = document.querySelectorAll('.ide-file[data-file-row]');
    var i;
    /* Datei im Editor anzeigen: das hidden-Attribut steuert die Sichtbarkeit der Panes,
     * active nur für die Optik von Tab und Zeile. */
    function selectFile(id) {
      var j;
      for (j = 0; j < tabs.length; j++) { tabs[j].classList.remove('active'); }
      for (j = 0; j < fileRows.length; j++) {
        if (fileRows[j].getAttribute('data-file-row') === id) { fileRows[j].classList.add('active'); }
        else { fileRows[j].classList.remove('active'); }
      }
      for (j = 0; j < panes.length; j++) {
        var hit = panes[j].getAttribute('data-file-pane') === id;
        if (hit) { panes[j].removeAttribute('hidden'); }
        else { panes[j].setAttribute('hidden', 'hidden'); }
        var area = panes[j].querySelector('textarea');
        if (hit && area && typeof area.focus === 'function') { area.focus(); }
      }
      for (j = 0; j < tabs.length; j++) {
        if (tabs[j].getAttribute('data-file-tab') === id) { tabs[j].classList.add('active'); }
      }
    }
    for (i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function (ev) {
        selectFile(ev.currentTarget.getAttribute('data-file-tab'));
      });
    }
    var runBtn = document.getElementById('run-java');
    if (!runBtn) { return; }
    var consoleEl = document.getElementById('java-console');
    var errBox = document.getElementById('java-errors');
    var replLog = document.getElementById('java-repl-log');
    var replIn = document.getElementById('java-repl-in');
    var replHint = document.getElementById('java-repl-hint');
    var acBox = document.getElementById('java-repl-ac');
    var objBox = document.getElementById('java-objects');
    var objCount = document.getElementById('java-obj-badge');
    function badge() { return document.getElementById('java-error-badge'); }

    var session = null;

    /* Konsolen-Tabs rechts: Ausgabe / Objektkonsole / Objekte / Fehlerkonsole */
    var ctabs = document.querySelectorAll('[data-ctab]');
    var cpanes = document.querySelectorAll('[data-cpane]');
    function showPane(name) {
      var i2;
      for (i2 = 0; i2 < ctabs.length; i2++) {
        if (ctabs[i2].getAttribute('data-ctab') === name) { ctabs[i2].classList.add('active'); }
        else { ctabs[i2].classList.remove('active'); }
      }
      for (i2 = 0; i2 < cpanes.length; i2++) {
        if (cpanes[i2].getAttribute('data-cpane') === name) { cpanes[i2].removeAttribute('hidden'); }
        else { cpanes[i2].setAttribute('hidden', 'hidden'); }
      }
    }
    var ti;
    for (ti = 0; ti < ctabs.length; ti++) {
      (function (btn) {
        btn.addEventListener('click', function () { showPane(btn.getAttribute('data-ctab')); });
      })(ctabs[ti]);
    }

    /* Fehlerkarte mit Sprung: Klick zeigt die Stelle direkt im Editor der
     * richtigen Datei (Datei wechseln, Cursor an die Zeile, Zeile markieren). */
    function paneElByName(name) {
      var j;
      for (j = 0; j < panes.length; j++) {
        if (panes[j].getAttribute('data-file-name') === name) { return panes[j]; }
      }
      return null;
    }
    function lineStartOffset(text, line) {
      var pos = 0;
      var li = 1;
      while (li < line && pos < text.length) {
        var nx = text.indexOf(NL, pos);
        if (nx === -1) { return text.length; }
        pos = nx + 1;
        li++;
      }
      return pos;
    }
    function jumpToError(er) {
      var paneEl = er.file ? paneElByName(er.file) : null;
      if (!paneEl) { return; }
      selectFile(paneEl.getAttribute('data-file-pane'));
      var ta = paneEl.querySelector('textarea');
      if (ta && er.line) {
        /* Cursor an den Anfang der Fehlerzeile setzen */
        var off = lineStartOffset(String(ta.value || ''), er.line);
        if (ta.setSelectionRange) { ta.setSelectionRange(off, off); }
        /* Zeile im Editor sichtbar machen (Gutter-Zelle als Anhaltspunkt) */
        try {
          var rec = gutterByFile(er.file);
          var cell = rec && rec.cells[er.line - 1];
          if (cell && typeof cell.offsetTop === 'number' && isFinite(cell.offsetTop) && cell.offsetTop > 0) {
            var top = Math.max(0, cell.offsetTop - 48);
            if (ta.scrollTop !== undefined) { ta.scrollTop = top; }
            if (rec.box && rec.box.scrollTop !== undefined) { rec.box.scrollTop = top; }
          }
        } catch (escroll) { /* aeltere Browser: ohne Scroll einfach weiter */ }
        markErrLines([er]);
      }
    }
    function renderErrCard(box, er) {
      var card = document.createElement('div');
      card.className = 'err-card' + (er.kind === 'stderr' ? ' err-stderr' : '');
      var head = document.createElement('div');
      head.className = 'err-head';
      var kindLabel = er.kind === 'stderr' ? 'System.err' : (er.kind === 'syntax' ? 'Syntaxfehler' : (er.kind === 'compile' ? 'Übersetzungsproblem' : 'Laufzeitfehler'));
      var where = er.file ? (er.file + (er.line ? ':' + er.line : '')) : '';
      head.textContent = kindLabel + (where ? ' (' + where + ')' : '');
      card.appendChild(head);
      var msg = document.createElement('div');
      msg.className = 'err-msg';
      msg.textContent = er.message;
      card.appendChild(msg);
      if (er.hint) {
        var hint = document.createElement('div');
        hint.className = 'err-hint';
        hint.textContent = 'Tipp: ' + er.hint;
        card.appendChild(hint);
      }
      /* Klick auf die Karte springt zur Stelle, wenn die Datei im Projekt liegt */
      if (er.file && paneElByName(er.file)) {
        card.className = card.className + ' err-jump';
        card.setAttribute('title', 'Klick: zeigt die Stelle im Editor');
        card.addEventListener('click', function () { jumpToError(er); });
      }
      box.appendChild(card);
    }

    var errMode = 'run'; /* 'run' = Ergebnis eines Laufs, 'live' = Stand beim Tippen */
    var errHasCards = false;
    function showErrors(list, mode) {
      if (!errBox) { return; }
      errMode = mode || 'run';
      errHasCards = list.length > 0;
      errBox.innerHTML = '';
      if (list.length === 0) {
        errBox.innerHTML = '<div class="err-card err-ok">' + (errMode === 'live'
          ? 'Keine Fehler im Code gefunden – klicke ▶ Ausführen, wenn du starten willst.'
          : 'Alles fehlerfrei durchgelaufen.') + '</div>';
        return;
      }
      for (var h = 0; h < list.length; h++) { renderErrCard(errBox, list[h]); }
    }

    /* Live-Prüfung beim Tippen: Die Fehlerkonsole passt sich sofort an den
     * Code an (wie das Klassendiagramm), ohne einen Lauf zu starten.
     * Alte Fehlermeldungen mit verschobenen Zeilennummern verschwinden so
     * von selbst; der grüne Erfolg eines Laufs bleibt stehen. */
    var liveErrTimer = null;
    function updateLiveErrors() {
      if (!errBox) { return; }
      var comp;
      try { comp = compileAll(collectFiles()); } catch (e) { comp = null; }
      if (!comp) { return; }
      var list = comp.errors || [];
      var b = badge();
      if (list.length > 0) {
        showErrors(list, 'live');
        if (b) { b.textContent = String(list.length); }
        clearLineMarks();
        markErrLines(list);
      } else if (errMode === 'live' || errHasCards) {
        /* Vorher standen Fehler: Erfolg melden und Markierungen lösen */
        showErrors([], 'live');
        if (b) { b.textContent = '0'; }
        clearLineMarks();
      }
    }
    function scheduleLiveErrors() {
      if (!errBox) { return; }
      if (liveErrTimer) { clearTimeout(liveErrTimer); }
      liveErrTimer = setTimeout(updateLiveErrors, 500);
    }

    /* ---------- Zeilennummern neben dem Editor (Gutter) ----------
     * Für jede Datei ein schmales Feld mit den Nummern der Zeilen. Fehlerzeilen
     * werden rot markiert, die Zeile des Einzelschritts gelb. */
    var stepInfo = document.getElementById('java-step-info');
    var stepBtn = document.getElementById('java-step');
    var restBtn = document.getElementById('java-run-rest');
    var fmtBtn = document.getElementById('java-format');
    var gutterMap = Object.create(null);
    function setupGutters() {
      var gi;
      for (gi = 0; gi < panes.length; gi++) {
        (function (pane) {
          var ta = pane.querySelector('textarea');
          if (!ta || !pane.getAttribute('data-file-pane') || !ta.parentNode || !ta.parentNode.getAttribute) { return; }
          if (!ta.parentNode.getAttribute('data-hl')) { return; }
          var box = document.createElement('div');
          box.className = 'ln-gutter';
          var inner = document.createElement('div');
          inner.className = 'ln-inner';
          box.appendChild(inner);
          ta.parentNode.appendChild(box);
          if (ta.parentNode.classList && ta.parentNode.classList.add) { ta.parentNode.classList.add('has-gutter'); }
          var rec = { ta: ta, box: box, inner: inner, cells: [], count: 0, file: pane.getAttribute('data-file-name') };
          gutterMap[pane.getAttribute('data-file-pane')] = rec;
          updateGutter(rec);
          ta.addEventListener('input', function () { updateGutter(rec); });
          ta.addEventListener('scroll', function () { box.scrollTop = ta.scrollTop; });
        })(panes[gi]);
      }
    }
    function updateGutter(rec) {
      var lines = rec.ta.value.split(NL).length;
      if (lines < 1) { lines = 1; }
      if (lines === rec.count) { return; }
      rec.inner.innerHTML = '';
      rec.cells = [];
      var li;
      for (li = 1; li <= lines; li++) {
        var cell = document.createElement('div');
        cell.className = 'ln-cell';
        cell.textContent = String(li);
        rec.inner.appendChild(cell);
        rec.cells.push(cell);
      }
      rec.count = lines;
    }
    function gutterByFile(fname) {
      var key;
      for (key in gutterMap) {
        if (Object.prototype.hasOwnProperty.call(gutterMap, key) && gutterMap[key].file === fname) { return gutterMap[key]; }
      }
      return null;
    }
    function clearLineMarks() {
      var key;
      for (key in gutterMap) {
        if (!Object.prototype.hasOwnProperty.call(gutterMap, key)) { continue; }
        var rec = gutterMap[key];
        var i2;
        for (i2 = 0; i2 < rec.cells.length; i2++) { rec.cells[i2].className = 'ln-cell'; }
      }
      if (stepInfo) { stepInfo.textContent = ''; }
    }
    function markErrLines(errs) {
      var h2;
      for (h2 = 0; h2 < errs.length; h2++) {
        var er = errs[h2];
        if (!er.file || !er.line) { continue; }
        var rec = gutterByFile(er.file);
        if (rec && rec.cells[er.line - 1]) { rec.cells[er.line - 1].className = 'ln-cell ln-err'; }
      }
    }
    function markCurLine(file, line) {
      var rec = gutterByFile(file);
      if (rec && rec.cells[line - 1]) { rec.cells[line - 1].className = 'ln-cell ln-cur'; }
    }

    /* ---------- Editor-Hilfen: Tab, Einrücken, Auto-Einzug ---------- */
    function fireInput(ta) {
      var ev = null;
      if (typeof document !== 'undefined' && document.createEvent) {
        ev = document.createEvent('Event');
        if (ev && ev.initEvent) { ev.initEvent('input', false, false); }
      }
      if (ev && ta.dispatchEvent) { ta.dispatchEvent(ev); return; }
      if (ta.dispatch) { ta.dispatch('input'); }
    }
    function setRange(ta, text, from, to) {
      ta.value = text;
      fireInput(ta);
    }
    function insertAt(ta, ins, caretAt) {
      var s = ta.selectionStart;
      var epos = ta.selectionEnd;
      var text = ta.value;
      setRange(ta, text.slice(0, s) + ins + text.slice(epos), null, null);
      var np = (typeof caretAt === 'number') ? caretAt : s + ins.length;
      if (ta.setSelectionRange) { ta.setSelectionRange(np, np); }
    }
    function lineBounds(ta, from, to) {
      var text = ta.value;
      var ls = text.lastIndexOf(NL, from - 1) + 1;
      var le = text.indexOf(NL, to);
      if (le === -1) { le = text.length; }
      return { ls: ls, le: le, text: text };
    }
    function insertTab(ta) {
      var s = ta.selectionStart;
      var epos = ta.selectionEnd;
      if (s !== epos) { indentSelection(ta); return; }
      insertAt(ta, '    ');
    }
    function indentSelection(ta) {
      var b = lineBounds(ta, ta.selectionStart, ta.selectionEnd);
      var seg = b.text.slice(b.ls, b.le);
      if (seg === '') { return; }
      var seg2 = '    ' + seg.split(NL).join('    ' + NL);
      setRange(ta, b.text.slice(0, b.ls) + seg2 + b.text.slice(b.le), null, null);
      if (ta.setSelectionRange) { ta.setSelectionRange(b.ls, b.ls + seg2.length); }
    }
    function stripIndent(line) {
      var n = 0;
      while (n < line.length && (line.charAt(n) === ' ' || line.charAt(n) === String.fromCharCode(9))) { n++; }
      return line.slice(Math.min(n, 4));
    }
    function outdentSelection(ta) {
      var b = lineBounds(ta, ta.selectionStart, ta.selectionEnd);
      var seg = b.text.slice(b.ls, b.le);
      var lines = seg.split(NL);
      var o;
      for (o = 0; o < lines.length; o++) { lines[o] = stripIndent(lines[o]); }
      var seg2 = lines.join(NL);
      setRange(ta, b.text.slice(0, b.ls) + seg2 + b.text.slice(b.le), null, null);
      var shrink = seg.length - seg2.length;
      var end = Math.max(b.ls, ta.selectionEnd - shrink);
      if (ta.setSelectionRange) { ta.setSelectionRange(Math.max(b.ls, ta.selectionStart - shrink), end); }
    }
    function currentIndent(line) {
      var n = 0;
      while (n < line.length && (line.charAt(n) === ' ' || line.charAt(n) === String.fromCharCode(9))) { n++; }
      return line.slice(0, n);
    }
    function enterNewline(ta) {
      var s = ta.selectionStart;
      var epos = ta.selectionEnd;
      var b = lineBounds(ta, s, s);
      var line = b.text.slice(b.ls, s);
      var ind = currentIndent(line);
      var trimmed = trimRightWs(line);
      var add = ind;
      if (trimmed.charAt(trimmed.length - 1) === '{') { add = add + '    '; }
      insertAt(ta, NL + add, s + 1 + add.length);
    }
    function typeCloseBrace(ta) {
      var s = ta.selectionStart;
      var b = lineBounds(ta, s, ta.selectionEnd);
      /* Bereits vorhandene schließende Klammer überspringen statt verdoppeln */
      if (ta.value.charAt(s) === '}') {
        if (ta.setSelectionRange) { ta.setSelectionRange(s + 1, s + 1); }
        return;
      }
      var before = b.text.slice(b.ls, s);
      var onlyWs = true;
      var q;
      for (q = 0; q < before.length; q++) {
        if (before.charAt(q) !== ' ' && before.charAt(q) !== String.fromCharCode(9)) { onlyWs = false; break; }
      }
      if (onlyWs && before.length >= 4) {
        /* Automatisch gesetzten Einzug (4 Spaces) wieder zurücknehmen */
        var cut = 4;
        setRange(ta, b.text.slice(0, s - cut) + '}' + b.text.slice(ta.selectionEnd), null, null);
        if (ta.setSelectionRange) { ta.setSelectionRange(s - cut + 1, s - cut + 1); }
        return;
      }
      insertAt(ta, '}');
    }

    /* ---------- Klammer-Automatik: ( [ { schließen sich sofort ----------
     * Der Cursor steht zwischen dem Paar, damit schneller getippt werden kann.
     * Eine { am Zeilenende öffnet gleich einen eingerückten Block mit
     * schließender Klammer darunter - Klammerfehler werden so verhindert. */
    function wrapSelection(ta, open, close) {
      var s = ta.selectionStart;
      var epos = ta.selectionEnd;
      var text = ta.value;
      if (s !== epos) {
        var sel = text.slice(s, epos);
        setRange(ta, text.slice(0, s) + open + sel + close + text.slice(epos), null, null);
        if (ta.setSelectionRange) { ta.setSelectionRange(s + 1, s + 1 + sel.length); }
        return;
      }
      setRange(ta, text.slice(0, s) + open + close + text.slice(s), null, null);
      if (ta.setSelectionRange) { ta.setSelectionRange(s + 1, s + 1); }
    }
    function typeOpenBracket(ta, open) {
      wrapSelection(ta, open, open === '(' ? ')' : ']');
    }
    function typeOpenBrace(ta) {
      var s = ta.selectionStart;
      var epos = ta.selectionEnd;
      var b = lineBounds(ta, s, s);
      var before = b.text.slice(b.ls, s);
      var afterOnLine = b.text.slice(s, b.le);
      var rest = afterOnLine.replace(/ /g, '').replace(/\t/g, '');
      var trimmed = trimRightWs(before);
      /* Auswahl, mitten in der Zeile oder leere Zeile: nur paarweise schließen */
      if (s !== epos || trimmed === '' || rest !== '') {
        wrapSelection(ta, '{', '}');
        return;
      }
      /* { am Ende einer Zeile öffnet den Block: Cursor in eine neue Zeile
       * mit Einzug, die schließende Klammer steht gleich mit darunter. */
      var ind = currentIndent(before);
      var pre = ' ';
      var lastCh = before.charAt(before.length - 1);
      if (lastCh === ' ' || lastCh === String.fromCharCode(9)) { pre = ''; }
      var ins = pre + '{' + NL + ind + '    ' + NL + ind + '}';
      setRange(ta, b.text.slice(0, s) + ins + b.text.slice(s), null, null);
      var np = s + pre.length + 1 + NL.length + ind.length + 4;
      if (ta.setSelectionRange) { ta.setSelectionRange(np, np); }
    }
    function typeOverClose(ta, close) {
      var s = ta.selectionStart;
      /* Bereits vorhandene schließende Klammer überspringen statt verdoppeln */
      if (ta.value.charAt(s) === close) {
        if (ta.setSelectionRange) { ta.setSelectionRange(s + 1, s + 1); }
        return;
      }
      insertAt(ta, close);
    }

    function formatAllFiles() {
      var changed = 0;
      var fi;
      for (fi = 0; fi < panes.length; fi++) {
        var ta = panes[fi].querySelector('textarea');
        if (!ta || !panes[fi].getAttribute('data-file-pane')) { continue; }
        var neu = formatJavaSource(ta.value);
        if (neu !== ta.value) {
          ta.value = neu;
          fireInput(ta);
          changed++;
        }
      }
      return changed;
    }
    if (fmtBtn) {
      fmtBtn.addEventListener('click', function () {
        var n = formatAllFiles();
        if (stepInfo) { stepInfo.textContent = n > 0 ? 'Code formatiert (' + n + ' Datei(en) angepasst).' : 'Code war schon sauber eingerückt.'; }
      });
    }

    /* ---------- Einzelschritt: Anweisung für Anweisung ---------- */
    var stepCount = 0;
    var lastPaused = false;
    function showResult(res) {
      session = res.session && !res.paused ? res.session : null;
      if (consoleEl) {
        if (res.output !== '') { consoleEl.textContent = res.output; }
        else if (res.paused) { consoleEl.textContent = '(bis zu diesem Schritt: noch keine Ausgabe)'; }
        else if (res.ok && !res.ranMain) { consoleEl.textContent = 'Kein main nötig: Erzeuge dir Objekte in der Objektkonsole (Tab oben).'; }
        else if (res.ok) { consoleEl.textContent = '(keine Ausgabe)'; }
        else { consoleEl.textContent = ''; }
      }
      showErrors(res.errors);
      var b = badge();
      if (b) { b.textContent = res.errors.length === 0 ? '0' : String(res.errors.length); }
      renderObjects(res.paused ? res.objects : null);
      updateReplHint();
      markErrLines(res.errors);
      if (res.paused) { markCurLine(res.pauseFile, res.pauseLine); }
    }
    function startGfxLoop() {
      GFX_ONERROR = function (jerr) {
        gfxStopLoop();
        if (errBox) {
          errBox.innerHTML = '';
          renderErrCard(errBox, jerr);
        }
        var b = badge();
        if (b) { b.textContent = '1'; }
        if (stepInfo) { stepInfo.textContent = 'Das Programm wurde angehalten: Fehler im laufenden Grafik-Fenster.'; }
      };
      gfxStartLoop();
    }
    function afterRun(res) {
      renderClasses();
      if (!res.ok) {
        gfxStopLoop();
        return;
      }
      if (res.paused) {
        showPane('out');
        return;
      }
      if (res.hasGfx) { startGfxLoop(); }
      if (!res.ranMain && res.output === '') { showPane('repl'); }
      else { showPane('out'); }
    }
    function internalError(e) {
      if (consoleEl) { consoleEl.textContent = ''; }
      if (errBox) { errBox.innerHTML = '<div class="err-card">Interner Fehler der Simulation: ' + escapeHtml(e && e.message ? e.message : String(e)) + '</div>'; }
    }
    function doRun() {
      clearLineMarks();
      stepCount = 0;
      lastPaused = false;
      var res;
      try {
        res = run(collectFiles());
      } catch (e) { internalError(e); return; }
      stepCount = res.leafSteps || 0;
      showResult(res);
      if (stepInfo && res.ok && !res.paused) { stepInfo.textContent = ''; }
      afterRun(res);
    }
    function doStep() {
      clearLineMarks();
      /* Nach einem kompletten Lauf vorn anfangen, nach einer Pause weiterzählen */
      if (!lastPaused) { stepCount = 0; }
      var res;
      try {
        res = run(collectFiles(), { stepLimit: stepCount + 1 });
      } catch (e) { internalError(e); return; }
      stepCount = res.leafSteps || 0;
      lastPaused = res.paused === true;
      showResult(res);
      if (!res.ok) {
        gfxStopLoop();
        if (stepInfo) { stepInfo.textContent = 'Schritt nicht möglich: Der Code enthält Fehler.'; }
        return;
      }
      if (res.paused) {
        if (stepInfo) { stepInfo.textContent = 'Schritt ' + stepCount + ' ausgeführt - als Nächstes Zeile ' + res.pauseLine + (res.pauseFile ? ' (' + res.pauseFile + ')' : '') + '. Klick für den nächsten Schritt.'; }
        showPane('out');
        renderClasses();
      } else {
        if (stepInfo) { stepInfo.textContent = 'Schritt ' + stepCount + ' ausgeführt - das Programm ist damit komplett durchgelaufen.'; }
        afterRun(res);
      }
    }
    function doRest() {
      clearLineMarks();
      lastPaused = false;
      var res;
      try {
        res = run(collectFiles());
      } catch (e) { internalError(e); return; }
      stepCount = res.leafSteps || 0;
      showResult(res);
      if (stepInfo) { stepInfo.textContent = res.ok ? 'Programm bis zum Ende ausgeführt (' + stepCount + ' Schritte).' : ''; }
      afterRun(res);
    }
    if (stepBtn) { stepBtn.addEventListener('click', doStep); }
    if (restBtn) { restBtn.addEventListener('click', doRest); }
    setupGutters();

    /* Objektdiagramm: alle erzeugten Objekte mit ihren Attributwerten.
     * forceList: Stand aus einem Einzelschritt (ohne laufende Session). */
    function renderObjects(forceList) {
      if (!objBox) { return; }
      var list = forceList || (session ? session.objects() : []);
      if (objCount) { objCount.textContent = String(list.length); }
      objBox.innerHTML = '';
      if (list.length === 0) {
        objBox.appendChild(document.createTextNode('Noch keine Objekte. Erzeuge eines in der Objektkonsole, z. B. Auto a = new Auto("BMW");'));
        return;
      }
      for (var i2 = 0; i2 < list.length; i2++) {
        var o = list[i2];
        var box = document.createElement('div');
        box.className = 'obj-box';
        var head = document.createElement('div');
        head.className = 'obj-head';
        head.textContent = (o.name ? o.name + ' : ' : '') + o.cls + '  (Objekt ' + o.id + ')';
        box.appendChild(head);
        for (var j2 = 0; j2 < o.fields.length; j2++) {
          var row = document.createElement('div');
          row.className = 'obj-field';
          row.textContent = o.fields[j2].name + ' = ' + o.fields[j2].text;
          box.appendChild(row);
        }
        objBox.appendChild(box);
      }
    }

    function replLine(input, res) {
      if (!replLog) { return; }
      var wrap = document.createElement('div');
      wrap.className = 'repl-line';
      var inLine = document.createElement('div');
      inLine.className = 'repl-in-line';
      inLine.textContent = '> ' + input;
      wrap.appendChild(inLine);
      if (res.output !== '') {
        var out = document.createElement('pre');
        out.className = 'repl-out';
        out.textContent = res.output;
        wrap.appendChild(out);
      }
      if (res.result !== null) {
        var rline = document.createElement('div');
        rline.className = 'repl-res';
        rline.textContent = '= ' + res.result + '   (' + res.resultType + ')';
        wrap.appendChild(rline);
      }
      for (var i3 = 0; i3 < res.errors.length; i3++) {
        var card = document.createElement('div');
        card.className = 'err-card';
        card.textContent = res.errors[i3].message + (res.errors[i3].hint ? '  Tipp: ' + res.errors[i3].hint : '');
        wrap.appendChild(card);
      }
      replLog.appendChild(wrap);
      replLog.scrollTop = replLog.scrollHeight;
    }

    function updateReplHint() {
      if (!replHint) { return; }
      if (!session) {
        replHint.textContent = 'Erst ▶ Ausführen klicken, dann kannst du hier Objekte erzeugen und Methoden aufrufen.';
        return;
      }
      var names = session.classNames();
      if (names.length === 0) { replHint.textContent = 'Dieses Projekt enthält noch keine Klasse.'; return; }
      replHint.textContent = 'Klassen: ' + names.join(', ') + '. Beispiel: ' + names[0] + ' a = new ' + names[0] + '(...); danach a.methode();';
    }

    runBtn.addEventListener('click', doRun);

    /* Klassendiagramm direkt in der IDE (Idee von BlueJ): live aus dem Code gelesen,
     * ohne das Programm auszuführen. Parse-Fehler stören nicht: Der lesbare Stand
     * wird gezeigt. */
    var classBox = document.getElementById('java-classes');
    function collectFiles() {
      var files = {};
      var panes3 = document.querySelectorAll('[data-file-pane]');
      var k;
      for (k = 0; k < panes3.length; k++) {
        var nm = panes3[k].getAttribute('data-file-name');
        var ta = panes3[k].querySelector('textarea');
        if (nm && ta) { files[nm] = ta.value; }
      }
      return files;
    }
    function typeName(ty) {
      if (!ty) { return ''; }
      return ty.name + (ty.arr ? '[]' : '');
    }
    function visMark(v) {
      if (v === 'public') { return '+ '; }
      if (v === 'private') { return '- '; }
      if (v === 'protected') { return '# '; }
      return '';
    }
    function renderClasses() {
      if (!classBox) { return; }
      var comp;
      try { comp = compileAll(collectFiles()); } catch (e) { comp = null; }
      if (!comp || comp.classes.length === 0) {
        classBox.innerHTML = '<p class="muted">Noch keine Klasse erkannt. Schreibe eine Klasse wie in BlueJ - sie erscheint sofort hier.</p>';
        return;
      }
      var classMap = {};
      var ci;
      for (ci = 0; ci < comp.classes.length; ci++) { classMap[comp.classes[ci].name] = comp.classes[ci]; }
      /* Vererbungsbaum: Kinder je Projekt-Klasse sammeln, Wurzeln rendern */
      var kids = {};
      var roots = [];
      var name;
      for (name in classMap) {
        if (Object.prototype.hasOwnProperty.call(classMap, name)) { kids[name] = []; }
      }
      for (name in classMap) {
        if (Object.prototype.hasOwnProperty.call(classMap, name)) {
          var pc = classMap[name];
          if (pc.parent && kids[pc.parent] !== undefined) { kids[pc.parent].push(name); }
          else { roots.push(name); }
        }
      }
      function nodeHtml(clsName, depth) {
        var cls = classMap[clsName];
        var rows = '';
        var fi;
        for (fi = 0; fi < cls.fields.length; fi++) {
          var fd = cls.fields[fi];
          rows = rows + '<div class="uml-row">' + visMark(fd.visibility) + escapeHtml(fd.name) + ' : ' + escapeHtml(typeName(fd.type)) + '</div>';
        }
        for (fi = 0; fi < cls.ctors.length; fi++) {
          var ct = cls.ctors[fi];
          var cp = [];
          var pi;
          for (pi = 0; pi < ct.params.length; pi++) { cp.push(escapeHtml(typeName(ct.params[pi].type) + ' ' + ct.params[pi].name)); }
          rows = rows + '<div class="uml-row">' + visMark(ct.visibility) + escapeHtml(cls.name) + '(' + cp.join(', ') + ')</div>';
        }
        for (fi = 0; fi < cls.methods.length; fi++) {
          var mt = cls.methods[fi];
          var mp = [];
          var pj;
          for (pj = 0; pj < mt.params.length; pj++) { mp.push(escapeHtml(typeName(mt.params[pj].type) + ' ' + mt.params[pj].name)); }
          var ret = (mt.ret && mt.ret.void) ? '' : ' : ' + escapeHtml(typeName(mt.ret));
          rows = rows + '<div class="uml-row">' + visMark(mt.visibility) + escapeHtml(mt.name) + '(' + mp.join(', ') + ')' + ret + '</div>';
        }
        if (rows === '') { rows = '<div class="uml-row uml-empty">(noch ohne Attribute und Methoden)</div>'; }
        /* Notiz nur für Eltern ausserhalb des Projekts (z.B. Grafik-Basisklassen) */
        var parentNote = (cls.parent && kids[cls.parent] === undefined) ? '<div class="uml-parent">↳ erbt von ' + escapeHtml(cls.parent) + '</div>' : '';
        var box = '<div class="uml-class"><div class="uml-name">' + escapeHtml(cls.name) + '</div><div class="uml-sec">' + rows + '</div>' + parentNote + '</div>';
        var kidNames = kids[clsName] || [];
        if (depth > 12 || kidNames.length === 0) { return box; }
        var kidHtml = '';
        var k;
        for (k = 0; k < kidNames.length; k++) {
          kidHtml = kidHtml + '<div class="uml-child"><div class="uml-arrow"></div>' + nodeHtml(kidNames[k], depth + 1) + '</div>';
        }
        return box + '<div class="uml-children">' + kidHtml + '</div>';
      }
      var html = '';
      var r;
      for (r = 0; r < roots.length; r++) { html = html + '<div class="uml-root">' + nodeHtml(roots[r], 0) + '</div>'; }
      if (!comp.ok) { html = html + '<p class="muted">Achtung: Der Code enthält noch Fehler - das Diagramm zeigt den bisher lesbaren Stand.</p>'; }
      classBox.innerHTML = html;
    }
    var classTimer = null;
    var paneAreas = document.querySelectorAll('[data-file-pane] textarea');
    for (i = 0; i < paneAreas.length; i++) {
      paneAreas[i].addEventListener('input', function () {
        if (classTimer) { clearTimeout(classTimer); }
        classTimer = setTimeout(renderClasses, 500);
        /* Fehlerkonsole live anpassen: Fehler beim Tippen erscheinen sofort,
         * behobene Fehler verschwinden ohne neuen Lauf. */
        scheduleLiveErrors();
      });
    }
    renderClasses();

    /* ---------- Automatisch speichern ----------
     * Jede Änderung im Editor landet ohne Speichern-Klick auf dem
     * Server (entprellt). Vor dem Anlegen einer neuen Datei oder dem Löschen
     * wird zwingend gespeichert - so geht nichts mehr versehentlich verloren. */
    var saveForm = document.getElementById('code-save-form');
    var saveInfo = document.getElementById('java-save-info');
    var autoTimer = null;
    var savedSnap = null;
    function collectSaveSnap() {
      var snap = { files: {} };
      var panes5 = document.querySelectorAll('[data-file-pane]');
      var k3;
      for (k3 = 0; k3 < panes5.length; k3++) {
        var nm3 = panes5[k3].getAttribute('data-file-name');
        var ta4 = panes5[k3].querySelector('textarea');
        if (nm3 && ta4) { snap.files[nm3] = ta4.value; }
      }
      return snap;
    }
    function isDirty() {
      if (!savedSnap) { return false; }
      var now = collectSaveSnap();
      var key;
      for (key in savedSnap.files) {
        if (Object.prototype.hasOwnProperty.call(savedSnap.files, key) && savedSnap.files[key] !== now.files[key]) { return true; }
      }
      return false;
    }
    function buildSaveBody() {
      var params = new URLSearchParams();
      var csrfEl = saveForm ? saveForm.querySelector('input[name="csrf"]') : null;
      if (csrfEl) { params.append('csrf', csrfEl.value); }
      var panes5 = document.querySelectorAll('[data-file-pane]');
      var k3;
      for (k3 = 0; k3 < panes5.length; k3++) {
        var fid = panes5[k3].getAttribute('data-file-pane');
        var ta4 = panes5[k3].querySelector('textarea');
        if (fid && ta4) { params.append('files[' + fid + ']', ta4.value); }
      }
      return params.toString();
    }
    /* Dezenter Speicher-Status: nur sichtbar, wenn gerade etwas passiert -
     * kein dauerhaft präsenter Text in der Leiste. */
    function saveFlash(text, isError) {
      if (!saveInfo) { return; }
      saveInfo.textContent = text;
      saveInfo.className = 'save-status' + (isError ? ' save-err' : '');
    }
    function autoSave() {
      if (!saveForm || typeof fetch !== 'function' || typeof URLSearchParams !== 'function') { return null; }
      var action = saveForm.getAttribute('action');
      if (!action) { return null; }
      return fetch(action, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: buildSaveBody(), keepalive: true }).then(function () {
        savedSnap = collectSaveSnap();
        var d = new Date();
        var hh = (d.getHours() < 10 ? '0' : '') + d.getHours();
        var mi = (d.getMinutes() < 10 ? '0' : '') + d.getMinutes();
        saveFlash('✓ automatisch gespeichert um ' + hh + ':' + mi + ' Uhr', false);
      }).catch(function () {
        saveFlash('⚠ Speichern fehlgeschlagen – bitte prüfen, ob du noch online bist.', true);
      });
    }
    function autoSaveSoon() {
      if (!saveForm || typeof fetch !== 'function') { return; }
      saveFlash('Speichert …', false);
      if (autoTimer) { clearTimeout(autoTimer); }
      autoTimer = setTimeout(autoSave, 700);
    }
    if (saveForm && typeof fetch === 'function') {
      savedSnap = collectSaveSnap();
      var pe3;
      for (pe3 = 0; pe3 < paneAreas.length; pe3++) { paneAreas[pe3].addEventListener('input', autoSaveSoon); }
      var guardForms = [];
      var selList = ['form.ide-side-new', 'form.ide-file-del'];
      var si;
      for (si = 0; si < selList.length; si++) {
        var gfList = document.querySelectorAll(selList[si]);
        var gf3;
        for (gf3 = 0; gf3 < gfList.length; gf3++) { guardForms.push(gfList[gf3]); }
      }
      var gf2;
      for (gf2 = 0; gf2 < guardForms.length; gf2++) {
        (function (frm) {
          frm.addEventListener('submit', function (ev) {
            if (!isDirty()) { return; }
            if (ev && ev.preventDefault) { ev.preventDefault(); }
            var p = autoSave();
            var go = function () { if (frm.submit) { frm.submit(); } };
            if (p && typeof p.then === 'function') { p.then(go, go); }
            else { setTimeout(go, 400); }
          });
        })(guardForms[gf2]);
      }
      if (typeof window !== 'undefined' && window.addEventListener) {
        window.addEventListener('beforeunload', function () {
          if (isDirty()) { autoSave(); }
        });
      }
    }

    /* Strg + Enter führt aus; Tab rückt ein, Enter rückt automatisch ein */
    var pe;
    for (pe = 0; pe < panes.length; pe++) {
      (function (pane) {
        var ta2 = pane.querySelector('textarea');
        if (!ta2) { return; }
        ta2.addEventListener('keydown', function (e) {
          if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); doRun(); return; }
          /* AltGr meldet sich je nach Browser anders: Chrome/Windows als
           * Strg+Alt, Firefox/Windows nur als Alt, macOS als Option (altKey).
           * Deshalb stören wir uns nur an Cmd (metaKey) und reinem Strg
           * (z. B. Strg+Tab). Reines Alt lassen wir durch - dahinter
           * steckt fast immer AltGr bzw. die Optionstaste. */
          if (e.metaKey) { return; }
          if (e.ctrlKey && !e.altKey) { return; }
          if (e.key === 'Tab') {
            e.preventDefault();
            if (e.shiftKey) { outdentSelection(ta2); }
            else { insertTab(ta2); }
            return;
          }
          if (e.key === 'Enter') { e.preventDefault(); enterNewline(ta2); return; }
          if (e.key === '(' || e.key === '[') { e.preventDefault(); typeOpenBracket(ta2, e.key); return; }
          if (e.key === '{') { e.preventDefault(); typeOpenBrace(ta2); return; }
          if (e.key === ')' || e.key === ']') { e.preventDefault(); typeOverClose(ta2, e.key); return; }
          if (e.key === '}') { e.preventDefault(); typeCloseBrace(ta2); return; }
        });
        /* Fänger für alle Fälle, in denen keydown das Zeichen nicht meldet
         * (z. B. AltGr-Kombis je nach Treiber, Mobile-Tastaturen): Das
         * beforeinput-Ereignis kommt direkt vor dem Einfügen. Läuft die
         * Automatik oben schon (preventDefault), feuert beforeinput gar
         * nicht - doppelt eingefügt wird also nichts. */
        ta2.addEventListener('beforeinput', function (ev) {
          if (!ev || !ev.preventDefault) { return; }
          if (ev.inputType === 'insertLineBreak') { ev.preventDefault(); enterNewline(ta2); return; }
          if (ev.inputType !== 'insertText' || typeof ev.data !== 'string') { return; }
          if (ev.data === '(' || ev.data === '[') { ev.preventDefault(); typeOpenBracket(ta2, ev.data); return; }
          if (ev.data === '{') { ev.preventDefault(); typeOpenBrace(ta2); return; }
          if (ev.data === ')' || ev.data === ']') { ev.preventDefault(); typeOverClose(ta2, ev.data); return; }
          if (ev.data === '}') { ev.preventDefault(); typeCloseBrace(ta2); return; }
        });
      })(panes[pe]);
    }

    /* Objektkonsole mit Autovervollständigung */
    var acItems = [];
    var acSel = -1;
    var acTouched = false;
    var acFrom = 0;
    function acHide() {
      if (acBox) { acBox.setAttribute('hidden', 'hidden'); }
      acItems = [];
      acSel = -1;
      acTouched = false;
    }
    function acRender() {
      if (!acBox) { return; }
      acBox.innerHTML = '';
      if (acItems.length === 0) { acBox.setAttribute('hidden', 'hidden'); return; }
      acBox.removeAttribute('hidden');
      for (var i4 = 0; i4 < acItems.length; i4++) {
        (function (idx) {
          var it = acItems[idx];
          var row = document.createElement('div');
          row.className = 'ac-item' + (idx === acSel ? ' sel' : '');
          var lab = document.createElement('span');
          lab.className = 'ac-label';
          lab.textContent = it.label;
          var hintEl = document.createElement('span');
          hintEl.className = 'ac-hint';
          hintEl.textContent = it.hint || '';
          row.appendChild(lab);
          row.appendChild(hintEl);
          row.addEventListener('mousedown', function (ev) { ev.preventDefault(); acPick(idx); });
          acBox.appendChild(row);
        })(i4);
      }
    }
    function acPick(idx) {
      if (!replIn || !acItems[idx]) { acHide(); return; }
      var it = acItems[idx];
      var caret = replIn.selectionStart;
      var text = replIn.value;
      /* Einfügepunkt an der AKTUELLEN Cursor-Position frisch berechnen
       * (nicht den beim Tippen gemerkten acFrom verwenden: Der Cursor kann
       * inzwischen woanders stehen, z. B. nach einem Mausklick). */
      var from = caret;
      if (session) {
        var compl = session.complete(text.substring(0, caret));
        var ki;
        for (ki = 0; ki < compl.items.length; ki++) {
          if (compl.items[ki].label === it.label && compl.items[ki].insert === it.insert) { from = compl.from; break; }
        }
      }
      /* Wort-Rest hinter dem Cursor mit ersetzen (mitten im Wort) */
      var wordEnd = caret;
      while (wordEnd < text.length && isIdentChar(text.charAt(wordEnd))) { wordEnd++; }
      if (text.substring(from, wordEnd) === it.insert) {
        /* Wort steht schon vollständig da: nur Vorschläge schließen, Cursor bleibt, wo er ist. */
        acHide();
        replIn.focus();
        return;
      }
      replIn.value = text.substring(0, from) + it.insert + text.substring(wordEnd);
      var newCaret = from + it.insert.length;
      replIn.setSelectionRange(newCaret, newCaret);
      acHide();
      replIn.focus();
    }
    function acUpdate() {
      if (!session || !replIn || !acBox) { acHide(); return; }
      var caret = replIn.selectionStart;
      var head = replIn.value.substring(0, caret);
      /* Nach abgeschlossener Anweisung (;, }, )) keine Vorschläge mehr:
       * dort beginnt kein Bezeichner, und die Box würde nur beim Tab-Druck
       * den Cursor verschieben oder Text anhängen. */
      var lw = head.length - 1;
      while (lw >= 0 && (head.charAt(lw) === ' ' || head.charAt(lw) === String.fromCharCode(9))) { lw--; }
      if (lw >= 0 && (head.charAt(lw) === ';' || head.charAt(lw) === '}' || head.charAt(lw) === ')')) { acHide(); return; }
      var compl = session.complete(head);
      acFrom = compl.from;
      acItems = compl.items.slice(0, 8);
      acSel = -1;
      acTouched = false;
      acRender();
    }
    if (replIn) {
      replIn.addEventListener('input', acUpdate);
      replIn.addEventListener('mouseup', acUpdate);
      replIn.addEventListener('keyup', function (e) {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { acUpdate(); }
      });
      replIn.addEventListener('blur', function () { acHide(); });
      replIn.addEventListener('keydown', function (e) {
        var visible = acItems.length > 0 && acBox && !acBox.hasAttribute('hidden');
        if (visible) {
          if (e.key === 'ArrowDown') { e.preventDefault(); acSel = (acSel + 1) % acItems.length; acTouched = true; acRender(); return; }
          if (e.key === 'ArrowUp') { e.preventDefault(); acSel = (acSel - 1 + acItems.length) % acItems.length; acTouched = true; acRender(); return; }
          if (e.key === 'Tab') { e.preventDefault(); acPick(acSel >= 0 ? acSel : 0); return; }
          if (e.key === 'Escape') { acHide(); return; }
          if (e.key === 'Enter' && acTouched && acSel >= 0) { e.preventDefault(); acPick(acSel); return; }
        }
        if (e.key === 'Enter') {
          e.preventDefault();
          var input = replIn.value;
          if (!session) {
            /* Noch keine Session: Eingabe NICHT löschen, damit nichts verloren geht. */
            updateReplHint();
            return;
          }
          replIn.value = '';
          acHide();
          var res = session.evalLine(input);
          replLine(input, res);
          renderObjects();
        }
      });
    }

    var clearBtn = document.getElementById('java-clear');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (consoleEl) { consoleEl.textContent = ''; }
        if (errBox) { errBox.innerHTML = ''; }
        if (replLog) { replLog.innerHTML = ''; }
        var b = badge();
        if (b) { b.textContent = '0'; }
      });
    }
    renderObjects();
    updateReplHint();
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  global.InfoCraftJavaVM = {
    lex: lex, JError: JError, Parser: Parser, run: run, openSession: openSession, NL: NL,
    gfxTick: gfxTick, gfxStopLoop: gfxStopLoop, gfxDispatchKey: gfxDispatchKey, gfxSnapshot: gfxSnapshot, formatJavaSource: formatJavaSource
  };
  if (typeof module !== 'undefined' && module.exports) { module.exports = global.InfoCraftJavaVM; }
  if (typeof document !== 'undefined') { wireUi(); }
})(typeof window !== 'undefined' ? window : globalThis);

