output "app_public_ip" {
  description = "EIP dell'istanza: punta qui il record A di app_domain"
  value       = aws_eip.app.public_ip
}

output "db_endpoint" {
  description = "Endpoint RDS per il .env di produzione (DB_HOST)"
  value       = aws_db_instance.main.address
}

output "assets_bucket" {
  description = "Bucket S3 degli asset (AWS_BUCKET nel .env)"
  value       = aws_s3_bucket.assets.bucket
}

output "ses_dkim_tokens" {
  description = "3 token DKIM: crea i CNAME <token>._domainkey.<dominio> → <token>.dkim.amazonses.com"
  value       = aws_ses_domain_dkim.main.dkim_tokens
}

output "ses_verification_token" {
  description = "TXT _amazonses.<dominio> per la verifica del dominio mittente"
  value       = aws_ses_domain_identity.main.verification_token
}
