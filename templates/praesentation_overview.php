<?php
/** @var list<\Infocraft\Models\Presentation> $items @var \Infocraft\Models\User|null $user */
// Nach Jahrgangsstufe gruppieren, damit jede Stufe einen Abschnitt mit
// Kachelraster bekommt (gleiches Muster wie der Aufgabenpool).
$byLevel = [];
foreach ($items as $presentation) {
    $byLevel[(int) $presentation->level][] = $presentation;
}
ksort($byLevel);
$canEdit = static function (\Infocraft\Models\Presentation $p) use ($user): bool {
    return $user !== null && ($user->role === 'admin' || $p->teacherId === $user->id);
};
?>
<section class="hero hero-compact">
    <div class="hero-emoji">🎬</div>
    <div class="hero-text">
        <h1>Präsentationen</h1>
        <p><?= $isTeacher
            ? 'Folien für den Unterricht: Text und Code mit Syntax-Highlighting, Vollbild-Modus und live veränderbaren Code-Folien, gegliedert nach Jahrgangsstufe.'
            : 'Folien deiner Lehrkraft, gegliedert nach Jahrgangsstufe.' ?></p>
    </div>
</section>

<?php if ($isTeacher): ?>
<section class="panel panel-compact">
    <h3>➕ Neue Präsentation anlegen</h3>
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
            <button class="btn btn-primary" type="submit" title="Präsentation anlegen">➕</button>
        </div>
    </form>
</section>

<?php if ($items === []): ?>
<div class="notice">
    <strong>Noch keine Präsentationen</strong>
    <p>Lege oben eine Präsentation an und füge Text- und Code-Folien hinzu. Im Präsentationsmodus lässt sich der Code direkt vorführen und verändern.</p>
</div>
<?php else: ?>
<?php foreach ($byLevel as $level => $levelItems): ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $level ?> <span class="pill"><?= count($levelItems) ?> Präsentation<?= count($levelItems) === 1 ? '' : 'en' ?></span></h2>
<div class="task-grid">
    <?php foreach ($levelItems as $presentation): ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <a class="task-tile-main" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>" title="Präsentation starten">
                <strong>🎬 <?= e($presentation->title) ?></strong>
                <span class="task-tile-meta"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?><?= $presentation->slug !== null ? ' · 🔗 Themen-Link' : '' ?></span>
            </a>
        </div>
        <div class="task-tile-pills">
            <?php if ($presentation->slug !== null): ?><span class="pill" title="Permanenter Themen-Link, z. B. für den Stundenplan"><a href="<?= url('praesentationen/thema/' . rawurlencode($presentation->slug)) ?>">🔗 Thema</a></span><?php endif; ?>
        </div>
        <div class="task-tile-actions">
            <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>" title="Präsentieren">▶</a>
            <a class="icon-btn" href="<?= url('praesentationen/' . (int) $presentation->id . '/moderator') ?>" title="Moderator: Steuerzentrale - aktuelle und nächste Folie, Timer - synchron zur Präsentationsansicht">🖥</a>
            <?php if ($canEdit($presentation)): ?>
            <a class="icon-btn" href="<?= url('praesentationen/' . (int) $presentation->id) ?>" title="Bearbeiten: Titel, Stufe, Folien">✏️</a>
            <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="Präsentation löschen">🗑</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>
<?php if ($items === []): ?>
<div class="notice">
    <strong>Noch keine Präsentationen</strong>
    <p>Deine Lehrkraft hat noch keine Präsentationen für deine Klassenstufe freigegeben.</p>
</div>
<?php else: ?>
<?php foreach ($byLevel as $level => $levelItems): ?>
<h2 class="level-heading">Jahrgangsstufe <?= (int) $level ?> <span class="pill"><?= count($levelItems) ?> Präsentation<?= count($levelItems) === 1 ? '' : 'en' ?></span></h2>
<div class="task-grid">
    <?php foreach ($levelItems as $presentation): ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <a class="task-tile-main" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>" title="Präsentation ansehen">
                <strong>🎬 <?= e($presentation->title) ?></strong>
                <span class="task-tile-meta"><?= count($presentation->slides()) ?> Folie<?= count($presentation->slides()) === 1 ? '' : 'n' ?></span>
            </a>
        </div>
        <div class="task-tile-actions">
            <a class="btn btn-primary" href="<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>" title="Ansehen">▶</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
