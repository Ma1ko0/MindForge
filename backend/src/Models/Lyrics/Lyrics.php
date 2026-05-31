<?php

declare(strict_types=1);

namespace App\Lyrics;

use App\Model;

class Lyrics extends Model
{
    private ?int $id;
    private int $userId;
    private ?int $projectId;
    private string $title;
    private string $content;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(?int $id, int $userId, string $title, string $content, ?string $createdAt = null, ?string $updatedAt = null, ?int $projectId = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->projectId = $projectId;
    }

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getProjectId(): ?int { return $this->projectId; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
}
