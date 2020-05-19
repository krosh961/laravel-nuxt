<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends AuthenticatableForUser
{
    use Notifiable, HasApiTokens;

    protected $emailForResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nickname', 'email', 'password', 'first_name', 'last_name', 'avatar',
        'created_through_soc_acc', 'gender', 'birthday', 'main_email_id', 'main_phone_id', 'timezone',
        'country',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', // , 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'avatar' => 'array',
        'gender' => 'boolean',
        // 'birthday' => 'date:Y-m-d'
    ];

    /**
     * The attributes that should be mutated to dates.
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'birthday',
    ];

    /**
     * Какие Accessors при сериализации должны быть выданы.
     */
    protected $appends = ['activated', 'full_name', 'main_email', 'main_phone'];

    /**
     * Accessor который определяет активирован ли пользователь.
     */
    public function getActivatedAttribute()
    {
        // если есть подтвержденная почта или прикрепленный соц акканут
        return $this->emails()->where('verified', true)->exists() ||
            $this->socAccounts()->exists();
    }

    /**
     * Accessor для полного имени.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name && $this->last_name ? "$this->first_name  $this->last_name" : null;
    }

    /**
     * Accessor для главной почты.
     */
    public function getMainEmailAttribute()
    {
        return $this->emails()->find($this->main_email_id);
    }

    /**
     * Accessor для главного телефона.
     */
    public function getMainPhoneAttribute()
    {
        return $this->phones()->find($this->main_phone_id);
    }

    /**
     * Accessor для возраста.
     */
    public function getAgeAttribute()
    {
        if (! $this->birthday) {
            return;
        }

        return Carbon::parse($this->birthday)->age;
    }

    /**
     * Мутация для главной почты.
     */
    public function setMainEmailAttribute($email)
    {
        $this->attributes['main_email_id'] = $email ? $email->id : null;
    }

    /**
     * Мутация для главного телефона.
     */
    public function setMainPhoneAttribute($phone)
    {
        $this->attributes['main_phone_id'] = $phone ? $phone->id : null;
    }

    /**
     * Сохраняет почту и если не было главной то делает
     */
    public function saveEmail($email)
    {
        $this->emails()->save($email);

        // ставит если нет главного
        if (! $this->main_email_id) {
            $this->mainEmail = $email;
            $this->save();
        }
    }

    /**
     * Сохраняет телефон, первый сохраненный будет главным
     */
    public function savePhone($phone)
    {
        $this->phones()->save($phone);
        // ставит если нет главного
        if (! $this->main_phone_id) {
            $this->mainPhone = $phone;
            $this->save();
        }
    }

    /**
     *Удаляет телефон, меняет главный телефон если надо.
     */
    public function deletePhone($id)
    {
        Phone::destroy($id);

        if (! $this->mainPhone && $this->phones()->exists()) { // $this->mainPhone &&
            $this->mainPhone = $this->phones()->first();
            $this->save();
        }
    }

    /**
     * Получить социальные аккаунты.
     */
    public function socAccounts()
    {
        // , 'socialite_provider_user', 'user_id', 'provider_id'
        return $this->belongsToMany(SocialiteProvider::class)
            ->using(SocialiteProviderUser::class)
            ->withTimestamps();
    }

    /**
     * Почтовы еадреса.
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Почтовы еадреса.
     */
    public function phones()
    {
        return $this->hasMany(Phone::class);
    }

    /**
     * История изменений пароля.
     */
    public function passwordsHistory()
    {
        return $this->hasMany(UserPasswordHistroy::class);
    }

    /**
     * Пользователь по нику.
     */
    public function scopeOfNickname($query, $nickname)
    {
        return $query->where('nickname', $nickname);
    }

    /**
     * Пользователь по почте.
     */
    public function scopeOfEmail($query, $email)
    {
        $query->whereHas('emails', function ($relationQuery) use ($email) {
            $relationQuery->where('email', $email);
        })->get();
    }

    /**
     * Это перезапись Illuminate/Auth/Passwords/CanResetPassword.php
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        return $this->emailForResetPassword; // $this->mainEmail;
    }

    public function setEmailForResetPassword($value)
    {
        $this->emailForResetPassword = $value;
    }
}
