<?php

namespace Wonder\Auth\Frontend;

/** Annulla una transazione del pannello con la parte che non si è salvata: 'user', o 'contact' da un hook di estensione. */
final class AccountSaveFailed extends \RuntimeException {}
