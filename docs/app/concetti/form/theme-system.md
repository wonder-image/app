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
FormField::key('name')->text()          // 1. dichiarazione (DSL)
  → Inputs\InputText                     // 2. il type-helper ritorna la classe del tipo
  → Input::render($theme)                // 3. resa
  → InputText::element()                 // 4. il tipo costruisce il proprio Element
  → Wonder\Elements\Form\Components\*    // 5. componente concreto (Element)
  → Themes\Resolver                      // 6. sceglie il renderer del tema
  → Themes\Wonder\Form\* | Themes\Bootstrap\Form\*   // 7. HTML finale
```

## Dove si trova nel codice

| Passo | File |
|---|---|
| Base universale | `class/App/ResourceSchema/Input.php` |
| Classi di tipo | `class/App/ResourceSchema/Inputs/*` (37 tipi) |
| Modificatori condivisi | `class/App/ResourceSchema/Inputs/Concerns/*` |
| Facade / type-helper | `class/App/ResourceSchema/FormField.php` |
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

## Chi costruisce l'Element

Ogni tipo costruisce **il proprio** Element, nel suo `element()`. Non esiste
una mappa centrale `helper → Element`: la classe del tipo *è* la mappa.

| Classe di tipo | Element |
|---|---|
| `InputHidden` | `Hidden` |
| `InputText` | `InputText` |
| `InputTextGenerator` | `TextGenerator` |
| `InputEmail` | `InputEmail` |
| `InputPhone` | `InputTel` |
| `InputNumber` | `InputNumber` |
| `InputPrice` | `InputPrice` |
| `InputPercentige` | `InputPercentige` |
| `InputPassword` | `InputPassword` |
| `InputUrl` | `InputUrl` |
| `InputColor` | `InputColor` |
| `InputTextDate` | `Date` |
| `InputTextDatetime` | `InputDatetime` |
| `InputDate` | `DatePicker` |
| `InputDateRange` | `DateRange` |
| `InputTime` | `InputTime` |
| `InputTextarea` | `Textarea`, o `TextareaEditor` con `version()` |
| `InputSelect` | `Select` |
| `InputSelectSearch` | `Select` + `data-wi-select-search` |
| `InputTextList` | `TextList` |
| `InputSearchText` / `InputSearchRadio` | `SearchRemote` |
| `InputCountry` / `InputStates` / `InputPhonePrefix` | `Select` ricercabile precompilato |
| `InputRadio` | `CheckGroup` (radio) |
| `InputCheckbox` | `Checkbox`, o `CheckGroup` con `options()` |
| `InputCheckTree` | `CheckTree` |
| `InputDynamicCheck` | `DynamicCheck` |
| `InputCheckBoolean` | `CheckBoolean` |
| `InputGoogleAddress` | `GoogleAddress` |
| `InputFile` / `InputFileDragDrop` | `File` |
| `InputRepeater` | `Repeater` |
| `InputAcceptDocument` | `InputAcceptDocument` |
| `InputReCaptcha` | `reCAPTCHA` |

Il contratto è in tre pezzi, tutti su `Input`:

| Metodo | Chi lo scrive | Cosa fa |
|---|---|---|
| `element()` | ogni tipo (obbligatorio) | costruisce l'Element e vi applica ciò che il tipo sa di sé (opzioni, url, policy, formatting) |
| `hydrate()` | la base, uguale per tutti | label, value, error, attributi, autocomplete |
| `decorate()` | il tipo, se serve | ritocchi che vogliono l'Element già valorizzato (limiti di data, step temporale) |

`compile()` li esegue in quest'ordine e ritorna l'Element pronto; `render()` lo
passa al `Themes\Resolver`. Un `element()` che ritorna `null` segnala che il
campo non è renderizzabile nel contesto corrente (per esempio `InputCountry`
senza la funzione `countries()`).

{% hint style="info" %}
La stringa `helper` esiste ancora (`$input->get('helper')`) ma **non decide più
niente al render**: serve solo a riconoscere il tipo di un campo dall'esterno
(`Resource`, il renderer `Repeater`, le colonne repeater dichiarate come array).
{% endhint %}

## Aggiungere un nuovo tipo di input

Se serve un input non coperto, l'intervento è a livello **framework**, non al
call site:

1. crea la classe del tipo sotto `class/App/ResourceSchema/Inputs/`
   (`class InputFoo extends Input { protected string $helper = 'foo'; }`) con i
   soli modificatori che quel tipo supporta — riusa i trait in `Inputs/Concerns/`
   per i gruppi già esistenti (opzioni, versione, upload, …);
2. implementa `element()` sulla nuova classe: costruisce e configura il suo
   `Wonder\Elements\Form\Components\*` (nuovo o esistente). Se serve anche un
   ritocco dopo l'idratazione, aggiungi `decorate()`;
3. aggiungi il type-helper su `FormField`
   (`public function foo(): InputFoo { return $this->morphInto(InputFoo::class); }`).
   Se il tipo deve funzionare anche con l'escape hatch
   `new FormField($name, 'foo')`, aggiungilo pure a
   `FormField::HELPERS`;
4. aggiungi il renderer sotto `class/Themes/Wonder/Form/` **e**
   `class/Themes/Bootstrap/Form/` così entrambi i temi sono coperti;
5. dichiara il campo con `FormField::key(...)->foo(...)`, oppure parti
   direttamente da `InputFoo::key(...)` quando serve l'API tipizzata.

{% hint style="danger" %}
Non aggirare un tipo mancante con HTML scritto a mano: rompe theme switching,
validazione e wiring label/error. Estendi la pipeline.
{% endhint %}

## Collegamenti con il resto

- I type-helper sono in [FormField](form-field.md).
- Il rendering è invocato dalle Resource (form CRUD), dalle
  [CustomPageSchema](../risorse/custom-page-schema.md) e dai layout.

## Errori comuni

- **Input non renderizzato** → `element()` ha ritornato `null`: manca il
  contesto che quel tipo richiede (`countries()`, `states()`, un documento
  legale attivo) oppure il campo non ha nome.
- **Input ok nel backend ma rotto nel frontend (o viceversa)** → manca il
  renderer in uno dei due temi.
- **Markup incoerente** → si è bypassata la pipeline con HTML manuale.

## Checklist (nuovo tipo)

- [ ] classe sotto `Inputs/` con i soli modificatori del tipo
- [ ] `element()` (e `decorate()` se serve)
- [ ] type-helper su `FormField` (+ `FormField::HELPERS` per l'escape hatch legacy)
- [ ] renderer in `Themes/Wonder/Form/` e `Themes/Bootstrap/Form/`
- [ ] testato su entrambi i temi
