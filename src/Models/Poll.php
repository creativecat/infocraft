<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Umfragen
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft\Models;

use Infocraft\Database;

/**
 * Live-Abstimmung während einer Präsentation (Issue #3, Kahoot-Stil):
 * Die Lehrkraft startet eine Abstimmung im Moderatorenbildschirm, die
 * Schülerinnen und Schüler stimmen mit Antwortknöpfen ab, die Lehrkraft
 * sieht live Ergebnisbalken. Eine Abgabe je Person, überschreibbar bis
 * die Abstimmung geschlossen wird.
 */
final class Poll
{
    public function __construct(
        public readonly int $id,
        public readonly int $presentationId,
        public readonly int $teacherId,
        public readonly string $question,
        public readonly array $answers,
        public readonly string $state = 'open',
    ) {
    }

    public static function fromRow(array $row): self
    {
        $answers = json_decode((string) $row['answers'], true);
        return new self(
            (int) $row['id'],
            (int) $row['presentation_id'],
            (int) $row['teacher_id'],
            (string) $row['question'],
            is_array($answers) ? array_values($answers) : [],
            (string) ($row['state'] ?? 'open'),
        );
    }

    /** Aktuell offene Abstimmung einer Präsentation (oder null). */
    public static function openFor(int $presentationId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM polls WHERE presentation_id = ? AND state = \'open\' ORDER BY id DESC LIMIT 1');
        $stmt->execute([$presentationId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    /** Zuletzt beendete Abstimmung (für den Ergebnisblick nach dem Schließen). */
    public static function lastClosedFor(int $presentationId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM polls WHERE presentation_id = ? AND state = \'closed\' ORDER BY id DESC LIMIT 1');
        $stmt->execute([$presentationId]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM polls WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($row);
    }

    public static function start(int $presentationId, int $teacherId, string $question, array $answers): self
    {
        // Höchstens eine offene Abstimmung je Präsentation.
        $existing = self::openFor($presentationId);
        if ($existing !== null) {
            return $existing;
        }
        $stmt = Database::pdo()->prepare('INSERT INTO polls (presentation_id, teacher_id, question, answers) VALUES (?, ?, ?, ?)');
        $stmt->execute([$presentationId, $teacherId, mb_substr(trim($question), 0, 300), json_encode(array_values($answers))]);
        $poll = self::find((int) Database::pdo()->lastInsertId());
        return $poll ?? throw new \RuntimeException('Abstimmung konnte nicht angelegt werden.');
    }

    public function close(): void
    {
        $stmt = Database::pdo()->prepare('UPDATE polls SET state = \'closed\', closed_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$this->id]);
    }

    public function delete(): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM polls WHERE id = ?');
        $stmt->execute([$this->id]);
    }

    /** Stimme zählen (überschreibt eine frühere Abgabe derselben Person). */
    public function vote(int $userId, int $choice): bool
    {
        if ($this->state !== 'open') {
            return false;
        }
        if ($choice < 0 || $choice >= count($this->answers)) {
            return false;
        }
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?');
        $exists->execute([$this->id, $userId]);
        if ((int) $exists->fetchColumn() > 0) {
            $update = $pdo->prepare('UPDATE poll_votes SET choice = ?, voted_at = CURRENT_TIMESTAMP WHERE poll_id = ? AND user_id = ?');
            $update->execute([$choice, $this->id, $userId]);
            return true;
        }
        $insert = $pdo->prepare('INSERT INTO poll_votes (poll_id, user_id, choice) VALUES (?, ?, ?)');
        $insert->execute([$this->id, $userId, $choice]);
        return true;
    }

    /** @return array{question: string, answers: list<string>, counts: list<int>, total: int, state: string, voted: ?int, pollId: int} */
    public function statusFor(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT choice, COUNT(*) AS n FROM poll_votes WHERE poll_id = ? GROUP BY choice');
        $stmt->execute([$this->id]);
        $counts = array_fill(0, count($this->answers), 0);
        $total = 0;
        foreach ($stmt->fetchAll() as $row) {
            $choice = (int) $row['choice'];
            if ($choice >= 0 && $choice < count($this->answers)) {
                $counts[$choice] = (int) $row['n'];
            }
            $total += (int) $row['n'];
        }
        $own = $pdo->prepare('SELECT choice FROM poll_votes WHERE poll_id = ? AND user_id = ? LIMIT 1');
        $own->execute([$this->id, $userId]);
        $ownRow = $own->fetch();
        return [
            'pollId' => $this->id,
            'question' => $this->question,
            'answers' => $this->answers,
            'counts' => $counts,
            'total' => $total,
            'state' => $this->state,
            'voted' => $ownRow === false ? null : (int) $ownRow['choice'],
        ];
    }
}
