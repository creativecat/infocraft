<?php
declare(strict_types=1);

namespace Infocraft\Models;

/**
 * Whitelist-Sanitizer für formatierte Aufgabenbeschreibungen (WYSIWYG).
 * Erlaubt nur harmlose Format-Tags, entfernt Skripte, Styles, Kommentare
 * und alle Attribute außer geprüften Links. Unbekannte Tags werden als
 * Text angezeigt, statt ungesehen durchgereicht zu werden.
 */
final class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => true, 'br' => true, 'div' => true, 'b' => true, 'strong' => true,
        'i' => true, 'em' => true, 'u' => true, 's' => true,
        'ul' => true, 'ol' => true, 'li' => true,
        'h2' => true, 'h3' => true, 'h4' => true,
        'code' => true, 'pre' => true, 'blockquote' => true, 'span' => true,
        'a' => true,
    ];

    public static function clean(string $html): string
    {
        // Skripte, Styles und Kommentare komplett entfernen.
        $html = preg_replace('/<\s*script\b[^>]*>.*?<\s*\/\s*script\s*>/is', '', $html) ?? '';
        $html = preg_replace('/<\s*style\b[^>]*>.*?<\s*\/\s*style\s*>/is', '', $html) ?? '';
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? '';

        // Alle Tags durchgehen: erlaubte behalten (bereinigt), alle anderen escapen.
        $html = preg_replace_callback('/<\s*(\/?)\s*([a-zA-Z0-9]+)([^>]*)>/s', static function (array $m): string {
            $closing = $m[1] === '/';
            $tag = strtolower($m[2]);
            if (!isset(self::ALLOWED[$tag])) {
                return htmlspecialchars('<' . $m[1] . $m[2] . $m[3] . '>', ENT_QUOTES, 'UTF-8');
            }
            if ($tag === 'br') {
                return '<br>';
            }
            if ($closing) {
                return '</' . $tag . '>';
            }
            if ($tag === 'a') {
                $href = '';
                if (preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', (string) $m[3], $hm)) {
                    $href = $hm[2] ?? $hm[3] ?? $hm[4] ?? '';
                }
                if (preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener" target="_blank">';
                }
                return '<a>';
            }
            return '<' . $tag . '>';
        }, $html) ?? '';

        return $html;
    }
}
