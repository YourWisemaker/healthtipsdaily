<?php

namespace Tests\TestUtils;

use DateTime;
use DateTimeInterface;

/**
 * A utility class to replace Carbon functionality in tests
 * This helps avoid memory issues caused by Carbon's Test trait
 */
class DateTimeHelper
{
    /**
     * The current fixed test time
     *
     * @var DateTime|null
     */
    private static $testNow = null;

    /**
     * Set a fixed time for testing
     *
     * @param DateTime|string|null $dateTime
     * @return void
     */
    public static function setTestNow($dateTime = null): void
    {
        if (is_string($dateTime)) {
            $dateTime = new DateTime($dateTime);
        }
        
        self::$testNow = $dateTime;
    }

    /**
     * Get the current time (fixed or actual)
     *
     * @return DateTime
     */
    public static function now(): DateTime
    {
        return self::$testNow ? clone self::$testNow : new DateTime();
    }

    /**
     * Create a new DateTime instance
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $hour
     * @param int $minute
     * @param int $second
     * @return DateTime
     */
    public static function create(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): DateTime
    {
        return new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second));
    }

    /**
     * Format a DateTime to a specific format
     *
     * @param DateTimeInterface $dateTime
     * @param string $format
     * @return string
     */
    public static function format(DateTimeInterface $dateTime, string $format = 'Y-m-d H:i:s'): string
    {
        return $dateTime->format($format);
    }
    
    /**
     * Subtract days from a DateTime
     *
     * @param DateTimeInterface|null $dateTime
     * @param int $days
     * @return DateTime
     */
    public static function subDays(DateTimeInterface $dateTime = null, int $days = 1): DateTime
    {
        $dt = $dateTime ? clone $dateTime : self::now();
        $dt->modify("-{$days} days");
        return $dt;
    }
    
    /**
     * Convert DateTime to database format
     *
     * @param DateTimeInterface $dateTime
     * @return string
     */
    public static function toDateTimeString(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
