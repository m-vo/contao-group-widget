<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\InflectorFactory;

class GroupEntityProxy
{
    private object $groupEntity;

    private bool $collection;

    private string $methodGet = '';

    private string $methodSet = '';

    private string $methodAdd = '';

    private string $methodRemove = '';

    public function __construct(object $groupEntity, string $associationProperty, bool $collection = true)
    {
        $this->groupEntity = $groupEntity;
        $this->collection = $collection;

        if ($collection) {
            $inflector = InflectorFactory::create()->build();

            $pluralSuffix = ucfirst($associationProperty);
            $singularSuffix = ucfirst($inflector->singularize($associationProperty));

            $this->methodGet = sprintf('get%s', $pluralSuffix);
            $this->methodAdd = sprintf('add%s', $singularSuffix);
            $this->methodRemove = sprintf('remove%s', $singularSuffix);
        } else {
            $suffix = ucfirst($associationProperty);

            $this->methodGet = sprintf('get%s', $suffix);
            $this->methodSet = sprintf('set%s', $suffix);
        }

        foreach (array_filter([$this->methodGet, $this->methodSet, $this->methodAdd, $this->methodRemove]) as $method) {
            if (!method_exists($groupEntity, $method)) {
                throw new \LogicException(sprintf("Group entity '%s' needs to have a method '%s' to be able to access the association '%s'.", $groupEntity::class, $method, $associationProperty));
            }
        }
    }

    public function getReference(): object
    {
        return $this->groupEntity;
    }

    /**
     * Call a method "getThings()" on the association property "things".
     */
    public function getElements(): Collection
    {
        $method = $this->methodGet;
        $value = $this->groupEntity->$method();

        if ($this->collection) {
            return $value;
        }

        // Wrap into a collection with 0 or 1 elements in case of a one to one relation
        return new ArrayCollection(array_filter([$value]));
    }

    /**
     * Call a method "addThing($element)" on the association property "things".
     */
    public function addElement(object $element): void
    {
        $method = $this->collection ? $this->methodAdd : $this->methodSet;

        $this->groupEntity->$method($element);
    }

    /**
     * Call a method "removeThing($element)" on the association property "things".
     */
    public function removeElement(object $element): void
    {
        if ($this->collection) {
            $method = $this->methodRemove;
            $this->groupEntity->$method($element);

            return;
        }

        // Set to null in case of a one to one relation
        $method = $this->methodSet;
        $this->groupEntity->$method(null);
    }
}
