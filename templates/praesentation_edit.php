<section class="panel">
    <div class="panel-head">
        <h3>🎬 Präsentation bearbeiten</h3>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">▶ Präsentieren</a>
            <a class="btn btn-ghost" href="<?= url('praesentationen') ?>" title="Alle Präsentationen">←</a>
        </div>
    </div>
    <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" value="<?= e($presentation->title) ?>"></label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"<?= $i === $presentation->level ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
        </div>
    </form>
</section>

<?php if ($slides === []): ?>
<div class="notice">
    <strong>Noch keine Folien</strong>
    <p>Füge unten die erste Folie hinzu: eine Text-Folie für Überschriften und Merksätze oder eine Code-Folie für Live-Demos.</p>
</div>
<?php endif; ?>

<?php foreach ($slides as $pos => $slide): ?>
<section class="panel">
    <div class="panel-head">
        <h3>Folie <?= $pos + 1 ?>: <?= $slide['type'] === 'code' ? '💻 Code' : '📄 Text' ?><?= $slide['title'] !== '' ? ' · ' . e($slide['title']) : '' ?></h3>
        <div class="btn-row">
            <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie/' . $pos . '/hoch') ?>" class="form-inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"<?= $pos === 0 ? ' disabled' : '' ?>>↑</button></form>
            <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie/' . $pos . '/runter') ?>" class="form-inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"<?= $pos === count($slides) - 1 ? ' disabled' : '' ?>>↓</button></form>
            <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie/' . $pos . '/loeschen') ?>" class="form-inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit" title="Löschen">🗑</button></form>
        </div>
    </div>
    <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie/' . $pos) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Folien-Titel</span><input type="text" name="title" maxlength="200" value="<?= e($slide['title']) ?>"></label>
        <?php if ($slide['type'] === 'code'): ?>
        <div class="form-row form">
            <label class="field"><span>Sprache</span>
                <select name="lang">
                    <?php foreach (['java', 'python', 'sql', 'html'] as $lang): ?>
                    <option value="<?= $lang ?>"<?= $slide['lang'] === $lang ? ' selected' : '' ?>><?= $lang ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="field"><span>Code (erscheint mit Syntax-Highlighting, in der Präsentation veränderbar)</span>
            <span class="hlwrap" data-hl="<?= e($slide['lang']) ?>"><textarea name="code" rows="10" class="mono" spellcheck="false"><?= e($slide['code']) ?></textarea></span>
        </label>
        <?php else: ?>
        <label class="field"><span>Text (Markdown-lite: # Überschrift, **fett**, *kursiv*, `Code`, - Liste, ``` Block)</span>
            <textarea name="body" rows="8" class="mono" spellcheck="true"><?= e($slide['body']) ?></textarea>
        </label>
        <?php endif; ?>
        <label class="field"><span>🎙 Sprechernotiz (nur im Moderatorenbildschirm für Lehrkräfte sichtbar, Schülerinnen und Schüler sehen sie nie)</span>
            <textarea name="notes" rows="3" class="mono" spellcheck="true" placeholder="Kernbotschaft, Impulsfrage, Zeitangabe - z. B.: &quot;Frage zuerst: Wer hat schon mal ...? (3 Min)&quot;"><?= e($slide['notes'] ?? '') ?></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Folie speichern">💾</button></div>
    </form>
</section>
<?php endforeach; ?>

<section class="panel">
    <h3>Folie anfügen</h3>
    <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie') ?>" class="form-inline form">
        <?= csrf_field() ?>
        <label class="field"><span>Typ</span>
            <select name="type">
                <option value="text">📄 Text-Folie (Überschrift, Merksatz, Liste)</option>
                <option value="code">💻 Code-Folie (Java, Python oder SQL mit Highlighting)</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit" title="Folie anfügen">➕</button>
    </form>
</section>
