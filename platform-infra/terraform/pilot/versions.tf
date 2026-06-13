# Profilo PILOTA (0-10 tenant): una EC2 con Caddy (https automatico) +
# RDS MySQL + S3 + SES. Deviazione consapevole e documentata da docs/32
# (ECS Fargate): meno parti mobili e ~30€/mese per validare il business;
# upgrade path al profilo completo quando i trigger di ARCHITECTURE_FINAL_REVIEW
# §9 scattano (>10 tenant / primo cliente enterprise).

terraform {
  required_version = ">= 1.9"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}
