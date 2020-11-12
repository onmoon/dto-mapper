<?php

declare(strict_types=1);

namespace OnMoon\DtoMapper\Exception;

use function Safe\sprintf;

class CannotMapToDto extends DtoMapperError
{
    public static function becausePhpDocIsNotReadable(string $name, string $class): self
    {
        return new self(
            sprintf(
                'PhpDoc does not define a type for "%s" in "%s"',
                $name,
                $class
            )
        );
    }

    public static function becausePhpDocIsMultiple(string $name, string $class): self
    {
        return new self(
            sprintf(
                'PhpDoc specifies multiple types for "%s" in "%s"',
                $name,
                $class
            )
        );
    }

    public static function becausePhpDocIsNotAnArray(string $name, string $class): self
    {
        return new self(
            sprintf(
                'PhpDoc does not define an array for "%s" in "%s"',
                $name,
                $class
            )
        );
    }

    public static function becausePhpDocIsCorrupt(string $name, string $class): self
    {
        return new self(
            sprintf(
                'PhpDoc does not define an array of a single class for "%s" in "%s"',
                $name,
                $class
            )
        );
    }

    public static function becauseNoPhpType(string $name, string $class): self
    {
        return new self(
            sprintf(
                'No php type specified for for "%s" in "%s"',
                $name,
                $class
            )
        );
    }

    public static function becauseClassDoesNotExist(string $childClass, string $name, string $class): self
    {
        return new self(
            sprintf(
                'Class "%s" does not exist for "%s" in "%s"',
                $childClass,
                $name,
                $class
            )
        );
    }

    public static function becauseRootClassDoesNotExist(string $class): self
    {
        return new self(
            sprintf(
                'Class "%s" does not exist',
                $class
            )
        );
    }
}
