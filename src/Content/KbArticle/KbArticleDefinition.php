<?php

declare(strict_types=1);

namespace Deskly\KnowledgeBase\Content\KbArticle;

use Deskly\KnowledgeBase\Content\KbCategory\KbCategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class KbArticleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'deskly_kb_article';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return KbArticleEntity::class;
    }

    public function getCollectionClass(): string
    {
        return KbArticleCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('category_id', 'categoryId', KbCategoryDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('title', 'title'))->addFlags(new Required(), new ApiAware()),
            (new StringField('slug', 'slug'))->addFlags(new Required(), new ApiAware()),
            (new StringField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new StringField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new LongTextField('short_text', 'shortText'))->addFlags(new Required(), new ApiAware()),
            (new LongTextField('content', 'content'))->addFlags(new Required(), new ApiAware()),
            (new JsonField('tags', 'tags'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('category', 'category_id', KbCategoryDefinition::class, 'id', false),
        ]);
    }
}
