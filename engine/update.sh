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
FORCE_CHECK_EVERY_SECONDS=30
UPDATE_LOG=/tmp/secondcrack-update.$INSTANCE.log
ERROR_LOG=/tmp/secondcrack-error.$INSTANCE.err

SCRIPT_LOCK_FILE="${SECONDCRACK_PATH}/engine/secondcrack-updater.pid"
BASH_LOCK_DIR="${SECONDCRACK_PATH}/engine/secondcrack-updater.sh.lock"
SH_LOCK_FILE="/var/run/secondcrack/$INSTANCE.pid"
echo "$$" > $SH_LOCK_FILE

if mkdir "$BASH_LOCK_DIR" ; then
    trap "rmdir '$BASH_LOCK_DIR' 2>/dev/null ; exit" INT TERM EXIT

    echo "`date` -- updating secondcrack" >> $UPDATE_LOG
    php -f "${SECONDCRACK_PATH}/engine/update.php" "$SCRIPT_LOCK_FILE"

    if [ "`which inotifywait`" != "" ] ; then
        while true ; do
            inotifywait -q -q -r -t $FORCE_CHECK_EVERY_SECONDS -e close_write -e create -e delete -e moved_from "$SOURCE_PATH"
            if [ $? -eq 0 ] ; then
                echo "`date` -- updating secondcrack, a source file changed" >> $UPDATE_LOG
            else
                echo "`date` -- updating secondcrack, $FORCE_CHECK_EVERY_SECONDS seconds elapsed" >> $UPDATE_LOG
            fi

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
        done
    fi

    rmdir "$BASH_LOCK_DIR" 2>/dev/null
    trap - INT TERM EXIT
else
   echo "Already running"
fi
