<?php

namespace Erusev\ParsedownExtra\Configurables;

use Erusev\Parsedown\MutableConfigurable;

final class AbbreviationBook implements MutableConfigurable
{
    /** @var array<string, string> */
    private $book;

    /**
     * @param array<string, string> $book
     */
    public function __construct(array $book = [])
    {
        $this->book = $book;
    }

    /** @return self */
    public static function initial()
    {
        return new self;
    }

    public function mutatingSet(string $abbreviation, string $definition): void
    {
        $this->book[$abbreviation] = $definition;
    }

    public function lookup(string $abbreviation): ?string
    {
        return $this->book[$abbreviation] ?? null;
    }

    /** @return array<string, string> */
    public function all()
    {
        return $this->book;
    }

    /** @return self */
    public function isolatedCopy(): self
    {
        return new self($this->book);
    }
}
