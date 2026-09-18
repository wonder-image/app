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

        /**
         * Dispone i figli in colonne che si riempiono dall'alto in basso
         * (multi-colonna CSS), invece della griglia a righe: le schede si
         * impilano una sotto l'altra e passano alla colonna dopo, senza buchi
         * quando hanno altezze diverse.
         *
         * `$minWidth` è la larghezza minima di una colonna: sotto quella
         * misura il contenuto torna a una colonna sola, senza media query.
         */
        public function masonry(int $columns = 2, string $minWidth = '22rem', string $gap = '1rem'): self
        {

            return $this
                ->schema('masonry', max(1, $columns))
                ->schema('masonry-min-width', trim($minWidth) !== '' ? trim($minWidth) : '22rem')
                ->schema('masonry-gap', trim($gap) !== '' ? trim($gap) : '1rem');

        }

    }
