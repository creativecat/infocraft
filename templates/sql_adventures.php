<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: SQL-Werkstatt: Abenteuer-Übersicht (Quest-Auswahl)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.24.0 */ ?>
<section class="hero">
    <div class="hero-emoji">🗺️</div>
    <h1>SQL-Abenteuer</h1>
    <p><?= $isTeacher
        ? 'Erzählte SQL-Missionen mit Rahmenhandlung: Du kannst jedes Abenteuer direkt spielen oder es an deine Klassen austeilen (Schreibrechte nötig).'
        : 'Spiel dich mit SQL durch echte Geschichten: Wähle ein Abenteuer aus und löse Kapitel für Kapitel.' ?></p>
</section>

<?php $anyAvailable = false; foreach ($adventures as $adv) { if ($adv['available']) { $anyAvailable = true; break; } } ?>
<?php if (!$anyAvailable): ?>
<div class="notice">
    <strong>Noch kein Abenteuer freigeschaltet</strong>
    <p>Deine Lehrkraft hat dir noch kein Abenteuer zugeteilt. Sobald das passiert, erscheint es hier mit deinem Fortschritt.</p>
</div>
<?php endif; ?>

<div class="cards adv-cards">
    <?php foreach ($adventures as $adv): ?>
    <?php $percent = $adv['total'] > 0 ? (int) round($adv['solved'] * 100 / $adv['total']) : 0; ?>
    <?php if ($adv['available']): ?>
    <a class="card adv-card" href="<?= url('werkstatt/sql/' . e($adv['template']->slug)) ?>" title="Kapitel-Ansicht öffnen">
        <div class="adv-scene adv-card-scene"><?= $adv['meta']['scene'] ?></div>
        <div class="adv-card-body">
            <h2><?= $adv['meta']['emoji'] ?> <?= e($adv['template']->title) ?></h2>
            <p class="card-desc"><?= e($adv['meta']['teaser']) ?></p>
            <div class="adv-progress" title="Fortschritt: <?= $adv['solved'] ?> von <?= $adv['total'] ?> Kapiteln gelöst"><div class="adv-progress-fill" style="width: <?= $percent ?>%"></div></div>
            <div class="card-meta">
                <span class="pill"><?= $adv['solved'] ?> / <?= $adv['total'] ?> Kapitel<?= $adv['solved'] === $adv['total'] && $adv['total'] > 0 ? ' ✅' : '' ?></span>
                <span class="pill">Jahrgang <?= (int) $adv['template']->level ?></span>
            </div>
        </div>
    </a>
    <?php else: ?>
    <div class="card adv-card adv-card-locked" title="Deine Lehrkraft kann dir dieses Abenteuer freischalten">
        <div class="adv-scene adv-card-scene"><?= $adv['meta']['scene'] ?></div>
        <div class="adv-card-body">
            <h2><?= $adv['meta']['emoji'] ?> <?= e($adv['template']->title) ?></h2>
            <p class="card-desc"><?= e($adv['meta']['teaser']) ?></p>
            <div class="card-meta"><span class="pill">🔒 Noch nicht zugeteilt</span></div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<p><a class="btn btn-ghost" href="<?= url('werkstatt/sql') ?>">← Zurück zur Werkstatt</a></p>
