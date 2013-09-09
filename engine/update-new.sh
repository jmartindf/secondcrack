#!/bin/bash

if [ "$1" == "" -o "$2" == "" ] ; then
    echo ""
    echo "Usage: update.sh SOURCE_PATH SECONDCRACK_PATH"
    echo "  where SOURCE_PATH contains /posts, /templates, ..."
    echo "  and SECONDCRACK_PATH contains /cache, /engine, ..."
    echo ""
    exit 1
fi

SOURCE_PATH="$1"
SECONDCRACK_PATH="$2"
INSTANCE="$3"
UPDATE_LOG=/tmp/secondcrack-update.$INSTANCE.log
ERROR_LOG=/tmp/secondcrack-error.$INSTANCE.err

SCRIPT_LOCK_FILE="${SECONDCRACK_PATH}/engine/secondcrack-updater.pid"

if [ -f "$SOURCE_PATH/rebuild" ] ; then
  mv "$SOURCE_PATH/rebuild" "$SOURCE_PATH/rebuilding"
  php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE" rebuild 1>>$UPDATE_LOG.php 2>>$ERROR_LOG
  mv "$SOURCE_PATH/rebuilding" "$SOURCE_PATH/rebuilt"
else
  php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE" 1>>$UPDATE_LOG.php 2>>$ERROR_LOG
  while [ $? -eq 2 ] ; do 
      echo "`date` -- updating secondcrack, last run performed writes" >> $UPDATE_LOG
      php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE" 1>>$UPDATE_LOG.php 2>>$ERROR_LOG
  done
fi
