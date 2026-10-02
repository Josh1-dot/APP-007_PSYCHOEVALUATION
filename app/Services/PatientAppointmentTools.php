<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PatientAppointmentTools
{
    public const LIST_LIMIT = 10;

    public function __construct(public PatientContextFactory $contexts) {}

    public function listMyUpcomingAppointments(): PatientAppointmentResult
    {
        return $this->upcoming(self::LIST_LIMIT);
    }

    public function getMyNextAppointment(): PatientAppointmentResult
    {
        return $this->upcoming(1);
    }

    private function upcoming(int $limit): PatientAppointmentResult
    {
        $context = $this->contexts->fromAuthenticatedUser();
        $timezone = (string) config('app.timezone');
        $now = CarbonImmutable::now($timezone)->startOfSecond();
        $rows = Appointment::query()->select(['id', 'title', 'starts_at', 'duration', 'location', 'status'])
            ->where('tenant_id', $context->tenantId)->where('client_id', $context->clientId)
            ->whereHas('client', fn (Builder $query): Builder => $query->where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->whereNull('deleted_at')->whereNull('anonymized_at'))
            ->where('status', 'planifie')->where('starts_at', '>=', $now)
            ->orderBy('starts_at')->orderBy('id')->limit($limit + 1)->get();
        $items = $rows->take($limit)->map(function (Appointment $appointment) use ($timezone): PatientAppointmentData {
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $appointment->getRawOriginal('starts_at'), $timezone);

            return new PatientAppointmentData($appointment->title, $startsAt->toIso8601String(), $timezone, (int) $appointment->duration, $appointment->location, $appointment->status);
        })->all();

        return new PatientAppointmentResult($items, $rows->count() > $limit, route('calendar.index'));
    }
}
