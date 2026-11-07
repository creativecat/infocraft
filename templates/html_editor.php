<section class="panel">
    <div class="panel-head">
        <h3>🌐 <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/html') ?>">← Zurück</a>
    </div>
    <form method="post" action="<?= url('werkstatt/html/' . (int) $project->id) ?>" class="form" id="html-form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>
        <div class="editor-grid">
            <label class="field"><span>HTML (die Vorschau rechts aktualisiert sich beim Tippen)</span>
                <textarea name="content" id="html-editor" rows="24" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
            </label>
            <label class="field"><span>Vorschau</span>
                <iframe id="html-preview" class="preview-frame" sandbox="" title="Vorschau deiner Seite"></iframe>
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>
</section>

