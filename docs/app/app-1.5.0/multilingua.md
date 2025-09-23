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
    
    # Imposto le traduzioni
    TranslationProvider::init();
```

E aggiungere al file .htaccess nel `Backend` reparto `Set Up` -> `Editor` il seguente codice:

```
## Aggiunge lo slash finale a tutte le URL se manca
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*[^/])$ /$1/ [R=301,L]

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

La funzione [`__t`](#user-content-fn-1)[^1] si usa per cercare testi. Nei file JSON, puoi inserire chiavi con `{{key}}` e utilizzare la variabile `$replacements` per inserire il nome effettivo.

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

La funzione `__u` si usa per creare url

```php
function __u(string $path) {}
```

La funzione `__su` si usa per cambiare url da una lingua all'altra

```php
function __su(string $url, string $lang) {}
```

## SEO

Per migliorare l'indicizzazione del sito aggiungere in ogni file:

```php
$PAGE_KEY = 'home';

$SEO->title = __t("pages.{$PAGE_KEY}.seo.title");
$SEO->description = __t("pages.{$PAGE_KEY}.seo.description");
$SEO->url = __u();
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
        $link = __su($SEO->url, $lang);
        $flag = $value['flag'];

        $dropdownLang .= '<a href="'.$link.'" hreflang="'.$lang.'/" class="wi-dropdown-item"><span class="fi fi-'.$flag.' fis" style="border-radius: 50%;"></span> '.$name.'</a>';

    }

?>

<div class="wi-dropdown-btn f-end phone-none">
    <div class="btn btn-secondary tx-upper wi-switcher" style="width: auto;padding-right:0;"> <?=$defaultFlag?> </div>
    <div class="wi-dropdown-list"> <?=$dropdownLang?> </div>
</div>
```

#### Testo

```php
<?php

    $langHTML = [];

    foreach (__ls() as $lang => $value) {
        
        $link = __su($SEO->url, $lang);

        $bold = $lang == __l() ? "fw-700" : "";

        $langHTML[] = "<a href=\"$link\" hreflang=\"$lang\" class=\"tx-none $bold\">$lang</a>";

    }

?>

<div class="f-end c-h phone-none tx-white tx-upper">
    <?=implode(' | ', $langHTML)?>
</div>
```

#### Flag

Aggiungi al file frontend/set-up.php:

```php
Dependencies::flagIcons()
```

[^1]: Utilizzabile sia in JS che in PHP
