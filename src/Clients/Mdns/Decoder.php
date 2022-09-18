<?php declare(strict_types = 1);

/**
 * Decoder.php
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
 * mDNS request decoder
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Decoder
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
	 * @param string $message
	 *
	 * @return Message
	 */
	public static function decodeMessage(string $message): Message
	{
		$offset = 0;

		$header = self::decodeHeader($message, $offset);

		$questions = self::decodeResourceRecords(
			$message,
			$header->getQuestionCount(),
			$offset,
			true
		);

		$answers = self::decodeResourceRecords(
			$message,
			$header->getAnswerCount(),
			$offset
		);

		$authoritatives = self::decodeResourceRecords(
			$message,
			$header->getNameServerCount(),
			$offset
		);

		$additionals = self::decodeResourceRecords(
			$message,
			$header->getAdditionalRecordsCount(),
			$offset
		);

		return new Message(
			$header,
			$questions,
			$answers,
			$authoritatives,
			$additionals
		);
	}

	/**
	 * @param string $string
	 * @param int $offset
	 *
	 * @return string
	 */
	public static function decodeDomainName(string $string, int &$offset = 0): string
	{
		$len = ord($string[$offset]);

		++$offset;

		if ($len === 0) {
			return '.';
		}

		$domainName = '';

		while ($len !== 0) {
			$domainName .= substr($string, $offset, $len) . '.';

			$offset += $len;

			$len = ord($string[$offset]);

			++$offset;
		}

		return $domainName;
	}

	/**
	 * @param string $pkt
	 * @param int $offset
	 * @param int $count The number of resource records to decode
	 * @param bool $isQuestion Is the resource record from the question section
	 *
	 * @return ResourceRecord[]
	 */
	public static function decodeResourceRecords(
		string $pkt,
		int $count = 1,
		int &$offset = 0,
		bool $isQuestion = false
	): array {
		$resourceRecords = [];

		for ($i = 0; $i < $count; ++$i) {
			if ($isQuestion) {
				$values = unpack('ntype/nclass', substr($pkt, $offset, 4));

				$offset += 4;

				if ($values === false) {
					continue;
				}

				$resourceRecords[] = new ResourceRecord(
					self::decodeDomainName($pkt, $offset),
					(int) $values['type'],
					300,
					'',
					(int) $values['class'],
					true
				);

			} else {
				$values = unpack('ntype/nclass/Nttl/ndlength', substr($pkt, $offset, 10));

				$offset += 10;

				if ($values === false) {
					continue;
				}

				// Ignore unsupported types
				try {
					$resourceRecords[] = new ResourceRecord(
						self::decodeDomainName($pkt, $offset),
						(int) $values['type'],
						(int) $values['ttl'],
						self::decodeRdata((int) $values['type'], substr($pkt, $offset, $values['dlength'])),
						(int) $values['class'],
						false
					);
				} catch (Exceptions\UnsupportedType) {
					$offset += $values['dlength'];

					continue;
				}

				$offset += $values['dlength'];
			}
		}

		return $resourceRecords;
	}

	/**
	 * @param string $pkt
	 * @param int $offset
	 *
	 * @return Header
	 */
	public static function decodeHeader(string $pkt, int &$offset = 0): Header
	{
		$data = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', $pkt);

		if ($data === false) {
			throw new Exceptions\InvalidState('Header could not be decoded');
		}

		$flags = self::decodeFlags($data['flags']);

		$offset += 12;

		return new Header(
			(int) $data['id'],
			(bool) $flags['qr'],
			(int) $flags['opcode'],
			(bool) $flags['aa'],
			(bool) $flags['tc'],
			(bool) $flags['rd'],
			(bool) $flags['ra'],
			(int) $flags['z'],
			(int) $flags['rcode'],
			(int) $data['qdcount'],
			(int) $data['ancount'],
			(int) $data['nscount'],
			(int) $data['arcount']
		);
	}

	/**
	 * @param int $flags
	 *
	 * @return Array<string, int>
	 */
	private static function decodeFlags(int $flags): array
	{
		return [
			'qr'     => $flags >> 15 & 0x1,
			'opcode' => $flags >> 11 & 0xf,
			'aa'     => $flags >> 10 & 0x1,
			'tc'     => $flags >> 9 & 0x1,
			'rd'     => $flags >> 8 & 0x1,
			'ra'     => $flags >> 7 & 0x1,
			'z'      => $flags >> 4 & 0x7,
			'rcode'  => $flags & 0xf,
		];
	}

	/**
	 * @param int $type
	 * @param string $rdata
	 *
	 * @return string|Array<string, string>
	 *
	 * @throws Exceptions\UnsupportedType
	 */
	public static function decodeRdata(int $type, string $rdata): string|array
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
			$type === Types\ResourceRecordType::TYPE_A
			|| $type === Types\ResourceRecordType::TYPE_AAAA
		) {
			return self::decodeAorAaaaRecord($rdata);

		} elseif (
			$type === Types\ResourceRecordType::TYPE_CNAME
			|| $type === Types\ResourceRecordType::TYPE_DNAME
			|| $type === Types\ResourceRecordType::TYPE_NS
			|| $type === Types\ResourceRecordType::TYPE_PTR
		) {
			return self::decodeCnameDnameNsPtrRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_SOA) {
			return self::decodeSoaRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_MX) {
			return self::decodeMxRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_TXT) {
			return self::decodeTxtRecord($rdata);

		} elseif ($type === Types\ResourceRecordType::TYPE_SRV) {
			return self::decodeSrvRecord($rdata);
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
	public static function decodeAorAaaaRecord(string $rdata): string
	{
		return (string) inet_ntop($rdata);
	}

	/**
	 * Used for CNAME, DNAME, NS, and PTR records
	 *
	 * @param string $rdata
	 *
	 * @return string
	 */
	public static function decodeCnameDnameNsPtrRecord(string $rdata): string
	{
		return self::decodeDomainName($rdata);
	}

	/**
	 * Exclusively for SOA records
	 *
	 * @param string $rdata
	 *
	 * @return Array<string, string>
	 */
	public static function decodeSoaRecord(string $rdata): array
	{
		$offset = 0;

		$unpacked = unpack('Nserial/Nrefresh/Nretry/Nexpire/Nminimum', substr($rdata, $offset));

		return array_merge(
			[
				'mname' => self::decodeDomainName($rdata, $offset),
				'rname' => self::decodeDomainName($rdata, $offset),
			],
			$unpacked !== false ? $unpacked : []
		);
	}

	/**
	 * Exclusively for MX records
	 *
	 * @param string $rdata
	 *
	 * @return Array<string, string>
	 */
	public static function decodeMxRecord(string $rdata): array
	{
		$unpacked = unpack('npreference', $rdata);

		return [
			'preference' => $unpacked !== false ? $unpacked['preference'] : null,
			'exchange'   => self::decodeDomainName(substr($rdata, 2)),
		];
	}

	/**
	 * Exclusively for TXT records
	 *
	 * @param string $rdata
	 *
	 * @return string
	 */
	public static function decodeTxtRecord(string $rdata): string
	{
		$len = ord($rdata[0]);

		if ((strlen($rdata) + 1) < $len) {
			return '';
		}

		return substr($rdata, 1, $len);
	}

	/**
	 * Exclusively for SRV records
	 *
	 * @param string $rdata
	 *
	 * @return Array<string, string>
	 */
	public static function decodeSrvRecord(string $rdata): array
	{
		$offset = 6;

		$unpacked = unpack('npriority/nweight/nport', $rdata);

		if ($unpacked === false) {
			return [];
		}

		$unpacked['target'] = self::decodeDomainName($rdata, $offset);

		return $unpacked;
	}

}
