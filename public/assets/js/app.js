/* InfoCraft: kleine UI-Helfer (Präsentationsmodus, Tabellenfilter, CSV-Beispiel) */
(function () {
  'use strict';

  // Präsentationsmodus: größere Ansicht, Auswahl bleibt pro Endgerät gespeichert.
  var root = document.documentElement;
  var key = 'infocraft.presenting';
  var toggle = document.getElementById('present-toggle');

  function syncLabel() {
    if (!toggle) { return; }
    toggle.textContent = root.classList.contains('presenting')
      ? '✓ Präsentationsmodus'
      : '🖥️ Präsentieren';
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
    var dragId = null;

    document.addEventListener('dragstart', function (e) {
      var card = e.target && e.target.closest ? e.target.closest('.project-card') : null;
      if (card) { dragId = card.getAttribute('data-id'); }
    });
    document.addEventListener('dragend', function () { dragId = null; });

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
          var id = dragId || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : null);
          if (!id) { return; }
          var csrf = body.getAttribute('data-csrf') || '';
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

/* ===== Klassendiagramme: Live-Vorschau und PNG-Export ===== */
(function () {
  'use strict';
  var page = document.querySelector('[data-diagram-page]');
  if (!page || !window.mermaid) { return; }
  var source = document.getElementById('diagram-source');
  var preview = document.getElementById('diagram-preview');
  var refreshBtn = document.getElementById('diagram-refresh');
  var pngBtn = document.getElementById('diagram-png');
  var timer = null;
  var seq = 0;
  var kind = page.getAttribute('data-diagram-kind') || '';

  function escapeText(text) {
    return String(text).replace(/[&<>]/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch] || ch;
    });
  }

  /* Datenfluss-DSL (wie src/DiagramDsl.php) fuer die Live-Vorschau nach Mermaid uebersetzen.
     Vollstaendige Zeilentreffer statt Anker: kein Rueckwaerts-Suchen, prueft index und Laenge. */
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
      if (t.slice(0, 10).toLowerCase() === 'datenfluss') { continue; }
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
    var out = ['flowchart LR'].concat(shapes, edges);
    return { error: '', mermaid: out.join(NL) };
  }

  function render() {
    if (!preview || !source) { return; }
    var text = source.value;
    if (kind === 'datenfluss') {
      var t = transpileDatenfluss(text);
      if (t.error) {
        preview.innerHTML = '<div class="notice"><strong>Datenflussdiagramm kann nicht gezeichnet werden</strong><p>' + escapeText(t.error) + '</p></div>';
        return;
      }
      text = t.mermaid;
    }
    seq += 1;
    window.mermaid.render('dg-svg-' + seq, text).then(function (out) {
      preview.innerHTML = out.svg;
    }).catch(function (err) {
      preview.innerHTML = '<div class="notice"><strong>Diagramm kann nicht gezeichnet werden</strong><p>' + escapeText(err && err.message ? err.message : 'Unbekannter Fehler im Mermaid-Quelltext.') + '</p></div>';
    });
  }

  function exportPng() {
    if (!preview) { return; }
    var svg = preview.querySelector('svg');
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
      var name = pngBtn && pngBtn.getAttribute('data-png-name') ? pngBtn.getAttribute('data-png-name') : 'klassendiagramm';
      link.download = name + '.png';
      link.href = canvas.toDataURL('image/png');
      link.click();
    };
    img.onerror = function () {
      window.alert('PNG-Export fehlgeschlagen. Der Mermaid-Quelltext bleibt kopierbar.');
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
  }

  window.mermaid.initialize({ startOnLoad: false });
  render();
  if (refreshBtn) { refreshBtn.addEventListener('click', render); }
  if (pngBtn) { pngBtn.addEventListener('click', exportPng); }
  if (source) {
    source.addEventListener('input', function () {
      if (timer) { clearTimeout(timer); }
      timer = setTimeout(render, 500);
    });
  }
})();
/* InfoCraft: Syntax-Highlighting in allen Editoren (highlight.js, Overlay-Technik) */
(function () {
  'use strict';
  if (typeof hljs === 'undefined') { return; }
  var NL = String.fromCharCode(10);

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
    area.addEventListener('scroll', sync);
  });
})();

