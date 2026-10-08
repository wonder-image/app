# Head, schema.org e breadcrumb

Registra asset e markup attendibile prima della chiusura del layout:

```php
View::head('<link rel="stylesheet" href="'.e($cssUrl).'">');
View::head('<script src="'.e($jsUrl).'" defer></script>');
View::head($dataLayerScript);
```

Il componente `frontend.layout.head` stampa il registro una sola volta;
markup identico viene deduplicato. `View::renderHead()` svuota il registro.
I layout personalizzati che sostituiscono l'head devono chiamarlo nell'head,
dopo le dipendenze. Registra gli script che leggono il DOM con `defer` oppure
inizializzali su `DOMContentLoaded`.

Passa i dati strutturati come array PHP:

```php
$SEO->schemaOrg = ['@type' => 'Product', 'name' => $name, 'offers' => $offers];
$SEO->breadcrumb = [$homeUrl => 'Home', $productUrl => $name];
```

`schemaOrg` accetta una singola entità, una lista di entità o un documento
con `@graph`. Il core serializza un unico `@graph` nell'head e aggiunge il
breadcrumb tramite `breadcrumb()`. Se `$SEO->breadcrumb` è valorizzato,
sostituisce eventuali `BreadcrumbList` passati in `schemaOrg`, evitando
duplicati. Le entità usano il contesto schema.org condiviso del documento.
Le pagine private devono lasciare entrambi gli array vuoti.

Il breadcrumb visibile è indipendente dal JSON-LD e riutilizzabile nei siti:

```php
use Wonder\Elements\Components\Breadcrumb;

echo Breadcrumb::make($SEO->breadcrumb)->class('mb-5');
echo Breadcrumb::make($list)->label(__t('navigation.breadcrumb'));
```

`Breadcrumb::make($list)` oppure `new Breadcrumb($list)` accettano una mappa
URL => nome o una lista di `['url' => ..., 'name' => ...]`.
La label predefinita è `breadcrumb`; `label()` la personalizza.
Il renderer segue il tema attivo: Wonder usa la lista con `/`, Bootstrap usa
`breadcrumb`, `breadcrumb-item` e `active`. `render('bootstrap')` permette
di scegliere esplicitamente il tema fuori da un layout.
L'ultima voce ha `aria-current="page"`; i separatori `/` sono nascosti
alle tecnologie assistive. URL assoluti restano invariati e quelli locali
passano da `__u()`. Il componente non genera JSON-LD.
