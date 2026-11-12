<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: Präsentationsansicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php /* InfoBook im Präsentationsmodus (#62): eine Folie je Seite, mit Bildern, Audio, Video und Einbettungen direkt abspielbar. */ ?>
<div class="slide-stage" id="slide-stage" data-ibook="1">
    <div class="slide-toolbar">
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id) ?>" title="Zurück zum InfoBook">←</a>
        <span class="muted"><?= e($book->title) ?></span>
        <button class="btn btn-ghost" type="button" id="slide-full" title="Vollbild (Taste F)">⛶</button>
        <div class="slide-counter"><button class="btn btn-ghost" type="button" id="slide-prev" title="Zurück (Pfeiltaste links)">◀</button><span id="slide-counter"></span><button class="btn btn-ghost" type="button" id="slide-next" title="Weiter (Pfeiltaste rechts)">▶</button></div>
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
<script>
/* Präsentations-Navigation: Pfeiltasten, Leertaste, Klick zurueck/weiter,
 * Vollbild per Taste F (#62, gleiche Bedienung wie die Präsentationen). */
(function () {
  var stage = document.getElementById('slide-stage');
  if (!stage) { return; }
  var slides = stage.querySelectorAll('.slide');
  var counter = document.getElementById('slide-counter');
  var current = 0;
  function show(n) {
    if (n < 0 || n >= slides.length) { return; }
    slides[current].classList.remove('slide-active');
    current = n;
    slides[current].classList.add('slide-active');
    if (counter) { counter.textContent = (current + 1) + ' / ' + slides.length; }
    try { localStorage.setItem('ibook-slide-' + stage.getAttribute('data-ibook'), String(current)); } catch (e) { }
  }
  var prev = document.getElementById('slide-prev');
  var next = document.getElementById('slide-next');
  if (prev) { prev.addEventListener('click', function () { show(current - 1); }); }
  if (next) { next.addEventListener('click', function () { show(current + 1); }); }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight' || ev.key === ' ' || ev.key === 'PageDown') { ev.preventDefault(); show(current + 1); }
    else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') { ev.preventDefault(); show(current - 1); }
    else if (ev.key === 'f' || ev.key === 'F') {
      var full = document.getElementById('slide-full');
      if (full) { full.click(); }
    }
  });
  var fullBtn = document.getElementById('slide-full');
  if (fullBtn) {
    fullBtn.addEventListener('click', function () {
      try {
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else if (stage.requestFullscreen) { stage.requestFullscreen(); }
      } catch (e) { /* aeltere Browser: egal */ }
    });
  }
  var start = 0;
  try { start = parseInt(localStorage.getItem('ibook-slide-' + stage.getAttribute('data-ibook')) || '0', 10) || 0; } catch (e) { }
  if (start > 0 && start < slides.length && slides.length > 0) { show(start); }
  else if (counter) { counter.textContent = '1 / ' + slides.length; }
})();
</script>
