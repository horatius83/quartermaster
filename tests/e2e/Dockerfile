# Runs the end-to-end tests in Linux: Microsoft's Playwright image (Node plus every browser
# and its system libraries) with PHP added. Built and run by tests/e2e/docker.mjs.
ARG PLAYWRIGHT_VERSION
FROM mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble

RUN apt-get update \
 && apt-get install -y --no-install-recommends php-cli php-sqlite3 php-mbstring \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
