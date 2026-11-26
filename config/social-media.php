<?php

return [
    'document' => [
        'title' => 'Laporan Aktivitas Media Sosial Bulanan',
        'organization' => 'WPiTV Institut Widya Pratama Pekalongan',
        'city' => 'Pekalongan',
        'approver_position' => 'Kepala UPT Studio TV',
        'author_position' => 'News Division',
    ],
    // Explicit assignment by user ID; existing admin roles receive no operator grants.
    'managers' => array_filter(array_map('trim', explode(',', env('SOCIAL_MEDIA_MANAGER_IDS', '')))),
    'reviewers' => array_filter(array_map('trim', explode(',', env('SOCIAL_MEDIA_REVIEWER_IDS', '')))),
];
