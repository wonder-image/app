<?php

namespace Wonder\Auth\Frontend;

/** Interna ai servizi del pannello: annulla la transazione con la parte che non si è salvata ('user' o 'contact'). */
final class AccountSaveFailed extends \RuntimeException {}
