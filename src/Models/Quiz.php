<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Quiz der Quiz-Arena: Fragen mit vier Antwortmöglichkeiten,
 * ausgeteilt an Klassen oder Einzelne, mit Punkten pro Durchlauf.
 */
final class Quiz
{
    public function __construct(
        public readonly int $id,
        public readonly int $teacherId,
        public readonly string $title,
        public readonly string $description,
        public readonly int $level,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['title'],
            (string) ($row['description'] ?? ''),
            (int) ($row['level'] ?? 6),
        );
    }

    /** @return list<self> */
    public static function forTeacher(int $teacherId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM quizzes WHERE teacher_id = ? ORDER BY id DESC');
        $stmt->execute([$teacherId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM quizzes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $teacherId, string $title, string $description, int $level): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO quizzes (teacher_id, title, description, level) VALUES (?, ?, ?, ?)');
        $stmt->execute([$teacherId, substr(trim($title), 0, 200), $description, max(1, min(13, $level))]);
        $quiz = self::find((int) $pdo->lastInsertId());
        return $quiz ?? throw new \RuntimeException('Quiz konnte nicht angelegt werden.');
    }

    /** @return list<array{id: int, position: int, question: string, answer_1: string, answer_2: string, answer_3: string, answer_4: string, correct: int}> */
    public static function questions(int $quizId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY position, id');
        $stmt->execute([$quizId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['id'] = (int) $row['id'];
            $row['position'] = (int) $row['position'];
            $row['correct'] = (int) $row['correct'];
            $list[] = $row;
        }
        return $list;
    }

    public static function questionCount(int $quizId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        return (int) $stmt->fetchColumn();
    }

    /** @param list<string> $answers */
    public static function addQuestion(int $quizId, string $question, array $answers, int $correct): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM quiz_questions WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        $position = (int) $stmt->fetchColumn();
        $insert = $pdo->prepare('INSERT INTO quiz_questions (quiz_id, position, question, answer_1, answer_2, answer_3, answer_4, correct) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$quizId, $position, $question, $answers[0], $answers[1], $answers[2], $answers[3], $correct]);
    }

    /** Quiz aus teilen; false, wenn es die Zuweisung schon gab. */
    public static function assign(int $quizId, ?int $classId, ?int $userId): bool
    {
        if ($classId === null && $userId === null) {
            return false;
        }
        $pdo = Database::pdo();
        if ($classId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_targets WHERE quiz_id = ? AND class_id = ?');
            $stmt->execute([$quizId, $classId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_targets WHERE quiz_id = ? AND user_id = ?');
            $stmt->execute([$quizId, $userId]);
        }
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $insert = $pdo->prepare('INSERT INTO quiz_targets (quiz_id, class_id, user_id) VALUES (?, ?, ?)');
        $insert->execute([$quizId, $classId, $userId]);
        return true;
    }

    /** @return list<array{kind: string, name: string}> */
    public static function targets(int $quizId): array
    {
        $stmt = Database::pdo()->prepare('SELECT qt.class_id, qt.user_id, c.name AS class_name, u.first_name, u.last_name FROM quiz_targets qt LEFT JOIN classes c ON c.id = qt.class_id LEFT JOIN users u ON u.id = qt.user_id WHERE qt.quiz_id = ? ORDER BY qt.id');
        $stmt->execute([$quizId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['class_name'] !== null) {
                $list[] = ['kind' => 'Klasse', 'name' => (string) $row['class_name']];
            } else {
                $list[] = ['kind' => 'Person', 'name' => (string) $row['first_name'] . ' ' . (string) $row['last_name']];
            }
        }
        return $list;
    }

    public static function targeted(int $quizId, int $userId, ?int $classId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM quiz_targets WHERE quiz_id = ? AND (user_id = ? OR (class_id IS NOT NULL AND class_id = ?))');
        $stmt->execute([$quizId, $userId, $classId ?? 0]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Quizze, die eine Schülerin oder ein Spieler sehen darf: ausgeteilt und
     * bis zur eigenen Jahrgangsstufe, mit bester Punktzahl.
     * @return list<array{quiz: self, questionCount: int, best: ?array{score: int, total: int}}>
     */
    public static function forStudent(int $userId, ?int $classId, int $level): array
    {
        $sql = 'SELECT q.*, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count
                FROM quizzes q
                JOIN quiz_targets tt ON tt.quiz_id = q.id
                WHERE q.level <= ?
                  AND (tt.user_id = ? OR (tt.class_id IS NOT NULL AND tt.class_id = ?))
                GROUP BY q.id
                ORDER BY q.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$level, $userId, $classId ?? 0]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $quiz = self::fromRow($row);
            $list[] = [
                'quiz' => $quiz,
                'questionCount' => (int) $row['question_count'],
                'best' => self::bestAttempt($quiz->id, $userId),
            ];
        }
        return $list;
    }

    public static function saveAttempt(int $quizId, int $userId, int $score, int $total, string $answersJson): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO quiz_attempts (quiz_id, user_id, score, total, answers) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$quizId, $userId, $score, $total, $answersJson]);
    }

    /** @return array{score: int, total: int, answers: string}|null */
    public static function bestAttempt(int $quizId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT score, total, answers FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY score DESC, id DESC LIMIT 1');
        $stmt->execute([$quizId, $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : [
            'score' => (int) $row['score'],
            'total' => (int) $row['total'],
            'answers' => (string) ($row['answers'] ?? ''),
        ];
    }

    /** Ergebnisse für die Lehrkraft. @return list<array{userName: string, className: ?string, score: int, total: int, finishedAt: string}> */
    public static function attemptsFor(int $quizId): array
    {
        $sql = 'SELECT a.score, a.total, a.finished_at, u.first_name, u.last_name, c.name AS class_name
                FROM quiz_attempts a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN classes c ON c.id = u.class_id
                WHERE a.quiz_id = ?
                ORDER BY a.score DESC, u.last_name, u.first_name';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$quizId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'userName' => (string) $row['first_name'] . ' ' . (string) $row['last_name'],
                'className' => $row['class_name'] !== null ? (string) $row['class_name'] : null,
                'score' => (int) $row['score'],
                'total' => (int) $row['total'],
                'finishedAt' => (string) $row['finished_at'],
            ];
        }
        return $list;
    }
}
