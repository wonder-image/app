<?php

    namespace Wonder\Plugin\Google\Merchant;

    use Wonder\Concerns\HasSchema;
    use Wonder\Plugin\Google\Merchant\Item;
    use DOMDocument;
    
    class Feed {

        use HasSchema;

        public function __construct( $title, $link, $description ) 
        {

            $this->schema('title', $title);
            $this->schema('link', $link);
            $this->schema('description', $description);

        }

        public function item( Item $product ): self
        {

            if ($product->checkSchema()) {
                $this->schemaPush('item', $product->getSchema());
            }

            return $this;

        }

        public function xml(): DOMDocument
        {

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            // Root <rss>
            $rss = $dom->createElement('rss');
            $rss->setAttribute('version', '2.0');
            $rss->setAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $dom->appendChild($rss);

            // <channel>
            $channel = $dom->createElement('channel');
            $rss->appendChild($channel);

            // Titolo, link e descrizione principali
            $channel->appendChild($dom->createElement('title', $this->getSchema('title')));
            $channel->appendChild($dom->createElement('link', $this->getSchema('link')));
            $channel->appendChild($dom->createElement('description', $this->getSchema('description')));

            // Genera automaticamente i tag per ogni item
            foreach ($this->getSchema('item') as $item) {

                $itemNode = $dom->createElement('item');
                $channel->appendChild($itemNode);

                foreach ($item as $key => $value) {
                    // Se il valore è un array (es. immagini multiple)
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $itemNode->appendChild($dom->createElement('g:' . $key, htmlspecialchars($v)));
                        }
                    } else {
                        $itemNode->appendChild($dom->createElement('g:' . $key, htmlspecialchars($value)));
                    }
                }
            }

            $dom->formatOutput = true;

            return $dom;

        }

        public function generate($file = 'xml')
        {

            return $this->xml()->saveXML();

        }


    }