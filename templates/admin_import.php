<section class="hero">
    <div class="hero-emoji">📥</div>
    <h1>CSV-Import</h1>
    <p>Klassenlisten importieren: Es werden nur Vorname, Nachname, Klasse und Rolle übernommen, mehr nicht.</p>
</section>

<div class="notice">
    <strong>💡 Neue Konten erhalten automatisch ein Einmalpasswort (OTP)</strong>
    <p>Nach dem Import erscheint eine Liste mit Anmeldenamen, OTP und Merksatz nach der Satzbaulogik ("Apfel, Berg, Katze und Wolke" ergibt z. B. Abkw42!). Diese Liste ist nur einmal sichtbar, also direkt ausdrucken oder sichern.</p>
</div>

<section class="panel">
    <h3>CSV hochladen oder einfügen</h3>
    <p class="muted">Erwartetes Format (Trennzeichen Semikolon, erste Zeile mit Überschriften):<br>
    <span class="mono">Vorname;Nachname;Klasse;Rolle</span></p>
    <p class="muted">Rolle: <span class="mono">Schülerin</span>, <span class="mono">Schüler</span> oder <span class="mono">Lehrkraft</span>. Fehlt die Rolle, wird ein Schülerkonto angelegt. Fehlt die Klasse, bleibt die Person ohne Klasse.</p>
    <form method="post" action="<?= url('admin/import') ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>CSV-Datei</span><input type="file" name="csv_file" accept=".csv,text/csv"></label>
        <label class="field"><span>...oder CSV-Text</span>
            <textarea name="csv_text" id="csv-text" rows="6" class="mono" placeholder="Vorname;Nachname;Klasse;Rolle&#10;Mia;Sommer;6R;Schülerin"></textarea>
        </label>
        <div class="btn-row">
            <button class="btn btn-ghost" type="button" id="fill-example">Beispiel einfügen</button>
            <button class="btn btn-primary" type="submit">Import starten</button>
        </div>
    </form>
    <p class="muted">Bestehende Konten (gleicher Vor- und Nachname) werden aktualisiert, keine Dubletten angelegt. Neue Konten bekommen einen Anmeldenamen (vorname.nachname) und ein OTP.</p>
</section>

