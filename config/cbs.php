<?php

return [
    /*
  
    | CBS API-backed tables
    
    |
    | Keep this false while the synonym tables are still the source of truth.
    | After the API sync tables are migrated and populated, set
    | CBS_USE_API_TABLES=true to make the existing BR models read the local
    | t_* tables instead of the syn_t_* synonyms.
    |
    */
    'use_api_tables' => env('CBS_USE_API_TABLES', true),

    'endpoints' => [
        'client_image' => env('CBS_CLIENT_IMAGE_ENDPOINT', '/api/v1/ClientImage/GetClientImage'),
        'client_search' => env('CBS_CLIENT_SEARCH_ENDPOINT', '/api/v1/Client/SearchClient'),
    ],

    'client_signature_image_type' => env('CBS_CLIENT_SIGNATURE_IMAGE_TYPE', 'S'),
    'client_image_cache_seconds' => (int) env('CBS_CLIENT_IMAGE_CACHE_SECONDS', 21600),

    'tables' => [
        'client' => env('CBS_CLIENT_TABLE', 't_Client'),
        'client_individual' => env('CBS_CLIENT_INDIVIDUAL_TABLE', 't_ClientIndividual'),
        'client_corporate' => env('CBS_CLIENT_CORPORATE_TABLE', 't_ClientCorporate'),
        'account_customer' => env('CBS_ACCOUNT_CUSTOMER_TABLE', 't_AccountCustomer'),
        'image_account' => env('CBS_IMAGE_ACCOUNT_TABLE', 't_ImageAccount'),
        'client_introducer' => env('CBS_CLIENT_INTRODUCER_TABLE', 't_ClientIntroducer'),
        'client_relation' => env('CBS_CLIENT_RELATION_TABLE', 't_ClientRelation'),
    ],
];
