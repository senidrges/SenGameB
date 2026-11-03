<?php

namespace App\Services\Push;

use RuntimeException;

/**
 * Firebase no longer knows the device token (app uninstalled or data cleared).
 */
class FcmTokenUnregistered extends RuntimeException {}
