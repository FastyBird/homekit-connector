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
HomeKit connector utilities
"""

# Python base dependencies
import math
from typing import Optional


class KeyHashHelpers:
    """
    Key hash generator & parser

    @package        FastyBird:HomeKitConnector!
    @module         helpers

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    MAX_LEN: int = 6

    ALPHABET: str = "bcdfghjklmnpqrstvwxyz0123456789BCDFGHJKLMNPQRSTVWXYZ"

    BASE: int = len(ALPHABET)

    # -----------------------------------------------------------------------------

    @staticmethod
    def encode(number: int, max_length: Optional[int] = None) -> str:
        """Convert number to key hash"""
        pad = (KeyHashHelpers.MAX_LEN if max_length is None else max_length) - 1
        number = int(number + pow(KeyHashHelpers.BASE, pad))

        result = []
        pointer = int(math.log(number, KeyHashHelpers.BASE))

        while True:
            bcp = int(pow(KeyHashHelpers.BASE, pointer))
            position = int(number / bcp) % KeyHashHelpers.BASE
            result.append(KeyHashHelpers.ALPHABET[position : position + 1])
            number = number - (position * bcp)
            pointer -= 1

            if pointer < 0:
                break

        return "".join(reversed(result))

    # -----------------------------------------------------------------------------

    @staticmethod
    def decode(string: str, max_length: Optional[int] = None) -> int:
        """Convert key hash to number"""
        string = "".join(reversed(string))
        result = 0
        length = len(string) - 1
        pointer = 0

        while True:
            bcpow = int(pow(KeyHashHelpers.BASE, length - pointer))
            result = result + KeyHashHelpers.ALPHABET.index(string[pointer : pointer + 1]) * bcpow
            pointer += 1
            if pointer > length:
                break

        pad = (KeyHashHelpers.MAX_LEN if max_length is None else max_length) - 1
        result = int(result - pow(KeyHashHelpers.BASE, pad))

        return int(result)
