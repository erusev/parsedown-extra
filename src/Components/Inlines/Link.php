<?php

namespace Erusev\ParsedownExtra\Components\Inlines;

use Erusev\Parsedown\AST\Handler;
use Erusev\Parsedown\Components\Inline;
use Erusev\Parsedown\Components\Inlines\Link as BaseLink;
use Erusev\Parsedown\Components\Inlines\Url;
use Erusev\Parsedown\Components\Inlines\WidthTrait;
use Erusev\Parsedown\Configurables\InlineTypes;
use Erusev\Parsedown\Configurables\SafeMode;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Html\Sanitisation\UrlSanitiser;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\Parsing\Excerpt;
use Erusev\Parsedown\State;

final class Link implements Inline
{
    use WidthTrait;

    private const ATT_REGEX = '(?:[#.][-\w]+[ ]*)';

    /** @var string */
    private $label;

    /** @var string */
    private $url;

    /** @var string|null */
    private $title;

    /** @var string|null */
    private $id;

    /** @var list<string> */
    private $classes;

    /**
     * @param string $label
     * @param string $url
     * @param string|null $title
     * @param string|null $id
     * @param list<string> $classes
     * @param int $width
     */
    private function __construct($label, $url, $title, $id, $classes, $width)
    {
        $this->label = $label;
        $this->url = $url;
        $this->title = $title;
        $this->id = $id;
        $this->classes = $classes;
        $this->width = $width;
    }

    /**
     * @param Excerpt $Excerpt
     * @param State $State
     * @return static|null
     */
    public static function build(Excerpt $Excerpt, State $State)
    {
        $Base = BaseLink::build($Excerpt, $State);

        if (! isset($Base)) {
            return null;
        }

        $id = null;
        $classes = [];
        $extraWidth = 0;

        $remainder = $Excerpt->addingToOffset($Base->width())->text();

        if (\preg_match('/^[ ]*{('.self::ATT_REGEX.'+)}/', $remainder, $matches)) {
            $attributeString = $matches[1];

            ['id' => $id, 'classes' => $classes] = self::parseAttributeData($attributeString);

            $extraWidth = \strlen($matches[0]);
        }

        return new self(
            $Base->label(),
            $Base->url(),
            $Base->title(),
            $id,
            $classes,
            $Base->width() + $extraWidth
        );
    }

    /** @return string */
    public function label()
    {
        return $this->label;
    }

    /** @return string */
    public function url()
    {
        return $this->url;
    }

    /** @return string|null */
    public function title()
    {
        return $this->title;
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
                $attributes = ['href' => $this->url()];

                $title = $this->title();

                if (isset($title)) {
                    $attributes['title'] = $title;
                }

                if ($State->get(SafeMode::class)->isEnabled()) {
                    $attributes['href'] = UrlSanitiser::filter($attributes['href']);
                }

                $id = $this->id();

                if (isset($id)) {
                    $attributes['id'] = $id;
                }

                $classes = $this->classes();

                if (!empty($classes)) {
                    $attributes['class'] = \implode(' ', $classes);
                }

                $State = $State->setting(
                    $State->get(InlineTypes::class)->removing([Url::class])
                );

                return new Element(
                    'a',
                    $attributes,
                    $State->applyTo(Parsedown::line($this->label(), $State))
                );
            }
        );
    }

    /**
     * @return Text
     */
    public function bestPlaintext()
    {
        return new Text($this->label());
    }
}
