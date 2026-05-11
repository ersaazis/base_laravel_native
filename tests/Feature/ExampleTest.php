<?php

test('startup screen renders for guests', function () {
    $response = $this->get('/');

    $response->assertOk();
});
