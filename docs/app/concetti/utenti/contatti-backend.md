# Contatti backend

Il core espone `App/Resources/Contacts/ContactResource` e
`ContactAddressResource` senza richiedere ecommerce o gestionale. Le URL sono
`/backend/contacts/` e `/backend/contact-addresses/`; i nomi delle route sono
`backend.resource.contacts.*` e `backend.resource.contact-addresses.*`.

## Responsabilità

- Contatti: identità privata/azienda, recapiti, una fatturazione opzionale,
  note e stato della scheda. Nome/cognome o ragione sociale sono richiesti;
  una scheda nuova non richiede indirizzo di fatturazione o account.
- Indirizzi: elenco filtrabile per contatto, aggiunta e modifica. Il pulsante
  nell'header della modifica contatto apre il relativo elenco. Destinatario e
  indirizzo completo sono validati tramite `AccountAddressValidation`;
  provincia richiesta solo nei paesi con stati/province, etichetta opzionale.
- Tabelle condivise: `contacts` e `contact_addresses`, senza copie o reset.
- Account, consensi, ordini, coupon e credenziali di pagamento non vengono
  gestiti da queste Resource. `user_id`, ruoli commerciali, riferimenti esterni,
  flags predefinito e payload personalizzati non sono modificabili da POST.

## Sicurezza e compatibilità

Elenco, creazione e modifica richiedono `admin` o `administrator`. I form
includono `_contact_csrf` e i mutatori lo verificano prima della scrittura.
Gli indirizzi devono appartenere a un contatto esistente: non possono essere
trasferiti modificando `contact_id`. API ed eliminazione sono disabilitate.

Il menu Contatti generico è visibile nei progetti app-only; una Resource più
completa di modulo/sito sulla stessa tabella nasconde il menu generico, senza
togliere le sue URL. `Resource::isTableFallback()` marca questi pannelli come
fallback: `ResourceRegistry::resolveByTable()` preferisce le Resource non
fallback e conserva la precedenza precedente tra queste. Il gestionale
continua quindi a ricevere i link dai consensi e dagli altri lookup per tabella.
Il sito può estendere le Resource e sovrascrivere gli slug come di consueto.

Le traduzioni core vengono precaricate dal dispatcher prima di `lang.php`,
perché una chiamata `__r()` può registrare Resource che usano `__t()` nei
metadati. Resta l'ordine core → moduli → sito. Il presenter idrata il paese dei
campi `states` dalle chiavi `province`/`country`, anche prefissate; la lib
backend usa `/api/states/` mantenendo il fallback legacy, il valore a paese
invariato e la protezione contro risposte obsolete.

`AccountAddressForm` abilita il contesto `required_when_states_available`
sull'input provincia: `InputStates` adegua l'obbligatorietà al paese idratato,
senza modificare gli altri utilizzi del componente. Il prefisso telefonico
iniziale deriva dal paese del contatto (Italia quando assente); un valore
esplicito o un valore vuoto inviato con POST non viene sostituito.

## Verifica

Dal sito: `php forge update --local` e `php forge start --driver=herd`.
Dal modulo ecommerce: `php tests/integrazione/ContactResourcesTest.php` e
`node tests/integrazione/ContactResourcesBrowserTest.cjs`. I test PHP usano
transazioni annullate; i test browser renderizzano fixture CLI e l'API locale,
senza bypassare il login web. Verificare anche il percorso autenticato reale
con un amministratore di test quando disponibile.

Dalla lib: `node test/backend-country-state.test.cjs`, `npm test` e
`npm run build`. Distribuire il bundle backend aggiornato insieme al core.
