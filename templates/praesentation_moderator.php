<?php /** Moderatorenbildschirm (nur für Lehrkräfte): Steuerzentrale mit aktueller
 * und nächster Folie, Folienliste, Timer und Uhr. Zusammen mit der Präsentations-
 * ansicht im zweiten Fenster (gleicher Browser) über einen BroadcastChannel
 * synchron: Pfeiltasten steuern beide Fenster. */ ?>
<section class="panel">
    <div class="mod-toolbar">
        <a class="btn btn-ghost" href="<?= url('praesentationen') ?>" title="Alle Präsentationen">←</a>
        <h3 class="mod-title">🎬 <?= e($presentation->title) ?></h3>
        <span class="pill">Moderator</span>
        <button class="btn btn-ghost" type="button" id="mod-open" title="Präsentationsansicht im neuen Fenster öffnen (z. B. für den Beamer)">⛶</button>
        <div class="slide-counter"><button class="btn btn-ghost" type="button" id="mod-prev" title="Zurück (Pfeiltaste links)">◀</button><span id="mod-counter"></span><button class="btn btn-ghost" type="button" id="mod-next" title="Weiter (Pfeiltaste rechts)">▶</button></div>
    </div>

    <?php if ($slides === []): ?>
    <div class="notice">
        <strong>Noch keine Folien</strong>
        <p>Diese Präsentation hat noch keine Folien. Füge zuerst Folien hinzu, bevor du sie präsentierst.</p>
    </div>
    <?php else: ?>
    <div class="mod-deck" id="mod-deck" data-pid="<?= (int) $presentation->id ?>">
        <div class="mod-main">
            <div class="mod-box" id="mod-now-box">
                <h4 class="mod-box-title">Aktuelle Folie <span class="pill" id="mod-now-num"></span></h4>
                <div class="mod-slide-holder" id="mod-now"></div>
            </div>
            <div class="mod-box mod-box-next" id="mod-next-box">
                <h4 class="mod-box-title">Nächste Folie</h4>
                <div class="mod-slide-holder" id="mod-next"></div>
            </div>
        </div>
        <aside class="mod-side">
            <div class="mod-timer-box">
                <div class="mod-clock" id="mod-clock">--:--</div>
                <div class="mod-timer" id="mod-timer">00:00</div>
                <div class="btn-row">
                    <button class="btn btn-primary" type="button" id="mod-timer-toggle" title="Start">▶</button>
                    <button class="btn btn-ghost" type="button" id="mod-timer-reset" title="Reset">↺</button>
                </div>
            </div>
            <div class="mod-note-box">
                <h4 class="mod-box-title">🎙 Sprechernotiz <span class="pill">nur für dich</span></h4>
                <p class="mod-note" id="mod-note"></p>
            </div>
            <div class="mod-list-box">
                <h4 class="mod-box-title">Folien</h4>
                <ol class="mod-list" id="mod-list"></ol>
            </div>
        </aside>
    </div>

    <div id="mod-pool" hidden>
        <?php foreach ($slides as $pos => $slide): ?>
        <section class="slide mod-slide" data-pos="<?= (int) $pos ?>" data-notes="<?= e($slide['notes'] ?? '') ?>">
            <?php if ($slide['type'] === 'code'): ?>
            <h2 class="slide-title"><?= e($slide['title']) ?> <span class="pill"><?= e($slide['lang']) ?></span></h2>
            <pre class="code slide-code"><code class="language-<?= e($slide['lang']) ?>"><?= e($slide['code']) ?></code></pre>
            <?php else: ?>
            <?php if ($slide['title'] !== ''): ?><h2 class="slide-title"><?= e($slide['title']) ?></h2><?php endif; ?>
            <div class="slide-text wiki-content"><?= $slide['html'] /* bereits escapet */ ?></div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>
<script>
(function () {
  var deck = document.getElementById('mod-deck');
  if (!deck) { return; }
  var pid = deck.getAttribute('data-pid');
  var pool = document.getElementById('mod-pool');
  var nodes = [];
  if (pool) {
    var sections = pool.querySelectorAll('[data-pos]');
    for (var s = 0; s < sections.length; s++) { nodes.push(sections[s]); }
  }
  var nowHolder = document.getElementById('mod-now');
  var nextHolder = document.getElementById('mod-next');
  var list = document.getElementById('mod-list');
  var counter = document.getElementById('mod-counter');
  var nowNum = document.getElementById('mod-now-num');
  var noteEl = document.getElementById('mod-note');
  var prev = document.getElementById('mod-prev');
  var next = document.getElementById('mod-next');
  var openBtn = document.getElementById('mod-open');
  var current = 0;

  // Synchronisation mit dem Präsentationsfenster (gleicher Browser):
  // Der Moderatorenbildschirm sendet {typ:'goto'}, die Ansicht antwortet {typ:'pos'}.
  var remote = false;
  var channel = null;
  if (typeof BroadcastChannel !== 'undefined') {
    channel = new BroadcastChannel('ic-praes-' + pid);
    channel.onmessage = function (ev) {
      var msg = ev.data || {};
      if (msg.typ === 'pos' && typeof msg.pos === 'number') {
        remote = true;
        goto(msg.pos);
        remote = false;
      }
    };
  }

  function fit(i) {
    if (nodes.length === 0) { return 0; }
    if (i < 0) { return 0; }
    if (i > nodes.length - 1) { return nodes.length - 1; }
    return i;
  }

  function goto(i) {
    current = fit(i);
    if (nowHolder) {
      while (nowHolder.firstChild) { nowHolder.removeChild(nowHolder.firstChild); }
      nowHolder.appendChild(nodes[current]);
    }
    if (nextHolder) {
      while (nextHolder.firstChild) { nextHolder.removeChild(nextHolder.firstChild); }
      if (nodes[current + 1]) { nextHolder.appendChild(nodes[current + 1]); }
      else {
        var end = document.createElement('p');
        end.className = 'muted';
        end.appendChild(document.createTextNode('Ende - letzte Folie.'));
        nextHolder.appendChild(end);
      }
    }
    if (counter) { counter.textContent = (current + 1) + ' / ' + nodes.length; }
    if (nowNum) { nowNum.textContent = 'Nr. ' + (current + 1); }
    if (noteEl) {
      var noteText = nodes[current].getAttribute('data-notes') || '';
      while (noteEl.firstChild) { noteEl.removeChild(noteEl.firstChild); }
      if (noteText !== '') {
        var lines = noteText.split(String.fromCharCode(10));
        for (var ln = 0; ln < lines.length; ln++) {
          if (ln > 0) { noteEl.appendChild(document.createElement('br')); }
          noteEl.appendChild(document.createTextNode(lines[ln]));
        }
      } else {
        noteEl.appendChild(document.createTextNode('(Keine Sprechernotiz für diese Folie)'));
      }
    }
    if (list) {
      var items = list.querySelectorAll('li');
      for (var k = 0; k < items.length; k++) {
        if (k === current) { items[k].classList.add('is-current'); }
        else { items[k].classList.remove('is-current'); }
      }
    }
    if (channel && !remote) { channel.postMessage({ typ: 'goto', pos: current }); }
  }

  // Folienliste aufbauen: Klick springt direkt zur Folie.
  if (list) {
    for (var p = 0; p < nodes.length; p++) {
      var li = document.createElement('li');
      var heading = nodes[p].querySelector('.slide-title');
      var text = (p + 1) + '. ';
      text += heading ? heading.textContent : 'Folie';
      li.appendChild(document.createTextNode(text));
      li.setAttribute('data-pos', String(p));
      li.addEventListener('click', function (ev) {
        goto(parseInt(ev.currentTarget.getAttribute('data-pos'), 10) || 0);
      });
      list.appendChild(li);
    }
  }

  if (prev) { prev.addEventListener('click', function () { goto(current - 1); }); }
  if (next) { next.addEventListener('click', function () { goto(current + 1); }); }
  if (openBtn) {
    openBtn.addEventListener('click', function () {
      window.open('<?= url('praesentationen/' . (int) $presentation->id . '/zeigen') ?>', '_blank');
    });
  }

  document.addEventListener('keydown', function (ev) {
    var tag = ev.target && ev.target.tagName ? ev.target.tagName.toUpperCase() : '';
    if (tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT') { return; }
    if (ev.key === 'ArrowRight' || ev.key === 'PageDown') {
      ev.preventDefault();
      goto(current + 1);
    } else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') {
      ev.preventDefault();
      goto(current - 1);
    } else if (ev.key === 'Home') {
      goto(0);
    } else if (ev.key === 'End') {
      goto(nodes.length - 1);
    }
  });

  // Timer und Uhr
  var seconds = 0;
  var running = false;
  var timerEl = document.getElementById('mod-timer');
  var clockEl = document.getElementById('mod-clock');
  var toggleBtn = document.getElementById('mod-timer-toggle');
  var resetBtn = document.getElementById('mod-timer-reset');

  function two(n) { return n < 10 ? '0' + n : '' + n; }

  function paintTimer() {
    if (timerEl) {
      var m = Math.floor(seconds / 60);
      timerEl.textContent = two(m) + ':' + two(seconds % 60);
    }
  }

  function paintClock() {
    if (clockEl) {
      var d = new Date();
      clockEl.textContent = two(d.getHours()) + ':' + two(d.getMinutes());
    }
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      running = !running;
      toggleBtn.textContent = running ? '⏸ Pause' : '▶ Start';
    });
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      running = false;
      seconds = 0;
      if (toggleBtn) { toggleBtn.textContent = '▶ Start'; }
      paintTimer();
    });
  }
  paintTimer();
  paintClock();
  window.setInterval(function () {
    paintClock();
    if (running) { seconds += 1; paintTimer(); }
  }, 1000);

  goto(0);
})();
</script>
