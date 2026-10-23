<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Concerns;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Notifications\Notification;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Pagination\LengthAwarePaginator;
use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;

/**
 * Gedeeld door de groeps- en productweergave van de productcontrole:
 * rechten, de controles die per site uit staan en het filteren, sorteren en
 * pagineren van de rijen (de tabel draait op eigen data, niet op een query).
 */
trait ReadsProductCompleteness
{
    public const IGNORED_CHECKS_SETTING = 'product_completeness_ignored_checks';

    /**
     * Eén rapport per request; niet publiek, dus Livewire bewaart het niet.
     *
     * @var array{groups: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}|null
     */
    protected ?array $completenessReport = null;

    /**
     * @return array{groups: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public function completenessReport(): array
    {
        return $this->completenessReport ??= ProductCompleteness::report();
    }

    /** @var array<string, array{label: string, category: string}>|null */
    protected ?array $activeChecks = null;

    /** @var array<string, array{label: string, category: string}>|null */
    protected ?array $allChecks = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_product');
    }

    /**
     * @return array<string, array{label: string, category: string}>
     */
    public function completenessChecks(): array
    {
        return $this->allChecks ??= ProductCompleteness::checks(ProductCompleteness::locales()[1]);
    }

    /**
     * De controles die op deze site meetellen.
     *
     * @return array<string, array{label: string, category: string}>
     */
    public function activeChecks(): array
    {
        return $this->activeChecks ??= array_diff_key($this->completenessChecks(), array_flip(self::ignoredChecks()));
    }

    /**
     * @return array<int, string>
     */
    public static function ignoredChecks(): array
    {
        // Zonder cache: na opslaan rendert de tabel in hetzelfde request.
        $value = Customsetting::get(self::IGNORED_CHECKS_SETTING, Sites::getActive(), '[]', disableCache: true);
        $ignored = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($ignored) ? array_values($ignored) : [];
    }

    /**
     * Keuzelijst voor filters, gegroepeerd per categorie.
     *
     * @return array<string, array<string, string>>
     */
    public function checkOptions(): array
    {
        $categories = ProductCompleteness::categories();
        $options = [];
        foreach ($this->activeChecks() as $key => $check) {
            $options[$categories[$check['category']]][$key] = $check['label'];
        }

        return $options;
    }

    /**
     * Haalt uitgeschakelde controles uit een rij.
     *
     * @param  array<int, string>  $missing
     * @return array<int, string>
     */
    protected function activeMissing(array $missing): array
    {
        return array_values(array_intersect($missing, array_keys($this->activeChecks())));
    }

    public function checkColor(string $key): string
    {
        return match ($this->completenessChecks()[$key]['category'] ?? null) {
            ProductCompleteness::CATEGORY_IMAGES => 'danger',
            ProductCompleteness::CATEGORY_TEXTS => 'warning',
            ProductCompleteness::CATEGORY_TRANSLATIONS => 'info',
            default => 'gray',
        };
    }

    protected function configureChecksAction(): Action
    {
        return Action::make('configureChecks')
            ->label(__('Controles instellen'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalDescription(__('Zet uit wat deze webshop niet gebruikt, zodat het niet als ontbrekend telt.'))
            ->fillForm(fn () => ['active' => array_keys($this->activeChecks())])
            ->schema([
                CheckboxList::make('active')
                    ->label(__('Controles die meetellen'))
                    ->options(collect($this->completenessChecks())->map(fn (array $check) => $check['label'])->all())
                    ->columns(2)
                    ->bulkToggleable(),
            ])
            ->action(function (array $data) {
                $ignored = array_values(array_diff(array_keys($this->completenessChecks()), $data['active'] ?? []));
                Customsetting::set(self::IGNORED_CHECKS_SETTING, json_encode($ignored), Sites::getActive());
                $this->activeChecks = null;

                Notification::make()->title(__('Controles opgeslagen'))->success()->send();
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function paginateRows(array $rows, ?string $sortColumn, ?string $sortDirection, int | string $page, int | string $recordsPerPage): LengthAwarePaginator
    {
        $sortColumn ??= 'missing_count';
        $sortDirection ??= $sortColumn === 'missing_count' ? 'desc' : 'asc';

        usort($rows, function (array $a, array $b) use ($sortColumn, $sortDirection) {
            $result = is_string($a[$sortColumn] ?? null)
                ? strnatcasecmp($a[$sortColumn], (string) ($b[$sortColumn] ?? ''))
                : ($a[$sortColumn] ?? 0) <=> ($b[$sortColumn] ?? 0);

            if ($result === 0) {
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            }

            return $sortDirection === 'desc' ? -$result : $result;
        });

        $perPage = $recordsPerPage === 'all' ? max(1, count($rows)) : max(1, (int) $recordsPerPage);
        $page = max(1, (int) $page);

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function matchesSearch(array $row, ?string $search, array $fields): bool
    {
        $search = mb_strtolower(trim((string) $search));
        if ($search === '') {
            return true;
        }

        foreach ($fields as $field) {
            if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Openbaar-filter: '1', '0' of leeg (alles).
     *
     * @param  array<string, mixed>  $row
     */
    protected function matchesPublic(array $row, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $row['public'] === (bool) $value;
    }
}
