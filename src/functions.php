<?php
/*
 * Copyright 2017 Sean Proctor
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
   this file contains all the re-usable functions for the calendar
*/

namespace PhpCalendar;

use Symfony\Component\HttpFoundation\Request;

define('PHPC_CONFIG_FILE', realpath(__DIR__.'/../config.php'));
define('PHPC_VERSION', '2.1.0');
define('PHPC_DEBUG', 1);

function __($msg)
{
    global $translator;

    if (empty($translator)) {
        return $msg;
    }

    return $translator->trans($msg);
}

function __p($context, $msg)
{
    global $translator;

    if (empty($translator)) {
        return $msg;
    }

    $id = $context . "\04" . $msg;
    $result = $translator->trans($context . "\04" . $msg);
    if ($result == $id) {
        return $msg;
    } else {
        return $result;
    }
}

function minute_pad($minute)
{
    return sprintf('%02d', $minute);
}

function escape_entities($string)
{
    return htmlspecialchars($string, ENT_NOQUOTES, "UTF-8");
}

/**
 * @param bool $val
 * @return string
 */
function asbool($val)
{
    return $val ? "1" : "0";
}

/**
 * @param \DateTimeInterface $date
 * @param int                $date_format
 * @param bool               $hours24
 * @return string
 */
function format_datetime(\DateTimeInterface $date, $date_format, $hours24)
{
    return format_date($date, $date_format) . ' '
    . __('at') . ' ' . format_time($date, $hours24);
}

/**
 * @param \DateTimeInterface $date
 * @param int                $date_format
 * @return string
 */
function format_date(\DateTimeInterface $date, $date_format)
{
    $month = short_month_name($date->format('n'));
    $day = $date->format('j');
    $year = $date->format('Y');
    
    switch ($date_format) {
        default:
        case 0:
            return "$month $day, $year";
        case 1:
            return "$year $month $day";
        case 2:
            return "$day $month $year";
    }
}

/**
 * @param \DateTimeInterface $date
 * @param int                $date_format
 * @return string
 */
function format_date_short(\DateTimeInterface $date, $date_format)
{
    switch ($date_format) {
        default:
        case 0: // Month Day Year
            return $date->format('n\/j\/Y');
        case 1: // Year Month Day
            return $date->format('Y\-n\-j');
        case 2: // Day Month Year
            return $date->format('j\-n\-Y');
    }
}

/**
 * @param \DateTimeInterface $date
 * @param bool               $hour24
 * @return string
 */
function format_time(\DateTimeInterface $date, $hour24)
{
    if ($hour24) {
        return $date->format('G\:i');
    } else {
        return $date->format('g\:i\ A');
    }
}

// parses a description and adds the appropriate mark-up
/**
 * @param string $text
 * @return string
 */
function parse_desc($text)
{
    return \Parsedown::instance()->parse($text);
}

/**
 * @param int $year
 * @return int
 */
function days_in_year($year)
{
    return 365 + intval(create_datetime(1, 1, $year)->format('L'));
}

/**
 * @param \DateTimeInterface $date1
 * @param \DateTimeInterface $date2
 * @return int
 */
function days_between(\DateTimeInterface $date1, \DateTimeInterface $date2)
{
    $year1 = intval($date1->format('Y'));
    $year2 = intval($date2->format('Y'));
    if ($year2 < $year1) {
        return -days_between($date2, $date1);
    }
    $days = 0;
    for ($year = $year1; $year < $year2; $year++) {
        $days += days_in_year($year);
    }
    // add day of year of $date2, subtract day of year of $date1
    $days += intval($date2->format('z'));
    $days -= intval($date1->format('z'));
    return $days;
}

/**
 * @return string[]
 */
function get_languages()
{
    static $langs = null;

    $translation_path = realpath(__DIR__.'/../translations');
    if (!empty($langs)) {
        return $langs;
    }

    // create links for each existing language translation
    $handle = opendir($translation_path);

    if (!$handle) {
        soft_error("Error reading locale directory.");
    }

    $langs = array('en' => 'en');
    while (($filename = readdir($handle)) !== false) {
        $pathname = "$translation_path/$filename";
        if (strncmp($filename, ".", 1) == 0 || !is_dir($pathname)) {
            continue;
        }
        if (file_exists("$pathname/LC_MESSAGES/messages.mo")) {
            $langs[$filename] = $filename;
        }
    }

    closedir($handle);

    return $langs;
}

// returns the number of days in the week before the 
//  taking into account whether we start on sunday or monday
/**
 * @param int $month
 * @param int $day
 * @param int $year
 * @param int $week_start
 * @return int
 */
function day_of_week($month, $day, $year, $week_start)
{
    return day_of_week_date(_create_datetime($month, $day, $year), $week_start);
}

// returns the number of days in the week before the 
//  taking into account whether we start on sunday or monday
function day_of_week_date(\DateTimeInterface $date, $week_start)
{
    $days = intval($date->format('w'));

    return ($days + 7 - $week_start) % 7;
}

// returns the number of days in $month
/**
 * @param int $month
 * @param int $year
 * @return int
 */
function days_in_month($month, $year)
{
    return intval(_create_datetime($month, 1, $year)->format('t'));
}

//returns the number of weeks in $month
/**
 * @param int $month
 * @param int $year
 * @param int $week_start
 * @return number
 */
function weeks_in_month($month, $year, $week_start)
{
    $days = days_in_month($month, $year);

    // days not in this month in the partial weeks
    $days_before_month = day_of_week($month, 1, $year, $week_start);
    $days_after_month = 6 - day_of_week($month, $days, $year, $week_start);

    // add up the days in the month and the outliers in the partial weeks
    // divide by 7 for the weeks in the month
    return intval(($days_before_month + $days + $days_after_month) / 7);
}

/**
 * @param int $year
 * @param int $week_start
 * @return int
 */
function weeks_in_year($year, $week_start)
{
    // This is true for ISO, not US
    if ($week_start == 1) {
        return _create_datetime(12, 28, $year)->format("W");
    }
    // else
    return intval((day_of_week(1, 1, $year, $week_start) + days_in_year($year)) / 7);
}

/**
 * @param \DateTimeInterface $date
 * @param int $week_start
 * @return int[]
 */
// return the week number corresponding to the $day.
function week_of_year(\DateTimeInterface $date, $week_start)
{
    $day = $date->format('d');
    $month = $date->format('m');
    $year = $date->format('Y');
    
    // week_start = 1 uses ISO 8601 and contains the Jan 4th,
    //   Most other places the first week contains Jan 1st
    //   There are a few outliers that start weeks on Monday and use
    //   Jan 1st for the first week. We'll ignore them for now.
    if ($week_start == 1) {
        $year_contains = 4;
    } else {
        $year_contains = 1;
    }
    
    // if the week is in December and contains Jan $year_contains, it's a week
    // from next year
    if ($month == 12 && $day - 24 >= $year_contains) {
        $year++;
        $month = 1;
        $day -= 31;
    }
    
    // $day is the first day of the week relative to the current month,
    // so it can be negative. If it's in the previous year, we want to use
    // that negative value, unless the week is also in the previous year,
    // then we want to switch to using that year.
    if ($day < 1 && $month == 1 && $day > $year_contains - 7) {
        $day_of_year = $day - 1;
    } else {
        $day_of_year = $date->format('z');
        $year = $date->format('Y');
    }

    /* Days in the week before Jan 1. */
    $days_before_year = day_of_week(1, $year_contains, $year, $week_start);

    // Days left in the week
    $days_left = 8 - day_of_week_date($date, $week_start) - $year_contains;

    /* find the number of weeks by adding the days in the week before
    * the start of the year, days up to $day, and the days left in
    * this week, then divide by 7 */
    return [intval(($days_before_year + $day_of_year + $days_left) / 7), $year];
}

/**
 * @param Context            $context
 * @param string             $action
 * @param \DateTimeInterface $date
 * @return string
 */
function action_date_url(Context $context, $action, \DateTimeInterface $date)
{
    return action_url(
        $context,
        $action,
        ['year' => $date->format('Y'), 'month' => $date->format('n'), 'day' => $date->format('j')]
    );
}

/**
 * @param Context $context
 * @param string  $action
 * @param string  $eid
 * @return string
 */
function action_event_url(Context $context, $action, $eid)
{
    return action_url($context, $action, array("eid" => $eid));
}

/**
 * @param Context $context
 * @param string  $action
 * @param string  $eid
 * @return string
 */
function action_occurrence_url(Context $context, $action, $oid)
{
    return action_url($context, $action, array("oid" => $oid));
}

/**
 * @param Context  $context
 * @param string   $action
 * @param string[] $parameters
 * @return string
 */
function action_url(Context $context, $action, $parameters = array())
{
    $parameters['phpcid'] = $context->calendar->getCid();
    $url = "{$context->script}?action={$action}";
    foreach ($parameters as $key => $value) {
        $url .= "&$key=$value";
    }
    return $url;
}

/**
 * @param Request $request
 * @return string
 */
function change_lang_url(Request $request, $lang)
{
    $uri = $request->getRequestUri();
    if (strpos($uri, "?") !== false) {
        $uri .= 'amp;';
    } else {
        $uri .= '?';
    }
    return $uri . $lang;
}

/**
 * @param Context       $context
 * @param string        $text
 * @param string        $action
 * @param string[]|null $args
 * @param string|null   $classes
 * @param string|null   $id
 * @return string
 */
function create_action_link(Context $context, $text, $action, $args = null, $classes = null, $id = null)
{
    if (!$args) {
        $args = array();
    }
    if (!array_key_exists("phpcid", $args)) {
        $args["phpcid"] = htmlentities($context->getCalendar()->cid);
    }

    $url = $context->script . '?action=' . htmlentities($action);
    foreach ($args as $key => $value) {
        if (empty($value)) {
            continue;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                $url .= "&amp;" . htmlentities("{$key}[]=$v");
            }
        } else {
            $url .= "&amp;" . htmlentities("$key=$value");
        }
    }

    return "<a href=\"$url\"" . ($classes ? " class=\"$classes\"" : '') . ($id ? " id=\"$id\"" :  '') . ">$text</a>";
}

// takes a menu $html and appends an entry
/**
 * @param Context $context
 * @param string  $action
 * @param string  $text
 * @return string
 */
function menu_item(Context $context, $action, $text)
{
    $url = htmlentities(action_url($context, $action));
    $active = $context->getAction() == $action ? " active" : "";
    return "<li class=\"nav-item$active\"><a class=\"nav-link\" href=\"$url\">$text</a></li>";
}

/**
 * @param string   $title
 * @param string[] $values // Array of URL => title
 * @return string // dropdown box that will change the page to the URL from $values when an element is selected
 */
function create_dropdown($title, $values)
{
    $output = "<div class=\"nav-item dropdown\">\n"
    ."    <a class=\"nav-link dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\" role=\"button\" aria-haspopup=\"true\" aria-expanded=\"false\">$title</a>\n"
    ."    <div class=\"dropdown-menu\">\n";
    foreach ($values as $key => $value) {
        $output .= "        <a class=\"dropdown-item\" href=\"$key\">$value</a>\n";
    }
    return $output . "    </div></div>";
}

/**
 * @return string[]
 */
function get_timezone_list()
{
    $timezones = array();
    $timezones[__("Default")] = "";
    foreach (timezone_identifiers_list() as $timezone) {
        $sp = explode("/", $timezone, 2);
        $continent = $sp[0];
        if (empty($sp[1])) {
            $timezones[$continent] = $timezone;
        } else {
            $area = $sp[1];
            if (empty($timezones[$continent])) {
                $timezones[$continent] = array();
            }
            $timezones[$continent][$timezone] = $area;
        }
    }
    return $timezones;
}

// takes a number of the month, returns the name
/**
 * @param int $month
 * @return string
 */
function month_name($month)
{
    $month = ($month - 1) % 12 + 1;
    switch ($month) {
        case 1:
            return __('January');
        case 2:
            return __('February');
        case 3:
            return __('March');
        case 4:
            return __('April');
        case 5:
            return __('May');
        case 6:
            return __('June');
        case 7:
            return __('July');
        case 8:
            return __('August');
        case 9:
            return __('September');
        case 10:
            return __('October');
        case 11:
            return __('November');
        case 12:
            return __('December');
    }
    return ''; // This can't happen
}

/**
 * @param int $day
 * @return string
 */
function day_name($day)
{
    $day = $day % 7;

    switch ($day) {
        case 0:
            return __('Sunday');
        case 1:
            return __('Monday');
        case 2:
            return __('Tuesday');
        case 3:
            return __('Wednesday');
        case 4:
            return __('Thursday');
        case 5:
            return __('Friday');
        case 6:
            return __('Saturday');
    }
    return ''; // This can't happen
}

/**
 * @param int $day
 * @return string
 */
function short_day_name($day)
{
    $day = $day % 7;

    switch ($day) {
        case 0:
            return __('Sun');
        case 1:
            return __('Mon');
        case 2:
            return __('Tue');
        case 3:
            return __('Wed');
        case 4:
            return __('Thu');
        case 5:
            return __('Fri');
        case 6:
            return __('Sat');
    }
    return ''; // This can't happen
}

/**
 * @param int $month
 * @return string
 */
function short_month_name($month)
{
    $month = ($month - 1) % 12 + 1;

    switch ($month) {
        case 1:
            return __('Jan');
        case 2:
            return __('Feb');
        case 3:
            return __('Mar');
        case 4:
            return __('Apr');
        case 5:
            return __('May');
        case 6:
            return __('Jun');
        case 7:
            return __('Jul');
        case 8:
            return __('Aug');
        case 9:
            return __('Sep');
        case 10:
            return __('Oct');
        case 11:
            return __('Nov');
        case 12:
            return __('Dec');
    }
    return ''; // This can't happen
}

function print_update_form()
{
    global $script;

    echo "<!DOCTYPE html>
<html>
  <head>
    <title>PHP-Calendar Update</title>
  </head>
  <body>
    <h2>PHP-Calendar Updater</h2>
    <p>Your PHP-Calendar database needs to be updated. You should make a backup of your existing database before running the updater.
    <p><a href=\"$script?update=1\">Update now</a>
  </body>
</html>";
}

/**
 * @param \DateTimeInterface $date
 * @return bool|string
 */
function index_of_date(\DateTimeInterface $date)
{
    return $date->format('Y-m-d');
}

/**
 * @param \DateTimeInterface $date
 * @return boolean
 */
function is_today(\DateTimeInterface $date)
{
    return days_between($date, new \DateTime()) == 0;
}

/**
 * normalize date after month or day were incremented or decremented
 *
 * @param $month
 * @param $day
 * @param $year
 */
function normalize_date(&$month, &$day, &$year)
{
    if ($month < 1) {
        $month = 12;
        $year--;
    } elseif ($month > 12) {
        $month = 1;
        $year++;
    }
    if ($day <= 0) {
        $month--;
        if ($month < 1) {
            $month += 12;
            $year--;
        }
        $day += days_in_month($month, $year);
    } elseif ($day > days_in_month($month, $year)) {
        $day -= days_in_month($month, $year);
        $month++;
        if ($month > 12) {
            $month -= 12;
            $year++;
        }
    }
}

/**
 * @param \DateTimeInterface $date
 * @return string
 */
function sqlDate(\DateTimeInterface $date)
{
    $utcDate = new \DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
    $utcDate->setTimezone(new \DateTimeZone('UTC'));
    return $utcDate->format('Y-m-d H:i:s');
}

/**
 * @param string $dateStr
 * @return \DateTime
 */
function fromSqlDate($dateStr)
{
    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStr, new \DateTimeZone('UTC'));
    $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    return $date;
}

/**
 * @param string $dateStr
 * @return \DateTimeImmutable
 */
function fromSqlDateImmutable($dateStr)
{
    $date = fromSqlDate($dateStr);
    return new \DateTimeImmutable($date->format('c'));
}

/**
 * @param string $timestamp
 * @return \DateTime
 */
function fromTimestamp($timestamp)
{
    $date = \DateTime::createFromFormat('U', $timestamp, new \DateTimeZone('UTC'));
    $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    return $date;
}

/**
 * @param string $timestamp
 * @return \DateTimeImmutable
 */
function fromTimestampImmutable($timestamp)
{
    $date = fromTimestamp($timestamp);
    return new \DateTimeImmutable($date->format('c'));
}

/**
 * @param int $month
 * @param int $day
 * @param int $year
 * @return \DateTime
 */
function _create_datetime($month, $day, $year)
{
    return new \DateTime(sprintf("%04d-%02d-%02d", $year, $month, $day));
}

/**
 * @param int $month
 * @param int $day
 * @param int $year
 * @return \DateTime
 */
function create_datetime($month, $day, $year)
{
    normalize_date($month, $day, $year);
    return _create_datetime($month, $day, $year);
}

/**
 * @param Calendar           $calendar
 * @param User               $user
 * @param \DateTimeInterface $from
 * @param \DateTimeInterface $to
 * @return array
 */
function get_occurrences_by_day(Calendar $calendar, User $user, \DateTimeInterface $from, \DateTimeInterface $to)
{
    $all_occurrences = $calendar->getOccurrencesByDateRange($from, $to);
    $occurrences_by_day = array();

    foreach ($all_occurrences as $occurrence) {
        if (!$occurrence->canRead($user)) {
            continue;
        }

        $end = $occurrence->getEnd();

        $start = $occurrence->getStart();

        if ($start > $from) {
            $diff = new \DateInterval("P0D");
        } else { // the event started before the range we're showing
            $diff = $from->diff($start);
        }

        // put the event in every day until the end
        for ($date = $start->add($diff); $date < $to && $date <= $end; $date = $date->add(new \DateInterval("P1D"))) {
            $key = index_of_date($date);
            if (!isset($occurrences_by_day[$key])) {
                $occurrences_by_day[$key] = array();
            }
            if (sizeof($occurrences_by_day[$key]) == $calendar->getMaxDisplayEvents()) {
                $occurrences_by_day[$key][] = null;
            }
            if (sizeof($occurrences_by_day[$key]) > $calendar->getMaxDisplayEvents()) {
                continue;
            }
            $occurrences_by_day[$key][] = $occurrence;
        }
    }
    return $occurrences_by_day;
}
