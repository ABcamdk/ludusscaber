#!/bin/bash
# Start Node.js scraper i baggrunden
node /app/scraper.js &

# Start PHP serveren på den port Railway tildeler
php -S 0.0.0.0:${PORT:-8080} -t /app