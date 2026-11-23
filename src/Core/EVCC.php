<?php

namespace Core;

final class EVCC
{
    /**
     * Class constructor
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        $cacheDir = $options['cache-dir'] ?? '/tmp';

        $this->icsFile    = $cacheDir . '/evcc.ics';
        $this->eventsFile = $cacheDir . '/events.json';
    }

    /**
     * Fetch Google calendar data
     *
     * @return boolean
     */
    public function fetch(): bool
    {
        // Force
        is_file($this->icsFile) && unlink($this->icsFile);
        is_file($this->eventsFile) && unlink($this->eventsFile);

        $ch = curl_init($this->options['calendar']['url']);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        file_put_contents($this->icsFile, curl_exec($ch));

        return is_file($this->icsFile) && filesize($this->icsFile) > 0;
    }

    /**
     * Analyse Google calendar data
     *
     * @return array
     */
    public function analyse(): array
    {
        if (!is_file($this->icsFile) || !filesize($this->icsFile)) {
            $this->fetch();
        }

        $ical = new \ICal\ICal();

        $ical->initFile($this->icsFile);

        $now    = time();
        $events = [];

        /** @var \ICal\Event */
        foreach ($ical->events() as $ICalEvent) {
            if (
                // phpcs:ignore PSR2.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace
                !preg_match(
                    '~^' . $this->options['calendar']['summary-start'] . '(.*)~',
                    $ICalEvent->summary,
                    $matches
                )
            ) {
                // Event summary starts not with required token
                continue;
            }

            $ts = strtotime($ICalEvent->dtstart);

            if ($ts > $now) {
                // Text after the starting token
                $summary = trim($matches[1]);

                // Init
                $event = ['ts' => $ts, 'start' => date('r', $ts)];

                if (preg_match('~^(\d+) *% (.*)$~', $summary, $matches)) {
                    $event['target']  = +$matches[1];
                    $event['comment'] = trim($matches[2]);
                } elseif (preg_match('~^(\d+) +km (.*)$~', $summary, $matches)) {
                    $event['target'] = round(
                        $this->options['vehicle']['min-soc'] +
                            $this->options['vehicle']['consumption'] * $matches[1] /
                            $this->options['vehicle']['capacity']
                    );
                    $event['comment'] = $matches[1] . ' km ' . trim($matches[2]);
                } else {
                    // Just "<summary-start> text ..." > 100 %
                    $event['target']  = 100;
                    $event['comment'] = $summary;
                }

                // Max. 100 %
                $event['target'] = min($event['target'], 100);

                if ($description = $ICalEvent->description) {
                    $description = preg_replace('~[^a-z0-9,]+~i', '', $description);
                    $description = explode(',', $description);

                    $event['strategy'] = sprintf(
                        '{"continuous":%s,"precondition":%d}',
                        $description[0],
                        $description[1] * 60
                    );
                }

                $events[] = $event;
            }
        }

        uasort(
            $events,
            static function (array $a, array $b) {
                return $a['ts'] <=> $b['ts'];
            }
        );

        file_put_contents($this->eventsFile, json_encode($events, JSON_PRETTY_PRINT));

        return $events;
    }

    /**
     * Send data to evcc
     *
     * @return array  Messages
     */
    public function plan(): array
    {
        if (!is_file($this->eventsFile) || !filesize($this->eventsFile)) {
            $events = $this->analyse();
        } else {
            $events = json_decode(file_get_contents($this->eventsFile), true);
        }

        $event = array_shift($events);

        $result = [];

        if (empty($event['strategy'])) {
            // No data in calendar event body

            // hours > sec.
            $switch = $this->options['precondition']['switch'] * 3600;

            // Late all = 7 days
            $precondition = $event['ts'] - time() > $switch ? 604800 : $this->options['precondition']['late'] * 3600;

            // Mit möglichen Unterbrechungen bei dynamischem Tarif
            $event['strategy'] = sprintf(
                '{"continuous":%s,"precondition":%d}',
                $this->options['precondition']['continuous'] ? 'true' : 'false',
                $precondition
            );
        }

        $result = array_merge(
            $result,
            $this->curlPOST(
                sprintf(
                    'vehicles/%s/plan/soc/%d/%s',
                    $this->options['vehicle']['db'],
                    $event['target'],
                    date('c', $event['ts'])
                )
            )
        );

        $result = array_merge(
            $result,
            $this->curlPOST(
                sprintf(
                    'vehicles/%s/plan/strategy',
                    $this->options['vehicle']['db'],
                ),
                $event['strategy']
            )
        );

        // Send phases only if not FALSE
        if ($this->options['loadpoint']['phases'] !== false) {
            $result = array_merge(
                $result,
                $this->curlPOST(
                    sprintf(
                        '/loadpoints/%d/phases/%d',
                        $this->options['loadpoint']['number'],
                        $this->options['loadpoint']['phases']
                    )
                )
            );
        }

        return $result;
    }

    // ----------------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------------

    /** @var array */
    private $options = [];

    /**
     * Raw calendar ICS data
     * @var string
     */
    private $icsFile;

    /**
     * Analysed calendar data, filtered for plan data
     * @var string
     */
    private $eventsFile;

    /**
     * curl POST wrapper
     *
     * @param  string $path
     * @param  string $data
     * @return array  Messages
     */
    private function curlPOST(string $path, string $data = ''): array
    {
        $url = sprintf(
            'http://%s:%s/api/%s',
            $this->options['evcc']['host'] ?? 'localhost',
            $this->options['evcc']['port'] ?? 7070,
            $path
        );

        $result = ["> POST $url"];

        $ch = curl_init($url);

        // Force POST
        curl_setopt($ch, CURLOPT_POST, true);

        if ($data != '') {
            $result[] = "> $data";
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result[] = '< ' . trim(curl_exec($ch));

        return $result;
    }
}
