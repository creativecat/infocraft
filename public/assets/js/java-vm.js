/**
 * InfoCraft Java-Simulation: LEXER + PARSER (Teil 1 von 2)
 * Eigener Java-Subset-Interpreter, laeuft komplett im Browser (keine Server-
 * Kompilierung). Ein Dateipaar pro Klasse, mehrere Dateien pro Projekt.
 *
 * Fehlerkultur: Jede Meldung (kind syntax/compile/runtime/stderr) traegt
 * Datei + Zeile und eine deutsche Hilfestellung (hint) fuer Schuelerinnen
 * und Schueler. Es5-Styles, damit aeltere Schulgerte mitkommen.
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

  /* Operatoren: laengere zuerst, damit <= vor < gelesen wird. */
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
      /* Annotationen (@Override) ueberspringen */
      if (c === '@') {
        i++;
        while (i < n && /[A-Za-z0-9_]/.test(src.charAt(i))) { i++; }
        continue;
      }
      /* Bezeichner und Schluesselwoerter */
      if (/[A-Za-z_]/.test(c)) {
        var id = '';
        while (i < n && /[A-Za-z0-9_]/.test(src.charAt(i))) { id += src.charAt(i); i++; }
        toks.push(KEYWORDS[id] ? { k: 'kw', v: id, line: line } : { k: 'id', v: id, line: line });
        continue;
      }
      /* Zahlen: 42 oder 3.14 (keine Exponenten noetig) */
      if (/[0-9]/.test(c)) {
        var num = '';
        var isDbl = false;
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
        if (src.charAt(i) !== "'") { fail('Ein char besteht aus genau einem Zeichen.', "Beispiel: 'a' (mit einfachen Hochkommata). Fuer Text nutze doppelte: \"abc\""); return null; }
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
        if (!closed2) { fail('Der String ab Zeile ' + sl + ' wird nie geschlossen (fehlendes Anfuehrungszeichen).', 'Jeder String beginnt und endet mit doppelten Anfuehrungszeichen.'); return null; }
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
      fail('Unerwartetes Zeichen: ' + c, 'Dieses Zeichen gehoert nicht zu Java. Pruefe Tastatur-Layout und Sonderzeichen.');
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

    /* package/import-Zeilen ueberspringen (sinnt leer, aber harmlos) */
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
        if (at('kw', 'interface')) { err('interface wird noch nicht unterstuetzt.', 'Nutze class (die Simulation kennt erst Klassen ohne interfaces).'); }
        if (at('kw', 'enum')) { err('enum wird noch nicht unterstuetzt.', 'Nutze eine class mit konstanten Werten als statische Felder.'); }
        if (at('kw', 'record')) { err('record wird noch nicht unterstuetzt.', 'Nutze eine normale class mit Konstruktor und Attributen.'); }
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
      var mods = parseModifiers(); /* Sichtbarkeit merken, Rest ist fuer die Simulation irrelevant */
      if (!at('kw', 'class')) { err('Erwartet: class, gefunden: ' + describe(peek()), 'In einer Datei duerfen mehrere Klassen stehen (public class Name { ... }), und auch Anweisungen direkt ohne Klasse, z. B.: Test t = new Test();'); }
      next(); /* class */
      if (!at('id')) { err('Nach class folgt der Name der Klasse.', 'z. B. public class Auto { ... }'); }
      var nameTok = next();
      var cls = { name: nameTok.v, parent: null, isPublic: mods.visibility === 'public', fields: [], ctors: [], methods: [], line: nameTok.line, srcFile: srcFile };
      if (at('kw', 'extends')) {
        next();
        if (!at('id')) { err('Nach extends folgt der Name der Oberklasse.', 'z. B. class Hund extends Tier'); }
        cls.parent = next().v;
      }
      if (at('kw', 'implements')) { err('implements wird noch nicht unterstuetzt.', 'Die Simulation kennt erst Vererbung mit extends.'); }
      expectP('{', '{', 'Nach dem Klassennamen kommt die oeffnende geschweifte Klammer.');
      while (!at('p', '}') && !at('eof')) {
        parseMember(cls);
      }
      expectP('}', '}', 'Die Klasse braucht eine schliessende geschweifte Klammer am Ende.');
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
      /* Attribute und Methoden mit Rueckgabetyp */
      if (t.k === 'kw' && PRIMS[t.v]) {
        addTyped(cls, parseType(true), mods, t);
        return;
      }
      if (t.k === 'id') {
        var ty = parseType(true);
        if (!ty) { err('Unverstaendliches Klassenmitglied.', 'Erwartet: Attribut (Typ name;), Konstruktor (Name(...)) oder Methode (Typ name(...)).'); }
        if (!at('id')) { err('Nach dem Typ ' + ty.name + ' folgt ein Name.', 'z. B. ' + ty.name + ' attribut; oder ' + ty.name + ' methode() { ... }'); }
        addTyped(cls, ty, mods, t);
        return;
      }
      err('Unverstaendliches Klassenmitglied.', 'Erwartet: Attribut (Typ name;), Konstruktor (Name(...)) oder Methode (Typ name(...)).');
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
        if (dims > 1) { err('Mehrdimensionale Arrays werden noch nicht unterstuetzt.', 'Nutze eindimensionale Arrays wie int[].'); }
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
      expectP(')', ')', 'Die Parameterliste endet mit einer schliessenden Klammer.');
      return params;
    }

    function parseBlock() {
      expectP('{', '{', 'Der Rumpf steht in geschweiften Klammern { ... }.');
      var body = [];
      while (!at('p', '}') && !at('eof')) {
        body.push(parseStatement());
      }
      expectP('}', '}', 'Der Rumpf endet mit einer schliessenden geschweiften Klammer.');
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
      if (at('kw', 'switch')) { err('switch wird noch nicht unterstuetzt.', 'Nutze if / else if / else statt switch.'); }
      if (at('kw', 'try') || at('kw', 'throw')) { err('try/catch und throw werden noch nicht unterstuetzt.', 'Pruefe Werte vorher mit if, statt Ausnahmen zu werfen.'); }
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
      expectP(')', ')', 'Die if-Bedingung endet mit einer schliessenden Klammer.');
      var then = parseStatement();
      var els = null;
      if (at('kw', 'else')) { next(); els = parseStatement(); }
      return { type: 'if', cond: cond, then: then, els: els, line: t.line };
    }

    function parseWhile() {
      var t = next();
      expectP('(', '(', 'Hinter while kommt die Bedingung in runden Klammern.');
      var cond = parseExpr();
      expectP(')', ')', 'Die while-Bedingung endet mit einer schliessenden Klammer.');
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
      expectP(')', ')', 'Die Bedingung endet mit einer schliessenden Klammer.');
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
      expectP(')', ')', 'Der for-Kopf endet mit einer schliessenden Klammer.');
      var body = parseStatement();
      return { type: 'for', init: init, cond: cond, upd: upd, body: body, line: t.line };
    }

    /* ---------- Ausdruecke ---------- */
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
          expectP(']', ']', 'Hinter dem Index kommt eine schliessende eckige Klammer.');
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
      expectP(')', ')', 'Der Methodenaufruf endet mit einer schliessenden Klammer.');
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
        expectP(')', ')', 'Der geklammerte Ausdruck endet mit einer schliessenden Klammer.');
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
            expectP('{', '{', 'new int[] {1, 2} startet mit einer oeffnenden geschweiften Klammer.');
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
          expectP(']', ']', 'Hinter der Array-Groesse kommt eine schliessende eckige Klammer.');
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
          expectP(')', ')', 'Der Konstruktor-Aufruf endet mit einer schliessenden Klammer.');
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
      err('Unerwartet: ' + describe(t), 'Hier steht etwas, das nicht als Ausdruck lesbar ist. Pruefe Klammern und Semikolons in den Zeilen davor.');
    }

    /* Objektkonsole: Anweisungen ohne umgebende Klasse lesen (kein main noetig). */
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
  /* Gemeinsame Vorarbeit fuer run() und openSession(): alle Dateien lexen,
   * parsen und uebersetzen. Liefert {ok, classes, classMap, errors, fileNames}. */
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
            errors.push(JError('compile', fname, parsed.classes[pj].line, 'Die Klasse ' + parsed.classes[pj].name + ' existiert doppelt.', 'Jede Klasse darf nur einmal definiert werden (auch ueber mehrere Dateien hinweg).'));
            return { ok: false, classes: classes, classMap: classMap, errors: errors, fileNames: fileNames, toplevel: toplevel };
          }
          parsed.classes[pj].srcFile = fname;
          classes.push(parsed.classes[pj]);
          classMap[parsed.classes[pj].name] = parsed.classes[pj];
        }
        /* Anweisungen direkt in der Datei (Reihenfolge ueber Dateien wahren) */
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

  function run(files) {
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
      objects: []
    };
    RUN_VM = vm;

    var sessionEnv = newEnv(null);
    var session = makeSession(comp, vm, sessionEnv);

    var mainFound = null;
    try {
      var ci;
      for (ci = 0; ci < comp.classes.length; ci++) { initStatics(comp.classes[ci], vm); }
      /* Anweisungen direkt in der Datei laufen zuerst (Objekte erzeugen u. a.),
       * danach – falls vorhanden – die main-Methode. Beides sieht die Objektkonsole. */
      runToplevel(comp, vm, sessionEnv, errors);
      for (ci = 0; ci < comp.classes.length; ci++) {
        var mm = findMethod(comp.classes[ci], 'main', 1);
        if (mm && mm.m.isStatic) { mainFound = mm; break; }
      }
      if (mainFound) {
        invokeMethod(mainFound.cls, mainFound.m, null, [{ t: 'array', elem: 'String', arr: [] }], vm);
      }
      /* Ohne main-Methode kein Fehler mehr: Das Projekt laesst sich dann
       * interaktiv ueber die Objektkonsole bedienen (Objekte erzeugen,
       * Methoden aufrufen, Objektdiagramm). */
    } catch (e) {
      if (e && e.jerr) { errors.push(e.jerr); }
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
      return { output: out.join(''), errors: all, ok: ok, session: session, ranMain: !!ranMain };
    }
  }

  /* ================= Objektkonsole (interaktive Sitzung) =================
   * Nach dem Ausfuehren bleibt das Programm "lebendig": In der Objektkonsole
   * lassen sich Objekte erzeugen, Methoden aufrufen und Ausdruecke auswerten.
   * Alle erzeugten Objekte zeigt das Objektdiagramm. Eine main-Methode ist
   * dafuer nicht noetig (und wird in den Startpaketen bewusst nicht benutzt).
   */
  /* Anweisungen direkt in der Datei ausfuehren (ohne umgebende Klasse),
   * z. B. Objekte erzeugen: Test t = new Test();  Fehler landen in errors. */
  function runToplevel(comp, vm, env, errors) {
    var i2;
    for (i2 = 0; i2 < comp.toplevel.length; i2++) {
      var st = comp.toplevel[i2];
      var ctx2 = { file: st.srcFile || '', line: st.line || 1, cls: null, thisVal: null, vm: vm };
      try {
        execBlock([st], env, ctx2);
      } catch (e) {
        if (e && e.jerr) { errors.push(e.jerr); return; }
        if (e && (e.ctrl === 'return' || e.ctrl === 'break' || e.ctrl === 'continue')) {
          errors.push(JError('syntax', ctx2.file, st.line || 1, 'return, break und continue gehoeren in Methoden und Schleifen deiner Klasse.', 'Direkt in der Datei stehen normale Anweisungen, z. B.: Test t = new Test();'));
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
    var vm = { out: out, errCards: errCards, statics: Object.create(null), steps: 0, depth: 0, objects: [] };
    RUN_VM = vm;
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

  /* Variablen der Sitzung mit Objekt-Referenzen (fuer Namenszuordnung im Diagramm). */
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

  /* Wert fuer das Objektdiagramm beschreiben (ruft keinen Schuelercode auf). */
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
      var varName = null;
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
      hint: 'Methode (' + ps.join(', ') + ') zurueck: ' + (m.ret && m.ret.void ? 'void' : typeName(m.ret))
    };
  }

  var STRING_METHODS = ['length()', 'charAt(', 'substring(', 'indexOf(', 'equals(', 'equalsIgnoreCase(', 'contains(', 'startsWith(', 'toUpperCase()', 'toLowerCase()', 'trim()', 'replace(', 'isEmpty()'];
  var REPL_KEYWORDS = ['new', 'int', 'double', 'boolean', 'char', 'if', 'while', 'for', 'return', 'true', 'false', 'null'];
  var BUILTIN_CLASSES = ['System', 'Math', 'Integer', 'Double', 'String', 'Datenbank'];

  /* Empfaenger vor einem abschliessenden Punkt ermitteln (auch System.out.). */
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
          res.errors.push(JError('syntax', 'Objektkonsole', ctx.line, 'return, break und continue gehoeren in Methoden und Schleifen deiner Klasse.', 'In der Objektkonsole schreibst du Anweisungen direkt auf, z. B. auto.fahren();'));
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

    /* Autovervollstaendung: {from: Einfuegeposition, items: [{label, insert, hint}]} */
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
      /* Punkt vor dem Wort: Mitglieder des Empfaengers anbieten (auch mitten im Wort) */
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
        items.push({ label: REPL_KEYWORDS[i], insert: REPL_KEYWORDS[i], hint: 'Schluesselwort' });
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
          out.push({ label: 'length', insert: 'length', hint: 'Laenge des Arrays (ohne Klammern)' });
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

  /* Vorab-Pruefung nicht unterstuetzter Sprachelemente mit klarer Meldung. */
  function precheck(files) {
    var checks = [
      [/Scanner/, 'Scanner (Tastatur-Eingaben) wird noch nicht unterstuetzt.', 'Die Simulation unterstuetzt noch keine Eingaben. Arbeite mit festen Werten im Programm.'],
      [/\bArrayList\b|\bList\b/, 'ArrayList und List werden noch nicht unterstuetzt.', 'Nutze Arrays, z. B. int[] zahlen = new int[5];'],
      [/\bHashMap\b|\bMap\b|\bHashSet\b|\bSet\b/, 'HashMap, Map, HashSet und Set werden noch nicht unterstuetzt.', 'Nutze Arrays, um mehrere Werte zu speichern.'],
      [/\bswitch\b/, 'switch wird noch nicht unterstuetzt.', 'Nutze if / else if / else statt switch.'],
      [/\btry\b|\bcatch\b|\bfinally\b|\bthrow\b|\bthrows\b/, 'try/catch/throw (Ausnahmebehandlung) wird noch nicht unterstuetzt.', 'Pruefe Werte vorher mit if, statt Ausnahmen zu werfen.']
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
          if (!classMap[m[1]]) {
            errors.push(JError('compile', names[ni], li + 1, 'Zeile ' + (li + 1) + ': Unbekannte Klasse ' + m[1] + ' bei new.', 'Lege eine Datei ' + m[1] + '.java mit der Klasse ' + m[1] + ' an (oder pruefe die Schreibweise).'));
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
        if (perFile[cls.srcFile] === 1 || cls.isPublic) {
          errors.push(JError('compile', cls.srcFile, cls.line, 'Die Klasse ' + cls.name + ' heisst anders als die Datei ' + cls.srcFile + '.',
            perFile[cls.srcFile] === 1
              ? 'In Java muss der Name der (einzigen) Klasse genau dem Dateinamen entsprechen: ' + cls.name + '.java'
              : 'Bei mehreren Klassen in einer Datei muss die public-Klasse wie die Datei heissen (oder schreibe class ' + cls.name + ' ohne public)'));
          return false;
        }
      }
      if (cls.parent && !classMap[cls.parent]) {
        errors.push(JError('compile', cls.srcFile, cls.line, 'Die Oberklasse ' + cls.parent + ' von ' + cls.name + ' fehlt.', 'Lege eine Datei ' + cls.parent + '.java an (oder korrigiere den Namen hinter extends).'));
        return false;
      }
      for (var mm = 0; mm < cls.methods.length; mm++) {
        var meth = cls.methods[mm];
        for (var p = 0; p < meth.params.length; p++) {
          if (!meth.params[p].type.arr && !isPrimType(meth.params[p].type.name) && meth.params[p].type.name !== 'String' && !classMap[meth.params[p].type.name]) {
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
      vm.statics[cls.name][sf.name] = sf.init ? evalExpr(sf.init, newEnv(null), ctx) : defaultVal(sf.type);
    }
  }

  function newEnv(parent) { return { vars: {}, parent: parent }; }

  function envDeclare(env, name, type, val) { env.vars[name] = { type: type, val: val }; }

  function envFind(env, name) {
    var e = env;
    while (e) {
      if (Object.prototype.hasOwnProperty.call(e.vars, name)) { return e.vars[name]; }
      e = e.parent;
    }
    return null;
  }

  /* Typpruefung bei Zuweisung (deutsche Hilfestellung statt Compiler-Kauderwelsch). */
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
    if (isArr || (t && !isPrimType(t))) {
      if (val.t === 'null' || val.t === 'ref' || val.t === 'array' || val.t === 'String') { return; }
      throw rt(ctx, line, (t || 'Referenz') + ' passt nicht zu ' + val.t + '.', 'Der Wert muss ein Objekt oder null sein.');
    }
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
      obj.f[f.name] = f.init ? evalExpr(f.init, env, ctx) : defaultVal(f.type);
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
      throw rt(ctx, cls.line, 'Kein Konstruktor von ' + cls.name + ' nimmt ' + args.length + ' Argument(e).', 'Es gibt keinen Konstruktor mit dieser Parameterzahl. Ergaenze einen Konstruktor ' + cls.name + '(...) oder passe den new-Aufruf an.');
    }
    if (ctor) { execCtor(cls, ctor, obj, args, newEnv(null)); }
    else { initFieldsOf(cls, obj, newEnv(null), ctx); }
    return { t: 'ref', cls: cls.name, obj: obj };
  }

  function execCtor(cls, ctor, obj, args, env) {
    var ctx = { file: cls.srcFile, line: ctor.line, cls: cls, thisVal: { t: 'ref', cls: obj.cls, obj: obj }, vm: RUN_VM };
    var i;
    for (i = 0; i < ctor.params.length; i++) { envDeclare(env, ctor.params[i].name, ctor.params[i].type, args[i]); }
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
      throw rt({ file: mcls.srcFile, line: m.line }, m.line, 'StackOverflow: zu viele verschachtelte Aufrufe.', 'Vermutlich fehlt die Abbruchbedingung der Rekursion: pruefe, wann die Methode aufhoeren muss (z. B. if (n <= 0) return ...).');
    }
    var ctx = { file: mcls.srcFile, line: m.line, cls: mcls, thisVal: thisVal, vm: vm };
    var env = newEnv(null);
    var i;
    for (i = 0; i < m.params.length; i++) { envDeclare(env, m.params[i].name, m.params[i].type, args[i]); }
    var result = VOIDV;
    try {
      execBlock(m.body, env, ctx);
    } catch (e) {
      if (e && e.ctrl === 'return') {
        if (!m.ret.void) { result = e.v; }
        vm.depth--;
        return result;
      }
      vm.depth--;
      throw e;
    }
    vm.depth--;
    if (!m.ret.void) {
      throw rt(ctx, m.line, 'Die Methode ' + m.name + ' erreicht ihr Ende ohne return.', 'Jeder Weg durch eine Methode mit Rueckgabetyp braucht ein return wert; – auch am Ende der Methode oder im else-Zweig.');
    }
    return result;
  }

  /* ===== Anweisungen ===== */
  function step(ctx) {
    ctx.vm.steps++;
    if (ctx.vm.steps > MAX_STEPS) {
      throw rt(ctx, ctx.line, 'Das Programm macht sehr viele Schritte (Endlosschleife?).', 'Pruefe, ob deine Schleifen-Variable im Rumpf wirklich geaendert wird und die Bedingung irgendwann false wird.');
    }
  }

  function output(ctx, text) {
    ctx.vm.out.push(text);
    var len = 0;
    var i;
    for (i = 0; i < ctx.vm.out.length; i++) { len += ctx.vm.out[i].length; }
    if (len > MAX_OUTPUT) {
      throw rt(ctx, ctx.line, 'Das Programm produziert zu viel Ausgabe.', 'Vermutlich laeuft eine Schleife zu oft oder ohne Ende. Begrenze die Ausgaben.');
    }
  }

  function stderrOut(ctx, line, text) {
    ctx.vm.errCards.push({ kind: 'stderr', file: ctx.file, line: line, message: text, hint: 'System.err schreibt in die Fehlerkonsole. Fuer normale Ausgaben nutze System.out.println(...).' });
  }

  function execBlock(body, env, ctx) {
    for (var i = 0; i < body.length; i++) { execStmt(body[i], env, ctx); }
  }

  function execStmt(st, env, ctx) {
    step(ctx);
    if (!st || st.type === 'empty') { return; }
    ctx.line = st.line;
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
    throw rt(ctx, st.line, 'Interne Anweisung nicht unterstuetzt: ' + st.type, '');
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
      throw rt(ctx, line, 'Die Methode gibt nichts (void) zurueck und kann nicht als Wert verwendet werden.', 'Rufe die void-Methode allein auf oder nutze eine Methode mit Rueckgabetyp.');
    }
    return String(v.v);
  }

  /* ===== Ausdruecke ===== */
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
        if (!ctx.thisVal || ctx.thisVal.t !== 'ref') { throw rt(ctx, e.line, 'this steht hier nicht zur Verfuegung.', 'this nutzt man in Objektmethoden und Konstruktoren.'); }
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
        if (!cls) { throw rt(ctx, e.line, 'Unbekannte Klasse ' + e.cls + '.', 'Es gibt keine Klasse ' + e.cls + '. Pruefe die Schreibweise oder lege die Klasse an.'); }
        var aargs = [];
        var ai;
        for (ai = 0; ai < e.args.length; ai++) { aargs.push(evalExpr(e.args[ai], env, ctx)); }
        return instantiate(cls, aargs, ctx.vm);
      }
      case 'newarr': {
        var sz = evalExpr(e.size, env, ctx);
        if (sz.t !== 'int') { throw rt(ctx, e.line, 'Die Array-Groesse muss int sein.', 'z. B. new int[10]'); }
        if (sz.v < 0) { throw rt(ctx, e.line, 'NegativeArraySizeException: Das Array soll Groesse ' + sz.v + ' haben.', 'Die Groesse eines Arrays darf nicht negativ sein.'); }
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
      case 'field': return resolveField(e, evalExpr(e.target, env, ctx), ctx);
      case 'cast': return evalCast(e, env, ctx);
      case 'call': return evalCall(e, env, ctx);
      case 'super': throw rt(ctx, e.line, 'super ist nur fuer super(...) oder super.methode(...) gedacht.', 'z. B. super.getFarbe() oder im Konstruktor: super(name).');
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
    throw rt(ctx, e.line, 'Die Variable ' + e.v + ' ist nicht bekannt.', 'Pruefe die Schreibweise (Java unterscheidet Gross-/Kleinschreibung) und ob ' + e.v + ' deklariert ist (int ' + e.v + ' = ...).');
  }

  function resolveLValue(e, env, ctx) {
    if (e.type === 'name') {
      var slot = envFind(env, e.v);
      if (slot) {
        return {
          get: function () { return slot.val; },
          set: function (nv) { checkAssign(ctx, slot, nv, e.line, e.v); slot.val = nv; }
        };
      }
      if (ctx.thisVal && ctx.thisVal.t === 'ref' && Object.prototype.hasOwnProperty.call(ctx.thisVal.obj.f, e.v)) {
        var obj = ctx.thisVal.obj;
        return {
          get: function () { return obj.f[e.v]; },
          set: function (nv) { obj.f[e.v] = nv; }
        };
      }
      var c = ctx.cls;
      while (c) {
        if (ctx.vm.statics[c.name] && Object.prototype.hasOwnProperty.call(ctx.vm.statics[c.name], e.v)) {
          var st = ctx.vm.statics[c.name];
          return {
            get: function () { return st[e.v]; },
            set: function (nv) { st[e.v] = nv; }
          };
        }
        c = c.parent ? RUN_CLASS_MAP[c.parent] : null;
      }
      throw rt(ctx, e.line, 'Die Variable ' + e.v + ' ist nicht bekannt.', 'Pruefe die Schreibweise (Java unterscheidet Gross-/Kleinschreibung) und ob ' + e.v + ' deklariert ist.');
    }
    if (e.type === 'field') {
      var target = evalExpr(e.target, env, ctx);
      if (target.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Objekt fuer ' + '.' + e.name + ' ist null.', 'Das Objekt wurde vermutlich noch nicht mit new erzeugt oder eine Methode hat null zurueckgegeben.'); }
      if (target.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + e.name + ' steht kein Objekt.', 'Attribute erreicht man ueber ein Objekt, z. B. auto.farbe'); }
      var o2 = target.obj;
      if (!Object.prototype.hasOwnProperty.call(o2.f, e.name)) {
        throw rt(ctx, e.line, 'Das Attribut ' + e.name + ' gehoert nicht zur Klasse ' + target.cls + '.', 'Pruefe Schreibweise und Gross-/Kleinschreibung und ob die Klasse ein Attribut ' + e.name + ' hat.');
      }
      return {
        get: function () { return o2.f[e.name]; },
        set: function (nv) { o2.f[e.name] = nv; }
      };
    }
    if (e.type === 'index') {
      var targetArr = evalExpr(e.target, env, ctx);
      if (targetArr.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Array ist null.', 'Erzeuge das Array zuerst: new int[...] oder new int[]{...}'); }
      if (targetArr.t !== 'array') { throw rt(ctx, e.line, 'In eckigen Klammern [] kann man nur auf Arrays zugreifen.', 'z. B. zahlen[0]'); }
      var idx = evalExpr(e.index, env, ctx);
      if (idx.t !== 'int') { throw rt(ctx, e.line, 'Der Array-Index muss int sein.', 'z. B. zahlen[0] oder zahlen[i]'); }
      if (idx.v < 0 || idx.v >= targetArr.arr.length) {
        throw rt(ctx, e.line, 'ArrayIndexOutOfBoundsException: Index ' + idx.v + ', das Array hat ' + targetArr.arr.length + ' Element(e).', 'Gueltige Indizes gehen von 0 bis ' + (targetArr.arr.length - 1) + '. Pruefe die Schleifen-Grenzen (i < laenge, nicht <=).');
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
    if (target.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Das Objekt fuer .' + e.name + ' ist null.', 'Erzeuge das Objekt mit new, bevor du darauf zugreifst.'); }
    if (target.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + e.name + ' steht kein Objekt.', ''); }
    if (!Object.prototype.hasOwnProperty.call(target.obj.f, e.name)) {
      throw rt(ctx, e.line, 'Das Attribut ' + e.name + ' gehoert nicht zur Klasse ' + target.cls + '.', 'Pruefe Schreibweise und Gross-/Kleinschreibung und ob die Klasse ein Attribut ' + e.name + ' hat.');
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
    throw rt(ctx, e.line, 'Dieser Cast wird nicht unterstuetzt.', '');
  }

  function evalBin(e, env, ctx) {
    var op = e.op;
    if (op === '&&' || op === '||') {
      var l = evalExpr(e.l, env, ctx);
      if (!requireBool(ctx, l, e.line, op)) { throw rt(ctx, e.line, 'Der linke Teil von ' + op + ' muss boolean sein.', 'Mit ' + op + ' verknuepfst du Bedingungen, z. B. if (x > 0 && x < 10).'); }
      if (op === '&&' && !l.v) { return FALSE; }
      if (op === '||' && l.v) { return TRUE; }
      var r = evalExpr(e.r, env, ctx);
      if (!requireBool(ctx, r, e.line, op)) { throw rt(ctx, e.line, 'Der rechte Teil von ' + op + ' muss boolean sein.', 'Mit ' + op + ' verknuepfst du Bedingungen, z. B. if (x > 0 && x < 10).'); }
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
        throw rt(ctx, line, 'NullPointerException: Ein Operand von ' + op + ' ist null (kein Objekt).', 'Objekte muessen mit new erzeugt werden, bevor man mit ihnen rechnet.');
      }
      if (lv.t !== 'int' && lv.t !== 'double' && lv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' kann man nicht auf ' + lv.t + ' rechnen.', 'Links und rechts muessen Zahlen (oder Strings fuer +) stehen.'); }
      if (rv.t !== 'int' && rv.t !== 'double' && rv.t !== 'char') { throw rt(ctx, line, 'Mit ' + op + ' kann man nicht auf ' + rv.t + ' rechnen.', 'Links und rechts muessen Zahlen (oder Strings fuer +) stehen.'); }
      var bothInt = (lv.t === 'int' || lv.t === 'char') && (rv.t === 'int' || rv.t === 'char');
      var a = lv.v;
      var b = rv.v;
      if (op === '+') { return bothInt ? mkInt(a + b) : mkDbl(a + b); }
      if (op === '-') { return bothInt ? mkInt(a - b) : mkDbl(a - b); }
      if (op === '*') { return bothInt ? mkInt(a * b) : mkDbl(a * b); }
      if (op === '/') {
        if (bothInt) {
          if (b === 0) { throw rt(ctx, line, 'ArithmeticException: Division durch 0 (int).', 'Eine Ganzzahl-Division durch 0 ist in Java nicht erlaubt. Pruefe den Teiler.'); }
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
    throw rt(ctx, line, 'Operation ' + op + ' wird nicht unterstuetzt.', '');
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
        if (!ctx.thisVal || ctx.thisVal.t !== 'ref') { throw rt(ctx, e.line, 'Die Methode ' + name + ' braucht ein Objekt.', 'Die Methode ist nicht static; rufe sie ueber ein Objekt auf oder schreibe static davor.'); }
        return invokeMethod(fm.cls, fm.m, ctx.thisVal, args, ctx.vm);
      }
      throw rt(ctx, e.line, 'Die Methode ' + name + ' mit ' + args.length + ' Parameter(n) ist nicht bekannt.', 'Pruefe Schreibweise und Gross-/Kleinschreibung, die Parameterzahl und ob die Methode ueberhaupt existiert.');
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
      throw rt(ctx, e.line, 'Die Klasse ' + target.v + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Pruefe Schreibweise und Parameterzahl.');
    }

    /* Builtins */
    if (target.type === 'name' && target.v === 'Math') { return mathCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Integer') { return integerCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Double') { return doubleCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'String') { return stringStaticCall(name, args, ctx, e.line); }
    if (target.type === 'name' && target.v === 'Datenbank') { return dbCall(name, args, ctx, e.line); }

    /* super.methode(): Suche ab der Oberklasse */
    if (target.type === 'super') {
      if (!ctx.cls || !ctx.cls.parent || !RUN_CLASS_MAP[ctx.cls.parent]) { throw rt(ctx, e.line, 'super.methode() geht nur, wenn die Klasse extends hat.', ''); }
      var supCls = RUN_CLASS_MAP[ctx.cls.parent];
      var supm = findMethod(supCls, name, args.length);
      if (!supm) { throw rt(ctx, e.line, 'Die Oberklasse ' + supCls.name + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Pruefe Schreibweise und Parameterzahl.'); }
      return invokeMethod(supm.cls, supm.m, ctx.thisVal, args, ctx.vm);
    }

    var recv = evalExpr(target, env, ctx);

    if (recv.t === 'String') { return stringCall(recv, name, args, ctx, e.line); }
    if (recv.t === 'array') {
      if (name === 'length' && args.length === 0) { return mkInt(recv.arr.length); }
      throw rt(ctx, e.line, 'Arrays haben keine Methode ' + name + '.', 'Die Laenge bekommt man mit array.length (ohne Klammern).');
    }
    if (recv.t === 'null') { throw rt(ctx, e.line, 'NullPointerException: Der Aufruf ' + name + '(...) laeuft auf null.', 'Das Objekt wurde vermutlich noch nicht mit new erzeugt. Pruefe auch Rueckgaben anderer Methoden.'); }
    if (recv.t !== 'ref') { throw rt(ctx, e.line, 'Vor .' + name + '(...) steht kein Objekt.', ''); }
    var objCls = RUN_CLASS_MAP[recv.cls];
    var vm0 = findMethod(objCls, name, args.length);
    if (!vm0) {
      throw rt(ctx, e.line, 'Die Klasse ' + recv.cls + ' hat keine Methode ' + name + ' mit ' + args.length + ' Parameter(n).', 'Pruefe Schreibweise und Gross-/Kleinschreibung, die Parameterzahl und ob die Methode existiert.');
    }
    return invokeMethod(vm0.cls, vm0.m, recv, args, ctx.vm);
  }

  /* ===== System / Math / Integer / Double / String ===== */
  function systemCall(stream, fname, args, ctx, line) {
    if (fname !== 'println' && fname !== 'print') {
      throw rt(ctx, line, 'System.' + stream + '.' + fname + ' ist nicht unterstuetzt.', 'Nutze System.out.println(...), System.out.print(...), System.err.println(...) oder System.err.print(...).');
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
    throw rt(ctx, line, 'Math.' + name + ' wird nicht unterstuetzt (oder falsche Parameterzahl).', 'Verfuegbar: abs, max, min, pow, sqrt, floor, ceil, round, random, PI, E.');
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
    throw rt(ctx, line, 'Integer.' + name + ' wird nicht unterstuetzt.', 'Verfuegbar: parseInt, toString, MAX_VALUE, MIN_VALUE.');
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
    throw rt(ctx, line, 'Double.' + name + ' wird nicht unterstuetzt.', 'Verfuegbar: parseDouble.');
  }

  function stringStaticCall(name, args, ctx, line) {
    if (name === 'valueOf' && args.length === 1) { return mkStr(javaStr(args[0], ctx, line)); }
    throw rt(ctx, line, 'String.' + name + ' wird nicht unterstuetzt.', 'Verfuegbar: String.valueOf(...).');
  }

  /* ===== Datenbank: eingebaute Mini-SQL-Datenbank (im Speicher, pro Lauf neu) =====
   * Fuer Schuelerinnen und Schueler: Tabellen anlegen, befuellen und abfragen,
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
    throw rt(ctx, line, 'Die Spalte ' + name + ' gibt es in der Tabelle ' + table.name + ' nicht.', 'Verfuegbare Spalten: ' + alle.join(', '));
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

  /* Sucht ein Schluesselwort (WHERE, ORDER BY, LIMIT) ausserhalb von 'Texten'
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

  /* String-Literal: '...' mit verdoppeltem Anfuehrungszeichen '' als Escape. */
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
    throw rt(ctx, line, 'Der Wert ' + dbStrip(raw) + ' ist kein gueltiger SQL-Wert.', 'Text gehoert in einfache Anfuehrungszeichen: ' + "'Anna'" + '. Zahlen schreibt man ohne: 12 oder 1.5');
  }

  var DB_WHERE_TOK = /'(?:[^']|'')*'|<=|>=|<>|!=|[A-Za-z_][A-Za-z0-9_]*|[0-9]+(?:\.[0-9]+)?|[=<>]/g;

  /* WHERE in Gruppen zerlegen: OR trennt Gruppen, AND verknuepft innerhalb. */
  function dbParseWhere(cond, table, ctx, line) {
    var toks = [];
    var m;
    DB_WHERE_TOK.lastIndex = 0;
    while ((m = DB_WHERE_TOK.exec(cond))) { toks.push(m[0]); }
    if (toks.length === 0) {
      throw rt(ctx, line, 'Die WHERE-Bedingung ist unvollstaendig.', 'z. B. WHERE name = ' + "'Anna'" + ' oder WHERE punkte > 10');
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
        throw rt(ctx, line, 'Der Vergleich bei WHERE ist unvollstaendig.', 'Jeder Vergleich hat die Form spalte OP wert, z. B. punkte > 10.');
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
      throw rt(ctx, line, 'Der WHERE-Teil ist unvollstaendig.', 'z. B. WHERE name = ' + "'Anna'");
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

  /* Ausfuehren von CREATE TABLE / INSERT / UPDATE / DELETE / DROP TABLE. */
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
        throw rt(ctx, line, 'Die Tabelle ' + m[1] + ' existiert bereits.', 'Waehle einen anderen Namen oder loesche sie vorher: DROP TABLE ' + m[1]);
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
        throw rt(ctx, line, 'INSERT verstehe ich nicht.', 'Form: INSERT INTO tabelle VALUES (wert, ...) – Text in einfachen Anfuehrungszeichen.');
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
          throw rt(ctx, line, 'Werte bei INSERT muessen in Klammern stehen.', 'z. B. INSERT INTO ' + table.name + ' VALUES (' + "'Anna'" + ', 12)');
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
          throw rt(ctx, line, 'INSERT hat ' + lits.length + ' Wert(e), erwartet werden aber ' + useCols.length + '.', 'Anzahl der Werte und Spalten muss uebereinstimmen.');
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
              throw rt(ctx, line, 'In die Spalte ' + colName + ' (INT) gehoert eine Zahl ohne Anfuehrungszeichen.', 'z. B. 12 statt ' + "'" + val.v + "'");
            }
          } else if (kind === 'text') {
            if (val.t === 'int' || val.t === 'double') {
              throw rt(ctx, line, 'In die Spalte ' + colName + ' (TEXT) gehoert ein Text in einfachen Anfuehrungszeichen.', "z. B. 'Anna' statt " + val.v);
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
      throw rt(ctx, line, 'SELECT gehoert in Datenbank.abfrage(...), nicht in ausfuehren(...).', 'z. B. System.out.println(Datenbank.abfrage("SELECT * FROM tabelle"));');
    }
    throw rt(ctx, line, 'Den SQL-Befehl ' + kw + ' unterstuetzt die Mini-Datenbank nicht.', 'Verfuegbar: CREATE TABLE, INSERT INTO, UPDATE, DELETE FROM, DROP TABLE, SELECT.');
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
        throw rt(ctx, line, 'Die Tabelle ' + table.name + ' hat keine Spalten.', 'Pruefe das CREATE TABLE.');
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
    throw rt(ctx, line, 'Datenbank.' + name + ' wird nicht unterstuetzt.', 'Verfuegbar: ausfuehren(sql), abfrage(sql), anzahl(sql), wert(sql), tabellen().');
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
      if (idx < 0 || idx >= s.length) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: charAt(' + idx + '), aber der String hat ' + s.length + ' Zeichen.', 'Gueltige Indizes: 0 bis ' + (s.length - 1) + '.'); }
      return mkChar(s.charCodeAt(idx));
    }
    if (name === 'substring' && args.length === 1) {
      var b = wantIdx(0, 'substring');
      if (b < 0 || b > s.length) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: substring(' + b + ') bei Laenge ' + s.length + '.', 'Der Start-Index muss zwischen 0 und ' + s.length + ' liegen.'); }
      return mkStr(s.substring(b));
    }
    if (name === 'substring' && args.length === 2) {
      var b2 = wantIdx(0, 'substring');
      var e2 = wantIdx(1, 'substring');
      if (b2 < 0 || e2 > s.length || b2 > e2) { throw rt(ctx, line, 'StringIndexOutOfBoundsException: substring(' + b2 + ', ' + e2 + ') bei Laenge ' + s.length + '.', 'Es gilt 0 <= Anfang <= Ende <= ' + s.length + '.'); }
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
    throw rt(ctx, line, 'Die String-Methode ' + name + ' (mit ' + args.length + ' Parametern) wird nicht unterstuetzt.', 'Verfuegbar: length, charAt, substring, indexOf, equals, equalsIgnoreCase, contains, startsWith, endsWith, toUpperCase, toLowerCase, trim, replace, isEmpty, compareTo.');
  }

  /* ================= UI-Anbindung (nur im Browser aktiv) ================= */
  function wireUi() {
    if (typeof document === 'undefined') { return; }
    var tabs = document.querySelectorAll('[data-file-tab]');
    var panes = document.querySelectorAll('[data-file-pane]');
    var fileRows = document.querySelectorAll('.ide-file[data-file-row]');
    var i;
    /* Datei im Editor anzeigen: das hidden-Attribut steuert die Sichtbarkeit der Panes,
     * active nur fuer die Optik von Tab und Zeile. */
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

    function renderErrCard(box, er) {
      var card = document.createElement('div');
      card.className = 'err-card' + (er.kind === 'stderr' ? ' err-stderr' : '');
      var head = document.createElement('div');
      head.className = 'err-head';
      var kindLabel = er.kind === 'stderr' ? 'System.err' : (er.kind === 'syntax' ? 'Syntaxfehler' : (er.kind === 'compile' ? 'Uebersetzungsproblem' : 'Laufzeitfehler'));
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
      box.appendChild(card);
    }

    function showErrors(list) {
      if (!errBox) { return; }
      errBox.innerHTML = '';
      if (list.length === 0) {
        errBox.innerHTML = '<div class="err-card err-ok">Alles fehlerfrei durchgelaufen.</div>';
        return;
      }
      for (var h = 0; h < list.length; h++) { renderErrCard(errBox, list[h]); }
    }

    /* Objektdiagramm: alle erzeugten Objekte mit ihren Attributwerten */
    function renderObjects() {
      if (!objBox) { return; }
      var list = session ? session.objects() : [];
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
        head.textContent = (o.name ? o.name + ' : ' : ': ') + o.cls + '  (Objekt ' + o.id + ')';
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
      if (names.length === 0) { replHint.textContent = 'Dieses Projekt enthaelt noch keine Klasse.'; return; }
      replHint.textContent = 'Klassen: ' + names.join(', ') + '. Beispiel: ' + names[0] + ' a = new ' + names[0] + '(...); danach a.methode();';
    }

    function doRun() {
      var files = {};
      var panes2 = document.querySelectorAll('[data-file-pane]');
      var k;
      for (k = 0; k < panes2.length; k++) {
        var nm = panes2[k].getAttribute('data-file-name');
        var ta = panes2[k].querySelector('textarea');
        if (nm && ta) { files[nm] = ta.value; }
      }
      var res;
      try {
        res = run(files);
      } catch (e) {
        if (consoleEl) { consoleEl.textContent = ''; }
        if (errBox) { errBox.innerHTML = '<div class="err-card">Interner Fehler der Simulation: ' + escapeHtml(e && e.message ? e.message : String(e)) + '</div>'; }
        return;
      }
      session = res.session || null;
      if (consoleEl) {
        if (res.output !== '') { consoleEl.textContent = res.output; }
        else if (res.ok && !res.ranMain) { consoleEl.textContent = 'Kein main noetig: Erzeuge dir Objekte in der Objektkonsole (Tab oben).'; }
        else if (res.ok) { consoleEl.textContent = '(keine Ausgabe)'; }
        else { consoleEl.textContent = ''; }
      }
      showErrors(res.errors);
      var b = badge();
      if (b) { b.textContent = res.errors.length === 0 ? '0' : String(res.errors.length); }
      renderObjects();
      updateReplHint();
      renderClasses();
      if (!res.ok) { showPane('errors'); }
      else if (!res.ranMain && res.output === '') { showPane('repl'); }
      else { showPane('out'); }
    }
    runBtn.addEventListener('click', doRun);

    /* Klassendiagramm direkt in der IDE (Idee von BlueJ): live aus dem Code gelesen,
     * ohne das Programm auszufuehren. Parse-Fehler stoeren nicht: Der lesbare Stand
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
      var html = '';
      var ci;
      for (ci = 0; ci < comp.classes.length; ci++) {
        var cls = comp.classes[ci];
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
        var parentNote = cls.parent ? '<div class="uml-parent">↳ erbt von ' + escapeHtml(cls.parent) + '</div>' : '';
        html = html + '<div class="uml-class"><div class="uml-name">' + escapeHtml(cls.name) + '</div><div class="uml-sec">' + rows + '</div>' + parentNote + '</div>';
      }
      if (!comp.ok) { html = html + '<p class="muted">Achtung: Der Code enthaelt noch Fehler - das Diagramm zeigt den bisher lesbaren Stand.</p>'; }
      classBox.innerHTML = html;
    }
    var classTimer = null;
    var paneAreas = document.querySelectorAll('[data-file-pane] textarea');
    for (i = 0; i < paneAreas.length; i++) {
      paneAreas[i].addEventListener('input', function () {
        if (classTimer) { clearTimeout(classTimer); }
        classTimer = setTimeout(renderClasses, 500);
      });
    }
    renderClasses();

    /* Strg + Enter fuehrt aus, egal welcher Editor fokussiert ist */
    var pe;
    for (pe = 0; pe < panes.length; pe++) {
      (function (pane) {
        var ta2 = pane.querySelector('textarea');
        if (!ta2) { return; }
        ta2.addEventListener('keydown', function (e) {
          if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); doRun(); }
        });
      })(panes[pe]);
    }

    /* Objektkonsole mit Autovervollstaendung */
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
      /* Einfuegepunkt an der AKTUELLEN Cursor-Position frisch berechnen
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
        /* Wort steht schon vollstaendig da: nur Vorschlaege schliessen, Cursor bleibt, wo er ist. */
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
      /* Nach abgeschlossener Anweisung (;, }, )) keine Vorschlaege mehr:
       * dort beginnt kein Bezeichner, und die Box wuerde nur beim Tab-Druck
       * den Cursor verschieben oder Text anhaengen. */
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
            /* Noch keine Session: Eingabe NICHT loeschen, damit nichts verloren geht. */
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
    lex: lex, JError: JError, Parser: Parser, run: run, openSession: openSession, NL: NL
  };
  if (typeof module !== 'undefined' && module.exports) { module.exports = global.InfoCraftJavaVM; }
  if (typeof document !== 'undefined') { wireUi(); }
})(typeof window !== 'undefined' ? window : globalThis);

