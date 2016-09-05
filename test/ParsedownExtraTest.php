<?php

class ParsedownExtraTest extends ParsedownTest
{
    protected function initDirs()
    {
        $dirs = parent::initDirs();
        $dirs[] = __DIR__ . '/data/';
        return $dirs;
    }
}
