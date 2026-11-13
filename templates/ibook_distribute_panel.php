<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: gemeinsames Verteilen-Panel (Partial für Seiten- und Abschnittsansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.26.0 */ ?>
<?php /* #202: Gemeinsames Ziel-Formular fuer das Verteilen von Seiten und
 * Abschnitten. Der Ziel-Abschnitt laesst sich direkt waehlen oder mit eigenem
 * Titel neu angelegt werden; ohne Wahl nimmt der erste Abschnitt des
 * Zielbereichs. Erwartet: $book, $user, $distAction (Formular-Ziel),
 * $distLabel (Aufschrift), $distTitle (Knopf-Hinweistext) und $distHidden
 * (Liste von [name, value]-Paaren fuer versteckte Felder). */ ?>
<details class="ibook-distribute-details">
    <summary class="btn btn-ghost">📤 <?= e($distLabel) ?></summary>
    <form method="post" action="<?= $distAction ?>" class="form ibook-distribute-panel">
        <?= csrf_field() ?>
        <?php foreach ($distHidden as [$hName, $hValue]): ?>
        <input type="hidden" name="<?= e($hName) ?>" value="<?= e((string) $hValue) ?>">
        <?php endforeach; ?>
        <div class="form-row form">
            <label class="field"><span>Klasse</span>
                <select name="class_id" required>
                    <?php $distClasses = \Infocraft\Models\SchoolClass::forTeacher($user->id, $user->role === 'admin'); ?>
                    <?php foreach ($distClasses as $class): ?>
                    <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Zielbereich (falls neuer Abschnitt)</span>
                <select name="bereich">
                    <option value="lernbereich">📖 Lernbereich (Schülerinnen und Schüler lesen)</option>
                    <option value="gemeinsam">🤝 Zusammenarbeit (alle schreiben mit)</option>
                </select>
            </label>
        </div>
        <div class="form-row form">
            <label class="field"><span>Ziel-Abschnitt</span>
                <select name="section_id">
                    <option value="0">Automatisch: erster Abschnitt im Zielbereich (sonst neu anlegen)</option>
                    <option value="-1">Neuen Abschnitt anlegen (Titel rechts daneben)</option>
                    <?php $distAreaNames = ['lernbereich' => 'Lernbereich', 'gemeinsam' => 'Zusammenarbeit']; ?>
                    <?php foreach ($distClasses as $class): ?>
                    <?php $distBook = \Infocraft\Models\InfoBook::classBook((int) $class->id); ?>
                    <?php if ($distBook === null) { continue; } ?>
                    <?php foreach (\Infocraft\Models\InfoBook::sections($distBook->id) as $distSec): ?>
                    <?php if (!in_array((string) $distSec['area'], ['lernbereich', 'gemeinsam'], true)) { continue; } ?>
                    <option value="<?= (int) $distSec['id'] ?>">Klasse <?= e($class->name) ?>: <?= e((string) $distSec['title']) ?> (<?= e($distAreaNames[(string) $distSec['area']]) ?>)</option>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Titel für neuen Abschnitt</span>
                <input type="text" name="new_section_title" maxlength="160" placeholder="Nur nötig bei neuem Abschnitt">
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="<?= e($distTitle) ?>">📤 <?= e($distLabel) ?></button></div>
    </form>
</details>
