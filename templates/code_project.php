<section class="panel">
    <div class="panel-head">
        <h3><?= $project->type === 'html' ? '🌐' : '💻' ?> <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>">← Zurück</a>
    </div>
    <form method="post" action="<?= url('werkstatt/code/' . (int) $project->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <label class="field"><span>Inhalt <?= $project->type === 'html' ? '(HTML)' : '(' . e($project->language ?? 'Code') . ')' ?></span>
            <textarea name="content" rows="18" class="mono code-editor"><?= e($project->content) ?></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>
    <div class="notice">
        <strong>Baustelle 🚧</strong>
        <p>Projekte lassen sich hier anlegen, bearbeiten, in Ordnern sortieren und von Lehrkräften austauschen. Das direkte Ausführen von Java und Python in der Werkstatt folgt in Phase 4.</p>
    </div>
</section>

