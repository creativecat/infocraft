<?php
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Presentation;
use Infocraft\Models\User;

/**
 * Präsentationen: Folien für den Unterricht, mit Vollbild-Modus und
 * editierbarem Code direkt in der Präsentation (Syntax-Highlighting).
 * Lehrkräfte verwalten ihre Präsentationen, Schülerinnen und Schüler
 * sehen die Folien ab der angegebenen Jahrgangsstufe.
 */
final class PresentationController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $isTeacher = Auth::isTeacher();
        $items = $isTeacher ? Presentation::all() : Presentation::visibleFor(Auth::levelOf($user) ?? 0);
        render('praesentation_overview', [
            'title' => 'Präsentationen',
            'active' => 'module-praesentationen',
            'items' => $items,
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
            flash('error', 'Nur Lehrkräfte können Präsentationen anlegen.');
            redirect('/praesentationen');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        $level = (int) ($_POST['level'] ?? 6);
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/praesentationen');
        }
        $presentation = Presentation::create($user->id, $title, $level);
        flash('success', 'Präsentation angelegt. Füge jetzt die erste Folie hinzu.');
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function edit(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $presentation = Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Präsentation gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher()) {
            if ($presentation->level > (Auth::levelOf($user) ?? 0)) {
                http_response_code(404);
                render('error', ['title' => 'Noch nicht dran', 'message' => 'Diese Präsentation gehört zu einer höheren Jahrgangsstufe.']);
                return;
            }
            redirect('/praesentationen/' . $presentation->id . '/zeigen');
        }
        if ($user->role !== 'admin' && $presentation->teacherId !== $user->id) {
            flash('error', 'Diese Präsentation kannst du nicht bearbeiten.');
            redirect('/praesentationen');
        }
        render('praesentation_edit', [
            'title' => $presentation->title . ' bearbeiten',
            'active' => 'module-praesentationen',
            'presentation' => $presentation,
            'slides' => $presentation->slides(),
            'user' => $user,
        ]);
    }

    /** Titel und Jahrgangsstufe speichern. */
    public static function saveMeta(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/praesentationen/' . $presentation->id);
        }
        $presentation->save($title, (int) ($_POST['level'] ?? $presentation->level));
        flash('success', 'Gespeichert.');
        redirect('/praesentationen/' . $presentation->id);
    }

    /** Präsentationsmodus: eine Folie nach der anderen, Vollbild per Knopfdruck. */
    public static function show(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        // Permanenter Themen-Link (/praesentationen/thema/{slug}) oder klassisch per id.
        $presentation = isset($params['slug'])
            ? Presentation::findBySlug((string) $params['slug'])
            : Presentation::find((int) ($params['id'] ?? 0));
        if ($presentation === null) {
            http_response_code(404);
            render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Präsentation gibt es nicht.']);
            return;
        }
        if (!Auth::isTeacher() && $presentation->level > (Auth::levelOf($user) ?? 0)) {
            http_response_code(404);
            render('error', ['title' => 'Noch nicht dran', 'message' => 'Diese Präsentation gehört zu einer höheren Jahrgangsstufe.']);
            return;
        }
        $slides = $presentation->slides();
        $rendered = [];
        foreach ($slides as $slide) {
            $slide['html'] = ($slide['type'] ?? 'text') === 'text'
                ? WikiController::renderMarkdown((string) ($slide['body'] ?? ''))
                : '';
            $rendered[] = $slide;
        }
        render('praesentation_view', [
            'title' => $presentation->title,
            'active' => 'module-praesentationen',
            'presentation' => $presentation,
            'slides' => $rendered,
            'mayEdit' => Auth::isTeacher() && ($user->role === 'admin' || $presentation->teacherId === $user->id),
            'user' => $user,
        ]);
    }

    /** Folie anfügen (Text oder Code), ohne JavaScript. */
    public static function addSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $slides = $presentation->slides();
        $type = ($_POST['type'] ?? '') === 'code' ? 'code' : 'text';
        if ($type === 'code') {
            $slides[] = ['type' => 'code', 'title' => 'Code zeigen', 'lang' => 'java', 'code' => ''];
        } else {
            $slides[] = ['type' => 'text', 'title' => '', 'body' => ''];
        }
        $presentation->setSlides($slides);
        flash('success', 'Folie angefügt.');
        redirect('/praesentationen/' . $presentation->id);
    }

    /** Folie speichern (aus dem Editor oder direkt aus dem Präsentationsmodus). */
    public static function saveSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $slides = $presentation->slides();
        if (!isset($slides[$pos])) {
            flash('error', 'Diese Folie gibt es nicht.');
            redirect('/praesentationen/' . $presentation->id);
        }
        $type = $slides[$pos]['type'] === 'code' ? 'code' : 'text';
        if ($type === 'code') {
            $slides[$pos]['title'] = (string) ($_POST['title'] ?? $slides[$pos]['title']);
            $slides[$pos]['lang'] = Presentation::cleanLang((string) ($_POST['lang'] ?? $slides[$pos]['lang']));
            $slides[$pos]['code'] = (string) ($_POST['code'] ?? $slides[$pos]['code']);
        } else {
            $slides[$pos]['title'] = (string) ($_POST['title'] ?? $slides[$pos]['title']);
            $slides[$pos]['body'] = (string) ($_POST['body'] ?? $slides[$pos]['body']);
        }
        $presentation->setSlides($slides);
        flash('success', 'Folie gespeichert.');
        if (($_POST['ziel'] ?? '') === 'zeigen') {
            redirect('/praesentationen/' . $presentation->id . '/zeigen#f' . $pos);
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function deleteSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $slides = $presentation->slides();
        if (isset($slides[$pos])) {
            array_splice($slides, $pos, 1);
            $presentation->setSlides($slides);
            flash('success', 'Folie gelöscht.');
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function moveSlide(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        $pos = (int) ($params['pos'] ?? -1);
        $dir = ($params['dir'] ?? '') === 'hoch' ? 'hoch' : 'runter';
        $slides = $presentation->slides();
        $target = $dir === 'hoch' ? $pos - 1 : $pos + 1;
        if (isset($slides[$pos], $slides[$target])) {
            $tmp = $slides[$pos];
            $slides[$pos] = $slides[$target];
            $slides[$target] = $tmp;
            $presentation->setSlides($slides);
            flash('success', 'Reihenfolge geändert.');
        }
        redirect('/praesentationen/' . $presentation->id);
    }

    public static function delete(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        csrf_validate();
        $presentation = self::editable($user, (int) ($params['id'] ?? 0));
        Presentation::delete($presentation->id);
        flash('success', 'Präsentation gelöscht.');
        redirect('/praesentationen');
    }

    /** Nur Lehrkraft (Eigentümerin/Eigentümer oder Admin) darf bearbeiten. */
    private static function editable(User $user, int $id): Presentation
    {
        $presentation = Presentation::find($id);
        if ($presentation === null || !Auth::isTeacher() || ($user->role !== 'admin' && $presentation->teacherId !== $user->id)) {
            flash('error', 'Diese Präsentation kannst du nicht bearbeiten.');
            redirect('/praesentationen');
        }
        return $presentation;
    }
}
