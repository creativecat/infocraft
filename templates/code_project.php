<section class="panel">
    <div class="panel-head">
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>">← Zurück</a>
    </div>
    <?php if ($isForeign): ?>
    <div class="notice">
        <strong>🎓 Schülerprojekt von <?= e($ownerName) ?></strong>
        <p>Du bist als Lehrkraft hier. Änderungen speicherst du wie gewohnt, die Schülerin oder der Schüler sieht dieselben Dateien. Perfekt, um den Zwischenstand anzusehen oder gemeinsam am Code zu arbeiten.</p>
    </div>
    <?php endif; ?>
    <?php if ($project->type === 'code' && $project->language === 'java'): ?>
    <!-- Java-IDE über die volle Breite: Dateien im eigenen Fenster, Editor und Konsole in einem gemeinsamen Fenster -->
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="ide-savebar" id="code-save-form">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="button" id="java-format" title="Rückt deinen Java-Code automatisch ein: 4 Leerzeichen pro Ebene, sauber eingerückt und ausgerückt.">🧹 Formatieren</button>
        <span class="save-status" id="java-save-info" title="Dein Code wird bei jeder Änderung automatisch gespeichert – auch ohne Speichern-Klick."></span>
    </form>
    <div class="ide">
        <aside class="ide-side">
            <div class="ide-side-title">Dateien</div>
            <div class="ide-side-files">
                <?php foreach ($files as $i => $file): ?>
                <div class="ide-file<?= $i === 0 ? ' active' : '' ?>" data-file-row="<?= (int) $file->id ?>">
                    <button type="button" class="ide-file-btn file-tab<?= $i === 0 ? ' active' : '' ?>" data-file-tab="<?= (int) $file->id ?>" title="<?= e($file->name) ?>"><?= e($file->name) ?></button>
                    <?php if ($file->id > 0): ?>
                    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/datei/' . (int) $file->id . '/loeschen') ?>" class="form-inline ide-file-del">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost" type="submit" title="Datei <?= e($file->name) ?> löschen">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/datei') ?>" class="form-inline ide-side-new">
                <?= csrf_field() ?>
                <input type="text" name="name" placeholder="Auto.java" pattern="[A-Za-z][A-Za-z0-9_]*\.java" required>
                <button class="btn btn-primary" type="submit">＋ Neue Datei</button>
            </form>
        </aside>
        <div class="ide-work">
        <div class="ide-main">
            <?php foreach ($files as $i => $file): ?>
            <div class="file-pane" data-file-pane="<?= (int) $file->id ?>" data-file-name="<?= e($file->name) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                <div class="hlwrap ide-hlwrap" data-hl="java">
                    <textarea name="files[<?= (int) $file->id ?>]" form="code-save-form" rows="26" class="mono code-editor ide-editor" spellcheck="false"><?= e($file->content) ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
            <details class="ide-help">
                <summary class="muted">💾 Mini-Datenbank: SQL direkt in Java</summary>
                <p>Die eingebaute Klasse <code>Datenbank</code> speichert Tabellen im Speicher deines Browsers – ganz ohne Installation:</p>
                <pre class="code">Datenbank.ausfuehren("CREATE TABLE schueler (name TEXT, punkte INT)");
Datenbank.ausfuehren("INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)");
System.out.println(Datenbank.abfrage("SELECT * FROM schueler WHERE punkte > 10"));

int anzahl = Datenbank.anzahl("SELECT * FROM schueler");   // Zeilen einer Abfrage
String beste = Datenbank.wert("SELECT name FROM schueler ORDER BY punkte DESC");
System.out.println(Datenbank.tabellen());                  // alle Tabellen</pre>
                <p class="muted">Verfügbar: <code>ausfuehren</code> für CREATE TABLE, INSERT, UPDATE, DELETE und DROP TABLE; <code>abfrage</code> liefert das SELECT-Ergebnis als formatierte Tabelle, <code>anzahl</code> die Zeilenanzahl, <code>wert</code> den ersten Wert, <code>tabellen</code> die vorhandenen Tabellen. Text in SQL gehört in einfache Anführungszeichen ('Anna'), Zahlen ohne (12). Die Datenbank startet bei jedem Ausführen neu und leer.</p>
            </details>
        </div>
        <div class="ide-console">
            <div class="console-run">
                <button class="btn btn-primary" id="run-java" type="button">▶ Ausführen</button>
                <button class="btn btn-ghost" id="java-step" type="button" title="Führt genau eine Anweisung aus und zeigt in den Zeilennummern, welche Zeile als Nächstes dran ist.">👣 Einzelschritt</button>
                <button class="btn btn-ghost" id="java-run-rest" type="button" title="Lässt dein Programm vom Pausenpunkt an bis zum Ende laufen.">⏩ Weiter</button>
                <span class="muted">Strg + Enter funktioniert auch. Dein Programm läuft komplett in deinem Browser.</span>
            </div>
            <div id="java-step-info" class="step-info"></div>
            <div class="console-tabs" role="tablist">
                <button type="button" class="console-tab active" data-ctab="out">▶ Ausgabe</button>
                <button type="button" class="console-tab" data-ctab="classes">📐 Klassen</button>
                <button type="button" class="console-tab" data-ctab="repl">🧪 Objektkonsole</button>
                <button type="button" class="console-tab" data-ctab="objects">🧩 Objekte <span class="err-badge" id="java-obj-badge">0</span></button>
                <button type="button" class="console-tab" data-ctab="api">📖 API-Hilfe</button>
            </div>
            <div class="console-pane" data-cpane="out">
                <pre id="java-console" class="code ide-console-out">Noch nichts ausgeführt.</pre>
            </div>
            <div class="console-pane" data-cpane="classes" hidden>
                <p class="muted">Dein Klassendiagramm wie in BlueJ: live aus deinem Code, ganz ohne Ausführen. Jede Klasse mit Attributen und Methoden (+ öffentlich, - privat, # geschützt, ↳ Vererbung). Ändere den Code – das Diagramm folgt sofort.</p>
                <div id="java-classes" class="umldiag"></div>
                <?php if ($diagramAuto): ?>
                <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/diagramm') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="In der Diagramm-Werkstatt weiterbearbeiten und als PNG exportieren">📐 Als Diagramm übernehmen (PNG-Export)</button>
                </form>
                <?php else: ?>
                <p class="muted">Das Übernehmen in die Diagramm-Werkstatt hat deine Lehrkraft für deine Klasse deaktiviert.</p>
                <?php endif; ?>
            </div>
            <div class="console-pane" data-cpane="repl" hidden>
                <p class="muted" id="java-repl-hint">Erst ▶ Ausführen klicken, dann kannst du hier Objekte erzeugen und Methoden aufrufen.</p>
                <div id="java-repl-log" class="repl-log"></div>
                <div class="repl-input-row">
                    <span class="repl-prompt">›</span>
                    <input id="java-repl-in" class="mono" type="text" autocomplete="off" spellcheck="false" placeholder='Auto a = new Auto("BMW");'>
                </div>
                <div id="java-repl-ac" class="ac-box" hidden></div>
            </div>
            <div class="console-pane" data-cpane="api" hidden>
                <div class="api-docs">
                    <p class="muted">Hier stehen alle fertigen Klassen, die du in deinen Java-Programmen benutzen kannst. Zum Aufklappen auf die Überschrift klicken.</p>

                    <details open>
                        <summary>🖨️ Ausgeben und Rechnen (System.out, Math, String, Datenbank)</summary>
                        <table>
                            <tr><td><pre class="code">System.out.println(text);</pre></td><td>Schreibt eine Zeile in die Ausgabe (Zahlen, Text, Rechnungen).</td></tr>
                            <tr><td><pre class="code">System.out.print(text);</pre></td><td>Wie println, aber ohne Zeilenumbruch am Ende.</td></tr>
                            <tr><td><pre class="code">Math.random()</pre></td><td>Eine Zufallszahl zwischen 0.0 und 1.0 (z. B. <code>(int)(Math.random()*6)+1</code> für einen Würfel).</td></tr>
                            <tr><td><pre class="code">Math.abs(x) · Math.max(a,b) · Math.min(a,b)</pre></td><td>Betrag, größter und kleinster Wert.</td></tr>
                            <tr><td><pre class="code">Math.pow(a,b) · Math.sqrt(x) · Math.round(x)</pre></td><td>Hoch, Wurzel, Runden.</td></tr>
                            <tr><td><pre class="code">text.length() · text.toUpperCase()</pre></td><td>String-Methoden: Länge, Großbuchstaben u.&nbsp;a. (charAt, substring, indexOf, equals, contains, replace, trim).</td></tr>
                            <tr><td><pre class="code">Integer.parseInt(text)</pre></td><td>Macht aus "42" die Zahl 42.</td></tr>
                        </table>
                        <p class="muted">Die Mini-Datenbank <code>Datenbank</code> ist oben im Editor unter "Mini-Datenbank" erklärt.</p>
                    </details>

                    <details>
                        <summary>👣 Einzelschritt: Anweisung für Anweisung</summary>
                        <p>Mit <strong>👣 Einzelschritt</strong> führst du genau <em>eine</em> Anweisung aus (z.&nbsp;B. erst das Objekt erzeugen), dann siehst du in den Zeilennummern <strong>gelb markiert</strong>, welche Zeile als Nächstes drankommt. Immer weiterklicken – so siehst du, wie dein Programm Schritt für Schritt läuft. <strong>⏩ Weiter</strong> lässt den Rest bis zum Ende laufen. Fehlerzeilen werden in den Zeilennummern <strong>rot</strong> markiert.</p>
                    </details>

                    <details>
                        <summary>🖼️ Grafik-Fenster: World und Koordinaten</summary>
                        <p>Erzeugst du eine Figur oder eine World, öffnet sich das Grafik-Fenster mit einem XY-Feld. <strong>Wichtig:</strong> Der Ursprung (0|0) ist <strong>links oben</strong>, y zeigt nach <strong>unten</strong> – wie in der OnlineIDE/learnj.</p>
                        <table>
                            <tr><td><pre class="code">World w = new World(800, 600);</pre></td><td>Legt die Welt an (Breite, Höhe) und öffnet das Fenster. Ohne eigene World gilt automatisch 800 × 600.</td></tr>
                            <tr><td><pre class="code">w.setBackgroundColor(Color.red);
// oder "#ff8000" oder rgb(255,128,0)</pre></td><td>Hintergrundfarbe der Welt.</td></tr>
                            <tr><td><pre class="code">w.getWidth() · w.getHeight()</pre></td><td>Breite und Höhe der Welt als Zahl.</td></tr>
                            <tr><td><pre class="code">w.setCursor("hand")</pre></td><td>Mauszeiger im Grafik-Fenster (z. B. "hand", "crosshair", "move").</td></tr>
                            <tr><td><pre class="code">new Color(255, 128, 0)</pre></td><td>Eigene Farbe aus Rot-, Grün-, Blau-Anteilen (je 0–255, optional 4. Wert: Durchsichtigkeit 0.0–1.0).</td></tr>
                            <tr><td><pre class="code">Color.red · Color.green · Color.blue
Color.yellow · Color.orange · Color.pink
Color.magenta · Color.cyan · Color.white
Color.black · Color.gray · Color.lightGray
Color.darkGray · Color.brown · Color.purple</pre></td><td>Fertige Farben. Überall, wo eine Farbe erwartet wird, gehen auch <code>"#ff8000"</code>, <code>rgb(255,128,0)</code>, <code>rgba(255,128,0,0.5)</code>, Zahlen wie <code>0xff8000</code> und Color-Objekte.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>⭐ Figuren anlegen: Circle, Rectangle, Text u. a.</summary>
                        <table>
                            <tr><td><pre class="code">Circle c = new Circle(100, 200, 30);</pre></td><td>Kreis an Position (100|200) mit Radius 30.</td></tr>
                            <tr><td><pre class="code">Ellipse e = new Ellipse(100, 200, 40, 20);</pre></td><td>Ellipse mit Radius horizontal (40) und vertikal (20).</td></tr>
                            <tr><td><pre class="code">Rectangle r = new Rectangle(50, 60, 80, 40);</pre></td><td>Rechteck an Position (50|60), Breite 80, Höhe 40.</td></tr>
                            <tr><td><pre class="code">RoundedRectangle rr = new RoundedRectangle(50, 60, 80, 40, 15);</pre></td><td>Rechteck mit abgerundeten Ecken (Ecken-Radius 15).</td></tr>
                            <tr><td><pre class="code">Text t = new Text(20, 50, 24, "Punkte: 0");</pre></td><td>Schriftgröße 24. Optional 5. Parameter: Schriftname, z. B. "Arial".</td></tr>
                            <tr><td><pre class="code">t.setText("Punkte: 1");
t.setAlignment(Alignment.center);</pre></td><td>Text ändern und links/mittig/rechts ausrichten.</td></tr>
                            <tr><td><pre class="code">Triangle tr = new Triangle(0, 0, 40, 0, 20, 30);</pre></td><td>Dreieck aus drei Punkten (x1|y1, x2|y2, x3|y3).</td></tr>
                            <tr><td><pre class="code">Line l = new Line(10, 20, 90, 120);</pre></td><td>Linie von (10|20) nach (90|120). Danach: <code>l.setPoint(x, y)</code> (Endpunkt) oder <code>l.setPoints(x1, y1, x2, y2)</code>.</td></tr>
                            <tr><td><pre class="code">Polygon p = new Polygon(false, 10, 10, 60, 10, 60, 50);
p.addPoint(35, 70);</pre></td><td>Vieleck. <code>true</code> schließt und füllt es, <code>false</code> zeichnet nur die Linien. <code>addPoints(x1, y1, ...)</code> hängt mehrere Punkte an.</td></tr>
                            <tr><td><pre class="code">Bitmap b = new Bitmap(8, 4, 100, 100, 40, 20);
b.setColor(0, 0, Color.red);</pre></td><td>Pixel-Bild: 8 Spalten, 4 Zeilen an Position (100|100), Zelle 40×20 Pixel. <code>b.setColor(x, y, farbe)</code> färbt eine Zelle, <code>b.getColorAsInt(x, y)</code> liest sie als Zahl.</td></tr>
                        </table>
                        <p class="muted">Figuren, die mit <code>new</code> erzeugt werden, erscheinen automatisch im Grafik-Fenster und in der Objekt-Übersicht.</p>
                    </details>

                    <details>
                        <summary>🎨 Farben und Rahmen der Figuren</summary>
                        <table>
                            <tr><td><pre class="code">c.setFillColor(Color.red);</pre></td><td>Füllfarbe. Mit <code>setFillColor(null)</code> wird die Figur nur noch als Rand gezeichnet.</td></tr>
                            <tr><td><pre class="code">c.setBorderColor(Color.black);
c.setBorderWidth(3);</pre></td><td>Randfarbe und Randdicke (in Pixeln).</td></tr>
                            <tr><td><pre class="code">Shape.setDefaultVisibility(false);
FilledShape.setDefaultFillColor(Color.green);</pre></td><td>Voreinstellungen für ALLE Figuren, die danach erzeugt werden. Es gibt auch <code>FilledShape.setDefaultBorderColor(...)</code> und <code>FilledShape.setDefaultBorderWidth(...)</code>.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🔄 Bewegen, Drehen, Spiegeln: alle Figuren</summary>
                        <table>
                            <tr><td><pre class="code">r.move(10, 5);</pre></td><td>Um 10 nach rechts und 5 nach unten verschieben (auch Minus geht: hoch und links).</td></tr>
                            <tr><td><pre class="code">r.rotate(45);</pre></td><td>Um 45 Grad drehen (um die Mitte). Optional mit Drehpunkt: <code>r.rotate(45, 100, 100)</code>.</td></tr>
                            <tr><td><pre class="code">r.scale(1.5);</pre></td><td>1.5-fach strecken (unter 1: verkleinern). Optional um einen Punkt: <code>r.scale(1.5, 100, 100)</code>.</td></tr>
                            <tr><td><pre class="code">r.mirrorX(); · r.mirrorY();</pre></td><td>Spiegeln (an der Senkrechten/wagerechten Achse der Figur).</td></tr>
                            <tr><td><pre class="code">r.setVisible(false); · r.setAlpha(0.5);</pre></td><td>Verstecken und halbdurchsichtig machen (0.0 bis 1.0).</td></tr>
                            <tr><td><pre class="code">r.bringToFront(); · r.sendToBack();</pre></td><td>In den Vordergrund bzw. Hintergrund legen.</td></tr>
                            <tr><td><pre class="code">r.destroy();</pre></td><td>Figur endgültig entfernen.</td></tr>
                            <tr><td><pre class="code">r.getWorld();
r.getX(); · r.getY();</pre></td><td>Welt und Position der Figur abfragen (getX/getY gibt es für alle Figuren).</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🐢 Turtle: Zeichnen wie ein Stift</summary>
                        <table>
                            <tr><td><pre class="code">Turtle t = new Turtle();
Turtle t2 = new Turtle(100, 200);</pre></td><td>Turtle in der Mitte (oder an Position). Sie zeigt zunächst nach rechts.</td></tr>
                            <tr><td><pre class="code">t.forward(50);</pre></td><td>50 Pixel geradeaus laufen (zeichichnet eine Linie).</td></tr>
                            <tr><td><pre class="code">t.turn(90);</pre></td><td>Um 90 Grad drehen (plus = im Uhrzeigersinn).</td></tr>
                            <tr><td><pre class="code">t.penUp(); · t.penDown();</pre></td><td>Stift anheben: ab jetzt bewegt sich die Turtle, ohne zu zeichnen.</td></tr>
                            <tr><td><pre class="code">t.setBorderColor(Color.blue);
t.setBorderWidth(3);</pre></td><td>Stiftfarbe und Strichdicke.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🎬 Animation mit act() – wie in der OnlineIDE</summary>
                        <p>Wenn deine Klasse von einer Figur erbt, kannst du die Methode <code>act()</code> überschreiben. Sie wird automatisch ca. 30-mal pro Sekunde aufgerufen – perfekt für Bewegungen und Spiele:</p>
                        <pre class="code">class Ball extends Circle {
    Ball() { super(100, 100, 20); }
    void act() {
        move(3, 0);                  // fliegt nach rechts
        if (getX() > 800) { move(-800, 0); }
    }
}</pre>
                        <p class="muted">Das Programm läuft danach weiter, bis du neu startest oder ein Fehler auftritt.</p>
                    </details>

                    <details>
                        <summary>⌨️ Tastatur und 🖱️ Maus: Ereignisse</summary>
                        <p>Diese Methoden kannst du in deine Klasse schreiben (überschreiben). InfoCraft ruft sie automatisch auf:</p>
                        <table>
                            <tr><td><pre class="code">void onKeyDown(String taste) { ... }
void onKeyUp(String taste) { ... }
void onKeyTyped(String zeichen) { ... }</pre></td><td>Beim Drücken/Loslassen einer Taste. taste ist z. B. <code>"ArrowLeft"</code>, <code>"ArrowRight"</code>, <code>"ArrowUp"</code>, <code>"ArrowDown"</code>, <code>"Space"</code>, <code>"a"</code>.</td></tr>
                            <tr><td><pre class="code">boolean akt = isKeyDown("ArrowRight");</pre></td><td>Fragt dauernd ab, ob eine Taste gerade gedrückt ist (auch "links", "rechts", "oben", "unten" auf Deutsch).</td></tr>
                            <tr><td><pre class="code">void onMouseDown(int taste) { ... }
void onMouseUp(int taste) { ... }</pre></td><td>Klick auf die Figur (taste: 0 = links, 2 = rechts).</td></tr>
                            <tr><td><pre class="code">void onMouseEnter() { ... }
void onMouseLeave() { ... }</pre></td><td>Maus fährt über die Figur / verlässt sie.</td></tr>
                        </table>
                        <p class="muted">Das Grafik-Fenster muss angeklickt sein, damit die Tastatur ankommt.</p>
                    </details>

                    <details>
                        <summary>👥 Group: Figuren zusammen bewegen + Zusammenstöße</summary>
                        <table>
                            <tr><td><pre class="code">Group g = new Group(auto, ball);
g.add(rad2);</pre></td><td>Gruppe anlegen (oder Figuren einzeln hinzufügen). Eine Figur gehört zu höchstens einer Gruppe.</td></tr>
                            <tr><td><pre class="code">g.move(0, 5); g.rotate(45); g.scale(2);</pre></td><td>Alle Mitglieder gemeinsam bewegen, drehen, strecken.</td></tr>
                            <tr><td><pre class="code">g.setVisible(false); · g.destroy();</pre></td><td>Ganze Gruppe verstecken oder entfernen.</td></tr>
                            <tr><td><pre class="code">Shape[] treffer = g.getCollidingShapes(ball);</pre></td><td>Welche Figuren der Gruppe berühren die Figur? (Überprüfung über die Umrahmung, wie in der OnlineIDE)</td></tr>
                            <tr><td><pre class="code">Shape[][] paare = g.getCollisionPairs(andereGruppe, true);</pre></td><td>Welche Paare berühren sich? <code>true</code> stoppt nach dem ersten Treffer.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🔘 Knöpfe und Eingabefelder im Grafik-Fenster</summary>
                        <p>Mit diesen Elementen bauen Kinder echte kleine Programme mit Bedienung:</p>
                        <table>
                            <tr><td><pre class="code">Button b = new Button(20, 20, 120, 36);
b.setText("Neu starten");
b.setFillColor(Color.lightGray);
b.setBorderColor(Color.black);
b.setBorderWidth(1);
b.setFontSize(16);</pre></td><td>Knopf an Position (20|20), 120×36 Pixel. Der Text passt sich automatisch an.</td></tr>
                            <tr><td><pre class="code">Textfield tf = new Textfield(20, 70, 140, 30);
tf.setText("Name...");</pre></td><td>Eingabefeld. <code>tf.getText()</code> liest den Text.</td></tr>
                            <tr><td><pre class="code">Checkbox cb = new Checkbox(20, 110, 200, 24);
cb.setChecked(true);</pre></td><td>Ankreuzfeld, <code>cb.isChecked()</code> fragt es ab.</td></tr>
                            <tr><td><pre class="code">Radiobutton rb = new Radiobutton(20, 140, 200, 24);
rb.setSelected(true);</pre></td><td>Auswahlknopf, <code>rb.isSelected()</code> fragt ihn ab.</td></tr>
                        </table>
                        <p><strong>Reagieren:</strong> Schreibe in deine eigene Unterklasse die Methode <code>onChange(String neuerWert)</code> – sie wird aufgerufen, wenn jemand klickt oder tippt:</p>
                        <pre class="code">class MeinKnopf extends Button {
    MeinKnopf() { super(20, 20, 120, 36); setText("Klick mich"); }
    void onChange(String wert) {
        setFillColor(Color.green);
    }
}</pre>
                        <p class="muted">Hinweis: In der OnlineIDE heißen die Klassen anders (Label, TextField, RadioButton); hier heißen sie Button, Textfield, Checkbox, Radiobutton.</p>
                    </details>

                    <p class="muted">Vorbild für die Grafik-API ist die <a href="https://www.onlineide.net" rel="noopener" target="_blank">OnlineIDE/learnj</a> ("Java lernen durch Ausprobieren"). Fast alle deren Klassen und Methoden funktionieren hier genauso – kleine Unterschiede stehen direkt bei den Klassen.</p>
                </div>
            </div>
            <div class="console-pane" data-cpane="objects" hidden>
                <p class="muted">Jedes <code>new</code> landet hier: Alle Objekte deines Programms mit ihren Attributwerten – dein Objektdiagramm neben dem Klassendiagramm.</p>
                <div id="java-objects" class="objdiag">Noch keine Objekte.</div>
            </div>
            <div class="console-errors-box" id="java-errors-box">
                <div class="console-errors-head">🚨 Fehlerkonsole <span class="err-badge" id="java-error-badge">0</span></div>
                <div class="java-errors" id="java-errors">Noch nichts ausgeführt – hier erscheinen Fehler mit Datei, Zeile und Tipp.</div>
                <button class="btn btn-ghost" id="java-clear" type="button">Leeren</button>
            </div>
        </div>
        </div>
    </div>
    <?php else: ?>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <label class="field"><span>Inhalt <?= $project->type === 'html' ? '(HTML)' : '(' . e($project->language ?? 'Code') . ')' ?></span>
            <div class="hlwrap" data-hl="<?= e($project->type === 'html' ? 'xml' : ($project->language ?? 'plaintext')) ?>">
                <textarea name="content" id="code-editor" rows="18" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
            </div>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>
    <?php endif; ?>
</section>

<?php if ($project->type === 'code' && $project->language === 'python'): ?>
<section class="panel">
    <div class="panel-head">
        <h3>📐 Klassendiagramm</h3>
    </div>
    <?php if ($diagramAuto): ?>
    <p class="muted">Aus diesem <?= e($project->language ?? 'Code') ?>-Skript wird automatisch ein Klassendiagramm erzeugt (Klassen, Attribute, Methoden, Vererbung). Danach kannst du es weiterbearbeiten und als PNG exportieren.</p>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/diagramm') ?>" class="form-inline">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Klassendiagramm erzeugen</button>
    </form>
    <?php else: ?>
    <div class="notice">
        <strong>Deaktiviert</strong>
        <p>Deine Lehrkraft hat das automatische Erzeugen von Klassendiagrammen für deine Klasse deaktiviert. Eigene Diagramme kannst du weiterhin in der Diagramm-Werkstatt anlegen.</p>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>

<?php if ($project->type === 'code' && $project->language === 'python'): ?>
<section class="panel">
    <div class="panel-head">
        <h3>▶️ Python ausführen</h3>
        <button class="btn btn-primary" id="run-python" type="button">▶ Los</button>
    </div>
    <p class="muted">Dein Programm läuft vollständig in deinem Browser (Pyodide): Es wird kein Code auf dem Server ausgeführt. Mit Strg + Enter startest du ebenfalls. Beim ersten Start wird Python geladen, das dauert einen Moment.</p>
    <pre id="code-output" class="code">Noch nichts ausgeführt.</pre>
</section>
<?php elseif ($project->type === 'code' && $project->language === 'java'): ?>
<script src="<?= url('assets/js/java-vm.js') ?>"></script>
<?php elseif ($project->type === 'html'): ?>
<section class="panel">
    <div class="notice">
        <strong>💡 Tipp</strong>
        <p>Für HTML-Seiten gibt es die <a href="<?= url('werkstatt/html') ?>">HTML-Werkstatt</a> mit Sofortvorschau beim Tippen.</p>
    </div>
</section>
<?php endif; ?>
