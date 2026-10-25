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

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Keep form values across a redirect so a failed submit doesn't make the user retype them. */
function remember_input(array $fields): void
{
    $_SESSION['old_input'] = $fields;
}

/** A value kept by remember_input(); the whole set is used up by the first request that reads it. */
function old(string $key, string $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
    }
    return isset($old[$key]) ? (string)$old[$key] : $default;
}

/** Render a view file (relative to app/views, no extension) and return the HTML. */
function render(string $view, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include APP_DIR . '/views/' . $view . '.php';
    return (string)ob_get_clean();
}

/** Render a full page inside the layout. */
function page(string $view, array $data = [], string $title = 'Quartermaster'): never
{
    $content = render($view, $data);
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
