FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 MARIADB_DATA_DIR=/var/lib/mysql

RUN dnf -y update && dnf -y install mariadb-server procps-ng which && dnf clean all

# Asegura que 'mariadbd' exista (algunas builds lo exponen como mysqld)
RUN command -v mariadbd >/dev/null 2>&1 || (ln -s "$(command -v mysqld)" /usr/sbin/mariadbd)

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

VOLUME ["/var/lib/mysql"]
EXPOSE 3306

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD mysqladmin ping -h 127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD" || exit 1
CMD ["/docker-entrypoint.sh"]
