<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\Storage;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

class DoctrineTestHelper
{
    public static function createEntityManager(): EntityManager
    {
        if (!\extension_loaded('pdo_sqlite')) {
            TestCase::markTestSkipped('Extension pdo_sqlite is required.');
        }

        $config = new Configuration();

        $config->setEntityNamespaces(['Tests' => 'Mvo\ContaoGroupWidget\Tests\Fixtures\Entity']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Tests');
        $config->setMetadataDriverImpl(
            new AnnotationDriver(
                new AnnotationReader(), __DIR__.'/../Fixtures/Entity'
            )
        );
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setMetadataCacheImpl(new ArrayCache());

        $params = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $entityManager = EntityManager::create($params, $config);

        self::setupDatabase($entityManager);

        return $entityManager;
    }

    private static function setupDatabase(EntityManager $entityManager): void
    {
        $connection = $entityManager->getConnection();

        $toSchema = (new SchemaTool($entityManager))
            ->getSchemaFromMetadata(
                $entityManager->getMetadataFactory()->getAllMetadata()
            )
        ;

        $diff = $connection
            ->getSchemaManager()
            ->createSchema()
            ->getMigrateToSql($toSchema, $connection->getDatabasePlatform(), )
        ;

        foreach ($diff as $sql) {
            $connection->executeQuery($sql);
        }
    }
}
