<?php

namespace App\Http\Controllers\API\Profile;

use App\Email;
use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Profile\Current\SaveAvatarRequest;
use App\Http\Requests\Profile\Current\SaveEmailRequest;
use App\Http\Requests\Profile\Current\SavePhoneRequest;
use App\Http\Requests\Profile\Current\SetPasswordRequest;
use App\Http\Requests\Profile\Current\SetUserDataRequest;
use App\Http\Resources\UserResource;
use App\Phone;
use App\Traits\Avatar;
use App\Traits\EmailVerification;
use App\UserPasswordHistroy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Image;

class ProfileController extends BaseController
{
    use EmailVerification, Avatar;

    /**
     * Меняет пароль currentPassword newPassword.
     */
    public function setPassword(SetPasswordRequest $request)
    {
        $user = auth()->user();

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->save();

            return new UserResource($user);
        }

        $currentPassword = $request->currentPassword;
        $newPassword = $request->newPassword;

        $passwordHistory = $user->passwordsHistory()->get();
        $oldSamePassword = null;

        // приходится сравнивать вот так, но всеравно никто не меняет пароль по сто раз
        $passwordHistory->each(function ($item) use ($newPassword, &$oldSamePassword) {
            if (Hash::check($newPassword, $item->password)) {
                $oldSamePassword = $item;

                return false;
            }
        });

        if ($oldSamePassword) {
            return $this->sendError("У Вас был такой пароль!(добавляли в $oldSamePassword->created_at)", 422);
        }

        if (Hash::check($currentPassword, $user->password)) {
            $hashedNewPassword = Hash::make($newPassword);

            $user->password = $hashedNewPassword;
            $user->save();

            $user->passwordsHistory()->save(new UserPasswordHistroy([
                'password' => $hashedNewPassword,
            ]));
        } else {
            return $this->sendError('Не верный текущий пароль', 422);
        }

        return new UserResource($user);
    }

    /**
     * Меняет пароль.
     */
    public function setUserData(SetUserDataRequest $request)
    {
        $user = auth()->user();

        $fields = collect($request->all())->keyBy(function ($value, $key) {
            return snake_case($key);
        })->all();

        $user->fill($fields)->save();
        // 'first_name' => $request->firstName,
        // 'last_name' => $request->lastName,
        // 'gender' => $request->gender,
        // 'birthday' => $request->birthday,
        // 'timezone' => $request->timezone,
        // 'country' => $request->country

        return new UserResource($user);
    }

    /** image
     * Сохраняет аватарку.
     */
    public function setAvatar(SaveAvatarRequest $request)
    {
        $user = auth()->user();
        $cropInfo = json_decode($request->cropInfo, true);
        $file = $request->file('file');

        $img = Image::make($file)->crop(
            $cropInfo['width'],
            $cropInfo['height'],
            $cropInfo['x'],
            $cropInfo['y']
        );
        $avatar = $this->setUserAvatar($user, $img);

        return new UserResource($user);
    }

    /**
     * Сохраняет почту.
     */
    public function saveEmail(SaveEmailRequest $request)
    {
        $user = auth()->user();
        $data = $request->only(['email', 'label', 'public']);

        $email = new Email(array_merge($data, [
            'verified' => false,
        ]));
        $email->verification_token = $this->createVerificationTokenAndMail($email);

        $user->saveEmail($email);

        if ($request->main) {
            $user->main_email = $email;
            $user->save();
        }

        return new UserResource($user);
    }

    /**
     * Удаляет почту.
     */
    public function deleteEmail(Request $request)
    {
        if (auth()->user()->main_email->id === $request->id) {
            return $this->sendError('Нельзя удалять главную почту', 422);
        }

        Email::destroy($request->id);

        return new UserResource(auth()->user());
    }

    /**
     * Ставит почту как главную.
     */
    public function setMainEmail(Request $request)
    {
        $email = Email::find($request->id);
        $user = auth()->user();
        $user->main_email = $email;
        $user->save();

        return new UserResource($user);
    }

    /**
     * Ставит гланый телефон.
     */
    public function setMainPhone(Request $request)
    {
        $phone = Phone::find($request->id);
        $user = auth()->user();
        $user->main_phone = $phone;
        $user->save();

        return new UserResource($user);
    }

    /**
     * Ставит почту как главную.
     */
    public function changePublicEmail(Request $request)
    {
        $email = Email::find($request->id);
        $email->public = $request->public;
        $email->save();

        return new UserResource(auth()->user());
    }

    /**
     * История паролей.
     */
    public function getPasswordsHistory()
    {
        return $this->sendResponse(auth()->user()->passwordsHistory()->get());
    }

    /**
     * Сохраняет почту.
     */
    public function savePhone(SavePhoneRequest $request)
    {
        $user = auth()->user();
        // $data = $request->only(['prefix', 'number', 'label', 'public']);

        $phone = new Phone(array_merge($request->all(), [
            'verified' => false,
        ]));
        $phone->sms_verification_code = 'sms token';

        $user->savePhone($phone);

        if ($request->main) {
            $user->main_phone = $phone;
            $user->save();
        }

        return new UserResource($user);
    }

    /**
     * Удаляет почту.
     */
    public function deletePhone(Request $request)
    {
        $user = auth()->user();
        $user->deletePhone($request->id);

        return new UserResource($user);
    }

    /**
     * Ставит почту как главную.
     */
    public function changePublicPhone(Request $request)
    {
        $phone = Phone::find($request->id);
        $phone->public = $request->public;
        $phone->save();

        return new UserResource(auth()->user());
    }
}
