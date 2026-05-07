<?php

declare(strict_types=1);

namespace App\TodoItem;

use App\Model;

class TodoItem extends Model
{
	private ?string $content;

	private bool $isChecked;
	public function __construct(?string $content, bool $isChecked) {
		$this->content = $content;
		$this->isChecked = $isChecked;
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
}
