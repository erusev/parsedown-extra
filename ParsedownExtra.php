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
    #
    # ~

    function __construct()
    {
        $this->BlockTypes[':'] []= 'DefinitionList';

        $this->DefinitionTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->DefinitionTypes['['], 'Footnote');

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

        if (isset($this->Definitions['Footnote']))
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
    # Atx

    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);

        if (preg_match('/[ #]*'.$this->attributesPattern.'[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributes($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Definition List

    protected function blockDefinitionList($Line, $Block)
    {
        if (isset($Block['type']))
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

        $Element['text'] []= array(
            'name' => 'dd',
            'handler' => 'line',
            'text' => ltrim($Line['text'], ' :'),
        );

        $Block['element'] = $Element;

        return $Block;
    }

    protected function blockDefinitionListContinue($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block['element']['text'] []= array(
                'name' => 'dd',
                'handler' => 'line',
                'text' => ltrim($Line['text'], ' :'),
            );

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Element = array_pop($Block['element']['text']);

            $Element['text'] .= "\n" . chop($Line['text']);

            $Block['element']['text'] []= $Element;

            return $Block;
        }
    }

    #
    # Setext

    protected function blockSetextHeader($Line, array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);

        if (preg_match('/[ ]*'.$this->attributesPattern.'[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributes($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Markup

    protected function blockMarkupComplete($Block)
    {
        if ( ! isset($Block['element']['attributes']['markdown']) or $Block['element']['attributes']['markdown'] !== '1')
        {
            return $Block;
        }

        unset($Block['element']['attributes']['markdown']);

        $Block['element']['text'] = "\n".$this->text($Block['element']['text'])."\n";

        return $Block;
    }

    #
    # Definitions
    #

    #
    # Abbreviation

    protected function definitionAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches))
        {
            $Abbreviation = array(
                'id' => $matches[1],
                'data' => $matches[2],
            );

            return $Abbreviation;
        }
    }

    #
    # Footnote

    protected function definitionFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.+)$/', $Line['text'], $matches))
        {
            $Footnote = array(
                'id' => $matches[1],
                'data' => array(
                    'text' => $matches[2],
                    'count' => null,
                    'number' => null,
                ),
            );

            return $Footnote;
        }
    }

    #
    # Inline Elements
    #

    #
    # Footnote Marker

    protected function inlineFootnoteMarker($excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $excerpt, $matches))
        {
            $name = $matches[1];

            if ( ! isset($this->Definitions['Footnote'][$name]))
            {
                return;
            }

            $this->Definitions['Footnote'][$name]['count'] ++;

            if ( ! isset($this->Definitions['Footnote'][$name]['number']))
            {
                $this->Definitions['Footnote'][$name]['number'] = ++ $this->footnoteCount; # » &
            }

            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$this->Definitions['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$name, 'class' => 'footnote-ref'),
                    'text' => $this->Definitions['Footnote'][$name]['number'],
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

    protected function inlineLink($excerpt)
    {
        $Span = parent::inlineLink($excerpt);

        $remainder = substr($excerpt, $Span['extent']);

        if (preg_match('/^[ ]*'.$this->attributesPattern.'/', $remainder, $matches))
        {
            $Span['element']['attributes'] += $this->parseAttributes($matches[1]);

            $Span['extent'] += strlen($matches[0]);
        }

        return $Span;
    }

    #
    # image

    protected function inlineImage($excerpt)
    {
        $Span = parent::inlineImage($excerpt);

        $remainder = substr($excerpt, $Span['extent']);

        if (preg_match('/^[ ]*'.$this->attributesPattern.'/', $remainder, $matches))
        {
            $Span['element']['attributes'] += $this->parseAttributes($matches[1]);

            $Span['extent'] += strlen($matches[0]);
        }

        return $Span;
    }

    #
    # ~

    protected function unmarkedText($text)
    {
        $text = parent::unmarkedText($text);

        if (isset($this->Definitions['Abbreviation']))
        {
            foreach ($this->Definitions['Abbreviation'] as $abbreviation => $phrase)
            {
                $text = str_replace($abbreviation, '<abbr title="'.$phrase.'">'.$abbreviation.'</abbr>', $text);
            }
        }

        return $text;
    }

    #
    # ~
    #

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

        uasort($this->Definitions['Footnote'], function($A, $B) {
            return $A['number'] - $B['number'];
        });

        foreach ($this->Definitions['Footnote'] as $name => $Data)
        {
            if ( ! isset($Data['number']))
            {
                continue;
            }

            $text = $Data['text'];
            $text = $this->line($text);

            $numbers = range(1, $Data['count']);

            foreach ($numbers as $number)
            {
                $text .= '&#160;<a href="#fnref'.$number.':'.$name.'" rev="footnote" class="footnote-backref">&#8617;</a>';
            }

            $Element['text'][1]['text'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$name),
                'handler' => 'elements',
                'text' => array(
                    array(
                        'name' => 'p',
                        'text' => $text,
                    ),
                ),
            );
        }

        return $Element;
    }

    #
    # ~
    #

    protected function parseAttributes($attributeString)
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

    protected $attributesPattern = '{((?:[#.][-\w]+[ ]*)+)}';
}
