# 32 — Qualità: Performance, Costi AWS, Scalabilità, DR, Backup, Logging, Monitoring

## 1. Performance: budget e punti caldi

| Percorso | Budget (p95) | Strategia |
|---|---|---|
| `GET /app/config` | 100 ms | Cache Redis + ETag (304 frequentissimi) |
| `GET /availability` | < 1 s (RNF-01) | Cache Redis 60s + invalidazione su scrittura ([30](30-engine-appuntamenti.md) §3) |
| `POST /appointments` | 500 ms | Transazione corta; tutto il resto post-commit in coda |
| Catalogo/staff | 200 ms | Cache + CDN per immagini |
| Dashboard report | 2 s | Query su read replica (fase 2+), aggregati pre-calcolati notturni |

Colli di bottiglia previsti e mitigazioni:
1. **MySQL scritture appuntamenti** in ore di punta (sera/lunedì): transazioni brevi, indici progettati, connection pooling (proxy RDS se necessario)
2. **Lock contention sugli staff popolari**: il lock è per-staff e per-intervallo, non globale; il soft-hold Redis riduce i tentativi concorrenti
3. **Worker push nelle finestre promemoria** (es. 18:00): i job ritardati distribuiscono naturalmente; autoscaling worker su profondità coda
4. **Runner macOS pipeline iOS**: risorsa costosa e lenta — batching dei treni di rilascio, pool dimensionato a consumo
5. **Hot key Redis** (config tenant grandi): TTL con jitter, local in-process cache di 5s per le chiavi più lette

## 2. Scalabilità per fase (allineata a [16-piano-crescita.md](16-piano-crescita.md))

| Fase (tenant) | Topologia |
|---|---|
| 0-50 | ECS: 2 task API + 1 worker + scheduler; RDS MySQL Multi-AZ `db.t4g.medium`; ElastiCache 1 nodo + replica; tutto in 1 regione |
| 50-500 | API 3-6 task autoscaling; worker per coda; RDS `db.r6g.large`; CloudFront per asset |
| 500-3.000 | Read replica (report/letture pesanti); Horizon su nodi dedicati; valutazione RDS Proxy; partizionamento audit/notifications |
| 3.000-10.000 | RDS verso Aurora MySQL (failover rapido, storage elastico); valutazione sharding per fasce tenant solo se i trigger di performance lo impongono; possibile cella separata per nuova regione geografica |

Nota dimensionale: 10.000 tenant ≈ 60M appuntamenti/anno ≈ ~2 scritture/secondo medie, picchi 30-50/s — **ben dentro le capacità di un singolo MySQL ben indicizzato**. Il vero carico è in lettura (availability), assorbito da Redis. Lo sharding è un'opzione remota, non un requisito.

## 3. Stima costi AWS mensili (ordine di grandezza, eu-south/eu-west, 2026)

| Voce | Fase 0 (≤50) | Fase 2 (~1.000) | Fase 4 (~10.000) |
|---|---|---|---|
| ECS Fargate (API+worker+sched) | ~150 € | ~700 € | ~3.500 € |
| RDS MySQL Multi-AZ (+replica da F2) | ~120 € | ~600 € | ~2.500 € (Aurora) |
| ElastiCache Redis | ~60 € | ~250 € | ~900 € |
| S3 + CloudFront | ~20 € | ~150 € | ~800 € |
| SES (email) | ~5 € | ~80 € | ~600 € |
| ALB, WAF, NAT, varie rete | ~80 € | ~250 € | ~900 € |
| CloudWatch/observability | ~30 € | ~200 € | ~1.000 € |
| **Totale infra** | **~465 €** | **~2.230 €** | **~10.200 €** |
| Infra per tenant/mese | ~9,3 € | ~2,2 € | ~1,0 € |

Fuori infra AWS: SMS a consumo (pass-through con margine), CI/CD con runner macOS (~200-1.500 €/mese a seconda dei treni), account developer store. **Conclusione per l'unit economics ([15](15-strategia-monetizzazione.md))**: il costo infrastrutturale per tenant scende da ~4% a ~0,4% del canone — la voce dominante resta il costo umano (supporto/CS), non l'infrastruttura. Stime da validare con il calcolatore AWS a progettazione esecutiva.

## 4. Disaster Recovery

| Parametro | Obiettivo (da [14](14-strategia-sicurezza.md)) | Implementazione |
|---|---|---|
| RPO | ≤ 24h contrattuale; **effettivo ~5 min** | RDS automated backup + binlog PITR; snapshot giornalieri |
| RTO | ≤ 4h | Multi-AZ failover automatico (minuti) per guasto AZ; per disastro regionale: restore da snapshot cross-region copiati giornalmente + IaC Terraform per ricreare lo stack (runbook provato ogni 6 mesi) |
| Redis | perdita accettabile | cache ricostruibile; le code Horizon critiche usano persistenza AOF + i job critici sono ri-derivabili dal DB (outbox) |
| S3 | 11 nove durabilità | versioning + replica cross-region per gli asset di branding e gli export |

Scenari nel runbook: guasto AZ (automatico), corruzione dati logica (PITR a istante precedente), regione indisponibile (restore cross-region, DNS switch), compromissione credenziali (rotazione, revoca token, audit).

## 5. Backup

- **MySQL**: backup automatici RDS retention 30 giorni + snapshot mensili conservati 12 mesi (obblighi contrattuali); copia cross-region giornaliera; **test di restore trimestrale automatizzato** (restore su istanza temporanea + verifica checksum tabelle campione)
- **S3**: versioning, lifecycle (asset orfani → IA → delete), replica
- **Configurazione**: tutto in IaC (Terraform) e segreti in Secrets Manager con rotazione — la "ricostruibilità" è parte del backup
- Esclusioni esplicite: Redis (ricostruibile), log oltre retention

## 6. Migrazioni database a zero downtime

Politica **expand/contract**: (1) aggiungere colonne/tabelle nuove compatibili; (2) deploy codice che scrive su entrambe/legge dalla nuova; (3) backfill incrementale in job; (4) rimozione del vecchio in release successiva. DDL pesanti su tabelle grandi con online DDL (`ALGORITHM=INPLACE`) o strumento di migrazione online; mai ALTER bloccanti in ore di punta. Le migrazioni sono parte della pipeline e provate su staging con dataset di volume realistico.

## 7. Logging, Monitoring, Alerting

### Logging
- Log strutturati JSON → CloudWatch Logs; correlazione con `request_id` propagato (anche nei job); `tenant_id` su ogni riga (mai dati personali nei log: no nomi, no telefoni, no payload completi)
- Retention: applicativi 30 giorni caldi + archivio S3 12 mesi; audit_logs in DB (non sono log tecnici)

### Metriche (CloudWatch + dashboard)
- **Tecniche**: p50/p95/p99 per endpoint, error rate, profondità code per coda, lag scheduler, CPU/memoria/connessioni RDS, hit ratio Redis, saturazione worker
- **Di business**: prenotazioni/ora (globale e per tenant), 409 slot_unavailable rate (proxy di contention), promemoria inviati vs dovuti, build per stato, tenant at-risk
- **Per-tenant**: richieste, job, notifiche — individuazione noisy neighbor ([28](28-multi-tenant-tecnico.md) §7)

### Alerting (con escalation on-call)
| Alert | Soglia indicativa |
|---|---|
| Error rate API | > 1% per 5 min |
| p95 availability endpoint | > 1s per 10 min |
| Coda `critical` | profondità > 100 o età > 60s |
| Promemoria non inviati (riconciliazione) | ≥ 1 |
| Replica lag / CPU RDS | > 30s / > 80% |
| Fallimenti login anomali per tenant | spike (possibile attacco) |
| Guard isolamento tenant scattata | ≥ 1 (severità massima) |
| Build pipeline failure rate | > 20% nel treno |

### Tracing
- Distributed tracing (X-Ray o OpenTelemetry) sui percorsi: prenotazione end-to-end (API→DB→eventi→notifica) e pipeline build; campionamento 5% + 100% degli errori
