/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Aufgaben: Bedienungshilfen im Browser
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
/**
 * Aufgabenbereich: WYSIWYG-Editor für Beschreibungen (Toolbar + Editierbereich,
 * ohne externe Abhängigkeiten) und ein-/ausblendbare Aufgabenbeschreibungen
 * in der Schüler-Sicht (Zustand pro Aufgabe im localStorage gemerkt).
 */
(function () {
    'use strict';

    function syncEditor(root) {
        var view = root.querySelector('.wysiwyg-view');
        var area = root.querySelector('textarea');
        var flag = root.parentElement && root.parentElement.querySelector('[name="description_html"]');
        if (!view || !area) {
            return;
        }
        area.value = view.innerHTML;
        if (flag) {
            flag.value = '1';
        }
    }

    function initWysiwyg(root) {
        var view = root.querySelector('.wysiwyg-view');
        var area = root.querySelector('textarea');
        if (!view || !area) {
            return;
        }
        if (root.dataset.initialHtml === '1') {
            view.innerHTML = area.value;
        } else if (area.value !== '') {
            view.textContent = area.value;
        }
        view.addEventListener('input', function () { syncEditor(root); });
        view.addEventListener('keyup', function () { syncEditor(root); });
        root.querySelectorAll('button[data-cmd]').forEach(function (button) {
            button.addEventListener('mousedown', function (event) {
                event.preventDefault();
                var cmd = button.dataset.cmd;
                var arg = button.dataset.arg || null;
                if (cmd === 'createlink') {
                    var href = window.prompt('Link-Adresse (https://...)', 'https://');
                    if (!href || href === 'https://') {
                        return;
                    }
                    document.execCommand(cmd, false, href);
                } else if (cmd === 'insertunorderedlist' || cmd === 'insertorderedlist' || cmd === 'formatblock') {
                    document.execCommand(cmd, false, arg);
                } else {
                    document.execCommand(cmd, false, null);
                }
                view.focus();
                syncEditor(root);
            });
        });
        var form = area.closest('form');
        if (form) {
            form.addEventListener('submit', function () { syncEditor(root); });
        }
    }

    function initTaskDescriptions() {
        document.querySelectorAll('[data-desc-toggle]').forEach(function (button) {
            var bodyId = button.dataset.descToggle;
            var body = document.getElementById(bodyId);
            if (!body) {
                return;
            }
            var key = 'task-desc-' + bodyId;
            var apply = function (visible) {
                body.style.display = visible ? '' : 'none';
                button.textContent = visible ? 'Beschreibung verbergen' : 'Beschreibung zeigen';
            };
            apply(window.localStorage.getItem(key) !== 'hidden');
            button.addEventListener('click', function () {
                var visible = body.style.display === 'none';
                window.localStorage.setItem(key, visible ? 'shown' : 'hidden');
                apply(visible);
            });
        });
    }

    function initTopicFilterByLevel() {
        var levelSelect = document.querySelector('[data-topic-level-source]');
        var topicSelect = document.querySelector('[data-topic-level-filter]');
        if (!levelSelect || !topicSelect) {
            return;
        }
        var run = function () {
            var level = levelSelect.value;
            var hasVisible = false;
            topicSelect.querySelectorAll('option[data-topic-level]').forEach(function (option) {
                var match = level === '' || option.dataset.topicLevel === level;
                option.hidden = !match;
                if (match) {
                    hasVisible = true;
                }
            });
            var fallback = topicSelect.querySelector('option[data-topic-any]');
            if (fallback) {
                fallback.hidden = false;
            }
            var current = topicSelect.selectedOptions[0];
            if (current && current.hidden) {
                topicSelect.selectedIndex = fallback ? fallback.index : 0;
            }
        };
        levelSelect.addEventListener('change', run);
        run();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-wysiwyg]').forEach(initWysiwyg);
        initTaskDescriptions();
        initTopicFilterByLevel();
    });
})();
