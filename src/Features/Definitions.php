<?php

namespace Erusev\ParsedownExtra\Features;

use Erusev\Parsedown\Configurables\BlockTypes;
use Erusev\Parsedown\State;
use Erusev\Parsedown\StateBearer;
use Erusev\ParsedownExtra\Components\Blocks\DefinitionList;

final class Definitions implements StateBearer
{
    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $State = ($StateBearer ?? new State)->state();

        $BlockTypes = $State->get(BlockTypes::class)
            ->addingMarkedLowPrecedence(':', [DefinitionList::class])

        ;

        $this->State = $State
            ->setting($BlockTypes)
        ;
    }

    public function state(): State
    {
        return $this->State;
    }

    /** @return self */
    public static function from(StateBearer $StateBearer)
    {
        return new self($StateBearer);
    }
}
