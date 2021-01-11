<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Mvo\ContaoGroupWidget\Entity\GroupElementEntityInterface;
use Mvo\ContaoGroupWidget\Entity\GroupEntityInterface;
use Mvo\ContaoGroupWidget\Group\Group;
use Psr\Container\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Storage adapter to store group/element data using entity classes.
 */
final class EntityStorage implements StorageInterface
{
    private ContainerInterface $locator;

    private PropertyAccessor $propertyAccessor;

    private Group $group;

    private ClassMetadata $groupMetadata;
    private ClassMetadata $elementMetadata;
    private ?GroupEntityInterface $groupEntity = null;

    public function __construct(ContainerInterface $locator, string $entity, Group $group)
    {
        $this->locator = $locator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        $metadataFactory = $this->entityManager()->getMetadataFactory();

        // Retrieve group entity metadata
        $this->groupMetadata = $metadataFactory->getMetadataFor($entity);

        if (!$this->groupMetadata->getReflectionClass()->implementsInterface(GroupEntityInterface::class)) {
            throw new \RuntimeException(sprintf("Group entity class for group '%s' must implement '%s'.", $group->getName(), GroupEntityInterface::class));
        }

        // Retrieve element entity metadata
        $this->elementMetadata = $metadataFactory->getMetadataFor(
            $this->groupMetadata->getAssociationTargetClass('elements')
        );

        if (!$this->elementMetadata->getReflectionClass()->implementsInterface(GroupElementEntityInterface::class)) {
            throw new \RuntimeException(sprintf("Element entity class for group '%s' must implement '%s'.", $group->getName(), GroupElementEntityInterface::class));
        }

        $this->group = $group;
    }

    public function getElements(): array
    {
        // Sort elements by position field
        $elements = $this->getGroupEntity()->getElements()->toArray();

        usort(
            $elements,
            static fn (GroupElementEntityInterface $a, GroupElementEntityInterface $b): int => $b->getPosition() <=> $a->getPosition()
        );

        // Get IDs
        return array_map(
            static function (GroupElementEntityInterface $element): int {
                $id = $element->getId();

                if (null === $id) {
                    throw new \RuntimeException('Element ID could not be retrieved - did you miss a flush?');
                }

                return $id;
            },
            $elements
        );
    }

    public function createElement(): int
    {
        /** @var GroupElementEntityInterface $element */
        $element = $this->elementMetadata->getReflectionClass()->newInstance();

        $element->setPosition(0);

        $groupEntity = $this->getGroupEntity();
        $groupEntity->addElement($element);

        // Persist and flush to make ID available
        $manager = $this->entityManager();
        $manager->persist($element);
        $manager->flush();

        $id = $element->getId();

        if (null === $id) {
            throw new \RuntimeException('New element ID could not be retrieved.');
        }

        return $id;
    }

    public function removeElement(int $elementId): void
    {
        $groupEntity = $this->getGroupEntity();

        /** @var GroupElementEntityInterface $element */
        foreach ($groupEntity->getElements() as $element) {
            if ($elementId === $element->getId()) {
                $groupEntity->removeElement($element);

                return;
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function orderElements(array $elementIds): void
    {
        $elementsById = [];

        /** @var GroupElementEntityInterface $element */
        foreach ($this->getGroupEntity()->getElements() as $element) {
            $id = $element->getId();

            if (null !== $id) {
                $elementsById[$id] = $element;
            }
        }

        if (\count(array_intersect($elementIds, array_keys($elementsById))) !== \count($elementsById)) {
            throw new \InvalidArgumentException('Cannot order, provided elements do not match stored data.');
        }

        foreach (array_reverse($elementIds) as $position => $id) {
            $elementsById[$id]->setPosition($position);
        }
    }

    public function getField(int $elementId, string $field)
    {
        /** @var GroupElementEntityInterface $element */
        foreach ($this->getGroupEntity()->getElements() as $element) {
            if ($element->getId() === $elementId) {
                return $this->propertyAccessor->getValue($element, $field);
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function setField(int $elementId, string $field, $value): void
    {
        /** @var GroupElementEntityInterface $element */
        foreach ($this->getGroupEntity()->getElements() as $element) {
            if ($element->getId() === $elementId) {
                $this->propertyAccessor->setValue($element, $field, $value);

                return;
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function persist(): void
    {
        $this->entityManager()->flush();
    }

    public function remove(): void
    {
        $manager = $this->entityManager();

        $manager->remove($this->getGroupEntity());

        $manager->flush();
    }

    private function getGroupEntity(): GroupEntityInterface
    {
        if (null !== $this->groupEntity) {
            return $this->groupEntity;
        }

        $manager = $this->entityManager();

        $query = $manager->createQueryBuilder()
            ->select('g')
            ->from($this->groupMetadata->getName(), 'g')
            ->where('g.sourceTable = :table AND g.sourceId = :id')
            ->setParameters([
                'table' => $this->group->getTable(),
                'id' => $this->group->getRowId(),
            ])
            ->setMaxResults(1)
            ->getQuery()
        ;

        if (null !== ($result = $query->getResult()[0] ?? null)) {
            $entity = $result;
        } else {
            /** @var GroupEntityInterface $entity */
            $entity = $this->groupMetadata->getReflectionClass()->newInstance();

            $entity->setSourceTable($this->group->getTable());
            $entity->setSourceId($this->group->getRowId());

            $manager->persist($entity);
        }

        return $this->groupEntity = $entity;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->locator->get('doctrine.orm.entity_manager');
    }
}
