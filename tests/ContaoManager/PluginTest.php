<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoGroupWidget\Tests\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Mvo\ContaoGroupWidget\ContaoManager\Plugin;
use Mvo\ContaoGroupWidget\MvoContaoGroupWidgetBundle;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testGetsLoadedAfterCoreBundle(): void
    {
        $plugin = new Plugin();

        $bundles = $plugin->getBundles($this->createMock(ParserInterface::class));

        self::assertCount(1, $bundles);

        /** @var BundleConfig $config */
        $config = $bundles[0];

        self::assertEquals(MvoContaoGroupWidgetBundle::class, $config->getName());
        self::assertEquals([ContaoCoreBundle::class], $config->getLoadAfter());
    }
}
