<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Closure;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Models\WishlistItem;

/**
 * De verlanglijst van de huidige bezoeker. Zelfde opzet als CartHelper: een
 * uuid-cookie voor gasten, lazy claimen zodra er een ingelogde gebruiker is,
 * en een statische cache per verzoek. Anders dan de wagen wordt er geen lege
 * rij aangemaakt bij alleen kijken: hartjes en teller lezen met create=false.
 */
class WishlistHelper
{
    protected static ?Wishlist $wishlist = null;

    protected static bool $resolved = false;

    /** @var array<int, Closure(Wishlist): void> */
    protected static array $afterChange = [];

    public static function afterChange(Closure $callback): void
    {
        static::$afterChange[] = $callback;
    }

    /** Statische cache leeg, voor tests en voor de herstel-link. */
    public static function reset(): void
    {
        static::$wishlist = null;
        static::$resolved = false;
    }

    protected function cookieName(): string
    {
        return config('dashed-ecommerce-core.wishlist_cookie', 'wishlist_token');
    }

    protected function currentToken(): ?string
    {
        $token = request()->cookie($this->cookieName());

        return $token && Str::isUuid($token) ? $token : null;
    }

    /**
     * Zet een token als de cookie van de bezoeker, ook op het huidige verzoek:
     * de cookie zelf komt pas bij het volgende verzoek mee (zie
     * CartHelper::getOrCreateToken() voor het waarom).
     */
    public function useToken(string $token): void
    {
        Cookie::queue($this->cookieName(), $token, 60 * 24 * 365);
        request()->cookies->set($this->cookieName(), $token);
        static::reset();
    }

    public function getWishlist(bool $create = false): ?Wishlist
    {
        if (static::$resolved && (static::$wishlist || ! $create)) {
            return static::$wishlist;
        }

        $token = $this->currentToken();
        $user = auth()->user();

        $byToken = $token ? Wishlist::where('token', $token)->first() : null;
        $byUser = $user ? Wishlist::where('user_id', $user->id)->orderBy('id')->first() : null;

        if ($user && $byToken && $byToken->user_id !== $user->id) {
            $byToken = $this->claimForUser($user, $byToken, $byUser);
            $byUser = $byToken;
        }

        $wishlist = $byUser ?? $byToken;

        if (! $wishlist && $create) {
            $wishlist = Wishlist::create([
                'token' => $token ?? (string) Str::uuid(),
                'user_id' => $user?->id,
                'email' => $user?->email,
                'locale' => app()->getLocale(),
                'site_id' => \Dashed\DashedCore\Classes\Sites::getActive(),
            ]);
        }

        if ($wishlist && $wishlist->token !== $token) {
            $this->useToken($wishlist->token);
        }

        if ($wishlist && $user && ! $wishlist->email) {
            $wishlist->forceFill(['email' => $user->email])->save();
        }

        static::$wishlist = $wishlist;
        static::$resolved = true;

        return $wishlist;
    }

    /**
     * Gastlijst in de accountlijst schuiven: de accountlijst wint bij dubbele
     * producten, de gastlijst gaat daarna weg. Heeft de gebruiker nog geen
     * lijst, dan wordt de gastlijst gewoon van hem.
     */
    public function claimForUser(User $user, ?Wishlist $guest = null, ?Wishlist $own = null): ?Wishlist
    {
        $guest ??= ($token = $this->currentToken()) ? Wishlist::where('token', $token)->first() : null;
        $own ??= Wishlist::where('user_id', $user->id)->orderBy('id')->first();

        if (! $guest || $guest->user_id === $user->id) {
            return $own ?? $guest;
        }

        if (! $own) {
            $guest->forceFill(['user_id' => $user->id, 'email' => $guest->email ?: $user->email])->save();

            return $guest;
        }

        $bestaand = $own->items()->pluck('product_id')->all();

        foreach ($guest->items as $item) {
            if (! in_array($item->product_id, $bestaand, true)) {
                $own->items()->create(['product_id' => $item->product_id, 'price_at_add' => $item->price_at_add]);
            }
        }

        $guest->delete();
        $own->touchActivity();

        return $own;
    }

    /** @return array<int, int> */
    public function productIds(): array
    {
        return $this->getWishlist()?->items()->pluck('product_id')->all() ?? [];
    }

    public function has(Product|int $product): bool
    {
        return in_array($product instanceof Product ? $product->id : $product, $this->productIds(), true);
    }

    public function count(): int
    {
        return count($this->productIds());
    }

    public function add(Product $product): WishlistItem
    {
        $wishlist = $this->getWishlist(create: true);

        $item = $wishlist->items()->firstOrCreate(
            ['product_id' => $product->id],
            ['price_at_add' => $product->currentPrice],
        );

        $this->adoptCartEmail($wishlist);
        $wishlist->touchActivity();
        $this->changed($wishlist);

        return $item;
    }

    public function remove(Product|int $product): void
    {
        $wishlist = $this->getWishlist();

        if (! $wishlist) {
            return;
        }

        $wishlist->items()->where('product_id', $product instanceof Product ? $product->id : $product)->delete();
        $wishlist->touchActivity();
        $this->changed($wishlist);
    }

    /** Geeft de nieuwe staat: true = staat er nu op. */
    public function toggle(Product $product): bool
    {
        if ($this->has($product)) {
            $this->remove($product);

            return false;
        }

        $this->add($product);

        return true;
    }

    /** Eerste adres wint; leeg of ongeldig wordt genegeerd. */
    public function adoptEmail(?string $email): void
    {
        $wishlist = $this->getWishlist();

        if (! $wishlist || $wishlist->email || ! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $wishlist->forceFill(['email' => Str::lower(trim($email))])->save();
        $this->changed($wishlist);
    }

    /**
     * De checkout zet abandoned_email op de wagen zodra de bezoeker zijn adres
     * intypt; dat adres is dan ook van de verlanglijst. Nooit andersom.
     */
    protected function adoptCartEmail(Wishlist $wishlist): void
    {
        if ($wishlist->email) {
            return;
        }

        try {
            $email = cartHelper()->getCart()->abandoned_email ?? null;
        } catch (\Throwable) {
            return;
        }

        if ($email) {
            $wishlist->forceFill(['email' => $email])->save();
        }
    }

    protected function changed(Wishlist $wishlist): void
    {
        foreach (static::$afterChange as $callback) {
            try {
                $callback($wishlist->fresh() ?? $wishlist);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
