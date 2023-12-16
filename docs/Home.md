<p align="center">
	<img src="https://github.com/fastybird/.github/blob/main/assets/repo_title.png?raw=true" alt="FastyBird"/>
</p>

The [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) HomeKit Connector is
an extension of the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
ecosystem that enables effortless integration  with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit). It provides
users with a simple and user-friendly interface to connect FastyBird devices with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit),
allowing easy control of the devices from the Apple Home app. This makes managing and monitoring your devices hassle-free.

# Naming Convention

The connector uses the following naming convention for its entities:

## Connector

A connector is an entity that manages communication with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) system.
It needs to be configured for a specific interface.

## Device

A device is an entity that represents a virtual [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) device.

## Device Service

A service is an entity that refers to a specific functionality or feature that a device provides. For example,
a thermostat device might provide a "temperature control" service and a "humidity control" service.

## Service Characteristic

A characteristic is an entity that refers to the individual attribute of a service that can be queried or manipulated.
Characteristic represent specific data point that describe the state of a device or allow control over it.
Examples of characteristic include temperature, humidity, on/off status, and brightness level.

# Configuration

To integrate [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) ecosystem devices
with [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit), you will need to configure at least one connector.
The connector can be configured using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things)
user interface or through the console.

## Configuring the Connectors, Devices, Services and Characteristic through the Console

To configure the connector through the console, run the following command:

```shell
php bin/fb-console fb:homekit-connector:install
```

> **NOTE:**
The path to the console command may vary depending on your FastyBird application distribution. For more information, refer to the FastyBird documentation.

```shell
HomeKit connector - installer
=============================

 ! [NOTE] This action will create|update|delete connector configuration

 What would you like to do? [Nothing]:
  [0] Create connector
  [1] Edit connector
  [2] Delete connector
  [3] Manage connector
  [4] List connectors
  [5] Nothing
 > 0
```

### Create connector

If you choose to create a new connector, you will be asked to provide basic connector configuration:

```shell
 Provide connector identifier:
 > my-homekit-server
```

```shell
 Provide connector name:
 > My HomeKit server
```

Now you can provide the communication port for the connector. If you don't provide a value, the default port will be used.
If the port is not available, you can choose a different port.

```shell
 Provide server port [51827]:
 > 
```

After providing the necessary information, your new [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) connector will be ready for use.

```shell
 [OK] Connector "My HomeKit server" was successfully created.
 ```

### Create device

After new connector is created you will be asked if you want to create new device:

```shell
 Would you like to configure connector device(s)? (yes/no) [yes]:
 > 
```

Or you could choose to manage connector devices from the main menu.

Now you will be asked to provide some device details:

```shell
 Provide device identifier:
 > living-room-thermostat
```

```shell
 Provide device name:
 > Living room thermostat
```

You are now required to select a device category, which will determine the specific services and characteristics of the device.
If you are unable to find the appropriate category, you can choose the `Other` option.

```shell
 Please select device category [Other]:
  [0 ] Air Conditioner
  [1 ] Air Purifier
  [2 ] Alarm System
  [3 ] Camera
  [4 ] Dehumidifier
  [5 ] Door
  [6 ] Door Lock
  [7 ] Fan
  [8 ] Faucet
  [9 ] Garage Door Opener
  [10] Heater
  [11] Humidifier
  [12] Light Bulb
  [13] Other
  [14] Outlet
  [15] Programmable Switch
  [16] Range Extender
  [17] Remote Controller
  [18] Sensor
  [19] Shower Head
  [20] Speaker
  [21] Sprinkler
  [22] Switch
  [23] Television
  [24] Thermostat
  [25] Video Door Bell
  [26] Window
  [27] Window Covering
 > 24
```

If there are no errors, you will receive a success message.

```shell
 [OK] Device "Living room thermostat" was successfully created.
```

Each device have to have defined services. So in next steps you will be prompted to configure device's services.

> **NOTE:**
The list of items may vary depending on the device category.

```shell
 What type of device service you would like to add? [BatteryService]:
  [0] BatteryService
  [1] HeaterCooler
  [2] HumiditySensor
  [3] MotionSensor
  [4] OccupancySensor
  [5] TemperatureSensor
  [6] Thermostat
 > 6
```

Let's create Thermostat service:

```shell
 What type of service characteristic you would like to add? [CurrentHeatingCoolingState]:
  [0] CurrentHeatingCoolingState
  [1] TargetHeatingCoolingState
  [2] CurrentTemperature
  [3] TargetTemperature
  [4] TemperatureDisplayUnits
 > 4
```

These characteristics are mandatory and must be configured.

You have two options. Connect characteristics with FastyBird device or configure it as static value.
Let's try static configuration value:

```shell
 Connect characteristics with device? (yes/no) [yes]:
 > n
```

Some characteristics have a defined set of allowed values, while others accept values from a range. Therefore, the next
question will vary depending on the selected characteristic.

```shell
 Please select characteristic value:
  [0] Celsius
  [1] Fahrenheit
 > 0
```

And if you choose to connect characteristic with device:

```shell
 Connect characteristics with device? (yes/no) [yes]:
 > y
```

```shell
 Select device for mapping:
  [0] thermometer-living-room [Living room thermometer]
  [1] floor-heating-living-room [Living room floor heating]
  [2] window-sensor-living-room [Living room window sensor]
 > 0
```

Now you have to choose type of the device property:

```shell
 What type of property you want to map? [Channel property]:
  [0] Device property
  [1] Channel property
 > 1
```

And select device channel:

```shell
 Select device channel for mapping:
  [0] temperature-humidity
 > 0
```

And channel's property:

```shell
 Select channel property for mapping:
  [0] temperature
  [1] humidity
 > 0
```

After all required characteristics are configured you will be prompted with question if you want to configure
optional characteristics.

```shell
 What type of service characteristic you would like to add? (optional) [CurrentRelativeHumidity]:
  [0] CurrentRelativeHumidity
  [1] TargetRelativeHumidity
  [2] CoolingThresholdTemperature
  [3] HeatingThresholdTemperature
  [4] Name
  [5] None
```

The process is same as previous steps.

If there are no errors, you will receive a success message.

```shell
 [OK] Service "thermostat_1" was successfully created.
```

If you want to configure more device services you could repeat whole process:

```shell
 Would you like to configure another device service? (yes/no) [no]:
 > 
```

You could configure as many devices as you want.

### Connectors, Devices, Services and Characteristics management

With this console command you could manage all your connectors, their devices and services and characteristics. Just use the main menu to navigate to proper action.

## Configuring the Connector with the FastyBird User Interface

You can also configure the [Apple HomeKit](https://en.wikipedia.org/wiki/HomeKit) connector using the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) user interface. For more information on how to do this,
please refer to the [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) documentation.

# Known Issues and Limitations

## Supported devices count

If you need to handle more devices than the maximum limit of 150 devices that can be handled by the connector and
Apple HomeKit, you will need to create additional connectors using different ports.

## Devices update

It is recommended to make configuration changes in smaller increments to avoid potential failure of device refresh
when making multiple changes.
