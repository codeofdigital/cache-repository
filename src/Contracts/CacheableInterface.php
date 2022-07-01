<?php

namespace CodeOfDigital\CacheRepository\Contracts;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

interface CacheableInterface
{
    public function setCacheRepository(CacheRepository $repository): static;

    public function getCacheRepository(): CacheRepository;

    public function getCacheKey($method, $args = null): string;

    public function getCacheTime(): int;

    public function skipCache($status = true): static;
}