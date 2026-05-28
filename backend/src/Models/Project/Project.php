<?php

declare(strict_types=1);

namespace App\Project;

use App\Model;

class Project extends Model
{
    private ?int $id;
    private int $userId;
    private string $name;
    private string $description;
    private string $color;
    private string $icon;
    private bool $isArchived;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $name,
        string $description = '',
        string $color = 'blue',
        string $icon = 'folder',
        bool $isArchived = false,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->description = $description;
        $this->color = $color;
        $this->icon = $icon;
        $this->isArchived = $isArchived;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getColor(): string { return $this->color; }
    public function getIcon(): string { return $this->icon; }
    public function isArchived(): bool { return $this->isArchived; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
}
