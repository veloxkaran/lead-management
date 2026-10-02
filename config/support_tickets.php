<?php

return [

    /*
    | A ticket that isn't Completed counts as overdue once it has been open
    | longer than this many hours for its priority (tickets have no due
    | date). Drives the "Overdue" view on the Support Tickets list.
    */
    'overdue_after_hours' => [
        'urgent' => (int) env('TICKET_OVERDUE_URGENT_HOURS', 24),
        'high' => (int) env('TICKET_OVERDUE_HIGH_HOURS', 72),
        'medium' => (int) env('TICKET_OVERDUE_MEDIUM_HOURS', 168),
        'low' => (int) env('TICKET_OVERDUE_LOW_HOURS', 336),
    ],

];
