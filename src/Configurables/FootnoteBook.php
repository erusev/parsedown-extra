<?php

namespace Erusev\ParsedownExtra\Configurables;

use Erusev\Parsedown\MutableConfigurable;
use Erusev\ParsedownExtra\Components\Blocks\Footnote;

final class FootnoteBook implements MutableConfigurable
{
    /** @var array<string,Footnote> */
    private $Footnotes;

    /** @var array<string,array{count:int,num:int}> */
    private $inlineRecord;

    /** @var int */
    private $inlineCount;

    /**
     * @param array<string,Footnote> $Footnotes
     * @param array<string,array{count:int,num:int}> $inlineRecord
     */
    public function __construct(array $Footnotes = [], array $inlineRecord = [])
    {
        $this->Footnotes = $Footnotes;
        $this->inlineRecord = $inlineRecord;

        $this->inlineCount = \array_reduce(
            $inlineRecord,
            /** @param array{count:int,num:int} $record */
            function (int $sum, $record): int {
                return $sum + $record['count'];
            },
            0
        );
    }

    /** @return self */
    public static function initial()
    {
        return new self;
    }

    public function mutatingSetBlock(Footnote $Footnote): void
    {
        $this->Footnotes[$Footnote->title()] = $Footnote;
    }

    /** @return array{num:int,count:int}|null */
    public function mutatingGetNextInlineNumbers(string $title): ?array
    {
        if (! isset($this->Footnotes[$title])) {
            return null;
        }

        if (! isset($this->inlineRecord[$title])) {
            $this->inlineRecord[$title] = [
                'count' => 0,
                'num' => ++$this->inlineCount,
            ];
        }

        ++$this->inlineRecord[$title]['count'];

        return $this->inlineRecord[$title];
    }

    /** @return array<string,Footnote> */
    public function all()
    {
        return $this->Footnotes;
    }

    public function inlineRecord(string $title): int
    {
        return $this->inlineRecord[$title]['count'] ?? 0;
    }

    /** @return self */
    public function isolatedCopy(): self
    {
        return new self($this->Footnotes, $this->inlineRecord);
    }
}
