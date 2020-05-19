<?php

namespace App;

use App\Traits\BaseModelTimezones;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BaseModelPivot extends Pivot
{
    use BaseModelTimezones;
}
