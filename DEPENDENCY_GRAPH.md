# DEPENDENCY_GRAPH — Sintesi

> Diagramma testuale completo, con codice quotato per ogni collegamento: [`PROJECT_DEPENDENCIES.md`](PROJECT_DEPENDENCIES.md) (prodotto nella sessione di audit precedente, non duplicato qui). Questo documento risponde in forma sintetica alle 5 domande dirette: moduli indipendenti, moduli critici, dipendenze, cicli, accoppiamenti.

## Moduli indipendenti (nessuno dipende da loro)

- **`ControlRoom`** — superficie di presentazione pura per il super-admin. Nessun altro modulo lo importa.
- **`Dashboard`** — superficie di presentazione pura per titolare/staff. Nessun altro modulo lo importa.

Questi due sono "foglie" del grafo: possono essere modificati liberamente senza rischio di rompere altri moduli (il rischio è solo nella direzione opposta — se i moduli da cui dipendono cambiano interfaccia).

## Modulo critico

**`Foundation/Tenancy`** — non è un modulo applicativo ma è il nodo con più dipendenti in assoluto: ogni modulo tenant-scoped (`TenantManagement` escluso in parte, `Scheduling`, `Catalog`, `Staff`, `Customers`, `Branding`, `Notifications`, `AppFactory`) lo usa per lo scoping automatico dei dati. Una regressione qui ha raggio d'impatto massimo — è anche l'area meglio testata del repository (vedi `REAL_PROJECT_STATE.md` §Fase 8), il che ne bilancia la criticità.

Secondo per criticità: **`AppFactory`** — è l'unico modulo con una dipendenza *fuori* dal backend (esegue `flutter build` come sottoprocesso sul filesystem di `platform-mobile`), e l'unico i cui controller sono montati direttamente nelle route di un altro modulo (`ControlRoom`).

## Dipendenze (sintesi — grafo completo in `PROJECT_DEPENDENCIES.md` §1)

```
Foundation/Tenancy ← TenantManagement, Scheduling, Catalog, Staff, Customers, Branding, Notifications, AppFactory
Catalog, Staff → Scheduling
TenantManagement, Branding → AppFactory
TenantManagement, Branding → ControlRoom
Scheduling, Catalog, Staff, Branding → Dashboard
Foundation/Auth ← routes/api.php, Dashboard, ControlRoom (tre guard distinti sullo stesso provider "users")
```

## Cicli

**Nessun ciclo di dipendenza trovato** tra i moduli backend (il grafo è aciclico — normale per un'architettura a bounded context con `Foundation` come unico strato condiviso alla base). Nessun ciclo trovato nemmeno lato Flutter (`features/*` non si importano a vicenda, salvo `booking → catalog`, unidirezionale).

## Dipendenze vietate vs. corrette (aggiunto in questa sessione)

**Regola unica del repository** (dichiarata in `docs/22`, rispettata *de facto*, non imposta da test — vedi `PROJECT_STRUCTURE_AUDIT.md`): un modulo può dipendere solo dal layer `Application/` di un altro modulo, mai dal suo `Infrastructure/`.

| Dipendenza | Vietata / Corretta | Verificata nel codice? |
|---|---|---|
| `ModuloA/Application` → `ModuloB/Application` | ✅ Corretta | Sì (es. `ControlRoom` → `TenantManagement::ProvisionTenant`) |
| `ModuloA/Application` → `ModuloB/Infrastructure/Models` | 🚫 Vietata | Nessuna violazione trovata, ma nessun test la impedirebbe se introdotta |
| `ModuloA/Http` → `ModuloA/Application` | ✅ Corretta | Sì, unico pattern osservato |
| `ModuloA/Http` → `ModuloB/Http` (un controller chiama un altro controller) | 🚫 Vietata | Nessuna violazione trovata |
| Qualsiasi modulo → `Foundation/*` | ✅ Corretta (per definizione — `Foundation` è lo strato condiviso alla base) | Sì, pattern universale |
| `Foundation/*` → un modulo applicativo | 🚫 Vietata (romperebbe la direzione della dipendenza) | Nessuna violazione trovata |
| `ControlRoom`/`Dashboard` → qualsiasi modulo | ✅ Corretta (sono le "foglie" del grafo) | Sì |
| Un modulo → `ControlRoom`/`Dashboard` | 🚫 Vietata (mai avuto senso, mai osservata) | Nessuna violazione trovata |
| `features/X` (Flutter) → `features/Y` (Flutter) | 🚫 Vietata, salvo eccezione dichiarata | Solo `booking → catalog`, documentata come eccezione necessaria |
| `core/*` (Flutter) → `features/*` (Flutter) | 🚫 Vietata (il codice trasversale non deve conoscere le feature specifiche) | Nessuna violazione trovata |

**Eccezione unica e dichiarata**: `LocalBuildDispatcher` (modulo `AppFactory`) dipende dal filesystem di `platform-mobile`, fuori da qualunque confine di modulo backend — non è una violazione della regola sopra (quella regola vale tra moduli *dello stesso* backend), ma è l'unica dipendenza del repository che attraversa il confine backend↔mobile a livello di processo, non di HTTP. Trattata come rischio a sé in `PROJECT_DEPENDENCIES.md` §3.3, non come violazione della regola di modulo.

## Accoppiamenti eccessivi (dettaglio completo in `PROJECT_DEPENDENCIES.md` §6)

| # | Accoppiamento | Severità |
|---|---|---|
| 1 | `LocalBuildDispatcher` assume `platform-mobile` sibling-directory dello stesso filesystem del backend | Alta |
| 2 | Nessun test di architettura impone i confini tra moduli | Media |
| 3 | CI App Factory dipende da un singolo backend di produzione via SSH, nessuno staging isolato | Media |
| 4 | `ControlRoom` monta controller di `AppFactory` direttamente nelle proprie route | Bassa |
| 5 | `User` non ha lo scope di tenancy automatico — isolamento a convenzione manuale | Media |

Nessuno di questi accoppiamenti causa un bug oggi noto — sono rischi strutturali. Trattamento proposto per ciascuno: `PROJECT_EVOLUTION_ROADMAP.md`.
