variable "aws_region" {
  description = "Regione AWS (UE per data residency GDPR, docs/14)"
  type        = string
  default     = "eu-south-1" # Milano
}

variable "app_domain" {
  description = "Dominio del backend/dashboard (es. api.tuodominio.it) — il DNS deve puntare all'EIP dopo l'apply"
  type        = string
}

variable "ses_domain" {
  description = "Dominio mittente email (es. tuodominio.it)"
  type        = string
}

variable "ssh_public_key" {
  description = "Chiave pubblica SSH per l'accesso di deploy (contenuto di ~/.ssh/id_ed25519.pub)"
  type        = string
}

variable "admin_cidr" {
  description = "CIDR autorizzato a SSH (il TUO IP/32 — mai 0.0.0.0/0)"
  type        = string
}

variable "db_password" {
  description = "Password del database applicativo (generata, min 20 caratteri)"
  type        = string
  sensitive   = true

  validation {
    condition     = length(var.db_password) >= 20
    error_message = "La password del database deve avere almeno 20 caratteri."
  }
}

variable "instance_type" {
  description = "Taglia EC2 (arm64)"
  type        = string
  default     = "t4g.small"
}
