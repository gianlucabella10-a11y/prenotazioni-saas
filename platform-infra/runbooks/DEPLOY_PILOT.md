# Runbook — Deploy ambiente pilota (BETA REALE ONLINE)

Porta backend + dashboard online su https reale e abilita la distribuzione
dell'app cliente come web/PWA. Profilo pilota (0-10 tenant): 1× EC2 + RDS
MySQL + S3 + SES, **~30 €/mese**. Niente store, niente Redis, niente billing
automatico (incasso manuale per il pilota n.1).

## Cosa serve da TE (le uniche cose che io non posso fare)

| # | Elemento | Perché |
|---|---|---|
| 1 | **Account AWS** con credenziali (`aws configure` o env `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`) | Creare le risorse cloud e sostenerne il costo |
| 2 | **Un dominio** con accesso al DNS (es. `tuodominio.it`) | https del backend (`api.tuodominio.it`) + mittente SES |
| 3 | **Chiave SSH** (`ssh-keygen -t ed25519`) e il tuo **IP pubblico** | Accesso di deploy all'istanza |

Quando hai questi, il resto è ~15 minuti. Dimmi "pronto con AWS" e procedo io
con i comandi (oppure eseguili tu seguendo i passi sotto).

## Prerequisiti tool (una tantum, user-space, no sudo)

```bash
# Terraform (binario singolo in ~/.local/bin)
#   https://developer.hashicorp.com/terraform/install  (arch: darwin_arm64)
# AWS CLI v2
#   https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html
aws configure        # inserisci access key, secret, region eu-south-1
```

## Passi

### 1. Variabili Terraform
```bash
cd platform-infra/terraform/pilot
cp terraform.tfvars.example terraform.tfvars
# compila: app_domain, ses_domain, ssh_public_key, admin_cidr, db_password
```

### 2. Apply
```bash
terraform init
terraform plan      # rivedi: 1 EC2, 1 RDS, 1 S3, SES, SG, IAM, EIP
terraform apply
```
Annota gli **output**: `app_public_ip`, `db_endpoint`, `assets_bucket`,
`ses_dkim_tokens`, `ses_verification_token`.

### 3. DNS
- **A** `api.tuodominio.it` → `app_public_ip` (Caddy emette il certificato https da solo al primo avvio)
- **TXT** `_amazonses.tuodominio.it` → `ses_verification_token`
- **3× CNAME** `<token>._domainkey.tuodominio.it` → `<token>.dkim.amazonses.com` (dai `ses_dkim_tokens`)
- Attendi la verifica SES (di solito < 1h) e, per uscire dalla sandbox SES, richiedi production access dalla console.
- **Credenziali SMTP SES**: console SES → *SMTP settings* → *Create SMTP credentials* → usa username/password in `.env` (`MAIL_USERNAME`/`MAIL_PASSWORD`). Il profilo pilota invia le email via SMTP (driver `smtp`), senza SDK AWS aggiuntivo.

### 4. Primo deploy del codice
```bash
cd ../../..              # radice progetto
APP_HOST=api.tuodominio.it ./platform-infra/bin/deploy.sh
```

### 5. .env di produzione (una sola volta sull'istanza)
```bash
scp platform-backend/.env.production.example ubuntu@api.tuodominio.it:/var/www/platform/.env
ssh ubuntu@api.tuodominio.it
cd /var/www/platform
php8.4 artisan key:generate          # popola APP_KEY
nano .env                            # DB_HOST=<db_endpoint>, DB_PASSWORD, AWS_BUCKET, domini
php8.4 artisan config:cache && sudo systemctl restart platform-queue
```

### 6. Provisioning del primo tenant pilota
```bash
# crea super-admin + tenant reale (come fatto in locale), via tinker o seeder dedicato
php8.4 artisan tinker   # ProvisionTenant::execute([...])
# annota il tenant api_key e l'invite_token del titolare
```

### 7. Smoke test (gate di "online")
```bash
curl -s https://api.tuodominio.it/up                      # 200
curl -s -H "X-Tenant-Key: <api_key>" https://api.tuodominio.it/api/v1/app/config   # brand JSON
# dashboard: https://api.tuodominio.it/dashboard/login
```

### 8. Distribuzione app cliente (senza store)
```bash
cd platform-mobile/apps/client_app
flutter build web --release \
  --dart-define=ENV=production \
  --dart-define=API_BASE_URL=https://api.tuodominio.it/api/v1 \
  --dart-define=TENANT_KEY=<api_key>
# pubblica build/web sotto un sottodominio del tenant (es. prenota.salone.it),
# servito dalla stessa istanza (Caddy) o da S3+CloudFront. PWA installabile da
# "Aggiungi a schermata Home". Android nativo: APK sideload (step successivo).
```

## Hardening minimo della beta
- **Backup DB**: già attivo (RDS, 7 giorni). Verifica un restore di prova.
- **Sentry**: aggiungere DSN a backend e app (P1, prima del pilota reale).
- **Log**: `LOG_LEVEL=warning`, nessun dato personale (già da config).

## Rollback
```bash
# il codice è stateless: ridepoia la revisione precedente con deploy.sh.
# il DB: point-in-time restore da RDS (entro la finestra di 7 giorni).
```

## Costo e spegnimento
~30 €/mese. Per fermare tutto: `terraform destroy` (il DB ha
`deletion_protection=true` e `skip_final_snapshot=false`: prima disattiva la
protezione e accetta lo snapshot finale).
