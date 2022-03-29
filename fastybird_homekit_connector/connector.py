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
from os import path
from typing import Dict, Optional, Union

# Library dependencies
from fastybird_devices_module.connectors.connector import IConnector
from fastybird_devices_module.entities.channel import (
    ChannelControlEntity,
    ChannelEntity,
    ChannelPropertyEntity,
)
from fastybird_devices_module.entities.connector import ConnectorControlEntity
from fastybird_devices_module.entities.device import (
    DeviceControlEntity,
    DeviceEntity,
    DevicePropertyEntity,
)
from fastybird_metadata.types import ControlAction
from kink import inject
from pyhap.accessory import Bridge  # type: ignore[import]
from pyhap.accessory_driver import AccessoryDriver  # type: ignore[import]

# Library libs
from fastybird_homekit_connector import __connector_version__
from fastybird_homekit_connector.entities import HomeKitDeviceEntity
from fastybird_homekit_connector.logger import Logger


@inject(alias=IConnector)
class HomeKitConnector(IConnector):  # pylint: disable=too-many-public-methods,too-many-instance-attributes
    """
    HomeKit connector service

    @package        FastyBird:HomeKitConnector!
    @module         connector

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __connector_id: uuid.UUID

    __driver: AccessoryDriver  # type: ignore[no-any-unimported]
    __bridge: Bridge  # type: ignore[no-any-unimported]

    __logger: Union[Logger, logging.Logger]

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        connector_id: uuid.UUID,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__connector_id = connector_id

        # Start the accessory on port 51826 & save the accessory.state to our custom path
        self.__driver = AccessoryDriver(
            port=51826,
            persist_file=(
                "/var/miniserver-gateway/miniserver_gateway/config/homekit.accessory.state".replace("/", path.sep)
            ),
        )

        self.__bridge = Bridge(driver=self.__driver, display_name="Bridge")
        self.__bridge.set_info_service(
            firmware_revision=__connector_version__,
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

    # -----------------------------------------------------------------------------

    def initialize_device(self, device: HomeKitDeviceEntity) -> None:
        """Initialize device in connector registry"""

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

    def remove_device_property(self, device: DeviceEntity, property_id: uuid.UUID) -> None:
        """Remove device from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_properties(self, device: DeviceEntity) -> None:
        """Reset devices properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_channel(self, device: DeviceEntity, channel: ChannelEntity) -> None:
        """Initialize device channel aka registers group in connector registry"""

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

    # -----------------------------------------------------------------------------

    def remove_device_channel_property(self, channel: ChannelEntity, property_id: uuid.UUID) -> None:
        """Remove device channel property from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels_properties(self, channel: ChannelEntity) -> None:
        """Reset devices channels properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start connector services"""
        self.__driver.add_job(self.__driver.async_start())
        self.__driver.loop.run_forever()

        self.__logger.info("Connector has been started")

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
