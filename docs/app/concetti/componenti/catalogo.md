---
icon: grid-2
---

# Catalogo dei componenti

## Cos'è

Il **catalogo** è la documentazione viva dei componenti: una pagina per ogni
Element (`Button`, `Alert`, `InputText`, `Image`, ...) con la descrizione, gli
esempi d'uso e, accanto a ogni esempio, l'anteprima resa dal tema scelto —
**Wonder** (frontend) o **Bootstrap** (backend, anche in modalità scura) — più
l'elenco dei metodi letto dalla classe.

L'indice mostra tutti i componenti per categoria (Form, Componenti, Media,
Grafici) e per ciascuno dice in quali temi esiste il renderer. Non è una lista
scritta a mano: il catalogo lo chiede al `Themes\Resolver`, lo stesso che usa
`render($theme)`.

## Dove si apre

| Dove | Come |
|---|---|
| **Backend di un sito** | sezione **Dev → Componenti** (`/backend/app/docs/components/`), per gli utenti `admin`. Le anteprime Wonder usano i token CSS del sito (`set-up/root.css`): vedi i componenti come appaiono *nel tuo* frontend. |
| **Dalla radice di `wonder-image/app`**, senza un sito | `npm install` una volta, poi `composer docs` (o `php bin/docs.php`) e apri `http://127.0.0.1:8090/`. Serve gli asset di `wonder-image/lib` da `node_modules` e usa i token di default del framework. |

## Come è fatto

Le schede stanno in `docs/components/<categoria>/*.php` del pacchetto: ogni
file ritorna un `Wonder\Docs\ComponentDoc` con descrizione, esempi e note. Il
codice di un esempio è **lo stesso** che viene eseguito per l'anteprima
(`Wonder\Docs\ExampleRunner`), quindi ciò che copi è ciò che vedi.

Le anteprime sono `<iframe>` che caricano una pagina a sé con i soli asset del
tema: è l'unico modo di mostrare Wonder e Bootstrap sulla stessa pagina senza
che i due CSS si sovrappongano. Il selettore in testa alla pagina cambia il tema
di tutte le anteprime (e il chiaro/scuro di quelle Bootstrap); ogni riquadro ha
comunque le sue schede. Dove un componente o un'API esiste in un tema solo, il
riquadro lo dice al posto dell'anteprima.

La guida completa per scrivere una scheda, con le regole del DSL e i comandi
di validazione, è in
[`docs/components/README.md`](https://github.com/wonder-image/app/blob/main/docs/components/README.md).

## I componenti nati con il catalogo

Due Element nuovi, usabili ovunque:

- **`Code`** (`Wonder\Elements\Components\Code`): un blocco di codice con
  evidenziazione server-side (PHP dal tokenizer nativo; HTML, CSS, JS, JSON,
  bash) e bottone "copia". `Code::make($codice, 'php')->title('esempio.php')`.
- **`Preview`** (`Wonder\Elements\Components\Preview`): una finestra con più
  sorgenti selezionabili e passaggio chiaro/scuro.
  `Preview::make('Titolo')->source('bootstrap', 'Bootstrap', $url, ['schemes' => true])`.

Entrambi esistono nei due temi; CSS e script escono una volta per pagina da
`Themes\Support\PageAssets`.

## E GitBook?

Questa guida resta su GitBook per i concetti e le regole. GitBook rende
Markdown statico: non può eseguire PHP né caricare la lib, quindi le anteprime
vivono nel catalogo. Se un'istanza del catalogo sarà pubblica, si potrà
incorporarla qui con un embed.

## Collegamenti con il resto

- [Componenti UI](README.md) — la guida ai componenti di layout e di pagina.
- [Sistema Form / Theme / Element](../form/theme-system.md) — come un Element
  diventa HTML nel tema attivo.
- [Versioni e release](../../piattaforma/versioni-e-release.md) — la versione
  minima di `wonder-image/lib`, che il `package.json` del pacchetto ripete.
