<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbArticle;

use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class KbArticleEntity extends Entity
{
    use EntityIdTrait;

    protected string $categoryId;
    protected string $title;
    protected string $slug;
    protected ?string $metaTitle = null;
    protected ?string $metaDescription = null;
    protected string $shortText;
    protected string $content;
    protected ?array $tags = null;
    protected int $position = 0;
    protected bool $active = true;
    protected ?KbCategoryEntity $category = null;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getShortText(): string
    {
        return $this->shortText;
    }

    public function setShortText(string $shortText): void
    {
        $this->shortText = $shortText;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCategory(): ?KbCategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?KbCategoryEntity $category): void
    {
        $this->category = $category;
    }
}
