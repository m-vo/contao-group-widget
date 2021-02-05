<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Mvo\ContaoGroupWidget\Entity\GroupEntityProxy;
use Mvo\ContaoGroupWidget\Util\ObjectAccessor;

/**
 * Storage adapter to store group/element data using entity classes.
 *
 * @internal
 */
final class EntityStorage implements StorageInterface
{
    private EntityManagerInterface $entityManager;
    private ObjectAccessor $objectAccessor;

    private GroupEntityProxy $groupEntityProxy;
    private string $elementEntity;
    private string $elementIdentifier;

    public function __construct(EntityManagerInterface $entityManager, GroupEntityProxy $groupEntityProxy, string $elementEntity)
    {
        $this->entityManager = $entityManager;
        $this->objectAccessor = new ObjectAccessor();

        $this->groupEntityProxy = $groupEntityProxy;
        $this->elementEntity = $elementEntity;

        $elementIdentifier = $this->entityManager
            ->getClassMetadata($elementEntity)
            ->getIdentifier()
        ;

        if (!\is_array($elementIdentifier) || 1 !== \count($elementIdentifier)) {
            throw new \InvalidArgumentException("Entity '$elementEntity' cannot have a composite identifier in order to use it as a group element.");
        }

        $this->elementIdentifier = $elementIdentifier[0];
    }

    /**
     * @internal
     */
    public function getGroupEntityProxy(): GroupEntityProxy
    {
        return $this->groupEntityProxy;
    }

    /**
     * @internal
     */
    public function getElementEntityClass(): string
    {
        return $this->elementEntity;
    }

    public function getElements(): array
    {
        // Sort elements by position field
        $elements = $this->groupEntityProxy->getElements()->toArray();

        usort(
            $elements,
            fn (object $a, object $b): int => $this->getElementPosition($b) <=> $this->getElementPosition($a)
        );

        // Get IDs
        return array_map(
            function (object $element): int {
                return $this->getElementId($element);
            },
            $elements
        );
    }

    public function createElement(): int
    {
        $element = $this->entityManager
            ->getMetadataFactory()
            ->getMetadataFor($this->elementEntity)
            ->getReflectionClass()
            ->newInstance()
        ;

        // Position at the end
        $this->setElementPosition($element, 0);

        $this->groupEntityProxy->addElement($element);

        // Persist and flush to make ID available
        $this->entityManager->persist($element);
        $this->entityManager->flush();

        return $this->getElementId($element);
    }

    public function removeElement(int $elementId): void
    {
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($elementId === $this->getElementId($element)) {
                $this->groupEntityProxy->removeElement($element);

                return;
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function orderElements(array $elementIds): void
    {
        $elementsById = [];

        foreach ($this->groupEntityProxy->getElements() as $element) {
            $elementsById[$this->getElementId($element)] = $element;
        }

        if (\count(array_intersect($elementIds, array_keys($elementsById))) !== \count($elementsById)) {
            throw new \InvalidArgumentException('Cannot order, provided elements do not match stored data.');
        }

        foreach (array_reverse($elementIds) as $position => $id) {
            $this->setElementPosition($elementsById[$id], $position);
        }
    }

    public function getField(int $elementId, string $field)
    {
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($elementId === $this->getElementId($element)) {
                return $this->objectAccessor->getValue($element, $field);
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function setField(int $elementId, string $field, $value): void
    {
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($elementId === $this->getElementId($element)) {
                $this->objectAccessor->setValue($element, $field, $value);

                return;
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function persist(): void
    {
        $this->entityManager->flush();
    }

    public function remove(): void
    {
        $this->entityManager->remove($this->groupEntityProxy->getReference());

        $this->entityManager->flush();
    }

    /**
     * Get an element's identifier.
     */
    private function getElementId(object $element): int
    {
        $id = $this->objectAccessor->getValue($element, $this->elementIdentifier);

        if (null === $id) {
            throw new \RuntimeException("Identifier of group element entity '{$this->elementEntity}' could not be retrieved - did you miss a flush?");
        }

        if (!\is_int($id)) {
            throw new \RuntimeException("Identifier of group element entity '{$this->elementEntity}' must be an integer.");
        }

        return $id;
    }

    /**
     * Get an element's position or 0 if no field/accessor is defined.
     */
    private function getElementPosition(object $element): int
    {
        if ($this->objectAccessor->supports($element, 'position')) {
            return $this->objectAccessor->getValue($element, 'position');
        }

        return 0;
    }

    /**
     * Set an element's position if the element has a respective field/accessor.
     */
    private function setElementPosition(object $element, int $position): void
    {
        if ($this->objectAccessor->supports($element, 'position')) {
            $this->objectAccessor->setValue($element, 'position', $position);
        }
    }
}
