<?php

arch('globals')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('finance and orders domains do not use floatval')
    ->expect(['App\Domain\Finance', 'App\Domain\Orders'])
    ->not->toUse(['floatval']);

// Prepared for P11:
// arch('order status is only transitioned via StateMachine')
//     ->expect('App\Domain\Orders\StateMachine')
//     ->...
