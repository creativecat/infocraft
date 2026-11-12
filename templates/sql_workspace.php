<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: SQL-Werkstatt: Arbeitsfläche
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php $sqlTableNames = array_map(static fn (array $t): string => (string) $t['name'], $tables); ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h3><?= $template->isAdventure ? '🏝️' : '🗄️' ?> <?= e($template->title) ?></h3>
            <p class="muted"><?= e($template->description) ?></p>
        </div>
        <span class="pill" title="<?= $permission === 'read' ? 'Nur Leserechte: Du arbeitest an der gemeinsamen Datenbank, es wird keine eigene Kopie angelegt.' : 'Schreibrechte: Du arbeitest an deiner eigenen datensparsamen Kopie der Daten.' ?>"><?= e($permissionLabel) ?></span>
        <?php if (in_array($template->slug, ['shop', 'instacraft'], true)): ?>
        <a class="btn btn-ghost" href="<?= url('werkstatt/sql/' . e($template->slug) . '/app') ?>" title="Mobil-Frontend auf derselben Datenbank: Bestellungen, Likes und Kommentare landen in den Tabellen der Werkstatt - und umgekehrt.">📱 App-Ansicht</a>
        <?php endif; ?>
    </div>
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
<script src="<?= vendor_asset('assets/vendor/mermaid.min.js', 'https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js', 3000000) ?>"></script>
<script src="<?= vendor_asset('assets/vendor/highlight.min.js', 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.12.0/highlight.min.js', 100000) ?>"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: true }); }</script>

<section class="panel" id="aufgaben">
    <h3>🎯 Aufgaben</h3>
    <?php if ($missions === []): ?>
    <p class="muted">Für diese Datenbank gibt es noch keine Aufgaben.</p>
    <?php endif; ?>
    <?php foreach ($missions as $mission): ?>
    <?php $isSolved = in_array((int) $mission['id'], $solved, true); ?>
    <div class="mission<?= $isSolved ? ' solved' : '' ?>">
        <div class="mission-head">
            <strong>Aufgabe <?= (int) $mission['position'] ?>: <?= e($mission['title']) ?></strong>
            <?php if ($isSolved): ?><span class="badge-solved">✅ gelöst</span><?php endif; ?>
        </div>
        <p><?= e($mission['story']) ?></p>
        <?php if ($isSolved): ?>
        <details>
            <summary class="muted">Musterlösung ansehen</summary>
            <pre class="code"><code class="language-sql"><?= e($mission['solution_sql']) ?></code></pre>
        </details>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

