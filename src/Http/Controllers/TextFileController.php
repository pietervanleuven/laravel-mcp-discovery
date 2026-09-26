<?php

namespace PieterVanLeuven\McpDiscovery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PieterVanLeuven\McpDiscovery\Emitters\TextFileEmitter;
use PieterVanLeuven\McpDiscovery\McpDiscovery;

class TextFileController
{
    public function __construct(protected McpDiscovery $discovery) {}

    public function show(Request $request): Response
    {
        $emitter = $this->discovery->emitter((string) $request->route('emitter'));

        abort_unless($emitter instanceof TextFileEmitter && $emitter->enabled(), 404);

        return new Response($emitter->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) $this->discovery->emitterOption('server_card', 'cache', 3600),
        ]);
    }
}
