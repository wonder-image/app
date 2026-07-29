# Google Maps — mappe frontend

Le mappe Google nei siti Wonder si dichiarano con
`Wonder\Elements\Media\GoogleMap`. L'Element risolve le credenziali, carica
l'API JS una sola volta e gestisce istanze indipendenti di `MapManager`; per
un percorso può creare anche `MapNavigator`.

## Credenziali

Due valori seguono la cascata `.env` → riga `security` → default:

| Valore | Env key | Colonna `security` | Uso |
|---|---|---|---|
| Chiave pubblica GCP | `GCP_CLIENT_API_KEY` | `gcp_client_api_key` | caricamento Maps JavaScript API |
| Map ID | `G_MAPS_MAP_ID` | `g_maps_map_id` | cloud styling e Advanced Marker |

La chiave browser va limitata per dominio e API nella Google Cloud Console.
Il Map ID è opzionale: quando manca, l'Element usa un marker Google classico;
se è presente può usare il contenuto HTML restituito da `markerRenderer()`.

L'helper di basso livello resta disponibile:

```php
use Wonder\App\Support\GoogleMaps;

GoogleMaps::apiKey();
GoogleMaps::mapId();
GoogleMaps::enabled();
GoogleMaps::mapOptions(['travelMode' => 'BICYCLING']);
```

## Mappa con marker

`fromGeoJson()` accetta una Feature, una FeatureCollection o una lista di
Feature `Point`. Le coordinate GeoJSON restano nell'ordine `[lng, lat]`.

```php
use Wonder\Elements\Media\GoogleMap;

echo GoogleMap::fromGeoJson($featureCollection)
    ->id('property-map')
    ->zoom(15)
    ->height(420)
    ->mapType('roadmap')
    ->labels()
    ->fitBounds(['padding' => 40, 'maxZoom' => 15])
    ->render();
```

L'altezza predefinita è `420px`, quindi la mappa non dipende dall'altezza del
contenitore chiamante. `width()` e `height()` accettano pixel interi o valori
CSS; `columnSpan()` resta opzionale come per gli altri Media Element.

Per un marker di dominio, caricare prima una funzione globale che costruisca
il nodo DOM e passarne il percorso validato:

```php
echo GoogleMap::fromGeoJson($featureCollection)
    ->markerRenderer('RealEstateMaps.markerContent')
    ->highlightMarkers()
    ->render();
```

```js
window.RealEstateMaps = {
    markerContent(properties) {
        const marker = document.createElement('div');
        marker.className = 'wi-marker';
        marker.textContent = String(properties.name || '');
        return marker;
    },
};
```

Usare `textContent` per dati esterni. Senza Map ID lo stesso contenuto viene
aperto in una `InfoWindow` ancorata al marker classico.

## Percorso e navigazione

Un percorso richiede almeno due coordinate `{lat, lng}`:

```php
echo GoogleMap::make()
    ->id('route-map')
    ->route($points)
    ->travelMode('WALKING')
    ->navigation()
    ->render();
```

La modalità percorso usa il preset read-only di `MapManager`: centro, zoom e
viewport vengono determinati dalla route, non dalle opzioni della mappa marker.

`navigation()` prepara `MapNavigator`, ma non avvia la geolocalizzazione.
L'avvio deve seguire un'azione esplicita dell'utente:

```js
WonderMaps.startNavigation('route-map');
WonderMaps.stopNavigation('route-map');
WonderMaps.openInGoogleMaps('route-map');
```

`navigation(true, true)` è disponibile per casi controllati, ma può mostrare
subito la richiesta di permesso del browser.

## API runtime

Ogni renderer usa il registro globale condiviso `window.WonderMaps`:

```js
WonderMaps.get('property-map');
WonderMaps.destroy('property-map');
```

L'Element emette `wonder:map:init` sul contenitore e su `window` quando le
tile sono pronte; in errore emette `wonder:map:error`, imposta
`data-map-state="error"` e rimuove lo skeleton. Il payload JSON è codificato
con le protezioni `JSON_HEX_*`, quindi dati come `</script>` non possono
interrompere lo script del renderer.

Per editing, autocomplete o altre API non coperte dall'Element si possono
ancora usare direttamente `requireGoogleMaps()`, `MapManager` e
`MapNavigator` esposti dal bundle `wonder-image`.
