<?php

use App\Support\Scoring\ScoringCalculator;

beforeEach(fn () => $this->calc = new ScoringCalculator);

test('quiz: skor selalu bounded 0-100', function () {
    // Buat dummy question objects (tidak perlu factory)
    $q1 = (object) ['id' => 101, 'correct_option_index' => 0];
    $q2 = (object) ['id' => 102, 'correct_option_index' => 1];
    $questions = collect([$q1, $q2]);

    // semua benar
    $r = $this->calc->quiz($questions, [101 => 0, 102 => 1], 70);
    expect($r['score'])->toBe(100)->and($r['passed'])->toBeTrue();

    // semua salah
    $r = $this->calc->quiz($questions, [101 => 3, 102 => 3], 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();

    // setengah benar, threshold 40
    $r = $this->calc->quiz($questions, [101 => 0, 102 => 3], 40);
    expect($r['score'])->toBe(50)->and($r['passed'])->toBeTrue();

    // setengah benar, threshold 70 (gagal)
    $r = $this->calc->quiz($questions, [101 => 0, 102 => 3], 70);
    expect($r['score'])->toBe(50)->and($r['passed'])->toBeFalse();
});

test('quiz: empty questions tidak crash (score 0, not passed)', function () {
    $r = $this->calc->quiz(collect(), [], 70);
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('quiz: jawaban hilang dihitung salah', function () {
    $q = (object) ['id' => 101, 'correct_option_index' => 0];
    $r = $this->calc->quiz(collect([$q]), [], 70); // answers kosong
    expect($r['score'])->toBe(0)->and($r['passed'])->toBeFalse();
});

test('case: skor selalu antara 0-100 berdasarkan quality', function () {
    $scenes = collect([
        (object) ['id' => 1, 'options' => [['quality' => 'best'], ['quality' => 'poor']]],
        (object) ['id' => 2, 'options' => [['quality' => 'acceptable'], ['quality' => 'poor']]],
    ]);

    // best + acceptable = (100+60)/2 = 80
    $r = $this->calc->caseStudy($scenes, [1 => 0, 2 => 0]);
    expect($r['score'])->toBe(80);
    expect($r['breakdown'][1]['quality'])->toBe('best');
    expect($r['breakdown'][2]['quality'])->toBe('acceptable');

    // poor + poor = 0
    $r = $this->calc->caseStudy($scenes, [1 => 1, 2 => 1]);
    expect($r['score'])->toBe(0);

    // best + best = 100
    $scenes2 = collect([
        (object) ['id' => 1, 'options' => [['quality' => 'best']]],
        (object) ['id' => 2, 'options' => [['quality' => 'best']]],
    ]);
    $r = $this->calc->caseStudy($scenes2, [1 => 0, 2 => 0]);
    expect($r['score'])->toBe(100);
});

test('case: decision hilang default ke poor (0)', function () {
    $scenes = collect([
        (object) ['id' => 1, 'options' => [['quality' => 'best']]],
    ]);
    // decisions kosong -> idx=0 default, tapi options[0] ada best
    $r = $this->calc->caseStudy($scenes, []);
    expect($r['breakdown'][1]['quality'])->toBe('best'); // idx default 0 = best
});

test('ttx: rata-rata inject scores', function () {
    expect($this->calc->ttx([80, 90, 100]))->toBe(90);
    expect($this->calc->ttx([]))->toBe(0);
    expect($this->calc->ttx([50]))->toBe(50);
});
