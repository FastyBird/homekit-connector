<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use Ninjify\Nunjuck\TestCase\BaseTestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * @testCase
 */
final class TlvDecodeTest extends BaseTestCase
{

	public function testDecode(): void
	{
		$tlvTool = new Protocol\Tlv();

		$data = pack(
			'C*',
			0x06, // state
			0x01, // 1 byte value size
			0x03, // M3
			0x01, // identifier
			0x05, // 5 byte value size
			0x68, // ASCII 'h'
			0x65, // ASCII 'e'
			0x6c, // ASCII 'l'
			0x6c, // ASCII 'l'
			0x6f  // ASCII 'o'
		);

		$result = $tlvTool->decode($data);

		Assert::count(1, $result);
		Assert::same(
			[
				Types\TlvCode::CODE_STATE => 3,
				Types\TlvCode::CODE_IDENTIFIER => 'hello',
			],
			$result[0]
		);
	}

	public function testDecodeWithMerge(): void
	{
		$tlvTool = new Protocol\Tlv();

		$rawData = [
			0x06, // state
			0x01, // 1 byte value size
			0x03, // M3
			0x09, // certificate
			0xff, // 255 byte value size
			0x61, // ASCII 'a'
		];

		for ($i = 0; $i < 254; $i++) {
			$rawData[] = 0x61;
		}

		$rawData = array_merge(
			$rawData,
			[
				0x09, // certificate, continuation of previous TLV
				0x2d, // 45 byte value size
				0x61, // ASCII 'a'
			]
		);

		for ($i = 0; $i < 44; $i++) {
			$rawData[] = 0x61;
		}

		$rawData = array_merge(
			$rawData,
			[
				0x01, // identifier, new TLV item
				0x05, // 5 byte value size
				0x68, // ASCII 'h'
				0x65, // ASCII 'e'
				0x6c, // ASCII 'l'
				0x6c, // ASCII 'l'
				0x6f, // ASCII 'o'
			]
		);

		$data = pack('C*', ...$rawData);

		$result = $tlvTool->decode($data);

		$certificate = [];

		for ($i = 0; $i < 300; $i++) {
			$certificate[] = ord('a');
		}

		Assert::count(1, $result);
		Assert::same(
			[
				Types\TlvCode::CODE_STATE => 3,
				Types\TlvCode::CODE_CERTIFICATE => $certificate,
				Types\TlvCode::CODE_IDENTIFIER => 'hello',
			],
			$result[0]
		);
	}

	public function testDecodeSeparated(): void
	{
		$tlvTool = new Protocol\Tlv();

		$data = pack(
			'C*',
			0x01, // identifier
			0x05, // 5 byte value size
			0x68, // ASCII 'h'
			0x65, // ASCII 'e'
			0x6c, // ASCII 'l'
			0x6c, // ASCII 'l'
			0x6f, // ASCII 'o'
			0x0b, // permissions
			0x01, // 1 byte value size
			0x00, // user permission
			0xff, // separator
			0x00, // 0 byte value size
			0x01, // identifier
			0x05, // 5 byte value size
			0x77, // ASCII 'w'
			0x6f, // ASCII 'o'
			0x72, // ASCII 'r'
			0x6c, // ASCII 'l'
			0x64, // ASCII 'd'
			0x0b, // permissions
			0x01, // 1 byte value size
			0x01  // admin permission
		);

		$result = $tlvTool->decode($data);

		Assert::count(2, $result);
		Assert::same(
			[
				Types\TlvCode::CODE_IDENTIFIER => 'hello',
				Types\TlvCode::CODE_PERMISSIONS => 0,
			],
			$result[0]
		);
		Assert::same(
			[
				Types\TlvCode::CODE_IDENTIFIER => 'world',
				Types\TlvCode::CODE_PERMISSIONS => 1,
			],
			$result[1]
		);
	}

	/**
	 * @throws FastyBird\HomeKitConnector\Exceptions\InvalidArgument  Provided TLV code in data is not valid
	 */
	public function testDecodeInvalidStateValue(): void
	{
		$tlvTool = new Protocol\Tlv();

		$data = pack(
			'C*',
			0xfa // unknown code
		);

		$tlvTool->decode($data);
	}

	/**
	 * @throws FastyBird\HomeKitConnector\Exceptions\InvalidArgument  Only short (1-byte length) integers is supported
	 */
	public function testDecodeInvalidContentSize(): void
	{
		$tlvTool = new Protocol\Tlv();

		$data = pack(
			'C*',
			0x00, // method (integer type)
			0x02, // 2 byte value size
			0x00, // first integer byte
			0x00 // second integer byte (only 1-byte length integers is supported)
		);

		$tlvTool->decode($data);
	}

	/**
	 * @throws FastyBird\HomeKitConnector\Exceptions\InvalidArgument  Unable to decode string from bytes
	 */
	public function testDecodeInvalidContentValue(): void
	{
		$tlvTool = new Protocol\Tlv();

		$data = pack(
			'C*',
			0x01, // identifier (string type)
			0x01, // 1 byte value size
			0xf0 // invalid unicode symbol
		);

		$tlvTool->decode($data);
	}

}

$test_case = new TlvDecodeTest();
$test_case->run();
