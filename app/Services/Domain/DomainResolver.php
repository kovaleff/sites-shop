<?php

namespace App\Services\Domain;

/**
 * Проверка существования домена в DNS.
 *
 * Вынесена за интерфейс, чтобы сетевую проверку можно было подменить в тестах.
 */
interface DomainResolver
{
    public function resolves(string $domain): bool;
}
