<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Mvo\ContaoGroupWidget\Storage\StorageFactoryInterface;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

/**
 * Group factory methods.
 *
 * @final
 */
class Registry
{
    private ContainerInterface $locator;

    /**
     * @var array<string, StorageFactoryInterface>
     */
    private array $storageFactories = [];

    /**
     * @var array<string, Group>
     */
    private array $groupCache = [];

    public function __construct(ContainerInterface $locator, \IteratorAggregate $storageFactories)
    {
        $this->locator = $locator;

        /** @var StorageFactoryInterface $factory */
        foreach ($storageFactories->getIterator() as $factory) {
            $this->storageFactories[$factory::getName()] = $factory;
        }
    }

    /**
     * Creates and returns a group. The same instance will be returned if
     * called with identical arguments.
     */
    public function getGroup(string $table, int $rowId, string $name): Group
    {
        $cacheKey = md5($table."\x0".$rowId."\x0".$name);

        if (null !== ($group = $this->groupCache[$cacheKey] ?? null)) {
            return $group;
        }

        return $this->groupCache[$cacheKey] = $this->createGroup($table, $rowId, $name);
    }

    public function getInitializedGroups(string $table, int $rowId): array
    {
        return array_values(
            array_filter(
                $this->groupCache,
                static fn (Group $group): bool => $table === $group->getTable() && $rowId === $group->getRowId()
            )
        );
    }

    /**
     * @return array<string>
     */
    public function getGroupFields(string $table): array
    {
        return array_keys(
            array_filter(
                $GLOBALS['TL_DCA'][$table]['fields'] ?? [],
                static fn (array $definition): bool => 'group' === ($definition['inputType'] ?? null)
            )
        );
    }

    public static function getSubscribedServices(): array
    {
        return [
            self::class,
            'twig' => Environment::class,
        ];
    }

    private function createGroup(string $table, int $rowId, string $name): Group
    {
        $group = new Group($this->locator, $table, $rowId, $name);

        $group->setStorage($this->createStorage($table, $name, $group));

        return $group;
    }

    private function createStorage(string $table, $name, Group $group): StorageInterface
    {
        $storageType = $GLOBALS['TL_DCA'][$table]['fields'][$name]['storage'] ?? 'serialized';

        $storageFactory = $this->storageFactories[$storageType] ?? null;

        if (null === $storageFactory) {
            throw new \InvalidArgumentException("Invalid definition for group '$name': Unknown storage type '$storageType'.");
        }

        return $storageFactory->create($group);
    }
}
