<?php

namespace Erusev\ParsedownExtra\Features;

use Erusev\Parsedown\Configurables\BlockTypes;
use Erusev\Parsedown\Configurables\RenderStack;
use Erusev\Parsedown\Html\Renderable;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Html\TransformableRenderable;
use Erusev\Parsedown\State;
use Erusev\Parsedown\StateBearer;
use Erusev\ParsedownExtra\Components\Blocks\Abbreviation;
use Erusev\ParsedownExtra\Configurables\AbbreviationBook;

final class Abbreviations implements StateBearer
{
    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $State = ($StateBearer ?? new State)->state();

        $BlockTypes = $State->get(BlockTypes::class)
            ->addingMarkedLowPrecedence('*', [Abbreviation::class])
        ;

        $RenderStack = $State->get(RenderStack::class)
            ->push(self::expandAbbreviations())
        ;

        $this->State = $State
            ->setting($BlockTypes)
            ->setting($RenderStack)
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

    /** @return \Closure(Renderable[],State):Renderable[] */
    public static function expandAbbreviations()
    {
        /**
         * @param Renderable[] $Rs
         * @param State $S
         * @return Renderable[] $Rs
         */
        return function (array $Rs, State $S): array {
            $abbrvs = $S->get(AbbreviationBook::class)->all();

            if (empty($abbrvs)) {
                return $Rs;
            }

            return \array_map(
                function (Renderable $R) use ($abbrvs): Renderable {
                    if ($R instanceof TransformableRenderable) {
                        foreach ($abbrvs as $abbrv => $meaning) {
                            $R = $R->replacingAll(
                                $abbrv,
                                new Element('abbr', ['title' => $meaning], [new Text($abbrv)])
                            );
                        }
                    }

                    return $R;
                },
                $Rs
            );
        };
    }
}
