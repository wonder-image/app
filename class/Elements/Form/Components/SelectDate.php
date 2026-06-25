<?php

namespace Wonder\Elements\Form\Components;

/**
 * `DatePicker` con validazione client-side inline su `dateMin`/`dateMax`
 * (formato `dd/mm/yyyy`). Mantiene il markup del frontend storico
 * `selectDate()`, che mostrava un alert custom quando la data scelta
 * usciva dall'intervallo.
 *
 * Il `DatePicker` base, invece, deleg a un check JS globale e non
 * include logica di validazione nel template.
 */
class SelectDate extends DatePicker
{
}
