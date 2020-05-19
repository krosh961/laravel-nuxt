<?php

namespace App;

use App\Traits\BaseModelTimezones;
use Illuminate\Database\Eloquent\Model as Eloquent;

class BaseModel extends Eloquent
{
    use BaseModelTimezones;
}
