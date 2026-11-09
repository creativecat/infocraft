/* InfoCraft: kleine UI-Helfer (Präsentationsmodus, Tabellenfilter, CSV-Beispiel) */
(function () {
  'use strict';

  // Präsentationsmodus: größere Ansicht, Auswahl bleibt pro Endgerät gespeichert.
  var root = document.documentElement;
  var key = 'infocraft.presenting';
  var toggle = document.getElementById('present-toggle');

  function syncLabel() {
    if (!toggle) { return; }
    var active = root.classList.contains('presenting');
    toggle.title = active ? 'Präsentationsmodus ist aktiv' : 'Präsentieren';
    toggle.setAttribute('aria-label', toggle.title);
  }

  if (localStorage.getItem(key) === '1') {
    root.classList.add('presenting');
  }
  syncLabel();

  if (toggle) {
    toggle.addEventListener('click', function () {
      var active = root.classList.toggle('presenting');
      localStorage.setItem(key, active ? '1' : '0');
      syncLabel();
    });
  }

  // Nutzerverwaltung: Tabelle live filtern.
  var filter = document.getElementById('user-filter');
  var table = document.getElementById('user-table');
  if (filter && table) {
    filter.addEventListener('input', function () {
      var query = filter.value.toLowerCase();
      var rows = table.querySelectorAll('tbody tr');
      for (var i = 0; i < rows.length; i++) {
        var hit = rows[i].textContent.toLowerCase().indexOf(query) !== -1;
        rows[i].style.display = hit ? '' : 'none';
      }
    });
  }

  // CSV-Import: Beispieltext einfügen.
  var fill = document.getElementById('fill-example');
  var csvText = document.getElementById('csv-text');
  if (fill && csvText) {
    fill.addEventListener('click', function () {
      csvText.value = [
        'Vorname;Nachname;Klasse;Rolle',
        'Mia;Sommer;6R;Schülerin',
        'Ben;Winter;;Lehrkraft'
      ].join('\n');
    });
  }
})();
/* InfoCraft Phase 2: Sidebar, Drag & Drop für Projekte, SQL-Editor */
(function () {
  'use strict';

  var body = document.body;

  // Sidebar: Klick oben links schaltet zwischen Icons mit Schrift und nur Icons
  // um. Die Wahl wird sofort lokal angewandt (ohne Flackern) und für das
  // Nutzerkonto auf dem Server gespeichert.
  var sidebarBtn = document.getElementById('sidebar-toggle');
  var sidebarKey = 'infocraft.sidebar';
  var sidebarStates = ['full', 'mini'];

  function applySidebar(state) {
    body.classList.remove('sidebar-mini');
    if (state === 'mini') { body.classList.add('sidebar-mini'); }
  }

  function persistSidebar(state) {
    localStorage.setItem(sidebarKey, state);
    var csrf = body.getAttribute('data-csrf');
    if (!csrf || !body.classList) { return; }
    try {
      var data = new FormData();
      data.append('csrf', csrf);
      data.append('view', state);
      fetch('/einstellungen/navigation', { method: 'POST', body: data, credentials: 'same-origin' });
    } catch (e) { /* Speichern ist optional, die Ansicht gilt trotzdem. */ }
  }

  var sidebarState = localStorage.getItem(sidebarKey) || 'full';
  if (sidebarStates.indexOf(sidebarState) === -1) { sidebarState = 'full'; }
  applySidebar(sidebarState);

  if (sidebarBtn) {
    sidebarBtn.addEventListener('click', function () {
      var index = (sidebarStates.indexOf(sidebarState) + 1) % sidebarStates.length;
      sidebarState = sidebarStates[index];
      persistSidebar(sidebarState);
      applySidebar(sidebarState);
    });
  }

  // Code-Werkstatt: Projekte per Drag & Drop in Ordner verschieben.
  var grid = document.querySelector('[data-move-url]');
  if (grid) {
    var moveUrl = grid.getAttribute('data-move-url');
    var folderMoveUrl = grid.getAttribute('data-folder-move-url');
    var dragId = null;
    var dragFolderId = null;

    document.addEventListener('dragstart', function (e) {
      var card = e.target && e.target.closest ? e.target.closest('.project-card') : null;
      if (card) { dragId = card.getAttribute('data-id'); dragFolderId = null; return; }
      /* Ordner verschieben (#45): Der Ordner-Eintrag selbst ist ziehbar. */
      var li = e.target && e.target.closest ? e.target.closest('.folder-item[data-folder-drag]') : null;
      if (li) {
        dragFolderId = li.getAttribute('data-folder-drag');
        dragId = null;
        if (e.dataTransfer) { e.dataTransfer.setData('text/plain', 'folder:' + dragFolderId); }
      }
    });
    document.addEventListener('dragend', function () { dragId = null; dragFolderId = null; });

    var folders = document.querySelectorAll('.folder-item');
    for (var i = 0; i < folders.length; i++) {
      (function (item) {
        item.addEventListener('dragover', function (e) {
          e.preventDefault();
          item.classList.add('drop-hover');
        });
        item.addEventListener('dragleave', function () {
          item.classList.remove('drop-hover');
        });
        item.addEventListener('drop', function (e) {
          e.preventDefault();
          item.classList.remove('drop-hover');
          var csrf = body.getAttribute('data-csrf') || '';
          /* Ordner auf Ordner (#45): Zielordner holen und Verschieben anfragen. */
          if (dragFolderId) {
            if (!folderMoveUrl || dragFolderId === item.getAttribute('data-folder')) { return; }
            fetch(folderMoveUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'folder_id=' + encodeURIComponent(dragFolderId)
                + '&parent_id=' + encodeURIComponent(item.getAttribute('data-folder'))
                + '&csrf=' + encodeURIComponent(csrf)
            }).then(function (response) { return response.json(); }).then(function (data) {
              if (data && data.ok) {
                window.location.reload();
              } else {
                alert((data && data.error) || 'Verschieben fehlgeschlagen.');
              }
            }).catch(function () {
              alert('Verschieben fehlgeschlagen.');
            });
            return;
          }
          var id = dragId || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : null);
          if (!id || !/^[0-9]+$/.test(String(id))) { return; }
          fetch(moveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'project_id=' + encodeURIComponent(id)
              + '&folder_id=' + encodeURIComponent(item.getAttribute('data-folder'))
              + '&csrf=' + encodeURIComponent(csrf)
          }).then(function (response) { return response.json(); }).then(function (data) {
            if (data && data.ok) {
              window.location.reload();
            } else {
              alert((data && data.error) || 'Verschieben fehlgeschlagen.');
            }
          }).catch(function () {
            alert('Verschieben fehlgeschlagen.');
          });
        });
      })(folders[i]);
    }
  }

  // SQL-Werkstatt: Strg + Enter führt die Anweisung aus.
  var sqlForm = document.getElementById('sql-form');
  if (sqlForm) {
    sqlForm.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        sqlForm.submit();
      }
    });
  }
})();
/* InfoCraft Phase 3: HTML-Werkstatt – Gestalten (WYSIWYG), Quelltext, Vorschau,
   Vorschau in neuem Fenster, HTML öffnen/herunterladen, Tag-Erklärungen */
(function () {
  'use strict';

  var htmlEditor = document.getElementById('html-editor');
  var htmlPreview = document.getElementById('html-preview');
  var wyFrame = document.getElementById('html-wysiwyg');
  var saveForm = document.getElementById('html-form');
  if (!htmlEditor || !htmlPreview || !saveForm || !wyFrame) { return; }

  var wyDoc = null;
  var activeTab = 'wy';
  var ioNote = document.getElementById('html-io-note');

  function note(text) {
    if (ioNote) { ioNote.textContent = text; }
  }

  function updatePreview() {
    htmlPreview.srcdoc = htmlEditor.value;
  }

  /* WYSIWYG: die ganze Seite in den iframe laden (Skripte laufen dort nicht, sandbox ohne allow-scripts). */
  function wyLoad() {
    wyDoc = null;
    wyFrame.srcdoc = htmlEditor.value;
  }

  wyFrame.addEventListener('load', function () {
    try { wyDoc = wyFrame.contentDocument; } catch (err) { wyDoc = null; }
    if (wyDoc) { wyDoc.designMode = 'on'; }
  });

  /* Zurück in die Textarea: nur den Bereich zwischen <body...> und </body> ersetzen (per indexOf, ohne Regex). */
  function wySave() {
    if (!wyDoc || !wyDoc.body) { return; }
    var inner = wyDoc.body.innerHTML;
    var src = htmlEditor.value;
    var low = src.toLowerCase();
    var ob = low.indexOf('<body');
    var cb = low.lastIndexOf('</body>');
    if (ob === -1 || cb === -1 || cb < ob) { return; }
    var gt = src.indexOf('>', ob);
    if (gt === -1 || gt > cb) { return; }
    htmlEditor.value = src.slice(0, gt + 1) + inner + src.slice(cb);
    updatePreview();
  }

  /* Tabs: Gestalten / Quelltext / Vorschau */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.vw-tab[data-vtab]'));
  var panes = Array.prototype.slice.call(document.querySelectorAll('[data-vpane]'));
  function setTab(name) {
    if (activeTab === 'wy' && name !== 'wy') { wySave(); }
    activeTab = name;
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-vtab') === name;
      t.classList.toggle('vw-on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panes.forEach(function (p) {
      if (p.getAttribute('data-vpane') === name) { p.removeAttribute('hidden'); }
      else { p.setAttribute('hidden', ''); }
    });
    if (name === 'wy') { wyLoad(); }
    if (name === 'view') { updatePreview(); }
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { setTab(t.getAttribute('data-vtab')); });
  });
  setTab('wy');

  /* Sofortvorschau beim Tippen im Quelltext */
  var previewTimer = null;
  htmlEditor.addEventListener('input', function () {
    if (previewTimer) { clearTimeout(previewTimer); }
    previewTimer = setTimeout(updatePreview, 400);
  });

  /* WYSIWYG-Werkzeuge: Absatz, F/K/U, Listen, Farbe, Link, Rückgängig */
  function wyExec(cmd, val) {
    if (!wyDoc) { return; }
    if (wyFrame.contentWindow) { wyFrame.contentWindow.focus(); }
    wyDoc.execCommand(cmd, false, val || null);
  }
  var wyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-wycmd]'));
  wyButtons.forEach(function (b) {
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function (e) {
      e.preventDefault();
      var cmd = b.getAttribute('data-wycmd');
      if (cmd === 'createLink') {
        var ziel = window.prompt('Wohin soll der Link führen? (z. B. https://www.wikipedia.de)', 'https://');
        if (ziel && ziel !== 'https://') { wyExec('createLink', ziel); }
        return;
      }
      wyExec(cmd);
    });
  });
  var wyBlock = document.getElementById('wy-block');
  if (wyBlock) {
    wyBlock.addEventListener('change', function () {
      if (wyBlock.value) { wyExec('formatBlock', '<' + wyBlock.value + '>'); }
    });
  }
  var wyColor = document.getElementById('wy-color');
  if (wyColor) {
    wyColor.addEventListener('input', function () { wyExec('foreColor', wyColor.value); });
  }

  /* HTML öffnen (hochladen): Datei in die Textarea laden */
  var uploadBtn = document.getElementById('html-upload-btn');
  var uploadInput = document.getElementById('html-upload');
  if (uploadBtn && uploadInput) {
    uploadBtn.addEventListener('click', function () { uploadInput.click(); });
    uploadInput.addEventListener('change', function () {
      var file = uploadInput.files && uploadInput.files[0];
      if (!file) { return; }
      var reader = new FileReader();
      reader.onload = function () {
        htmlEditor.value = String(reader.result || '');
        updatePreview();
        setTab('src');
        note('Datei geladen: ' + file.name + '. Schau sie dir im Quelltext an und speichere dann mit dem Speichern-Knopf in InfoCraft.');
      };
      reader.readAsText(file);
      uploadInput.value = '';
    });
  }

  /* HTML herunterladen: Dateiname aus dem Titel (Umlaute und Sonderzeichen zu Bindestrichen) */
  var dlBtn = document.getElementById('html-download');
  var titleInput = saveForm.querySelector('input[name="title"]');
  if (dlBtn) {
    dlBtn.addEventListener('click', function () {
      if (activeTab === 'wy') { wySave(); }
      var base = (titleInput && titleInput.value ? titleInput.value : 'webseite').toLowerCase();
      base = base.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');
      base = base.replace(/[^a-z0-9]+/g, '-');
      while (base.charAt(0) === '-') { base = base.slice(1); }
      while (base.length > 0 && base.charAt(base.length - 1) === '-') { base = base.slice(0, base.length - 1); }
      if (!base) { base = 'webseite'; }
      var blob = new Blob([htmlEditor.value], { type: 'text/html' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = base + '.html';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
      note('HTML-Datei heruntergeladen. Mit dem Öffnen-Knopf kannst du sie später wieder hochladen.');
    });
  }

  /* Vorschau in neuem Fenster */
  var newWinBtn = document.getElementById('html-newwin');
  if (newWinBtn) {
    newWinBtn.addEventListener('click', function () {
      if (activeTab === 'wy') { wySave(); }
      var blob = new Blob([htmlEditor.value], { type: 'text/html' });
      window.open(URL.createObjectURL(blob), '_blank');
      note('Vorschau in neuem Fenster geöffnet.');
    });
  }

  /* Tag-Erklärungen: Karte zum gewählten Tag zeigen */
  var tagSelect = document.getElementById('tag-explain');
  var tagCards = Array.prototype.slice.call(document.querySelectorAll('[data-tag-card]'));
  if (tagSelect && tagCards.length > 0) {
    tagSelect.addEventListener('change', function () {
      var v = tagSelect.value;
      tagCards.forEach(function (card) {
        if (card.getAttribute('data-tag-card') === v) { card.removeAttribute('hidden'); }
        else { card.setAttribute('hidden', ''); }
      });
    });
  }

  /* Speichern: Gestalten-Inhalt vor dem Speichern zurück in die Textarea */
  saveForm.addEventListener('submit', function () {
    if (activeTab === 'wy') { wySave(); }
  });
  saveForm.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      if (activeTab === 'wy') { wySave(); }
      saveForm.submit();
    }
  });
})();
/* InfoCraft Phase 4: Python direkt im Browser ausführen (Pyodide) */
(function () {
  'use strict';

  var runBtn = document.getElementById('run-python');
  var editor = document.getElementById('code-editor');
  var output = document.getElementById('code-output');
  if (!runBtn || !editor || !output) { return; }

  var pyodidePromise = null;

  function ensurePyodide() {
    if (pyodidePromise) { return pyodidePromise; }
    pyodidePromise = new Promise(function (resolve, reject) {
      if (window.loadPyodide) { resolve(window.loadPyodide()); return; }
      var script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/pyodide.js';
      script.onload = function () { resolve(window.loadPyodide()); };
      script.onerror = function () { reject(new Error('Pyodide konnte nicht geladen werden (Internetverbindung nötig).')); };
      document.head.appendChild(script);
    });
    return pyodidePromise;
  }

  function run() {
    output.textContent = 'Python wird geladen ... (beim ersten Start kann das etwas dauern)';
    ensurePyodide().then(function (pyodide) {
      var lines = [];
      pyodide.setStdout({ batched: function (text) { lines.push(text); } });
      pyodide.setStderr({ batched: function (text) { lines.push(text); } });
      output.textContent = 'Programm läuft ...';
      return pyodide.runPythonAsync(editor.value).then(function () {
        output.textContent = lines.length > 0 ? lines.join('\n') : '(keine Ausgabe)';
      });
    }).catch(function (err) {
      output.textContent = 'Fehler:\n' + (err && err.message ? err.message : String(err));
    });
  }

  runBtn.addEventListener('click', run);
  editor.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      run();
    }
  });
})();


// ===== Prüfungsmodus: Warnung vor dem Verlassen und automatisches Speichern =====
(function () {
  'use strict';
  var body = document.body;
  if (!body || !body.hasAttribute('data-exam-lock')) {
    return;
  }
  // Ein abgeschicktes Formular (Abgabe, Abmelden) ist keine Verlust-Warnung wert.
  document.addEventListener('submit', function () {
    body.removeAttribute('data-exam-lock');
  });
  window.addEventListener('beforeunload', function (e) {
    if (document.body.hasAttribute('data-exam-lock')) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
})();
// Automatisches Speichern der Teilaufgaben-Antworten (Prüfungen und Schularbeiten).
// Der Endpunkt inklusive Prüfungs-ID steht am Aufgabenbereich (data-exam-save).
(function () {
  'use strict';
  var host = document.querySelector('[data-exam-save]');
  var csrf = document.body.getAttribute('data-csrf');
  if (!host || !csrf) {
    return;
  }
  var saveUrl = host.getAttribute('data-exam-save');
  document.querySelectorAll('textarea.ex-answer').forEach(function (area) {
    var taskId = area.getAttribute('data-task');
    var status = document.getElementById('ex-status-' + taskId);
    var timer = null;
    area.addEventListener('input', function () {
      if (timer) { clearTimeout(timer); }
      timer = setTimeout(function () {
        var data = new URLSearchParams();
        data.append('csrf', csrf);
        data.append('task_id', taskId);
        data.append('answer', area.value);
        if (status) { status.textContent = 'Speichern ...'; }
        fetch(saveUrl, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        }).then(function (res) { return res.json(); }).then(function (json) {
          if (status) {
            var now = new Date();
            var hh = ('0' + now.getHours()).slice(-2);
            var mm = ('0' + now.getMinutes()).slice(-2);
            status.textContent = json.ok ? 'Automatisch gespeichert (' + hh + ':' + mm + ')' : ('Nicht gespeichert: ' + (json.error || 'Fehler'));
          }
        }).catch(function () {
          if (status) { status.textContent = 'Nicht gespeichert (offline?)'; }
        });
      }, 1000);
    });
  });
})();

/* ===== Diagramm-Editor: Typ-Buttons umschalten (sofort gespeichert), Klammern schliessen automatisch ===== */
(function () {
  'use strict';
  var picker = document.querySelector('[data-kind-switch]');
  if (picker) {
    var form = picker.closest('form');
    var radios = picker.querySelectorAll('input[name="kind"]');
    var helps = form ? form.querySelectorAll('[data-help-kind]') : [];
    var labels = form ? form.querySelectorAll('[data-label-kind]') : [];
    var wrap = form ? form.querySelector('[data-hl]') : null;
    var HL_LANGS = { struktogramm: 'struktogramm', manuell: 'mermaid', datenfluss: 'datenfluss' };
    function applyKind() {
      var current = '';
      radios.forEach(function (r) { if (r.checked) { current = r.value; } });
      helps.forEach(function (b) { b.hidden = b.getAttribute('data-help-kind') !== current; });
      labels.forEach(function (l) { l.hidden = l.getAttribute('data-label-kind') !== current; });
      if (wrap) {
        wrap.dispatchEvent(new CustomEvent('diagramhllanguage', { detail: { lang: HL_LANGS[current] || 'mermaid' } }));
      }
      /* Der Rest der Seite (Vorschau, Buttons, Autosave) hoert auf dieses Event. */
      picker.dispatchEvent(new CustomEvent('diagramkindchange', { bubbles: true, detail: { kind: current } }));
    }
    radios.forEach(function (r) { r.addEventListener('change', applyKind); });
    applyKind();
  }

  var area = document.getElementById('diagram-source');
  if (!area) { return; }
  var openers = { '(': ')', '[': ']', '{': '}' };
  var closers = { ')': '(', ']': '[', '}': '{' };
  function fireInput() {
    area.dispatchEvent(new Event('input', { bubbles: true }));
  }
  area.addEventListener('keydown', function (ev) {
    if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
    var start = area.selectionStart;
    var end = area.selectionEnd;
    if (openers[ev.key]) {
      ev.preventDefault();
      var inner = area.value.slice(start, end);
      area.value = area.value.slice(0, start) + ev.key + inner + openers[ev.key] + area.value.slice(end);
      area.selectionStart = start + 1;
      area.selectionEnd = start + 1 + inner.length;
      fireInput();
    } else if (closers[ev.key] && area.value.charAt(end) === ev.key) {
      /* schliessende Klammer direkt daneben? Cursor springt drueber statt doppelt */
      ev.preventDefault();
      area.selectionStart = area.selectionEnd = end + 1;
    }
  });
})();

/* ===== Diagramm-Werkbank: Live-Vorschau beim Tippen, Autosave, Typwechsel, Vollbild ===== */
(function () {
  'use strict';
  var page = document.querySelector('[data-diagram-page]');
  if (!page) { return; }
  var source = document.getElementById('diagram-source');
  var form = document.getElementById('diagram-form');
  var refreshBtn = document.getElementById('diagram-refresh');
  var pngBtn = document.getElementById('diagram-png');
  var fitBtn = document.getElementById('diagram-fit');
  var fsBtn = document.getElementById('diagram-fullscreen');
  var statusEl = document.getElementById('diagram-status');
  var saveStatusEl = document.getElementById('diagram-savestatus');
  var renderTimer = null;
  var saveTimer = null;
  var seq = 0;
  var currentKind = page.getAttribute('data-diagram-kind') || 'manuell';
  var lastGoodSvg = null;
  var lastGoodNassi = null;

  function escapeText(text) {
    return String(text).replace(/[&<>]/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch] || ch;
    });
  }

  function setStatus(msg) {
    if (statusEl) { statusEl.textContent = msg; statusEl.hidden = false; }
  }

  function hideStatus() {
    if (statusEl) { statusEl.hidden = true; }
  }

  /* ---------- Struktogramm (Port von src/DiagramDsl.php) ---------- */

  function strukturText(text) {
    var t = String(text).trim();
    if (t.length >= 2 && t.charAt(0) === '"' && t.charAt(t.length - 1) === '"') {
      t = t.slice(1, -1);
    }
    return t.trim();
  }

  function parseStruktogramm(src) {
    var lines = [];
    String(src).split(/\r\n|\r|\n/).forEach(function (raw, index) {
      var t = raw.trim();
      if (t === '' || t.indexOf('%%') === 0 || t.indexOf('//') === 0) { return; }
      if (lines.length === 0 && t.toLowerCase().indexOf('struktogramm') === 0) { return; }
      var parts = t.split(/\s+/);
      lines.push({ ln: index + 1, kw: parts[0].toLowerCase(), rest: parts.slice(1).join(' ').trim() });
    });
    var pos = { i: 0 };
    var result = parseStrukturSeq(lines, pos, []);
    if (result.error) { return { error: result.error, root: null }; }
    if (result.items.length === 0) {
      return { error: 'Das Struktogramm ist leer. Beginne zum Beispiel mit: anweisung "Erster Schritt"', root: null };
    }
    return { error: '', root: { type: 'seq', items: result.items } };
  }

  function parseStrukturSeq(lines, pos, terminators) {
    var items = [];
    while (pos.i < lines.length) {
      var line = lines[pos.i];
      var kw = line.kw;
      if (terminators.indexOf(kw) !== -1) {
        return { items: items, term: kw, error: '' };
      }
      if (kw === 'anweisung') {
        items.push({ type: 'stmt', text: strukturText(line.rest) });
        pos.i += 1;
        continue;
      }
      if (kw === 'wenn') {
        var cond = line.rest;
        if (cond === '' || !/\s*dann$/i.test(cond)) {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Bei wenn fehlt die Bedingung mit dann, z. B. wenn "punkte >= 90" dann' };
        }
        cond = cond.replace(/\s*dann$/i, '').trim();
        pos.i += 1;
        var truePart = parseStrukturSeq(lines, pos, ['sonst', 'ende']);
        if (truePart.error) { return truePart; }
        if (truePart.term === null) {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Der wenn-Block muss mit ende geschlossen werden.' };
        }
        var falseItems = [];
        if (truePart.term === 'sonst') {
          pos.i += 1;
          var falsePart = parseStrukturSeq(lines, pos, ['ende']);
          if (falsePart.error) { return falsePart; }
          if (falsePart.term !== 'ende') {
            return { items: items, term: null, error: 'Zeile ' + line.ln + ': Der sonst-Zweig muss mit ende geschlossen werden.' };
          }
          falseItems = falsePart.items;
        }
        pos.i += 1; /* ende verbrauchen */
        items.push({ type: 'if', cond: strukturText(cond), true: truePart.items, false: falseItems });
        continue;
      }
      if (kw === 'solange') {
        var wCond = line.rest;
        if (wCond === '') {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Bei solange fehlt die Bedingung, z. B. solange "summe < 10"' };
        }
        pos.i += 1;
        var body = parseStrukturSeq(lines, pos, ['ende']);
        if (body.error) { return body; }
        if (body.term !== 'ende') {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Der solange-Block muss mit ende geschlossen werden.' };
        }
        pos.i += 1; /* ende verbrauchen */
        items.push({ type: 'while', cond: strukturText(wCond), body: body.items });
        continue;
      }
      if (kw === 'wiederhole') {
        pos.i += 1;
        var rBody = parseStrukturSeq(lines, pos, ['bis']);
        if (rBody.error) { return rBody; }
        if (rBody.term !== 'bis') {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Der wiederhole-Block muss mit bis "Bedingung" geschlossen werden.' };
        }
        var bCond = lines[pos.i].rest;
        pos.i += 1; /* bis-Zeile verbrauchen */
        if (bCond === '') {
          return { items: items, term: null, error: 'Zeile ' + line.ln + ': Bei bis fehlt die Bedingung, z. B. bis "teig glatt"' };
        }
        items.push({ type: 'until', cond: strukturText(bCond), body: rBody.items });
        continue;
      }
      if (kw === 'ende' || kw === 'sonst' || kw === 'bis') {
        var want = terminators.length === 0
          ? 'eine neue Anweisung (anweisung, wenn, solange, wiederhole)'
          : '"' + terminators.join('" oder "') + '"';
        return { items: items, term: null, error: 'Zeile ' + line.ln + ': "' + kw + '" passt hier nicht, hier gehört ' + want + ' hin.' };
      }
      return { items: items, term: null, error: 'Zeile ' + line.ln + ': Unbekannte Anweisung "' + line.kw + '". Erlaubt sind: anweisung, wenn/dann/sonst, solange, wiederhole/bis, ende.' };
    }
    return { items: items, term: null, error: '' };
  }

  function renderNassiSeq(items) {
    if (!items.length) { return '<div class="nassi-empty"></div>'; }
    var html = '<div class="nassi-seq">';
    items.forEach(function (item) { html += renderNassiItem(item); });
    return html + '</div>';
  }

  function renderNassiItem(item) {
    if (item.type === 'stmt') {
      return '<div class="nassi-stmt">' + (item.text !== '' ? escapeText(item.text) : '&nbsp;') + '</div>';
    }
    if (item.type === 'if') {
      return '<div class="nassi-if"><div class="nassi-cond"><span class="nassi-tri"></span>' + escapeText(item.cond) + '</div><div class="nassi-branches">'
        + '<div class="nassi-branch"><span class="nassi-branch-label">wahr</span>' + renderNassiSeq(item.true) + '</div>'
        + '<div class="nassi-branch"><span class="nassi-branch-label">falsch</span>' + renderNassiSeq(item.false) + '</div></div></div>';
    }
    if (item.type === 'while') {
      return '<div class="nassi-loop nassi-while"><div class="nassi-loop-head">' + escapeText(item.cond) + '</div><div class="nassi-loop-body">' + renderNassiSeq(item.body) + '</div></div>';
    }
    return '<div class="nassi-loop nassi-until"><div class="nassi-loop-body">' + renderNassiSeq(item.body) + '</div><div class="nassi-loop-head">' + escapeText(item.cond) + '</div></div>';
  }

  function renderNassi(root) {
    var html = '<div class="nassi"><div class="nassi-seq">';
    root.items.forEach(function (item) { html += renderNassiItem(item); });
    return html + '</div></div>';
  }

  /* ---------- Datenfluss-DSL (wie src/DiagramDsl.php) fuer die Live-Vorschau ---------- */

  function dfFull(re, line) {
    var m = line.match(re);
    if (!m || m.index !== 0 || m[0].length !== line.length) { return null; }
    return m;
  }

  function dfClean(text) {
    return String(text).replace(/["\[\]()<>|]/g, '');
  }

  function transpileDatenfluss(src) {
    var NL = String.fromCharCode(10);
    var node = '(?:(quelle|senke|speicher)\\s+"([^"]*)"|\\(([A-Za-z_][A-Za-z0-9_]*)\\))';
    var reEdgeLabel = new RegExp(node + '\\s*-->\\s*\\|([^|]*)\\|\\s*' + node);
    var reEdge = new RegExp(node + '\\s*-->\\s*' + node);
    var reNode = new RegExp(node);
    var ids = {};
    var order = 0;
    var shapes = [];
    var edges = [];

    function register(m, off) {
      var isExt = m[off] !== undefined && m[off] !== '';
      var shapeKind = isExt ? m[off] : 'funktion';
      var text = isExt ? m[off + 1] : m[off + 2];
      var key = shapeKind + ':' + text;
      if (!Object.prototype.hasOwnProperty.call(ids, key)) {
        order += 1;
        ids[key] = 'N' + order;
        if (shapeKind === 'funktion') {
          shapes.push('N' + order + '(("' + dfClean(text) + '"))');
        } else if (shapeKind === 'speicher') {
          shapes.push('N' + order + '[("' + dfClean(text) + '")]');
        } else {
          shapes.push('N' + order + '["' + dfClean(text) + '"]');
        }
      }
      return ids[key];
    }

    var lines = String(src).split(NL);
    for (var i = 0; i < lines.length; i++) {
      var t = lines[i].trim();
      if (t === '' || t.indexOf('%%') === 0 || t.indexOf('//') === 0) { continue; }
      if (t.toLowerCase().indexOf('datenfluss') === 0) { continue; }
      var m = dfFull(reEdgeLabel, t);
      if (m) {
        var from = register(m, 1);
        var to = register(m, 5);
        edges.push(from + ' -->|' + dfClean(m[4]) + '| ' + to);
        continue;
      }
      m = dfFull(reEdge, t);
      if (m) {
        edges.push(register(m, 1) + ' --> ' + register(m, 4));
        continue;
      }
      m = dfFull(reNode, t);
      if (m) {
        register(m, 1);
        continue;
      }
      return { error: 'Zeile ' + (i + 1) + ': "' + t + '" verstehe ich nicht. Erlaubt sind: quelle "Name", senke "Name", speicher "Name", (funktionsName) und Pfeile --> bzw. -->|Daten|.', mermaid: '' };
    }
    if (order === 0) {
      return { error: 'Das Datenflussdiagramm ist leer. Beginne zum Beispiel mit: quelle "Eingabe" --> (verarbeiten)', mermaid: '' };
    }
    var out = ['flowchart TB'].concat(shapes, edges);
    return { error: '', mermaid: out.join(NL) };
  }

  /* ---------- Vorschau-Container je Typ ---------- */

  function previewHost() {
    return statusEl || page;
  }

  function nassiBox(create) {
    var box = document.getElementById('nassi-preview');
    if (!box && create !== false) {
      box = document.createElement('div');
      box.className = 'nassi-wrap';
      box.id = 'nassi-preview';
      previewHost().parentNode.insertBefore(box, previewHost());
    }
    return box;
  }

  function mermaidBox(create) {
    var box = document.getElementById('diagram-preview');
    if (!box && create !== false) {
      box = document.createElement('div');
      box.className = 'diagram-preview';
      box.id = 'diagram-preview';
      previewHost().parentNode.insertBefore(box, previewHost());
    }
    return box;
  }

  /* Fehler der Diagramm-Bibliothek saeubern: keine internen Namen und
     Versionsnummern im Gesichtsfeld der Schuelerinnen und Schueler. */
  function cleanError(msg) {
    var text = String(msg || 'Unbekannter Fehler im Diagramm-Quelltext.');
    text = text.replace(/mermaid/gi, 'Diagramm');
    text = text.replace(/\s*version\s+[0-9][0-9.]*/gi, '');
    return text;
  }

  /* Fehler aus dem Quelltext: die betroffene Zeile in den Zeilennummern rot
     markieren (Modul am Dateiende stellt die Zeilennummern fuer alle Felder). */
  function markSourceError(msg) {
    if (!window.IC_GUTTER || !source) { return; }
    var m = /Zeile (\d+)/.exec(String(msg || ''));
    if (m) { window.IC_GUTTER.markError(source, parseInt(m[1], 10)); }
  }
  function clearSourceError() {
    if (!window.IC_GUTTER || !source) { return; }
    window.IC_GUTTER.clearError(source);
  }

  function render(isAuto) {
    if (!source) { return; }
    var text = source.value;
    if (currentKind === 'struktogramm') {
      var box = nassiBox();
      if (!box) { return; }
      var parsed = parseStruktogramm(text);
      if (parsed.error) {
        markSourceError(parsed.error);
        if (isAuto && lastGoodNassi !== null) {
          box.innerHTML = lastGoodNassi;
          setStatus('Vorschau pausiert, bis der Quelltext wieder gezeichnet werden kann.');
          return;
        }
        box.innerHTML = '<div class="notice"><strong>Struktogramm kann nicht gezeichnet werden</strong><p>' + escapeText(parsed.error) + '</p></div>';
        lastGoodNassi = null;
        return;
      }
      lastGoodNassi = renderNassi(parsed.root);
      box.innerHTML = lastGoodNassi;
      clearSourceError();
      hideStatus();
      return;
    }
    var target = mermaidBox();
    if (!target) { return; }
    if (!window.mermaid || typeof window.mermaid.render !== 'function') {
      target.innerHTML = '<p class="muted">Vorschau offline: Die Diagramm-Bibliothek konnte nicht geladen werden. Der Quelltext bleibt speicherbar.</p>';
      return;
    }
    if (currentKind === 'datenfluss') {
      var t = transpileDatenfluss(text);
      if (t.error) {
        markSourceError(t.error);
        /* Waehrend des Tippens bleibt die letzte gueltige Zeichnung stehen,
           statt eine rote Fehlermeldung ueber halbfertige Eingaben zu zeigen. */
        if (isAuto && lastGoodSvg !== null) {
          target.innerHTML = lastGoodSvg;
          setStatus('Vorschau pausiert, bis der Quelltext wieder gezeichnet werden kann.');
          return;
        }
        target.innerHTML = '<div class="notice"><strong>Datenflussdiagramm kann nicht gezeichnet werden</strong><p>' + escapeText(t.error) + '</p></div>';
        return;
      }
      text = t.mermaid;
    } else {
      /* Klassendiagramm ohne sichtbare Typzeile (#56): Vor dem Zeichnen wird
         eine eventuell noch vorhandene alte Typzeile entfernt und danach
         genau eine saubere classDiagram-Zeile wiederhergestellt. Der Rest des
         Quelltexts bleibt unangetastet. */
      text = restoreClassDiagramHeader(text);
    }
    seq += 1;
    window.mermaid.render('dg-svg-' + seq, text).then(function (out) {
      target.innerHTML = out.svg;
      if (currentKind === 'manuell') {
        /* Rein kosmetische Nachbearbeitung: Sie darf das fertige SVG nie
           ueber den Fehlerpfad ersetzen (#56). */
        try { styleClassDiagramSvg(target); } catch (eStyle) { /* Optik optional */ }
      }
      lastGoodSvg = target.innerHTML;
      clearSourceError();
      hideStatus();
    }).catch(function (err) {
      var msg = cleanError(err && err.message ? err.message : '');
      if (isAuto && lastGoodSvg !== null) {
        target.innerHTML = lastGoodSvg;
        setStatus('Vorschau pausiert, bis der Quelltext wieder gezeichnet werden kann.');
        return;
      }
      target.innerHTML = '<div class="notice"><strong>Diagramm kann nicht gezeichnet werden</strong><p>' + escapeText(msg) + '</p></div>';
    });
  }

  function exportPng() {
    var target = document.getElementById('diagram-preview');
    if (!target) { return; }
    var svg = target.querySelector('svg');
    if (!svg) { return; }
    var viewBox = (svg.getAttribute('viewBox') || '0 0 800 600').split(/\s+/).map(Number);
    var width = Math.max(200, Math.round(viewBox[2] || 800));
    var height = Math.max(200, Math.round(viewBox[3] || 600));
    var clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', width);
    clone.setAttribute('height', height);
    var data = new XMLSerializer().serializeToString(clone);
    var img = new Image();
    img.onload = function () {
      var canvas = document.createElement('canvas');
      canvas.width = width * 2;
      canvas.height = height * 2;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      var link = document.createElement('a');
      var name = pngBtn && pngBtn.getAttribute('data-png-name') ? pngBtn.getAttribute('data-png-name') : 'diagramm';
      link.download = name + '.png';
      link.href = canvas.toDataURL('image/png');
      link.click();
    };
    img.onerror = function () {
      window.alert('PNG-Export fehlgeschlagen. Der Diagramm-Quelltext bleibt kopierbar.');
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
  }

  /* ---------- Automatisch speichern (beim Tippen und beim Typwechsel sofort) ---------- */

  function saveNow(reason) {
    if (!form) { return; }
    var data = new FormData(form);
    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    }).then(function (res) {
      if (!res.ok) { throw new Error('HTTP ' + res.status); }
      if (saveStatusEl) {
        var now = new Date();
        var hh = ('0' + now.getHours()).slice(-2);
        var mm = ('0' + now.getMinutes()).slice(-2);
        saveStatusEl.textContent = (reason === 'kind' ? 'Typ gespeichert, ' : 'Automatisch gespeichert ') + hh + ':' + mm;
        saveStatusEl.hidden = false;
      }
    }).catch(function () {
      if (saveStatusEl) {
        saveStatusEl.textContent = 'Nicht gespeichert (offline?)';
        saveStatusEl.hidden = false;
      }
    });
  }

  /* ---------- Typwechsel: Vorschau und Buttons sofort umschalten ---------- */

  var KIND_LABELS = { struktogramm: 'Struktogramm', manuell: 'Klassendiagramm', datenfluss: 'Datenflussdiagramm' };

  function applyKind(kind) {
    currentKind = kind;
    page.setAttribute('data-diagram-kind', kind);
    var pill = document.getElementById('diagram-kind-pill');
    if (pill) { pill.textContent = KIND_LABELS[kind] || 'Diagramm'; }
    var title = document.getElementById('diagram-preview-title');
    if (title) { title.textContent = 'Vorschau' + (kind === 'struktogramm' ? ' (Struktogramm)' : ''); }
    var isStruktur = kind === 'struktogramm';
    if (fitBtn) { fitBtn.hidden = isStruktur; }
    if (pngBtn) { pngBtn.hidden = isStruktur; }
    var mBox = mermaidBox(false);
    var nBox = nassiBox(false);
    if (isStruktur) {
      if (mBox) { mBox.hidden = true; }
      nassiBox().hidden = false;
    } else {
      if (nBox) { nBox.hidden = true; }
      mermaidBox().hidden = false;
    }
    lastGoodSvg = null;
    lastGoodNassi = null;
  }

  document.addEventListener('diagramkindchange', function (ev) {
    var kind = ev.detail && ev.detail.kind;
    if (!kind || !KIND_LABELS[kind]) { return; }
    applyKind(kind);
    render(false);
    saveNow('kind');
  });

  /* ---------- Eingabe: Typzeile raus, Live-Vorschau, Autosave ---------- */

  /* Mehr Ruhe beim Tippen: erst nach dieser Pause wird neu gezeichnet und geprueft. */
  var DIAGRAM_RENDER_DELAY = 1200;
  /* Nach dieser Tipp-Pause sichert der Editor automatisch. */
  var DIAGRAM_AUTOSAVE_DELAY = 1500;
  /* Typzeile (nur das Wort alleine in der ersten Zeile, ggf. mit Rest der
     Zeile dahinter als Zeilenende). Zeilen mit weiterem Inhalt bleiben. */
  var KIND_LINE_RE = /^[ \t]*(?:struktogramm|datenfluss|classDiagram)[ \t]*(?:\r?\n|$)/i;
  /* Klassendiagramm-Quelltext zum Zeichnen vorbereiten (#56): Eine alte,
     noch gespeicherte Typzeile fliegt raus, danach steht garantiert genau
     eine saubere classDiagram-Zeile vornan. Die Auswahl des Typs haengt
     am Diagramm (beim Anlegen gewaehlt), niemand tippt die Zeile. */
  function restoreClassDiagramHeader(text) {
    var body = String(text).replace(KIND_LINE_RE, '');
    return 'classDiagram' + String.fromCharCode(10) + body;
  }

  /* Einheitliche UML-Optik fuer Klassendiagramme: blauer Titelkopf, weisser
     Boxkoerper, harte Ecken und Trennstriche zwischen Titel, Attributen und
     Methoden - genauso wie das Klassendiagramm in der Code-Werkstatt.
     Mermaid zeichnet von sich aus graue Standardboxen. Deshalb wird nach dem
     Rendern nachgebessert. Alle Werte werden direkt als Attribute an das SVG
     gesetzt (nicht per CSS), damit der PNG-Export die Optik behaelt.
     Zwei Renderer werden abgedeckt: der klassische (g.classGroup mit rect und
     line.divider) und der aktuelle (g.node mit label-group/members-group/
     methods-group und divider-Gruppen). */
  function styleClassDiagramSvg(target) {
    if (!target) { return; }
    var headFill = '#dbeafe';
    var border = '#1d4ed8';
    var dividerCol = '#93c5fd';
    function setAttrs(el, attrs) {
      for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { el.setAttribute(k, attrs[k]); } }
    }
    function styleLegacyClassGroup(grp) {
      var rect = grp.querySelector('rect');
      var lines = grp.querySelectorAll('line.divider');
      if (!rect || lines.length === 0) { return; }
      var bx = parseFloat(rect.getAttribute('x')) || 0;
      var by = parseFloat(rect.getAttribute('y')) || 0;
      var bw = parseFloat(rect.getAttribute('width')) || 0;
      setAttrs(rect, { 'fill': '#ffffff', 'stroke': border, 'stroke-width': '2', 'rx': '0', 'ry': '0' });
      var li;
      for (li = 0; li < lines.length; li++) { setAttrs(lines[li], { 'stroke': dividerCol, 'stroke-width': '1' }); }
      var firstY = parseFloat(lines[0].getAttribute('y1'));
      if (!(firstY > by)) { firstY = by + 20; }
      var head = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      setAttrs(head, { 'x': bx, 'y': by, 'width': bw, 'height': Math.max(4, firstY - by), 'fill': headFill, 'stroke': 'none' });
      grp.insertBefore(head, grp.firstChild);
    }
    function styleNeoClassGroup(node) {
      /* Nur Klassengruppen: flowchart-Knoten haben keine members-/methods-group. */
      var label = node.querySelector('.label-group');
      if (!label) { return; }
      var members = node.querySelector('.members-group');
      var methods = node.querySelector('.methods-group');
      if (!members && !methods) { return; }
      var box = node.querySelector('.outer-path') || node.querySelector('.basic.label-container');
      if (!box) { return; }
      var b = box.getBBox();
      var paths = box.querySelectorAll('path');
      var pi;
      for (pi = 0; pi < paths.length; pi++) { setAttrs(paths[pi], { 'fill': '#ffffff', 'stroke': border, 'stroke-width': '2' }); }
      var dividers = node.querySelectorAll('g.divider');
      var di;
      for (di = 0; di < dividers.length; di++) {
        var dp = dividers[di].querySelector('path');
        if (dp) { setAttrs(dp, { 'stroke': dividerCol, 'stroke-width': '1' }); }
      }
      var firstY = null;
      if (dividers.length > 0 && dividers[0].querySelector('path')) { firstY = dividers[0].querySelector('path').getBBox().y; }
      if (firstY === null || !(firstY > b.y)) { firstY = b.y + Math.max(8, b.height * 0.25); }
      var head = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      setAttrs(head, { 'x': b.x, 'y': b.y, 'width': b.width, 'height': Math.max(4, firstY - b.y), 'fill': headFill, 'stroke': 'none' });
      node.insertBefore(head, box.nextSibling);
    }
    var legacy = target.querySelectorAll('g.classGroup');
    var i;
    for (i = 0; i < legacy.length; i++) { styleLegacyClassGroup(legacy[i]); }
    var nodes = target.querySelectorAll('g.node');
    for (i = 0; i < nodes.length; i++) { styleNeoClassGroup(nodes[i]); }
  }

  if (window.mermaid && window.mermaid.initialize) {
    window.mermaid.initialize({
      startOnLoad: false,
      themeVariables: {
        mainBkg: '#ffffff',
        nodeBorder: '#1d4ed8',
        classText: '#1e293b',
        textColor: '#1e3a8a',
        lineColor: '#475569'
      }
    });
  }
  /* Erste Zeichnung (#56): Ein Fehler hier darf die Werkbank nicht
     lahmlegen - Listener, Autosave und Neuzeichnen bleiben bedienbar. */
  try { render(false); } catch (eRender) { /* siehe Konsole */ if (typeof console !== 'undefined' && console.error) { console.error('InfoCraft: Erste Diagramm-Zeichnung abgebrochen', eRender); } }
  if (refreshBtn) { refreshBtn.addEventListener('click', function () { try { render(false); } catch (eR2) { /* refresh bleibt klickbar */ } }); }
  if (pngBtn) { pngBtn.addEventListener('click', exportPng); }
  if (fitBtn) {
    fitBtn.addEventListener('click', function () {
      var target = document.getElementById('diagram-preview');
      if (target) { target.classList.toggle('natural'); }
    });
  }
  if (fsBtn) {
    fsBtn.addEventListener('click', function () {
      if (document.fullscreenElement === page) {
        document.exitFullscreen();
        return;
      }
      if (page.requestFullscreen) {
        page.requestFullscreen();
        return;
      }
      page.classList.toggle('is-fallback-fullscreen');
    });
  }
  document.addEventListener('fullscreenchange', function () {
    if (document.fullscreenElement !== page) { page.classList.remove('is-fallback-fullscreen'); }
  });
  if (source) {
    source.addEventListener('input', function () {
      /* Typzeile (struktogramm, datenfluss, classDiagram) tippt niemand mehr: sofort raus. */
      var stripped = source.value.replace(KIND_LINE_RE, '');
      if (stripped !== source.value) { source.value = stripped; }
      if (renderTimer) { clearTimeout(renderTimer); }
      renderTimer = setTimeout(function () { render(true); }, DIAGRAM_RENDER_DELAY);
      if (saveTimer) { clearTimeout(saveTimer); }
      saveTimer = setTimeout(function () { saveNow('auto'); }, DIAGRAM_AUTOSAVE_DELAY);
    });
  }
  var titleInput = document.getElementById('diagram-title');
  if (titleInput) {
    titleInput.addEventListener('input', function () {
      if (saveTimer) { clearTimeout(saveTimer); }
      saveTimer = setTimeout(function () { saveNow('auto'); }, DIAGRAM_AUTOSAVE_DELAY);
    });
  }
})();
/* InfoCraft: Syntax-Highlighting in allen Editoren (highlight.js, Overlay-Technik) */
(function () {
  'use strict';
  if (typeof hljs === 'undefined') { return; }
  var NL = String.fromCharCode(10);

  if (hljs.getLanguage && !hljs.getLanguage('struktogramm')) {
    hljs.registerLanguage('struktogramm', function () {
      return {
        case_insensitive: true,
        keywords: 'struktogramm anweisung wenn dann sonst solange wiederhole bis ende',
        contains: [
          hljs.COMMENT('%%', NL),
          hljs.COMMENT('//', NL),
          { className: 'string', begin: '"', end: '"' },
          { className: 'number', begin: '[0-9]+' }
        ]
      };
    });
  }
  if (hljs.getLanguage && !hljs.getLanguage('datenfluss')) {
    hljs.registerLanguage('datenfluss', function () {
      return {
        case_insensitive: true,
        keywords: 'datenfluss quelle senke speicher',
        contains: [
          hljs.COMMENT('%%', NL),
          hljs.COMMENT('//', NL),
          { className: 'string', begin: '"', end: '"' },
          { className: 'title', begin: '\\([A-Za-z_][A-Za-z0-9_]*\\)' }
        ]
      };
    });
  }
  if (hljs.getLanguage && !hljs.getLanguage('mermaid')) {
    hljs.registerLanguage('mermaid', function () {
      return {
        case_insensitive: true,
        keywords: 'classDiagram erDiagram direction class interface enum record abstract static implements extends LR TB note',
        contains: [
          hljs.COMMENT('%%', NL),
          { className: 'string', begin: '"', end: '"' },
          { className: 'meta', begin: '<<', end: '>>' },
          { className: 'number', begin: '[0-9]+' },
          { className: 'title', begin: '[A-Z][A-Za-z0-9_]*' }
        ]
      };
    });
  }

  // Statische Code-Blöcke (Musterloesungen, ER-Quelltext, Mermaid-Quelltext)
  document.querySelectorAll('pre.code code').forEach(function (el) {
    var m = el.className.match(/language-([a-z]+)/);
    if (!m || !hljs.getLanguage(m[1])) { return; }
    el.innerHTML = hljs.highlight(el.textContent, { language: m[1] }).value;
  });

  // Editierbare Textareas: farbiges pre hinter transparentem Textarea
  document.querySelectorAll('[data-hl]').forEach(function (wrap) {
    var area = wrap.querySelector('textarea');
    if (!area) { return; }
    /* Verschachtelte Wrapper (SQL-Werkstatt): nur der innerste bekommt das Overlay,
     * sonst liegen zwei farbige Ebenen uebereinander (#49). */
    if (wrap.querySelector('[data-hl]')) { return; }
    var lang = wrap.getAttribute('data-hl');
    if (!hljs.getLanguage(lang)) { return; }
    var pre = document.createElement('pre');
    var code = document.createElement('code');
    code.className = 'language-' + lang;
    pre.appendChild(code);
    wrap.appendChild(pre);
    wrap.classList.add('hl-active');
    var sync = function () {
      pre.scrollTop = area.scrollTop;
      pre.scrollLeft = area.scrollLeft;
    };
    var render = function () {
      code.innerHTML = hljs.highlight(area.value + NL, { language: lang }).value;
      sync();
    };
    render();
    area.addEventListener('input', render);
    /* Diagramm-Editor: Sprachwechsel, wenn oben der Typ umgeschaltet wird. */
    wrap.addEventListener('diagramhllanguage', function (ev) {
      var nextLang = ev.detail && ev.detail.lang;
      if (!nextLang || nextLang === lang || !hljs.getLanguage(nextLang)) { return; }
      lang = nextLang;
      wrap.setAttribute('data-hl', lang);
      code.className = 'language-' + lang;
      render();
    });
    area.addEventListener('scroll', sync);
  });
})();


/* ---------- Zeilennummern fuer alle Textfelder mit Syntax-Highlight ----------
 * Wie in der Java-IDE: links vom Quelltext stehen Zeilennummern, damit im
 * Unterricht immer ueber Zeilen gesprochen werden kann ("Schau mal in Zeile 4").
 * Die Java-IDE baut ihre eigenen Zeilennummern (mit Fehler- und Schritt-
 * Markierung) in java-vm.js - sie wird hier ausgelassen.
 * Ueber window.IC_GUTTER koennen Fehlerzeilen rot markiert werden. */
(function () {
  'use strict';
  var NL = String.fromCharCode(10);
  var records = [];

  function updateGutter(rec) {
    var lines = rec.ta.value.split(NL).length;
    if (lines < 1) { lines = 1; }
    if (lines === rec.count) { return; }
    rec.inner.innerHTML = '';
    rec.cells = [];
    var li;
    for (li = 1; li <= lines; li++) {
      var cell = document.createElement('div');
      cell.className = 'ln-cell';
      cell.textContent = String(li);
      rec.inner.appendChild(cell);
      rec.cells.push(cell);
    }
    rec.count = lines;
  }

  function recFor(ta) {
    var i;
    for (i = 0; i < records.length; i++) {
      if (records[i].ta === ta) { return records[i]; }
    }
    return null;
  }

  function markError(ta, line) {
    var rec = recFor(ta);
    if (!rec || !line || line < 1 || line > rec.cells.length) { return; }
    clearError(ta);
    rec.cells[line - 1].className = 'ln-cell ln-err';
  }

  function clearError(ta) {
    var rec = recFor(ta);
    if (!rec) { return; }
    var i;
    for (i = 0; i < rec.cells.length; i++) { rec.cells[i].className = 'ln-cell'; }
  }

  window.IC_GUTTER = { markError: markError, clearError: clearError };

  document.querySelectorAll('[data-hl]').forEach(function (wrap) {
    var ta = wrap.querySelector('textarea');
    if (!ta) { return; }
    /* Java-IDE: eigene Zeilennummern mit Einzelschritt- und Fehlermarkierung. */
    if (wrap.closest && wrap.closest('[data-file-pane]')) { return; }
    if (wrap.querySelector('.ln-gutter')) { return; }
    /* Verschachtelte Wrapper (SQL-Werkstatt): nur der innerste bekommt Nummern (#49). */
    if (wrap.querySelector('[data-hl]')) { return; }
    /* Felder mit eigener Gutter daneben (Objektkonsole): keine zweite Spalte (#49). */
    if (wrap.previousElementSibling && String(wrap.previousElementSibling.className || '').indexOf('repl-gutter') !== -1) { return; }
    var box = document.createElement('div');
    box.className = 'ln-gutter';
    var inner = document.createElement('div');
    inner.className = 'ln-inner';
    box.appendChild(inner);
    wrap.appendChild(box);
    if (wrap.classList && wrap.classList.add) { wrap.classList.add('has-gutter'); }
    var rec = { ta: ta, box: box, inner: inner, cells: [], count: 0 };
    records.push(rec);
    updateGutter(rec);
    ta.addEventListener('input', function () { updateGutter(rec); });
    ta.addEventListener('scroll', function () { box.scrollTop = ta.scrollTop; });
  });
})();


/* ---------- Ghost-Text: grauer Vorschlag direkt in der Zeile (#55) ----------
 * Waehrend der Autovervollstaendigung steht der beste Vorschlag ausgegraut
 * hinter dem Cursor, wie in modernen Editoren. Tab oder Enter uebernimmt ihn.
 * Technik: ein Layer im hlwrap unter dem Textarea, gleiche Schrift und Breite;
 * der getippte Teil ist unsichtbar (visibility), nur der Rest erscheint grau. */
(function () {
  'use strict';
  var records = [];

  function recFor(ta) {
    var i;
    for (i = 0; i < records.length; i++) { if (records[i].ta === ta) { return records[i]; } }
    return null;
  }

  function hide(ta) {
    var rec = recFor(ta);
    if (!rec) { return; }
    if (rec.el && rec.el.parentNode) { rec.el.parentNode.removeChild(rec.el); }
    records.splice(records.indexOf(rec), 1);
  }

  function sync(rec) {
    /* Gleicher Bildausschnitt wie im Textarea (pre-Overlay macht es genauso). */
    try {
      rec.el.scrollTop = rec.ta.scrollTop || 0;
      rec.el.scrollLeft = rec.ta.scrollLeft || 0;
    } catch (e) { /* ohne Scroll-API: egal */ }
  }

  function show(ta, text) {
    if (!ta || !text) { hide(ta); return; }
    if (typeof document === 'undefined' || !document.createElement) { return; }
    try {
      var wrap = ta.closest ? (ta.closest('[data-hl]') || ta.parentNode) : ta.parentNode;
      if (!wrap || !wrap.appendChild || !wrap.querySelector || wrap.querySelector('textarea') !== ta) { return; }
      var rec = recFor(ta);
      if (!rec) {
        var el = document.createElement('pre');
        el.className = 'ic-ghost';
        el.setAttribute('aria-hidden', 'true');
        rec = { ta: ta, el: el };
        records.push(rec);
        ta.addEventListener('scroll', function () { sync(rec); });
      }
      var cs = null;
      try { cs = window.getComputedStyle ? window.getComputedStyle(ta) : null; } catch (e2) { cs = null; }
      if (cs) {
        var props = ['fontFamily', 'fontSize', 'lineHeight', 'paddingTop', 'paddingLeft', 'paddingRight', 'paddingBottom', 'tabSize', 'whiteSpace'];
        for (var pi = 0; pi < props.length; pi++) {
          try { rec.el.style[props[pi]] = cs[props[pi]]; } catch (e3) { /* aeltere Browser */ }
        }
      }
      var pos = ta.selectionStart === undefined ? String(ta.value == null ? '' : ta.value).length : ta.selectionStart;
      rec.el.textContent = '';
      var pre = document.createElement('span');
      pre.className = 'ic-ghost-pre';
      pre.textContent = String(ta.value == null ? '' : ta.value).slice(0, pos);
      var tail = document.createElement('span');
      tail.className = 'ic-ghost-txt';
      tail.textContent = String(text);
      rec.el.appendChild(pre);
      rec.el.appendChild(tail);
      if (!rec.el.parentNode) { wrap.appendChild(rec.el); }
      sync(rec);
    } catch (e) { hide(ta); }
  }

  window.IC_GHOST = { show: show, hide: hide };
})();


/* ---------- Autovervollstaendigung (#45 Nacharbeit) ----------
 * In jedem Code-Feld (Textareas mit data-ac): Vorschlaege zu Befehlen,
 * Klassen, Methoden und Attributen waehrend des Tippens. Tab oder Enter
 * uebernimmt den markierten Vorschlag direkt, Pfeiltasten blaettern,
 * Esc schliesst. Enter/Tab verhalten sich normal, wenn kein Vorschlag offen ist.
 * Quellen: statische Sprachlisten plus Bezeichner aus dem eigenen Code. */
(function () {
  'use strict';

  var JAVA_KEYWORDS = ['class', 'interface', 'enum', 'extends', 'implements', 'public', 'private', 'protected', 'static', 'final', 'void', 'int', 'double', 'boolean', 'char', 'String', 'long', 'float', 'if', 'else', 'for', 'while', 'do', 'switch', 'case', 'break', 'continue', 'return', 'new', 'this', 'null', 'true', 'false', 'import', 'package', 'try', 'catch', 'finally', 'throw', 'instanceof', 'super'];
  var JAVA_SNIPPETS = [
    { label: 'System.out.println(...)', insert: 'System.out.println(', detail: 'Ausgabe in der Konsole' },
    { label: 'Database.execute(...)', insert: 'Database.execute(', detail: 'SQL ausfuehren' },
    { label: 'Database.query(...)', insert: 'Database.query(', detail: 'SELECT als Tabelle' },
    { label: 'Database.count(...)', insert: 'Database.count(', detail: 'Zeilen zaehlen' },
    { label: 'Database.value(...)', insert: 'Database.value(', detail: 'ersten Wert lesen' },
    { label: 'Database.tables()', insert: 'Database.tables()', detail: 'alle Tabellen' },
    { label: 'Math.random()', insert: 'Math.random()', detail: 'Zufallszahl 0..1' },
    { label: 'ArrayList<>()', insert: 'ArrayList<>() ', detail: 'Liste anlegen' },
    { label: 'ArrayList<String>', insert: 'ArrayList<String>', detail: 'Liste fuer Texte' },
    { label: 'Scanner', insert: 'Scanner', detail: 'Eingaben lesen' },
    { label: 'String.valueOf(...)', insert: 'String.valueOf(', detail: 'in Text umwandeln' }
  ];
  var SQL_KEYWORDS = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT JOIN', 'ON', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE FROM', 'CREATE TABLE', 'DROP TABLE', 'COUNT(*)', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'DISTINCT', 'AS', 'AND', 'OR', 'NOT', 'NULL', 'IS NULL', 'IS NOT NULL', 'ASC', 'DESC', 'INNER JOIN', 'LIKE', 'IN', 'BETWEEN'];
  var ASM_COMMANDS = ['MOV', 'ADD', 'SUB', 'MUL', 'DIV', 'INC', 'DEC', 'CMP', 'JMP', 'JE', 'JNE', 'JG', 'JL', 'JGE', 'JLE', 'JZ', 'JNZ', 'LOAD', 'STORE', 'PUSH', 'POP', 'CALL', 'RET', 'HLT', 'NOP', 'OUT', 'IN'];
  var HTML_TAGS = ['p', 'h1', 'h2', 'h3', 'strong', 'em', 'br', 'a', 'img', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'div', 'span', 'header', 'main', 'footer', 'section', 'form', 'input', 'button', 'style', 'script', 'title', 'link'];
  var MD_SNIPPETS = ['# Ueberschrift', '## Abschnitt', '- Aufzaehlung', '1. Nummerierung', '**fett**', '*kursiv*', '`Code`', '``` Codeblock', '[Link](https://)', '> Zitat'];

  function collectIdentifiers(scopeDoc) {
    /* Klassen, Attribute und Methoden aus dem eigenen Java-Code sammeln. */
    var found = {};
    var sources = [];
    try {
      if (scopeDoc.querySelectorAll('[data-file-pane] textarea').length > 0) {
        scopeDoc.querySelectorAll('[data-file-pane] textarea').forEach(function (ta) { sources.push(ta.value || ''); });
      } else {
        sources.push(taValue(currentTa));
      }
    } catch (e) { /* Harness ohne querySelectorAll: harmlos */ }
    var code = sources.join(String.fromCharCode(10));
    var re = /class\s+([A-Za-z_][A-Za-z0-9_]*)|([A-Za-z_][A-Za-z0-9_]*)\s*\(\s*\)/g;
    var m;
    while ((m = re.exec(code)) !== null) {
      var name = m[1] || m[2];
      if (name && !found[name]) { found[name] = true; }
    }
    var fields = code.match(/(?:private|public|protected|static)?\s*(?:final\s+)?[A-Za-z_][A-Za-z0-9_]*\s+([A-Za-z_][A-Za-z0-9_]*)\s*(?:=|;)/g) || [];
    for (var i = 0; i < fields.length; i++) {
      var fm = /([A-Za-z_][A-Za-z0-9_]*)\s*(?:=|;)\s*$/.exec(fields[i]);
      if (fm && !found[fm[1]]) { found[fm[1]] = true; }
    }
    return found;
  }

  var currentTa = null;
  function taValue(ta) { return ta && ta.value ? ta.value : ''; }

  function memberItems(objectName) {
    if (objectName === 'Database') {
      return [
        { label: 'execute(...)', insert: 'execute(', detail: 'SQL ausfuehren' },
        { label: 'query(...)', insert: 'query(', detail: 'SELECT als Tabelle' },
        { label: 'count(...)', insert: 'count(', detail: 'Zeilen zaehlen' },
        { label: 'value(...)', insert: 'value(', detail: 'ersten Wert' },
        { label: 'tables()', insert: 'tables()', detail: 'alle Tabellen' }
      ];
    }
    if (objectName === 'out' || objectName === 'System' || objectName === 'err') {
      return [
        { label: 'println(...)', insert: 'println(', detail: 'Zeile ausgeben' },
        { label: 'print(...)', insert: 'print(', detail: 'ohne Zeilenumbruch' }
      ];
    }
    if (objectName === 'Math') {
      return [
        { label: 'random()', insert: 'random()', detail: 'Zufallszahl 0..1' },
        { label: 'abs(...)', insert: 'abs(', detail: 'Betrag' },
        { label: 'max(...)', insert: 'max(', detail: 'groesserer Wert' },
        { label: 'min(...)', insert: 'min(', detail: 'kleinerer Wert' }
      ];
    }
    return null;
  }

  function sourceItems(kind, ta) {
    var items = [];
    var kw;
    var i;
    if (kind === 'java') {
      for (i = 0; i < JAVA_KEYWORDS.length; i++) { kw = JAVA_KEYWORDS[i]; items.push({ label: kw, insert: kw + ' ', detail: 'Java' }); }
      for (i = 0; i < JAVA_SNIPPETS.length; i++) { items.push(JAVA_SNIPPETS[i]); }
      var ids = collectIdentifiers(document);
      for (var id in ids) {
        if (Object.prototype.hasOwnProperty.call(ids, id)) {
          items.push({ label: id, insert: id, detail: 'aus deinem Code' });
        }
      }
    } else if (kind === 'sql' || kind === 'sqldb') {
      for (i = 0; i < SQL_KEYWORDS.length; i++) { kw = SQL_KEYWORDS[i]; items.push({ label: kw, insert: kw + ' ', detail: 'SQL' }); }
      var tables = [];
      if (kind === 'sqldb' && window.IC_DB_TABLES) {
        tables = window.IC_DB_TABLES;
      } else if (ta) {
        var attr = ta.getAttribute('data-tables');
        if (attr) { tables = attr.split(','); }
      }
      for (i = 0; i < tables.length; i++) {
        var tname = String(tables[i]).trim();
        if (tname !== '') { items.push({ label: tname, insert: tname, detail: 'Tabelle' }); }
      }
    } else if (kind === 'asm') {
      for (i = 0; i < ASM_COMMANDS.length; i++) { kw = ASM_COMMANDS[i]; items.push({ label: kw, insert: kw + ' ', detail: 'Befehl' }); }
    } else if (kind === 'html') {
      for (i = 0; i < HTML_TAGS.length; i++) {
        var tag = HTML_TAGS[i];
        items.push({ label: '<' + tag + '>', insert: '<' + tag + '>', detail: 'Tag' });
        items.push({ label: '</' + tag + '>', insert: '</' + tag + '>', detail: 'Tag schliessen' });
      }
    } else if (kind === 'md') {
      for (i = 0; i < MD_SNIPPETS.length; i++) { items.push({ label: MD_SNIPPETS[i], insert: MD_SNIPPETS[i], detail: 'Markdown' }); }
    } else if (kind === 'python') {
      var PY = ['def', 'return', 'if', 'elif', 'else', 'for', 'while', 'in', 'range', 'print', 'import', 'from', 'class', 'True', 'False', 'None', 'and', 'or', 'not', 'len', 'str', 'int', 'float', 'list', 'append'];
      for (i = 0; i < PY.length; i++) { items.push({ label: PY[i], insert: PY[i], detail: 'Python' }); }
    }
    return items;
  }

  function attach(ta, kind) {
    if (!ta || ta.__icAc) { return; }
    ta.__icAc = true;
    var wrap = ta.closest ? (ta.closest('[data-hl]') || ta.parentNode) : ta.parentNode;
    var drop = document.createElement('div');
    drop.className = 'ac-drop';
    drop.style.display = 'none';
    if (wrap && wrap.appendChild) { wrap.appendChild(drop); }
    if (wrap && wrap.classList && wrap.classList.add) { wrap.classList.add('has-ac'); }
    var matches = [];
    var active = -1;
    var tokenStart = -1;
    var lastToken = '';

    function tokenInfo() {
      var pos = ta.selectionStart === undefined ? taValue(ta).length : ta.selectionStart;
      var before = taValue(ta).slice(0, pos);
      var m = /([A-Za-z_][A-Za-z0-9_]*)(?:\.([A-Za-z_][A-Za-z0-9_]*))?$/.exec(before);
      if (!m) { return null; }
      return { objectName: m[1], memberPrefix: m[2] !== undefined ? m[2] : null, token: m[2] !== undefined ? m[2] : m[1], start: pos - (m[2] !== undefined ? m[2].length : m[1].length), dot: m[2] !== undefined, pos: pos };
    }

    function close() {
      matches = [];
      active = -1;
      drop.style.display = 'none';
      drop.innerHTML = '';
      try { if (window.IC_GHOST) { window.IC_GHOST.hide(ta); } } catch (e) { /* optional */ }
    }

    /* Rest des Vorschlags, der noch hinter dem Cursor fehlt (#55). */
    function ghostRemainder(match, token) {
      var ins = String(match.insert);
      var t = String(token || '');
      if (t !== '' && ins.toLowerCase().indexOf(t.toLowerCase()) === 0) { return ins.slice(t.length); }
      return ins;
    }

    function render() {
      drop.innerHTML = '';
      for (var i = 0; i < matches.length; i++) {
        var item = document.createElement('div');
        item.className = 'ac-item' + (i === active ? ' ac-active' : '');
        /* Nur der Bezeichner steht im Vorschlag, die Erklaerung als Tooltip (#54). */
        item.textContent = matches[i].label;
        if (matches[i].detail) { item.title = String(matches[i].detail); }
        item.setAttribute('data-idx', String(i));
        drop.appendChild(item);
      }
      drop.style.display = matches.length > 0 ? '' : 'none';
    }

    function ghost() {
      try {
        if (!window.IC_GHOST || matches.length === 0) { if (window.IC_GHOST) { window.IC_GHOST.hide(ta); } return; }
        var idx = active >= 0 ? active : 0;
        window.IC_GHOST.show(ta, ghostRemainder(matches[idx], lastToken));
      } catch (e) { /* Ghost ist rein optisch */ }
    }

    function update() {
      var info = tokenInfo();
      if (!info || info.token.length < 1) { close(); return; }
      var pool;
      if (info.dot) {
        pool = memberItems(info.objectName);
        if (!pool) { close(); return; }
      } else {
        pool = sourceItems(kind, ta);
      }
      var prefix = info.token.toLowerCase();
      matches = [];
      for (var i = 0; i < pool.length; i++) {
        var label = String(pool[i].label).toLowerCase();
        if (label.indexOf(prefix) === 0 && pool[i].label !== info.token) { matches.push(pool[i]); }
        if (matches.length >= 8) { break; }
      }
      active = matches.length > 0 ? 0 : -1;
      tokenStart = info.start;
      lastToken = info.token;
      render();
      ghost();
      try {
        if (typeof ta.getBoundingClientRect === 'function' && drop.style) {
          var rect = ta.getBoundingClientRect();
          drop.style.left = '0px';
          drop.style.top = '100%';
          drop.style.maxWidth = '100%';
          /* Position unter dem Textfeld rechtsbuendig begrenzen */
          drop.style.right = 'auto';
        }
      } catch (e) { /* Positionierung optional */ }
    }

    function accept(idx) {
      if (idx < 0 || idx >= matches.length) { return false; }
      var ins = String(matches[idx].insert);
      var pos = ta.selectionStart === undefined ? taValue(ta).length : ta.selectionStart;
      var value = taValue(ta);
      var after = value.slice(pos);
      var before = value.slice(0, pos);
      var m = /([A-Za-z_][A-Za-z0-9_]*)$/.exec(before);
      var start = m ? pos - m[1].length : pos;
      var next = value.slice(0, start) + ins + after;
      ta.value = next;
      var caret = start + ins.length;
      try {
        ta.setSelectionRange(caret, caret);
      } catch (e) { /* Harness ohne Auswahl-API */ }
      try {
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      } catch (e) {
        var ev = document.createEvent('Event');
        ev.initEvent('input', true, false);
        ta.dispatchEvent(ev);
      }
      close();
      return true;
    }

    ta.addEventListener('input', update);
    ta.addEventListener('keydown', function (e) {
      if (matches.length === 0) { return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % matches.length; render(); ghost(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = active <= 0 ? matches.length - 1 : active - 1; render(); ghost(); }
      else if (e.key === 'Tab' || e.key === 'Enter') { e.preventDefault(); accept(active); }
      else if (e.key === 'Escape') { close(); }
    });
    ta.addEventListener('blur', function () { setTimeout(close, 120); });
    drop.addEventListener('mousedown', function (e) {
      /* Klick auf einen Vorschlag: Fokus halten, der uebernimmt den Eintrag. */
      var onItem = e.target && e.target.classList && e.target.classList.contains('ac-item');
      if (onItem) { e.preventDefault(); return; }
      /* Klick auf die freie Flaeche der Vorschlagsbox (#60): Die Box liegt
       * als Layer direkt UEBER der Zeile mit dem Ausfuehren-Button und hat
       * den Klick bisher komplett geschluckt - die Abfrage lief dann nie.
       * Jetzt: Box schliessen und den Klick an das Element darunter
       * durchreichen, z. B. an "Abfrage ausfuehren". */
      e.preventDefault();
      close();
      try {
        if (document.elementFromPoint && typeof e.clientX === 'number') {
          var below = document.elementFromPoint(e.clientX, e.clientY);
          if (below && below !== drop && below !== ta && below.click) { below.click(); }
        }
      } catch (e2) { /* Harness ohne elementFromPoint: Box ist zu, das genuegt */ }
    });
    drop.addEventListener('click', function (e) {
      var item = e.target && e.target.getAttribute ? e.target : (e.target.parentNode);
      var idx = item && item.getAttribute ? parseInt(item.getAttribute('data-idx'), 10) : NaN;
      if (!isNaN(idx)) { accept(idx); }
    });
  }

  /* Kleine ASM-Sprache fuer Syntaxfarben im Assembler-Editor (#45 Nacharbeit) */
  if (typeof hljs !== 'undefined' && hljs.getLanguage && !hljs.getLanguage('asm')) {
    hljs.registerLanguage('asm', function () {
      return {
        case_insensitive: true,
        keywords: { keyword: 'MOV ADD SUB MUL DIV INC DEC CMP JMP JE JNE JG JL JGE JLE JZ JNZ LOAD STORE PUSH POP CALL RET HLT NOP OUT IN', built_in: 'R0 R1 R2 R3 R4 R5 R6 R7 ACC PC SP FLAG' },
        contains: [
          hljs.COMMENT(';', String.fromCharCode(10)),
          { className: 'number', begin: '[0-9]+' },
          { className: 'string', begin: '"', end: '"' }
        ]
      };
    });
  }

  /* Alle Felder mit data-ac verbinden (zusaetzlich zu den Wrappern) */
  document.querySelectorAll('[data-ac]').forEach(function (wrap) {
    var ta = wrap.querySelector('textarea');
    var kind = wrap.getAttribute('data-ac');
    if (ta) { attach(ta, kind); }
  });
  window.IC_AUTOCOMPLETE = { attach: attach };
})();

/* =====================================================================
 * InfoBook-Seiteneditor (#62/#63/#64): Eine Seite ist EINE freie Flaeche
 * wie in OneNote. Bilder per Drag & Drop ablegen, Textfelder frei
 * platzieren, mit dem Stift ueberall zeichnen (auch ueber Bildern,
 * druckempfindlich via Pointer Events, ideal fuer Apple Pencil).
 * Zeichnungen sind einzelne Strich-Objekte: auswaehlbar, verschieben,
 * skalieren, Farbe und Strichstaerke nachtraeglich aendern, Radierer
 * entfernt einzelne Striche. Die Symbolleiste oben aehnelt GoodNotes:
 * Werkzeug (Auswahl/Stift/Radierer), Farben, Strichstaerken, Einfuegen
 * und ein Kontextbereich, der je nach Auswahl passende Einstellungen
 * zeigt (Textfeld: Hintergrund und Ausrichtung, Einbettung: Adresse).
 * Alte Baustein-Seiten werden beim Laden automatisch in Elemente der
 * freien Flaeche umgewandelt. Striche werden mit der Seite gespeichert,
 * kein separates PNG und kein manueller "uebernehmen"-Schritt mehr.
 * ===================================================================== */
(function () {
  'use strict';
  var editor = typeof document !== 'undefined' ? document.getElementById('ibook-editor') : null;
  if (!editor) { return; }
  var bookId = parseInt(editor.getAttribute('data-book-id') || '0', 10) || 0;
  var raw = [];
  try { raw = JSON.parse(editor.getAttribute('data-blocks') || '[]') || []; } catch (e) { raw = []; }
  if (!Array.isArray(raw)) { raw = []; }
  var csrf = '';
  try { csrf = document.body.getAttribute('data-csrf') || ''; } catch (e) { /* Harness */ }
  var pageId = parseInt(editor.getAttribute('data-page-id') || '0', 10) || 0;
  var status = null;
  var dirty = false;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }
  function say(msg, isErr) {
    if (!status) { return; }
    status.textContent = msg;
    status.className = 'ibook-status' + (isErr ? ' ibook-status-err' : '');
  }
  /* Autospeichern (#67): Jede Aenderung plant einen speicherten POST nach
   * kurzer Pause. Der Speichern-Knopf ist dafuer entfallen. */
  var saveTimer = null;
  function saveUrl() {
    var a = (typeof saveform !== 'undefined' && saveform && saveform.getAttribute) ? (saveform.getAttribute('action') || '') : '';
    if (a) { return a; }
    return '/infobuecher/' + bookId + '/seite/' + pageId;
  }
  function markDirty() {
    dirty = true;
    if (saveTimer === null && typeof setTimeout === 'function') {
      saveTimer = setTimeout(doSave, 900);
    }
  }
  function mediaUrl(id) { return '/infobuecher/medium/' + id; }
  function num(v, d) { var p = Math.pow(10, d || 2); return Math.round((v || 0) * p) / p; }
  function clampN(v, min, max) { return Math.max(min, Math.min(max, v)); }

  function upload(kind, file, done) {
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('kind', kind);
    fd.append('file', file, file.name);
    fetch('/infobuecher/' + bookId + '/medium', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok) { done(data); say((kind === 'image' ? 'Bild' : kind === 'audio' ? 'Audio' : 'Video') + ' hochgeladen: ' + data.name); }
        else { say('Upload fehlgeschlagen: ' + ((data && data.error) || 'unbekannter Fehler'), true); }
      })
      .catch(function (err) { say('Upload fehlgeschlagen: ' + err, true); });
  }

  /* ---------- Genau EIN Canvas-Block (#64): alte Bausteine werden beim
   * Laden in Elemente der freien Flaeche umgewandelt. ---------- */
  var block = { type: 'canvas', w: 1600, h: 1000, items: [], ink: 0, inkName: '' };
  (function normalize() {
    var y = 40;
    raw.forEach(function (b) {
      if (!b || typeof b !== 'object') { return; }
      var t = b.type || 'text';
      if (t === 'canvas') {
        block.w = b.w || block.w;
        block.h = b.h || block.h;
        block.items = Array.isArray(b.items) ? b.items : [];
        block.ink = b.ink || 0;
        block.inkName = b.inkName || '';
        return;
      }
      if (t === 'text') {
        block.items.push({ kind: 'text', body: b.body || '', x: 80, y: y, w: 600, bg: '', align: 'left' });
        y += 200;
      } else if (t === 'image') {
        if (b.media) { block.items.push({ kind: 'image', media: b.media, name: b.name || 'Bild', alt: b.alt || '', x: 320, y: y, w: 400, h: 300 }); }
        y += 340;
      } else if (t === 'embed') {
        block.items.push({ kind: 'embed', url: b.url || '', title: b.title || '', x: 320, y: y, w: 480 });
        y += 300;
      } else if (t === 'audio') {
        if (b.media) { block.items.push({ kind: 'audio', media: b.media, name: b.name || 'Audio', x: 320, y: y, w: 360 }); }
        y += 140;
      } else if (t === 'video') {
        if (b.media) { block.items.push({ kind: 'video', media: b.media, name: b.name || 'Video', x: 320, y: y, w: 480, h: 270 }); }
        y += 320;
      } else if (t === 'zeichnen') {
        if (b.media) { block.items.push({ kind: 'image', media: b.media, name: b.name || 'Zeichnung', alt: 'Freihandzeichnung', x: 320, y: y, w: 480, h: 320 }); }
        y += 360;
      }
    });
  })();

  /* ---------- Zustand ---------- */
  var stage = el('div', 'icv-stage');
  stage.setAttribute('style', 'aspect-ratio:' + block.w + '/' + block.h);
  var mode = 'select';
  var penColor = '#1f2937';
  var penSw = 4;
  var sel = null;
  var selDiv = null;
  var selList = []; /* Mehrfachauswahl per Lasso (#68) */
  var PEN_COLORS = ['#1f2937', '#ef4444', '#3b82f6', '#059669', '#f59e0b'];
  var TEXT_BGS = [['', 'Gelb (Post-It)'], ['none', 'Ohne Hintergrund'], ['#ffffff', 'Weiß'], ['#eff6ff', 'Blau'], ['#f0fdf4', 'Grün'], ['#fef2f2', 'Rosé']];
  var KIND_NAMES = { text: 'Textfeld', image: 'Bild', ink: 'Strich', embed: 'Einbettung', audio: 'Audio', video: 'Video' };

  function pctOf(v, total) { return (Math.round((v || 0) / total * 10000) / 100) + '%'; }
  function stageRect() {
    var r = null;
    try { r = stage.getBoundingClientRect(); } catch (e) { /* Harness */ }
    if (!r || typeof r.width !== 'number') { return { left: 0, top: 0, width: block.w, height: block.h }; }
    return r;
  }
  function toLogical(clientX, clientY) {
    var r = stageRect();
    return {
      x: (clientX - r.left) * (block.w / (r.width || block.w)),
      y: (clientY - r.top) * (block.h / (r.height || block.h))
    };
  }
  function styleItem(div, item) {
    div.style.left = pctOf(item.x, block.w);
    div.style.top = pctOf(item.y, block.h);
    div.style.width = pctOf(item.w, block.w);
    if ((item.kind === 'image' || item.kind === 'ink' || item.kind === 'video') && item.h) {
      div.style.height = pctOf(item.h, block.h);
    }
    /* Post-It-Hintergrund (#68): Die Farbe gehoert zum GESAMTEN Zettel,
     * nicht nur zur Schreibfläche. 'none' bedeutet ganz ohne Hintergrund
     * (auch ohne das gelbe Standard-Post-It), '' ist das gelbe Post-It. */
    if (item.kind === 'text') {
      if (item.bg === 'none') {
        div.style.background = 'transparent';
        div.style.borderColor = 'transparent';
        div.style.boxShadow = 'none';
      } else if (item.bg) {
        div.style.background = item.bg;
        div.style.borderColor = 'rgba(15, 23, 42, 0.15)';
        div.style.boxShadow = '';
      } else {
        div.style.background = '';
        div.style.borderColor = '';
        div.style.boxShadow = '';
      }
    }
  }
  function svgEl(name) {
    if (typeof document.createElementNS === 'function') {
      return document.createElementNS('http://www.w3.org/2000/svg', name);
    }
    return document.createElement(name); /* Harness-Fallback */
  }
  function buildInkSvg(item) {
    var svg = svgEl('svg');
    svg.setAttribute('class', 'icv-ink-svg');
    svg.setAttribute('viewBox', '0 0 ' + Math.max(4, item.w || 4) + ' ' + Math.max(4, item.h || 4));
    svg.setAttribute('preserveAspectRatio', 'none');
    var path = svgEl('path');
    var p = item.p || [];
    var d = '';
    for (var i = 0; i + 1 < p.length; i += 2) {
      d += (i === 0 ? 'M ' : 'L ') + num(p[i], 1) + ' ' + num(p[i + 1], 1) + ' ';
    }
    path.setAttribute('d', d);
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', item.c || '#1f2937');
    path.setAttribute('stroke-width', item.sw || 4);
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(path);
    return svg;
  }
  function applyInkStyle(item) {
    if (selDiv && selDiv.querySelector) {
      var path = null;
      try { path = selDiv.querySelector('path'); } catch (e) { /* Harness */ }
      if (path) {
        path.setAttribute('stroke', item.c || '#1f2937');
        path.setAttribute('stroke-width', item.sw || 4);
      }
    }
  }
  /* YouTube-Video-Nummer aus allen gaengigen Linkformen (#67), dafuer gibt
   * es ohne API und ohne Cookies das Vorschaubild von i.ytimg.com. */
  function ytThumbFor(u) {
    var m = /(?:youtube\.com\/(?:watch\?[^#]*v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/.exec(String(u || ''));
    return m ? ('https://i.ytimg.com/vi/' + m[1] + '/hqdefault.jpg') : '';
  }

  /* ---------- Elemente aufbauen ---------- */
  /* Textfeld-Hoehe folgt dem Text (#66): kein manuelles Ziehen mehr, das
   * Feld wachs mit jedem Tastendruck mit (min-height als Fallback im CSS). */
  function fitTa(ta) {
    if (!ta || !ta.style) { return; }
    try {
      ta.style.height = 'auto';
      var h = (typeof ta.scrollHeight === 'number' && ta.scrollHeight > 0) ? ta.scrollHeight : 0;
      if (h > 0) { ta.style.height = h + 'px'; }
    } catch (e) { /* Harness ohne Layout */ }
  }
  /* ---------- Unendliche Seitenhoehe (#67): Die Flaeche waechst mit,
   * sobald ein Objekt den unteren Rand ueberschreitet. Alle Objekte
   * behalten ihre Position (Prozentkoordinaten skalierten bisher schon
   * mit, der Rand unten kommt schlicht dazu). ---------- */
  function updateStageStyle() {
    stage.setAttribute('style', 'aspect-ratio:' + block.w + '/' + Math.max(1, Math.round(block.h)));
    if (overlay.width !== block.w || overlay.height !== Math.round(block.h)) {
      try { overlay.height = Math.max(1, Math.round(block.h)); overlay.width = block.w; } catch (eOv) { /* Harness */ }
    }
  }
  function itemsBottom() {
    var bottom = 0;
    block.items.forEach(function (it) {
      var h;
      if (it.kind === 'image' || it.kind === 'ink' || it.kind === 'video') { h = it.h || 100; }
      else if (it.kind === 'embed') { h = 130; }
      else if (it.kind === 'audio') { h = 80; }
      else { h = 80; } /* Textfeld: Inhalt wachst mit */
      bottom = Math.max(bottom, (it.y || 0) + h);
    });
    return bottom;
  }
  function relayoutAll() {
    for (var i = 0; i < itemDivs.length; i++) {
      if (itemDivs[i] && block.items[i]) { styleItem(itemDivs[i], block.items[i]); }
    }
  }
  function growStage() {
    var need = Math.ceil(itemsBottom()) + 80;
    if (need > block.h) {
      block.h = need;
      updateStageStyle();
      relayoutAll();
    }
  }
  var dragActive = false;
  function attachMove(handle, item, div) {
    handle.addEventListener('pointerdown', function (ev) {
      if (mode !== 'select' || dragActive) { return; }
      if (ev && ev.__icvDragging) { return; } /* nur ein Drag pro Klick (#66) */
      /* Ohne Koordinaten (Test-Harness, synthetische Events) kein Drag-Start:
       * Der offene Handler wuerde spaetere echte Moves mit NaN vergiften. */
      if (typeof ev.clientX !== 'number' || typeof ev.clientY !== 'number') { return; }
      if (ev) { ev.__icvDragging = true; }
      dragActive = true; /* waehrend des Drags keine andere Auswahl (#67) */
      if (ev.preventDefault) { ev.preventDefault(); }
      var sx = ev.clientX, sy = ev.clientY, ox = item.x, oy = item.y;
      /* Gruppen (#68): Mitbeweger mit Startpositionen merken, damit der
       * Drag alle Gruppenmitglieder um dieselbe Strecke zieht. */
      var peers = item.g ? block.items.filter(function (o) {
        return o !== item && o.g === item.g;
      }).map(function (o) { return { it: o, x: o.x || 0, y: o.y || 0 }; }) : [];
      function onMove(ev2) {
        if (typeof ev2.clientX !== 'number' || typeof ev2.clientY !== 'number') { return; }
        var rect = stageRect(); /* frisch: die Flaeche kann waachsen (#67) */
        item.x = clampN(ox + (ev2.clientX - sx) * (block.w / (rect.width || block.w)), 0, Math.max(0, block.w - 24));
        item.y = clampN(oy + (ev2.clientY - sy) * (block.h / (rect.height || block.h)), 0, 99999);
        styleItem(div, item);
        peers.forEach(function (p) { /* Gruppe zieht mit (#68) */
          p.it.x = clampN(p.x + (ev2.clientX - sx) * (block.w / (rect.width || block.w)), 0, Math.max(0, block.w - 24));
          p.it.y = clampN(p.y + (ev2.clientY - sy) * (block.h / (rect.height || block.h)), 0, 99999);
          var di = block.items.indexOf(p.it);
          if (di >= 0 && itemDivs[di]) { styleItem(itemDivs[di], p.it); }
        });
        growStage(); /* unteren Rand erreicht: Flaeche waechst mit (#67) */
      }
      function onUp() {
        dragActive = false;
        if (handle.removeEventListener) {
          handle.removeEventListener('pointermove', onMove);
          handle.removeEventListener('pointerup', onUp);
          handle.removeEventListener('pointercancel', onUp);
        }
        collect(); markDirty();
      }
      try { if (handle.setPointerCapture) { handle.setPointerCapture(ev.pointerId); } } catch (e) { /* aeltere Browser */ }
      handle.addEventListener('pointermove', onMove);
      handle.addEventListener('pointerup', onUp);
      handle.addEventListener('pointercancel', onUp);
    });
  }
  /* Skalieren ueber alle vier Ecken (#67): corner ist 'nw'/'ne'/'sw'/'se',
   * die gegenueberliegende Ecke bleibt als Anker fix. Proportional bei
   * Bild, Strich und Video, nur Breite bei Text, Einbettung und Audio. */
  function attachResize(handle, item, div, corner) {
    handle.addEventListener('pointerdown', function (ev) {
      if (mode !== 'select' || dragActive) { return; }
      if (ev && ev.__icvDragging) { return; }
      /* Ohne Koordinaten (Test-Harness, synthetische Events) kein Resize-Start. */
      if (typeof ev.clientX !== 'number' || typeof ev.clientY !== 'number') { return; }
      if (ev) { ev.__icvDragging = true; }
      dragActive = true;
      if (ev.preventDefault) { ev.preventDefault(); }
      var sx = ev.clientX, sy = ev.clientY;
      var ox = item.x, oy = item.y, ow = item.w, oh = item.h || 0;
      var prop = item.kind === 'image' || item.kind === 'ink' || item.kind === 'video';
      var growW = corner.indexOf('e') >= 0;  /* Ecke rechts: w wachst */
      var growN = corner.indexOf('n') >= 0;  /* Ecke oben: h wachst nach oben */
      function onMove(ev2) {
        if (typeof ev2.clientX !== 'number' || typeof ev2.clientY !== 'number') { return; }
        var rect = stageRect();
        var dx = (ev2.clientX - sx) * (block.w / (rect.width || block.w));
        var nw = clampN(growW ? ow + dx : ow - dx, 24, block.w);
        if (prop) {
          var f = ow > 0 ? nw / ow : 1;
          var nh = clampN(oh * f, 18, 99999);
          item.w = nw;
          item.h = oh > 0 ? nh : oh;
          if (corner === 'nw') { item.x = (ox + ow) - nw; item.y = (oy + oh) - (oh > 0 ? nh : oh); }
          else if (corner === 'ne') { item.x = ox; item.y = (oy + oh) - (oh > 0 ? nh : oh); }
          else if (corner === 'sw') { item.x = (ox + ow) - nw; item.y = oy; }
          else { item.x = ox; item.y = oy; }
        } else {
          item.w = clampN(nw, 60, block.w);
          if (!growW) { item.x = (ox + ow) - item.w; }
        }
        styleItem(div, item);
        growStage();
      }
      function onUp() {
        dragActive = false;
        if (handle.removeEventListener) {
          handle.removeEventListener('pointermove', onMove);
          handle.removeEventListener('pointerup', onUp);
          handle.removeEventListener('pointercancel', onUp);
        }
        collect(); markDirty();
      }
      try { if (handle.setPointerCapture) { handle.setPointerCapture(ev.pointerId); } } catch (e) { /* aeltere Browser */ }
      handle.addEventListener('pointermove', onMove);
      handle.addEventListener('pointerup', onUp);
      handle.addEventListener('pointercancel', onUp);
    });
  }
  function buildItem(item) {
    var div = el('div', 'icv-item ' + (item.kind === 'ink' ? 'icv-stroke' : 'icv-' + item.kind) + (item === sel ? ' icv-selected' : ''));
    styleItem(div, item);
    /* Verschieben-Leiste, sichtbar bei Auswahl (CSS) */
    var bar = el('div', 'icv-move');
    bar.title = 'Ziehen zum Verschieben';
    bar.appendChild(el('span', 'icv-grip', '⋮⋮'));
    attachMove(bar, item, div);
    div.appendChild(bar);
    if (item.kind === 'text') {
      var ta = el('textarea', 'mono icv-ta');
      ta.value = item.body || '';
      /* Hintergrund sitzt am ganzen Zettel (#68), nicht an der Textarea. */
      if (item.align && item.align !== 'left') { ta.style.textAlign = item.align; }
      if (typeof window !== 'undefined' && window.IC_AUTOCOMPLETE && window.IC_AUTOCOMPLETE.attach) {
        try { window.IC_AUTOCOMPLETE.attach(ta, 'md'); } catch (eAC) { /* harmlos */ }
      }
      ta.addEventListener('input', function () {
        item.body = ta.value;
        fitTa(ta); /* Hoehe folgt dem Text (#66) */
        collect(); markDirty();
      });
      div.appendChild(ta);
      fitTa(ta);
    } else if (item.kind === 'image') {
      var im = el('img', 'icv-img');
      im.src = mediaUrl(item.media); im.alt = item.alt || item.name || 'Bild';
      div.appendChild(im);
    } else if (item.kind === 'ink') {
      div.appendChild(buildInkSvg(item));
    } else if (item.kind === 'embed') {
      /* YouTube-Vorschau (#67): Thumbnail direkt aus der URL, ohne API. */
      var yt = ytThumbFor(item.url);
      if (yt) {
        var th = el('img', 'icv-embed-thumb');
        th.setAttribute('src', yt);
        th.setAttribute('alt', 'Vorschau');
        div.appendChild(th);
      }
      div.appendChild(el('div', 'icv-ph', '🔗 ' + ((item.url || '').indexOf('learningapps') >= 0 ? 'LearningApps' : (item.url || '').indexOf('youtube') >= 0 ? 'YouTube' : 'Einbettung')));
      div.appendChild(el('div', 'muted icv-ph-sub', (item.title ? item.title + ' · ' : '') + (item.url || 'Adresse in der Leiste oben eingeben (YouTube oder LearningApps)')));
    } else if (item.kind === 'audio') {
      div.appendChild(el('div', 'icv-ph', '🔊 Audio'));
      div.appendChild(el('div', 'muted icv-ph-sub', (item.name || 'Audio') + ' · im Lesemodus abspielbar'));
    } else if (item.kind === 'video') {
      /* Erster Frame als Vorschau (#67): preload=metadata holt das Standbild,
       * pointer-events: none (CSS) haelt Ziehen und Skalieren frei. */
      var vid = el('video', 'icv-video-preview');
      vid.setAttribute('src', mediaUrl(item.media));
      vid.setAttribute('preload', 'metadata');
      vid.setAttribute('muted', 'muted');
      vid.setAttribute('playsinline', '');
      div.appendChild(vid);
      div.appendChild(el('div', 'muted icv-ph-sub', (item.name || 'Video') + ' · im Lesemodus abspielbar'));
    }
    /* Vier Ecken zum Skalieren (#67), sichtbar nur bei Auswahl (CSS). */
    var corners = ['nw', 'ne', 'sw', 'se'];
    for (var ci = 0; ci < corners.length; ci++) {
      var rs = el('div', 'icv-rs icv-rs-' + corners[ci]);
      rs.title = 'Ecke ziehen zum Skalieren';
      attachResize(rs, item, div, corners[ci]);
      div.appendChild(rs);
    }
    /* Loeschen-Knopf am Element (#66): Sichtbar bei Auswahl (CSS), entfernt
     * das Element direkt - bisher war Loeschen nur ueber die Symbolleiste
     * moeglich und die Entf-Taste tat gar nichts. */
    var del = el('button', 'icv-del', '✕');
    del.type = 'button';
    del.title = 'Element entfernen (Entf-Taste geht auch)';
    del.addEventListener('click', function (ev) {
      if (ev && ev.stopPropagation) { ev.stopPropagation(); }
      removeItem(item);
    });
    div.appendChild(del);
    div.addEventListener('pointerdown', function () {
      /* Waehrend eines laufenden Drags keine neue Auswahl (#67): Erst wenn
       * das Objekt wieder liegt, ist die Flaeche frei. */
      if (mode === 'select' && !dragActive && sel !== item) { selectItem(item); }
    });
    /* Ganze Fläche als Griff (#66): Striche, Bilder, Audio, Video und
     * Einbettungen lassen sich ueberall greifen. Textfelder weiter nur
     * ueber die Leiste oben, sonst waere Textauswahl und Tippen kaputt. */
    if (item.kind !== 'text') { attachMove(div, item, div); }
    return div;
  }
  var overlay = el('canvas', 'icv-inkcanvas');
  overlay.width = block.w; overlay.height = block.h;
  overlay.style.touchAction = 'none';
  var ctx = null;
  try { ctx = overlay.getContext ? overlay.getContext('2d') : null; } catch (e) { ctx = null; /* Harness */ }

  var itemDivs = [];
  function renderStage() {
    selDiv = null;
    stage.textContent = '';
    itemDivs = [];
    block.items.forEach(function (item) {
      var div = buildItem(item);
      itemDivs.push(div);
      if (item === sel) { selDiv = div; }
      stage.appendChild(div);
    });
    stage.appendChild(overlay);
    stage.appendChild(areaBtns); /* Flaechen-Knoepfe unten rechts (#68) */
    stage.appendChild(lassoSvg); /* Lasso-Overlay (#68) */
    if (selList.length) {
      /* Nach dem Neuaufbau die Lasso-Auswahl wieder aufdröckeln (#68) */
      selList.forEach(function (it) { markDiv(it, true); });
    }
  }

  /* ---------- Selektion und Kontextbereich ---------- */
  var ctxBar = el('div', 'icv-ctx');
  var ctxLabel = el('span', 'icv-ctx-label', '');
  var ctxText = el('div', 'icv-ctx-group');
  var ctxEmbed = el('div', 'icv-ctx-group');
  var ctxUrl = el('input', 'icv-ctx-input');
  ctxUrl.type = 'text';
  ctxUrl.placeholder = 'https://www.youtube.com/watch?v=... oder https://learningapps.org/...';
  var ctxTitle = el('input', 'icv-ctx-input');
  ctxTitle.type = 'text';
  ctxTitle.placeholder = 'Titel (optional)';
  ctxUrl.addEventListener('input', function () { if (sel && sel.kind === 'embed') { sel.url = ctxUrl.value; collect(); markDirty(); } });
  ctxTitle.addEventListener('input', function () { if (sel && sel.kind === 'embed') { sel.title = ctxTitle.value; collect(); markDirty(); } });
  TEXT_BGS.forEach(function (pair, i) {
    var bg = pair[0];
    var b = el('button', 'icv-bgbtn', pair[1]);
    b.type = 'button';
    b.title = 'Hintergrund des Textfelds: ' + pair[1];
    b.setAttribute('data-icv-bg', bg);
    if (bg && bg !== 'none') { b.style.background = bg; }
    b.addEventListener('click', function () {
      if (!sel || sel.kind !== 'text') { return; }
      sel.bg = bg;
      styleItem(selDiv, sel); /* faerbt den ganzen Zettel (#68) */
      collect(); markDirty();
    });
    ctxText.appendChild(b);
  });
  [['left', '⯇ Links'], ['center', '⯆ Zentriert'], ['right', '⯈ Rechts']].forEach(function (a) {
    var b = el('button', 'icv-bgbtn', a[1]);
    b.type = 'button';
    b.title = 'Textausrichtung im Textfeld';
    b.setAttribute('data-icv-align', a[0]);
    b.addEventListener('click', function () {
      if (!sel || sel.kind !== 'text') { return; }
      sel.align = a[0];
      var ta = selDiv && selDiv.querySelector ? selDiv.querySelector('textarea') : null;
      if (ta) { ta.style.textAlign = a[0]; }
      collect(); markDirty();
    });
    ctxText.appendChild(b);
  });
  ctxEmbed.appendChild(ctxUrl);
  ctxEmbed.appendChild(ctxTitle);
  /* Gruppieren/Loesen (#68): nur sinnvoll mit Auswahl, deshalb im
   * Kontextbereich - die Leiste bleibt frei von Auswahl-abhaengigen Icons. */
  var grpBtn = el('button', 'icv-bgbtn', '🪢 Gruppieren');
  grpBtn.type = 'button';
  grpBtn.title = 'Alle ausgewählten Objekte zu einer Gruppe verbinden: sie bewegen sich dann gemeinsam';
  grpBtn.setAttribute('data-icv-group', '1');
  grpBtn.addEventListener('click', function () {
    if (selList.length < 2) { return; }
    var gid = nextGroupId();
    selList.forEach(function (it) { it.g = gid; });
    collect(); markDirty(); updateCtx();
  });
  ctxBar.appendChild(grpBtn);
  var ungrpBtn = el('button', 'icv-bgbtn', '✂️ Gruppe lösen');
  ungrpBtn.type = 'button';
  ungrpBtn.title = 'Gruppe wieder auflösen: die Objekte bewegen sich wieder einzeln';
  ungrpBtn.setAttribute('data-icv-ungroup', '1');
  ungrpBtn.addEventListener('click', function () {
    var target = (sel && sel.g) ? sel : (selList[0] && selList[0].g ? selList[0] : null);
    if (!target || !target.g) { return; }
    var gid = target.g;
    block.items.forEach(function (it) { if (it.g === gid) { delete it.g; } });
    collect(); markDirty(); updateCtx();
  });
  ctxBar.appendChild(ungrpBtn);
  function nextGroupId() {
    var max = 0;
    block.items.forEach(function (it) { if (it.g && it.g > max) { max = it.g; } });
    return max + 1;
  }
  ctxBar.appendChild(ctxLabel);
  ctxBar.appendChild(ctxText);
  ctxBar.appendChild(ctxEmbed);

  function updateCtx() {
    if (!ctxBar.classList) { return; }
    if (!sel && selList.length === 0) {
      ctxBar.classList.remove('icv-toolbar-show');
      refreshToolVis();
      return;
    }
    ctxBar.classList.add('icv-toolbar-show');
    if (selList.length > 1) {
      /* Lasso-Mehrfachauswahl (#68): nur Gruppieren, keine Einzel-Einstellungen */
      ctxLabel.textContent = 'Ausgewählt: ' + selList.length + ' Objekte';
      if (ctxText.style) { ctxText.style.display = 'none'; }
      if (ctxEmbed.style) { ctxEmbed.style.display = 'none'; }
      if (grpBtn.style) { grpBtn.style.display = ''; }
      var sameGroup = selList.every(function (it) { return it.g && it.g === selList[0].g; });
      if (ungrpBtn.style) { ungrpBtn.style.display = sameGroup ? '' : 'none'; }
    } else {
      ctxLabel.textContent = 'Ausgewählt: ' + (KIND_NAMES[sel.kind] || sel.kind);
      if (ctxText.style) { ctxText.style.display = sel.kind === 'text' ? '' : 'none'; }
      if (ctxEmbed.style) { ctxEmbed.style.display = sel.kind === 'embed' ? '' : 'none'; }
      if (grpBtn.style) { grpBtn.style.display = 'none'; }
      if (ungrpBtn.style) { ungrpBtn.style.display = (sel && sel.g) ? '' : 'none'; }
      if (sel.kind === 'embed') { ctxUrl.value = sel.url || ''; ctxTitle.value = sel.title || ''; }
    }
    refreshToolVis();
  }
  /* Icon-Audit (#68): Stift-Farben und Strichstaerken gehoeren zum Stift-
   * werkzeug oder zu einem ausgewaehlten Strich. Ohne beides bleiben sie
   * aus der Leiste weg, dafuer sind die Einfuegen-Icons immer da. */
  function refreshToolVis() {
    var inkSel = (sel && sel.kind === 'ink') || selList.some(function (it) { return it && it.kind === 'ink'; });
    var show = mode === 'draw' || inkSel;
    if (swatchWrap.style) { swatchWrap.style.display = show ? '' : 'none'; }
    if (swWrap.style) { swWrap.style.display = show ? '' : 'none'; }
  }
  function selectItem(item) {
    /* Auswahl ohne DOM-Neubau (#66): renderStage() hier wuerde die gerade
     * laufenden Pointer-Verbindungen (Drag am Element, Strich greifen)
     * abbrechen lassen. Stattdessen nur die Klassen umsetzen. */
    clearMulti(); /* Lasso-Auswahl weicht der Einzelwahl (#68) */
    if (sel !== item) {
      var prevDiv = selDiv;
      sel = item;
      if (prevDiv && prevDiv.classList) { prevDiv.classList.remove('icv-selected'); }
      var idx = block.items.indexOf(item);
      selDiv = (idx >= 0 && itemDivs[idx]) ? itemDivs[idx] : null;
      if (selDiv && selDiv.classList) { selDiv.classList.add('icv-selected'); }
    } else if (!selDiv) {
      var idx2 = block.items.indexOf(item);
      selDiv = (idx2 >= 0 && itemDivs[idx2]) ? itemDivs[idx2] : null;
    }
    updateCtx();
  }
  /* ---------- Mehrfachauswahl und Gruppieren (#68) ---------- */
  function markDiv(it, on) {
    var idx = block.items.indexOf(it);
    var d = idx >= 0 ? itemDivs[idx] : null;
    if (d && d.classList) { d.classList.toggle('icv-selected', !!on); }
  }
  function clearMulti() {
    selList.forEach(function (it) { markDiv(it, false); });
    selList = [];
  }
  function deselect() {
    clearMulti();
    sel = null;
    if (selDiv && selDiv.classList) { selDiv.classList.remove('icv-selected'); }
    selDiv = null;
    updateCtx();
  }
  function removeItem(item) {
    var i = block.items.indexOf(item);
    if (i >= 0) { block.items.splice(i, 1); }
    if (sel === item) { sel = null; selDiv = null; }
    renderStage();
    updateCtx();
    collect(); markDirty();
  }

  /* ---------- Symbolleiste (GoodNotes-artig, sticky oben) ----------
   * Icons only (#68): Die Buttons zeigen nur ihr Icon, die Beschreibung
   * steht im title und erscheint beim Hover. Auswahl-abhaengige Dinge
   * (Loeschen, Hintergrund, Ausrichtung, Gruppieren) liegen im
   * Kontextbereich, der nur mit Auswahl erscheint. */
  var toolbar = el('div', 'icv-toolbar');
  var toolBtns = {};
  [['select', '🖱️', 'Auswahl: Elemente anklicken, verschieben, Text bearbeiten; Ziehen über die Fläche = Lasso'],
   ['draw', '✏️', 'Stift: Freihand zeichnen, auch über Bildern (Apple Pencil: druckempfindlich)'],
   ['eraser', '🧽', 'Radierer: Gezeichnete Striche wegradieren']].forEach(function (t) {
    var b = el('button', 'btn btn-ghost icv-mode-btn' + (t[0] === 'select' ? ' icv-mode-btn-active' : ''), t[1]);
    b.type = 'button';
    b.title = t[2];
    b.setAttribute('data-icv-tool', t[0]);
    b.addEventListener('click', function () { setMode(t[0]); });
    toolBtns[t[0]] = b;
    toolbar.appendChild(b);
  });
  function setMode(m) {
    mode = m;
    ['select', 'draw', 'eraser'].forEach(function (k) {
      if (toolBtns[k] && toolBtns[k].classList) { toolBtns[k].classList.toggle('icv-mode-btn-active', k === m); }
    });
    if (stage.classList) {
      stage.classList.remove('icv-mode-draw');
      stage.classList.remove('icv-mode-eraser');
      if (m === 'draw') { stage.classList.add('icv-mode-draw'); }
      if (m === 'eraser') { stage.classList.add('icv-mode-eraser'); }
    }
    refreshToolVis(); /* Stift-Icons nur beim Stift bzw. Strich-Auswahl (#68) */
  }
  var swatchWrap = el('div', 'icv-swatches');
  function refreshSwatches() {
    var btns = swatchWrap.children || [];
    for (var i = 0; i < btns.length; i++) {
      if (btns[i].classList) { btns[i].classList.toggle('icv-swatch-active', btns[i].getAttribute('data-icv-color') === penColor); }
    }
  }
  PEN_COLORS.forEach(function (c) {
    var s = el('button', 'icv-swatch', '');
    s.type = 'button';
    s.title = 'Stiftfarbe' + (sel && sel.kind === 'ink' ? ' (ändert den ausgewählten Strich)' : '');
    s.setAttribute('data-icv-color', c);
    s.style.background = c;
    s.addEventListener('click', function () {
      penColor = c;
      refreshSwatches();
      if (sel && sel.kind === 'ink') { sel.c = c; applyInkStyle(sel); collect(); markDirty(); }
    });
    swatchWrap.appendChild(s);
  });
  var swWrap = el('div', 'icv-sws');
  function refreshSw() {
    var btns = swWrap.children || [];
    for (var i = 0; i < btns.length; i++) {
      if (btns[i].classList) { btns[i].classList.toggle('icv-sw-active', parseInt(btns[i].getAttribute('data-icv-sw'), 10) === penSw); }
    }
  }
  [[2, 'Fein', 10], [4, 'Mittel', 14], [8, 'Dick', 18], [16, 'Extra dick', 24]].forEach(function (w) {
    var b = el('button', 'icv-swbtn', '●');
    b.type = 'button';
    b.title = 'Strichstärke: ' + w[1] + ' (' + w[0] + ')';
    b.style.fontSize = w[2] + 'px';
    b.setAttribute('data-icv-sw', String(w[0]));
    b.addEventListener('click', function () {
      penSw = w[0];
      refreshSw();
      if (sel && sel.kind === 'ink') { sel.sw = w[0]; applyInkStyle(sel); collect(); markDirty(); }
    });
    swWrap.appendChild(b);
  });
  toolbar.appendChild(swatchWrap);
  toolbar.appendChild(swWrap);
  /* Einfuegen: Icons only (#68), Beschreibung als Hover (title) */
  var txtBtn = el('button', 'btn btn-ghost', '📝'); txtBtn.type = 'button';
  txtBtn.title = 'Textfeld einfügen (oder freie Stelle doppelt antippen)';
  txtBtn.addEventListener('click', function () { setMode('select'); addTextAt(block.w / 2 - 170, block.h / 2 - 60); });
  toolbar.appendChild(txtBtn);
  var imgBtn = el('button', 'btn btn-ghost', '🖼️'); imgBtn.type = 'button';
  imgBtn.title = 'Bild einfügen (hochladen oder einfach auf die Fläche ziehen)';
  var imgFile = el('input', 'iblock-file icv-file'); imgFile.type = 'file';
  imgFile.accept = 'image/png,image/jpeg,image/gif,image/webp';
  imgBtn.addEventListener('click', function () { if (imgFile.click) { imgFile.click(); } });
  imgFile.addEventListener('change', function () {
    if (imgFile.files && imgFile.files[0]) { addImageAt(imgFile.files[0], block.w / 2 - 160, block.h / 2 - 120); }
  });
  toolbar.appendChild(imgBtn);
  toolbar.appendChild(imgFile);
  var audBtn = el('button', 'btn btn-ghost', '🔊'); audBtn.type = 'button';
  audBtn.title = 'Audio einfügen (hochladen, im Lesemodus abspielbar)';
  var audFile = el('input', 'iblock-file icv-file'); audFile.type = 'file';
  audFile.accept = 'audio/*';
  audBtn.addEventListener('click', function () { if (audFile.click) { audFile.click(); } });
  audFile.addEventListener('change', function () {
    if (audFile.files && audFile.files[0]) { addMediaAt('audio', audFile.files[0]); }
  });
  toolbar.appendChild(audBtn);
  toolbar.appendChild(audFile);
  var vidBtn = el('button', 'btn btn-ghost', '🎞️'); vidBtn.type = 'button';
  vidBtn.title = 'Video einfügen (hochladen, im Lesemodus abspielbar)';
  var vidFile = el('input', 'iblock-file icv-file'); vidFile.type = 'file';
  vidFile.accept = 'video/*';
  vidBtn.addEventListener('click', function () { if (vidFile.click) { vidFile.click(); } });
  vidFile.addEventListener('change', function () {
    if (vidFile.files && vidFile.files[0]) { addMediaAt('video', vidFile.files[0]); }
  });
  toolbar.appendChild(vidBtn);
  toolbar.appendChild(vidFile);
  var embBtn = el('button', 'btn btn-ghost', '🔗'); embBtn.type = 'button';
  embBtn.title = 'Einbettung einfügen: YouTube (ohne Cookies) oder LearningApps';
  embBtn.addEventListener('click', function () { addEmbedAt(block.w / 2 - 240, block.h / 2 - 40); });
  toolbar.appendChild(embBtn);
  toolbar.appendChild(ctxBar);
  /* Flaechenhoehe (#67): nach unten wachst automatisch, hier geht es
   * manuell: groesser immer, kleiner nur bis knapp unter das unterste
   * Element (Minimum 500). Die Knoepfe sitzen unten rechts IN der Flaeche
   * (#68), nicht mehr in der Leiste. */
  var areaBtns = el('div', 'icv-area-btns');
  var growBtn = el('button', 'btn btn-ghost', '⬇️');
  growBtn.type = 'button';
  growBtn.title = 'Fläche um 300 vergrößern';
  growBtn.setAttribute('data-icv-grow', '1');
  growBtn.addEventListener('click', function () {
    block.h = Math.ceil(block.h) + 300;
    updateStageStyle();
    relayoutAll();
    collect(); markDirty();
  });
  areaBtns.appendChild(growBtn);
  var shrinkBtn = el('button', 'btn btn-ghost', '⬆️');
  shrinkBtn.type = 'button';
  shrinkBtn.title = 'Fläche verkleinern, maximal bis zum untersten Element';
  shrinkBtn.setAttribute('data-icv-shrink', '1');
  shrinkBtn.addEventListener('click', function () {
    var min = Math.max(500, Math.ceil(itemsBottom()) + 60);
    block.h = Math.max(min, Math.ceil(block.h) - 300);
    updateStageStyle();
    relayoutAll();
    collect(); markDirty();
  });
  areaBtns.appendChild(shrinkBtn);
  /* Loeschen braucht eine Auswahl (#68): deshalb im Kontextbereich statt
   * dauerhaft in der Leiste (X am Element und Entf-Taste gibt es weiter). */
  var delBtn = el('button', 'btn btn-danger icv-delbtn', '🗑'); delBtn.type = 'button';
  delBtn.title = 'Ausgewähltes Element entfernen';
  delBtn.addEventListener('click', function () { if (sel) { removeItem(sel); } });
  ctxBar.appendChild(delBtn);

  /* ---------- Einfuegen ---------- */
  function addTextAt(x, y) {
    var item = { kind: 'text', body: '', x: clampN(x, 0, block.w - 60), y: clampN(y, 0, block.h - 24), w: 340, bg: '', align: 'left' };
    block.items.push(item);
    renderStage(); /* sichtbar machen, selectItem baut das DOM nicht mehr neu (#66) */
    selectItem(item);
    var ta = selDiv && selDiv.querySelector ? selDiv.querySelector('textarea') : null;
    if (ta && ta.focus) { ta.focus(); }
    collect(); markDirty();
  }
  function addImageAt(file, x, y) {
    say('Lade Bild hoch ...');
    upload('image', file, function (data) {
      var item = { kind: 'image', media: data.id, name: data.name, alt: '', x: clampN(x, 0, block.w - 32), y: clampN(y, 0, block.h - 32), w: 320, h: 240 };
      block.items.push(item);
      renderStage(); /* sichtbar machen, selectItem baut das DOM nicht mehr neu (#66) */
      var probe = el('img');
      probe.onload = function () {
        if (probe.naturalWidth > 0) {
          var f = Math.min((block.w * 0.45) / probe.naturalWidth, 1);
          item.w = Math.max(24, Math.round(probe.naturalWidth * f));
          item.h = Math.max(18, Math.round(probe.naturalHeight * f));
        }
        renderStage(); collect(); markDirty();
      };
      probe.src = mediaUrl(data.id);
      selectItem(item); collect(); markDirty();
    });
  }
  function addMediaAt(kind, file) {
    say('Lade ' + (kind === 'audio' ? 'Audio' : 'Video') + ' hoch ...');
    upload(kind, file, function (data) {
      var item = kind === 'audio'
        ? { kind: 'audio', media: data.id, name: data.name, x: clampN(block.w / 2 - 180, 0, block.w - 60), y: clampN(block.h / 2 - 40, 0, block.h - 24), w: 360 }
        : { kind: 'video', media: data.id, name: data.name, x: clampN(block.w / 2 - 240, 0, block.w - 60), y: clampN(block.h / 2 - 135, 0, block.h - 24), w: 480, h: 270 };
      block.items.push(item);
      renderStage(); /* sichtbar machen, selectItem baut das DOM nicht mehr neu (#66) */
      selectItem(item); collect(); markDirty();
    });
  }
  function addEmbedAt(x, y) {
    var item = { kind: 'embed', url: '', title: '', x: clampN(x, 0, block.w - 100), y: clampN(y, 0, block.h - 40), w: 480 };
    block.items.push(item);
    renderStage(); /* sichtbar machen, selectItem baut das DOM nicht mehr neu (#66) */
    selectItem(item);
    if (ctxUrl && ctxUrl.focus) { ctxUrl.focus(); }
    collect(); markDirty();
  }

  /* ---------- Stift: Strich-Objekte zeichnen (#64) ---------- */
  var stroke = null;
  function inkSeg(x0, y0, x1, y1, c, w) {
    if (!ctx) { return; }
    try {
      ctx.strokeStyle = c;
      ctx.lineWidth = Math.max(1, w);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      ctx.moveTo(x0, y0);
      ctx.lineTo(x1, y1);
      ctx.stroke();
    } catch (e) { /* Harness */ }
  }
  function clearOverlay() {
    if (!ctx) { return; }
    try { ctx.clearRect(0, 0, overlay.width, overlay.height); } catch (e) { /* Harness */ }
  }
  function commitStroke() {
    if (!stroke) { return; }
    var p = stroke.pts;
    if (p.length < 2) { stroke = null; return; }
    if (p.length === 2) { p.push(p[0] + 1, p[1] + 1); } /* einzelner Tipp: sichtbarer Punkt */
    var minX = p[0], minY = p[1], maxX = p[0], maxY = p[1];
    for (var i = 0; i < p.length; i += 2) {
      if (p[i] < minX) { minX = p[i]; }
      if (p[i] > maxX) { maxX = p[i]; }
      if (p[i + 1] < minY) { minY = p[i + 1]; }
      if (p[i + 1] > maxY) { maxY = p[i + 1]; }
    }
    var rel = [];
    for (var j = 0; j < p.length; j += 2) { rel.push(num(p[j] - minX, 1), num(p[j + 1] - minY, 1)); }
    var item = { kind: 'ink', c: stroke.c, sw: stroke.sw, x: num(minX, 2), y: num(minY, 2), w: Math.max(4, num(maxX - minX, 2)), h: Math.max(4, num(maxY - minY, 2)), p: rel };
    block.items.push(item);
    stroke = null;
    clearOverlay();
    renderStage(); /* Strich sofort sichtbar, selectItem baut das DOM nicht mehr neu (#66) */
    growStage(); /* unter den Rand gezeichnet: Flaeche waechst mit (#67) */
    selectItem(item);
    collect(); markDirty();
  }
  overlay.addEventListener('pointerdown', function (ev) {
    if (mode === 'draw') {
      var p = toLogical(ev.clientX, ev.clientY);
      stroke = { pts: [p.x, p.y], c: penColor, sw: penSw };
      inkSeg(p.x, p.y, p.x, p.y, penColor, penSw);
      try { if (overlay.setPointerCapture) { overlay.setPointerCapture(ev.pointerId); } } catch (e) { /* aeltere Browser */ }
    } else if (mode === 'eraser') {
      eraseAt(toLogical(ev.clientX, ev.clientY));
      overlay.__icErase = true;
      try { if (overlay.setPointerCapture) { overlay.setPointerCapture(ev.pointerId); } } catch (e) { /* aeltere Browser */ }
    }
  });
  overlay.addEventListener('pointermove', function (ev) {
    if (mode === 'eraser' && overlay.__icErase) {
      if (ev.preventDefault) { ev.preventDefault(); }
      eraseAt(toLogical(ev.clientX, ev.clientY));
      return;
    }
    if (mode !== 'draw' || !stroke) { return; }
    if (ev.preventDefault) { ev.preventDefault(); }
    var p = toLogical(ev.clientX, ev.clientY);
    var n = stroke.pts.length;
    /* Druckempfindlichkeit wirkt live (Datensatz bleibt schlank) */
    var pr = ev.pressure && ev.pressure > 0 ? ev.pressure : 1;
    inkSeg(stroke.pts[n - 2], stroke.pts[n - 1], p.x, p.y, stroke.c, stroke.sw * (0.5 + pr));
    stroke.pts.push(p.x, p.y);
    if (stroke.pts.length > 4000) { commitStroke(); stroke = { pts: [p.x, p.y], c: penColor, sw: penSw }; } /* sehr lange Zuege teilen */
  });
  overlay.addEventListener('pointerup', function () {
    if (mode === 'draw') { commitStroke(); }
    overlay.__icErase = false;
  });
  overlay.addEventListener('pointercancel', function () {
    stroke = null;
    overlay.__icErase = false;
  });

  /* ---------- Radierer: getroffene Striche entfernen ---------- */
  function eraseAt(p) {
    var removed = false;
    for (var i = block.items.length - 1; i >= 0; i--) {
      var it = block.items[i];
      if (it.kind !== 'ink') { continue; }
      var pad = (it.sw || 4) + 6;
      if (p.x >= it.x - pad && p.x <= it.x + (it.w || 0) + pad && p.y >= it.y - pad && p.y <= it.y + (it.h || 0) + pad) {
        block.items.splice(i, 1);
        if (sel === it) { sel = null; selDiv = null; }
        removed = true;
      }
    }
    if (removed) { renderStage(); updateCtx(); collect(); markDirty(); }
  }

  /* ---------- Drag & Drop und Doppeltipp ---------- */
  stage.addEventListener('dragover', function (ev) {
    if (ev.preventDefault) { ev.preventDefault(); }
    if (stage.classList) { stage.classList.add('icv-drop'); }
  });
  stage.addEventListener('dragleave', function () {
    if (stage.classList) { stage.classList.remove('icv-drop'); }
  });
  stage.addEventListener('drop', function (ev) {
    if (ev.preventDefault) { ev.preventDefault(); }
    if (stage.classList) { stage.classList.remove('icv-drop'); }
    var f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
    if (!f) { return; }
    setMode('select');
    var p = toLogical(ev.clientX, ev.clientY);
    addImageAt(f, p.x - 160, p.y - 120);
  });
  stage.addEventListener('dblclick', function (ev) {
    if (mode !== 'select' || (ev.target && ev.target !== stage)) { return; }
    var p = toLogical(ev.clientX, ev.clientY);
    addTextAt(p.x - 170, p.y - 14);
  });
  /* ---------- Lasso (#68): Auswahl mit gedrueckter Maustaste ---------- */
  var lassoSvg = svgEl('svg');
  lassoSvg.setAttribute('class', 'icv-lasso');
  lassoSvg.setAttribute('preserveAspectRatio', 'none');
  var lassoLine = svgEl('polyline');
  lassoLine.setAttribute('fill', 'rgba(29, 78, 216, 0.08)');
  lassoLine.setAttribute('stroke', '#1d4ed8');
  lassoLine.setAttribute('stroke-width', '3');
  lassoLine.setAttribute('stroke-dasharray', '8 6');
  lassoLine.setAttribute('vector-effect', 'non-scaling-stroke');
  lassoSvg.appendChild(lassoLine);
  lassoSvg.style.display = 'none';
  function drawLasso(pts) {
    lassoSvg.setAttribute('viewBox', '0 0 ' + Math.max(4, block.w) + ' ' + Math.max(4, Math.round(block.h)));
    var s = '';
    for (var i = 0; i + 1 < pts.length; i += 2) { s += (i ? ' ' : '') + pts[i] + ',' + pts[i + 1]; }
    lassoLine.setAttribute('points', s);
    lassoSvg.style.display = '';
  }
  function itemBox(it) {
    var h;
    if (it.kind === 'image' || it.kind === 'ink' || it.kind === 'video') { h = it.h || 100; }
    else if (it.kind === 'embed') { h = 130; }
    else { h = 80; }
    return { x: it.x || 0, y: it.y || 0, w: it.w || 320, h: h };
  }
  function pointInPoly(x, y, poly) {
    var inside = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var xi = poly[i].x, yi = poly[i].y, xj = poly[j].x, yj = poly[j].y;
      if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) { inside = !inside; }
    }
    return inside;
  }
  function lassoHitsItem(poly, it) {
    var b = itemBox(it);
    var i;
    for (i = 0; i < poly.length; i++) {
      if (poly[i].x >= b.x && poly[i].x <= b.x + b.w && poly[i].y >= b.y && poly[i].y <= b.y + b.h) { return true; }
    }
    var corners = [[b.x, b.y], [b.x + b.w, b.y], [b.x, b.y + b.h], [b.x + b.w, b.y + b.h]];
    for (i = 0; i < corners.length; i++) {
      if (pointInPoly(corners[i][0], corners[i][1], poly)) { return true; }
    }
    return false;
  }
  function lassoSelect(pts) {
    var poly = [];
    for (var i = 0; i + 1 < pts.length; i += 2) { poly.push({ x: pts[i], y: pts[i + 1] }); }
    var hits = block.items.filter(function (it) { return lassoHitsItem(poly, it); });
    if (hits.length === 0) { deselect(); return; }
    if (hits.length === 1) { selectItem(hits[0]); return; }
    /* Mehrfachauswahl: alles markieren, was das Lasso beruehrt hat (#68) */
    sel = null;
    if (selDiv && selDiv.classList) { selDiv.classList.remove('icv-selected'); }
    selDiv = null;
    selList = hits;
    selList.forEach(function (it) { markDiv(it, true); });
    updateCtx();
  }
  stage.addEventListener('pointerdown', function (ev) {
    if (mode !== 'select' || dragActive) { return; }
    if (ev.target && ev.target !== stage) { return; }
    if (typeof ev.clientX !== 'number' || typeof ev.clientY !== 'number') { deselect(); return; }
    if (ev.preventDefault) { ev.preventDefault(); }
    /* Ziehen ueber die freie Flaeche zeichnet das Lasso (#68); ein simpler
     * Klick ohne Bewegung deselektiert wie bisher. */
    dragActive = true;
    var pts = [];
    function addPt(ev2) {
      var p = toLogical(ev2.clientX, ev2.clientY);
      pts.push(Math.round(p.x), Math.round(p.y));
      drawLasso(pts);
    }
    addPt(ev);
    function onLassoMove(ev2) {
      if (typeof ev2.clientX !== 'number' || typeof ev2.clientY !== 'number') { return; }
      addPt(ev2);
    }
    function onLassoUp() {
      dragActive = false;
      if (stage.removeEventListener) {
        stage.removeEventListener('pointermove', onLassoMove);
        stage.removeEventListener('pointerup', onLassoUp);
        stage.removeEventListener('pointercancel', onLassoUp);
      }
      lassoSvg.style.display = 'none';
      if (pts.length >= 8) { lassoSelect(pts); } else { deselect(); }
    }
    stage.addEventListener('pointermove', onLassoMove);
    stage.addEventListener('pointerup', onLassoUp);
    stage.addEventListener('pointercancel', onLassoUp);
  });
  /* Entf-/Backspace-Taste entfernt die Auswahl (#66): Die intuitive Erwartung
   * vom Desktop. Nie waehrend der Texteingabe (Textarea/Input). */
  if (typeof document !== 'undefined' && document.addEventListener) {
    document.addEventListener('keydown', function (ev) {
      if (mode !== 'select' || !sel) { return; }
      if (ev.key !== 'Delete' && ev.key !== 'Backspace' && ev.key !== 'Entf') { return; }
      var t = ev.target;
      if (t && t.tagName && (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT' || t.isContentEditable)) { return; }
      if (ev.preventDefault) { ev.preventDefault(); }
      removeItem(sel);
    });
  }

  /* ---------- Speichern: alles wird mit der Seite gesichert ---------- */
  function collect() {
    var entry = {
      type: 'canvas',
      w: num(block.w, 2),
      h: num(block.h, 2),
      items: block.items.map(function (item) {
        var e = { kind: item.kind };
        if (item.kind === 'text') {
          e.body = item.body || '';
          e.bg = item.bg || '';
          e.align = item.align || 'left';
        } else if (item.kind === 'ink') {
          e.c = item.c || '#1f2937';
          e.sw = item.sw || 4;
          e.p = (item.p || []).map(function (n) { return num(n, 1); });
        } else if (item.kind === 'embed') {
          e.url = item.url || '';
          e.title = item.title || '';
        } else {
          e.media = item.media || 0;
          e.name = item.name || '';
          if (item.alt) { e.alt = item.alt; }
        }
        e.x = num(item.x, 2);
        e.y = num(item.y, 2);
        e.w = num(item.w || 320, 2);
        if (item.g) { e.g = item.g; } /* Gruppen-ID (#68): Objekte bewegen sich gemeinsam */
        if (item.kind === 'image' || item.kind === 'ink' || item.kind === 'video') { e.h = num(item.h || 120, 2); }
        return e;
      })
    };
    if (block.ink) { entry.ink = block.ink; entry.inkName = block.inkName || ''; }
    var jsonIn = document.getElementById('ibook-blocks-json');
    if (jsonIn) { jsonIn.value = JSON.stringify([entry]); }
  }
  var saveform = document.getElementById('ibook-saveform');
  if (saveform) {
    /* Fallback, falls doch einmal per Enter abgeschickt wird (#67). */
    saveform.addEventListener('submit', function () { collect(); dirty = false; });
  }
  var titleInput = document.getElementById('ibook-title');
  if (titleInput) { titleInput.addEventListener('input', markDirty); }
  /* Autospeichern (#67): kurze Pause nach der letzten Aenderung, dann POST
   * als urlencoded-Body (kein FormData-Konstrukt am Fake-DOM noetig). Der
   * Server erkennt den Autosave am Header und antwortet JSON statt Redirect. */
  function doSave() {
    saveTimer = null;
    if (!dirty) { return; }
    collect();
    var params = new URLSearchParams();
    params.append('csrf', csrf);
    params.append('title', titleInput && titleInput.value ? String(titleInput.value) : '');
    var jsonIn = document.getElementById('ibook-blocks-json');
    params.append('blocks', jsonIn ? String(jsonIn.value) : '[]');
    try {
      fetch(saveUrl(), {
        method: 'POST',
        headers: { 'X-IC-Autosave': '1', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) {
            dirty = false;
            say('✓ Automatisch gespeichert' + (data.saved_at ? ' (' + data.saved_at + ')' : ''));
            var meta = document.getElementById('ibook-page-meta');
            if (meta && data.saved_at) {
              var t = String(meta.textContent || '');
              var cut = t.indexOf('Letzte Änderung');
              if (cut >= 0) { meta.textContent = t.slice(0, cut) + 'Letzte Änderung: ' + data.saved_at; }
            }
          } else {
            say('Speichern fehlgeschlagen: ' + ((data && data.error) || 'unbekannter Fehler'), true);
          }
        })
        .catch(function (err) { say('Speichern fehlgeschlagen: ' + err, true); });
    } catch (eSave) {
      /* Im Notfall ohne Antwort abfeuern: Hauptsache die Daten kommen an. */
      try {
        if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
          navigator.sendBeacon(saveUrl(), params.toString());
          dirty = false;
        }
      } catch (eB) { /* nichts mehr zu retten */ }
    }
  }
  if (typeof window !== 'undefined' && window.addEventListener) {
    window.addEventListener('beforeunload', function () {
      /* Offene Aenderungen gehen beim Verlassen nicht verloren (#67):
       * sendBeacon schreibt sie noch weg, ohne die Navigation aufzuhalten. */
      if (!dirty) { return; }
      collect();
      try {
        if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
          var params = new URLSearchParams();
          params.append('csrf', csrf);
          params.append('title', titleInput && titleInput.value ? String(titleInput.value) : '');
          var jsonIn = document.getElementById('ibook-blocks-json');
          params.append('blocks', jsonIn ? String(jsonIn.value) : '[]');
          navigator.sendBeacon(saveUrl(), params.toString());
          dirty = false;
        }
      } catch (eB) { /* Navigation darf nie haengen bleiben */ }
    });
  }

  status = el('div', 'ibook-status');
  editor.parentNode.insertBefore(status, editor.nextSibling);
  editor.appendChild(toolbar);
  editor.appendChild(stage);
  renderStage();
  refreshSwatches();
  refreshSw();
  updateCtx();
  collect();
})();


/* Globaler data-confirm-Schutz: Formulare mit data-confirm fragen vor dem
 * Absenden nach (z.B. InfoBook/Abschnitt loeschen). */
(function () {
  'use strict';
  if (typeof document === 'undefined' || !document.addEventListener) { return; }
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || !form.getAttribute || !form.getAttribute('data-confirm')) { return; }
    var msg = form.getAttribute('data-confirm');
    var ok = false;
    try { ok = window.confirm(msg); } catch (e) { ok = true; /* Harness ohne confirm */ }
    if (!ok) { ev.preventDefault(); }
  }, true);
})();
