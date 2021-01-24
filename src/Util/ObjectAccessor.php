<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Util;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @internal
 */
final class ObjectAccessor
{
    private PropertyAccessor $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function supports(object $object, string $property): bool
    {
        if (
            $this->propertyAccessor->isReadable($object, $property) &&
            $this->propertyAccessor->isWritable($object, $property)
        ) {
            return true;
        }

        $reflectionClass = new \ReflectionClass($object);

        return $reflectionClass->hasProperty($property);
    }

    /**
     * Get a value via PropertyAccess, fall back to reflection.
     *
     * @return mixed
     */
    public function getValue(object $object, string $property)
    {
        if ($this->propertyAccessor->isReadable($object, $property)) {
            return $this->propertyAccessor->getValue($object, $property);
        }

        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($object);
    }

    /**
     * Set a value via PropertyAccess, fall back to reflection.
     */
    public function setValue(object $object, string $property, $value): void
    {
        if ($this->propertyAccessor->isWritable($object, $property)) {
            $this->propertyAccessor->setValue($object, $property, $value);

            return;
        }

        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);

        $reflectionProperty->setValue($object, $value);
    }
}
