<section class="panel">
    <div class="panel-head">
        <h3>📐 <?= e($diagram->title) ?> <span class="pill"><?= e($diagram->kindLabel()) ?></span></h3>
        <a class="btn btn-ghost" href="<?= url('diagramme') ?>">← Zurück</a>
    </div>
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($diagram->title) ?>" required maxlength="200"></label>
        <label class="field"><span>Mermaid-Quelltext (classDiagram), die Vorschau aktualisiert sich beim Tippen</span>
            <div class="hlwrap" data-hl="mermaid">
                <textarea name="mermaid" id="diagram-source" rows="14" class="mono" spellcheck="false"><?= e($diagram->mermaid) ?></textarea>
            </div>
        </label>
        <div class="btn-row"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
    </form>
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id . '/loeschen') ?>" class="form-inline" onsubmit="return confirm('Diagramm wirklich löschen?');">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit">🗑️ Diagramm löschen</button>
    </form>
</section>

<section class="panel" data-diagram-page="1">
    <div class="panel-head">
        <h3>👁️ Vorschau</h3>
        <div class="btn-row">
            <button class="btn btn-ghost" id="diagram-refresh" type="button">↻ Aktualisieren</button>
            <button class="btn btn-primary" id="diagram-png" type="button" data-png-name="<?= e(preg_replace('/[^\w.-]+/', '_', $diagram->title)) ?>">🖼️ PNG herunterladen</button>
        </div>
    </div>
    <div class="diagram-preview" id="diagram-preview">
        <p class="muted">Wird gezeichnet ...</p>
    </div>
    <details>
        <summary class="muted">Mermaid-Quelltext anzeigen (zum Weiterverwenden, z. B. in Präsentationen)</summary>
        <pre class="code"><code class="language-mermaid"><?= e($diagram->mermaid) ?></code></pre>
    </details>
</section>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: false }); }</script>
