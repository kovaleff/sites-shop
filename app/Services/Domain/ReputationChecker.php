<?php

namespace App\Services\Domain;

/**
 * Репутация домена: списки небезопасных ресурсов.
 */
interface ReputationChecker
{
    public function isUnsafe(string $domain): bool;
}
