<?php

namespace Erusev\ParsedownExtra;

use Erusev\Parsedown\State;
use Erusev\Parsedown\StateBearer;
use Erusev\ParsedownExtra\Features\Abbreviations;
use Erusev\ParsedownExtra\Features\CustomAttributes;
use Erusev\ParsedownExtra\Features\Definitions;
use Erusev\ParsedownExtra\Features\Footnotes;

final class ParsedownExtra implements StateBearer
{
    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $StateBearer = Abbreviations::from($StateBearer ?? new State);
        $StateBearer = Definitions::from($StateBearer);
        $StateBearer = CustomAttributes::from($StateBearer);
        $StateBearer = Footnotes::from($StateBearer);

        $this->State = $StateBearer->state();
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
