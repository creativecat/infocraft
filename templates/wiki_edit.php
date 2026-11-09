<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('wiki/' . (int) $page->id) ?>" title="Abbrechen">←</a>
        <h3>📖 Seite bearbeiten</h3>
    </div>
    <form method="post" action="<?= url('wiki/' . (int) $page->id) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" value="<?= e($page->title) ?>"></label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"<?= $i === $page->level ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="field"><span>Inhalt (Markdown-lite: # Überschrift, **fett**, *kursiv*, `Code`, - Liste, ``` Block)</span>
            <div class="hlwrap" data-hl="markdown" data-ac="md"><textarea name="content" rows="14" class="mono" spellcheck="true"><?= e($page->content) ?></textarea></div>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
    </form>
</section>

