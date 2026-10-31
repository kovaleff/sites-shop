<?php

namespace App\Services\Domain;

final readonly class DomainVerificationResult
{
    private function __construct(
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public static function passed(): self
    {
        return new self(true);
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }
}
