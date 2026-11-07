<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Aufgabenpool</h1>
    <p>Aufgaben anlegen und an ganze Klassen oder einzelne Personen austellen. Fertige Aufgaben aus dem Katalog übernehmen und auf einen Schlag alle einer Jahrgangsstufe. Schülerinnen und Schüler sehen nur Aufgaben bis zu ihrer Jahrgangsstufe.</p>
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
        <p class="muted">Noch keine Aufgaben angelegt. Nimm links eine eigene auf oder unten aus dem Katalog.</p>
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
                <div class="btn-row">
                    <button class="btn btn-ghost" type="submit">Austeilen</button>
                </div>
            </form>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit">🗑 Löschen</button>
            </form>
        </div>
        <?php endforeach; ?>
    </section>
</div>

<section class="panel">
    <h3>📦 Aufgabenkatalog (fertige Aufgaben)</h3>
    <p class="muted">Kuratierte Aufgaben passend zum LehrplanPLUS. Übernehme einzelne Aufgaben als Kopie in deinen Pool oder mit einem Klick alle einer Jahrgangsstufe.</p>
    <div class="two-col">
        <form method="get" action="<?= url('aufgaben') ?>" class="form">
            <div class="two-col">
                <label class="field"><span>Jahrgangsstufe</span>
                    <select name="level">
                        <option value="0"<?= $filterLevel === 0 ? ' selected' : '' ?>>alle</option>
                        <?php for ($level = 6; $level <= 13; $level++): ?>
                        <option value="<?= $level ?>"<?= $filterLevel === $level ? ' selected' : '' ?>>Jgst. <?= $level ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="field"><span>Typ</span>
                    <select name="type">
                        <option value=""<?= $filterType === '' ? ' selected' : '' ?>>alle</option>
                        <?php foreach (['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Code', 'quiz' => 'Quiz'] as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue) ?>"<?= $filterType === $typeValue ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="btn-row"><button class="btn btn-ghost" type="submit">Filtern</button></div>
        </form>
        <form method="post" action="<?= url('aufgaben/katalog/stufe') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Alle einer Jahrgangsstufe auf einmal übernehmen (überspringt Aufgaben, die du schon hast)</span>
                <select name="level">
                    <?php for ($level = 6; $level <= 13; $level++): ?>
                    <option value="<?= $level ?>"<?= $filterLevel === $level ? ' selected' : '' ?>>Jgst. <?= $level ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit">Stufe übernehmen</button></div>
        </form>
    </div>
    <?php if ($catalog === []): ?>
    <p class="muted">Keine Katalog-Aufgaben für diesen Filter.</p>
    <?php endif; ?>
    <?php foreach ($catalog as $entry): ?>
    <?php $owned = in_array(mb_strtolower(trim($entry['title'])), $ownedTitles, true); ?>
    <div class="mission">
        <div class="mission-head">
            <strong><?= e($entry['title']) ?></strong>
            <span class="pill"><?= e($entry['type']) ?> · Jgst. <?= (int) $entry['level'] ?></span>
        </div>
        <p class="muted"><?= e($entry['description']) ?></p>
        <?php if ($owned): ?>
        <p><span class="pill">✓ bereits im Pool</span></p>
        <?php else: ?>
        <form method="post" action="<?= url('aufgaben/katalog') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="template_id" value="<?= (int) $entry['id'] ?>">
            <button class="btn btn-ghost" type="submit">In meinen Pool übernehmen</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

