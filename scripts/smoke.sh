#!/usr/bin/env bash
# End-to-end smoke test for the live deploy.
# Runs against TEST_URL (default: https://pandora-dev.js-store.com.tw)
# Checks HTTP status + expected content markers. Non-zero exit on any failure.
#
# Usage:
#   ./scripts/smoke.sh                   # test dev
#   TEST_URL=https://shop.jerosse.tw ./scripts/smoke.sh   # test prod
#
# CI-friendly: exits 1 on first failure, prints PASS/FAIL per check.

set -u

BASE="${TEST_URL:-https://pandora-dev.js-store.com.tw}"
PASS=0
FAIL=0
FAILURES=()

c_green='\033[0;32m'
c_red='\033[0;31m'
c_gray='\033[0;90m'
c_reset='\033[0m'

pass() {
  printf "${c_green}✓${c_reset} %s\n" "$1"
  PASS=$((PASS + 1))
}
fail() {
  printf "${c_red}✗${c_reset} %s  ${c_gray}(%s)${c_reset}\n" "$1" "$2"
  FAIL=$((FAIL + 1))
  FAILURES+=("$1")
}

# assert_status URL EXPECTED_CODE
assert_status() {
  local url="$1" expected="$2"
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || echo "000")
  if [[ "$code" == "$expected" ]]; then
    pass "$url → $code"
  else
    fail "$url expected $expected got $code" "HTTP code mismatch"
  fi
}

# assert_contains URL PATTERN [PATTERN ...]   (all must match)
assert_contains() {
  local url="$1"; shift
  local body
  body=$(curl -sS "$url" 2>/dev/null || echo "")
  local missing=()
  for pat in "$@"; do
    if ! printf '%s' "$body" | grep -q -- "$pat"; then
      missing+=("$pat")
    fi
  done
  if [[ ${#missing[@]} -eq 0 ]]; then
    pass "$url contains [$*]"
  else
    fail "$url missing patterns: ${missing[*]}" "content assertion"
  fi
}

# assert_not_contains URL PATTERN  (must NOT match)
assert_not_contains() {
  local url="$1" pat="$2"
  local body
  body=$(curl -sS "$url" 2>/dev/null || echo "")
  if printf '%s' "$body" | grep -q -- "$pat"; then
    fail "$url unexpectedly contains '$pat'" "should not contain"
  else
    pass "$url does NOT contain '$pat'"
  fi
}

# assert_json_path URL JQ_EXPR EXPECTED
assert_json_path() {
  local url="$1" expr="$2" expected="$3"
  local got
  got=$(curl -sS "$url" | python3 -c "
import json, sys
d = json.load(sys.stdin)
expr = '$expr'
# minimal dotted-path + index navigation
parts = expr.lstrip('.').replace('[', '.').replace(']', '').split('.')
for p in parts:
    if p == '': continue
    if p.isdigit(): d = d[int(p)]
    else: d = d[p]
print(d)
" 2>/dev/null || echo "__error__")
  if [[ "$got" == "$expected" ]]; then
    pass "$url $expr = $expected"
  else
    fail "$url $expr expected $expected got $got" "json path"
  fi
}

echo "─── Frontend smoke test — $BASE ───"

# 1. Core routes
assert_status "$BASE/" 200
assert_status "$BASE/products" 200
assert_status "$BASE/articles" 200
assert_status "$BASE/about" 200
assert_status "$BASE/join" 200
assert_status "$BASE/cart" 200
assert_status "$BASE/account" 200
assert_status "$BASE/account/mascot" 200
assert_status "$BASE/return-policy" 200
assert_status "$BASE/privacy" 200

# 2. Article tabs (SSR with various type filters)
assert_status "$BASE/articles?type=blog,news" 200
assert_status "$BASE/articles?type=brand" 200
assert_status "$BASE/articles?type=recommend" 200

# 3. Product category filter
assert_status "$BASE/products?category=nonexistent-category" 200

# 4. Content assertions — these must render (not just 200)
assert_contains "$BASE/" '婕樂纖' 'PRICING · 三階梯' '越買越划算' '最多人選'
assert_contains "$BASE/articles" '婕樂纖誌' 'card-enter'
assert_contains "$BASE/join" '自用加盟' '創業加盟'

# 5. API endpoints
assert_status "$BASE/api/products" 200
assert_status "$BASE/api/articles" 200
assert_status "$BASE/api/article-categories" 200
assert_status "$BASE/api/product-categories" 200
# Multi-type filter returns at least blog+news count (grows daily via scraper cron)
multi_total=$(curl -sS "$BASE/api/articles?type=blog,news&per_page=1" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("total", 0))' 2>/dev/null || echo 0)
if [[ "$multi_total" -ge 100 ]]; then
  pass "multi-type filter (blog,news) total=$multi_total (>=100)"
else
  fail "multi-type filter returned $multi_total, expected >=100" "json threshold"
fi

# 6. 404 handling — unknown slugs
assert_status "$BASE/api/products/this-slug-definitely-does-not-exist-12345" 404
assert_status "$BASE/api/articles/this-slug-definitely-does-not-exist-12345" 404
assert_contains "$BASE/products/this-slug-definitely-does-not-exist-12345" '找不到此頁面'

# 7. Must-not-show on main pages
assert_not_contains "$BASE/" '發生錯誤'
assert_not_contains "$BASE/articles" '發生錯誤'
assert_not_contains "$BASE/products" '發生錯誤'

# 8. Coupon UI removed
assert_not_contains "$BASE/cart" '優惠碼'
assert_not_contains "$BASE/checkout" '有優惠碼'

echo
echo "─── Summary ───"
printf "Passed: ${c_green}%d${c_reset}   Failed: ${c_red}%d${c_reset}\n" "$PASS" "$FAIL"

if [[ $FAIL -gt 0 ]]; then
  echo
  echo "Failures:"
  for f in "${FAILURES[@]}"; do echo "  - $f"; done
  exit 1
fi
exit 0
