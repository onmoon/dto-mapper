<?php

declare(strict_types=1);

namespace OnMoon\DtoMapper\Exception;

use Safe\Exceptions\StringsException;
use function Safe\sprintf;

class UnexpectedNullValue extends DtoMapperException
{
    /** @var mixed $object */
    private $object;

    /**
     * @param mixed $value
     *
     * @throws StringsException
     */
    public function __construct(string $name, string $class, $value)
    {
        $message      = sprintf(
            'Failed to get not null value for "%s" in "%s"',
            $name,
            $class
        );
        $this->object = $value;
        parent::__construct($message);
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }
}
