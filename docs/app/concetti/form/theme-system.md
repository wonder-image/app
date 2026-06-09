---
icon: layer-group
---

# Sistema Form / Theme / Element

## Cos'è

La pipeline che trasforma un `FormField` (dichiarazione) nell'HTML finale,
scegliendo il tema giusto: **Wonder** per il frontend (markup `.wi-*`),
**Bootstrap** per il backend (markup `form-floating`).

## Il percorso di un input

```
FormInput::key('name')->text()          // 1. dichiarazione (DSL)
  → FormField::render($theme)           // 2. resa
  → FormFieldElementFactory::make()     // 3. dispatch: helper → Element
  → Wonder\Elements\Form\Components\*    // 4. componente concreto (Element)
  → Themes\Resolver                      // 5. sceglie il renderer del tema
  → Themes\Wonder\Form\* | Themes\Bootstrap\Form\*   // 6. HTML finale
```

## Dove si trova nel codice

| Passo | File |
|---|---|
| DSL | `class/App/ResourceSchema/FormField.php` |
| Dispatcher | `class/App/Support/FormFieldElementFactory.php` |
| Componenti (Element) | `class/Elements/Form/Components/*` (36 componenti) |
| Resolver tema | `class/Themes/Resolver.php` |
| Renderer frontend | `class/Themes/Wonder/Form/*` (32 renderer) |
| Renderer backend | `class/Themes/Bootstrap/Form/*` (30 renderer) |

{% hint style="info" %}
I due temi non hanno lo stesso numero di renderer: alcuni input esistono solo
sul frontend Wonder (es. indirizzi Google, reCAPTCHA, alcune varianti di
upload) e non hanno un equivalente backend. Conta i file con
`find class/Themes/Wonder/Form -name '*.php' | wc -l` per il valore aggiornato.
{% endhint %}

## La mappa helper → Element (dispatcher)

Il cuore è il `match($helper)` in
`FormFieldElementFactory::make()`. Ogni `helper` (impostato dai type-helper di
`FormField`) viene mappato a un Element concreto:

| `helper` | Element |
|---|---|
| `hidden` | `Hidden` |
| `text` | `InputText` |
| `textGenerator` | `TextGenerator` |
| `email` | `InputEmail` |
| `tel` / `phone` | `InputTel` |
| `number` | `InputNumber` |
| `price` | `InputPrice` |
| `percentige` | `InputPercentige` |
| `password` | `InputPassword` |
| `url` | `InputUrl` |
| `color` | `InputColor` |
| `textDate` | `Date` |
| `textDatetime` | `InputDatetime` |
| `dateInput` | `DatePicker` |
| `dateRange` | `DateRange` |
| `timeInput` | `InputTime` |
| `textarea` | `Textarea` |
| `select` | `Select` |
| `selectSearch` | `SelectSearch` |
| `inputCountry` | `Country` |
| `inputStates` | `States` |
| `inputPhonePrefix` | `PhonePrefix` |
| `radio` | `CheckGroup` (radio) |
| `checkbox` | `Checkbox` |
| `checkTree` | `CheckTree` |
| `dynamicCheck` | `DynamicCheck` |
| `checkBoolean` | `CheckBoolean` |
| `googleAddress` | `GoogleAddress` |
| `inputFile` / `inputFileDragDrop` | `File` |
| `inputRepeater` | `Repeater` |
| `inputAcceptDocument` | `AcceptDocument` |
| `recaptcha` | `reCAPTCHA` |

Nota: gli helper `tel`/`phone` e `inputFile`/`inputFileDragDrop` condividono lo
stesso Element. Un `helper` non mappato ritorna `null` (nessun render).

## Aggiungere un nuovo tipo di input

Se serve un input non coperto, l'intervento è a livello **framework**, non al
call site:

1. aggiungi il type-helper su `FormField` (e, se utile, su `FormSchema`);
2. mappa la chiave `helper` in `FormFieldElementFactory::make()` verso un Element
   (nuovo o esistente);
3. aggiungi il renderer sotto `class/Themes/Wonder/Form/` **e**
   `class/Themes/Bootstrap/Form/` così entrambi i temi sono coperti;
4. dichiara il campo con `FormInput::key(...)->nuovoHelper(...)`.

{% hint style="danger" %}
Non aggirare un tipo mancante con HTML scritto a mano: rompe theme switching,
validazione e wiring label/error. Estendi la pipeline.
{% endhint %}

## Collegamenti con il resto

- I type-helper sono in [FormField e FormInput](form-field.md).
- Il rendering è invocato dalle Resource (form CRUD), dalle
  [CustomPageSchema](../risorse/custom-page-schema.md) e dai layout.

## Errori comuni

- **Input non renderizzato** → `helper` non presente nel `match` del factory
  (ritorna `null`).
- **Input ok nel backend ma rotto nel frontend (o viceversa)** → manca il
  renderer in uno dei due temi.
- **Markup incoerente** → si è bypassata la pipeline con HTML manuale.

## Checklist (nuovo tipo)

- [ ] type-helper su `FormField`
- [ ] arm nel `match` di `FormFieldElementFactory`
- [ ] renderer in `Themes/Wonder/Form/` e `Themes/Bootstrap/Form/`
- [ ] testato su entrambi i temi
