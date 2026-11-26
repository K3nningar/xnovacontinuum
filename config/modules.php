<?php

return [
    'factions' => [
        'enabled' => (bool) env('MODULE_FACTIONS_ENABLED', true),
    ],
    'officers' => [
        'enabled' => (bool) env('MODULE_OFFICERS_ENABLED', true),
        'disabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('MODULE_OFFICERS_DISABLED', ''))))),
    ],
];
