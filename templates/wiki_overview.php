<section class="hero hero-compact">
    <div class="hero-emoji">📚</div>
    <div class="hero-text">
        <h1>Wiki</h1>
        <p>Nachschlagen und vertiefen: Fachbegriffe und Konzepte aus dem Informatik-Unterricht.</p>
    </div>
</section>

<?php if ($isTeacher): ?>
<section class="panel panel-compact">
    <h3>➕ Neue Wiki-Seite anlegen</h3>
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
<div class="task-grid">
    <?php foreach ($pages as $page): ?>
    <?php $isOwner = $user !== null && ($user->role === 'admin' || $page->teacherId === $user->id); ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <a class="task-tile-main" href="<?= url('wiki/' . (int) $page->id) ?>" title="Seite lesen">
                <strong>📖 <?= e($page->title) ?></strong>
                <span class="task-tile-desc"><?= e(mb_substr(preg_replace('/[#*`]/', '', trim($page->content)), 0, 140)) ?><?= mb_strlen($page->content) > 140 ? ' …' : '' ?></span>
            </a>
        </div>
        <div class="task-tile-pills">
            <span class="pill">ab Jgst. <?= (int) $page->level ?></span>
        </div>
        <?php if ($isOwner): ?>
        <div class="task-tile-actions">
            <a class="icon-btn" href="<?= url('wiki/' . (int) $page->id . '/bearbeiten') ?>" title="Seite bearbeiten">✏️</a>
            <form method="post" action="<?= url('wiki/' . (int) $page->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="Seite löschen">🗑</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<?php if ($pages === []): ?>
<div class="notice">
    <strong>Noch keine Wiki-Seiten</strong>
    <p>Deine Lehrkraft hat noch keine Seiten angelegt.</p>
</div>
<?php else: ?>
<div class="task-grid">
    <?php foreach ($pages as $page): ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <a class="task-tile-main" href="<?= url('wiki/' . (int) $page->id) ?>" title="Seite lesen">
                <strong>📖 <?= e($page->title) ?></strong>
                <span class="task-tile-desc"><?= e(mb_substr(preg_replace('/[#*`]/', '', trim($page->content)), 0, 140)) ?><?= mb_strlen($page->content) > 140 ? ' …' : '' ?></span>
            </a>
        </div>
        <div class="task-tile-pills">
            <span class="pill">ab Jgst. <?= (int) $page->level ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
