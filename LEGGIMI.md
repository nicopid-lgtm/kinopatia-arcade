# Kinopatia Arcade — come metterla online

Questa cartella è l'app completa: va solo caricata su uno spazio web con **HTTPS**.
Dopo ottieni un link da mandare a tutti. Chi lo apre può giocare subito o installarla
sul telefono (icona Kinopatia, schermo intero, funziona anche offline).

## Opzione A — GitHub Pages (gratis, consigliata)
1. Crea un account o entra su github.com con l'account dello studio.
2. In alto a destra **+ → New repository** · nome `kinopatia-arcade` · **Public** · *Create repository*.
3. Nella pagina del repository: **Add file → Upload files** e trascina **tutto il contenuto** di questa
   cartella (index.html, manifest.webmanifest, sw.js, .nojekyll, cartella icons) → **Commit changes**.
4. **Settings → Pages** · Source: *Deploy from a branch* · Branch: `main` · Folder: `/ (root)` → **Save**.
5. Dopo 1–2 minuti il link è: `https://NOME-ACCOUNT.github.io/kinopatia-arcade/`

### Dominio vostro (es. arcade.kinopatia.com)
- Dove gestite il DNS di kinopatia.com aggiungete un record:
  `CNAME   arcade   →   NOME-ACCOUNT.github.io`
- Su GitHub: **Settings → Pages → Custom domain** = `arcade.kinopatia.com` → Save → spunta **Enforce HTTPS**.

## Opzione B — il vostro hosting
Caricate il contenuto della cartella (via FTP o file manager) in una cartella del sito,
es. `kinopatia.com/arcade/`. Serve che il sito sia in **HTTPS** (per l'installazione come app).

## Aggiornare l'app
1. Sostituite `index.html` con la nuova versione.
2. In `sw.js` cambiate la riga `VERSION` (es. `...-2026-12-01-1`).
Chi ha già l'app installata riceve l'aggiornamento alla prossima apertura.

## Edizioni
- `.../` oppure `.../#natale` → Christmas Edition
- `.../#classic` → edizione neutra

## Nota sulla classifica
Per ora la classifica è **demo**: ogni giocatore vede i propri record salvati sul proprio telefono.
Per una classifica condivisa tra tutti (e i contatti in HubSpot) serve collegare un piccolo backend:
il punto da collegare nel codice è l'oggetto `LB` in index.html.
