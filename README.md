## Parsedown Extra

An extension of [Parsedown](http://parsedown.org) that adds support for [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/).

[See Demo](http://parsedown.org/extra/)

### Installation

Include both `Parsedown.php` and `ParsedownExtra.php` or install [the composer package](https://packagist.org/packages/condenast-spain/parsedown-extra).

### Example

``` php
$Extra = new ParsedownExtra();

echo $Extra->text('# Header {.sth}'); # prints: <h1 class="sth">Header</h1>
```

### Questions

**Who uses Parsedown Extra?**

[October CMS](http://octobercms.com/), [Bolt CMS](http://bolt.cm/), [Kirby CMS](http://getkirby.com/), [Grav CMS](http://getgrav.org/), [Statamic CMS](http://www.statamic.com/) and more.
