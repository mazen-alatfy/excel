<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportContactsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService)
    {
    }

    /**
     * POST /api/contacts/import
     */
    public function import(ImportContactsRequest $request): JsonResponse
    {
        $result = $this->contactService->importAndStore($request->file('file'));

        if ($result === null) {
            return response()->json(['message' => 'Expected columns: name, email, phone, age'], 422);
        }

        return ApiResponse::success($result, 'Contacts saved.', 201);
    }

    /**
     * GET /api/contacts
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->contactService->getAll(), 'Contacts fetched.', 200);
    }
}
