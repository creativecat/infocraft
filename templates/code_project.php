<section class="panel">
    <div class="panel-head">
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>">← Zurück</a>
    </div>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <?php if ($project->type === 'code' && $project->language === 'java'): ?>
            <div class="field">
                <span>Dateien (eine Klasse pro Datei)</span>
                <div class="file-tabs">
                    <?php foreach ($files as $i => $file): ?>
                        <button type="button" class="file-tab<?= $i === 0 ? ' active' : '' ?>" data-file-tab="<?= (int) $file->id ?>"><?= e($file->name) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($files as $i => $file): ?>
                    <div class="file-pane" data-file-pane="<?= (int) $file->id ?>" data-file-name="<?= e($file->name) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                        <div class="hlwrap" data-hl="java">
                            <textarea name="files[<?= (int) $file->id ?>]" rows="18" class="mono code-editor" spellcheck="false"><?= e($file->content) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <label class="field"><span>Inhalt <?= $project->type === 'html' ? '(HTML)' : '(' . e($project->language ?? 'Code') . ')' ?></span>
                <div class="hlwrap" data-hl="<?= e($project->type === 'html' ? 'xml' : ($project->language ?? 'plaintext')) ?>">
                    <textarea name="content" id="code-editor" rows="18" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
                </div>
            </label>
        <?php endif; ?>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>

    <?php if ($project->type === 'code' && $project->language === 'java'): ?>
    <div class="file-manage">
        <?php foreach ($files as $file): ?>
            <?php if ($file->id > 0): ?>
            <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/datei/' . (int) $file->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" title="Datei löschen">🗑 <?= e($file->name) ?></button>
            </form>
            <?php endif; ?>
        <?php endforeach; ?>
        <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id . '/datei') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="text" name="name" placeholder="Auto.java" pattern="[A-Za-z][A-Za-z0-9_]*\.java" required>
            <button class="btn btn-primary" type="submit">＋ Neue Datei</button>
        </form>
    </div>
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
<section class="panel">
    <div class="panel-head">
        <h3>☕ Java ausführen</h3>
        <button class="btn btn-primary" id="run-java" type="button">▶ Ausführen</button>
    </div>
    <p class="muted">Dein Programm läuft vollständig in deinem Browser: InfoCraft übersetzt den Java-Quelltext selbst und zeigt die Ausgaben hier. Es wird kein Code auf dem Server ausgeführt. Mit Strg + Enter startest du ebenfalls.</p>
    <div class="notice">
        <strong>💾 Mini-Datenbank: SQL direkt in Java</strong>
        <p>Die eingebaute Klasse <code>Datenbank</code> speichert Tabellen im Speicher deines Browsers – ganz ohne Installation oder Vorkenntnisse:</p>
        <pre class="code">Datenbank.ausfuehren("CREATE TABLE schueler (name TEXT, punkte INT)");
Datenbank.ausfuehren("INSERT INTO schueler VALUES ('Anna', 12), ('Ben', 8)");
System.out.println(Datenbank.abfrage("SELECT * FROM schueler WHERE punkte > 10"));

int anzahl = Datenbank.anzahl("SELECT * FROM schueler");   // Zeilen einer Abfrage
String beste = Datenbank.wert("SELECT name FROM schueler ORDER BY punkte DESC");
System.out.println(Datenbank.tabellen());                  // alle Tabellen</pre>
        <p class="muted">Verfügbar: <code>ausfuehren</code> für CREATE TABLE, INSERT, UPDATE, DELETE und DROP TABLE; <code>abfrage</code> liefert das SELECT-Ergebnis als formatierte Tabelle, <code>anzahl</code> die Zeilenanzahl, <code>wert</code> den ersten Wert, <code>tabellen</code> die vorhandenen Tabellen. Text in SQL gehört in einfache Anführungszeichen ('Anna'), Zahlen ohne (12). Die Datenbank startet bei jedem Ausführen neu und leer.</p>
    </div>
    <pre id="java-console" class="code">Noch nichts ausgeführt.</pre>
    <div class="java-errors" id="java-errors">
        <div class="panel-head">
            <h4>🚨 Fehlerkonsole <span class="err-badge" id="java-error-badge">0</span></h4>
            <button class="btn btn-ghost" id="java-clear" type="button">Leeren</button>
        </div>
    </div>
</section>
<script src="<?= url('assets/js/java-vm.js') ?>"></script>
<?php elseif ($project->type === 'html'): ?>
<section class="panel">
    <div class="notice">
        <strong>💡 Tipp</strong>
        <p>Für HTML-Seiten gibt es die <a href="<?= url('werkstatt/html') ?>">HTML-Werkstatt</a> mit Sofortvorschau beim Tippen.</p>
    </div>
</section>
<?php endif; ?>


