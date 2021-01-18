<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Mvo\ContaoGroupWidget\Entity\GroupElementEntityInterface;
use Mvo\ContaoGroupWidget\Entity\GroupEntityProxy;

/**
 * Storage adapter to store group/element data using entity classes.
 */
final class EntityStorage implements StorageInterface
{
    private EntityManagerInterface $entityManager;

    private GroupEntityProxy $groupEntityProxy;
    private string $elementEntity;

    public function __construct(EntityManagerInterface $entityManager, GroupEntityProxy $groupEntityProxy, string $elementEntity)
    {
        $this->entityManager = $entityManager;

        $this->groupEntityProxy = $groupEntityProxy;
        $this->elementEntity = $elementEntity;
    }

    public function getElements(): array
    {
        // Sort elements by position field
        $elements = $this->groupEntityProxy->getElements()->toArray();

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
        $element = $this->entityManager
            ->getMetadataFactory()
            ->getMetadataFor($this->elementEntity)
            ->getReflectionClass()
            ->newInstance()
        ;

        // Position at the end
        $element->setPosition(0);

        $this->groupEntityProxy->addElement($element);

        // Persist and flush to make ID available
        $this->entityManager->persist($element);
        $this->entityManager->flush();

        $id = $element->getId();

        if (null === $id) {
            throw new \RuntimeException('New element ID could not be retrieved.');
        }

        return $id;
    }

    public function removeElement(int $elementId): void
    {
        /** @var GroupElementEntityInterface $element */
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($elementId === $element->getId()) {
                $this->groupEntityProxy->removeElement($element);

                return;
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function orderElements(array $elementIds): void
    {
        $elementsById = [];

        /** @var GroupElementEntityInterface $element */
        foreach ($this->groupEntityProxy->getElements() as $element) {
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
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($element->getId() === $elementId) {
                $property = new \ReflectionProperty($element, $field);
                $property->setAccessible(true);

                return $property->getValue($element);
            }
        }

        throw new \InvalidArgumentException("Element '$elementId' does not exist.");
    }

    public function setField(int $elementId, string $field, $value): void
    {
        /** @var GroupElementEntityInterface $element */
        foreach ($this->groupEntityProxy->getElements() as $element) {
            if ($element->getId() === $elementId) {
                $property = new \ReflectionProperty($element, $field);
                $property->setAccessible(true);

                $property->setValue($element, $value);

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
}
