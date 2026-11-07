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
        <label class="field ide-title"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <button class="btn btn-primary" type="submit">💾 Speichern</button>
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
            <div class="console-tabs" role="tablist">
                <button type="button" class="console-tab active" data-ctab="out">▶ Ausgabe</button>
                <button type="button" class="console-tab" data-ctab="repl">🧪 Objektkonsole</button>
                <button type="button" class="console-tab" data-ctab="objects">🧩 Objekte <span class="err-badge" id="java-obj-badge">0</span></button>
                <button type="button" class="console-tab" data-ctab="errors">🚨 Fehlerkonsole <span class="err-badge" id="java-error-badge">0</span></button>
            </div>
            <div class="console-pane" data-cpane="out">
                <div class="console-run">
                    <button class="btn btn-primary" id="run-java" type="button">▶ Ausführen</button>
                    <span class="muted">Strg + Enter funktioniert auch. Dein Programm läuft komplett in deinem Browser.</span>
                </div>
                <pre id="java-console" class="code ide-console-out">Noch nichts ausgeführt.</pre>
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
            <div class="console-pane" data-cpane="objects" hidden>
                <p class="muted">Jedes <code>new</code> landet hier: Alle Objekte deines Programms mit ihren Attributwerten – dein Objektdiagramm neben dem Klassendiagramm.</p>
                <div id="java-objects" class="objdiag">Noch keine Objekte.</div>
            </div>
            <div class="console-pane" data-cpane="errors" hidden>
                <div class="java-errors" id="java-errors"></div>
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

<?php if ($project->type === 'code' && ($project->language === 'java' || $project->language === 'python')): ?>
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
