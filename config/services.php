<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'models/gemini-1.5-flash'),
    ],

    'openai' => [ // Kept for reference or fallback
        'api_key' => env('OPENAI_API_KEY'),
        'embedding_model' => 'text-embedding-3-small',
        'chat_model' => 'gpt-4o',
    ],

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT'),
        'index_name' => env('PINECONE_INDEX_NAME'),
        'host' => env('PINECONE_HOST'),
    ],

];
