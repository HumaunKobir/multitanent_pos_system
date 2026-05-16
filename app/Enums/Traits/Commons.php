<?php

namespace App\Enums\Traits;

trait Commons
{
    public static function getInstances(): array
    {
        return static::cases();
    }

    public static function getKeys(): array
    {
        return array_column(static::cases(), 'name');
    }

    public static function getValues(): array
    {
        return array_column(static::cases(), 'value');
    }

    public static function asSelectArray(): array
    {
        $result = [];
        foreach (static::cases() as $case) {
            $result[$case->value] = $case->name;
        }

        return $result;
    }

    public static function fromValue(int $value): static
    {
        return static::from($value);
    }

    public static function fromKey(string $key): static
    {
        foreach (static::cases() as $case) {
            if ($case->name === $key) {
                return $case;
            }
        }

        throw new \ValueError("'{$key}' is not a valid key for enum ".static::class);
    }
}
