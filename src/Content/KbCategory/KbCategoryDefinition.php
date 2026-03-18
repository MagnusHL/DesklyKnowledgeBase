<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbCategory;

use Deskly\KnowledgeBase\Content\KbArticle\KbArticleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class KbCategoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'deskly_kb_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return KbCategoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return KbCategoryCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('slug', 'slug'))->addFlags(new Required(), new ApiAware()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
            (new StringField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new StringField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            new OneToManyAssociationField('articles', KbArticleDefinition::class, 'category_id'),
        ]);
    }
}
