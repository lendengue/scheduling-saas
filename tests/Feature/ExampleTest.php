<?php

test('the landing page renders on the central domain', function () {
    $this->get(route('home'))->assertOk();
});
