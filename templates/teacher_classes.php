<section class="hero">
    <div class="hero-emoji">🏫</div>
    <h1>Meine Klassen</h1>
    <p>Übersicht deiner Klassen. Für einzelne Schülerinnen und Schüler oder für eine ganze Klasse kannst du hier neue Einmalpasswörter (OTP) erzeugen.</p>
</section>

<?php if ($byClass === []): ?>
<div class="notice">
    <strong>Noch keine Klassen</strong>
    <p>Dir sind noch keine Klassen zugeordnet. Die Administration ordnet Lehrkräfte Klassen zu (unten auf dieser Seite für Admins sichtbar).</p>
</div>
<?php endif; ?>

<?php foreach ($byClass as $entry): ?>
<section class="panel">
    <div class="panel-head">
        <h3>Klasse <?= e($entry['class']->name) ?> <span class="pill">Jahrgangsstufe <?= (int) $entry['class']->level() ?></span></h3>
        <form method="post" action="<?= url('unterricht/passwort') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= (int) $entry['class']->id ?>">
            <button class="btn btn-primary" type="submit">🔑 OTP für die ganze Klasse</button>
        </form>
    </div>
    <?php if ($entry['students'] === []): ?>
        <p class="muted">In dieser Klasse gibt es noch keine Schülerinnen und Schüler.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Name</th><th>Anmeldename</th><th>Aktion</th></tr></thead>
        <tbody>
        <?php foreach ($entry['students'] as $student): ?>
        <tr>
            <td><?= e($student->fullName()) ?></td>
            <td class="mono"><?= $student->loginName !== null ? e($student->loginName) : '<span class="muted">noch nicht vergeben</span>' ?></td>
            <td>
                <form method="post" action="<?= url('unterricht/passwort') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $student->id ?>">
                    <button class="btn btn-ghost" type="submit">🔑 Neues OTP</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if ($teachers !== [] && $allClasses !== []): ?>
<section class="panel">
    <h3>⚙️ Lehrkraft einer Klasse zuordnen (Administration)</h3>
    <form method="post" action="<?= url('unterricht/klasse/zuordnen') ?>" class="form form-row">
        <?= csrf_field() ?>
        <label class="field">
            <span>Lehrkraft</span>
            <select name="teacher_id">
                <?php foreach ($teachers as $teacher): ?>
                <option value="<?= (int) $teacher->id ?>"><?= e($teacher->fullName()) ?> (<?= e($teacher->roleLabel()) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Klasse</span>
            <select name="class_id">
                <?php foreach ($allClasses as $class): ?>
                <option value="<?= (int) $class->id ?>"><?= e($class->name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Zuordnen</button>
    </form>
</section>
<?php endif; ?>

