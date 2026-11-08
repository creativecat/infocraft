<?php /** @var \Infocraft\Models\Exam $exam */ ?>
<section class="panel">
    <div class="panel-head">
        <h3><?= e($exam->title) ?> <span class="pill"><?= $exam->kind === 'pruefung' ? '🔒 Prüfung' : '📄 Schularbeit' ?></span> <span class="status status-<?= e($exam->phase === 'vorbereitung' ? 'geplant' : ($exam->phase === 'herausgegeben' ? 'fertig' : 'in-arbeit')) ?>"><?= e($exam->phase) ?></span></h3>
        <span class="muted">ab Jahrgangsstufe <?= e($exam->level) ?><?= $exam->description !== '' ? ' · ' . e($exam->description) : '' ?></span>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= url('pruefungen') ?>" title="Übersicht">←</a>
        <?php if ($exam->phase === 'korrektur' || $exam->phase === 'herausgegeben'): ?>
        <a class="btn btn-primary" href="<?= url('pruefungen/' . $exam->id . '/korrektur') ?>">Korrektur &amp; Ergebnisse</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($exam->phase === 'vorbereitung'): ?>

<section class="panel">
    <h3>Gruppen &amp; Vorlagen</h3>
    <p class="muted">Jede Gruppe arbeitet mit ihrer eigenen Vorlage. Beim Start bekommt jedes Gruppenmitglied eine persönliche Kopie in die Werkstatt gelegt.</p>
    <?php if ($groups === []): ?>
    <div class="notice"><strong>Noch keine Gruppen</strong><p>Lege die erste Gruppe an, z. B. Gruppe A für alle mit Vorlage 1 und Gruppe B mit einer anderen Vorlage.</p></div>
    <?php else: ?>
    <?php foreach ($groups as $group): ?>
    <div class="panel" style="margin-top:12px;">
        <div class="panel-head">
            <h3><?= e($group['name']) ?></h3>
            <span class="pill"><?= $group['templateTitle'] !== null ? 'Vorlage: ' . e($group['templateTitle']) : 'keine Vorlage' ?></span>
        </div>
        <div class="two-col">
            <div>
                <strong class="muted">Mitglieder</strong>
                <?php if ($group['members'] === []): ?>
                <p class="muted">Noch niemand dabei.</p>
                <?php else: ?>
                <ul>
                    <?php foreach ($group['members'] as $member): ?>
                    <li><?= e($member->fullName()) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <form method="post" action="<?= url('pruefungen/' . $exam->id . '/mitglied') ?>" class="form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="group_id" value="<?= e($group['id']) ?>">
                    <label class="field"><span>Schülerin oder Schüler hinzufügen</span>
                        <select name="user_id" required>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($classes as $class): ?>
                            <optgroup label="Klasse <?= e($class->name) ?>">
                                <?php foreach ($studentsByClass[$class->id] ?? [] as $student): ?>
                                <option value="<?= e($student->id) ?>"><?= e($student->fullName()) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="btn-row"><button class="btn btn-primary" type="submit" title="Hinzufügen">➕</button></div>
                </form>
            </div>
            <div>
                <strong class="muted">Vorlage für diese Gruppe</strong>
                <form method="post" action="<?= url('pruefungen/' . $exam->id . '/vorlage') ?>" class="form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="group_id" value="<?= e($group['id']) ?>">
                    <label class="field"><span>Projekt aus der Werkstatt</span>
                        <select name="project_id">
                            <option value="0">Keine Vorlage (nur Teilaufgaben)</option>
                            <?php foreach ($templateProjects as $project): ?>
                            <option value="<?= e($project->id) ?>"><?= e($project->title) ?> (<?= e($project->type) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Vorlage festlegen">📎</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/gruppe') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Neue Gruppe</span><input type="text" name="name" required maxlength="100" placeholder="z. B. Gruppe A"></label>
            <button class="btn btn-primary" type="submit" title="Gruppe anlegen">➕</button>
        </div>
    </form>
</section>

<section class="panel">
    <h3>Teilaufgaben mit BE und Musterlösung</h3>
    <p class="muted">Bewertungseinheiten (BE) pro Teilaufgabe; die Musterlösung sehen die Schülerinnen und Schüler nach der Herausgabe im direkten Vergleich mit ihrer eigenen Lösung. Summe: <?= e($totalBe) ?> BE.</p>
    <?php if ($tasks === []): ?>
    <div class="notice"><strong>Noch keine Teilaufgaben</strong><p>Beschreibe, was zu tun ist, und lege die Musterlösung fest.</p></div>
    <?php else: ?>
    <?php foreach ($tasks as $task): ?>
    <div class="panel" style="margin-top:12px;">
        <div class="panel-head">
            <h3><?= e($task['position']) ?>. <?= e($task['title']) ?> <span class="pill"><?= e($task['be']) ?> BE</span></h3>
        </div>
        <?php if ($task['description'] !== ''): ?><p><?= e($task['description']) ?></p><?php endif; ?>
        <p class="muted"><strong>Musterlösung:</strong></p>
        <div class="muster"><?= e($task['sample_solution']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/aufgabe') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel der Teilaufgabe</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Überschrift schreiben"></label>
            <label class="field"><span>BE</span><input type="number" name="be" required min="1" max="100" value="4"></label>
        </div>
        <label class="field"><span>Aufgabenstellung (was ist zu tun?)</span><textarea name="description" rows="2" maxlength="2000" placeholder="Beschreibe die Aufgabe für die Schülerinnen und Schüler."></textarea></label>
        <label class="field"><span>Musterlösung</span><textarea name="sample_solution" rows="3" placeholder="Die Lösung, die nach der Herausgabe im Vergleich gezeigt wird."></textarea></label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Teilaufgabe hinzufügen">➕</button></div>
    </form>
</section>

<section class="panel">
    <h3>Start der <?= $exam->kind === 'pruefung' ? 'Prüfung' : 'Schularbeit' ?></h3>
    <?php if ($exam->kind === 'pruefung'): ?>
    <p class="muted">Beim Start werden die Arbeitskopien ausgeteilt. Für alle teilnehmenden Schülerinnen und Schüler sind dann alle anderen Bereiche gesperrt: Sie können nur noch in der Prüfung und in ihrem eigenen Prüfungsprojekt arbeiten.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/start') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit" title="Jetzt starten und austeilen">▶</button>
    </form>
</section>

<?php elseif ($exam->phase === 'durchfuehrung'): ?>

<section class="panel">
    <h3>Live-Monitor</h3>
    <p class="muted">Die <?= $exam->kind === 'pruefung' ? 'Prüfung' : 'Schularbeit' ?> läuft. Diese Personen haben angefangen:</p>
    <?php if ($works === []): ?>
    <div class="notice"><strong>Noch keine Teilnehmer auf der Arbeitsseite</strong><p>Die Ausgeteilten sehen die Prüfung auf ihrer Übersicht und öffnen sie dort.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Klasse</th><th>Begonnen</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($works as $workRow): ?>
            <tr>
                <td><?= e($workRow['name']) ?></td>
                <td><?= e($workRow['className'] ?? '-') ?></td>
                <td class="muted"><?= e($workRow['startedAt'] ?? '-') ?></td>
                <td><?= $workRow['submittedAt'] !== null ? '<span class="badge-solved">abgegeben ' . e($workRow['submittedAt']) . '</span>' : '<span class="status status-in-arbeit">arbeitet</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/ende') ?>" style="margin-top:14px;">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit" title="Abgabe beenden & Korrektur starten">⏹</button>
    </form>
</section>

<?php elseif ($exam->phase === 'korrektur'): ?>

<section class="panel">
    <h3>Korrektur läuft</h3>
    <p class="muted">Die Abgabe ist beendet. Jetzt alle Arbeiten bewerten: manuell oder mit einem Klick anonymisiert über die API-Korrektur.</p>
    <div class="btn-row"><a class="btn btn-primary" href="<?= url('pruefungen/' . $exam->id . '/korrektur') ?>" title="Zur Korrektur">✅</a></div>
</section>

<?php else: ?>

<section class="panel">
    <h3>Herausgegeben</h3>
    <p class="muted">Die Ergebnisse sind freigegeben. Die Schülerinnen und Schüler sehen Punkte, Note und den Vergleich mit der Musterlösung.</p>
    <div class="btn-row"><a class="btn btn-primary" href="<?= url('pruefungen/' . $exam->id . '/korrektur') ?>">Ergebnisse ansehen</a></div>
</section>

<?php endif; ?>
