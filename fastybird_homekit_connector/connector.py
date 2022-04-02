#!/usr/bin/python3

#     Copyright 2022. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

"""
HomeKit connector module
"""

# Python base dependencies
import logging
import uuid
from asyncio import AbstractEventLoop
from os import path
from typing import Dict, Optional, Union

# Library dependencies
from fastybird_devices_module.connectors.connector import IConnector
from fastybird_devices_module.entities.channel import (
    ChannelControlEntity,
    ChannelEntity,
    ChannelPropertyEntity, ChannelDynamicPropertyEntity,
)
from fastybird_devices_module.entities.connector import ConnectorControlEntity
from fastybird_devices_module.entities.device import (
    DeviceControlEntity,
    DeviceEntity,
    DevicePropertyEntity,
)
from fastybird_devices_module.repositories.device import DevicesRepository
from fastybird_devices_module.repositories.state import ChannelPropertiesStatesRepository
from fastybird_metadata.types import ControlAction, SwitchPayload
from kink import inject
from pyhap.accessory import Bridge  # type: ignore[import]
from pyhap.accessory_driver import AccessoryDriver  # type: ignore[import]

# Library libs
from fastybird_homekit_connector.entities import HomeKitDeviceEntity
from fastybird_homekit_connector.logger import Logger
from fastybird_homekit_connector.registry.model import AccessoriesRegistry, ServicesRegistry, CharacteristicsRegistry


@inject(
    alias=IConnector,
    bind={
        "loop": AbstractEventLoop,
    }
)
class HomeKitConnector(IConnector):  # pylint: disable=too-many-public-methods,too-many-instance-attributes
    """
    HomeKit connector service

    @package        FastyBird:HomeKitConnector!
    @module         connector

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __connector_id: uuid.UUID

    __accessories_registry: AccessoriesRegistry
    __services_registry: ServicesRegistry
    __characteristics_registry: CharacteristicsRegistry

    __driver: AccessoryDriver  # type: ignore[no-any-unimported]
    __bridge: Bridge  # type: ignore[no-any-unimported]

    __devices_repository: DevicesRepository

    __channel_property_state_repository: ChannelPropertiesStatesRepository

    __loop: Optional[AbstractEventLoop] = None

    __logger: Union[Logger, logging.Logger]

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        connector_id: uuid.UUID,
        devices_repository: DevicesRepository,
        accessories_registry: AccessoriesRegistry,
        services_registry: ServicesRegistry,
        characteristics_registry: CharacteristicsRegistry,
        channel_property_state_repository: ChannelPropertiesStatesRepository,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
        loop: Optional[AbstractEventLoop] = None,
    ) -> None:
        self.__connector_id = connector_id

        self.__devices_repository = devices_repository

        self.__accessories_registry = accessories_registry
        self.__services_registry = services_registry
        self.__characteristics_registry = characteristics_registry

        self.__channel_property_state_repository = channel_property_state_repository

        self.__loop = loop

        # Start the accessory on port 51826 & save the accessory.state to our custom path
        self.__driver = AccessoryDriver(
            port=51826,
            persist_file=(
                "/var/miniserver-gateway/miniserver_gateway/homekit.accessory.state".replace("/", path.sep)
            ),
            loop=self.__loop,
            pincode=bytearray(str("426-42-409").encode("ascii")),
        )

        self.__bridge = Bridge(driver=self.__driver, display_name="Bridge")
        self.__bridge.set_info_service(
            firmware_revision="0.0.1",
            manufacturer="FastyBird",
            model="rPI gateway",
            serial_number=connector_id.__str__(),
        )

        self.__driver.add_accessory(accessory=self.__bridge)

        self.__logger = logger

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Connector identifier"""
        return self.__connector_id

    # -----------------------------------------------------------------------------

    def initialize(self, settings: Optional[Dict] = None) -> None:
        """Set connector to initial state"""
        for device in self.__devices_repository.get_all_by_connector(connector_id=self.__connector_id):
            self.initialize_device(device=device)

    # -----------------------------------------------------------------------------

    def initialize_device(self, device: HomeKitDeviceEntity) -> None:
        """Initialize device in connector registry"""
        self.__accessories_registry.append(
            accessory_id=device.id,
            accessory_enabled=device.enabled,
            accessory_name=device.name if device.name is not None else device.identifier,
            driver=self.__driver,
        )

        for channel in device.channels:
            self.initialize_device_channel(device=device, channel=channel)

    # -----------------------------------------------------------------------------

    def remove_device(self, device_id: uuid.UUID) -> None:
        """Remove device from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices(self) -> None:
        """Reset devices registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_property(self, device: DeviceEntity, device_property: DevicePropertyEntity) -> None:
        """Initialize device property in connector registry"""

    # -----------------------------------------------------------------------------

    def notify_device_property(self, device: DeviceEntity, device_property: DevicePropertyEntity) -> None:
        """Notify device property was reported to connector"""

    # -----------------------------------------------------------------------------

    def remove_device_property(self, device: DeviceEntity, property_id: uuid.UUID) -> None:
        """Remove device from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_properties(self, device: DeviceEntity) -> None:
        """Reset devices properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_channel(self, device: DeviceEntity, channel: ChannelEntity) -> None:
        """Initialize device channel aka registers group in connector registry"""
        self.__services_registry.append(
            accessory_id=device.id,
            service_id=channel.id,
            service_identifier=channel.identifier,
        )

        for channel_property in channel.properties:
            self.initialize_device_channel_property(channel=channel, channel_property=channel_property)

    # -----------------------------------------------------------------------------

    def remove_device_channel(self, device: DeviceEntity, channel_id: uuid.UUID) -> None:
        """Remove device channel from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels(self, device: DeviceEntity) -> None:
        """Reset devices channels registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_channel_property(
        self,
        channel: ChannelEntity,
        channel_property: ChannelPropertyEntity,
    ) -> None:
        """Initialize device channel property aka input or output register in connector registry"""
        self.__characteristics_registry.append(
            service_id=channel.id,
            characteristic_id=channel_property.id,
            characteristic_identifier=channel_property.identifier,
            characteristic_data_type=channel_property.data_type,
            characteristic_format=channel_property.format,
            characteristic_number_of_decimals=channel_property.number_of_decimals,
            characteristic_queryable=channel_property.queryable,
            characteristic_settable=channel_property.settable,
        )

    # -----------------------------------------------------------------------------

    def notify_device_channel_property(
        self,
        channel: ChannelEntity,
        channel_property: ChannelPropertyEntity,
    ) -> None:
        """Notify device channel property was reported to connector"""
        if isinstance(channel_property, ChannelDynamicPropertyEntity):
            char = self.__characteristics_registry.get_by_id(characteristic_id=channel_property.id)

            if char is not None:
                state = self.__channel_property_state_repository.get_by_id(property_id=channel_property.id)

                if state is not None:
                    char.actual_value = state.actual_value

    # -----------------------------------------------------------------------------

    def remove_device_channel_property(self, channel: ChannelEntity, property_id: uuid.UUID) -> None:
        """Remove device channel property from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels_properties(self, channel: ChannelEntity) -> None:
        """Reset devices channels properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start connector services"""
        for accessory in self.__accessories_registry:
            for service in self.__services_registry.get_all_by_accessory(accessory_id=accessory.id):
                for characteristic in self.__characteristics_registry.get_all_for_service(service_id=service.id):
                    service.add_characteristic(characteristic)

                accessory.add_service(service)

            self.__bridge.add_accessory(acc=accessory)

        self.__driver.add_job(self.__driver.async_start())

        if self.__loop is None:
            self.__driver.loop.run_forever()

        self.__logger.info("Connector has been started")
        print(self.__driver.state.pincode.decode())

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Close all opened connections & stop connector"""
        self.__driver.add_job(self.__driver.async_stop)

        self.__logger.info("Connector has been stopped")

    # -----------------------------------------------------------------------------

    def has_unfinished_tasks(self) -> bool:
        """Check if connector has some unfinished task"""
        return bool(self.__driver.loop.is_running())

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Run connector service"""

    # -----------------------------------------------------------------------------

    def write_property(self, property_item: Union[DevicePropertyEntity, ChannelPropertyEntity], data: Dict) -> None:
        """Write device or channel property value to device"""

    # -----------------------------------------------------------------------------

    def write_control(
        self,
        control_item: Union[ConnectorControlEntity, DeviceControlEntity, ChannelControlEntity],
        data: Optional[Dict],
        action: ControlAction,
    ) -> None:
        """Write connector control action"""
