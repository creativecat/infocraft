<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1><?= e($quiz->title) ?></h1>
    <p><?= e($quiz->description) ?> · ab Jahrgangsstufe <?= e($quiz->level) ?> · <a href="<?= url('quiz') ?>">zur Übersicht</a></p>
</section>

<section class="panel">
    <h3>Fragen (<?= count($questions) ?>)</h3>
    <?php if ($questions === []): ?>
    <p class="muted">Noch keine Fragen. Füge unten die erste Frage hinzu.</p>
    <?php else: ?>
    <ol>
        <?php foreach ($questions as $question): ?>
        <li style="margin-bottom: 14px;">
            <strong><?= e($question['question']) ?></strong>
            <ul>
                <?php foreach ([1, 2, 3, 4] as $n): ?>
                <li><?= (int) $question['correct'] === $n ? '✅ ' : '' ?><?= e($question['answer_' . $n]) ?></li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</section>

<section class="panel">
    <h3>Neue Frage</h3>
    <form method="post" action="<?= url('quiz/' . $quiz->id . '/frage') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Frage</span><input type="text" name="question" required maxlength="500"></label>
        <div class="form-row form">
            <label class="field"><span>Antwort 1</span><input type="text" name="answer_1" required maxlength="300"></label>
            <label class="field"><span>Antwort 2</span><input type="text" name="answer_2" required maxlength="300"></label>
            <label class="field"><span>Antwort 3</span><input type="text" name="answer_3" required maxlength="300"></label>
            <label class="field"><span>Antwort 4</span><input type="text" name="answer_4" required maxlength="300"></label>
        </div>
        <label class="field"><span>Richtige Antwort</span>
            <select name="correct">
                <option value="1">Antwort 1</option>
                <option value="2">Antwort 2</option>
                <option value="3">Antwort 3</option>
                <option value="4">Antwort 4</option>
            </select>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Frage hinzufügen">➕</button></div>
    </form>
</section>

<section class="panel">
    <h3>Quiz austellen</h3>
    <?php if ($questions === []): ?>
    <p class="muted">Sinnvoll erst, wenn mindestens eine Frage vorhanden ist.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('quiz/' . $quiz->id . '/austeilen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Klasse</span>
                <select name="class_id">
                    <option value="0">- keine -</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= e($class->id) ?>">Klasse <?= e($class->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>oder einzelne Person</span>
                <select name="user_id">
                    <option value="0">- keine -</option>
                    <?php foreach ($studentsByClass as $classId => $students): ?>
                    <optgroup label="Klasse <?= e($classNames[$classId] ?? '') ?>">
                        <?php foreach ($students as $student): ?>
                        <option value="<?= e($student->id) ?>"><?= e($student->fullName()) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit" title="Austeilen">📤</button>
        </div>
    </form>
    <?php if ($targets !== []): ?>
    <p class="muted">Ausgeteilt an:
        <?php foreach ($targets as $index => $target): ?><?= $index > 0 ? ', ' : '' ?><?= $target['kind'] === 'Klasse' ? 'Klasse ' : '' ?><?= e($target['name']) ?><?php endforeach; ?>
    </p>
    <?php endif; ?>
</section>

