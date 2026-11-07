<?php $folderNames = [0 => 'Ohne Ordner']; foreach ($folders as $folder) { $folderNames[$folder->id] = $folder->name; } ?>
<section class="hero">
    <div class="hero-emoji">🎨</div>
    <h1>HTML-Werkstatt</h1>
    <p>Webseiten schreiben mit sofortiger Vorschau: links tippen, rechts entsteht die Seite.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Neue Seite</h3>
        <form method="post" action="<?= url('werkstatt/html/projekt') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. Meine Hobbyseite"></label>
            <label class="field"><span>Vorlage</span>
                <select name="template">
                    <option value="grund">Grundgerüst mit Text und Liste</option>
                    <option value="galerie">Bildergalerie</option>
                    <option value="formular">Kontaktformular</option>
                    <option value="blank">Leere Seite</option>
                </select>
            </label>
            <label class="field"><span>Ordner</span>
                <select name="folder_id">
                    <option value="0">Ohne Ordner</option>
                    <?php foreach ($folders as $folder): ?>
                    <option value="<?= (int) $folder->id ?>"><?= e($folder->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit">Seite anlegen</button></div>
        </form>
    </section>
    <section class="panel">
        <h3>💡 So arbeitest du hier</h3>
        <ul class="feature-list">
            <li>Editor links, Vorschau rechts: beim Tippen aktualisiert sich deine Seite sofort</li>
            <li>Vorlagen liefern fertige Grundgerüste zum Umbauen</li>
            <li>Mit Strg + S speicherst du direkt</li>
            <?php if ($isTeacher): ?><li>Seiten teilst du wie Code-Projekte über die Code-Werkstatt an Klassen aus</li><?php endif; ?>
        </ul>
    </section>
</div>

<div class="cards">
    <?php foreach ($projects as $project): ?>
    <div class="card">
        <div class="card-icon">🌐</div>
        <h2><?= e($project->title) ?></h2>
        <p class="card-desc muted"><?= e($folderNames[$project->folderId ?? 0] ?? 'Ohne Ordner') ?></p>
        <div class="card-meta">
            <a class="btn btn-primary" href="<?= url('werkstatt/html/' . (int) $project->id) ?>">Bearbeiten</a>
            <form method="post" action="<?= url('werkstatt/code/loeschen') ?>" class="form-inline" onsubmit="return confirm('Seite wirklich löschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                <button class="btn btn-ghost" type="submit" title="Löschen">🗑️</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($projects === []): ?>
    <p class="muted">Noch keine Seiten. Lege oben deine erste Seite an.</p>
    <?php endif; ?>
</div>

