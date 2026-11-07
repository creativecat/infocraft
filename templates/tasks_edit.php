<?php
/** @var \Infocraft\Models\Task $task @var \Infocraft\Models\TaskTopic[] $topics @var \Infocraft\Models\TaskMaterial[] $materials @var list<\Infocraft\Models\TaskTodo> $todos */
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
$typeHints = [
    'code' => '💡 Tipp für Java/Python-Aufgaben: Teile eine Starterdatei aus, z. B. Auto.java mit vorbereiteter Klasse oder ein Python-Grundgerüst. Über „Variante anlegen" im Aufgabenpool kannst du die Aufgabe später mit einem Klick als Java- oder Python-Variante duplizieren.',
    'html' => '💡 Tipp für HTML-Aufgaben: Teile ein index.html-Gerüst aus, in dem die Kinder nur die markierten Stellen ergänzen.',
    'sql' => '💡 Tipp für SQL-Aufgaben: Teile ein .sql-Schema aus, damit jede Aufgabe ihre eigene kleine Datenbank mitbringt (CREATE TABLE + INSERTs). Später kannst du Java-Aufgaben genauso eine SQL-Datenbank mitgeben.',
    'quiz' => '💡 Tipp für Quiz-Aufgaben: Formuliere die Fragen als ToDos, dann können die Schülerinnen und Schüler jede Frage einzeln abhaken.',
    'allgemein' => '💡 Tipp: Beschreibe die Aufgabe mit der Formatierungsleiste und lege ToDos an, die die Schülerinnen und Schüler abhaken können.',
];
?>
<section class="hero">
    <div class="hero-emoji">✏️</div>
    <h1>Aufgabe bearbeiten</h1>
    <p>Passe die Aufgabe an, ergänze Themengebiet, Beschreibung und Musterlösung. Stelle ToDos bereit, die deine Schülerinnen und Schüler abhaken können, und teile Vorlagen (Java-Starterdatei, HTML-Gerüst, SQL-Schema) als Material aus.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>📝 Aufgabe</h3>
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
                        <?php for ($level = 6; $level <= 13; $level++): ?>
                        <option value="<?= $level ?>"<?= (int) $task->level === $level ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                        <?php endfor; ?>
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
                        <button type="button" data-cmd="insertunorderedlist" title="Liste">• Liste</button>
                        <button type="button" data-cmd="insertorderedlist" title="Nummerierte Liste">1. Liste</button>
                        <button type="button" data-cmd="formatblock" data-arg="pre" title="Code-Block">Code</button>
                        <button type="button" data-cmd="createlink" title="Link einfügen">🔗 Link</button>
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
            <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
        </form>
        <p class="muted"><a href="<?= url('aufgaben') ?>">← zurück zum Aufgabenpool</a></p>
    </section>
    <div>
        <section class="panel">
            <h3>📋 ToDos (Vorlage für Schülerinnen und Schüler)</h3>
            <p class="muted">Diese Punkte sehen die Schülerinnen und Schüler in der Aufgabe und können sie abhaken. Sie können zusätzlich eigene ToDos ergänzen.</p>
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
                        <button class="btn btn-ghost" type="submit" title="ToDo löschen">🗑</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/todos') ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Neues ToDo</span><input type="text" name="todo_title" required placeholder="z. B. Klasse Auto mit Attributen anlegen"></label>
                <div class="btn-row"><button class="btn btn-ghost" type="submit">➕ ToDo hinzufügen</button></div>
            </form>
        </section>
        <section class="panel">
            <h3>📎 Materialien (Vorlagen für die Schülerinnen und Schüler)</h3>
            <p class="muted">z. B. eine Java-Starterdatei (Auto.java), ein HTML-Gerüst (index.html) oder ein SQL-Schema (aufgabe.sql) mit eigenen Tabellen für genau diese Aufgabe.</p>
            <?php if ($materials === []): ?>
            <p class="muted">Noch keine Materialien ausgeteilt.</p>
            <?php else: ?>
            <ul class="material-list">
                <?php foreach ($materials as $material): ?>
                <li class="material-item">
                    <a href="<?= url('aufgaben/' . (int) $task->id . '/material/' . (int) $material->id) ?>">📄 <?= e($material->filename) ?></a>
                    <span class="muted">(<?= number_format($material->size / 1024, 1, ',', '') ?> KiB)</span>
                    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/material') ?>" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_material_id" value="<?= (int) $material->id ?>">
                        <button class="btn btn-ghost" type="submit" title="Material löschen">🗑</button>
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
                <div class="btn-row"><button class="btn btn-ghost" type="submit">➕ Material hinzufügen</button></div>
            </form>
        </section>
    </div>
</div>

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
