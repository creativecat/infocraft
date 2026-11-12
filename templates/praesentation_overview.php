<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Präsentationen: Übersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
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
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"<?= $i === 9 ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Präsentation anlegen">➕</button></div>
    </form>
</section>

<?php if ($items === []): ?>
<div class="notice">
    <strong>Noch keine Präsentationen</strong>
    <p>Lege oben eine Präsentation an und füge Text- und Code-Folien hinzu. Im Präsentationsmodus lässt sich der Code direkt vorführen und verändern.</p>
</div>
<?php else: ?>
<?php
$counts = [];
foreach ($items as $presentation) {
    $counts[$presentation->level] = ($counts[$presentation->level] ?? 0) + 1;
}
?>
<?php $currentLevel = 0; ?>
<?php foreach ($items as $presentation): ?>
<?php if ($presentation->level !== $currentLevel): ?>
<?php $currentLevel = $presentation->level; ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $currentLevel ?> <span class="pill"><?= (int) $counts[$currentLevel] ?> Präsentation<?= $counts[$currentLevel] === 1 ? '' : 'en' ?></span></h2>
<?php endif; ?>
<section class="panel praes-panel">
    <div class="praes-row">
        <div class="praes-info">
            <a class="praes-title" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">🎬 <?= e($presentation->title) ?></a>
            <span class="muted praes-meta"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?><?php if ($presentation->slug !== null): ?> &middot; <a href="<?= url('praesentationen/thema/' . rawurlencode($presentation->slug)) ?>">Themen-Link</a><?php endif; ?></span>
        </div>
        <div class="praes-actions">
            <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">▶ Präsentieren</a>
            <a class="btn btn-ghost" href="<?= url('praesentationen/' . (int) $presentation->id . '/moderator') ?>" title="Moderator: Steuerzentrale - aktuelle und nächste Folie, Timer - synchron zur Präsentationsansicht">🖥</a>
            <?php if ($user->role === 'admin' || $presentation->teacherId === $user->id): ?>
            <a class="btn btn-ghost" href="<?= url('praesentationen/' . (int) $presentation->id) ?>" title="Bearbeiten">✏️</a>
            <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" title="Löschen">🗑</button>
            </form>
            <?php endif; ?>
        </div>
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
<?php
$counts = [];
foreach ($items as $presentation) {
    $counts[$presentation->level] = ($counts[$presentation->level] ?? 0) + 1;
}
?>
<?php $currentLevel = 0; ?>
<?php foreach ($items as $presentation): ?>
<?php if ($presentation->level !== $currentLevel): ?>
<?php $currentLevel = $presentation->level; ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $currentLevel ?> <span class="pill"><?= (int) $counts[$currentLevel] ?> Präsentation<?= $counts[$currentLevel] === 1 ? '' : 'en' ?></span></h2>
<?php endif; ?>
<section class="panel praes-panel">
    <div class="praes-row">
        <div class="praes-info">
            <a class="praes-title" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">🎬 <?= e($presentation->title) ?></a>
            <span class="muted praes-meta"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?></span>
        </div>
        <div class="praes-actions">
            <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>">▶ Ansehen</a>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
