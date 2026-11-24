FROM mcr.microsoft.com/playwright:v1.48.0-jammy

RUN apt-get update && apt-get install -y \
    php-cli \
    php-curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json ./
RUN npm install

COPY . .

EXPOSE 8080
CMD ["sh", "-c", "node /app/scraper.js & sleep 2 && php -S 0.0.0.0:${PORT:-8080} -t /app"]
