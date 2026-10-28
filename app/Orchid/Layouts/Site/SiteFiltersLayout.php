<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Orchid\Filters\SiteCategoryFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class SiteFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            SiteCategoryFilter::class,
        ];
    }
}
