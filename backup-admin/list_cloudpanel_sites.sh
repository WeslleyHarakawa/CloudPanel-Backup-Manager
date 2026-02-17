#!/bin/bash
set -e
for home in /home/*; do
  [ -d "$home/htdocs" ] || continue
  site_user="$(basename "$home")"
  for app in "$home"/htdocs/*; do
    [ -d "$app" ] || continue
    docroot="$app"
    domain="$(basename "$app")"
    db_host=""
    db_name=""
    db_user=""
    db_pass=""
    if [ -f "$docroot/.env" ]; then
      envfile="$docroot/.env"
      line="$(grep -E '^DB_HOST=' "$envfile" | head -n1)"
      if [ -n "$line" ]; then
        db_host="$(echo "${line#DB_HOST=}" | tr -d '\"' | tr -d \"'\")"
      fi
      line="$(grep -E '^DB_DATABASE=' "$envfile" | head -n1)"
      if [ -n "$line" ]; then
        db_name="$(echo "${line#DB_DATABASE=}" | tr -d '\"' | tr -d \"'\")"
      fi
      line="$(grep -E '^DB_USERNAME=' "$envfile" | head -n1)"
      if [ -n "$line" ]; then
        db_user="$(echo "${line#DB_USERNAME=}" | tr -d '\"' | tr -d \"'\")"
      fi
      line="$(grep -E '^DB_PASSWORD=' "$envfile" | head -n1)"
      if [ -n "$line" ]; then
        db_pass="$(echo "${line#DB_PASSWORD=}" | tr -d '\"' | tr -d \"'\")"
      fi
    elif [ -f "$docroot/wp-config.php" ]; then
      config="$docroot/wp-config.php"
      db_host="$(grep \"DB_HOST\" \"$config\" | head -n1 | sed \"s/.*'\\([^']*\\)'.*/\\1/\")"
      db_name="$(grep \"DB_NAME\" \"$config\" | head -n1 | sed \"s/.*'\\([^']*\\)'.*/\\1/\")"
      db_user="$(grep \"DB_USER\" \"$config\" | head -n1 | sed \"s/.*'\\([^']*\\)'.*/\\1/\")"
      db_pass="$(grep \"DB_PASSWORD\" \"$config\" | head -n1 | sed \"s/.*'\\([^']*\\)'.*/\\1/\")"
    fi
    echo "${site_user}|${domain}|${docroot}|${db_host}|${db_name}|${db_user}|${db_pass}"
  done
done
