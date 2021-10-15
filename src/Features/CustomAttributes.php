<?php

namespace Erusev\ParsedownExtra\Features;

use Erusev\Parsedown\Components\Blocks\Header as CoreHeader;
use Erusev\Parsedown\Components\Blocks\SetextHeader as CoreSetextHeader;
use Erusev\Parsedown\Components\Inlines\Image as CoreImage;
use Erusev\Parsedown\Components\Inlines\Link as CoreLink;
use Erusev\Parsedown\Configurables\BlockTypes;
use Erusev\Parsedown\Configurables\InlineTypes;
use Erusev\Parsedown\Html\Renderable;
use Erusev\Parsedown\Html\Renderables\Container;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\RawHtml;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Html\TransformableRenderable;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\State;
use Erusev\Parsedown\StateBearer;
use Erusev\ParsedownExtra\Components\Blocks\Footnote;
use Erusev\ParsedownExtra\Components\Blocks\Header;
use Erusev\ParsedownExtra\Components\Blocks\SetextHeader;
use Erusev\ParsedownExtra\Components\Inlines\Image;
use Erusev\ParsedownExtra\Components\Inlines\Link;
use Erusev\ParsedownExtra\Configurables\AbbreviationBook;
use Erusev\ParsedownExtra\Configurables\FootnoteBook;

final class CustomAttributes implements StateBearer
{
    /** @var State */
    private $State;

    public function __construct(StateBearer $StateBearer = null)
    {
        $State = ($StateBearer ?? new State)->state();

        $BlockTypes = $State->get(BlockTypes::class)
            ->replacing(CoreHeader::class, Header::class)
            ->replacing(CoreSetextHeader::class, SetextHeader::class)
        ;

        $InlineTypes = $State->get(InlineTypes::class)
            ->replacing(CoreLink::class, Link::class)
            ->replacing(CoreImage::class, Image::class)
        ;

        $this->State = $State
            ->setting($BlockTypes)
            ->setting($InlineTypes)
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
