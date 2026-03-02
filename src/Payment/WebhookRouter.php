<?php

declare(strict_types=1);

namespace Plugs\Payment;

use InvalidArgumentException;
use Plugs\Http\Request;
use Plugs\Http\Response;

class WebhookRouter
{
    /**
     * The payment manager instance.
     *
     * @var PaymentManager
     */
    protected PaymentManager $manager;

    /**
     * Create a new WebhookRouter instance.
     *
     * @param PaymentManager $manager
     */
    public function __construct(PaymentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle the incoming Webhook HTTP request.
     *
     * @param string $gateway The gateway identifying string (e.g., 'stripe') from the URI.
     * @param Request $request
     * @return Response
     */
    public function handle(string $gateway, Request $request): Response
    {
        try {
            $driver = $this->manager->driver($gateway);

            // Cryptographically verify the webhook signature before processing
            if (!$driver->verifyWebhookSignature($request)) {
                throw new \Plugs\Payment\Exceptions\InvalidSignatureException("Invalid webhook signature for gateway: {$gateway}");
            }

            // Let the driver's webhook method handle signature validation and normalizing logic.
            // The driver is expected to internally dispatch `PaymentSucceeded`, `PaymentFailed`, etc.
            $driver->webhook($request->all());

            $response = new Response(200);
            $response->getBody()->write('Webhook Handled');
            return $response;
        } catch (InvalidArgumentException $e) {
            // Driver not found
            $response = new Response(400);
            $response->getBody()->write('Unsupported Gateway');
            return $response;
        } catch (\Plugs\Payment\Exceptions\InvalidSignatureException $e) {
            // Signature Parsing Failure
            $response = new Response(403);
            $response->getBody()->write('Forbidden: ' . $e->getMessage());
            return $response;
        } catch (\Exception $e) {
            // Processing or Signature parsing failure defaults to 400
            // Logging can be hooked into standard exception handling
            $response = new Response(400);
            $response->getBody()->write($e->getMessage());
            return $response;
        }
    }
}
