<?php /** @var \Infocraft\Models\Exam $exam */ ?>
<div data-exam-save="<?= url('pruefungen/' . $exam->id . '/antwort') ?>">
<section class="panel">
    <div class="panel-head">
        <h3><?= e($exam->title) ?> <span class="pill"><?= $exam->kind === 'pruefung' ? '🔒 Prüfung' : '📄 Schularbeit' ?></span></h3>
        <?php if ($work['submitted_at'] !== null): ?>
        <span class="badge-solved">abgegeben</span>
        <?php endif; ?>
    </div>
    <?php if ($exam->description !== ''): ?>
    <p><?= e($exam->description) ?></p>
    <?php endif; ?>
    <?php if ($project !== null): ?>
    <p class="muted">Dein Material: <a href="<?= url($project->type === 'html' ? 'werkstatt/html/' : 'werkstatt/code/') . $project->id ?>"><?= $project->type === 'html' ? 'Seite öffnen' : 'Projekt öffnen' ?></a> (<?= e($project->title) ?>)</p>
    <?php endif; ?>
    <p class="muted">Deine Eingaben speichern sich automatisch. Du kannst die Seite also auch neu laden, ohne etwas zu verlieren.</p>
</section>

<?php foreach ($tasks as $task): ?>
<section class="panel">
    <div class="panel-head">
        <h3>Aufgabe <?= e($task['position']) ?>: <?= e($task['title']) ?> <span class="pill"><?= e($task['be']) ?> BE</span></h3>
        <span class="ex-answer-status" id="ex-status-<?= e($task['id']) ?>"></span>
    </div>
    <?php if ($task['description'] !== ''): ?>
    <p><?= e($task['description']) ?></p>
    <?php endif; ?>
    <label class="field"><span>Deine Lösung</span>
        <textarea class="ex-answer" data-task="<?= e($task['id']) ?>" rows="8" placeholder="Schreibe hier deine Lösung. Es speichert automatisch."<?= $work['submitted_at'] !== null ? ' readonly' : '' ?>><?= e($answers[$task['id']] ?? '') ?></textarea>
    </label>
</section>
<?php endforeach; ?>

<?php if ($work['submitted_at'] === null): ?>
<section class="panel">
    <h3>Abgabe</h3>
    <p class="muted">Wenn du fertig bist, gib hier endgültig ab. Danach kannst du nichts mehr ändern.</p>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/abgeben') ?>" onsubmit="return confirm('Wirklich endgültig abgeben? Danach ist keine Änderung mehr möglich.');">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Endgültig abgeben</button>
    </form>
</section>
<?php else: ?>
<div class="notice">
    <strong>Deine Arbeit ist abgegeben</strong>
    <p>Vielen Dank! Sobald die Lehrkraft korrigiert und die Ergebnisse herausgibt, siehst du hier dein Ergebnis mit der Musterlösung im Vergleich.</p>
</div>
<?php endif; ?>
</div>
