# FastyBird IoT HomeKit connector

***

[![Build Status](https://badgen.net/github/checks/FastyBird/homekit-connector/master?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/homekit-connector?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/blob/master/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/homekit-connector?cache=300&style=flat-square)](https://coveralls.io/r/FastyBird/homekit-connector)

![PHP](https://badgen.net/packagist/php/FastyBird/homekit-connector?cache=300&style=flat-square)
[![PHP latest stable](https://badgen.net/packagist/v/FastyBird/homekit-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHP downloads total](https://badgen.net/packagist/dt/FastyBird/homekit-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHPStan](https://img.shields.io/badge/phpstan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

***

## What is FastyBird IoT HomeKit connector?

HomeKit connector is a combined [FastyBird IoT](https://www.fastybird.com) extension which is integrating [HomeKit Accessory Protoccol](https://www.homekit.org) into [FastyBird](https://www.fastybird.com) IoT system

HomeKit connector is an [Apache2 licensed](http://www.apache.org/licenses/LICENSE-2.0) distributed extension, developed
in [PHP](https://www.php.net) with [Nette framework](https://nette.org).

### Features:

- Preconfigured Apple supported devices
- Integrated mapping multiple devices into one HomeKit device
- HomeKit connector management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- HomeKit device management for [FastyBird IoT](https://www.fastybird.com) [devices module](https://github.com/FastyBird/devices-module)
- [{JSON:API}](https://jsonapi.org/) schemas for full api access

## Requirements

HomeKit connector is tested against PHP 8.1
and [ReactPHP Socket](https://github.com/reactphp/socket) 1.11 async, streaming plaintext TCP/IP and secure TLS socket server and client connections
and [Nette framework](https://nette.org/en/) 3.0 PHP framework for real programmers

## Installation

### Manual installation

The best way to install **fastybird/homekit-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/homekit-connector
```

### Marketplace installation

You could install this connector in your [FastyBird IoT](https://www.fastybird.com) application under marketplace
section

## Documentation

Learn how to connect your devices from FastyBird IoT system with Apple HomeKit
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
