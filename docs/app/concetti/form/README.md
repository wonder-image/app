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

`FormInput::key(...)` (dichiarazione) → `FormField::render($theme)` (resa) →
`FormFieldElementFactory` → componente → tema Wonder o Bootstrap.

È così che restano coerenti il theme switching, lo stato di validazione, il
wiring label/error, il parsing degli attributi e gli helper file/repeater/date.
{% endhint %}

Se manca un tipo di input, **non** lo si aggira con HTML al volo: si estende
`FormField` e si mappa il nuovo elemento (vedi
[Sistema Form / Theme / Element](theme-system.md)).

## A cosa serve

- Stesso codice di dichiarazione per frontend e backend, due rese diverse.
- Validazione, label, errori e attributi gestiti in un solo punto.
- Helper pronti per file upload, repeater, date, indirizzi Google, ecc.

## Dove si trova nel codice

| Elemento | File |
|---|---|
| DSL dei campi | `class/App/ResourceSchema/FormField.php` (base `Input`) |
| Campo per `formSchema()` | `class/App/ResourceSchema/FormInput.php` |
| Riga di repeater | `class/App/ResourceSchema/RepeaterColumn.php` |
| Dispatcher | `class/App/Support/FormFieldElementFactory.php` |
| Componenti concreti | `class/Elements/Form/Components/*` |
| Renderer per tema | `class/Themes/Wonder/*`, `class/Themes/Bootstrap/*` |

## Le pagine di questa sezione

- [FormField e FormInput](form-field.md) — tutti i type-helper e i modificatori,
  con esempi.
- [Repeater](repeater.md) — righe ripetibili e righe correlate a un'altra
  tabella.
- [Sistema Form / Theme / Element](theme-system.md) — la pipeline di rendering e
  come aggiungere un nuovo tipo di input.

## Esempio minimo

```php
use Wonder\App\ResourceSchema\FormInput;

public static function formSchema(): array
{
    return [
        FormInput::key('name')->text()->required(),
        FormInput::key('email')->email()->required(),
        FormInput::key('cover')->fileDragDrop('image', 'classic'),
        FormInput::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosto'])->value('true'),
    ];
}
```
