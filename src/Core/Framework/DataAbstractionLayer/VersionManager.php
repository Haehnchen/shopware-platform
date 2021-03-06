<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DeleteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitCollection;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataEntity;
use Shopware\Core\Framework\Version\VersionDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class VersionManager
{
    /**
     * @var EntityWriterInterface
     */
    private $entityWriter;

    /**
     * @var EntityReaderInterface
     */
    private $entityReader;

    /**
     * @var EntitySearcherInterface
     */
    private $entitySearcher;

    /**
     * @var DefinitionRegistry
     */
    private $entityDefinitionRegistry;

    /**
     * @var EntityWriteGatewayInterface
     */
    private $entityWriteGateway;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        EntityWriterInterface $entityWriter,
        EntityReaderInterface $entityReader,
        EntitySearcherInterface $entitySearcher,
        DefinitionRegistry $entityDefinitionRegistry,
        EntityWriteGatewayInterface $entityWriteGateway,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer
    ) {
        $this->entityWriter = $entityWriter;
        $this->entityReader = $entityReader;
        $this->entitySearcher = $entitySearcher;
        $this->entityDefinitionRegistry = $entityDefinitionRegistry;
        $this->entityWriteGateway = $entityWriteGateway;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->upsert($definition, $rawData, $writeContext);

        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware()) {
            $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);
        }

        return $writtenEvent;
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->insert($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->update($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function delete(string $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $deleteEvent = $this->entityWriter->delete($definition, $ids, $writeContext);

        $this->writeAuditLog($deleteEvent->getDeleted(), $writeContext, __FUNCTION__);

        return $deleteEvent;
    }

    public function createVersion(string $definition, string $id, WriteContext $context, ?string $name = null, ?string $versionId = null): string
    {
        $primaryKey = [
            'id' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ];

        $versionId = $versionId ?? Uuid::uuid4()->getHex();
        $versionData = ['id' => $versionId];

        if ($name) {
            $versionData['name'] = $name;
        } else {
            /* @var EntityDefinition|string $definition */
            $versionData['name'] = $definition::getEntityName() . (new \DateTime())->format(\DateTime::ATOM);
        }

        $this->entityWriter->upsert(VersionDefinition::class, [$versionData], $context);

        /** @var EntityDefinition|string $definition */
        $affected = $this->clone($definition, $primaryKey['id'], $primaryKey['id'], $versionId, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $versionContext->getContext(), []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        $this->writeAuditLog($affected, $context, 'clone', $versionId);

        return $versionId;
    }

    public function merge(string $versionId, WriteContext $writeContext): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('version_commit.versionId', $versionId));
        $criteria->addSorting(new FieldSorting('version_commit.autoIncrement'));

        $commitIds = $this->entitySearcher->search(VersionCommitDefinition::class, $criteria, $writeContext->getContext());
        $commits = $this->entityReader->read(VersionCommitDefinition::class, new Criteria($commitIds->getIds()), $writeContext->getContext());

        $allChanges = [];
        $entities = [];
        $cascades = [];

        $versionContext = $writeContext->createWithVersionId($versionId);
        $liveContext = $writeContext->createWithVersionId(Defaults::LIVE_VERSION);

        $writtenEvents = [];
        $deletedEvents = [];

        /** @var VersionCommitCollection $commits */
        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $dataDefinition = $this->entityDefinitionRegistry->get($data->getEntityName());

                if ($data->getAction() !== 'clone') {
                    $allChanges[] = $data;
                }

                /** @var AssociationInterface[] $cascadeFields */
                $cascadeFields = $dataDefinition::getFields()
                    ->filterByFlag(CascadeDelete::class)
                    ->filterInstance(AssociationInterface::class);

                foreach ($cascadeFields as $field) {
                    $cascades[$field->getReferenceClass()] = 1;
                }

                $entity = [
                    'definition' => $dataDefinition,
                    'primary' => $data->getEntityId(),
                ];

                // deduplicate to prevent deletion errors
                $entityKey = md5(JsonFieldSerializer::encodeJson($entity));
                $entities[$entityKey] = $entity;

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                    case 'upsert':
                        $payload = $this->addVersionToPayload($data->getPayload(), $dataDefinition, Defaults::LIVE_VERSION);
                        $events = $this->entityWriter->upsert($dataDefinition, [$payload], $liveContext);

                        $writtenEvents = array_merge_recursive($writtenEvents, $events);

                        break;

                    case 'delete':
                        $id = $data->getEntityId();
                        $id = $this->addVersionToPayload($id, $dataDefinition, Defaults::LIVE_VERSION);

                        if ($deleteProtectionKey = $dataDefinition::getDeleteProtectionKey()) {
                            $liveContext->getContext()->getDeleteProtection()->allow($deleteProtectionKey);
                        }

                        $deletedEvents[] = $this->entityWriter->delete($dataDefinition, [$id], $liveContext);

                        break;
                }
            }

            $liveContext->getContext()->getDeleteProtection()->allow(VersionCommitDefinition::getDeleteProtectionKey());

            $this->entityWriter->delete(VersionCommitDefinition::class, [['id' => $commit->getId()]], $liveContext);

            $liveContext->getContext()->getDeleteProtection()->disallow(VersionCommitDefinition::getDeleteProtectionKey());
        }

        $newData = array_map(function (VersionCommitDataEntity $data) {
            $definition = $this->entityDefinitionRegistry->get($data->getEntityName());

            $id = $data->getEntityId();
            $id = $this->addVersionToPayload($id, $definition, Defaults::LIVE_VERSION);

            $payload = $this->addVersionToPayload($data->getPayload(), $definition, Defaults::LIVE_VERSION);

            return [
                'entityId' => $id,
                'payload' => JsonFieldSerializer::encodeJson($payload),
                'userId' => $data->getUserId(),
                'integrationId' => $data->getIntegrationId(),
                'entityName' => $data->getEntityName(),
                'action' => $data->getAction(),
                'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
            ];
        }, $allChanges);

        $sourceContext = $writeContext->getContext()->getSourceContext();
        $commit = [
            'versionId' => Defaults::LIVE_VERSION,
            'data' => $newData,
            'integrationId' => $sourceContext->getIntegrationId(),
            'userId' => $sourceContext->getUserId(),
            'isMerge' => true,
            'message' => 'merge commit ' . (new \DateTime())->format(\DateTime::ATOM),
        ];

        $this->entityWriter->insert(VersionCommitDefinition::class, [$commit], $writeContext);

        $writeContext->getContext()->getDeleteProtection()->allow(VersionDefinition::getDeleteProtectionKey());
        $this->entityWriter->delete(VersionDefinition::class, [['id' => $versionId]], $writeContext);
        $writeContext->getContext()->getDeleteProtection()->disallow(VersionDefinition::getDeleteProtectionKey());

        foreach ($entities as $entity) {
            // this entity will be deleted because of it's constraint
            if (isset($cascades[$entity['definition']])) {
                continue;
            }

            /** @var EntityDefinition|string $definition */
            $definition = $entity['definition'];
            $primary = $entity['primary'];
            $primary = $this->addVersionToPayload($primary, $definition, $versionId);

            $wasDeletionAllowed = false;
            $deleteProtectionKey = $definition::getDeleteProtectionKey();

            if ($deleteProtectionKey) {
                $wasDeletionAllowed = $writeContext->getContext()->getDeleteProtection()->isAllowed($deleteProtectionKey);
                $writeContext->getContext()->getDeleteProtection()->allow(VersionDefinition::getDeleteProtectionKey());
            }

            $this->entityWriter->delete($definition, [$primary], $versionContext);

            if ($deleteProtectionKey && !$wasDeletionAllowed) {
                $writeContext->getContext()->getDeleteProtection()->disallow($deleteProtectionKey);
            }
        }

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($writtenEvents, $liveContext->getContext(), []);
        $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

        /** @var DeleteResult[] $deletedEvents */
        foreach ($deletedEvents as $deletedEvent) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents($deletedEvent->getDeleted(), $liveContext->getContext(), $deletedEvent->getNotFound());
            $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);
        }
    }

    public function clone(
        string $definition,
        string $id,
        string $newId,
        string $versionId,
        WriteContext $context
    ): array {
        /** @var string|EntityDefinition $definition */
        $criteria = new Criteria([$id]);
        $this->addCloneAssociations($definition, $criteria);

        $detail = $this->entityReader->read($definition, $criteria, $context->getContext())->first();

        if ($detail === null) {
            throw new \Exception(sprintf('Cannot create new version. %s by id (%s) not found.', $definition::getEntityName(), $id));
        }

        $data = json_decode($this->serializer->serialize($detail, 'json'), true);
        $data = JsonType::format($data);

        $keepIds = $newId === $id;

        $data = $this->filterPropertiesForClone($definition, $data, $keepIds, $id, $definition);
        $data['id'] = $newId;

        $versionContext = $context->createWithVersionId($versionId);

        return $this->entityWriter->insert($definition, [$data], $versionContext);
    }

    private function filterPropertiesForClone(string $definition, array $data, bool $keepIds, string $cloneId, string $cloneDefinition): array
    {
        $extensions = [];
        $payload = [];

        /** @var string|EntityDefinition $definition */

        /** @var FieldCollection $fields */
        $fields = $definition::getFields()->filter(
            function (Field $field) {
                return !$field->is(ReadOnly::class);
            }
        );

        /** @var Field $field */
        foreach ($fields as $field) {
            //set data and payload cursor to root or extensions to simplify following if conditions
            $dataCursor = &$data;

            $payloadCursor = &$payload;

            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $dataCursor = $data['extensions'];
                $payloadCursor = $extensions;
            }

            if (!array_key_exists($field->getPropertyName(), $dataCursor)) {
                continue;
            }

            if ($field instanceof ParentFkField && !$keepIds) {
                continue;
            }

            $value = $dataCursor[$field->getPropertyName()];

            // remove reference of cloned entity in all sub entity routes. Appears in a parent-child nested data tree
            if ($field instanceof FkField && !$keepIds && $value === $cloneId && $cloneDefinition === $field->getReferenceClass()) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            //scalar value? assign directly
            if (!$field instanceof AssociationInterface) {
                $payloadCursor[$field->getPropertyName()] = $value;

                continue;
            }

            //many to one should be skipped because it is no part of the root entity
            if ($field instanceof ManyToOneAssociationField) {
                continue;
            }

            if (!$field->is(CascadeDelete::class)) {
                continue;
            }

            if ($field instanceof OneToManyAssociationField) {
                $nested = [];
                foreach ($value as $item) {
                    $nestedItem = $this->filterPropertiesForClone($field->getReferenceClass(), $item, $keepIds, $cloneId, $cloneDefinition);

                    if (!$keepIds) {
                        $nestedItem = $this->removePrimaryKey($field, $nestedItem);
                    }

                    $nested[] = $nestedItem;
                }

                $nested = array_filter($nested);
                if (empty($nested)) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $nested = [];

                foreach ($value as $item) {
                    $nested[] = ['id' => $item['id']];
                }
                $nested = array_filter($nested);

                if (empty($nested)) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }
        }

        if (!empty($extensions)) {
            $payload['extensions'] = $extensions;
        }

        return $payload;
    }

    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, string $action, ?string $versionId = null): void
    {
        $sourceContext = $writeContext->getContext()->getSourceContext();
        $versionId = $versionId ?? $writeContext->getContext()->getVersionId();

        if ($userId = $sourceContext->getUserId()) {
            $userId = Uuid::fromHexToBytes($sourceContext->getUserId());
        }

        if ($integrationId = $sourceContext->getIntegrationId()) {
            $integrationId = Uuid::fromHexToBytes($sourceContext->getIntegrationId());
        }

        $commitId = Uuid::uuid4();

        $date = (new \DateTime())->format(Defaults::DATE_FORMAT);

        $insert = new InsertCommand(
            VersionCommitDefinition::class,
            [
                'id' => $commitId->getBytes(),
                'user_id' => $userId,
                'integration_id' => $integrationId,
                'version_id' => Uuid::fromStringToBytes($versionId),
                'created_at' => $date,
            ],
            ['id' => $commitId->getBytes()],
            new EntityExistence(
                VersionCommitDefinition::class,
                ['id' => $commitId->getBytes()],
                false,
                false,
                false,
                []
            )
        );

        $commands = [$insert];

        foreach ($writtenEvents as $definition => $items) {
            $definition = (string) $definition;
            /** @var EntityDefinition|string $definition */
            $entityName = $definition::getEntityName();

            if (!$definition::isVersionAware()) {
                continue;
            }

            if (strpos('version', $entityName) === 0) {
                continue;
            }

            foreach ($items as $item) {
                $payload = $item->getPayload();

                $primary = $item->getPrimaryKey();
                if (!\is_array($primary)) {
                    $primary = ['id' => $item->getPrimaryKey()];
                }
                $primary['versionId'] = $versionId;

                $id = Uuid::uuid4()->getBytes();

                $commands[] = new InsertCommand(
                    VersionCommitDataDefinition::class,
                    [
                        'id' => $id,
                        'version_commit_id' => $commitId->getBytes(),
                        'entity_name' => $entityName,
                        'entity_id' => JsonFieldSerializer::encodeJson($primary),
                        'payload' => JsonFieldSerializer::encodeJson($payload),
                        'user_id' => $userId,
                        'integration_id' => $integrationId,
                        'action' => $action,
                        'created_at' => $date,
                    ],
                    ['id' => $id],
                    new EntityExistence(
                        VersionCommitDataDefinition::class,
                        ['id' => $id],
                        false,
                        false,
                        false,
                        []
                    )
                );
            }
        }

        if (\count($commands) <= 1) {
            return;
        }

        $this->entityWriteGateway->execute($commands, $writeContext);
    }

    private function addVersionToPayload(array $payload, string $definition, string $versionId): array
    {
        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields()->filter(function (Field $field) {
            return $field instanceof VersionField || $field instanceof ReferenceVersionField;
        });

        /** @var FieldCollection $fields */
        foreach ($fields as $field) {
            $payload[$field->getPropertyName()] = $versionId;
        }

        return $payload;
    }

    private function removePrimaryKey(OneToManyAssociationField $field, array $nestedItem): array
    {
        $pkFields = $field->getReferenceClass()::getPrimaryKeys();

        /** @var Field|StorageAware $pkField */
        foreach ($pkFields as $pkField) {
            /*
             * `EntityTranslationDefinition`s dont have an `id`, they use a composite primary key consisting of the
             * entity id and the `languageId`. When cloning the entity we want to copy the `languageId`. The entity id
             * has to be unset, so that its set by the parent, resulting in a valid primary key.
             */
            if ($field instanceof TranslationsAssociationField && $pkField->getStorageName() === $field->getLanguageField()) {
                continue;
            }
            if (array_key_exists($pkField->getPropertyName(), $nestedItem)) {
                unset($nestedItem[$pkField->getPropertyName()]);
            }
        }

        return $nestedItem;
    }

    private function addCloneAssociations(string $definition, Criteria $criteria, int $childCounter = 1): void
    {
        //add all cascade delete associations
        /** @var string|EntityDefinition $definition */
        $cascades = $definition::getFields()->filterByFlag(CascadeDelete::class);

        foreach ($cascades as $cascade) {
            $nested = new Criteria();

            $criteria->addAssociation($definition::getEntityName() . '.' . $cascade->getPropertyName(), $nested);

            /** @var AssociationInterface $cascade */
            if ($cascade instanceof ManyToManyAssociationField) {
                continue;
            }

            //many to one shouldn't be cascaded
            if ($cascade instanceof ManyToOneAssociationField) {
                continue;
            }

            /** @var OneToManyAssociationField $cascade */
            $reference = $cascade->getReferenceClass();

            $childrenAware = $reference::isChildrenAware();

            //first level of parent-child tree?
            if ($childrenAware && $reference !== $definition) {
                //where product.children.parentId IS NULL
                $nested->addFilter(new EqualsFilter($reference::getEntityName() . '.parentId', null));
            }

            if ($cascade instanceof ChildrenAssociationField) {
                //break endless loop
                if ($childCounter >= 30) {
                    continue;
                }

                ++$childCounter;
                $this->addCloneAssociations($reference, $nested, $childCounter);

                continue;
            }

            $this->addCloneAssociations($reference, $nested);
        }
    }
}
