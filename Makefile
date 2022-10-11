.PHONY: qa lint cs csf phpstan tests coverage

all:
	@$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$' | xargs

vendor: composer.json composer.lock
	composer install

qa: lint phpstan cs

lint: vendor
	vendor/bin/parallel-lint --exclude .git --exclude vendor src tests

cs: vendor
	vendor/bin/phpcs --standard=phpcs.xml src tests

csf: vendor
	vendor/bin/phpcbf --standard=phpcs.xml src tests

phpstan: vendor
	vendor/bin/phpstan analyse -c phpstan.neon src

tests: vendor
	vendor/bin/tester -s -p php --colors 1 -C tests/cases

coverage: vendor
	vendor/bin/tester -s -p php --colors 1 -C --coverage ./coverage.xml --coverage-src ./src tests/cases
