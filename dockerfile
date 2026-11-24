FROM mcr.microsoft.com/playwright:v1.48.0-jammy

# Installer PHP og cURL-udvidelsen
RUN apt-get update && apt-get install -y \
    php-cli \
    php-curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Installer Node-afhængigheder
COPY package.json ./
RUN npm install

# Kopier resten af koden
COPY . .
RUN chmod +x /app/start.sh

EXPOSE 8080
CMD ["/app/start.sh"]