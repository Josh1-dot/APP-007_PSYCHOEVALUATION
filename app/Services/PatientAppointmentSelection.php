<?php

namespace App\Services;

final readonly class PatientAppointmentSelection
{
    public function __construct(public PatientAppointmentResult $result, public ?int $referenceId) {}
}
