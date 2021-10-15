<?php

namespace Erusev\ParsedownExtra\Components\Blocks;

use Erusev\Parsedown\Components\Block;
use Erusev\Parsedown\Components\StateUpdatingBlock;
use Erusev\Parsedown\Html\Renderables\Invisible;
use Erusev\Parsedown\Parsing\Context;
use Erusev\Parsedown\State;
use Erusev\ParsedownExtra\Configurables\AbbreviationBook;

final class Abbreviation implements StateUpdatingBlock
{
    /** @var State */
    private $State;

    private function __construct(State $State)
    {
        $this->State = $State;
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
            '/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/',
            $Context->line()->text(),
            $matches
        )) {
            $short = $matches[1];
            $long = $matches[2];

            $State->get(AbbreviationBook::class)->mutatingSet($short, $long);

            return new self($State);
        }

        return null;
    }

    /** @return State */
    public function latestState()
    {
        return $this->State;
    }

    /**
     * @return Invisible
     */
    public function stateRenderable()
    {
        return new Invisible;
    }
}
