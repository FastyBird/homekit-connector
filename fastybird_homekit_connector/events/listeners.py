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
HomeKit connector events module listeners
"""

# Python base dependencies
import logging
from enum import Enum
from typing import Optional, Union

# Library dependencies
from fastybird_devices_module.managers.state import ChannelPropertiesStatesManager
from fastybird_devices_module.repositories.channel import ChannelPropertiesRepository
from fastybird_devices_module.repositories.state import (
    ChannelPropertiesStatesRepository,
)
from fastybird_devices_module.utils import normalize_value
from fastybird_exchange.publisher import Publisher
from fastybird_metadata.routing import RoutingKey
from fastybird_metadata.types import ModuleSource, PropertyAction
from kink import inject
from whistle import Event, EventDispatcher

# Library libs
from fastybird_homekit_connector.events.events import CharacteristicCommandEvent
from fastybird_homekit_connector.logger import Logger
from fastybird_homekit_connector.registry.model import CharacteristicsRegistry


@inject(
    bind={
        "publisher": Publisher,
    }
)
class EventsListener:  # pylint: disable=too-many-instance-attributes
    """
    Events listener

    @package        FastyBird:HomeKitConnector!
    @module         events/listeners

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __characteristics_registry: CharacteristicsRegistry

    __channels_properties_repository: ChannelPropertiesRepository
    __channels_properties_states_repository: ChannelPropertiesStatesRepository
    __channels_properties_states_manager: ChannelPropertiesStatesManager

    __event_dispatcher: EventDispatcher

    __logger: Union[Logger, logging.Logger]

    __publisher: Optional[Publisher] = None

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        characteristics_registry: CharacteristicsRegistry,
        event_dispatcher: EventDispatcher,
        channels_properties_repository: ChannelPropertiesRepository,
        channels_properties_states_repository: ChannelPropertiesStatesRepository,
        channels_properties_states_manager: ChannelPropertiesStatesManager,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
        publisher: Optional[Publisher] = None,
    ) -> None:
        self.__characteristics_registry = characteristics_registry

        self.__channels_properties_repository = channels_properties_repository
        self.__channels_properties_states_repository = channels_properties_states_repository
        self.__channels_properties_states_manager = channels_properties_states_manager

        self.__event_dispatcher = event_dispatcher

        self.__logger = logger

        self.__publisher = publisher

    # -----------------------------------------------------------------------------

    def open(self) -> None:
        """Open all listeners callbacks"""
        self.__event_dispatcher.add_listener(
            event_id=CharacteristicCommandEvent.EVENT_NAME,
            listener=self.__handle_characteristic_command_event,
        )

    # -----------------------------------------------------------------------------

    def close(self) -> None:
        """Close all listeners registrations"""
        self.__event_dispatcher.remove_listener(
            event_id=CharacteristicCommandEvent.EVENT_NAME,
            listener=self.__handle_characteristic_command_event,
        )

    # -----------------------------------------------------------------------------

    def __handle_characteristic_command_event(self, event: Event) -> None:
        if not isinstance(event, CharacteristicCommandEvent):
            return

        if self.__publisher is None:
            return

        characteristic = self.__characteristics_registry.get_by_id(characteristic_id=event.id)

        if characteristic is None:
            return

        channel_property = self.__channels_properties_repository.get_by_id(property_id=event.id)

        if channel_property is None or channel_property.parent is None:
            return

        property_state = self.__channels_properties_states_repository.get_by_id(property_id=channel_property.parent.id)

        expected_value = normalize_value(
            data_type=channel_property.data_type,
            value=event.expected_value,
            value_format=channel_property.format,
            value_invalid=channel_property.invalid,
        )

        if property_state is None:
            self.__channels_properties_states_manager.create(
                channel_property=channel_property.parent,
                data={
                    "expectedValue": expected_value,
                    "pending": True,
                },
            )

        else:
            self.__channels_properties_states_manager.update(
                channel_property=channel_property.parent,
                state=property_state,
                data={
                    "expectedValue": expected_value,
                    "pending": True,
                },
            )

        self.__publisher.publish(
            source=ModuleSource.DEVICES_MODULE,
            routing_key=RoutingKey.CHANNEL_PROPERTY_ACTION,
            data={
                "action": PropertyAction.SET.value,
                "device": channel_property.channel.device.id.__str__(),
                "channel": channel_property.channel.id.__str__(),
                "property": channel_property.id.__str__(),
                "expected_value": expected_value.value if isinstance(expected_value, Enum) else expected_value,
            },
        )

        self.__logger.debug(
            "Sending channel property value command",
            extra={
                "device": {
                    "id": channel_property.channel.device.id.__str__(),
                },
                "channel": {
                    "id": channel_property.channel.id.__str__(),
                },
                "property": {
                    "id": channel_property.id.__str__(),
                    "parent": channel_property.parent.id.__str__(),
                },
            },
        )
