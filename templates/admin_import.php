<div class="two-col">
    <section class="panel">
        <h3>📥 Nutzer per CSV importieren</h3>
        <p>Erwartetes Format (Trennzeichen <code>;</code>, <code>,</code> oder Tab, Kopfzeile optional):</p>
        <pre class="code">Vorname;Nachname;Klasse;Rolle
Mia;Sommer;6R;Schülerin
Ben;Winter;;Lehrkraft</pre>
        <p class="muted">Rolle: Schülerin/Schüler, Lehrkraft oder Admin. Fehlt die Spalte Rolle, wird der Eintrag als Schülerin/Schüler angelegt. Datenschutz: gespeichert werden nur Vorname, Nachname, Klasse und Rolle, nichts darüber.</p>
        <form method="post" action="<?= url('admin/import') ?>" enctype="multipart/form-data" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span>CSV-Datei</span>
                <input type="file" name="csv_file" accept=".csv,.txt">
            </label>
            <label class="field">
                <span>oder CSV-Text einfügen</span>
                <textarea id="csv-text" name="csv_text" class="input" rows="10" placeholder="Vorname;Nachname;Klasse;Rolle"></textarea>
            </label>
            <div class="btn-row">
                <button class="btn btn-ghost" type="button" id="fill-example">Beispiel einfügen</button>
                <button class="btn btn-primary" type="submit">Importieren</button>
            </div>
        </form>
    </section>
    <section class="panel">
        <h3>Was der Import tut</h3>
        <ul class="feature-list">
            <li>Legt Klassen automatisch an, wenn sie noch nicht existieren.</li>
            <li>Bestehende Nutzer (gleicher Vor- und Nachname) werden aktualisiert, nicht doppelt angelegt.</li>
            <li>Der Import läuft in einer Transaktion: Bei einem Fehler wird nichts halbfertig geschrieben.</li>
        </ul>
        <p class="muted">Per CSV vorgelegte Konten werden später beim ersten SSO-Login automatisch mit dem Schulkonto verknüpft (Abgleich über Vor- und Nachname).</p>
    </section>
</div>
