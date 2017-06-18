<?php

#
#
# Parsedown Extra
# https://github.com/erusev/parsedown-extra
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class ParsedownExtra extends Parsedown
{
    # ~

    const version = '0.7.0';

    # ~

    function __construct()
    {
        if (parent::version < '1.5.0')
        {
            throw new Exception('ParsedownExtra requires a later version of Parsedown');
        }


        $this->BlockTypes['$'][] = 'Variable';
        $this->InlineTypes['$'][] = 'GetVariable';
        $this->inlineMarkerList .= '$';
        $this->BlockTypes['-'][] = 'Section';
        $this->BlockTypes['='][] = 'Figure';
        $this->BlockTypes[':'] []= 'DefinitionList';
        $this->BlockTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->BlockTypes['['], 'Footnote');

        # identify footnote markers before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');
    }

    #
    # ~

    function text($text)
    {
        $this->footnoteCount = 0;
        $markup = parent::text($text);

        # merge consecutive dl elements

        $markup = preg_replace('/<\/dl>\s+<dl>\s+/', '', $markup);

        # add footnotes

        if (isset($this->DefinitionData['Footnote']))
        {
            $Element = $this->buildFootnoteElement();

            $markup .= "\n" . $this->element($Element);
        }

        return $markup;
    }

    #
    # Setters
    #

    function setFootnotePrefix( $footnotePrefix )
    {
        $this->footnotePrefix = $footnotePrefix;
        return $this;
    }

    function clearFootnotePrefix()
    {
        return $this->setFootnotePrefix( '' );
    }

    protected $footnotePrefix = '';

    #
    # Blocks
    #

    #
    # Abbreviation

    protected function blockAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches))
        {
            $this->DefinitionData['Abbreviation'][$matches[1]] = $matches[2];

            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    #
    # Footnote

    protected function blockFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.*)$/', $Line['text'], $matches))
        {
            $Block = array(
                'label' => $matches[1],
                'text' => $matches[2],
                'hidden' => true,
            );

            return $Block;
        }
    }

    protected function blockFootnoteContinue($Line, $Block)
    {
        if ($Line['text'][0] === '[' and preg_match('/^\[\^(.+?)\]:/', $Line['text']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            if ($Line['indent'] >= 4)
            {
                $Block['text'] .= "\n\n" . $Line['text'];

                return $Block;
            }
        }
        else
        {
            $Block['text'] .= "\n" . $Line['text'];

            return $Block;
        }
    }

    protected function blockFootnoteComplete($Block)
    {
        $this->DefinitionData['Footnote'][$Block['label']] = array(
            'text' => $Block['text'],
            'count' => null,
            'number' => null,
        );

        return $Block;
    }

    #
    # Definition List

    protected function blockDefinitionList($Line, $Block)
    {
        if ( ! isset($Block) or isset($Block['type']))
        {
            return;
        }

        $Element = array(
            'name' => 'dl',
            'handler' => 'elements',
            'text' => array(),
        );

        $terms = explode("\n", $Block['element']['text']);

        foreach ($terms as $term)
        {
            $Element['text'] []= array(
                'name' => 'dt',
                'handler' => 'line',
                'text' => $term,
            );
        }

        $Block['element'] = $Element;

        $Block = $this->addDdElement($Line, $Block);

        return $Block;
    }

    protected function blockDefinitionListContinue($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block = $this->addDdElement($Line, $Block);

            return $Block;
        }
        else
        {
            if (isset($Block['interrupted']) and $Line['indent'] === 0)
            {
                return;
            }

            if (isset($Block['interrupted']))
            {
                $Block['dd']['handler'] = 'text';
                $Block['dd']['text'] .= "\n\n";

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], min($Line['indent'], 4));

            $Block['dd']['text'] .= "\n" . $text;

            return $Block;
        }
    }

    #
    # Header

    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);

        // May return null, so abort if it does.
        if (!$Block) {
            return;
        }

        if (preg_match('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Markup

    protected function blockMarkupComplete($Block)
    {
        if ( ! isset($Block['void']))
        {
            $Block['markup'] = $this->processTags($Block['markup']);
        }

        return $Block;
    }

    #
    # Setext

    protected function blockSetextHeader($Line, array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);

        if (preg_match('/[ ]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line)
    {
        if (preg_match('/^(['.$Line['text'][0].']{3,}[ ]*([\w-]+)?)([ ]+\{('.$this->regexAttribute.'+)\})?[ ]*$/', $Line['text'], $matches))
        {
            $new_line = $matches[1];

            $Line["text"] = $new_line;
            $Block = parent::blockFencedCode($Line);

            if ( $Block && isset($matches[4]) )
            {
                $attributeString = $matches[4];

                $newattrs = $this->parseAttributeData($attributeString);
                foreach ( $newattrs as $k => $v )
                    $Block['element']['attributes'][$k] = $v;
            }

            return $Block;
        }
    }

    #
    # Inline Elements
    #

    #
    # Footnote Marker

    protected function inlineFootnoteMarker($Excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $Excerpt['text'], $matches))
        {
            $name = $matches[1];

            if ( ! isset($this->DefinitionData['Footnote'][$name]))
            {
                return;
            }

            $this->DefinitionData['Footnote'][$name]['count'] ++;

            if ( ! isset($this->DefinitionData['Footnote'][$name]['number']))
            {
                $this->DefinitionData['Footnote'][$name]['number'] = ++ $this->footnoteCount; # » &
            }

            $fnPrefix = !empty( $this->footnotePrefix ) ? $this->footnotePrefix . ':' : '';

            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$fnPrefix.$this->DefinitionData['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$fnPrefix.$name, 'class' => 'footnote-ref'),
                    'text' => $this->DefinitionData['Footnote'][$name]['number'],
                ),
            );

            return array(
                'extent' => strlen($matches[0]),
                'element' => $Element,
            );
        }
    }

    private $footnoteCount = 0;

    #
    # image

    protected function inlineImage($Excerpt)
    {
        $Inline = parent::inlineImage($Excerpt);

        $remainder = substr($Excerpt['text'], $Inline['extent']);

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches))
        {
            $Inline['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Inline['extent'] += strlen($matches[0]);
        }

        return $Inline;
    }

    #
    # Link
    #
    # This copy of the parent::inlineLink() is required to manage attributes
    # defined via reference. It is called only by self::inlineLink().
    # Only the end of the method is modified to copy all defined attributes, and
    # not only `href` and `title`.
    # TODO To avoid this quasi-copy, the parent inlineLink should be modified to
    # manage attributes differently or in a separate and overridable method.

    protected function _inlineLink($Excerpt)
    {
        $Element = array(
            'name' => 'a',
            'handler' => 'line',
            'text' => null,
            'attributes' => array(
                'href' => null,
                'title' => null,
            ),
        );

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches))
        {
            $Element['text'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        }
        else
        {
            return;
        }

        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*"|\'[^\']*\'))?\s*[)]/', $remainder, $matches))
        {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2]))
            {
                $Element['attributes']['title'] = substr($matches[2], 1, - 1);
            }

            $extent += strlen($matches[0]);
        }
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['text'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            }
            else
            {
                $definition = strtolower($Element['text']);
            }

            if ( ! isset($this->DefinitionData['Reference'][$definition]))
            {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            // Copy all attributes defined in the reference, except "url".
            $Definition['href'] = $Definition['url'];
            unset($Definition['url']);
            $Element['attributes'] = $Definition + $Element['attributes'];
        }

        $Element['attributes']['href'] = str_replace(array('&', '<'), array('&amp;', '&lt;'), $Element['attributes']['href']);

        return array(
            'extent' => $extent,
            'element' => $Element,
        );
    }

    protected function inlineLink($Excerpt)
    {
        $Link = $this->_inlineLink($Excerpt);

        // may return null, so abort if it does
        if (!$Link) return $Link;

        $remainder = substr($Excerpt['text'], $Link['extent']);

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches))
        {
            $Link['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Link['extent'] += strlen($matches[0]);
        }

        return $Link;
    }

    #
    # ~
    #

    protected function unmarkedText($text)
    {
        $text = parent::unmarkedText($text);

        if (isset($this->DefinitionData['Abbreviation']))
        {
            foreach ($this->DefinitionData['Abbreviation'] as $abbreviation => $meaning)
            {
                $pattern = '/\b'.preg_quote($abbreviation, '/').'\b/u';

                $text = preg_replace($pattern, '<abbr title="'.$meaning.'">'.$abbreviation.'</abbr>', $text);
            }
        }

        return $text;
    }

    #
    # Util Methods
    #

    protected function addDdElement(array $Line, array $Block)
    {
        $text = substr($Line['text'], 1);
        $text = trim($text);

        unset($Block['dd']);

        $Block['dd'] = array(
            'name' => 'dd',
            'handler' => 'line',
            'text' => $text,
        );

        if (isset($Block['interrupted']))
        {
            $Block['dd']['handler'] = 'text';

            unset($Block['interrupted']);
        }

        $Block['element']['text'] []= & $Block['dd'];

        return $Block;
    }

    protected function buildFootnoteElement()
    {
        $Element = array(
            'name' => 'div',
            'attributes' => array('class' => 'footnotes'),
            'handler' => 'elements',
            'text' => array(
                array(
                    'name' => 'hr',
                ),
                array(
                    'name' => 'ol',
                    'handler' => 'elements',
                    'text' => array(),
                ),
            ),
        );

        uasort($this->DefinitionData['Footnote'], 'self::sortFootnotes');

        foreach ($this->DefinitionData['Footnote'] as $definitionId => $DefinitionData)
        {
            if ( ! isset($DefinitionData['number']))
            {
                continue;
            }

            $text = $DefinitionData['text'];

            $text = parent::text($text);

            $numbers = range(1, $DefinitionData['count']);

            $backLinksMarkup = '';

            $fnPrefix = !empty( $this->footnotePrefix ) ? $this->footnotePrefix . ':' : '';

            foreach ($numbers as $number)
            {
                $backLinksMarkup .= ' <a href="#fnref'.$fnPrefix.$number.':'.$definitionId.'" rev="footnote" class="footnote-backref">&#8617;</a>';
            }

            $backLinksMarkup = substr($backLinksMarkup, 1);

            if (substr($text, - 4) === '</p>')
            {
                $backLinksMarkup = '&#160;'.$backLinksMarkup;

                $text = substr_replace($text, $backLinksMarkup.'</p>', - 4);
            }
            else
            {
                $text .= "\n".'<p>'.$backLinksMarkup.'</p>';
            }

            $Element['text'][1]['text'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$fnPrefix.$definitionId),
                'text' => "\n".$text."\n",
            );
        }

        return $Element;
    }

    #
    # Reference

    protected function blockReference($Line)
    {
        $regex = '/^\[(.+?)\]:[ ]*<?(\S+?)>?(?:[ ]+["\'(](.*)["\')])?[ ]*(?:{(?:' . $this->regexAttribute . '+)})?[ ]*$/';
        if (preg_match($regex, $Line['text'], $matches))
        {
            $id = strtolower($matches[1]);

            $Data = array(
                'url' => $matches[2],
                'title' => null,
            );

            if (isset($matches[3]))
            {
                $Data['title'] = $matches[3];
            }

            if (isset($matches[4]))
            {
                $Data += $this->parseAttributeData($matches[4]);
            }

            $this->DefinitionData['Reference'][$id] = $Data;

            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    /**
     * Table
     * @param type $Line
     * @param array $Block
     * @return string
     */
    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
        {
            return;
        }

        if (strpos($Block['element']['text'], '|') !== false and chop($Line['text'], ' -:|') === '')
        {
            $alignments = array();

            $divider = $Line['text'];

            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell)
            {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '')
                {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':')
                {
                    $alignment = 'left';
                }

                if (substr($dividerCell, - 1) === ':')
                {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments []= $alignment;
            }

            # ~

            //Get all header lines
            $hlines = explode("\n",$Block['element']['text']);

            # ~

            //Start Block type
            $Block = array(
                'alignments' => $alignments,
                'identified' => true,
                'element' => array(
                    'name' => 'table',
                    'handler' => 'elements',
                ),
            );

            $Block['element']['text'] []= array(
                'name' => 'caption',
                'handler' => 'elements',
                'text' => array()
            );

            $Block['element']['text'] []= array(
                'name' => 'thead',
                'handler' => 'elements',
                'text' => array(),
            );

            $Block['element']['text'] []= array(
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => array(),
            );

            // Treating multiple header lines.
            foreach($hlines as $hline) {
                $HeaderElements = array();

                //$header = $Block['element']['text'];
                $header = $hline;
                $header = trim($header);
                $header = ltrim($header, '|');

                $headerCells = explode('|', $header);
                $lastHeaderCell = count($headerCells) - 1;
                $colspan = 1;

                foreach ($headerCells as $index => $headerCell)
                {
                    if($headerCell=='') {
                        $colspan++;
                        if ($index>0 && $index !== $lastHeaderCell) {
                            $prev = $index -1;
                            while($prev > -1) {
                                if(isset($HeaderElements[$prev])) {
                                    if (isset($HeaderElements[$prev]['attributes']['colspan'])) {
                                        $HeaderElements[$prev]['attributes']['colspan'] += $colspan;
                                    } else {
                                        $HeaderElements[$prev]['attributes']['colspan'] = $colspan;
                                    }
                                    break;
                                }
                                $prev--;
                            }
                        }
                        continue;
                    }

                    $headerCell = trim($headerCell);

                    $HeaderElement = array(
                        'name' => 'th',
                        'text' => $headerCell,
                        'handler' => 'line',
                    );

                    if (isset($alignments[$index]))
                    {
                        $HeaderElement['attributes'] = array(
                            'style' => 'text-align: '.$alignments[$index].';',
                        );
                    }

                    if($colspan > 1) {
                        $Element['attributes']['colspan'] = $colspan;
                        $colspan = 1;
                    }

                    $HeaderElements[$index]= $HeaderElement;
                }

                $Block['element']['text'][1]['text'] []= array(
                    'name' => 'tr',
                    'handler' => 'elements',
                    'text' => $HeaderElements,
                );
            }
            // Remove caption if empty.
            if ($Block['element']['text'][0]['text'] === array()) {
                unset($Block['element']['text'][0]);
            }
            return $Block;
        }
    }

    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);
            $lastHeaderCell = count($matches) - 1;
            $colspan=1;

            foreach ($matches[0] as $index => $cell)
            {
                if($cell=='') {
                    $colspan++;
                    if ($index>0 && $index !== $lastHeaderCell) {
                        $prev = $index -1;
                        while($prev > -1) {
                            if(isset($Elements[$prev])) {
                                if(isset($Elements[$prev]['attributes']['colspan'])) {
                                    $Elements[$prev]['attributes']['colspan'] += $colspan;
                                } else {
                                    $Elements[$prev]['attributes']['colspan'] = $colspan;
                                }
                                break;
                            }
                            $prev--;
                        }
                    }
                    continue;
                }
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => trim($cell),
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'style' => 'text-align: '.$Block['alignments'][$index].';',
                    );
                }
                if($colspan > 1) {
                    $Element['attributes']['colspan'] = $colspan;
                    $colspan = 1;
                }

                $Elements [$index]= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $Elements,
            );

            $Block['element']['text'][2]['text'] []= $Element;

            return $Block;
        } elseif (preg_match('/^\[(.*.)\]$/', $Line['text'],$parts)) { //Get Table Caption
            $Block['element']['text'][0]['text'] []= array(
                'name' => 'caption',
                'handler' => 'line',
                'text' => $parts[1],
            );

          return $Block;
        }
    }

    protected $variables=array();
    protected function inlineGetVariable($Excerpt)
    {
        if (preg_match('/^\$([a-z_]+)/', $Excerpt['text'], $m) && isset($this->variables[$m[1]])) {
            return array(
                'extent' => strlen($m[0]),
                'markup' => $this->variables[$m[1]],
            );
        }
    }

    protected function blockVariable($Line)
    {
        if (preg_match('/^\$([a-z_]+)=\{(.*)/', $Line['text'], $m))
        {
            $Block = array(
                'id' => $m[1],
                'markup' => $m[2],
            );
            return $Block;
        }
    }


    protected function blockVariableContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;
        else if (isset($Block['closed'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (substr($Line['text'], 0, 1)=='}') {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['markup'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockVariableComplete($Block)
    {
        if(isset($Block['id'])) {
            $this->variables[$Block['id']] = $this->text($Block['markup']);
            $Block['markup']='';
        }
        return $Block;
    }

    #
    # Quote

    protected function blockQuote($Line)
    {
        if (preg_match('/^>[ ]?(\{'.$this->regexAttribute.'+\})? ?(.*)?/', $Line['text'], $matches))
        {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $matches[3],
                ),
            );

            if (isset($matches[2]) && $matches[2]) {
                $Block['element']['name'] = 'div';
                $Block['element']['attributes']=$this->parseAttributeData($matches[2]);
            }

            return $Block;
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if ($Line['text'][0] === '>' and preg_match('/^>[ ]?(.*)/', $Line['text'], $matches))
        {
            if (isset($matches[1][0]) && ($matches[1][0]=='{' || $matches[1][0]=='(')) {
                return;
            }
            if (isset($Block['interrupted']))
            {
                $Block['element']['text'] []= '';

                unset($Block['interrupted']);
            }

            $Block['element']['text'] []= $matches[1];

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Block['element']['text'] []= $Line['text'];

            return $Block;
        }
    }

    protected function blockSection($Line, $Block)
    {
        if (preg_match('/^'.$Line['text'][0].'{3,} *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'section',
                    'handler'=>'text',
                    'text' => '',
                ),
            );

            if(isset($m[1])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[1],1,strlen($m[1])-2));
            }
            unset($m);

            return $Block;
        }
    }

    protected function blockSectionContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,} *$/', $Line['text'])) {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockSectionComplete($Block)
    {
        $Block['complete'] = true;
        return $Block;
    }

    /**
     * Implement: https://github.com/egil/php-markdown-extra-extended
     */
    protected function blockFigure($Line, $Block)
    {
        if (preg_match('/^'.$Line['text'][0].'{3,} *(\[.*\])? *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'figure',
                    'handler'=>'line',
                    'text' => '',
                ),
            );

            if (isset($m[1])) {
                $Block['element']['caption']=substr($m[1],1,strlen($m[1])-2);
            }

            if(isset($m[2])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[2],1,strlen($m[2])-2));
            }
            unset($m);

            return $Block;
        }
    }

    protected function blockFigureContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;

        if (isset($Block['interrupted'])) {
            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,} *(\[.*\])? *(\{'.$this->regexAttribute.'+\})? *$/', $Line['text'], $m)) {
            if (isset($m[1])) {
                $Block['element']['caption']=substr($m[1],1,strlen($m[1])-2);
            }
            if(isset($m[2])) {
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[2],1,strlen($m[2])-2));
            }
            unset($m);
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockFigureComplete($Block)
    {
        if(isset($Block['element']['caption'])) {
            $line = $this->line($Block['element']['caption']);
            $Block['element']['handler']='multiple';
            $Block['element']['text'] = array(
                $Block['element']['text'],
                array(
                    'name'=>'figcaption',
                    'text'=>$line,
                ),
            );
            $Block['element']['attributes']['title']=strip_tags($line);
            unset($Block['element']['caption']);
        }
        return $Block;
    }

    protected function multiple($a)
    {
        if(isset($a['element'])) return $this->multiple(array($a));
        $s = '';
        foreach($a as $i=>$Block) {
            if(is_string($Block)) {
                if(strpos($Block, "\n")!==false) $s.= $this->text($Block);
                else $s.= $this->line($Block);
            } else if(isset($Block['handler'])) {
                $h = $Block['handler'];
                $s .= $this->$h($Block);
            } else if(isset($Block['name'])) {
                $s .= $this->element($Block);
            }
        }
        return $s;
    }

    # ~

    protected function parseAttributeData($attributeString)
    {
        $Data = array();

        preg_match_all('/[^\s"]+(?:"[^"]*")?/', $attributeString, $attributes);

        if ( count($attributes) ) {
            $attributes = $attributes[0];
        } else {
            return $Data;
        }

        foreach ($attributes as $attribute)
        {
            // If it's an id...
            if ($attribute[0] === '#')
            {
                $Data['id'] = substr($attribute, 1);
            }

            // Else if it's a class
            elseif ($attribute[0] === ".")
            {
                $classes []= substr($attribute, 1);
            }

            // Else it must be an attribute
            elseif ( strpos($attribute, '=') )
            {
                preg_match('#([\w-]+)="?([\D\w-]+)"?#', $attribute, $match);

                if ( !empty($match) ) {
                    $Data[$match[1]] = $match[2];
                }
            }
        }

        if (isset($classes))
        {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    # ~

    protected function processText($document, $element){
        $nodeMarkup = $document->saveHTML($element);

        $text = '';

        if ($element instanceof DOMElement and !in_array($element->nodeName, $this->textLevelElements) and !in_array($element->nodeName, $this->voidElements)){
            if( $element->hasChildNodes() ){
                $text = $this->processTags($nodeMarkup);
            }else{
                $text = $nodeMarkup;
            }
        }else{
            $text = $nodeMarkup;
        }
        return $text;
    }

    # ~

    protected function processTags($elementMarkup) # recursive
    {
        # http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        $DOMDocument = new DOMDocument;

        # http://stackoverflow.com/q/11309194/200145
        if (extension_loaded('mbstring')) {
            $elementMarkup = mb_convert_encoding($elementMarkup, 'HTML-ENTITIES', 'UTF-8');
        }

        # http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $markup = '';

        if( $DOMDocument->hasChildNodes() ){
            foreach ($DOMDocument->childNodes as $childElement) {
                 $markup .= $this->processTag( $DOMDocument, $childElement);
            }
        }

        libxml_clear_errors();

        return $markup;
    }

    # ~

    protected function processTag($document, $element) # recursive
    {

        $elementText = '';

        if ($element instanceof DOMElement and $element->getAttribute('markdown') === '1')
        {
            if( $element->hasChildNodes() ){
                foreach ($element->childNodes as $Node){
                    $elementText .= $document->saveHTML($Node);
                }
            }else{
                $elementText = $document->saveHTML($element);
            }

            $element->removeAttribute('markdown');
            $elementText = "\n".$this->text($elementText)."\n";
        }
        else
        {
            if( $element->hasChildNodes() ){
                foreach ($element->childNodes as $Node){
                    $elementText .= $this->processText($document, $Node);
                }
            }else{
                $elementText =  $this->processText($document, $element);
            }

        }

        # because we don't want for markup to get encoded
        $element->nodeValue = 'placeholder\x1A';

        $markup = $document->saveHTML($element);
        $markup = str_replace('placeholder\x1A', $elementText, $markup);

        return $markup;
    }

    # ~

    protected function sortFootnotes($A, $B) # callback
    {
        return $A['number'] - $B['number'];
    }

    #
    # Fields
    #

    protected $regexAttribute = '((?:[.#:;=\-"\'\s\w]+))[^}]?';
}
