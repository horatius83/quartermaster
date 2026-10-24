<?php
declare(strict_types=1);

// Front controller. Every request that isn't a real file comes through here (see .htaccess).
// The app/ folder sits next to this public folder, outside the web root.
$appDir = is_dir(__DIR__ . '/../app') ? __DIR__ . '/../app' : __DIR__ . '/app';
require $appDir . '/bootstrap.php';

$routes = [
    // Auth & setup
    ['GET',  '/',                          'home'],
    ['GET',  '/login',                     'login_form'],
    ['POST', '/login',                     'login_submit'],
    ['POST', '/logout',                    'logout_submit'],
    ['GET',  '/setup',                     'setup_form'],
    ['POST', '/setup',                     'setup_submit'],

    // DM: dashboard & party
    ['GET',  '/dm',                        'dm_dashboard'],
    ['GET',  '/dm/party',                  'dm_party'],
    ['POST', '/dm/party',                  'dm_party_create'],
    ['POST', '/dm/party/{id}/password',    'dm_party_password'],
    ['POST', '/dm/party/{id}/delete',      'dm_party_delete'],

    // DM: inventories
    ['GET',  '/dm/inventory',              'dm_inventory_index'],
    ['GET',  '/dm/inventory/{id}',         'dm_inventory'],
    ['POST', '/dm/inventory/{id}/give',    'dm_inventory_give'],
    ['POST', '/dm/inventory/{id}/sheet',   'dm_inventory_sheet'],
    ['POST', '/dm/inv/{id}/qty',           'dm_inv_qty'],
    ['POST', '/dm/inv/{id}/move',          'dm_inv_move'],
    ['POST', '/dm/inv/{id}/delete',        'dm_inv_delete'],

    // DM: stores
    ['GET',  '/dm/stores',                 'dm_stores_index'],
    ['POST', '/dm/stores',                 'dm_store_create'],
    ['GET',  '/dm/stores/{id}',            'dm_store'],
    ['POST', '/dm/stores/{id}',            'dm_store_update'],
    ['POST', '/dm/stores/{id}/toggle',     'dm_store_toggle'],
    ['POST', '/dm/stores/{id}/delete',     'dm_store_delete'],
    ['POST', '/dm/stores/{id}/stock',      'dm_stock_add'],
    ['POST', '/dm/stock/{id}',             'dm_stock_update'],
    ['POST', '/dm/stock/{id}/delete',      'dm_stock_delete'],

    // DM: catalog
    ['GET',  '/dm/catalog',                'dm_catalog'],
    ['POST', '/dm/catalog',                'dm_catalog_create'],
    ['GET',  '/dm/catalog/suggest',        'dm_catalog_suggest'],
    ['POST', '/dm/catalog/{id}',           'dm_catalog_update'],
    ['POST', '/dm/catalog/{id}/delete',    'dm_catalog_delete'],

    // DM: carts
    ['GET',  '/dm/carts',                  'dm_carts'],
    ['POST', '/dm/carts/{id}/approve',     'dm_cart_approve'],
    ['POST', '/dm/carts/{id}/decline',     'dm_cart_decline'],
    ['POST', '/dm/cart-items/{id}',        'dm_cart_item_qty'],

    // Player
    ['GET',  '/shop',                      'shop_index'],
    ['GET',  '/shop/{id}',                 'shop'],
    ['POST', '/shop/{id}/add',             'shop_add'],
    ['POST', '/shop/{id}/submit',          'shop_submit'],
    ['POST', '/shop/{id}/withdraw',        'shop_withdraw'],
    ['POST', '/shop/line/{id}',            'shop_line_qty'],
    ['GET',  '/pack',                      'player_pack'],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}

dispatch($routes, $_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
