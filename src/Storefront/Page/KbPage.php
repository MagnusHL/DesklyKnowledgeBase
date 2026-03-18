<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Storefront\Page;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleCollection;
use Deskly\KnowledgeBase\Content\KbArticle\KbArticleEntity;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryCollection;
use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryEntity;
use Shopware\Storefront\Page\Page;

class KbPage extends Page
{
    protected ?KbCategoryCollection $categories = null;

    protected ?KbCategoryEntity $category = null;

    protected ?KbArticleEntity $article = null;

    protected ?KbArticleCollection $articles = null;

    public function getCategories(): ?KbCategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(?KbCategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCategory(): ?KbCategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?KbCategoryEntity $category): void
    {
        $this->category = $category;
    }

    public function getArticle(): ?KbArticleEntity
    {
        return $this->article;
    }

    public function setArticle(?KbArticleEntity $article): void
    {
        $this->article = $article;
    }

    public function getArticles(): ?KbArticleCollection
    {
        return $this->articles;
    }

    public function setArticles(?KbArticleCollection $articles): void
    {
        $this->articles = $articles;
    }
}
