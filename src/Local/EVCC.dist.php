<?php

namespace Local;

/**
 * Extend EVCC handler class
 *
 * Overwrite the method if needed as you like
 */
class EVCC extends \Core\EVCC
{
    /**
     * Only called if the summary matches the defined prefix
     *
     * @param array  &$event      Event
     * @param string $summary     Stripped by configured prefix
     * @param string $description Full event body
     */
    protected function checkEvent(array &$event, string $summary, ?string $description): void
    {
        /*
            Manipulate direct the $event as you like, BUT DO NOT REMOVE keys, only change values!

            $event = [
                'timestamp' => 1798650900,
                'start'     => 'Wed, 30 Dec 2026 18:15:00 +0100',
                'target'    => 100,
                'strategy'  => [
                    "late"         => true,            // Flag if strategy is "late all"
                    'continuous'   => false,
                    'precondition' => 604800,
                ],
                'summary'     => 'Event summary ...',  // WITHOUT leading token
                'description' => '',
                'comment'     => [],                   // For your use, shown in frontend
            ]

        */

        // ------------------------------------------------------------------

        /**
         * Raise charging target during winter by 5 %
         */

        // $m = +date('m');
        //
        // if ($m >= 11 || $m <= 2) {
        //     // Raise by 5 % ...
        //     $event['target'] += 5;
        //     // ... but max. up to 100 % :-)
        //     $event['target'] = min($event['target'], 100);
        // }

        /**
         * Extend precondition time during winter by 15 min.
         */

        // if (!$event['strategy']['late'] && $event['strategy']['precondition'] > 0) {
        //     $m = +date('n');

        //     if ($m >= 11 || $m <= 2) {
        //         $event['strategy']['precondition'] += 15 * 60;  // Seconds
        //         $event['comment'][] = 'Extended late charging';
        //     }
        // }

        /**
         * End charging during winter 10 min. earlier
         */

        // $m = +date('n');

        // if ($m >= 11 || $m <= 2) {
        //     $event['timestamp'] -= 10 * 60;  // Seconds
        //     $event['comment'][] = 'Shifted late charging by 15 min.';
        // }
    }
}
