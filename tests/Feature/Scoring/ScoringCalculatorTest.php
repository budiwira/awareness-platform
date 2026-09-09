<?php

use App\Support\Scoring\ScoringCalculator;

beforeEach(fn () => $this->calc = new ScoringCalculator);

test('quiz: shuffled index dipetakan ke original sebelum dinilai', function () {
    // correct_index = 2 (original). Option order: shuffled 0 => original 2.
    $q = (object) ['id' => 101, 'correct_index' => 2];
    $orders = [101 => [0 => 2, 1 => 0, 2 => 1, 3 => 3]];

    $r = $this->calc->quiz(collect([$q]), [101 => 0], $orders, 70);
    expect($r['score'])->toBe(100)->and($r['passed'])->toBeTrue();

    // shuffled 1 => original 0 (salah)
    $r = $this->calc->quiz(collect([$q]), [101 => 1], $orders, 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('quiz: jawaban null dihitung salah (tidak crash, tidak benar)', function () {
    $q = (object) ['id' => 101, 'correct_index' => 0];
    $r = $this->calc->quiz(collect([$q]), [], [], 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('quiz: shuffled index di luar option_orders dihitung salah', function () {
    $q = (object) ['id' => 101, 'correct_index' => 0];
    $orders = [101 => [0 => 0]];           // hanya satu opsi terdaftar
    $r = $this->calc->quiz(collect([$q]), [101 => 7], $orders, 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('quiz: skor bounded 0-100 dengan campuran benar/salah', function () {
    $q1 = (object) ['id' => 1, 'correct_index' => 0];
    $q2 = (object) ['id' => 2, 'correct_index' => 1];
    $orders = [1 => [0 => 0, 1 => 1], 2 => [0 => 1, 1 => 0]];

    // q1: shuffled 0 => original 0 (benar)
    // q2: shuffled 1 => original 0 (salah, karena correct_index = 1)
    $r = $this->calc->quiz(collect([$q1, $q2]), [1 => 0, 2 => 1], $orders, 40);
    expect($r['score'])->toBe(50)->and($r['passed'])->toBeTrue();

    $r = $this->calc->quiz(collect([$q1, $q2]), [1 => 0, 2 => 1], $orders, 70);
    expect($r['passed'])->toBeFalse();
});

test('quiz: empty questions aman (score 0)', function () {
    $r = $this->calc->quiz(collect(), [], [], 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('case: skor berdasarkan quality points + breakdown', function () {
    $scenes = collect([
        (object) ['id' => 1, 'options' => [['quality' => 'best'], ['quality' => 'poor']]],
        (object) ['id' => 2, 'options' => [['quality' => 'acceptable'], ['quality' => 'poor']]],
    ]);

    $r = $this->calc->caseStudy($scenes, [1 => 0, 2 => 0]);
    expect($r['score'])->toBe(80)
        ->and($r['breakdown'][1]['quality'])->toBe('best')
        ->and($r['breakdown'][2]['quality'])->toBe('acceptable');

    expect($this->calc->caseStudy($scenes, [1 => 1, 2 => 1])['score'])->toBe(0);
});

test('ttx: rata-rata inject scores, empty aman', function () {
    expect($this->calc->ttx([80, 90, 100]))->toBe(90);
    expect($this->calc->ttx([]))->toBe(0);
});
