FROM wordpress:6.1-php7.4 AS builder
RUN apt update && apt install -y zip
RUN curl -L -o /tmp/base-theme.zip https://downloads.wordpress.org/theme/newspaperss.2.10.0.zip
RUN curl -L -o /tmp/wpamp-plugin.zip https://downloads.wordpress.org/plugin/amp.2.4.1.zip
COPY --from=composer/composer:2.5.5-bin /composer /usr/bin/composer
COPY --from=wordpress:cli-php7.4 /usr/local/bin/wp /usr/local/bin/wp
COPY --from=node:17.9.1 /usr/local/bin/node /usr/local/bin/node
COPY --from=node:17.9.1 /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
RUN ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
ARG COMPOSER_AUTH
RUN wp --allow-root package install hellonico/wp-cli-fixtures
WORKDIR /app

FROM wordpress:4.9-php7.2 AS wp49-php72
COPY --from=wordpress:cli-php7.2 /usr/local/bin/wp /usr/local/bin/wp
COPY --from=builder /tmp/base-theme.zip /tmp/base-theme.zip
COPY --from=builder /tmp/wpamp-plugin.zip /tmp/wpamp-plugin.zip
ENV CACERT_PATH="/tmp/cacert.pem"
RUN set -ex && \
    echo '' > /etc/ca-certificates.conf && \
    curl --fail --insecure -o $CACERT_PATH https://curl.se/ca/cacert.pem && \
    openssl x509 -in $CACERT_PATH -inform PEM -out /usr/local/share/ca-certificates/cacert.crt && \
    update-ca-certificates -f -v;
RUN { \
        echo "curl.cainfo = \"$CACERT_PATH\""; \
        echo "openssl.cafile = \"$CACERT_PATH\""; \
    } > /usr/local/etc/php/conf.d/cert_opts.ini; \
    { \
		echo 'post_max_size = 200M'; \
		echo 'memory_limit = 1024M'; \
	} > /usr/local/etc/php/conf.d/tap.ini;
RUN wp --allow-root package install https://github.com/nlemoine/wp-cli-fixtures/archive/refs/tags/0.3.0.zip
COPY apache_port_template.conf /etc/apache2/ports.conf
ARG WP49_PHP72_PORT
# Tell WP service to listen to WP49_PHP72_PORT
RUN sed -i "s/<PORT>/$WP49_PHP72_PORT/g" /etc/apache2/ports.conf
COPY . /tmp/docker-context

FROM wordpress:5.9-php7.4 AS wp59-php74
COPY --from=wordpress:cli-php7.4 /usr/local/bin/wp /usr/local/bin/wp
COPY --from=builder /root/.wp-cli /root/.wp-cli
COPY --from=builder /tmp/base-theme.zip /tmp/base-theme.zip
COPY --from=builder /tmp/wpamp-plugin.zip /tmp/wpamp-plugin.zip
COPY apache_port_template.conf /etc/apache2/ports.conf
ARG WP59_PHP74_PORT
# Tell WP apache to listen to WP59_PHP74_PORT
RUN sed -i "s/<PORT>/$WP59_PHP74_PORT/g" /etc/apache2/ports.conf
COPY . /tmp/docker-context

FROM wordpress:6.1-php7.4 AS wp61-php74
COPY --from=wordpress:cli-php7.4 /usr/local/bin/wp /usr/local/bin/wp
COPY --from=builder /root/.wp-cli /root/.wp-cli
COPY --from=builder /tmp/base-theme.zip /tmp/base-theme.zip
COPY --from=builder /tmp/wpamp-plugin.zip /tmp/wpamp-plugin.zip
COPY apache_port_template.conf /etc/apache2/ports.conf
ARG WP61_PHP74_PORT
# Tell WP apache to listen to WP61_PHP74_PORT
RUN sed -i "s/<PORT>/$WP61_PHP74_PORT/g" /etc/apache2/ports.conf
COPY . /tmp/docker-context

FROM wordpress:6.1-php8.2 AS wp61-php82
COPY --from=wordpress:cli-php8.2 /usr/local/bin/wp /usr/local/bin/wp
COPY --from=builder /root/.wp-cli /root/.wp-cli
COPY --from=builder /tmp/base-theme.zip /tmp/base-theme.zip
COPY --from=builder /tmp/wpamp-plugin.zip /tmp/wpamp-plugin.zip
COPY apache_port_template.conf /etc/apache2/ports.conf
ARG WP61_PHP82_PORT
# Tell WP apache to listen to WP61_PHP82_PORT
RUN sed -i "s/<PORT>/$WP61_PHP82_PORT/g" /etc/apache2/ports.conf
COPY . /tmp/docker-context
