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
        if (version_compare(parent::version, '1.5.0') < 0)
        {
            throw new Exception('ParsedownExtra requires a later version of Parsedown');
        }

        parent::__construct();

        $this->BlockTypes[':'] []= 'DefinitionList';
        $this->BlockTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->BlockTypes['['], 'Footnote');

        # identify footnote markers before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');

        $this->prepareExtendedSupport();
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

    /**
     * Allow to support extended markdown extra (figure, section, table, variable).
     *
     * @param bool $extendedSupport
     * @return ParsedownExtra
     */
    public function setExtendedSupport($extendedSupport)
    {
        if ($extendedSupport !== $this->extendedSupport) {
            $this->extendedSupport = (bool) $extendedSupport;
            $this->prepareExtendedSupport();
        }
        return $this;
    }

    public function getExtendedSupport()
    {
        return $this->extendedSupport;
    }

    protected function prepareExtendedSupport()
    {
        if ($this->getExtendedSupport()) {
            $this->BlockTypes['$'][] = 'Variable';
            $this->InlineTypes['$'][] = 'GetVariable';
            self::$inlineMarkerList .= '$';
            $this->BlockTypes['-'][] = 'Section';
            $this->BlockTypes['='][] = 'Figure';
        } else {
            unset($this->BlockTypes['$']);
            unset($this->InlineTypes['$']);
            self::$inlineMarkerList = str_replace('$', '', self::$inlineMarkerList);
            $key = array_search('Section', $this->BlockTypes['-']);
            if ($key !== false) {
                unset($this->BlockTypes['-'][$key]);
            }
            $key = array_search('Figure', $this->BlockTypes['=']);
            if ($key !== false) {
                unset($this->BlockTypes['='][$key]);
            }
        }
    }

    protected $extendedSupport = true;

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
        if (self::substr($Line['text'], 0, 1) === '[' && preg_match('/^\[\^(.+?)\]:/', $Line['text']))
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
        if ( ! isset($Block) || isset($Block['type']))
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
        if (self::substr($Line['text'], 0, 1) === ':')
        {
            $Block = $this->addDdElement($Line, $Block);

            return $Block;
        }
        else
        {
            if (isset($Block['interrupted']) && $Line['indent'] === 0)
            {
                return;
            }

            if (isset($Block['interrupted']))
            {
                $Block['dd']['handler'] = 'text';
                $Block['dd']['text'] .= "\n\n";

                unset($Block['interrupted']);
            }

            $text = self::substr($Line['body'], min($Line['indent'], 4));

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

            $Block['element']['text'] = self::substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Markup

    protected function blockMarkupComplete($Block)
    {
        if (empty($Block['void']))
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

            $Block['element']['text'] = self::substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line)
    {
        $firstCharacter = self::substr($Line['text'], 0, 1);
        if (preg_match('/^([' . preg_quote($firstCharacter, '/') . ']{3,}[ ]*([\w-]+)?)([ ]+\{('.$this->regexAttribute.'+)\})?[ ]*$/', $Line['text'], $matches))
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
                'extent' => self::strlen($matches[0]),
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

        $remainder = self::substr($Excerpt['text'], $Inline['extent']);

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches))
        {
            $Inline['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Inline['extent'] += self::strlen($matches[0]);
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

            $extent += self::strlen($matches[0]);

            $remainder = self::substr($remainder, $extent);
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
                $Element['attributes']['title'] = self::substr($matches[2], 1, - 1);
            }

            $extent += self::strlen($matches[0]);
        }
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = self::strlen($matches[1]) ? $matches[1] : $Element['text'];
                $definition = self::strtolower($definition);
                $extent += self::strlen($matches[0]);
            }
            else
            {
                $definition = self::strtolower($Element['text']);
            }

            if ( ! isset($this->DefinitionData['Reference'][$definition]))
            {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            // Copy all attributes defined in the reference, except "url".
            // "href" should remains before the title for strict compatibility
            // with CommonMarkTest, so it is prepended.
            $Definition = array('href' => $Definition['url']) + $Definition;
            unset($Definition['url']);
            $Element['attributes'] = $Definition + $Element['attributes'];
        }

        $Element['attributes']['href'] = self::str_replace(array('&', '<'), array('&amp;', '&lt;'), $Element['attributes']['href']);

        return array(
            'extent' => $extent,
            'element' => $Element,
        );
    }

    protected function inlineLink($Excerpt)
    {
        $Link = $this->_inlineLink($Excerpt);

        // may return null, so abort if it does
        if (!$Link) {
            return $Link;
        }

        $remainder = self::substr($Excerpt['text'], $Link['extent']);

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches))
        {
            $Link['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Link['extent'] += self::strlen($matches[0]);
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
        $text = self::substr($Line['text'], 1);
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

            $backLinksMarkup = self::substr($backLinksMarkup, 1);

            if (self::substr($text, - 4) === '</p>')
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
            $id = self::strtolower($matches[1]);

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
        if ( ! isset($Block) || isset($Block['type']) || isset($Block['interrupted']))
        {
            return;
        }

        if (self::strpos($Block['element']['text'], '|') !== false && chop($Line['text'], ' -:|') === '')
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

                if (self::substr($dividerCell, 0, 1) === ':')
                {
                    $alignment = 'left';
                }

                if (self::substr($dividerCell, - 1) === ':')
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

        if (self::strpos($Line['text'], '|') !== false)
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
        if (preg_match('/^\$([a-z_]+)/', $Excerpt['text'], $matches) && isset($this->variables[$matches[1]])) {
            return array(
                'extent' => self::strlen($matches[0]),
                'markup' => $this->variables[$matches[1]],
            );
        }
    }

    protected function blockVariable($Line)
    {
        if (preg_match('/^\$([a-z_]+)=\{(.*)/', $Line['text'], $matches))
        {
            $Block = array(
                'id' => $matches[1],
                'markup' => $matches[2],
            );
            return $Block;
        }
    }


    protected function blockVariableContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;
        else if (isset($Block['closed'])) return;

        if (isset($Block['interrupted'])) {
            $Block['element']['text'] .= "\n";
            unset($Block['interrupted']);
        }

        if (self::substr($Line['text'], 0, 1) === '}') {
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
        $Block['complete'] = true;
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
        if (self::substr($Line['text'], 0, 1) === '>' && preg_match('/^>[ ]?(.*)/', $Line['text'], $matches))
        {
            $firstCharacter = self::substr($matches[1], 0, 1);
            if ($firstCharacter === '{' || $firstCharacter === '(') {
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
        $firstCharacter = self::substr($Line['text'], 0, 1);
        if (preg_match('/^' . preg_quote($firstCharacter, '/') . '{3,} *(\{' . $this->regexAttribute . '+\})? *$/', $Line['text'], $matches)) {
            $Block = array(
                'char' => $firstCharacter,
                'element' => array(
                    'name' => 'section',
                    'handler'=>'text',
                    'text' => '',
                ),
            );

            if(isset($matches[1])) {
                $Block['element']['attributes'] = $this->parseAttributeData(self::substr($matches[1], 1, self::strlen($matches[1]) - 2));
            }

            return $Block;
        }
    }

    protected function blockSectionContinue($Line, $Block)
    {
        if (isset($Block['complete'])) return;

        if (isset($Block['interrupted'])) {
            $Block['element']['text'] .= "\n";
            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,} *$/', $Line['text'])) {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n" . $Line['body'];

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
        $firstCharacter = self::substr($Line['text'], 0, 1);
        if (preg_match('/^' . preg_quote($firstCharacter, '/') . '{3,} *(?:\[(.*)\])? *(?:\{(' . $this->regexAttribute . '+)\})? *$/', $Line['text'], $matches)) {
            $Block = array(
                'char' => $firstCharacter,
                'element' => array(
                    'name' => 'figure',
                    'handler'=>'line',
                    'text' => '',
                ),
            );

            if (isset($matches[1])) {
                $Block['element']['caption'] = $matches[1];
                $Block['caption']['position'] = 'before';
            }

            if (isset($matches[2])) {
                $Block['element']['attributes'] = $this->parseAttributeData($matches[2]);
            }

            return $Block;
        }
    }

    protected function blockFigureContinue($Line, $Block)
    {
        if (isset($Block['complete'])) {
            return;
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['text'] .= "\n";
            unset($Block['interrupted']);
        }

        if (preg_match('/^' . preg_quote($Block['char'], '/') . '{3,} *(?:\[(.*)\])? *(?:\{(' . $this->regexAttribute . '+)\})? *$/', $Line['text'], $matches)) {
            if (isset($matches[1])) {
                $Block['element']['caption'] = $matches[1];
                $Block['caption']['position'] = 'after';
            }
            if (isset($matches[2])) {
                $Block['element']['attributes'] = $this->parseAttributeData($matches[2]);
            }
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockFigureComplete($Block)
    {
        if(isset($Block['element']['caption'])) {
            $line = $this->line($Block['element']['caption']);
            $Block['element']['handler']='multiple';
            if ($Block['caption']['position'] == 'before') {
                $Block['element']['text'] = array(
                    array(
                        'name'=>'figcaption',
                        'text'=>$line,
                    ),
                    $Block['element']['text'],
                );
            } else {
                $Block['element']['text'] = array(
                    $Block['element']['text'],
                    array(
                        'name'=>'figcaption',
                        'text'=>$line,
                    ),
                );
            }
            $Block['element']['attributes']['title'] = strip_tags($line);
            unset($Block['element']['caption']);
            unset($Block['caption']['position']);
        } else {
            $Block['element']['handler']='multiple';
            $Block['element']['text'] = array($Block['element']['text']);
        }

        $Block['complete'] = true;
        return $Block;
    }

    protected function multiple($Blocks)
    {
        if (isset($Blocks['element'])) {
            $Blocks = array($Blocks);
        }
        $output = '';
        foreach ($Blocks as $Block) {
            if (is_string($Block)) {
                $pos = self::strpos($Block, "\n");
                if ($pos !== false) {
                    $output .= $this->text($Block);
                } else {
                    $output .= $this->line($Block);
                }
            } elseif (isset($Block['handler'])) {
                $handler = $Block['handler'];
                $output .= $this->$handler($Block);
            } elseif (isset($Block['name'])) {
                $output .= $this->element($Block);
            }
        }
        return $output;
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
            $firstCharacter = self::substr($attribute, 0, 1);
            // If it's an id...
            if ($firstCharacter === '#')
            {
                $Data['id'] = self::substr($attribute, 1);
            }

            // Else if it's a class
            elseif ($firstCharacter === ".")
            {
                $classes [] = self::substr($attribute, 1);
            }

            // Else it must be an attribute
            elseif (self::strpos($attribute, '='))
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

    #
    # Process block of markups with or without the attribute markdown="1".
    #
    # Note: This is a single recursive method, so it avoids to reach the maximum
    # function nesting level (256 by default) too quickly when big pages are
    # processed.
    #
    # Recursive method.
    #

    protected function processTags($elementMarkup) # recursive
    {
        $markup = '';

        if (extension_loaded('mbstring')) {
            # http://stackoverflow.com/q/11309194/200145
            $elementMarkup = mb_convert_encoding($elementMarkup, 'HTML-ENTITIES', 'UTF-8');
        }

        # http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        // The process is complex, because the parser works by line, so tags may
        // be incomplete.
        $DOMDocument = new DOMDocument('1.0', 'UTF-8');

        # http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_COMPACT);
        if (!$DOMDocument->hasChildNodes()) {
            $DOMDocument = null;
        }

        if ($DOMDocument) {
            // Process each tag, node or text.
            foreach ($DOMDocument->childNodes as $element) {
                $elementText = '';
                $elementTexts = array();

                if ($element instanceof DOMElement) {
                    // Process markdown if specified.
                    if ($element->getAttribute('markdown') === '1') {
                        $element->removeAttribute('markdown');
                        if ($element->hasChildNodes()) {
                            foreach ($element->childNodes as $node) {
                                $elementText .= $DOMDocument->saveHTML($node);
                            }
                        } else {
                            $elementText = $DOMDocument->saveHTML($element);
                        }

                        # The process may be recursive (and below).
                        $elementText = "\n" . $this->text($elementText) . "\n";
                    }
                    // Don't process markdown, but some children may be processed.
                    elseif ($element->hasChildNodes()) {
                        $elementTexts = $element->childNodes;
                    }
                    // No children, so save the element directly.
                    else {
                        $elementText = $DOMDocument->saveHTML($element);
                    }
                }
                // Not a markup element, so process it as text.
                else {
                    $elementTexts = array($element);
                }

                foreach ($elementTexts as $node) {
                    $nodeMarkup = $DOMDocument->saveHTML($node);
                    # The process may be recursive (and above).
                    if ($node instanceof DOMElement
                        && !in_array($node->nodeName, $this->textLevelElements)
                        && !in_array($node->nodeName, $this->voidElements)
                        && $node->hasChildNodes()
                        # These quick checks avoid most of the recursive calls.
                        && trim($nodeMarkup)
                        && self::strpos($nodeMarkup, '<') !== false
                    ) {
                        $elementText .= $this->processTags($nodeMarkup);
                    } else {
                        $elementText .= $nodeMarkup;
                    }
                }

                # Because we don't want for markup to get encoded.
                $element->nodeValue = 'placeholder\x1A';

                $markupElement = $DOMDocument->saveHTML($element);
                $markupElement = self::str_replace('placeholder\x1A', $elementText, $markupElement);

                $markup .= $markupElement;
            }
        }

        libxml_clear_errors();

        return $markup;
    }

    # ~

    protected function sortFootnotes($A, $B) # callback
    {
        return $A['number'] - $B['number'];
    }

    #
    # Unicode compatibiliy layer.
    #

    /**
     * A compatibility layer to replace a string inside a unicode string.
     *
     * Note: this is not a full replacement of the function.
     *
     * @param string|array $search
     * @param string|array $replace
     * @param string|array $subject
     * @param int $count
     * @return string|array
     */
    static protected function str_replace($search, $replace, $subject, &$count = null)
    {
        if (extension_loaded('mbstring') || extension_loaded('iconv')) {
            $pattern = is_array($search)
                ? array_map(function ($v) { return '/' . preg_quote($v, '/') . '/'; }, $search)
                : '/' . preg_quote($search, '/') . '/';
            return preg_replace($pattern, $replace, $subject);
        } else {
            return str_replace($search, $replace, $subject);
        }
    }

    #
    # Fields
    #

    protected $regexAttribute = '((?:[.#:;=\-"\'\s\w]+))[^}]?';
}
