# Font web

`Wonder\View\WebFonts` è il catalogo chiuso dei font che un sito può usare nelle aree
senza header (accesso, account, checkout) e nel carrello, scelti nel gestionale. I file
woff2 sono in `resources/assets/font/web/` (Fontsource, licenza OFL), serviti dal sito:
niente richieste a Google.

| Chiave | Font |
|---|---|
| `inter` | Inter |
| `roboto` | Roboto |
| `open-sans` | Open Sans |
| `lato` | Lato |
| `montserrat` | Montserrat |
| `poppins` | Poppins |
| `dm-sans` | DM Sans |
| `nunito` | Nunito |
| `work-sans` | Work Sans |

```php
use Wonder\View\WebFonts;

WebFonts::all();          // ['inter' => 'Inter', …] per un menu a tendina
WebFonts::has('inter');   // true
$css = WebFonts::css('inter');
if ($css !== '') {
    echo '<style>'.$css.'</style>';
}
```

- `css()` restituisce i `@font-face` e una regola `html:root{…}` che ridefinisce
  `--font-family`, `--title-big-font-family`, `--title-font-family`,
  `--subtitle-font-family`, `--text-font-family` e `--text-small-font-family`.
  `html:root` vince sul `:root` di `root.css` del sito in qualunque ordine.
- Chiave vuota o sconosciuta → `''`: la pagina resta nel font del sito.
- L'URL dei file parte da `$PATH->appAssets`; nei test si passa `css($key, $baseUrl)`.
- Font variable per Inter, Roboto, Open Sans, Montserrat, DM Sans, Nunito e Work Sans;
  pesi statici per Lato (400, 700) e Poppins (400, 500, 600, 700).
