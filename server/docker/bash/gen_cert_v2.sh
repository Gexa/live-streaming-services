#!/bin/bash

gen_ssl() {
    HOST=$(curl "http://metadata.google.internal/computeMetadata/v1/instance/hostname" -H "Metadata-Flavor: Google" 2>/dev/null)

    ISSUE=$(acme.sh --issue -d ${HOST} -w /var/www 2>/dev/null)
    INSTALL=$(acme.sh --install-cert -d ${HOST} \
        --debug 2
        --key-file       /var/www/ssl/key.pem  \
        --fullchain-file /var/www/ssl/cert.pem \
        --reloadcmd     "service nginx force-reload" 2>/dev/null)

    HOSTNAME=$(hostname -f 2>/dev/null);
    echo ${HOSTNAME}
    echo ${ISSUE}
    echo ${INSTALL}
}

gen_ssl