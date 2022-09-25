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

use Curve25519\Curve25519;
use Elliptic\EC;
use Elliptic\EdDSA;
use FastyBird\HomeKitConnector\Protocol\Tlv;

require_once __DIR__ . '/../vendor/autoload.php';

const SETUP_ID = '268273ace626474f3e8a8d3210d024a5c3aa33917a66ac96f8eea23a2d668507';

const NONCE_VERIFY_M2 = [
	0,   // 0
	0,   // 0
	0,   // 0
	0,   // 0
	80,  // P
	86,  // V
	45,  // -
	77,  // M
	115, // s
	103, // g
	48,  // 0
	50,  // 2
];

$setupId = [
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
];

$clientPublicKey = [
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
];

$ec = new EC('curve25519');

//$keyPair = $ec->keyFromPrivate(bin2hex(pack('C*', ...$setupId)));
$keyPair = $ec->keyFromPrivate(SETUP_ID, 16);

$clientKeyPair = $ec->keyFromPublic($clientPublicKey);
//var_dump($keyPair->getPrivate());

$sharedSecret = $keyPair->derive($clientKeyPair->getPublic());

//var_dump($keyPair->getPublic()->encode(16));
//var_dump(unpack('C*', hex2bin($sharedSecret->toString(16))));

$accessoryPublicKey = \Curve25519\publicKey(hex2bin(SETUP_ID));
$sharedSecret = \Curve25519\sharedKey(hex2bin(SETUP_ID), pack('C*', ...$clientPublicKey));

$macAddress = '26:5B:B6:FE:93:40:ED';

$accessoryInfo = $accessoryPublicKey . $macAddress . pack('C*', ...$clientPublicKey);

$ec = new EdDSA('ed25519');

$serverPrivateKey = $ec->keyFromSecret(unpack('C*', strval(hex2bin(SETUP_ID))));
$accessorySignature = $serverPrivateKey->sign(unpack('C*', $accessoryInfo))->toBytes();

$encodeKey = hash_hkdf(
	'sha512',
	$sharedSecret,
	32,
	'Pair-Verify-Encrypt-Info',
	'Pair-Verify-Encrypt-Salt'
);

$responseInnerData = [
	[
		Types\TlvCode::CODE_IDENTIFIER => strval($macAddress),
		Types\TlvCode::CODE_SIGNATURE  => $accessorySignature,
	],
];

$tlvTool = new Tlv();

$responseEncryptedData = unpack('C*', sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
	$tlvTool->encode($responseInnerData),
	'',
	pack('C*', ...NONCE_VERIFY_M2),
	$encodeKey
));


var_dump($responseEncryptedData);
