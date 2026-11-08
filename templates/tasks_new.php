<?php
/** @var \Infocraft\Models\TaskTopic[] $allTopics @var \Infocraft\Models\User $user */
$typeLabels = ['allgemein' => 'Allgemein', 'sql' => 'SQL', 'html' => 'HTML', 'code' => 'Java/Python', 'quiz' => 'Quiz'];
?>
<section class="hero">
    <div class="hero-emoji">➕</div>
    <h1>Neue Aufgabe</h1>
    <p>Eine neue Aufgabe für deinen Pool anlegen: Themengebiet nach LehrplanPLUS, Beschreibung, Musterlösung und Punkte. ToDos, Vorlagen und das Verteilen folgen direkt nach dem Anlegen auf der Bearbeiten-Seite.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>📝 Aufgabe anlegen</h3>
        <form method="post" action="<?= url('aufgaben/anlegen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. JOINs üben"></label>
            <div class="two-col">
                <label class="field"><span>Typ</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue) ?>"><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Jahrgangsstufe</span>
                    <select name="level" id="task-create-level" data-topic-level-source="1">
                        <?php foreach (valid_levels() as $level): ?>
                        <option value="<?= $level ?>"<?= $level === 9 ? ' selected' : '' ?>>Jahrgangsstufe <?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="field"><span>Themengebiet (nach LehrplanPLUS, passend zur Stufe)</span>
                <select name="topic_id" data-topic-level-filter="1">
                    <option value="0">ohne Themengebiet</option>
                    <?php foreach ($allTopics as $topic): ?>
                    <option value="<?= (int) $topic->id ?>" data-topic-level="<?= (int) $topic->level ?>"><?= e($topic->name) ?> (Jgst. <?= (int) $topic->level ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Oder neues Themengebiet anlegen (überschreibt die Auswahl)</span>
                <input type="text" name="new_topic" placeholder="z. B. Roboter programmieren">
            </label>
            <div class="field"><span>Beschreibung (mit Formatierungsleiste, Schülerinnen und Schüler können sie ein- und ausblenden)</span>
                <div data-wysiwyg data-initial-html="0">
                    <div class="wysiwyg-toolbar">
                        <button type="button" data-cmd="bold" title="Fett"><b>B</b></button>
                        <button type="button" data-cmd="italic" title="Kursiv"><i>K</i></button>
                        <button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>
                        <button type="button" data-cmd="formatblock" data-arg="h3" title="Überschrift">H</button>
                        <button type="button" data-cmd="insertunorderedlist" title="Liste">•</button>
                        <button type="button" data-cmd="insertorderedlist" title="Nummerierte Liste">1.</button>
                        <button type="button" data-cmd="formatblock" data-arg="pre" title="Code-Block">{ }</button>
                        <button type="button" data-cmd="createlink" title="Link einfügen">🔗</button>
                        <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">✗</button>
                    </div>
                    <div class="wysiwyg-view" contenteditable="true" data-placeholder="Was ist zu tun?"></div>
                    <input type="hidden" name="description_html" value="0">
                    <textarea name="description" class="wysiwyg-area" hidden></textarea>
                </div>
            </div>
            <label class="field"><span>Musterlösung (optional, dient der Korrektur und der KI als Vergleich)</span><textarea name="sample_solution" rows="4" placeholder="Wie sieht eine gute Lösung aus?"></textarea></label>
            <label class="field"><span>Erwartungshorizont (optional, wird dir in der Korrektur und der KI als Bewertungshilfe mitgegeben)</span><textarea name="rubric" rows="3" placeholder="Worauf kommt es an, wie verteilen sich die Punkte?"></textarea></label>
            <div class="two-col">
                <label class="field"><span>Maximale Punktzahl (0 = keine Punktevergabe)</span>
                    <input type="number" name="max_points" min="0" max="1000" value="0">
                </label>
                <div class="field"><span>Optionen</span>
                    <label style="display:flex;gap:6px;align-items:center;font-weight:normal;"><input type="checkbox" name="late_lock" value="1"> Nach dem Abgabetermin keine neuen Abgaben mehr annehmen</label>
                    <label style="display:flex;gap:6px;align-items:center;font-weight:normal;margin-top:6px;"><input type="checkbox" name="solution_visible" value="1"> Musterlösung freigeben: Schülerinnen und Schüler sehen sie nach der Korrektur oder nach dem Abgabetermin</label>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit" title="Aufgabe anlegen">✅</button>
                <a class="btn btn-ghost" href="<?= url('aufgaben') ?>">Abbrechen</a>
            </div>
        </form>
    </section>
    <div>
        <section class="panel">
            <h3>🧭 So läuft es danach</h3>
            <ul class="phase-list">
                <li>1. 📝 Anlegen (diese Seite): Titel, Typ, Stufe, Thema, Beschreibung</li>
                <li>2. ✏️ Bearbeiten: ToDos und Vorlagen ergänzen (Java-Starterdatei, HTML-Gerüst, SQL-Schema)</li>
                <li>3. 📤 Verteilen &amp; Termine: an die Klasse oder einzelne Personen, mit Start- und Abgabetermin</li>
                <li>4. ✅ Korrektur: Abgaben ansehen, benoten, Musterlösung freigeben</li>
            </ul>
            <p class="muted">Tipp: Fertige Aufgaben aus dem <a href="<?= url('aufgaben?view=katalog') ?>">Aufgabenkatalog</a> bringen ToDos, Vorlagen und Musterlösung bereits mit.</p>
        </section>
        <section class="panel">
            <h3>🤖 KI-Unterstützung</h3>
            <p class="muted"><?= $aiConfigured
                ? 'Schnittstelle hinterlegt, in der Korrekturansicht kannst du KI-Vorschläge anfordern.'
                : 'Noch keine Schnittstelle hinterlegt. <a href="' . url('aufgaben/ki') . '">Jetzt einrichten</a> (je Lehrkraft oder schulweit).' ?></p>
        </section>
    </div>
</div>

<script src="<?= url('assets/js/tasks.js') ?>" defer></script>
