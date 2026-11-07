<?php /** Präsentationsmodus: Folie für Folie, Vollbild (Taste F), Pfeiltasten.
 * Code-Folien tragen Syntax-Highlighting (highlight.js) und sind für
 * Lehrkräfte direkt hier editierbar und speicherbar. */ ?>
<div class="slide-stage" id="slide-stage" data-pid="<?= (int) $presentation->id ?>">
    <div class="slide-toolbar">
        <a class="btn btn-ghost" href="<?= url('praesentationen') ?>">← Alle</a>
        <?php if ($mayEdit): ?>
        <a class="btn btn-ghost" href="<?= url('praesentationen/' . (int) $presentation->id) ?>">Bearbeiten</a>
        <?php endif; ?>
        <?php if ($isTeacher): ?>
        <a class="btn btn-ghost" href="<?= url('praesentationen/' . (int) $presentation->id . '/moderator') ?>" title="Steuerzentrale: aktuelle und nächste Folie, Timer - synchron zu diesem Fenster">🖥 Moderator</a>
        <?php endif; ?>
        <button class="btn btn-ghost" type="button" id="slide-full" title="Vollbild (Taste F)">⛶ Vollbild</button>
        <div class="slide-counter"><button class="btn btn-ghost" type="button" id="slide-prev" title="Zurück (Pfeiltaste links)">◀</button><span id="slide-counter"></span><button class="btn btn-ghost" type="button" id="slide-next" title="Weiter (Pfeiltaste rechts)">▶</button></div>
    </div>

    <?php if ($slides === []): ?>
    <section class="slide slide-active" data-slide="0">
        <h2><?= e($presentation->title) ?></h2>
        <p>Diese Präsentation hat noch keine Folien.</p>
    </section>
    <?php else: ?>
    <?php foreach ($slides as $pos => $slide): ?>
    <section class="slide<?= $pos === 0 ? ' slide-active' : '' ?>" data-slide="<?= $pos ?>" id="f<?= $pos ?>">
        <?php if ($slide['type'] === 'code'): ?>
        <h2 class="slide-title"><?= e($slide['title']) ?> <span class="pill"><?= e($slide['lang']) ?></span></h2>
        <pre class="code slide-code"><code class="language-<?= e($slide['lang']) ?>"><?= e($slide['code']) ?></code></pre>
        <?php if ($mayEdit): ?>
        <button class="btn btn-ghost" type="button" data-edit-toggle="slide-edit-<?= $pos ?>">✏️ Code verändern (live)</button>
        <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/folie/' . $pos) ?>" class="form slide-edit" id="slide-edit-<?= $pos ?>" hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="title" value="<?= e($slide['title']) ?>">
            <input type="hidden" name="lang" value="<?= e($slide['lang']) ?>">
            <input type="hidden" name="ziel" value="zeigen">
            <label class="field"><span>Code verändern (Syntax-Highlighting folgt live beim Tippen)</span>
                <span class="hlwrap" data-hl="<?= e($slide['lang']) ?>"><textarea name="code" rows="12" class="mono" spellcheck="false"><?= e($slide['code']) ?></textarea></span>
            </label>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit">💾 Speichern</button>
                <button class="btn btn-ghost" type="button" data-edit-cancel="slide-edit-<?= $pos ?>">Abbrechen</button>
            </div>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($slide['title'] !== ''): ?><h2 class="slide-title"><?= e($slide['title']) ?></h2><?php endif; ?>
        <div class="slide-text wiki-content"><?= $slide['html'] /* bereits escapet */ ?></div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>
<script>
(function () {
  var stage = document.getElementById('slide-stage');
  if (!stage) { return; }
  var slides = stage.querySelectorAll('[data-slide]');
  var prev = document.getElementById('slide-prev');
  var next = document.getElementById('slide-next');
  var fullBtn = document.getElementById('slide-full');
  var counter = document.getElementById('slide-counter');
  var current = 0;
  // Sync mit dem Steuerfenster für Lehrkräfte (gleiches Browser-Fenster-Paar, gleiche Origin):
  // Dieser Modus sendet {typ:'pos'}, das Steuerfenster sendet {typ:'goto'}.
  var remote = false;
  var channel = null;
  if (stage.dataset && typeof BroadcastChannel !== 'undefined') {
    channel = new BroadcastChannel('ic-praes-' + stage.dataset.pid);
    channel.onmessage = function (ev) {
      var msg = ev.data || {};
      if (msg.typ === 'goto' && typeof msg.pos === 'number') {
        remote = true;
        show(msg.pos);
        remote = false;
      }
    };
  }

  function fit(i) {
    if (i < 0) { return 0; }
    if (i > slides.length - 1) { return slides.length - 1; }
    return i;
  }

  function show(i) {
    current = fit(i);
    for (var k = 0; k < slides.length; k++) {
      if (k === current) { slides[k].classList.add('slide-active'); }
      else { slides[k].classList.remove('slide-active'); }
    }
    if (counter) { counter.textContent = (current + 1) + ' / ' + slides.length; }
    if (channel && !remote) { channel.postMessage({ typ: 'pos', pos: current }); }
  }

  function toggleFull() {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (stage.requestFullscreen) {
      stage.requestFullscreen();
    }
  }

  if (prev) { prev.addEventListener('click', function () { show(current - 1); }); }
  if (next) { next.addEventListener('click', function () { show(current + 1); }); }
  if (fullBtn) { fullBtn.addEventListener('click', toggleFull); }

  document.addEventListener('keydown', function (ev) {
    var tag = ev.target && ev.target.tagName ? ev.target.tagName.toUpperCase() : '';
    if (tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT') { return; }
    if (ev.key === 'ArrowRight' || ev.key === 'PageDown' || ev.key === ' ') {
      ev.preventDefault();
      show(current + 1);
    } else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') {
      ev.preventDefault();
      show(current - 1);
    } else if (ev.key === 'Home') {
      show(0);
    } else if (ev.key === 'End') {
      show(slides.length - 1);
    } else if (ev.key === 'f' || ev.key === 'F') {
      toggleFull();
    }
  });

  // Code-Folie live verändern: Formular aufklappen, statischen Block ausblenden.
  var openButtons = stage.querySelectorAll('[data-edit-toggle]');
  for (var i = 0; i < openButtons.length; i++) {
    openButtons[i].addEventListener('click', function (ev) {
      var id = ev.currentTarget.getAttribute('data-edit-toggle');
      var form = document.getElementById(id);
      if (!form) { return; }
      var section = form.closest('[data-slide]');
      if (section) {
        var statics = section.querySelectorAll('pre.slide-code');
        for (var j = 0; j < statics.length; j++) { statics[j].hidden = true; }
      }
      form.hidden = false;
      ev.currentTarget.hidden = true;
      var area = form.querySelector('textarea');
      if (area) { area.focus(); }
    });
  }
  var cancelButtons = stage.querySelectorAll('[data-edit-cancel]');
  for (var c = 0; c < cancelButtons.length; c++) {
    cancelButtons[c].addEventListener('click', function (ev) {
      var id = ev.currentTarget.getAttribute('data-edit-cancel');
      var form = document.getElementById(id);
      if (form) { form.hidden = true; }
      var section = form && form.closest ? form.closest('[data-slide]') : null;
      if (section) {
        var statics = section.querySelectorAll('pre.slide-code');
        for (var j2 = 0; j2 < statics.length; j2++) { statics[j2].hidden = false; }
        var opener = section.querySelector('[data-edit-toggle]');
        if (opener) { opener.hidden = false; }
      }
    });
  }

  // Startfolie aus dem URL-Anker (#f2 = dritte Folie), sonst Folie 1.
  var hash = window.location.hash;
  var start = 0;
  if (hash.indexOf('#f') === 0) {
    start = fit(parseInt(hash.slice(2), 10) || 0);
  }
  show(start);
})();
</script>
