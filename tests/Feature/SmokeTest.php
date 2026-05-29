<?php

it('responds to the health check route', function () {
    $this->get('/up')->assertOk();
});

it('loads the Laravel welcome page', function () {
    $this->get('/')->assertOk();
});
