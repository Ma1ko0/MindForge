<?php

declare(strict_types=1);

namespace App\TodoList;

use App\Model;

class TodoList extends Model
{
	private int $id;
	private string $name;

	private int $userId;

	private ?int $projectId;

	public function __construct(int $id, string $name, int $userId, ?int $projectId = null) {
		$this->id = $id;
		$this->name = $name;
		$this->userId = $userId;
		$this->projectId = $projectId;
	}

	public function getProjectId(): ?int
	{
		return $this->projectId;
	}

	public function setProjectId(?int $projectId): self
	{
		$this->projectId = $projectId;
		return $this;
	}

	/**
	 * Get the value of name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Set the value of name
	 *
	 * @param string $name
	 *
	 * @return self
	 */
	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Get the value of userId
	 *
	 * @return int
	 */
	public function getUserId(): int
	{
		return $this->userId;
	}

	/**
	 * Set the value of userId
	 *
	 * @param int $userId
	 *
	 * @return self
	 */
	public function setUserId(int $userId): self
	{
		$this->userId = $userId;

		return $this;
	}

	/**
	 * Get the value of id
	 *
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * Set the value of id
	 *
	 * @param int $id
	 *
	 * @return self
	 */
	public function setId(int $id): self
	{
		$this->id = $id;

		return $this;
	}
}
