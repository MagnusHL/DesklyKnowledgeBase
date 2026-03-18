<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbCategory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(KbCategoryEntity $entity)
 * @method void set(string $key, KbCategoryEntity $entity)
 * @method KbCategoryEntity[] getIterator()
 * @method KbCategoryEntity[] getElements()
 * @method KbCategoryEntity|null get(string $key)
 * @method KbCategoryEntity|null first()
 * @method KbCategoryEntity|null last()
 */
class KbCategoryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return KbCategoryEntity::class;
    }
}
