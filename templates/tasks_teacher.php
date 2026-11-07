<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Aufgabenpool</h1>
    <p>Aufgaben anlegen und an ganze Klassen oder einzelne Personen austellen. Schülerinnen und Schüler sehen nur Aufgaben bis zu ihrer Jahrgangsstufe.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Aufgabe anlegen</h3>
        <form method="post" action="<?= url('aufgaben/anlegen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. JOINs üben"></label>
            <label class="field"><span>Beschreibung</span><textarea name="description" rows="4" placeholder="Was ist zu tun?"></textarea></label>
            <div class="two-col">
                <label class="field"><span>Typ</span>
                    <select name="type">
                        <option value="allgemein">Allgemein</option>
                        <option value="sql">SQL</option>
                        <option value="html">HTML</option>
                        <option value="code">Code (Java/Python)</option>
                        <option value="quiz">Quiz</option>
                    </select>
                </label>
                <label class="field"><span>Jahrgangsstufe</span>
                    <select name="level">
                        <?php for ($level = 6; $level <= 13; $level++): ?>
                        <option value="<?= $level ?>"<?= $level === 9 ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <div class="btn-row"><button class="btn btn-primary" type="submit">Anlegen</button></div>
        </form>
    </section>
    <section class="panel">
        <h3>📚 Meine Aufgaben</h3>
        <?php if ($tasks === []): ?>
        <p class="muted">Noch keine Aufgaben angelegt.</p>
        <?php endif; ?>
        <?php foreach ($tasks as $task): ?>
        <div class="mission">
            <div class="mission-head">
                <strong><?= e($task->title) ?></strong>
                <span class="pill"><?= e($task->type) ?> · Jgst. <?= (int) $task->level ?></span>
            </div>
            <?php if ($task->description !== ''): ?><p class="muted"><?= e($task->description) ?></p><?php endif; ?>
            <p class="muted">Ausgeteilt an: <?= $targets[$task->id] === [] ? 'niemanden' : e(implode(', ', array_map(static fn (array $target): string => $target['kind'] . ' ' . $target['name'], $targets[$task->id]))) ?></p>
            <form method="post" action="<?= url('aufgaben/austeilen') ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="task_id" value="<?= (int) $task->id ?>">
                <div class="two-col">
                    <label class="field"><span>Ganze Klasse</span>
                        <select name="class_id">
                            <option value="0">- keine -</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field"><span>Einzelne Person</span>
                        <select name="user_id">
                            <option value="0">- keine -</option>
                            <?php foreach ($studentsByClass as $classId => $students): ?>
                            <optgroup label="Klasse <?= e($classNames[$classId] ?? '') ?>">
                                <?php foreach ($students as $student): ?>
                                <option value="<?= (int) $student->id ?>"><?= e($student->fullName()) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="btn-row"><button class="btn btn-ghost" type="submit">Austeilen</button></div>
            </form>
        </div>
        <?php endforeach; ?>
    </section>
</div>

