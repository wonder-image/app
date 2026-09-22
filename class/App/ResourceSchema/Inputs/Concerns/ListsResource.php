<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Dichiara di quale risorsa questo campo elenca le righe.
 *
 * Serve a chi crea una riga da un'altra parte della stessa pagina — il
 * «+ Aggiungi» di un campo vicino — per sapere quali altri campi vanno
 * aggiornati: senza, una categoria creata da un select non comparirebbe
 * nell'albero delle categorie della stessa schermata, che elenca le stesse
 * righe e non se ne accorge.
 *
 * `quickCreate()` la dichiara da sé sul campo che apre il modale: questo
 * metodo serve ai campi che la risorsa la elencano soltanto.
 */
trait ListsResource
{
    public function listsResource(string $resourceClass): static
    {
        $slug = method_exists($resourceClass, 'slug') ? (string) $resourceClass::slug() : '';

        if ($slug === '') {
            return $this;
        }

        $this->schema['lists_resource'] = $slug;

        return $this;
    }
}
