<?php

declare(strict_types=1);

namespace OnMoon\DtoMapper;

use ArrayAccess;
use ArrayObject;
use BadMethodCallException;
use DateTimeInterface;
use Exception;
use OnMoon\DtoMapper\Exception\CannotMapToDto;
use OnMoon\DtoMapper\Exception\MapperReturnedNotAString;
use OnMoon\DtoMapper\Exception\NotADateTimeValue;
use OnMoon\DtoMapper\Exception\NotAnArrayValue;
use OnMoon\DtoMapper\Exception\UnexpectedNullValue;
use OnMoon\DtoMapper\Exception\UnexpectedScalarValue;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Safe\DateTime;
use Safe\Exceptions\ArrayException;
use Safe\Exceptions\PcreException;
use Safe\Exceptions\StringsException;
use Safe\Exceptions\VarException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use function array_key_exists;
use function array_map;
use function class_exists;
use function count;
use function get_class;
use function get_class_methods;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function method_exists;
use function Safe\settype;
use function Safe\sort;
use function Safe\substr;
use function strpos;
use function strtr;

class DtoMapper
{
    private PropertyInfoExtractor $propertyInfo;

    public function __construct()
    {
        $phpDocExtractor                 = new PhpDocExtractor();
        $typeExtractors                  = [$phpDocExtractor];
        $this->propertyInfo = new PropertyInfoExtractor([], $typeExtractors, [], [], []);
    }

    /**
     * @param array|object $from
     *
     * @return mixed
     *
     * @throws CannotMapToDto
     * @throws MapperReturnedNotAString
     * @throws NotADateTimeValue
     * @throws NotAnArrayValue
     * @throws ReflectionException
     * @throws StringsException
     * @throws UnexpectedNullValue
     * @throws UnexpectedScalarValue
     * @throws PcreException
     * @throws VarException
     * @throws ArrayException
     */
    public function map($from, string $toDTO, ?callable $propertyMapper = null)
    {
        if (! class_exists($toDTO)) {
            throw CannotMapToDto::becauseRootClassDoesNotExist($toDTO);
        }

        $reflectionClass = new ReflectionClass($toDTO);
        $instance        = $reflectionClass->newInstanceWithoutConstructor();

        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            $nextMapper = null;
            if ($propertyMapper !== null) {
                $mappedProperty = $propertyMapper($property->getName(), []);
                if (! is_string($mappedProperty) || empty($mappedProperty)) {
                    throw new MapperReturnedNotAString($property->getName(), $toDTO, $mappedProperty);
                }

                $nextMapper = static function (string $name, array $context) use ($mappedProperty, $propertyMapper) {
                    $context[] = $mappedProperty;

                    return $propertyMapper($name, $context);
                };
            } else {
                $mappedProperty = $property->getName();
            }

            $rawValue = $this->getValue($from, $mappedProperty);

            $value = $this->getPropertyValue($rawValue, $property, $from, $toDTO, $nextMapper);
            $property->setAccessible(true);
            $property->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * @param mixed $rawValue
     * @param mixed $from
     *
     * @return mixed
     *
     * @throws ArrayException
     * @throws CannotMapToDto
     * @throws MapperReturnedNotAString
     * @throws NotADateTimeValue
     * @throws NotAnArrayValue
     * @throws PcreException
     * @throws ReflectionException
     * @throws StringsException
     * @throws UnexpectedNullValue
     * @throws UnexpectedScalarValue
     * @throws VarException
     */
    private function getPropertyValue($rawValue, ReflectionProperty $property, $from, string $className, ?callable $nextMapper)
    {
        $type = $property->getType();
        if ($type === null) {
            throw new Exception('Property has no PHP type');
        }

        if ($rawValue === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw new UnexpectedNullValue($property->getName(), $className, $from);
        }

        /** @phpstan-ignore-next-line */
        $typeName = $type->getName();
        if ($typeName === 'array') {
            if (! (is_array($rawValue) || $rawValue instanceof ArrayObject)) {
                throw new NotAnArrayValue($property->getName(), $className, $rawValue);
            }

            if (count($rawValue) === 0) {
                return [];
            }

            $docTypes = $this->propertyInfo->getTypes($className, $property->getName());
            if ($docTypes === null) {
                throw new Exception('No valid phpDoc found for array property');
            }

            if (count($docTypes) !== 1) {
                throw new Exception('Too much DocBlock types');
            }

            if (! $docTypes[0]->isCollection()) {
                throw new Exception('DocBlock type is not a collection');
            }

            $docIterableType = $docTypes[0]->getCollectionValueType();
            if ($docIterableType === null) {
                throw new Exception('No valid phpDoc found for array property');
            }

            $itemClassName = $docIterableType->getClassName()??$docIterableType->getBuiltinType();
            $isBuiltIn     = ($docIterableType->getClassName() === null);

            $result = [];
            foreach ($rawValue as $v) {
                $result[] = $this->getNotIterableValue($v, $itemClassName, $isBuiltIn, $property->getName(), $className, $nextMapper);
            }

            return $result;
        }

        return $this->getNotIterableValue($rawValue, $typeName, $type->isBuiltin(), $property->getName(), $className, $nextMapper);
    }

    /**
     * @param mixed $rawValue
     *
     * @return mixed
     *
     * @throws ArrayException
     * @throws CannotMapToDto
     * @throws MapperReturnedNotAString
     * @throws NotADateTimeValue
     * @throws NotAnArrayValue
     * @throws PcreException
     * @throws ReflectionException
     * @throws StringsException
     * @throws UnexpectedNullValue
     * @throws UnexpectedScalarValue
     * @throws VarException
     */
    private function getNotIterableValue($rawValue, string $typeName, bool $isBuiltIn, string $propertyName, string $className, ?callable $nextMapper)
    {
        if (is_subclass_of($typeName, DateTimeInterface::class)) {
            if ($rawValue instanceof DateTimeInterface) {
                $value = new DateTime($rawValue->format('Y-m-d H:i:s.u'), $rawValue->getTimezone());
            } elseif (is_string($rawValue)) {
                $value = new $typeName($rawValue);
            } else {
                throw new NotADateTimeValue($propertyName, $className, $rawValue);
            }
        } elseif ($isBuiltIn) {
            settype($rawValue, $typeName);
            $value = $rawValue;
        } elseif (class_exists($typeName)) {
            if (! is_array($rawValue) && ! is_object($rawValue)) {
                throw new UnexpectedScalarValue($propertyName, $className, $rawValue);
            }

            $value = $this->map($rawValue, $typeName, $nextMapper);
        } else {
            throw CannotMapToDto::becauseUnknownType($typeName, $propertyName, $className);
        }

        return $value;
    }

    /**
     * @param array|object $object
     *
     * @return mixed|null
     *
     * @throws StringsException|ArrayException
     */
    private function getValue($object, string $item)
    {
        if (((is_array($object) || $object instanceof ArrayObject) &&
                (isset($object[$item]) || array_key_exists($item, (array) $object)))
            || ($object instanceof ArrayAccess && isset($object[$item]))
        ) {
            return $object[$item];
        }

        if (! is_object($object)) {
            return null;
        }

        if (isset($object->$item) || array_key_exists((string) $item, (array) $object)) {
            return $object->$item;
        }

        static $cache = [];

        $class = get_class($object);

        // object method
        // precedence: getXxx() > isXxx() > hasXxx()
        if (! isset($cache[$class])) {
            $methods = get_class_methods($object);
            sort($methods);
            $lcMethods  = array_map(
                static function (string $value) {
                    return self::strToLowerEn($value);
                },
                $methods
            );
            $classCache = [];

            foreach ($methods as $i => $method) {
                $classCache[$method] = $method;
                $classCache[$lcName  = $lcMethods[$i]] = $method;

                if ($lcName[0] === 'g' && strpos($lcName, 'get') === 0) {
                    $name   = substr($method, 3);
                    $lcName = substr($lcName, 3);
                } elseif ($lcName[0] === 'i' && strpos($lcName, 'is') === 0) {
                    $name   = substr($method, 2);
                    $lcName = substr($lcName, 2);
                } elseif ($lcName[0] === 'h' && strpos($lcName, 'has') === 0) {
                    $name   = substr($method, 3);
                    $lcName = substr($lcName, 3);
                    if (in_array('is' . $lcName, $lcMethods)) {
                        continue;
                    }
                } else {
                    continue;
                }

                // skip get() and is() methods (in which case, $name is empty)
                if (! $name) {
                    continue;
                }

                if (! isset($classCache[$name])) {
                    $classCache[$name] = $method;
                }

                if (isset($classCache[$lcName])) {
                    continue;
                }

                $classCache[$lcName] = $method;
            }

            $cache[$class] = $classCache;
        }

        $magicCall = false;
        $lcItem    = $this->strToLowerEn($item);
        if (isset($cache[$class][$item])) {
            $method = $cache[$class][$item];
        } elseif (isset($cache[$class][$lcItem])) {
            $method = $cache[$class][$lcItem];
        } elseif (isset($cache[$class]['__call'])) {
            $method    = $item;
            $magicCall = true;
        } else {
            return null;
        }

        if (! method_exists($object, $method)) {
            return null;
        }

        try {
            $ret = $object->$method();
        } catch (BadMethodCallException $e) {
            if ($magicCall) {
                return null;
            }

            throw $e;
        }

        return $ret;
    }

    private static function strToLowerEn(string $str) : string
    {
        return strtr($str, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
