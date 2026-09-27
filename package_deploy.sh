#!/usr/bin/env bash
# package_deploy.sh - Automated Safe Deployment Packager for Hekmat Charity

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "\n${CYAN}============================================================${NC}"
echo -e "${CYAN}    📦  HEKMAT CHARITY SAFE PRODUCTION PACKAGER  📦        ${NC}"
echo -e "${CYAN}============================================================${NC}\n"

# 1. Run full regression test suite first
echo -e "${BOLD}▶ [1/2] Verifying system with Anti-Regression Suite...${NC}"
./run_tests.sh

# 2. If tests pass, generate safe release archive
RELEASE_NAME="hekmat-deploy-safe-$(date +%Y%m%d_%H%M%S).zip"
echo -e "\n${BOLD}▶ [2/2] Tests Passed! Creating clean production package: ${RELEASE_NAME}...${NC}"

zip -r "${RELEASE_NAME}" . \
    -x "*.git*" \
    -x "*node_modules*" \
    -x "*tests/fixtures/test_hekmat.db*" \
    -x "*test-results*" \
    -x "*playwright-report*" \
    -x "*.DS_Store*" \
    -x "*__pycache__*" \
    -x "*hekmat-deploy-*.zip" \
    -x "*.zip" > /dev/null

echo -e "\n${GREEN}============================================================${NC}"
echo -e "${GREEN}  🎉 DEPLOY PACKAGE READY: ${RELEASE_NAME} 🎉${NC}"
echo -e "${GREEN}  100% verified against regressions. Safe to upload!        ${NC}"
echo -e "${GREEN}============================================================${NC}\n"
