<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een Custom Audience bij Meta die gevuld wordt vanuit een nieuwsbrieflijst,
 * een nieuwsbriefsegment of een prijsgroep van deze site.
 */
class MetaAudience extends Model
{
    public const SOURCE_NEWSLETTER_LIST = 'newsletter_list';
    public const SOURCE_NEWSLETTER_SEGMENT = 'newsletter_segment';
    public const SOURCE_PRICE_GROUP = 'price_group';

    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_QUEUED = 'queued';

    protected $table = 'dashed__meta_audiences';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'member_count' => 'integer',
        'last_synced_at' => 'datetime',
        'last_full_sync_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(MetaAudienceMember::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MetaAudienceLog::class)->latest('id');
    }

    /** @return array<string, string> */
    public static function sourceTypeOptions(): array
    {
        $options = [];

        if (class_exists(\Dashed\DashedNewsletter\Models\NewsletterList::class)) {
            $options[self::SOURCE_NEWSLETTER_LIST] = __('Nieuwsbrieflijst');
            $options[self::SOURCE_NEWSLETTER_SEGMENT] = __('Nieuwsbriefsegment');
        }

        $options[self::SOURCE_PRICE_GROUP] = __('Prijsgroep');

        return $options;
    }

    /** @return array<int|string, string> */
    public static function sourceOptions(?string $type, ?string $siteId = null): array
    {
        return match ($type) {
            self::SOURCE_NEWSLETTER_LIST => class_exists(\Dashed\DashedNewsletter\Models\NewsletterList::class)
                ? \Dashed\DashedNewsletter\Models\NewsletterList::query()
                    ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                    ->orderBy('name')->pluck('name', 'id')->all()
                : [],
            self::SOURCE_NEWSLETTER_SEGMENT => class_exists(\Dashed\DashedNewsletter\Models\NewsletterSegment::class)
                ? \Dashed\DashedNewsletter\Models\NewsletterSegment::query()
                    ->with('list:id,name')
                    ->when($siteId, fn ($q) => $q->whereHas('list', fn ($l) => $l->where('site_id', $siteId)))
                    ->orderBy('name')->get()
                    ->mapWithKeys(fn ($segment) => [$segment->id => $segment->name.($segment->list ? ' ('.$segment->list->name.')' : '')])
                    ->all()
                : [],
            self::SOURCE_PRICE_GROUP => PriceGroup::query()->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    public function sourceLabel(): string
    {
        $type = self::sourceTypeOptions()[$this->source_type] ?? $this->source_type;
        $name = self::sourceOptions($this->source_type)[$this->source_id] ?? __('verwijderd');

        return $type.': '.$name;
    }
}
