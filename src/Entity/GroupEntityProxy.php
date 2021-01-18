<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Inflector\InflectorFactory;

class GroupEntityProxy
{
    private object $groupEntity;

    private string $methodGet;
    private string $methodAdd;
    private string $methodRemove;

    public function __construct(object $groupEntity, string $associationProperty)
    {
        $this->groupEntity = $groupEntity;

        $inflector = InflectorFactory::create()->build();

        $pluralSuffix = ucfirst($associationProperty);
        $singularSuffix = ucfirst($inflector->singularize($associationProperty));

        $this->methodGet = sprintf('get%s', $pluralSuffix);
        $this->methodAdd = sprintf('add%s', $singularSuffix);
        $this->methodRemove = sprintf('remove%s', $singularSuffix);

        foreach ([$this->methodGet, $this->methodAdd, $this->methodRemove] as $method) {
            if (!method_exists($groupEntity, $method)) {
                throw new \LogicException(sprintf("Group entity '%s' needs to have a method '%s' to be able to access the association '%s.", \get_class($groupEntity), $method, $associationProperty));
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

        return $this->groupEntity->$method();
    }

    /**
     * Call a method "addThing($element)" on the association property "things".
     */
    public function addElement(GroupElementEntityInterface $element): void
    {
        $method = $this->methodAdd;

        $this->groupEntity->$method($element);
    }

    /**
     * Call a method "removeThing($element)" on the association property "things".
     */
    public function removeElement(GroupElementEntityInterface $element): void
    {
        $method = $this->methodRemove;

        $this->groupEntity->$method($element);
    }
}
