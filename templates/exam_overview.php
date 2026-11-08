<?php if ($isTeacher): ?>
<section class="hero hero-compact">
    <div class="hero-emoji">📋</div>
    <div class="hero-text">
        <h1>Prüfungen &amp; Arbeiten</h1>
        <p>Prüfungen und Schularbeiten vorbereiten: Gruppen bilden, pro Gruppe eine Vorlage auswählen, Teilaufgaben mit BE und Musterlösung anlegen. Beim Start bekommt jede Schülerin und jeder Schüler eine eigene Kopie.</p>
    </div>
</section>

<section class="panel panel-compact">
    <h3>➕ Neue Prüfung oder Schularbeit anlegen</h3>
    <form method="post" action="<?= url('pruefungen/anlegen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. HTML-Prüfung: Meine erste Seite"></label>
            <label class="field"><span>Art</span>
                <select name="kind">
                    <option value="pruefung">Prüfung (mit Sperre aller Bereiche)</option>
                    <option value="arbeit">Schularbeit (ohne Sperre)</option>
                </select>
            </label>
            <label class="field"><span>Ab Jahrgangsstufe</span>
                <select name="level">
                    <?php foreach (valid_levels() as $i): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Beschreibung</span><input type="text" name="description" maxlength="300" placeholder="Worum geht es?"></label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Anlegen">➕</button></div>
    </form>
</section>

<?php if ($exams === []): ?>
<div class="notice">
    <strong>Noch keine Prüfungen oder Schularbeiten</strong>
    <p>Lege oben den ersten Entwurf an: Gruppen, Vorlage und Teilaufgaben folgen im nächsten Schritt.</p>
</div>
<?php else: ?>
<div class="task-grid">
    <?php foreach ($exams as $exam): ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <span class="task-tile-main" title="<?= e($exam->title) ?>">
                <strong><?= $exam->kind === 'pruefung' ? '🔒' : '📄' ?> <?= e($exam->title) ?></strong>
                <span class="task-tile-meta"><?= $exam->description !== '' ? e($exam->description) : ($exam->kind === 'pruefung' ? 'Prüfung' : 'Schularbeit') ?> · ab Jgst. <?= (int) $exam->level ?></span>
            </span>
        </div>
        <div class="task-tile-pills">
            <span class="status status-<?= e($exam->phase === 'vorbereitung' ? 'geplant' : ($exam->phase === 'herausgegeben' ? 'fertig' : 'in-arbeit')) ?>"><?= e($exam->phase) ?></span>
        </div>
        <div class="task-tile-actions">
            <a class="btn btn-primary" href="<?= url('pruefungen/' . (int) $exam->id) ?>" title="Vorbereiten und verwalten: Gruppen, Vorlage, Teilaufgaben">⚙️</a>
            <?php if ($exam->phase === 'korrektur' || $exam->phase === 'herausgegeben'): ?>
            <a class="icon-btn" href="<?= url('pruefungen/' . (int) $exam->id . '/korrektur') ?>" title="Korrektur und Ergebnisse">✅</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<section class="hero hero-compact">
    <div class="hero-emoji">📋</div>
    <div class="hero-text">
        <h1>Prüfungen &amp; Arbeiten</h1>
        <p>Deine Prüfungen und Schularbeiten: arbeiten, abgeben und nach der Herausgabe das Ergebnis mit Musterlösung im Vergleich sehen.</p>
    </div>
</section>

<?php if ($exams === []): ?>
<div class="notice">
    <strong>Alles erledigt</strong>
    <p>Derzeit läuft keine Prüfung und keine Schularbeit für dich.</p>
</div>
<?php else: ?>
<div class="task-grid">
    <?php foreach ($exams as $entry): ?>
    <?php $exam = $entry['exam']; $work = $entry['work']; ?>
    <div class="task-tile">
        <div class="task-tile-head">
            <span class="task-tile-main" title="<?= e($exam->title) ?>">
                <strong><?= $exam->kind === 'pruefung' ? '🔒' : '📄' ?> <?= e($exam->title) ?></strong>
                <span class="task-tile-meta"><?= $exam->description !== '' ? e($exam->description) : ($exam->kind === 'pruefung' ? 'Prüfung' : 'Schularbeit') ?></span>
            </span>
        </div>
        <div class="task-tile-pills">
            <?php if ($exam->phase === 'durchfuehrung'): ?>
            <span class="status status-in-arbeit">läuft jetzt</span>
            <?php elseif ($exam->phase === 'herausgegeben'): ?>
            <span class="status status-fertig">Herausgegeben</span>
            <?php elseif ($work !== null && $work['submitted_at'] !== null): ?>
            <span class="status status-geplant">abgegeben</span>
            <?php endif; ?>
        </div>
        <div class="task-tile-actions">
            <?php if ($exam->phase === 'durchfuehrung' && ($work === null || $work['submitted_at'] === null)): ?>
            <a class="btn btn-primary" href="<?= url('pruefungen/' . (int) $exam->id . '/arbeit') ?>" title="Zur Arbeit">✍️</a>
            <?php elseif ($exam->phase === 'herausgegeben'): ?>
            <a class="btn btn-primary" href="<?= url('pruefungen/' . (int) $exam->id . '/ergebnis') ?>" title="Ergebnis ansehen">📊</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
