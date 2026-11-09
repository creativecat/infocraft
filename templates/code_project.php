<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>" title="Zurück">←</a>
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <?php if (($presentationTask ?? null) !== null): ?>
        <span class="pill" title="Präsentationsmodus: Du arbeitest in einer temporären Kopie dieser Aufgabe. Die Aufgabenvorlage und die Materialien bleiben unangetastet, dein Code wird nur hier gespeichert. Mit „Präsentation beenden“ wird die Kopie gelöscht.">🎬 Präsentationsmodus ℹ️</span>
        <?php endif; ?>
        <span class="save-status" id="java-save-info" title="Dein Code wird bei jeder Änderung automatisch gespeichert – auch ohne Speichern-Klick."></span>
    </div>
    <?php if ($isForeign): ?>
    <div class="notice">
        <strong>🎓 Schülerprojekt von <?= e($ownerName) ?></strong>
        <p>Du bist als Lehrkraft hier. Änderungen speicherst du wie gewohnt, die Schülerin oder der Schüler sieht dieselben Dateien. Perfekt, um den Zwischenstand anzusehen oder gemeinsam am Code zu arbeiten.</p>
    </div>
    <?php endif; ?>
    <?php if (($presentationTask ?? null) !== null): ?>
    <div class="two-col pres-task-info">
        <div>
            <h4 class="diag-head">📝 Aufgabenbeschreibung</h4>
            <?php if ($presentationTask->description !== ''): ?>
            <div class="task-desc"><?= $presentationTask->descriptionHtml() ?></div>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe ist keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="diag-head">📋 ToDos (zum Abhaken)</h4>
            <?php if (($presentationTodos ?? []) !== []): ?>
            <ul class="todo-list" id="pres-todos" data-task="<?= (int) $presentationTask->id ?>">
                <?php foreach ($presentationTodos as $ptodo): ?>
                <li class="todo-item">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                        <input type="checkbox" data-pres-todo="<?= (int) $ptodo->id ?>">
                        <span><?= e($ptodo->title) ?></span>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe sind keine ToDos hinterlegt.</p>
            <?php endif; ?>
            <?php if (($presentationMaterials ?? []) !== []): ?>
            <h4 class="diag-head">📎 Materialien der Aufgabe</h4>
            <ul class="material-list">
                <?php foreach ($presentationMaterials as $pmat): ?>
                <li class="material-item"><a href="<?= url('aufgaben/' . (int) $presentationTask->id . '/material/' . (int) $pmat->id) ?>">📄 <?= e($pmat->filename) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (trim($presentationTask->sampleSolution) !== ''): ?>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/loesung/fenster') ?>" target="_blank" title="Musterlösung der Aufgabe in einem eigenen Fenster anzeigen (bleibt unabhängig vom Präsentationsfenster offen, inkl. Erwartungshorizont)">📄 Musterlösung anzeigen</a>
            </div>
            <?php endif; ?>
            <?php if ($project->type === 'code' && $project->language === 'python'): ?>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/download') ?>" title="Diese Version als Musterlösung herunterladen">⬇️ als Musterlösung herunterladen</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/ende') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="Präsentation beenden und diese temporäre Kopie löschen">✕ beenden und löschen</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if (($workingTask ?? null) !== null): ?>
    <div class="two-col pres-task-info task-work-info">
        <div>
            <h4 class="diag-head">📌 Aufgabe: <?= e($workingTask->title) ?></h4>
            <?php if ($workingTask->description !== ''): ?>
            <div class="task-desc"><?= $workingTask->descriptionHtml() ?></div>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe ist keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="diag-head">📋 ToDos (zum Abhaken)</h4>
            <form method="post" action="<?= url('aufgaben/' . (int) $workingTask->id . '/todos/schueler') ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= url('werkstatt/code/' . (int) $project->id) ?>">
                <ul class="todo-list">
                    <?php foreach (($workingTodos ?? []) as $wtodo): ?>
                    <li class="todo-item">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                            <input type="checkbox" onchange="this.form.submit()" name="toggle_template_id" value="<?= (int) $wtodo->id ?>"<?= ($workingDone[(int) $wtodo->id] ?? false) ? ' checked' : '' ?>>
                            <span<?= ($workingDone[(int) $wtodo->id] ?? false) ? ' style="text-decoration:line-through;"' : '' ?>><?= e($wtodo->title) ?></span>
                        </label>
                    </li>
                    <?php endforeach; ?>
                    <?php foreach (($workingOwnTodos ?? []) as $wown): ?>
                    <li class="todo-item">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                            <input type="checkbox" onchange="this.form.submit()" name="toggle_todo_id" value="<?= (int) $wown->id ?>"<?= $wown->done === 1 ? ' checked' : '' ?>>
                            <span<?= $wown->done === 1 ? ' style="text-decoration:line-through;"' : '' ?>><?= e($wown->title) ?></span>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </form>
            <form method="post" action="<?= url('aufgaben/' . (int) $workingTask->id . '/todos/schueler') ?>" class="form-inline" style="margin-top:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= url('werkstatt/code/' . (int) $project->id) ?>">
                <input type="text" name="todo_title" placeholder="Eigenes ToDo ergänzen, z. B. Schleife nochmal üben" maxlength="200">
                <button class="btn btn-ghost" type="submit" title="Legt ein eigenes ToDo an, das nur du siehst">➕ ToDo</button>
            </form>
            <?php if (($workingMaterials ?? []) !== []): ?>
            <h4 class="diag-head">📎 Materialien der Aufgabe</h4>
            <ul class="material-list">
                <?php foreach ($workingMaterials as $wmat): ?>
                <li class="material-item"><a href="<?= url('aufgaben/' . (int) $workingTask->id . '/material/' . (int) $wmat->id) ?>">📄 <?= e($wmat->filename) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $workingTask->id . '/abgeben') ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                <input type="hidden" name="back" value="<?= url('werkstatt/code/' . (int) $project->id) ?>">
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit" title="Dieses Projekt als Lösung der Aufgabe einreichen"><?= $workingSubmitted ? 'Neue Version abgeben' : '✅ Abgeben' ?></button>
                </div>
            </form>
            <?php if ($workingSubmitted): ?><p class="muted">Dieses Projekt ist als Lösung abgegeben. Du kannst es verbessern und erneut abgeben.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($project->type === 'code' && $project->language === 'java'): ?>
    <!-- Java-IDE über die volle Breite: Dateien im eigenen Fenster, Editor und Konsole in einem gemeinsamen Fenster -->
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="ide-savebar" id="code-save-form" aria-hidden="true">
        <?= csrf_field() ?>
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
            <?php if (($presentationTask ?? null) !== null): ?>
            <div class="pres-side-actions">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/download') ?>" title="Diese Version als Musterlösung herunterladen">⬇️ Musterlösung</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/ende') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="Präsentation beenden und diese temporäre Kopie löschen">✕ beenden</button>
                </form>
            </div>
            <?php endif; ?>
        </aside>
        <div class="ide-work">
        <div class="ide-main">
            <div class="ide-editors">
            <?php foreach ($files as $i => $file): ?>
            <div class="file-pane" data-file-pane="<?= (int) $file->id ?>" data-file-name="<?= e($file->name) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                <div class="hlwrap ide-hlwrap" data-hl="java" data-ac="java">
                    <textarea name="files[<?= (int) $file->id ?>]" form="code-save-form" rows="26" class="mono code-editor ide-editor" spellcheck="false"><?= e($file->content) ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
            <button class="btn btn-ghost editor-float-btn" type="button" id="java-format" title="Formatieren: rückt deinen Java-Code automatisch ein, 4 Leerzeichen pro Ebene, sauber eingerückt und ausgerückt.">🧹</button>
            </div>
            <div class="console-errors-box" id="java-errors-box">
                <div class="console-errors-head">🚨 Fehlerkonsole <span class="err-badge" id="java-error-badge">0</span></div>
                <div class="java-errors" id="java-errors">Noch nichts ausgeführt – hier erscheinen Fehler mit Datei, Zeile und Tipp.</div>
                <button class="btn btn-ghost" id="java-clear" type="button" title="Leeren">🗑</button>
            </div>
        </div>
        <div class="ide-console">
            <div class="console-tabs" role="tablist">
                <button type="button" class="console-tab active" data-ctab="out" title="Ausgabe und Objektkonsole: Startknöpfe, Ausgabe deines Programms und Anweisungsfeld">▶</button>
                <button type="button" class="console-tab" data-ctab="classes" title="Klassendiagramm: live aus deinem Code, ganz ohne Ausführen">📐</button>
                <button type="button" class="console-tab" data-ctab="objects" title="Objektdiagramm: alle Objekte mit Attributwerten, Methoden per Klick testen">🧩 <span class="err-badge" id="java-obj-badge">0</span></button>
                <button type="button" class="console-tab" data-ctab="sprites" title="Sprites: eigene Bilder und Figuren für new Sprite(...)">🖼</button>
                <button type="button" class="console-tab api-open" id="java-api-open" title="Öffnet die API-Hilfe in einem eigenen Fenster. Es bleibt offen, während du weiterprogrammierst.">📖</button>
            </div>
            <div class="console-pane" data-cpane="out">
                <div class="console-run">
                    <button class="btn btn-primary run-icon" id="run-java" type="button" title="Ausführen (Strg + Enter): dein Programm läuft komplett in deinem Browser." aria-label="Ausführen">▶</button>
                    <button class="btn btn-ghost run-icon" id="java-step" type="button" title="Einzelschritt: führt genau eine Anweisung aus und zeigt in den Zeilennummern, welche Zeile als Nächstes dran ist." aria-label="Einzelschritt">👣</button>
                    <button class="btn btn-ghost run-icon" id="java-run-rest" type="button" title="Weiter: lässt dein Programm vom Pausenpunkt an bis zum Ende laufen." aria-label="Weiter">⏩</button>
                    <button class="btn btn-ghost run-icon" id="java-stop" type="button" title="Stopp: hält dein Programm an – die act()-Animation stoppt und ein angefangener Einzelschritt wird abgebrochen." aria-label="Stopp">⏹</button>
                </div>
                <div id="java-step-info" class="step-info"></div>
                <div class="console-block-title">Ausgabe</div>
                <pre id="java-console" class="code ide-console-out">Noch nichts ausgeführt.</pre>
                <div class="console-block-title">Objektkonsole</div>
                <p class="muted" id="java-repl-hint">Erst ▶ Ausführen klicken. Dann schreibst du hier auf mehrere Zeilen, was dein Programm tun soll: Objekte erzeugen und Methoden aufrufen, getrennt vom Entwurf deiner Klassen im Codefenster.</p>
                <div class="repl-edit">
                    <div class="repl-gutter mono" id="java-repl-gutter">1</div>
                    <div class="hlwrap" data-hl="java" data-ac="java"><textarea id="java-repl-in" class="mono" rows="4" autocomplete="off" spellcheck="false"><?= e($replText ?? '') ?></textarea></div>
                </div>
                <div class="repl-actions">
                    <button class="btn btn-primary" id="java-repl-run" type="button" title="Anweisungen ausführen (Strg + Enter): erzeugt die Objekte und ruft die Methoden auf, je Zeile eine Anweisung.">▶ Anweisungen ausführen</button>
                    <span class="muted">Die Anweisungen bleiben stehen, du kannst sie ändern und erneut ausführen. Sie werden automatisch mit deinem Projekt gespeichert, auch über das Neuladen hinaus.</span>
                </div>
                <div id="java-repl-log" class="repl-log"></div>
                <div id="java-repl-ac" class="ac-box" hidden></div>
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
            </div>
            <div class="console-pane" data-cpane="objects" hidden>
                <h4 class="diag-head">🧩 Objektdiagramm <span class="pill">nach UML</span> <details class="hint-details"><summary title="Erklärung zum Objektdiagramm und den anklickbaren Methoden">ℹ️</summary>
                    <p class="muted">Jedes <code>new</code> landet hier: alle Objekte deines Programms als Kacheln mit abgerundeten Ecken, darunter ihre Attributwerte – die kleine Objektbank neben dem Klassendiagramm, wie in BlueJ.</p>
                    <p class="muted"><strong>Methoden per Klick testen:</strong> Klicke auf einen Methodennamen unter dem Objekt. Methoden ohne Parameter laufen sofort, Rückgabewert und Ausgaben erscheinen in einer kleinen Blase am Objekt (und im Protokoll der Objektkonsole im Tab ▶). Methoden mit Parametern setzen den Aufruf ins Anweisungsfeld der Objektkonsole, damit du die Argumente ergänzt und ihn dort ausführst.</p>
                </details></h4>
                <div id="java-objects" class="objdiag">Noch keine Objekte. Klicke oben auf ▶ Ausführen.</div>
            </div>
            <div class="console-pane" data-cpane="sprites" hidden>
                <p class="muted">Sprites sind fertige Figuren für dein Programm: <code>Sprite s = new Sprite(400, 300, "Schildkröte");</code>. Standard-Figuren: Schildkröte, Auto, Katze, Hund, Fisch, Baum, Wolke, Sonne, Stern, Herz, Raumschiff, Geist, Pilz, Blume.</p>
                <p class="muted">Deine eigenen Bilder erscheinen hier und heißen im Code so wie ihre Datei (bei „ball.png“ also <code>"ball"</code>). Sie wandern beim Austeilen mit.</p>
                <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/bild') ?>" enctype="multipart/form-data" class="form-inline sprite-upload">
                    <?= csrf_field() ?>
                    <input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp" required title="Bild hochladen (PNG, JPEG, GIF oder WebP, maximal 1 MB)">
                    <button class="btn btn-primary" type="submit" title="Bild hochladen">Bild hochladen ⬆</button>
                </form>
                <?php if ($images !== []): ?>
                <div class="ide-img-list">
                    <?php foreach ($images as $image): ?>
                    <div class="ide-img-row">
                        <img src="<?= url('werkstatt/code/' . (int) $project->id . '/bild/' . rawurlencode($image->name)) ?>" alt="<?= e($image->name) ?>" class="ide-img-thumb">
                        <code class="ide-img-name"><?= e($image->name) ?></code>
                        <code class="muted">new Sprite(x, y, "<?= e($image->name) ?>")</code>
                        <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/bild/' . rawurlencode($image->name) . '/loeschen') ?>" class="form-inline ide-img-del">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost" type="submit" title="Bild <?= e($image->name) ?> löschen">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="muted">Noch keine eigenen Bilder (maximal 8 pro Projekt, jeweils maximal 1 MB).</p>
                <?php endif; ?>
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
                        <?php if ($dbPanel): ?>
                        <p class="muted">Die Mini-Datenbank <code>Database</code> ist ganz unten im Fenster Mini-Datenbank erklärt.</p>
                        <?php else: ?>
                        <p class="muted">Das Fenster Mini-Datenbank hat deine Lehrkraft für deine Klasse ausgeblendet. Die Klasse <code>Database</code> funktioniert im Programm trotzdem.</p>
                        <?php endif; ?>
                    </details>

                    <details>
                        <summary>👣 Einzelschritt: Anweisung für Anweisung</summary>
                        <p>Mit <strong>👣 Einzelschritt</strong> führst du genau <em>eine</em> Anweisung aus (z.&nbsp;B. erst das Objekt erzeugen), dann siehst du in den Zeilennummern <strong>gelb markiert</strong>, welche Zeile als Nächstes drankommt. Immer weiterklicken – so siehst du, wie dein Programm Schritt für Schritt läuft. <strong>⏩ Weiter</strong> lässt den Rest bis zum Ende laufen. Fehlerzeilen werden in den Zeilennummern <strong>rot</strong> markiert.</p>
                    </details>

                    <details>
                        <summary>🖼️ Grafik-Fenster: World und Koordinaten</summary>
                        <p>Erzeugst du eine Figur oder eine World, öffnet sich das Grafik-Fenster mit einem XY-Feld. <strong>Wichtig:</strong> Der Ursprung (0|0) ist <strong>links oben</strong>, y zeigt nach <strong>unten</strong> – wie in der OnlineIDE/learnj.</p>
                        <table>
                            <tr><td><pre class="code">World w = new World(800, 600);</pre></td><td>Legt die Welt an (Breite, Höhe) und öffnet das Fenster in genau dieser Größe. Ohne eigene World gilt automatisch 800 × 600 (im kleineren Fenster maßstabsgerecht dargestellt).</td></tr>
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
Turtle t2 = new Turtle(100, 200);</pre></td><td>Turtle in der Mitte (oder an Position). Sie zeigt zunächst nach rechts und wird als echte Schildkröte gezeichnet.</td></tr>
                            <tr><td><pre class="code">t.forward(50);</pre></td><td>50 Pixel geradeaus laufen (zeichichnet eine Linie).</td></tr>
                            <tr><td><pre class="code">t.turn(90);</pre></td><td>Um 90 Grad drehen (plus = im Uhrzeigersinn).</td></tr>
                            <tr><td><pre class="code">t.penUp(); · t.penDown();</pre></td><td>Stift anheben: ab jetzt bewegt sich die Turtle, ohne zu zeichnen.</td></tr>
                            <tr><td><pre class="code">t.setBorderColor(Color.blue);
t.setBorderWidth(3);</pre></td><td>Stiftfarbe und Strichdicke.</td></tr>
                        </table>
                    </details>

                    <details>
                        <summary>🧩 Sprite: fertige Figuren und eigene Bilder</summary>
                        <p>Mit <code>Sprite</code> zeigst du sofort schöne Figuren – wie in einem echten Spiel. Der Name ist ein String, die Figur sitzt mit ihrer Mitte an der Position:</p>
                        <table>
                            <tr><td><pre class="code">Sprite s = new Sprite(400, 300, "Schildkröte");</pre></td><td>Legt eine Figur an (Mittelpunkt 400|300). Ohne Namen: automatisch die Schildkröte.</td></tr>
                            <tr><td><pre class="code">s.scale(2); · s.rotate(45);
s.move(10, 0); · s.destroy();</pre></td><td>Wie jede Figur strecken, drehen, bewegen, entfernen – auch <code>setAlpha</code>, <code>setVisible</code>, <code>mirrorX</code> und alles unter „Bewegen, Drehen, Spiegeln“.</td></tr>
                            <tr><td><pre class="code">Sprite a = new Sprite(100, 100, "Auto");</pre></td><td>Standard-Figuren: <code>Schildkröte</code>, <code>Auto</code>, <code>Katze</code>, <code>Hund</code>, <code>Fisch</code>, <code>Baum</code>, <code>Wolke</code>, <code>Sonne</code>, <code>Stern</code>, <code>Herz</code>, <code>Raumschiff</code>, <code>Geist</code>, <code>Pilz</code>, <code>Blume</code>.</td></tr>
                            <tr><td><pre class="code">Sprite b = new Sprite(400, 300, "Ball");</pre></td><td>Eigene Bilder: Lade rechts im Tab 🖼 (Sprites) eine Grafik hoch (PNG, JPEG, GIF, WebP, maximal 1 MB). Ihr Name ohne Endung ist der Sprite-Name – bei „ball.png“ also <code>"Ball"</code> bzw. <code>"ball"</code>.</td></tr>
                        </table>
                        <p class="muted">Eigene Bilder gehören zum Projekt: Wenn deine Lehrkraft es austeilt, bekommen alle dieselben Sprites. Die Größe des Grafik-Fensters legst du im Code fest (<code>new World(640, 480)</code>), bei jedem ▶ Ausführen startet es leer, und Schließen/Anzeigen stehen direkt am Grafik-Fenster unten. Und mit <code>class Auto extends Sprite</code> kannst du wie gewohnt <code>act()</code>, Tasten und Maus nutzen.</p>
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
        </div>
        <?php /* Grafik-Fenster als eigene Zeile direkt unter der IDE (#52):
               volle Breite unter Dateileiste, Editor und Konsole, unabhaengig
               davon, wie hoch die linke Spalte ist. */ ?>
        <div class="gfx-dock" id="gfx-dock" hidden>
            <div class="gfx-dock-bar">
                <button class="btn btn-ghost gfx-dock-btn" id="java-gfx-hide" type="button" title="Grafik-Fenster schließen">✕ Schließen</button>
                <button class="btn btn-ghost gfx-dock-btn" id="java-gfx-open" type="button" title="Grafik-Fenster anzeigen: blendet das Grafik-Fenster wieder ein. Es öffnet sich auch automatisch, sobald dein Programm etwas zeichnet.">🎨 Anzeigen</button>
                <span class="muted gfx-dock-hint">Größe im Code festlegen: <code>new World(640, 480)</code> (Standard 800 × 600). Bei jedem ▶ Ausführen startet das Fenster leer.</span>
            </div>
        </div>
    </div>
    <?php elseif ($project->type === 'code' && $project->language === 'asm'): ?>
    <!-- Assembler-IDE: gleiche Optik wie die Java-IDE, aber reduziert auf
         Editor, Ausgabe, Register-Anzeige und Befehlsübersicht. -->
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="ide-savebar" id="code-save-form" aria-hidden="true">
        <?= csrf_field() ?>
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
                <input type="text" name="name" placeholder="programm.asm" pattern="[A-Za-z][A-Za-z0-9_]{0,50}\.asm" required>
                <button class="btn btn-primary" type="submit" title="Neue Datei">＋</button>
            </form>
            <?php if (($presentationTask ?? null) !== null): ?>
            <div class="pres-side-actions">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/download') ?>" title="Diese Version als Musterlösung herunterladen">⬇️ Musterlösung</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/ende') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="Präsentation beenden und diese temporäre Kopie löschen">✕ beenden</button>
                </form>
            </div>
            <?php endif; ?>
        </aside>
        <div class="ide-work">
        <div class="ide-main">
            <div class="ide-editors">
            <?php foreach ($files as $i => $file): ?>
            <div class="file-pane" data-file-pane="<?= (int) $file->id ?>" data-file-name="<?= e($file->name) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                <div class="hlwrap ide-hlwrap" data-hl="asm" data-ac="asm">
                    <textarea name="files[<?= (int) $file->id ?>]" form="code-save-form" rows="26" class="mono code-editor ide-editor" spellcheck="false"><?= e($file->content) ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="console-errors-box" id="asm-errors-box">
                <div class="console-errors-head">🚨 Fehlerkonsole <span class="err-badge" id="asm-error-badge">0</span></div>
                <div class="java-errors" id="asm-errors">Noch nichts ausgeführt – hier erscheinen Fehler mit Datei, Zeile und Tipp.</div>
                <button class="btn btn-ghost" id="asm-clear" type="button" title="Leeren">🗑</button>
            </div>
        </div>
        <div class="ide-console">
            <div class="console-tabs" role="tablist">
                <button type="button" class="console-tab active" data-ctab="out" title="Ausgabe: Startknöpfe, Ausgabe deines Programms und Register">▶</button>
                <button type="button" class="console-tab" data-ctab="cmd" title="Befehle: alle Assembler-Befehle mit Erklärung">📖</button>
            </div>
            <div class="console-pane" data-cpane="out">
                <div class="console-run">
                    <button class="btn btn-primary run-icon" id="run-asm" type="button" title="Ausführen (Strg + Enter): dein Assembler-Programm läuft komplett in deinem Browser." aria-label="Ausführen">▶</button>
                    <button class="btn btn-ghost run-icon" id="asm-step" type="button" title="Einzelschritt: führt genau einen Befehl aus und markiert in den Zeilennummern, welcher Befehl als Nächster dran ist." aria-label="Einzelschritt">👣</button>
                    <button class="btn btn-ghost run-icon" id="asm-run-rest" type="button" title="Weiter: lässt dein Programm vom Pausenpunkt an bis zum Ende laufen." aria-label="Weiter">⏩</button>
                    <button class="btn btn-ghost run-icon" id="asm-stop" type="button" title="Stopp: hält dein Programm an." aria-label="Stopp">⏹</button>
                </div>
                <div id="asm-step-info" class="step-info"></div>
                <div class="console-block-title">Ausgabe</div>
                <pre id="asm-console" class="code ide-console-out">Noch nichts ausgeführt.</pre>
                <div class="console-block-title">Register</div>
                <p class="muted">Deine 16 Register mit ihren Werten, live bei jedem ▶ Ausführen und jedem 👣 Einzelschritt. <code>R0</code> bis <code>R15</code>, dahinter die Vergleichsflags und der Programmzähler.</p>
                <div id="asm-registers" class="asm-registers"></div>
                <div class="console-block-title">Eingabe für IN</div>
                <p class="muted">Zahlen für <code>IN</code> hineinschreiben, mehrere mit Komma getrennt (z. B. <code>3, 12</code>): <code>IN R1</code> nimmt jeweils die nächste Zahl.</p>
                <input type="text" id="asm-in" class="mono asm-in" autocomplete="off" spellcheck="false">
            </div>
            <div class="console-pane" data-cpane="cmd" hidden>
                <h4 class="diag-head">📖 Alle Befehle</h4>
                <div id="asm-commands" class="asm-commands">
                    <table>
                        <tr><td><pre class="code">LOAD R1, 5
LOAD R1, R2</pre></td><td>Lädt die Zahl <code>5</code> bzw. den Inhalt von <code>R2</code> ins Register <code>R1</code>. (Schreibweise <code>MOV</code> geht auch.)</td></tr>
                        <tr><td><pre class="code">ADD R1, R2</pre></td><td><code>R1 = R1 + R2</code>. Auch mit Zahl: <code>ADD R1, 5</code>.</td></tr>
                        <tr><td><pre class="code">SUB R1, R2</pre></td><td><code>R1 = R1 - R2</code>.</td></tr>
                        <tr><td><pre class="code">MUL R1, R2</pre></td><td><code>R1 = R1 * R2</code>.</td></tr>
                        <tr><td><pre class="code">DIV R1, R2</pre></td><td><code>R1 = R1 / R2</code> (ganzzahlig, Rest wird verworfen). Division durch 0 ist ein Fehler.</td></tr>
                        <tr><td><pre class="code">MOD R1, R2</pre></td><td><code>R1 = R1 % R2</code> (Rest der Division).</td></tr>
                        <tr><td><pre class="code">INC R1</pre></td><td><code>R1 = R1 + 1</code>.</td></tr>
                        <tr><td><pre class="code">DEC R1</pre></td><td><code>R1 = R1 - 1</code>.</td></tr>
                        <tr><td><pre class="code">CMP R1, R2</pre></td><td>Vergleicht beide Werte und setzt die Flags: <strong>gleich</strong>, <strong>kleiner</strong>, <strong>größer</strong>. Alle Sprünge hängen an diesen Flags.</td></tr>
                        <tr><td><pre class="code">JMP marke</pre></td><td>Springt immer zur Marke <code>marke:</code>.</td></tr>
                        <tr><td><pre class="code">JZ marke
JNZ marke</pre></td><td>Springt, wenn die Flags <strong>gleich</strong> sind (JZ) bzw. nicht (JNZ).</td></tr>
                        <tr><td><pre class="code">JGT marke
JLT marke</pre></td><td>Springt, wenn <strong>größer</strong> (JGT) bzw. <strong>kleiner</strong> (JLT).</td></tr>
                        <tr><td><pre class="code">marke:</pre></td><td>Setzt eine Marke (Sprungziel), z. B. <code>schleife:</code>.</td></tr>
                        <tr><td><pre class="code">OUT R1</pre></td><td>Gibt den Wert von <code>R1</code> in der Ausgabe aus.</td></tr>
                        <tr><td><pre class="code">OUTS "Hallo!"</pre></td><td>Gibt einen Text aus (Anführungszeichen nötig).</td></tr>
                        <tr><td><pre class="code">IN R1</pre></td><td>Liest die nächste Zahl aus dem Eingabefeld ins Register.</td></tr>
                        <tr><td><pre class="code">HALT</pre></td><td>Beendet das Programm. Fehlt HALT, meckert die Fehlerkonsole.</td></tr>
                        <tr><td><pre class="code">; ein Kommentar</pre></td><td>Alles hinter <code>;</code> bis zum Zeilenende wird ignoriert.</td></tr>
                    </table>
                    <h4 class="diag-head">🎓 Abitur-Befehle (Akkumulator-Maschine)</h4>
                    <p class="muted">Für Abituraufgaben gibt es eine Akkumulator-Maschine: der <strong>Akku</strong> (ganz oben in der Register-Anzeige) ist ein besonderes Register, die Speicherzellen <code>R0</code> bis <code>R15</code> sind der Hauptspeicher. Klein- und Großschreibung sind egal.</p>
                    <table>
                        <tr><td><pre class="code">LOAD #5
LOAD 3
LOAD *3
LOAD (3)</pre></td><td>Lädt in den Akku: die Konstante <code>5</code> (<code>#</code>), den Inhalt der Speicherzelle <code>R3</code>, oder indirekt: den Inhalt der Zelle, deren Nummer in <code>R3</code> steht (<code>*3</code> oder <code>(3)</code>).</td></tr>
                        <tr><td><pre class="code">STORE 3
STORE *3
STORE (3)</pre></td><td>Speichert den Akku in die Zelle <code>R3</code> bzw. indirekt in die Zelle, deren Nummer in <code>R3</code> steht.</td></tr>
                        <tr><td><pre class="code">ADD 3
ADD #3
ADDI 3</pre></td><td>Akku = Akku + Inhalt von <code>R3</code> bzw. + Konstante. Genauso gibt es <code>SUB</code>, <code>SUBI</code>, <code>MUL</code>, <code>DIV</code> und <code>LOADI n</code> (Konstante in den Akku).</td></tr>
                        <tr><td><pre class="code">SUB #5</pre></td><td>Achtung, Abitur-Konvention (Hessen): wird das Ergebnis negativ, wird es auf <code>0</code> gesetzt.</td></tr>
                        <tr><td><pre class="code">DIV #0</pre></td><td>Division durch <code>0</code> beendet das Programm mit einem Fehler.</td></tr>
                        <tr><td><pre class="code">GOTO marke
JZERO marke
JNZERO marke</pre></td><td>Springt immer, bzw. wenn der Akku <code>0</code> ist (JZERO) bzw. nicht <code>0</code> ist (JNZERO).</td></tr>
                        <tr><td><pre class="code">JEQ marke
JNE marke</pre></td><td>Bayerische Abiturschreibweise: springt, wenn das Flag <strong>gleich</strong> gesetzt ist (JEQ) bzw. nicht (JNE).</td></tr>
                        <tr><td><pre class="code">OUT</pre></td><td>Ohne Register: gibt den Akku aus.</td></tr>
                        <tr><td><pre class="code">END
HOLD</pre></td><td>Beendet das Programm (bedeuten dasselbe wie <code>HALT</code>).</td></tr>
                    </table>
                    <p class="muted">Register heißen <code>R0</code> bis <code>R15</code>. Alle Werte sind ganze Zahlen. Dein Programm läuft komplett in deinem Browser, die Dateien werden der Reihe nach zu einem Programm zusammengesetzt, Marken gelten über alle Dateien hinweg.</p>
                </div>
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
<script src="<?= asset_url('assets/vendor/highlight.min.js') ?>"></script>

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
<script src="<?= asset_url('assets/js/java-vm.js') ?>"></script>
<script>window.IC_IMAGES = <?= json_encode((object) ($imageUrls ?? []), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php elseif ($project->type === 'code' && $project->language === 'asm'): ?>
<script src="<?= asset_url('assets/js/asm-vm.js') ?>"></script>
<?php elseif ($project->type === 'html'): ?>
<section class="panel">
    <div class="notice">
        <strong>💡 Tipp</strong>
        <p>Für HTML-Seiten gibt es die <a href="<?= url('werkstatt/html') ?>">HTML-Werkstatt</a> mit Sofortvorschau beim Tippen.</p>
    </div>
</section>
<?php endif; ?>

<?php if ($project->type === 'code' && $project->language === 'java' && $dbPanel): ?>
<section class="panel" id="db-panel">
    <input type="hidden" id="java-db-seed" value="<?= e($dbSql ?? '') ?>">
    <?php if (!empty($dbReadOnly)): ?>
    <input type="hidden" id="java-db-ro" value="1">
    <?php endif; ?>
    <div class="panel-head">
        <h3>💾 Mini-Datenbank: SQL direkt in Java</h3>
        <?php if (!empty($dbReadOnly)): ?>
        <span class="pill" title="Deine Lehrkraft hat diese Datenbank nur mit Leserechten an die Aufgabe angehängt: Alle arbeiten mit denselben Daten, SELECT und Database.query sind erlaubt, schreibende Befehle sind gesperrt.">🔒 Nur Leserechte</span>
        <?php endif; ?>
    </div>
    <p>Die eingebaute Klasse <code>Database</code> speichert Tabellen im Speicher deines Browsers – ganz ohne Installation:</p>
    <pre class="code">Database.execute("CREATE TABLE schueler (name TEXT, punkte INT)");
Database.execute("INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)");
System.out.println(Database.query("SELECT * FROM schueler WHERE punkte > 10"));

int anzahl = Database.count("SELECT * FROM schueler");   // Zeilen einer Abfrage
String beste = Database.value("SELECT name FROM schueler ORDER BY punkte DESC");
System.out.println(Database.tables());                     // alle Tabellen</pre>
    <p class="muted">Verfügbar: <code>execute</code> für CREATE TABLE, INSERT, UPDATE, DELETE und DROP TABLE; <code>query</code> liefert das SELECT-Ergebnis als formatierte Tabelle, <code>count</code> die Zeilenanzahl, <code>value</code> den ersten Wert, <code>tables</code> die vorhandenen Tabellen. Text in SQL gehört in einfache Anführungszeichen ('Anna'), Zahlen ohne (12). Die Datenbank bleibt mit deinem Projekt gespeichert: Bei jedem ▶ Ausführen startet sie mit den Tabellen und Daten aus deinem letzten Stand (und der Beispieldatenbank deiner Aufgabe, falls die Lehrkraft eine .sql-Datei als Material angehängt hat).</p>

    <?php if (!empty($dbTemplates)): ?>
    <form method="post" enctype="multipart/form-data" action="<?= url('werkstatt/code/' . $project->id . '/datenbank/datei') ?>" class="form-inline db-load-form">
        <?= csrf_field() ?>
        <label for="db-sql-file"><strong>Eigene SQL-Datei:</strong></label>
        <input type="file" name="sql_file" id="db-sql-file" accept=".sql" required>
        <button class="btn btn-ghost" type="submit" title="Lädt eine eigene .sql-Datei mit CREATE TABLE- und INSERT-Befehlen als Mini-Datenbank deines Projekts.">📤 SQL-Datei hochladen</button>
    </form>
    <form method="post" action="<?= url('werkstatt/code/' . $project->id . '/datenbank') ?>" class="form-inline db-load-form">
        <?= csrf_field() ?>
        <label for="db-template-select"><strong>Beispieldatenbank laden:</strong></label>
        <select name="template_id" id="db-template-select">
            <option value="0">Datenbank leeren</option>
            <?php foreach ($dbTemplates as $dbTemplate): ?>
            <option value="<?= (int) $dbTemplate->id ?>"><?= e($dbTemplate->title) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-ghost" type="submit" title="Ersetzt die Mini-Datenbank durch die gewählte Beispieldatenbank mit ihren Tabellen und Daten.">📦 Laden</button>
    </form>
    <p class="muted">Ersetzt deine aktuelle Mini-Datenbank durch die Tabellen und Daten der gewählten Vorlage (z. B. Schule oder Schulbibliothek). Eigene Tabellen und Daten gehen dabei verloren.</p>
    <?php endif; ?>

    <div class="db-two-col">
        <div class="db-block">
            <h4 class="diag-head">🗄 Vorhandene Tabellen</h4>
            <p class="muted">Alle Tabellen und Spalten deiner Datenbank, live nach jedem ▶ Ausführen: Hier siehst du, worauf dein Programm zugreifen kann (<code>Database.execute(...)</code> und <code>Database.query(...)</code>).</p>
            <div id="java-dbschema-static" class="umldiag">
                <?php if (empty($seedTables)): ?>
                <p class="muted">Noch keine Tabellen: Lade oben eine Beispieldatenbank oder lade eine eigene SQL-Datei hoch - die Tabellen erscheinen dann sofort hier (und bleiben mit jedem ▶ Ausführen erhalten).</p>
                <?php else: ?>
                <?php foreach ($seedTables as $seedTable): ?>
                <table class="db-schema-table">
                    <thead>
                        <tr><th colspan="2"><?= e($seedTable['name']) ?></th></tr>
                        <tr><th>Spalte</th><th>Typ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seedTable['cols'] as $seedCol): ?>
                        <tr><td><?= e($seedCol['name']) ?></td><td><?= e($seedCol['type']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="java-dbschema" class="umldiag"></div>
        </div>

        <div class="db-block">
            <details class="db-console-details">
            <summary class="diag-head">🧪 DB-Konsole: eigene Abfragen und Tabellen ausprobieren (aufklappen)</summary>
                        <p class="muted"><?php if (empty($dbReadOnly)): ?>Schicke Abfragen direkt an die Datenbank, ohne dein Programm zu ändern: <code>SELECT</code> zeigt das Ergebnis an, <code>INSERT</code>, <code>UPDATE</code>, <code>DELETE</code>, <code>CREATE TABLE</code> und <code>DROP TABLE</code> ändern die Datenbank dauerhaft (sie wird automatisch mit deinem Projekt gespeichert). Damit legst du hier auch neue Daten an und prüfst sofort, was dein Programm daraus liest.<?php else: ?>Schicke Abfragen an die angehängte Datenbank: <code>SELECT</code> zeigt das Ergebnis an. Schreibende Befehle sind gesperrt, weil deine Lehrkraft nur Leserechte vergeben hat - alle arbeiten mit denselben Daten.<?php endif; ?></p>
            <div class="hlwrap" data-hl="sql" data-ac="sqldb"><textarea id="java-db-in" class="mono db-console-in" rows="3" autocomplete="off" spellcheck="false"></textarea></div>
            <div class="repl-actions">
                <button class="btn btn-primary" id="java-db-run" type="button" title="Abfrage ausführen (Strg + Enter): schickt den SQL-Befehl an die Datenbank und zeigt das Ergebnis.">▶ Abfrage ausführen</button>
            </div>
            <pre id="java-db-out" class="code ide-console-out db-console-out"></pre>
            </details>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (($presentationTask ?? null) !== null): ?>
<script>
/* Präsentationsmodus: ToDos lokal abhakbar machen (bleibt im Browser erhalten,
 * auch nach einem Reload, ohne die Aufgaben-ToDos der Schüler zu verändern). */
(function () {
  'use strict';
  var box = document.getElementById('pres-todos');
  if (!box) { return; }
  var key = 'pres-todos-' + box.getAttribute('data-task');
  function readState() {
    try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; }
  }
  var boxes = box.querySelectorAll('input[data-pres-todo]');
  var i;
  var state = readState();
  for (i = 0; i < boxes.length; i++) {
    var tid = boxes[i].getAttribute('data-pres-todo');
    boxes[i].checked = !!state[tid];
    boxes[i].addEventListener('change', function () {
      var s = readState();
      s[this.getAttribute('data-pres-todo')] = this.checked;
      try { localStorage.setItem(key, JSON.stringify(s)); } catch (e) {}
    });
  }
})();
</script>
<?php endif; ?>
