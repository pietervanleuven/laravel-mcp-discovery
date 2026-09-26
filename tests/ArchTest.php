<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('emitters extend the base emitter')
    ->expect('PieterVanLeuven\McpDiscovery\Emitters')
    ->classes()
    ->toExtend('PieterVanLeuven\McpDiscovery\Emitters\Emitter')
    ->ignoring('PieterVanLeuven\McpDiscovery\Emitters\Emitter');

arch('bot detectors implement the contract')
    ->expect('PieterVanLeuven\McpDiscovery\Bots\UserAgentDetector')
    ->toImplement('PieterVanLeuven\McpDiscovery\Bots\Detector');
