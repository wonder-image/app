<?php

namespace Wonder\Http;

class RouteDefinition
{
    public function __construct(
        private readonly int $index,
    ) {}

    public function area(string $area): self
    {
        Route::update($this->index, [ 'area' => trim($area) ]);
        return $this;
    }

    public function response(string $response): self
    {
        Route::update($this->index, [ 'response' => trim($response) ]);
        return $this;
    }

    public function theme(?string $theme): self
    {
        Route::update($this->index, [ 'theme' => $theme !== null ? trim($theme) : null ]);
        return $this;
    }

    public function guarded(bool $guarded = true): self
    {
        Route::update($this->index, [ 'private' => $guarded ]);
        return $this;
    }

    public function permit(array $permit): self
    {
        Route::update($this->index, [ 'permit' => $permit ]);
        return $this;
    }

    public function name(string $name): self
    {
        Route::assignName($this->index, $name);
        return $this;
    }

    public function redirect(string $to, int $status = 302): self
    {
        Route::update($this->index, [
            'redirect_to' => $to,
            'redirect_status' => $status,
            'handler' => null,
        ]);
        return $this;
    }

    public function where(string|array $parameter, ?string $pattern = null): self
    {
        Route::applyWhere($this->index, $parameter, $pattern);
        return $this;
    }

    public function mask(string|array $paths, int $status = 301): self
    {
        Route::addMasks($this->index, $paths, $status);
        return $this;
    }

    public function path(string $path): self
    {
        Route::updatePath($this->index, $path);
        return $this;
    }

    public function backend(bool $enabled = true): self
    {
        Route::update($this->index, [ 'backend' => $enabled ]);
        return $this;
    }

    public function frontend(bool $enabled = true): self
    {
        Route::update($this->index, [ 'frontend' => $enabled ]);
        return $this;
    }
}
