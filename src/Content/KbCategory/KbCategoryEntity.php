<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbCategory;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class KbCategoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;
    protected string $slug;
    protected ?string $description = null;
    protected ?string $metaTitle = null;
    protected ?string $metaDescription = null;
    protected int $position = 0;
    protected bool $active = true;
    protected ?KbArticleCollection $articles = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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

    public function getArticles(): ?KbArticleCollection
    {
        return $this->articles;
    }

    public function setArticles(KbArticleCollection $articles): void
    {
        $this->articles = $articles;
    }
}
