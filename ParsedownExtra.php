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

        # identify footnote markers before before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');
    }

    #
    # ~

    function text($text)
    {
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
            $Block['markup'] = $this->processTag($Block['markup']);
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

            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$this->DefinitionData['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$name, 'class' => 'footnote-ref'),
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
    # Link

    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);

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
                $pattern = '/\b'.preg_quote($abbreviation, '/').'\b/';

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

            foreach ($numbers as $number)
            {
                $backLinksMarkup .= ' <a href="#fnref'.$number.':'.$definitionId.'" rev="footnote" class="footnote-backref">&#8617;</a>';
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
                'attributes' => array('id' => 'fn:'.$definitionId),
                'text' => "\n".$text."\n",
            );
        }

        return $Element;
    }
    
    /** 
     * Table
     * @param type $Line
     * @param array $Block
     * @return string
     */
    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted'])) {
            return;
        }
        
        if (strpos($Block['element']['text'], '|') !== false and chop($Line['text'], ' -:|') === '') {
            //Get all header lines
            $hlines = explode("\n",$Block['element']['text']);
            
            
            //Get cell alignments
            $alignments = array();

            $divider = $Line['text'];
            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell)
            {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '') {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':') {
                    $alignment = 'left';
                }

                if (substr($dividerCell, - 1) === ':') {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments []= $alignment;
            }
            
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
            
            //Treating header lines
            foreach($hlines as $hline) {              
                $HeaderElements = array();

                //$header = $Block['element']['text'];           
                $header = $hline;
                $header = trim($header);
                $header = ltrim($header, '|');

                $headerCells = explode('|', $header);
                $colspan = 1;

                foreach ($headerCells as $index => $headerCell)
                {
                    if($headerCell=='') {
                        $colspan++;
                        if($index>0) {
                            $prev = $index -1;
                            while($prev > -1) {
                                if(isset($HeaderElements[$prev])) {
                                    if(!isset($HeaderElements[$prev]['attributes']['colspan'])) {                                  
                                      $HeaderElements[$prev]['attributes']['colspan']=$colspan;
                                    } else {                                  
                                      $HeaderElements[$prev]['attributes']['colspan'] += $colspan;
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

            $row = preg_replace('/^ *\\| *| *\\| *$/', '', $row);
            $cells = preg_split('/\\|/', $row);

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);
            $colspan=1;

            foreach ($cells as $index => $cell)
            {
                if($cell=='') {
                    $colspan++;
                    if($index>0) {
                        $prev = $index -1;
                        while($prev > -1) {
                            if(isset($Elements[$prev])) {
                                if(!isset($Elements[$prev]['attributes']['colspan'])) $Elements[$prev]['attributes']['colspan']=$colspan;
                                else $Elements[$prev]['attributes']['colspan'] += $colspan;
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

    protected function blockQuote($Line)
    {
        if (preg_match('/^> ?(\{'.$this->regexAttribute.'+\})? ?(.*)/', $Line['text'], $m))
        {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $m[2],
                ),
            );

            if(isset($m[1])) {
                $Block['element']['name'] = 'div';
                $Block['element']['attributes']=$this->parseAttributeData(substr($m[1],1,strlen($m[1])-2));
            }


            return $Block;
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if ($Line['body'][0] === '>' and preg_match('/^>[ ]?(.*)/', $Line['body'], $matches))
        {
            if($matches[1][0]=='{' || $matches[1][0]=='(') return;
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
            $Block['element']['text'] []= $Line['body'];

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

        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute)
        {
            if ($attribute[0] === '#')
            {
                $Data['id'] = substr($attribute, 1);
            }
            else # "."
            {
                $classes []= substr($attribute, 1);
            }
        }

        if (isset($classes))
        {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    # ~

    protected function processTag($elementMarkup) # recursive
    {
        # http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        $DOMDocument = new DOMDocument;

        # http://stackoverflow.com/q/11309194/200145
        $elementMarkup = mb_convert_encoding($elementMarkup, 'HTML-ENTITIES', 'UTF-8');

        # http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup);
        $DOMDocument->removeChild($DOMDocument->doctype);
        $DOMDocument->replaceChild($DOMDocument->firstChild->firstChild->firstChild, $DOMDocument->firstChild);

        $elementText = '';

        if ($DOMDocument->documentElement->getAttribute('markdown') === '1')
        {
            foreach ($DOMDocument->documentElement->childNodes as $Node)
            {
                $elementText .= $DOMDocument->saveHTML($Node);
            }

            $DOMDocument->documentElement->removeAttribute('markdown');

            $elementText = "\n".$this->text($elementText)."\n";
        }
        else
        {
            foreach ($DOMDocument->documentElement->childNodes as $Node)
            {
                $nodeMarkup = $DOMDocument->saveHTML($Node);

                if ($Node instanceof DOMElement and ! in_array($Node->nodeName, $this->textLevelElements))
                {
                    $elementText .= $this->processTag($nodeMarkup);
                }
                else
                {
                    $elementText .= $nodeMarkup;
                }
            }
        }

        # because we don't want for markup to get encoded
        $DOMDocument->documentElement->nodeValue = 'placeholder\x1A';

        $markup = $DOMDocument->saveHTML($DOMDocument->documentElement);
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

    protected $regexAttribute = '(?:[#.][-\w]+[ ]*)';
}
