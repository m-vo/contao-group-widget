<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\Parameter;
use Mvo\ContaoGroupWidget\Entity\GroupEntityProxy;
use Mvo\ContaoGroupWidget\Group\Group;
use Mvo\ContaoGroupWidget\Util\ObjectAccessor;

class EntityStorageFactory implements StorageFactoryInterface
{
    private EntityManagerInterface $entityManager;

    private ObjectAccessor $objectAccessor;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->objectAccessor = new ObjectAccessor();
    }

    public static function getName(): string
    {
        return 'entity';
    }

    public function create(Group $group): EntityStorage
    {
        $entityClass = $group->getDefinition('entity');

        if (null === $entityClass) {
            // If no entity reference is defined, assume that there is an entity backing the
            // current DCA with a field for the group.
            [$entity, $targetMapping] = $this->getLocalEntity($group);
        } else {
            // Otherwise try to locate a referenced DCA via the 'entity' key.
            [$entity, $targetMapping] = $this->getReferencedEntity($entityClass, $group);
        }

        // Wrap entity into a proxy that handles accessing and manipulating the element
        // association. The entity must have methods that follow a contract (either
        // 'get<Elements>'/'add<Element>'/'remove<Element>' or
        // 'get<Element>'/'set<Element>' in case of a one to one relation).
        $oneToOneRelation = ClassMetadataInfo::ONE_TO_ONE === $targetMapping['type'];
        $groupEntityProxy = new GroupEntityProxy($entity, $targetMapping['fieldName'], !$oneToOneRelation);

        // Get the class of the element entity (the target in the association).
        $elementEntity = $targetMapping['targetEntity'];

        return new EntityStorage($this->entityManager, $groupEntityProxy, $elementEntity);
    }

    private function getLocalEntity(Group $group)
    {
        $name = $group->getName();
        $table = $group->getTable();

        // Find metadata for the entity that matches the group's table
        $classMetadata = (
            function () use ($table, $name): ClassMetadata {
                /** @var ClassMetadata $metadata */
                foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
                    if ($table === $metadata->getTableName()) {
                        return $metadata;
                    }
                }

                throw new \InvalidArgumentException("There is no entity for table '$table'. Did you forget an 'entity' definition for group '$name'?");
            }
        )();

        if (!$classMetadata->hasAssociation($name)) {
            throw new \InvalidArgumentException("Entity '{$classMetadata->getName()}' does not contain a field for group '$name'.");
        }

        $targetMapping = $classMetadata->getAssociationMapping($name);

        // Get current instance
        $query = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from($classMetadata->getName(), 'g')
            ->where('g.id = :id')
            ->setParameters(new ArrayCollection([
                new Parameter('id', $group->getRowId()),
            ]))
            ->setMaxResults(1)
            ->getQuery()
        ;

        if (null !== ($result = $query->getResult()[0] ?? null)) {
            return [$result, $targetMapping];
        }

        throw new \RuntimeException("No group record was found for table '{$group->getTable()}' ID {$group->getRowId()}.");
    }

    private function getReferencedEntity(string $referencedEntity, Group $group)
    {
        // Find metadata for the referenced entity
        /** @var ClassMetadata $classMetadata */
        /** @psalm-suppress ArgumentTypeCoercion */
        $classMetadata = $this->entityManager
            ->getMetadataFactory()
            ->getMetadataFor($referencedEntity)
        ;

        if (!$classMetadata->hasAssociation('elements')) {
            throw new \InvalidArgumentException("Entity '{$classMetadata->getName()}' does not contain a field 'elements' referencing group elements of group '{$group->getName()}'.");
        }

        $targetMapping = $classMetadata->getAssociationMapping('elements');

        if (!$classMetadata->hasField('sourceTable') || !$classMetadata->hasField('sourceId')) {
            throw new \InvalidArgumentException("Entity '{$classMetadata->getName()}' needs to contain fields 'sourceTable' and 'sourceId' in order to associate field group '{$group->getName()}'.");
        }

        // Get current instance
        /** @psalm-suppress InvalidArgument */
        $query = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from($classMetadata->getName(), 'g')
            ->where('g.sourceTable = :table AND g.sourceId = :id')
            ->setParameters(new ArrayCollection([
                new Parameter('table', $group->getTable()),
                new Parameter('id', $group->getRowId()),
            ]))
            ->setMaxResults(1)
            ->getQuery()
        ;

        if (null !== ($result = $query->getResult()[0] ?? null)) {
            return [$result, $targetMapping];
        }

        // Create a new instance
        $entity = $classMetadata
            ->getReflectionClass()
            ->newInstance()
        ;

        $this->objectAccessor->setValue($entity, 'sourceTable', $group->getTable());
        $this->objectAccessor->setValue($entity, 'sourceId', $group->getRowId());

        $this->entityManager->persist($entity);

        return [$entity, $targetMapping];
    }
}
