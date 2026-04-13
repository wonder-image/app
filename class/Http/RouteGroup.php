<?php

namespace Wonder\Http;

class RouteGroup
{
    private array $attributes = [];

    public function area(string $area): self
    {
        $this->attributes['area'] = trim($area);
        return $this;
    }

    public function response(string $response): self
    {
        $this->attributes['response'] = trim($response);
        return $this;
    }

    public function theme(?string $theme): self
    {
        $this->attributes['theme'] = $theme !== null ? trim($theme) : null;
        return $this;
    }

    public function guarded(bool $guarded = true): self
    {
        $this->attributes['private'] = $guarded;
        return $this;
    }

    public function permit(array $permit): self
    {
        $this->attributes['permit'] = $permit;
        return $this;
    }

    public function where(string|array $parameter, ?string $pattern = null): self
    {
        $where = is_array($parameter)
            ? $parameter
            : [ $parameter => $pattern ];

        $current = isset($this->attributes['where']) && is_array($this->attributes['where'])
            ? $this->attributes['where']
            : [];

        $this->attributes['where'] = array_merge($current, $where);

        return $this;
    }

    public function name(string $name): self
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->attributes['prefix'] = $prefix;
        return $this;
    }

    public function path(string $path): self
    {
        return $this->prefix($path);
    }

    public function backend(bool $enabled = true): self
    {
        $this->attributes['backend'] = $enabled;
        return $this;
    }

    public function frontend(bool $enabled = true): self
    {
        $this->attributes['frontend'] = $enabled;
        return $this;
    }

    public function group(callable $callback): void
    {
        Route::group($this->attributes, $callback);
    }
}
