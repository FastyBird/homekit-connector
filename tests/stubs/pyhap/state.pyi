class State(object):
    @property
    def port(self) -> int: ...

    @property
    def pincode(self) -> bytearray: ...
