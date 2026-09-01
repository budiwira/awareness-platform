<?php

test('home renders landing page for guest', function () {
    $this->get('/')->assertOk();
});

test('login page renders', function () {
    $this->get('/login')->assertOk();
});