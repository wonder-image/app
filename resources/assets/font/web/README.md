# Font web

Font serviti dal pacchetto invece che da Google Fonts. Inter, Open Sans, Lato, Poppins,
DM Sans, Nunito e Work Sans sono righe di `css_font` (i predefiniti di
`Wonder\App\RuntimeDefaults::defaultFonts()`, inseriti da `forge update`) col link a
`fonts.css`: la testata lo carica una volta sola. Roboto e Montserrat restano su Google;
le loro cartelle servono a `Wonder\View\WebFonts`.

Presi da [Fontsource](https://fontsource.org) il 2026-10-07, solo il sottoinsieme latino.
Licenza SIL Open Font License 1.1: il file `LICENSE` è in ogni cartella.

| Cartella | Pacchetto | Versione | File |
|---|---|---|---|
| Inter | `@fontsource-variable/inter` | 5.3.0 | variable, pesi 100-900 |
| Roboto | `@fontsource-variable/roboto` | 5.3.0 | variable, 100-900 |
| OpenSans | `@fontsource-variable/open-sans` | 5.3.0 | variable, 300-800 |
| Lato | `@fontsource/lato` | 5.3.0 | 400, 700 (Lato non ha 500 e 600) |
| Montserrat | `@fontsource-variable/montserrat` | 5.3.0 | variable, 100-900 |
| Poppins | `@fontsource/poppins` | 5.3.0 | 400, 500, 600, 700 |
| DMSans | `@fontsource-variable/dm-sans` | 5.3.0 | variable, 100-1000 |
| Nunito | `@fontsource-variable/nunito` | 5.3.0 | variable, 200-1000 |
| WorkSans | `@fontsource-variable/work-sans` | 5.3.0 | variable, 100-900 |

I font per i PDF (FPDF) sono nelle altre cartelle di `font/`: vedi `../README.md`.
