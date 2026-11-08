<?php if ($isTeacher): ?>
<section class="hero hero-compact">
    <div class="hero-emoji">🏆</div>
    <div class="hero-text">
        <h1>Quiz-Arena</h1>
        <p>Quiz anlegen, Fragen hinzufügen und an Klassen oder Einzelne austellen. Die Ergebnisse siehst du pro Quiz.</p>
    </div>
</section>

<section class="panel panel-compact">
    <h3>➕ Neues Quiz anlegen</h3>
    <form method="post" action="<?= url('quiz/anlegen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. SQL-Begriffe"></label>
            <label class="field"><span>Beschreibung</span><input type="text" name="description" maxlength="200" placeholder="Worum geht es?"></label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit" title="Quiz anlegen">➕</button>
        </div>
    </form>
</section>

<?php if ($quizzes === []): ?>
<div class="notice">
    <strong>Noch keine Quiz</strong>
    <p>Lege oben dein erstes Quiz an, füge Fragen hinzu und stelle es aus.</p>
</div>
<?php else: ?>
<div class="task-grid">
    <?php foreach ($quizzes as $entry): ?>
    <?php $quiz = $entry['quiz']; ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <span class="task-tile-main" title="<?= e($quiz->title) ?>">
                <strong>❓ <?= e($quiz->title) ?></strong>
                <span class="task-tile-meta"><?= $quiz->description !== '' ? e($quiz->description) : 'Quiz' ?></span>
            </span>
        </div>
        <div class="task-tile-pills">
            <span class="pill"><?= e($entry['questionCount']) ?> Fragen</span>
            <span class="pill">ab Jgst. <?= (int) $quiz->level ?></span>
        </div>
        <div class="task-tile-actions">
            <a class="btn btn-primary" href="<?= url('quiz/' . (int) $quiz->id) ?>" title="Fragen pflegen und Quiz austeilen">❓</a>
            <a class="icon-btn" href="<?= url('quiz/' . (int) $quiz->id . '/ergebnisse') ?>" title="Ergebnisse der Klasse ansehen">📊</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<section class="hero hero-compact">
    <div class="hero-emoji">🏆</div>
    <div class="hero-text">
        <h1>Quiz-Arena</h1>
        <p>Quiz deiner Lehrkraft: Fragen beantworten, Punkte sammeln, Bestwertung verbessern.</p>
    </div>
</section>

<?php if ($quizzes === []): ?>
<div class="notice">
    <strong>Keine Quiz</strong>
    <p>Derzeit ist kein Quiz für dich ausgeteilt.</p>
</div>
<?php else: ?>
<div class="task-grid">
    <?php foreach ($quizzes as $entry): ?>
    <?php $quiz = $entry['quiz']; ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <span class="task-tile-main" title="<?= e($quiz->title) ?>">
                <strong>❓ <?= e($quiz->title) ?></strong>
                <span class="task-tile-meta"><?= $quiz->description !== '' ? e($quiz->description) : 'Quiz' ?></span>
            </span>
        </div>
        <div class="task-tile-pills">
            <span class="pill"><?= e($entry['questionCount']) ?> Fragen</span>
            <?php if ($entry['best'] !== null): ?><span class="pill pill-ok" title="Deine Bestwertung">⭐ <?= e($entry['best']['score']) ?>/<?= e($entry['best']['total']) ?></span><?php endif; ?>
        </div>
        <div class="task-tile-actions">
            <a class="btn btn-primary" href="<?= url('quiz/' . (int) $quiz->id . '/spielen') ?>" title="<?= $entry['best'] !== null ? 'Nochmal spielen' : 'Spielen' ?>">▶</a>
            <?php if ($entry['best'] !== null): ?>
            <a class="icon-btn" href="<?= url('quiz/' . (int) $quiz->id . '/ergebnis') ?>" title="Dein Ergebnis ansehen">📊</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
