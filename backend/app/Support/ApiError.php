<?php

namespace App\Support;

class ApiError
{
    public const BAD_REQUEST = 40001;
    public const UNAUTHENTICATED = 40101;
    public const FORBIDDEN = 40301;
    public const NOT_FOUND = 40401;
    public const VALIDATION_FAILED = 42201;
    public const LOGIN_FAILED = 40102;
    public const USER_DISABLED = 40302;
    public const SERVER_ERROR = 50001;
}
