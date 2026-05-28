<?php

declare(strict_types=1);

namespace App\Habit;

use App\Model;

class Habit extends Model
{
    public const FREQ_DAILY    = 'daily';
    public const FREQ_WEEKDAYS = 'weekdays';
    public const FREQ_WEEKEND  = 'weekend';
    public const FREQ_CUSTOM   = 'custom';

    private ?int $id;
    private int $userId;
    private string $name;
    private string $description;
    private string $frequency;
    private string $weekdays;
    private string $color;
    private string $icon;
    private bool $isActive;

    public function __construct(
        ?int $id,
        int $userId,
        string $name,
        string $description = '',
        string $frequency = self::FREQ_DAILY,
        string $weekdays = '',
        string $color = 'blue',
        string $icon = 'repeat',
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->description = $description;
        $this->frequency = $frequency;
        $this->weekdays = $weekdays;
        $this->color = $color;
        $this->icon = $icon;
        $this->isActive = $isActive;
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getFrequency(): string { return $this->frequency; }
    public function getWeekdays(): string { return $this->weekdays; }
    public function getColor(): string { return $this->color; }
    public function getIcon(): string { return $this->icon; }
    public function isActive(): bool { return $this->isActive; }

    /** Is this habit scheduled to occur on the given Y-m-d date? */
    public function isScheduledOn(string $date): bool
    {
        try {
            $iso = (int)(new \DateTime($date))->format('N'); // 1=Mon … 7=Sun
        } catch (\Throwable) {
            return false;
        }
        return match ($this->frequency) {
            self::FREQ_DAILY    => true,
            self::FREQ_WEEKDAYS => $iso >= 1 && $iso <= 5,
            self::FREQ_WEEKEND  => $iso >= 6,
            self::FREQ_CUSTOM   => in_array((string)$iso, array_map('trim', explode(',', $this->weekdays)), true),
            default             => true,
        };
    }
}
