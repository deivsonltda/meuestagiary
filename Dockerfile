# =========================================================
# MeuEstagiário - PHP 8.2 FPM + Nginx (Alpine)
# - Garante que /public/assets exista (sem depender de symlink do Git)
# - Cria pastas de runtime (storage/log) com permissões
# - Mantém nginx em foreground e php-fpm em background
# =========================================================

FROM php:8.2-fpm-alpine

# ---------- Dependências + extensões PHP ----------
RUN apk add --no-cache \
      nginx \
      curl \
      bash \
      icu-dev \
      oniguruma-dev \
      libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip pdo pdo_mysql \
    && rm -rf /var/cache/apk/*

# ---------- App ----------
WORKDIR /var/www/html
COPY . .

# ---------- Nginx config ----------
# Remove config default e coloca o seu
RUN rm -f /etc/nginx/http.d/default.conf
COPY ./_deploy/nginx.conf /etc/nginx/http.d/default.conf

# ---------- Pastas + permissões + assets ----------
# 1) cria storage/log (se precisar)
# 2) garante public/assets apontando para a pasta real /assets
#    (resolve o problema de CSS/JS não carregar no deploy)
RUN mkdir -p /var/www/html/storage /var/www/html/log \
  && rm -rf /var/www/html/public/assets \
  && ln -s /var/www/html/assets /var/www/html/public/assets \
  && chown -R www-data:www-data /var/www/html

# ---------- Porta ----------
EXPOSE 80

# ---------- Start ----------
# - php-fpm em background
# - nginx em foreground
CMD ["sh", "-lc", "php-fpm -D && nginx -g 'daemon off;'"]