<?php

declare(strict_types=1);

use Contao\EasyCodingStandard\Fixer\TypeHintOrderFixer;
use Contao\EasyCodingStandard\Sniffs\UseSprintfInExceptionsSniff;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->import(__DIR__ . '/../vendor/contao/easy-coding-standard/config/contao.php');

    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => "@author  Moritz Vondano\n@license MIT",
    ]);

    $ecsConfig->ruleWithConfiguration(PhpUnitTestCaseStaticMethodCallsFixer::class, [
        'call_type' => 'self'
    ]);

    $ecsConfig->skip([
        TypeHintOrderFixer::class,
        UseSprintfInExceptionsSniff::class,
    ]);
};
