<?php

namespace CodeOfDigital\CacheRepository\Contracts;

use Illuminate\Support\Collection;

interface RepositoryCriteriaInterface
{
    public function pushCriteria($criteria): static;

    public function popCriteria($criteria): static;

    public function getCriteria(): Collection;

    public function getByCriteria(CriteriaInterface $criteria): mixed;

    public function skipCriteria(bool $status = true): static;

    public function resetCriteria(): static;
}