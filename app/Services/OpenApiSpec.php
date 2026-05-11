<?php

namespace App\Services;

use Illuminate\Support\Arr;

class OpenApiSpec
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $spec = null;

    public function serverUrl(): string
    {
        return (string) Arr::get($this->load(), 'servers.0.url', '');
    }

    public function hasOperation(string $method, string $path): bool
    {
        $method = strtolower($method);

        foreach (array_keys(Arr::get($this->load(), 'paths', [])) as $template) {
            if ($this->pathMatches((string) $template, $path)) {
                return Arr::has($this->load(), "paths.{$template}.{$method}");
            }
        }

        return false;
    }

    public function assertOperation(string $method, string $path): void
    {
        if (! $this->hasOperation($method, $path)) {
            throw new MobileApiException("OpenAPI operation not found for [{$method} {$path}].");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->spec !== null) {
            return $this->spec;
        }

        $decoded = json_decode(file_get_contents(resource_path('openapi.json')), true);

        return $this->spec = is_array($decoded) ? $decoded : [];
    }

    private function pathMatches(string $template, string $path): bool
    {
        $pattern = preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($template, '#'));

        return is_string($pattern) && preg_match("#^{$pattern}$#", $path) === 1;
    }
}
