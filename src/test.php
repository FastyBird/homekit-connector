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

use ChaCha20Poly1305\Cipher;
use FastyBird\HomeKitConnector\Protocol\Tlv;

require_once __DIR__ . '/../vendor/autoload.php';

const NONCE_SETUP_M5 = [
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
	53,  // 5
];
const NONCE_SETUP_M5_V2 = '0000PS-Msg05';

$tlvTool = new Tlv();

$request = [
  5,
  154,
  156,
  89,
  101,
  171,
  220,
  171,
  162,
  42,
  127,
  152,
  187,
  120,
  111,
  231,
  160,
  233,
  252,
  178,
  0,
  199,
  46,
  242,
  65,
  79,
  253,
  219,
  244,
  242,
  222,
  105,
  236,
  181,
  128,
  248,
  107,
  173,
  32,
  34,
  221,
  7,
  104,
  179,
  224,
  159,
  124,
  104,
  65,
  101,
  229,
  13,
  59,
  50,
  46,
  37,
  40,
  128,
  214,
  13,
  253,
  208,
  166,
  9,
  156,
  26,
  209,
  224,
  142,
  180,
  51,
  127,
  24,
  152,
  25,
  101,
  171,
  165,
  251,
  243,
  148,
  152,
  68,
  11,
  230,
  18,
  29,
  194,
  111,
  26,
  118,
  105,
  14,
  56,
  122,
  174,
  172,
  102,
  113,
  140,
  139,
  247,
  167,
  191,
  150,
  32,
  55,
  124,
  78,
  175,
  85,
  236,
  146,
  30,
  187,
  46,
  220,
  158,
  86,
  240,
  109,
  25,
  230,
  183,
  107,
  98,
  166,
  105,
  229,
  186,
  183,
  19,
  163,
  139,
  236,
  209,
  81,
  166,
  168,
  238,
  104,
  213,
  127,
  197,
  33,
  6,
  119,
  247,
  195,
  118,
  136,
  4,
  123,
  238,
  77,
  178,
  6,
  1,
  5,
];

$decryptKey = [
  141,
  107,
  105,
  124,
  15,
  118,
  155,
  80,
  136,
  92,
  6,
  187,
  36,
  75,
  157,
  163,
  12,
  122,
  184,
  6,
  142,
  133,
  167,
  84,
  244,
  198,
  112,
  120,
  159,
  133,
  189,
  124,
];

$tlv = $tlvTool->decode(pack('C*', ...$request));

$cipher = new Cipher();
$context = $cipher->init(pack('C*', ...$decryptKey), pack('C*', ...NONCE_SETUP_M5));
$cipher->aad($context, '');
$plaintext = $cipher->decrypt($context, pack('C*', ...$tlv[0][5]));

$decoded = $tlvTool->decode($plaintext);

$cipher = new Cipher();
$context = $cipher->init(pack('C*', ...$decryptKey), pack('C*', ...NONCE_SETUP_M5));
$cipher->aad($context, '');
$encoded = $cipher->encrypt($context, $tlvTool->encode($decoded));

$cipher = new Cipher();
$context = $cipher->init(pack('C*', ...$decryptKey), pack('C*', ...NONCE_SETUP_M5));
$cipher->aad($context, '');
$plaintext = $cipher->decrypt($context, $encoded);

var_dump(unpack('C*', $plaintext));

// 1 (36) / 3 (32) / 10 (64)