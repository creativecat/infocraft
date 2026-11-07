<?php
declare(strict_types=1);

namespace Infocraft;

final class Config
{
    /** @var array<string, mixed> */
    private static array $data = [];

    /** @param array<string, mixed> $data */
    public static function init(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
