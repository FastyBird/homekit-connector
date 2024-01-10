# Naming Convention

The connector uses the following naming convention for its entities:

## Connector

A connector entity in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is holding basic configuration
and is responsible for managing communication with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) system and other [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem services.

## Device

A device entity in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is an entity that represents a
virtual [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) device mapped to other device connector to [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem.

## Channel

A channel in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is mapping physical devices attributes to
a virtual [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) devices services.

## Property

A property in the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem refers to a entity which is holding configuration values or
device actual state of a device. Connector, Device and Channel entity has own Property entities.

### Connector Property

Connector related properties are used to store configuration like `communication port`, `pin code` or `setup id`. This configuration values are used
to build [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) gateway which is then connected to [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) home application.

### Device Property

Device related properties are used to store configuration like `category` or `type`. Some of them have to be configured
to be able to use this connector or to communicate with device. In case some of the mandatory property is missing,
connector will log and error.

### Channel Property

Channel related properties are used for mapping devices attributes to virtual devices characteristics.

## Device Service

A service is an entity that refers to a specific functionality or feature that a device provides. For example,
a thermostat device might provide a "temperature control" service and a "humidity control" service.

## Service Characteristic

A characteristic is an entity that refers to the individual attribute of a service that can be queried or manipulated.
Characteristic represent specific data point that describe the state of a device or allow control over it.
Examples of characteristic include temperature, humidity, on/off status, and brightness level.
