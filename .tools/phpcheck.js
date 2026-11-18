#!/usr/bin/env node
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: PHP-Syntaxprüfung für CI und lokale Arbeit
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
/**
 * Grober PHP-Syntax-Vorcheck ohne PHP-Interpreter:
 * prüft die Balance von (), {}, [] ausserhalb von Strings, Kommentaren
 * und Heredocs/Nowdocs. Kein Ersatz für php -l, aber faengt die
 * haeufigsten Syntaxfehler (vergessene Klammer, untermionierte
 * Zeichenkette) in der CI und lokal ab.
 *
 * Nutzung: node .tools/phpcheck.js <datei.php ...>
 */
'use strict';

const fs = require('fs');

function checkFile(path) {
    const src = fs.readFileSync(path, 'utf8');
    const stack = [];
    const pairs = { ')': '(', '}': '{', ']': '[' };
    let line = 1;
    let i = 0;
    let inPhp = false;
    const n = src.length;
    while (i < n) {
        const c = src[i];
        if (c === '\n') {
            line++;
            i++;
            continue;
        }
        if (!inPhp) {
            // HTML-Bereich: nur auf PHP-Start-Tags achten, alles andere
            // ist Markup (dortige #, //, Klammern sind kein PHP-Code).
            if (c === '<' && src.slice(i, i + 5) === '<?php') {
                inPhp = true;
                i += 5;
                continue;
            }
            if (c === '<' && src[i + 1] === '=' && src[i + 2] === '?') {
                inPhp = true;
                i += 3;
                continue;
            }
            i++;
            continue;
        }
        // PHP-Ende-Tag (nicht in Strings/Kommentaren, das regeln wir dort)
        if (c === '?' && src[i + 1] === '>') {
            inPhp = false;
            i += 2;
            continue;
        }
        // Zeilenkommentar // oder # (endet auch am PHP-Ende-Tag)
        if (c === '#' || (c === '/' && src[i + 1] === '/')) {
            while (i < n && src[i] !== '\n' && !(src[i] === '?' && src[i + 1] === '>')) i++;
            continue;
        }
        // Blockkommentar
        if (c === '/' && src[i + 1] === '*') {
            i += 2;
            while (i < n && !(src[i] === '*' && src[i + 1] === '/')) {
                if (src[i] === '\n') line++;
                i++;
            }
            i += 2;
            continue;
        }
        // Heredoc/Nowdoc <<<ID ... ID;
        if (c === '<' && src.slice(i, i + 3) === '<<<') {
            const m = /^<<<[ \t]*(?:')?([A-Za-z_][A-Za-z0-9_]*)(?:')?/i.exec(src.slice(i, i + 60));
            if (m) {
                const id = m[1];
                i = src.indexOf('\n', i) + 1;
                line++;
                const re = new RegExp('^[ \\t]*' + id + '\\b');
                while (i < n) {
                    const nl = src.indexOf('\n', i);
                    const seg = nl === -1 ? src.slice(i) : src.slice(i, nl);
                    if (re.test(seg)) {
                        i = nl + 1;
                        line++;
                        break;
                    }
                    i = nl === -1 ? n : nl + 1;
                    line++;
                }
                continue;
            }
        }
        // Strings
        if (c === '"' || c === "'") {
            const quote = c;
            i++;
            while (i < n && src[i] !== quote) {
                // Backslash-Paare (\\ und \') ueberspringen, sonst wuerde
                // z. B. 'Infocraft\\' das schliessende Quote verschlucken.
                if (src[i] === '\\') { i += 2; continue; }
                if (src[i] === '\n') line++;
                i++;
            }
            i++; // schliessendes (oder Dateiende-) Quote
            continue;
        }
        if (c === '(' || c === '{' || c === '[') {
            stack.push({ c, line });
            i++;
            continue;
        }
        if (c === ')' || c === '}' || c === ']') {
            const open = stack.pop();
            if (open === undefined || open.c !== pairs[c]) {
                return 'FAIL ' + path + ':' + line + ' unerwartete schliessende Klammer "' + c + '"';
            }
            i++;
            continue;
        }
        i++;
    }
    if (stack.length > 0) {
        const open = stack[stack.length - 1];
        return 'FAIL ' + path + ':' + open.line + ' "' + open.c + '" wurde nie geschlossen';
    }
    return 'ok ' + path;
}

const files = process.argv.slice(2);
if (files.length === 0) {
    console.error('Nutzung: node .tools/phpcheck.js <datei.php ...>');
    process.exitCode = 2;
}
let failed = false;
for (const file of files) {
    let result;
    try {
        result = checkFile(file);
    } catch (err) {
        result = 'FAIL ' + file + ': ' + err.message;
    }
    console.log(result);
    if (result.startsWith('FAIL')) {
        failed = true;
    }
}
if (failed) {
    process.exitCode = 1;
}
