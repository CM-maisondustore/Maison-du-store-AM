<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

class AppointmentSchedule
{
    public function isOpen(string $date, string $time): bool
    {
        $slot = CarbonImmutable::createFromFormat('!Y-m-d H:i', "$date $time", config('app.timezone'));
        $now = CarbonImmutable::now(config('app.timezone'));
        return in_array($time, config('appointments.slots'), true)
            && !in_array($slot->dayOfWeek, config('appointments.closed_weekdays'), true)
            && $slot->greaterThanOrEqualTo($now->addMinutes(config('appointments.lead_minutes')))
            && $slot->startOfDay()->lessThanOrEqualTo($now->addMonthsNoOverflow(config('appointments.months_ahead'))->endOfMonth());
    }

    public function unavailable(string $date): array
    {
        $booked = Appointment::where('date', $date)->where('status', '!=', 'cancelled')->pluck('time')->all();
        return array_values(array_filter(config('appointments.slots'), fn ($time) =>
            !$this->isOpen($date, $time) || in_array($time, $booked, true)));
    }
}
