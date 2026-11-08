<section class="panel">
    <div class="panel-head">
        <h3>📖 <?= e($page->title) ?> <span class="pill">ab Jgst. <?= e($page->level) ?></span></h3>
        <a class="btn btn-ghost" href="<?= url('wiki') ?>" title="Alle Seiten">←</a>
    </div>
    <div class="wiki-content"><?= $html /* bereits escapet und aus Markup erzeugt */ ?></div>
    <?php if ($mayEdit): ?>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= url('wiki/' . (int) $page->id . '/bearbeiten') ?>" title="Bearbeiten">✏️</a>
        <form method="post" action="<?= url('wiki/' . (int) $page->id . '/loeschen') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit" title="Löschen">🗑</button>
        </form>
    </div>
    <?php endif; ?>
</section>

