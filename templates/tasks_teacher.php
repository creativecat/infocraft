<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Aufgabenpool</h1>
    <p>Aufgaben anlegen, bearbeiten und an ganze Klassen oder einzelne Personen austellen, mit Starttermin (ab wann die Aufgabe erscheint), Abgabetermin und optionaler Frist-Sperre. Eigene Aufgaben in den gemeinsamen Pool aller Lehrkräfte teilen, geteilte Aufgaben als Kopie übernehmen und frei anpassen. Fertige Aufgaben gibt es dazu aus dem Katalog.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Aufgabe anlegen</h3>
        <form method="post" action="<?= url('aufgaben/anlegen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. JOINs üben"></label>
            <label class="field"><span>Beschreibung</span><textarea name="description" rows="4" placeholder="Was ist zu tun?"></textarea></label>
            <label class="field"><span>Musterlösung (optional, dient der Korrektur und der KI als Vergleich)</span><textarea name="sample_solution" rows="2" placeholder="Wie sieht eine gute Lösung aus?"></textarea></label>
            <label class="field"><span>Erwartungshorizont (optional, wird dir in der Korrektur und der KI als Bewertungshilfe mitgegeben)</span><textarea name="rubric" rows="2" placeholder="Worauf kommt es an, wie verteilen sich die Punkte?"></textarea></label>
            <div class="two-col">
                <label class="field"><span>Maximale Punktzahl (0 = keine Punktevergabe)</span>
                    <input type="number" name="max_points" min="0" max="1000" value="0">
                </label>
                <div class="field"><span>Frist-Sperre</span>
                    <label style="display:flex;gap:6px;align-items:center;font-weight:normal;"><input type="checkbox" name="late_lock" value="1"> Nach dem Abgabetermin keine neuen Abgaben mehr annehmen</label>
                </div>
            </div>
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
        <p class="muted">🤖 KI-Unterstützung: <?= $aiConfigured
            ? 'Schnittstelle hinterlegt, in der Korrekturansicht kannst du KI-Vorschläge anfordern.'
            : 'Noch keine Schnittstelle hinterlegt. <a href="' . url('aufgaben/ki') . '">Jetzt einrichten</a> (je Lehrkraft oder schulweit).' ?></p>
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
                <span class="pill"><?= e($task->type) ?> · Jgst. <?= (int) $task->level ?><?= $task->shared === 1 ? ' · 🤝 im gemeinsamen Pool' : '' ?><?= $task->maxPoints > 0 ? ' · max. ' . (int) $task->maxPoints . ' Punkte' : '' ?><?= $task->lateLock === 1 ? ' · 🔒 Frist-Sperre' : '' ?></span>
            </div>
            <?php if ($task->description !== ''): ?><p class="muted"><?= e($task->description) ?></p><?php endif; ?>
            <?php if ($targets[$task->id] === []): ?>
            <p class="muted">Ausgeteilt an: niemanden</p>
            <?php else: ?>
            <p class="muted">Ausgeteilt an:</p>
            <?php foreach ($targets[$task->id] as $target): ?>
            <?php $hidden = $target['visibleFrom'] !== null && $target['visibleFrom'] > date('Y-m-d H:i'); ?>
            <div class="form-inline" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:4px 0;">
                <span class="pill"><?= e($target['kind']) ?> <?= e($target['name']) ?><?= $hidden ? ' · ⏳ erscheint am ' . e($target['visibleFrom']) : '' ?></span>
                <span class="muted">Sichtbar: <?= $target['visibleFrom'] !== null ? 'ab ' . e($target['visibleFrom']) . ' Uhr' : 'sofort' ?> · Abgabe: <?= $target['dueAt'] !== null ? e($target['dueAt']) . ' Uhr' : 'ohne Termin' ?> · ausgeteilt am <?= e($target['assignedAt']) ?></span>
                <form method="post" action="<?= url('aufgaben/start') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="visible_from" value="<?= $target['visibleFrom'] !== null ? e(str_replace(' ', 'T', $target['visibleFrom'])) : '' ?>" title="Starttermin: ab wann sehen die Schülerinnen und Schüler diese Aufgabe? Leer lassen oder leeren Termin speichern für sofortige Sichtbarkeit.">
                    <button class="btn btn-ghost" type="submit" title="Starttermin dieser Zuweisung setzen oder mit leerem Feld streichen (sofort sichtbar)">🚀 Start</button>
                </form>
                <form method="post" action="<?= url('aufgaben/einziehen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <button class="btn btn-ghost" type="submit" title="Zuweisung zurücknehmen: die Aufgabe verschwindet für die Klasse bzw. Person, Abgaben bleiben erhalten">↩️ Einziehen</button>
                </form>
                <form method="post" action="<?= url('aufgaben/termin') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="due_at" value="<?= $target['dueAt'] !== null ? e(str_replace(' ', 'T', $target['dueAt'])) : '' ?>" title="Abgabetermin (Datum und Uhrzeit)">
                    <button class="btn btn-ghost" type="submit" title="Abgabetermin dieser Zuweisung setzen oder mit leerem Feld streichen">📅 Termin</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
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
                <label class="field"><span>Starttermin (optional): ab diesem Datum und Uhrzeit erscheint die Aufgabe für die Klasse bzw. Person, vorher ist sie unsichtbar</span>
                    <input type="datetime-local" name="visible_from">
                </label>
                <label class="field"><span>Abgabetermin (optional, Datum und Uhrzeit)</span>
                    <input type="datetime-local" name="due_at">
                </label>
                <div class="btn-row">
                    <button class="btn btn-ghost" type="submit">Austeilen</button>
                    <?php if ($targets[$task->id] !== []): ?>
                    <a class="btn btn-primary" href="<?= url('aufgaben/' . (int) $task->id . '/korrektur') ?>">✅ Korrektur (Abgaben ansehen)</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>">✏️ Bearbeiten</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/teilen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="shared" value="<?= $task->shared === 1 ? '0' : '1' ?>">
                    <button class="btn btn-ghost" type="submit"><?= $task->shared === 1 ? '🚫 Nicht mehr teilen' : '🤝 In den gemeinsamen Pool teilen' ?></button>
                </form>
                <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/loeschen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit">🗑 Löschen</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
</div>

<section class="panel">
    <h3>🤝 Gemeinsamer Pool (von anderen Lehrkräften geteilt)</h3>
    <p class="muted">Geteilte Aufgaben deiner Kolleginnen und Kollegen. Übernimm sie als Kopie in deinen eigenen Pool und passe sie individuell an, das Original bleibt unberührt.</p>
    <?php if ($pool === []): ?>
    <p class="muted">Noch hat keine Lehrkraft eine Aufgabe geteilt (oder du hast alle geteilten Aufgaben bereits übernommen). Teile selbst eine, damit andere davon profitieren.</p>
    <?php endif; ?>
    <?php foreach ($pool as $entry): ?>
    <?php $poolTask = $entry['task']; ?>
    <div class="mission">
        <div class="mission-head">
            <strong><?= e($poolTask->title) ?></strong>
            <span class="pill"><?= e($poolTask->type) ?> · Jgst. <?= (int) $poolTask->level ?> · von <?= e($entry['authorName']) ?></span>
        </div>
        <?php if ($poolTask->description !== ''): ?><p class="muted"><?= e($poolTask->description) ?></p><?php endif; ?>
        <?php if (in_array(mb_strtolower(trim($poolTask->title)), $ownedTitles, true)): ?>
        <p><span class="pill">✓ bereits in deinem Pool</span></p>
        <?php else: ?>
        <form method="post" action="<?= url('aufgaben/pool/' . (int) $poolTask->id . '/uebernehmen') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">Als Kopie in meinen Pool übernehmen</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

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
