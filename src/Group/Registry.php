<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Doctrine\DBAL\Connection;
use Twig\Environment;

/**
 * Group factory methods.
 */
final class Registry
{
    private Environment $twig;
    private Connection $connection;

    private array $groupCache = [];

    public function __construct(Environment $twig, Connection $connection)
    {
        $this->twig = $twig;
        $this->connection = $connection;
    }

    public function getGroup(string $table, int $rowId, string $name): Group
    {
        $cacheKey = md5($table."\x0".$rowId."\x0".$name);

        if (null !== ($group = $this->groupCache[$cacheKey] ?? null)) {
            return $group;
        }

        return $this->groupCache[$cacheKey] = new Group(
            $this->twig,
            $this->connection,
            $table, $rowId, $name
        );
    }

    public function getAllInitializedGroups(): array
    {
        return array_values($this->groupCache);
    }

    public function getGroupFields(string $table): array
    {
        return array_filter(
            $GLOBALS['TL_DCA'][$table]['fields'] ?? [],
            static fn (array $definition): bool => 'group' === ($definition['inputType'] ?? null)
        );
    }
}
