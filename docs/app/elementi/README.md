# Elementi

La sezione `Elementi` raccoglie i componenti PHP che vengono renderizzati automaticamente sul tema attivo tramite `Wonder\Themes`.

L'obiettivo e' avere una singola API lato PHP e un rendering coerente sia con tema `wonder` sia con tema `bootstrap`.

## Disponibili

* [Charts](charts.md)

## Come funzionano

Gli elementi:

* espongono un'API PHP fluida;
* salvano la configurazione nello `schema` interno;
* vengono renderizzati con il tema attivo tramite `->render()`.

Per i grafici l'HTML finale e l'inizializzazione di Chart.js vengono gestiti automaticamente dal renderer del tema.
