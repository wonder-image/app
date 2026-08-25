<?php

namespace Wonder\App\ResourceSchema;

/**
 * Alias di `FormField` usato nei `formSchema()` di siti e moduli:
 * `FormInput::key('nome')->text()->required()`.
 *
 * Non aggiunge nulla — esiste per leggibilità (un *input* del form) e per
 * retro-compatibilità: è il nome che compare nella documentazione e in tutti
 * i Resource generati da `php forge make:resource`.
 *
 * Ereditando da `FormField` eredita anche il morphing dei type-helper: da
 * `->text()` in poi l'oggetto è la classe tipizzata corrispondente sotto
 * `Inputs\`.
 */
final class FormInput extends FormField
{
}
