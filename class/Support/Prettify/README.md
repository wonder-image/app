# Wonder\\Support\\Prettify

Questa cartella contiene classi di **presentazione** (output user-facing), non pipeline dati.

## Classi

- `Wonder\Support\Prettify\Date`: traduzioni giorno/mese e data leggibile.
- `Wonder\Support\Prettify\Phone`: normalizzazione, analisi e formattazione numero telefono.
- `Wonder\Support\Prettify\Address`: rendering testuale/HTML/PDF indirizzi.

## Regola con `Data\Formatters`

- `Wonder\Data\Formatters`: trasformazioni dati nel flusso di input/validazione.
- `Wonder\Support\Prettify`: formattazione di output per UI, testi leggibili e localizzazione.

In breve: `Formatters` per il dato tecnico, `Prettify` per il dato mostrato all'utente.
