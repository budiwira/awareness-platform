<?php

test('home redirects guest to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('login page renders', function () {
    $this->get('/login')->assertOk();
});