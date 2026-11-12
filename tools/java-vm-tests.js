/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Tests der Java-Simulation
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
/* InfoCraft: Tests fuer den Java-Interpreter (public/assets/js/java-vm.js).
 * Ausfuehrung: node tools/java-vm-tests.js  (Exit 1 bei Fehlern).
 */
delete global.window;
var V = require('../public/assets/js/java-vm.js');
var NL = String.fromCharCode(10);
var failed = 0;
var count = 0;

function t(name, files, check) {
  count++;
  var res;
  try {
    res = V.run(files);
  } catch (e) {
    failed++;
    console.log('FAIL ' + name + ' -> Interpreter stuerzt ab: ' + (e && e.message ? e.message : String(e)));
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

function mainOf(body) {
  return 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + body + NL + '    }' + NL + '}';
}

function err1(r, kind, lineHint) {
  if (r.ok) { throw new Error('erwartet Fehler, Programm lief ok'); }
  if (r.errors.length !== 1) { throw new Error('erwartet genau 1 Fehler, erhalten ' + r.errors.length); }
  if (r.errors[0].kind !== kind) { throw new Error('erwartet kind=' + kind + ', erhalten ' + r.errors[0].kind); }
  if (lineHint && r.errors[0].line !== lineHint) { throw new Error('erwartet Zeile ' + lineHint + ', erhalten ' + r.errors[0].line); }
  if (!r.errors[0].hint || r.errors[0].hint.length < 5) { throw new Error('Hilfestellung fehlt'); }
}

/* --- Hello World & Ausgaben --- */
t('HelloWorld', { 'Main.java': mainOf('        System.out.println("Hallo Welt");') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Hallo Welt' + NL, 'output');
});
t('PrintOhneZeilenumbruch', { 'Main.java': mainOf('        System.out.print("A");' + NL + '        System.out.print("B");' + NL + '        System.out.println();') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'AB' + NL, 'output');
});
t('Stderr', { 'Main.java': mainOf('        System.err.println("Warnung");' + NL + '        System.out.println("ok");') }, r => {
  eq(r.ok, true, 'ok bleibt trotz System.err true');
  eq(r.output, 'ok' + NL, 'stdout');
  if (r.errors.length !== 1 || r.errors[0].kind !== 'stderr' || r.errors[0].message !== 'Warnung' + NL) {
    throw new Error('stderr-Karte fehlt: ' + JSON.stringify(r.errors));
  }
});

/* --- Arithmetik & Typen --- */
t('IntDivisionVsDouble', { 'Main.java': mainOf('        System.out.println(7 / 2);' + NL + '        System.out.println(7.0 / 2);' + NL + '        System.out.println(5.0);' + NL + '        System.out.println(7 % 3);' + NL + '        System.out.println(2.5 + 0.5);') }, r => {
  eq(r.output, '3' + NL + '3.5' + NL + '5.0' + NL + '1' + NL + '3.0' + NL, 'int-Division truncatet, double-Format');
});
t('StringKonkatenation', { 'Main.java': mainOf('        System.out.println("x" + 1 + 2);' + NL + '        System.out.println(1 + 2 + "x");' + NL + '        System.out.println("a" + null);') }, r => {
  eq(r.output, 'x12' + NL + '3x' + NL + 'anull' + NL, 'String-Konkatenation von links nach rechts');
});
t('CharArithmetik', { 'Main.java': mainOf('        char c = 65;' + NL + '        System.out.println((char) c);' + NL + '        System.out.println(c + 1);' + NL + '        System.out.println((char) (c + 1));') }, r => {
  eq(r.output, 'A' + NL + '66' + NL + 'B' + NL, 'char+int ergibt int, (char) cast');
});
t('IntCastZuweisung', { 'Main.java': mainOf('        double d = 3.9;' + NL + '        int ganze = (int) d;' + NL + '        System.out.println(ganze);') }, r => {
  eq(r.output, '3' + NL, '(int) cast');
});
t('DoubleOhneCastFehler', { 'Main.java': mainOf('        double d = 3.9;' + NL + '        int ganze = d;') }, r => {
  err1(r, 'runtime');
});
t('Ternary', { 'Main.java': mainOf('        int alter = 20;' + NL + '        System.out.println(alter >= 18 ? "volljaehrig" : "minderjaehrig");') }, r => {
  eq(r.output, 'volljaehrig' + NL, 'Bedingungsoperator');
});

/* --- Kontrollfluss --- */
t('Schleifen', { 'Main.java': mainOf(
  '        int summe = 0;' + NL +
  '        for (int i = 1; i <= 5; i++) { summe += i; }' + NL +
  '        System.out.println(summe);' + NL +
  '        int j = 10;' + NL +
  '        while (j > 8) { j--; }' + NL +
  '        System.out.println(j);' + NL +
  '        int k = 0;' + NL +
  '        do { k++; } while (k < 3);' + NL +
  '        System.out.println(k);' + NL +
  '        for (int i = 0; i < 5; i++) { if (i == 2) { break; } System.out.print(i); }' + NL +
  '        System.out.println();'
) }, r => {
  eq(r.output, '15' + NL + '8' + NL + '3' + NL + '01' + NL, 'for/while/do-while/break');
});
t('IfBedingungKeinBoolean', { 'Main.java': mainOf('        int x = 5;' + NL + '        if (x) { System.out.println("ja"); }') }, r => {
  err1(r, 'runtime');
});
t('Endlosschleife', { 'Main.java': mainOf('        while (true) { }') }, r => {
  err1(r, 'runtime');
});

/* --- Arrays --- */
t('ArrayUndBounds', { 'Main.java': mainOf(
  '        int[] z = new int[3];' + NL +
  '        z[0] = 5; z[2] = 7;' + NL +
  '        System.out.println(z.length);' + NL +
  '        int[] w = {1, 2, 3};' + NL +
  '        System.out.println(w[1] + w[2]);'
) }, r => {
  eq(r.output, '3' + NL + '5' + NL, 'Arrays');
});
t('ArrayOutOfBounds', { 'Main.java': mainOf('        int[] z = new int[3];' + NL + '        System.out.println(z[5]);') }, r => {
  err1(r, 'runtime', 4);
});
t('NegativesArray', { 'Main.java': mainOf('        int n = -1;' + NL + '        int[] z = new int[n];') }, r => {
  err1(r, 'runtime');
});

/* --- Methoden, Konstruktoren, Vererbung --- */
var tierFiles = {
  'Tier.java': 'public class Tier {' + NL + '    private String name;' + NL + '    public Tier(String name) { this.name = name; }' + NL + '    public String getName() { return name; }' + NL + '    public String gibLaut() { return "..."; }' + NL + '    public String toString() { return "Tier(" + name + ")"; }' + NL + '}',
  'Hund.java': 'public class Hund extends Tier {' + NL + '    public Hund(String name) { super(name); }' + NL + '    public String gibLaut() { return super.gibLaut() + " Wuff"; }' + NL + '}',
  'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        Hund h = new Hund("Bello");' + NL + '        System.out.println(h.gibLaut());' + NL + '        System.out.println(h);' + NL + '        System.out.println(h.getName());' + NL + '    }' + NL + '}'
};
t('VererbungSuperToString', tierFiles, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '... Wuff' + NL + 'Tier(Bello)' + NL + 'Bello' + NL, 'Vererbung, super-Aufruf, toString');
});
t('StatischeFelderMethoden', {
  'Zaehler.java': 'public class Zaehler {' + NL + '    private static int anzahl = 0;' + NL + '    public static void erhoehe() { anzahl++; }' + NL + '    public static int getAnzahl() { return anzahl; }' + NL + '}',
  'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        Zaehler.erhoehe();' + NL + '        Zaehler.erhoehe();' + NL + '        System.out.println(Zaehler.getAnzahl());' + NL + '    }' + NL + '}'
}, r => {
  eq(r.output, '2' + NL, 'statische Methoden und Felder');
});
t('Ueberladen', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        System.out.println(addiere(2, 3));' + NL + '        System.out.println(addiere(2, 3, 4));' + NL + '    }' + NL + '    public static int addiere(int a, int b) { return a + b; }' + NL + '    public static int addiere(int a, int b, int c) { return a + b + c; }' + NL + '}' }, r => {
  eq(r.output, '5' + NL + '9' + NL, 'Ueberladung nach Parameterzahl');
});
t('FehlendesReturn', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        System.out.println(istPositiv(-3));' + NL + '    }' + NL + '    public static boolean istPositiv(int x) { if (x > 0) { return true; } }' + NL + '}' }, r => {
  err1(r, 'runtime');
});
t('Rekursion', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        System.out.println(fakultaet(5));' + NL + '    }' + NL + '    public static int fakultaet(int n) { if (n <= 1) { return 1; } return n * fakultaet(n - 1); }' + NL + '}' }, r => {
  eq(r.output, '120' + NL, 'Rekursion');
});
t('StackOverflow', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        System.out.println(endelos(1));' + NL + '    }' + NL + '    public static int endelos(int n) { return endelos(n + 1); }' + NL + '}' }, r => {
  err1(r, 'runtime');
});
t('NPE', { 'Main.java': mainOf('        String s = null;' + NL + '        System.out.println(s.length());') }, r => {
  err1(r, 'runtime');
});
t('UnbekannteMethode', { 'Main.java': mainOf('        gruessen();') }, r => {
  err1(r, 'runtime');
});

/* --- String-/Math-/Integer-Methoden --- */
t('StringMethoden', { 'Main.java': mainOf(
  '        String s = "Hallo InfoCraft";' + NL +
  '        System.out.println(s.length());' + NL +
  '        System.out.println(s.substring(6));' + NL +
  '        System.out.println(s.charAt(1));' + NL +
  '        System.out.println(s.indexOf("Craft"));' + NL +
  '        System.out.println(s.toUpperCase());' + NL +
  '        System.out.println("anna".equals("anna"));' + NL +
  '        System.out.println("anna".equalsIgnoreCase("ANNA"));'
) }, r => {
  eq(r.output, '15' + NL + 'InfoCraft' + NL + 'a' + NL + '10' + NL + 'HALLO INFOCRAFT' + NL + 'true' + NL + 'true' + NL, 'String-Methoden');
});
t('EqualsStattGleichGleich', { 'Main.java': mainOf('        String a = "Anna";' + NL + '        String b = "Anna";' + NL + '        System.out.println(a == b);') }, r => {
  err1(r, 'runtime');
});
t('MathUndInteger', { 'Main.java': mainOf(
  '        System.out.println(Math.abs(-7));' + NL +
  '        System.out.println(Math.max(3, 9));' + NL +
  '        System.out.println((int) Math.pow(2, 10));' + NL +
  '        System.out.println(Integer.parseInt("42") + 1);' + NL +
  '        System.out.println(String.valueOf(123));'
) }, r => {
  eq(r.output, '7' + NL + '9' + NL + '1024' + NL + '43' + NL + '123' + NL, 'Math/Integer/String.valueOf');
});
t('NumberFormatException', { 'Main.java': mainOf('        System.out.println(Integer.parseInt("abc"));') }, r => {
  err1(r, 'runtime');
});

/* --- Fehlerfaelle: Syntax & Uebersetzung --- */
t('SemikolonFehlt', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        int x = 5' + NL + '    }' + NL + '}' }, r => {
  err1(r, 'syntax', 4);
});
t('KlammerOffen', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        System.out.println("x");' + NL + '    }' + NL + '' }, r => {
  err1(r, 'syntax');
});
/* #91: Endet der Quelltext mitten im Ausdruck (z. B. Anweisung ohne
 * schliessende Klammer am Dateiende), sagt die VM jetzt klar bescheid
 * statt mit "Unerwartet: Dateiende" auf fruehere Zeilen zu verweisen. */
t('AusdruckEndetAmDateiende', { 'Main.java': 'Auto a = new Auto("x");' + NL + 'a.fahren(' + NL }, r => {
  err1(r, 'syntax');
  if (r.errors[0].message.indexOf('endet mitten') < 0) { throw new Error('Meldung sollte "endet mitten in einem Ausdruck" enthalten'); }
  if (r.errors[0].hint.indexOf(')') < 0 || r.errors[0].hint.indexOf('Semikolon') < 0) { throw new Error('Hinweis sollte ) und Semikolon nennen'); }
});
t('AusdruckEndetAmDateiendeKlarerHint', { 'Main.java': 'System.out.println("Hi"' + NL }, r => {
  err1(r, 'syntax');
  if (r.errors[0].message.indexOf('Unerwartet: Dateiende') >= 0) { throw new Error('Meldung sollte nicht mehr "Unerwartet: Dateiende" sein'); }
});
t('KlassennameDateiname', { 'Falsch.java': 'public class AndererName {' + NL + '    public static void main(String[] args) { }' + NL + '}' }, r => {
  err1(r, 'compile');
});
t('UnbekannteKlasseBeiNew', { 'Main.java': mainOf('        Auto a = new Auto();') }, r => {
  err1(r, 'compile');
});
t('OberklasseFehlt', { 'Main.java': 'public class Main extends Fehlt {' + NL + '    public static void main(String[] args) { }' + NL + '}' }, r => {
  err1(r, 'compile');
});
/* Ohne main kein Fehler mehr: Stattdessen laesst sich das Projekt ueber die
 * Objektkonsole interaktiv bedienen (Session wird mitgeliefert). */
t('MainFehlt', { 'NurSo.java': 'public class NurSo {' + NL + '    public int x = 1;' + NL + '}' }, r => {
  eq(r.ok, true, 'ok');
  eq(r.ranMain, false, 'ranMain');
  if (!r.session) { throw new Error('Session erwartet, damit Objektkonsole nutzbar ist'); }
  if (r.session.hasMain) { throw new Error('hasMain sollte false sein'); }
});
t('ScannerHinweis', { 'Main.java': 'import java.util.Scanner;' + NL + 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        Scanner sc = new Scanner(System.in);' + NL + '    }' + NL + '}' }, r => {
  err1(r, 'compile');
  if (r.errors[0].message.indexOf('Scanner') < 0) { throw new Error('Scanner-Hinweis erwartet'); }
});
t('SwitchHinweis', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        int x = 1;' + NL + '        switch (x) { }' + NL + '    }' + NL + '}' }, r => {
  err1(r, 'compile');
});
t('VoidMethodeAlsWert', { 'Main.java': 'public class Main {' + NL + '    public static void main(String[] args) {' + NL + '        int x = sageNichts();' + NL + '    }' + NL + '    public static void sageNichts() { }' + NL + '}' }, r => {
  if (r.ok) { throw new Error('erwartet Fehler'); }
});
t('StringArgVerkettetMitZahl', { 'Main.java': mainOf('        double preis = 1.5;' + NL + '        System.out.println("Preis: " + preis + " EUR");') }, r => {
  eq(r.output, 'Preis: 1.5 EUR' + NL, 'double in String-Konkatenation');
});

/* --- Mini-Datenbank (eingebaute Klasse Database) --- */

/* Beispieldatenbank als Seed (#45): SQLite-Syntax mit PRIMARY KEY, NOT NULL,
 * REFERENCES und Kommentaren muss die Mini-Datenbank laden koennen. */
V.setDbSeed([
  '-- Vorlage: Schule (SQL-Werkstatt)',
  'CREATE TABLE lehrkraefte (',
  '    id INTEGER PRIMARY KEY,',
  '    name TEXT NOT NULL,',
  '    fach TEXT NOT NULL',
  ');',
  'CREATE TABLE kursteilnahmen (',
  '    id INTEGER PRIMARY KEY,',
  '    kurs_id INTEGER NOT NULL REFERENCES kurse (id),',
  '    note REAL',
  ');',
  '/* Kommentar */',
  "INSERT INTO lehrkraefte (id, name, fach) VALUES (1, 'Frau Fischer', 'Informatik');",
  'INSERT INTO kursteilnahmen (id, kurs_id, note) VALUES (1, 1, 1.7), (2, 1, NULL);'
].join(NL));
t('DbSeedSqliteConstraints', { 'Main.java': mainOf(
  'System.out.println(Database.count("SELECT * FROM lehrkraefte"));' + NL +
  'System.out.println(Database.count("SELECT * FROM kursteilnahmen"));' + NL +
  'System.out.println(Database.tables());') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL + '2' + NL + 'lehrkraefte, kursteilnahmen' + NL, 'seed mit constraints und kommentaren');
});
/* Seed wieder entfernen: Folgetests starten mit leerer Datenbank. */
V.setDbSeed('');

t('DbCreateInsertSelect', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE schueler (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Database.query("SELECT * FROM schueler"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name | punkte' + NL + '-----+-------' + NL + 'Anna | 12    ' + NL + 'Ben  | 8     ' + NL, 'tabellenausgabe');
});
t('DbSelectWhere', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Database.query("SELECT name FROM s WHERE punkte > 10"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'gefilterte ausgabe');
});
t('DbSelectOrderByLimit', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Database.query("SELECT name FROM s ORDER BY punkte DESC LIMIT 1"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'sortiert und begrenzt');
});
t('DbAnzahlUndCount', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Database.count("SELECT * FROM s WHERE punkte > 10"));' + NL +
  '        System.out.println(Database.query("SELECT COUNT(*) FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL + 'COUNT(*)' + NL + '--------' + NL + '2       ' + NL, 'anzahl und COUNT(*)');
});
t('DbWert', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        String beste = Database.value("SELECT name FROM s ORDER BY punkte DESC");' + NL +
  '        System.out.println("Beste: " + beste);' + NL +
  '        System.out.println("[" + Database.value("SELECT name FROM s WHERE punkte > 99") + "]");') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Beste: Anna' + NL + '[]' + NL, 'wert und leerer wert');
});
t('DbUpdate', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  "        Database.execute(\"UPDATE s SET punkte = 20 WHERE name = 'Ben'\");" + NL +
  '        System.out.println(Database.query("SELECT * FROM s WHERE punkte >= 20"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name | punkte' + NL + '-----+-------' + NL + 'Ben  | 20    ' + NL, 'nach update');
});
t('DbDelete', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        Database.execute("DELETE FROM s WHERE punkte < 10");' + NL +
  '        System.out.println(Database.count("SELECT * FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL, 'zeilen geloescht');
});
t('DbInsertSpaltenlisteNull', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s (name) VALUES ('Caesar')\");" + NL +
  '        String erg = Database.query("SELECT * FROM s");' + NL +
  '        if (erg.indexOf("Caesar") < 0) { System.out.println("FEHLER"); }' + NL +
  '        else { System.out.println("OK"); }' + NL +
  '        System.out.println(Database.value("SELECT punkte FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'OK' + NL + 'null' + NL, 'nicht genannte spalte ist null');
});
t('DbLike', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna'), ('Ben'), ('Clara')\");" + NL +
  '        System.out.println(Database.query("SELECT name FROM s WHERE name LIKE ' + "'%an%'" + '"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'like mit platzhalter');
});
t('DbUndOder', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Database.execute(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8), ('Clara', 3)\");" + NL +
  '        System.out.println(Database.count("SELECT * FROM s WHERE punkte > 5 AND punkte < 20"));' + NL +
  "        System.out.println(Database.count(\"SELECT * FROM s WHERE name = 'Anna' OR name = 'Clara'\"));") }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '2' + NL + '2' + NL, 'AND und OR');
});
t('DbTabellen', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE schueler (name TEXT)");' + NL +
  '        Database.execute("CREATE TABLE produkte (titel TEXT)");' + NL +
  '        System.out.println(Database.tables());') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'schueler, produkte' + NL, 'tabellenliste');
});
t('DbResetProLauf', { 'Main.java': mainOf(
  '        System.out.println(Database.tables());') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '(noch keine Tabellen)' + NL, 'datenbank ist pro lauf neu');
});
t('DbFehlerTabelleUnbekannt', { 'Main.java': mainOf(
  '        System.out.println(Database.query("SELECT * FROM fehlt_ganz"));') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('gibt es noch nicht') < 0) { throw new Error('Tabellen-Fehlermeldung erwartet'); }
});
t('DbFehlerSpalteUnbekannt', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT)");' + NL +
  '        System.out.println(Database.query("SELECT falsch FROM s"));') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('Spalte') < 0) { throw new Error('Spalten-Fehlermeldung erwartet'); }
});
t('DbFehlerSelectBeiAusfuehren', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT)");' + NL +
  '        Database.execute("SELECT * FROM s");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('query') < 0) { throw new Error('Hinweis auf query erwartet'); }
});
t('DbFehlerFehlendeAnfuehrungszeichen', { 'Main.java': mainOf(
  '        Database.execute("CREATE TABLE s (name TEXT)");' + NL +
  '        Database.execute("INSERT INTO s VALUES (Anna)");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('gültiger SQL-Wert') < 0) { throw new Error('Literal-Fehlermeldung erwartet'); }
});
t('DbFehlerMethodeUnbekannt', { 'Main.java': mainOf(
  '        Database.speichern("irgendwas");') }, r => {
  err1(r, 'runtime');
});

/* --- Objektkonsole: interaktive Sitzung (Objekte erzeugen, Methoden, Objektdiagramm) --- */
function sesOk(name, files, check) {
  count++;
  var open;
  try {
    open = V.openSession(files);
  } catch (e) {
    failed++;
    console.log('FAIL ' + name + ' -> openSession stuerzt ab: ' + (e && e.message ? e.message : String(e)));
    return;
  }
  try {
    if (!open.ok || !open.session) { throw new Error('Sitzung sollte aufgehen, errors=' + JSON.stringify(open.errors)); }
    check(open.session);
    console.log('ok   ' + name);
  } catch (e) {
    failed++;
    console.log('FAIL ' + name + ' -> ' + e.message);
  }
}

function labelsOf(compl) {
  var labels = [];
  var i;
  for (i = 0; i < compl.items.length; i++) { labels.push(compl.items[i].label); }
  return labels;
}

var AUTO_JAVA = 'public class Auto {' + NL +
  '    private String marke;' + NL +
  '    private int km;' + NL +
  '    public Auto(String markeStart) {' + NL +
  '        marke = markeStart;' + NL +
  '        km = 0;' + NL +
  '    }' + NL +
  '    public String getMarke() { return marke; }' + NL +
  '    public void fahren(int strecke) {' + NL +
  '        km = km + strecke;' + NL +
  '        System.out.println(marke + " faehrt " + strecke + " km");' + NL +
  '    }' + NL +
  '    public int getKm() { return km; }' + NL +
  '}';

sesOk('SessionObjektErzeugen', { 'Auto.java': AUTO_JAVA }, s => {
  var r1 = s.evalLine('Auto a = new Auto("BMW");');
  if (r1.errors.length > 0) { throw new Error('Fehler beim Objekt erzeugen: ' + JSON.stringify(r1.errors)); }
  var objs = s.objects();
  if (objs.length !== 1) { throw new Error('1 Objekt erwartet, ' + objs.length); }
  if (objs[0].name !== 'a' || objs[0].cls !== 'Auto') { throw new Error('Objekt-Name/Klasse falsch: ' + JSON.stringify(objs[0])); }
  var f = objs[0].fields;
  if (f.length !== 2 || f[0].name !== 'marke' || f[0].text !== '"BMW"') { throw new Error('Felder falsch: ' + JSON.stringify(f)); }
  var names = s.classNames();
  if (names.length !== 1 || names[0] !== 'Auto') { throw new Error('classNames falsch: ' + JSON.stringify(names)); }
});

sesOk('SessionMethodeUndErgebnis', { 'Auto.java': AUTO_JAVA }, s => {
  s.evalLine('Auto a = new Auto("BMW");');
  var r = s.evalLine('a.getMarke()');
  if (r.errors.length > 0) { throw new Error('Fehler: ' + JSON.stringify(r.errors)); }
  if (r.result !== 'BMW' || r.resultType !== 'String') { throw new Error('Ergebnis falsch: ' + JSON.stringify(r.result) + '/' + r.resultType); }
  var r2 = s.evalLine('a.fahren(100);');
  if (r2.output.indexOf('faehrt 100') < 0) { throw new Error('Ausgabe fehlt: ' + JSON.stringify(r2.output)); }
  var r3 = s.evalLine('a.getKm()');
  if (r3.result !== '100') { throw new Error('km erwartet 100, erhalten ' + JSON.stringify(r3.result)); }
});

sesOk('SessionZustandsaenderung', { 'Auto.java': AUTO_JAVA }, s => {
  s.evalLine('Auto a = new Auto("BMW");');
  s.evalLine('a.fahren(40);');
  var objs = s.objects();
  if (objs[0].fields[1].text !== '40') { throw new Error('km=40 erwartet im Objektdiagramm: ' + JSON.stringify(objs[0].fields)); }
});

sesOk('SessionOhneSemikolonUndRechnen', { 'Auto.java': AUTO_JAVA }, s => {
  var r = s.evalLine('2 + 3 * 4');
  if (r.result !== '14') { throw new Error('14 erwartet, ' + JSON.stringify(r.result)); }
  s.evalLine('int x = 5;');
  var r2 = s.evalLine('x * 2');
  if (r2.result !== '10') { throw new Error('10 erwartet, ' + JSON.stringify(r2.result)); }
});

sesOk('SessionFehlerMitHilfe', { 'Auto.java': AUTO_JAVA }, s => {
  s.evalLine('Auto a = new Auto("BMW");');
  var r = s.evalLine('a.hupen();');
  if (r.errors.length !== 1) { throw new Error('1 Fehler erwartet'); }
  if (r.errors[0].hint.length < 5) { throw new Error('Hilfestellung fehlt'); }
  var r2 = s.evalLine('int ;');
  if (r2.errors.length !== 1) { throw new Error('Syntaxfehler erwartet'); }
});

sesOk('SessionAutocomplete', { 'Auto.java': AUTO_JAVA }, s => {
  s.evalLine('Auto a = new Auto("BMW");');
  var c1 = s.complete('a.');
  var labels = labelsOf(c1);
  if (labels.indexOf('getMarke(') < 0 || labels.indexOf('marke') < 0 || labels.indexOf('fahren(') < 0) {
    throw new Error('Mitglieder fehlen: ' + JSON.stringify(labels));
  }
  var c2 = s.complete('Aut');
  labels = labelsOf(c2);
  if (labels.indexOf('Auto') < 0) { throw new Error('Klassenname fehlt: ' + JSON.stringify(labels)); }
  if (c2.from !== 0) { throw new Error('from=0 erwartet'); }
  var c3 = s.complete('a.fa');
  labels = labelsOf(c3);
  if (labels.length !== 1 || labels[0] !== 'fahren(') { throw new Error('fahren( erwartet: ' + JSON.stringify(labels)); }
  var c4 = s.complete('Auto.');
  labels = labelsOf(c4);
  if (labels.indexOf('new Auto(') < 0) { throw new Error('Konstruktor-Vorschlag fehlt: ' + JSON.stringify(labels)); }
  var c5 = s.complete('System.out.');
  labels = labelsOf(c5);
  if (labels.indexOf('println(') < 0) { throw new Error('println fehlt: ' + JSON.stringify(labels)); }
  var c6 = s.complete('Database.');
  labels = labelsOf(c6);
  if (labels.indexOf('query(') < 0) { throw new Error('query fehlt: ' + JSON.stringify(labels)); }
});

sesOk('SessionVerweiseZwischenObjekten', { 'Hund.java':
  'public class Hund {' + NL +
  '    private String name;' + NL +
  '    private Hund freund;' + NL +
  '    public Hund(String nameStart) { name = nameStart; }' + NL +
  '    public void freundSetzen(Hund freundNeu) { freund = freundNeu; }' + NL +
  '    public Hund getFreund() { return freund; }' + NL +
  '}' }, s => {
  s.evalLine('Hund a = new Hund("Bello");');
  s.evalLine('Hund b = new Hund("Rex");');
  s.evalLine('a.freundSetzen(b);');
  var objs = s.objects();
  if (objs.length !== 2) { throw new Error('2 Objekte erwartet'); }
  if (objs[0].name !== 'a' || objs[1].name !== 'b') { throw new Error('Namen falsch: ' + objs[0].name + '/' + objs[1].name); }
  var freundText = null;
  var i;
  for (i = 0; i < objs[0].fields.length; i++) { if (objs[0].fields[i].name === 'freund') { freundText = objs[0].fields[i].text; } }
  if (freundText !== 'b : Hund') { throw new Error('Verweis erwartet b : Hund, erhalten ' + JSON.stringify(freundText)); }
  var r = s.evalLine('a.getFreund()');
  if (r.resultType !== 'Hund') { throw new Error('resultType Hund erwartet: ' + r.resultType); }
});

t('SessionNachMainLauf', {
  'Auto.java': AUTO_JAVA,
  'Programm.java': 'public class Programm {' + NL +
    '    public static void main(String[] args) {' + NL +
    '        Auto eins = new Auto("VW");' + NL +
    '        Auto zwei = new Auto("Audi");' + NL +
    '        eins.fahren(10);' + NL +
    '    }' + NL +
    '}'
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.ranMain, true, 'ranMain');
  if (!r.session) { throw new Error('Session erwartet'); }
  if (r.session.hasMain !== true) { throw new Error('hasMain erwartet'); }
  var objs = r.session.objects();
  if (objs.length !== 2) { throw new Error('2 Objekte aus main erwartet, ' + objs.length); }
  var r2 = r.session.evalLine('Auto drei = new Auto("Opel");');
  if (r2.errors.length > 0) { throw new Error('Objektkonsole nach main sollte funktionieren'); }
  if (r.session.objects().length !== 3) { throw new Error('3 Objekte erwartet'); }
  var r3 = r.session.evalLine('drei.getMarke()');
  if (r3.result !== 'Opel') { throw new Error('Opel erwartet: ' + JSON.stringify(r3.result)); }
});

/* ===== Neue Datei-Form: mehrere Klassen pro Datei, Anweisungen direkt in der Datei ===== */

t('MehrereKlassenInEinerDatei', {
  'Zoo.java': 'class Tier {' + NL +
    '    protected String name;' + NL +
    '    public Tier(String nameStart) { name = nameStart; }' + NL +
    '    public String getName() { return name; }' + NL +
    '}' + NL +
    'class Hund extends Tier {' + NL +
    '    public Hund(String nameStart) { super(nameStart); }' + NL +
    '    public void bellen() { System.out.println(name + " sagt: Wuff!"); }' + NL +
    '}' + NL +
    'public class Zoo {' + NL +
    '    public static void main(String[] args) {' + NL +
    '        Hund h = new Hund("Bello");' + NL +
    '        h.bellen();' + NL +
    '    }' + NL +
    '}'
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Bello sagt: Wuff!' + NL, 'Ausgabe');
  eq(r.ranMain, true, 'ranMain');
  if (!r.session) { throw new Error('Session erwartet'); }
  var names = r.session.classNames();
  if (names.length !== 3 || names[0] !== 'Tier' || names[1] !== 'Hund' || names[2] !== 'Zoo') {
    throw new Error('3 Klassen in einer Datei erwartet: ' + JSON.stringify(names));
  }
});

t('ObjekteDirektInDerDatei', {
  'Auto.java': 'public class Auto {' + NL +
    '    private String marke;' + NL +
    '    public Auto(String markeStart) { marke = markeStart; }' + NL +
    '    public String getMarke() { return marke; }' + NL +
    '}' + NL +
    'Auto a = new Auto("BMW");' + NL +
    'System.out.println(a.getMarke());'
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'BMW' + NL, 'Ausgabe');
  eq(r.ranMain, false, 'ranMain');
  if (!r.session) { throw new Error('Session erwartet'); }
  var objs = r.session.objects();
  if (objs.length !== 1 || objs[0].name !== 'a' || objs[0].cls !== 'Auto') {
    throw new Error('Objekt a : Auto erwartet: ' + JSON.stringify(objs));
  }
  /* Variable aus der Datei bleibt in der Objektkonsole nutzbar */
  var r2 = r.session.evalLine('a.getMarke()');
  if (r2.result !== 'BMW') { throw new Error('BMW erwartet: ' + JSON.stringify(r2.result)); }
});

t('DateiNurAnweisungenOhneKlasse', {
  'Start.java': 'int summe = 0;' + NL +
    'for (int i = 1; i <= 5; i++) { summe = summe + i; }' + NL +
    'System.out.println("Summe: " + summe);'
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Summe: 15' + NL, 'Ausgabe');
  eq(r.ranMain, false, 'ranMain');
  if (!r.session) { throw new Error('Session erwartet'); }
  var r2 = r.session.evalLine('summe');
  if (r2.result !== '15') { throw new Error('15 erwartet: ' + JSON.stringify(r2.result)); }
});

t('DateiAnweisungMitFehler', {
  'Test.java': 'public class Test {' + NL +
    '    public void hallo() { System.out.println("hi"); }' + NL +
    '}' + NL +
    'Test t = new Test();' + NL +
    't.gibtsnicht();'
}, r => {
  if (r.ok) { throw new Error('Fehler erwartet'); }
  if (r.errors.length === 0) { throw new Error('Fehlermeldung erwartet'); }
  var e0 = r.errors[0];
  if (e0.file !== 'Test.java') { throw new Error('Datei im Fehler erwartet: ' + JSON.stringify(e0.file)); }
  if (!e0.line || e0.line < 4) { throw new Error('Zeile im Fehler erwartet: ' + JSON.stringify(e0.line)); }
  if (e0.message.indexOf('gibtsnicht') === -1) { throw new Error('Methodenname im Fehlertext: ' + JSON.stringify(e0.message)); }
});

sesOk('SessionNachDateiAnweisungen', {
  'Auto.java': AUTO_JAVA + NL + 'Auto parker = new Auto("Audi");'
}, s => {
  var objs = s.objects();
  if (objs.length !== 1 || objs[0].name !== 'parker' || objs[0].cls !== 'Auto') {
    throw new Error('Objekt parker : Auto aus Datei-Anweisung erwartet: ' + JSON.stringify(objs));
  }
  var r1 = s.evalLine('parker.fahren(40);');
  if (r1.errors.length > 0) { throw new Error('fahren sollte klappen: ' + JSON.stringify(r1.errors)); }
  var objs2 = s.objects();
  if (objs2[0].fields.length !== 2 || objs2[0].fields[1].text !== '40') {
    throw new Error('km sollte 40 sein: ' + JSON.stringify(objs2[0].fields));
  }
  var labels = labelsOf(s.complete('parker.'));
  if (labels.indexOf('fahren(') === -1 || labels.indexOf('getMarke(') === -1 || labels.indexOf('marke') === -1) {
    throw new Error('Mitglieder von parker erwartet: ' + JSON.stringify(labels));
  }
});

/* --- #107: Erneutes Erzeugen ueberschreibt statt zu stapeln --- */
sesOk('SessionUeberschreibtGleichesObjekt', { 'Auto.java': AUTO_JAVA }, s => {
  var r1 = s.evalLine('Auto a = new Auto("BMW");');
  if (r1.errors.length > 0) { throw new Error('Fehler: ' + JSON.stringify(r1.errors)); }
  var r2 = s.evalLine('Auto a = new Auto("Opel");');
  if (r2.errors.length > 0) { throw new Error('Fehler beim erneuten Erzeugen: ' + JSON.stringify(r2.errors)); }
  var objs = s.objects();
  if (objs.length !== 1) { throw new Error('1 Objekt erwartet (ueberschrieben), erhalten ' + objs.length + ': ' + JSON.stringify(objs)); }
  if (objs[0].name !== 'a' || objs[0].cls !== 'Auto') { throw new Error('Name/Klasse falsch: ' + JSON.stringify(objs[0])); }
  if (objs[0].fields[0].text !== '"Opel"') { throw new Error('Feld sollte beim neuen Objekt Opel sein: ' + JSON.stringify(objs[0].fields)); }
  if (objs[0].id !== 1) { throw new Error('Nummerierung erwartet 1, erhalten ' + objs[0].id); }
  /* Und die weitere Arbeit gilt dem NEUEN Objekt: */
  var r3 = s.evalLine('a.getMarke();');
  if (r3.result !== 'Opel') { throw new Error('Neues Objekt erwartet, erhalten ' + JSON.stringify(r3.result)); }
});
sesOk('SessionAliasHaeltAltesObjekt', { 'Auto.java': AUTO_JAVA }, s => {
  s.evalLine('Auto a = new Auto("BMW");');
  s.evalLine('Auto b = a;');
  s.evalLine('Auto a = new Auto("Opel");');
  var objs = s.objects();
  if (objs.length !== 2) { throw new Error('2 Objekte erwartet (a neu, b als Alias alt), erhalten ' + objs.length + ': ' + JSON.stringify(objs)); }
  var byName = {};
  var oi;
  for (oi = 0; oi < objs.length; oi++) { byName[objs[oi].name] = objs[oi]; }
  if (!byName.a || byName.a.fields[0].text !== '"Opel"') { throw new Error('a sollte das neue Opel sein: ' + JSON.stringify(byName.a)); }
  if (!byName.b || byName.b.fields[0].text !== '"BMW"') { throw new Error('b sollte das alte BMW halten: ' + JSON.stringify(byName.b)); }
});

/* --- Grafik-Fenster (OnlineIDE/learnj-API): Figuren, Bewegung, Farben --- */
t('GfxKreis', {
  'Kreis.java': [
    'Circle c = new Circle(10, 20, 5);',
    'c.move(3, 4);',
    'System.out.println(c.getX());',
    'System.out.println(c.getY());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '13.0' + NL + '24.0' + NL, 'Position nach move');
  eq(r.hasGfx, true, 'hasGfx');
  var snap = V.gfxSnapshot();
  if (snap.length !== 1 || snap[0].kind !== 'circle' || snap[0].r !== 5) { throw new Error('Kreis im Snapshot: ' + JSON.stringify(snap)); }
});
t('GfxRotateScaleFarbe', {
  'Figur.java': [
    'Rectangle r = new Rectangle(100, 100, 40, 20);',
    'r.rotate(90);',
    'r.scale(2, 200, 110);',
    'r.setFillColor(0xff8000);',
    'r.setBorderColor(Color.black);',
    'r.setBorderWidth(3);',
    'r.setFillColor(null);'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  var s = V.gfxSnapshot()[0];
  if (s.rot !== 90) { throw new Error('rot erwartet 90: ' + JSON.stringify(s)); }
  if (s.scaleF !== 2) { throw new Error('scaleF erwartet 2: ' + JSON.stringify(s)); }
  if (s.fill !== null) { throw new Error('fill nach null erwartet null: ' + JSON.stringify(s.fill)); }
  if (s.border !== 'rgb(0, 0, 0)' || s.borderWidth !== 3) { throw new Error('Rand: ' + JSON.stringify(s)); }
});
t('GfxWorldUndText', {
  'Welt.java': [
    'World w = new World(200, 100);',
    'w.setBackgroundColor(Color.blue);',
    'System.out.println(w.getWidth());',
    'Text t = new Text(5, 5, 20, "Hallo");',
    't.setText("Welt");',
    't.setAlignment(Alignment.center);',
    'System.out.println(t.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '200.0' + NL + '5.0' + NL, 'World-Groesse und Text-Position');
  var snap = V.gfxSnapshot();
  if (snap.length !== 1 || snap[0].kind !== 'text' || snap[0].text !== 'Welt' || snap[0].align !== 'center') {
    throw new Error('Text im Snapshot: ' + JSON.stringify(snap));
  }
});
t('GfxGroupKollision', {
  'Sammel.java': [
    'Circle a = new Circle(0, 0, 10);',
    'Rectangle b = new Rectangle(5, 5, 10, 10);',
    'Circle c = new Circle(100, 100, 5);',
    'Group g = new Group(a, c);',
    'g.move(10, 10);',
    'System.out.println(g.getCollidingShapes(b).length);',
    'System.out.println(a.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL + '10.0' + NL, 'eine Kollision, Gruppe bewegt Mitglieder mit');
});
t('GfxTurtleBitmapPolygon', {
  'Zeich.java': [
    'Turtle t = new Turtle(10, 10);',
    't.forward(50);',
    't.turn(90);',
    't.forward(20);',
    'Bitmap bm = new Bitmap(4, 2, 0, 0, 10, 10);',
    'bm.setColor(1, 0, Color.red);',
    'System.out.println(bm.getColorAsInt(1, 0));',
    'System.out.println(bm.getColorAsInt(3, 1));',
    'Polygon p = new Polygon(false, 0, 0, 10, 0, 10, 10);',
    'p.addPoint(5, 15);',
    'System.out.println("fertig");'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '16711680' + NL + '16777215' + NL + 'fertig' + NL, 'Bitmap-Farben als int');
  var snap = V.gfxSnapshot();
  var turtle = null, poly = null;
  snap.forEach(function (s) { if (s.kind === 'turtle') { turtle = s; } if (s.kind === 'polygon') { poly = s; } });
  if (!turtle || Math.round(turtle.x) !== 60 || Math.round(turtle.y) !== 30) { throw new Error('Turtle-Endpunkt: ' + JSON.stringify(turtle)); }
  if (!turtle.pathCount || turtle.pathCount !== 3) { throw new Error('Turtle-Pfad: ' + JSON.stringify(turtle)); }
  if (!poly || poly.pointsCount !== 4) { throw new Error('Polygon-Punkte: ' + JSON.stringify(poly)); }
});
t('SpriteStandard', {
  'Std.java': [
    'Sprite s = new Sprite(100, 200, "Schildkröte");',
    's.scale(2);',
    's.rotate(90);',
    's.move(10, 5);',
    'System.out.println(s.getX());',
    'System.out.println(s.getY());',
    'Sprite d = new Sprite();',
    'System.out.println(d.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '110.0' + NL + '205.0' + NL + '400.0' + NL, 'Ausgaben und Default-Position');
  var snap = V.gfxSnapshot();
  var sp = null, sp2 = null;
  snap.forEach(function (s) { if (s.kind === 'sprite') { if (sp === null) { sp = s; } else { sp2 = s; } } });
  if (!sp) { throw new Error('Sprite fehlt im Snapshot: ' + JSON.stringify(snap)); }
  if (sp.spriteKey !== 'Schildkröte') { throw new Error('spriteKey: ' + JSON.stringify(sp.spriteKey)); }
  if (sp.scaleF !== 2 || sp.rot !== 90) { throw new Error('scale/rotate: ' + JSON.stringify(sp)); }
  if (Math.round(sp.x) !== 110 || Math.round(sp.y) !== 205) { throw new Error('Position: ' + JSON.stringify(sp)); }
  if (!sp2 || sp2.spriteKey !== 'Schildkröte' || Math.round(sp2.x) !== 400) { throw new Error('Default-Sprite: ' + JSON.stringify(sp2)); }
});
t('SpriteUnbekannt', {
  'Std.java': [
    'Sprite s = new Sprite(100, 200, "Nashorn");'
  ].join(NL)
}, r => {
  err1(r, 'runtime');
  var msg = r.errors[0].message + ' ' + r.errors[0].hint;
  if (msg.indexOf('gibt es nicht') === -1) { throw new Error('Meldung nennt unbekannten Namen nicht: ' + JSON.stringify(r.errors[0])); }
  if (msg.indexOf('Schildkröte') === -1) { throw new Error('Hilfestellung nennt Standard-Namen nicht: ' + JSON.stringify(r.errors[0])); }
});
global.window = { IC_IMAGES: { Ball: 'data:image/png;base64,AAAA' } };
t('SpriteEigenesBild', {
  'Std.java': [
    'Sprite s = new Sprite(50, 60, "Ball");',
    'System.out.println(s.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '50.0' + NL, 'output');
  var snap = V.gfxSnapshot();
  var sp = null;
  snap.forEach(function (s) { if (s.kind === 'sprite') { sp = s; } });
  if (!sp || sp.spriteKey !== 'Ball') { throw new Error('Eigenes Bild: ' + JSON.stringify(sp)); }
  if (!sp.spriteSrc || sp.spriteSrc.indexOf('data:image/png') !== 0) { throw new Error('spriteSrc: ' + JSON.stringify(sp.spriteSrc)); }
});
delete global.window;
t('GfxDefaultsUndDestroy', {
  'Std.java': [
    'Shape.setDefaultVisibility(false);',
    'FilledShape.setDefaultFillColor(Color.green);',
    'Circle c = new Circle(1, 1, 5);',
    'System.out.println(c.getX());',
    'Rectangle r = new Rectangle(0, 0, 10, 10);',
    'r.destroy();',
    'r.move(1, 1);'
  ].join(NL)
}, r => {
  if (r.ok) { throw new Error('Fehler nach destroy erwartet'); }
  var snap = V.gfxSnapshot();
  if (snap.length !== 1 || snap[0].visible !== false || snap[0].fill !== 'rgb(0, 128, 0)') {
    throw new Error('Voreinstellungen: ' + JSON.stringify(snap));
  }
  if (r.errors.length === 0 || r.errors[0].message.indexOf('zerstört') === -1) { throw new Error('destroy-Fehlermeldung: ' + JSON.stringify(r.errors)); }
});
t('GfxActUndTastatur', {
  'Auto.java': [
    'class Auto extends Rectangle {',
    '    Auto() { super(10, 10, 20, 20); }',
    '    void act() { move(2, 0); }',
    '    void onKeyDown(String taste) { move(0, 5); }',
    '}',
    'Auto a = new Auto();'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  V.gfxTick();
  V.gfxTick();
  V.gfxDispatchKey('ArrowDown', true);
  var s = V.gfxSnapshot()[0];
  if (Math.round(s.x) !== 14 || Math.round(s.y) !== 15) { throw new Error('act + onKeyDown Bewegung: ' + JSON.stringify(s)); }
  V.gfxStopLoop();
});
t('GfxGuiElemente', {
  'Panel.java': [
    'Textfield tf = new Textfield(10, 10, 100, 24, "Name");',
    'System.out.println(tf.getText());',
    'tf.setText("Anna");',
    'System.out.println(tf.getText());',
    'Checkbox cb = new Checkbox(10, 50, 100, 20, "Laut", true);',
    'System.out.println(cb.isChecked());',
    'cb.setChecked(false);',
    'System.out.println(cb.isChecked());',
    'Radiobutton rb = new Radiobutton(10, 80, 100, 20);',
    'rb.setSelected(true);',
    'System.out.println(rb.isSelected());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Name' + NL + 'Anna' + NL + 'true' + NL + 'false' + NL + 'true' + NL, 'GUI-Zustände');
});
t('GfxVererbungSuper', {
  'Kopf.java': [
    'class Kopf extends Circle {',
    '    int gedreht = 0;',
    '    Kopf() { super(50, 50, 12); setFillColor(Color.yellow); }',
    '    void act() { gedreht = gedreht + 1; move(1, 0); }',
    '}',
    'Kopf k = new Kopf();',
    'k.move(10, 0);',
    'System.out.println(k.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '60.0' + NL, 'Position nach super-Konstruktor und move');
  var s = V.gfxSnapshot()[0];
  if (s.fill !== 'rgb(255, 255, 0)') { throw new Error('Fuellung im Konstruktor: ' + JSON.stringify(s)); }
  V.gfxTick();
  if (Math.round(V.gfxSnapshot()[0].x) !== 61) { throw new Error('act() nach Vererbung: ' + JSON.stringify(V.gfxSnapshot()[0])); }
});

/* --- Einzelschritt (stepLimit) --- */
t('StepLimitPausiert', {
  'Step.java': [
    'class Step { }',
    'System.out.println("eins");',
    'System.out.println("zwei");',
    'System.out.println("drei");'
  ].join(NL)
}, r => {
  /* Dieser Lauf ist ohne Limit (komplett) - der naechste mit. */
  eq(r.ok, true, 'ok');
  eq(r.output, 'eins' + NL + 'zwei' + NL + 'drei' + NL, 'voller Lauf');

});
(function () {
  var files = {
    'Step.java': [
      'class Step { }',
      'System.out.println("eins");',
      'System.out.println("zwei");',
      'System.out.println("drei");'
    ].join(NL)
  };
  count++;
  var r1 = V.run(files, { stepLimit: 1 });
  try {
    if (!r1.paused) { throw new Error('Nach 1 Schritt pausiert erwartet'); }
    if (r1.leafSteps !== 1) { throw new Error('leafSteps=1 erwartet: ' + r1.leafSteps); }
    if (r1.pauseLine !== 3) { throw new Error('pauseLine=3 (zweiter println) erwartet: ' + r1.pauseLine); }
    if (r1.output !== 'eins' + NL) { throw new Error('Ausgabe bis Pause: ' + JSON.stringify(r1.output)); }
    var r2 = V.run(files, { stepLimit: 2 });
    if (!r2.paused || r2.output !== 'eins' + NL + 'zwei' + NL || r2.pauseLine !== 4) { throw new Error('zweiter Schritt: ' + JSON.stringify(r2)); }
    var r3 = V.run(files, { stepLimit: 3 });
    if (r3.paused) { throw new Error('dritter Schritt beendet das Programm'); }
    if (r3.output !== 'eins' + NL + 'zwei' + NL + 'drei' + NL) { throw new Error('Ausgabe komplett: ' + JSON.stringify(r3.output)); }
    var r0 = V.run(files, { stepLimit: 0 });
    if (!r0.paused || r0.output !== '') { throw new Error('stepLimit 0 pausiert sofort ohne Ausgabe'); }
    console.log('ok   StepLimit');
  } catch (e) {
    failed++;
    console.log('FAIL StepLimit -> ' + e.message);
  }
})();

/* --- Automatisches Formatieren --- */
(function () {
  count++;
  try {
    var messy = 'class A {' + NL + 'int x;' + NL + 'void go(){' + NL + 'if (x>0){' + NL + 'x = x - 1;' + NL + '}' + NL + '}' + NL + '}' + NL + 'System.out.println("} nicht zaehlen");' + NL + '// { auch nicht' + NL + '/* { Block' + NL + 'weiter } */' + NL + 'int y;';
    var fmt = V.formatJavaSource(messy);
    var lines = fmt.split(NL);
    if (lines[1] !== '    int x;') { throw new Error('Attribut eingerueckt: ' + JSON.stringify(lines[1])); }
    if (lines[3] !== '        if (x>0){') { throw new Error('Verschachtelung: ' + JSON.stringify(lines[3])); }
    if (lines[4] !== '            x = x - 1;') { throw new Error('zweite Ebene: ' + JSON.stringify(lines[4])); }
    if (lines[8] !== 'System.out.println("} nicht zaehlen");') { throw new Error('String-Klammer ignoriert: ' + JSON.stringify(lines[8])); }
    if (lines[10] !== '/* { Block') { throw new Error('Kommentar unverändert: ' + JSON.stringify(lines[10])); }
    if (lines[12] !== 'int y;') { throw new Error('Tiefe nach Kommentar: ' + JSON.stringify(lines[12])); }
    console.log('ok   FormatJavaSource');
  } catch (e) {
    failed++;
    console.log('FAIL FormatJavaSource -> ' + e.message);
  }
})();

/* --- Typ-Pruefung: Datentypen bei Attributen und Variablen werden jetzt erzwungen --- */
t('TypAttributIntKeinString',
  { 'Auto.java': 'public class Auto {' + NL + '    private int speed;' + NL + '    public void setze() { speed = "schnell"; }' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.setze();' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); if (r.errors.length === 0) { throw new Error('Fehlerliste leer'); } if (r.errors[0].message.indexOf('int passt nicht zu String') === -1) { throw new Error('Meldung: ' + r.errors[0].message); } });

t('TypAttributStringKeinInt',
  { 'Auto.java': 'public class Auto {' + NL + '    private String name;' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.name = 42;' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); if (r.errors[0].message.indexOf('String passt nicht zu int') === -1) { throw new Error('Meldung: ' + r.errors[0].message); } });

t('TypParamUndReturn',
  { 'Auto.java': 'public class Auto {' + NL + '    public void fahren(int km) { System.out.println(km); }' + NL + '    public String getName() { return 42; }' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.fahren("zehn");' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); if (r.errors[0].message.indexOf('int passt nicht zu String') === -1) { throw new Error('Meldung: ' + r.errors[0].message); } });

t('TypReturnPruefung',
  { 'Auto.java': 'public class Auto {' + NL + '    public String getName() { return 42; }' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.getName();' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); if (r.errors[0].message.indexOf('String passt nicht zu int') === -1) { throw new Error('Meldung: ' + r.errors[0].message); } });

t('TypVererbungPasst',
  { 'Tier.java': 'public class Tier {' + NL + '    protected String name;' + NL + '    public void gibLaut() { System.out.println(name); }' + NL + '}' + NL + 'public class Hund extends Tier {' + NL + '    public Hund(String n) { name = n; }' + NL + '}' + NL + 'Tier t = new Hund("Bello");' + NL + 't.gibLaut();' },
  function (r) { eq(r.ok, true, 'Unterklasse ist ein Tier'); eq(r.output, 'Bello' + NL, 'Ausgabe'); });

t('TypFremdeKlasseAbgelehnt',
  { 'A.java': 'public class A {' + NL + '}' + NL + 'public class B {' + NL + '}' + NL + 'A a = new B();' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); if (r.errors[0].message.indexOf('A passt nicht zu B') === -1) { throw new Error('Meldung: ' + r.errors[0].message); } });

t('ZweiPublicKlassenEineDatei',
  { 'Tier.java': 'public class Tier {' + NL + '    protected String name;' + NL + '    public Tier(String n) { name = n; }' + NL + '    public void gibLaut() { System.out.println("Tier " + name); }' + NL + '}' + NL + 'public class Hund extends Tier {' + NL + '    public Hund(String n) { super(n); }' + NL + '    public void gibLaut() { System.out.println("Wuff, sagt " + name); }' + NL + '}' + NL + 'Hund h = new Hund("Bello");' + NL + 'h.gibLaut();' },
  function (r) { eq(r.ok, true, 'mehrere public-Klassen pro Datei erlaubt'); eq(r.output, 'Wuff, sagt Bello' + NL, 'Ausgabe'); });

t('DateiEndeRueckgabe',
  { 'Auto.java': 'public class Auto {' + NL + '    public int plus(int a, int b) { return a + b; }' + NL + '    public void sagHallo() { System.out.println("Hallo!"); }' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.sagHallo();' + NL + 'a.plus(2, 3);' },
  function (r) { eq(r.ok, true, 'lauf ok'); eq(r.output, 'Hallo!' + NL + 'Ergebnis: 5   (int)' + NL, 'Rueckgabe erscheint in der Ausgabe'); });

t('VoidReturnMitWertAbgelehnt',
  { 'Auto.java': 'public class Auto {' + NL + '    public void test() { return 42; }' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.test();' },
  function (r) { eq(r.ok, false, 'Fehler erwartet'); });

t('ObjektNameAusMain',
  { 'Auto.java': 'public class Auto {' + NL + '    public String marke;' + NL + '    public Auto(String m) { marke = m; }' + NL + '}' + NL + 'Auto a = new Auto("BMW");' + NL + 'Auto b;' + NL + 'b = new Auto("Audi");' },
  function (r) {
    eq(r.ok, true, 'lauf ok');
    var objs = r.session.objects();
    eq(objs.length, 2, '2 Objekte aus main');
    var byMarke = {};
    var i, j;
    for (i = 0; i < objs.length; i++) {
      for (j = 0; j < objs[i].fields.length; j++) {
        if (objs[i].fields[j].name === 'marke') { byMarke[objs[i].fields[j].text] = objs[i].name; }
      }
    }
    eq(byMarke['"BMW"'], 'a', 'Objekt aus Deklaration heisst a');
    eq(byMarke['"Audi"'], 'b', 'Objekt aus spaeter Zuweisung heisst b');
  });

t('MeldungenMitUmlauten',
  { 'Auto.java': 'public class Auto {' + NL + '    public String marke;' + NL + '}' + NL + 'Auto a = new Auto();' + NL + 'a.tueren;' },
  function (r) {
    eq(r.ok, false, 'Fehler erwartet');
    if (r.errors[0].message.indexOf('gehört') === -1) { throw new Error('Umlaut in Meldung erwartet: ' + r.errors[0].message); }
    if (r.errors[0].message.indexOf('gehoert') !== -1) { throw new Error('Ersatz-Schreibung gefunden: ' + r.errors[0].message); }
  });

/* --- Klassen nach dem Vorbild der Online-IDE: Vector2 --- */
t('Vector2Basics', { 'Main.java': mainOf(
  '        Vector2 v = new Vector2(3, 4);' + NL +
  '        System.out.println(v.getLength());' + NL +
  '        System.out.println(v.x + " " + v.y);' + NL +
  '        v.x = 5;' + NL +
  '        System.out.println(v.x);' + NL +
  '        System.out.println(v.toString());') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, '5.0' + NL + '3.0 4.0' + NL + '5.0' + NL + '(5.0, 4.0)' + NL, 'Laenge, Felder, toString');
});
t('Vector2Rechnen', { 'Main.java': mainOf(
  '        Vector2 a = new Vector2(1, 2);' + NL +
  '        Vector2 b = new Vector2(3, 1);' + NL +
  '        System.out.println(a.plus(b));' + NL +
  '        System.out.println(a.minus(b));' + NL +
  '        System.out.println(a.scalarProduct(b));' + NL +
  '        System.out.println(a.distanceTo(b));' + NL +
  '        a.add(b);' + NL +
  '        System.out.println(a);' + NL +
  '        System.out.println(b.scaledBy(2));' + NL +
  '        System.out.println(a.equals(new Vector2(4, 3)));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '(4.0, 3.0)' + NL + '(-2.0, 1.0)' + NL + '5.0' + NL + '2.23606797749979' + NL + '(4.0, 3.0)' + NL + '(6.0, 2.0)' + NL + 'true' + NL, 'Vektor-Rechnungen');
});
t('Vector2DrehenNormieren', { 'Main.java': mainOf(
  '        Vector2 v = new Vector2(1, 0);' + NL +
  '        Vector2 r90 = v.rotatedBy(90);' + NL +
  '        System.out.println(r90.getY());' + NL +
  '        System.out.println(r90.getX() < 0.000001 && r90.getX() > -0.000001);' + NL +
  '        System.out.println(v.rotatedBy(90).getAngleDeg());' + NL +
  '        Vector2 u = new Vector2(3, 4);' + NL +
  '        System.out.println(u.getUnitVector());' + NL +
  '        System.out.println(u.setLength(10).getLength());' + NL +
  '        System.out.println(Vector2.distance(0, 0, 3, 4));' + NL +
  '        Vector2 polar = Vector2.fromPolarCoordinates(5, 90);' + NL +
  '        System.out.println(polar.getY());' + NL +
  '        System.out.println(polar.getX() < 0.000001 && polar.getX() > -0.000001);') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, '1.0' + NL + 'true' + NL + '90.0' + NL + '(0.6, 0.8)' + NL + '10.0' + NL + '5.0' + NL + '5.0' + NL + 'true' + NL, 'Drehen, Normieren, statische Methoden');
});
t('Vector2NullvektorFehler', { 'Main.java': mainOf(
  '        Vector2 v = new Vector2(0, 0);' + NL +
  '        v.getUnitVector();') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('Nullvektor') === -1) { throw new Error('Nullvektor-Meldung erwartet: ' + r.errors[0].message); }
});

/* --- MathTools --- */
t('MathToolsAbstaende', { 'Main.java': mainOf(
  '        Vector2 p = new Vector2(0, 3);' + NL +
  '        Vector2 a = new Vector2(0, 0);' + NL +
  '        Vector2 b = new Vector2(4, 0);' + NL +
  '        System.out.println(MathTools.distancePointToLine(p, a, b));' + NL +
  '        Vector2 p2 = new Vector2(5, 0);' + NL +
  '        System.out.println(MathTools.distancePointToLineSegment(p2, a, b));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '3.0' + NL + '1.0' + NL, 'Abstand Punkt-Gerade und Punkt-Strecke');
});
t('MathToolsSchnitte', { 'Main.java': mainOf(
  '        Vector2 s = MathTools.intersectLineSegments(new Vector2(0, 0), new Vector2(4, 4), new Vector2(0, 4), new Vector2(4, 0));' + NL +
  '        System.out.println(s);' + NL +
  '        Vector2 parallel = MathTools.intersectLineSegments(new Vector2(0, 0), new Vector2(1, 0), new Vector2(0, 1), new Vector2(1, 1));' + NL +
  '        System.out.println(parallel);') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '(2.0, 2.0)' + NL + 'null' + NL, 'Streckenschnitte');
});
t('MathToolsPolygon', { 'Main.java': mainOf(
  '        Vector2[] dreieck = new Vector2[]{ new Vector2(0, 0), new Vector2(10, 0), new Vector2(5, 8) };' + NL +
  '        System.out.println(MathTools.polygonContainsPoint(dreieck, new Vector2(5, 2)));' + NL +
  '        System.out.println(MathTools.polygonContainsPoint(dreieck, new Vector2(20, 5)));' + NL +
  '        Vector2[] hits = MathTools.intersectCircleWithPolygon(0, 0, 2.5, new Vector2[]{ new Vector2(-2, -2), new Vector2(2, -2), new Vector2(2, 2), new Vector2(-2, 2) });' + NL +
  '        System.out.println(hits.length);') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, 'true' + NL + 'false' + NL + '8' + NL, 'Polygon-Test und Kreis-Schnitte');
});

/* --- Random --- */
t('RandomKlassen', { 'Main.java': mainOf(
  '        System.out.println(Random.randint(3, 3));' + NL +
  '        System.out.println(Random.randdouble(2, 2));' + NL +
  '        Random wuerfel = new Random();' + NL +
  '        System.out.println(wuerfel.nextInt(1));' + NL +
  '        double z = wuerfel.nextDouble();' + NL +
  '        System.out.println(z >= 0.0 && z < 1.0);') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '3' + NL + '2.0' + NL + '0' + NL + 'true' + NL, 'Zufallszahlen mit festen Grenzen');
});
t('RandomBereichFehler', { 'Main.java': mainOf('        Random.randint(5, 3);') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('obere Grenze') === -1) { throw new Error('Grenzen-Meldung erwartet: ' + r.errors[0].message); }
});

/* --- Assertions --- */
t('AssertionsErfolg', { 'Main.java': mainOf(
  '        Assertions.assertTrue(1 + 1 == 2, "Addition stimmt");' + NL +
  '        Assertions.assertEquals(4, 2 * 2, "Malnehmen stimmt");' + NL +
  '        Assertions.assertEquals("Anna", "Anna", "Namen gleich");' + NL +
  '        Assertions.assertCodeReached("Ende erreicht");') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '✓ Addition stimmt' + NL + '✓ Malnehmen stimmt' + NL + '✓ Namen gleich' + NL + '✓ Ende erreicht' + NL, 'Erfolgs-Ausgaben');
});
t('AssertionsFehler', { 'Main.java': mainOf('        Assertions.assertEquals(1, 2, "Rechnung pruefen");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('Erwartet 1, erhalten 2') === -1) { throw new Error('Soll-Ist-Meldung erwartet: ' + r.errors[0].message); }
});
t('AssertionsFalse', { 'Main.java': mainOf('        Assertions.assertFalse(true, "muss falsch sein");') }, r => {
  err1(r, 'runtime');
});

/* --- Key, Direction, Position, CollisionPair --- */
t('KeyKonstanten', { 'Main.java': mainOf(
  '        System.out.println(Key.ArrowUp);' + NL +
  '        System.out.println(Key.Space);' + NL +
  '        System.out.println(Key.Strg);' + NL +
  '        System.out.println(Input.isKeyDown(Key.ArrowLeft));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'ArrowUp' + NL + 'Space' + NL + 'Control' + NL + 'false' + NL, 'Tasten-Konstanten');
});
t('DirectionRichtungen', { 'Main.java': mainOf(
  '        System.out.println(Direction.top);' + NL +
  '        System.out.println(Direction.getDx(Direction.right));' + NL +
  '        System.out.println(Direction.getDy(Direction.top));' + NL +
  '        System.out.println(Direction.getDx(Direction.left));' + NL +
  '        System.out.println(Direction.getDy("unten"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'top' + NL + '1' + NL + '-1' + NL + '-1' + NL + '1' + NL, 'Richtungs-Konstanten und Deltas');
});
t('PositionUndCollisionPair', { 'Main.java': mainOf(
  '        Position p = new Position(3, 7);' + NL +
  '        System.out.println(p.toString());' + NL +
  '        p.x = 10;' + NL +
  '        System.out.println(p.x + " " + p.y);' + NL +
  '        Circle c1 = new Circle(0, 0, 5);' + NL +
  '        Circle c2 = new Circle(2, 0, 5);' + NL +
  '        CollisionPair paar = new CollisionPair(c1, c2);' + NL +
  '        System.out.println(paar.shapeA.getX());' + NL +
  '        System.out.println(paar.shapeB.getX());') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, '(3|7)' + NL + '10 7' + NL + '0.0' + NL + '2.0' + NL, 'Position-Felder und CollisionPair');
});

/* --- Input (ohne Fenster: Vorgabewert) --- */
t('InputVorgabewerte', { 'Main.java': mainOf(
  '        String name = Input.readString("Wie heißt du?", "Alex");' + NL +
  '        System.out.println(name);' + NL +
  '        System.out.println(Input.readInt("Zahl?", 42));' + NL +
  '        System.out.println(Input.readDouble("Kommazahl?", 1.5));' + NL +
  '        System.out.println(Input.readBoolean("Ja?", true));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Alex' + NL + '42' + NL + '1.5' + NL + 'true' + NL, 'Vorgabewerte ohne Dialogfenster');
});

/* --- SystemTools, Sound, Gamepad --- */
t('SystemTools', { 'Main.java': mainOf(
  '        SystemTools.setSpeed(60);' + NL +
  '        System.out.println(SystemTools.getSpeed());' + NL +
  '        System.out.println("Kurz");' + NL +
  '        SystemTools.clearScreen();' + NL +
  '        System.out.println("Danach");' + NL +
  '        SystemTools.startLoggingPrintOutput();' + NL +
  '        System.out.println("Mit Log");' + NL +
  '        System.out.println(SystemTools.getPrintLog());' + NL +
  '        System.out.println(SystemTools.getNumberOfProcessorCores() >= 1);') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, 'Danach' + NL + 'Mit Log' + NL + 'Mit Log' + NL + '' + NL + 'true' + NL, 'Speed, clearScreen, Print-Log, Kerne');
});
t('SoundUndGamepad', { 'Main.java': mainOf(
  '        Sound.playSound("beep");' + NL +
  '        Sound.playSound("win");' + NL +
  '        System.out.println(Gamepad.isConnected(0));' + NL +
  '        System.out.println(Gamepad.getAxisValue(0, 0));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'false' + NL + '0.0' + NL, 'Sound ohne Fehler, Gamepad ohne angeschlossenem Pad');
});
t('SoundUnbekanntFehler', { 'Main.java': mainOf('        Sound.playSound("trommel");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('trommel') === -1) { throw new Error('Unbekannter Sound gemeldet: ' + r.errors[0].message); }
});

/* --- Arc und Sector (Grafik aus der Online-IDE) --- */
t('ArcSectorFiguren', { 'Main.java': mainOf(
  '        World w = new World(400, 300);' + NL +
  '        Arc a = new Arc(200, 150, 20, 80, 0, 90);' + NL +
  '        System.out.println(a.getInnerRadius());' + NL +
  '        System.out.println(a.getOuterRadius());' + NL +
  '        System.out.println(a.containsPoint(250, 150));' + NL +
  '        System.out.println(a.containsPoint(150, 150));' + NL +
  '        a.setStartAngle(180);' + NL +
  '        System.out.println(a.getStartAngle());' + NL +
  '        Sector s = new Sector(200, 150, 60, 0, 45);' + NL +
  '        System.out.println(s.getRadius());' + NL +
  '        System.out.println(s.containsPoint(230, 160));' + NL +
  '        System.out.println(s.containsPoint(200, 150));' + NL +
  '        s.drawRadii(true);' + NL +
  '        System.out.println(s.getEndAngle());') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, '20.0' + NL + '80.0' + NL + 'true' + NL + 'false' + NL + '180.0' + NL + '60.0' + NL + 'true' + NL + 'true' + NL + '45.0' + NL, 'Arc- und Sector-Methoden');
});
t('ArcVererbung', {
  'Smile.java': [
    'class Smile extends Arc {',
    '    Smile() { super(200, 150, 20, 80, 20, 160); }',
    '}',
    'Smile m = new Smile();',
    'm.move(10, 0);',
    'System.out.println(m.getX());'
  ].join(NL)
}, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, '210.0' + NL, 'Vererbung von Arc mit move');
});

/* --- #50/#51: Konstruktor- und Parameter-Fehler mit Signatur --- */
t('CtorOhneArgsWirftFehler', { 'Auto.java': 'public class Auto {' + NL + '    private String marke;' + NL + '    public Auto(String marke) { this.marke = marke; }' + NL + '}', 'Main.java': mainOf('        new Auto();') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('new Auto(...) mit 0 Argument(en)') === -1) { throw new Error('Meldung nennt new-Aufruf nicht: ' + r.errors[0].message); }
  if (r.errors[0].message.indexOf('Auto(String marke)') === -1) { throw new Error('Signatur fehlt: ' + r.errors[0].message); }
});
t('CtorZuVieleArgsNenntSignatur', { 'Auto.java': 'public class Auto {' + NL + '    public Auto(String marke) { }' + NL + '}', 'Main.java': mainOf('        new Auto("A", 1);') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('Auto(String marke)') === -1) { throw new Error('Signatur fehlt: ' + r.errors[0].message); }
});
t('CtorDefaultOhneKonstruktorOk', { 'Auto.java': 'public class Auto {' + NL + '    public String marke;' + NL + '}', 'Main.java': mainOf('        Auto a = new Auto();' + NL + '        System.out.println(a.marke == null);') }, r => {
  eq(r.ok, true, 'ok: ' + JSON.stringify(r.errors));
  eq(r.output, 'true' + NL, 'Default-Konstruktor mit 0 Argumenten erlaubt');
});
t('MethodeFalscheArgsNenntSignatur', { 'Auto.java': 'public class Auto {' + NL + '    public void setzeMarke(String marke) { }' + NL + '}', 'Main.java': mainOf('        Auto a = new Auto();' + NL + '        a.setzeMarke();') }, r => {
  err1(r, 'runtime');
  var text = r.errors[0].message + ' ' + r.errors[0].hint;
  if (text.indexOf('setzeMarke(String marke)') === -1) { throw new Error('erwartete Signatur fehlt: ' + text); }
});

console.log('');
console.log(failed === 0 ? ('Alle ' + count + ' Tests bestanden.') : (failed + ' von ' + count + ' Tests fehlgeschlagen.'));
process.exit(failed === 0 ? 0 : 1);

