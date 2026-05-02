<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Internal traffic exclusions
    |--------------------------------------------------------------------------
    |
    | Visits / cart events from these emails or IPs are flagged
    | `is_internal = true` so admin dashboards and reports don't get
    | polluted by team-member testing activity. Rows are still stored
    | (so we can audit what happened during a debug session) — just
    | hidden from analytics by default via the `external()` scope.
    |
    | Both lists accept comma-separated env values, e.g.
    |   ANALYTICS_INTERNAL_EMAILS="alice@x.com,bob@y.com"
    |   ANALYTICS_INTERNAL_IPS="125.229.72.117,1.2.3.4"
    |
    | IPs are matched exactly. To catch the whole DHCP pool of a
    | location, list each IP that browser actually exits from.
    |
    */
    'internal_emails' => array_filter(array_map(
        'trim',
        explode(',', (string) env('ANALYTICS_INTERNAL_EMAILS', 'crazyflyfly@gmail.com')),
    )),

    'internal_ips' => array_filter(array_map(
        'trim',
        explode(',', (string) env('ANALYTICS_INTERNAL_IPS', '125.229.72.117')),
    )),
];
