<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Profile\Other\GetUserRequest;
use App\Http\Resources\UserResource;
use App\User;

class OtherProfileController extends BaseController
{
    /**
     * Получение пользователя.
     */
    public function getUser(GetUserRequest $request)
    {
        $nickname = $request->nickname;
        $user = User::ofNickname($nickname)->first();

        if (! $user) {
            return $this->sendError('Не удалось найти пользователя.', 404);
        }

        return new UserResource($user);
    }
}
