<?php


namespace OnMoon\DtoMapper\Tests;


class TestSubDto
{
    private int $int;

    public function toArray() {
        return ['int' => $this->int];
    }
}