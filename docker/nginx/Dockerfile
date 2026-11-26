# syntax=docker/dockerfile:1.7

# Image Nginx de production : sert les fichiers publics et les assets compilés.
# Le PHP n'est pas ici : Nginx transmet les .php au conteneur "app" (FastCGI, port 9000).
# Contexte de build : la racine du dépôt.

# --- Étape 1 : assets front ---
FROM node:24-alpine AS assets
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml* ./
RUN pnpm install --frozen-lockfile || pnpm install
COPY resources ./resources
# Les vues Blade de src/ sont analysées par Tailwind : sans elles, leurs classes seraient supprimées.
COPY src ./src
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN pnpm run build

FROM nginx:1.29-alpine AS web
COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY --chmod=755 docker/nginx/90-reload-certs.sh /docker-entrypoint.d/90-reload-certs.sh
