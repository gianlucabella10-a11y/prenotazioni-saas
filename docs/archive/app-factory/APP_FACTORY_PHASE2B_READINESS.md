# APP_FACTORY_PHASE2B_READINESS

> Esito FASE 2B: **build parametrica + tooling** (icone/splash/identità per-tenant). Il repo committato resta ai **default attuali** (build standard invariata); la parametrizzazione per-tenant è applicata a build-time dallo script. **Niente firma, niente upload store, niente CI** (fase successiva). Verifiche: `flutter analyze` pulito · `flutter test` 36 · `bash -n` + dry-run dello script OK · backend invariato (131).

## 1. Cosa è stato fatto (e verificato qui)
- **Tooling icone/splash**: `flutter_launcher_icons` + `flutter_native_splash` (dev-deps, `pub get` risolto) + config `flutter_launcher_icons.yaml` / `flutter_native_splash.yaml` che consumano `tool/app_factory/build/{icon,splash}.png` (popolati dallo script con gli asset dell'Asset Factory FASE 2A).
- **Android parametrico** (`android/app/build.gradle.kts`): `applicationId` e `appName` da Gradle property `-PAPP_ID` / `-PAPP_NAME`, **default = valori attuali** → la build standard non cambia. `AndroidManifest.xml`: `android:label="${appName}"`.
- **iOS parametrico** (additivo): `ios/Flutter/Tenant.xcconfig` (incluso da Debug/Release.xcconfig), `Info.plist` → `CFBundleDisplayName = $(TENANT_APP_NAME)` (default "Client App"). Il `bundle_id` è impostato a build-time dallo script (sed mirato sul solo Runner del `project.pbxproj`), così il repo committato resta valido.
- **Orchestratore** `tool/app_factory/make_app.sh`: da un **manifest 2.0** (`php artisan app:generate`) legge identità/asset/dart-define, in **dry-run** stampa il piano + il comando di build **senza modificare file**; con `--build` genera icone/splash, scrive l'identità nativa e lancia `flutter build appbundle` (non firmato).
- **Font**: meccanismo già cablato in `AppThemeBuilder` (FASE 2A); istruzioni in `assets/fonts/README.md` per attivarli.

## 2. Cosa richiede un Mac/CI (non compilabile in questo ambiente)
> `flutter analyze`/`test` non compilano il nativo: le modifiche native sono **standard e default-preserving**, ma vanno confermate con una build reale.
- `flutter build appbundle` (Android) con `-PAPP_ID/-PAPP_NAME` → verificare applicationId/label nell'AAB.
- `flutter build ipa` (macOS) dopo `make_app.sh --build` → verificare bundle id (pbxproj) e `CFBundleDisplayName`.
- Esecuzione reale di `flutter_launcher_icons` / `flutter_native_splash` con un logo ad alta risoluzione.
- iOS: l'upload richiede l'**account Apple Developer del cliente** (policy 4.2.6/4.3) + firma — fuori da FASE 2B.

## 3. Font (file esterni necessari)
`assets/fonts/` è pronta con istruzioni: servono i `.ttf` licenziati (Oswald/Poppins/Inter) + l'attivazione della sezione `fonts:` nel `pubspec.yaml` (snippet nel README). Finché non presenti, l'app usa il font di default (nessuna rottura).

## 4. Rischi tecnici
- **Modifiche native non build-verificate qui**: mitigate mantenendo i default identici e gating le mutazioni dietro `--build`/CI; resta da fare una build di conferma su runner.
- **`sed` sul pbxproj**: pattern ancorato al solo Runner (`com.platform.clientApp;`), i `RunnerTests` non matchano; eseguito su checkout effimero in CI (il repo committato non cambia).
- **Firma/keystore**: non gestiti (FASE 2C) — keystore Android in vault, cert/profili iOS dall'account del cliente.
- **Scala**: a 100+ la build per-tenant va su **CI matrix** (un job per tenant dal registry), con runner macOS come risorsa scarsa per iOS.

## 5. Flusso end-to-end (oggi)
1. Control Room: crea cliente + carica logo → `php artisan app:generate {uuid}` (Asset Factory + manifest 2.0, stato `ready_to_build`).
2. `tool/app_factory/make_app.sh --manifest <manifest> --assets-dir storage/app/public` → dry-run (piano + comando) oppure `--build` per generare asset nativi + AAB (non firmato) su un runner.

## Definition of Done — verifica
`flutter analyze` pulito · `flutter test` 36 · `bash -n` + dry-run `make_app.sh` OK (nessuna mutazione) · backend 131 invariato. Riferimenti: `APP_FACTORY_PHASE2_READINESS.md`, `APP_FACTORY_MASTER_PLAN.md`.
