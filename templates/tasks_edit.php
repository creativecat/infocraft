<?php /** @var \Infocraft\Models\Task $task */ ?>
<section class="hero">
    <div class="hero-emoji">✏️</div>
    <h1>Aufgabe bearbeiten</h1>
    <p>Ändere Titel, Beschreibung, Typ, Jahrgangsstufe, Musterlösung, Erwartungshorizont, Punktzahl oder Frist-Sperre. Die Änderung gilt sofort für alle ausgeteilten Exemplare dieser Aufgabe.</p>
</section>

<section class="panel">
    <div class="panel-head">
        <h3><?= e($task->title) ?> <span class="pill"><?= e($task->type) ?> · Jgst. <?= (int) $task->level ?></span></h3>
        <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">← Zurück zum Aufgabenpool</a>
    </div>
    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" value="<?= e($task->title) ?>"></label>
        <label class="field"><span>Beschreibung</span><textarea name="description" rows="6"><?= e($task->description) ?></textarea></label>
        <label class="field"><span>Musterlösung (optional, dient der Korrektur und der KI als Vergleich)</span><textarea name="sample_solution" rows="4"><?= e($task->sampleSolution) ?></textarea></label>
        <label class="field"><span>Erwartungshorizont (optional, wird dir in der Korrektur und der KI als Bewertungshilfe mitgegeben)</span><textarea name="rubric" rows="3" placeholder="Worauf kommt es an, wie verteilen sich die Punkte?"><?= e($task->rubric) ?></textarea></label>
        <div class="two-col">
            <label class="field"><span>Maximale Punktzahl (0 = keine Punktevergabe)</span>
                <input type="number" name="max_points" min="0" max="1000" value="<?= (int) $task->maxPoints ?>">
            </label>
            <div class="field"><span>Frist-Sperre</span>
                <label style="display:flex;gap:6px;align-items:center;font-weight:normal;"><input type="checkbox" name="late_lock" value="1"<?= $task->lateLock === 1 ? ' checked' : '' ?>> Nach dem Abgabetermin keine neuen Abgaben mehr annehmen</label>
            </div>
        </div>
        <div class="two-col">
            <label class="field"><span>Typ</span>
                <select name="type">
                    <?php foreach (['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Code (Java/Python)', 'quiz' => 'Quiz'] as $typeValue => $typeLabel): ?>
                    <option value="<?= e($typeValue) ?>"<?= $task->type === $typeValue ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Jahrgangsstufe</span>
                <select name="level">
                    <?php for ($level = 6; $level <= 13; $level++): ?>
                    <option value="<?= $level ?>"<?= $task->level === $level ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                    <?php endfor; ?>
                </select>
            </label>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit">Änderungen speichern</button>
            <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">Abbrechen</a>
        </div>
    </form>
    <?php if ($task->shared === 1): ?>
    <p class="muted">🤝 Diese Aufgabe ist im gemeinsamen Pool aller Lehrkräfte geteilt. Deine Änderungen sehen die Kolleginnen und Kollegen dort sofort.</p>
    <?php endif; ?>
</section>
