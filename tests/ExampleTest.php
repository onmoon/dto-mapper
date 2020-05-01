<?php

declare(strict_types=1);

namespace OnMoon\DtoMapper\Tests;

use OnMoon\DtoMapper\DtoMapper;
use PHPUnit\Framework\TestCase;
use Safe\DateTime;

class ExampleTest extends TestCase
{
    public function testExample() : void
    {
        $source = [
            'string' => '123',
            'stringArray' => ['1', '2', '3'],
            'date' => new DateTime(),
            'dateArray' => [new DateTime(), new DateTime()],
            'subDto' => ['int' => 1],
            'subDtoArray' => [['int' => 2],['int' => 3]],
        ];

        $result = $source;
        $result['subDtoNullArray'] = null;

        $mapper = new DtoMapper();
        $mapped = $mapper->map($source, TestDto::class);

        self::assertEquals($result, $mapped->toArray());
    }
}
