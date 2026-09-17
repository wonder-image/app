# Valori FatturaPA

Classi con i codici ufficiali della fattura elettronica, in
`Wonder\Plugin\Custom\Fattura\Valori`. Ogni classe espone la costante `Valori`
(codice → descrizione).

| Classe | Contenuto |
|---|---|
| `TipiDocumento` | TD01–TD28 (TD24 e TD25 per la fattura differita) |
| `Natura` | N1–N7; `Natura::VALIDE` e `Natura::valide()` escludono N2, N3 e N6, non più validi dal 2021 |
| `AliquoteIva` | aliquote italiane `22.00`, `10.00`, `5.00`, `4.00` |
| `EsigibilitaIva` | `I` immediata, `D` differita, `S` scissione dei pagamenti |
| `RegimiFiscali` | RF01–RF19 |
| `Pagamento` | modalità di pagamento MP01–MP23 |
| `CondizioniPagamento` | TP01 a rate, TP02 completo, TP03 anticipo |

```php
use Wonder\Plugin\Custom\Fattura\Valori\Natura;

foreach (Natura::valide() as $code => $description) {
    // opzioni di una select per le operazioni a 0%
}
```
