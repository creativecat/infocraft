<?php
/** @var list<array{task: \Infocraft\Models\Task, className: ?string, teacherName: string, dueAt: string|null, submission: array{content: string, submittedAt: string}|null, grading: array{grade: string, comment: string, source: string, checked: bool}|null}> $tasks */
?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Meine Aufgaben</h1>
    <p>Aufgaben, die deine Lehrkraft für dich bereitgestellt hat. Andere Klassenstufen bleiben unsichtbar. Schreibe deine Lösung in das Feld und gib sie ab, du kannst bis zum Abgabetermin jederzeit verbessern.</p>
</section>

<?php if ($tasks === []): ?>
<div class="notice">
    <strong>Alles erledigt</strong>
    <p>Derzeit sind keine Aufgaben für dich ausgestellt.</p>
</div>
<?php else: ?>
<?php foreach ($tasks as $entry): ?>
<?php
$task = $entry['task'];
$gradingVisible = $entry['grading'] !== null && ($entry['grading']['source'] === 'lk' || $entry['grading']['checked']);
$late = $entry['dueAt'] !== null && date('Y-m-d H:i') > $entry['dueAt'];
?>
<section class="panel">
    <div class="panel-head">
        <h3><span class="pill"><?= e($task->type) ?></span> <?= e($task->title) ?></h3>
        <span class="muted">von <?= e($entry['teacherName']) ?><?= $entry['className'] !== null ? ' · Klasse ' . e($entry['className']) : '' ?></span>
    </div>
    <?php if ($task->description !== ''): ?>
    <p><?= e($task->description) ?></p>
    <?php endif; ?>
    <p class="muted">
        <?php if ($entry['dueAt'] !== null): ?>
        Abgabe bis <strong><?= e($entry['dueAt']) ?> Uhr</strong><?= $late ? ' · <strong>Abgabetermin überschritten</strong>' : '' ?>
        <?php else: ?>
        Passender Arbeitsbereich: <?= e($task->type) ?> in der Werkstatt.
        <?php endif; ?>
    </p>
    <?php if ($gradingVisible && $entry['grading'] !== null): ?>
    <div class="notice">
        <strong>Korrigiert: Note <?= e($entry['grading']['grade'] !== '' ? $entry['grading']['grade'] : '-') ?></strong>
        <?php if ($entry['grading']['comment'] !== ''): ?><p><?= e($entry['grading']['comment']) ?></p><?php endif; ?>
    </div>
    <p class="muted">Deine abgegebene Lösung (nach der Korrektur nicht mehr änderbar):</p>
    <div class="own"><?= e($entry['submission'] !== null ? $entry['submission']['content'] : '(keine Abgabe)') ?></div>
    <?php else: ?>
    <?php if ($entry['submission'] !== null): ?>
    <p class="muted">Abgegeben am <?= e($entry['submission']['submittedAt']) ?>. Du kannst deine Lösung verbessern und erneut abgeben<?= $entry['dueAt'] !== null ? ' (Abgabetermin: ' . e($entry['dueAt']) . ' Uhr, danach zählt sie als verspätet)' : '' ?>.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/abgeben') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Deine Lösung (Text)</span>
            <textarea name="content" rows="5" placeholder="Schreibe hier deine Lösung hinein ..."><?= $entry['submission'] !== null ? e($entry['submission']['content']) : '' ?></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit"><?= $entry['submission'] !== null ? 'Neue Version abgeben' : 'Abgeben' ?></button></div>
    </form>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
