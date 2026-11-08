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
    if (!window.mermaid) {
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
    } else if (!/^\s*classDiagram/i.test(text)) {
      /* Klassendiagramm: die classDiagram-Zeile zaehlt der Editor mit dazu, ohne dass jemand sie tippt. */
      text = 'classDiagram' + String.fromCharCode(10) + text;
    }
    seq += 1;
    window.mermaid.render('dg-svg-' + seq, text).then(function (out) {
      target.innerHTML = out.svg;
      if (currentKind === 'manuell') { styleClassDiagramSvg(target); }
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
  var KIND_LINE_RE = /^\s*(?:struktogramm|datenfluss|classDiagram)\s*\r?\n/i;

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
  render(false);
  if (refreshBtn) { refreshBtn.addEventListener('click', function () { render(false); }); }
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
