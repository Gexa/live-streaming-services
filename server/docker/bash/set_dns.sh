#!/bin/bash

# https://stackoverflow.com/questions/34726498/how-to-update-google-cloud-dns-with-ephemeral-ip-for-an-instance
# https://stackoverflow.com/questions/38636010/removing-dns-entries-with-gcloud

ttlify() {
  local i
  for i in "$@"; do
    [[ "${i}" =~ ^([0-9]+)([a-z]*)$ ]] || continue
    local num="${BASH_REMATCH[1]}"
    local unit="${BASH_REMATCH[2]}"
    case "${unit}" in
                     weeks|week|wee|we|w) unit=''; num=$[num*60*60*24*7];;
                           days|day|da|d) unit=''; num=$[num*60*60*24];;
                     hours|hour|hou|ho|h) unit=''; num=$[num*60*60];;
      minutes|minute|minut|minu|min|mi|m) unit=''; num=$[num*60];;
      seconds|second|secon|seco|sec|se|s) unit=''; num=$[num];;
    esac
    echo "${num}${unit}"
  done
}

dns_start() {
  gcloud dns record-sets transaction start    -z "${ZONENAME}"
}

dns_info() {
  gcloud dns record-sets transaction describe -z "${ZONENAME}"
}

dns_abort() {
  gcloud dns record-sets transaction abort    -z "${ZONENAME}"
}

dns_commit() {
  gcloud dns record-sets transaction execute  -z "${ZONENAME}"
}

dns_add() {
  if [[ -n "$1" && "$1" != '@' ]]; then
    local -r name="$1.${ZONE}."
  else
    local -r name="${ZONE}."
  fi
  local -r ttl="$(ttlify "$2")"
  local -r type="$3"
  shift 3
  gcloud dns record-sets transaction add -z "${ZONENAME}" --name "${name}" --ttl "${ttl}" --type "${type}" "$@"
}

dns_del() {
  if [[ -n "$1" && "$1" != '@' ]]; then
    local -r name="$1.${ZONE}."
  else
    local -r name="${ZONE}."
  fi
  local -r ttl="$(ttlify "$2")"
  local -r type="$3"
  shift 3
  gcloud dns record-sets transaction remove -z "${ZONENAME}" --name "${name}" --ttl "${ttl}" --type "${type}" "$@"
}

lookup_dns_ip() {
  host "$1" | sed -rn 's@^.* has address @@p'
}

my_ip() {
  IP=$(curl "http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/access-configs/0/external-ip" -H "Metadata-Flavor: Google" 2>/dev/null)
  echo "$IP"
}

get_name() {
  HOST=$(curl "http://metadata.google.internal/computeMetadata/v1/instance/hostname" -H "Metadata-Flavor: Google" 2>/dev/null)
  awk -F/ '{n=split($1, a, "."); printf("%s.%s", a[n-1], a[n])}' <<< $HOST
}

doit() {
  ZONENAME="gc-dns-zone-name-REPLACE_THIS"
  NAME=$(hostname)
  ZONE=`get_name`
  #echo "$NAME"
  dns_start
  dns_del "${NAME}" 1min A `lookup_dns_ip ${NAME}` #TODO REPLACE
  dns_add "${NAME}" 1min A `my_ip`
  dns_commit
}


gcloud auth activate-service-account --key-file=/tmp/adc.json
gcloud config set project PROJECT-ID-REPLACE_THIS

# RUN DNS MODS
doit