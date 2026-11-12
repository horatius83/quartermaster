<?php
declare(strict_types=1);

// Front controller. Every request that isn't a real file comes through here (see .htaccess).
// The app/ folder sits next to this public folder, outside the web root.
define('PUBLIC_DIR', __DIR__);
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
    ['GET',  '/account/password',          'account_password_form'],
    ['POST', '/account/password',          'account_password_submit'],

    // Admin: the dashboard, logins and the account log
    ['GET',  '/admin',                     'admin_dashboard'],
    ['POST', '/admin/srd',                 'admin_import_srd'],
    ['GET',  '/admin/users',               'admin_users'],
    ['POST', '/admin/users',               'admin_user_create'],
    ['POST', '/admin/users/{id}/delete',   'admin_user_delete'],
    ['POST', '/admin/users/{id}/password', 'admin_user_password'],
    ['POST', '/admin/campaigns/{id}/owner','admin_campaign_owner'],
    ['GET',  '/admin/log',                 'admin_log'],
    ['GET',  '/dm/admin-account',          'admin_account_form'],
    ['POST', '/dm/admin-account',          'admin_account_submit'],

    // DM: campaigns
    ['GET',  '/dm/campaigns',              'dm_campaigns'],
    ['POST', '/dm/campaigns',              'dm_campaign_create'],
    ['POST', '/dm/campaigns/{id}/ruleset', 'dm_campaign_set_ruleset'],
    ['POST', '/dm/campaigns/switch',       'dm_campaign_switch'],
    ['POST', '/dm/campaigns/{id}',         'dm_campaign_update'],
    ['POST', '/dm/campaigns/{id}/delete',  'dm_campaign_delete'],

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
    ['GET',  '/dm/history',                'dm_history'],
    ['GET',  '/dm/history/{id}',           'dm_history_character'],

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

    // DM: offers to sell from a pack
    ['GET',  '/dm/sales',                  'dm_sales'],
    ['POST', '/dm/sales/{id}/accept',      'dm_sale_accept'],
    ['POST', '/dm/sales/{id}/counter',     'dm_sale_counter'],
    ['POST', '/dm/sales/{id}/reject',      'dm_sale_reject'],

    // Player
    ['GET',  '/shop',                      'shop_index'],
    ['GET',  '/shop/{id}',                 'shop'],
    ['POST', '/shop/{id}/add',             'shop_add'],
    ['POST', '/shop/{id}/submit',          'shop_submit'],
    ['POST', '/shop/{id}/withdraw',        'shop_withdraw'],
    ['POST', '/shop/line/{id}',            'shop_line_qty'],
    ['GET',  '/pack',                      'player_pack'],
    ['POST', '/pack/{id}/sell',            'pack_sell'],
    ['POST', '/pack/sales/{id}/withdraw',  'pack_sale_withdraw'],
    ['POST', '/pack/sales/{id}/accept',    'pack_sale_accept'],
    ['POST', '/pack/sales/{id}/decline',   'pack_sale_decline'],
    ['GET',  '/history',                   'player_history'],
];

dispatch($routes, $_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
