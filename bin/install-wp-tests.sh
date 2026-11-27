#!/usr/bin/env bash
# Install WordPress test suite against local MySQL (database iworks_tests_sm).
# Usage: bin/install-wp-tests.sh [db_name] [db_user] [db_pass] [db_host] [wp_version]
set -euo pipefail

DB_NAME="${1:-iworks_tests_sm}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"
DB_HOST="${4:-127.0.0.1}"
WP_VERSION="${5:-latest}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="${ROOT}/tests/wordpress"
WP_CORE="${TMP}/wordpress"
WP_TESTS="${TMP}/wordpress-tests-lib"

mkdir -p "${TMP}"

download() {
	local url="$1"
	local out="$2"
	if command -v curl >/dev/null 2>&1; then
		curl -sSL "${url}" -o "${out}"
	else
		wget -q -O "${out}" "${url}"
	fi
}

if [[ ! -d "${WP_CORE}/wp-includes" ]]; then
	echo "Downloading WordPress ${WP_VERSION}…"
	mkdir -p "${WP_CORE}"
	if [[ "${WP_VERSION}" == "latest" ]]; then
		download "https://wordpress.org/latest.tar.gz" "${TMP}/wordpress.tar.gz"
	else
		download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" "${TMP}/wordpress.tar.gz"
	fi
	tar -xzf "${TMP}/wordpress.tar.gz" -C "${TMP}"
	rm -f "${TMP}/wordpress.tar.gz"
fi

if [[ ! -d "${WP_TESTS}/includes" ]]; then
	echo "Downloading WordPress test suite…"
	# Match the test library to the WordPress version under test.
	if [[ "${WP_VERSION}" == "latest" ]]; then
		TESTS_REF="trunk"
	else
		TESTS_REF="tags/${WP_VERSION}"
	fi
	svn co --quiet "https://develop.svn.wordpress.org/${TESTS_REF}/tests/phpunit/includes/" "${WP_TESTS}/includes"
	svn co --quiet "https://develop.svn.wordpress.org/${TESTS_REF}/tests/phpunit/data/" "${WP_TESTS}/data"
	download "https://develop.svn.wordpress.org/${TESTS_REF}/wp-tests-config-sample.php" "${WP_TESTS}/wp-tests-config-sample.php"
fi

CFG="${WP_TESTS}/wp-tests-config.php"
if [[ ! -f "${CFG}" ]]; then
	cp "${WP_TESTS}/wp-tests-config-sample.php" "${CFG}"
	perl -i -pe "s|dirname\\( __FILE__ \\) \\. '/src/'|'${WP_CORE}/'|" "${CFG}"
	perl -i -pe "s|youremptytestdbnamehere|${DB_NAME}|" "${CFG}"
	perl -i -pe "s|yourusernamehere|${DB_USER}|" "${CFG}"
	perl -i -pe "s|yourpasswordhere|${DB_PASS}|" "${CFG}"
	perl -i -pe "s|localhost|${DB_HOST}|" "${CFG}"
fi

echo "Ensuring database ${DB_NAME} exists…"
if [[ -z "${DB_PASS}" ]]; then
	mysqladmin create "${DB_NAME}" --user="${DB_USER}" --host="${DB_HOST}" 2>/dev/null || true
else
	mysqladmin create "${DB_NAME}" --user="${DB_USER}" --password="${DB_PASS}" --host="${DB_HOST}" 2>/dev/null || true
fi

echo "WP tests ready at ${WP_TESTS}"
