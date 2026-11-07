<?php /** @var \Infocraft\Models\User $student */ ?>
<section class="panel">
    <div class="panel-head">
        <h3>Arbeit von <?= e($student->fullName()) ?> <span class="pill"><?= e($exam->title) ?></span></h3>
        <a class="btn btn-ghost" href="<?= url('pruefungen/' . $exam->id . '/korrektur') ?>">← Alle Arbeiten</a>
    </div>
    <?php if ($work !== null): ?>
    <p class="muted">Begonnen: <?= e($work['started_at'] ?? '-') ?><?= $work['submitted_at'] !== null ? ' · Abgegeben: ' . e($work['submitted_at']) : ' · nicht abgegeben' ?></p>
    <?php endif; ?>
</section>

<?php if ($projectContent !== null): ?>
<section class="panel">
    <h3>Eingereichtes Projekt (Arbeitskopie)</h3>
    <pre class="work-code"><?= e($projectContent) ?></pre>
</section>
<?php endif; ?>

<form method="post" action="<?= url('pruefungen/' . $exam->id . '/bewertung') ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= e($student->id) ?>">
    <?php foreach ($tasks as $task): ?>
    <?php $answer = $answers[$task['id']] ?? ''; ?>
    <?php $grading = $gradings[$task['id']] ?? null; ?>
    <section class="panel">
        <div class="panel-head">
            <h3>Aufgabe <?= e($task['position']) ?>: <?= e($task['title']) ?> <span class="pill"><?= e($task['be']) ?> BE</span></h3>
            <?php if ($grading !== null && $grading['source'] === 'api' && !$grading['checked']): ?>
            <span class="status status-in-arbeit">API-Vorschlag, noch nicht geprüft</span>
            <?php elseif ($grading !== null): ?>
            <span class="badge-solved">geprüft</span>
            <?php endif; ?>
        </div>
        <div class="compare-grid">
            <div>
                <strong class="muted">Antwort der Schülerin/des Schülers</strong>
                <div class="own"><?= e($answer !== '' ? $answer : '(keine Antwort)') ?></div>
            </div>
            <div>
                <strong class="muted">Musterlösung</strong>
                <div class="muster"><?= e($task['sample_solution']) ?></div>
            </div>
        </div>
        <div class="form-row form" style="margin-top:12px;">
            <label class="field"><span>Erreichte BE (0 bis <?= e($task['be']) ?>)</span>
                <input type="number" step="0.5" min="0" max="<?= e($task['be']) ?>" name="be_<?= e($task['id']) ?>" value="<?= $grading !== null ? e(number_format($grading['be'], 1, '.', '')) : '0' ?>">
            </label>
            <label class="field" style="flex:2;"><span>Feedback (erscheint für die Schülerin/den Schüler)</span>
                <textarea name="comment_<?= e($task['id']) ?>" rows="2" maxlength="2000" placeholder="Was war gut, was fehlt?"><?= $grading !== null ? e($grading['comment']) : '' ?></textarea>
            </label>
        </div>
    </section>
    <?php endforeach; ?>
    <div class="btn-row"><button class="btn btn-primary" type="submit">Bewertung speichern (zählt als geprüft)</button></div>
</form>
