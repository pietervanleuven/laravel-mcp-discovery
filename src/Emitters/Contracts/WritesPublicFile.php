<?php

namespace PieterVanLeuven\McpDiscovery\Emitters\Contracts;

interface WritesPublicFile
{
    /**
     * File name relative to the public directory, e.g. "llms.txt".
     */
    public function fileName(): string;

    /**
     * Write (or replace) this emitter's section in the given file contents.
     */
    public function inject(string $contents): string;
}
