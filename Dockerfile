FROM alpine:3.22 AS source
RUN apk add --no-cache curl
WORKDIR /source
RUN curl --fail --location --retry 3 https://museum.php.net/php5/php-5.3.3.tar.bz2 -o php.tar.bz2 \
    && echo 'f2876750f3c54854a20e26a03ca229f2fbf89b8ee6176b9c0586cb9b2f0b3f9a  php.tar.bz2' | sha256sum -c -

FROM ubuntu:12.04 AS build
ENV DEBIAN_FRONTEND=noninteractive
COPY docker/ubuntu-sources.list /etc/apt/sources.list
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential bzip2 libbz2-dev zlib1g-dev libxml2-dev libssl-dev \
    libcurl4-openssl-dev libjpeg-dev libpng12-dev libfreetype6-dev libicu-dev \
    libmcrypt-dev libmhash-dev libpcre3-dev libreadline6-dev libxslt1-dev \
    && rm -rf /var/lib/apt/lists/*
COPY --from=source /source/php.tar.bz2 /tmp/php.tar.bz2
RUN mkdir /usr/src/php && tar -xjf /tmp/php.tar.bz2 -C /usr/src/php --strip-components=1
# PHP 5.3 predates Ubuntu's multiarch library paths.
RUN for library in jpeg png crypto ssl curl; do \
        ln -s /usr/lib/x86_64-linux-gnu/lib${library}.so /usr/lib/lib${library}.so; \
    done
WORKDIR /usr/src/php
RUN sed -i 's/#if OPENSSL_VERSION_NUMBER >= 0x0090805fL/#if OPENSSL_VERSION_NUMBER >= 0x0090805fL \&\& !defined(OPENSSL_NO_SSL2)/g' ext/openssl/xp_ssl.c \
    && sed -i 's/SSLv2_client_method()/SSLv23_client_method()/g; s/SSLv2_server_method()/SSLv23_server_method()/g' ext/openssl/xp_ssl.c
RUN CFLAGS="-g -O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0" ./configure \
    --prefix=/opt/php533 \
    --with-config-file-path=/etc/php533 \
    --with-config-file-scan-dir=/etc/php533/conf.d \
    --disable-all --enable-cli --enable-cgi --enable-phar --enable-session \
    --enable-short-tags --enable-tokenizer --with-pcre-regex --with-zlib=/usr \
    --with-mysql=mysqlnd --with-mysqli=mysqlnd --with-pdo-mysql=mysqlnd \
    --with-mysql-sock=/var/lib/mysql/mysql.sock --with-iconv \
    --enable-gd-native-ttf --with-jpeg-dir=/usr --with-png-dir=/usr \
    --with-freetype-dir=/usr --enable-intl --enable-dom --enable-libxml \
    --enable-simplexml --enable-xml --enable-xmlreader --enable-xmlwriter \
    --with-xsl --with-libxml-dir=/usr --enable-mbstring --enable-mbregex \
    --enable-bcmath --with-bz2=/usr --enable-calendar --enable-ctype \
    --enable-fileinfo --enable-filter --enable-shmop --enable-sysvsem \
    --enable-sysvshm --enable-sysvmsg --enable-json --with-mhash \
    --with-mcrypt=/usr --enable-pcntl --with-pcre-dir=/usr --enable-pdo \
    --enable-posix --with-readline=/usr --enable-sockets --with-curl=/usr \
    --with-openssl=/usr --enable-zip --with-pear=/opt/php533/lib/php \
    --enable-sqlite-utf8 --enable-zend-multibyte \
    && sed -i '/^EXTRA_LIBS =/ s/$/ -lstdc++/' Makefile \
    && make -j2 && make install

FROM ubuntu:12.04
ENV DEBIAN_FRONTEND=noninteractive
COPY docker/ubuntu-sources.list /etc/apt/sources.list
RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 apache2-mpm-prefork curl libbz2-1.0 zlib1g libxml2 libssl1.0.0 libcurl3 \
    libjpeg8 libpng12-0 libfreetype6 libicu48 libmcrypt4 libmhash2 \
    libpcre3 libreadline6 libxslt1.1 libstdc++6 \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod actions cgi rewrite headers \
    && mkdir -p /etc/php533/conf.d /var/lib/php533/sessions /usr/lib/cgi-bin \
    && chown www-data:www-data /var/lib/php533/sessions \
    && chmod 700 /var/lib/php533/sessions \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf.d/lab.conf
COPY --from=build /opt/php533 /opt/php533
COPY docker/php.ini /etc/php533/php.ini
COPY docker/php53-cgi.sh /usr/lib/cgi-bin/php53-wrapper
COPY docker/apache.conf /etc/apache2/sites-available/default
COPY docker/start-apache.sh /usr/local/bin/start-apache
COPY crm/ /var/www/html/
COPY scripts/check-runtime.php /opt/lab/check-runtime.php
RUN chmod 755 /usr/lib/cgi-bin/php53-wrapper /usr/local/bin/start-apache \
    && ln -s /opt/php533/bin/php /usr/local/bin/php \
    && php /opt/lab/check-runtime.php \
    && DB_HOST=db DB_NAME=crm_demo DB_USER=crm_demo DB_PASSWORD=unused apache2ctl -t
WORKDIR /var/www/html
EXPOSE 80
CMD ["/usr/local/bin/start-apache"]
