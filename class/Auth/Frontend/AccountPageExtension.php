<?php

namespace Wonder\Auth\Frontend;

/**
 * Hook opzionali di un'estensione sulle pagine del core: modal e form di «Dati personali», errori di pagina,
 * conferma email. Il pannello li chiama solo se l'estensione implementa questa interfaccia; BaseAccountExtension
 * li implementa senza effetti. Dentro le transazioni del core un hook annulla tutto con `AccountSaveFailed`.
 */
interface AccountPageExtension
{
    /**
     * Campi del modal «Dati personali».
     * @param array<string, mixed> $values Valori postati quando il modal si riapre dopo un errore, altrimenti vuoto.
     */
    public function personalFormFields(array $fields, object $user, array $values): array;

    /**
     * Modal aggiuntivi di «Dati personali»: postano su `account.personal` con il campo nascosto `form`.
     * @param string $open Il `form` del modal da riaprire; `$errors` e `$values` valgono solo per quello.
     * @param list<string> $errors
     */
    public function personalModals(array $modals, object $user, string $open, array $errors, array $values): array;

    /**
     * POST di un modal aggiunto: `null` se `$form` non è suo, `[]` se è salvato (il core avvisa e reindirizza),
     * altrimenti gli errori già tradotti.
     * @return list<string>|null
     */
    public function savePersonalForm(string $form, object $user, array $input): ?array;

    /**
     * Errori di pagina, già tradotti, mostrati sopra il contenuto.
     * @param string $page `overview` o `personal`.
     * @param list<string> $errors
     * @return list<string>
     */
    public function pageErrors(array $errors, object $user, string $page): array;

    /** Nella transazione della conferma email, dopo l'aggiornamento dell'utente. */
    public function afterEmailConfirmed(int $userId, string $email): void;
}
