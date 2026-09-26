<?php

namespace PieterVanLeuven\McpDiscovery\Bots;

use Illuminate\Http\Request;

interface Detector
{
    public function isBot(Request $request): bool;
}
