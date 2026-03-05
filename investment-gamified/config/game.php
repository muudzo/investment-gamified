<?php

return [
    'starting_balance' => env('GAME_STARTING_BALANCE', 10000.00),

    'xp' => [
        'buy_reward'         => env('XP_BUY_REWARD', 10),
        'sell_reward'        => env('XP_SELL_REWARD', 15),
        'level_up_base'      => env('LEVEL_BASE_XP', 1000),
    ],
];
