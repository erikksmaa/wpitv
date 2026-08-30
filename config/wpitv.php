<?php

return [
    'whatsapp' => [
        'admin_number' => env('WPITV_WHATSAPP_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Institutional Featured Videos
    |--------------------------------------------------------------------------
    |
    | These public YouTube videos complement the latest database video on the
    | homepage. They intentionally stay outside ms_video so they never affect
    | public listings, search, pagination, or dashboard statistics.
    |
    */
    'featured_videos' => [
        'profile' => [
            'label' => 'Profil Kampus',
            'title' => 'Profil Institut Widya Pratama',
            'subtitle' => 'Kenali lebih dekat Institut Widya Pratama.',
            'context' => 'Kenali Institut Widya Pratama',
            'youtube_id' => 'FSxax6qS41A',
        ],
        'pmb' => [
            'label' => 'Informasi PMB',
            'title' => 'Informasi Penerimaan Mahasiswa Baru',
            'subtitle' => 'Informasi penerimaan mahasiswa baru Institut Widya Pratama.',
            'context' => 'Informasi calon mahasiswa',
            'youtube_id' => 'YmzJSsWjGgI',
        ],
    ],
];
