<?php declare(strict_types = 1);

/**
 * HapRequestError.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Exceptions
 * @since          0.19.0
 *
 * @date           27.09.22
 */

namespace FastyBird\HomeKitConnector\Exceptions;

use FastyBird\HomeKitConnector\Types;
use IPub\SlimRouter\Exceptions as SlimRouterExceptions;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class HapRequestError extends SlimRouterExceptions\HttpException implements Exception
{

	/**
	 * @param ServerRequestInterface $request
	 * @param Types\ServerStatus $error
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(
		ServerRequestInterface $request,
		private Types\ServerStatus $error,
		string $message = '',
		int $code = 0,
		Throwable|null $previous = null,
	)
	{
		parent::__construct($request, $message, $code, $previous);
	}

	/**
	 * @return Types\ServerStatus
	 */
	public function getError(): Types\ServerStatus
	{
		return $this->error;
	}

}
