## Parsedown Extra

An extension of [Parsedown](http://parsedown.org) that adds support for [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/).

[See Demo](http://parsedown.org/extra/)

### Installation

Include both `Parsedown.php` and `ParsedownExtra.php` or install [the composer package](https://packagist.org/packages/erusev/parsedown-extra).

### Example

``` php
$Extra = new ParsedownExtra();

echo $Extra->text('# Header {.sth}'); # prints: <h1 class="sth">Header</h1>
```

### Questions

**Who uses Parsedown Extra?**

[October CMS](https://octobercms.com/), [Winter CMS](https://wintercms.com/), [Bolt CMS](https://boltcms.io/), [Kirby CMS](https://getkirby.com/), [Grav CMS](https://getgrav.org/), [Statamic CMS](https://www.statamic.com/) and [more](https://www.versioneye.com/php/erusev:parsedown-extra/references).

**How can I help?**

Use it, star it, share it and in case you feel generous, [donate](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=528P3NZQMP8N2).

**What else should I know?**

I also make [Nota](https://nota.md/) — a notes app designed for local Markdown files.
