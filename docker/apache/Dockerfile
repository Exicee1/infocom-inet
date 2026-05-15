FROM httpd:2.4.67

RUN apt-get update && apt-get install -y --no-install-recommends \
    openssl ca-certificates ssl-cert libssl-dev curl unzip gnupg ssl-cert \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY conf/httpd.conf /usr/local/apache2/conf/httpd.conf
COPY conf/httpd-mpm.conf /usr/local/apache2/conf/extra/httpd-mpm.conf
COPY conf/httpd-vhosts.conf /usr/local/apache2/conf/httpd-vhosts.conf
COPY conf/httpd-ssl.conf /usr/local/apache2/conf/extra/httpd-ssl.conf

RUN mkdir -p /usr/local/apache2/htdocs /usr/local/apache2/conf/ssl

WORKDIR /usr/local/apache2/htdocs
EXPOSE 80 443
CMD ["httpd", "-DFOREGROUND"]
