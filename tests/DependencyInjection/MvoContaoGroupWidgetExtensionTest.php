<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\DependencyInjection;

use Mvo\ContaoGroupWidget\DependencyInjection\MvoContaoGroupWidgetExtension;
use Mvo\ContaoGroupWidget\EventListener\GroupWidgetListener;
use Mvo\ContaoGroupWidget\Group\Registry;
use Mvo\ContaoGroupWidget\Storage\EntityStorageFactory;
use Mvo\ContaoGroupWidget\Storage\SerializedStorageFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MvoContaoGroupWidgetExtensionTest extends TestCase
{
    public function testLoadsServicesYaml(): void
    {
        $extension = new MvoContaoGroupWidgetExtension();

        $containerBuilder = new ContainerBuilder();

        $extension->load([], $containerBuilder);

        $definitions = array_keys($containerBuilder->getDefinitions());

        self::assertContains(Registry::class, $definitions);
        self::assertContains(GroupWidgetListener::class, $definitions);
        self::assertContains(SerializedStorageFactory::class, $definitions);
        self::assertContains(EntityStorageFactory::class, $definitions);

        // Make sure we're adjusting the test when adding new services
        self::assertCount(5, $definitions);
    }
}
