<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anonymous carts for shoppers who have not logged in.
 *
 * A guest cart is identified by a random bearer token that the client keeps
 * (localStorage / app storage) and sends back in the X-Guest-Token header.
 * Only its SHA-256 hash is stored, so a database leak does not expose live carts.
 * When the shopper logs in, the guest cart is merged into their own cart.
 */
class GuestCartService
{
    public const HEADER = 'X-Guest-Token';

    /** Same ceiling as the cart's quantity validation. */
    public const MAX_LINE_QUANTITY = 100;

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function tokenFrom(Request $request): ?string
    {
        $token = $request->header(self::HEADER) ?: $request->input('guest_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function find(?string $token): ?Cart
    {
        if ($token === null) {
            return null;
        }

        return Cart::whereNull('user_id')
            ->where('guest_token_hash', self::hash($token))
            ->first();
    }

    /**
     * @return array{0: Cart, 1: string} the new cart and its plain token
     */
    public function create(): array
    {
        $token = 'gc_' . Str::random(60);

        $cart = Cart::create(['guest_token_hash' => self::hash($token)]);

        return [$cart, $token];
    }

    /**
     * Move every line of the guest cart into the user's cart and delete the guest
     * cart. Lines that match an existing one (same product, variant and
     * personalisation) are combined. Stock is not enforced here: the cart response
     * flags lines that exceed stock, and checkout re-checks it.
     *
     * Returns the number of guest lines merged (0 when the token is unknown).
     */
    public function mergeInto(User $user, ?string $token): int
    {
        if ($token === null) {
            return 0;
        }

        return DB::transaction(function () use ($user, $token) {
            $guestCart = Cart::whereNull('user_id')
                ->where('guest_token_hash', self::hash($token))
                ->lockForUpdate()
                ->first();

            if (!$guestCart) {
                return 0;
            }

            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
            $existing = $userCart->items()->get();
            $merged = 0;

            foreach ($guestCart->items as $guestItem) {
                $match = $existing->first(fn (CartItem $item) =>
                    $item->product_id === $guestItem->product_id
                    && $item->product_variant_id === $guestItem->product_variant_id
                    && ($item->custom_options ?? []) == ($guestItem->custom_options ?? []));

                if ($match) {
                    $match->update([
                        'quantity' => min($match->quantity + $guestItem->quantity, self::MAX_LINE_QUANTITY),
                    ]);
                } else {
                    $guestItem->update(['cart_id' => $userCart->id]);
                    $existing->push($guestItem);
                }

                $merged++;
            }

            // Lines moved to the user's cart no longer belong to it, so this only
            // removes the ones that were combined into an existing line.
            $guestCart->delete();
            $userCart->touch();

            return $merged;
        });
    }
}
