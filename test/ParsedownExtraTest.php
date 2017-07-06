<?php

class ParsedownExtraTest extends ParsedownTest
{
    protected function initDirs()
    {
        $dirs = parent::initDirs();
        $dirs[] = __DIR__ . '/data/';
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

        $filepath = __DIR__ . '/data/footnote.md';
        if (!file_exists($filepath)) {
            $this->markTestSkipped();
        }
        $markdown = file_get_contents($filepath);

        $expectedMarkup = file_get_contents(__DIR__.'/data/footnote.html');

        $expectedMarkup = str_replace("\r\n", "\n", $expectedMarkup);
        $expectedMarkup = str_replace("\r", "\n", $expectedMarkup);

        $actualMarkup = $parsedown->text($markdown);
        $this->assertEquals($expectedMarkup, $actualMarkup);

        $actualMarkup = $parsedown->text($markdown);
        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function test_footnote_prefix()
    {
        $filepath = __DIR__ . '/data/footnote.md';
        if (!file_exists($filepath)) {
            $this->markTestSkipped();
        }
        $markdownInput = file_get_contents($filepath);
        $markdownInput = str_replace( "\r\n", "\n", $markdownInput );
        $markdownInput = str_replace( "\r", "\n", $markdownInput );

        $parsedown = new ParsedownExtra();
        $parsedown->setFootnotePrefix( '123' );

        $expectedOutput = <<< EOF
<p>first <sup id="fnref123:1:1"><a href="#fn:123:1" class="footnote-ref">1</a></sup> second <sup id="fnref123:1:2"><a href="#fn:123:2" class="footnote-ref">2</a></sup>.</p>
<p>first <sup id="fnref123:1:a"><a href="#fn:123:a" class="footnote-ref">3</a></sup> second <sup id="fnref123:1:b"><a href="#fn:123:b" class="footnote-ref">4</a></sup>.</p>
<p>second time <sup id="fnref123:2:1"><a href="#fn:123:1" class="footnote-ref">1</a></sup></p>
<div class="footnotes">
<hr />
<ol>
<li id="fn:123:1">
<p>one&#160;<a href="#fnref123:1:1" rev="footnote" class="footnote-backref">&#8617;</a> <a href="#fnref123:2:1" rev="footnote" class="footnote-backref">&#8617;</a></p>
</li>
<li id="fn:123:2">
<p>two&#160;<a href="#fnref123:1:2" rev="footnote" class="footnote-backref">&#8617;</a></p>
</li>
<li id="fn:123:a">
<p>one&#160;<a href="#fnref123:1:a" rev="footnote" class="footnote-backref">&#8617;</a></p>
</li>
<li id="fn:123:b">
<p>two&#160;<a href="#fnref123:1:b" rev="footnote" class="footnote-backref">&#8617;</a></p>
</li>
</ol>
</div>
EOF;

        $this->assertEquals( $expectedOutput, $parsedown->text( $markdownInput ) );
    }

    public function testOneLineMultipleHtmlMarkup()
    {
        $input = '<div>First paragraph.</div><p>Second paragraph.</p>';
        $expectedMarkup = '<div>First paragraph.</div>
<p>Second paragraph.</p>';
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testOneMultilineOneHtmlMarkup()
    {
        $input = '<div>Third
paragraph

multiline
</div>';
        $expectedMarkup = '<div>Third
paragraph
<p>multiline</p>
</div>';
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testOneMultilineMultipleHtmlMarkup()
    {
        $input = '<div>First paragraph.</div><p>Second paragraph.</p><div>Third
paragraph

multiline
</div>';
        $expectedMarkup = '<div>First paragraph.</div>
<p>Second paragraph.</p>
<div>Third
paragraph
<p>multiline</p>
</div>';
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testOneHtmlMarkupInline()
    {
        $input = <<<EOF
<div>1</div>
The p tag (and contents), along with this line were eaten.
EOF;
        $expectedMarkup = <<<EOF
<div>1</div>
The p tag (and contents), along with this line were eaten.
EOF;
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testMultipleHtmlMarkupInline()
    {
        // @url https://github.com/erusev/parsedown-extra/issues/44#issuecomment-80815953
        $input = <<<EOF
<div>1</div><p>2</p>
The p tag (and contents), along with this line are eaten.
EOF;
        $expectedMarkup = <<<EOF
<div>1</div>
<p>2</p>
The p tag (and contents), along with this line are eaten.
EOF;
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testInlineIframe()
    {
        // @url https://github.com/erusev/parsedown-extra/issues/44#issuecomment-106090346
        $input = '<iframe />';
        $expectedMarkup = '<iframe></iframe>';
        $actualMarkup = (new ParsedownExtra())->text($expectedMarkup);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testStripping()
    {
        // @url https://github.com/erusev/parsedown-extra/issues/44#issuecomment-159655861
        $input = '<p><strong>Contact Method:</strong> email</p><p>Test</p><p><em>Some italic text.</em></p>';
        $expectedMarkup = '<p><strong>Contact Method:</strong> email</p>
<p>Test</p>
<p><em>Some italic text.</em></p>';
        $actualMarkup = (new ParsedownExtra())->text($input);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }
}
