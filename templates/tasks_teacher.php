<?php
/** @var string $view @var list<\Infocraft\Models\Task> $tasks @var array<int, list<\Infocraft\Models\Task>> $groupedTasks @var array<int, list<array<string, mixed>>> $groupedCatalog @var array<int, list<array{id:int,kind:string,name:string,dueAt:?string,visibleFrom:?string,assignedAt:string,classId:?int,userId:?int}>> $targets @var \Infocraft\Models\SchoolClass[] $classes @var array<int, list<\Infocraft\Models\User>> $studentsByClass @var list<array<string, mixed>> $catalog @var list<array{task:\Infocraft\Models\Task,authorName:string}> $pool @var \Infocraft\Models\TaskTopic[] $topics @var \Infocraft\Models\TaskTopic[] $allTopics */
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
$topicNames = [];
foreach ($allTopics as $topic) {
    $topicNames[$topic->id] = $topic->name;
}
$viewLabels = ['meine' => '📋 Meine Aufgaben', 'katalog' => '📦 Aufgabenkatalog', 'pool' => '🤝 Gemeinsamer Pool'];
?>
<section class="hero hero-compact">
    <div class="hero-emoji">📝</div>
    <div class="hero-text">
        <h1>Aufgabenpool</h1>
        <p>Aufgaben verwalten, didaktisch nach Jahrgangsstufe und Themengebiet gegliedert. Anlegen, verteilen, korrigieren.</p>
    </div>
</section>

<nav class="subnav">
    <?php foreach ($viewLabels as $viewKey => $viewLabel): ?>
    <a href="<?= url('aufgaben?view=' . $viewKey) ?>" class="subnav-item<?= $view === $viewKey ? ' active' : '' ?>"><?= $viewLabel ?></a>
    <?php endforeach; ?>
    <a href="<?= url('aufgaben/neu') ?>" class="btn btn-primary subnav-new" title="Neue Aufgabe anlegen">➕</a>
</nav>

<section class="panel panel-compact">
    <form method="get" action="<?= url('aufgaben') ?>" class="form">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="filter-grid">
            <label class="field"><span>Klasse</span>
                <select name="class">
                    <option value="0">alle Klassen</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?= (int) $class->id ?>"<?= $filterClassId === $class->id ? ' selected' : '' ?>>Klasse <?= e($class->name) ?> (Jgst. <?= (int) $class->level() ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Themengebiet</span>
                <select name="topic">
                    <option value="0">alle Themengebiete</option>
                    <?php foreach ($topics as $topic): ?>
                    <option value="<?= (int) $topic->id ?>"<?= $filterTopicId === $topic->id ? ' selected' : '' ?>><?= e($topic->name) ?> (Jgst. <?= (int) $topic->level ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
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
                    <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                    <option value="<?= e($typeValue) ?>"<?= $filterType === $typeValue ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="field"><span>&nbsp;</span>
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit" title="Filter anwenden">🔎</button>
                    <a class="btn btn-ghost" href="<?= url('aufgaben?view=' . e($view)) ?>" title="Filter zurücksetzen">✕</a>
                </div>
            </div>
        </div>
    </form>
    <p class="muted">Filter wirkt auf die gewählte Ansicht. Themengebiete folgen dem LehrplanPLUS Informatik (Bayern).</p>
</section>

<?php if ($view === 'meine'): ?>
    <?php if (($upcoming ?? []) !== []): ?>
    <div class="notice">
        <strong>⏰ Nächste Abgabetermine</strong>
        <ul class="phase-list">
            <?php foreach ($upcoming as $item): ?>
            <li>📅 <?= e($item['dueAt']) ?> Uhr · <a href="<?= url('aufgaben/' . (int) $item['id'] . '/korrektur') ?>"><?= e($item['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($tasks === []): ?>
    <section class="panel">
        <p class="muted"><?= $filterClassId > 0 || $filterTopicId > 0 || $filterLevel > 0 || $filterType !== '' ? 'Keine Aufgaben für diesen Filter.' : 'Noch keine Aufgaben angelegt. Lege oben mit ➕ eine neue an oder übernimm fertige Aufgaben aus dem Aufgabenkatalog.' ?></p>
    </section>
    <?php endif; ?>

    <?php foreach ($groupedTasks as $level => $topicGroups): ?>
    <?php $levelCount = 0; foreach ($topicGroups as $groupTasks) { $levelCount += count($groupTasks); } ?>
    <section class="level-section">
        <h3 class="section-level">🎓 Jahrgangsstufe <?= (int) $level ?> <span class="pill"><?= (int) $levelCount ?></span></h3>
        <?php foreach ($topicGroups as $topicKey => $groupTasks): ?>
        <h4 class="section-topic"><?= $topicKey > 0 && isset($topicNames[$topicKey]) ? '📁 ' . e($topicNames[$topicKey]) : '🗂️ ohne Themengebiet' ?></h4>
        <div class="task-grid">
            <?php foreach ($groupTasks as $task): ?>
            <?php $taskTodos = $todoCounts[$task->id] ?? []; $taskMaterials = $materialCounts[$task->id] ?? []; ?>
            <?php $taskTotals = $totals[$task->id] ?? ['total' => 0, 'submitted' => 0]; ?>
            <div class="task-tile">
                <div class="task-tile-head">
                    <a class="task-tile-main" href="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>" title="<?= e($task->title) ?><?= $task->description !== '' ? ' - ' . e(strip_tags($task->descriptionHtml())) : '' ?>">
                        <strong><?= e($task->title) ?></strong>
                        <span class="task-tile-meta"><?= e($typeLabels[$task->type] ?? $task->type) ?> · Jgst. <?= (int) $task->level ?><?= $task->variantOf > 0 ? ' · 🔁 Variante' : '' ?></span>
                    </a>
                    <a class="icon-btn" href="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>" title="Bearbeiten: Thema, Vorlagen, ToDos, Verteilen">✏️</a>
                </div>
                <div class="task-tile-pills">
                    <?php if ($task->topicId > 0 && isset($topicNames[$task->topicId])): ?><span class="pill">📁 <?= e($topicNames[$task->topicId]) ?></span><?php endif; ?>
                    <?php if ($task->shared === 1): ?><span class="pill">🤝 im gemeinsamen Pool</span><?php endif; ?>
                    <?php if ($task->maxPoints > 0): ?><span class="pill">🎯 max. <?= (int) $task->maxPoints ?> P.</span><?php endif; ?>
                    <?php if ($task->lateLock === 1): ?><span class="pill" title="Frist-Sperre: nach dem Abgabetermin keine neuen Abgaben">🔒</span><?php endif; ?>
                    <?php if ($task->solutionVisible === 1): ?><span class="pill" title="Musterlösung freigegeben">💡</span><?php endif; ?>
                    <?php if (count($taskTodos) > 0): ?><span class="pill">📋 <?= count($taskTodos) ?></span><?php endif; ?>
                    <?php if (count($taskMaterials) > 0): ?><span class="pill">📎 <?= count($taskMaterials) ?></span><?php endif; ?>
                    <?php if ($taskTotals['total'] > 0): ?><span class="pill" title="Abgegeben / ausgeteilt">✍️ <?= (int) $taskTotals['submitted'] ?>/<?= (int) $taskTotals['total'] ?></span><?php endif; ?>
                    <?php if (($openGradings[$task->id] ?? 0) > 0): ?><span class="pill pill-warn">✅ <?= (int) $openGradings[$task->id] ?> zu korrigieren</span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <details class="panel">
        <summary>⬇️⬆️ Aufgabe exportieren / importieren</summary>
        <p class="muted">Export liefert eine Aufgabe als Datei (inklusive Musterlösung, Erwartungshorizont, Thema, ToDos und Materialien). Hier eine solche Datei als neue Kopie importieren, ideal zum Archivieren und zum Austausch mit Kolleginnen und Kollegen.</p>
        <form method="post" action="<?= url('aufgaben/import') ?>" enctype="multipart/form-data" class="form">
            <?= csrf_field() ?>
            <div class="two-col">
                <label class="field"><span>Datei auswählen (.json)</span><input type="file" name="import_file" accept=".json,application/json"></label>
                <label class="field"><span>...oder JSON direkt einfügen</span><textarea name="import_json" rows="3" placeholder='{"infocraft_task": 1, "title": "...", ...}'></textarea></label>
            </div>
            <div class="btn-row"><button class="btn btn-ghost" type="submit">⬆️ Importieren</button></div>
        </form>
    </details>
<?php endif; ?>

<?php if ($view === 'katalog'): ?>
    <section class="panel">
        <h3>📦 Aufgabenkatalog (fertige Aufgaben)</h3>
        <p class="muted">Kuratierte Aufgaben passend zum LehrplanPLUS, mit ToDos, Vorlagen, Beispieldatenbanken und Musterlösung. Übernimm einzelne Aufgaben als Kopie in deinen Pool oder mit einem Klick alle einer Jahrgangsstufe.</p>
        <form method="post" action="<?= url('aufgaben/katalog/stufe') ?>" class="form form-inline">
            <?= csrf_field() ?>
            <label class="field"><span>Alle einer Jahrgangsstufe auf einmal übernehmen</span>
                <select name="level">
                    <?php for ($level = 6; $level <= 13; $level++): ?>
                    <option value="<?= $level ?>"<?= $filterLevel === $level ? ' selected' : '' ?>>Jgst. <?= $level ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit" title="Ganze Stufe übernehmen">⬇️</button>
        </form>
    </section>

    <?php if ($catalog === []): ?>
    <section class="panel"><p class="muted">Keine Katalog-Aufgaben für diesen Filter.</p></section>
    <?php endif; ?>
    <?php foreach ($groupedCatalog as $level => $topicGroups): ?>
    <section class="level-section">
        <h3 class="section-level">🎓 Jahrgangsstufe <?= (int) $level ?> <span class="pill"><?= array_sum(array_map(static fn (array $g): int => count($g), $topicGroups)) ?></span></h3>
        <?php foreach ($topicGroups as $topicName => $entries): ?>
        <h4 class="section-topic">📁 <?= e((string) $topicName) ?></h4>
        <div class="task-grid">
            <?php foreach ($entries as $entry): ?>
            <?php $owned = in_array(mb_strtolower(trim($entry['title'])), $ownedTitles, true); ?>
            <div class="task-tile">
                <div class="task-tile-head">
                    <span class="task-tile-main">
                        <strong title="<?= e($entry['title']) ?>"><?= e($entry['title']) ?></strong>
                        <span class="task-tile-meta"><?= e($typeLabels[$entry['type']] ?? $entry['type']) ?> · Jgst. <?= (int) $entry['level'] ?></span>
                    </span>
                    <?php if ($owned): ?>
                    <span class="pill pill-ok" title="Bereits in deinem Pool">✓</span>
                    <?php else: ?>
                    <form method="post" action="<?= url('aufgaben/katalog') ?>" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="template_id" value="<?= (int) $entry['id'] ?>">
                        <button class="icon-btn" type="submit" title="In meinen Pool übernehmen (mit ToDos, Vorlagen und Musterlösung)">⬇️</button>
                    </form>
                    <?php endif; ?>
                </div>
                <p class="task-tile-desc"><?= e($entry['description']) ?></p>
                <div class="task-tile-pills">
                    <?php if (count($entry['todos'] ?? []) > 0): ?><span class="pill" title="ToDos für Schülerinnen und Schüler">📋 <?= count($entry['todos']) ?></span><?php endif; ?>
                    <?php if (($entry['solution'] ?? '') !== ''): ?><span class="pill" title="Musterlösung enthalten">💡</span><?php endif; ?>
                    <?php if (count($entry['files'] ?? []) > 0): ?><span class="pill" title="Vorlagen / Beispieldatenbank">📎 <?= count($entry['files']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($view === 'pool'): ?>
    <section class="panel">
        <h3>🤝 Gemeinsamer Pool (von anderen Lehrkräften geteilt)</h3>
        <p class="muted">Geteilte Aufgaben deiner Kolleginnen und Kollegen. Übernimm sie als Kopie in deinen eigenen Pool und passe sie individuell an, das Original bleibt unberührt.</p>
        <?php if ($pool === []): ?>
        <p class="muted"><?= $filterTopicId > 0 || $filterLevel > 0 || $filterType !== '' ? 'Keine geteilten Aufgaben für diesen Filter.' : 'Noch hat keine Lehrkraft eine Aufgabe geteilt (oder du hast alle geteilten Aufgaben bereits übernommen).' ?></p>
        <?php endif; ?>
        <?php foreach ($pool as $entry): ?>
        <?php $poolTask = $entry['task']; ?>
        <div class="mission">
            <div class="mission-head">
                <strong><?= e($poolTask->title) ?></strong>
                <span class="pill"><?= e($typeLabels[$poolTask->type] ?? $poolTask->type) ?> · Jgst. <?= (int) $poolTask->level ?><?= $poolTask->topicId > 0 && isset($topicNames[$poolTask->topicId]) ? ' · 📁 ' . e($topicNames[$poolTask->topicId]) : '' ?> · von <?= e($entry['authorName']) ?></span>
            </div>
            <?php if ($poolTask->description !== ''): ?>
            <div class="task-desc"><?= $poolTask->descriptionHtml() ?></div>
            <?php endif; ?>
            <?php if (in_array(mb_strtolower(trim($poolTask->title)), $ownedTitles, true)): ?>
            <p><span class="pill pill-ok">✓ bereits in deinem Pool</span></p>
            <?php else: ?>
            <form method="post" action="<?= url('aufgaben/pool/' . (int) $poolTask->id . '/uebernehmen') ?>" class="form-inline">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" title="Als Kopie in meinen Pool übernehmen">⬇️</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
