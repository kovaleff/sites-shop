<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Site;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Orchid\Layouts\Site\SiteConversationsTable;
use App\Orchid\Layouts\Site\SiteEditLayout;
use App\Orchid\Layouts\Site\SitePaymentsTable;
use App\Orchid\Layouts\Site\SiteStatusLayout;
use App\Orchid\Layouts\Site\SiteVerificationLayout;
use Closure;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            Layout::block(SiteVerificationLayout::class)
                ->title('Подтверждение владения и подписка'),

            Layout::block(SitePaymentsTable::class)
                ->title('Последние платежи'),

            Layout::block(SiteConversationsTable::class)
                ->title('Последние диалоги'),
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
        ]);

        DB::transaction(function () use ($request, $site): void {
            $site->fill(collect($request->input('site'))
                ->only(['title', 'domain', 'description', 'price', 'monthly_revenue', 'has_trademark'])
                ->all())
                ->save();

            $site->categories()->sync($request->input('site.categories', []));

            $site->markVerified((bool) $request->input('site.verified', false));
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

    private function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = Str::lower(trim($domain));

        return rtrim(preg_replace('/^www\./', '', $domain) ?? $domain, '/');
    }
}
