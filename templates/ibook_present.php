<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: Präsentationsansicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.23.0 */ ?>
<?php /* InfoBook im Präsentationsmodus (#62, #177, #188): eine Folie je
   Seite, mit Bildern, Audio, Video und Einbettungen direkt abspielbar.
   #188: Statt Autoplay mit Zeitsteuerung erscheinen die Elemente einer
   Folie einzeln per Klick (oder Leertaste/Pfeil rechts); die kleine
   Anzeige slide-stepinfo nennt den Schritt. #191: Die Reihenfolge steht
   am Element (data-step im gerenderten HTML aus der Bearbeitung),
   gleiche Schrittnummern erscheinen zusammen, data-anim waehlt die
   Einblend-Animation. Logik im IC_PRESENT-Modul von app.js, kein
   Inline-Script. */ ?>
<div class="slide-stage" id="slide-stage" data-ibook="<?= (int) $book->id ?>">
    <div class="slide-toolbar">
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id) ?>" title="Zurück zum InfoBook">←</a>
        <span class="muted"><?= e($book->title) ?></span>
        <div class="slide-counter"><button class="btn btn-ghost" type="button" id="slide-prev" title="Zurück (Pfeiltaste links): letzten Schritt wieder ausblenden">◀</button><span id="slide-counter"></span><button class="btn btn-ghost" type="button" id="slide-next" title="Weiter (Klick, Leertaste oder Pfeiltaste rechts): nächsten Schritt einblenden">▶</button></div>
        <span class="slide-stepinfo" id="slide-stepinfo" aria-live="polite"></span>
        <button class="btn btn-ghost" type="button" id="slide-full" title="Vollbild (Taste F)">⛶</button>
    </div>
    <?php if ($slides === []): ?>
    <section class="slide slide-active" data-slide="0">
        <h2 class="slide-title">Noch keine Seiten</h2>
        <div class="slide-text wiki-content"><p>Dieses InfoBook hat noch keine Seiten, die sich als Präsentation zeigen lassen.</p></div>
    </section>
    <?php else: ?>
    <?php foreach ($slides as $pos => $slide): ?>
    <section class="slide<?= $pos === 0 ? ' slide-active' : '' ?>" data-slide="<?= (int) $pos ?>" id="ib<?= (int) $pos ?>">
        <?php if ($slide['title'] !== ''): ?><h2 class="slide-title"><?= e((string) $slide['title']) ?></h2><?php endif; ?>
        <div class="slide-text wiki-content ibook-slide-blocks">
            <?php if ((string) $slide['section'] !== ''): ?><p class="pill ibook-slide-section"><?= e((string) $slide['section']) ?></p><?php endif; ?>
            <?= $slide['html'] /* bereits escapet: Text via Markdown-Renderer, Medien als Player */ ?>
        </div>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
