# Multilingua

Per aggiungere più lingue è possibile aggiungere al file set-up.php il seguente codice:

```php
# Aggiungo tutte le lingue
$LANG
    ->addLanguage('it', 'Italiano', "https://www.$PAGE->domain/it/", 'it', ['IT'])
    ->addLanguage('en', 'English', "https://www.$PAGE->domain/en/", 'gb', [])
    ->addLanguage(code, name, link, flag, countries)
    ->setLangFromPath();
    
# Resetto il TranslationProvider e aggiuungo delle variabili globali
TranslationProvider::init($LANG)
    ->setGlobals([
        'path_site' => $LANG->getSitePath(),
    ]);
    

# Imposto la URL del sito
$PATH->site = $LANG->getSitePath();
```
