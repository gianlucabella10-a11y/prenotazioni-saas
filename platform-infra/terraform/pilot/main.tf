# ---------------------------------------------------------------------------
# Rete: VPC di default della regione (profilo pilota: zero NAT, zero costi
# di rete extra). Le subnet pubbliche di default ospitano EC2; RDS resta
# chiuso al solo security group dell'app.
# ---------------------------------------------------------------------------

data "aws_vpc" "default" {
  default = true
}

data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

# Ubuntu 24.04 LTS arm64 (Canonical)
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"]

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-arm64-server-*"]
  }
}

# ---------------------------------------------------------------------------
# Security groups
# ---------------------------------------------------------------------------

resource "aws_security_group" "app" {
  name_prefix = "platform-app-"
  description = "Backend pilota: https pubblico, ssh solo admin"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    description = "HTTP (redirect a https gestito da Caddy + ACME)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "SSH (solo IP amministratore)"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.admin_cidr]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_security_group" "db" {
  name_prefix = "platform-db-"
  description = "RDS: accesso esclusivo dal security group app"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    description     = "MySQL dal backend"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.app.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

# ---------------------------------------------------------------------------
# Database: MySQL 8, backup 7 giorni, cifratura at-rest (docs/14 §3)
# ---------------------------------------------------------------------------

resource "aws_db_subnet_group" "main" {
  name_prefix = "platform-"
  subnet_ids  = data.aws_subnets.default.ids
}

resource "aws_db_instance" "main" {
  identifier_prefix = "platform-pilot-"
  engine            = "mysql"
  engine_version    = "8.0"
  instance_class    = "db.t4g.micro"

  allocated_storage = 20
  storage_type      = "gp3"
  storage_encrypted = true

  db_name  = "platform"
  username = "platform"
  password = var.db_password

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.db.id]
  publicly_accessible    = false

  backup_retention_period = 7
  deletion_protection     = true
  skip_final_snapshot     = false
  final_snapshot_identifier = "platform-pilot-final"

  apply_immediately = true
}

# ---------------------------------------------------------------------------
# Storage asset (loghi, immagini) — privato, accesso via URL firmati
# ---------------------------------------------------------------------------

resource "aws_s3_bucket" "assets" {
  bucket_prefix = "platform-assets-"
}

resource "aws_s3_bucket_public_access_block" "assets" {
  bucket                  = aws_s3_bucket.assets.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_versioning" "assets" {
  bucket = aws_s3_bucket.assets.id

  versioning_configuration {
    status = "Enabled"
  }
}

# ---------------------------------------------------------------------------
# Email transazionali: SES (promemoria, verifiche, inviti — docs/29)
# ---------------------------------------------------------------------------

resource "aws_ses_domain_identity" "main" {
  domain = var.ses_domain
}

resource "aws_ses_domain_dkim" "main" {
  domain = aws_ses_domain_identity.main.domain
}

# ---------------------------------------------------------------------------
# IAM: l'istanza accede solo a S3 (bucket asset) e SES (invio)
# ---------------------------------------------------------------------------

resource "aws_iam_role" "app" {
  name_prefix = "platform-app-"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Action    = "sts:AssumeRole"
      Effect    = "Allow"
      Principal = { Service = "ec2.amazonaws.com" }
    }]
  })
}

resource "aws_iam_role_policy" "app" {
  name_prefix = "platform-app-"
  role        = aws_iam_role.app.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect   = "Allow"
        Action   = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject", "s3:ListBucket"]
        Resource = [aws_s3_bucket.assets.arn, "${aws_s3_bucket.assets.arn}/*"]
      },
      {
        Effect   = "Allow"
        Action   = ["ses:SendEmail", "ses:SendRawEmail"]
        Resource = "*"
      },
    ]
  })
}

resource "aws_iam_instance_profile" "app" {
  name_prefix = "platform-app-"
  role        = aws_iam_role.app.name
}

# ---------------------------------------------------------------------------
# Istanza applicativa: Caddy (https automatico) + PHP-FPM 8.4 + worker coda
# Lo stack è installato dal cloud-init (server/user-data.sh); il codice
# arriva con bin/deploy.sh.
# ---------------------------------------------------------------------------

resource "aws_key_pair" "deploy" {
  key_name_prefix = "platform-deploy-"
  public_key      = var.ssh_public_key
}

resource "aws_instance" "app" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  subnet_id              = data.aws_subnets.default.ids[0]
  vpc_security_group_ids = [aws_security_group.app.id]
  key_name               = aws_key_pair.deploy.key_name
  iam_instance_profile   = aws_iam_instance_profile.app.name

  user_data = templatefile("${path.module}/../../server/user-data.sh", {
    app_domain = var.app_domain
  })

  root_block_device {
    volume_size = 30
    volume_type = "gp3"
    encrypted   = true
  }

  metadata_options {
    http_tokens = "required" # IMDSv2 only
  }

  tags = {
    Name = "platform-pilot-app"
  }
}

resource "aws_eip" "app" {
  instance = aws_instance.app.id
  domain   = "vpc"
}
