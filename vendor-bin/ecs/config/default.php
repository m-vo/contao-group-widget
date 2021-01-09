<?php

declare(strict_types=1);

use Contao\EasyCodingStandard\Sniffs\ContaoFrameworkClassAliasSniff;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/../vendor/contao/easy-coding-standard/config/set/contao.php');

    $services = $containerConfigurator->services();

    $services
        ->set(HeaderCommentFixer::class)
        ->call('configure', [[
            'header' => "@author  Moritz Vondano\n@license MIT",
        ]]);

    $services
        ->set(PhpUnitTestCaseStaticMethodCallsFixer::class)
        ->call('configure', [[
            'call_type' => 'self',
        ]])
    ;

    $services
        ->set(ContaoFrameworkClassAliasSniff::class, \stdClass::class);
};
