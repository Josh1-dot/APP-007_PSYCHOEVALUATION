<?php

namespace App\Services;

final readonly class PatientAppointmentResult
{
    /** @param list<PatientAppointmentData> $items */
    public function __construct(public array $items, public bool $hasMore, public string $calendarUrl) {}
}
