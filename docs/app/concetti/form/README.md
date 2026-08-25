---
icon: rectangle-list
---

# Form

## Cos'è

Nel framework **ogni input** — sia nel frontend (tema Wonder, markup `.wi-*`)
sia nel backend (tema Bootstrap) — si dichiara con il DSL `FormField` e si rende
con un'unica pipeline. Non si scrive HTML di input a mano.

## La regola dura

{% hint style="danger" %}
**Ogni input passa da `FormField`. Sempre.** Niente `<input>`, `<select>`,
`<textarea>` scritti a mano nelle pagine, nei componenti o nei layout. Niente
funzioni helper che emettono HTML di input. L'unica strada è:

`FormField::key(...)` (dichiarazione) → `Inputs\Input*` (tipo concreto) →
`Input::compile()` / `render($theme)` → componente → tema Wonder o Bootstrap.

È così che restano coerenti il theme switching, lo stato di validazione, il
wiring label/error, il parsing degli attributi e gli helper file/repeater/date.
{% endhint %}

Se manca un tipo di input, **non** lo si aggira con HTML al volo: si crea la
classe tipizzata sotto `ResourceSchema/Inputs`, si implementa `element()` e si
espone il relativo type-helper su `FormField` (vedi
[Sistema Form / Theme / Element](theme-system.md)).

## A cosa serve

- Stesso codice di dichiarazione per frontend e backend, due rese diverse.
- Validazione, label, errori e attributi gestiti in un solo punto.
- Helper pronti per file upload, repeater, date, indirizzi Google, ecc.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| API universale (base) | `class/App/ResourceSchema/Input.php` |
| Una classe per tipo di input | `class/App/ResourceSchema/Inputs/Input*.php` |
| Gruppi di modificatori condivisi | `class/App/ResourceSchema/Inputs/Concerns/*.php` |
| Facade e campo canonico per `formSchema()` | `class/App/ResourceSchema/FormField.php` |
| Riga di repeater | `class/App/ResourceSchema/RepeaterColumn.php` |
| Bridge legacy deprecato | `class/App/Support/FormFieldElementFactory.php` |
| Componenti concreti | `class/Elements/Form/Components/*` |
| Renderer per tema | `class/Themes/Wonder/*`, `class/Themes/Bootstrap/*` |

## Le pagine di questa sezione

- [FormField](form-field.md) — tutti i type-helper e i modificatori,
  con esempi.
- [Repeater](repeater.md) — righe ripetibili e righe correlate a un'altra
  tabella.
- [Sistema Form / Theme / Element](theme-system.md) — la pipeline di rendering e
  come aggiungere un nuovo tipo di input.

## Esempio minimo

```php
use Wonder\App\ResourceSchema\FormField;

public static function formSchema(): array
{
    return [
        FormField::key('name')->text()->required(),
        FormField::key('email')->email()->required(),
        FormField::key('cover')->fileDragDrop('image', 'classic'),
        FormField::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosto'])->value('true'),
    ];
}
```
