# Changelog

## Unreleased

### Added
- `Wonder\View\ComponentNamespaceRegistry`: i moduli registrano un namespace di componenti (`prefix` → directory base).
- `View::component()` è ora module-aware: `View::component('<prefix>/<nome>')` risolve con catena di override `{ROOT}/custom/view/components/{prefix}/...` → modulo. I nomi senza prefisso registrato mantengono il comportamento legacy.
- Helper globali `props()` (default + validazione chiavi obbligatorie) e `slot()` (slot nominati nei componenti template), in `app/function/helper.php`.
- Comando forge `module:publish <slug> [--only=<path>] [--force]`: pubblica le view di un modulo negli override del sito (`custom/view/...`).
