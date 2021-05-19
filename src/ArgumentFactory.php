<?php

declare(strict_types=1);

namespace LukasJankowski\StringArguments;

use const FILTER_NULL_ON_FAILURE;

use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;
use InvalidArgumentException;

class ArgumentFactory
{
    protected array $types = [
        's' => [
            'name' => 'string',
            'method' => 'makeString',
            'hasDefault' => '',
        ],
        'i' => [
            'name' => 'integer',
            'method' => 'makeInteger',
            'hasDefault' => false,
        ],
        'f' => [
            'name' => 'float',
            'method' => 'makeFloat',
            'hasDefault' => false,
        ],
        'b' => [
            'name' => 'bool',
            'method' => 'makeBoolean',
            'hasDefault' => false,
        ],
        'n' => [
            'name' => 'null',
            'method' => 'makeNull',
            'hasDefault' => null,
        ],
        'a' => [
            'name' => 'array',
            'method' => 'makeArray',
            'hasDefault' => [],
        ],
        'o' => [
            'name' => 'object',
            'method' => 'makeObject',
            'hasDefault' => '',
        ],
    ];

    private static string $compoundSeparator = ',';

    private static string $compoundAssign = '=';

    private static string $typeSeparator = ':';

    /**
     * ArgumentFactory constructor.
     */
    public function __construct(private string $argument)
    {
    }

    /**
     * Static alias for the build() method.
     */
    public static function make(string $argument): mixed
    {
        $factory = new self($argument);

        return $factory->build();
    }

    /**
     * Setter.
     */
    public static function setCompoundSeparator(string $separator): void
    {
        self::$compoundSeparator = $separator;
    }

    /**
     * Getter.
     */
    public static function getCompoundSeparator(): string
    {
        return self::$compoundSeparator;
    }

    /**
     * Setter.
     */
    public static function setTypeSeparator(string $typeSeparator): void
    {
        self::$typeSeparator = $typeSeparator;
    }

    /**
     * Getter.
     */
    public static function getTypeSeparator(): string
    {
        return self::$typeSeparator;
    }

    /**
     * Setter.
     */
    public static function setCompoundAssign(string $compoundAssign): void
    {
        self::$compoundAssign = $compoundAssign;
    }

    /**
     * Getter.
     */
    public static function getCompoundAssign(): string
    {
        return self::$compoundAssign;
    }

    /**
     * Attempt to construct the argument from the given string.
     */
    public function build(): mixed
    {
        $argument = $this->resolveArgument();
        $method = $argument['type']['method'];

        return ! is_string($argument['value']) ? $argument['value'] : $this->{$method}($argument['value']);
    }

    /**
     * Make a string.
     */
    private function makeString(string $value): string
    {
        return $value;
    }

    /**
     * Make an integer.
     */
    private function makeInteger(string $value): int
    {
        return $this->makeScalar($value, 'int', FILTER_VALIDATE_INT);
    }

    /**
     * Make a float.
     */
    private function makeFloat(string $value): float
    {
        return $this->makeScalar($value, 'float', FILTER_VALIDATE_FLOAT);
    }

    /**
     * Make a boolean.
     */
    private function makeBoolean(string $value): bool
    {
        return $this->makeScalar($value, 'bool', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Make null.
     */
    private function makeNull(): mixed
    {
        return null;
    }

    /**
     * Make an array.
     */
    private function makeArray(string $values): array
    {
        // Remove braces / don't split nested
        $values = preg_split(
            '#(\[(?:[^[\]]*|(?1))*])(*SKIP)(*FAIL)|\\' . self::$compoundSeparator . '#',
            substr($values, 1, -1)
        );

        $array = [];
        foreach ($values as $value) {
            $argument = $this->resolveArrayArgument($value);
            $value = self::make($argument['value']);

            if ($argument['key'] === null) {
                $array[] = $value;
            } else {
                $array[$argument['key']] = $value;
            }
        }

        return $array;
    }

    /**
     * Make an object.
     */
    public function makeObject(string $values): object
    {
        if ($values === '') {
            return (object) [];
        }

        return (object) $this->makeArray($values);
    }

    /**
     * Attempt to resolve an array argument.
     */
    private function resolveArrayArgument(string $value): array
    {
        $matches = [];
        preg_match(
            '#^([^\\' . self::$compoundAssign . ']*)\\' . self::$compoundAssign . '.*#',
            $value,
            $matches
        );

        if (empty($matches)) {
            return ['key' => null, 'value' => $value];
        }

        $offset = strlen($matches[1]) + strlen(self::$compoundAssign);

        return ['key' => $matches[1], 'value' => substr($value, $offset)];
    }

    /**
     * Make a scalar value.
     */
    private function makeScalar(string $value, string $type, int $filter): int|float|bool
    {
        $val = filter_var($value, $filter, FILTER_NULL_ON_FAILURE);

        if ($val === null) {
            throw new InvalidArgumentException(
                sprintf('Unable to cast "%s" to "%s"', $value, $type)
            );
        }

        return $val;
    }

    /**
     * Resolve the type.
     */
    private function resolveArgument(): array
    {
        $split = explode(self::$typeSeparator, $this->argument, 2);

        $type = $this->resolveType($split[0]);
        $value = $this->resolveValue($type, $split[1] ?? null);

        return ['type' => $type, 'value' => $value];
    }

    /**
     * Resolve the type of argument.
     */
    private function resolveType(string $typeIdentifier): array
    {
        // Default to string
        $type = $typeIdentifier === $this->argument ? $this->types['s'] : $this->types[$typeIdentifier] ?? null;

        if ($type === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid argument type provided "%s". Must be one of: %s',
                    $typeIdentifier,
                    implode(',', array_keys($this->types))
                )
            );
        }

        return $type;
    }

    /**
     * Resolve the value of the argument.
     */
    private function resolveValue(array $type, ?string $param): mixed
    {
        if ($param === null || $param === '') {
            if ($type['hasDefault'] === false) {
                throw new InvalidArgumentException(
                    sprintf('Type "%s" must have a value.', $type['name'])
                );
            }

            $param = $type['hasDefault'];
        }

        return $param;
    }
}
