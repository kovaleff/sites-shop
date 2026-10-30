<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    /**
     * Метаданные пагинации в camelCase (contracts/cabinet-api.yaml).
     *
     * @return array{page: int, perPage: int, total: int, lastPage: int, from: int|null, to: int|null}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
