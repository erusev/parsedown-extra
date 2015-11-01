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

    public function test_footnote_prefix()
    {
        $markdownInput = file_get_contents( dirname( __FILE__ ) . '/data/footnote.md' );
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
}
