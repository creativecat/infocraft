<section class="panel">
    <div class="panel-head">
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>">← Zurück</a>
    </div>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <label class="field"><span>Inhalt <?= $project->type === 'html' ? '(HTML)' : '(' . e($project->language ?? 'Code') . ')' ?></span>
            <textarea name="content" id="code-editor" rows="18" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>
</section>

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
    <div class="notice">
        <strong>☕ Java</strong>
        <p>Java-Programme kannst du hier bearbeiten, speichern und von Lehrkräften austauschen lassen. Das direkte Ausführen von Java in der Werkstatt folgt noch.</p>
    </div>
</section>
<?php elseif ($project->type === 'html'): ?>
<section class="panel">
    <div class="notice">
        <strong>💡 Tipp</strong>
        <p>Für HTML-Seiten gibt es die <a href="<?= url('werkstatt/html') ?>">HTML-Werkstatt</a> mit Sofortvorschau beim Tippen.</p>
    </div>
</section>
<?php endif; ?>

