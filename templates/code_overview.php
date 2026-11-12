<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Code-Werkstatt: Projektübersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.16.0 */ ?>
<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<?php /* Ordner mit Unterordnern: Diese zeigen keinen 'Noch keine Projekte'-Platzhalter,
     * auch wenn sie selbst keine direkten Projekte enthalten (#45). */ $folderHasChildren = [];
foreach ($folderRows as $row) { if (($row['folder']->parentId ?? 0) > 0) { $folderHasChildren[(int) ($row['folder']->parentId ?? 0)] = true; } } ?>
<section class="hero">
    <div class="hero-emoji">💻</div>
    <h1>Code-Werkstatt</h1>
    <p>Deine Projekte, organisiert in einem Ordnerbaum mit Unterordnern. Zieh Projekte einfach per Drag &amp; Drop in einen Ordner.</p>
</section>

<div class="two-col" data-move-url="<?= url('werkstatt/code/move') ?>" data-folder-move-url="<?= url('werkstatt/code/ordner/verschieben') ?>">
    <section class="panel">
        <h3>➕ Neues anlegen</h3>
        <form method="post" action="<?= url('werkstatt/code/projekt') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Projekttitel</span><input type="text" name="title" required placeholder="z. B. Mein erstes Programm"></label>
            <label class="field"><span>Programmiersprache</span>
                <select name="language" id="new-lang">
                    <option value="java"<?= (($defaultLanguage ?? 'java') === 'java') ? ' selected' : '' ?>>☕ Java</option>
                    <option value="python"<?= (($defaultLanguage ?? 'java') === 'python') ? ' selected' : '' ?>>🐍 Python</option>
                    <option value="asm"<?= (($defaultLanguage ?? 'java') === 'asm') ? ' selected' : '' ?>>⚙️ Assembler</option>
                    <option value="blocks"<?= (($defaultLanguage ?? 'java') === 'blocks') ? ' selected' : '' ?>>🧩 Blöcke</option>
                    <option value="automaton">🤖 Automat (endliche Automaten)</option>
                </select>
            </label>
            <label class="field"><span>Art</span>
                <select name="type" id="new-type">
                    <option value="code" selected>☕ Java-Programm</option>
                    <option value="html" id="new-type-html">🌐 HTML-Seite</option>
                </select>
            </label>
            <label class="field"><span>Ordner</span>
                <select name="folder_id">
                    <?php foreach ($folderNames as $folderId => $folderName): ?>
                    <option value="<?= (int) $folderId ?>"><?= e($folderName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field" id="new-starter"><span>Startpaket (nur Python)</span>
                <select name="starter">
                    <option value="">- leeres Blatt -</option>
                    <option value="zaehlen">🔢 Zählen mit Python (Schleifen)</option>
                    <option value="zeichnen">🎨 Zeichnen mit Zeichen (Turtle-Bild)</option>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Projekt anlegen">➕</button></div>
        </form>
        <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Neuer Ordner</span><input type="text" name="name" required placeholder="z. B. Hausaufgaben"></label>
            <label class="field"><span>Übergeordneter Ordner</span>
                <select name="parent_id">
                    <option value="0">- Hauptebene -</option>
                    <?php foreach ($folderNames as $folderId => $folderName): ?>
                    <?php if ($folderId === 0) { continue; } ?>
                    <option value="<?= (int) $folderId ?>"><?= e($folderName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Ordner anlegen">📁</button></div>
        </form>
        <?php if ($isTeacher && $projects !== []): ?>
        <h3>📤 Projekt an Klasse oder Person verteilen</h3>
        <form method="post" action="<?= url('werkstatt/code/verteilen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Projekt</span>
                <select name="project_id">
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project->id ?>"><?= e($project->title) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="two-col">
                <label class="field"><span>Ganze Klasse</span>
                    <select name="class_id">
                        <option value="0">- keine -</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Einzelne Person</span>
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
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Kopie austeilen">📤</button></div>
        </form>
        <?php endif; ?>
        <?php if ($isTeacher && $studentsByClass !== []): ?>
        <h3>🎒 Projekte meiner Schülerinnen und Schüler</h3>
        <?php foreach ($studentsByClass as $sClassId => $sStudents): ?>
            <p class="muted">Klasse <?= e($classNames[$sClassId] ?? '') ?>:</p>
            <p>
                <?php foreach ($sStudents as $sStudent): ?>
                <a class="pill" href="<?= url('werkstatt/code/schueler/' . (int) $sStudent->id) ?>">📂 <?= e($sStudent->fullName()) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endforeach; ?>
        <p class="muted">Jede Person zeigt ihre eigenen Projekte. Du kannst sie öffnen, den Zwischenstand ansehen und beim Arbeiten helfen.</p>
        <?php endif; ?>
    </section>
    <section class="panel">
        <h3>🗂️ Ordner &amp; Projekte</h3>
        <p class="muted">Ordner bilden einen Baum mit Unterordnern. Projekte und Ordner per Drag &amp; Drop auf einen anderen Ordner ziehen, um sie zu verschieben.</p>
        <ul class="folder-list">
            <li class="folder-item" data-folder="0">
                <strong>📁 Ohne Ordner</strong> <span class="pill"><?= (int) ($counts[0] ?? 0) ?></span>
                <div class="folder-children">
                    <?php if (($projectsByFolder[0] ?? []) === []): ?>
                    <p class="muted proj-empty">Noch keine Projekte.</p>
                    <?php endif; ?>
                    <?php foreach ($projectsByFolder[0] ?? [] as $project): ?>
                    <div class="proj-row project-card" draggable="true" data-id="<?= (int) $project->id ?>" title="Zum Verschieben in einen Ordner ziehen">
                        <span class="proj-icon"><?= $project->type === 'html' ? '🌐' : ($project->language === 'java' ? '☕' : ($project->language === 'asm' ? '⚙️' : ($project->language === 'blocks' ? '🧩' : '🐍'))) ?></span>
                        <a class="proj-title" href="<?= url('werkstatt/code/' . (int) $project->id) ?>"><?= e($project->title) ?></a>
                        <span class="pill proj-kind"><?= $project->type === 'html' ? 'HTML' : ($project->language === 'java' ? 'Java' : ($project->language === 'asm' ? 'Assembler' : ($project->language === 'blocks' ? 'Blöcke' : ($project->language === 'automaton' ? 'Automat' : 'Python')))) ?></span>
                        <a class="btn btn-primary proj-open" href="<?= url('werkstatt/code/' . (int) $project->id) ?>">Öffnen</a>
                        <form method="post" action="<?= url('werkstatt/code/loeschen') ?>" class="form-inline" onsubmit="return confirm('Projekt wirklich löschen?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                            <button class="btn btn-ghost" type="submit" title="Löschen">🗑️</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php foreach ($folderRows as $row): ?>
            <?php $folder = $row['folder']; ?>
            <li class="folder-item" draggable="true" data-folder="<?= (int) $folder->id ?>" data-folder-drag="<?= (int) $folder->id ?>" title="Ordner per Drag &amp; Drop in einen anderen Ordner ziehen" style="padding-left: <?= (int) $row['depth'] * 18 ?>px">
                <strong>📁 <?= e($folder->name) ?></strong> <span class="pill"><?= (int) ($counts[$folder->id] ?? 0) ?></span>
                <details class="folder-sub">
                    <summary class="muted" title="Unterordner anlegen">+</summary>
                    <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="parent_id" value="<?= (int) $folder->id ?>">
                        <input type="text" name="name" required placeholder="Unterordner">
                        <button class="btn btn-ghost" type="submit" title="Unterordner anlegen">📁</button>
                    </form>
                </details>
                <div class="folder-children">
                    <?php if (($projectsByFolder[$folder->id] ?? []) === [] && ($folder->parentId ?? 0) === 0 && empty($folderHasChildren[$folder->id])): ?>
                    <p class="muted proj-empty">Noch keine Projekte.</p>
                    <?php endif; ?>
                    <?php foreach ($projectsByFolder[$folder->id] ?? [] as $project): ?>
                    <div class="proj-row project-card" draggable="true" data-id="<?= (int) $project->id ?>" title="Zum Verschieben in einen Ordner ziehen">
                        <span class="proj-icon"><?= $project->type === 'html' ? '🌐' : ($project->language === 'java' ? '☕' : ($project->language === 'asm' ? '⚙️' : ($project->language === 'blocks' ? '🧩' : '🐍'))) ?></span>
                        <a class="proj-title" href="<?= url('werkstatt/code/' . (int) $project->id) ?>"><?= e($project->title) ?></a>
                        <span class="pill proj-kind"><?= $project->type === 'html' ? 'HTML' : ($project->language === 'java' ? 'Java' : ($project->language === 'asm' ? 'Assembler' : ($project->language === 'blocks' ? 'Blöcke' : ($project->language === 'automaton' ? 'Automat' : 'Python')))) ?></span>
                        <a class="btn btn-primary proj-open" href="<?= url('werkstatt/code/' . (int) $project->id) ?>">Öffnen</a>
                        <form method="post" action="<?= url('werkstatt/code/loeschen') ?>" class="form-inline" onsubmit="return confirm('Projekt wirklich löschen?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                            <button class="btn btn-ghost" type="submit" title="Löschen">🗑️</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>


<script>
/* Formular „Neues anlegen“: Auswahl je nach Sprache sinnvoll einschraenken.
 * Assembler ist immer ein Programm (keine HTML-Seite), Startpakete gibt es
 * nur fuer Python. Laeuft ohne Abhaengigkeiten direkt auf der Uebersicht. */
(function () {
  'use strict';
  var langSel = document.getElementById('new-lang');
  var typeSel = document.getElementById('new-type');
  var typeHtml = document.getElementById('new-type-html');
  var starter = document.getElementById('new-starter');
  if (!langSel || !typeSel || !typeHtml || !starter) { return; }
  function syncNewForm() {
    var lang = langSel.value;
    if (lang === 'asm' || lang === 'blocks') {
      typeSel.value = 'code';
      typeHtml.disabled = true;
    } else {
      typeHtml.disabled = false;
    }
    /* Startpaket nur bei Python anbieten */
    starter.style.display = (lang === 'python') ? '' : 'none';
    if (lang !== 'python') {
      starter.querySelectorAll('option').forEach(function (o) { if (o.value !== '') { o.selected = false; } });
      starter.querySelector('option[value=""]').selected = true;
    }
  }
  langSel.addEventListener('change', syncNewForm);
  syncNewForm();
})();
</script>
