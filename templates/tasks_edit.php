<?php
/** @var \Infocraft\Models\Task $task @var \Infocraft\Models\TaskTopic[] $topics @var \Infocraft\Models\TaskMaterial[] $materials @var list<\Infocraft\Models\TaskTodo> $todos @var list<array{id:int,kind:string,name:string,dueAt:?string,visibleFrom:?string,assignedAt:string,classId:?int,userId:?int}> $targets @var array<int, array{total:int,submitted:int}> $progress @var int $openGradings @var \Infocraft\Models\SchoolClass[] $classes @var array<int, list<\Infocraft\Models\User>> $studentsByClass @var string $defaultLanguage */
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
$typeHints = [
    'code' => '💡 Tipp für Java/Python-Aufgaben: Teile eine Starterdatei aus, z. B. Auto.java mit vorbereiteter Klasse. Über die Varianten-Aktion unten kannst du die Aufgabe später mit einem Klick als Java- oder Python-Variante duplizieren.',
    'html' => '💡 Tipp für HTML-Aufgaben: Teile ein index.html-Gerüst aus, in dem die Kinder nur die markierten Stellen ergänzen.',
    'sql' => '💡 Tipp für SQL-Aufgaben: Teile ein .sql-Schema aus, damit jede Aufgabe ihre eigene kleine Datenbank mitbringt (CREATE TABLE + INSERTs).',
    'quiz' => '💡 Tipp für Quiz-Aufgaben: Formuliere die Fragen als ToDos, dann können die Schülerinnen und Schüler jede Frage einzeln abhaken.',
    'allgemein' => '💡 Tipp: Beschreibe die Aufgabe mit der Formatierungsleiste und lege ToDos an, die die Schülerinnen und Schüler abhaken können.',
];
$classNames = [];
foreach ($classes as $class) {
    $classNames[$class->id] = $class->name;
}
?>
<section class="hero hero-compact">
    <div class="hero-emoji">✏️</div>
    <div class="hero-text">
        <h1>Aufgabe bearbeiten</h1>
        <p>Alles an einer Aufgabe an einem Ort: Inhalt links, Verteilen, Termine, ToDos und Vorlagen rechts.</p>
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h3>📝 Aufgabe <span class="pill"><?= e($typeLabels[$task->type] ?? $task->type) ?> · Jgst. <?= (int) $task->level ?></span></h3>
        <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required value="<?= e($task->title) ?>"></label>
            <div class="two-col">
                <label class="field"><span>Typ</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue) ?>"<?= $task->type === $typeValue ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Jahrgangsstufe</span>
                    <select name="level" data-topic-level-source="1">
                        <?php foreach (valid_levels() as $level): ?>
                        <option value="<?= $level ?>"<?= (int) $task->level === $level ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="field"><span>Themengebiet (nach LehrplanPLUS, passend zur Stufe)</span>
                <select name="topic_id" data-topic-level-filter="1">
                    <option value="0">ohne Themengebiet</option>
                    <?php foreach ($topics as $topic): ?>
                    <option value="<?= (int) $topic->id ?>" data-topic-level="<?= (int) $topic->level ?>"<?= (int) $task->topicId === (int) $topic->id ? ' selected data-topic-any="1"' : '' ?>><?= e($topic->name) ?> (Jgst. <?= (int) $topic->level ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Oder neues Themengebiet anlegen (überschreibt die Auswahl)</span>
                <input type="text" name="new_topic" placeholder="z. B. Schleifen üben">
            </label>
            <div class="field"><span>Beschreibung (mit Formatierungsleiste, Schülerinnen und Schüler können sie ein- und ausblenden)</span>
                <div data-wysiwyg data-initial-html="<?= $task->descriptionFormat === 'html' ? '1' : '0' ?>">
                    <div class="wysiwyg-toolbar">
                        <button type="button" data-cmd="bold" title="Fett"><b>B</b></button>
                        <button type="button" data-cmd="italic" title="Kursiv"><i>K</i></button>
                        <button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>
                        <button type="button" data-cmd="formatblock" data-arg="h3" title="Überschrift">H</button>
                        <button type="button" data-cmd="insertunorderedlist" title="Liste">•</button>
                        <button type="button" data-cmd="insertorderedlist" title="Nummerierte Liste">1.</button>
                        <button type="button" data-cmd="formatblock" data-arg="pre" title="Code-Block">{ }</button>
                        <button type="button" data-cmd="createlink" title="Link einfügen">🔗</button>
                        <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">✗</button>
                    </div>
                    <div class="wysiwyg-view" contenteditable="true" data-placeholder="Was ist zu tun?"></div>
                    <input type="hidden" name="description_html" value="0">
                    <textarea name="description" class="wysiwyg-area" hidden><?= e($task->description) ?></textarea>
                </div>
            </div>
            <label class="field"><span>Musterlösung (optional, dient der Korrektur und der KI als Vergleich)</span><textarea name="sample_solution" rows="3"><?= e($task->sampleSolution) ?></textarea></label>
            <label class="field"><span>Erwartungshorizont (optional, wird dir in der Korrektur und der KI als Bewertungshilfe mitgegeben)</span><textarea name="rubric" rows="3"><?= e($task->rubric) ?></textarea></label>
            <div class="two-col">
                <label class="field"><span>Maximale Punktzahl (0 = keine Punktevergabe)</span>
                    <input type="number" name="max_points" min="0" max="1000" value="<?= (int) $task->maxPoints ?>">
                </label>
                <div class="field"><span>Frist-Sperre</span>
                    <label style="display:flex;gap:6px;align-items:center;font-weight:normal;"><input type="checkbox" name="late_lock" value="1"<?= $task->lateLock === 1 ? ' checked' : '' ?>> Nach dem Abgabetermin keine neuen Abgaben mehr annehmen</label>
                    <label style="display:flex;gap:6px;align-items:center;font-weight:normal;margin-top:6px;"><input type="checkbox" name="solution_visible" value="1"<?= $task->solutionVisible === 1 ? ' checked' : '' ?>> Musterlösung freigeben: Schülerinnen und Schüler sehen sie nach der Korrektur oder nach dem Abgabetermin</label>
                </div>
            </div>
            <p class="muted"><?= e($typeHints[$task->type] ?? $typeHints['allgemein']) ?></p>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
        </form>
        <div class="btn-row">
            <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $task->id . '/export') ?>" title="Aufgabe als Datei sichern oder an Kolleginnen und Kollegen weitergeben (inkl. ToDos, Vorlagen, Musterlösung)">⬇️</a>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/teilen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="shared" value="<?= $task->shared === 1 ? '0' : '1' ?>">
                <button class="btn btn-ghost" type="submit" title="<?= $task->shared === 1 ? 'Nicht mehr im gemeinsamen Pool teilen' : 'In den gemeinsamen Pool aller Lehrkräfte teilen' ?>"><?= $task->shared === 1 ? '🚫' : '🤝' ?></button>
            </form>
            <?php if ($task->type === 'code'): ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/variante') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                <?= csrf_field() ?>
                <select name="target" title="Zielsprache der Kopie: ist eine KI-Schnittstelle hinterlegt, wird die Musterlösung konvertiert">
                    <?php if ($defaultLanguage === 'java'): ?>
                    <option value="java">☕ Java</option>
                    <option value="python">🐍 Python</option>
                    <?php else: ?>
                    <option value="python">🐍 Python</option>
                    <option value="java">☕ Java</option>
                    <?php endif; ?>
                </select>
                <button class="btn btn-ghost" type="submit" title="Aufgabe als Variante in der anderen Sprache duplizieren">🔁</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/loeschen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" title="Aufgabe löschen">🗑</button>
            </form>
            <a class="btn btn-ghost" href="<?= url('aufgaben') ?>" title="Zurück zum Aufgabenpool">←</a>
        </div>
    </section>
    <div>
        <section class="panel">
            <h3>📤 Verteilen &amp; Termine <?= $openGradings > 0 ? '<span class="pill pill-warn">✅ ' . (int) $openGradings . ' zu korrigieren</span>' : '' ?></h3>
            <?php if ($targets === []): ?>
            <p class="muted">Noch an niemanden ausgeteilt.</p>
            <?php else: ?>
            <?php foreach ($targets as $target): ?>
            <?php $hidden = $target['visibleFrom'] !== null && $target['visibleFrom'] > date('Y-m-d H:i'); ?>
            <?php $prog = $progress[$target['id']] ?? ['total' => 0, 'submitted' => 0]; ?>
            <div class="target-row">
                <span class="pill"><?= e($target['kind']) ?> <?= e($target['name']) ?><?= $hidden ? ' · ⏳ erscheint am ' . e($target['visibleFrom']) : '' ?><?= $target['dueAt'] !== null ? ' · 📅 ' . e($target['dueAt']) : '' ?> · ✍️ <?= (int) $prog['submitted'] ?>/<?= (int) $prog['total'] ?> abgegeben</span>
                <form method="post" action="<?= url('aufgaben/start') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="visible_from" value="<?= $target['visibleFrom'] !== null ? e(str_replace(' ', 'T', $target['visibleFrom'])) : '' ?>" title="Starttermin: ab wann sehen die Schülerinnen und Schüler diese Aufgabe?">
                    <button class="icon-btn" type="submit" title="Starttermin setzen oder mit leerem Feld streichen">🚀</button>
                </form>
                <form method="post" action="<?= url('aufgaben/termin') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="due_at" value="<?= $target['dueAt'] !== null ? e(str_replace(' ', 'T', $target['dueAt'])) : '' ?>" title="Abgabetermin (Datum und Uhrzeit)">
                    <button class="icon-btn" type="submit" title="Abgabetermin setzen oder streichen">📅</button>
                </form>
                <form method="post" action="<?= url('aufgaben/einziehen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <button class="icon-btn" type="submit" title="Zuweisung zurücknehmen, Abgaben bleiben erhalten">↩️</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if ($openGradings > 0 || $targets !== []): ?>
            <div class="btn-row"><a class="btn btn-primary" href="<?= url('aufgaben/' . (int) $task->id . '/korrektur') ?>" title="Abgaben ansehen und korrigieren">✅ Korrektur</a></div>
            <?php endif; ?>
            <?php endif; ?>
            <details class="dist-panel">
                <summary title="An Klasse oder einzelne Personen austeilen">📤 Austeilen</summary>
                <form method="post" action="<?= url('aufgaben/austeilen') ?>" class="form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="task_id" value="<?= (int) $task->id ?>">
                    <label class="field"><span>Ganze Klasse austeilten</span>
                        <select name="class_id">
                            <option value="0">- keine -</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="field"><span>oder einzelne Personen (mehrere gleichzeitig möglich)</span>
                        <div class="check-list">
                            <?php foreach ($studentsByClass as $classId => $students): ?>
                            <?php if ($students === []) { continue; } ?>
                            <div class="check-list-head">Klasse <?= e($classNames[$classId] ?? '') ?></div>
                            <?php foreach ($students as $student): ?>
                            <label class="check-item"><input type="checkbox" name="user_ids[]" value="<?= (int) $student->id ?>"> <?= e($student->fullName()) ?></label>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="two-col">
                        <label class="field"><span>Starttermin (optional, erscheint ab dann)</span>
                            <input type="datetime-local" name="visible_from">
                        </label>
                        <label class="field"><span>Abgabetermin (optional)</span>
                            <input type="datetime-local" name="due_at">
                        </label>
                    </div>
                    <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Austeilen">📤</button></div>
                </form>
            </details>
        </section>
        <section class="panel">
            <h3>📋 ToDos (Vorlage für Schülerinnen und Schüler)</h3>
            <?php if ($todos === []): ?>
            <p class="muted">Noch keine ToDos angelegt.</p>
            <?php else: ?>
            <ul class="todo-list">
                <?php foreach ($todos as $todo): ?>
                <li class="todo-item">
                    <span><?= e($todo->title) ?></span>
                    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/todos') ?>" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_todo_id" value="<?= (int) $todo->id ?>">
                        <button class="icon-btn" type="submit" title="ToDo löschen">🗑</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/todos') ?>" class="form">
                <?= csrf_field() ?>
                <div class="form-inline" style="display:flex;gap:6px;">
                    <input type="text" name="todo_title" required placeholder="Neues ToDo, z. B. Klasse Auto mit Attributen anlegen" style="flex:1;">
                    <button class="btn btn-ghost" type="submit" title="ToDo hinzufügen, alle Empfänger sehen es ab jetzt">➕</button>
                </div>
            </form>
        </section>
        <section class="panel">
            <h3>📎 Materialien (Vorlagen für die Schülerinnen und Schüler)</h3>
            <?php if ($materials === []): ?>
            <p class="muted">Noch keine Materialien ausgeteilt, z. B. Auto.java, index.html oder ein SQL-Schema.</p>
            <?php else: ?>
            <ul class="material-list">
                <?php foreach ($materials as $material): ?>
                <li class="material-item">
                    <a href="<?= url('aufgaben/' . (int) $task->id . '/material/' . (int) $material->id) ?>">📄 <?= e($material->filename) ?></a>
                    <span class="muted">(<?= number_format($material->size / 1024, 1, ',', '') ?> KiB)</span>
                    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/material') ?>" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_material_id" value="<?= (int) $material->id ?>">
                        <button class="icon-btn" type="submit" title="Material löschen">🗑</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/material') ?>" enctype="multipart/form-data" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Datei hochladen (max. 512 KiB)</span><input type="file" name="file"></label>
                <label class="field"><span>oder als Text anlegen (z. B. SQL-Schema oder Java-Quellcode)</span>
                    <input type="text" name="filename" placeholder="Dateiname, z. B. Auto.java">
                    <textarea name="material_content" rows="4" placeholder="Inhalt der Vorlage" style="margin-top:6px;"></textarea>
                </label>
                <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Material hinzufügen und zum Austeilen bereitstellen">➕</button></div>
            </form>
        </section>
    </div>
</div>

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
