FROM quay.io/centos/centos:stream9

ENV TERM=xterm \
    LC_ALL=en_US.UTF-8 \
    LANG=en_US.UTF-8

# 0) Repos fijos (evita mirrorlist rotas en builds)
RUN rm -f /etc/yum.repos.d/*.repo && \
    echo '[baseos]' > /etc/yum.repos.d/centos-stream.repo && \
    echo 'name=CentOS Stream 9 - BaseOS' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'baseurl=http://mirror.stream.centos.org/9-stream/BaseOS/$basearch/os/' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'enabled=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgcheck=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial' >> /etc/yum.repos.d/centos-stream.repo && \
    echo '' >> /etc/yum.repos.d/centos-stream.repo && \
    echo '[appstream]' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'name=CentOS Stream 9 - AppStream' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'baseurl=http://mirror.stream.centos.org/9-stream/AppStream/$basearch/os/' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'enabled=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgcheck=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial' >> /etc/yum.repos.d/centos-stream.repo && \
    echo '' >> /etc/yum.repos.d/centos-stream.repo && \
    echo '[crb]' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'name=CentOS Stream 9 - CRB' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'baseurl=http://mirror.stream.centos.org/9-stream/CRB/$basearch/os/' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'enabled=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgcheck=1' >> /etc/yum.repos.d/centos-stream.repo && \
    echo 'gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial' >> /etc/yum.repos.d/centos-stream.repo && \
    rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-centosofficial || true

# 1) Refrescar metadata, upgrade base y herramientas mínimas
RUN dnf -y clean all && rm -rf /var/cache/dnf && \
    dnf -y makecache && \
    dnf -y upgrade --refresh && \
    dnf -y install --allowerasing dnf-plugins-core curl ca-certificates && \
    update-ca-trust

# 2) EPEL + Remi para EL9 (usar URLs directas evita mirrors)
RUN dnf -y install http://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm && \
    dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# 3) Activar stream de PHP 8.2 (puedes cambiar a 8.3 si quieres)
RUN dnf -y module reset php && \
    dnf -y module enable php:remi-8.2

# 4) Instalar PHP-FPM + extensiones necesarias (mysqlnd)
RUN dnf -y install \
      php-fpm php-cli php-common php-mysqlnd php-opcache php-gd php-xml php-mbstring \
    && dnf clean all

# 5) Crear directorio para PID de PHP-FPM y configurar
RUN mkdir -p /run/php-fpm && \
    chown -R apache:apache /run/php-fpm

# 6) Configurar PHP-FPM para escuchar en 0.0.0.0:9000 y exponer variables de entorno
RUN sed -ri 's|^;?listen\s*=.*|listen = 0.0.0.0:9000|' /etc/php-fpm.d/www.conf && \
    sed -ri 's|^;?clear_env\s*=.*|clear_env = no|' /etc/php-fpm.d/www.conf && \
    sed -ri 's|^;?listen\.allowed_clients\s*=.*|; listen.allowed_clients disabled for Docker|' /etc/php-fpm.d/www.conf

WORKDIR /var/www/html
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD php-fpm -t
CMD ["php-fpm","-F"]
