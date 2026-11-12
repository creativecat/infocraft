<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Präsentationen: Moderatorenbildschirm
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
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
            <div class="mod-note-box">
                <h4 class="mod-box-title">📝 Eigene Notiz zu dieser Folie <span class="pill">bleibt im Browser</span></h4>
                <textarea id="mod-own-note" class="mono" rows="4" placeholder="Didaktischer Kommentar, Zeitmarke, was du betonen willst ..." title="Deine Notiz wird pro Folie im Browser gespeichert (localStorage)"></textarea>
            </div>
            <div class="mod-note-box">
                <h4 class="mod-box-title">🙋 Fragenspeicher <span class="pill">Zwischenrufe</span></h4>
                <div style="display:flex;gap:6px;">
                    <input type="text" id="mod-question-input" placeholder="Frage aus dem Publikum ..." title="Zwischenrufe sammeln und am Stundenende auswerten">
                    <button class="btn btn-primary" type="button" id="mod-question-add" title="Frage merken">＋</button>
                </div>
                <ul class="mod-question-list" id="mod-question-list"></ul>
                <button class="btn btn-ghost" type="button" id="mod-question-clear" title="Alle Fragen löschen (z. B. nach der Auswertung)">🗑 alle löschen</button>
            </div>
            <div class="mod-note-box">
                <h4 class="mod-box-title">📊 Live-Abstimmung <span class="pill">Klassenzimmer</span></h4>
                <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/abstimmung/start') ?>" class="form" id="mod-poll-form">
                    <?= csrf_field() ?>
                    <label class="field"><span>Frage an die Klasse</span>
                        <input type="text" name="question" maxlength="300" placeholder="z. B. Welche Schleife passt hier?" required>
                    </label>
                    <label class="field"><span>Antworten (mindestens 2, bis 4)</span>
                        <input type="text" name="answers[]" maxlength="100" placeholder="Antwort 1" required>
                        <input type="text" name="answers[]" maxlength="100" placeholder="Antwort 2" required>
                        <input type="text" name="answers[]" maxlength="100" placeholder="Antwort 3 (optional)">
                        <input type="text" name="answers[]" maxlength="100" placeholder="Antwort 4 (optional)">
                    </label>
                    <div class="btn-row"><button class="btn btn-primary" type="submit" title="Abstimmung starten, die Schülerinnen und Schüler sehen sofort Antwortknöpfe">▶ starten</button></div>
                </form>
                <form method="post" action="<?= url('praesentationen/' . (int) $presentation->id . '/abstimmung/ende') ?>" class="form-inline" id="mod-poll-close" hidden>
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="Abstimmung schließen, Ergebnis bleibt sichtbar">⏹ schließen</button>
                </form>
                <div id="mod-poll-result" class="mod-poll-result"></div>
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

<script src="<?= vendor_asset('assets/vendor/highlight.min.js', 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.12.0/highlight.min.js', 100000) ?>"></script>
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
    paintOwnNote();
  }

  // Eigene Notiz je Folie (Issue #2): localStorage je Präsentations-ID und
  // Foliennummer, kein DB-Schema nötig. Wirkt wie ein Scribbel-Block.
  var ownNoteEl = document.getElementById('mod-own-note');
  function ownNoteKey(pos) { return 'ic-modnote-' + pid + '-' + pos; }
  function paintOwnNote() {
    if (!ownNoteEl) { return; }
    var value = '';
    try { value = window.localStorage.getItem(ownNoteKey(current)) || ''; } catch (e) { value = ''; }
    if (ownNoteEl.value !== value) { ownNoteEl.value = value; }
  }
  if (ownNoteEl) {
    ownNoteEl.addEventListener('input', function () {
      try { window.localStorage.setItem(ownNoteKey(current), ownNoteEl.value); } catch (e) { /* Speicher voll oder gesperrt */ }
    });
  }

  // Fragenspeicher (Issue #2): Zwischenrufe aus dem Publikum sammeln,
  // einzelne abhaken, alle löschen. Ebenfalls localStorage je Präsentation.
  var questionInput = document.getElementById('mod-question-input');
  var questionAdd = document.getElementById('mod-question-add');
  var questionList = document.getElementById('mod-question-list');
  var questionClear = document.getElementById('mod-question-clear');
  function questionKey() { return 'ic-modfragen-' + pid; }
  function readQuestions() {
    try { return JSON.parse(window.localStorage.getItem(questionKey()) || '[]'); }
    catch (e) { return []; }
  }
  function writeQuestions(items) {
    try { window.localStorage.setItem(questionKey(), JSON.stringify(items)); } catch (e) { /* ignoriert */ }
  }
  function renderQuestions() {
    if (!questionList) { return; }
    while (questionList.firstChild) { questionList.removeChild(questionList.firstChild); }
    var items = readQuestions();
    for (var qi = 0; qi < items.length; qi++) {
      var li = document.createElement('li');
      li.appendChild(document.createTextNode('Folie ' + (items[qi].pos + 1) + ': ' + items[qi].text));
      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'icon-btn';
      del.title = 'Frage erledigt';
      del.setAttribute('data-qi', String(qi));
      del.appendChild(document.createTextNode('✓'));
      li.appendChild(del);
      questionList.appendChild(li);
    }
  }
  function addQuestion() {
    if (!questionInput) { return; }
    var text = questionInput.value.replace(/^\s+|\s+$/g, '');
    if (text === '') { return; }
    var items = readQuestions();
    items.push({ pos: current, text: text });
    writeQuestions(items);
    questionInput.value = '';
    renderQuestions();
  }
  if (questionAdd) { questionAdd.addEventListener('click', addQuestion); }
  if (questionInput) {
    questionInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); addQuestion(); }
    });
  }
  if (questionList) {
    questionList.addEventListener('click', function (ev) {
      var target = ev.target;
      if (!target || !target.getAttribute || target.getAttribute('data-qi') === null) { return; }
      var idx = parseInt(target.getAttribute('data-qi'), 10);
      var items = readQuestions();
      if (idx >= 0 && idx < items.length) {
        items.splice(idx, 1);
        writeQuestions(items);
        renderQuestions();
      }
    });
  }
  if (questionClear) {
    questionClear.addEventListener('click', function () {
      writeQuestions([]);
      renderQuestions();
    });
  }
  renderQuestions();

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

  // Live-Abstimmung (Issue #3): Ergebnisbalken im Moderatorenbildschirm.
  var pollForm = document.getElementById('mod-poll-form');
  var pollClose = document.getElementById('mod-poll-close');
  var pollResult = document.getElementById('mod-poll-result');
  function pollUrl() { return '<?= url('praesentationen/' . (int) $presentation->id . '/abstimmung/status') ?>'; }
  function renderPoll(status) {
    if (!pollResult) { return; }
    while (pollResult.firstChild) { pollResult.removeChild(pollResult.firstChild); }
    if (!status || !status.poll) { return; }
    var poll = status.poll;
    var head = document.createElement('p');
    head.appendChild(document.createTextNode((status.active ? '🔴 läuft: ' : '✅ beendet: ') + poll.question));
    pollResult.appendChild(head);
    var maxCount = 1;
    for (var ci = 0; ci < poll.counts.length; ci++) { if (poll.counts[ci] > maxCount) { maxCount = poll.counts[ci]; } }
    for (var ai = 0; ai < poll.answers.length; ai++) {
      var row = document.createElement('div');
      row.className = 'poll-bar-row';
      var label = document.createElement('span');
      label.appendChild(document.createTextNode((ai + 1) + '. ' + poll.answers[ai]));
      row.appendChild(label);
      var barBox = document.createElement('div');
      barBox.className = 'poll-bar-box';
      var bar = document.createElement('div');
      bar.className = 'poll-bar';
      bar.style.width = Math.round((poll.counts[ai] / maxCount) * 100) + '%';
      barBox.appendChild(bar);
      row.appendChild(barBox);
      var count = document.createElement('span');
      count.appendChild(document.createTextNode(String(poll.counts[ai])));
      row.appendChild(count);
      pollResult.appendChild(row);
    }
    var total = document.createElement('p');
    total.className = 'muted';
    total.appendChild(document.createTextNode(poll.total + ' Stimme(n)'));
    pollResult.appendChild(total);
    if (pollForm && status.active) { pollForm.hidden = true; }
    if (pollForm && !status.active) { pollForm.hidden = false; }
    if (pollClose) { pollClose.hidden = !status.active; }
  }
  function refreshPoll() {
    fetch(pollUrl(), { credentials: 'same-origin' }).then(function (res) { return res.json(); }).then(function (data) {
      if (data && data.ok) { renderPoll(data); }
    }).catch(function () { /* Netzwerk weg: nächster Ticker versucht es wieder */ });
  }
  refreshPoll();
  window.setInterval(refreshPoll, 2000);
  if (pollClose) { pollClose.addEventListener('submit', function () { window.setTimeout(refreshPoll, 800); }); }

  goto(0);
})();
</script>
