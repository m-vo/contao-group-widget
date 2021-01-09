<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;

/**
 * Group factory methods.
 */
final class Registry
{
    private ContainerInterface $locator;

    private array $groupCache = [];

    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    public function getGroup(string $table, int $rowId, string $name): Group
    {
        $cacheKey = md5($table."\x0".$rowId."\x0".$name);

        if (null !== ($group = $this->groupCache[$cacheKey] ?? null)) {
            return $group;
        }

        return $this->groupCache[$cacheKey] = new Group($this->locator, $table, $rowId, $name);
    }

    public function getAllInitializedGroups(): array
    {
        return array_values($this->groupCache);
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
            'database_connection' => Connection::class,
            'twig' => Environment::class,
            'doctrine.orm.entity_manager' => EntityManagerInterface::class,
        ];
    }
}
