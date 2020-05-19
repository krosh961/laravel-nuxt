<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Auth\ForgotPasswordResetRequest;
use App\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use UnexpectedValueException;

class ResetPasswordController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
     */

    /**
     * Constant representing a successfully sent reminder.
     *
     * @var string
     */
    const RESET_LINK_SENT = 'passwords.sent';
    /**
     * Constant representing a successfully reset password.
     *
     * @var string
     */
    const PASSWORD_RESET = 'passwords.reset';
    /**
     * Constant representing the user not found response.
     *
     * @var string
     */
    const INVALID_USER = 'passwords.user';
    /**
     * Constant representing an invalid password.
     *
     * @var string
     */
    const INVALID_PASSWORD = 'passwords.password';
    /**
     * Constant representing an invalid token.
     *
     * @var string
     */
    const INVALID_TOKEN = 'passwords.token';

    use ResetsPasswords;

    // TODO авторизация после сброса пароля

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reset(ForgotPasswordResetRequest $request)
    {
        $credentials = $this->credentials($request);

        // нужно передать свой валидатор, а то в исходном коде laravel сделали по глупому еще валидацию, зачем если есть валидация в запросе?! https://github.com/laravel/framework/blob/5.5/src/Illuminate/Auth/Passwords/PasswordBroker.php#L168
        // $this->broker()->validator(function () { return true; });
        $response = $this->validateReset($credentials);
        // $response = $this->broker()->validateReset($credentials);

        if ($response instanceof CanResetPasswordContract) {
            $user = $response;
        } else {
            switch ($response) {
                case static::INVALID_USER:
                    return $this->sendError(trans('passwords.user', ['input' => 'почте(она должна быть в вашем url, вероятно вы меняли ссылку)']), 404);
                case static::INVALID_PASSWORD:
                    return $this->sendError(trans('passwords.password'), 422);
                case static::INVALID_TOKEN:
                    return $this->sendError(trans('passwords.token'), 422);
            }
        }

        $this->resetPassword($user, $credentials['password']);
        $this->broker()->getRepository()->delete($user);

        return $this->sendResponse(null, trans('passwords.reset'));

        // Что делает reset: https://github.com/laravel/framework/blob/5.5/src/Illuminate/Auth/Passwords/PasswordBroker.php#L83
        // Передает $credentials для валидации, работы с токеном, возвращает ответ в виде константы класса Illuminate\Contracts\Auth\PasswordBroker
        // $response = $this->broker()->reset($credentials, function ($user, $password) {
        //     // если все правильно то выполнится этот колбэк
        //     $this->resetPassword($user, $password);
        // });

        // switch ($response) {
        //     case Password::PASSWORD_RESET:
        //         return $this->sendResponse(NULL, trans('passwords.reset'));
        //     case Password::INVALID_USER:
        //         return $this->sendError(trans('passwords.user', ['input' => 'почте(она должна быть в вашем url, вероятно вы меняли ссылку)']), 404);
        //     case Password::INVALID_PASSWORD:
        //         return $this->sendError(trans('passwords.password'), 422);
        //     case Password::INVALID_TOKEN:
        //         return $this->sendError(trans('passwords.token'), 422);
        // }
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $user->password = Hash::make($password);
        $user->save();

//        RememberToken is removed from user db!
//        $user->setRememberToken(Str::random(60));

        event(new PasswordReset($user));

//        $this->guard()->login($user);
    }

    /**
     * Пользователь по указанным данным
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\CanResetPassword|null
     *
     * @throws \UnexpectedValueException
     */
    public function getUser(array $credentials)
    {
        // $passwordHashed = Hash::make($credentials['password']);
        $user = User::whereHas('emails', function ($query) use ($credentials) {
            $query->where('email', $credentials['email']);
        })->first();
        $user->setEmailForResetPassword($credentials['email']);

        if ($user && ! $user instanceof CanResetPasswordContract) {
            throw new UnexpectedValueException('User must implement CanResetPassword interface.');
        }

        return $user;
    }

    /**
     * Валидация нового пароля.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validateNewPassword(array $credentials)
    {
        return true;
    }

    /**
     * Validate a password reset for the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\CanResetPassword|string
     */
    protected function validateReset(array $credentials)
    {
        if (is_null($user = $this->getUser($credentials))) {
            return static::INVALID_USER;
        }
        if (! $this->validateNewPassword($credentials)) {
            return static::INVALID_PASSWORD;
        }
        // if (! $this->broker()->getRepository()->exists($user, $credentials['token'])) {
        //     return static::INVALID_TOKEN;
        // }
        if (! $this->broker()->tokenExists($user, $credentials['token'])) {
            return static::INVALID_TOKEN;
        }

        return $user;
    }

    /**
     * Get the password reset credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        $cred = $request->only(
            'email', 'password', 'token' // 'password_confirmation'
        );
        $cred['password_confirmation'] = $cred['password'];

        return $cred;
    }
}
