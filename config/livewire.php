<?php

return [
    'temporary_file_upload' => [
        'disk' => 'local',
        'directory' => 'livewire-tmp',
        'rules' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        'preview_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'cleanup' => true,
    ],
];
