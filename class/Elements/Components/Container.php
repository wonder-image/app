<?php

    namespace Wonder\Elements\Components;

    use Wonder\Elements\Component;

    use Wonder\Elements\Concerns\{ IsContainer, HasRatio };

    class Container extends Component {

        use IsContainer, HasRatio;

        public function noGrid(bool $noGrid = true): self
        {

            return $this->schema('no-grid', $noGrid);

        }

    }
