<?php

namespace Wonder\Elements\Media;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

abstract class Media extends Component
{
    use CanSpanColumn;
    use Renderer;
}
