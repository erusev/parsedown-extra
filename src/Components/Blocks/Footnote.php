<?php

namespace Erusev\ParsedownExtra\Components\Blocks;

use Erusev\Parsedown\Components\Block;
use Erusev\Parsedown\Components\ContinuableBlock;
use Erusev\Parsedown\Components\StateUpdatingBlock;
use Erusev\Parsedown\Html\Renderables\Invisible;
use Erusev\Parsedown\Parsing\Context;
use Erusev\Parsedown\Parsing\Line;
use Erusev\Parsedown\Parsing\Lines;
use Erusev\Parsedown\State;
use Erusev\ParsedownExtra\Configurables\FootnoteBook;

final class Footnote implements StateUpdatingBlock, ContinuableBlock
{
    /** @var State */
    private $State;

    /** @var string */
    private $title;

    /** @var Lines */
    private $Lines;

    private function __construct(State $State, string $title, Lines $Lines)
    {
        $this->State = $State;
        $this->title = $title;
        $this->Lines = $Lines;
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
        if (\preg_match(
            '/^\[\^(.+?)\]:(.*+)$/',
            $Context->line()->text(),
            $matches
        )) {
            $indentOffset = $Context->line()->indentOffset() + $Context->line()->indent() + \strlen($matches[1]) + 4;

            $title = $matches[1];
            $text = $matches[2];

            $Footnote = new self(
                $State,
                $title,
                Lines::fromTextLines($text, $indentOffset)
            );

            $State->get(FootnoteBook::class)->mutatingSetBlock($Footnote);

            return $Footnote;
        }

        return null;
    }

    /** @return self|null */
    public function advance(Context $Context, State $State): ?self
    {
        if ($Context->line()->indent() < 4) {
            return null;
        }

        $Lines = $this->Lines;

        $offset = $Context->line()->indentOffset();

        if ($Context->precedingEmptyLines() > 0) {
            foreach (\explode("\n", $Context->precedingEmptyLinesText()) as $line) {
                $Lines = $Lines->appendingTextLines((new Line($line, $offset))->ltrimBodyUpto(4), $offset);
            }
        }

        $indentOffset = $Context->line()->indentOffset() + $Context->line()->indent();
        $Lines = $Lines->appendingTextLines($Context->line()->text(), $indentOffset);

        $Footnote = new self($State, $this->title, $Lines);

        $State->get(FootnoteBook::class)->mutatingSetBlock($Footnote);

        return $Footnote;
    }

    /** @return State */
    public function latestState()
    {
        return $this->State;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function lines(): Lines
    {
        return $this->Lines;
    }

    /**
     * @return Invisible
     */
    public function stateRenderable()
    {
        return new Invisible;
    }
}
