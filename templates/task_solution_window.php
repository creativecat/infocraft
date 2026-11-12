<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Aufgaben: Musterlösung im Fenster
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
/**
 * Musterlösung der Lehrkraft in einem eigenen Fenster (Issue #36):
 * schlanke Seite ohne Navigation, gedacht für den Zweitbildschirm,
 * bleibt unabhängig vom Arbeitsfenster offen.
 * Wird im $bare-Modus gerendert (nur Karte, keine Seitennavigation).
 */
?>
<div class="card" style="max-width:860px;width:100%;margin:0 auto;text-align:left;">
    <h2 style="margin-top:0;">🪟 Musterlösung: <?= e($task->title) ?></h2>
    <?php if (trim($task->sampleSolution) === ''): ?>
    <p class="muted">Für diese Aufgabe ist noch keine Musterlösung hinterlegt.
        Du kannst sie in der Bearbeitung der Aufgabe ergänzen.</p>
    <?php else: ?>
    <pre class="code-block" style="white-space:pre-wrap;font-family:ui-monospace,monospace;background:var(--bg-inset,#f6f8fa);padding:12px;border-radius:8px;overflow:auto;max-height:80vh;"><?= e($task->sampleSolution) ?></pre>
    <?php endif; ?>
    <?php if (trim($task->rubric) !== ''): ?>
    <h3>Erwartungshorizont</h3>
    <div style="white-space:pre-wrap;"><?= e($task->rubric) ?></div>
    <?php endif; ?>
    <p class="muted">Dieses Fenster gehört zur Aufgabe „<?= e($task->title) ?>“ und aktualisiert sich beim Neuladen.</p>
</div>
