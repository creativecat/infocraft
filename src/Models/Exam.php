<?php
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Pruefung oder Schularbeit mit vier Phasen:
 * vorbereitung -> durchfuehrung -> korrektur -> herausgegeben.
 * Gruppen erhalten beim Start jeweils ihre eigene Vorlage als Kopie.
 */
final class Exam
{
    public function __construct(
        public readonly int $id,
        public readonly int $teacherId,
        public readonly string $kind,
        public readonly string $title,
        public readonly string $description,
        public readonly int $level,
        public readonly string $phase,
        public readonly ?string $startedAt,
        public readonly ?string $releasedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['teacher_id'],
            (string) $row['kind'],
            (string) $row['title'],
            (string) $row['description'],
            (int) $row['level'],
            (string) $row['phase'],
            ($row['started_at'] ?? null) === null ? null : (string) $row['started_at'],
            ($row['released_at'] ?? null) === null ? null : (string) $row['released_at'],
        );
    }

    /** @return list<self> */
    public static function forTeacher(int $teacherId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM exams WHERE teacher_id = ? ORDER BY id DESC');
        $stmt->execute([$teacherId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM exams WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function create(int $teacherId, string $kind, string $title, string $description, int $level): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO exams (teacher_id, kind, title, description, level, phase) VALUES (?, ?, ?, ?, ?, \'vorbereitung\')');
        $stmt->execute([$teacherId, $kind, substr(trim($title), 0, 200), $description, $level]);
        $exam = self::find((int) $pdo->lastInsertId());
        return $exam ?? throw new \RuntimeException('Prüfung konnte nicht angelegt werden.');
    }

    public static function setPhase(int $examId, string $phase): void
    {
        $stmt = Database::pdo()->prepare('UPDATE exams SET phase = ? WHERE id = ?');
        $stmt->execute([$phase, $examId]);
    }

    /** started_at oder released_at auf jetzt setzen. */
    public static function markTimestamp(int $examId, string $column): void
    {
        if (!in_array($column, ['started_at', 'released_at'], true)) {
            return;
        }
        Database::pdo()->prepare('UPDATE exams SET ' . $column . ' = CURRENT_TIMESTAMP WHERE id = ?')->execute([$examId]);
    }

    public static function isMember(int $examId, int $userId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM exam_members m JOIN exam_groups g ON g.id = m.group_id WHERE g.exam_id = ? AND m.user_id = ?');
        $stmt->execute([$examId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Aktive Pruefungssperre fuer eine Schuelerin/einen Schueler (sonst null).
     * @return array{id: int, title: string, projectId: int|null}|null
     */
    public static function lockForUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.id, e.title, w.project_id FROM exams e JOIN exam_groups g ON g.exam_id = e.id JOIN exam_members m ON m.group_id = g.id LEFT JOIN exam_works w ON w.exam_id = e.id AND w.user_id = ? WHERE m.user_id = ? AND e.phase = \'durchfuehrung\' AND e.kind = \'pruefung\' ORDER BY e.id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'projectId' => $row['project_id'] === null ? null : (int) $row['project_id'],
        ];
    }

    /** @return list<self> */
    public static function forStudent(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT e.* FROM exams e JOIN exam_groups g ON g.exam_id = e.id JOIN exam_members m ON m.group_id = g.id WHERE m.user_id = ? ORDER BY e.id DESC'
        );
        $stmt->execute([$userId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = self::fromRow($row);
        }
        return $list;
    }

    /** @return list<array{id: int, position: int, title: string, description: string, be: int, sample_solution: string}> */
    public static function tasks(int $examId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM exam_tasks WHERE exam_id = ? ORDER BY position, id');
        $stmt->execute([$examId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'position' => (int) $row['position'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'be' => (int) $row['be'],
                'sample_solution' => (string) $row['sample_solution'],
            ];
        }
        return $list;
    }

    public static function addTask(int $examId, string $title, string $description, int $be, string $sampleSolution): void
    {
        $position = count(self::tasks($examId)) + 1;
        $stmt = Database::pdo()->prepare('INSERT INTO exam_tasks (exam_id, position, title, description, be, sample_solution) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$examId, $position, substr(trim($title), 0, 200), $description, $be, $sampleSolution]);
    }

    public static function totalBe(int $examId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(be), 0) FROM exam_tasks WHERE exam_id = ?');
        $stmt->execute([$examId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array{id: int, name: string, templateProjectId: int|null, templateTitle: string|null, members: list<User>}> */
    public static function groups(int $examId): array
    {
        $stmt = Database::pdo()->prepare('SELECT g.id, g.name, g.template_project_id, p.title AS template_title FROM exam_groups g LEFT JOIN projects p ON p.id = g.template_project_id WHERE g.exam_id = ? ORDER BY g.id');
        $stmt->execute([$examId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'templateProjectId' => $row['template_project_id'] === null ? null : (int) $row['template_project_id'],
                'templateTitle' => $row['template_title'] === null ? null : (string) $row['template_title'],
                'members' => self::membersOf((int) $row['id']),
            ];
        }
        return $list;
    }

    public static function addGroup(int $examId, string $name): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO exam_groups (exam_id, name) VALUES (?, ?)');
        $stmt->execute([$examId, substr(trim($name), 0, 100)]);
        return (int) $pdo->lastInsertId();
    }

    public static function groupBelongsTo(int $groupId, int $examId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM exam_groups WHERE id = ? AND exam_id = ?');
        $stmt->execute([$groupId, $examId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return list<User> */
    public static function membersOf(int $groupId): array
    {
        $stmt = Database::pdo()->prepare('SELECT u.* FROM exam_members m JOIN users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY u.last_name, u.first_name');
        $stmt->execute([$groupId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = User::fromRow($row);
        }
        return $list;
    }

    public static function addMember(int $groupId, int $userId): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'INSERT IGNORE INTO exam_members (group_id, user_id) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO exam_members (group_id, user_id) VALUES (?, ?)';
        Database::pdo()->prepare($sql)->execute([$groupId, $userId]);
    }

    public static function setTemplate(int $groupId, ?int $projectId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE exam_groups SET template_project_id = ? WHERE id = ?');
        $stmt->execute([$projectId, $groupId]);
    }

    /**
     * Gruppe, in der diese Person in dieser Pruefung steckt.
     * @return array{id: int, templateProjectId: int|null}|null
     */
    public static function memberGroup(int $examId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT g.id, g.template_project_id FROM exam_members m JOIN exam_groups g ON g.id = m.group_id WHERE g.exam_id = ? AND m.user_id = ? ORDER BY g.id LIMIT 1');
        $stmt->execute([$examId, $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'templateProjectId' => $row['template_project_id'] === null ? null : (int) $row['template_project_id'],
        ];
    }

    /** @return array{id: int, user_id: int, group_id: int|null, project_id: int|null, started_at: string|null, submitted_at: string|null}|null */
    public static function workFor(int $examId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM exam_works WHERE exam_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$examId, $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'group_id' => $row['group_id'] === null ? null : (int) $row['group_id'],
            'project_id' => $row['project_id'] === null ? null : (int) $row['project_id'],
            'started_at' => $row['started_at'] === null ? null : (string) $row['started_at'],
            'submitted_at' => $row['submitted_at'] === null ? null : (string) $row['submitted_at'],
        ];
    }

    public static function createWork(int $examId, int $userId, ?int $groupId, ?int $projectId): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'INSERT IGNORE INTO exam_works (exam_id, user_id, group_id, project_id, started_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
            : 'INSERT OR IGNORE INTO exam_works (exam_id, user_id, group_id, project_id, started_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)';
        Database::pdo()->prepare($sql)->execute([$examId, $userId, $groupId, $projectId]);
    }

    /** @return list<array{userId: int, name: string, className: string|null, projectId: int|null, projectTitle: string|null, projectType: string|null, startedAt: string|null, submittedAt: string|null}> */
    public static function works(int $examId): array
    {
        $stmt = Database::pdo()->prepare('SELECT w.*, u.first_name, u.last_name, c.name AS class_name, p.title AS project_title, p.type AS project_type FROM exam_works w JOIN users u ON u.id = w.user_id LEFT JOIN classes c ON c.id = u.class_id LEFT JOIN projects p ON p.id = w.project_id WHERE w.exam_id = ? ORDER BY u.last_name, u.first_name');
        $stmt->execute([$examId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'userId' => (int) $row['user_id'],
                'name' => ((string) $row['first_name']) . ' ' . ((string) $row['last_name']),
                'className' => $row['class_name'] === null ? null : (string) $row['class_name'],
                'projectId' => $row['project_id'] === null ? null : (int) $row['project_id'],
                'projectTitle' => $row['project_title'] === null ? null : (string) $row['project_title'],
                'projectType' => $row['project_type'] === null ? null : (string) $row['project_type'],
                'startedAt' => $row['started_at'] === null ? null : (string) $row['started_at'],
                'submittedAt' => $row['submitted_at'] === null ? null : (string) $row['submitted_at'],
            ];
        }
        return $list;
    }

    public static function submit(int $examId, int $userId): void
    {
        Database::pdo()->prepare('UPDATE exam_works SET submitted_at = CURRENT_TIMESTAMP WHERE exam_id = ? AND user_id = ? AND submitted_at IS NULL')->execute([$examId, $userId]);
    }

    public static function saveAnswer(int $taskId, int $userId, string $answer): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'INSERT INTO exam_answers (task_id, user_id, answer) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE answer = VALUES(answer), updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO exam_answers (task_id, user_id, answer) VALUES (?, ?, ?) ON CONFLICT(task_id, user_id) DO UPDATE SET answer = excluded.answer, updated_at = CURRENT_TIMESTAMP';
        Database::pdo()->prepare($sql)->execute([$taskId, $userId, $answer]);
    }

    /** @return array<int, string> taskId => Antwort */
    public static function answersFor(int $examId, int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT a.task_id, a.answer FROM exam_answers a JOIN exam_tasks t ON t.id = a.task_id WHERE t.exam_id = ? AND a.user_id = ?');
        $stmt->execute([$examId, $userId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['task_id']] = (string) $row['answer'];
        }
        return $map;
    }

    public static function saveGrading(int $taskId, int $userId, float $be, string $comment, string $source, bool $checked): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'INSERT INTO exam_gradings (task_id, user_id, be_erreicht, comment, source, checked) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE be_erreicht = VALUES(be_erreicht), comment = VALUES(comment), source = VALUES(source), checked = VALUES(checked), updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO exam_gradings (task_id, user_id, be_erreicht, comment, source, checked) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(task_id, user_id) DO UPDATE SET be_erreicht = excluded.be_erreicht, comment = excluded.comment, source = excluded.source, checked = excluded.checked, updated_at = CURRENT_TIMESTAMP';
        Database::pdo()->prepare($sql)->execute([$taskId, $userId, $be, $comment, $source, $checked ? 1 : 0]);
    }

    /** @return array<int, array{be: float, comment: string, source: string, checked: bool}> taskId => Bewertung */
    public static function gradingsFor(int $examId, int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT g.* FROM exam_gradings g JOIN exam_tasks t ON t.id = g.task_id WHERE t.exam_id = ? AND g.user_id = ?');
        $stmt->execute([$examId, $userId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['task_id']] = [
                'be' => (float) $row['be_erreicht'],
                'comment' => (string) $row['comment'],
                'source' => (string) $row['source'],
                'checked' => (int) $row['checked'] === 1,
            ];
        }
        return $map;
    }

    /** @return array{reached: float, total: int, graded: int} */
    public static function totalsFor(int $examId, int $userId): array
    {
        $reached = 0.0;
        $gradings = self::gradingsFor($examId, $userId);
        foreach ($gradings as $grading) {
            $reached += $grading['be'];
        }
        return ['reached' => $reached, 'total' => self::totalBe($examId), 'graded' => count($gradings)];
    }

    /** Note nach klassischem Schluessel (Prozent der erreichten BE). */
    public static function gradeFor(float $percent): int
    {
        if ($percent >= 92.0) {
            return 1;
        }
        if ($percent >= 81.0) {
            return 2;
        }
        if ($percent >= 67.0) {
            return 3;
        }
        if ($percent >= 50.0) {
            return 4;
        }
        if ($percent >= 30.0) {
            return 5;
        }
        return 6;
    }

    public static function gradeLabel(int $note): string
    {
        return match ($note) {
            1 => 'sehr gut',
            2 => 'gut',
            3 => 'befriedigend',
            4 => 'ausreichend',
            5 => 'mangelhaft',
            default => 'ungenügend',
        };
    }
}
