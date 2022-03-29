# FastyBird IoT HomeKit connector

[![Build Status](https://badgen.net/github/checks/FastyBird/homekit-connector/master?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/homekit-connector?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/blob/master/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/homekit-connector?cache=300&style=flat-square)](https://coveralls.io/r/FastyBird/homekit-connector)

![PHP](https://badgen.net/packagist/php/FastyBird/homekit-connector?cache=300&style=flat-square)
[![PHP latest stable](https://badgen.net/packagist/v/FastyBird/homekit-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHP downloads total](https://badgen.net/packagist/dt/FastyBird/homekit-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHPStan](https://img.shields.io/badge/phpstan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

![Python](https://badgen.net/pypi/python/fastybird-homekit-connector?cache=300&style=flat-square)
[![Python latest stable](https://badgen.net/pypi/v/fastybird-homekit-connector?cache=300&style=flat-square)](https://pypi.org/project/fastybird-homekit-connector/)
[![Python downloads month](https://img.shields.io/pypi/dm/fastybird-homekit-connector?cache=300&style=flat-square)](https://pypi.org/project/fastybird-homekit-connector/)
[![Black](https://img.shields.io/badge/black-enabled-brightgreen.svg?style=flat-square)](https://github.com/psf/black)
[![MyPy](https://img.shields.io/badge/mypy-enabled-brightgreen.svg?style=flat-square)](http://mypy-lang.org)

## What is FastyBird IoT HomeKit connector?

HomeKit connector is a combined [FastyBird IoT](https://www.fastybird.com) extension which is integrating [HomeKit](https://www.homekit.org) devices into [FastyBird](https://www.fastybird.com) IoT system

[FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) HomeKit connector is
an [Apache2 licensed](http://www.apache.org/licenses/LICENSE-2.0) distributed extension, developed
in [PHP](https://www.php.net) with [Nette framework](https://nette.org) and in [Python](https://python.org).

### Features:

- HomeKit connector management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- HomeKit device management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- [{JSON:API}](https://jsonapi.org/) schemas for full api access
- Integrated connector Python worker

## Requirements

PHP part of [FastyBird](https://www.fastybird.com) HomeKit connector is tested against PHP 7.4
and [ReactPHP http](https://github.com/reactphp/http) 0.8 event-driven, streaming plaintext HTTP server
and [Nette framework](https://nette.org/en/) 3.0 PHP framework for real programmers

Python part of [FastyBird](https://www.fastybird.com) HomeKit connector is tested against [Python 3.7](http://python.org)

## Installation

### Manual installation

#### Application backend in PHP

The best way to install **fastybird/homekit-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/homekit-connector
```

#### Application workers in Python

The best way to install **fastybird-homekit-connector** is using [Pip](https://pip.pypa.io/en/stable/):

```sh
pip install fastybird-homekit-connector
```

### Marketplace installation

You could install this connector in your [FastyBird IoT](https://www.fastybird.com) application under marketplace section

## Documentation

Learn how to connect HomeKit devices to FastyBird IoT system
in [documentation](https://github.com/FastyBird/homekit-connector/blob/master/.docs/en/index.md).

## Feedback

Use the [issue tracker](https://github.com/FastyBird/homekit-connector/issues) for bugs
or [mail](mailto:code@fastybird.com) or [Tweet](https://twitter.com/fastybird) us for any idea that can improve the
project.

Thank you for testing, reporting and contributing.

## Changelog

For release info check [release page](https://github.com/FastyBird/homekit-connector/releases)

## Maintainers

<table>
	<tbody>
		<tr>
			<td align="center">
				<a href="https://github.com/akadlec">
					<img width="80" height="80" src="https://avatars3.githubusercontent.com/u/1866672?s=460&amp;v=4">
				</a>
				<br>
				<a href="https://github.com/akadlec">Adam Kadlec</a>
			</td>
		</tr>
	</tbody>
</table>

***
Homepage [https://www.fastybird.com](https://www.fastybird.com) and
repository [https://github.com/fastybird/homekit-connector](https://github.com/fastybird/homekit-connector).
