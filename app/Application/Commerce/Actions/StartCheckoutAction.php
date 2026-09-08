<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Application\Commerce\DTOs\CheckoutData;
use App\Application\Registration\Actions\IssueAttendeeAction;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentGatewayName;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Exceptions\CheckoutUnavailableException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\OrderItem;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Commerce\ValueObjects\PaymentIntentResult;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Tenancy\Context\TenantContext;
use App\Infrastructure\Payments\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public flow: a buyer purchases tickets for an event. The event is resolved
 * unscoped and the tenant derived from it (never trusted from the request), like
 * registration. Creates the buyer's attendee (returning the join token once),
 * a pending order, and a payment intent via the tenant's gateway. The order is
 * marked paid only by the verified webhook (ConfirmPaymentAction).
 *
 * The gateway's createIntent is an external call kept OUTSIDE the DB transaction
 * so a slow/failing gateway never holds row locks.
 *
 * @phpstan-type CheckoutResult array{order: Order, attendee: Attendee, token: string, intent: PaymentIntentResult}
 */
final class StartCheckoutAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly IssueAttendeeAction $issueAttendee,
        private readonly PaymentGatewayFactory $gateways,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{order: Order, attendee: Attendee, token: string, intent: PaymentIntentResult}
     */
    public function execute(string $eventUlid, CheckoutData $data): array
    {
        $event = Event::query()->withoutGlobalScopes()->where('ulid', $eventUlid)->first();

        if ($event === null) {
            throw new CheckoutUnavailableException('Event not found.');
        }

        return $this->tenantContext->runFor($event->tenant, function () use ($event, $data): array {
            [$lines, $subtotal, $currency] = $this->resolveLines($event, $data);

            $account = PaymentAccount::query()->where('is_active', true)->first();
            $gatewayName = $account !== null
                ? $account->gateway
                : PaymentGatewayName::from((string) config('payments.default', 'fake'));

            /** @var array{order: Order, attendee: Attendee, token: string} $created */
            $created = DB::transaction(function () use ($event, $data, $lines, $subtotal, $currency, $gatewayName): array {
                $issued = $this->issueAttendee->execute($event, $data->buyerName, $data->buyerEmail);

                $order = Order::query()->create([
                    'event_id' => $event->getKey(),
                    'contact_id' => $issued['contact']->getKey(),
                    'attendee_id' => $issued['attendee']->getKey(),
                    'buyer_name' => $data->buyerName,
                    'buyer_email' => Str::lower(trim($data->buyerEmail)),
                    'status' => OrderStatus::Pending,
                    'currency' => $currency,
                    'subtotal_minor' => $subtotal,
                    'total_minor' => $subtotal,
                    'gateway' => $gatewayName->value,
                ]);

                foreach ($lines as $line) {
                    OrderItem::query()->create([
                        'order_id' => $order->getKey(),
                        'ticket_id' => $line['ticket']->getKey(),
                        'ticket_name' => $line['ticket']->name,
                        'quantity' => $line['quantity'],
                        'unit_amount_minor' => $line['ticket']->amount_minor,
                        'subtotal_minor' => $line['subtotal'],
                        'currency' => $currency,
                    ]);
                }

                $this->audit->log('commerce.checkout.started', tenant: $event->tenant, auditable: $order);

                return ['order' => $order, 'attendee' => $issued['attendee'], 'token' => $issued['token']];
            });

            $order = $created['order'];
            $intent = $this->gateways->for($account)->createIntent($order, $account);

            Payment::query()->create([
                'order_id' => $order->getKey(),
                'gateway' => $gatewayName->value,
                'gateway_reference' => $intent->reference,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $subtotal,
                'currency' => $currency,
            ]);

            return [
                'order' => $order->load('items'),
                'attendee' => $created['attendee'],
                'token' => $created['token'],
                'intent' => $intent,
            ];
        });
    }

    /**
     * Validate the requested tickets and compute the ordered lines + total.
     *
     * @return array{0: list<array{ticket: Ticket, quantity: int, subtotal: int}>, 1: int, 2: string}
     */
    private function resolveLines(Event $event, CheckoutData $data): array
    {
        if ($data->items === []) {
            throw new CheckoutUnavailableException('No items to purchase.');
        }

        $lines = [];
        $subtotal = 0;
        $currency = null;

        foreach ($data->items as $item) {
            if ($item['quantity'] < 1) {
                throw new CheckoutUnavailableException('Invalid quantity.');
            }

            $ticket = Ticket::query()
                ->where('ulid', $item['ticket'])
                ->where('event_id', $event->getKey())
                ->first();

            if ($ticket === null || ! $ticket->isOnSale() || ! $ticket->hasStockFor($item['quantity'])) {
                throw new CheckoutUnavailableException;
            }

            $currency ??= $ticket->currency;

            if ($ticket->currency !== $currency) {
                throw new CheckoutUnavailableException('Mixed currencies are not supported in one order.');
            }

            $lineSubtotal = $ticket->amount_minor * $item['quantity'];
            $subtotal += $lineSubtotal;
            $lines[] = ['ticket' => $ticket, 'quantity' => $item['quantity'], 'subtotal' => $lineSubtotal];
        }

        return [$lines, $subtotal, (string) $currency];
    }
}
