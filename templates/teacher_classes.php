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
            <button class="btn btn-primary" type="submit" title="OTP für die ganze Klasse">🔑</button>
        </form>
    </div>
    <div class="diagram-toggle-row">
        <span>📐 Klassendiagramme automatisch erzeugen: <strong><?= $entry['class']->diagramAuto === 1 ? 'erlaubt' : 'deaktiviert' ?></strong></span>
        <form method="post" action="<?= url('unterricht/klassen/diagramm') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= (int) $entry['class']->id ?>">
            <input type="hidden" name="enabled" value="<?= $entry['class']->diagramAuto === 1 ? '0' : '1' ?>">
            <button class="btn btn-ghost" type="submit" title="Klassendiagramme automatisch erzeugen: <?= $entry['class']->diagramAuto === 1 ? 'derzeit erlaubt, klicken zum Deaktivieren' : 'derzeit deaktiviert, klicken zum Erlauben' ?>"> <?= $entry['class']->diagramAuto === 1 ? '🚫' : '✅' ?> </button>
        </form>
    </div>
    <div class="diagram-toggle-row">
        <span>💾 Fenster Mini-Datenbank im Code-Bereich: <strong><?= $entry['class']->dbPanel === 1 ? 'sichtbar' : 'deaktiviert' ?></strong></span>
        <form method="post" action="<?= url('unterricht/klassen/datenbank') ?>" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= (int) $entry['class']->id ?>">
            <input type="hidden" name="enabled" value="<?= $entry['class']->dbPanel === 1 ? '0' : '1' ?>">
            <button class="btn btn-ghost" type="submit" title="Fenster Mini-Datenbank: <?= $entry['class']->dbPanel === 1 ? 'derzeit sichtbar, klicken zum Deaktivieren' : 'derzeit deaktiviert, klicken zum Sichtbarmachen' ?>"> <?= $entry['class']->dbPanel === 1 ? '🚫' : '✅' ?> </button>
        </form>
    </div>
    <p class="muted">Das Fenster Mini-Datenbank steht in der Code-Werkstatt ganz unten: SQL direkt im Java-Programm. Deaktiviert es die Lehrkraft, verschwindet das Fenster für diese Klasse, die Klasse <code>Database</code> im Programm bleibt aber erreichbar.</p>
    <p class="muted">Schülerinnen und Schüler zeichnen Klassendiagramme immer selbst. Hier stellst du nur ein, ob Diagramme automatisch aus Java-, Python- und SQL-Skripten erzeugt werden dürfen.</p>
    <h4>🧩 Arbeitsbereiche für diese Klasse</h4>
    <p class="muted">Standard: die Jahrgangsstufe im LehrplanPLUS entscheidet. <strong>Freigeben</strong> macht einen Bereich früher nutzbar, <strong>sperren</strong> blendet ihn für die Klasse aus. Austeilungen bleiben auch bei gesperrtem Bereich gespeichert.</p>
    <table class="table">
        <thead><tr><th>Arbeitsbereich</th><th>Ab Stufe</th><th>Einstellung</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($modules as $mKey => $m): ?>
        <?php $mMode = $entry['moduleModes'][$mKey] ?? 'standard'; ?>
        <tr>
            <td><?= $m['icon'] ?> <?= e($m['title']) ?></td>
            <td><?= (int) $m['level'] ?></td>
            <td>
                <form method="post" action="<?= url('unterricht/klassen/modul') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="class_id" value="<?= (int) $entry['class']->id ?>">
                    <input type="hidden" name="module" value="<?= e($mKey) ?>">
                    <select name="mode">
                        <option value="standard"<?= $mMode === 'standard' ? ' selected' : '' ?>>Standard (Lehrplan-Stufe)</option>
                        <option value="freigeben"<?= $mMode === 'freigeben' ? ' selected' : '' ?>>Freigeben (früher nutzbar)</option>
                        <option value="sperren"<?= $mMode === 'sperren' ? ' selected' : '' ?>>Sperren (ausgeblendet)</option>
                    </select>
                    <button class="btn btn-ghost" type="submit" title="Speichern">💾</button>
                </form>
            </td>
            <td class="muted"><?= $mMode === 'freigeben' ? 'freigegeben' : ($mMode === 'sperren' ? 'gesperrt' : 'Standard') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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
                    <button class="btn btn-ghost" type="submit" title="Neues OTP">🔑</button>
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
        <button class="btn btn-primary" type="submit" title="Zuordnen">🔗</button>
    </form>
</section>
<?php endif; ?>

