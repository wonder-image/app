# Google Maps — mappe frontend

Le mappe Google nei siti Wonder si costruiscono con le classi JS della lib
`wonder-image` (`MapManager`, `MapNavigator`, loader `requireGoogleMaps()`,
marker `gmapMarkerConstructor()` / componente `.wi-marker`) e con l'helper PHP
`Wonder\App\Support\GoogleMaps` per le credenziali.

## Credenziali

Due valori, entrambi con la cascata standard `.env` → riga `security` (backend
Impostazioni → Sicurezza, sezione "Google Maps") → default:

| Valore | Env key | Colonna `security` | Uso |
|---|---|---|---|
| Chiave pubblica GCP | `GCP_CLIENT_API_KEY` | `gcp_client_api_key` | caricamento API JS (emessa come costante JS `GOOGLE_API_KEY` dagli head layout) |
| Map ID | `G_MAPS_MAP_ID` | `g_maps_map_id` | stile mappa cloud + AdvancedMarkerElement; NON esiste come costante JS, va passato alle opzioni di `MapManager` |

Il Map ID si crea su Google Cloud Console (Maps → Map management). Senza Map ID
gli `AdvancedMarkerElement` (marker custom `.wi-marker`) non sono disponibili e
la lib ripiega sui `Marker` classici.

## Helper `Wonder\App\Support\GoogleMaps`

```php
use Wonder\App\Support\GoogleMaps;

GoogleMaps::apiKey();      // string, gcp_client_api_key trimmata
GoogleMaps::mapId();       // string, g_maps_map_id trimmata
GoogleMaps::enabled();     // bool, true se la chiave e' configurata
GoogleMaps::mapOptions();  // array opzioni per new MapManager(el, options)
```

`mapOptions()` include `mapId` solo se configurato e accetta override:

```php
GoogleMaps::mapOptions([ 'travelMode' => 'BICYCLING' ]);
```

## Esempio componente

```php
<?php use Wonder\App\Support\GoogleMaps; ?>

<?php if (GoogleMaps::enabled()) { ?>

    <div id="map" class="w-100 h-100"></div>

    <script>

        (async () => {

            await requireGoogleMaps([ 'maps', 'marker' ]);

            const MAP = new MapManager(
                document.getElementById('map'),
                <?=js_e(GoogleMaps::mapOptions())?>
            );

            MAP.initBaseMap({ center: { lat: 45.8, lng: 8.8 }, zoom: 12 });
            MAP.addAdvancedMarker(45.8, 8.8, 'Titolo', properties, gmapMarkerConstructor);

        })();

    </script>

<?php } ?>
```

`requireGoogleMaps()` usa di default la costante JS `GOOGLE_API_KEY` gia'
emessa dagli head layout; il loader e' promise-based e non chiama nessuna
callback globale. Per il resto dell'API JS (percorsi, editing, navigazione
GPS, theming `.wi-marker` via custom properties `--wi-marker-*`) vedi la
documentazione della lib `wonder-image` (`docs/` del repo lib e MANIFEST.json).
