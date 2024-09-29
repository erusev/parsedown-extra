<?php
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
$finder = Finder::create()
    ->in(__DIR__ . '/src/')
    ->in(__DIR__ . '/tests/')
;
$rules = [
    '@PSR2' => true,
    'array_syntax' => [
        'syntax' => 'short',
    ],
    'blank_lines_before_namespace' => true,
    'logical_operators' => true,
    'native_constant_invocation' => [
        'fix_built_in' => true,
    ],
    'native_function_invocation' => [
        'include' => ['@all'],
    ],
    'no_unused_imports' => true,
    'ordered_imports' => [
        'sort_algorithm' => 'alpha',
    ],
    'strict_comparison' => true,
    'strict_param' => true,
    'whitespace_after_comma_in_array' => true,
];
return (new Config)
    ->setRules($rules)
    ->setFinder($finder)
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
;
