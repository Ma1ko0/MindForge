<?php

declare(strict_types=1);

namespace App\TodoItem;

use App\Model;

class TodoItem extends Model
{
	private ?int $id;

	private ?string $content;

	private bool $isChecked;

	private int $listId;

	private ?string $dueDate;

	public function __construct(?int $id, ?string $content, bool $isChecked, int $listId, ?string $dueDate = null) {
		$this->id = $id;
		$this->content = $content;
		$this->isChecked = $isChecked;
		$this->listId = $listId;
		$this->dueDate = $dueDate;
	}

	public function getDueDate(): ?string
	{
		return $this->dueDate;
	}

	public function setDueDate(?string $dueDate): self
	{
		$this->dueDate = $dueDate;
		return $this;
	}

	/**
	 * Set the value of isChecked
	 *
	 * @param bool $isChecked
	 *
	 * @return self
	 */
	public function setIsChecked(bool $isChecked): self
	{
		$this->isChecked = $isChecked;

		return $this;
	}

	/**
	 * Get the value of isChecked
	 *
	 * @return bool
	 */
	public function getIsChecked(): bool
	{
		return $this->isChecked;
	}

	/**
	 * Get the value of content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Set the value of content
	 *
	 * @param string $content
	 *
	 * @return self
	 */
	public function setContent(string $content): self
	{
		$this->content = $content;

		return $this;
	}

	/**
	 * Get the value of listId
	 *
	 * @return int
	 */
	public function getListId(): int
	{
		return $this->listId;
	}

	/**
	 * Get the value of id
	 *
	 * @return int|null
	 */
	public function getId(): ?int
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

	/**
	 * Set the value of listId
	 *
	 * @param int $listId
	 *
	 * @return self
	 */
	public function setListId(int $listId): self
	{
		$this->listId = $listId;

		return $this;
	}
}
