<?php

namespace PieterVanLeuven\McpDiscovery\Emitters;

use Illuminate\Routing\Router;
use PieterVanLeuven\McpDiscovery\Emitters\Contracts\WritesPublicFile;
use PieterVanLeuven\McpDiscovery\Http\Controllers\TextFileController;

/**
 * Emitters that add a section to a plain text file at the site root.
 *
 * In 'route' mode the file is served dynamically. In 'file' mode
 * `php artisan mcp-discovery:write` updates the static file in public/.
 */
abstract class TextFileEmitter extends Emitter implements WritesPublicFile
{
    abstract public function section(): string;

    abstract protected function defaultBase(): string;

    abstract protected function startMarker(): string;

    abstract protected function endMarker(): string;

    public function mode(): string
    {
        return (string) $this->option('mode', 'route');
    }

    public function routes(Router $router): void
    {
        if ($this->mode() !== 'route') {
            return;
        }

        $router->get($this->fileName(), [TextFileController::class, 'show'])
            ->defaults('emitter', $this->key)
            ->name("mcp-discovery.{$this->key}");
    }

    public function render(): string
    {
        $base = $this->option('base');

        $contents = is_string($base) && is_file($base)
            ? (string) file_get_contents($base)
            : $this->defaultBase();

        return $this->inject($contents);
    }

    public function inject(string $contents): string
    {
        $section = $this->section();
        $block = $section === '' ? '' : $this->startMarker()."\n".$section."\n".$this->endMarker();

        $pattern = '/'.preg_quote($this->startMarker(), '/').'.*?'.preg_quote($this->endMarker(), '/').'/s';

        if (preg_match($pattern, $contents)) {
            return (string) preg_replace_callback($pattern, fn () => $block, $contents);
        }

        if ($block === '') {
            return $contents;
        }

        $contents = rtrim($contents);

        return ($contents === '' ? '' : $contents."\n\n").$block."\n";
    }
}
