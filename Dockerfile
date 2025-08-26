# Simple Dockerfile for Railway: PHP server + Node for worker deps
FROM php:8.2-cli

# Install curl and Node.js 20 (for topup-worker)
RUN apt-get update \
    && apt-get install -y curl ca-certificates gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy only worker package files first (better caching), then install
COPY topup-worker/package*.json ./topup-worker/
RUN cd topup-worker && npm install --omit=dev --no-audit --no-fund

# Copy the rest of backend
COPY . .

# Expose the port Railway provides
ENV PORT=8080
EXPOSE 8080

# Start PHP built-in server to serve backend files (requestWithdraw.php, etc.)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]


