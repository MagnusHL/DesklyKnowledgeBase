<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbArticle;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(KbArticleEntity $entity)
 * @method void set(string $key, KbArticleEntity $entity)
 * @method KbArticleEntity[] getIterator()
 * @method KbArticleEntity[] getElements()
 * @method KbArticleEntity|null get(string $key)
 * @method KbArticleEntity|null first()
 * @method KbArticleEntity|null last()
 */
class KbArticleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return KbArticleEntity::class;
    }
}
