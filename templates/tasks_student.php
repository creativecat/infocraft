<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Meine Aufgaben</h1>
    <p>Aufgaben, die deine Lehrkraft für dich bereitgestellt hat. Andere Klassenstufen bleiben unsichtbar.</p>
</section>

<?php if ($tasks === []): ?>
<div class="notice">
    <strong>Alles erledigt</strong>
    <p>Derzeit sind keine Aufgaben für dich ausgestellt.</p>
</div>
<?php else: ?>
<?php foreach ($tasks as $entry): ?>
<?php $task = $entry['task']; ?>
<section class="panel">
    <div class="panel-head">
        <h3><span class="pill"><?= e($task->type) ?></span> <?= e($task->title) ?></h3>
        <span class="muted">von <?= e($entry['teacherName']) ?><?= $entry['className'] !== null ? ' · Klasse ' . e($entry['className']) : '' ?></span>
    </div>
    <?php if ($task->description !== ''): ?>
    <p><?= e($task->description) ?></p>
    <?php endif; ?>
    <p class="muted">Passender Arbeitsbereich: <?= e($task->type) ?> in der Werkstatt.</p>
</section>
<?php endforeach; ?>
<?php endif; ?>

