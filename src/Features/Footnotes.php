<?php

namespace Erusev\ParsedownExtra\Features;

use Erusev\Parsedown\Configurables\BlockTypes;
use Erusev\Parsedown\Configurables\InlineTypes;
use Erusev\Parsedown\Configurables\RenderStack;
use Erusev\Parsedown\Html\Renderable;
use Erusev\Parsedown\Html\Renderables\Container;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\RawHtml;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\State;
use Erusev\Parsedown\StateBearer;
use Erusev\ParsedownExtra\Components\Blocks\Footnote;
use Erusev\ParsedownExtra\Components\Inlines\Footnote as InlineFootnote;
use Erusev\ParsedownExtra\Configurables\FootnoteBook;

final class Footnotes implements StateBearer
{
    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $State = ($StateBearer ?? new State)->state();

        $BlockTypes = $State->get(BlockTypes::class)
            ->addingMarkedHighPrecedence('[', [Footnote::class])
        ;

        $InlineTypes = $State->get(InlineTypes::class)
            ->addingHighPrecedence('[', [InlineFootnote::class])
        ;

        $RenderStack = $State->get(RenderStack::class)
            ->push(self::renderFootnotes())
        ;

        $this->State = $State
            ->setting($BlockTypes)
            ->setting($InlineTypes)
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
    public static function renderFootnotes()
    {
        /**
        * @param Renderable[] $Rs
        * @param State $S
        * @return Renderable[] $Rs
        */
        return function (array $Rs, State $S): array {
            $FB = $S->get(FootnoteBook::class);

            if (empty($FB->all())) {
                return $Rs;
            }

            return \array_merge($Rs, [new Element(
                'div',
                ['class' => 'footnotes'],
                [
                   Element::selfClosing('hr', []),
                   new Element('ol', [], \array_map(
                       function (Footnote $F) use ($FB, $S): Element {
                           $BackLink = \array_merge(
                               [new RawHtml('&#160;')],
                               \array_map(
                                   function (int $n) use ($F, $FB): Container {
                                       return new Container(\array_merge(
                                           [
                                           new Element(
                                               'a',
                                               [
                                                   'href' => '#fnref'.\strval($n).':'.$F->title(),
                                                   'rev' => 'footnote',
                                                   'class' => 'footnote-backref'
                                               ],
                                               [new RawHtml('&#8617;')]
                                           )],
                                           ($n < $FB->inlineRecord($F->title()) ? [new Text(' ')] : [])
                                       ));
                                   },
                                   ($count = $FB->inlineRecord($F->title())) > 1 ? \range(1, $count) : [1]
                               )
                           );

                           [$StateRenderables, $S] = Parsedown::lines($F->lines(), $S);

                           $InnerRender = $S->applyTo($StateRenderables);

                           $lastItem = $InnerRender[\count($InnerRender) -1];

                           if ($lastItem instanceof Element && $lastItem->name() === 'p' && $contents = $lastItem->contents()) {
                               $InnerRender[\count($InnerRender) -1] = new Element('p', [], \array_merge($contents, $BackLink));
                           } else {
                               $InnerRender[] = new Element('p', [], $BackLink);
                           }

                           return new Element('li', ['id' => 'fn:'.$F->title()], $InnerRender);
                       },
                       $FB->all()
                   ))
               ]
            )]);
        };
    }
}
