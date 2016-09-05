<?php

// prepare composer autoloader
$parsedownDir = null;
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    // composer root package
    require_once(__DIR__ . '/../vendor/autoload.php');
    $parsedownDir = __DIR__ . '/../vendor/erusev/parsedown';
} elseif (is_file(__DIR__ . '/../../../../vendor/autoload.php')) {
    // composer dependency package
    require_once(__DIR__ . '/../../../../vendor/autoload.php');
    $parsedownDir = __DIR__ . '/../../../../vendor/erusev/parsedown';
} else {
    die("Cannot find 'vendor/autoload.php'. Run \`composer install\`.");
}

// load TestParsedown class
if (!class_exists('TestParsedown', false)) {
    if (is_file('test/TestParsedown.php')) {
        require_once('test/TestParsedown.php');
    } else {
        require($parsedownDir . '/test/TestParsedown.php');
    }
}

// load ParsedownTest (ParsedownExtraTest extends ParsedownTest)
if (is_file($parsedownDir . '/test/ParsedownTest.php')) {
    require($parsedownDir . '/test/ParsedownTest.php');
} else {
    die("Cannot find 'vendor/erusev/parsedown/test/ParsedownTest.php'. Run \`composer install --dev --prefer-source\`.");
}
