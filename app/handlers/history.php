<?php
declare(strict_types=1);

function dm_history(): void
{
    require_dm();
    history_page(null, '/dm/history', 'dm/history', [
        'char'       => null,
        'characters' => q_all('SELECT id, name FROM characters ORDER BY name'),
    ], 'Pack history');
}

function dm_history_character(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    history_page($characterId, '/dm/history/' . $characterId, 'dm/history', [
        'char'       => $char,
        'characters' => q_all('SELECT id, name FROM characters ORDER BY name'),
    ], $char['name'] . '’s history');
}

function player_history(): void
{
    $char = require_player();
    history_page((int)$char['id'], '/history', 'player/history', ['char' => $char], 'Pack history');
}

/** One page of pack history ($characterId null = whole party); "Show older" asks for just the next rows. */
function history_page(?int $characterId, string $path, string $view, array $data, string $title): never
{
    [$rows, $hasMore] = inventory_log_page($characterId, max(0, (int)($_GET['before'] ?? 0)));
    $rowData = ['rows' => $rows, 'hasMore' => $hasMore, 'showCharacter' => $characterId === null, 'path' => $path];
    if (htmx_target() === 'log-more') {
        fragment('partials/history_rows', $rowData);
    }
    page($view, $rowData + $data + ['active' => 'history'], $title);
}
