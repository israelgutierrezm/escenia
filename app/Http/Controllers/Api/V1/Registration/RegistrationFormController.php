<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Registration;

use App\Application\Registration\Actions\SaveRegistrationFormAction;
use App\Domain\Registration\Models\RegistrationForm;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\SaveRegistrationFormRequest;
use App\Http\Resources\RegistrationFormResource;
use Illuminate\Http\JsonResponse;

/**
 * Host management of an event's registration form.
 */
class RegistrationFormController extends Controller
{
    use ResolvesEvent;

    public function show(string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $form = RegistrationForm::query()->where('event_id', $model->getKey())->first();

        return response()->json([
            'data' => $form !== null ? RegistrationFormResource::make($form) : null,
        ]);
    }

    public function save(SaveRegistrationFormRequest $request, SaveRegistrationFormAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEngagement', $model);

        /** @var array<int, array<string, mixed>> $fields */
        $fields = $request->validated('fields') ?? [];

        $form = $action->execute($model, $request->user(), (bool) $request->validated('is_open'), $fields);

        // PUT upsert: always 200, never 201 on first create.
        return RegistrationFormResource::make($form)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK);
    }
}
