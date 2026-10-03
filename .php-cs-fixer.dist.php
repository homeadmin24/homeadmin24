<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'node_modules',
        'public/build',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        
        // Additional rules for better code quality
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_opening_tag' => false,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => false, // Symfony doesn't use strict types by default
        'linebreak_after_opening_tag' => false,
        'mb_str_functions' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'no_php4_constructor' => true,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'allow_unused_params' => true,
        ],
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'php_unit_strict' => false, // Too strict for our use case
        'phpdoc_order' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
