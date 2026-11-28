<?php

namespace Core;

class EVCC
{
    public static function factory(array $options): self
    {
        $ref = new \ReflectionClass(
            is_subclass_of('Local\EVCC', 'Core\EVCC') ? 'Local\EVCC' : 'Core\EVCC'
        );

        return $ref->newInstance($options);
    }

    /**
     * Class constructor
     *
     * @param array $options
     */
    final public function __construct(array $options)
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
    final public function fetch(): bool
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
    final public function analyse(): array
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
                $event = [
                    'timestamp'   => $ts,
                    'start'       => date('r', $ts),
                    'target'      => 0,
                    'strategy'    => '',
                    'summary'     => '',
                    'description' => '',
                    'comment'     => []
                ];

                if (preg_match('~^(\d+) *% (.*)$~', $summary, $matches)) {
                    $event['target']  = +$matches[1];
                    $event['summary'] = trim($matches[2]);
                } elseif (preg_match('~^(\d+) +km (.*)$~', $summary, $matches)) {
                    $event['target'] = round(
                        $this->options['vehicle']['min-soc'] +
                            $this->options['vehicle']['consumption'] * $matches[1] /
                            $this->options['vehicle']['capacity']
                    );
                    $event['summary'] = $matches[1] . ' km ' . trim($matches[2]);
                } else {
                    // Just "<summary-start> text ..." > 100 %
                    $event['target']  = 100;
                    $event['summary'] = $summary;
                }

                // Max. 100 %
                $event['target'] = min($event['target'], 100);

                if (preg_match(
                    '~^' . $this->options['calendar']['summary-start'] . '(.*)$~m',
                    $ICalEvent->description ?: '',
                    $matches
                )) {
                    $description = trim(preg_replace('~[^a-z0-9. ]+~i', '', $matches[1]));
                    $description = preg_split('~ +~', $description);

                    $precondition = floatval($description[1]) * 3600;

                    $event['strategy'] = [
                        'late'         => $precondition == 604800,
                        'continuous'   => strtolower($description[0]) == 'true',
                        'precondition' => $precondition
                    ];

                    $ICalEvent->description = trim(str_replace($matches[0], '', $ICalEvent->description));
                } else {
                    // hours > sec.
                    $switch = $this->options['precondition']['switch'] * 3600;

                    // Late all = 7 days
                    $precondition =
                        $event['timestamp'] - time() > $switch ? 604800 : $this->options['precondition']['late'] * 3600;

                    // Mit möglichen Unterbrechungen bei dynamischem Tarif
                    $event['strategy'] = [
                        'late'         => $precondition == 604800,
                        'continuous'   => $this->options['precondition']['continuous'],
                        'precondition' => $precondition
                    ];
                }

                $event['description'] = $ICalEvent->description;

                $this->checkEvent($event, $summary, $ICalEvent->description);

                // Recalc
                $event['start'] = date('r', $event['timestamp']);

                $events[] = $event;
            }
        }

        uasort(
            $events,
            static function (array $a, array $b) {
                return $a['timestamp'] <=> $b['timestamp'];
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
    final public function plan(): array
    {
        if (!is_file($this->eventsFile) || !filesize($this->eventsFile)) {
            $events = $this->analyse();
        } else {
            $events = json_decode(file_get_contents($this->eventsFile), true);
        }

        $event = array_shift($events);

        $vehicle = $this->options['vehicle']['db'];

        $strategy = sprintf(
            '{"continuous":%s,"precondition":%d}',
            ['false', 'true'][$event['strategy']['continuous']],
            $event['strategy']['precondition']
        );

        $result = array_merge(
            $this->curlPOST(
                sprintf(
                    'vehicles/%s/plan/soc/%d/%s',
                    $vehicle,
                    $event['target'],
                    date('c', $event['timestamp'])
                )
            ),
            $this->curlPOST(sprintf('vehicles/%s/plan/strategy', $vehicle), $strategy)
        );

        // Send phases only if not FALSE
        if ($this->options['loadpoint']['phases'] !== false) {
            $loadpoint = $this->options['loadpoint'];

            $result = array_merge(
                $result,
                $this->curlPOST(sprintf('/loadpoints/%d/phases/%d', $loadpoint['number'], $loadpoint['phases']))
            );
        }

        return $result;
    }

    // ----------------------------------------------------------------------
    // PROTECTED
    // ----------------------------------------------------------------------

    /**
     * Only called if the summary matches the defined prefix
     *
     * Do nothing here, overwrite in \Local\EVCC.php
     *
     * @param array  &$event      Event
     * @param string $summary     Stripped by configured prefix
     * @param string $description Full event body
     */
    protected function checkEvent(array &$event, string $summary, ?string $description): void {}

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
