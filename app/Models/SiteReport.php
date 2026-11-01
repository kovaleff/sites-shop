<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Events\SiteReportResolved;
use Database\Factories\SiteReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reason', 'comment', 'status'])]
class SiteReport extends Model
{
    /** @use HasFactory<SiteReportFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', ReportStatus::New);
    }

    public function resolve(ReportStatus $status = ReportStatus::Resolved): void
    {
        if ($this->status !== ReportStatus::New) {
            return;
        }

        $this->status = $status;
        $this->resolved_at = now();
        $this->save();

        SiteReportResolved::dispatch($this, $status);
    }
}
