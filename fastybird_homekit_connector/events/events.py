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
HomeKit connector events module events
"""

# Python base dependencies
import uuid
from datetime import datetime
from typing import Union

# Library dependencies
from fastybird_metadata.types import ButtonPayload, SwitchPayload
from whistle import Event


class CharacteristicCommandEvent(Event):
    """
    Characteristic record command requested

    @package        FastyBird:HomeKitConnector!
    @module         events/events

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __id: uuid.UUID
    __expected_value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]

    EVENT_NAME: str = "registry.attributeRecordActualValueUpdated"

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        characteristic_id: uuid.UUID,
        expected_value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None],
    ) -> None:
        self.__id = characteristic_id
        self.__expected_value = expected_value

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Original attribute record"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def expected_value(self) -> Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Updated attribute record"""
        return self.__expected_value
