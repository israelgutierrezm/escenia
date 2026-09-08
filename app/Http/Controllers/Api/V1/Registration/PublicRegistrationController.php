<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Registration;

use App\Application\Registration\Actions\RegisterAttendeeAction;
use App\Application\Registration\DTOs\RegisterAttendeeData;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\RegistrationForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterAttendeeRequest;
use App\Http\Resources\AttendeeResource;
use App\Http\Resources\RegistrationFormResource;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated registration surface. The event is resolved by its
 * public ULID without tenant scope; the tenant is never taken from the request.
 * A successful registration returns the attendee's join token exactly once — it
 * is the credential for every attendee-facing endpoint thereafter.
 */
class PublicRegistrationController extends Controller
{
    public function show(string $event): JsonResponse
    {
        $model = Event::query()->withoutGlobalScopes()->where('ulid', $event)->firstOrFail();

        $form = RegistrationForm::query()
            ->withoutGlobalScopes()
            ->where('event_id', $model->getKey())
            ->firstOrFail();

        return response()->json([
            'data' => [
                'event' => ['id' => $model->ulid, 'title' => $model->title],
                'form' => RegistrationFormResource::make($form),
            ],
        ]);
    }

    public function store(RegisterAttendeeRequest $request, RegisterAttendeeAction $action, string $event): JsonResponse
    {
        $result = $action->execute($event, RegisterAttendeeData::fromArray($request->validated()));

        return response()->json([
            'data' => [
                'attendee' => AttendeeResource::make($result['attendee']),
                'token' => $result['token'],
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
