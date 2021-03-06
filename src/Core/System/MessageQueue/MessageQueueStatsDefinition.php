<?php declare(strict_types=1);

namespace Shopware\Core\System\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class MessageQueueStatsDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'message_queue_stats';
    }

    public static function getCollectionClass(): string
    {
        return MessageQueueStatsCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MessageQueueStatsEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        return [
            'size' => 0,
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IntField('size', 'size', 0))->setFlags(new Required()),
        ]);
    }
}
