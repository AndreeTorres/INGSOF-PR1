FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8
RUN dnf -y update && dnf -y install nginx openssl && dnf clean all
RUN mkdir -p /var/www/html /etc/nginx/conf.d /etc/ssl/private /etc/ssl/certs
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d/site.conf /etc/nginx/conf.d/site.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 80 443
STOPSIGNAL SIGQUIT
CMD ["/entrypoint.sh"]
