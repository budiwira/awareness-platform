<?php

namespace App\Domain\Tabletop;

use Illuminate\Validation\ValidationException;

final class ExerciseCapabilityCatalog
{
    /**
     * Canonical Tabletop V1 capability identifiers. Keys are durable domain
     * identifiers; labels and descriptions may evolve without changing them.
     *
     * @var array<string, array{label: string, description: string, evaluation_dimension: string}>
     */
    public const CAPABILITIES = [
        'EX-1' => [
            'label' => 'Detection & Triage',
            'description' => 'Mendeteksi, memvalidasi, dan memprioritaskan indikasi insiden dengan informasi yang tersedia.',
            'evaluation_dimension' => 'detection_triage',
        ],
        'EX-2' => [
            'label' => 'Escalation & Ownership',
            'description' => 'Menetapkan kepemilikan insiden, jalur eskalasi, dan otoritas keputusan.',
            'evaluation_dimension' => 'escalation_ownership',
        ],
        'EX-3' => [
            'label' => 'Containment Decision',
            'description' => 'Memilih tindakan containment yang proporsional terhadap risiko dan dampak operasi.',
            'evaluation_dimension' => 'containment_decision',
        ],
        'EX-4' => [
            'label' => 'Cross-functional Coordination',
            'description' => 'Mengoordinasikan keputusan, handoff, dan dependensi lintas fungsi.',
            'evaluation_dimension' => 'cross_functional_coordination',
        ],
        'EX-5' => [
            'label' => 'Incident Communication',
            'description' => 'Menyampaikan informasi insiden yang akurat, disetujui, dan tepat waktu.',
            'evaluation_dimension' => 'incident_communication',
        ],
        'EX-6' => [
            'label' => 'Recovery & Improvement',
            'description' => 'Memulihkan operasi secara terkendali dan mengubah pelajaran menjadi perbaikan.',
            'evaluation_dimension' => 'recovery_improvement',
        ],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CAPABILITIES);
    }

    /** @return list<array{code: string, label: string, description: string}> */
    public static function catalog(): array
    {
        return collect(self::CAPABILITIES)
            ->map(fn (array $item, string $code): array => [
                'code' => $code,
                'label' => $item['label'],
                'description' => $item['description'],
            ])->values()->all();
    }

    /**
     * @param  array<int, mixed>|null  $codes
     * @return list<string>|null
     */
    public static function normalize(?array $codes, string $field = 'capability_codes'): ?array
    {
        if ($codes === null) {
            return null;
        }

        $normalized = collect($codes)
            ->map(fn ($code) => is_string($code) ? trim($code) : $code)
            ->values();

        if ($normalized->contains(fn ($code) => ! is_string($code) || ! array_key_exists($code, self::CAPABILITIES))) {
            throw ValidationException::withMessages([$field => 'Kode capability tidak dikenal. Gunakan EX-1 sampai EX-6.']);
        }

        return $normalized->unique()->values()->all();
    }

    public static function dimensionFor(string $code): ?string
    {
        return self::CAPABILITIES[$code]['evaluation_dimension'] ?? null;
    }
}
