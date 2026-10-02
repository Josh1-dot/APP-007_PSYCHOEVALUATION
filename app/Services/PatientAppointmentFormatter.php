<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class PatientAppointmentFormatter
{
    public function format(PatientAppointmentResult $result): string
    {
        if ($result->items === []) {
            return 'Aucun rendez-vous à venir n’est actuellement disponible.';
        }
        $lines = [];
        foreach ($result->items as $item) {
            $date = CarbonImmutable::parse($item->startsAt)->setTimezone($item->timezone);
            $lines[] = $item->title.' — '.$date->format('d/m/Y à H:i:s').' ('.$item->timezone.', UTC'.$date->format('P').') — '.$item->durationMinutes.' min — '.($item->location ?: 'Lieu non renseigné').' — '.match ($item->status) {
                'planifie' => 'Planifié', default => throw new \RuntimeException('Invalid appointment status.')
            };
        }
        if ($result->hasMore) {
            $lines[] = 'D’autres rendez-vous à venir sont disponibles dans votre calendrier.';
        }
        $lines[] = $result->calendarUrl;

        return implode("\n", $lines);
    }
}
