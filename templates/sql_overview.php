<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: SQL-Werkstatt: Übersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">🗄️</div>
    <h1>SQL-Werkstatt</h1>
    <p><?= $isTeacher ? 'Öffne die Beispieldatenbanken direkt oder verteile sie an deine Klassen, mit passenden Rechten: nur lesen, lesen und schreiben oder Tabellen selbst anlegen lassen.' : 'Hier findest du die Datenbanken, die deine Lehrkraft für dich freigeschaltet hat.' ?></p>
</section>

<?php if (!$isTeacher): ?>
<?php if ($grants === []): ?>
<div class="notice">
    <strong>Noch keine Datenbank</strong>
    <p>Deine Lehrkraft hat dir noch keine Datenbank zugeteilt. Sobald das passiert, erscheint sie hier.</p>
</div>
<?php else: ?>
<div class="cards">
    <?php foreach ($grants as $grant): ?>
    <a class="card" href="<?= url('werkstatt/sql/' . $grant['template']->slug) ?>">
        <div class="card-icon"><?= $grant['template']->isAdventure ? '🏝️' : '🗄️' ?></div>
        <h2><?= e($grant['template']->title) ?></h2>
        <p class="card-desc"><?= e($grant['template']->description) ?></p>
        <div class="card-meta"><span class="pill"><?= e($permissions[$grant['permission']] ?? $grant['permission']) ?></span></div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="cards">
    <?php foreach ($templates as $template): ?>
    <a class="card" href="<?= url('werkstatt/sql/' . $template->slug) ?>">
        <div class="card-icon"><?= $template->isAdventure ? '🏝️' : '🗄️' ?></div>
        <h2><?= e($template->title) ?></h2>
        <p class="card-desc"><?= e($template->description) ?></p>
        <div class="card-meta"><span class="pill">Vollzugriff</span></div>
    </a>
    <?php endforeach; ?>
</div>
<div class="two-col">
    <section class="panel">
        <h3>📤 Datenbank austeilen</h3>
        <form method="post" action="<?= url('werkstatt/sql/verteilen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span>Datenbank (Vorlage)</span>
                <select name="template_id">
                    <?php foreach ($templates as $template): ?>
                    <option value="<?= (int) $template->id ?>"><?= e($template->title) ?><?= $template->isAdventure ? ' (Adventure)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="two-col">
                <label class="field">
                    <span>Ganze Klasse</span>
                    <select name="class_id">
                        <option value="0">- keine -</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Einzelne Person</span>
                    <select name="user_id">
                        <option value="0">- keine -</option>
                        <?php foreach ($studentsByClass as $classId => $students): ?>
                        <optgroup label="Klasse <?= e($classNames[$classId] ?? '') ?>">
                            <?php foreach ($students as $student): ?>
                            <option value="<?= (int) $student->id ?>"><?= e($student->fullName()) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="field">
                <span>Rechte</span>
                <select name="permission">
                    <?php foreach ($permissions as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted">Nur lesen = alle arbeiten an derselben gemeinsamen Datenbank, ohne eigene Kopie. Erst mit Schreibrechten erhält jede Person ihre eigene datensparsame Kopie.</p>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Austeilen">📤</button></div>
        </form>
        <p class="muted">Wird beides angegeben, gilt die ganze Klasse.</p>
    </section>
</div>

<section class="panel">
    <h3>📋 Aktuelle Zuweisungen</h3>
    <?php if ($assignments === []): ?>
    <p class="muted">Noch keine Zuweisungen in deinen Klassen.</p>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Datenbank</th><th>Person</th><th>Klasse</th><th>Rechte</th><th>Aktion</th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $assignment): ?>
        <tr>
            <td><?= e($assignment['templateTitle']) ?></td>
            <td><?= e($assignment['userName']) ?></td>
            <td><?= $assignment['className'] !== null ? e($assignment['className']) : '-' ?></td>
            <td><span class="pill"><?= e($permissions[$assignment['permission']] ?? $assignment['permission']) ?></span></td>
            <td>
                <form method="post" action="<?= url('werkstatt/sql/entfernen') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="grant_id" value="<?= (int) $assignment['grantId'] ?>">
                    <button class="btn btn-ghost" type="submit" title="Zurückziehen">⏹</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>

