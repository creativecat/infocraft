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
  var c6 = s.complete('Datenbank.');
  labels = labelsOf(c6);
  if (labels.indexOf('abfrage(') < 0) { throw new Error('abfrage fehlt: ' + JSON.stringify(labels)); }
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

console.log('');
console.log(failed === 0 ? ('Alle ' + count + ' Tests bestanden.') : (failed + ' von ' + count + ' Tests fehlgeschlagen.'));
process.exit(failed === 0 ? 0 : 1);

