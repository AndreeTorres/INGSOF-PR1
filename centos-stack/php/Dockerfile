FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8

# Repos Remi para PHP 8.2 (puedes cambiar a 8.3 si quieres)
RUN dnf -y update && dnf -y install dnf-plugins-core curl && \
    dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm && \
    dnf -y module reset php && dnf -y module enable php:remi-8.2 && \
    dnf -y install php-fpm php-cli php-common php-mysqlnd php-opcache php-gd php-xml php-mbstring && \
    dnf clean all

# PHP-FPM: escucha en 0.0.0.0:9000 y expone vars de entorno a PHP
RUN sed -ri 's|^;?listen\s*=.*|listen = 0.0.0.0:9000|' /etc/php-fpm.d/www.conf && \
    sed -ri 's|^;?clear_env\s*=.*|clear_env = no|' /etc/php-fpm.d/www.conf

WORKDIR /var/www/html
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD php-fpm -t
CMD ["php-fpm","-F"]
