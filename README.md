# EVCC + Google calendar

## Manages evcc charging planning via a Google Calendar

## Setup

Clone the repo and run `composer update`

If you have not installed composer global, get it from `https://getcomposer.org/download/`

## Configuration

```sh
cp config/config.dist.php config/config.php
nano config/config.php
```

The **Google calendar URL**, the evcc **vehicle db-ID** and the **loadpoint number** are required here.

Test your configuration with `php /path/to/evcc-cal/bin/test.php`

## Calendar events

Assume the event relevant start token is `::`.

### Define with percent

`:: 90 % Need to go somewhere`

### Define with km

`:: 90 km Need to go somewhere`

- Calculates the needed SOC for 90 km according to your avg. consumption and battery capacity
- Add the min. SOC which should be left after the km driven

### Simplified

`:: Need to go somewhere`

Always 100 %

### Strategy

evcc expect strategy JSON matching `{"continuous":<bool>,"precondition":<seconds>}`

You can override the strategy as follows, regardless of the global configuration.

Enter this somewhere in the **event body** in a single line (not case-sensitive):

`:: (true|false) <hours>`

_Please note that **hours** expected here with a **dot** as decimal separator_

Let’s say you want to charge continuously for an event but use at least the last 30 minutes for pre-heating:

```text
:: true 0.5
Event remarks, shown in frontend as tooltip on the event summary
```

## Extend control logic

If there is a local implementation class in `src/Local/EVCC.php` it will be used to extend the control logic.

```sh
cp src/Local/EVCC.dist.php src/Local/EVCC.php
nano src/Local/EVCC.php
```

Edit the method `checkEvent()` and adjust the `$event` array as needed.

## Frontend

Located in `public_html/`

## Cron job

`1 * * * * php /path/to/evcc-cal/bin/cron.php >> /path/to/evcc-cal/logs/cron.log`
