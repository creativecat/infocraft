<?php /* InfoBook: Seite bearbeiten/ansehen (#62). Bloecke: Text, Bild, Audio, Video, Einbettung, Zeichnung (Apple Pencil). */
?>
<?php /* #67: Zurueck ganz links, rechts Vorschau/Lesemodus und kleines aufklappbares Verteilen, Autospeichern statt Speichern-Button. */ ?>
<?php $preview = (bool) ($preview ?? false); ?>
<section class="hero ibook-hero-compact">
    <div class="btn-row ibook-topnav">
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $section['id']) ?>">← <?= e($book->title) ?></a>
        <div class="ibook-topnav-right">
            <?php if ($canWrite): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id'] . '?lesen=1') ?>" target="_blank" rel="noopener" title="Seite im Lesemodus in einem neuen Tab öffnen">👀 Vorschau (Lesemodus)</a>
            <?php endif; ?>
            <?php if ($preview): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" title="Zurück zur Bearbeitung">✏️ Bearbeiten</a>
            <?php endif; ?>
            <?php if (\Infocraft\Auth::isTeacher() && $canWrite): ?>
            <details class="ibook-distribute-details">
                <summary class="btn btn-ghost">📤 An Klasse verteilen</summary>
                <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/verteilen') ?>" class="form ibook-distribute-panel">
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
            </details>
            <?php endif; ?>
        </div>
    </div>
    <h1>📄 <span id="ibook-page-title"><?= e((string) $page['title']) ?></span></h1>
    <p class="muted" id="ibook-page-meta"><?= e($section['title']) ?> · Letzte Änderung: <?= e((string) ($page['updated_at'] ?? 'gerade eben')) ?></p>
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

<?php else: ?>

<form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" id="ibook-saveform" class="form">
    <?= csrf_field() ?>
    <section class="panel ibook-editor-panel">
        <h3>Titel</h3>
        <label class="field"><input type="text" name="title" id="ibook-title" maxlength="160" value="<?= e((string) $page['title']) ?>" placeholder="Titel der Seite"></label>

        <h3>Freie Fläche</h3>
        <div id="ibook-editor" data-book-id="<?= (int) $book->id ?>" data-page-id="<?= (int) $page['id'] ?>" data-blocks="<?= e(json_encode($blocks, JSON_UNESCAPED_UNICODE)) ?>"></div>
        <p class="muted ibook-hint">Alles auf einer Fläche: Bilder per Drag &amp; Drop ablegen, mit dem Stift überall zeichnen (auch über Bildern), Textfelder frei platzieren. Oben in der Symbolleiste wechselst du zwischen Auswahl, Stift und Radierer und fügst Text, Bild, Audio, Video und Einbettungen hinzu. Änderungen speichern sich automatisch, ein Speichern-Button ist nicht nötig.</p>

        <input type="hidden" name="blocks" id="ibook-blocks-json" value="[]">
    </section>
</form>
<?php endif; ?>
