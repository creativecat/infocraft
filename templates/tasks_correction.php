<?php
/** @var \Infocraft\Models\Task $task */
/** @var array{total: int, submitted: int, late: int, graded: int, open: int, avgGrade: float|null} $stats */
/** @var list<array{student: \Infocraft\Models\User, submission: array{content: string, submittedAt: string, selfRating: int, firstName: string, lastName: string, projectId: int|null}|null, grading: array{grade: string, comment: string, source: string, checked: bool}|null, dueAt: string|null, late: bool, project: \Infocraft\Models\Project|null}> $rows */
$topic = $task->topicId > 0 ? \Infocraft\Models\TaskTopic::find($task->topicId) : null;
?>
<section class="hero">
    <div class="hero-emoji">✅</div>
    <h1>Korrektur: <?= e($task->title) ?></h1>
    <p>Alle Abgaben dieser Aufgabe auf einen Blick. Trage Note und Kommentar ein, die Schülerinnen und Schüler sehen beides. KI-Vorschläge müssen von dir übernommen werden, bevor sie zählen.</p>
</section>

<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('aufgaben') ?>" title="Zurück zum Aufgabenpool">←</a>
        <h3><span class="pill"><?= e($task->type) ?> · Jgst. <?= (int) $task->level ?><?= $topic !== null ? ' · 📁 ' . e($topic->name) : '' ?><?= $task->maxPoints > 0 ? ' · max. ' . (int) $task->maxPoints . ' Punkte' : '' ?><?= $task->lateLock === 1 ? ' · 🔒 Frist-Sperre' : '' ?></span> <?= e($task->title) ?></h3>
    </div>
    <?php if ($task->description !== ''): ?><div class="task-desc"><?= $task->descriptionHtml() ?></div><?php endif; ?>
    <div class="btn-row">
        <?php if ($aiConfigured): ?>
        <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/ki') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit" title="KI-Erstbewertung für alle offenen Abgaben (anonymisiert)">🤖</button>
        </form>
        <?php else: ?>
        <a class="btn btn-ghost" href="<?= url('aufgaben/ki') ?>">🤖 KI-Schnittstelle einrichten, dann kannst du hier KI-Vorschläge anfordern</a>
        <?php endif; ?>
    </div>
    <?php if ($rows === []): ?>
    <p class="muted">Diese Aufgabe ist noch an niemanden ausgeteilt.</p>
    <?php else: ?>
    <p>
        <span class="pill">👥 <?= (int) $stats['total'] ?> Schülerinnen und Schüler</span>
        <span class="pill">✍️ <?= (int) $stats['submitted'] ?> abgegeben (<?= $stats['total'] > 0 ? (int) round($stats['submitted'] * 100 / $stats['total']) : 0 ?> %)</span>
        <span class="pill">⏰ <?= (int) $stats['late'] ?> verspätet</span>
        <span class="pill">✅ <?= (int) $stats['graded'] ?> korrigiert<?= $stats['open'] > 0 ? ' · 🔁 ' . (int) $stats['open'] . ' noch offen' : '' ?></span>
        <?php if ($stats['avgGrade'] !== null): ?><span class="pill">📊 Notenschnitt: <?= e(number_format($stats['avgGrade'], 2, ',', '')) ?></span><?php endif; ?>
        <?php if ($task->solutionVisible === 1): ?><span class="pill">💡 Musterlösung für Schülerinnen und Schüler freigegeben</span><?php endif; ?>
    </p>
    <?php endif; ?>
</section>

<?php if ($sampleSolution !== ''): ?>
<section class="panel">
    <h3>Musterlösung (zum Vergleich)</h3>
    <div class="muster"><?= e($sampleSolution) ?></div>
</section>
<?php endif; ?>

<?php if ($task->rubric !== ''): ?>
<section class="panel">
    <h3>Erwartungshorizont (Bewertungskriterien, wird der KI mitgegeben)</h3>
    <div class="muster"><?= e($task->rubric) ?></div>
</section>
<?php endif; ?>

<form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/korrigieren') ?>" class="form">
    <?= csrf_field() ?>
    <?php foreach ($rows as $row): ?>
    <?php $student = $row['student']; ?>
    <section class="panel">
        <div class="panel-head">
            <h3><?= e($student->fullName()) ?></h3>
            <?php if ($row['grading'] !== null && $row['grading']['source'] === 'api' && !$row['grading']['checked']): ?>
            <span class="status status-in-arbeit">KI-Vorschlag, noch nicht übernommen</span>
            <?php elseif ($row['grading'] !== null): ?>
            <span class="badge-solved">korrigiert</span>
            <?php elseif ($row['submission'] === null): ?>
            <span class="pill">offen (noch keine Abgabe)</span>
            <?php elseif ($row['late']): ?>
            <span class="pill">verspätet abgegeben</span>
            <?php else: ?>
            <span class="pill">abgegeben</span>
            <?php endif; ?>
        </div>
        <?php if ($row['submission'] !== null): ?>
        <p class="muted">Abgegeben: <?= e($row['submission']['submittedAt']) ?><?= $row['dueAt'] !== null ? ' · Abgabetermin: ' . e($row['dueAt']) . ' Uhr' : '' ?><?= ($row['submission']['selfRating'] ?? 0) > 0 ? ' · Selbsteinschätzung: ' . e(\Infocraft\Models\TaskSubmission::SELF_RATING_LABELS[(int) $row['submission']['selfRating']] ?? '') : '' ?></p>
        <?php if (($row['project'] ?? null) !== null): ?>
        <p><a class="btn btn-ghost" href="<?= url('werkstatt/' . ($row['project']->type === 'html' ? 'html' : 'code') . '/' . (int) $row['project']->id) ?>" title="Projekt in der Werkstatt öffnen (Quelltext ansehen)">🔗 Projekt: <?= e($row['project']->title) ?></a></p>
        <?php if ($row['submission']['content'] !== ''): ?><div class="own"><?= e($row['submission']['content']) ?></div><?php endif; ?>
        <details>
            <summary>Quelltext des Projekts ansehen</summary>
            <div class="own" style="white-space:pre-wrap;max-height:40vh;overflow:auto;"><?= e($row['project']->content) ?></div>
            <?php foreach (\Infocraft\Models\ProjectFile::allForProject((int) $row['project']->id) as $projectFile): ?>
            <p class="muted" style="margin-bottom:2px;">📄 <?= e($projectFile->name) ?></p>
            <div class="own" style="white-space:pre-wrap;max-height:25vh;overflow:auto;"><?= e($projectFile->content) ?></div>
            <?php endforeach; ?>
        </details>
        <?php else: ?>
        <div class="own"><?= e($row['submission']['content']) ?></div>
        <?php endif; ?>
        <?php else: ?>
        <p class="muted">Noch keine Abgabe.<?= $row['dueAt'] !== null ? ' Abgabetermin: ' . e($row['dueAt']) . ' Uhr' : '' ?></p>
        <?php endif; ?>
        <div class="two-col" style="margin-top:12px;">
            <label class="field"><span>Note (frei, z. B. 1, 2-, 3+<?= $task->maxPoints > 0 ? '; Punkte als Teil der Note, z. B. "2- (8/' . (int) $task->maxPoints . ' Punkte)"' : '' ?>)</span>
                <input type="text" name="grade_<?= (int) $student->id ?>" maxlength="10" value="<?= $row['grading'] !== null ? e($row['grading']['grade']) : '' ?>" placeholder="Note">
            </label>
            <label class="field"><span>Feedback (erscheint für die Schülerin bzw. den Schüler)</span>
                <textarea name="comment_<?= (int) $student->id ?>" rows="2" maxlength="2000" placeholder="Was war gut, was fehlt?"><?= $row['grading'] !== null ? e($row['grading']['comment']) : '' ?></textarea>
            </label>
        </div>
    </section>
    <?php endforeach; ?>
    <?php if ($rows !== []): ?>
    <div class="btn-row"><button class="btn btn-primary" type="submit" title="Korrektur speichern (zählt als übernommen)">💾</button></div>
    <?php endif; ?>
</form>
