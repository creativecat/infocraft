<section class="panel">
    <div class="panel-head">
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>" title="Zurück">←</a>
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
        <button class="btn btn-ghost" type="button" id="java-format" title="Formatieren: rückt deinen Java-Code automatisch ein, 4 Leerzeichen pro Ebene, sauber eingerückt und ausgerückt.">🧹</button>
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
                <button class="btn btn-primary" type="submit" title="Neue Datei">＋</button>
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
            <div class="console-errors-box" id="java-errors-box">
                <div class="console-errors-head">🚨 Fehlerkonsole <span class="err-badge" id="java-error-badge">0</span></div>
                <div class="java-errors" id="java-errors">Noch nichts ausgeführt – hier erscheinen Fehler mit Datei, Zeile und Tipp.</div>
                <button class="btn btn-ghost" id="java-clear" type="button" title="Leeren">🗑</button>
            </div>
        </div>
        <div class="ide-console">
            <div class="console-run">
                <button class="btn btn-primary run-icon" id="run-java" type="button" title="Ausführen (Strg + Enter): dein Programm läuft komplett in deinem Browser." aria-label="Ausführen">▶</button>
                <button class="btn btn-ghost run-icon" id="java-step" type="button" title="Einzelschritt: führt genau eine Anweisung aus und zeigt in den Zeilennummern, welche Zeile als Nächstes dran ist." aria-label="Einzelschritt">👣</button>
                <button class="btn btn-ghost run-icon" id="java-run-rest" type="button" title="Weiter: lässt dein Programm vom Pausenpunkt an bis zum Ende laufen." aria-label="Weiter">⏩</button>
                <button class="btn btn-ghost run-icon" id="java-stop" type="button" title="Stopp: hält dein Programm an – die act()-Animation stoppt und ein angefangener Einzelschritt wird abgebrochen." aria-label="Stopp">⏹</button>
                <span class="muted">Strg + Enter funktioniert auch. Dein Programm läuft komplett in deinem Browser.</span>
            </div>
            <div id="java-step-info" class="step-info"></div>
            <div class="console-tabs" role="tablist">
                <button type="button" class="console-tab active" data-ctab="out" title="Ausgabe">▶</button>
                <button type="button" class="console-tab" data-ctab="classes" title="Klassen (Klassendiagramm) und Objekte (Objektdiagramm)">📐 <span class="err-badge" id="java-obj-badge">0</span></button>
                <button type="button" class="console-tab" data-ctab="repl" title="Objektkonsole">🧪</button>
                <button type="button" class="console-tab api-open" id="java-api-open" title="Öffnet die API-Hilfe in einem eigenen Fenster. Es bleibt offen, während du weiterprogrammierst.">📖</button>
            </div>
            <div class="console-pane" data-cpane="out">
                <pre id="java-console" class="code ide-console-out">Noch nichts ausgeführt.</pre>
            </div>
            <div class="console-pane" data-cpane="classes" hidden>
                <h4 class="diag-head">📐 Klassendiagramm</h4>
                <p class="muted">Live aus deinem Code, ganz ohne Ausführen. Jede Klasse mit harten Ecken: blauer Kopf, Attribute und Methoden mit Strich davor getrennt (+ öffentlich, - privat, # geschützt, ↳ Vererbung). Genau wie in der Diagramm-Werkstatt.</p>
                <div id="java-classes" class="umldiag"></div>
                <?php if ($diagramAuto): ?>
                <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/diagramm') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="In der Diagramm-Werkstatt weiterbearbeiten und als PNG exportieren">📐</button>
                </form>
                <?php else: ?>
                <p class="muted">Das Übernehmen in die Diagramm-Werkstatt hat deine Lehrkraft für deine Klasse deaktiviert.</p>
                <?php endif; ?>
                <h4 class="diag-head">🧩 Objektdiagramm <span class="pill">nach UML</span></h4>
                <p class="muted">Jedes <code>new</code> landet hier: alle Objekte deines Programms als Kacheln mit abgerundeten Ecken, darunter ihre Attributwerte – die kleine Objektbank neben dem Klassendiagramm, wie in BlueJ.</p>
                <div id="java-objects" class="objdiag">Noch keine Objekte. Klicke oben auf ▶ Ausführen.</div>
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
            <div class="api-docs" id="java-api-src" hidden>
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
                        <p class="muted">Die Mini-Datenbank <code>Database</code> ist ganz unten im Fenster Mini-Datenbank erklärt.</p>
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
                            <tr><td><pre class="code">Circle c = new Circle(100, 200, 30);</pre></td><td>Kreis an Position (100|200) mit Radius 30. Für einen einzelnen Punkt nimm einen kleinen Kreis: <code>new Circle(100, 200, 5)</code>.</td></tr>
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

                    <details>
                        <summary>⌨️ Eingabe mit Input – wie in der Online-IDE</summary>
                        <p>Die Klasse <code>Input</code> öffnet ein Dialogfenster und fragt deine Nutzerin oder deinen Nutzer ab. Der zweite Wert ist die Antwort, falls jemand nichts eintippt:</p>
                        <table>
                            <tr><td><pre class="code">String name = Input.readString("Wie heißt du?", "Alex");</pre></td><td>Text eingeben.</td></tr>
                            <tr><td><pre class="code">int alter = Input.readInt("Wie alt bist du?", 12);</pre></td><td>Ganze Zahl eingeben (falsche Eingaben werden nachgefragt).</td></tr>
                            <tr><td><pre class="code">double groesse = Input.readDouble("Wie groß bist du?", 1.5);</pre></td><td>Kommazahl (Punkt oder Komma geht).</td></tr>
                            <tr><td><pre class="code">boolean weiter = Input.readBoolean("Noch eine Runde?", true);</pre></td><td>ja/nein, true/false, 1/0.</td></tr>
                            <tr><td><pre class="code">char buchstabe = Input.readChar("Dein Lieblingsbuchstabe?", "a");</pre></td><td>Genau ein Zeichen.</td></tr>
                            <tr><td><pre class="code">if (Input.isKeyDown(Key.ArrowRight)) { ... }</pre></td><td>Ist eine Taste gerade gedrückt? Tastennamen: <code>Key.ArrowUp</code>, <code>Key.ArrowDown</code>, <code>Key.ArrowLeft</code>, <code>Key.ArrowRight</code>, <code>Key.Enter</code>, <code>Key.Space</code>, <code>Key.Escape</code> u.&nbsp;a.</td></tr>
                        </table>
                        <p class="muted">Scanner gibt es hier nicht – nimm immer Input. Gleiche Klasse, gleiche Methoden wie in der Online-IDE.</p>
                    </details>

                    <details>
                        <summary>🎲 Zufall mit Random</summary>
                        <table>
                            <tr><td><pre class="code">int wurf = Random.randint(1, 6);</pre></td><td>Würfel: eine Zahl von 1 bis 6 (beide Grenzen können kommen).</td></tr>
                            <tr><td><pre class="code">double zufall = Random.randdouble(0, 1);</pre></td><td>Kommazahl zwischen 0 und 1.</td></tr>
                            <tr><td><pre class="code">Random wuerfel = new Random();
int augen = wuerfel.nextInt(6);      // 0 bis 5
double muecke = wuerfel.nextDouble(); // 0.0 bis 1.0</pre></td><td>Eigenes Zufalls-Objekt wie in Java.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>✅ Prüfen mit Assertions: automatische Tests</summary>
                        <p>Mit <code>Assertions</code> prüft dein Programm sich selbst – wie die Unit-Tests der Profis. Bestandene Prüfungen drucken ein ✓, schlägt eine fehl, stoppt das Programm mit einer klaren Meldung:</p>
                        <pre class="code">Assertions.assertEquals(4, 2 * 2, "Malnehmen stimmt");
Assertions.assertTrue(name.length() > 0, "Name darf nicht leer sein");
Assertions.assertFalse(7 < 5, "Sieben ist nicht kleiner als fünf");
Assertions.assertCodeReached("Das Ende wurde erreicht");</pre>
                        <p class="muted">Schlägt eine Prüfung fehl, steht da: „Erwartet 4, erhalten 5 – Rechnung prüfen“ mit Datei und Zeile.</p>
                    </details>

                    <details>
                        <summary>📐 Rechnen mit Vector2 und MathTools</summary>
                        <p><code>Vector2</code> ist ein Pfeil in der Ebene – perfekt für Bewegungen und Geometrie (gleiche Klasse wie in der Online-IDE):</p>
                        <table>
                            <tr><td><pre class="code">Vector2 v = new Vector2(3, 4);
System.out.println(v.getLength());   // 5.0
System.out.println(v.getAngleDeg()); // 53.13...</pre></td><td>Länge und Winkel (in Grad). Die Koordinaten sind Attribute: <code>v.x</code> und <code>v.y</code>.</td></tr>
                            <tr><td><pre class="code">Vector2 summe = a.plus(b);   // neuer Vektor
a.add(b);                   // verändert a selbst</pre></td><td>plus/minus/scaledBy/rotatedBy geben NEUE Vektoren; add/sub/scale/rotate verändern den Vektor.</td></tr>
                            <tr><td><pre class="code">v.scalarProduct(w); v.distanceTo(w);
Vector2.distance(x1, y1, x2, y2);
Vector2.fromPolarCoordinates(5, 90);</pre></td><td>Punktprodukt, Abstände, Polarkoordinaten.</td></tr>
                        </table>
                        <p><code>MathTools</code> rechnet mit Figuren-Punkten:</p>
                        <table>
                            <tr><td><pre class="code">MathTools.distancePointToLine(p, a, b);</pre></td><td>Abstand Punkt p zur Geraden durch a und b.</td></tr>
                            <tr><td><pre class="code">MathTools.distancePointToLineSegment(p, a, b);</pre></td><td>Abstand zur Strecke [ab] (nicht zur unendlichen Geraden).</td></tr>
                            <tr><td><pre class="code">Vector2 s = MathTools.intersectLineSegments(p0, p1, p2, p3);</pre></td><td>Schnittpunkt zweier Strecken oder <code>null</code>.</td></tr>
                            <tr><td><pre class="code">MathTools.polygonContainsPoint(ecken, punkt);
MathTools.intersectCircleWithPolygon(mx, my, r, ecken);</pre></td><td>Liegt der Punkt im Vieleck? Wo schneidet der Kreis die Kanten?</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🧭 Richtung und Position: Direction, Position, CollisionPair</summary>
                        <table>
                            <tr><td><pre class="code">Direction.top · Direction.right
Direction.bottom · Direction.left</pre></td><td>Richtungen wie in der Online-IDE. <code>Direction.getDx(richtung)</code> und <code>Direction.getDy(richtung)</code> geben die Änderung pro Schritt (rechts: +1|0, unten: 0|+1 ...).</td></tr>
                            <tr><td><pre class="code">Position p = new Position(3, 7);
System.out.println(p);    // (3|7)
p.x = 10;</pre></td><td>Ein Gitterpunkt mit ganzen Koordinaten (x und y sind Attribute).</td></tr>
                            <tr><td><pre class="code">CollisionPair paar = new CollisionPair(auto, ball);
paar.shapeA.getX(); paar.shapeB.getY();</pre></td><td>Merkt sich zwei Figuren, die zusammengestoßen sind (z. B. aus <code>getCollisionPairs</code>).</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🔊 Sound und 🎮 Gamepad</summary>
                        <table>
                            <tr><td><pre class="code">Sound.playSound("coin");
Sound.playSound("beep");</pre></td><td>Kurze Töne: beep, click, coin, boom, laser, jump, win, lose, alarm.</td></tr>
                            <tr><td><pre class="code">if (Gamepad.isConnected(0)) { ... }
Gamepad.isButtonDown(0, 0);
Gamepad.getAxisValue(0, 1);</pre></td><td>Controller abfragen: Nummer des Pads, Knopfnummer (A = 0) oder Achse (0 = links/rechts, 1 = hoch/runter).</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🥧 Kreisbogen und Kreissektor: Arc und Sector</summary>
                        <table>
                            <tr><td><pre class="code">Arc a = new Arc(200, 150, 20, 80, 0, 90);</pre></td><td>Kreisbogen-Ring: Mittelpunkt (200|150), Innenradius 20, Außenradius 80, von 0° bis 90° (0° = rechts, im Uhrzeigersinn).</td></tr>
                            <tr><td><pre class="code">a.setInnerRadius(30); a.setOuterRadius(90);
a.setStartAngle(45); a.setEndAngle(180);
a.containsPoint(250, 150);</pre></td><td>Radien und Winkel ändern (Getter: <code>getInnerRadius()</code> u.&nbsp;a.) und Punkte testen.</td></tr>
                            <tr><td><pre class="code">Sector s = new Sector(200, 150, 80, 0, 45);
s.drawRadii(true);</pre></td><td>Tortenstück („Kuchenstück“) mit Radius 80 von 0° bis 45°. <code>drawRadii(true)</code> zeichnet die Radien mit.</td></tr>
                        </table>
                        <p class="muted">Perfekt für Tortendiagramme, Pac-Man-Mund und Uhrzeiger.</p>
                    </details>

                    <details>
                        <summary>🖥 SystemTools: Ausgabe und Tempo</summary>
                        <table>
                            <tr><td><pre class="code">SystemTools.clearScreen();</pre></td><td>Löscht die bisherige Ausgabe.</td></tr>
                            <tr><td><pre class="code">SystemTools.setSpeed(60);
System.out.println(SystemTools.getSpeed());</pre></td><td>Wie oft pro Sekunde <code>act()</code> aufgerufen wird (Standard 30, für flüssige Spiele 60).</td></tr>
                            <tr><td><pre class="code">SystemTools.startLoggingPrintOutput();
System.out.println("wichtig");
System.out.println(SystemTools.getPrintLog());</pre></td><td>Alle Ausgaben mitschreiben und später auslesen.</td></tr>
                            <tr><td><pre class="code">SystemTools.getNumberOfProcessorCores();</pre></td><td>Wie viele Rechenkerne der Computer hat.</td></tr>
                        </table>
                    </details>

                    <p class="muted">Vorbild für die Grafik-API ist die <a href="https://www.onlineide.net" rel="noopener" target="_blank">OnlineIDE/learnj</a> ("Java lernen durch Ausprobieren"). Fast alle deren Klassen und Methoden funktionieren hier genauso – kleine Unterschiede stehen direkt bei den Klassen.</p>
                </div>
            </div>
            </div>
            <div class="gfx-dock" id="gfx-dock" hidden></div>
        </div>
    </div>
    <?php if ($dbPanel): ?>
    <section class="panel" id="db-panel">
        <div class="panel-head">
            <h3>💾 Mini-Datenbank: SQL direkt in Java</h3>
        </div>
        <p>Die eingebaute Klasse <code>Database</code> speichert Tabellen im Speicher deines Browsers – ganz ohne Installation:</p>
        <pre class="code">Database.execute("CREATE TABLE schueler (name TEXT, punkte INT)");
Database.execute("INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)");
System.out.println(Database.query("SELECT * FROM schueler WHERE punkte > 10"));

int anzahl = Database.count("SELECT * FROM schueler");   // Zeilen einer Abfrage
String beste = Database.value("SELECT name FROM schueler ORDER BY punkte DESC");
System.out.println(Database.tables());                     // alle Tabellen</pre>
        <p class="muted">Verfügbar: <code>execute</code> für CREATE TABLE, INSERT, UPDATE, DELETE und DROP TABLE; <code>query</code> liefert das SELECT-Ergebnis als formatierte Tabelle, <code>count</code> die Zeilenanzahl, <code>value</code> den ersten Wert, <code>tables</code> die vorhandenen Tabellen. Text in SQL gehört in einfache Anführungszeichen ('Anna'), Zahlen ohne (12). Die Datenbank startet bei jedem Ausführen neu und leer.</p>
    </section>
    <?php endif; ?>
    <?php else: ?>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <label class="field"><span>Inhalt <?= $project->type === 'html' ? '(HTML)' : '(' . e($project->language ?? 'Code') . ')' ?></span>
            <div class="hlwrap" data-hl="<?= e($project->type === 'html' ? 'xml' : ($project->language ?? 'plaintext')) ?>">
                <textarea name="content" id="code-editor" rows="18" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
            </div>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
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
        <button class="btn btn-primary" type="submit" title="Klassendiagramm erzeugen">📐</button>
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
        <button class="btn btn-primary" id="run-python" type="button" title="Los">▶</button>
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
