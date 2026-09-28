<?php

namespace App\Domain\Tabletop;

use Illuminate\Validation\ValidationException;

final class PlaybookPhaseStructure
{
    /**
     * @param  array<int, mixed>|null  $phases
     * @return list<array{key: string, title: string, guidance: string, participant_summary: string, capability_codes: list<string>}>|null
     */
    public static function normalize(?array $phases, string $field = 'structured_phases'): ?array
    {
        if ($phases === null) {
            return null;
        }

        $normalized = [];
        $keys = [];
        foreach ($phases as $index => $phase) {
            if (! is_array($phase)) {
                throw ValidationException::withMessages([$field => 'Phase ke-'.($index + 1).' harus berupa object.']);
            }

            $allowed = ['key', 'title', 'guidance', 'participant_summary', 'capability_codes'];
            if (array_diff(array_keys($phase), $allowed) !== []) {
                throw ValidationException::withMessages([$field => 'Structured phase memuat field yang tidak diizinkan.']);
            }

            $key = is_string($phase['key'] ?? null) ? trim($phase['key']) : '';
            $title = is_string($phase['title'] ?? null) ? trim($phase['title']) : '';
            $guidance = is_string($phase['guidance'] ?? null) ? trim($phase['guidance']) : '';
            $summary = is_string($phase['participant_summary'] ?? null) ? trim($phase['participant_summary']) : '';
            if (! preg_match('/^[a-z][a-z0-9_]{1,99}$/', $key) || $title === '' || $guidance === '' || $summary === '') {
                throw ValidationException::withMessages([$field => 'Setiap phase memerlukan key stabil, title, guidance, dan participant_summary.']);
            }
            if (mb_strlen($title) > 255 || mb_strlen($guidance) > 5000 || mb_strlen($summary) > 1000) {
                throw ValidationException::withMessages([$field => 'Konten structured phase melebihi batas panjang yang diizinkan.']);
            }
            if (isset($keys[$key])) {
                throw ValidationException::withMessages([$field => "Playbook phase {$key} duplikat."]);
            }
            $keys[$key] = true;

            $codes = ExerciseCapabilityCatalog::normalize(
                is_array($phase['capability_codes'] ?? null) ? $phase['capability_codes'] : [],
                $field
            );
            if ($codes === []) {
                throw ValidationException::withMessages([$field => "Playbook phase {$key} harus memiliki capability."]);
            }

            $normalized[] = [
                'key' => $key,
                'title' => $title,
                'guidance' => $guidance,
                'participant_summary' => $summary,
                'capability_codes' => $codes,
            ];
        }

        return $normalized;
    }

    /** @return list<string> */
    public static function keysFromSnapshot(?array $snapshot): array
    {
        $phases = is_array($snapshot['structured_phases'] ?? null) ? $snapshot['structured_phases'] : [];

        return collect($phases)
            ->pluck('key')
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function relevantKeys(?array $playbookSnapshot, ?array $exerciseSnapshot): array
    {
        $exerciseCodes = ExerciseCapabilityCatalog::normalize(
            is_array($exerciseSnapshot['capability_codes'] ?? null) ? $exerciseSnapshot['capability_codes'] : null,
            'exercise_snapshot.capability_codes'
        );
        if ($exerciseCodes === null || $exerciseCodes === []) {
            return [];
        }

        $phases = is_array($playbookSnapshot['structured_phases'] ?? null)
            ? $playbookSnapshot['structured_phases']
            : [];

        return collect($phases)->filter(function ($phase) use ($exerciseCodes): bool {
            if (! is_array($phase)) {
                return false;
            }
            $phaseCodes = is_array($phase['capability_codes'] ?? null) ? $phase['capability_codes'] : [];

            return array_intersect($exerciseCodes, $phaseCodes) !== [];
        })->pluck('key')->filter()->unique()->values()->all();
    }
}
