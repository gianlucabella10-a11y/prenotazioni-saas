<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Domain;

/** Build lifecycle of an App Project (distinct from the tenant status). */
enum AppProjectStatus: string
{
    case Draft = 'draft';                 // appena creata (identità allocata)
    case Configured = 'configured';       // brand/logo/template impostati
    case Ready = 'ready';                 // pronta per la generazione
    case Generated = 'generated';         // manifest prodotto (senza asset)
    case ReadyToBuild = 'ready_to_build'; // manifest + asset pronti per la build (FASE 2A)
    case Queued = 'queued';               // build accodata (worker), pre-compilazione
    case Building = 'building';           // build/firma in corso (CI, FASE 2C)
    case Built = 'built';                 // artifact prodotto (AAB/IPA), pre-store
    case Published = 'published';         // pubblicata sugli store (FASE 2C)
    case Failed = 'failed';               // build/pubblicazione fallita (FASE 2C)
}
