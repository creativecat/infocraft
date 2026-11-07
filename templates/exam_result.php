<?php /** @var \Infocraft\Models\Exam $exam */ ?>
<?php $percent = $totals['total'] > 0 ? $totals['reached'] / $totals['total'] * 100.0 : 0.0; ?>
<section class="panel">
    <div class="panel-head">
        <h3>Ergebnis: <?= e($exam->title) ?></h3>
        <span class="pill"><?= $exam->kind === 'pruefung' ? '🔒 Prüfung' : '📄 Schularbeit' ?></span>
    </div>
    <p class="score-big"><?= e(number_format($totals['reached'], 1, ',', '')) ?> von <?= e($totals['total']) ?> BE · Note <?= e($note) ?> (<?= e(\Infocraft\Models\Exam::gradeLabel($note)) ?>)</p>
    <p class="muted">Das entspricht <?= e(number_format($percent, 1, ',', '')) ?> Prozent der Bewertungseinheiten.</p>
    <?php if ($work !== null && $work['submitted_at'] !== null): ?>
    <p class="muted">Abgegeben am <?= e($work['submitted_at']) ?>.</p>
    <?php endif; ?>
</section>

<?php foreach ($tasks as $task): ?>
<?php $grading = $gradings[$task['id']] ?? null; ?>
<section class="panel">
    <div class="panel-head">
        <h3>Aufgabe <?= e($task['position']) ?>: <?= e($task['title']) ?> <span class="pill"><?= e($task['be']) ?> BE</span>
        <?php if ($grading !== null): ?>
        <span class="badge-solved"><?= e(number_format($grading['be'], 1, ',', '')) ?> BE erreicht</span>
        <?php endif; ?>
        </h3>
    </div>
    <div class="compare-grid">
        <div>
            <strong class="muted">Deine Lösung</strong>
            <div class="own"><?= e($answers[$task['id']] ?? '(keine Antwort)') ?></div>
        </div>
        <div>
            <strong class="muted">Musterlösung</strong>
            <div class="muster"><?= e($task['sample_solution']) ?></div>
        </div>
    </div>
    <?php if ($grading !== null && $grading['comment'] !== ''): ?>
    <p><strong>Feedback der Lehrkraft:</strong> <?= e($grading['comment']) ?></p>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<div class="btn-row"><a class="btn btn-ghost" href="<?= url('pruefungen') ?>">← Zur Übersicht</a></div>
