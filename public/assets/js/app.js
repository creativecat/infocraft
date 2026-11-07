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

  // Sidebar: Klick oben links schaltet voll -> mini -> ausgeblendet (bleibt pro Endgerät gespeichert).
  var sidebarBtn = document.getElementById('sidebar-toggle');
  var sidebarKey = 'infocraft.sidebar';
  var sidebarStates = ['full', 'mini', 'hidden'];

  function applySidebar(state) {
    body.classList.remove('sidebar-mini', 'sidebar-hidden');
    if (state === 'mini') { body.classList.add('sidebar-mini'); }
    if (state === 'hidden') { body.classList.add('sidebar-hidden'); }
  }

  var sidebarState = localStorage.getItem(sidebarKey) || 'full';
  if (sidebarStates.indexOf(sidebarState) === -1) { sidebarState = 'full'; }
  applySidebar(sidebarState);

  if (sidebarBtn) {
    sidebarBtn.addEventListener('click', function () {
      var index = (sidebarStates.indexOf(sidebarState) + 1) % sidebarStates.length;
      sidebarState = sidebarStates[index];
      localStorage.setItem(sidebarKey, sidebarState);
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
/* InfoCraft Phase 3: HTML-Werkstatt Sofortvorschau, Strg+S speichern */
(function () {
  'use strict';

  var htmlEditor = document.getElementById('html-editor');
  var htmlPreview = document.getElementById('html-preview');
  if (htmlEditor && htmlPreview) {
    var updatePreview = function () {
      htmlPreview.srcdoc = htmlEditor.value;
    };
    var previewTimer = null;
    htmlEditor.addEventListener('input', function () {
      if (previewTimer) { clearTimeout(previewTimer); }
      previewTimer = setTimeout(updatePreview, 400);
    });
    updatePreview();
  }

  var saveForm = document.getElementById('html-form');
  if (saveForm) {
    saveForm.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        saveForm.submit();
      }
    });
  }
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

  function escapeText(text) {
    return String(text).replace(/[&<>]/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch] || ch;
    });
  }

  function render() {
    if (!preview || !source) { return; }
    seq += 1;
    window.mermaid.render('dg-svg-' + seq, source.value).then(function (out) {
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
