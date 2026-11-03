<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Site;

use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Jobs\VerifySiteDomain;
use App\Models\Site;
use App\Orchid\Layouts\Site\SiteConversationsTable;
use App\Orchid\Layouts\Site\SiteEditLayout;
use App\Orchid\Layouts\Site\SiteModerationLayout;
use App\Orchid\Layouts\Site\SitePaymentsTable;
use App\Orchid\Layouts\Site\SiteReportsTable;
use App\Orchid\Layouts\Site\SiteReviewsTable;
use App\Orchid\Layouts\Site\SiteStatusLayout;
use App\Orchid\Layouts\Site\SiteVerificationLayout;
use Closure;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SiteEditScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Site $site): iterable
    {
        $site->load(['owner', 'categories']);

        return [
            'site' => [
                'title' => $site->title,
                'domain' => $site->domain,
                'description' => $site->description,
                'price' => $site->price,
                'monthly_revenue' => $site->monthly_revenue,
                'has_trademark' => $site->has_trademark,
                'status' => $site->status->value,
                'moderation_status' => $site->moderation_status->value,
                'moderation_note' => $site->moderation_note,
                'verification_checked_at' => $site->verification_checked_at?->format('d.m.Y H:i') ?? '—',
                'verification_error' => $site->verification_error ?? '—',
                'verified' => $site->verified,
                'verification_filename' => $site->verification_filename,
                'paid_until' => $site->paid_until?->format('d.m.Y'),
                'categories' => $site->categories->pluck('id')->all(),
            ],
            'owner_name' => $site->owner?->name,
            'payments' => $site->subscriptionPayments()
                ->latest()
                ->limit(10)
                ->get(),
            'conversations' => $site->conversations()
                ->with(['buyer', 'seller'])
                ->latest('last_message_at')
                ->limit(10)
                ->get(),
            'reports' => $site->reports()
                ->with('reporter')
                ->latest()
                ->limit(20)
                ->get(),
            'reviews' => $site->reviews()
                ->with('author')
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Объявление';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        /** @var Site|null $site */
        $site = request()->route('site');

        return $site?->domain ?? 'Карточка объявления биржи.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.sites',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        /** @var Site|null $site */
        $site = request()->route('site');

        return [
            Button::make('Изменить статус')
                ->icon('bs.arrow-repeat')
                ->method('changeStatus')
                ->canSee($site !== null && $site->status->allowedTransitions() !== []),

            Button::make('Проверить домен')
                ->icon('bs.shield-check')
                ->method('verifyDomain')
                ->canSee($site !== null && ! $site->verified),

            Button::make('Сохранить')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(SiteEditLayout::class)
                ->title('Коммерческие данные')
                ->description('Название, домен, описание, цена, доход и тематики объявления.'),

            Layout::block(SiteStatusLayout::class)
                ->title('Статус'),

            Layout::block(SiteModerationLayout::class)
                ->title('Модерация'),

            Layout::block(SiteVerificationLayout::class)
                ->title('Подтверждение владения и подписка'),

            Layout::block(SitePaymentsTable::class)
                ->title('Последние платежи'),

            Layout::block(SiteConversationsTable::class)
                ->title('Последние диалоги'),

            Layout::block(SiteReportsTable::class)
                ->title('Жалобы пользователей'),

            Layout::block(SiteReviewsTable::class)
                ->title('Отзывы покупателей'),
        ];
    }

    /**
     * Save the commercial fields, categories and verification state.
     */
    public function save(Request $request, Site $site): void
    {
        $payload = $request->input('site', []);
        $payload['domain'] = $this->normalizeDomain($payload['domain'] ?? null);

        $request->merge(['site' => $payload]);

        $request->validate([
            'site.title' => ['required', 'string', 'min:3', 'max:255'],
            'site.domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
                function (string $attribute, mixed $value, Closure $fail) use ($site): void {
                    $isTaken = Site::query()
                        ->where('domain', $value)
                        ->whereKeyNot($site->getKey())
                        ->whereIn('status', [ListingStatus::Active, ListingStatus::Inactive])
                        ->exists();

                    if ($isTaken) {
                        $fail('Домен уже используется другим объявлением.');
                    }
                },
            ],
            'site.description' => ['required', 'string', 'min:50', 'max:5000'],
            'site.price' => ['required', 'numeric', 'min:0'],
            'site.monthly_revenue' => ['required', 'numeric', 'min:0'],
            'site.has_trademark' => ['boolean'],
            'site.categories' => ['required', 'array', 'min:1'],
            'site.categories.*' => ['integer', 'exists:categories,id'],
            'site.verified' => ['boolean'],
            'site.moderation_status' => ['sometimes', 'string', Rule::in(ModerationStatus::values())],
            'site.moderation_note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $site): void {
            $site->fill(collect($request->input('site'))
                ->only(['title', 'domain', 'description', 'price', 'monthly_revenue', 'has_trademark'])
                ->all())
                ->save();

            $site->categories()->sync($request->input('site.categories', []));

            $site->markVerified((bool) $request->input('site.verified', false));

            if ($request->filled('site.moderation_status')) {
                $site->setModeration(
                    ModerationStatus::from($request->input('site.moderation_status')),
                    $request->input('site.moderation_note'),
                );
            }
        });

        Toast::info('Объявление сохранено.');
    }

    /**
     * Change the site status following the allowed transitions.
     */
    public function changeStatus(Request $request, Site $site): void
    {
        $request->validate([
            'site.status' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($site): void {
                    $allowed = array_map(
                        fn (ListingStatus $status) => $status->value,
                        $site->status->allowedTransitions(),
                    );

                    if (! in_array($value, $allowed, true)) {
                        $fail('Переход в этот статус недопустим.');
                    }
                },
            ],
        ]);

        try {
            $site->changeStatus(ListingStatus::from($request->input('site.status')));
        } catch (DomainException $exception) {
            Toast::error($exception->getMessage());

            return;
        }

        Toast::info('Статус объявления изменён.');
    }

    /**
     * Повторный запуск проверки владения доменом.
     */
    public function verifyDomain(Site $site): void
    {
        VerifySiteDomain::dispatch($site->getKey());

        Toast::info('Проверка домена запущена.');
    }

    /**
     * Жалоба принята: объявление снимается с публикации.
     */
    public function resolveReport(Request $request, Site $site): void
    {
        $report = $site->reports()->find((int) $request->input('report'));

        if ($report === null) {
            Toast::error('Жалоба не найдена.');

            return;
        }

        $report->resolve(ReportStatus::Resolved);
        $site->setModeration(ModerationStatus::Rejected, 'Жалоба принята: '.$report->reason->label());

        Toast::warning('Жалоба принята, объявление снято с публикации.');
    }

    public function dismissReport(Request $request, Site $site): void
    {
        $report = $site->reports()->find((int) $request->input('report'));

        if ($report === null) {
            Toast::error('Жалоба не найдена.');

            return;
        }

        $report->resolve(ReportStatus::Dismissed);

        Toast::info('Жалоба отклонена.');
    }

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = Str::lower(trim($domain));

        return rtrim(preg_replace('/^www\./', '', $domain) ?? $domain, '/');
    }
}
