# FastyBird IoT HomeKit connector

[![Build Status](https://badgen.net/github/checks/FastyBird/homekit-connector/master?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/homekit-connector?cache=300&style=flat-square)](https://github.com/FastyBird/homekit-connector/blob/master/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/homekit-connector?cache=300&style=flat-square)](https://coveralls.io/r/FastyBird/homekit-connector)
[![Mutation testing](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FFastyBird%2Fhomekit-connector%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/FastyBird/homekit-connector/main)

![PHP](https://badgen.net/packagist/php/FastyBird/homekit-connector?cache=300&style=flat-square)
[![PHP latest stable](https://badgen.net/packagist/v/FastyBird/homekit-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHP downloads total](https://badgen.net/packagist/dt/FastyBird/homekit-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHPStan](https://img.shields.io/badge/phpstan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

***

## What is HomeKit connector?

HomeKit connector is extension for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem
which is integrating [HomeKit Accessory Protoccol](https://www.homekit.org).

HomeKit connector is an [Apache2 licensed](http://www.apache.org/licenses/LICENSE-2.0) distributed extension, developed
in [PHP](https://www.php.net) on top of the [Nette framework](https://nette.org) and [Symfony framework](https://symfony.com).

### Features:

- Preconfigured Apple supported devices
- Integrated mapping multiple devices into one HomeKit device
- HomeKit connector management for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module)
- HomeKit device management for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module)
- [{JSON:API}](https://jsonapi.org/) schemas for full api access

## Requirements

HomeKit connector is tested against PHP 8.1 and require installed [GNU Multiple Precision](https://www.php.net/manual/en/book.gmp.php),
[Process Control](https://www.php.net/manual/en/book.pcntl.php), [Sockets](https://www.php.net/manual/en/book.sockets.php) and
[Sodium](https://www.php.net/manual/en/book.sodium.php) PHP extensions.

## Installation

### Manual installation

The best way to install **fastybird/homekit-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/homekit-connector
```

### Marketplace installation [WIP]

You could install this connector in your [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
application under marketplace section

## Documentation

Learn how to connect your devices from [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
system with [Apple HomeKit]((https://www.homekit.org)) in [documentation](https://github.com/FastyBird/homekit-connector/blob/master/.docs/en/index.md).

## Feedback

Use the [issue tracker](https://github.com/FastyBird/fastybird/issues) for bugs
or [mail](mailto:code@fastybird.com) or [Tweet](https://twitter.com/fastybird) us for any idea that can improve the
project.

Thank you for testing, reporting and contributing.

## Changelog

For release info check [release page](https://github.com/FastyBird/fastybird/releases)

## Contribute

The sources of this package are contained in the [FastyBird monorepo](https://github.com/FastyBird/fastybird). We welcome contributions for this package on [FastyBird/fastybird](https://github.com/FastyBird/).

## Maintainers

<table>
	<tbody>
		<tr>
			<td align="center">
				<a href="https://github.com/akadlec">
					<img alt="akadlec" width="80" height="80" src="https://avatars3.githubusercontent.com/u/1866672?s=460&amp;v=4" />
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
