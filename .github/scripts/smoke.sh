#!/usr/bin/env bash
# Smoke-tests the built, unzipped plugin zip inside a running wp-env (see ci.yml).
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8888}"
TABLE='wp_consentful_consent_log'
CID='ci-smoke-cid-001'

fail() {
	echo "::error::$1"
	exit 1
}

cli() {
	npx wp-env run cli -- "$@"
}

# 1. The plugin installed from the zip is active.
cli wp plugin is-active consentful \
	|| fail "plugin 'consentful' is not active"

# 2. Activation created the consent log table.
tables=$(cli wp db query "SHOW TABLES LIKE '${TABLE}'" --skip-column-names) \
	|| fail "wp db query failed while looking for ${TABLE}"
printf '%s\n' "$tables" | tr -d '\r' | grep -Fxq "$TABLE" \
	|| fail "consent log table ${TABLE} does not exist"

# 3. A consent record POST is accepted and lands in the consent log.
body=$(printf '{"cid":"%s","grants":{"necessary":true,"functional":false,"analytics":true,"marketing":false},"jurisdiction":"CA-QC","policyVersion":1,"schemaVersion":1,"bannerVersion":1}' "$CID")
status=$(curl -sS -o /dev/null -w '%{http_code}' \
	-X POST -H 'Content-Type: application/json' --data "$body" \
	"${BASE_URL}/wp-json/consentful/v1/consent") \
	|| fail "consent POST to ${BASE_URL}/wp-json/consentful/v1/consent did not complete"
[ "${status:0:1}" = '2' ] || fail "consent POST returned HTTP ${status}, expected 2xx"

count=$(cli wp db query "SELECT COUNT(*) FROM ${TABLE} WHERE consent_id = '${CID}'" --skip-column-names \
	| tr -d '\r' | grep -Ex '[0-9]+' | tail -n 1) \
	|| fail "could not read the consent row count back from ${TABLE}"
[ "$count" = '1' ] || fail "expected 1 consent row for cid ${CID}, found ${count}"

# 4. The front page carries the config blob and the inlined decider.
front=$(curl -sS "${BASE_URL}/") || fail "could not fetch the front page"
printf '%s' "$front" | grep -Fq 'window.consentfulConfig' \
	|| fail "front page is missing window.consentfulConfig"
printf '%s' "$front" | grep -Fq 'wait_for_update' \
	|| fail "front page is missing the inlined decider (wait_for_update marker)"

# 5. The geo endpoint is uncacheable.
geo=$(curl -sSi "${BASE_URL}/wp-json/consentful/v1/geo") || fail "could not fetch the geo endpoint"
printf '%s\n' "$geo" | grep -Eiq '^cache-control:.*no-store' \
	|| fail "geo response lacks Cache-Control: no-store"

echo "smoke OK: plugin active, ${TABLE} present, consent POST stored, decider inlined, geo no-store"
