<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     common
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector;

require_once __DIR__ . '/../vendor/autoload.php';

use ChaCha20Poly1305;
use Elliptic\EdDSA;
use FastyBird\HomeKitConnector\Protocol\Tlv;

const TEST_SESSION_KEY = '5CBC219D B052138E E1148C71 CD449896 3D682549 CE91CA24 F098468F 06015BEB'
	. '6AF245C2 093F98C3 651BCA83 AB8CAB2B 580BBF02 184FEFDF 26142F73 DF95AC50';

const SALT_ACCESSORY = 'Pair-Setup-Accessory-Sign-Salt';
const INFO_ACCESSORY = 'Pair-Setup-Accessory-Sign-Info';
const SALT_ENCRYPT = 'Pair-Setup-Encrypt-Salt';
const INFO_ENCRYPT = 'Pair-Setup-Encrypt-Info';

const NONCE_SETUP_M6 = [
	0,   // 0
	0,   // 0
	0,   // 0
	0,   // 0
	80,  // P
	83,  // S
	45,  // -
	77,  // M
	115, // s
	103, // g
	48,  // 0
	54,  // 9
];

$tlv = new Tlv();

$sessionKey = (string) hex2bin(str_replace(' ', '', TEST_SESSION_KEY));

$accessoryLtsk = [
	158,
	76,
	86,
	21,
	61,
	212,
	120,
	230,
	81,
	192,
	128,
	61,
	213,
	45,
	130,
	38,
	5,
	133,
	57,
	201,
	18,
	4,
	35,
	144,
	171,
	24,
	141,
	66,
	106,
	103,
	40,
	240,
];

$deviceID = 'DD:68:CD:2D:5D:07:05:C1';

$decryptKey = hash_hkdf(
	'sha512',
	$sessionKey,
	32,
	INFO_ENCRYPT,
	SALT_ENCRYPT
);

$accessoryX = hash_hkdf(
	'sha512',
	$sessionKey,
	32,
	INFO_ACCESSORY,
	SALT_ACCESSORY
);

$ec = new EdDSA('ed25519');

$signingKey = $ec->keyFromSecret($accessoryLtsk);
$publicKey = $signingKey->getPublic();

$accessoryInfo = array_merge(unpack('C*', $accessoryX . $deviceID), $publicKey);

$accessorySignature = $signingKey->sign($accessoryInfo)->toBytes();

$responseInnerData = [
	[
		Types\TlvCode::CODE_IDENTIFIER => $deviceID,
		Types\TlvCode::CODE_PUBLIC_KEY => $publicKey,
		Types\TlvCode::CODE_SIGNATURE  => $accessorySignature,
	],
];

$add = [
	36,
	115,
	219,
	93,
	228,
	156,
	197,
	57,
	188,
	25,
	168,
	75,
	76,
	136,
	128,
	238,
];

$cipher = new ChaCha20Poly1305\Cipher();
$context = $cipher->init($decryptKey, pack('C*', ...NONCE_SETUP_M6));
$cipher->aad($context, pack('C*', ''));

$responseEncryptedData = unpack('C*', $cipher->encrypt($context, $tlv->encode($responseInnerData)));

$tag = $cipher->finish($context);

$dataToEncode = $tlv->encode($responseInnerData);

var_dump(unpack('C*', $dataToEncode));

$encoded = sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
	$dataToEncode,
	'',
	pack('C*', ...NONCE_SETUP_M6),
	$decryptKey
);

$decoded = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
	$encoded,
	'',
	pack('C*', ...NONCE_SETUP_M6),
	$decryptKey
);

var_dump(unpack('C*', $decoded));