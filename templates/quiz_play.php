<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1><?= e($quiz->title) ?></h1>
</section>

<p class="quiz-progress center">Frage <?= e($position) ?> von <?= e($total) ?></p>

<section class="panel">
    <h3><?= e($question['question']) ?></h3>
    <form method="post" action="<?= url('quiz/' . $quiz->id . '/antwort') ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="position" value="<?= e($position) ?>">
        <?php foreach ([1, 2, 3, 4] as $n): ?>
        <label class="quiz-option">
            <input type="radio" name="answer" value="<?= $n ?>" required>
            <span><?= e($question['answer_' . $n]) ?></span>
        </label>
        <?php endforeach; ?>
        <div class="btn-row"><button class="btn btn-primary" type="submit">Antwort abgeben</button></div>
    </form>
</section>

