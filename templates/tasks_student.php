<?php
/**
 * @var list<array{task: \Infocraft\Models\Task, className: ?string, teacherName: string, dueAt: string|null, submission: array{content: string, submittedAt: string}|null, grading: array{grade: string, comment: string, source: string, checked: bool}|null}> $tasks
 * @var string|null $nextDue @var array<int, \Infocraft\Models\TaskTopic> $topics
 * @var array<int, list<\Infocraft\Models\TaskTodo>> $todoTemplates @var array<int, list<\Infocraft\Models\TaskTodo>> $studentTodos
 * @var array<int, list<\Infocraft\Models\TaskMaterial>> $materials
 */
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
$statusLabels = ['offen' => 'offen', 'abgegeben' => 'abgegeben', 'korrigiert' => 'korrigiert', 'ueberfaellig' => 'überfällig'];
?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Meine Aufgaben</h1>
    <p>Alle Aufgaben, die deine Lehrkraft für dich bereitgestellt hat, mit Abgabetermin, ToDos und Vorlagen. Hake ToDos ab, wenn du fertig bist, und lade dir die Materialien zur Aufgabe herunter. Andere Klassenstufen bleiben unsichtbar.</p>
</section>

<?php if ($nextDue !== null): ?>
<div class="notice">
    <strong>⏰ Nächste Abgabe: <?= e($nextDue) ?> Uhr</strong>
</div>
<?php endif; ?>

<section class="panel">
    <h3>🔎 Meine Aufgaben filtern</h3>
    <form method="get" action="<?= url('aufgaben') ?>" class="form">
        <div class="filter-grid">
            <label class="field"><span>Themengebiet</span>
                <select name="topic">
                    <option value="0">alle</option>
                    <?php foreach ($topics as $topic): ?>
                    <option value="<?= (int) $topic->id ?>"<?= $filterTopicId === $topic->id ? ' selected' : '' ?>><?= e($topic->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Typ</span>
                <select name="type">
                    <option value=""<?= $filterType === '' ? ' selected' : '' ?>>alle</option>
                    <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                    <option value="<?= e($typeValue) ?>"<?= $filterType === $typeValue ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Status</span>
                <select name="status">
                    <option value=""<?= $filterStatus === '' ? ' selected' : '' ?>>alle</option>
                    <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                    <option value="<?= e($statusValue) ?>"<?= $filterStatus === $statusValue ? ' selected' : '' ?>><?= e($statusLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit">Filtern</button>
            <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">Zurücksetzen</a>
        </div>
    </form>
</section>

<?php if ($tasks === []): ?>
<div class="notice">
    <strong>Alles erledigt</strong>
    <p><?= $filterTopicId > 0 || $filterType !== '' || $filterStatus !== '' ? 'Für diesen Filter gibt es keine Aufgaben. Setze den Filter zurück, um alle zu sehen.' : 'Derzeit sind keine Aufgaben für dich ausgestellt.' ?></p>
</div>
<?php endif; ?>
<?php foreach ($tasks as $entry): ?>
<?php
$task = $entry['task'];
$gradingVisible = $entry['grading'] !== null && ($entry['grading']['source'] === 'lk' || $entry['grading']['checked']);
$late = $entry['dueAt'] !== null && date('Y-m-d H:i') > $entry['dueAt'];
$submissionClosed = $late && $task->lateLock === 1;
$submitted = $entry['submission'] !== null;
$topic = $task->topicId > 0 && isset($topics[$task->topicId]) ? $topics[$task->topicId] : null;
$templates = $todoTemplates[$task->id] ?? [];
$myTodos = $studentTodos[$task->id] ?? [];
$myDone = [];
foreach ($myTodos as $myTodo) {
    if ($myTodo->templateId !== null) {
        $myDone[$myTodo->templateId] = $myTodo->done === 1;
    }
}
$ownTodos = array_values(array_filter($myTodos, static fn ($t) => $t->templateId === null));
$taskMaterials = $materials[$task->id] ?? [];
?>
<section class="panel">
    <div class="panel-head">
        <h3><?= e($task->title) ?></h3>
        <span class="pill">
            <?php if ($gradingVisible): ?>✅ korrigiert
            <?php elseif ($submitted): ?>✍️ abgegeben
            <?php elseif ($late): ?>⏰ überfällig
            <?php else: ?>🔵 offen<?php endif; ?>
            · <?= e($typeLabels[$task->type] ?? $task->type) ?><?= $topic !== null ? ' · 📁 ' . e($topic->name) : '' ?>
        </span>
    </div>
    <p class="muted">von <?= e($entry['teacherName']) ?><?= $entry['className'] !== null ? ' · Klasse ' . e($entry['className']) : '' ?><?= $task->maxPoints > 0 ? ' · max. ' . (int) $task->maxPoints . ' Punkte' : '' ?></p>
    <?php if ($entry['dueAt'] !== null): ?>
    <p class="muted">Abgabe bis <strong><?= e($entry['dueAt']) ?> Uhr</strong><?= $late && !$submissionClosed ? ' · <strong>Abgabetermin überschritten</strong>' : '' ?></p>
    <?php endif; ?>

    <?php if ($task->description !== ''): ?>
    <div class="btn-row">
        <button class="btn btn-ghost" type="button" data-desc-toggle="desc-<?= (int) $task->id ?>">Beschreibung verbergen</button>
    </div>
    <div class="task-desc" id="desc-<?= (int) $task->id ?>"><?= $task->descriptionHtml() ?></div>
    <?php endif; ?>

    <?php if ($taskMaterials !== []): ?>
    <p class="muted">📎 Materialien zum Herunterladen:</p>
    <ul class="material-list">
        <?php foreach ($taskMaterials as $material): ?>
        <li class="material-item"><a href="<?= url('aufgaben/' . (int) $task->id . '/material/' . (int) $material->id) ?>">📄 <?= e($material->filename) ?></a> <span class="muted">(<?= number_format($material->size / 1024, 1, ',', '') ?> KiB)</span></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($templates !== [] || $ownTodos !== []): ?>
    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/todos/schueler') ?>" class="form">
        <?= csrf_field() ?>
        <p class="muted">📋 ToDos (abhaken, wenn du fertig bist):</p>
        <ul class="todo-list">
            <?php foreach ($templates as $template): ?>
            <li class="todo-item">
                <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                    <input type="checkbox" onchange="this.form.submit()" name="toggle_template_id" value="<?= (int) $template->id ?>"<?= isset($myDone[$template->id]) && $myDone[$template->id] ? ' checked' : '' ?>>
                    <span<?= isset($myDone[$template->id]) && $myDone[$template->id] ? ' style="text-decoration:line-through;"' : '' ?>><?= e($template->title) ?></span>
                </label>
            </li>
            <?php endforeach; ?>
            <?php foreach ($ownTodos as $ownTodo): ?>
            <li class="todo-item">
                <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                    <input type="checkbox" onchange="this.form.submit()" name="toggle_todo_id" value="<?= (int) $ownTodo->id ?>"<?= $ownTodo->done === 1 ? ' checked' : '' ?>>
                    <span<?= $ownTodo->done === 1 ? ' style="text-decoration:line-through;"' : '' ?>><?= e($ownTodo->title) ?></span>
                </label>
                <button class="btn btn-ghost" type="submit" name="delete_todo_id" value="<?= (int) $ownTodo->id ?>" title="ToDo löschen">🗑</button>
            </li>
            <?php endforeach; ?>
        </ul>
        <label class="field"><span>Eigenes ToDo ergänzen</span><input type="text" name="todo_title" placeholder="z. B. Nochmal die Schleife üben"></label>
        <div class="btn-row"><button class="btn btn-ghost" type="submit">➕ ToDo hinzufügen</button></div>
    </form>
    <?php endif; ?>

    <?php if ($gradingVisible && $entry['grading'] !== null): ?>
    <div class="notice">
        <strong>Korrigiert: Note <?= e($entry['grading']['grade'] !== '' ? $entry['grading']['grade'] : '-') ?><?= $task->maxPoints > 0 ? ' (max. ' . (int) $task->maxPoints . ' Punkte)' : '' ?></strong>
        <?php if ($entry['grading']['comment'] !== ''): ?><p><?= e($entry['grading']['comment']) ?></p><?php endif; ?>
    </div>
    <p class="muted">Deine abgegebene Lösung (nach der Korrektur nicht mehr änderbar):</p>
    <div class="own"><?= e($entry['submission'] !== null ? $entry['submission']['content'] : '(keine Abgabe)') ?></div>
    <?php else: ?>
    <?php if ($submissionClosed): ?>
    <div class="notice">
        <strong>Abgabe geschlossen</strong>
        <p>Der Abgabetermin (<?= e($entry['dueAt']) ?> Uhr) ist abgelaufen, diese Aufgabe nimmt keine Abgaben mehr an.</p>
    </div>
    <p class="muted">Deine zuletzt abgegebene Lösung bleibt gespeichert:</p>
    <div class="own"><?= e($entry['submission'] !== null ? $entry['submission']['content'] : '(keine Abgabe)') ?></div>
    <?php else: ?>
    <?php if ($submitted): ?>
    <p class="muted">Abgegeben am <?= e($entry['submission']['submittedAt']) ?>. Du kannst deine Lösung verbessern und erneut abgeben<?= $entry['dueAt'] !== null ? ' (Abgabetermin: ' . e($entry['dueAt']) . ' Uhr, danach zählt sie als verspätet)' : '' ?>.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/abgeben') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Deine Lösung (Text)</span>
            <textarea name="content" rows="5" placeholder="Schreibe hier deine Lösung hinein ..."><?= $entry['submission'] !== null ? e($entry['submission']['content']) : '' ?></textarea>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit"><?= $submitted ? 'Neue Version abgeben' : 'Abgeben' ?></button></div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
