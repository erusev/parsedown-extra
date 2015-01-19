## Parsedown Extra

[![Build Status](https://travis-ci.org/erusev/parsedown-extra.svg)](https://travis-ci.org/erusev/parsedown-extra)

An extension of [Parsedown](http://parsedown.org) that adds support for [Markdown Extra](http://en.wikipedia.org/wiki/Markdown_Extra).

[[ demo ]](http://parsedown.org/extra/)

### Installation

Include both `Parsedown.php` and `ParsedownExtra.php` or install [the composer package](https://packagist.org/packages/erusev/parsedown-extra).

### Example

``` php
$Extra = new ParsedownExtra();

echo $Extra->text('Hello _Extra_!'); # prints: <p>Hello <em>Extra</em>!</p>
```

### Questions

**Who uses Parsedown Extra?**

[October CMS](http://octobercms.com/), [Bolt CMS](http://bolt.cm/), [Kirby CMS](http://getkirby.com/), [Grav CMS](http://getgrav.org/), [Statamic CMS](http://www.statamic.com/) and [more](https://www.versioneye.com/php/erusev:parsedown-extra/references).

**How can I help?**

Use the project, tell friends about it and if you feel generous, [donate some money](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=528P3NZQMP8N2).
