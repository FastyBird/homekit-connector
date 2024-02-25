<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/repo_title.png?raw=true" alt="FastyBird"/>
</p>

# FastyBird IoT HomeKit connector

[![Build Status](https://img.shields.io/github/actions/workflow/status/FastyBird/homekit-connector/ci.yaml?style=flat-square)](https://github.com/FastyBird/homekit-connector/actions)
[![Licence](https://img.shields.io/github/license/FastyBird/homekit-connector?style=flat-square)](https://github.com/FastyBird/homekit-connector/blob/main/LICENSE.md)
[![Code coverage](https://img.shields.io/coverallsCoverage/github/FastyBird/homekit-connector?style=flat-square)](https://coveralls.io/r/FastyBird/homekit-connector)
[![Mutation testing](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FFastyBird%2Fhomekit-connector%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/FastyBird/homekit-connector/main)

![PHP](https://badgen.net/packagist/php/FastyBird/homekit-connector?cache=300&style=flat-square)
[![Latest stable](https://badgen.net/packagist/v/FastyBird/homekit-connector/latest?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![Downloads total](https://badgen.net/packagist/dt/FastyBird/homekit-connector?cache=300&style=flat-square)](https://packagist.org/packages/FastyBird/homekit-connector)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

***

## What is HomeKit connector?

HomeKit connector is extension for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem
which is integrating [HomeKit Accessory Protoccol](https://www.homekit.org) and allows you integrated devices which do not support HomeKit
natively into your [Apple Home](https://www.apple.com/home-app/) application

### Features:

- Multiple bridges support
- Ability to map multiple devices into a single HomeKit device
- Bidirectional communication with Apple Home app
- Integration with the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module) for easy management and monitoring of Modbus devices
- [{JSON:API}](https://jsonapi.org/) schemas for full API access, providing a standardized and consistent way for developers to access and manipulate HomeKit device data
- Regular updates with new features and bug fixes, ensuring that the HomeKit Connector is always up-to-date and reliable.

HomeKit Connector is a distributed extension that is developed in [PHP](https://www.php.net), built on the [Nette](https://nette.org) and [Symfony](https://symfony.com) frameworks,
and is licensed under [Apache2](http://www.apache.org/licenses/LICENSE-2.0).

## Requirements

HomeKit connector is tested against PHP 8.2 and require installed [GNU Multiple Precision](https://www.php.net/manual/en/book.gmp.php),
[Process Control](https://www.php.net/manual/en/book.pcntl.php), [Sockets](https://www.php.net/manual/en/book.sockets.php) and
[Sodium](https://www.php.net/manual/en/book.sodium.php) PHP extensions.

## Installation

This extension is part of the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem and is installed by default.
In case you want to create you own distribution of [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem you could install this extension with  [Composer](http://getcomposer.org/):

```sh
composer require fastybird/homekit-connector
```

## Documentation

:book: Learn how to connect your devices from [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
system with [Apple HomeKit]((https://www.homekit.org)) in [documentation](https://github.com/FastyBird/modbus-connector/wiki).

# FastyBird

<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/fastybird_row.svg?raw=true" alt="FastyBird"/>
</p>

FastyBird is an Open Source IOT solution built from decoupled components with powerful API and the highest quality code. Read more on [fastybird.com.com](https://www.fastybird.com).

## Documentation

:book: Documentation is available on [docs.fastybird.com](https://docs.fastybird.com).

## Contributing

The sources of this package are contained in the [FastyBird monorepo](https://github.com/FastyBird/fastybird). We welcome
contributions for this package on [FastyBird/fastybird](https://github.com/FastyBird/).

## Feedback

Use the [issue tracker](https://github.com/FastyBird/fastybird/issues) for bugs reporting or send an [mail](mailto:code@fastybird.com)
to us or you could reach us on [X newtwork](https://x.com/fastybird) for any idea that can improve the project.

Thank you for testing, reporting and contributing.

## Changelog

For release info check [release page](https://github.com/FastyBird/fastybird/releases).

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
