# Font dei template (App Factory)

I template (`font_style`: `oswald`/`poppins`/`inter`) sono mappati da
`AppThemeBuilder` a una famiglia. Per **attivare** i font reali servono i file
`.ttf` con licenza appropriata (non inclusi nel repo).

1. Scarica e copia qui i file, es.:
   - `Oswald-Regular.ttf`, `Poppins-Regular.ttf`, `Inter-Regular.ttf`
2. Aggiungi al `pubspec.yaml` sotto `flutter:`:

```yaml
  fonts:
    - family: Oswald
      fonts:
        - asset: assets/fonts/Oswald-Regular.ttf
    - family: Poppins
      fonts:
        - asset: assets/fonts/Poppins-Regular.ttf
    - family: Inter
      fonts:
        - asset: assets/fonts/Inter-Regular.ttf
```

Finché i file non sono presenti, il meccanismo è già cablato ma l'app usa il
font di default (nessuna rottura della build).
