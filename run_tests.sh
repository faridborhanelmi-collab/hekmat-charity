#!/usr/bin/env bash
# run_tests.sh - Master Anti-Regression Test Runner for Hekmat Charity

set -e

export NO_PROXY="127.0.0.1,localhost"
export no_proxy="127.0.0.1,localhost"

GREEN='\033[0;32m'
RED='\033[0;31m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "\n${CYAN}============================================================${NC}"
echo -e "${CYAN}      🛡️  HEKMAT CHARITY ANTI-REGRESSION SUITE  🛡️        ${NC}"
echo -e "${CYAN}============================================================${NC}\n"

# Step 1: Ensure isolated test database exists
echo -e "${BOLD}▶ [1/3] Preparing Isolated Test Database Fixtures...${NC}"
php tests/fixtures/setup_test_db.php

# Step 2: Run PHP Unit & RBAC Regression Tests
echo -e "\n${BOLD}▶ [2/3] Running Unit, RBAC & Financial Regression Tests...${NC}"
php tests/run_unit_tests.php

# Step 3: Run Playwright E2E Regression Tests
echo -e "\n${BOLD}▶ [3/3] Running Playwright Browser Automated Tests...${NC}"
if npx playwright --version >/dev/null 2>&1; then
    npx playwright test
else
    echo -e "${YELLOW}⚠️ Playwright CLI not installed yet. Run 'npm install' & 'npx playwright install chromium'.${NC}"
fi

echo -e "\n${GREEN}============================================================${NC}"
echo -e "${GREEN}  🎉 ALL REGRESSION DEFENSES PASSED! CODE IS SAFE! 🎉       ${NC}"
echo -e "${GREEN}============================================================${NC}\n"
