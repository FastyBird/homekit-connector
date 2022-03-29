.PHONY: php_qa php_lint php_cs php_csf phpstan php_tests php_coverage py_qa py_tests py_coverage

all:
	@$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$' | xargs

vendor: composer.json composer.lock
	composer install

php_qa: php_lint phpstan php_cs

php_lint: vendor
	vendor/bin/linter src tests

php_cs: vendor
	vendor/bin/codesniffer src tests

php_csf: vendor
	vendor/bin/codefixer src tests

phpstan: vendor
	vendor/bin/phpstan analyse -c phpstan.neon src

php_tests: vendor
	vendor/bin/tester -s -p php --colors 1 -C tests/cases

php_coverage: vendor
	vendor/bin/tester -s -p php --colors 1 -C --coverage ./coverage.xml --coverage-src ./src tests/cases

pylint:
	python -m pip install pylint

mypy:
	python -m pip install mypy

black:
	python -m pip install black

isort:
	python -m pip install isort

py_qa: py_cs py_types py_isort py_black

py_cs: pylint
	pylint **/*.py

py_types: mypy
	mypy **/*.py

py_isort: isort
	isort **/*.py --check

py_black: black
	black **/*.py --check

py_tests:
	python -m unittest

py_coverage:
	coverage run --source=fastybird_homekit_connector -m unittest
