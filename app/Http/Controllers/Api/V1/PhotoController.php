<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\UserPhotoResource;
use App\Models\UserPhoto;
use App\Services\PhotoService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    use ApiResponse;

    public function __construct(public PhotoService $photoService) {}

    public function index(Request $request): JsonResponse
    {
        $photos = UserPhoto::where('user_id', $request->user()->id)
            ->orderBy('display_order', 'asc')
            ->get();

        return $this->successResponse(UserPhotoResource::collection($photos), 'Photos retrieved.');
    }

    public function store(UploadPhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $isPrimary = $request->boolean('is_primary', false);

        $photo = $this->photoService->uploadPhoto($user, $request->file('photo'), $isPrimary);

        return $this->successResponse(new UserPhotoResource($photo), 'Photo uploaded successfully.', 201);
    }

    public function destroy(Request $request, UserPhoto $photo): JsonResponse
    {
        $this->photoService->deletePhoto($request->user(), $photo);

        return $this->successResponse(null, 'Photo deleted successfully.');
    }

    public function setPrimary(Request $request, UserPhoto $photo): JsonResponse
    {
        $updatedPhoto = $this->photoService->setPrimaryPhoto($request->user(), $photo);

        return $this->successResponse(new UserPhotoResource($updatedPhoto), 'Primary photo set.');
    }
}
