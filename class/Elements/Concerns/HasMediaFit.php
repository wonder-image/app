<?php

namespace Wonder\Elements\Concerns;

trait HasMediaFit
{
    public function fitCover(bool $cover = true): self
    {
        if ($cover) {
            $this->schema('fit-contain', false);
        }

        return $this->schema('fit-cover', $cover);
    }

    public function fitContain(bool $contain = true): self
    {
        if ($contain) {
            $this->schema('fit-cover', false);
        }

        return $this->schema('fit-contain', $contain);
    }
}
