<?php /* InfoBook: Seite bearbeiten/ansehen (#62). Bloecke: Text, Bild, Audio, Video, Einbettung, Zeichnung (Apple Pencil). */
?>
<section class="hero ibook-hero-compact">
    <div class="btn-row ibook-topnav">
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $section['id']) ?>">← <?= e($book->title) ?></a>
    </div>
    <h1>📄 <span id="ibook-page-title"><?= e((string) $page['title']) ?></span></h1>
    <p class="muted"><?= e($section['title']) ?> · Letzte Änderung: <?= e((string) ($page['updated_at'] ?? 'gerade eben')) ?></p>
</section>

<?php if (!$canWrite): ?>
<section class="panel">
    <h3>Seite lesen</h3>
    <p class="muted">Diese Seite gehört zum Lernbereich: Lesen ist frei, Änderungen macht die Lehrkraft.</p>
</section>
<?php foreach ($blocks as $block): ?>
<?= (string) $block['html'] ?>
<?php endforeach; ?>
<?php if ($blocks === []): ?>
<div class="notice"><strong>Noch keine Inhalte</strong></div>
<?php endif; ?>
<?php else: ?>

<?php if (Auth::isTeacher()): ?>
<section class="panel ibook-distribute">
    <h3>📤 An Klasse verteilen</h3>
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/verteilen') ?>" class="form ibook-distribute-form">
        <?= csrf_field() ?>
        <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
        <div class="form-row form">
            <label class="field"><span>Klasse</span>
                <select name="class_id" required>
                    <?php $classes = \Infocraft\Models\SchoolClass::forTeacher($user->id, $user->role === 'admin'); ?>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Zielbereich</span>
                <select name="bereich">
                    <option value="lernbereich">📖 Lernbereich (Schülerinnen und Schüler lesen)</option>
                    <option value="gemeinsam">🤝 Zusammenarbeit (alle schreiben mit)</option>
                </select>
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Kopiert diese Seite mit allen Medien ins Klassen-InfoBook">📤 Seite verteilen</button></div>
    </form>
</section>
<?php endif; ?>

<form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" id="ibook-saveform" class="form">
    <?= csrf_field() ?>
    <section class="panel ibook-editor-panel">
        <h3>Titel</h3>
        <label class="field"><input type="text" name="title" id="ibook-title" maxlength="160" value="<?= e((string) $page['title']) ?>" placeholder="Titel der Seite"></label>

        <h3>Bausteine</h3>
        <div id="ibook-editor" data-book-id="<?= (int) $book->id ?>" data-page-id="<?= (int) $page['id'] ?>" data-blocks="<?= e(json_encode($blocks, JSON_UNESCAPED_UNICODE)) ?>"></div>

        <div class="btn-row ibook-addrow" id="ibook-addrow">
            <button type="button" class="btn btn-ghost" data-ib-add="text" title="Textbaustein: Markdown und Folien-Bausteine wie :::merksatz">✍️ Text</button>
            <button type="button" class="btn btn-ghost" data-ib-add="image" title="Bild hochladen">🖼 Bild</button>
            <button type="button" class="btn btn-ghost" data-ib-add="audio" title="Audiodatei hochladen, direkt abspielbar">🔊 Audio</button>
            <button type="button" class="btn btn-ghost" data-ib-add="video" title="Video hochladen, direkt abspielbar">🎞 Video</button>
            <button type="button" class="btn btn-ghost" data-ib-add="embed" title="YouTube oder LearningApps einbetten">🔗 Einbettung</button>
            <button type="button" class="btn btn-ghost" data-ib-add="zeichnen" title="Freihand schreiben und zeichnen, optimal für Apple Pencil">✏️ Zeichnen</button>
        </div>

        <input type="hidden" name="blocks" id="ibook-blocks-json" value="[]">
        <div class="btn-row">
            <button class="btn btn-primary" type="submit" title="Seite speichern">💾 Seite speichern</button>
        </div>
    </section>
</form>
<?php endif; ?>
