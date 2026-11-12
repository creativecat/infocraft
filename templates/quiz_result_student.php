<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Quiz: Ergebnis für Lernende
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1><?= e($quiz->title) ?></h1>
    <p class="score-big"><?= e($best['score']) ?> von <?= e($best['total']) ?> richtig</p>
</section>

<?php foreach ($questions as $index => $question): ?>
<?php $given = (int) ($answers[$index + 1] ?? 0); $correct = (int) $question['correct']; ?>
<section class="panel">
    <div class="panel-head">
        <h3>Frage <?= $index + 1 ?>: <?= e($question['question']) ?></h3>
        <span class="muted"><?= $given === $correct ? '✅ Richtig' : '❌ Falsch' ?></span>
    </div>
    <div class="<?= $given === $correct ? 'review-correct' : 'review-wrong' ?>">
        <p>Richtig: <strong><?= e($question['answer_' . $correct]) ?></strong></p>
        <?php if ($given !== $correct && $given > 0): ?>
        <p class="muted">Deine Antwort: <?= e($question['answer_' . $given]) ?></p>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>

<div class="btn-row">
    <a class="btn btn-primary" href="<?= url('quiz/' . $quiz->id . '/spielen') ?>">Nochmal spielen</a>
    <a class="btn btn-ghost" href="<?= url('quiz') ?>">Zur Übersicht</a>
</div>

