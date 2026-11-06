<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class UpdateSiteRequest extends StoreSiteRequest
{
    /**
     * Согласие с офертой запрашивается только при создании объявления.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['acceptOffer']);
    }

    protected function ignoreSiteId(): ?int
    {
        $site = $this->route('site');

        return is_numeric($site) ? (int) $site : null;
    }

    /**
     * Капча нужна только при создании объявления.
     */
    protected function shouldVerifyCaptcha(): bool
    {
        return false;
    }
}
