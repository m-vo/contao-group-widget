<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Doctrine\DBAL\Connection;

class GroupFactory
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function create(string $table, int $rowId, string $group): Group
    {
        return new Group($this->connection, $table, $rowId, $group);
    }

    public function getGroupFields(string $table): array
    {
        return array_filter(
            $GLOBALS['TL_DCA'][$table]['fields'] ?? [],
            static fn (array $definition): bool => 'group' === ($definition['inputType'] ?? null)
                && Group::TYPE_START === ($definition['eval'][Group::KEY_COMPONENT_TYPE] ?? Group::TYPE_START)
        );
    }
}
