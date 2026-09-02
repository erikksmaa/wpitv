<?php

return [
    'idle_timeout_minutes' => (int) env('ADMIN_SESSION_IDLE_TIMEOUT', 30),
    'absolute_lifetime_hours' => (int) env('ADMIN_SESSION_ABSOLUTE_LIFETIME', 8),
];
