<?php

namespace Erusev\ParsedownExtra\Tests;

use Erusev\Parsedown\StateBearer;
use Erusev\Parsedown\Tests\ParsedownTest;
use Erusev\ParsedownExtra\ParsedownExtra;

class ParsedownExtraTest extends ParsedownTest
{
    protected function initDirs()
    {
        $dirs = parent::initDirs();
        $dirs[] = __DIR__ . '/data/';
        return $dirs;
    }

    protected function initState(string $testName): StateBearer
    {
        return ParsedownExtra::from(parent::initState($testName));
    }
}
