/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Assembler-Registermaschine für die Code-Werkstatt
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
/* InfoCraft: Assembler-VM und Assembler-IDE (public/assets/js/asm-vm.js).
 *
 * Eigene didaktische Registermaschine fuer Assembler-Projekte:
 * - 16 Register R0 bis R15, alle Werte ganze Zahlen
 * - Befehle: MOV/LOAD, ADD, SUB, MUL, DIV, MOD, INC, DEC, CMP,
 *   JMP, JZ, JNZ, JGT, JLT, OUT, OUTS, IN, HALT
 * - Abitur-Befehlssatz (Akkumulator-Maschine, Akku): die Register
 *   R0 bis R15 sind die Speicherzellen. Hessen/Asteroth: LOAD #i,
 *   LOAD i, LOAD *i, STORE i, STORE *i, ADD/SUB/MUL/DIV ..., GOTO,
 *   JZERO, JNZERO, END (SUB-Unterlauf ergibt 0). Bayern/ISB:
 *   load x, load (x), loadi n, store x, store (x), add/addi,
 *   sub/subi, jmp, jeq, jne, hold.
 * - Marken mit Doppelpunkt (marke:), Kommentare mit Semikolon (;)
 * - Flags (gleich/kleiner/groesser) nach CMP und Arithmetik
 * - Deutsche Fehlermeldungen mit Datei und Zeilennummer
 *
 * Die VM laeuft komplett im Browser. Alle .asm-Dateien eines Projekts
 * werden in der Reihenfolge zu einem Programm zusammengesetzt, Marken
 * gelten ueber alle Dateien hinweg.
 */
(function () {
  'use strict';

  var MAX_STEPS = 1000000;
  var OPS = {
    MOV: true, LOAD: true, ADD: true, SUB: true, MUL: true, DIV: true, MOD: true,
    INC: true, DEC: true, CMP: true, JMP: true, JZ: true, JNZ: true, JGT: true,
    JLT: true, OUT: true, OUTS: true, IN: true, HALT: true,
    STORE: true, LOADI: true, ADDI: true, SUBI: true,
    GOTO: true, JZERO: true, JNZERO: true, JEQ: true, JNE: true, END: true, HOLD: true
  };
  var JUMPS = { JMP: 1, JZ: 1, JNZ: 1, JGT: 1, JLT: 1, GOTO: 1, JZERO: 1, JNZERO: 1, JEQ: 1, JNE: 1 };
  /* Akku-Befehle (Abitur): ein Operand ohne Komma, Akku ist der interne Akkumulator. */
  var ACC_OPS = { LOAD: 1, ADD: 1, SUB: 1, MUL: 1, DIV: 1, STORE: 1 };
  var IMM_OPS = { LOADI: 1, ADDI: 1, SUBI: 1 };
  var NL = String.fromCharCode(10);

  function regNum(token, err) {
    var m = /^R([0-9]{1,2})$/i.exec(token);
    if (!m) { err('Das Register "' + token + '" gibt es nicht. Schreibe z. B. R1 (R0 bis R15).'); return -1; }
    var n = parseInt(m[1], 10);
    if (n < 0 || n > 15) { err('Das Register R' + n + ' gibt es nicht. Erlaubt sind R0 bis R15.'); return -1; }
    return n;
  }

  function parseNumber(token, err) {
    if (!/^-?[0-9]{1,9}$/.test(token)) { err('"' + token + '" ist keine ganze Zahl.'); return 0; }
    return parseInt(token, 10);
  }

  /* Parst eine OUTS-Zeichenkette inklusive Anfuehrungszeichen. */
  function parseStringArg(rest) {
    var m = /^"((?:[^"\\]|\\.)*)"\s*$/.exec(rest);
    if (!m) { return null; }
    return m[1].replace(/\\(.)/g, function (_, c) {
      if (c === 'n') { return NL; }
      if (c === 't') { return '\t'; }
      return c;
    });
  }

  /* Parst einen Abitur-Operanden (Akkumulator-Maschine):
   * #i Konstante, i direkt (Speicherzelle Ri), *i und (i) indirekt. */
  function parseAccOperand(token, err) {
    var m;
    if (/^#-?[0-9]{1,9}$/.test(token)) { return { mode: 'imm', imm: parseInt(token.slice(1), 10), cell: -1 }; }
    m = /^\*-?[0-9]{1,3}$/.exec(token);
    if (m) { return { mode: 'ind', imm: 0, cell: parseInt(token.slice(1), 10) }; }
    m = /^\((\d{1,3})\)$/.exec(token);
    if (m) { return { mode: 'ind', imm: 0, cell: parseInt(m[1], 10) }; }
    m = /^R(\d{1,2})$/i.exec(token);
    if (m) { return { mode: 'dir', imm: 0, cell: parseInt(m[1], 10) }; }
    if (/^\d{1,3}$/.test(token)) { return { mode: 'dir', imm: 0, cell: parseInt(token, 10) }; }
    err('"' + token + '" verstehe ich nicht. Erlaubt sind #5 (Konstante), 5 (Speicherzelle R5), *5 oder (5) (indirekt). Falls du zwei Werte meinst, schreibe sie mit Komma, z. B. LOAD R1, 5.');
    return null;
  }

  /* Prueft eine Speicherzellen-Nummer (0 bis 15). */
  function cellOk(cell, err) {
    if (cell < 0 || cell > 15) {
      err('Die Speicherzelle ' + cell + ' gibt es nicht. Erlaubt sind R0 bis R15.');
      return false;
    }
    return true;
  }

  /* files: Liste von { name, content } oder Objekt { name: content }.
   * Rueckgabe: { instrs, labels, errors } mit instrs[i] = { op, a, b, aReg,
   * bReg, imm, label, file, line } und labels[name] = Index. */
  function parseAsm(files) {
    var list = [];
    var k;
    if (typeof files === 'string') {
      list.push({ name: 'programm.asm', content: files });
    } else if (typeof Array.isArray === 'function' && Array.isArray(files)) {
      for (k = 0; k < files.length; k++) { list.push(files[k]); }
    } else if (files && typeof files === 'object' && typeof files.content === 'string') {
      list.push({ name: files.name || 'programm.asm', content: files.content });
    } else if (files && typeof files === 'object') {
      for (k in files) {
        if (Object.prototype.hasOwnProperty.call(files, k)) { list.push({ name: k, content: files[k] }); }
      }
    }
    var instrs = [];
    var labels = {};
    var errors = [];
    var fi;
    for (fi = 0; fi < list.length; fi++) {
      var name = list[fi].name || ('Datei ' + (fi + 1));
      var lines = String(list[fi].content == null ? '' : list[fi].content).split(NL);
      var li;
      for (li = 0; li < lines.length; li++) {
        var raw = lines[li];
        var cut = raw.indexOf(';');
        var text = (cut >= 0 ? raw.slice(0, cut) : raw).trim();
        if (text === '') { continue; }
        var errHere = function (message) {
          errors.push({ file: name, line: li + 1, message: message });
        };
        /* Marke am Zeilenanfang (auch mit Befehl in derselben Zeile) */
        var labelMatch = /^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/.exec(text);
        if (labelMatch) {
          var lname = labelMatch[1];
          if (Object.prototype.hasOwnProperty.call(labels, lname)) {
            errHere('Die Marke "' + lname + '" gibt es in diesem Programm schon einmal.');
          } else {
            labels[lname] = instrs.length;
          }
          text = labelMatch[2];
          if (text === '') { continue; }
        }
        var parts = text.split(/\s+/);
        var op = parts[0].toUpperCase();
        /* Bayerische Abitur-Schreibweise: hold/end sind HALT */
        if (op === 'END' || op === 'HOLD') { op = 'HALT'; }
        if (!OPS[op]) {
          errHere('Den Befehl "' + parts[0] + '" gibt es nicht. Alle Befehle stehen im Tab 📖 (Befehle).');
          continue;
        }
        var rest = text.slice(parts[0].length).trim();
        var instr = { op: op, file: name, line: li + 1, aReg: -1, bReg: -1, imm: 0, label: '', acc: false, mode: '', cell: -1 };
        var args;
        if (op === 'HALT') {
          instrs.push(instr);
          continue;
        }
        if (op === 'OUTS') {
          var s = parseStringArg(rest);
          if (s === null) {
            errHere('OUTS braucht einen Text in Anfuehrungszeichen, z. B. OUTS "Hallo!".');
            continue;
          }
          instr.text = s;
          instrs.push(instr);
          continue;
        }
        if (JUMPS[op]) {
          if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(rest)) {
            errHere('Der Sprung ' + op + ' braucht eine Marke als Ziel, z. B. ' + op + ' schleife (und irgendwo "schleife:").');
            continue;
          }
          instr.label = rest;
          instrs.push(instr);
          continue;
        }
        if (op === 'OUT') {
          if (rest === '') { instr.acc = true; instrs.push(instr); continue; }
          instr.aReg = regNum(rest, errHere);
          if (instr.aReg < 0) { continue; }
          instrs.push(instr);
          continue;
        }
        if (op === 'IN') {
          instr.aReg = regNum(rest, errHere);
          if (instr.aReg < 0) { continue; }
          instrs.push(instr);
          continue;
        }
        if (op === 'INC' || op === 'DEC') {
          instr.aReg = regNum(rest, errHere);
          if (instr.aReg < 0) { continue; }
          instrs.push(instr);
          continue;
        }
        /* Abitur: LOADI/ADDI/SUBI laden eine Konstante in den Akku */
        if (IMM_OPS[op]) {
          instr.acc = true;
          instr.mode = 'imm';
          instr.imm = parseNumber(rest, errHere);
          instrs.push(instr);
          continue;
        }
        /* Abitur: ein Operand ohne Komma (LOAD #3, LOAD 3, STORE *3, ...) */
        if (ACC_OPS[op] && rest.indexOf(',') < 0) {
          var opnd = parseAccOperand(rest, errHere);
          if (!opnd) { continue; }
          if (opnd.mode === 'imm' && op === 'STORE') {
            errHere('STORE braucht eine Speicherzelle, z. B. STORE 3 oder STORE *3 (nicht STORE #3).');
            continue;
          }
          if (opnd.mode !== 'imm' && !cellOk(opnd.cell, errHere)) { continue; }
          instr.acc = true;
          instr.mode = opnd.mode;
          instr.imm = opnd.imm;
          instr.cell = opnd.cell;
          instrs.push(instr);
          continue;
        }
        if (op === 'STORE') {
          errHere('STORE braucht genau eine Speicherzelle, z. B. STORE 3 oder STORE *3.');
          continue;
        }
        /* Zwei-Operand-Befehle: MOV/LOAD/ADD/SUB/MUL/DIV/MOD/CMP
         * mit "ziel, quelle": quelle ist Register oder Zahl. */
        args = rest.split(',');
        if (args.length !== 2 || args[0].trim() === '' || args[1].trim() === '') {
          errHere(op + ' braucht zwei Werte mit Komma, z. B. ' + op + ' R1, 5 oder ' + op + ' R1, R2.');
          continue;
        }
        var a = args[0].trim();
        var b = args[1].trim();
        instr.aReg = regNum(a, errHere);
        if (instr.aReg < 0) { continue; }
        if (/^-?[0-9]{1,9}$/.test(b)) {
          instr.imm = parseInt(b, 10);
        } else if (/^R([0-9]{1,2})$/i.test(b)) {
          instr.bReg = regNum(b, errHere);
          if (instr.bReg < 0) { continue; }
        } else {
          errHere('"' + b + '" ist weder ein Register (R0 bis R15) noch eine Zahl.');
          continue;
        }
        instrs.push(instr);
      }
    }
    /* Sprungziele muessen existieren */
    var i2;
    for (i2 = 0; i2 < instrs.length; i2++) {
      if (JUMPS[instrs[i2].op] && !Object.prototype.hasOwnProperty.call(labels, instrs[i2].label)) {
        errors.push({
          file: instrs[i2].file, line: instrs[i2].line,
          message: 'Die Marke "' + instrs[i2].label + '" fehlt. Schreibe irgendwo eine Zeile "' + instrs[i2].label + ':".'
        });
      }
    }
    return { instrs: instrs, labels: labels, errors: errors };
  }

  /* Laufende Maschine: step() fuehrt genau einen Befehl aus. */
  function createRuntime(parsed, input) {
    var regs = [];
    var r;
    for (r = 0; r < 16; r++) { regs[r] = 0; }
    var inputs = [];
    if (typeof input === 'string' && input.trim() !== '') {
      var parts = input.split(',');
      var p;
      for (p = 0; p < parts.length; p++) {
        var v = parseInt(parts[p].trim(), 10);
        if (isNaN(v)) { v = 0; }
        inputs.push(v);
      }
    } else if (typeof input === 'number') {
      inputs.push(input);
    }
    var rt = {
      acc: 0,
      regs: regs,
      flags: { eq: false, lt: false, gt: false },
      pc: 0,
      steps: 0,
      out: '',
      halted: false,
      started: false,
      error: null,
      inputPos: 0,
      instrs: parsed.instrs,
      labels: parsed.labels,
      changed: null
    };
    function setFlags(value) {
      rt.flags.eq = value === 0;
      rt.flags.lt = value < 0;
      rt.flags.gt = value > 0;
    }
    function srcValue(instr) {
      return instr.bReg >= 0 ? rt.regs[instr.bReg] : instr.imm;
    }
    /* Abitur-Operand lesen: #i (imm), i (dir), *i bzw. (i) (ind). */
    function accOperand(instr) {
      if (instr.mode === 'imm') { return { ok: true, value: instr.imm, cell: -1 }; }
      if (instr.mode === 'dir') { return { ok: true, value: rt.regs[instr.cell], cell: instr.cell }; }
      var inner = rt.regs[instr.cell];
      if (inner < 0 || inner > 15) {
        rt.error = { file: instr.file, line: instr.line, message: 'Indirekte Adresse ' + inner + ' in R' + instr.cell + ' liegt ausserhalb der Speicherzellen R0 bis R15 (Zeile ' + instr.line + ').' };
        return { ok: false, value: 0, cell: -1 };
      }
      return { ok: true, value: rt.regs[inner], cell: inner };
    }
    function accFail() {
      rt.halted = true;
      return true;
    }
    rt.step = function () {
      if (rt.halted) { return true; }
      if (rt.pc >= parsed.instrs.length) {
        /* Ohne HALT: Hinweis statt Endlosschleife */
        rt.error = { file: '', line: 0, message: 'Dein Programm endet ohne HALT. Schreibe am Ende HALT, damit klar ist, wann es fertig ist.' };
        rt.halted = true;
        return true;
      }
      var instr = parsed.instrs[rt.pc];
      rt.changed = null;
      var jumpTo = -1;
      switch (instr.op) {
      case 'MOV': case 'LOAD':
        if (instr.acc) {
          var aoL = accOperand(instr);
          if (!aoL.ok) { return accFail(); }
          rt.acc = aoL.value;
          setFlags(rt.acc);
          break;
        }
        rt.regs[instr.aReg] = srcValue(instr);
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'STORE':
        if (instr.acc) {
          var aoS = accOperand(instr);
          if (!aoS.ok) { return accFail(); }
          var target = instr.mode === 'ind' ? aoS.cell : instr.cell;
          rt.regs[target] = rt.acc;
          rt.changed = target;
        }
        break;
      case 'LOADI':
        rt.acc = instr.imm;
        setFlags(rt.acc);
        break;
      case 'ADD':
        if (instr.acc) {
          var aoA = accOperand(instr);
          if (!aoA.ok) { return accFail(); }
          rt.acc = rt.acc + aoA.value;
          setFlags(rt.acc);
          break;
        }
        rt.regs[instr.aReg] = rt.regs[instr.aReg] + srcValue(instr);
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'SUB':
        if (instr.acc) {
          var aoS2 = accOperand(instr);
          if (!aoS2.ok) { return accFail(); }
          /* Abitur-Registermaschine: negatives Ergebnis wird 0 */
          rt.acc = rt.acc - aoS2.value;
          if (rt.acc < 0) { rt.acc = 0; }
          setFlags(rt.acc);
          break;
        }
        rt.regs[instr.aReg] = rt.regs[instr.aReg] - srcValue(instr);
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'MUL':
        if (instr.acc) {
          var aoM = accOperand(instr);
          if (!aoM.ok) { return accFail(); }
          rt.acc = rt.acc * aoM.value;
          setFlags(rt.acc);
          break;
        }
        rt.regs[instr.aReg] = rt.regs[instr.aReg] * srcValue(instr);
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'DIV':
        if (instr.acc) {
          var aoD = accOperand(instr);
          if (!aoD.ok) { return accFail(); }
          if (aoD.value === 0) {
            rt.error = { file: instr.file, line: instr.line, message: 'Division durch 0 geht nicht. Pruefe den Operanden von DIV in Zeile ' + instr.line + '.' };
            return accFail();
          }
          rt.acc = Math.trunc(rt.acc / aoD.value);
          setFlags(rt.acc);
          break;
        }
        if (srcValue(instr) === 0) {
          rt.error = { file: instr.file, line: instr.line, message: 'Division durch 0 geht nicht. Pruefe den zweiten Wert von DIV in Zeile ' + instr.line + '.' };
          rt.halted = true;
          return true;
        }
        rt.regs[instr.aReg] = Math.trunc(rt.regs[instr.aReg] / srcValue(instr));
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'MOD':
        if (srcValue(instr) === 0) {
          rt.error = { file: instr.file, line: instr.line, message: 'MOD durch 0 geht nicht (Rest einer Division durch 0). Pruefe den zweiten Wert in Zeile ' + instr.line + '.' };
          rt.halted = true;
          return true;
        }
        rt.regs[instr.aReg] = rt.regs[instr.aReg] % srcValue(instr);
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'INC':
        rt.regs[instr.aReg] = rt.regs[instr.aReg] + 1;
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'DEC':
        rt.regs[instr.aReg] = rt.regs[instr.aReg] - 1;
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'CMP':
        setFlags(rt.regs[instr.aReg] - srcValue(instr));
        break;
      case 'JMP': jumpTo = rt.labels[instr.label]; break;
      case 'GOTO': jumpTo = rt.labels[instr.label]; break;
      case 'JZERO': if (rt.acc === 0) { jumpTo = rt.labels[instr.label]; } break;
      case 'JNZERO': if (rt.acc !== 0) { jumpTo = rt.labels[instr.label]; } break;
      case 'JEQ': if (rt.flags.eq) { jumpTo = rt.labels[instr.label]; } break;
      case 'JNE': if (!rt.flags.eq) { jumpTo = rt.labels[instr.label]; } break;
      case 'ADDI':
        rt.acc = rt.acc + instr.imm;
        setFlags(rt.acc);
        break;
      case 'SUBI':
        rt.acc = rt.acc - instr.imm;
        if (rt.acc < 0) { rt.acc = 0; }
        setFlags(rt.acc);
        break;
      case 'JZ': if (rt.flags.eq) { jumpTo = rt.labels[instr.label]; } break;
      case 'JNZ': if (!rt.flags.eq) { jumpTo = rt.labels[instr.label]; } break;
      case 'JGT': if (rt.flags.gt) { jumpTo = rt.labels[instr.label]; } break;
      case 'JLT': if (rt.flags.lt) { jumpTo = rt.labels[instr.label]; } break;
      case 'OUT':
        if (instr.acc) { rt.out += String(rt.acc) + NL; break; }
        rt.out += String(rt.regs[instr.aReg]) + NL;
        break;
      case 'OUTS':
        rt.out += instr.text;
        break;
      case 'IN':
        if (rt.inputPos >= inputs.length) {
          rt.error = { file: instr.file, line: instr.line, message: 'IN braucht eine Zahl im Eingabefeld (unter der Register-Anzeige, mehrere mit Komma). Schreibe z. B. 3, 12 und fuehre erneut aus.' };
          rt.halted = true;
          return true;
        }
        rt.regs[instr.aReg] = inputs[rt.inputPos];
        rt.inputPos++;
        setFlags(rt.regs[instr.aReg]);
        rt.changed = instr.aReg;
        break;
      case 'HALT':
        rt.halted = true;
        break;
      }
      rt.steps++;
      rt.started = true;
      if (rt.halted) { return true; }
      if (jumpTo >= 0) { rt.pc = jumpTo; } else { rt.pc++; }
      if (rt.steps >= MAX_STEPS) {
        rt.error = { file: '', line: 0, message: 'Endlosschleife? Dein Programm lief ueber ' + MAX_STEPS + ' Schritte ohne HALT. Pruefe deine Spruenge und den Zaehler im Register.' };
        rt.halted = true;
      }
      return rt.halted;
    };
    rt.runAll = function () {
      while (!rt.step()) {
        if (rt.halted) { break; }
      }
      return rt;
    };
    return rt;
  }

  /* Kompletter Lauf fuer Tests und ▶ Ausfuehren:
   * run(files, input) -> { ok, output, errors, regs, flags, steps } */
  function run(files, input) {
    var parsed = parseAsm(files);
    if (parsed.errors.length > 0) {
      return { ok: false, output: '', errors: parsed.errors, regs: [], flags: null, steps: 0 };
    }
    if (parsed.instrs.length === 0) {
      return {
        ok: false, output: '', steps: 0, regs: [], flags: null,
        errors: [{ file: '', line: 0, message: 'Dein Programm ist leer. Schreibe z. B. LOAD R1, 5' + NL + 'OUT R1' + NL + 'HALT.' }]
      };
    }
    var rt = createRuntime(parsed, input);
    rt.runAll();
    var errors = [];
    if (rt.error) { errors.push(rt.error); }
    return { ok: errors.length === 0, output: rt.out, errors: errors, regs: rt.regs, flags: rt.flags, steps: rt.steps };
  }

  /* ============================ UI ============================ */
  function wireUi() {
    var runBtn = document.getElementById('run-asm');
    if (!runBtn) { return; }
    var stepBtn = document.getElementById('asm-step');
    var restBtn = document.getElementById('asm-run-rest');
    var stopBtn = document.getElementById('asm-stop');
    var outBox = document.getElementById('asm-console');
    var regBox = document.getElementById('asm-registers');
    var inBox = document.getElementById('asm-in');
    var errBox = document.getElementById('asm-errors');
    var errBadge = document.getElementById('asm-error-badge');
    var errClear = document.getElementById('asm-clear');
    var stepInfo = document.getElementById('asm-step-info');
    var saveForm = document.getElementById('code-save-form');
    var saveInfo = document.getElementById('java-save-info');
    var paneAreas = document.querySelectorAll('[data-file-pane] textarea');
    var paneList = document.querySelectorAll('[data-file-pane]');

    var runtime = null;
    var lastParsed = null;
    var prevRegs = null;

    function collectFiles() {
      var files = [];
      var i;
      for (i = 0; i < paneList.length; i++) {
        var nm = paneList[i].getAttribute('data-file-name');
        var ta = paneList[i].querySelector('textarea');
        if (nm && ta) { files.push({ name: nm, content: String(ta.value || '') }); }
      }
      return files;
    }

    function showErrors(errors) {
      if (!errBox) { return; }
      errBox.innerHTML = '';
      if (errBadge) { errBadge.textContent = String(errors.length); }
      for (var i = 0; i < errors.length; i++) {
        var er = errors[i];
        var card = document.createElement('div');
        card.className = 'err-card';
        var head = document.createElement('strong');
        head.textContent = er.file ? (er.file + ', Zeile ' + er.line) : 'Allgemein';
        card.appendChild(head);
        var msg = document.createElement('div');
        msg.textContent = er.message;
        card.appendChild(msg);
        errBox.appendChild(card);
      }
      if (errors.length === 0) { errBox.appendChild(document.createTextNode('Keine Fehler.')); }
    }

    /* Register-Anzeige: R0 bis R15, geaenderte Register blau, Flags dazu. */
    function renderRegisters() {
      if (!regBox || !runtime) { return; }
      regBox.innerHTML = '';
      var i;
      /* Akkumulator (Abitur-Befehle) immer ganz vorne */
      var akkuCell = document.createElement('div');
      akkuCell.className = 'asm-reg asm-akku';
      var akkuName = document.createElement('span');
      akkuName.textContent = 'Akku';
      var akkuVal = document.createElement('span');
      akkuVal.textContent = String(runtime.acc);
      akkuCell.appendChild(akkuName);
      akkuCell.appendChild(akkuVal);
      regBox.appendChild(akkuCell);
      for (i = 0; i < 16; i++) {
        var cell = document.createElement('div');
        cell.className = 'asm-reg';
        if (prevRegs && prevRegs[i] !== runtime.regs[i]) { cell.className += ' changed'; }
        var nameEl = document.createElement('span');
        nameEl.textContent = 'R' + i;
        var valEl = document.createElement('span');
        valEl.textContent = String(runtime.regs[i]);
        cell.appendChild(nameEl);
        cell.appendChild(valEl);
        regBox.appendChild(cell);
      }
      var flags = document.createElement('div');
      flags.className = 'asm-flags';
      var flagDefs = [['gleich', runtime.flags.eq], ['kleiner', runtime.flags.lt], ['größer', runtime.flags.gt]];
      for (i = 0; i < flagDefs.length; i++) {
        var f = document.createElement('span');
        f.className = 'asm-flag' + (flagDefs[i][1] ? ' on' : '');
        f.textContent = flagDefs[i][0];
        flags.appendChild(f);
      }
      var pc = document.createElement('span');
      pc.className = 'asm-flag';
      pc.textContent = 'Befehl ' + String(Math.min(runtime.pc + 1, runtime.instrs.length)) + ' von ' + String(runtime.instrs.length);
      flags.appendChild(pc);
      regBox.appendChild(flags);
      prevRegs = runtime.regs.slice(0);
    }

    /* Zeilennummern mit aktueller Zeile (Einzelschritt), wie in der Java-IDE */
    var gutters = [];
    function setupGutters() {
      var i;
      for (i = 0; i < paneList.length; i++) {
        var pane = paneList[i];
        var ta = pane.querySelector('textarea');
        if (!ta || !ta.parentNode || !ta.parentNode.getAttribute) { continue; }
        var box = document.createElement('div');
        box.className = 'ln-gutter';
        var inner = document.createElement('div');
        inner.className = 'ln-inner';
        box.appendChild(inner);
        ta.parentNode.appendChild(box);
        if (ta.parentNode.classList && typeof ta.parentNode.classList.add === 'function') {
          ta.parentNode.classList.add('has-gutter');
        }
        var rec = { ta: ta, box: box, inner: inner, cells: [], count: 0, pane: pane };
        gutters.push(rec);
        guttersSync();
        ta.addEventListener('input', function () { guttersSync(); });
        ta.addEventListener('scroll', function () { box.scrollTop = ta.scrollTop; });
      }
    }
    function guttersSync() {
      var g;
      for (g = 0; g < gutters.length; g++) {
        var lines = String(gutters[g].ta.value || '').split(NL).length;
        if (lines === gutters[g].count) { continue; }
        gutters[g].inner.innerHTML = '';
        gutters[g].cells = [];
        var i;
        for (i = 1; i <= lines; i++) {
          var cell = document.createElement('div');
          cell.className = 'ln-cell';
          cell.textContent = String(i);
          gutters[g].inner.appendChild(cell);
          gutters[g].cells.push(cell);
        }
        gutters[g].count = lines;
      }
    }
    function clearLineMark() {
      var g;
      for (g = 0; g < gutters.length; g++) {
        var i;
        for (i = 0; i < gutters[g].cells.length; i++) {
          if (gutters[g].cells[i].className.indexOf('ln-cur') >= 0) {
            gutters[g].cells[i].className = 'ln-cell';
          }
        }
      }
    }
    function markCurrentLine() {
      clearLineMark();
      if (!runtime || runtime.halted || !runtime.instrs[runtime.pc]) { return; }
      var instr = runtime.instrs[runtime.pc];
      var g;
      for (g = 0; g < gutters.length; g++) {
        if (gutters[g].pane.getAttribute('data-file-name') === instr.file && gutters[g].cells[instr.line - 1]) {
          gutters[g].cells[instr.line - 1].className = 'ln-cell ln-cur';
        }
      }
    }

    function startRuntime() {
      var files = collectFiles();
      lastParsed = parseAsm(files);
      if (lastParsed.errors.length > 0) {
        showErrors(lastParsed.errors);
        if (stepInfo) { stepInfo.textContent = ''; }
        return null;
      }
      if (lastParsed.instrs.length === 0) {
        showErrors([{ file: '', line: 0, message: 'Dein Programm ist leer. Schreibe z. B. LOAD R1, 5' + NL + 'OUT R1' + NL + 'HALT.' }]);
        return null;
      }
      showErrors([]);
      runtime = createRuntime(lastParsed, inBox ? String(inBox.value || '') : '');
      prevRegs = null;
      return runtime;
    }

    function finishInfo() {
      if (!stepInfo) { return; }
      if (runtime && runtime.halted) {
        if (runtime.error) { stepInfo.textContent = runtime.error.message; }
        else { stepInfo.textContent = 'Programm beendet (HALT) nach ' + runtime.steps + ' Schritten.'; }
      }
    }

    function doRun() {
      if (!startRuntime()) { return; }
      runtime.runAll();
      if (outBox) {
        outBox.textContent = runtime.out !== '' ? runtime.out : (runtime.error ? '' : '(keine Ausgabe: nutze OUT R1 oder OUTS "Text")');
      }
      if (runtime.error) {
        showErrors([runtime.error]);
      }
      renderRegisters();
      markCurrentLine();
      finishInfo();
    }

    function doStep() {
      if (!runtime || runtime.halted) {
        if (!startRuntime()) { return; }
      }
      runtime.step();
      if (outBox) { outBox.textContent = runtime.out !== '' ? runtime.out : (runtime.started ? '(bis hierher: noch keine Ausgabe)' : 'Noch nichts ausgeführt.'); }
      if (runtime.error) { showErrors([runtime.error]); }
      renderRegisters();
      markCurrentLine();
      if (stepInfo) {
        if (runtime.halted && !runtime.error) { stepInfo.textContent = 'Programm beendet (HALT) nach ' + runtime.steps + ' Schritten.'; }
        else if (!runtime.halted) { stepInfo.textContent = 'Schritt ' + runtime.steps + ': als Nächstes ist Zeile ' + (runtime.instrs[runtime.pc] ? runtime.instrs[runtime.pc].line : '?') + ' dran.'; }
      }
    }

    function doRest() {
      if (!runtime || runtime.halted) {
        if (!startRuntime()) { return; }
      }
      runtime.runAll();
      if (outBox) { outBox.textContent = runtime.out !== '' ? runtime.out : '(keine Ausgabe)'; }
      if (runtime.error) { showErrors([runtime.error]); }
      renderRegisters();
      markCurrentLine();
      finishInfo();
    }

    function doStop() {
      runtime = null;
      clearLineMark();
      renderStopped();
      if (stepInfo) { stepInfo.textContent = 'Programm gestoppt.'; }
    }
    function renderStopped() {
      if (regBox) { regBox.innerHTML = ''; }
    }

    runBtn.addEventListener('click', doRun);
    if (stepBtn) { stepBtn.addEventListener('click', doStep); }
    if (restBtn) { restBtn.addEventListener('click', doRest); }
    if (stopBtn) { stopBtn.addEventListener('click', doStop); }
    if (errClear) {
      errClear.addEventListener('click', function () {
        if (errBox) { errBox.innerHTML = ''; errBox.appendChild(document.createTextNode('Keine Fehler.')); }
        if (errBadge) { errBadge.textContent = '0'; }
      });
    }
    setupGutters();

    /* Tabs (▶ Ausgabe, 📖 Befehle): generisch wie in der Java-IDE. */
    var tabBtns = document.querySelectorAll('[data-ctab]');
    var tabPanes = document.querySelectorAll('[data-cpane]');
    function showPane(name) {
      var i;
      for (i = 0; i < tabBtns.length; i++) {
        if (tabBtns[i].getAttribute('data-ctab') === name) { tabBtns[i].className = 'console-tab active'; }
        else if (tabBtns[i].className.indexOf('api-open') < 0) { tabBtns[i].className = 'console-tab'; }
      }
      for (i = 0; i < tabPanes.length; i++) {
        if (tabPanes[i].getAttribute('data-cpane') === name) { tabPanes[i].removeAttribute('hidden'); }
        else { tabPanes[i].setAttribute('hidden', 'hidden'); }
      }
    }
    var ti;
    for (ti = 0; ti < tabBtns.length; ti++) {
      (function (btn) {
        btn.addEventListener('click', function () { showPane(btn.getAttribute('data-ctab')); });
      })(tabBtns[ti]);
    }

    /* Strg + Enter fuehrt aus */
    var i3;
    for (i3 = 0; i3 < paneAreas.length; i3++) {
      paneAreas[i3].addEventListener('keydown', function (ev) {
        if (ev && ev.ctrlKey && (ev.key === 'Enter' || ev.keyCode === 13)) {
          doRun();
        }
      });
    }

    /* Automatisches Speichern wie in der Java-IDE (entprellt, 700 ms). */
    var autoTimer = null;
    function collectSaveSnap() {
      var snap = { files: {} };
      var i;
      for (i = 0; i < paneList.length; i++) {
        var nm = paneList[i].getAttribute('data-file-name');
        var ta = paneList[i].querySelector('textarea');
        if (nm && ta) { snap.files[nm] = ta.value; }
      }
      return snap;
    }
    var savedSnap = collectSaveSnap();
    function buildSaveBody() {
      var params = new URLSearchParams();
      var csrfEl = saveForm ? saveForm.querySelector('input[name="csrf"]') : null;
      if (csrfEl) { params.append('csrf', csrfEl.value); }
      var i;
      for (i = 0; i < paneList.length; i++) {
        var fid = paneList[i].getAttribute('data-file-pane');
        var ta = paneList[i].querySelector('textarea');
        if (fid && ta) { params.append('files[' + fid + ']', ta.value); }
      }
      return params.toString();
    }
    function saveFlash(text, isError) {
      if (!saveInfo) { return; }
      saveInfo.textContent = text;
      saveInfo.className = 'save-status' + (isError ? ' save-err' : '');
    }
    function autoSave() {
      if (!saveForm || typeof fetch !== 'function' || typeof URLSearchParams !== 'function') { return; }
      var action = saveForm.getAttribute('action');
      if (!action) { return; }
      fetch(action, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: buildSaveBody(), keepalive: true }).then(function () {
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
      var pe;
      for (pe = 0; pe < paneAreas.length; pe++) { paneAreas[pe].addEventListener('input', autoSaveSoon); }
      var guardForms = [];
      var selList = ['form.ide-side-new', 'form.ide-file-del'];
      var si;
      for (si = 0; si < selList.length; si++) {
        var gfList = document.querySelectorAll(selList[si]);
        var gf;
        for (gf = 0; gf < gfList.length; gf++) { guardForms.push(gfList[gf]); }
      }
      var gf2;
      for (gf2 = 0; gf2 < guardForms.length; gf2++) {
        guardForms[gf2].addEventListener('submit', function () { autoSave(); });
      }
    }
  }

  var api = { run: run, parseAsm: parseAsm, createRuntime: createRuntime, OPS: OPS };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (typeof document !== 'undefined' && document.getElementById) {
    try { wireUi(); } catch (e) { /* Seite ohne Assembler-Elemente: nichts zu tun */ }
  }
})();
