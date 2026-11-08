<?php
/**
 * Fortschrittsübersicht einer Klasse (Issue #8): eine Zeile je Schülerin bzw.
 * Schüler mit Quiz-Bestwerten, erledigten Aufgaben und zuletzt bearbeiteten
 * Projekten aus der Werkstatt.
 */
/** @var \Infocraft\Models\SchoolClass $class */
/** @var list<array{student: \Infocraft\Models\User, quizzes: list<array{title: string, best: ?string, tries: int}>, quizDone: int, tasks: list<array{title: string, type: string, done: bool, submittedAt: ?string}>, tasksDone: int, projects: list<array{title: string, type: string, language: string, updatedAt: string}>}> $rows */
?>
<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('unterricht/klassen') ?>" title="Zurück zu meinen Klassen">←</a>
        <h3>📊 Fortschritt: Klasse <?= e($class->name) ?></h3>
    </div>
    <p class="muted">Eine Zeile je Schülerin und Schüler: Quiz-Bestwerte, erledigte Aufgaben und die zuletzt bearbeiteten Projekte aus der Werkstatt.</p>
    <?php if ($rows === []): ?>
    <p class="muted">In dieser Klasse gibt es noch keine Schülerinnen und Schüler.</p>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
    <?php $student = $row['student']; ?>
    <div class="target-row" style="flex-wrap:wrap;gap:10px;">
        <span class="pill"><strong><?= e($student->fullName()) ?></strong>
            · 🧠 Quiz: <?= (int) $row['quizDone'] ?>/<?= count($row['quizzes']) ?> gespielt
            · 📝 Aufgaben: <?= (int) $row['tasksDone'] ?>/<?= count($row['tasks']) ?> abgegeben
        </span>
        <span class="muted" style="max-width:100%;">
            <?php if ($row['quizzes'] !== []): ?>
            🧠 <?php foreach ($row['quizzes'] as $quizRow): ?><?= e($quizRow['title']) ?>: <strong><?= $quizRow['best'] !== null ? e($quizRow['best']) . ' (' . (int) $quizRow['tries'] . ' Versuche)' : 'noch nicht gespielt' ?></strong> · <?php endforeach; ?>
            <?php else: ?>🧠 noch keine Quizze ausgeteilt · <?php endif; ?>
            <?php if ($row['tasks'] !== []): ?>
            📝 <?php foreach ($row['tasks'] as $taskRow): ?><?= $taskRow['done'] ? '✅' : '⬜' ?> <?= e($taskRow['title']) ?><?= $taskRow['submittedAt'] !== null ? ' (' . e(mb_substr($taskRow['submittedAt'], 0, 16)) . ')' : '' ?> · <?php endforeach; ?>
            <?php else: ?>📝 noch keine Aufgaben ausgeteilt · <?php endif; ?>
            <?php if ($row['projects'] !== []): ?>
            💻 zuletzt: <?php foreach ($row['projects'] as $projectRow): ?><?= e($projectRow['title']) ?> (<?= e($projectRow['type']) ?><?= $projectRow['language'] !== '' ? '/' . e($projectRow['language']) : '' ?>, <?= e(mb_substr($projectRow['updatedAt'], 0, 16)) ?>) · <?php endforeach; ?>
            <?php else: ?>💻 noch keine Projekte in der Werkstatt<?php endif; ?>
        </span>
    </div>
    <?php endforeach; ?>
</section>
