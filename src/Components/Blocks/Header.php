<?php

namespace Erusev\ParsedownExtra\Components\Blocks;

use Erusev\Parsedown\AST\Handler;
use Erusev\Parsedown\Components\Block;
use Erusev\Parsedown\Components\Blocks\Header as BaseHeader;
use Erusev\Parsedown\Configurables\HeaderSlug;
use Erusev\Parsedown\Configurables\SlugRegister;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\Parsing\Context;
use Erusev\Parsedown\State;

final class Header implements Block
{
    private const ATT_REGEX = '(?:[#.][-\w]+[ ]*)';

    /** @var string */
    private $text;

    /** @var 1|2|3|4|5|6 */
    private $level;

    /** @var string|null */
    private $id;

    /** @var list<string> */
    private $classes;

    /**
     * @param string $text
     * @param 1|2|3|4|5|6 $level
     * @param string|null $id
     * @param list<string> $classes
     */
    private function __construct($text, $level, $id, $classes)
    {
        $this->text = $text;
        $this->level = $level;
        $this->id = $id;
        $this->classes = $classes;
    }

    /**
     * @param Context $Context
     * @param State $State
     * @param Block|null $Block
     * @return static|null
     */
    public static function build(
        Context $Context,
        State $State,
        Block $Block = null
    ) {
        $BaseHeader = BaseHeader::build($Context, $State, $Block);

        if (! isset($BaseHeader)) {
            return null;
        }

        $text = $BaseHeader->text();
        $level = $BaseHeader->level();
        $id = null;
        $classes = [];

        if (\preg_match('/[ #]*{('.self::ATT_REGEX.'+)}[ ]*$/', $text, $matches, \PREG_OFFSET_CAPTURE)) {
            /** @var array{0: array{string, int}, 1: array{string, int}} $matches */
            $attributeString = $matches[1][0];

            ['id' => $id, 'classes' => $classes] = self::parseAttributeData($attributeString);

            $text = \substr($text, 0, $matches[0][1]);
        }

        return new self($text, $level, $id, $classes);
    }

    /** @return string */
    public function text()
    {
        return $this->text;
    }

    /** @return 1|2|3|4|5|6 */
    public function level()
    {
        return $this->level;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    /** @return list<string> */
    public function classes()
    {
        return $this->classes;
    }

    /**
     * @return array{id:string|null,classes:list<string>}
     */
    private static function parseAttributeData(string $attributeString)
    {
        $data = [
            'id' => null,
            'classes' => [],
        ];

        $attributes = \preg_split('/[ ]+/', $attributeString, - 1, \PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute) {
            if ($attribute[0] === '#') {
                $data['id'] = \substr($attribute, 1);
            } else { # "."
                $data['classes'][]= \substr($attribute, 1);
            }
        }

        return $data;
    }

    /**
     * @return Handler<Element>
     */
    public function stateRenderable()
    {
        return new Handler(
            /** @return Element */
            function (State $State) {
                $HeaderSlug = $State->get(HeaderSlug::class);
                $Register = $State->get(SlugRegister::class);

                $id = $this->id();
                $classes = $this->classes();

                $attributes = \array_merge(
                    isset($id)
                        ? ['id' => $id]
                        : (
                            $HeaderSlug->isEnabled()
                            ? ['id' => $HeaderSlug->transform($Register, $this->text())]
                            : []
                        ),
                    !empty($classes)
                        ? ['class' => \implode(' ', $classes)]
                        : []
                );

                return new Element(
                    'h' . \strval($this->level()),
                    $attributes,
                    $State->applyTo(Parsedown::line($this->text(), $State))
                );
            }
        );
    }
}
