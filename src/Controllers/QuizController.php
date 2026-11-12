<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Quiz-Arena
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Controllers;

use Infocraft\Auth;
use Infocraft\Models\Quiz;
use Infocraft\Models\SchoolClass;
use Infocraft\Models\User;

/**
 * Quiz-Arena: Lehrkraefte legen Quiz an und teilen sie aus,
 * Schuelerinnen und Schueler spielen mit sofortigem Feedback.
 */
final class QuizController
{
    public static function index(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            $quizzes = [];
            foreach (Quiz::forTeacher($user->id) as $quiz) {
                $quizzes[] = [
                    'quiz' => $quiz,
                    'questionCount' => Quiz::questionCount($quiz->id),
                ];
            }
            render('quiz_overview', [
                'title' => 'Quiz-Arena',
                'active' => 'module-quiz',
                'isTeacher' => true,
                'quizzes' => $quizzes,
                'user' => $user,
            ]);
            return;
        }
        $level = Auth::levelOf($user) ?? 0;
        render('quiz_overview', [
            'title' => 'Quiz-Arena',
            'active' => 'module-quiz',
            'isTeacher' => false,
            'quizzes' => Quiz::forStudent($user->id, $user->classId, $level),
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
            flash('error', 'Nur Lehrkräfte können Quiz anlegen.');
            redirect('/');
        }
        csrf_validate();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', 'Bitte einen Titel angeben.');
            redirect('/quiz');
        }
        $level = (int) ($_POST['level'] ?? 6);
        $quiz = Quiz::create($user->id, $title, (string) ($_POST['description'] ?? ''), $level);
        flash('success', 'Quiz angelegt. Jetzt Fragen hinzufügen.');
        redirect('/quiz/' . $quiz->id);
    }

    public static function manage(array $params): void
    {
        $quiz = self::requireOwner((int) ($params['id'] ?? 0));
        $user = Auth::user();
        render('quiz_manage', [
            'title' => $quiz->title,
            'active' => 'module-quiz',
            'quiz' => $quiz,
            'questions' => Quiz::questions($quiz->id),
            'targets' => Quiz::targets($quiz->id),
            'classes' => SchoolClass::forTeacher($user->id, $user->role === 'admin'),
            'studentsByClass' => self::studentsByClass($user),
            'user' => $user,
        ]);
    }

    public static function addQuestion(array $params): void
    {
        $quiz = self::requireOwner((int) ($params['id'] ?? 0));
        csrf_validate();
        $question = trim((string) ($_POST['question'] ?? ''));
        $answers = [
            trim((string) ($_POST['answer_1'] ?? '')),
            trim((string) ($_POST['answer_2'] ?? '')),
            trim((string) ($_POST['answer_3'] ?? '')),
            trim((string) ($_POST['answer_4'] ?? '')),
        ];
        $correct = (int) ($_POST['correct'] ?? 0);
        if ($question === '' || in_array('', $answers, true) || $correct < 1 || $correct > 4) {
            flash('error', 'Bitte Frage, vier Antworten und die richtige Antwort angeben.');
            redirect('/quiz/' . $quiz->id);
        }
        Quiz::addQuestion($quiz->id, $question, $answers, $correct);
        flash('success', 'Frage hinzugefügt.');
        redirect('/quiz/' . $quiz->id);
    }

    public static function distribute(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Quiz austellen.');
            redirect('/');
        }
        csrf_validate();
        $isAdmin = $user->role === 'admin';
        $quiz = Quiz::find((int) ($params['id'] ?? 0));
        if ($quiz === null || ($quiz->teacherId !== $user->id && !$isAdmin)) {
            flash('error', 'Quiz nicht gefunden.');
            redirect('/quiz');
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        $singleId = (int) ($_POST['user_id'] ?? 0);
        if ($classId > 0) {
            if (!$isAdmin && !SchoolClass::hasTeacher($classId, $user->id)) {
                flash('error', 'Du unterrichtest diese Klasse nicht.');
                redirect('/quiz/' . $quiz->id);
            }
            Quiz::assign($quiz->id, $classId, null);
            flash('success', 'Quiz an die Klasse ausgeteilt.');
            redirect('/quiz/' . $quiz->id);
        }
        if ($singleId > 0) {
            $student = User::find($singleId);
            if ($student === null || $student->role !== 'student' || (!$isAdmin && ($student->classId === null || !SchoolClass::hasTeacher($student->classId, $user->id)))) {
                flash('error', 'Diese Person ist nicht in einer deiner Klassen.');
                redirect('/quiz/' . $quiz->id);
            }
            Quiz::assign($quiz->id, null, $student->id);
            flash('success', 'Quiz ausgeteilt.');
            redirect('/quiz/' . $quiz->id);
        }
        flash('error', 'Kein Ziel angegeben.');
        redirect('/quiz/' . $quiz->id);
    }

    public static function results(array $params): void
    {
        $quiz = self::requireOwner((int) ($params['id'] ?? 0));
        render('quiz_results', [
            'title' => 'Ergebnisse: ' . $quiz->title,
            'active' => 'module-quiz',
            'quiz' => $quiz,
            'attempts' => Quiz::attemptsFor($quiz->id),
            'user' => Auth::user(),
        ]);
    }

    public static function play(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (Auth::isTeacher()) {
            flash('error', 'Spielen ist Schülerinnen und Schülern vorbehalten. Hier kannst du deine Quiz bearbeiten.');
            redirect('/quiz');
        }
        $quiz = self::requirePlayable((int) ($params['id'] ?? 0), $user);
        $questions = Quiz::questions($quiz->id);
        if ($questions === []) {
            flash('error', 'Dieses Quiz hat noch keine Fragen.');
            redirect('/quiz');
        }
        $state = $_SESSION['quiz_play'] ?? null;
        if (!is_array($state) || (int) ($state['id'] ?? 0) !== $quiz->id) {
            $state = ['id' => $quiz->id, 'pos' => 0, 'score' => 0, 'answers' => []];
            $_SESSION['quiz_play'] = $state;
        }
        if ((int) $state['pos'] >= count($questions)) {
            redirect('/quiz/' . $quiz->id . '/ergebnis');
        }
        render('quiz_play', [
            'title' => $quiz->title,
            'active' => 'module-quiz',
            'quiz' => $quiz,
            'question' => $questions[(int) $state['pos']],
            'position' => (int) $state['pos'] + 1,
            'total' => count($questions),
            'user' => $user,
        ]);
    }

    public static function answer(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $quiz = self::requirePlayable((int) ($params['id'] ?? 0), $user);
        csrf_validate();
        $state = $_SESSION['quiz_play'] ?? null;
        if (!is_array($state) || (int) ($state['id'] ?? 0) !== $quiz->id) {
            redirect('/quiz/' . $quiz->id . '/spielen');
        }
        $questions = Quiz::questions($quiz->id);
        $pos = (int) ($state['pos'] ?? 0);
        $sentPos = (int) ($_POST['position'] ?? 0) - 1;
        $given = (int) ($_POST['answer'] ?? 0);
        if ($sentPos !== $pos || $pos < 0 || $pos >= count($questions) || $given < 1 || $given > 4) {
            flash('error', 'Ungültige Antwort. Bitte noch einmal.');
            redirect('/quiz/' . $quiz->id . '/spielen');
        }
        $question = $questions[$pos];
        $correct = (int) $question['correct'];
        $state['answers'][$pos + 1] = $given;
        if ($given === $correct) {
            $state['score'] = (int) $state['score'] + 1;
            flash('success', 'Richtig! ' . $question['answer_' . $correct]);
        } else {
            flash('error', 'Leider falsch. Richtig wäre: ' . $question['answer_' . $correct]);
        }
        $state['pos'] = $pos + 1;
        $_SESSION['quiz_play'] = $state;
        if ($state['pos'] >= count($questions)) {
            $score = (int) $state['score'];
            Quiz::saveAttempt($quiz->id, $user->id, $score, count($questions), json_encode($state['answers']) ?: '');
            unset($_SESSION['quiz_play']);
            flash('info', 'Runde beendet: ' . $score . ' von ' . count($questions) . ' richtig.');
            redirect('/quiz/' . $quiz->id . '/ergebnis');
        }
        redirect('/quiz/' . $quiz->id . '/spielen');
    }

    public static function result(array $params): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $quiz = self::requirePlayable((int) ($params['id'] ?? 0), $user);
        $best = Quiz::bestAttempt($quiz->id, $user->id);
        if ($best === null) {
            redirect('/quiz/' . $quiz->id . '/spielen');
        }
        $answers = json_decode($best['answers'], true);
        render('quiz_result_student', [
            'title' => 'Ergebnis: ' . $quiz->title,
            'active' => 'module-quiz',
            'quiz' => $quiz,
            'questions' => Quiz::questions($quiz->id),
            'best' => $best,
            'answers' => is_array($answers) ? $answers : [],
            'user' => $user,
        ]);
    }

    /** Verwaltung nur fuer die Besitzerin oder den Besitzer (Admin darf alles). */
    private static function requireOwner(int $quizId): Quiz
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Auth::isTeacher()) {
            flash('error', 'Nur Lehrkräfte können Quiz verwalten.');
            redirect('/quiz');
        }
        $quiz = Quiz::find($quizId);
        if ($quiz === null || ($quiz->teacherId !== $user->id && $user->role !== 'admin')) {
            http_response_code(404);
            render('error', ['title' => 'Quiz nicht gefunden', 'message' => 'Dieses Quiz gibt es nicht oder gehört dir nicht.']);
            exit;
        }
        return $quiz;
    }

    /** Spielen nur, wenn das Quiz ausgeteilt wurde und die Stufe passt. */
    private static function requirePlayable(int $quizId, User $user): Quiz
    {
        $quiz = Quiz::find($quizId);
        if ($quiz === null || !Quiz::targeted($quizId, $user->id, $user->classId) || (Auth::levelOf($user) ?? 0) < $quiz->level) {
            flash('error', 'Dieses Quiz ist nicht für dich ausgeteilt.');
            redirect('/quiz');
        }
        return $quiz;
    }

    /** @return array<int, list<User>> */
    private static function studentsByClass(User $user): array
    {
        $map = [];
        foreach (SchoolClass::forTeacher($user->id, $user->role === 'admin') as $class) {
            $map[$class->id] = User::byClass($class->id);
        }
        return $map;
    }
}
