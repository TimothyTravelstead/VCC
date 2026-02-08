#!/bin/bash
#
# Deploy cron script — runs every minute via crontab.
# Checks for .deploy_trigger (written by github-webhook.php),
# runs git fetch + reset, then removes the trigger.
#
# Install: crontab -e
#   * * * * * /home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/api/deploy-cron.sh
#

REPO_DIR="/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html"
TRIGGER_FILE="$REPO_DIR/.deploy_trigger"
LOG_FILE="$REPO_DIR/deploy-cron.log"
LOCK_FILE="$REPO_DIR/.deploy_lock"

# Exit if no trigger
[ -f "$TRIGGER_FILE" ] || exit 0

# Prevent concurrent runs
if [ -f "$LOCK_FILE" ]; then
    # Stale lock check (older than 5 minutes)
    if [ "$(find "$LOCK_FILE" -mmin +5 2>/dev/null)" ]; then
        rm -f "$LOCK_FILE"
    else
        exit 0
    fi
fi
touch "$LOCK_FILE"

# Read trigger info
TRIGGER_DATA=$(cat "$TRIGGER_FILE")
PUSHER=$(echo "$TRIGGER_DATA" | python3 -c "import sys,json; print(json.load(sys.stdin).get('pusher','unknown'))" 2>/dev/null || echo "unknown")
COMMIT=$(echo "$TRIGGER_DATA" | python3 -c "import sys,json; print(json.load(sys.stdin).get('commit',''))" 2>/dev/null || echo "")

# Remove trigger immediately to prevent re-runs
rm -f "$TRIGGER_FILE"

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Step 1: git fetch
FETCH_OUTPUT=$(cd "$REPO_DIR" && git fetch origin main 2>&1)
FETCH_CODE=$?
echo "[$TIMESTAMP] Deploy started by $PUSHER: $COMMIT" >> "$LOG_FILE"
echo "[$TIMESTAMP]   fetch (code $FETCH_CODE): $FETCH_OUTPUT" >> "$LOG_FILE"

if [ $FETCH_CODE -ne 0 ]; then
    echo "[$TIMESTAMP]   FAILED at fetch step" >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
    rm -f "$LOCK_FILE"
    exit 1
fi

# Step 2: git reset --hard origin/main
RESET_OUTPUT=$(cd "$REPO_DIR" && git reset --hard origin/main 2>&1)
RESET_CODE=$?
echo "[$TIMESTAMP]   reset (code $RESET_CODE): $RESET_OUTPUT" >> "$LOG_FILE"

if [ $RESET_CODE -ne 0 ]; then
    echo "[$TIMESTAMP]   FAILED at reset step" >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
    rm -f "$LOCK_FILE"
    exit 1
fi

# Step 3: clean untracked files
CLEAN_OUTPUT=$(cd "$REPO_DIR" && git clean -fd 2>&1)
if [ -n "$CLEAN_OUTPUT" ]; then
    echo "[$TIMESTAMP]   clean: $CLEAN_OUTPUT" >> "$LOG_FILE"
fi

echo "[$TIMESTAMP]   SUCCESS" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

rm -f "$LOCK_FILE"
