<?php

namespace OnMoon\DtoMapper\Tests;

use \Safe\DateTime;

class TestDto
{
    private string $string;
    /** @var string[] $stringArray */
    private array $stringArray;
    private DateTime $date;
    /** @var DateTime[] $dateArray */
    private array $dateArray;

    public function toArray() {
        return [
            'string' => $this->string,
            'stringArray' => $this->stringArray,
            'date' => $this->date,
            'dateArray' => $this->dateArray
        ];
    }
}
