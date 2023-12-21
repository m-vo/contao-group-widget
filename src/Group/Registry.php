<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Group;

use Contao\CoreBundle\Session\Attribute\ArrayAttributeBag;
use Doctrine\DBAL\Connection;
use Mvo\ContaoGroupWidget\Storage\NullStorage;
use Mvo\ContaoGroupWidget\Storage\StorageFactoryInterface;
use Mvo\ContaoGroupWidget\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\DcMultilingualBundle\Driver;
use Twig\Environment;

/**
 * Group factory methods.
 *
 * @final
 */
class Registry
{
    private Environment $twig;
    private RequestStack $requestStack;
    private Connection $connection;

    /**
     * @var array<string, StorageFactoryInterface>
     */
    private array $storageFactories = [];

    /**
     * @var array<string, array<string,Group>>
     */
    private array $groupCache = [];

    /**
     * @internal
     */
    public function __construct(Environment $twig, RequestStack $requestStack, Connection $connection, \IteratorAggregate $storageFactories)
    {
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->connection = $connection;

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
        if (null !== ($group = $this->groupCache[$cacheKey = $this->getCacheKey($table, $rowId)][$name] ?? null)) {
            return $group;
        }

        if (null === $group = $this->handleDcMultilingual($table, $rowId, $name)) {
            $group = $this->createGroup($table, $rowId, $name);
        }

        return $this->groupCache[$cacheKey][$name] = $group;
    }

    public function getInitializedGroups(string $table, int $rowId): array
    {
        return array_values($this->groupCache[$this->getCacheKey($table, $rowId)] ?? []);
    }

    /**
     * @return array<string>
     */
    public function getGroupFields(string $table): array
    {
        return array_keys(
            array_filter(
                array_filter($GLOBALS['TL_DCA'][$table]['fields'] ?? []),
                static fn (array $definition): bool => 'group' === ($definition['inputType'] ?? null)
            )
        );
    }

    private function createGroup(string $table, int $rowId, string $name, StorageInterface $storage = null): Group
    {
        $group = new Group($this->twig, $table, $rowId, $name);
        $group->setStorage($storage ?? $this->createStorage($table, $name, $group));

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

    private function getCacheKey(string $table, int $rowId): string
    {
        return $table."\x0".$rowId;
    }

    /**
     * DC Multilingual stores translations in their own rows. In order to be
     * compatible, we need to adjust the target $rowId in case a translated
     * version was selected.
     */
    private function handleDcMultilingual(string $table, int $rowId, string $name): ?Group
    {
        /** @psalm-suppress UndefinedClass */
        if (($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] ?? '') !== Driver::class) {
            return null;
        }

        /** @var ArrayAttributeBag $contaoBackendBag */
        $contaoBackendBag = $this->requestStack
            ->getSession()
            ->getBag('contao_backend')
        ;

        $language = $contaoBackendBag->get("dc_multilingual:$table:$rowId");

        if (null === $language) {
            return null;
        }

        $pidColumn = $GLOBALS['TL_DCA'][$table]['config']['langPid'] ?? 'langPid';
        $languageColumn = $GLOBALS['TL_DCA'][$table]['config']['langColumnName'] ?? 'language';

        $result = $this->connection->fetchOne(
            sprintf(
                'SELECT id FROM %s WHERE %s=? AND %s=?',
                $this->connection->quoteIdentifier($table),
                $this->connection->quoteIdentifier($pidColumn),
                $this->connection->quoteIdentifier($languageColumn),
            ),
            [$rowId, $language]
        );

        if ($result) {
            return $this->createGroup($table, (int) $result, $name);
        }

        // In case we do not have a record yet, we create a group with an empty
        // dummy storage that does not persist anything - otherwise the parent
        // entries would show up. As soon as the record gets saved/the page is
        // reloaded, we do have a record and the real storage kicks in
        // persisting the posted values.
        return $this->createGroup($table, $rowId, $name, new NullStorage());
    }
}
