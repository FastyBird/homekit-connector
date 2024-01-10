<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/repo_title.png?raw=true" alt="FastyBird"/>
</p>

> [!IMPORTANT]
This documentation is meant to be used by developers or users which has basic programming skills. If you are regular user
please use FastyBird IoT documentation which is available on [docs.fastybird.com](https://docs.fastybird.com).

The [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) HomeKit Connector is
an extension of the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
ecosystem that enables effortless integration  with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit). It provides
users with a simple and user-friendly interface to connect FastyBird devices with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit),
allowing easy control of the devices from the Apple Home app. This makes managing and monitoring your devices hassle-free.

# About Connector

This connector has some services divided into namespaces. All services are preconfigured and imported into application
container automatically.

```
\FastyBird\Connector\HomeKit
  \Clients - Services which handle communication between devices and HomeKit
  \Commands - Services used for user console interface
  \Controllers - HTTP HomeKit api services for handling incomming requests
  \Entities - All entities used by connector
  \Helpers - Useful helpers for reading values, bulding entities etc.
  \Queue - Services related to connector internal communication
  \Middleware - HTPP routing middleware services
  \Protocol - HomeKit communication protocol services
  \Schemas - {JSON:API} schemas mapping for API requests
  \Servers - HTTP server related services
  \Translations - Connector translations
  \Writers - Services for handling request from other services
```

All services, helpers, etc. are written to be self-descriptive :wink:.

> [!TIP]
To better understand what some parts of the connector meant to be used for, please refer to the [Naming Convention](Naming-Convention) page.

## Using Connector

The connector is ready to be used as is. Has configured all services in application container and there is no need to develop
some other services or bridges.

> [!TIP]
Find fundamental details regarding the installation and configuration of this connector on the [Configuration](Configuration) page.

This connector is equipped with interactive console. With this console commands you could manage almost all connector features.

* **fb:homekit-connector:install**: is used for connector installation and configuration. With interactive menu you could manage connector and devices.
* **fb:homekit-connector:execute**: is used for connector execution. It is simple command that will trigger all services which are related to communication with HomeKit devices and services with other [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem services like state storage, or user interface communication.

Each console command could be triggered like this :nerd_face:

```shell
php bin/fb-console fb:homekit-connector:install
```

> [!NOTE]
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

# Known Issues and Limitations

## Supported devices count

If you need to handle more devices than the maximum limit of 150 devices that can be handled by the connector and
Apple HomeKit, you will need to create additional connectors using different ports.

## Devices update

It is recommended to make configuration changes in smaller increments to avoid potential failure of device refresh
when making multiple changes.
