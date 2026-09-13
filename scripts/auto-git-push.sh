#!/bin/bash
# =============================================================================
# auto-git-push.sh — Auto-commit & push changes for AI Context Engine
# Runs via macOS LaunchAgent every 30 minutes (configurable)
# =============================================================================

REPO_DIR="/Users/abhinaykalkhanday/Desktop/Projects/MCP-Context-export"
LOG_FILE="$REPO_DIR/scripts/auto-git-push.log"
MAX_LOG_LINES=500

# ── Rotate log to keep it small ──────────────────────────────────────────────
if [ -f "$LOG_FILE" ]; then
  line_count=$(wc -l < "$LOG_FILE")
  if [ "$line_count" -gt "$MAX_LOG_LINES" ]; then
    tail -n $MAX_LOG_LINES "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
  fi
fi

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# ── Enter repo ────────────────────────────────────────────────────────────────
cd "$REPO_DIR" || { log "ERROR: Cannot find repo at $REPO_DIR"; exit 1; }

# ── Check if git repo ─────────────────────────────────────────────────────────
if ! git rev-parse --git-dir > /dev/null 2>&1; then
  log "ERROR: Not a git repository. Run: git init"
  exit 1
fi

# ── Check for changes ─────────────────────────────────────────────────────────
CHANGED=$(git status --porcelain)

if [ -z "$CHANGED" ]; then
  log "No changes. Skipping commit."
  exit 0
fi

# ── Count changed files ───────────────────────────────────────────────────────
FILE_COUNT=$(echo "$CHANGED" | grep -c .)

# ── Stage all changes ─────────────────────────────────────────────────────────
git add -A

# ── Build commit message ──────────────────────────────────────────────────────
TIMESTAMP=$(date '+%Y-%m-%d %H:%M')
COMMIT_MSG="auto: save $FILE_COUNT file(s) — $TIMESTAMP"

# Append short file summary (first 5 files)
SUMMARY=$(echo "$CHANGED" | head -5 | awk '{print $2}' | tr '\n' ', ' | sed 's/,$//')
if [ "$FILE_COUNT" -gt 5 ]; then
  SUMMARY="$SUMMARY ... (+$((FILE_COUNT - 5)) more)"
fi
COMMIT_MSG="$COMMIT_MSG

Changed: $SUMMARY"

# ── Commit ────────────────────────────────────────────────────────────────────
git commit -m "$COMMIT_MSG" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
  log "ERROR: git commit failed."
  exit 1
fi

log "✅ Committed $FILE_COUNT file(s): $SUMMARY"

# ── Push ──────────────────────────────────────────────────────────────────────
git push origin main >> "$LOG_FILE" 2>&1
if [ $? -eq 0 ]; then
  log "🚀 Pushed to GitHub successfully."
else
  log "⚠️  Push failed (check network/auth). Changes are committed locally."
fi
