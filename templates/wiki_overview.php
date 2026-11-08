<section class="hero">
    <div class="hero-emoji">📚</div>
    <h1>Wiki</h1>
    <p>Nachschlagen und vertiefen: Fachbegriffe und Konzepte aus dem Informatik-Unterricht.</p>
</section>

<?php if ($isTeacher): ?>
<section class="panel">
    <h3>Neue Wiki-Seite anlegen</h3>
    <form method="post" action="<?= url('wiki/anlegen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Was ist ein Algorithmus?"></label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"<?= $i === 6 ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="field"><span>Inhalt (Markdown-lite: # Überschrift, **fett**, *kursiv*, `Code`, - Liste, ``` Block)</span>
            <textarea name="content" rows="10" class="mono" spellcheck="true" placeholder="# Was ist ein Algorithmus?&#10;&#10;Eine **eindeutige** Handlungsvorschrift ..."></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Seite anlegen">📄</button></div>
    </form>
</section>

<?php if ($pages === []): ?>
<div class="notice">
    <strong>Noch keine Wiki-Seiten</strong>
    <p>Lege oben die erste Seite an, z. B. zu einem Fachbegriff wie Algorithmus, Variable oder Schleife.</p>
</div>
<?php else: ?>
<?php foreach ($pages as $page): ?>
<section class="panel">
    <div class="panel-head">
        <h3>📖 <?= e($page->title) ?></h3>
        <span class="pill">ab Jgst. <?= e($page->level) ?></span>
    </div>
    <p class="muted"><?= e(mb_substr(preg_replace('/[#*`]/', '', trim($page->content)), 0, 140)) ?><?= mb_strlen($page->content) > 140 ? ' …' : '' ?></p>
    <div class="btn-row">
        <a class="btn btn-primary" href="<?= url('wiki/' . (int) $page->id) ?>">Lesen</a>
        <?php if ($user->role === 'admin' || $page->teacherId === $user->id): ?>
        <a class="btn btn-ghost" href="<?= url('wiki/' . (int) $page->id . '/bearbeiten') ?>" title="Bearbeiten">✏️</a>
        <form method="post" action="<?= url('wiki/' . (int) $page->id . '/loeschen') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit" title="Löschen">🗑</button>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>
<?php if ($pages === []): ?>
<div class="notice">
    <strong>Noch keine Wiki-Seiten</strong>
    <p>Deine Lehrkraft hat noch keine Seiten angelegt.</p>
</div>
<?php else: ?>
<?php foreach ($pages as $page): ?>
<section class="panel">
    <div class="panel-head">
        <h3>📖 <?= e($page->title) ?></h3>
    </div>
    <p class="muted"><?= e(mb_substr(preg_replace('/[#*`]/', '', trim($page->content)), 0, 140)) ?><?= mb_strlen($page->content) > 140 ? ' …' : '' ?></p>
    <div class="btn-row"><a class="btn btn-primary" href="<?= url('wiki/' . (int) $page->id) ?>">Lesen</a></div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

