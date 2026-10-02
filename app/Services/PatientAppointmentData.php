<?php

namespace App\Services;

final readonly class PatientAppointmentData
{
    public function __construct(public string $title, public string $startsAt, public string $timezone, public int $durationMinutes, public ?string $location, public string $status) {}
}
