<?php

namespace App\Services\Domain;

final class DnsDomainResolver implements DomainResolver
{
    public function resolves(string $domain): bool
    {
        return @dns_get_record($domain, DNS_A | DNS_AAAA | DNS_CNAME) !== [];
    }
}
