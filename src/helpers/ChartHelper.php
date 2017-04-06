<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\helpers;

use Craft;
use craft\config\DbConfig;
use craft\db\Query;
use DateTime;
use yii\base\Exception;


/**
 * Class ChartHelper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class ChartHelper
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the data for a run chart, based on a given DB query, start/end dates, and the desired time interval unit.
     *
     * The query’s SELECT clause should already be set to a column aliased as `value`.
     *
     * The $options array can override the following defaults:
     *
     *  - `intervalUnit`  - The time interval unit to use ('hour', 'day', 'month', or 'year').
     *                     By default, a unit will be decided automatically based on the start/end date duration.
     *  - `categoryLabel` - The label to use for the chart categories (times). Defaults to "Date".
     *  - `valueLabel`    - The label to use for the chart values. Defaults to "Value".
     *  - `valueType`     - The type of values that are being plotted ('number', 'currency', 'percent', 'time'). Defaults to 'number'.
     *
     * @param Query    $query      The DB query that should be used
     * @param DateTime $startDate  The start of the time duration to select (inclusive)
     * @param DateTime $endDate    The end of the time duration to select (exclusive)
     * @param string   $dateColumn The column that represents the date
     * @param array    $options    Any customizations that should be made over the default options
     *
     * @return array
     * @throws Exception
     */
    public static function getRunChartDataFromQuery(Query $query, DateTime $startDate, DateTime $endDate, string $dateColumn, array $options = []): array
    {
        // Setup
        $options = array_merge([
            'intervalUnit' => null,
            'categoryLabel' => Craft::t('app', 'Date'),
            'valueLabel' => Craft::t('app', 'Value'),
            'valueType' => 'number',
        ], $options);

        $databaseType = Craft::$app->getConfig()->getDb()->driver;

        if ($options['intervalUnit'] && in_array($options['intervalUnit'], ['year', 'month', 'day', 'hour'], true)) {
            $intervalUnit = $options['intervalUnit'];
        } else {
            $intervalUnit = self::getRunChartIntervalUnit($startDate, $endDate);
        }

        switch ($databaseType) {
            case DbConfig::DRIVER_MYSQL:
                $yearSql = "YEAR([[{$dateColumn}]])";
                $monthSql = "MONTH([[{$dateColumn}]])";
                $daySql = "DAY([[{$dateColumn}]])";
                $hourSql = "HOUR([[{$dateColumn}]])";
                break;
            case DbConfig::DRIVER_PGSQL:
                $yearSql = "EXTRACT(YEAR FROM [[{$dateColumn}]])";
                $monthSql = "EXTRACT(MONTH FROM [[{$dateColumn}]])";
                $daySql = "EXTRACT(DAY FROM [[{$dateColumn}]])";
                $hourSql = "EXTRACT(HOUR FROM [[{$dateColumn}]])";
                break;
            default:
                throw new Exception('Unsupported connection type: '.$databaseType);
        }

        switch ($intervalUnit) {
            case 'year':
                switch ($databaseType) {
                    case DbConfig::DRIVER_MYSQL:
                        $sqlDateFormat = '%Y-01-01';
                        break;
                    case DbConfig::DRIVER_PGSQL:
                        $sqlDateFormat = 'YYYY-01-01';
                        break;
                    default:
                        throw new Exception('Unsupported connection type: '.$databaseType);
                }
                $phpDateFormat = 'Y-01-01';
                $sqlGroup = [$yearSql];
                break;
            case 'month':
                switch ($databaseType) {
                    case DbConfig::DRIVER_MYSQL:
                        $sqlDateFormat = '%Y-%m-01';
                        break;
                    case DbConfig::DRIVER_PGSQL:
                        $sqlDateFormat = 'YYYY-MM-01';
                        break;
                    default:
                        throw new Exception('Unsupported connection type: '.$databaseType);
                }
                $phpDateFormat = 'Y-m-01';
                $sqlGroup = [$yearSql, $monthSql];
                break;
            case 'day':
                switch ($databaseType) {
                    case DbConfig::DRIVER_MYSQL:
                        $sqlDateFormat = '%Y-%m-%d';
                        break;
                    case DbConfig::DRIVER_PGSQL:
                        $sqlDateFormat = 'YYYY-MM-DD';
                        break;
                    default:
                        throw new Exception('Unsupported connection type: '.$databaseType);
                }
                $phpDateFormat = 'Y-m-d';
                $sqlGroup = [$yearSql, $monthSql, $daySql];
                break;
            case 'hour':
                switch ($databaseType) {
                    case DbConfig::DRIVER_MYSQL:
                        $sqlDateFormat = '%Y-%m-%d %H:00:00';
                        break;
                    case DbConfig::DRIVER_PGSQL:
                        $sqlDateFormat = 'YYYY-MM-DD HH24:00:00';
                        break;
                    default:
                        throw new Exception('Unsupported connection type: '.$databaseType);
                }
                $phpDateFormat = 'Y-m-d H:00:00';
                $sqlGroup = [$yearSql, $monthSql, $daySql, $hourSql];
                break;
            default:
                throw new Exception('Invalid interval unit: '.$intervalUnit);
        }

        switch ($databaseType) {
            case DbConfig::DRIVER_MYSQL:
                $select = "DATE_FORMAT([[{$dateColumn}]], '{$sqlDateFormat}') AS [[date]]";
                break;
            case DbConfig::DRIVER_PGSQL:
                $select = "to_char([[{$dateColumn}]], '{$sqlDateFormat}') AS [[date]]";
                break;
            default:
                throw new Exception('Unsupported connection type: '.$databaseType);
        }

        $sqlGroup[] = '[[date]]';

        // Execute the query
        $results = $query
            ->addSelect([$select])
            ->andWhere([
                'and',
                ['>=', $dateColumn, Db::prepareDateForDb($startDate)],
                ['<', $dateColumn, Db::prepareDateForDb($endDate)]
            ])
            ->groupBy($sqlGroup)
            ->orderBy(['[[date]]' => SORT_ASC])
            ->all();

        // Assemble the data
        $rows = [];

        $cursorDate = $startDate;
        $endTimestamp = $endDate->getTimestamp();

        while ($cursorDate->getTimestamp() < $endTimestamp) {
            // Do we have a record for this date?
            $cursorDateUtc = (clone $cursorDate)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format($phpDateFormat);
            $formattedCursorDate = $cursorDate->format($phpDateFormat);

            if (isset($results[0]) && $results[0]['date'] === $cursorDateUtc) {
                $value = (float)$results[0]['value'];
                array_shift($results);
            } else {
                $value = 0;
            }

            $rows[] = [$formattedCursorDate, $value];
            $cursorDate->modify('+1 '.$intervalUnit);
        }

        return [
            'columns' => [
                [
                    'type' => $intervalUnit === 'hour' ? 'datetime' : 'date',
                    'label' => $options['categoryLabel']
                ],
                [
                    'type' => $options['valueType'],
                    'label' => $options['valueLabel']
                ]
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Returns the interval unit that should be used in a run chart, based on the given start and end dates.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     *
     * @return string The unit that the chart should use ('hour', 'day', 'month', or 'year')
     */
    public static function getRunChartIntervalUnit(DateTime $startDate, DateTime $endDate): string
    {
        // Get the total number of days between the two dates
        $days = $endDate->diff($startDate)->format('%a');

        if ($days >= 730) {
            return 'year';
        }

        if ($days >= 60) {
            return 'month';
        }

        if ($days >= 2) {
            return 'day';
        }

        return 'hour';
    }

    /**
     * Returns the short date, decimal, percent and currency D3 formats based on Craft's locale settings
     *
     * @return array
     */
    public static function formats(): array
    {
        return [
            'shortDateFormats' => self::shortDateFormats(),
        ];
    }

    /**
     * Returns the D3 short date formats based on Yii's short date format
     *
     * @return array
     */
    public static function shortDateFormats(): array
    {
        $format = Craft::$app->getLocale()->getDateFormat('short');

        // Some of these are RTL versions
        $removals = [
            'day' => ['y'],
            'month' => ['d', 'd‏'],
            'year' => ['d', 'd‏', 'm', 'M‏'],
        ];

        $shortDateFormats = [];

        foreach ($removals as $unit => $chars) {
            $shortDateFormats[$unit] = $format;

            foreach ($chars as $char) {
                $shortDateFormats[$unit] = preg_replace("/(^[{$char}]+\W+|\W+[{$char}]+)/iu", '', $shortDateFormats[$unit]);
            }
        }


        // yii formats to d3 formats

        $yiiToD3Formats = [
            'day' => ['dd' => '%-d', 'd' => '%-d'],
            'month' => ['MM' => '%-m', 'M' => '%-m'],
            'year' => ['yyyy' => '%Y', 'yy' => '%y', 'y' => '%y']
        ];

        foreach ($shortDateFormats as $unit => $format) {
            foreach ($yiiToD3Formats as $_unit => $_formats) {
                foreach ($_formats as $yiiFormat => $d3Format) {
                    $pattern = "/({$yiiFormat})/i";

                    preg_match($pattern, $shortDateFormats[$unit], $matches);

                    if (count($matches) > 0) {
                        $shortDateFormats[$unit] = preg_replace($pattern, $d3Format, $shortDateFormats[$unit]);

                        break;
                    }
                }
            }
        }

        return $shortDateFormats;
    }

    /**
     * Returns the predefined date ranges with their label, start date and end date.
     *
     * @return array
     */
    public static function dateRanges(): array
    {
        $dateRanges = [
            'd7' => ['label' => Craft::t('app', 'Last 7 days'), 'startDate' => '-7 days', 'endDate' => null],
            'd30' => ['label' => Craft::t('app', 'Last 30 days'), 'startDate' => '-30 days', 'endDate' => null],
            'lastweek' => ['label' => Craft::t('app', 'Last Week'), 'startDate' => '-2 weeks', 'endDate' => '-1 week'],
            'lastmonth' => ['label' => Craft::t('app', 'Last Month'), 'startDate' => '-2 months', 'endDate' => '-1 month'],
        ];

        return $dateRanges;
    }
}
