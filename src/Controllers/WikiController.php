<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\WikiPage;
use Infocraft\Models\User;

/**
 * Wiki: Nachschlage-Seiten der Lehrkraefte zu Fachbegriffen und Konzepten.
 * Schuelerinnen und Schueler sehen Seiten ab der angegebenen Jahrgangsstufe.
 */
final class WikiController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $isTeacher = Auth::isTeacher();
        $pages = $isTeacher ? WikiPage::all() : WikiPage::visibleFor(Auth::levelOf($user) ?? 0);
        render('wiki_overview', [
            'title' => 'Wiki',
            'active' => 'module-wiki',
            'pages' => $pages,
            'isTeacher' => $isTeacher,
            'user' => $user,
        ]);
    }

    public static function create(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Wiki-Seiten anlegen.');
            redirect('/wiki');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $level = (int) ($_POST['level'] ?? 6);
        if ($title === '' || trim($content) === '') {
            flash('error', 'Bitte Titel und Inhalt angeben.');
            redirect('/wiki');
        }
        WikiPage::create($user->id, $title, $content, $level);
        flash('success', 'Seite angelegt.');
        redirect('/wiki');
    }

    public static function show(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $page = WikiPage::find((int) ($params['id'] ?? 0));
        if ($page === null) {
            http_response_code(404);
            render('error', ['title' => 'Seite nicht gefunden', 'message' => 'Diese Wiki-Seite gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher() && $page->level > (Auth::levelOf($user) ?? 0)) {
            http_response_code(404);
            render('error', ['title' => 'Noch nicht dran', 'message' => 'Diese Wiki-Seite gehört zu einer höheren Jahrgangsstufe.']);
            return;
        }
        render('wiki_page', [
            'title' => $page->title,
            'active' => 'module-wiki',
            'page' => $page,
            'html' => self::renderMarkdown($page->content),
            'mayEdit' => Auth::isTeacher() && ($user->role === 'admin' || $page->teacherId === $user->id),
            'user' => $user,
        ]);
    }

    public static function edit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $page = WikiPage::find((int) ($params['id'] ?? 0));
        if ($page === null || !Auth::isTeacher() || ($user->role !== 'admin' && $page->teacherId !== $user->id)) {
            flash('error', 'Diese Seite kannst du nicht bearbeiten.');
            redirect('/wiki');
        }
        render('wiki_edit', [
            'title' => 'Seite bearbeiten',
            'active' => 'module-wiki',
            'page' => $page,
            'user' => $user,
        ]);
    }

    public static function save(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $page = WikiPage::find((int) ($params['id'] ?? 0));
        if ($page === null || !Auth::isTeacher() || ($user->role !== 'admin' && $page->teacherId !== $user->id)) {
            flash('error', 'Diese Seite kannst du nicht bearbeiten.');
            redirect('/wiki');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        if ($title === '' || trim($content) === '') {
            flash('error', 'Bitte Titel und Inhalt angeben.');
            redirect('/wiki/' . $page->id . '/bearbeiten');
        }
        $page->save($title, $content, (int) ($_POST['level'] ?? $page->level));
        flash('success', 'Gespeichert.');
        redirect('/wiki/' . $page->id);
    }

    public static function delete(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $page = WikiPage::find((int) ($params['id'] ?? 0));
        if ($page === null || !Auth::isTeacher() || ($user->role !== 'admin' && $page->teacherId !== $user->id)) {
            flash('error', 'Diese Seite kannst du nicht löschen.');
            redirect('/wiki');
        }
        WikiPage::delete($page->id);
        flash('success', 'Seite gelöscht.');
        redirect('/wiki');
    }

    /**
     * Markdown-lite: Absaetze, Ueberschriften (# ## ###), Listen (- ),
     * **fett**, *kursiv*, `Code` und ```-Bloecke. Erst escapen, dann ersetzen.
     */
    public static function renderMarkdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $html = '';
        $inList = false;
        $inCode = false;
        $codeBuf = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                if ($inCode) {
                    $html .= '<pre class="code">' . htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES) . '</pre>';
                    $codeBuf = [];
                    $inCode = false;
                } else {
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $codeBuf[] = $line;
                continue;
            }
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                continue;
            }
            if (str_starts_with($trimmed, '### ')) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h4>' . self::mdInline(substr($trimmed, 4)) . '</h4>';
                continue;
            }
            if (str_starts_with($trimmed, '## ')) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h3>' . self::mdInline(substr($trimmed, 3)) . '</h3>';
                continue;
            }
            if (str_starts_with($trimmed, '# ')) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h2>' . self::mdInline(substr($trimmed, 2)) . '</h2>';
                continue;
            }
            if (str_starts_with($trimmed, '- ')) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . self::mdInline(substr($trimmed, 2)) . '</li>';
                continue;
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $html .= '<p>' . self::mdInline($trimmed) . '</p>';
        }
        if ($inList) {
            $html .= '</ul>';
        }
        if ($inCode) {
            $html .= '<pre class="code">' . htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES) . '</pre>';
        }
        return $html;
    }

    private static function mdInline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES);
        // Links: [Text](https://... ) oder [Text](/serverrelativ) - nur http(s) und server-relative Pfade.
        $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/', '<a href="\2">\1</a>', $s) ?? $s;
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>\1</strong>', $s) ?? $s;
        $s = preg_replace('/\*(.+?)\*/s', '<em>\1</em>', $s) ?? $s;
        $s = preg_replace('/`(.+?)`/s', '<code>\1</code>', $s) ?? $s;
        return $s;
    }
}
