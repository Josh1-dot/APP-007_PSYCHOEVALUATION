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
        return $this->listMyUpcomingAppointmentsForConversation()->result;
    }

    public function getMyNextAppointment(): PatientAppointmentResult
    {
        return $this->getMyNextAppointmentForConversation()->result;
    }

    public function listMyUpcomingAppointmentsForConversation(): PatientAppointmentSelection
    {
        return $this->upcoming(self::LIST_LIMIT);
    }

    public function getMyNextAppointmentForConversation(): PatientAppointmentSelection
    {
        return $this->upcoming(1);
    }

    public function getMyAppointmentByReference(int $appointmentId): PatientAppointmentResult
    {
        $context = $this->contexts->fromAuthenticatedUser();
        $timezone = (string) config('app.timezone');
        $now = CarbonImmutable::now($timezone)->startOfSecond();
        $appointment = $this->upcomingQuery($context, $now)->whereKey($appointmentId)->first();
        $items = $appointment ? [$this->data($appointment, $timezone)] : [];

        return new PatientAppointmentResult($items, false, route('calendar.index'));
    }

    private function upcoming(int $limit): PatientAppointmentSelection
    {
        $context = $this->contexts->fromAuthenticatedUser();
        $timezone = (string) config('app.timezone');
        $now = CarbonImmutable::now($timezone)->startOfSecond();
        $rows = $this->upcomingQuery($context, $now)
            ->orderBy('starts_at')->orderBy('id')->limit($limit + 1)->get();
        $selected = $rows->take($limit);
        $items = $selected->map(fn (Appointment $appointment): PatientAppointmentData => $this->data($appointment, $timezone))->all();
        $referenceId = $limit === 1 || ($selected->count() === 1 && $rows->count() === 1) ? $selected->first()?->id : null;

        return new PatientAppointmentSelection(new PatientAppointmentResult($items, $rows->count() > $limit, route('calendar.index')), $referenceId);
    }

    private function upcomingQuery(PatientContext $context, CarbonImmutable $now): Builder
    {
        return Appointment::query()->select(['id', 'title', 'starts_at', 'duration', 'location', 'status'])
            ->where('tenant_id', $context->tenantId)->where('client_id', $context->clientId)
            ->whereHas('client', fn (Builder $query): Builder => $query->where('tenant_id', $context->tenantId)->where('user_id', $context->userId)->whereNull('deleted_at')->whereNull('anonymized_at'))
            ->where('status', 'planifie')->where('starts_at', '>=', $now);
    }

    private function data(Appointment $appointment, string $timezone): PatientAppointmentData
    {
        $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $appointment->getRawOriginal('starts_at'), $timezone);

        return new PatientAppointmentData($appointment->title, $startsAt->toIso8601String(), $timezone, (int) $appointment->duration, $appointment->location, $appointment->status);
    }
}
