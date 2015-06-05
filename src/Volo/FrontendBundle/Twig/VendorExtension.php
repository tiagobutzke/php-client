<?php

namespace Volo\FrontendBundle\Twig;

use Foodpanda\ApiSdk\Entity\Schedule\Schedule;
use Foodpanda\ApiSdk\Entity\Schedule\SchedulesCollection;

class VendorExtension extends \Twig_Extension
{
    /**
     * @var string
     */
    protected $locale;

    /**
     * @param string $locale
     */
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getClosingIn', array($this, 'getClosingIn')),
            new \Twig_SimpleFunction('getOpeningTime', array($this, 'getOpeningTime')),
            new \Twig_SimpleFunction('getClosingTime', array($this, 'getClosingTime')),
            new \Twig_SimpleFunction('getDeliveryDays', array($this, 'getDeliveryDays')),
            new \Twig_SimpleFunction('getNextOpeningHours', array($this, 'getNextOpeningHours')),
            new \Twig_SimpleFunction('getNextClosingHours', array($this, 'getNextClosingHours')),
            new \Twig_SimpleFunction('getNextOpeningWeekDayNumber', array($this, 'getNextOpeningWeekDayNumber')),
            new \Twig_SimpleFunction('getClosingHoursRange', array($this, 'getClosingHoursRange')),
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'vendor_extension';
    }

    /**
     * @param SchedulesCollection $schedulesCollection
     * @param string $weekDay
     *
     * @return string
     */
    public function getClosingIn(SchedulesCollection $schedulesCollection, $weekDay = null)
    {
        $now = new \DateTime();
        $currentTime = $now->getTimestamp();

        $closingTime = $this->getClosingTime($schedulesCollection, $weekDay);
        $openingTime = $this->getOpeningTime($schedulesCollection, $weekDay);

        $noOpeningForThisDay = ($closingTime === null || $openingTime === null);

        if ($noOpeningForThisDay ||
            ($closingTime->getTimestamp() <= $currentTime) || ($openingTime->getTimestamp() > $currentTime)
        ) {
            return 0;
        }
        $diffToClosingTime = $now->diff($closingTime);

        return $diffToClosingTime->i + (60 * $diffToClosingTime->h);
    }

    /**
     * @param SchedulesCollection $schedulesCollection
     * @param int $weekDay
     *
     * @return \DateTime
     */
    public function getClosingTime(SchedulesCollection $schedulesCollection, $weekDay = null)
    {
        $closingAt = $this->getTime($schedulesCollection, $weekDay, 'delivering', 'closing_time');

        return $closingAt === null ? null : \DateTime::createFromFormat('H:i', $closingAt);
    }

    /**
     * @param SchedulesCollection $schedulesCollection
     * @param int $weekDay
     *
     * @return \DateTime
     */
    public function getOpeningTime(SchedulesCollection $schedulesCollection, $weekDay = null)
    {
        $openingAt = $this->getTime($schedulesCollection, $weekDay, 'delivering', 'opening_time');

        return $openingAt === null ? null : \DateTime::createFromFormat('H:i', $openingAt);
    }

    /**
     * @param SchedulesCollection $schedules
     * @param int $weekDay
     * @param string $openingType
     * @param string $field
     *
     * @return string
     */
    protected function getTime(SchedulesCollection $schedules, $weekDay, $openingType, $field)
    {
        if ($weekDay === null) {
            $weekDay = (int)date('N');
        }

        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
            if ((int)$weekDay === (int)$schedule->getWeekday() && $openingType === $schedule->getOpeningType()) {
                return $field === 'closing_time' ? $schedule->getClosingTime() : $schedule->getOpeningTime();
            }
        }

        return null;
    }

    /**
     * @param SchedulesCollection $schedules
     * @param \DateTime $dateTime
     *
     * @return bool
     */
    protected function isDeliveryPossibleOnThisDay(SchedulesCollection $schedules, \DateTime $dateTime)
    {
        $deliveryPossible = false;
        $dayOfTheWeek = $dateTime->format('N');
        $closingTime = $this->getClosingTime($schedules, $dayOfTheWeek);

        if ($closingTime !== null) {
            $closingTimeTodayInSeconds = $this->getSecondsInThisDay($closingTime);
            $currentTimeTodayInSeconds = $this->getSecondsInThisDay($dateTime);

            $deliveryPossible = $currentTimeTodayInSeconds < $closingTimeTodayInSeconds;
        }


        return $deliveryPossible;
    }


    /**
     * @param \DateTime $dateTime
     *
     * @return int
     */
    protected function getSecondsInThisDay(\DateTime $dateTime)
    {
        $dateTimeToCheck = clone $dateTime;
        $secondsInThisDay = $dateTime->getTimestamp();
        $dateTimeToCheck->setTime(0, 0, 0);
        $secondsInThisDay -= $dateTimeToCheck->getTimestamp();

        return $secondsInThisDay;
    }

    /**
     * @param \DateTime $day
     */
    protected function incrementOneDay(\DateTime $day)
    {
        $day->add(new \DateInterval('P1D'));
        $day->setTime(0, 1, 0);
    }

    /**
     * @param SchedulesCollection $schedules
     * @param \DateTime $startDay
     *
     * @return \DateTime
     */
    protected function getNextOpeningDay(SchedulesCollection $schedules, \DateTime $startDay = null)
    {
        $dayToCheck = $startDay !== null ? clone $startDay : new \DateTime();

        if (!$this->isDeliveryPossibleOnThisDay($schedules, $dayToCheck)) {
            $this->incrementOneDay($dayToCheck);
        }

        for ($i = 0; $i < 7; $i++) {
            $weekDay = $dayToCheck->format('N');
            $openingHours = $this->getOpeningTime($schedules, $weekDay);

            if (null !== $openingHours) {
                return $dayToCheck;
            }
            $this->incrementOneDay($dayToCheck);
        }

        return null;
    }

    /**
     * @param SchedulesCollection $schedules
     * @param \DateTime $startDay
     *
     * @return int
     */
    public function getNextOpeningWeekDayNumber(SchedulesCollection $schedules, \DateTime $startDay = null)
    {
        $nextOpeningDay = $this->getNextOpeningDay($schedules, $startDay);

        return $nextOpeningDay === null ? null : $nextOpeningDay->format('N');
    }

    /**
     * @param SchedulesCollection $schedules
     *
     * @return int
     */
    public function getNextOpeningHours(SchedulesCollection $schedules)
    {
        $nextOpeningDay = $this->getNextOpeningWeekDayNumber($schedules);

        return $this->getOpeningTime($schedules, $nextOpeningDay);
    }

    /**
     * @param SchedulesCollection $schedules
     *
     * @return int
     */
    public function getNextClosingHours(SchedulesCollection $schedules)
    {
        $nextOpeningDay = $this->getNextOpeningWeekDayNumber($schedules);

        return $this->getClosingTime($schedules, $nextOpeningDay);
    }

    /**
     * @param SchedulesCollection $schedules
     * @param int $numberOfDays
     * @param \DateTime $startDay
     *
     * @return \DateTime[]
     */
    public function getDeliveryDays(SchedulesCollection $schedules, $numberOfDays, \DateTime $startDay = null)
    {
        $dayToCheck = $startDay !== null ? clone $startDay : new \DateTime();
        $deliveryPossible = $this->isDeliveryPossibleOnThisDay($schedules, $dayToCheck);

        if (!$deliveryPossible) {
            $this->incrementOneDay($dayToCheck);
        }
        $openingDays = [];
        $attempts = 0;
        while ((count($openingDays) < $numberOfDays) && ($attempts < 7)) {
            $nextOpeningDay = $this->getNextOpeningDay($schedules, $dayToCheck);
            $openingDays[] = $nextOpeningDay;

            // set the new startingDayToCheck to the new found day plus 1
            $dayToCheck->setTimestamp($nextOpeningDay->getTimestamp());
            $this->incrementOneDay($dayToCheck);
            $attempts++;
        }

        return $openingDays;
    }

    /**
     * This method takes a day (DateTime) and returns deliver ranges [[18:00, 19:00], [19:00, 20:00], [20:00, 21:30]]
     *
     * @param SchedulesCollection $schedules
     * @param \DateTime $day
     *
     * @return array
     */
    public function getClosingHoursRange(SchedulesCollection $schedules, \DateTime $day)
    {
        $openingTime = $this->getOpeningTime($schedules, $day->format('N'));
        $closingTime = $this->getClosingTime($schedules, $day->format('N'));

        // for example if it open as 10:00 (am) then this number is 10 hrs * 3600 = 36,000 seconds
        $openingTimeInSecondsOfTheDay = $this->getSecondsInThisDay($openingTime);
        // for example if it closes as 22:00 (pm) then this number is 20 hrs * 3600 = 72,000 seconds
        $closingTimeInSecondsOfTheDay = $this->getSecondsInThisDay($closingTime);

        // This case covers restaurants that close after 24:00(00:00)
        if ($closingTimeInSecondsOfTheDay < $openingTimeInSecondsOfTheDay) {
            $closingTimeInSecondsOfTheDay += 86400;
        }

        $today = new \DateTime();

        $deliveryStartingTimeInSecondsOfTheDay = $openingTimeInSecondsOfTheDay;

        // in the following part we try to determine the 1st hour of deliver
        // such that if the day is today, we start from the next hour from the time now
        // otherwise(not today) we start from the normal opening hours
        if ($day->format('Y-m-d') === $today->format('Y-m-d')) {
            $unixTimestampOfNow = $today->getTimestamp();
            $today->setTime(0, 0, 0);
            $midnightTimestamp = $today->getTimestamp();
            // number of seconds that passed today from 00:00 til this moment e.g. if it's 7:30 am then (7.5 hrs * 3600)
            $secondsSinceTheBeginningOfTheDay = $unixTimestampOfNow - $midnightTimestamp;

            $startTimeInSecondsToday = max($openingTimeInSecondsOfTheDay, $secondsSinceTheBeginningOfTheDay);

            // we do this to round to the nearest 1/2 hour in case that the Restaurant opens for example at 10:30
            $startingHour = ceil(2 * $startTimeInSecondsToday / 3600) / 2;
            $deliveryStartingTimeInSecondsOfTheDay = $startingHour * 3600;
        }

        return $this->getDeliveryRanges($deliveryStartingTimeInSecondsOfTheDay, $closingTimeInSecondsOfTheDay);
    }

    /**
     * @param $actualStartingTimeInSecondsToday
     * @param $closingTimeInSecondsToday
     * @return array
     */
    protected function getDeliveryRanges($actualStartingTimeInSecondsToday, $closingTimeInSecondsToday)
    {
        $dateTimeForFormatting = new \DateTime();

        $deliveryPairs = [];

        // we loop on the time hour by hour
        for ($i = $actualStartingTimeInSecondsToday; $i < $closingTimeInSecondsToday; $i += 3600) {
            $startingTime = ($i / 3600);
            $endingTime = min($closingTimeInSecondsToday, ($i + 3600)) / 3600;

            // calculating and formatting the range
            $rangeStartingMinutes = ($startingTime - floor($startingTime)) * 60;
            $rangeStartingHours = floor($startingTime);
            $dateTimeForFormatting->setTime($rangeStartingHours, $rangeStartingMinutes);
            $formattedRangeStartingTime = $this->formatTime($dateTimeForFormatting);

            $rangeEndingMinutes = ($endingTime - floor($endingTime)) * 60;
            $rangeEndingHours = floor($endingTime);
            $dateTimeForFormatting->setTime($rangeEndingHours, $rangeEndingMinutes);
            $formattedRangeEndingTime = $this->formatTime($dateTimeForFormatting);

            $rangeKey = sprintf('%02d:%02d', $rangeStartingHours, $rangeStartingMinutes);
            $deliveryPairs[$rangeKey] = sprintf('%s - %s', $formattedRangeStartingTime, $formattedRangeEndingTime);
        }

        return $deliveryPairs;
    }

    /**
     * @param \DateTime $dateTime
     *
     * @return string
     */
    protected function formatTime(\DateTime $dateTime)
    {
        $formatter = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT);

        return $formatter->format($dateTime);
    }
}