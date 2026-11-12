<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Quiz: Übersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php if ($isTeacher): ?>
<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1>Quiz-Arena</h1>
    <p>Quiz anlegen, Fragen hinzufügen und an Klassen oder Einzelne austellen. Die Ergebnisse siehst du pro Quiz.</p>
</section>

<section class="panel">
    <h3>Neues Quiz anlegen</h3>
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
            <button class="btn btn-primary" type="submit" title="Anlegen">➕</button>
        </div>
    </form>
</section>

<?php if ($quizzes === []): ?>
<div class="notice">
    <strong>Noch keine Quiz</strong>
    <p>Lege oben dein erstes Quiz an, füge Fragen hinzu und stelle es aus.</p>
</div>
<?php else: ?>
<?php foreach ($quizzes as $entry): ?>
<?php $quiz = $entry['quiz']; ?>
<section class="panel">
    <div class="panel-head">
        <h3><?= e($quiz->title) ?> <span class="pill"><?= e($entry['questionCount']) ?> Fragen</span></h3>
        <span class="muted">ab Jahrgangsstufe <?= e($quiz->level) ?><?= $quiz->description !== '' ? ' · ' . e($quiz->description) : '' ?></span>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= url('quiz/' . $quiz->id) ?>" title="Fragen & Austeilen">❓</a>
        <a class="btn btn-primary" href="<?= url('quiz/' . $quiz->id . '/ergebnisse') ?>" title="Ergebnisse">📊</a>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>
<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1>Quiz-Arena</h1>
    <p>Quiz deiner Lehrkraft: Fragen beantworten, Punkte sammeln, Bestwertung verbessern.</p>
</section>

<?php if ($quizzes === []): ?>
<div class="notice">
    <strong>Keine Quiz</strong>
    <p>Derzeit ist kein Quiz für dich ausgeteilt.</p>
</div>
<?php else: ?>
<?php foreach ($quizzes as $entry): ?>
<?php $quiz = $entry['quiz']; ?>
<section class="panel">
    <div class="panel-head">
        <h3><?= e($quiz->title) ?> <span class="pill"><?= e($entry['questionCount']) ?> Fragen</span></h3>
        <?php if ($entry['best'] !== null): ?>
        <span class="badge-solved">Bestwertung: <?= e($entry['best']['score']) ?>/<?= e($entry['best']['total']) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($quiz->description !== ''): ?>
    <p><?= e($quiz->description) ?></p>
    <?php endif; ?>
    <div class="btn-row">
        <a class="btn btn-primary" href="<?= url('quiz/' . $quiz->id . '/spielen') ?>"><?= $entry['best'] !== null ? 'Nochmal spielen' : 'Spielen' ?></a>
        <?php if ($entry['best'] !== null): ?>
        <a class="btn btn-ghost" href="<?= url('quiz/' . $quiz->id . '/ergebnis') ?>" title="Ergebnis ansehen">📊</a>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

