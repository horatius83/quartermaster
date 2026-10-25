<?php
declare(strict_types=1);

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['config'][$key] ?? $default;
}

/** Base path when the app lives in a subfolder (e.g. example.com/quartermaster). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $configured = config('base_path');
        if (is_string($configured)) {
            return $base = rtrim($configured, '/');
        }
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        // When the root .htaccess rewrites into public/, the browser never sees "/public".
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $base = ($dir !== '' && !str_starts_with($uri, $dir . '/') && $uri !== $dir) ? '' : $dir;
    }
    return $base;
}

function url(string $path = '/'): string
{
    return base_path() . $path;
}

function is_htmx(): bool
{
    return isset($_SERVER['HTTP_HX_REQUEST']);
}

/** True when htmx asked for just one element (not a boosted full-page navigation). */
function htmx_target(): ?string
{
    if (!is_htmx() || isset($_SERVER['HTTP_HX_BOOSTED'])) {
        return null;
    }
    return $_SERVER['HTTP_HX_TARGET'] ?? null;
}

function redirect(string $path): never
{
    $to = url($path);
    if (is_htmx()) {
        header('HX-Redirect: ' . $to);
    } else {
        header('Location: ' . $to, true, 303);
    }
    exit;
}

function flash(string $message, string $kind = 'ok'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'kind' => $kind];
}

/** Take the queued flash messages, or only those of one $kind (leaving the rest for the layout's toasts). */
function take_flashes(?string $kind = null): array
{
    $all = $_SESSION['flash'] ?? [];
    $taken = $kind === null ? $all : array_values(array_filter($all, fn($f) => $f['kind'] === $kind));
    $left = $kind === null ? [] : array_values(array_filter($all, fn($f) => $f['kind'] !== $kind));
    if ($left) {
        $_SESSION['flash'] = $left;
    } else {
        unset($_SESSION['flash']);
    }
    return $taken;
}

/** The request's path within the app, without the base path or query string (e.g. "/dm/party"). */
function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return '/' . trim($path, '/');
}

/** How long input kept by back_with_error() waits for its page before it's thrown away. */
const OLD_INPUT_TTL = 300;

/**
 * Send a failed form submit back to $path with an error, keeping the $keep fields so they needn't be retyped.
 * Only fields named in $keep are kept, so a password or token can't end up in the session by accident.
 * A field posted as a list (checkboxes, multi-selects) is kept as a list of strings.
 */
function back_with_error(string $path, string $message, array $keep): never
{
    $clip = fn($v) => mb_substr($v, 0, 255);
    $fields = [];
    foreach (array_intersect_key($_POST, array_flip($keep)) as $key => $value) {
        if (is_string($value)) {
            $fields[$key] = $clip($value);
        } elseif (is_array($value)) {
            $fields[$key] = array_map($clip, array_values(array_filter($value, 'is_string')));
        }
    }
    $_SESSION['old_input'] = ['path' => '/' . trim($path, '/'), 'fields' => $fields, 'at' => time()];
    flash($message, 'error');
    redirect($path);
}

/**
 * Take the input kept by back_with_error(), but only on the page it was sent back to: other pages (another
 * tab, say) leave it alone. Once it's older than OLD_INPUT_TTL it's thrown away, so it can't turn up later.
 */
function take_old_input(): array
{
    $kept = $_SESSION['old_input'] ?? null;
    if (!$kept) {
        return [];
    }
    if (time() - $kept['at'] > OLD_INPUT_TTL) {
        unset($_SESSION['old_input']);
        return [];
    }
    if ($kept['path'] !== request_path()) {
        return [];
    }
    unset($_SESSION['old_input']);
    return $kept['fields'];
}

/** Render a view file (relative to app/views, no extension) and return the HTML. */
function render(string $view, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include APP_DIR . '/views/' . $view . '.php';
    return (string)ob_get_clean();
}

/** Render a full page inside the layout. Views get $old: input kept from a failed form submit, if any. */
function page(string $view, array $data = [], string $title = 'Quartermaster'): never
{
    $content = render($view, $data + ['old' => take_old_input()]);
    echo render('layout', ['content' => $content, 'title' => $title, 'active' => $data['active'] ?? '']);
    exit;
}

/** Output a fragment (for htmx swaps). */
function fragment(string $view, array $data = []): never
{
    echo render($view, $data);
    exit;
}

/** Queue a toast message for an htmx response (shown by assets/app.js). */
function toast(string $message, string $kind = 'ok'): void
{
    $GLOBALS['toasts'][] = ['message' => $message, 'kind' => $kind];
    header('HX-Trigger: ' . json_encode(['qm-toast' => ['items' => $GLOBALS['toasts']]]), true);
}

/** For htmx requests: show an error toast and leave the page untouched. */
function htmx_fail(string $message): never
{
    header('HX-Reswap: none');
    toast($message, 'error');
    exit;
}

function abort(int $code, string $message = ''): never
{
    if (is_htmx() && !isset($_SERVER['HTTP_HX_BOOSTED'])) {
        // htmx ignores 4xx/5xx bodies by default, so answer 200 with a toast instead.
        htmx_fail($message ?: 'Something went wrong.');
    }
    if (!is_htmx()) {
        http_response_code($code); // boosted htmx navigations need a 2xx to swap
    }
    echo render('layout', [
        'title'   => 'Error',
        'active'  => '',
        'content' => '<main class="main"><h1 class="page-title">Hmm.</h1><p>' . e($message ?: 'Something went wrong.') . '</p><p><a href="' . e(url('/')) . '">Back to safety</a></p></main>',
    ]);
    exit;
}

function post_str(string $key, int $max = 255): string
{
    $v = trim((string)($_POST[$key] ?? ''));
    return mb_substr($v, 0, $max);
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? '';
    return is_numeric($v) ? (int)$v : $default;
}

function post_float(string $key, float $default = 0.0): float
{
    $v = $_POST[$key] ?? '';
    return is_numeric($v) ? (float)$v : $default;
}

function get_str(string $key, int $max = 100): string
{
    return mb_substr(trim((string)($_GET[$key] ?? '')), 0, $max);
}

function fmt_weight(float|string $w): string
{
    $w = round((float)$w, 2);
    return rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') . ' lb';
}

function category_tag(string $name, string $slug): string
{
    return '<span class="tag tag-' . e($slug) . '">' . e($name) . '</span>';
}

function time_ago(?string $ts): string
{
    if (!$ts) {
        return '';
    }
    $diff = time() - strtotime($ts . ' UTC');
    return match (true) {
        $diff < 60     => 'just now',
        $diff < 3600   => intdiv($diff, 60) . ' min ago',
        $diff < 86400  => intdiv($diff, 3600) . ' hr ago',
        $diff < 172800 => 'yesterday',
        default        => intdiv($diff, 86400) . ' days ago',
    };
}
