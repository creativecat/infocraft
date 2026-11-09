<section class="panel">
    <div class="panel-head">
        <h3>🌐 <?= e($project->title) ?></h3>
        <a class="btn btn-ghost" href="<?= url('werkstatt/html') ?>" title="Zurück">←</a>
        <?php if (($presentationTask ?? null) !== null): ?>
        <span class="pill" title="Präsentationsmodus: Du arbeitest in einer temporären Kopie dieser Aufgabe. Die Aufgabenvorlage und die Materialien bleiben unangetastet, dein Code wird nur hier gespeichert. Mit „Präsentation beenden“ wird die Kopie gelöscht.">🎬 Präsentationsmodus ℹ️</span>
        <?php endif; ?>
    </div>
    <?php if ($isForeign): ?>
    <div class="notice">
        <strong>🎓 Schülerprojekt von <?= e($ownerName) ?></strong>
        <p>Du bist als Lehrkraft hier. Änderungen speicherst du wie gewohnt, die Schülerin oder der Schüler sieht dieselbe Seite.</p>
    </div>
    <?php endif; ?>
    <?php if (($presentationTask ?? null) !== null): ?>
    <div class="two-col pres-task-info">
        <div>
            <h4 class="diag-head">📝 Aufgabenbeschreibung</h4>
            <?php if ($presentationTask->description !== ''): ?>
            <div class="task-desc"><?= $presentationTask->descriptionHtml() ?></div>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe ist keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="diag-head">📋 ToDos (zum Abhaken)</h4>
            <?php if (($presentationTodos ?? []) !== []): ?>
            <ul class="todo-list" id="pres-todos" data-task="<?= (int) $presentationTask->id ?>">
                <?php foreach ($presentationTodos as $ptodo): ?>
                <li class="todo-item">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                        <input type="checkbox" data-pres-todo="<?= (int) $ptodo->id ?>">
                        <span><?= e($ptodo->title) ?></span>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe sind keine ToDos hinterlegt.</p>
            <?php endif; ?>
            <?php if (($presentationMaterials ?? []) !== []): ?>
            <h4 class="diag-head">📎 Materialien der Aufgabe</h4>
            <ul class="material-list">
                <?php foreach ($presentationMaterials as $pmat): ?>
                <li class="material-item"><a href="<?= url('aufgaben/' . (int) $presentationTask->id . '/material/' . (int) $pmat->id) ?>">📄 <?= e($pmat->filename) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (trim($presentationTask->sampleSolution) !== ''): ?>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/loesung/fenster') ?>" target="_blank" title="Musterlösung der Aufgabe in einem eigenen Fenster anzeigen (bleibt unabhängig vom Präsentationsfenster offen, inkl. Erwartungshorizont)">📄 Musterlösung anzeigen</a>
            </div>
            <?php endif; ?>
            <div class="btn-row">
                <a class="btn btn-ghost" href="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/download') ?>" title="Diese Version als Musterlösung herunterladen">⬇️ als Musterlösung herunterladen</a>
                <form method="post" action="<?= url('aufgaben/' . (int) $presentationTask->id . '/praesentation/ende') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost" type="submit" title="Präsentation beenden und diese temporäre Kopie löschen">✕ beenden und löschen</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (($workingTask ?? null) !== null): ?>
    <div class="two-col pres-task-info task-work-info">
        <div>
            <h4 class="diag-head">📌 Aufgabe: <?= e($workingTask->title) ?></h4>
            <?php if ($workingTask->description !== ''): ?>
            <div class="task-desc"><?= $workingTask->descriptionHtml() ?></div>
            <?php else: ?>
            <p class="muted">Für diese Aufgabe ist keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>
        <div>
            <h4 class="diag-head">📋 ToDos (zum Abhaken)</h4>
            <form method="post" action="<?= url('aufgaben/' . (int) $workingTask->id . '/todos/schueler') ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= url('werkstatt/html/' . (int) $project->id) ?>">
                <ul class="todo-list">
                    <?php foreach (($workingTodos ?? []) as $wtodo): ?>
                    <li class="todo-item">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                            <input type="checkbox" onchange="this.form.submit()" name="toggle_template_id" value="<?= (int) $wtodo->id ?>"<?= ($workingDone[(int) $wtodo->id] ?? false) ? ' checked' : '' ?>>
                            <span<?= ($workingDone[(int) $wtodo->id] ?? false) ? ' style="text-decoration:line-through;"' : '' ?>><?= e($wtodo->title) ?></span>
                        </label>
                    </li>
                    <?php endforeach; ?>
                    <?php foreach (($workingOwnTodos ?? []) as $wown): ?>
                    <li class="todo-item">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:normal;">
                            <input type="checkbox" onchange="this.form.submit()" name="toggle_todo_id" value="<?= (int) $wown->id ?>"<?= $wown->done === 1 ? ' checked' : '' ?>>
                            <span<?= $wown->done === 1 ? ' style="text-decoration:line-through;"' : '' ?>><?= e($wown->title) ?></span>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </form>
            <?php if (($workingOwnTodos ?? []) === []): ?>
            <p class="muted">Eigene ToDos ergänzt du in der Aufgaben-Sicht unter „Meine Aufgaben“.</p>
            <?php endif; ?>
            <?php if (($workingMaterials ?? []) !== []): ?>
            <h4 class="diag-head">📎 Materialien der Aufgabe</h4>
            <ul class="material-list">
                <?php foreach ($workingMaterials as $wmat): ?>
                <li class="material-item"><a href="<?= url('aufgaben/' . (int) $workingTask->id . '/material/' . (int) $wmat->id) ?>">📄 <?= e($wmat->filename) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('aufgaben/' . (int) $workingTask->id . '/abgeben') ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                <input type="hidden" name="back" value="<?= url('werkstatt/html/' . (int) $project->id) ?>">
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit" title="Dieses Projekt als Lösung der Aufgabe einreichen"><?= $workingSubmitted ? 'Neue Version abgeben' : '✅ Abgeben' ?></button>
                </div>
            </form>
            <?php if ($workingSubmitted): ?><p class="muted">Dieses Projekt ist als Lösung abgegeben. Du kannst es verbessern und erneut abgeben.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= url('werkstatt/html/' . (int) $project->id) ?>" class="form" id="html-form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($project->title) ?>" required></label>

        <div class="vw-bar" role="tablist" aria-label="Ansichten">
            <button type="button" class="vw-tab vw-on" id="vt-wy" role="tab" aria-selected="true" aria-controls="vp-wy" data-vtab="wy" title="Gestalten">🖌️</button>
            <button type="button" class="vw-tab" id="vt-src" role="tab" aria-selected="false" aria-controls="vp-src" data-vtab="src" title="Quelltext">📝</button>
            <button type="button" class="vw-tab" id="vt-view" role="tab" aria-selected="false" aria-controls="vp-view" data-vtab="view" title="Vorschau">👁️</button>
        </div>

        <div class="html-io">
            <button type="button" class="btn btn-ghost" id="html-upload-btn" title="HTML-Datei öffnen">⬆</button>
            <input type="file" id="html-upload" accept=".html,.htm,text/html" hidden>
            <button type="button" class="btn btn-ghost" id="html-download" title="HTML herunterladen">⬇</button>
            <button type="button" class="btn btn-ghost" id="html-newwin" title="Vorschau in neuem Fenster">↗</button>
            <span class="muted" id="html-io-note" role="status" aria-live="polite"></span>
        </div>

        <div class="vw-pane" id="vp-wy" role="tabpanel" aria-labelledby="vt-wy" data-vpane="wy">
            <div class="wy-toolbar" role="toolbar" aria-label="Gestalten-Werkzeuge">
                <label class="wy-tool">Absatzart
                    <select id="wy-block" aria-label="Absatzart">
                        <option value="">wählen…</option>
                        <option value="p">Absatz</option>
                        <option value="h1">Überschrift 1</option>
                        <option value="h2">Überschrift 2</option>
                        <option value="h3">Überschrift 3</option>
                    </select>
                </label>
                <button type="button" class="wy-btn" data-wycmd="bold" title="Fett"><b>F</b></button>
                <button type="button" class="wy-btn" data-wycmd="italic" title="Kursiv"><i>K</i></button>
                <button type="button" class="wy-btn" data-wycmd="underline" title="Unterstrichen"><u>U</u></button>
                <button type="button" class="wy-btn" data-wycmd="insertUnorderedList" title="Liste mit Punkten">•</button>
                <button type="button" class="wy-btn" data-wycmd="insertOrderedList" title="Nummerierte Liste">1.</button>
                <label class="wy-tool">Farbe <input type="color" id="wy-color" value="#4f46e5" title="Schriftfarbe" aria-label="Schriftfarbe"></label>
                <button type="button" class="wy-btn" data-wycmd="createLink" title="Link einfügen">🔗</button>
                <button type="button" class="wy-btn" data-wycmd="undo" title="Rückgängig">↶</button>
                <button type="button" class="wy-btn" data-wycmd="redo" title="Wiederholen">↷</button>
            </div>
            <iframe id="html-wysiwyg" title="Seite gestalten" sandbox="allow-same-origin"></iframe>
            <p class="vw-hint">Tipp: Gestalte hier – und schau danach im 📝 Quelltext nach, welcher Tag bei deinem Klick entsteht. So lernst du, was jeder Effekt in HTML bedeutet.</p>
        </div>

        <div class="vw-pane" id="vp-src" role="tabpanel" aria-labelledby="vt-src" data-vpane="src" hidden>
            <div class="tag-help">
                <label class="wy-tool">❓ Tag erklären
                    <select id="tag-explain" aria-label="HTML-Tag erklären">
                        <option value="" selected disabled>&lt;…&gt;</option>
                        <option value="doctype">&lt;!DOCTYPE html&gt;</option>
                        <option value="html">&lt;html&gt;</option>
                        <option value="head">&lt;head&gt;</option>
                        <option value="meta">&lt;meta&gt;</option>
                        <option value="title">&lt;title&gt;</option>
                        <option value="style">&lt;style&gt;</option>
                        <option value="body">&lt;body&gt;</option>
                        <option value="h1">&lt;h1&gt; &lt;h2&gt; &lt;h3&gt;</option>
                        <option value="p">&lt;p&gt;</option>
                        <option value="br">&lt;br&gt;</option>
                        <option value="strong">&lt;strong&gt;</option>
                        <option value="em">&lt;em&gt;</option>
                        <option value="u">&lt;u&gt;</option>
                        <option value="a">&lt;a&gt;</option>
                        <option value="img">&lt;img&gt;</option>
                        <option value="list">&lt;ul&gt; &lt;ol&gt; &lt;li&gt;</option>
                        <option value="table">&lt;table&gt; &lt;tr&gt; &lt;th&gt; &lt;td&gt;</option>
                        <option value="div">&lt;div&gt;</option>
                        <option value="span">&lt;span&gt;</option>
                        <option value="hr">&lt;hr&gt;</option>
                        <option value="comment">&lt;!-- … --&gt;</option>
                        <option value="entities">&amp;lt; &amp;gt; &amp;amp;</option>
                    </select>
                </label>
                <div class="tag-cards" id="tag-cards">
                    <div class="tag-card" data-tag-card="doctype" hidden>
                        <strong>&lt;!DOCTYPE html&gt; – die Visitenkarte</strong>
                        <p>Der allererste Satz der Datei. Er sagt dem Browser: „Das ist modernes HTML!" Ohne ihn muss der Browser raten – und rät manchmal falsch.</p>
                        <pre class="mono">&lt;!DOCTYPE html&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="html" hidden>
                        <strong>&lt;html&gt; – der Rahmen der Seite</strong>
                        <p>Um alles andere legt sich der html-Tag. Er ist wie der Karton, in dem die ganze Seite steckt.</p>
                        <pre class="mono">&lt;html lang="de"&gt;
  …
&lt;/html&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="head" hidden>
                        <strong>&lt;head&gt; – der unsichtbare Kopf</strong>
                        <p>Hier stehen Informationen ÜBER die Seite: Titel, Zeichensatz, Styles. Im Browserfenster sieht man vom head nichts.</p>
                        <pre class="mono">&lt;head&gt;
  &lt;title&gt;Meine Seite&lt;/title&gt;
&lt;/head&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="meta" hidden>
                        <strong>&lt;meta&gt; – Einstellungen</strong>
                        <p>Meta heißt „über". Diese Zeile sagt dem Browser, welche Buchstaben die Seite benutzt – wichtig für Umlaute wie ä, ö, ü und ß.</p>
                        <pre class="mono">&lt;meta charset="UTF-8"&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="title" hidden>
                        <strong>&lt;title&gt; – der Name der Seite</strong>
                        <p>Er steht oben im Browser-Tab und landet später im Lesezeichen. Der title gehört in den head.</p>
                        <pre class="mono">&lt;title&gt;Lenas Webseite&lt;/title&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="style" hidden>
                        <strong>&lt;style&gt; – hier wohnt das CSS</strong>
                        <p>Farben, Schriften, Abstände: Was hier steht, gilt für die ganze Seite. Das ist die Schminktasche deiner Webseite.</p>
                        <pre class="mono">&lt;style&gt;
  h1 { color: #4f46e5; }
&lt;/style&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="body" hidden>
                        <strong>&lt;body&gt; – alles Sichtbare</strong>
                        <p>Texte, Bilder, Listen, Tabellen: Erst mit dem body beginnt die eigentliche Seite, die man im Fenster sieht.</p>
                        <pre class="mono">&lt;body&gt;
  &lt;h1&gt;Hallo!&lt;/h1&gt;
&lt;/body&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="h1" hidden>
                        <strong>&lt;h1&gt; bis &lt;h3&gt; – Überschriften</strong>
                        <p>Wie in einer Gliederung: h1 ist die wichtigste Überschrift (pro Seite nur einmal!), h2 und h3 darunter. Vorlese-Programme und Suchmaschinen bauen darauf.</p>
                        <pre class="mono">&lt;h1&gt;Meine Hobbys&lt;/h1&gt;
&lt;h2&gt;Sport&lt;/h2&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="p" hidden>
                        <strong>&lt;p&gt; – der Absatz</strong>
                        <p>p wie „paragraph", der englische Absatz. Die Seite wird ordentlich gegliedert, der Browser zeigt zwischen Absätzen etwas Abstand.</p>
                        <pre class="mono">&lt;p&gt;Dies ist ein Absatz.&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="br" hidden>
                        <strong>&lt;br&gt; – der Zeilenumbruch</strong>
                        <p>„break" erzwingt eine neue Zeile innerhalb eines Absatzes – zum Beispiel für ein Gedicht oder eine Adresse. br hat keinen Endtag.</p>
                        <pre class="mono">&lt;p&gt;Zeile eins&lt;br&gt;Zeile zwei&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="strong" hidden>
                        <strong>&lt;strong&gt; – wichtig!</strong>
                        <p>Heißt nicht „fett", sondern „wichtig". Der Browser zeigt es fett an – und Vorlese-Programme betonen es auch. Es bedeutet also mehr, nicht nur Optik.</p>
                        <pre class="mono">&lt;p&gt;&lt;strong&gt;Achtung:&lt;/strong&gt; Heiß!&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="em" hidden>
                        <strong>&lt;em&gt; – die Betonung</strong>
                        <p>„emphasis" heißt Betonung. Der Browser zeigt es kursiv – als würde die Stimme das Wort beim Vorlesen betonen.</p>
                        <pre class="mono">&lt;p&gt;Das ist &lt;em&gt;richtig&lt;/em&gt; gut.&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="u" hidden>
                        <strong>&lt;u&gt; – unterstrichen</strong>
                        <p>Unterstreicht Text. Im Web sparsam nutzen: Unterstreichungen verheißen sonst einen Link.</p>
                        <pre class="mono">&lt;p&gt;&lt;u&gt;Wichtig&lt;/u&gt; und gemerkt!&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="a" hidden>
                        <strong>&lt;a&gt; – der Link</strong>
                        <p>„anchor" heißt Anker – der berühmte Link! href („hyper reference") sagt, wohin die Reise geht. Zwischen die Tags schreibst du den Text, auf den man klickt.</p>
                        <pre class="mono">&lt;a href="https://www.wikipedia.de"&gt;Nachschlagen&lt;/a&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="img" hidden>
                        <strong>&lt;img&gt; – das Bild</strong>
                        <p>„image" = Bild. src zeigt, wo das Bild liegt. alt beschreibt es in Worten – für alle, die es nicht sehen können, und falls es nicht lädt. alt ist Pflicht!</p>
                        <pre class="mono">&lt;img src="bello.png" alt="Mein Hund Bello" width="300"&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="list" hidden>
                        <strong>&lt;ul&gt; &lt;ol&gt; &lt;li&gt; – Listen</strong>
                        <p>ul = ungeordnete Liste (Punkte), ol = geordnete Liste (Zahlen), li = ein Listeneintrag. Der Text gehört immer in li – nie direkt in ul oder ol.</p>
                        <pre class="mono">&lt;ul&gt;
  &lt;li&gt;Schwimmen&lt;/li&gt;
  &lt;li&gt;Lesen&lt;/li&gt;
&lt;/ul&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="table" hidden>
                        <strong>&lt;table&gt; &lt;tr&gt; &lt;th&gt; &lt;td&gt; – die Tabelle</strong>
                        <p>Zeilen (tr) und Zellen: th = Kopfzelle (fett, zentriert), td = normale Zelle. Tabellen sind für Daten da – fürs Layout nimmt man heute CSS.</p>
                        <pre class="mono">&lt;table&gt;
  &lt;tr&gt;&lt;th&gt;Name&lt;/th&gt;&lt;th&gt;Note&lt;/th&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;Lena&lt;/td&gt;&lt;td&gt;1&lt;/td&gt;&lt;/tr&gt;
&lt;/table&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="div" hidden>
                        <strong>&lt;div&gt; – die unsichtbare Box</strong>
                        <p>div bedeutet selbst nichts – es gruppiert nur. Erst mit CSS wird daraus eine Karte, ein Kasten oder eine Spalte.</p>
                        <pre class="mono">&lt;div class="karte"&gt;
  …
&lt;/div&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="span" hidden>
                        <strong>&lt;span&gt; – die Textmarkierung</strong>
                        <p>Markiert ein Stück Text mitten im Satz, ohne einen neuen Absatz zu beginnen – zum Beispiel, um ihm mit CSS eine eigene Farbe zu geben.</p>
                        <pre class="mono">&lt;p&gt;Das Wort ist &lt;span style="color: red"&gt;rot&lt;/span&gt;.&lt;/p&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="hr" hidden>
                        <strong>&lt;hr&gt; – die Trennlinie</strong>
                        <p>„horizontal rule" = waagerechte Linie, zum Beispiel zwischen zwei Themenblöcken. Sie hat keinen Endtag.</p>
                        <pre class="mono">&lt;h2&gt;Thema eins&lt;/h2&gt;
&lt;hr&gt;
&lt;h2&gt;Thema zwei&lt;/h2&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="comment" hidden>
                        <strong>&lt;!-- … --&gt; – der Notizzettel</strong>
                        <p>Ein unsichtbarer Kommentar im Code. Der Browser ignoriert ihn – aber wer in den Quelltext schaut, kann ihn lesen. Perfekt für Notizen an dich selbst!</p>
                        <pre class="mono">&lt;!-- Hier kommt bald die Bildergalerie --&gt;</pre>
                    </div>
                    <div class="tag-card" data-tag-card="entities" hidden>
                        <strong>&amp;lt; &amp;gt; &amp;amp; – Sonderzeichen</strong>
                        <p>Manche Zeichen haben Sonderaufgaben: &lt; beginnt einen Tag. Wie zeigt man dann ein echtes Kleiner-als? Mit Sondercodes: &amp;lt; macht &lt;, &amp;gt; macht &gt;, &amp;amp; macht &amp;.</p>
                        <pre class="mono">&lt;p&gt;5 &amp;amp;lt; 10&lt;/p&gt;</pre>
                    </div>
                </div>
            </div>
            <div class="hlwrap" data-hl="xml" data-ac="html">
                <textarea name="content" id="html-editor" rows="24" class="mono code-editor" spellcheck="false"><?= e($project->content) ?></textarea>
            </div>
        </div>

        <div class="vw-pane" id="vp-view" role="tabpanel" aria-labelledby="vt-view" data-vpane="view" hidden>
            <iframe id="html-preview" class="preview-frame" sandbox="" title="Vorschau deiner Seite"></iframe>
        </div>

        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
    </form>
</section>
<script src="<?= asset_url('assets/vendor/highlight.min.js') ?>"></script>

<?php if (($presentationTask ?? null) !== null): ?>
<script>
/* Präsentationsmodus: ToDos lokal abhakbar machen (bleibt im Browser erhalten,
 * auch nach einem Reload, ohne die Aufgaben-ToDos der Schüler zu verändern). */
(function () {
  'use strict';
  var box = document.getElementById('pres-todos');
  if (!box) { return; }
  var key = 'pres-todos-' + box.getAttribute('data-task');
  function readState() {
    try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; }
  }
  var boxes = box.querySelectorAll('input[data-pres-todo]');
  var i;
  var state = readState();
  for (i = 0; i < boxes.length; i++) {
    var tid = boxes[i].getAttribute('data-pres-todo');
    boxes[i].checked = !!state[tid];
    boxes[i].addEventListener('change', function () {
      var s = readState();
      s[this.getAttribute('data-pres-todo')] = this.checked;
      try { localStorage.setItem(key, JSON.stringify(s)); } catch (e) {}
    });
  }
})();
</script>
<?php endif; ?>
