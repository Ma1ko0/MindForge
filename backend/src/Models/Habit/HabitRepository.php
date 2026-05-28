<?php

declare(strict_types=1);

namespace App\Habit;

use App\DatabaseQueryBuilder;
use Repository;

class HabitRepository extends Repository
{
    protected const TABLENAME_HABITS = "Habits";
    protected const TABLENAME_COMPLETIONS = "HabitCompletions";

    public function listByUserId(int $userId, bool $onlyActive = true): array
    {
        if ($userId <= 0) {
            return [];
        }
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'name', 'description', 'frequency', 'weekdays', 'color', 'icon', 'is_active'])
            ->table(self::TABLENAME_HABITS)
            ->where('userId', '=', $userId);
        if ($onlyActive) {
            $builder->where('is_active', '=', 1);
        }
        $rows = $builder->orderBy('name', 'ASC')->get();
        return array_map([$this, 'mapRow'], $rows);
    }

    public function getById(int $id, int $userId): ?Habit
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id', 'userId', 'name', 'description', 'frequency', 'weekdays', 'color', 'icon', 'is_active'])
            ->table(self::TABLENAME_HABITS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->limit(1)
            ->get();
        return $rows ? $this->mapRow($rows[0]) : null;
    }

    public function create(int $userId, string $name, string $description, string $frequency, string $weekdays, string $color, string $icon): int
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException("User ID must be positive");
        }
        $builder = (new DatabaseQueryBuilder($this->getConnection()))
            ->insert([
                'userId' => $userId,
                'name' => $name,
                'description' => $description,
                'frequency' => $frequency,
                'weekdays' => $weekdays,
                'color' => $color,
                'icon' => $icon,
            ])
            ->table(self::TABLENAME_HABITS);
        $builder->execute();
        return (int)$builder->getLastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $description, string $frequency, string $weekdays, string $color, string $icon): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update([
                'name' => $name,
                'description' => $description,
                'frequency' => $frequency,
                'weekdays' => $weekdays,
                'color' => $color,
                'icon' => $icon,
            ])
            ->table(self::TABLENAME_HABITS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function setActive(int $id, int $userId, bool $active): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->update(['is_active' => $active ? 1 : 0])
            ->table(self::TABLENAME_HABITS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    public function delete(int $id, int $userId): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_HABITS)
            ->where('id', '=', $id)
            ->where('userId', '=', $userId)
            ->execute();
    }

    // ---------------- Completions ----------------

    public function isCompletedOn(int $habitId, string $date): bool
    {
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['id'])
            ->table(self::TABLENAME_COMPLETIONS)
            ->where('habitId', '=', $habitId)
            ->where('completionDate', '=', $date)
            ->limit(1)
            ->get();
        return !empty($rows);
    }

    public function markComplete(int $habitId, string $date): void
    {
        // INSERT IGNORE pattern via raw — Builder doesn't support that
        $sql = "INSERT IGNORE INTO " . self::TABLENAME_COMPLETIONS
            . " (habitId, completionDate) VALUES (:hid, :d)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([':hid' => $habitId, ':d' => $date]);
    }

    public function markIncomplete(int $habitId, string $date): bool
    {
        return (new DatabaseQueryBuilder($this->getConnection()))
            ->delete()
            ->table(self::TABLENAME_COMPLETIONS)
            ->where('habitId', '=', $habitId)
            ->where('completionDate', '=', $date)
            ->execute();
    }

    /**
     * Returns an array of Y-m-d dates on which this habit was completed,
     * limited to a sensible window for streak calc.
     */
    public function getCompletionDates(int $habitId, int $sinceDays = 400): array
    {
        $cutoff = (new \DateTime())->modify('-' . $sinceDays . ' days')->format('Y-m-d');
        $rows = (new DatabaseQueryBuilder($this->getConnection()))
            ->select(['completionDate'])
            ->table(self::TABLENAME_COMPLETIONS)
            ->where('habitId', '=', $habitId)
            ->where('completionDate', '>=', $cutoff)
            ->orderBy('completionDate', 'DESC')
            ->get();
        return array_map(fn ($r) => (string)$r['completionDate'], $rows);
    }

    /**
     * Streak = consecutive scheduled days, walking back from today, that are completed.
     * Today not yet completed → start counting from yesterday.
     */
    public function calculateStreak(Habit $habit): int
    {
        $dates = array_flip($this->getCompletionDates((int)$habit->getId()));
        $cursor = new \DateTime('today');
        $today = $cursor->format('Y-m-d');
        $streak = 0;
        $startedCounting = false;

        for ($i = 0; $i < 400; $i++) {
            $date = $cursor->format('Y-m-d');
            if ($habit->isScheduledOn($date)) {
                if (isset($dates[$date])) {
                    $streak++;
                    $startedCounting = true;
                } else {
                    if ($date === $today && !$startedCounting) {
                        // today not done yet, allow skip and start from yesterday
                    } else {
                        break;
                    }
                }
            }
            $cursor->modify('-1 day');
        }
        return $streak;
    }

    private function mapRow(array $row): Habit
    {
        return new Habit(
            (int)$row['id'],
            (int)$row['userId'],
            (string)$row['name'],
            (string)($row['description'] ?? ''),
            (string)($row['frequency'] ?? 'daily'),
            (string)($row['weekdays'] ?? ''),
            (string)($row['color'] ?? 'blue'),
            (string)($row['icon'] ?? 'repeat'),
            (int)($row['is_active'] ?? 1) === 1,
        );
    }
}
