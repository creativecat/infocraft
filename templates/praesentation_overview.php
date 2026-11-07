<section class="hero">
    <div class="hero-emoji">🎬</div>
    <h1>Präsentationen</h1>
    <p>Folien für den Unterricht: Text und Code mit Syntax-Highlighting, Vollbild-Modus und live veränderbaren Code-Folien.</p>
</section>

<?php if ($isTeacher): ?>
<section class="panel">
    <h3>Neue Präsentation anlegen</h3>
    <form method="post" action="<?= url('praesentationen/anlegen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Java: Klassen und Objekte"></label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php for ($i = 6; $i <= 13; $i++): ?>
                    <option value="<?= $i ?>"<?= $i === 9 ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit">Präsentation anlegen</button></div>
    </form>
</section>

<?php if ($items === []): ?>
<div class="notice">
    <strong>Noch keine Präsentationen</strong>
    <p>Lege oben eine Präsentation an und füge Text- und Code-Folien hinzu. Im Präsentationsmodus lässt sich der Code direkt vorführen und verändern.</p>
</div>
<?php else: ?>
<?php $currentLevel = 0; ?>
<?php foreach ($items as $presentation): ?>
<?php if ($presentation->level !== $currentLevel): ?>
<?php $currentLevel = $presentation->level; ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $currentLevel ?></h2>
<?php endif; ?>
<section class="panel">
    <div class="panel-head">
        <h3>🎬 <?= e($presentation->title) ?></h3>
        <span class="pill">ab Jgst. <?= e($presentation->level) ?></span>
    </div>
    <p class="muted"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?><?php if ($presentation->slug !== null): ?> &middot; Themen-Link: <a href="<?= url('praesentationen/thema/' . rawurlencode($presentation->slug)) ?>">/praesentationen/thema/<?= e($presentation->slug) ?></a><?php endif; ?></p>
    <div class="btn-row">
        <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">▶ Präsentieren</a>
        <?php if ($user->role === 'admin' || $presentation->teacherId === $user->id): ?>
        <a class="btn btn-ghost" href="<?= url('praesentationen/' . (int) $presentation->id) ?>">Bearbeiten</a>
        <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/loeschen') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">Löschen</button>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>
<?php if ($items === []): ?>
<div class="notice">
    <strong>Noch keine Präsentationen</strong>
    <p>Deine Lehrkraft hat noch keine Präsentationen für deine Klassenstufe freigegeben.</p>
</div>
<?php else: ?>
<?php $currentLevel = 0; ?>
<?php foreach ($items as $presentation): ?>
<?php if ($presentation->level !== $currentLevel): ?>
<?php $currentLevel = $presentation->level; ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $currentLevel ?></h2>
<?php endif; ?>
<section class="panel">
    <div class="panel-head">
        <h3>🎬 <?= e($presentation->title) ?></h3>
        <span class="pill">ab Jgst. <?= e($presentation->level) ?></span>
    </div>
    <p class="muted"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?></p>
    <div class="btn-row"><a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">▶ Ansehen</a></div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
