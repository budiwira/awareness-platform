<?php

test('home page renders', function () {
    $this->get('/')->assertOk();
});

test('login page renders', function () {
    $this->get('/login')->assertOk();
});

test('register page renders', function () {
    $this->get('/register')->assertOk();
});