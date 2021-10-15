<?php

namespace Erusev\ParsedownExtra\Components\Inlines;

use Erusev\Parsedown\Components\Inline;
use Erusev\Parsedown\Components\Inlines\WidthTrait;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsing\Excerpt;
use Erusev\Parsedown\State;
use Erusev\ParsedownExtra\Configurables\FootnoteBook;

final class Footnote implements Inline
{
    use WidthTrait;

    /** @var string */
    private $title;

    /** @var int */
    private $number;

    /** @var int */
    private $count;

    public function __construct(string $title, int $number, int $count)
    {
        $this->title = $title;
        $this->number = $number;
        $this->count = $count;
        $this->width = \strlen($title) + 3;
    }

    /**
     * @param Excerpt $Excerpt
     * @param State $State
     * @return static|null
     */
    public static function build(Excerpt $Excerpt, State $State = null)
    {
        $State = $State ?: new State;

        $FootnoteBook = $State->get(FootnoteBook::class);

        if (\preg_match('/^\[\^(.+?)\]/', $Excerpt->text(), $matches)) {
            $title = $matches[1];

            $numbers = $FootnoteBook->mutatingGetNextInlineNumbers($title);

            if (! isset($numbers)) {
                return null;
            }

            ['num' => $num, 'count' => $count] = $numbers;

            return new self($title, $num, $count);
        }

        return null;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return Element
     */
    public function stateRenderable()
    {
        return new Element(
            'sup',
            ['id' => 'fnref'.\strval($this->count()).':'.$this->title()],
            [new Element(
                'a',
                ['href' => '#fn:'.$this->title(), 'class' => 'footnote-ref'],
                [new Text(\strval($this->number()))]
            )]
        );
    }

    /**
     * @return Text
     */
    public function bestPlaintext()
    {
        return new Text($this->title());
    }
}
