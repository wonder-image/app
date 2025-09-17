---
icon: language
---

# Multilingua

Per aggiungere più lingue è possibile aggiungere un file in `/custom/config/lang.php` con il seguente codice:

```php
<?php

    use Wonder\Localization\{ LanguageContext, TranslationProvider };

    # Imposto le lingue
    LanguageContext::addLangPath($ROOT.'/lang/')
        ::defaultLang('it')
        ::addLanguage('it', 'Italiano', "https://www.$PAGE->domain/it/", 'it', ['IT'])
        ::addLanguage('en', 'English', "https://www.$PAGE->domain/en/", 'gb', [])
        ::setLangFromPath();

    # Imposto la URL del sito
    $PATH->site = LanguageContext::getSitePath();
    
    # Imposto le variabili globali
    TranslationProvider::setGlobals([
            'path_site' => $PATH->site
        ]);
```

E aggiungere al file .htaccess nel `Backend` reparto `Set Up` -> `Editor` il seguente codice:

```
## Reindirizza lingue alla cartella /theme/
RewriteCond %{REQUEST_URI} ^/(it|en|de)/?(.*)$
RewriteRule ^(it|en|de)/(.*)$ /theme/$2 [L,QSA]
RewriteRule ^(it|en|de)/?$ /theme/index.php [L,QSA]
```

## Redirect

Nel file index.php è consigliato utilizzare questo codice per il redirect dell'utente alla lingua determinata dal paese del suo indirizzo IP.

```php
<?php
    
    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";
        
    (new Wonder\Localization\LanguageRedirector())
        ->redirectByCountry($_SESSION['system_cache']['country'] ?? null);
```

## Funzioni utili

La funzione `__t` si usa per cercare testi. Nei file JSON, puoi inserire chiavi con `{{key}}` e utilizzare la variabile `$replacements` per inserire il nome effettivo.

```php
function __t(string $key, array $replacements = []) {}
```

La funzion `__l` si usa per sapere la lingua impostata.

```php
function __l() {}
```

La funzione `__ls` si usa per restituire tutte le lingue impostate.

```php
function __ls() {}
```

## SEO

Per migliorare l'indicizzazione del sito aggiungere in ogni file:

```php
$PAGE_KEY = 'home';

$SEO->title = __t("pages.{$PAGE_KEY}.seo.title");
$SEO->description = __t("pages.{$PAGE_KEY}.seo.description");
$SEO->url = $PATH->site;
$SEO->breadcrumb = [
    $SEO->url => __t("components.navigation.{$PAGE_KEY}")
];
```

E per indicare la lingua del sito:

```php
<!DOCTYPE html>
<html lang="<?=__l()?>">
<head>
    ...
```

## Utilità

#### Dropdown

```php
<?php
    
    $dropdownLang = "";

    $defaultFlag = '<span class="fi fi-'.__ls()[__l()]['flag'].' fis" style="border-radius: 50%;border: 1px solid #fff;"></span>';
    
    foreach (__ls() as $lang => $value) {
        
        $name = $value['name'];
        $link = str_replace($PATH->site, $value['link'], $SEO->url);
        $flag = $value['flag'];

        $dropdownLang .= '<a href="'.$link.'" hreflang="'.$lang.'/" class="wi-dropdown-item"><span class="fi fi-'.$flag.' fis" style="border-radius: 50%;"></span> '.$name.'</a>';

    }

?>

<div class="wi-dropdown-btn f-end phone-none">
    <div class="btn btn-secondary tx-upper wi-switcher" style="width: auto;padding-right:0;"> <?=$defaultFlag?> </div>
    <div class="wi-dropdown-list"> <?=$dropdownLang?> </div>
</div>
```

#### Flag

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.0.0/css/flag-icons.min.css"/>
```
