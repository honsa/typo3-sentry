<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Tests')
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Use the non-deprecated PER ruleset
        '@PER-CS1x0' => true,
        // Add TYPO3-specific rules
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@all']],
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_no_package' => false,
        'phpdoc_summary' => false,
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['author', 'package', 'subpackage'],
        ],
    ])
    ->setFinder($finder);

