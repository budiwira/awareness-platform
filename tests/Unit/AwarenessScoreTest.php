<?php

use App\Models\CaseParticipation;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Models\TtxScore;
use App\Support\Scoring\AwarenessScore;

function makeAssignment(string $status): ModuleAssignment
{
    return new ModuleAssignment(['status' => $status]);
}

function makeQuizAttempt(int $quizId, int $score): QuizAttempt
{
    return new QuizAttempt(['quiz_id' => $quizId, 'score' => $score]);
}

function makeCase(int $score): CaseParticipation
{
    return new CaseParticipation(['score' => $score, 'status' => 'completed']);
}

function makeSolve(int $points): CtfSolve
{
    return new CtfSolve(['points' => $points]);
}

function makeTtxScore(int $score): TtxScore
{
    return new TtxScore(['score' => $score]);
}

test('no data scores 0', function () {
    $score = (new AwarenessScore)->compute(collect(), collect(), collect(), collect(), collect(), 0);

    expect($score['overall'])->toBe(0);
});

test('perfect user scores 100 across all 5 signals', function () {
    $score = (new AwarenessScore)->compute(
        collect([makeAssignment('completed')]),
        collect([makeQuizAttempt(1, 100)]),
        collect([makeCase(100)]),
        collect([makeSolve(200)]),
        collect([makeTtxScore(100)]),
        200
    );

    expect($score['overall'])->toBe(100);
});

test('weighted computation across 5 signals is correct', function () {
    // completion: 1/2 = 50  -> *0.25 = 12.5
    // quiz: best [80,60] avg 70 -> *0.25 = 17.5
    // case: [100] avg 100 -> *0.15 = 15
    // ctf: 100/200 = 50 -> *0.15 = 7.5
    // ttx: [90] avg 90 -> *0.20 = 18
    // overall = 12.5+17.5+15+7.5+18 = 70.5 → round 71
    $score = (new AwarenessScore)->compute(
        collect([makeAssignment('completed'), makeAssignment('assigned')]),
        collect([makeQuizAttempt(1, 80), makeQuizAttempt(2, 60)]),
        collect([makeCase(100)]),
        collect([makeSolve(100)]),
        collect([makeTtxScore(90)]),
        200
    );

    expect($score['overall'])->toBe(71)
        ->and($score['breakdown'][0]['score'])->toBe(50)
        ->and($score['breakdown'][1]['score'])->toBe(70)
        ->and($score['breakdown'][2]['score'])->toBe(100)
        ->and($score['breakdown'][3]['score'])->toBe(50)
        ->and($score['breakdown'][4]['score'])->toBe(90);
});

test('ctf engagement capped at 100', function () {
    $score = (new AwarenessScore)->compute(
        collect(), collect(), collect(),
        collect([makeSolve(500)]),
        collect(),
        200
    );

    expect($score['breakdown'][3]['score'])->toBe(100);
});

test('breakdown is explainable with weights', function () {
    $score = (new AwarenessScore)->compute(collect(), collect(), collect(), collect(), collect(), 0);

    expect($score['breakdown'])->toHaveCount(5)
        ->and($score['breakdown'][0]['weight'])->toBe(25)
        ->and($score['breakdown'][1]['weight'])->toBe(25)
        ->and($score['breakdown'][2]['weight'])->toBe(15)
        ->and($score['breakdown'][3]['weight'])->toBe(15)
        ->and($score['breakdown'][4]['weight'])->toBe(20);
});