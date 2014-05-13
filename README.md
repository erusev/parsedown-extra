## Parsedown Extra

An extension of [Parsedown](http://parsedown.org) that adds support for [Markdown Extra](http://en.wikipedia.org/wiki/Markdown_Extra).

### Installation

Include both `Parsedown.php` and `ParsedownExtra.php` or install [the composer package](https://packagist.org/packages/erusev/parsedown-extra).

### Example

``` php
$Parsedown = new ParsedownExtra();

echo $Parsedown->text('Hello _Parsedown_!'); # prints: <p>Hello <em>Parsedown</em>!</p>
```
