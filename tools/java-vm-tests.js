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
t('KlassennameDateiname', { 'Falsch.java': 'public class AndererName {' + NL + '    public static void main(String[] args) { }' + NL + '}' }, r => {
  err1(r, 'compile');
});
t('UnbekannteKlasseBeiNew', { 'Main.java': mainOf('        Auto a = new Auto();') }, r => {
  err1(r, 'compile');
});
t('OberklasseFehlt', { 'Main.java': 'public class Main extends Fehlt {' + NL + '    public static void main(String[] args) { }' + NL + '}' }, r => {
  err1(r, 'compile');
});
t('MainFehlt', { 'NurSo.java': 'public class NurSo {' + NL + '    public int x = 1;' + NL + '}' }, r => {
  err1(r, 'compile');
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

/* --- Mini-Datenbank (eingebaute Klasse Datenbank) --- */
t('DbCreateInsertSelect', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE schueler (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Datenbank.abfrage("SELECT * FROM schueler"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name | punkte' + NL + '-----+-------' + NL + 'Anna | 12    ' + NL + 'Ben  | 8     ' + NL, 'tabellenausgabe');
});
t('DbSelectWhere', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Datenbank.abfrage("SELECT name FROM s WHERE punkte > 10"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'gefilterte ausgabe');
});
t('DbSelectOrderByLimit', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Datenbank.abfrage("SELECT name FROM s ORDER BY punkte DESC LIMIT 1"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'sortiert und begrenzt');
});
t('DbAnzahlUndCount', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        System.out.println(Datenbank.anzahl("SELECT * FROM s WHERE punkte > 10"));' + NL +
  '        System.out.println(Datenbank.abfrage("SELECT COUNT(*) FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL + 'COUNT(*)' + NL + '--------' + NL + '2       ' + NL, 'anzahl und COUNT(*)');
});
t('DbWert', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        String beste = Datenbank.wert("SELECT name FROM s ORDER BY punkte DESC");' + NL +
  '        System.out.println("Beste: " + beste);' + NL +
  '        System.out.println("[" + Datenbank.wert("SELECT name FROM s WHERE punkte > 99") + "]");') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'Beste: Anna' + NL + '[]' + NL, 'wert und leerer wert');
});
t('DbUpdate', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  "        Datenbank.ausfuehren(\"UPDATE s SET punkte = 20 WHERE name = 'Ben'\");" + NL +
  '        System.out.println(Datenbank.abfrage("SELECT * FROM s WHERE punkte >= 20"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name | punkte' + NL + '-----+-------' + NL + 'Ben  | 20    ' + NL, 'nach update');
});
t('DbDelete', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8)\");" + NL +
  '        Datenbank.ausfuehren("DELETE FROM s WHERE punkte < 10");' + NL +
  '        System.out.println(Datenbank.anzahl("SELECT * FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '1' + NL, 'zeilen geloescht');
});
t('DbInsertSpaltenlisteNull', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s (name) VALUES ('Caesar')\");" + NL +
  '        String erg = Datenbank.abfrage("SELECT * FROM s");' + NL +
  '        if (erg.indexOf("Caesar") < 0) { System.out.println("FEHLER"); }' + NL +
  '        else { System.out.println("OK"); }' + NL +
  '        System.out.println(Datenbank.wert("SELECT punkte FROM s"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'OK' + NL + 'null' + NL, 'nicht genannte spalte ist null');
});
t('DbLike', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna'), ('Ben'), ('Clara')\");" + NL +
  '        System.out.println(Datenbank.abfrage("SELECT name FROM s WHERE name LIKE ' + "'%an%'" + '"));') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'name' + NL + '----' + NL + 'Anna' + NL, 'like mit platzhalter');
});
t('DbUndOder', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT, punkte INT)");' + NL +
  "        Datenbank.ausfuehren(\"INSERT INTO s VALUES ('Anna', 12), ('Ben', 8), ('Clara', 3)\");" + NL +
  '        System.out.println(Datenbank.anzahl("SELECT * FROM s WHERE punkte > 5 AND punkte < 20"));' + NL +
  "        System.out.println(Datenbank.anzahl(\"SELECT * FROM s WHERE name = 'Anna' OR name = 'Clara'\"));") }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '2' + NL + '2' + NL, 'AND und OR');
});
t('DbTabellen', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE schueler (name TEXT)");' + NL +
  '        Datenbank.ausfuehren("CREATE TABLE produkte (titel TEXT)");' + NL +
  '        System.out.println(Datenbank.tabellen());') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, 'schueler, produkte' + NL, 'tabellenliste');
});
t('DbResetProLauf', { 'Main.java': mainOf(
  '        System.out.println(Datenbank.tabellen());') }, r => {
  eq(r.ok, true, 'ok');
  eq(r.output, '(noch keine Tabellen)' + NL, 'datenbank ist pro lauf neu');
});
t('DbFehlerTabelleUnbekannt', { 'Main.java': mainOf(
  '        System.out.println(Datenbank.abfrage("SELECT * FROM fehlt_ganz"));') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('gibt es noch nicht') < 0) { throw new Error('Tabellen-Fehlermeldung erwartet'); }
});
t('DbFehlerSpalteUnbekannt', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT)");' + NL +
  '        System.out.println(Datenbank.abfrage("SELECT falsch FROM s"));') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('Spalte') < 0) { throw new Error('Spalten-Fehlermeldung erwartet'); }
});
t('DbFehlerSelectBeiAusfuehren', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT)");' + NL +
  '        Datenbank.ausfuehren("SELECT * FROM s");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('abfrage') < 0) { throw new Error('Hinweis auf abfrage erwartet'); }
});
t('DbFehlerFehlendeAnfuehrungszeichen', { 'Main.java': mainOf(
  '        Datenbank.ausfuehren("CREATE TABLE s (name TEXT)");' + NL +
  '        Datenbank.ausfuehren("INSERT INTO s VALUES (Anna)");') }, r => {
  err1(r, 'runtime');
  if (r.errors[0].message.indexOf('gueltiger SQL-Wert') < 0) { throw new Error('Literal-Fehlermeldung erwartet'); }
});
t('DbFehlerMethodeUnbekannt', { 'Main.java': mainOf(
  '        Datenbank.speichern("irgendwas");') }, r => {
  err1(r, 'runtime');
});

console.log('');
console.log(failed === 0 ? ('Alle ' + count + ' Tests bestanden.') : (failed + ' von ' + count + ' Tests fehlgeschlagen.'));
process.exit(failed === 0 ? 0 : 1);

