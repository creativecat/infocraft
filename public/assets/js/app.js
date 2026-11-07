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
  // Automatisches Speichern der Teilaufgaben-Antworten.
  var saveUrl = body.getAttribute('data-exam-save');
  var csrf = body.getAttribute('data-csrf');
  if (!saveUrl || !csrf) {
    return;
  }
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
