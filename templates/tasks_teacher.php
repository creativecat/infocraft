<?php
/** @var list<\Infocraft\Models\Task> $tasks @var array<int, list<array{id:int,kind:string,name:string,dueAt:?string,visibleFrom:?string,assignedAt:string,classId:?int,userId:?int}>> $targets @var \Infocraft\Models\SchoolClass[] $classes @var array<int, list<\Infocraft\Models\User>> $studentsByClass @var \Infocraft\Models\TaskTopic[] $topics @var \Infocraft\Models\TaskTopic[] $allTopics */
$classNames = [];
foreach ($classes as $class) {
    $classNames[$class->id] = $class->name;
}
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
$topicNames = [];
foreach ($allTopics as $topic) {
    $topicNames[$topic->id] = $topic->name;
}
?>
<section class="hero">
    <div class="hero-emoji">📝</div>
    <h1>Aufgabenpool</h1>
    <p>Aufgaben anlegen, mit Themengebiet und Vorlagen ausstatten und an Klassen oder einzelne Personen austeilen, mit Start- und Abgabetermin. Filtere nach Klasse und Themengebiet, um übersichtlich zu arbeiten. Eigene Aufgaben in den gemeinsamen Pool aller Lehrkräfte teilen und geteilte als Kopie übernehmen.</p>
</section>

<section class="panel">
    <h3>🔎 Filter (gilt für deine Aufgaben, den gemeinsamen Pool und den Katalog)</h3>
    <form method="get" action="<?= url('aufgaben') ?>" class="form">
        <div class="filter-grid">
            <label class="field"><span>Klasse (steuert die Themengebiete ihrer Stufe)</span>
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
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit">Filtern</button>
            <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">Zurücksetzen</a>
        </div>
    </form>
    <p class="muted">Die Themengebiete orientieren sich am LehrplanPLUS Informatik Bayern: Informatik ist am Gymnasium ein Pflichtfach der Jahrgangsstufen 8 bis 10 im naturwissenschaftlich-technologischen Profil (davor Pluskurse bzw. Arbeitsgemeinschaften, z. B. HTML in der 6. Klasse), an der Mittelschule Pflichtfach der Jahrgangsstufen 5 bis 10, an der Realschule Wahlpflichtfach ab Jahrgangsstufe 7.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Aufgabe anlegen</h3>
        <form method="post" action="<?= url('aufgaben/anlegen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. JOINs üben"></label>
            <div class="two-col">
                <label class="field"><span>Typ</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue) ?>"><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Jahrgangsstufe</span>
                    <select name="level" id="task-create-level" data-topic-level-source="1">
                        <?php for ($level = 6; $level <= 13; $level++): ?>
                        <option value="<?= $level ?>"<?= $level === 9 ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <label class="field"><span>Themengebiet (nach LehrplanPLUS, passend zur Stufe)</span>
                <select name="topic_id" data-topic-level-filter="1">
                    <option value="0">ohne Themengebiet</option>
                    <?php foreach ($allTopics as $topic): ?>
                    <option value="<?= (int) $topic->id ?>" data-topic-level="<?= (int) $topic->level ?>"><?= e($topic->name) ?> (Jgst. <?= (int) $topic->level ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Oder neues Themengebiet anlegen (überschreibt die Auswahl)</span>
                <input type="text" name="new_topic" placeholder="z. B. Roboter programmieren">
            </label>
            <div class="field"><span>Beschreibung (mit Formatierungsleiste, Schülerinnen und Schüler können sie ein- und ausblenden)</span>
                <div data-wysiwyg data-initial-html="0">
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
                    <textarea name="description" class="wysiwyg-area" hidden></textarea>
                </div>
            </div>
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
            <div class="btn-row"><button class="btn btn-primary" type="submit">Anlegen</button></div>
        </form>
        <p class="muted">📎 Vorlagen (Java-Starterdatei, HTML-Gerüst, SQL-Schema) und 📋 ToDos pflegst du nach dem Anlegen über „Bearbeiten".</p>
        <p class="muted">🤖 KI-Unterstützung: <?= $aiConfigured
            ? 'Schnittstelle hinterlegt, in der Korrekturansicht kannst du KI-Vorschläge anfordern.'
            : 'Noch keine Schnittstelle hinterlegt. <a href="' . url('aufgaben/ki') . '">Jetzt einrichten</a> (je Lehrkraft oder schulweit).' ?></p>
    </section>
    <section class="panel">
        <h3>📚 Meine Aufgaben<?= count($tasks) > 0 ? ' (' . count($tasks) . ')' : '' ?></h3>
        <?php if ($tasks === []): ?>
        <p class="muted"><?= $filterClassId > 0 || $filterTopicId > 0 || $filterLevel > 0 || $filterType !== '' ? 'Keine Aufgaben für diesen Filter.' : 'Noch keine Aufgaben angelegt. Nimm links eine eigene auf oder unten aus dem Katalog.' ?></p>
        <?php endif; ?>
        <?php foreach ($tasks as $task): ?>
        <div class="mission">
            <div class="mission-head">
                <strong><?= e($task->title) ?></strong>
                <span class="pill"><?= e($typeLabels[$task->type] ?? $task->type) ?> · Jgst. <?= (int) $task->level ?><?= $task->topicId > 0 && isset($topicNames[$task->topicId]) ? ' · 📁 ' . e($topicNames[$task->topicId]) : '' ?><?= $task->shared === 1 ? ' · 🤝 im gemeinsamen Pool' : '' ?><?= $task->maxPoints > 0 ? ' · max. ' . (int) $task->maxPoints . ' Punkte' : '' ?><?= $task->lateLock === 1 ? ' · 🔒 Frist-Sperre' : '' ?></span>
            </div>
            <?php if ($task->description !== ''): ?>
            <div class="task-desc"><?= $task->descriptionHtml() ?></div>
            <?php endif; ?>
            <?php if ($task->variantOf > 0): ?>
            <p class="muted">🔁 Variante der Aufgabe #<?= (int) $task->variantOf ?></p>
            <?php endif; ?>
            <?php $taskTodos = $todoCounts[$task->id] ?? []; $taskMaterials = $materialCounts[$task->id] ?? []; ?>
            <p class="muted">Vorlagen: <?= count($taskTodos) ?> ToDos<?= count($taskMaterials) > 0 ? ' · ' . count($taskMaterials) . ' Materialie(n)' : '' ?></p>
            <?php if ($targets[$task->id] === []): ?>
            <p class="muted">Ausgeteilt an: niemanden</p>
            <?php else: ?>
            <p class="muted">Ausgeteilt an (Fortschritt = abgegeben/empfangen):</p>
            <?php foreach ($targets[$task->id] as $target): ?>
            <?php $hidden = $target['visibleFrom'] !== null && $target['visibleFrom'] > date('Y-m-d H:i'); ?>
            <?php $prog = $progress[$task->id][$target['id']] ?? ['total' => 0, 'submitted' => 0]; ?>
            <div class="form-inline" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:4px 0;">
                <span class="pill"><?= e($target['kind']) ?> <?= e($target['name']) ?><?= $hidden ? ' · ⏳ erscheint am ' . e($target['visibleFrom']) : '' ?> · ✍️ <?= (int) $prog['submitted'] ?>/<?= (int) $prog['total'] ?> abgegeben</span>
                <form method="post" action="<?= url('aufgaben/start') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="visible_from" value="<?= $target['visibleFrom'] !== null ? e(str_replace(' ', 'T', $target['visibleFrom'])) : '' ?>" title="Starttermin: ab wann sehen die Schülerinnen und Schüler diese Aufgabe?">
                    <button class="btn btn-ghost" type="submit" title="Starttermin setzen oder mit leerem Feld streichen">🚀 Start</button>
                </form>
                <form method="post" action="<?= url('aufgaben/termin') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <input type="datetime-local" name="due_at" value="<?= $target['dueAt'] !== null ? e(str_replace(' ', 'T', $target['dueAt'])) : '' ?>" title="Abgabetermin (Datum und Uhrzeit)">
                    <button class="btn btn-ghost" type="submit" title="Abgabetermin setzen oder streichen">📅 Termin</button>
                </form>
                <form method="post" action="<?= url('aufgaben/einziehen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                    <button class="btn btn-ghost" type="submit" title="Zuweisung zurücknehmen, Abgaben bleiben erhalten">↩️</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <details class="dist-panel">
                <summary>📤 Verteilen &amp; Termine</summary>
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
                    <div class="btn-row">
                        <button class="btn btn-ghost" type="submit">Austeilen</button>
                        <?php if ($targets[$task->id] !== []): ?>
                        <a class="btn btn-primary" href="<?= url('aufgaben/' . (int) $task->id . '/korrektur') ?>">✅ Korrektur (Abgaben ansehen)</a>
                        <?php endif; ?>
                    </div>
                </form>
            </details>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $task->id . '/bearbeiten') ?>">✏️ Bearbeiten (Thema, Vorlagen, ToDos)</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/teilen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="shared" value="<?= $task->shared === 1 ? '0' : '1' ?>">
                    <button class="btn btn-ghost" type="submit"><?= $task->shared === 1 ? '🚫 Nicht mehr teilen' : '🤝 In den gemeinsamen Pool teilen' ?></button>
                </form>
                <?php if ($task->type === 'code'): ?>
                <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/variante') ?>" class="form-inline" style="display:flex;gap:6px;align-items:center;">
                    <?= csrf_field() ?>
                    <select name="target" title="Zielsprache der Kopie">
                        <option value="python">als Python</option>
                        <option value="java">als Java</option>
                    </select>
                    <button class="btn btn-ghost" type="submit" title="Aufgabe als Java- bzw. Python-Variante duplizieren; ist eine KI-Schnittstelle hinterlegt, wird die Musterlösung konvertiert">🔁 Variante anlegen</button>
                </form>
                <?php endif; ?>
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
    <p class="muted">Kuratierte Aufgaben passend zum LehrplanPLUS, mit Themengebiet. Übernehme einzelne Aufgaben als Kopie in deinen Pool oder mit einem Klick alle einer Jahrgangsstufe.</p>
    <form method="post" action="<?= url('aufgaben/katalog/stufe') ?>" class="form">
        <?= csrf_field() ?>
        <div class="two-col">
            <label class="field"><span>Alle einer Jahrgangsstufe auf einmal übernehmen (überspringt Aufgaben, die du schon hast)</span>
                <select name="level">
                    <?php for ($level = 6; $level <= 13; $level++): ?>
                    <option value="<?= $level ?>"<?= $filterLevel === $level ? ' selected' : '' ?>>Jgst. <?= $level ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <div class="btn-row" style="align-items:flex-end;"><button class="btn btn-primary" type="submit">Stufe übernehmen</button></div>
        </div>
    </form>
    <?php if ($catalog === []): ?>
    <p class="muted">Keine Katalog-Aufgaben für diesen Filter.</p>
    <?php endif; ?>
    <?php foreach ($catalog as $entry): ?>
    <?php $owned = in_array(mb_strtolower(trim($entry['title'])), $ownedTitles, true); ?>
    <div class="mission">
        <div class="mission-head">
            <strong><?= e($entry['title']) ?></strong>
            <span class="pill"><?= e($typeLabels[$entry['type']] ?? $entry['type']) ?> · Jgst. <?= (int) $entry['level'] ?><?= ($entry['topic'] ?? '') !== '' ? ' · 📁 ' . e($entry['topic']) : '' ?></span>
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

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
