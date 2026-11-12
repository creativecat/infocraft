<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Verwaltung: Einstellungen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.18.0
 */
/** @var string $defaultLanguage @var array<string, string> $legal @var \Infocraft\Models\User $user */
?>
<section class="hero">
    <div class="hero-emoji">⚙️</div>
    <h1>Admin-Einstellungen</h1>
    <p>Schulweite Vorgaben für alle Lehrkräfte und Schülerinnen und Schüler. Änderungen greifen sofort.</p>
</section>

<section class="panel">
    <h3>☕ Standard-Programmiersprache</h3>
    <p class="muted">Gilt überall, wo Java und Python zur Wahl stehen: neue Projekte in der Code-Werkstatt, Aufgaben-Varianten und Sprach-Auswahlklappen. Empfehlung am bayerischen Gymnasium (NTG) ist <strong>Java</strong>; wer in der Unterstufe mit Python arbeitet, stellt hier um.</p>
    <form method="post" action="<?= url('admin/einstellungen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="two-col">
            <label class="field"><span>Programmiersprache</span>
                <select name="default_language">
                    <option value="java"<?= $defaultLanguage === 'java' ? ' selected' : '' ?>>☕ Java</option>
                    <option value="python"<?= $defaultLanguage === 'python' ? ' selected' : '' ?>>🐍 Python</option>
                </select>
            </label>
            <div class="btn-row" style="align-items:flex-end;"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
        </div>
    </form>
</section>

<section class="panel">
    <h3>🏛️ Impressum und Datenschutz</h3>
    <p class="muted">Angaben für die öffentlichen Seiten Impressum und Datenschutz. Was hier hinterlegt ist, erscheint im Fuß der Plattform als Link. Die Mustervorlage (#172) füllt die Textfelder vor: Wer nichts Eigenes speichert, übernimmt den Vorgabetext; ein eigenes Feld ersetzt ihn dauerhaft.</p>
    <form method="post" action="<?= url('admin/einstellungen/rechtliches') ?>" class="form">
        <?= csrf_field() ?>
        <fieldset class="field legal-kind">
            <legend>Betreiber-Art (#167)</legend>
            <label class="inline"><input type="radio" name="legal_kind" value="school"<?= ($legal['kind'] ?? 'school') !== 'private' ? ' checked' : '' ?>> Schule / Institution</label>
            <label class="inline"><input type="radio" name="legal_kind" value="private"<?= ($legal['kind'] ?? '') === 'private' ? ' checked' : '' ?>> Privatperson</label>
            <p class="muted">Bei „Privatperson“ entfällt „Vertreten durch“, das Impressum nennt die Person selbst als Anbieterin oder Anbieter.</p>
        </fieldset>
        <div class="two-col">
            <label class="field"><span>Name des Anbieters (Schule/Institution oder Name der Privatperson)</span>
                <input type="text" name="legal_operator" value="<?= e($legal['operator']) ?>" placeholder="z. B. Gymnasium Musterstadt"></label>
            <label class="field"><span>Vertreten durch (nur bei Schule/Institution)</span>
                <input type="text" name="legal_person" value="<?= e($legal['person']) ?>" placeholder="z. B. Oberschulrätin"></label>
        </div>
        <div class="three-col">
            <label class="field"><span>Straße und Hausnummer</span>
                <input type="text" name="legal_street" value="<?= e($legal['street']) ?>" placeholder="z. B. Schulweg 3"></label>
            <label class="field"><span>PLZ</span>
                <input type="text" name="legal_zip" value="<?= e($legal['zip']) ?>" placeholder="z. B. 12345"></label>
            <label class="field"><span>Ort</span>
                <input type="text" name="legal_city" value="<?= e($legal['city']) ?>" placeholder="z. B. Musterstadt"></label>
        </div>
        <div class="two-col">
            <label class="field"><span>Telefon</span>
                <input type="text" name="legal_phone" value="<?= e($legal['phone']) ?>" placeholder="z. B. 09876 123456"></label>
            <label class="field"><span>E-Mail</span>
                <input type="text" name="legal_email" value="<?= e($legal['email']) ?>" placeholder="z. B. info@schule.example"></label>
        </div>
        <label class="field"><span>Ergänzende Angaben (Impressum)</span>
            <textarea name="legal_extra" rows="3" placeholder="z. B. Aufsicht, Registergericht"><?= e($legal['extra']) ?></textarea></label>
        <label class="field"><span>Datenschutzbeauftragte Person</span>
            <textarea name="legal_dsb" rows="2" placeholder="Name und Kontakt"><?= e($legal['dsb']) ?></textarea></label>
        <label class="field"><span>Hosting</span>
            <textarea name="legal_hosting" rows="2" placeholder="Anbieter und Adresse des Hostings"><?= e($legal['hosting']) ?></textarea></label>
        <label class="field"><span>Verarbeitete Daten</span>
            <textarea name="legal_data" rows="3" placeholder="Welche Daten werden erhoben, wofür, wie lange?"><?= e($legal['data']) ?></textarea></label>
        <label class="field"><span>Eigener Text (Datenschutz)</span>
            <textarea name="legal_text" rows="4" placeholder="Weitere Hinweise, Rechte der Betroffenen"><?= e($legal['text']) ?></textarea></label>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾 Impressum und Datenschutz speichern</button></div>
    </form>
</section>