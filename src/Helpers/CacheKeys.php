<?php

namespace CodeOfDigital\CacheRepository\Helpers;

class CacheKeys
{
    protected static string $storeFile = 'repository-cache-keys.json';

    protected static array $keys = [];

    public static function putKey($group, $key): void
    {
        self::loadKeys();

        self::$keys[$group] = self::getKeys($group);

        if (!in_array($key, self::$keys[$group])) self::$keys[$group][] = $key;

        self::storeKeys();
    }

    public static function loadKeys(): array
    {
        if (!empty(self::$keys)) {
            return self::$keys;
        }

        $file = self::getFileKeys();

        if (!file_exists($file)) {
            self::storeKeys();
        }

        $content = file_get_contents($file);
        self::$keys = json_decode($content, true);

        return self::$keys;
    }

    public static function getFileKeys(): string
    {
        return storage_path('framework/cache/' . self::$storeFile);
    }

    public static function storeKeys(): bool|int
    {
        $file = self::getFileKeys();
        self::$keys = empty(self::$keys) ? [] : self::$keys;
        $content = json_encode(self::$keys);

        return file_put_contents($file, $content);
    }

    public static function getKeys($group): array
    {
        self::loadKeys();
        self::$keys[$group] = self::$keys[$group] ?? [];
        return self::$keys[$group];
    }

    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }

    public function __call($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }
}