#!/bin/bash
set -e

# ==============================================================================
# Hekmat Charity Automated Production Deployment Script
# Target: Oracle Cloud (147.5.102.140) -> /home/hekmat.neromoda.ir/public_html
# ==============================================================================

SERVER_IP="147.5.102.140"
SERVER_USER="ubuntu"
REMOTE_PATH="/home/hekmat.neromoda.ir/public_html"
DIR="$(cd "$(dirname "$0")" && pwd)"

# Locate SSH Key
SSH_KEY=""
for k in \
    "/Users/faridborhanelmi/Desktop/Telegram Automation/oracle_new.pem" \
    "$DIR/scratch/server_key.pem" \
    "$DIR/scratch/live_server_key.pem" \
    "$HOME/.ssh/hekmat_live_key" \
    "$HOME/.ssh/oracle_key"; do
    if [ -f "$k" ]; then
        SSH_KEY="$k"
        chmod 600 "$SSH_KEY" 2>/dev/null || true
        break
    fi
done

if [ -z "$SSH_KEY" ]; then
    echo "❌ خطا: کلید SSH سرور یافت نشد!"
    exit 1
fi

SSH_CMD="ssh -i \"$SSH_KEY\" -o StrictHostKeyChecking=no -o ConnectTimeout=10"

echo "🛡️ [1/3] اجرای آزمون‌های رگرسیون قبل از دیپلوی..."
php "$DIR/tests/run_unit_tests.php" > /dev/null
echo "✅ آزمون‌های رگرسیون با موفقیت پاس شدند."

echo "📦 [2/3] همگام‌سازی فایل‌ها با سرور اصلی ($SERVER_IP)..."
rsync -avz -e "$SSH_CMD" \
    --exclude '.git' \
    --exclude 'node_modules' \
    --exclude 'test-results' \
    --exclude 'tests/fixtures/test_hekmat.db*' \
    --exclude '*.db' \
    --exclude '*.db-wal' \
    --exclude '*.db-shm' \
    --exclude '*.bak*' \
    --exclude '.DS_Store' \
    --exclude '__pycache__' \
    --exclude 'uploads' \
    --exclude '*.zip' \
    --exclude '*.tar.gz' \
    --exclude '*.mp4' \
    --exclude '*.pdf' \
    --exclude '*.xlsx' \
    "$DIR/" "$SERVER_USER@$SERVER_IP:$REMOTE_PATH/"

echo "🔧 [3/3] بررسی سینتکس فایل‌ها، مهاجرت دیتابیس یادآوری و تنظیم مجوزها..."
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no -o ConnectTimeout=10 "$SERVER_USER@$SERVER_IP" "php -l $REMOTE_PATH/donor-dashboard.php && php -l $REMOTE_PATH/login.php && php -l $REMOTE_PATH/admin/donor-reminders.php && php $REMOTE_PATH/cron/migrate_donors.php && sudo chown ubuntu:www-data $REMOTE_PATH && sudo chmod 775 $REMOTE_PATH && sudo chmod 666 $REMOTE_PATH/hekmat.db* 2>/dev/null || true"

echo "🎉 استقرار نسخه جدید بنیاد حکمت روی سرور اصلی با موفقیت کامل انجام شد!"
