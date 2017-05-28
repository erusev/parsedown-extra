<?php

class ParsedownExtraTest extends ParsedownTest
{
    protected function initDirs()
    {
        $dirs = parent::initDirs();

        $dirs []= dirname(__FILE__).'/data/';

        return $dirs;
    }

    protected function initParsedown()
    {
        $Parsedown = new ParsedownExtra();

        return $Parsedown;
    }

    public function testMultipleFootnoteCalls()
    {
        $parsedown = $this->initParsedown();

        $markdown = file_get_contents(__DIR__.'/data/footnote.md');

        $expectedMarkup = file_get_contents(__DIR__.'/data/footnote.html');

        $expectedMarkup = str_replace("\r\n", "\n", $expectedMarkup);
        $expectedMarkup = str_replace("\r", "\n", $expectedMarkup);

        $actualMarkup = $parsedown->text($markdown);
        $this->assertEquals($expectedMarkup, $actualMarkup);

        $actualMarkup = $parsedown->text($markdown);
        $this->assertEquals($expectedMarkup, $actualMarkup);
    }
}
