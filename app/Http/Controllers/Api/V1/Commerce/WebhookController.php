<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Application\Commerce\Actions\HandleWebhookAction;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public gateway webhook receiver. The account is identified by its public ULID
 * in the URL; authenticity comes from the gateway signature verified inside the
 * action, never from the URL. The raw request body is passed through unparsed so
 * signatures over the exact bytes verify.
 */
class WebhookController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const SIGNATURE_HEADERS = [
        'fake' => 'X-Fake-Signature',
        'stripe' => 'Stripe-Signature',
        'mercadopago' => 'X-Signature',
    ];

    public function handle(Request $request, HandleWebhookAction $action, string $account): JsonResponse
    {
        $model = PaymentAccount::query()->withoutGlobalScopes()->where('ulid', $account)->firstOrFail();

        $header = self::SIGNATURE_HEADERS[$model->gateway->value];
        $signature = (string) $request->header($header, '');

        $action->execute($model, (string) $request->getContent(), $signature);

        return response()->json(['received' => true]);
    }
}
