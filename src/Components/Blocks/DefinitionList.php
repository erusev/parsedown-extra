<?php

namespace Erusev\ParsedownExtra\Components\Blocks;

use Erusev\Parsedown\AST\Handler;
use Erusev\Parsedown\Components\AcquisitioningBlock;
use Erusev\Parsedown\Components\Block;
use Erusev\Parsedown\Components\Blocks\Paragraph;
use Erusev\Parsedown\Components\ContinuableBlock;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\Parsing\Context;
use Erusev\Parsedown\Parsing\Line;
use Erusev\Parsedown\Parsing\Lines;
use Erusev\Parsedown\State;

final class DefinitionList implements ContinuableBlock, AcquisitioningBlock
{
    /** @var Paragraph */
    private $StartingBlock;

    /** @var Lines[] */
    private $Lis;

    /** @var bool */
    private $isLoose;

    /** @var int */
    private $requiredIndent;

    /**
     * @param Paragraph $StartingBlock
     * @param Lines[] $Lis
     * @param bool $isLoose
     * @param int $requiredIndent
     */
    private function __construct(Paragraph $StartingBlock, $Lis, $isLoose, $requiredIndent)
    {
        $this->StartingBlock = $StartingBlock;
        $this->Lis = $Lis;
        $this->isLoose = $isLoose;
        $this->requiredIndent = $requiredIndent;
    }

    /**
     * @param Context $Context
     * @param Block|null $Block
     * @param State|null $State
     * @return static|null
     */
    public static function build(
        Context $Context,
        State $State = null,
        Block $Block = null
    ) {
        if (! isset($Block) || ! $Block instanceof Paragraph || $Context->precedingEmptyLines() > 1) {
            return null;
        }

        $text = $Context->line()->text();

        if (\substr($text, 0, 1) !== ':' || $Context->line()->indent() > 3) {
            return null;
        }

        $secondChar = \substr($text, 1, 1);

        if ($secondChar !== ' ' && $secondChar !== "\t") {
            return null;
        }

        $preAfterMarkerSpacesIndentOffset = $Context->line()->indentOffset() + $Context->line()->indent() + 1;

        $LineWithMarkerIndent = new Line(\substr($text, 2), $preAfterMarkerSpacesIndentOffset);
        $indentAfterMarker = $LineWithMarkerIndent->indent();

        if ($indentAfterMarker > 4) {
            $perceivedIndent = $indentAfterMarker -1;
            $afterMarkerSpaces = 1;
        } else {
            $perceivedIndent = 0;
            $afterMarkerSpaces = $indentAfterMarker;
        }

        $indentOffset = $preAfterMarkerSpacesIndentOffset + $afterMarkerSpaces;
        $text = \str_repeat(' ', $perceivedIndent) . $LineWithMarkerIndent->text();


        return new self(
            $Block,
            [!empty($text) ? Lines::fromTextLines($text, $indentOffset) : Lines::none()],
            false,
            $Context->line()->indent() + 2 + $afterMarkerSpaces
        );
    }

    /** @return bool */
    public function acquiredPrevious()
    {
        return true;
    }

    /**
     * @param Context $Context
     * @return self|null
     */
    public function advance(Context $Context, State $State)
    {
        if ($Context->precedingEmptyLines() > 0 && \end($this->Lis)->isEmpty()) {
            return null;
        }

        $indent = $Context->line()->indent();
        $offset = $Context->line()->indentOffset();

        $newLines = '';

        if ($Context->precedingEmptyLines() > 0) {
            foreach (\explode("\n", $Context->precedingEmptyLinesText()) as $line) {
                $newLines .= (new Line($line, $offset))->ltrimBodyUpto($this->requiredIndent) . "\n";
            }

            $newLines = \substr($newLines, 0, -1);
        }

        if ($indent >= $this->requiredIndent) {
            $newLines .= $Context->line()->ltrimBodyUpto($this->requiredIndent);

            $Lis = $this->Lis;
            $Lis[\count($Lis) -1] = $Lis[\count($Lis) -1]->appendingTextLines(
                $newLines,
                $offset
            );

            return new self(
                $this->StartingBlock,
                $Lis,
                $this->isLoose,
                $this->requiredIndent
            );
        } elseif ($NewDefinitionList = self::build($Context, $State, $this->StartingBlock)) {
            return new self(
                $this->StartingBlock,
                \array_merge($this->Lis, $NewDefinitionList->Lis),
                $this->isLoose || $Context->precedingEmptyLines() > 0,
                $NewDefinitionList->requiredIndent
            );
        } elseif (! ($Context->precedingEmptyLines() > 0)) {
            $Lis = $this->Lis;
            $text = $Context->line()->ltrimBodyUpto($this->requiredIndent);

            $Lis[\count($Lis) -1] = $Lis[\count($Lis) -1]->appendingTextLines(
                $newLines . \str_repeat(' ', $Context->line()->indent()) . $text,
                $Context->line()->indentOffset() + $Context->line()->indent()
            );

            return new self(
                $this->StartingBlock,
                $Lis,
                $this->isLoose,
                $this->requiredIndent
            );
        }

        return null;
    }

    /**
     * @return Handler<Element>
     */
    public function stateRenderable()
    {
        return new Handler(
            function (State $State): Element {
                return new Element(
                    'dl',
                    [],
                    \array_merge(
                        \array_map(
                            function (string $term): Element {
                                return new Element('dt', [], [new Text($term)]);
                            },
                            \explode("\n", $this->StartingBlock->text())
                        ),
                        \array_map(
                            function (Lines $Lines) use ($State): Element {
                                list($StateRenderables, $State) = Parsedown::lines(
                                    $Lines,
                                    $State
                                );

                                $Renderables = $State->applyTo($StateRenderables);

                                if (! $this->isLoose
                                    && isset($Renderables[0])
                                    && $Renderables[0] instanceof Element
                                    && $Renderables[0]->name() === 'p'
                                ) {
                                    $Contents = $Renderables[0]->contents();
                                    unset($Renderables[0]);
                                    $Renderables = \array_merge($Contents ?: [], $Renderables);
                                }

                                return new Element('dd', [], $Renderables);
                            },
                            $this->Lis
                        )
                    )
                );
            }
        );
    }
}
