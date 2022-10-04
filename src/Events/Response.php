<?php declare(strict_types = 1);

/**
 * Response.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\EventDispatcher;

/**
 * Http response event
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Response extends EventDispatcher\Event
{

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 */
	public function __construct(
		private ServerRequestInterface $request,
		private ResponseInterface $response,
	) {
	}

	/**
	 * @return ServerRequestInterface
	 */
	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}

	/**
	 * @return ResponseInterface
	 */
	public function getResponse(): ResponseInterface
	{
		return $this->response;
	}

}
