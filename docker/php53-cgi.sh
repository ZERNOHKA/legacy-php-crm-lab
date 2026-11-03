#!/bin/sh
set -eu
if [ "${REDIRECT_STATUS:-}" != "200" ]; then
    printf 'Status: 403 Forbidden\r\nContent-Type: text/plain\r\n\r\nDirect CGI access is disabled.\n'
    exit 0
fi
export PHPRC=/etc/php533/php.ini
# Do not forward HTTP-derived arguments to this legacy CGI executable.
exec /opt/php533/bin/php-cgi -c /etc/php533/php.ini
