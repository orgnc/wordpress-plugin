FROM wordpress:4.9-php5.6 AS wp49-php56
COPY --from=wordpress:cli-php5.6 /usr/local/bin/wp /usr/local/bin/wp

FROM wordpress:4.9-php7.2 AS wp49-php72
COPY --from=wordpress:cli-php7.2 /usr/local/bin/wp /usr/local/bin/wp

FROM wordpress:5.9-php7.4 AS wp59-php74
COPY --from=wordpress:cli-php7.4 /usr/local/bin/wp /usr/local/bin/wp

FROM wordpress:6.1-php7.4 AS wp61-php74
COPY --from=wordpress:cli-php7.4 /usr/local/bin/wp /usr/local/bin/wp

FROM wordpress:6.1-php8.2 AS wp61-php82
COPY --from=wordpress:cli-php8.2 /usr/local/bin/wp /usr/local/bin/wp
