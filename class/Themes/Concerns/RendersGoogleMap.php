<?php

namespace Wonder\Themes\Concerns;

use JsonException;
use RuntimeException;
use Wonder\App\Support\GoogleMaps;

trait RendersGoogleMap
{
    use RendersMediaAttributes;

    protected function googleMapContext(object $class): array
    {
        $schema = $class->getSchema();
        $mapId = $this->resolveId($schema['id'] ?? null);
        $config = $class->config();

        if (trim((string) ($config['apiKey'] ?? '')) === '') {
            $config['apiKey'] = GoogleMaps::apiKey();
        }

        if (trim((string) ($config['mapId'] ?? '')) === '') {
            $config['mapId'] = GoogleMaps::mapId();
        }

        return [
            'safe_map_id' => $this->escape($mapId),
            'map_id_json' => $this->encodeGoogleMapJson($mapId),
            'config_json' => $this->encodeGoogleMapJson($config),
            'attributes' => $this->renderMediaAttributes(
                $class,
                ['w-100', 'skeleton'],
                [
                    'id' => $mapId,
                    'data-wonder-map' => '',
                    'data-map-state' => 'idle',
                ]
            ),
        ];
    }

    protected function renderGoogleMapScript(string $mapIdJson, string $configJson): string
    {
        $script = <<<'HTML'
<script>(function(){
    const root=window;
    const api=root.WonderMaps||(root.WonderMaps=(function(){
        const registry=Object.create(null);
        const pending=Object.create(null);
        let loaderPromise=null;
        let loaderKey=null;

        const resolveElement=function(target){
            if(typeof target==='string'){return document.getElementById(target);}
            return target&&target.nodeType===1?target:null;
        };

        const resolveFunction=function(path){
            if(typeof path!=='string'||path===''){return null;}
            const parts=path.split('.');
            let value=root;
            if(parts[0]==='window'){parts.shift();}
            for(const part of parts){
                if(value===null||typeof value==='undefined'){return null;}
                value=value[part];
            }
            return typeof value==='function'?value:null;
        };

        const event=function(name,element,detail){
            const payload=Object.assign({id:element.id,element:element},detail||{});
            element.dispatchEvent(new CustomEvent(name,{detail:payload}));
            root.dispatchEvent(new CustomEvent(name,{detail:payload}));
        };

        const reportError=function(element,error){
            const normalized=error instanceof Error?error:new Error(String(error||'Errore Google Maps'));
            element.classList.remove('skeleton');
            element.dataset.mapState='error';
            event('wonder:map:error',element,{error:normalized});
            if(root.console&&typeof root.console.error==='function'){
                root.console.error('[WonderMaps]',normalized);
            }
            return normalized;
        };

        const markReady=function(element,state){
            if(element.dataset.mapState==='error'||registry[element.id]!==state){
                return;
            }
            element.classList.remove('skeleton');
            element.dataset.mapState='ready';
            event('wonder:map:init',element,{
                manager:state.manager,
                navigator:state.navigator
            });
        };

        const googleApiKey=function(config){
            const configured=String(config.apiKey||'').trim();
            if(configured!==''){return configured;}
            return typeof GOOGLE_API_KEY!=='undefined'?String(GOOGLE_API_KEY||'').trim():'';
        };

        const loadLibraries=function(config){
            const loader=typeof root.requireGoogleMaps==='function'
                ?root.requireGoogleMaps
                :(typeof requireGoogleMaps==='function'?requireGoogleMaps:null);
            if(!loader){
                return Promise.reject(new Error('requireGoogleMaps() non è disponibile nel bundle corrente.'));
            }

            const key=googleApiKey(config);
            if(key===''){
                return Promise.reject(new Error('Google Maps API key mancante.'));
            }

            const libraries=['maps','marker'];
            if(Array.isArray(config.route)&&config.route.length>=2){
                libraries.push('routes');
            }

            if(!loaderPromise){
                loaderKey=key;
                loaderPromise=Promise.resolve(loader(libraries,{key:key}));
            }else if(loaderKey!==key){
                return Promise.reject(
                    new Error('La pagina non può inizializzare Google Maps con chiavi API differenti.')
                );
            }

            return loaderPromise.then(function(){
                if(!root.google||!root.google.maps||typeof root.google.maps.importLibrary!=='function'){
                    throw new Error('Google Maps importLibrary() non è disponibile.');
                }

                return Promise.all(libraries.map(function(library){
                    return root.google.maps.importLibrary(library);
                }));
            });
        };

        const point=function(value){
            if(!value){return null;}
            const lat=Number(value.lat);
            const lng=Number(value.lng);
            if(!Number.isFinite(lat)||!Number.isFinite(lng)){return null;}
            if(lat < -90||lat > 90||lng < -180||lng > 180){return null;}
            return {lat:lat,lng:lng};
        };

        const markerContent=function(renderer,properties){
            if(typeof renderer!=='function'){return null;}
            const content=renderer(properties||{});
            return content&&content.nodeType===1?content:null;
        };

        const closeInfoWindows=function(state){
            for(const infoWindow of state.infoWindows){
                if(infoWindow&&typeof infoWindow.close==='function'){infoWindow.close();}
            }
        };

        const highlight=function(state,entry){
            const content=entry.content;
            if(!content||!content.classList){return;}
            const active=content.classList.contains('highlight');

            for(const other of state.markerEntries){
                if(other.content&&other.content.classList){
                    other.content.classList.remove('highlight');
                }
                if(other.marker){other.marker.zIndex=null;}
            }

            if(!active){
                content.classList.add('highlight');
                entry.marker.zIndex=1;
            }
        };

        const addMarker=function(state,definition,renderer,highlightMarkers){
            const position=point(definition);
            if(!position){return null;}

            const properties=definition.properties&&typeof definition.properties==='object'
                ?definition.properties
                :{};
            const title=String(definition.title||'');
            let marker=null;
            let content=null;

            if(state.manager.mapId
                && google.maps.marker
                && typeof google.maps.marker.AdvancedMarkerElement==='function'
            ){
                marker=state.manager.addAdvancedMarker(
                    position.lat,
                    position.lng,
                    title,
                    properties,
                    renderer===null
                        ?null
                        :function(value){return markerContent(renderer,value);}
                );
                content=marker&&marker.content?marker.content:null;
            }else{
                if(typeof google.maps.Marker!=='function'){
                    throw new Error('Marker Google classico non disponibile e Map ID non configurato.');
                }

                marker=new google.maps.Marker({
                    map:state.manager.map,
                    position:position,
                    title:title
                });
                state.manager.advancedMarkers.push(marker);
                content=markerContent(renderer,properties);

                if(content&&typeof google.maps.InfoWindow==='function'){
                    if(highlightMarkers&&content.classList){content.classList.add('highlight');}
                    const infoWindow=new google.maps.InfoWindow({content:content});
                    state.infoWindows.push(infoWindow);
                    marker.addListener('click',function(){
                        closeInfoWindows(state);
                        infoWindow.open({anchor:marker,map:state.manager.map});
                    });
                }
            }

            const entry={marker:marker,content:content};
            state.markerEntries.push(entry);

            if(highlightMarkers&&content&&state.manager.mapId&&typeof marker.addListener==='function'){
                marker.addListener('click',function(){highlight(state,entry);});
            }

            return marker;
        };

        const removeMarker=function(marker){
            if(!marker){return;}
            if(typeof marker.setMap==='function'){
                marker.setMap(null);
            }else if('map' in marker){
                marker.map=null;
            }
        };

        const dispose=function(state){
            if(!state){return;}
            if(state.navigator&&typeof state.navigator.stop==='function'){
                state.navigator.stop();
            }
            if(state.manager&&typeof state.manager.disableEditing==='function'){
                state.manager.disableEditing();
            }
            if(state.manager&&typeof state.manager.disableRoute==='function'){
                state.manager.disableRoute();
            }
            for(const entry of state.markerEntries){
                removeMarker(entry.marker);
            }
            closeInfoWindows(state);
            if(registry[state.element.id]===state){
                delete registry[state.element.id];
            }
            state.element.dataset.mapState='idle';
        };

        const get=function(target){
            const element=resolveElement(target);
            if(!element){return null;}
            const state=registry[element.id]||null;
            return state&&state.element===element?state:null;
        };

        const destroy=function(target){
            const element=resolveElement(target);
            const id=element
                ?element.id
                :(typeof target==='string'?target:'');
            if(id===''){return null;}

            const job=pending[id];
            if(job){
                job.cancelled=true;
                if(pending[id]===job){delete pending[id];}
            }

            const state=registry[id]||null;
            if(state){dispose(state);}
            if(element){element.dataset.mapState='idle';}
            return null;
        };

        const build=function(element,config){
            const Manager=typeof root.MapManager==='function'
                ?root.MapManager
                :(typeof MapManager==='function'?MapManager:null);
            if(!Manager){
                throw new Error('MapManager non è disponibile nel bundle corrente.');
            }

            const managerOptions={
                travelMode:String(config.travelMode||'DRIVING'),
                onError:function(message){reportError(element,message);}
            };
            const configuredMapId=String(config.mapId||'').trim();
            if(configuredMapId!==''){managerOptions.mapId=configuredMapId;}

            const manager=new Manager(element,managerOptions);
            manager.mapType=String(config.mapType||'roadmap');
            manager.labelsVisible=config.labelsVisible!==false;

            const markers=Array.isArray(config.markers)?config.markers:[];
            const route=Array.isArray(config.route)?config.route.map(point).filter(Boolean):[];
            const firstMarker=markers.map(point).find(Boolean)||null;
            const configuredCenter=point(config.center);
            const center=configuredCenter||firstMarker||route[0]||{lat:20,lng:0};
            const zoom=Number.isFinite(Number(config.zoom))?Number(config.zoom):15;
            const mapOptions=Object.assign(
                {center:center,zoom:zoom,clickableIcons:false},
                config.mapOptions&&typeof config.mapOptions==='object'?config.mapOptions:{}
            );

            const state={
                element:element,
                manager:manager,
                navigator:null,
                markerEntries:[],
                infoWindows:[]
            };

            const navigation=config.navigation&&config.navigation.enabled===true;
            if(route.length>=2){
                if(navigation){
                    const Navigator=typeof root.MapNavigator==='function'
                        ?root.MapNavigator
                        :(typeof MapNavigator==='function'?MapNavigator:null);
                    if(!Navigator){
                        throw new Error('MapNavigator non è disponibile nel bundle corrente.');
                    }
                    state.navigator=new Navigator(manager);
                    state.navigator.init(route);
                    if(config.navigation.auto_start===true){state.navigator.start();}
                }else{
                    manager.initReadOnlyMap(route);
                }
            }else{
                manager.initBaseMap(mapOptions);
            }

            const renderer=resolveFunction(config.markerRenderer);
            if(config.markerRenderer&&renderer===null){
                if(root.console&&typeof root.console.warn==='function'){
                    root.console.warn(
                        '[WonderMaps] Renderer marker non trovato, uso il marker Google predefinito:',
                        String(config.markerRenderer)
                    );
                }
            }

            for(const definition of markers){
                addMarker(state,definition,renderer,config.highlightMarkers===true);
            }

            if(route.length<2
                && markers.length>1
                && config.fitBounds!==false
                && typeof manager.fitBounds==='function'
            ){
                const fitOptions=config.fitBounds&&typeof config.fitBounds==='object'
                    ?config.fitBounds
                    :{};
                manager.fitBounds(fitOptions);
            }

            if(!manager.map){
                throw new Error('MapManager non ha inizializzato la mappa.');
            }

            registry[element.id]=state;
            element.dataset.mapState='loading';

            if(google.maps.event&&typeof google.maps.event.addListenerOnce==='function'){
                google.maps.event.addListenerOnce(manager.map,'tilesloaded',function(){
                    markReady(element,state);
                });
            }else{
                markReady(element,state);
            }

            return state;
        };

        const init=function(target,config){
            const element=resolveElement(target);
            if(!element){return Promise.reject(new Error('Contenitore Google Maps non trovato.'));}

            const existing=registry[element.id]||null;
            if(existing&&existing.element===element){return Promise.resolve(existing);}
            if(existing){dispose(existing);}

            const currentJob=pending[element.id]||null;
            if(currentJob&&currentJob.element===element){return currentJob.promise;}
            if(currentJob){
                currentJob.cancelled=true;
                if(pending[element.id]===currentJob){delete pending[element.id];}
            }

            element.dataset.mapState='loading';
            const job={element:element,cancelled:false,promise:null};
            job.promise=loadLibraries(config||{})
                .then(function(){
                    if(job.cancelled||!element.isConnected){
                        const error=new Error('Inizializzazione Google Maps annullata.');
                        error.name='AbortError';
                        throw error;
                    }
                    return build(element,config||{});
                })
                .catch(function(error){
                    if(error&&error.name==='AbortError'){throw error;}
                    if(registry[element.id]&&registry[element.id].element===element){
                        dispose(registry[element.id]);
                    }
                    throw reportError(element,error);
                })
                .finally(function(){
                    if(pending[element.id]===job){delete pending[element.id];}
                });

            pending[element.id]=job;
            return job.promise;
        };

        const startNavigation=function(target){
            const state=get(target);
            if(!state||!state.navigator){return false;}
            state.navigator.stop();
            state.navigator.start();
            return true;
        };

        const stopNavigation=function(target){
            const state=get(target);
            if(!state||!state.navigator){return false;}
            state.navigator.stop();
            return true;
        };

        const openInGoogleMaps=function(target){
            const state=get(target);
            if(!state||!state.navigator){return false;}
            state.navigator.openInGoogleMaps();
            return true;
        };

        return {
            get:get,
            init:init,
            destroy:destroy,
            startNavigation:startNavigation,
            stopNavigation:stopNavigation,
            openInGoogleMaps:openInGoogleMaps
        };
    })());

    const initializeMap=function(){
        api.init(__MAP_ID__,__CONFIG__).catch(function(){});
    };

    root.addEventListener('loaded',initializeMap);
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded',initializeMap,{once:true});
    }else{
        initializeMap();
    }
})();</script>
HTML;

        return strtr($script, [
            '__MAP_ID__' => $mapIdJson,
            '__CONFIG__' => $configJson,
        ]);
    }

    private function encodeGoogleMapJson(mixed $value): string
    {
        try {
            $json = json_encode(
                $value,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Impossibile serializzare la configurazione Google Maps: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return is_string($json) ? $json : 'null';
    }
}
