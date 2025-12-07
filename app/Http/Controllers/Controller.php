<?php

namespace App\Http\Controllers;

use App\Traits\ResponseTrait;
use App\Traits\ValidationTrait;

abstract class Controller
{
    use ResponseTrait, ValidationTrait;
}
