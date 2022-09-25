from cryptography.hazmat.primitives.asymmetric import ed25519, x25519
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives.ciphers.aead import ChaCha20Poly1305
from cryptography.hazmat.primitives.hashes import SHA512
from cryptography.hazmat.primitives.kdf.hkdf import HKDF
from pyhap.tlv import (
    TlvCode,
    tlv_parser
)

setup_id = bytes(bytearray([
  38,
  130,
  115,
  172,
  230,
  38,
  71,
  79,
  62,
  138,
  141,
  50,
  16,
  208,
  36,
  165,
  195,
  170,
  51,
  145,
  122,
  102,
  172,
  150,
  248,
  238,
  162,
  58,
  45,
  102,
  133,
  7,
]))

client_public_key = bytes(bytearray([
  47,
  119,
  147,
  248,
  220,
  1,
  204,
  100,
  209,
  106,
  31,
  55,
  226,
  85,
  158,
  49,
  145,
  19,
  127,
  16,
  39,
  240,
  205,
  0,
  152,
  23,
  145,
  73,
  132,
  25,
  38,
  85,
]))

SALT_VERIFY = b'Pair-Verify-Encrypt-Salt'
INFO_VERIFY = b'Pair-Verify-Encrypt-Info'
NONCE_VERIFY_M2 = b'\x00\x00\x00\x00PV-Msg02'

server_private_key = ed25519.Ed25519PrivateKey.from_private_bytes(setup_id)
server_public_key = server_private_key.public_key()

private_key = x25519.X25519PrivateKey.from_private_bytes(setup_id)
public_key = private_key.public_key()
shared_key = private_key.exchange(
    x25519.X25519PublicKey.from_public_bytes(client_public_key)
)

mac = "26:5B:B6:FE:93:40:ED".encode()

public_key_bytes = public_key.public_bytes(
    encoding=serialization.Encoding.Raw,
    format=serialization.PublicFormat.Raw,
)

material = (
        public_key_bytes
        + mac
        + client_public_key
)

server_proof = server_private_key.sign(material)

sub_tlv = tlv_parser.encode([{
    TlvCode.identifier: "26:5B:B6:FE:93:40:ED",
    TlvCode.signature: server_proof,
}])

hkdf = HKDF(algorithm=SHA512(), length=32, salt=SALT_VERIFY, info=INFO_VERIFY, backend=default_backend())
session_key = hkdf.derive(shared_key)

chacha = ChaCha20Poly1305(session_key)
encrypted_data = chacha.encrypt(NONCE_VERIFY_M2, sub_tlv, None)

for byte in encrypted_data:
    print(byte)