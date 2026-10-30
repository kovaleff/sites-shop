<?php

namespace App\Http\Requests;

class UpdateSiteRequest extends StoreSiteRequest
{
    protected function ignoreSiteId(): ?int
    {
        $site = $this->route('site');

        return is_numeric($site) ? (int) $site : null;
    }
}
