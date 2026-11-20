<?php
declare(strict_types=1);

function dm_history(): void
{
    require_dm();
    history_page(null, '/dm/history', 'dm/history', [
        'char'       => null,
        'party'      => false,
        'characters' => party_names(),
        'partyPill'  => party_pill(),
    ], 'Pack history');
}

function dm_history_character(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    history_page($characterId, '/dm/history/' . $characterId, 'dm/history', [
        'char'       => $char,
        'party'      => false,
        'characters' => party_names(),
        'partyPill'  => party_pill(),
    ], $char['name'] . '’s history');
}

/** Whether the DM's history pills offer the party purse: while it's shared, or its stash still holds something. */
function party_pill(): bool
{
    return shares_purse() || party_stash_held();
}

/** What went into or out of the party purse and stash. */
function dm_history_party(): void
{
    require_dm();
    history_page(null, '/dm/history/party', 'dm/history', [
        'char'       => null,
        'party'      => true,
        'characters' => party_names(),
        'partyPill'  => true,
    ], 'Party purse history', party: true);
}

/** A player's own history, and the party purse and stash's while their campaign shares one. */
function player_history(): void
{
    $char = require_player();
    $party = shares_purse();
    history_page((int)$char['id'], '/history', 'player/history', ['char' => $char, 'party' => $party], 'Pack history', party: $party);
}

/**
 * One page of pack history ($characterId null = whole party; see inventory_log_page for $party); "Show older" asks
 * for just the next rows.
 */
function history_page(?int $characterId, string $path, string $view, array $data, string $title, bool $party = false): never
{
    [$rows, $hasMore] = inventory_log_page($characterId, max(0, (int)($_GET['before'] ?? 0)), $party);
    // A character's history with the party's in it marks the party's entries; the other views don't need to.
    $rowData = [
        'rows' => $rows, 'hasMore' => $hasMore, 'path' => $path,
        'showCharacter' => $characterId === null && !$party,
        'markParty' => $characterId !== null && $party,
    ];
    if (htmx_target() === 'log-more') {
        fragment('partials/history_rows', $rowData);
    }
    page($view, $rowData + $data + ['active' => 'history'], $title);
}
