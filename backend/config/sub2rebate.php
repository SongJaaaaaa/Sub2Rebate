<?php

return [
    'invite_url_template' => env(
        'SUB2REBATE_INVITE_URL_TEMPLATE',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/register?inviteCode={code}'
    ),

    'sub2api_invite_url_template' => env(
        'SUB2API_INVITE_URL_TEMPLATE',
        'https://api.sjiaa.cc.cd/register?aff={code}'
    ),

    'sub2api_affiliate_page_url' => env(
        'SUB2API_AFFILIATE_PAGE_URL',
        'https://api.sjiaa.cc.cd/affiliate'
    ),

    'sub2api_base_url' => env('SUB2API_BASE_URL', 'https://api.sjiaa.cc.cd'),

    'sub2api_admin_api_key' => env('SUB2API_ADMIN_API_KEY', ''),

    'sub2api_admin_email' => env('SUB2API_ADMIN_EMAIL', ''),

    'sub2api_admin_password' => env('SUB2API_ADMIN_PASSWORD', ''),

    'sub2api_admin_timeout' => env('SUB2API_ADMIN_TIMEOUT', 10),
];
