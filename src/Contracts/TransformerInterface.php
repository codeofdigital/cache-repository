<?php

namespace CodeOfDigital\CacheRepository\Contracts;

interface TransformerInterface
{
    public function transformSingle(): array;

    public function transform(): array;
}