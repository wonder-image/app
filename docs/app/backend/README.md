# Backend

Questa sezione raccoglie la documentazione del backend nuovo.

Se una modifica backend introduce un cambio architetturale o una nuova
convenzione di sviluppo, aggiorna nello stesso lavoro anche la
documentazione correlata, `AGENTS.md` e la skill AI rilevante nel suo
source/fork mantenuto esternamente.

Pagine consigliate:

- [Manuale Model e Resource](resource-manuale/README.md)
- [Resource e CustomPageSchema](resource-manuale/custom-page-schema.md)
- [Migrazione HTTP Backend](migrazione-http-backend.md)
- [Specifica tecnica CRUD dinamico](resource-crud-dinamico.md)

## Renderizzazione form

Nel backend la source of truth dei campi e' nelle classi:

- `Resource::formSchema()` per i CRUD resource
- `CustomPageSchema` per le pagine backend non CRUD

`FormField` e `FormField` definiscono lo schema del campo, ma non devono
essere trattati come helper HTML. La renderizzazione prova prima il
pipeline themed basato su `Wonder\\Elements\\Form\\*` e
`Wonder\\Themes\\Bootstrap\\Form\\*`.

Per compatibilita', gli helper legacy in `app/function/backend/input.php`
restano come fallback per i tipi non ancora coperti dal renderer themed o
per i campi con comportamento speciale.

Ad oggi il renderer themed copre anche i casi speciali backend di
`textDate`, `dateInput`, `dateRange` e `textarea($version)`, mantenendo
il fallback legacy solo per gli helper residui non ancora migrati.
