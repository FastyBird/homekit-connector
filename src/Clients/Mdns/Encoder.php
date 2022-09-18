<?php declare(strict_types = 1);

/**
 * Encoder.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Clients\Mdns;

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use Nette;

/**
 * mDNS request encoder
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Encoder
{

	use Nette\SmartObject;

	private const SUPPORTED_RR_TYPES = [
		Types\ResourceRecordType::TYPE_A,
		Types\ResourceRecordType::TYPE_AAAA,
		Types\ResourceRecordType::TYPE_CNAME,
		Types\ResourceRecordType::TYPE_DNAME,
		Types\ResourceRecordType::TYPE_NS,
		Types\ResourceRecordType::TYPE_PTR,
		Types\ResourceRecordType::TYPE_SOA,
		Types\ResourceRecordType::TYPE_MX,
		Types\ResourceRecordType::TYPE_TXT,
		Types\ResourceRecordType::TYPE_SRV,
	];

	/**
	 * @param Message $message
	 *
	 * @return string
	 *
	 * @throws Exceptions\UnsupportedType
	 */
	public static function encodeMessage(Message $message): string
	{
		return self::encodeHeader($message->getHeader()) .
			self::encodeResourceRecords($message->getQuestions()) .
			self::encodeResourceRecords($message->getAnswers()) .
			self::encodeResourceRecords($message->getAuthoritatives()) .
			self::encodeResourceRecords($message->getAdditionals());
	}

	/**
	 * Encode a domain name as a sequence of labels
	 *
	 * @param string $domain
	 *
	 * @return string
	 */
	public static function encodeDomainName(string $domain): string
	{
		if ($domain === '.') {
			return chr(0);
		}

		$domain = rtrim($domain, '.') . '.';

		$res = '';

		foreach (explode('.', $domain) as $label) {
			$res .= chr(strlen($label)) . $label;
		}

		return $res;
	}

	/**
	 * @param ResourceRecord[] $resourceRecords
	 *
	 * @return string
	 *
	 * @throws Exceptions\UnsupportedType
	 */
	public static function encodeResourceRecords(array $resourceRecords): string
	{
		$records = array_map(function (ResourceRecord $rr): string {
			$encoded = self::encodeDomainName($rr->getName());

			if ($rr->isQuestion()) {
				return $encoded . pack('nn', $rr->getType(), $rr->getClass());
			}

			$data = self::encodeRdata($rr->getType(), $rr->getRdata());

			$encoded .= pack('nnNn', $rr->getType(), $rr->getClass(), $rr->getTtl(), strlen($data));

			return $encoded . $data;
		}, $resourceRecords);

		return implode('', $records);
	}

	/**
	 * @param Header $header
	 *
	 * @return string
	 */
	public static function encodeHeader(Header $header): string
	{
		return pack(
			'nnnnnn',
			$header->getId(),
			self::encodeFlags($header),
			$header->getQuestionCount(),
			$header->getAnswerCount(),
			$header->getNameServerCount(),
			$header->getAdditionalRecordsCount()
		);
	}

	/**
	 * Encode the bit field of the Header between "ID" and "QDCOUNT"
	 *
	 * @param Header $header
	 *
	 * @return int
	 */
	private static function encodeFlags(Header $header): int
	{
		return 0x0 |
			($header->isResponse() & 0x1) << 15 |
			($header->getOpcode() & 0xf) << 11 |
			($header->isAuthoritative() & 0x1) << 10 |
			($header->isTruncated() & 0x1) << 9 |
			($header->isRecursionDesired() & 0x1) << 8 |
			($header->isRecursionAvailable() & 0x1) << 7 |
			($header->getZ() & 0x7) << 4 |
			($header->getRcode() & 0xf);
	}

	/**
	 * @param int $type
	 * @param string|Array<string, string|int|string[]>|Array<int, string> $rdata
	 *
	 * @return string
	 *
	 * @throws Exceptions\UnsupportedType
	 */
	public static function encodeRdata(int $type, string|array $rdata): string
	{
		if (!in_array($type, self::SUPPORTED_RR_TYPES, true)) {
			throw new Exceptions\UnsupportedType(
				sprintf(
					'Record type "%s" is not a supported type.',
					Types\ResourceRecordType::isValidValue($type) ? strval(Types\ResourceRecordType::get($type)) : $type
				)
			);
		}

		if (
			(
				$type === Types\ResourceRecordType::TYPE_A
				|| $type === Types\ResourceRecordType::TYPE_AAAA
			) && is_string($rdata)
		) {
			return self::encodeAorAaaaRecord($rdata);

		} elseif (
			(
				$type === Types\ResourceRecordType::TYPE_CNAME
				|| $type === Types\ResourceRecordType::TYPE_DNAME
				|| $type === Types\ResourceRecordType::TYPE_NS
				|| $type === Types\ResourceRecordType::TYPE_PTR
			) && is_string($rdata)
		) {
			return self::encodeCnameDnameNsPtrRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_SOA && is_array($rdata)) {
			return self::encodeSoaRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_MX && is_array($rdata)) {
			return self::encodeMxRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_TXT) {
			return self::encodeTxtRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_SRV && is_array($rdata)) {
			return self::encodeSrvRecord($rdata);
		}

		return '';
	}

	/**
	 * Used for A and AAAA records
	 *
	 * @param string $rdata
	 *
	 * @return string
	 */
	public static function encodeAorAaaaRecord(string $rdata): string
	{
		if (!filter_var($rdata, FILTER_VALIDATE_IP)) {
			throw new Exceptions\InvalidArgument(sprintf('The IP address "%s" is invalid.', $rdata));
		}

		return (string) inet_pton($rdata);
	}

	/**
	 * Used for CNAME, DNAME, NS, and PTR records
	 *
	 * @param string $rdata
	 *
	 * @return string
	 */
	public static function encodeCnameDnameNsPtrRecord(string $rdata): string
	{
		return self::encodeDomainName($rdata);
	}

	/**
	 * Exclusively for SOA records
	 *
	 * @param Array<string, string|int|string[]>|Array<int, string> $rdata
	 *
	 * @return string
	 */
	public static function encodeSoaRecord(array $rdata): string
	{
		if (!is_string($rdata['mname']) || !is_string($rdata['rname'])) {
			throw new Exceptions\InvalidArgument('Provided SOA record data are not valid');
		}

		return self::encodeDomainName((string) $rdata['mname']) .
			self::encodeDomainName((string) $rdata['rname']) .
			pack(
				'NNNNN',
				$rdata['serial'],
				$rdata['refresh'],
				$rdata['retry'],
				$rdata['expire'],
				$rdata['minimum']
			);
	}

	/**
	 * Exclusively for MX records
	 *
	 * @param Array<string, string|int|string[]>|Array<int, string> $rdata
	 *
	 * @return string
	 */
	public static function encodeMxRecord(array $rdata): string
	{
		if (!is_numeric($rdata['preference']) || !is_string($rdata['exchange'])) {
			throw new Exceptions\InvalidArgument('Provided MX record data are not valid');
		}

		return pack('n', (int) $rdata['preference']) . self::encodeDomainName((string) $rdata['exchange']);
	}

	/**
	 * Exclusively for TXT records
	 *
	 * @param string|Array<string, string|int|string[]>|Array<int, string> $rdata
	 *
	 * @return string
	 */
	public static function encodeTxtRecord(string|array $rdata): string
	{
		if (is_string($rdata)) {
			$rdata = substr($rdata, 0, 255);

			return chr(strlen($rdata)) . $rdata;
		}

		return '';
	}

	/**
	 * Exclusively for SRV records
	 *
	 * @param Array<string, string|int|string[]>|Array<int, string> $rdata
	 *
	 * @return string
	 */
	public static function encodeSrvRecord(array $rdata): string
	{
		if (
			!is_numeric($rdata['priority'])
			|| !is_numeric($rdata['weight'])
			|| !is_numeric($rdata['port'])
			|| !is_string($rdata['target'])
		) {
			throw new Exceptions\InvalidArgument('Provided SRV record data are not valid');
		}

		return pack('nnn', (int) $rdata['priority'], (int) $rdata['weight'], (int) $rdata['port']) .
			self::encodeDomainName((string) $rdata['target']);
	}

}
