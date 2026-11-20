<?php
declare(strict_types=1);

// Players send the DM requests and comments from their Requests page. The DM replies, which closes the request and
// shows the reply to the player, or closes it without one.

function dm_requests(): void
{
    require_dm();
    page('dm/requests', [
        'active' => 'requests',
        'open'   => request_list("r.status = 'open'"),
        'recent' => request_list("r.status = 'closed'", [], 'r.closed_at DESC, r.id DESC', 10),
    ], 'Player requests');
}

/**
 * A request in this campaign. One that isn't was either withdrawn by its player while the page was open, or belongs to
 * another of the DM's campaigns, picked in another tab since this page loaded.
 */
function dm_request(int $requestId): array
{
    $request = request_detail($requestId);
    if ($request) {
        return $request;
    }
    $elsewhere = q_val(
        'SELECT cp.name FROM requests r JOIN characters ch ON ch.id = r.character_id JOIN campaigns cp ON cp.id = ch.campaign_id
          WHERE r.id = ? AND cp.owner_user_id = ?',
        [$requestId, current_user()['id']]
    );
    htmx_fail($elsewhere !== null
        ? "That request is in {$elsewhere}, but you’ve since switched to " . current_campaign()['name'] . '. Switch back to answer it.'
        : 'That request has already been closed or withdrawn. Reload the page to see it.');
}

/** Close an open request, with the DM's reply or none, and answer with its card and the sidebar badge. */
function close_request(array $request, string $reply, string $message): never
{
    $done = q(
        "UPDATE requests SET status = 'closed', dm_reply = ?, closed_at = ? WHERE id = ? AND status = 'open'",
        [$reply, now(), $request['id']]
    )->rowCount();
    if (!$done) {
        htmx_fail('That request has already been closed or withdrawn. Reload the page to see it.');
    }
    // The character can leave the party (taking their requests) between the update and this read.
    $closed = request_detail((int)$request['id']) ?? htmx_fail("{$request['character_name']} has left the party, and their request with them.");
    toast($message);
    echo render('partials/request_card', ['request' => $closed]);
    echo render('partials/badge_oob', ['id' => 'request-badge', 'count' => open_request_count()]);
    exit;
}

/** Answer the request; the player sees the reply on their Requests page. */
function dm_request_reply(int $requestId): void
{
    require_dm();
    $request = dm_request($requestId);
    $reply = post_text('dm_reply', MAX_REQUEST_REPLY_CHARS);
    if ($reply === '') {
        htmx_fail('Write a reply first, or use Close without reply.');
    }
    close_request($request, $reply, "Replied to {$request['character_name']}.");
}

/** Close the request with no reply, when it's been dealt with at the table. */
function dm_request_close(int $requestId): void
{
    require_dm();
    $request = dm_request($requestId);
    close_request($request, '', "Closed {$request['character_name']}’s request.");
}
