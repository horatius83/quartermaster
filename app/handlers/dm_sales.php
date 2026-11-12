<?php
declare(strict_types=1);

// Players offer things from their packs for sale. The DM accepts the asking price, makes a counter-offer
// (which the player then accepts or declines, on their pack page), or rejects the offer.

function dm_sales(): void
{
    require_dm();
    page('dm/sales', [
        'active'  => 'sales',
        'pending' => sale_list("sa.status = 'pending'"),
        'waiting' => sale_list("sa.status = 'countered'", [], 'sa.countered_at'),
        'recent'  => sale_list("sa.status IN ('sold','rejected','declined')", [], 'sa.decided_at DESC', 6),
    ], 'Sale offers');
}

/** A sale in this campaign, or a 404. */
function dm_sale(int $saleId): array
{
    return sale_detail($saleId) ?? abort(404, 'No such offer.');
}

/** Answer with the sale's card and the sidebar badge. */
function sale_card_response(int $saleId, string $message): never
{
    toast($message);
    echo render('partials/sale_card', ['sale' => sale_detail($saleId)]);
    echo render('partials/badge_oob', ['id' => 'sale-badge', 'count' => pending_sale_count()]);
    exit;
}

/** Pay the player's asking price, even after countering it. */
function dm_sale_accept(int $saleId): void
{
    require_dm();
    $sale = dm_sale($saleId);
    try {
        settle_sale($saleId, byPlayer: false);
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    sale_card_response($saleId, "Bought {$sale['quantity']} × {$sale['name']} from {$sale['character_name']} for " . coins((int)$sale['asking_cp']) . '.');
}

/** Offer the player another price for the lot. A counter-offer still waiting on the player can be changed. */
function dm_sale_counter(int $saleId): void
{
    require_dm();
    $sale = dm_sale($saleId);
    $offer = parse_price(post_str('offer', 40));
    if ($offer === null || $offer <= 0 || $offer > MAX_SALE_CP) {
        htmx_fail('Name your counter-offer, like “6 gp” or “4 gp 5 sp”.');
    }
    $done = q(
        "UPDATE sales SET status = 'countered', offer_cp = ?, dm_note = ?, countered_at = ? WHERE id = ? AND status IN " . OPEN_SALE_STATUSES,
        [$offer, post_str('dm_note', 500), now(), $saleId]
    )->rowCount();
    if (!$done) {
        htmx_fail('That offer has already been settled.');
    }
    sale_card_response($saleId, "Offered {$sale['character_name']} " . coins($offer) . " for the {$sale['name']}.");
}

/** Turn the offer down, or take back a counter-offer the player hasn't answered. The items stay in the pack. */
function dm_sale_reject(int $saleId): void
{
    require_dm();
    $sale = dm_sale($saleId);
    $done = q(
        "UPDATE sales SET status = 'rejected', dm_note = ?, decided_at = ? WHERE id = ? AND status IN " . OPEN_SALE_STATUSES,
        [post_str('dm_note', 500), now(), $saleId]
    )->rowCount();
    if (!$done) {
        htmx_fail('That offer has already been settled.');
    }
    sale_card_response($saleId, "Rejected {$sale['character_name']}’s offer for the {$sale['name']}.");
}
