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

Assume the summary start token is `::`.

### Define with percent

`:: 90 % Need to go somewhere`

### Define with km

`:: 90 km Need to go somewhere`

- Calculates the needed SOC for 90 km according to your avg. consumption and battery capacity
- Add the min. SOC (should be left after the km driven)

### Simplified

`:: Need to go somewhere`

Always 100 %

### Strategy

evcc expect strategy JSON matching `{"continuous":<bool>,"precondition":<seconds>}`

You can override the strategy as follows, regardless of the global configuration.

Enter this in the event body (not case-sensitive):

`(true|false), <hours>`

_Please note that **hours** expected here with a **dot** as decimal separator_

Let’s say you want to charge continuously for an event and use the last 30 minutes for pre-heating:

`true, 0.5`

## Frontend

Located in `public_html/`

## Cron job

`1 * * * * php /path/to/evcc-cal/bin/cron.php >> /path/to/evcc-cal/logs/cron.log`
