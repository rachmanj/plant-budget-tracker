<?php

return [

    'enabled' => env('TRIAL_MODE', false),

    'ends_at' => env('TRIAL_ENDS_AT'),

    'updates_file' => resource_path('trial-updates.json'),

    'max_items' => (int) env('TRIAL_UPDATES_MAX', 12),

];
