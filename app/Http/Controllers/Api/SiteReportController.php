<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteReportRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SiteReportController extends Controller
{
    /**
     * Жалоба на объявление из каталога.
     */
    public function store(StoreSiteReportRequest $request, Site $site): JsonResponse
    {
        if ($site->isOwnedBy($request->user())) {
            throw ValidationException::withMessages([
                'site' => 'Нельзя пожаловаться на собственное объявление.',
            ]);
        }

        abort_unless($site->isPubliclyVisible(), 404);

        $alreadyReported = $site->reports()
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadyReported) {
            throw ValidationException::withMessages([
                'site' => 'Вы уже жаловались на это объявление.',
            ]);
        }

        $report = $site->reports()->make([
            'reason' => $request->string('reason')->toString(),
            'comment' => $request->input('comment'),
            'status' => ReportStatus::New,
        ]);

        $report->user_id = $request->user()->id;
        $report->save();

        return response()->json([
            'data' => [
                'id' => $report->id,
                'status' => $report->status->value,
                'createdAt' => $report->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
