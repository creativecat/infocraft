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
