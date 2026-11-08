/* InfoCraft: Tests fuer die Assembler-VM (public/assets/js/asm-vm.js).
 * Ausfuehrung: node tools/asm-vm-tests.js  (Exit 1 bei Fehlern).
 */
var A = require('../public/assets/js/asm-vm.js');
var NL = String.fromCharCode(10);
var failed = 0;
var count = 0;

function t(name, program, input, check) {
  count++;
  var res;
  try {
    res = A.run(program, input);
  } catch (e) {
    failed++;
    console.log('FAIL ' + name + ' -> VM stuerzt ab: ' + (e && e.message ? e.message : String(e)));
    return;
  }
  try {
    check(res);
    console.log('ok   ' + name);
  } catch (e) {
    failed++;
    console.log('FAIL ' + name + ' -> ' + e.message);
    console.log('     output=' + JSON.stringify(res.output));
    console.log('     errors=' + JSON.stringify(res.errors));
  }
}

function eq(actual, expected, what) {
  if (actual !== expected) { throw new Error(what + ': erwartet ' + JSON.stringify(expected) + ', erhalten ' + JSON.stringify(actual)); }
}

function err1(r, lineHint, part) {
  if (r.ok) { throw new Error('erwartet Fehler, Programm lief ok'); }
  if (r.errors.length !== 1) { throw new Error('erwartet genau 1 Fehler, erhalten ' + r.errors.length); }
  if (lineHint !== null && r.errors[0].line !== lineHint) { throw new Error('erwartet Zeile ' + lineHint + ', erhalten ' + r.errors[0].line); }
  if (part && r.errors[0].message.indexOf(part) === -1) {
    throw new Error('Fehlermeldung soll "' + part + '" enthalten, erhaelt: ' + r.errors[0].message);
  }
}

/* --- Grundlagen --- */
t('Addition', 'LOAD R1, 5' + NL + 'LOAD R2, 7' + NL + 'ADD R1, R2' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '12' + NL, 'output');
  eq(r.regs[1], 12, 'R1');
});
t('MOV-Alias', 'MOV R3, 4' + NL + 'OUT R3' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '4' + NL, 'output');
});
t('SUBUndNegativeZahl', 'LOAD R1, -3' + NL + 'SUB R1, -10' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '7' + NL, 'output');
});
t('MULUndDIV', 'LOAD R1, 7' + NL + 'MUL R1, 6' + NL + 'DIV R1, 4' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '10' + NL, 'output (42 / 4 = 10 ganzzahlig)');
});
t('MOD', 'LOAD R1, 17' + NL + 'MOD R1, 5' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '2' + NL, 'output (17 % 5)');
});
t('INCDEC', 'LOAD R1, 10' + NL + 'INC R1' + NL + 'INC R1' + NL + 'DEC R1' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '11' + NL, 'output');
});
t('OUTS', 'OUTS "Hallo!"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Hallo!', 'output');
});
t('KommentareUndLeerzeilen', '; Kommentar' + NL + NL + 'LOAD R1, 2 ; inline' + NL + 'OUT R1' + NL + '; Ende' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '2' + NL, 'output');
});

/* --- Marken und Spruenge --- */
t('SchleifeMitMarke', 'LOAD R1, 3' + NL + 'schleife:' + NL + 'OUT R1' + NL + 'DEC R1' + NL + 'JNZ schleife' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '3' + NL + '2' + NL + '1' + NL, 'output');
});
t('JMP', 'JMP ende' + NL + 'OUT R1' + NL + 'ende:' + NL + 'OUTS "Ende"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Ende', 'output');
});
t('CMPMitJGT', 'LOAD R1, 5' + NL + 'CMP R1, 3' + NL + 'JGT gross' + NL + 'OUTS "kleiner"' + NL + 'HALT' + NL + 'gross:' + NL + 'OUTS "groesser"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'groesser', 'output');
});
t('CMPMitJLT', 'LOAD R1, 5' + NL + 'CMP R1, 9' + NL + 'JLT klein' + NL + 'OUTS "nicht kleiner"' + NL + 'HALT' + NL + 'klein:' + NL + 'OUTS "kleiner"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'kleiner', 'output');
});
t('JZBeiGleich', 'LOAD R1, 4' + NL + 'CMP R1, 4' + NL + 'JZ gleich' + NL + 'OUTS "ungleich"' + NL + 'HALT' + NL + 'gleich:' + NL + 'OUTS "gleich"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'gleich', 'output');
});
t('MarkeMitBefehlInZeile', 'LOAD R1, 3' + NL + 'start: DEC R1' + NL + 'JNZ start' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '0' + NL, 'output');
  eq(r.steps, 9, 'Schritte (LOAD, 3x DEC/JNZ, OUT, HALT)');
});

/* --- IN: Eingabefeld --- */
t('INZweiZahlen', 'IN R1' + NL + 'IN R2' + NL + 'ADD R1, R2' + NL + 'OUT R1' + NL + 'HALT', '3, 12', r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '15' + NL, 'output');
});
t('INOhneEingabe', 'IN R1' + NL + 'OUT R1' + NL + 'HALT', null, r => {
  err1(r, 1, 'Eingabefeld');
});

/* --- Fehler --- */
t('UnbekannterBefehl', 'FOO R1' + NL + 'HALT', null, r => {
  err1(r, 1, 'FOO');
});
t('UnbekanntesRegister', 'LOAD X1, 5' + NL + 'HALT', null, r => {
  err1(r, 1, 'Register');
});
t('RegisterAusserhalb', 'LOAD R16, 5' + NL + 'HALT', null, r => {
  err1(r, 1, 'R0 bis R15');
});
t('FehlendesKomma', 'LOAD R1 5' + NL + 'HALT', null, r => {
  err1(r, 1, 'Komma');
});
t('FehlendeMarke', 'JMP nirgendwo' + NL + 'HALT', null, r => {
  err1(r, 1, 'Marke');
});
t('DoppelteMarke', 'a:' + NL + 'HALT' + NL + 'a:' + NL + 'HALT', null, r => {
  err1(r, 3, 'schon einmal');
});
t('OUTSOhneAnfuehrungszeichen', 'OUTS Hallo' + NL + 'HALT', null, r => {
  err1(r, 1, 'Anfuehrungszeichen');
});
t('DivisionDurchNull', 'LOAD R1, 8' + NL + 'DIV R1, 0' + NL + 'HALT', null, r => {
  err1(r, 2, 'Division durch 0');
});
t('OhneHALT', 'LOAD R1, 5' + NL + 'OUT R1', null, r => {
  err1(r, null, 'HALT');
});
t('LeeresProgramm', '; nur ein Kommentar', null, r => {
  err1(r, null, 'leer');
});
t('Endlosschleife', 'loop:' + NL + 'JMP loop', null, r => {
  err1(r, null, 'Endlosschleife');
});

/* --- Mehrere Dateien: Reihenfolge und globale Marken --- */
t('MehrereDateien', [
  { name: 'a.asm', content: 'LOAD R1, 5' + NL + 'CALL weiter' + NL + 'HALT' },
  { name: 'b.asm', content: 'weiter: OUT R1' + NL + 'HALT' }
], null, r => {
  err1(r, 2, 'Befehl');
});
t('MehrereDateienGlobalMarke', [
  { name: 'a.asm', content: 'LOAD R1, 5' + NL + 'JMP weiter' + NL + 'HALT' },
  { name: 'b.asm', content: 'weiter: OUT R1' + NL + 'HALT' }
], null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '5' + NL, 'output');
});

/* --- Registermaschine im Detail --- */
t('RegisterAlle16', 'LOAD R15, 99' + NL + 'OUT R15' + NL + 'OUT R0' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '99' + NL + '0' + NL, 'output');
});
t('RegisterKopieren', 'LOAD R1, 42' + NL + 'LOAD R2, R1' + NL + 'ADD R2, R1' + NL + 'OUT R2' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '84' + NL, 'output');
});
t('FlagsNachRechnung', 'LOAD R1, -4' + NL + 'ADD R1, 4' + NL + 'JZ nullstelle' + NL + 'OUTS "nicht null"' + NL + 'HALT' + NL + 'nullstelle:' + NL + 'OUTS "null"' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'null', 'output');
});
t('ParseOhneLauf', 'LOAD R1, 5' + NL + 'HALT', null, r => {
  eq(r.ok, true, 'ok');
  eq(r.regs.length, 16, '16 Register');
});

/* --- createRuntime: Einzelschritt --- */
count++;
(function () {
  var parsed = A.parseAsm('LOAD R1, 5' + NL + 'INC R1' + NL + 'OUT R1' + NL + 'HALT');
  var rt = A.createRuntime(parsed, '');
  rt.step();
  var a = rt.regs[1];
  rt.step();
  var b = rt.regs[1];
  rt.runAll();
  if (a === 5 && b === 6 && rt.out === '6' + NL && rt.halted) {
    console.log('ok   Einzelschritt');
  } else {
    failed++;
    console.log('FAIL Einzelschritt -> regs ' + a + '/' + b + ', out ' + JSON.stringify(rt.out));
  }
})();

count++;
(function () {
  var parsed = A.parseAsm('LOAD R1, 5' + NL + 'OUTS "Hallo\\nWelt"' + NL + 'HALT');
  var rt = A.createRuntime(parsed, '');
  rt.runAll();
  if (rt.out === 'Hallo' + NL + 'Welt') {
    console.log('ok   OUTSMitEscape');
  } else {
    failed++;
    console.log('FAIL OUTSMitEscape -> out ' + JSON.stringify(rt.out));
  }
})();

if (failed === 0) { console.log('Alle ' + count + ' ASM-Tests bestanden.'); process.exit(0); }
console.log(failed + ' ASM-Tests fehlgeschlagen.'); process.exit(1);
