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
from typing import Optional, Union

# Library dependencies
from fastybird_devices_module.repositories.channel import ChannelPropertiesRepository
from fastybird_devices_module.utils import normalize_value
from fastybird_exchange.publisher import Publisher
from fastybird_metadata.routing import RoutingKey
from fastybird_metadata.types import ModuleSource, PropertyAction
from kink import inject
from whistle import Event, EventDispatcher

# Library libs
from fastybird_homekit_connector.events.events import CharacteristicCommandEvent
from fastybird_homekit_connector.logger import Logger


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

    __channels_properties_repository: ChannelPropertiesRepository

    __event_dispatcher: EventDispatcher

    __logger: Union[Logger, logging.Logger]

    __publisher: Optional[Publisher] = None

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        event_dispatcher: EventDispatcher,
        channels_properties_repository: ChannelPropertiesRepository,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
        publisher: Optional[Publisher] = None,
    ) -> None:
        self.__channels_properties_repository = channels_properties_repository

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

        channel_property = self.__channels_properties_repository.get_by_id(property_id=event.id)

        if channel_property is None or channel_property.parent is None:
            return

        expected_value = normalize_value(
            data_type=channel_property.data_type,
            value=event.expected_value,
            value_format=channel_property.format,
            value_invalid=channel_property.invalid,
        )

        self.__publisher.publish(
            source=ModuleSource.DEVICES_MODULE,
            routing_key=RoutingKey.CHANNEL_PROPERTY_ACTION,
            data={
                "action": PropertyAction.SET.value,
                "device": str(channel_property.channel.device.id),
                "channel": str(channel_property.channel.id),
                "property": str(channel_property.id),
                "expected_value": (
                    expected_value
                    if isinstance(expected_value, (str, int, float, bool)) or expected_value is None
                    else str(expected_value)
                ),
            },
        )

        self.__logger.debug(
            "Sending channel property value command",
            extra={
                "device": {
                    "id": str(channel_property.channel.device.id),
                },
                "channel": {
                    "id": str(channel_property.channel.id),
                },
                "property": {
                    "id": str(channel_property.id),
                    "parent": str(channel_property.parent.id),
                },
            },
        )
