<?php

namespace CodeOfDigital\CacheRepository\Contracts;

interface TransformerInterface
{
    public function transform(): array;
}