<?php
/** @var \Infocraft\Models\Task $task */
/** @var list<array{student: \Infocraft\Models\User, submission: array{content: string, submittedAt: string, firstName: string, lastName: string}|null, grading: array{grade: string, comment: string, source: string, checked: bool}|null, dueAt: string|null, late: bool}> $rows */
?>
<section class="hero">
    <div class="hero-emoji">✅</div>
    <h1>Korrektur: <?= e($task->title) ?></h1>
    <p>Alle Abgaben dieser Aufgabe auf einen Blick. Trage Note und Kommentar ein, die Schülerinnen und Schüler sehen beides. KI-Vorschläge müssen von dir übernommen werden, bevor sie zählen.</p>
</section>

<section class="panel">
    <div class="panel-head">
        <h3><span class="pill"><?= e($task->type) ?> · Jgst. <?= (int) $task->level ?><?= $task->maxPoints > 0 ? ' · max. ' . (int) $task->maxPoints . ' Punkte' : '' ?><?= $task->lateLock === 1 ? ' · 🔒 Frist-Sperre' : '' ?></span> <?= e($task->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">← Zurück zum Aufgabenpool</a>
    </div>
    <?php if ($task->description !== ''): ?><p class="muted"><?= e($task->description) ?></p><?php endif; ?>
    <div class="btn-row">
        <?php if ($aiConfigured): ?>
        <form method="post" action="<?= url('aufgaben/' . (int) $task->id . '/ki') ?>" class="form-inline">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">🤖 KI-Erstbewertung für alle offenen Abgaben (anonymisiert)</button>
        </form>
        <?php else: ?>
        <a class="btn btn-ghost" href="<?= url('aufgaben/ki') ?>">🤖 KI-Schnittstelle einrichten, dann kannst du hier KI-Vorschläge anfordern</a>
        <?php endif; ?>
    </div>
    <?php if ($rows === []): ?>
    <p class="muted">Diese Aufgabe ist noch an niemanden ausgeteilt.</p>
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
        <p class="muted">Abgegeben: <?= e($row['submission']['submittedAt']) ?><?= $row['dueAt'] !== null ? ' · Abgabetermin: ' . e($row['dueAt']) . ' Uhr' : '' ?></p>
        <div class="own"><?= e($row['submission']['content']) ?></div>
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
    <div class="btn-row"><button class="btn btn-primary" type="submit">Korrektur speichern (zählt als übernommen)</button></div>
    <?php endif; ?>
</form>
