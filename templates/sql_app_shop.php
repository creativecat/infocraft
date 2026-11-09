<?php $appUrl = url('werkstatt/sql/' . e($template->slug) . '/app'); ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h3>📱 <?= e($template->title) ?>-App</h3>
            <p class="muted">Ein Mobil-Frontend auf deiner SQL-Datenbank (#46): Alles, was du hier bestellst, landet in denselben Tabellen, die du in der Werkstatt mit SELECT abfragst - und umgekehrt.</p>
        </div>
        <div class="btn-row">
            <a class="btn btn-ghost" href="<?= url('werkstatt/sql/' . e($template->slug)) ?>">← Werkstatt</a>
            <a class="btn btn-ghost" href="<?= $appUrl ?>">🔄 Aktualisieren</a>
        </div>
    </div>
    <?php if (!$canWrite): ?>
    <div class="notice">Nur Leserechte: Du kannst die App anschauen, aber nicht bestellen. Deine Lehrkraft kann dir Schreibrechte geben.</div>
    <?php endif; ?>
    <div class="phone-frame">
        <div class="app-bar">🛍️ InfoShop <span class="app-bar-sub">Bestellen als</span></div>
        <form method="post" action="<?= $appUrl ?>" class="app-who">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="kunde">
            <label class="field">
                <span class="sr-only">Kunde wählen</span>
                <select name="kunde_id" onchange="this.form.submit()">
                    <?php foreach ($kunden as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"<?= (int) $k['id'] === (int) $kundeId ? ' selected' : '' ?>><?= e($k['vorname'] . ' ' . $k['nachname'] . ' (' . $k['stadt'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button class="btn btn-ghost" type="submit">übernehmen</button></noscript>
        </form>
        <div class="app-body">
            <?php foreach ($produkte as $p): ?>
            <div class="app-card">
                <div class="app-card-title"><?= e($p['name']) ?></div>
                <div class="muted app-card-sub"><?= e($p['kategorie']) ?> · <?= number_format((float) $p['preis'], 2, ',', '.') ?> € · Lager: <?= (int) $p['lager'] ?></div>
                <?php if ($canWrite): ?>
                <form method="post" action="<?= $appUrl ?>" class="app-buy">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="kaufen">
                    <input type="hidden" name="produkt_id" value="<?= (int) $p['id'] ?>">
                    <label class="field"><span class="sr-only">Menge</span>
                        <input type="number" name="menge" value="1" min="1" max="99" class="app-menge">
                    </label>
                    <button class="btn btn-primary" type="submit">In den Warenkorb</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($produkte === []): ?>
            <p class="muted app-pad">Keine Produkte (mehr) in der Tabelle produkte.</p>
            <?php endif; ?>
        </div>
        <div class="app-bar app-bar-sec">📦 Letzte Bestellungen</div>
        <div class="app-body">
            <?php foreach ($bestellungen as $b): ?>
            <div class="app-row">
                <div>
                    <strong>#<?= (int) $b['id'] ?></strong> · <?= e($b['datum']) ?> · <?= (int) $b['positionen'] ?> Position(en) · <strong><?= number_format((float) $b['summe'], 2, ',', '.') ?> €</strong>
                    <div class="muted">Status: <?= e($b['status']) ?></div>
                </div>
                <?php if ($canWrite && $b['status'] === 'app'): ?>
                <form method="post" action="<?= $appUrl ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="storno">
                    <input type="hidden" name="bestellung_id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-ghost" type="submit">Storno</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($bestellungen === []): ?>
            <p class="muted app-pad">Noch keine Bestellungen für diesen Kunden.</p>
            <?php endif; ?>
        </div>
    </div>
    <p class="muted">Tipp für den Unterricht: Nach dem Bestellen zeigst du in der Werkstatt, was die App getan hat:
        <code>SELECT * FROM bestellungen;</code>, <code>SELECT * FROM bestellpositionen;</code> oder den Umsatz mit <code>SELECT SUM(menge * preis) FROM bestellpositionen JOIN produkte ON produkte.id = produkt_id;</code></p>
</section>
