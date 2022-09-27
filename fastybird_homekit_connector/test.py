from cryptography.hazmat.primitives.ciphers.aead import ChaCha20Poly1305
from cryptography.exceptions import InvalidTag

decrypt_key = bytes(bytearray([42,74,94,33,205,30,56,162,165,119,226,173,250,178,110,228,169,102,129,74,165,5,19,89,18,225,25,5,26,183,221,105]))
encrypted_data = bytes(bytearray([102,103,170,239,201,76,6,84,6,213,216,250,253,175,8,81,184,100,11,76,65,180,134,82,241,195,178,228,178,1,182,239,100,202,221,55,139,194,119,95,233,78,0,19,168,219,247,210,11,114,189,77,215,111,237,196,60,235,251,73,76,235,233,179,79,50,184,226,77,4,214,102,73,11,72,226,188,144,90,183,204,244,24,159,163,62,101,247,96,80,94,204,138,39,116,148]))
data_length = bytes(bytearray([80,0]))

nonce = b'\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00'

chacha = ChaCha20Poly1305(decrypt_key)

try:
    decrypted_data = chacha.decrypt(nonce, encrypted_data, data_length)
except InvalidTag:
    decrypted_data = None

print(decrypted_data)

