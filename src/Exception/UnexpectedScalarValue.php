<?php

declare(strict_types=1);

namespace OnMoon\DtoMapper\Exception;

use Safe\Exceptions\StringsException;

use function Safe\sprintf;

class UnexpectedScalarValue extends DtoMapperError
{
    /** @var mixed $value */
    private $value;

    /**
     * @param mixed $value
     *
     * @throws StringsException
     */
    public function __construct(string $name, string $class, $value)
    {
        $message     = sprintf(
            'Value is scalar for "%s" in "%s"',
            $name,
            $class
        );
        $this->value = $value;
        parent::__construct($message);
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
