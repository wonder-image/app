<?php
    
    namespace Wonder\Elements\Table;

    use Wonder\Elements\Component;

    use Wonder\App\Resource;

    use Wonder\App\{ Path };

    use Wonder\Backend\Table\Table as Datatable;

    use Wonder\Elements\Concerns\{ CanSpanColumn, Renderer };

    class Table extends Component {

        use CanSpanColumn, Renderer;

        public Datatable $DT;

        public function __construct( Resource $Resource ) 
        {

            $Model = $Resource::$model;

            $this->DT = new Datatable($Model::$table, $Model::connection());
            $this->DT->endpoint((new Path)->apiDT);

            $this->DT->query($Resource::$condition);
            $this->DT->queryOrder($Resource::$orderColumn, $Resource::$orderDirection);

            $this->DT->addLink( 'view', (new Path)->backend.'/'.$Model::$folder.'/view.php?redirect={redirectBase64}&id={rowId}' );
            $this->DT->addLink( 'modify', (new Path)->backend.'/'.$Model::$folder.'/?redirect={redirectBase64}&modify={rowId}' );
            $this->DT->addLink( 'duplicate', (new Path)->backend.'/'.$Model::$folder.'/?redirect={redirectBase64}&duplicate={rowId}' );
            $this->DT->addLink( 'download', (new Path)->backend.'/'.$Model::$folder.'/download.php?id={rowId}' );
            $this->DT->addLink( 'file', (new Path)->upload.'/'.$Model::$folder );

            $this->DT->labels($Resource::getLabel());

            $this->DT->text(
                $Resource::getText('label'),
                $Resource::getText('plural_label'),
                $Resource::getText('last'),
                $Resource::getText('all'),
                $Resource::getText('article'),
                $Resource::getText('full'),
                $Resource::getText('empty'),
                $Resource::getText('this')
            );

        }


        public static function resource( Resource $resource ): self
        {

            return new self($resource);

        }

        public function title(bool $bool = true): self
        {

            $this->DT->title($bool);

            return $this;

        }

        public function nResult(bool $bool = true): self
        {

            $this->DT->titleNResult($bool);

            return $this;

        }

        public function columns(array $columns): self
        {
            
            $this->DT->columns($columns);

            return $this;

        }

        public function filter(array $filters): self
        {

            return $this;

        }


    }