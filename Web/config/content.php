<?php

use App\Models\NewsArticle;

return [
    'news' => [
        'model' => NewsArticle::class,
        'title' => 'News & Market Headlines',
        'singular' => 'News article',
        'description' => 'Publish original ABS editorial content or concise source-linked market updates with clear attribution and publication controls.',
        'create_label' => 'Publish Market Headline',
        'fields' => [],
    ],
];
