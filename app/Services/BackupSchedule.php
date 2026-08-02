<?php

namespace App\Services;

use App\Models\BackupPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class BackupSchedule
{
    public function next(BackupPolicy $policy, ?CarbonInterface $from = null): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($from ?? now())->setTimezone($policy->timezone);
        [$hour, $minute] = array_map('intval', explode(':', $policy->run_at));
        $candidate = $cursor->setTime($hour, $minute, 0);

        if ($policy->frequency === 'weekly') {
            $candidate = $candidate->nextOrSame((int) $policy->weekday);
        } elseif ($policy->frequency === 'monthly') {
            $day = min((int) $policy->day_of_month, $candidate->daysInMonth);
            $candidate = $candidate->setDay($day);
        }

        if ($candidate->lessThanOrEqualTo($cursor)) {
            if ($policy->frequency === 'monthly') {
                $candidate = $candidate->addMonthNoOverflow();
                $candidate = $candidate->setDay(min((int) $policy->day_of_month, $candidate->daysInMonth));
            } else {
                $candidate = $policy->frequency === 'weekly' ? $candidate->addWeek() : $candidate->addDay();
            }
        }

        return $candidate->utc();
    }
}
