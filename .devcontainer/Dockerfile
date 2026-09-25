FROM mcr.microsoft.com/devcontainers/php:8.4-bookworm

# The Codespaces default PHP can change independently of the POS lockfile.
# Build the MySQL PDO driver against this exact PHP runtime.
RUN docker-php-ext-install pdo_mysql \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*
