<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: SQL-Werkstatt: Abenteuer-Arbeitsfläche (Kapitel-Ansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.24.0 */ ?>
<?php $sqlTableNames = array_map(static fn (array $t): string => (string) $t['name'], $tables); ?>
<?php
$solvedCount = 0;
foreach ($missions as $mission) {
    if (in_array((int) $mission['id'], $solved, true)) {
        $solvedCount++;
    }
}
$total = count($missions);
$currentId = 0;
$currentPosition = 0;
foreach ($missions as $mission) {
    if (!in_array((int) $mission['id'], $solved, true)) {
        $currentId = (int) $mission['id'];
        $currentPosition = (int) $mission['position'];
        break;
    }
}
$percent = $total > 0 ? (int) round($solvedCount * 100 / $total) : 0;
?>
<section class="panel adv-hero">
    <div class="adv-scene adv-hero-scene"><?= $meta['scene'] ?></div>
    <div class="adv-hero-body">
        <h3><?= $meta['emoji'] ?> <?= e($template->title) ?></h3>
        <p><?= e($meta['intro']) ?></p>
        <div class="adv-progress-line">
            <div class="adv-progress" title="Fortschritt: <?= $solvedCount ?> von <?= $total ?> Kapiteln gelöst"><div class="adv-progress-fill" style="width: <?= $percent ?>%"></div></div>
            <span class="pill"><?= $solvedCount ?> / <?= $total ?> Kapitel<?= $solvedCount === $total && $total > 0 ? ' ✅' : '' ?></span>
        </div>
        <span class="pill" title="<?= $permission === 'read' ? 'Nur Leserechte: Für die Kapitel dieses Abenteuers sind Schreibrechte nötig.' : 'Schreibrechte: Du arbeitest an deiner eigenen datensparsamen Kopie der Daten.' ?>"><?= e($permissionLabel) ?></span>
        <?php if ($permission === 'read'): ?>
        <p class="muted">Dieses Abenteuer lebt von Änderungen an den Daten. Deine Lehrkraft kann dir Schreibrechte geben.</p>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h3>🧭 Deine SQL-Konsole</h3>
    <p class="muted">Probier hier frei aus, was in den Tabellen steckt: Jede Kapitel-Lösung beginnt mit einem Blick in die Daten.</p>
    <form method="post" action="<?= url('werkstatt/sql/' . $template->slug) ?>" class="form" id="sql-form">
        <?= csrf_field() ?>
        <label class="field">
            <span>SQL-Anweisung (Strg + Enter führt aus)</span>
            <div class="hlwrap sql" data-hl="sql" data-ac="sql"><textarea name="sql" rows="5" class="mono sql-input" data-tables="<?= e(implode(',', $sqlTableNames)) ?>" placeholder="SELECT * FROM ..."><?= e($lastSql) ?></textarea></div>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Ausführen">▶</button></div>
    </form>
    <?php if ($result !== null): ?>
        <?php if ($result['ok']): ?>
        <div class="query-result ok" data-result="ok">
            <?php if ($result['columns'] !== []): ?>
            <table class="table">
                <thead><tr><?php foreach ($result['columns'] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                <tr><?php foreach ($result['columns'] as $column): ?><td><?= e($row[$column] ?? '') ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>✅ Erfolgreich ausgeführt, <?= (int) $result['affected'] ?> Zeile(n) geändert.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="query-result error" data-result="error">⛔ <?= e($result['error']) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<details class="panel adv-tools">
    <summary><strong>🎒 Werkzeugkasten: Tabellen und ER-Diagramm</strong></summary>
    <div class="two-col">
        <section class="panel">
            <h3>📋 Tabellen der Datenbank</h3>
            <?php foreach ($tables as $table): ?>
            <details class="table-browser">
                <summary><strong><?= e($table['name']) ?></strong> <span class="muted">(<?= count($table['columns']) ?> Spalten)</span></summary>
                <ul class="feature-list">
                    <?php foreach ($table['columns'] as $column): ?>
                    <li><span class="mono"><?= e($column['name']) ?></span> <span class="muted"><?= e($column['type']) ?><?= $column['pk'] ? ' · Primärschlüssel' : '' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>
        </section>
        <section class="panel">
            <h3>🕸️ ER-Diagramm</h3>
            <div class="mermaid"><?= e($er) ?></div>
            <details>
                <summary class="muted">ER-Diagramm-Quelltext anzeigen (zum Weiterverwenden, z. B. in Präsentationen)</summary>
                <pre class="code"><code class="language-mermaid"><?= e($er) ?></code></pre>
            </details>
            <?php if ($diagramAuto): ?>
            <form method="post" action="<?= url('werkstatt/sql/' . $template->slug . '/diagramm') ?>" class="form-inline" style="margin-top:10px;">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" title="Tabellen als Klassendiagramm übernehmen">📐</button>
            </form>
            <?php else: ?>
            <p class="muted" style="margin-top:10px;">Das automatische Erzeugen von Klassendiagrammen ist für deine Klasse deaktiviert.</p>
            <?php endif; ?>
        </section>
    </div>
</details>
<script src="<?= vendor_asset('assets/vendor/mermaid.min.js', 'https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js', 3000000) ?>"></script>
<script src="<?= vendor_asset('assets/vendor/highlight.min.js', 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.12.0/highlight.min.js', 100000) ?>"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: true }); }</script>

<section class="panel" id="aufgaben">
    <h3>📖 Kapitel deiner Reise</h3>
    <?php if ($missions === []): ?>
    <p class="muted">Für dieses Abenteuer gibt es noch keine Kapitel.</p>
    <?php endif; ?>
    <?php if ($total > 0 && $solvedCount === $total): ?>
    <div class="adv-finale">
        <h3>🎉 Abenteuer geschafft!</h3>
        <p><?= e($meta['finale']) ?></p>
        <p class="muted">Du kannst die Musterlösungen noch einmal ansehen oder in der Konsole weiterexperimentieren.</p>
    </div>
    <?php endif; ?>
    <?php foreach ($missions as $mission): ?>
    <?php $isSolved = in_array((int) $mission['id'], $solved, true); ?>
    <?php $isCurrent = (int) $mission['id'] === $currentId; ?>
    <?php if ($isSolved): ?>
    <div class="mission adv-chapter adv-solved">
        <div class="mission-head">
            <span class="adv-chapter-badge">Kapitel <?= (int) $mission['position'] ?></span>
            <strong><?= e($mission['title']) ?></strong>
            <span class="badge-solved">✅ gelöst</span>
        </div>
        <p class="muted"><?= e($mission['story']) ?></p>
        <details>
            <summary class="muted">Musterlösung ansehen</summary>
            <pre class="code"><code class="language-sql"><?= e($mission['solution_sql']) ?></code></pre>
        </details>
    </div>
    <?php elseif ($isCurrent): ?>
    <div class="mission adv-chapter adv-current">
        <div class="mission-head">
            <span class="adv-chapter-badge adv-chapter-now">Kapitel <?= (int) $mission['position'] ?></span>
            <strong><?= e($mission['title']) ?></strong>
        </div>
        <p><?= e($mission['story']) ?></p>
        <form method="post" action="<?= url('werkstatt/sql/' . $template->slug . '/mission/' . (int) $mission['id']) ?>" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span>Deine SQL-Anweisung<?= ($mission['kind'] ?? 'query') === 'action' ? ' (verändert die Daten, Schreibrechte nötig)' : '' ?></span>
                <div class="hlwrap sql" data-hl="sql" data-ac="sql"><textarea name="mission_sql" rows="3" class="mono" placeholder="SELECT ..."></textarea></div>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Prüfen">✅</button></div>
        </form>
        <details>
            <summary class="muted">💡 Tipp anzeigen</summary>
            <p><?= e($mission['hint']) ?></p>
        </details>
    </div>
    <?php else: ?>
    <div class="mission adv-chapter adv-locked" title="Dieses Kapitel öffnet sich, sobald du Kapitel <?= $currentPosition ?> gelöst hast">
        <div class="mission-head">
            <span class="adv-chapter-badge">Kapitel <?= (int) $mission['position'] ?></span>
            <strong>🔒 <?= e($mission['title']) ?></strong>
        </div>
        <p class="muted">Noch verschlossen: Löse zuerst Kapitel <?= $currentPosition ?>, dann geht es hier weiter.</p>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</section>

<p><a class="btn btn-ghost" href="<?= url('werkstatt/sql/abenteuer') ?>">🗺️ Zurück zur Abenteuer-Übersicht</a></p>
