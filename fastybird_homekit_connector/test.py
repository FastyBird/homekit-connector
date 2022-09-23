import ed25519
from os import urandom
from cryptography.hazmat.primitives.kdf.hkdf import HKDF
from cryptography.hazmat.primitives.hashes import SHA512
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives.ciphers.aead import ChaCha20Poly1305
from pyhap.tlv import (
    TlvCode,
    TlvState,
    TlvError,
    tlv_parser
)

# accessory_ltsk = bytes(bytearray.fromhex(urandom(32).hex()))
accessory_ltsk = b'\x9eLV\x15=\xd4x\xe6Q\xc0\x80=\xd5-\x82&\x05\x859\xc9\x12\x04#\x90\xab\x18\x8dBjg(\xf0'

# device_id = ':'.join(urandom(1).hex().upper() for _ in range(8))
device_id = "DD:68:CD:2D:5D:07:05:C1"

SALT_ACCESSORY = b'Pair-Setup-Accessory-Sign-Salt'
INFO_ACCESSORY = b'Pair-Setup-Accessory-Sign-Info'
SALT_ENCRYPT = b'Pair-Setup-Encrypt-Salt'
INFO_ENCRYPT = b'Pair-Setup-Encrypt-Info'

NONCE_SETUP_M6 = b'\x00\x00\x00\x00PS-Msg06'

TEST_K = bytes.fromhex(
    '5CBC219D B052138E E1148C71 CD449896 3D682549 CE91CA24 F098468F 06015BEB'
    '6AF245C2 093F98C3 651BCA83 AB8CAB2B 580BBF02 184FEFDF 26142F73 DF95AC50'
)

hkdf = HKDF(algorithm=SHA512(), length=32, salt=SALT_ENCRYPT, info=INFO_ENCRYPT, backend=default_backend())
decrypt_key = hkdf.derive(TEST_K)

hkdf = HKDF(algorithm=SHA512(), length=32, salt=SALT_ACCESSORY, info=INFO_ACCESSORY, backend=default_backend())
accessory_x = hkdf.derive(TEST_K)

signing_key = ed25519.SigningKey(accessory_ltsk)
public_key = signing_key.get_verifying_key().to_bytes()
accessory_info = accessory_x + device_id.encode() + public_key
accessory_signature = signing_key.sign(accessory_info)

sub_tlv = tlv_parser.encode([{
    TlvCode.identifier: device_id,
    TlvCode.public_key: public_key,
    TlvCode.signature: accessory_signature,
}])

chacha = ChaCha20Poly1305(decrypt_key)
encrypted_data = chacha.encrypt(NONCE_SETUP_M6, sub_tlv, None)

i = 1
for byte in encrypted_data:
    print("{} - {}".format(i, byte))
    i = i + 1

print("")
print(len(encrypted_data))
